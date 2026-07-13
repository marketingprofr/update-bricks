/**
 * PHASE 4 : APPLICATION DES RÉAFFECTATIONS ET SUPPRESSION
 * /!\ CE SCRIPT MODIFIE LA BASE DE DONNÉES /!\
 *
 * Workflow :
 *   1. Vérifie que le backup a été fait (03-backup.php)
 *   2. Ajoute les catégories L2 aux posts concernés (SQL direct)
 *   3. Retire les catégories profondes des posts (SQL direct)
 *   4. Supprime les catégories profondes (wp_delete_term, du plus profond
 *      au moins profond)
 *   5. Recalcule les compteurs et purge les caches
 *
 * Le script est relançable sans danger : en cas de timeout serveur,
 * relancez la même URL, il reprend là où il s'est arrêté.
 *
 * Utilisation :
 *   1. Coller ce code dans un snippet PHP WPCodeBox (à la suite de la balise <?php)
 *   2. Activer le snippet
 *   3. Visiter : https://votre-site.fr/?catcleanup=apply  (connecté en admin)
 *      → s'exécute en SIMULATION tant que $catcleanup_dry_run vaut true
 *   4. Passer $catcleanup_dry_run à false ci-dessous, sauvegarder, revisiter l'URL
 *   5. Désactiver le snippet après usage
 */

// Marqueur de chargement pour le diagnostic ?catcleanup=ping
$GLOBALS['catcleanup_loaded'][] = '04-apply';

// ─── Outils partagés : contrôle d'accès + diagnostic ─────────────────
if (!function_exists('catcleanup_require_admin')) {
    function catcleanup_require_admin() {
        if (current_user_can('manage_options')) return true;
        status_header(403);
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo "catcleanup : acces refuse.\n"
            . "Vous n'etes pas reconnu comme administrateur sur cette URL.\n"
            . "- Connectez-vous a wp-admin dans CE navigateur\n"
            . "- Utilisez EXACTEMENT le meme domaine que wp-admin (www/non-www, https)\n"
            . "- Ex : si l'admin est sur https://www.site.fr/wp-admin, utilisez https://www.site.fr/?catcleanup=...\n";
        exit;
    }
}

if (!function_exists('catcleanup_ping_handler')) {
    function catcleanup_ping_handler() {
        if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'ping') return;
        catcleanup_require_admin();
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        $loaded = isset($GLOBALS['catcleanup_loaded']) ? (array) $GLOBALS['catcleanup_loaded'] : [];
        sort($loaded);
        echo "Diagnostic catcleanup\n=====================\n";
        echo 'Snippets charges dans cette requete (' . count($loaded) . ") :\n";
        foreach ($loaded as $l) { echo "- {$l}\n"; }
        echo "\nSi un snippet actif dans WPCodeBox n'apparait PAS ci-dessus :\n";
        echo "- verifiez qu'il est bien ACTIF (bouton on/off)\n";
        echo "- verifiez son mode d'execution : il doit tourner PARTOUT (frontend + admin), pas en 'admin only'\n";
        echo "\nURLs disponibles : ?catcleanup=analyze | preview | backup | apply | verify\n";
        exit;
    }
    if (did_action('init')) {
        catcleanup_ping_handler();
    } else {
        add_action('init', 'catcleanup_ping_handler', 0);
    }
}


// ─── CONFIGURATION ────────────────────────────────────────────────────
// true  = simulation (aucune modification)
// false = exécution réelle
$catcleanup_dry_run = true;

// Nombre de catégories supprimées par lot (affichage de progression)
$catcleanup_batch_size = 200;
// ──────────────────────────────────────────────────────────────────────

