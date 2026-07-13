/**
 * PHASE 2 : PRÉVISUALISATION DES RÉAFFECTATIONS
 * Mode lecture seule — ne modifie rien.
 * Génère un CSV téléchargeable avec le détail post par post.
 * Copier-coller dans WPCodeBox et exécuter.
 */

global $wpdb;
set_time_limit(300);

// ─── Chargement et arbre (identique à 01-analyze) ──────────
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
function full_path($id, &$by_id) {
    $parts = []; $cur = $id; $v = [];
    while ($cur !== 0 && isset($by_id[$cur]) && !in_array($cur, $v)) {
        $v[] = $cur; $parts[] = $by_id[$cur]['name']; $cur = $by_id[$cur]['parent'];
    }
    return implode(' > ', array_reverse($parts));
}

// ─── Identifier les catégories profondes ────────────────────
$deep_cats = [];
foreach ($by_id as $id => $c) {
    if ($depths[$id] > 2) {
        $anc = ancestor_l2($id, $by_id, $depths);
        $deep_cats[$id] = [
            'term_id'       => $id,
            'ttid'          => $c['term_taxonomy_id'],
            'name'          => $c['name'],
            'depth'         => $depths[$id],
            'ancestor_id'   => $anc,
            'ancestor_name' => isset($by_id[$anc]) ? $by_id[$anc]['name'] : '???',
            'ancestor_ttid' => isset($by_id[$anc]) ? $by_id[$anc]['term_taxonomy_id'] : 0,
        ];
    }
}

if (empty($deep_cats)) {
    echo '<p>Aucune categorie de niveau 3+ trouvee. Rien a faire.</p>';
    return;
}

// ─── Récupérer les posts impactés ───────────────────────────
$deep_ttids     = array_column($deep_cats, 'ttid');
$placeholders   = implode(',', array_fill(0, count($deep_ttids), '%d'));

$rows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT tr.object_id, tr.term_taxonomy_id, p.post_title, p.post_type
         FROM {$wpdb->term_relationships} tr
         JOIN {$wpdb->posts} p ON p.ID = tr.object_id
         WHERE tr.term_taxonomy_id IN ($placeholders)
         ORDER BY tr.object_id",
        ...$deep_ttids
    ),
    ARRAY_A
);

// Indexer ttid → deep_cat pour lookup rapide
$ttid_to_deep = [];
foreach ($deep_cats as $dc) {
    $ttid_to_deep[$dc['ttid']] = $dc;
}

// ─── Construire le plan de réaffectation ────────────────────
$post_plan = []; // post_id => ['title', 'type', 'old_cats' => [...], 'new_l2s' => [...]]

foreach ($rows as $row) {
    $pid  = (int) $row['object_id'];
    $ttid = (int) $row['term_taxonomy_id'];
    $dc   = $ttid_to_deep[$ttid];

    if (!isset($post_plan[$pid])) {
        $post_plan[$pid] = [
            'title'    => $row['post_title'],
            'type'     => $row['post_type'],
            'old_cats' => [],
            'new_l2s'  => [],
        ];
    }
    $post_plan[$pid]['old_cats'][$dc['term_id']] = $dc['name'];
    $post_plan[$pid]['new_l2s'][$dc['ancestor_id']] = $dc['ancestor_name'];
}

// ─── Résumé par ancêtre L2 ──────────────────────────────────
$l2_summary = []; // ancestor_id => ['name', 'deep_count', 'post_count', 'deep_names']
foreach ($deep_cats as $dc) {
    $aid = $dc['ancestor_id'];
    if (!isset($l2_summary[$aid])) {
        $l2_summary[$aid] = [
            'name'       => $dc['ancestor_name'],
            'deep_count' => 0,
            'post_ids'   => [],
            'deep_names' => [],
        ];
    }
    $l2_summary[$aid]['deep_count']++;
    $l2_summary[$aid]['deep_names'][] = $dc['name'];
}
foreach ($post_plan as $pid => $plan) {
    foreach ($plan['new_l2s'] as $aid => $aname) {
        $l2_summary[$aid]['post_ids'][$pid] = true;
    }
}
foreach ($l2_summary as &$s) {
    $s['post_count'] = count($s['post_ids']);
    unset($s['post_ids']);
}
unset($s);
uasort($l2_summary, function ($a, $b) { return $b['post_count'] - $a['post_count']; });

