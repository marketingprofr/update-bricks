/**
 * PHASE 1 : ANALYSE DES CATÉGORIES
 * Mode lecture seule — ne modifie rien.
 *
 * Utilisation :
 *   1. Coller ce code dans un snippet PHP WPCodeBox (à la suite de la balise <?php)
 *   2. Activer le snippet (exécution : partout ou frontend)
 *   3. Visiter : https://votre-site.fr/?catcleanup=analyze  (connecté en admin)
 *   4. Désactiver le snippet après usage
 */

// Marqueur de chargement pour le diagnostic ?catcleanup=ping
$GLOBALS['catcleanup_loaded'][] = '01-analyze';

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
        echo "\nURLs disponibles : ?catcleanup=analyze | preview | backup | apply | verify | remap\n";
        exit;
    }
    if (did_action('init')) {
        catcleanup_ping_handler();
    } else {
        add_action('init', 'catcleanup_ping_handler', 0);
    }
}


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
        if (!isset($by_id[$id])) return 0;             // parent inexistant → l'enfant devient racine
        if (isset($depths[$id])) return $depths[$id];
        if ($by_id[$id]['parent'] === 0) return $depths[$id] = 1;
        if (in_array($id, $visited)) return $depths[$id] = 1; // cycle détecté
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

if (!function_exists('catcleanup_full_path')) {
    function catcleanup_full_path($id, &$by_id) {
        $parts = []; $cur = $id; $visited = [];
        while ($cur !== 0 && isset($by_id[$cur]) && !in_array($cur, $visited)) {
            $visited[] = $cur;
            $parts[]   = $by_id[$cur]['name'];
            $cur       = $by_id[$cur]['parent'];
        }
        return implode(' > ', array_reverse($parts));
    }
}

