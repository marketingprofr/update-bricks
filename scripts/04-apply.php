/**
 * PHASE 4 : APPLICATION DES RÉAFFECTATIONS ET SUPPRESSION
 * /!\ CE SCRIPT MODIFIE LA BASE DE DONNÉES /!\
 *
 * Workflow :
 *   1. Vérifie que le backup a été fait (03-backup.php)
 *   2. Ajoute les catégories L2 aux posts concernés (SQL direct)
 *   3. Retire les catégories profondes des posts (SQL direct)
 *   4. Supprime les catégories profondes (wp_delete_term)
 *   5. Recalcule les compteurs
 *
 * Copier-coller dans WPCodeBox et exécuter.
 */

global $wpdb;

// ─── CONFIGURATION ──────────────────────────────────────────
// Mettre à false pour exécuter réellement. Laisser à true pour
// voir ce qui serait fait sans toucher à la base.
$DRY_RUN = true;

// Nombre de catégories traitées par lot pour wp_delete_term
$BATCH_SIZE = 200;
// ─────────────────────────────────────────────────────────────

set_time_limit(0);
if (!$DRY_RUN) {
    wp_defer_term_counting(true);
}

// ─── Vérification du backup ─────────────────────────────────
$backup_path = get_transient('category_cleanup_backup_done');
if (!$backup_path) {
    echo "<div style='padding:16px;background:#ffebee;border:1px solid #f44336;border-radius:4px;margin:20px;'>"
        . "<strong>Backup non detecte.</strong> Executez d'abord 03-backup.php et telechargez le fichier SQL."
        . "</div>";
    return;
}