// ─── Générer le CSV ─────────────────────────────────────────
$csv_lines = ["post_id;post_title;post_type;categories_actuelles;categories_L2_cibles"];
foreach ($post_plan as $pid => $plan) {
    $old  = implode(' | ', $plan['old_cats']);
    $new  = implode(' | ', $plan['new_l2s']);
    $title = str_replace(';', ',', $plan['title']);
    $csv_lines[] = "{$pid};{$title};{$plan['type']};{$old};{$new}";
}
$csv_content = implode("\n", $csv_lines);

$upload_dir = wp_upload_dir();
$csv_path   = $upload_dir['basedir'] . '/category-reassignment-preview.csv';
file_put_contents($csv_path, "\xEF\xBB\xBF" . $csv_content); // BOM UTF-8 pour Excel
$csv_url = $upload_dir['baseurl'] . '/category-reassignment-preview.csv';

// ─── Sortie HTML ────────────────────────────────────────────
$css = 'font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1200px;margin:20px auto;padding:20px;';
echo "<div style='{$css}'>";

echo '<h1>Previsualisation des reaffectations</h1>';
echo '<p><strong>' . count($post_plan) . '</strong> posts seront reaffectes vers <strong>'
    . count($l2_summary) . '</strong> categories de niveau 2.</p>';
echo "<p><a href='{$csv_url}' download style='padding:8px 16px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;'>Telecharger le CSV complet</a></p>";

// Résumé par L2
echo '<h2>Resume par categorie cible (L2)</h2>';
echo '<table style="border-collapse:collapse;width:100%;">';
echo '<tr style="background:#f0f0f0;">'
    . '<th style="padding:8px;border:1px solid #ddd;">Categorie L2 cible</th>'
    . '<th style="padding:8px;border:1px solid #ddd;">ID</th>'
    . '<th style="padding:8px;border:1px solid #ddd;">Sous-cat. fusionnees</th>'
    . '<th style="padding:8px;border:1px solid #ddd;">Posts impactes</th></tr>';

foreach ($l2_summary as $aid => $s) {
    $deep_list = implode(', ', array_slice($s['deep_names'], 0, 5));
    if (count($s['deep_names']) > 5) $deep_list .= ' ...+' . (count($s['deep_names']) - 5);
    echo "<tr>"
        . "<td style='padding:8px;border:1px solid #ddd;'><strong>{$s['name']}</strong></td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$aid}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$s['deep_count']} ({$deep_list})</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$s['post_count']}</td>"
        . "</tr>";
}
echo '</table>';

// Aperçu post par post (50 premiers)
echo '<h2>Apercu des reaffectations (50 premiers posts)</h2>';
echo '<table style="border-collapse:collapse;width:100%;">';
echo '<tr style="background:#f0f0f0;">'
    . '<th style="padding:8px;border:1px solid #ddd;">Post ID</th>'
    . '<th style="padding:8px;border:1px solid #ddd;">Titre</th>'
    . '<th style="padding:8px;border:1px solid #ddd;">Type</th>'
    . '<th style="padding:8px;border:1px solid #ddd;">Categories actuelles (profondes)</th>'
    . '<th style="padding:8px;border:1px solid #ddd;">Categories L2 cibles</th></tr>';

$shown = 0;
foreach ($post_plan as $pid => $plan) {
    if (++$shown > 50) break;
    $old = implode(', ', $plan['old_cats']);
    $new = implode(', ', $plan['new_l2s']);
    echo "<tr>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$pid}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$plan['title']}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$plan['type']}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$old}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'><strong>{$new}</strong></td>"
        . "</tr>";
}
echo '</table>';

if (count($post_plan) > 50) {
    echo '<p><em>' . (count($post_plan) - 50) . ' posts supplementaires — voir le CSV pour la liste complete.</em></p>';
}

echo "<p style='margin-top:20px;padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'>"
    . "<strong>Pensez a supprimer le fichier CSV apres telechargement :</strong> {$csv_path}</p>";

echo '</div>';