// ─── Exécution à la demande uniquement ────────────────────────────────
$catcleanup_analyze = function () {
    if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'analyze') return;
    catcleanup_require_admin();

    global $wpdb;
    if (function_exists('set_time_limit')) @set_time_limit(120);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    $by_id = catcleanup_load_categories();
    if (empty($by_id)) {
        echo '<p style="color:red;">Aucune categorie trouvee.</p>';
        exit;
    }

    $depths = [];
    foreach ($by_id as $id => $c) {
        catcleanup_calc_depth($id, $by_id, $depths);
    }

    $default_cat = (int) get_option('default_category');

    // ─── Statistiques et liste des suppressions ──────────────
    $stats_by_depth = [];
    $to_remove      = [];
    $skipped        = [];
    $orphans        = [];

    foreach ($by_id as $id => $c) {
        $d = $depths[$id];
        if (!isset($stats_by_depth[$d])) {
            $stats_by_depth[$d] = ['count' => 0, 'posts' => 0];
        }
        $stats_by_depth[$d]['count']++;
        $stats_by_depth[$d]['posts'] += $c['count'];

        if ($c['parent'] !== 0 && !isset($by_id[$c['parent']])) {
            $orphans[] = $c;
        }

        if ($d > 2) {
            if ($id === $default_cat) {
                $skipped[] = ['cat' => $c, 'reason' => 'categorie par defaut WordPress — jamais supprimee'];
                continue;
            }
            $anc = catcleanup_ancestor_l2($id, $by_id, $depths);
            if (!isset($by_id[$anc])) {
                $skipped[] = ['cat' => $c, 'reason' => 'ancetre L2 introuvable — sera ignoree par 04-apply'];
                continue;
            }
            $to_remove[] = [
                'term_id'       => $id,
                'name'          => $c['name'],
                'depth'         => $d,
                'posts'         => $c['count'],
                'ancestor_id'   => $anc,
                'ancestor_name' => $by_id[$anc]['name'],
            ];
        }
    }

    ksort($stats_by_depth);
    $total_remove = count($to_remove);
    $total_keep   = count($by_id) - $total_remove;

    // Posts réellement impactés (au moins une catégorie L3+ supprimable)
    $affected_posts = 0;
    $deep_ttids     = [];
    foreach ($to_remove as $r) {
        $deep_ttids[] = $by_id[$r['term_id']]['term_taxonomy_id'];
    }
    if (!empty($deep_ttids)) {
        $in = implode(',', array_map('intval', $deep_ttids));
        $affected_posts = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT object_id) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($in)"
        );
    }

    // Nombre de sous-catégories fusionnées par ancêtre L2 (précalculé)
    $merged_per_l2 = [];
    foreach ($to_remove as $r) {
        if (!isset($merged_per_l2[$r['ancestor_id']])) $merged_per_l2[$r['ancestor_id']] = 0;
        $merged_per_l2[$r['ancestor_id']]++;
    }

    // ─── Sortie HTML ────────────────────────────────────────
    echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1200px;margin:20px auto;padding:20px;'>";

    echo '<h1>Analyse des categories WordPress</h1>';

    echo '<h2>Resume</h2>';
    echo '<p><strong>' . count($by_id) . '</strong> categories au total &mdash; '
        . "<strong>{$total_keep}</strong> a conserver &mdash; "
        . "<strong>{$total_remove}</strong> a supprimer (niveaux 3+) &mdash; "
        . "<strong>{$affected_posts}</strong> posts impactes</p>";

    echo '<table style="border-collapse:collapse;width:100%;">';
    echo '<tr style="background:#f0f0f0;">'
        . '<th style="padding:8px;border:1px solid #ddd;">Niveau</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Categories</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Posts associes</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Action</th></tr>';
    foreach ($stats_by_depth as $d => $s) {
        $action = $d <= 2 ? 'Conserver' : 'Supprimer';
        $bg     = $d <= 2 ? '#e8f5e9' : '#ffebee';
        echo "<tr style='background:{$bg};'>"
            . "<td style='padding:8px;border:1px solid #ddd;'>Niveau {$d}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$s['count']}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$s['posts']}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$action}</td></tr>";
    }
    echo '</table>';
    echo '<p><em>Note : la colonne "Posts associes" reflete le compteur WordPress (posts publies uniquement).</em></p>';

    // ─── Cas particuliers ────────────────────────────────────
    if (!empty($skipped)) {
        echo '<h2>Cas particuliers (conserves malgre leur profondeur)</h2><ul>';
        foreach ($skipped as $s) {
            echo '<li>' . esc_html($s['cat']['name']) . ' (ID: ' . $s['cat']['term_id'] . ') — ' . esc_html($s['reason']) . '</li>';
        }
        echo '</ul>';
    }

    if (!empty($orphans)) {
        echo '<h2>Categories orphelines</h2>';
        echo '<p>Leur parent n\'existe plus — elles sont traitees comme des racines (niveau 1) :</p><ul>';
        foreach ($orphans as $o) {
            echo '<li>' . esc_html($o['name']) . ' (ID: ' . $o['term_id'] . ', parent manquant: ' . $o['parent'] . ')</li>';
        }
        echo '</ul>';
    }

    // Membres de cycles de parenté : profondeur forcée à 1 alors qu'ils ont
    // un parent existant → données corrompues à corriger manuellement.
    $cycle_members = array_filter($by_id, function ($c) use ($by_id, $depths) {
        return $depths[$c['term_id']] === 1
            && $c['parent'] !== 0
            && isset($by_id[$c['parent']]);
    });
    if (!empty($cycle_members)) {
        echo '<h2>Cycles de parente detectes</h2>';
        echo '<p style="color:red;">Ces categories font partie d\'une boucle de parente (A est parent de B qui est parent de A). '
            . 'Elles sont conservees par securite. Corrigez leur parent manuellement dans l\'admin WordPress avant le nettoyage :</p><ul>';
        foreach ($cycle_members as $c) {
            echo '<li>' . esc_html($c['name']) . ' (ID: ' . $c['term_id'] . ', parent: ' . $c['parent'] . ')</li>';
        }
        echo '</ul>';
    }

    // ─── Arbre L1-L2 conservé ───────────────────────────────
    echo '<h2>Arbre des categories conservees (niveaux 1-2)</h2>';

    $l1 = array_filter($by_id, function ($c) use ($depths) { return $depths[$c['term_id']] === 1; });
    usort($l1, function ($a, $b) { return strcmp($a['name'], $b['name']); });

    foreach ($l1 as $parent) {
        $children = array_filter($by_id, function ($c) use ($parent, $depths) {
            return $c['parent'] === $parent['term_id'] && $depths[$c['term_id']] === 2;
        });
        usort($children, function ($a, $b) { return strcmp($a['name'], $b['name']); });

        $n = count($children);
        echo "<details style='margin:4px 0;'><summary><strong>" . esc_html($parent['name']) . "</strong> (ID: {$parent['term_id']}, {$n} sous-categories)</summary>";
        if ($children) {
            echo '<ul style="margin:4px 0;">';
            foreach ($children as $ch) {
                $deep_n = isset($merged_per_l2[$ch['term_id']]) ? $merged_per_l2[$ch['term_id']] : 0;
                $info   = $deep_n > 0 ? " — {$deep_n} sous-categories profondes fusionnees ici" : '';
                echo '<li>' . esc_html($ch['name']) . " (ID: {$ch['term_id']}, {$ch['count']} posts){$info}</li>";
            }
            echo '</ul>';
        }
        echo '</details>';
    }

    // ─── Catégories à supprimer (aperçu) ────────────────────
    echo '<h2>Categories a supprimer (200 premieres)</h2>';
    echo '<p>' . count($to_remove) . ' categories de niveau 3+ au total.</p>';

    usort($to_remove, function ($a, $b) {
        return $a['depth'] !== $b['depth'] ? $a['depth'] - $b['depth'] : strcmp($a['name'], $b['name']);
    });

    echo '<table style="border-collapse:collapse;width:100%;">';
    echo '<tr style="background:#f0f0f0;">'
        . '<th style="padding:8px;border:1px solid #ddd;">ID</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Chemin complet</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Niv.</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Posts</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Ancetre L2</th></tr>';

    $shown = 0;
    foreach ($to_remove as $r) {
        if (++$shown > 200) break;
        $path = esc_html(catcleanup_full_path($r['term_id'], $by_id));
        echo '<tr>'
            . "<td style='padding:8px;border:1px solid #ddd;'>{$r['term_id']}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$path}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$r['depth']}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$r['posts']}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>" . esc_html($r['ancestor_name']) . " (ID: {$r['ancestor_id']})</td>"
            . '</tr>';
    }
    echo '</table>';

    if (count($to_remove) > 200) {
        echo '<p><em>' . (count($to_remove) - 200) . ' categories supplementaires non affichees. Utilisez 02-preview.php pour l\'export complet.</em></p>';
    }

    echo '</div>';
    exit;
};

if (did_action('init')) {
    $catcleanup_analyze();
} else {
    add_action('init', $catcleanup_analyze, 0);
}