// ─── Chargement et arbre ────────────────────────────────────
$cats = $wpdb->get_results("
    SELECT t.term_id, t.name, t.slug,
           tt.term_taxonomy_id, tt.parent, tt.count
    FROM {$wpdb->terms} t
    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'category'
", ARRAY_A);

$by_id = [];
foreach ($cats as $c) {
    $c['term_id']          = (int) $c['term_id'];
    $c['term_taxonomy_id'] = (int) $c['term_taxonomy_id'];
    $c['parent']           = (int) $c['parent'];
    $c['count']            = (int) $c['count'];
    $by_id[$c['term_id']]  = $c;
}

$depths = [];
function calc_depth($id, &$by_id, &$depths, $visited = []) {
    if (isset($depths[$id])) return $depths[$id];
    if (!isset($by_id[$id]) || $by_id[$id]['parent'] === 0) return $depths[$id] = 1;
    if (in_array($id, $visited)) return $depths[$id] = 1;
    $visited[] = $id;
    return $depths[$id] = 1 + calc_depth($by_id[$id]['parent'], $by_id, $depths, $visited);
}
foreach ($by_id as $id => $c) calc_depth($id, $by_id, $depths);

function ancestor_l2($id, &$by_id, &$depths) {
    if ($depths[$id] <= 2) return $id;
    $cur = $id;
    while (isset($depths[$cur]) && $depths[$cur] > 2) $cur = $by_id[$cur]['parent'];
    return $cur;
}

// Catégorie par défaut de WordPress (ne pas supprimer)
$default_cat = (int) get_option('default_category', 1);

// ─── Identifier les catégories à supprimer ──────────────────
$deep_cats = [];
foreach ($by_id as $id => $c) {
    if ($depths[$id] <= 2) continue;
    if ($id === $default_cat) continue;

    $anc = ancestor_l2($id, $by_id, $depths);
    if (!isset($by_id[$anc])) continue;

    $deep_cats[] = [
        'term_id'       => $id,
        'ttid'          => $c['term_taxonomy_id'],
        'name'          => $c['name'],
        'depth'         => $depths[$id],
        'ancestor_id'   => $anc,
        'ancestor_name' => $by_id[$anc]['name'],
        'ancestor_ttid' => $by_id[$anc]['term_taxonomy_id'],
    ];
}

if (empty($deep_cats)) {
    echo '<p>Aucune categorie de niveau 3+ a supprimer. Rien a faire.</p>';
    return;
}

// Trier du plus profond au moins profond (pour suppression)
usort($deep_cats, function ($a, $b) { return $b['depth'] - $a['depth']; });

// Grouper par ancêtre L2 (pour réaffectation bulk)
$groups = []; // ancestor_id => [deep_ttids...]
foreach ($deep_cats as $dc) {
    $groups[$dc['ancestor_id']]['ancestor_ttid'] = $dc['ancestor_ttid'];
    $groups[$dc['ancestor_id']]['ancestor_name'] = $dc['ancestor_name'];
    $groups[$dc['ancestor_id']]['deep_ttids'][]  = $dc['ttid'];
}

// ─── Sortie ─────────────────────────────────────────────────
$css = 'font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1200px;margin:20px auto;padding:20px;';
echo "<div style='{$css}'>";

$mode_label = $DRY_RUN ? 'MODE SIMULATION (dry run)' : 'EXECUTION REELLE';
$mode_bg    = $DRY_RUN ? '#fff3cd' : '#ffebee';
echo "<h1>Application du nettoyage — {$mode_label}</h1>";
echo "<div style='padding:12px;background:{$mode_bg};border-radius:4px;margin-bottom:20px;'>";
if ($DRY_RUN) {
    echo "Aucune modification ne sera faite. Passez <code>\$DRY_RUN = false</code> pour executer.";
} else {
    echo "<strong>Les modifications sont irreversibles (hors restauration du backup).</strong>";
}
echo "</div>";

echo "<p>" . count($deep_cats) . " categories a supprimer, reparties en " . count($groups) . " groupes L2.</p>";

// ─── ÉTAPE 1 : Réaffectation des posts ──────────────────────
echo '<h2>Etape 1 : Reaffectation des posts</h2>';

$total_inserted = 0;
foreach ($groups as $anc_id => $group) {
    $anc_ttid      = $group['ancestor_ttid'];
    $deep_ttids    = $group['deep_ttids'];
    $deep_ttid_str = implode(',', array_map('intval', $deep_ttids));

    // Combien de posts seront affectés ?
    $post_count = (int) $wpdb->get_var("
        SELECT COUNT(DISTINCT object_id)
        FROM {$wpdb->term_relationships}
        WHERE term_taxonomy_id IN ({$deep_ttid_str})
    ");

    // Combien ont déjà l'ancêtre L2 ?
    $already_count = 0;
    if ($post_count > 0) {
        $already_count = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT tr1.object_id)
            FROM {$wpdb->term_relationships} tr1
            WHERE tr1.term_taxonomy_id IN ({$deep_ttid_str})
            AND EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr2
                WHERE tr2.object_id = tr1.object_id
                AND tr2.term_taxonomy_id = {$anc_ttid}
            )
        ");
    }

    $to_insert = $post_count - $already_count;

    echo "<p>L2 <strong>{$group['ancestor_name']}</strong> (ID {$anc_id}) : "
        . "{$post_count} posts, {$already_count} ont deja la L2, <strong>{$to_insert} a ajouter</strong></p>";

    if (!$DRY_RUN && $to_insert > 0) {
        $inserted = $wpdb->query("
            INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order)
            SELECT DISTINCT tr.object_id, {$anc_ttid}, 0
            FROM {$wpdb->term_relationships} tr
            WHERE tr.term_taxonomy_id IN ({$deep_ttid_str})
        ");
        $total_inserted += (int) $inserted;
    } else {
        $total_inserted += $to_insert;
    }
}

echo "<p><strong>Total : {$total_inserted} relations ajoutees.</strong></p>";

// ─── ÉTAPE 2 : Suppression des relations profondes ──────────
echo '<h2>Etape 2 : Suppression des relations profondes</h2>';

$all_deep_ttids = array_column($deep_cats, 'ttid');
$all_deep_str   = implode(',', array_map('intval', $all_deep_ttids));

$rel_count = (int) $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->term_relationships}
    WHERE term_taxonomy_id IN ({$all_deep_str})
");

echo "<p>{$rel_count} relations a supprimer.</p>";

