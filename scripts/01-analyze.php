/**
 * PHASE 1 : ANALYSE DES CATÉGORIES
 * Mode lecture seule — ne modifie rien.
 * Copier-coller dans WPCodeBox et exécuter.
 */

global $wpdb;
set_time_limit(120);

// ─── Chargement des catégories ──────────────────────────────
$cats = $wpdb->get_results("
    SELECT t.term_id, t.name, t.slug,
           tt.term_taxonomy_id, tt.parent, tt.count
    FROM {$wpdb->terms} t
    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'category'
", ARRAY_A);

if (!$cats) {
    echo '<p style="color:red;">Aucune catégorie trouvée. Vérifiez la connexion à la base de données.</p>';
    return;
}

$by_id = [];
foreach ($cats as $c) {
    $c['term_id']          = (int) $c['term_id'];
    $c['term_taxonomy_id'] = (int) $c['term_taxonomy_id'];
    $c['parent']           = (int) $c['parent'];
    $c['count']            = (int) $c['count'];
    $by_id[$c['term_id']]  = $c;
}

// ─── Calcul des profondeurs ─────────────────────────────────
$depths = [];

function calc_depth($id, &$by_id, &$depths, $visited = []) {
    if (isset($depths[$id])) return $depths[$id];
    if (!isset($by_id[$id]) || $by_id[$id]['parent'] === 0) {
        return $depths[$id] = 1;
    }
    if (in_array($id, $visited)) {
        return $depths[$id] = 1;
    }
    $visited[] = $id;
    return $depths[$id] = 1 + calc_depth($by_id[$id]['parent'], $by_id, $depths, $visited);
}

foreach ($by_id as $id => $c) {
    calc_depth($id, $by_id, $depths);
}

// ─── Ancêtre de niveau 2 ───────────────────────────────────
function ancestor_l2($id, &$by_id, &$depths) {
    if ($depths[$id] <= 2) return $id;
    $cur = $id;
    while (isset($depths[$cur]) && $depths[$cur] > 2) {
        $cur = $by_id[$cur]['parent'];
    }
    return $cur;
}

// ─── Chemin complet ─────────────────────────────────────────
function full_path($id, &$by_id) {
    $parts   = [];
    $cur     = $id;
    $visited = [];
    while ($cur !== 0 && isset($by_id[$cur]) && !in_array($cur, $visited)) {
        $visited[] = $cur;
        $parts[]   = $by_id[$cur]['name'];
        $cur       = $by_id[$cur]['parent'];
    }
    return implode(' &gt; ', array_reverse($parts));
}

// ─── Statistiques ───────────────────────────────────────────
$stats_by_depth = [];
$to_remove      = [];
$orphans        = [];

foreach ($by_id as $id => $c) {
    $d = $depths[$id];
    if (!isset($stats_by_depth[$d])) {
        $stats_by_depth[$d] = ['count' => 0, 'posts' => 0];
    }
    $stats_by_depth[$d]['count']++;
    $stats_by_depth[$d]['posts'] += $c['count'];

    if ($d > 2) {
        $anc = ancestor_l2($id, $by_id, $depths);
        $to_remove[] = [
            'term_id'       => $id,
            'name'          => $c['name'],
            'depth'         => $d,
            'posts'         => $c['count'],
            'ancestor_id'   => $anc,
            'ancestor_name' => isset($by_id[$anc]) ? $by_id[$anc]['name'] : '???',
        ];
    }

    if ($c['parent'] !== 0 && !isset($by_id[$c['parent']])) {
        $orphans[] = $c;
    }
}

ksort($stats_by_depth);
$total_keep   = 0;
$total_remove = 0;
foreach ($stats_by_depth as $d => $s) {
    if ($d <= 2) $total_keep   += $s['count'];
    else         $total_remove += $s['count'];
}

// Nombre de posts réellement impactés (ayant au moins une catégorie L3+)
$deep_ttids = [];
foreach ($to_remove as $r) {
    $deep_ttids[] = $by_id[$r['term_id']]['term_taxonomy_id'];
}
$affected_posts = 0;
if (!empty($deep_ttids)) {
    $placeholders   = implode(',', array_fill(0, count($deep_ttids), '%d'));
    $affected_posts = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(DISTINCT object_id) FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($placeholders)",
            ...$deep_ttids
        )
    );
}

// ─── Sortie HTML ────────────────────────────────────────────
$css = 'font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1200px;margin:20px auto;padding:20px;';
echo "<div style='{$css}'>";

echo '<h1>Analyse des categories WordPress</h1>';

echo '<h2>Resume</h2>';
echo "<p><strong>" . count($by_id) . "</strong> categories au total &mdash; "
    . "<strong>{$total_keep}</strong> a conserver (niveaux 1-2) &mdash; "
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

// ─── Orphelins ──────────────────────────────────────────────
if (!empty($orphans)) {
    echo '<h2>Categories orphelines</h2>';
    echo '<p>Parent inexistant — seront traitees comme des racines :</p><ul>';
    foreach ($orphans as $o) {
        echo "<li>{$o['name']} (ID: {$o['term_id']}, parent manquant: {$o['parent']})</li>";
    }
    echo '</ul>';
}

// ─── Arbre L1-L2 conservé ───────────────────────────────────
echo '<h2>Arbre des categories conservees (niveaux 1-2)</h2>';

$l1 = array_filter($by_id, function ($c) use ($depths) { return $depths[$c['term_id']] === 1; });
usort($l1, function ($a, $b) { return strcmp($a['name'], $b['name']); });

foreach ($l1 as $parent) {
    $children = array_filter($by_id, function ($c) use ($parent, $depths) {
        return $c['parent'] === $parent['term_id'] && $depths[$c['term_id']] === 2;
    });
    usort($children, function ($a, $b) { return strcmp($a['name'], $b['name']); });

    $n = count($children);
    echo "<details style='margin:4px 0;'><summary><strong>{$parent['name']}</strong> (ID: {$parent['term_id']}, {$n} sous-categories)</summary>";
    if ($children) {
        echo '<ul style="margin:4px 0;">';
        foreach ($children as $ch) {
            $deep_n = 0;
            foreach ($to_remove as $r) {
                if ($r['ancestor_id'] === $ch['term_id']) $deep_n++;
            }
            $info = $deep_n > 0 ? " — {$deep_n} sous-categories profondes fusionnees ici" : '';
            echo "<li>{$ch['name']} (ID: {$ch['term_id']}, {$ch['count']} posts){$info}</li>";
        }
        echo '</ul>';
    }
    echo '</details>';
}

// ─── Catégories à supprimer (aperçu) ────────────────────────
echo '<h2>Categories a supprimer (200 premieres)</h2>';
echo '<p>' . count($to_remove) . ' categories de niveau 3+ au total.</p>';

usort($to_remove, function ($a, $b) {
    return $a['depth'] !== $b['depth']
        ? $a['depth'] - $b['depth']
        : strcmp($a['name'], $b['name']);
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
    $path = full_path($r['term_id'], $by_id);
    echo "<tr>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$r['term_id']}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$path}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$r['depth']}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$r['posts']}</td>"
        . "<td style='padding:8px;border:1px solid #ddd;'>{$r['ancestor_name']} (ID: {$r['ancestor_id']})</td>"
        . "</tr>";
}
echo '</table>';

if (count($to_remove) > 200) {
    echo '<p><em>' . (count($to_remove) - 200) . ' categories supplementaires non affichees. Utilisez le script 02-preview.php pour l\'export complet.</em></p>';
}

echo '</div>';
