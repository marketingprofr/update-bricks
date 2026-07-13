/**
 * PHASE 4 : APPLICATION DES RÉAFFECTATIONS ET SUPPRESSION
 * /!\ CE SCRIPT MODIFIE LA BASE DE DONNÉES /!\
 *
 * Fonctionnement PAR LOTS : chaque requête traite un nombre limité de
 * suppressions (300 par défaut, ~20 s max), puis la page se recharge
 * automatiquement et continue — jusqu'à la fin. C'est ce qui évite le
 * timeout serveur quand il y a des milliers de catégories : chaque
 * wp_delete_term déclenche les hooks des plugins (SEO, cache...), on ne
 * peut pas tout faire en une seule requête HTTP.
 *
 * Ordre des opérations (garantit zéro perte même en cas d'interruption) :
 *   1. Ajout des catégories L2 aux posts concernés (SQL direct)
 *   2. Retrait des relations vers les catégories profondes (SQL par lots)
 *   3. Suppression des catégories profondes par lots (wp_delete_term)
 *   4. Quand tout est supprimé : recalcul des compteurs + purge caches
 *
 * L'état est recalculé à chaque requête : le script REPREND toujours là où
 * il s'est arrêté, on peut le relancer sans danger après un crash/timeout.
 * Un verrou empêche deux exécutions simultanées.
 *
 * Utilisation :
 *   1. Coller ce code dans un snippet PHP WPCodeBox (à la suite de la balise <?php)
 *   2. Activer le snippet
 *   3. Visiter : https://votre-site.fr/?catcleanup=apply  (connecté en admin)
 *      → s'exécute en SIMULATION tant que $catcleanup_dry_run vaut true
 *   4. Passer $catcleanup_dry_run à false ci-dessous, sauvegarder, revisiter l'URL
 *   5. Laisser la page se recharger toute seule jusqu'au message final
 *   6. Désactiver le snippet après usage
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

// Suppressions de catégories max par requête (la page se recharge ensuite)
$catcleanup_batch_size = 300;

// Budget temps max (secondes) de suppression par requête
$catcleanup_time_budget = 20;
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
$catcleanup_apply = function () use ($catcleanup_dry_run, $catcleanup_batch_size, $catcleanup_time_budget) {
    if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'apply') return;
    catcleanup_require_admin();

    global $wpdb;
    $DRY_RUN     = (bool) $catcleanup_dry_run;
    $BATCH_SIZE  = max(1, (int) $catcleanup_batch_size);
    $TIME_BUDGET = max(5, (int) $catcleanup_time_budget);

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

    // ─── Verrou anti double-exécution (exécution réelle uniquement) ──
    // Un navigateur peut relancer automatiquement une requête GET qui a
    // expiré : sans verrou, deux traitements tourneraient en parallèle.
    $LOCK_NAME = 'catcleanup_apply_lock';
    if (!$DRY_RUN) {
        $now      = time();
        $acquired = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
            $LOCK_NAME, (string) $now
        ));
        if (!$acquired) {
            $held_at = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $LOCK_NAME
            ));
            $age = $now - $held_at;
            if ($age < 600) {
                echo "<div style='padding:16px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;margin:20px;'>"
                    . "<strong>Un traitement est deja en cours</strong> (demarre il y a {$age} s). "
                    . 'Attendez la fin ou reessayez dans quelques minutes. '
                    . "<script>setTimeout(function(){location.reload();}, 15000);</script>"
                    . 'Cette page se rechargera automatiquement dans 15 s.'
                    . '</div>';
                exit;
            }
            // Verrou périmé (crash précédent) : on le reprend
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
                (string) $now, $LOCK_NAME
            ));
        }
    }
    $release_lock = function () use ($wpdb, $DRY_RUN, $LOCK_NAME) {
        if (!$DRY_RUN) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name = %s", $LOCK_NAME
            ));
        }
    };

    if (!$DRY_RUN) {
        wp_defer_term_counting(true);
    }

    // ─── Chargement et arbre (état recalculé à chaque requête) ──────
    $by_id = catcleanup_load_categories();
    if (empty($by_id)) {
        echo '<p>Aucune categorie trouvee.</p>';
        $release_lock();
        exit;
    }

    $depths = [];
    foreach ($by_id as $id => $c) {
        catcleanup_calc_depth($id, $by_id, $depths);
    }

    $default_cat = (int) get_option('default_category');

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

    $flush_output = function () {
        if (ob_get_level() > 0) { @ob_flush(); }
        flush();
    };

    // ─── En-tête ────────────────────────────────────────────
    echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1200px;margin:20px auto;padding:20px;'>";

    $mode_label = $DRY_RUN ? 'MODE SIMULATION (dry run)' : 'EXECUTION REELLE (par lots)';
    $mode_bg    = $DRY_RUN ? '#fff3cd' : '#ffebee';
    echo "<h1>Application du nettoyage &mdash; {$mode_label}</h1>";
    echo "<div style='padding:12px;background:{$mode_bg};border-radius:4px;margin-bottom:20px;'>";
    if ($DRY_RUN) {
        echo 'Aucune modification ne sera faite. Passez <code>$catcleanup_dry_run = false;</code> dans le snippet pour executer. '
            . "L'execution reelle traitera {$BATCH_SIZE} suppressions max par requete, avec rechargement automatique jusqu'a la fin.";
    } else {
        echo '<strong>Traitement par lots en cours. Laissez cette page ouverte, elle se recharge toute seule.</strong>';
    }
    echo '</div>';

    // ─── Fin du traitement ? ────────────────────────────────
    if (empty($deep_cats)) {
        echo '<h2>Finalisation</h2>';
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
            echo "<div style='padding:16px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;'>"
                . '<strong>Operation terminee.</strong> Toutes les categories de niveau 3+ ont ete supprimees. '
                . 'Executez 05-verify.php (?catcleanup=verify) pour la verification complete, '
                . 'puis reindexez votre plugin SEO et videz le cache du site.'
                . '</div>';
        } else {
            echo '<p>Aucune categorie de niveau 3+ a supprimer. Rien a faire (deja applique ?).</p>';
        }
        $release_lock();
        echo '</div>';
        exit;
    }

    usort($deep_cats, function ($a, $b) { return $b['depth'] - $a['depth']; });

    $all_deep_ttids = array_map('intval', array_column($deep_cats, 'ttid'));
    $all_deep_str   = implode(',', $all_deep_ttids);
    $total_deep     = count($deep_cats);

    echo "<p><strong>Etat actuel :</strong> {$total_deep} categories de niveau 3+ restantes.</p>";
    $flush_output();

    // Combien de relations profondes restent ? (0 = étapes 1-2 déjà faites)
    $deep_rels = (int) $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$all_deep_str})
    ");

    // ─── ÉTAPES 1-2 : Réaffectation puis retrait des relations ──────
    if ($deep_rels > 0) {
        echo '<h2>Etape 1 : Reaffectation des posts vers les categories L2</h2>';

        $groups = [];
        foreach ($deep_cats as $dc) {
            $groups[$dc['ancestor_id']]['ancestor_ttid'] = $dc['ancestor_ttid'];
            $groups[$dc['ancestor_id']]['ancestor_name'] = $dc['ancestor_name'];
            $groups[$dc['ancestor_id']]['deep_ttids'][]  = $dc['ttid'];
        }

        $total_inserted = 0;
        $group_lines    = 0;
        foreach ($groups as $anc_id => $group) {
            $anc_ttid      = (int) $group['ancestor_ttid'];
            $deep_ttid_str = implode(',', array_map('intval', $group['deep_ttids']));

            $post_count = (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT object_id)
                FROM {$wpdb->term_relationships}
                WHERE term_taxonomy_id IN ({$deep_ttid_str})
            ");
            if ($post_count === 0) continue;

            if (!$DRY_RUN) {
                $inserted = $wpdb->query("
                    INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order)
                    SELECT DISTINCT tr.object_id, {$anc_ttid}, 0
                    FROM {$wpdb->term_relationships} tr
                    WHERE tr.term_taxonomy_id IN ({$deep_ttid_str})
                ");
                $total_inserted += (int) $inserted;
            } else {
                $total_inserted += $post_count; // majorant en simulation
            }

            if (++$group_lines <= 30) {
                echo '<p>L2 <strong>' . esc_html($group['ancestor_name']) . "</strong> (ID {$anc_id}) : {$post_count} posts concernes</p>";
            }
        }
        if ($group_lines > 30) {
            echo '<p><em>... et ' . ($group_lines - 30) . ' autres groupes L2.</em></p>';
        }
        echo "<p><strong>Relations L2 ajoutees : {$total_inserted}" . ($DRY_RUN ? ' max (simulation)' : '') . '.</strong></p>';
        $flush_output();

        echo '<h2>Etape 2 : Retrait des relations vers les categories profondes</h2>';
        echo "<p>{$deep_rels} relations a retirer.</p>";

        if (!$DRY_RUN) {
            $deleted_rels = 0;
            do {
                $chunk = $wpdb->query("
                    DELETE FROM {$wpdb->term_relationships}
                    WHERE term_taxonomy_id IN ({$all_deep_str})
                    LIMIT 20000
                ");
                $deleted_rels += (int) $chunk;
                $flush_output();
            } while ($chunk > 0);
            echo "<p><strong>{$deleted_rels} relations retirees.</strong></p>";
        }
        $flush_output();
    } else {
        echo '<p><em>Etapes 1-2 (reaffectation et retrait des relations) : deja effectuees, on passe aux suppressions.</em></p>';
    }

    // ─── ÉTAPE 3 : Suppression des catégories (lot borné) ───────────
    echo '<h2>Etape 3 : Suppression des categories (par lots)</h2>';

    $deadline  = time() + $TIME_BUDGET;
    $processed = 0;
    $errors    = [];

    if ($DRY_RUN) {
        echo "<p>{$total_deep} categories seraient supprimees, par lots de {$BATCH_SIZE} et par ordre de profondeur decroissante.</p>";
    } else {
        foreach ($deep_cats as $dc) {
            if ($processed >= $BATCH_SIZE || time() >= $deadline) break;

            try {
                $result = wp_delete_term($dc['term_id'], 'category');
                if (is_wp_error($result)) {
                    $errors[] = $dc['name'] . ' (ID ' . $dc['term_id'] . '): ' . $result->get_error_message();
                } elseif ($result === false || $result === 0) {
                    $errors[] = $dc['name'] . ' (ID ' . $dc['term_id'] . '): suppression refusee';
                }
            } catch (\Throwable $e) {
                // Un hook de plugin a plante sur ce terme : on note et on continue
                $errors[] = $dc['name'] . ' (ID ' . $dc['term_id'] . '): exception ' . $e->getMessage();
            }
            $processed++;
        }

        $remaining = $total_deep - $processed;
        echo "<p><strong>{$processed} categories traitees dans ce lot.</strong> Restant : {$remaining}.</p>";

        if (!empty($errors)) {
            echo '<h3>Erreurs sur ce lot</h3><ul>';
            foreach (array_slice($errors, 0, 20) as $e) {
                echo "<li style='color:red;'>" . esc_html($e) . '</li>';
            }
            if (count($errors) > 20) echo '<li>... et ' . (count($errors) - 20) . ' autres</li>';
            echo '</ul>';
        }

        $release_lock();

        if ($remaining > 0) {
            // Continuation automatique : nouvelle requête, état recalculé
            $pct = $total_deep > 0 ? round(100 * $processed / $total_deep) : 0;
            echo "<div style='padding:16px;background:#e3f2fd;border:1px solid #2196f3;border-radius:4px;margin-top:16px;'>"
                . "<strong>Traitement en cours...</strong> Cette page va se recharger automatiquement dans 2 s "
                . "pour continuer ({$remaining} categories restantes). "
                . "<a href='javascript:location.reload();'>Continuer maintenant</a>"
                . '</div>'
                . "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
            echo '</div>';
            exit;
        }

        // Dernier lot terminé : la finalisation se fera au prochain
        // rechargement (deep_cats sera vide → recalcul compteurs + caches)
        echo "<div style='padding:16px;background:#e3f2fd;border:1px solid #2196f3;border-radius:4px;margin-top:16px;'>"
            . '<strong>Suppressions terminees.</strong> Rechargement pour la finalisation '
            . '(recalcul des compteurs et purge des caches)... '
            . "<a href='javascript:location.reload();'>Finaliser maintenant</a>"
            . '</div>'
            . "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        echo '</div>';
        exit;
    }

    // ─── Résumé simulation ──────────────────────────────────
    $release_lock();
    echo '<h2>Resume (simulation)</h2>';
    echo '<ul>'
        . "<li>Categories a supprimer : {$total_deep}</li>"
        . "<li>Relations profondes a retirer : {$deep_rels}</li>"
        . "<li>Lots necessaires : environ " . max(1, (int) ceil($total_deep / $BATCH_SIZE)) . "</li>"
        . '</ul>';
    echo "<p style='padding:12px;background:#e3f2fd;border:1px solid #2196f3;border-radius:4px;'>"
        . 'Pour executer reellement, modifiez <code>$catcleanup_dry_run = false;</code> en haut du snippet et revisitez cette URL. '
        . 'La page se rechargera automatiquement entre chaque lot : laissez-la ouverte jusqu\'au message final.</p>';
    echo '</div>';
    exit;
};

if (did_action('init')) {
    $catcleanup_apply();
} else {
    add_action('init', $catcleanup_apply, 0);
}