if (!$DRY_RUN && $rel_count > 0) {
    $deleted = $wpdb->query("
        DELETE FROM {$wpdb->term_relationships}
        WHERE term_taxonomy_id IN ({$all_deep_str})
    ");
    echo "<p><strong>{$deleted} relations supprimees.</strong></p>";
}

// ─── ÉTAPE 3 : Suppression des catégories ───────────────────
echo '<h2>Etape 3 : Suppression des categories</h2>';

$deleted_terms = 0;
$errors        = [];
$batches       = array_chunk($deep_cats, $BATCH_SIZE);

foreach ($batches as $bi => $batch) {
    $batch_num = $bi + 1;
    $total_batches = count($batches);
    echo "<p>Lot {$batch_num}/{$total_batches} (" . count($batch) . " categories)...</p>";

    if (!$DRY_RUN) {
        ob_flush(); flush();
    }

    foreach ($batch as $dc) {
        if ($DRY_RUN) {
            $deleted_terms++;
            continue;
        }

        $result = wp_delete_term($dc['term_id'], 'category');
        if (is_wp_error($result)) {
            $errors[] = "Erreur sur {$dc['name']} (ID {$dc['term_id']}): " . $result->get_error_message();
        } elseif ($result === false) {
            $errors[] = "Impossible de supprimer {$dc['name']} (ID {$dc['term_id']}): categorie par defaut ?";
        } else {
            $deleted_terms++;
        }
    }
}

echo "<p><strong>{$deleted_terms} categories supprimees.</strong></p>";

if (!empty($errors)) {
    echo '<h3>Erreurs</h3><ul>';
    foreach ($errors as $e) echo "<li style='color:red;'>{$e}</li>";
    echo '</ul>';
}

// ─── ÉTAPE 4 : Recalcul des compteurs ───────────────────────
echo '<h2>Etape 4 : Recalcul des compteurs et caches</h2>';

if (!$DRY_RUN) {
    wp_defer_term_counting(false);

    $remaining_tt_ids = $wpdb->get_col("
        SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy}
        WHERE taxonomy = 'category'
    ");

    if (!empty($remaining_tt_ids)) {
        wp_update_term_count_now($remaining_tt_ids, 'category');
    }

    wp_cache_flush();
    delete_transient('category_cleanup_backup_done');

    echo '<p>Compteurs recalcules, caches purges.</p>';
} else {
    echo '<p><em>(simulation : pas de recalcul)</em></p>';
}

// ─── Vérification rapide ────────────────────────────────────
echo '<h2>Verification rapide</h2>';

if (!$DRY_RUN) {
    $remaining_deep = (int) $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$wpdb->term_taxonomy} tt
        WHERE tt.taxonomy = 'category'
        AND tt.parent != 0
        AND tt.parent IN (
            SELECT tt2.term_id FROM {$wpdb->term_taxonomy} tt2
            WHERE tt2.taxonomy = 'category' AND tt2.parent != 0
        )
    ");
    echo $remaining_deep === 0
        ? "<p style='color:green;'>Aucune categorie de niveau 3+ restante.</p>"
        : "<p style='color:red;'>{$remaining_deep} categories de niveau 3+ encore presentes. Verifiez avec 05-verify.php.</p>";

    $orphan_posts = (int) $wpdb->get_var("
        SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        WHERE p.post_status = 'publish'
        AND p.post_type IN ('post', 'page')
        AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tr.object_id = p.ID AND tt.taxonomy = 'category'
        )
    ");
    echo $orphan_posts === 0
        ? "<p style='color:green;'>Aucun post/page publie sans categorie.</p>"
        : "<p style='color:orange;'>{$orphan_posts} posts/pages publies sans categorie (ils recevront la categorie par defaut).</p>";
} else {
    echo '<p><em>(simulation : verification non disponible)</em></p>';
}

// ─── Résumé final ───────────────────────────────────────────
echo '<h2>Resume</h2>';
echo '<ul>'
    . "<li>Relations ajoutees : {$total_inserted}</li>"
    . "<li>Relations supprimees : " . ($DRY_RUN ? $rel_count . ' (prevision)' : $rel_count) . "</li>"
    . "<li>Categories supprimees : {$deleted_terms}</li>"
    . "<li>Erreurs : " . count($errors) . "</li>"
    . '</ul>';

if ($DRY_RUN) {
    echo "<p style='padding:12px;background:#e3f2fd;border:1px solid #2196f3;border-radius:4px;'>"
        . "Pour executer reellement, modifiez <code>\$DRY_RUN = false;</code> en haut du script et relancez.</p>";
} else {
    echo "<p style='padding:12px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;'>"
        . "Operation terminee. Executez 05-verify.php pour une verification complete, "
        . "puis pensez a reindexer votre plugin SEO si necessaire.</p>";
}

echo '</div>';