// ─── Fonctions partagées (protégées contre la redéclaration) ─────────
if (!function_exists('catcleanup_load_categories')) {
    function catcleanup_load_categories() {
        global $wpdb;
        $cats = $wpdb->get_results("
            SELECT t.term_id, t.name, t.slug,
                   tt.term_taxonomy_id, tt.parent, tt.count
            FROM {$wpdb->terms} t
            JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy = 'category'
        ", ARRAY_A);
        $by_id = [];
        foreach ((array) $cats as $c) {
            $c['term_id']          = (int) $c['term_id'];
            $c['term_taxonomy_id'] = (int) $c['term_taxonomy_id'];
            $c['parent']           = (int) $c['parent'];
            $c['count']            = (int) $c['count'];
            $by_id[$c['term_id']]  = $c;
        }
        return $by_id;
    }
}

if (!function_exists('catcleanup_calc_depth')) {
    function catcleanup_calc_depth($id, &$by_id, &$depths, $visited = []) {
        if (!isset($by_id[$id])) return 0;
        if (isset($depths[$id])) return $depths[$id];
        if ($by_id[$id]['parent'] === 0) return $depths[$id] = 1;
        if (in_array($id, $visited)) return $depths[$id] = 1;
        $visited[] = $id;
        $d = 1 + catcleanup_calc_depth($by_id[$id]['parent'], $by_id, $depths, $visited);
        // Si un cycle est passé par cet id pendant la récursion, sa profondeur
        // a déjà été fixée à 1 (racine) : on la garde pour ne jamais supprimer
        // un membre de cycle.
        if (isset($depths[$id])) return $depths[$id];
        return $depths[$id] = $d;
    }
}

if (!function_exists('catcleanup_ancestor_l2')) {
    function catcleanup_ancestor_l2($id, &$by_id, &$depths) {
        $cur = $id;
        while (isset($by_id[$cur]) && isset($depths[$cur]) && $depths[$cur] > 2) {
            $cur = $by_id[$cur]['parent'];
        }
        return $cur;
    }
}

// ─── Exécution à la demande uniquement ────────────────────────────────
$catcleanup_apply = function () use ($catcleanup_dry_run, $catcleanup_batch_size) {
    if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'apply') return;
    catcleanup_require_admin();

    global $wpdb;
    $DRY_RUN = (bool) $catcleanup_dry_run;

    if (function_exists('set_time_limit')) @set_time_limit(0);
    ignore_user_abort(true);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    // ─── Vérification du backup ─────────────────────────────
    $backup_path = get_transient('category_cleanup_backup_done');
    if (!$backup_path) {
        echo "<div style='padding:16px;background:#ffebee;border:1px solid #f44336;border-radius:4px;margin:20px;'>"
            . '<strong>Backup non detecte.</strong> Executez d\'abord 03-backup.php (?catcleanup=backup) et telechargez le fichier SQL.'
            . '</div>';
        exit;
    }

    if (!$DRY_RUN) {
        wp_defer_term_counting(true);
    }

    // ─── Chargement et arbre ────────────────────────────────
    $by_id = catcleanup_load_categories();
    if (empty($by_id)) {
        echo '<p>Aucune categorie trouvee.</p>';
        exit;
    }

    $depths = [];
    foreach ($by_id as $id => $c) {
        catcleanup_calc_depth($id, $by_id, $depths);
    }

    $default_cat = (int) get_option('default_category');

    // ─── Catégories à supprimer ─────────────────────────────
    $deep_cats = [];
    foreach ($by_id as $id => $c) {
        if ($depths[$id] <= 2) continue;
        if ($id === $default_cat) continue;
        $anc = catcleanup_ancestor_l2($id, $by_id, $depths);
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
        echo '<p>Aucune categorie de niveau 3+ a supprimer. Rien a faire (deja applique ?).</p>';
        exit;
    }

    // Trier du plus profond au moins profond (pour suppression)
    usort($deep_cats, function ($a, $b) { return $b['depth'] - $a['depth']; });

    // Grouper par ancêtre L2 (pour réaffectation bulk)
    $groups = [];
    foreach ($deep_cats as $dc) {
        $groups[$dc['ancestor_id']]['ancestor_ttid'] = $dc['ancestor_ttid'];
        $groups[$dc['ancestor_id']]['ancestor_name'] = $dc['ancestor_name'];
        $groups[$dc['ancestor_id']]['deep_ttids'][]  = $dc['ttid'];
    }

    $flush_output = function () {
        if (ob_get_level() > 0) { @ob_flush(); }
        flush();
    };

    // ─── En-tête ────────────────────────────────────────────
    echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1200px;margin:20px auto;padding:20px;'>";

    $mode_label = $DRY_RUN ? 'MODE SIMULATION (dry run)' : 'EXECUTION REELLE';
    $mode_bg    = $DRY_RUN ? '#fff3cd' : '#ffebee';
    echo "<h1>Application du nettoyage &mdash; {$mode_label}</h1>";
    echo "<div style='padding:12px;background:{$mode_bg};border-radius:4px;margin-bottom:20px;'>";
    if ($DRY_RUN) {
        echo 'Aucune modification ne sera faite. Passez <code>$catcleanup_dry_run = false;</code> dans le snippet pour executer.';
    } else {
        echo '<strong>Les modifications sont en cours. Ne fermez pas cette page.</strong>';
    }
    echo '</div>';

    echo '<p>' . count($deep_cats) . ' categories a supprimer, reparties en ' . count($groups) . ' groupes L2.</p>';
    $flush_output();

    // ─── ÉTAPE 1 : Réaffectation des posts ──────────────────
    echo '<h2>Etape 1 : Reaffectation des posts</h2>';

    $total_inserted = 0;
    foreach ($groups as $anc_id => $group) {
        $anc_ttid      = (int) $group['ancestor_ttid'];
        $deep_ttid_str = implode(',', array_map('intval', $group['deep_ttids']));

        $post_count = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT object_id)
            FROM {$wpdb->term_relationships}
            WHERE term_taxonomy_id IN ({$deep_ttid_str})
        ");

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

        echo '<p>L2 <strong>' . esc_html($group['ancestor_name']) . "</strong> (ID {$anc_id}) : "
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
            $total_inserted += max(0, $to_insert);
        }
    }

    echo "<p><strong>Total : {$total_inserted} relations ajoutees" . ($DRY_RUN ? ' (prevision)' : '') . '.</strong></p>';
    $flush_output();

    // ─── ÉTAPE 2 : Suppression des relations profondes ──────
    echo '<h2>Etape 2 : Suppression des relations profondes</h2>';

    $all_deep_str = implode(',', array_map('intval', array_column($deep_cats, 'ttid')));

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
    $flush_output();

    // ─── ÉTAPE 3 : Suppression des catégories ───────────────
    echo '<h2>Etape 3 : Suppression des categories</h2>';

    $deleted_terms = 0;
    $errors        = [];
    $batches       = array_chunk($deep_cats, max(1, (int) $catcleanup_batch_size));
    $total_batches = count($batches);

    foreach ($batches as $bi => $batch) {
        $batch_num = $bi + 1;
        echo "<p>Lot {$batch_num}/{$total_batches} (" . count($batch) . ' categories)...</p>';
        $flush_output();

        foreach ($batch as $dc) {
            if ($DRY_RUN) {
                $deleted_terms++;
                continue;
            }

            $result = wp_delete_term($dc['term_id'], 'category');
            if (is_wp_error($result)) {
                $errors[] = 'Erreur sur ' . $dc['name'] . ' (ID ' . $dc['term_id'] . '): ' . $result->get_error_message();
            } elseif ($result === false || $result === 0) {
                $errors[] = 'Impossible de supprimer ' . $dc['name'] . ' (ID ' . $dc['term_id'] . ')';
            } else {
                $deleted_terms++;
            }
        }
    }

    echo "<p><strong>{$deleted_terms} categories supprimees" . ($DRY_RUN ? ' (prevision)' : '') . '.</strong></p>';

    if (!empty($errors)) {
        echo '<h3>Erreurs</h3><ul>';
        foreach ($errors as $e) {
            echo "<li style='color:red;'>" . esc_html($e) . '</li>';
        }
        echo '</ul>';
    }
    $flush_output();

    // ─── ÉTAPE 4 : Recalcul des compteurs ───────────────────
    echo '<h2>Etape 4 : Recalcul des compteurs et caches</h2>';

    if (!$DRY_RUN) {
        wp_defer_term_counting(false);

        $remaining_tt_ids = array_map('intval', (array) $wpdb->get_col("
            SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'category'
        "));

        if (!empty($remaining_tt_ids)) {
            wp_update_term_count_now($remaining_tt_ids, 'category');
        }

        clean_taxonomy_cache('category');
        wp_cache_flush();
        delete_transient('category_cleanup_backup_done');

        echo '<p>Compteurs recalcules, caches purges.</p>';
    } else {
        echo '<p><em>(simulation : pas de recalcul)</em></p>';
    }

    // ─── Vérification rapide ────────────────────────────────
    echo '<h2>Verification rapide</h2>';

    if (!$DRY_RUN) {
        $remaining_deep = (int) $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->term_taxonomy} tt
            WHERE tt.taxonomy = 'category'
            AND tt.parent != 0
            AND tt.parent IN (
                SELECT tt2.term_id FROM (
                    SELECT term_id, parent FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'category'
                ) tt2
                WHERE tt2.parent != 0
            )
        ");
        echo $remaining_deep === 0
            ? "<p style='color:green;'>Aucune categorie de niveau 3+ restante.</p>"
            : "<p style='color:orange;'>{$remaining_deep} categorie(s) de niveau 3+ encore presente(s) "
              . '(categorie par defaut ou cas particuliers ignores). Verifiez avec 05-verify.php.</p>';
    } else {
        echo '<p><em>(simulation : verification non disponible)</em></p>';
    }

    // ─── Résumé final ───────────────────────────────────────
    echo '<h2>Resume</h2>';
    echo '<ul>'
        . "<li>Relations ajoutees : {$total_inserted}</li>"
        . "<li>Relations supprimees : {$rel_count}" . ($DRY_RUN ? ' (prevision)' : '') . '</li>'
        . "<li>Categories supprimees : {$deleted_terms}</li>"
        . '<li>Erreurs : ' . count($errors) . '</li>'
        . '</ul>';

    if ($DRY_RUN) {
        echo "<p style='padding:12px;background:#e3f2fd;border:1px solid #2196f3;border-radius:4px;'>"
            . 'Pour executer reellement, modifiez <code>$catcleanup_dry_run = false;</code> en haut du snippet et revisitez cette URL.</p>';
    } else {
        echo "<p style='padding:12px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;'>"
            . 'Operation terminee. Executez 05-verify.php (?catcleanup=verify) pour une verification complete, '
            . 'puis reindexez votre plugin SEO et videz le cache du site.</p>';
    }

    echo '</div>';
    exit;
};

if (did_action('init')) {
    $catcleanup_apply();
} else {
    add_action('init', $catcleanup_apply, 0);
}
