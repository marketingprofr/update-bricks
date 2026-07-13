/**
 * PHASE 2 : PRÉVISUALISATION DES RÉAFFECTATIONS
 * Mode lecture seule — ne modifie rien.
 * Génère un CSV téléchargeable avec le détail post par post.
 *
 * Utilisation :
 *   1. Coller ce code dans un snippet PHP WPCodeBox (à la suite de la balise <?php)
 *   2. Activer le snippet
 *   3. Visiter : https://votre-site.fr/?catcleanup=preview  (connecté en admin)
 *   4. Désactiver le snippet après usage
 */

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
$catcleanup_preview = function () {
    if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'preview') return;
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    if (function_exists('set_time_limit')) @set_time_limit(600);
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

    // ─── Catégories profondes supprimables (mêmes règles que 04-apply) ──
    $deep_cats = [];
    foreach ($by_id as $id => $c) {
        if ($depths[$id] <= 2) continue;
        if ($id === $default_cat) continue;
        $anc = catcleanup_ancestor_l2($id, $by_id, $depths);
        if (!isset($by_id[$anc])) continue;

        $deep_cats[$id] = [
            'term_id'       => $id,
            'ttid'          => $c['term_taxonomy_id'],
            'name'          => $c['name'],
            'ancestor_id'   => $anc,
            'ancestor_name' => $by_id[$anc]['name'],
        ];
    }

    if (empty($deep_cats)) {
        echo '<p>Aucune categorie de niveau 3+ supprimable. Rien a faire.</p>';
        exit;
    }

    $ttid_to_deep = [];
    foreach ($deep_cats as $dc) {
        $ttid_to_deep[$dc['ttid']] = $dc;
    }

    // ─── Relations post ↔ catégorie profonde, par lots (économie mémoire) ──
    $post_plan = []; // pid => ['old' => [tid => nom], 'new' => [aid => nom]]
    foreach (array_chunk(array_column($deep_cats, 'ttid'), 500) as $chunk) {
        $in   = implode(',', array_map('intval', $chunk));
        $rows = $wpdb->get_results(
            "SELECT object_id, term_taxonomy_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($in)",
            ARRAY_A
        );
        foreach ((array) $rows as $row) {
            $pid  = (int) $row['object_id'];
            $ttid = (int) $row['term_taxonomy_id'];
            $dc   = $ttid_to_deep[$ttid];
            if (!isset($post_plan[$pid])) {
                $post_plan[$pid] = ['old' => [], 'new' => []];
            }
            $post_plan[$pid]['old'][$dc['term_id']]     = $dc['name'];
            $post_plan[$pid]['new'][$dc['ancestor_id']] = $dc['ancestor_name'];
        }
        unset($rows);
    }

    // ─── Résumé par ancêtre L2 ──────────────────────────────
    $l2_summary = [];
    foreach ($deep_cats as $dc) {
        $aid = $dc['ancestor_id'];
        if (!isset($l2_summary[$aid])) {
            $l2_summary[$aid] = ['name' => $dc['ancestor_name'], 'deep_count' => 0, 'deep_names' => [], 'post_count' => 0];
        }
        $l2_summary[$aid]['deep_count']++;
        $l2_summary[$aid]['deep_names'][] = $dc['name'];
    }
    foreach ($post_plan as $plan) {
        foreach ($plan['new'] as $aid => $aname) {
            $l2_summary[$aid]['post_count']++;
        }
    }
    uasort($l2_summary, function ($a, $b) { return $b['post_count'] - $a['post_count']; });

    // ─── CSV en flux (titres récupérés par lots de 1000) ─────
    $upload_dir = wp_upload_dir();
    $csv_path   = $upload_dir['basedir'] . '/category-reassignment-preview.csv';
    $csv_url    = $upload_dir['baseurl'] . '/category-reassignment-preview.csv';

    $fh = fopen($csv_path, 'wb');
    if (!$fh) {
        echo '<p style="color:red;">Impossible d\'ecrire le fichier CSV dans ' . esc_html($csv_path) . '</p>';
        exit;
    }
    fwrite($fh, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
    fputcsv($fh, ['post_id', 'post_title', 'post_type', 'categories_actuelles', 'categories_L2_cibles'], ';');

    $preview_rows = [];
    $ghost_count  = 0;

    foreach (array_chunk(array_keys($post_plan), 1000) as $chunk) {
        $in    = implode(',', array_map('intval', $chunk));
        $posts = $wpdb->get_results(
            "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE ID IN ($in)",
            OBJECT_K
        );
        foreach ($chunk as $pid) {
            $plan = $post_plan[$pid];
            if (isset($posts[$pid])) {
                $title = $posts[$pid]->post_title;
                $type  = $posts[$pid]->post_type;
            } else {
                $title = '(post introuvable — relation fantome)';
                $type  = '?';
                $ghost_count++;
            }
            fputcsv($fh, [$pid, $title, $type, implode(' | ', $plan['old']), implode(' | ', $plan['new'])], ';');

            if (count($preview_rows) < 50) {
                $preview_rows[] = ['pid' => $pid, 'title' => $title, 'type' => $type, 'old' => $plan['old'], 'new' => $plan['new']];
            }
        }
        unset($posts);
    }
    fclose($fh);

    // ─── Sortie HTML ────────────────────────────────────────
    echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1200px;margin:20px auto;padding:20px;'>";

    echo '<h1>Previsualisation des reaffectations</h1>';
    echo '<p><strong>' . count($post_plan) . '</strong> posts seront reaffectes vers <strong>'
        . count($l2_summary) . '</strong> categories de niveau 2 '
        . '(' . count($deep_cats) . ' categories profondes supprimees).</p>';

    if ($ghost_count > 0) {
        echo "<p style='color:orange;'>{$ghost_count} relations pointent vers des posts inexistants (relations fantomes) — elles disparaitront avec la suppression des categories.</p>";
    }

    echo "<p><a href='" . esc_url($csv_url) . "' download style='padding:8px 16px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;'>Telecharger le CSV complet</a></p>";

    echo '<h2>Resume par categorie cible (L2)</h2>';
    echo '<table style="border-collapse:collapse;width:100%;">';
    echo '<tr style="background:#f0f0f0;">'
        . '<th style="padding:8px;border:1px solid #ddd;">Categorie L2 cible</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">ID</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Sous-cat. fusionnees</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Posts impactes</th></tr>';
    foreach ($l2_summary as $aid => $s) {
        $deep_list = implode(', ', array_map('esc_html', array_slice($s['deep_names'], 0, 5)));
        if (count($s['deep_names']) > 5) $deep_list .= ' ...+' . (count($s['deep_names']) - 5);
        echo '<tr>'
            . "<td style='padding:8px;border:1px solid #ddd;'><strong>" . esc_html($s['name']) . '</strong></td>'
            . "<td style='padding:8px;border:1px solid #ddd;'>{$aid}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$s['deep_count']} ({$deep_list})</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>{$s['post_count']}</td>"
            . '</tr>';
    }
    echo '</table>';

    echo '<h2>Apercu des reaffectations (50 premiers posts)</h2>';
    echo '<table style="border-collapse:collapse;width:100%;">';
    echo '<tr style="background:#f0f0f0;">'
        . '<th style="padding:8px;border:1px solid #ddd;">Post ID</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Titre</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Type</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Categories actuelles (profondes)</th>'
        . '<th style="padding:8px;border:1px solid #ddd;">Categories L2 cibles</th></tr>';
    foreach ($preview_rows as $row) {
        echo '<tr>'
            . "<td style='padding:8px;border:1px solid #ddd;'>{$row['pid']}</td>"
            . "<td style='padding:8px;border:1px solid #ddd;'>" . esc_html($row['title']) . '</td>'
            . "<td style='padding:8px;border:1px solid #ddd;'>" . esc_html($row['type']) . '</td>'
            . "<td style='padding:8px;border:1px solid #ddd;'>" . esc_html(implode(', ', $row['old'])) . '</td>'
            . "<td style='padding:8px;border:1px solid #ddd;'><strong>" . esc_html(implode(', ', $row['new'])) . '</strong></td>'
            . '</tr>';
    }
    echo '</table>';

    if (count($post_plan) > 50) {
        echo '<p><em>' . (count($post_plan) - 50) . ' posts supplementaires — voir le CSV pour la liste complete.</em></p>';
    }

    echo "<p style='margin-top:20px;padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'>"
        . '<strong>Le CSV est accessible publiquement le temps de sa presence sur le serveur. '
        . 'Pensez a le supprimer apres telechargement :</strong> ' . esc_html($csv_path) . '</p>';

    echo '</div>';
    exit;
};

if (did_action('init')) {
    $catcleanup_preview();
} else {
    add_action('init', $catcleanup_preview);
}
