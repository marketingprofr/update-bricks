/**
 * PHASE 5 : VÉRIFICATION POST-OPÉRATION
 * Mode lecture seule — ne modifie rien.
 * Vérifie que le nettoyage s'est bien passé.
 * Copier-coller dans WPCodeBox et exécuter.
 */

global $wpdb;
set_time_limit(120);

$css = 'font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1000px;margin:20px auto;padding:20px;';
echo "<div style='{$css}'>";
echo '<h1>Verification post-nettoyage</h1>';

$checks_ok   = 0;
$checks_warn = 0;
$checks_fail = 0;

// ─── CHECK 1 : Profondeur maximale ─────────────────────────
echo '<h2>1. Profondeur maximale des categories</h2>';

$cats = $wpdb->get_results("
    SELECT t.term_id, t.name, tt.parent
    FROM {$wpdb->terms} t
    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'category'
", ARRAY_A);

$by_id = [];
foreach ($cats as $c) {
    $c['term_id'] = (int) $c['term_id'];
    $c['parent']  = (int) $c['parent'];
    $by_id[$c['term_id']] = $c;
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

$max_depth  = empty($depths) ? 0 : max($depths);
$deep_cats  = array_filter($depths, function ($d) { return $d > 2; });

if ($max_depth <= 2) {
    echo "<p style='color:green;'>OK — Profondeur maximale : {$max_depth}. Toutes les categories sont au niveau 1 ou 2.</p>";
    $checks_ok++;
} else {
    echo "<p style='color:red;'>ECHEC — Profondeur maximale : {$max_depth}. "
        . count($deep_cats) . " categories de niveau 3+ subsistent :</p>";
    echo '<ul>';
    foreach ($deep_cats as $id => $d) {
        echo "<li>{$by_id[$id]['name']} (ID: {$id}, niveau: {$d})</li>";
    }
    echo '</ul>';
    $checks_fail++;
}

// ─── CHECK 2 : Nombre total de catégories ──────────────────
echo '<h2>2. Nombre de categories restantes</h2>';

$total = count($by_id);
$l1    = count(array_filter($depths, function ($d) { return $d === 1; }));
$l2    = count(array_filter($depths, function ($d) { return $d === 2; }));

echo "<p>{$total} categories au total : {$l1} de niveau 1, {$l2} de niveau 2.</p>";
$checks_ok++;

// ─── CHECK 3 : Posts sans catégorie ─────────────────────────
echo '<h2>3. Posts publies sans categorie</h2>';

$post_types = $wpdb->get_col("
    SELECT DISTINCT post_type FROM {$wpdb->posts}
    WHERE post_status = 'publish'
    AND post_type NOT IN ('attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_global_styles', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_face', 'wp_font_family')
");

$orphan_total = 0;
foreach ($post_types as $pt) {
    $orphan_count = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->posts} p
        WHERE p.post_status = 'publish'
        AND p.post_type = %s
        AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tr.object_id = p.ID AND tt.taxonomy = 'category'
        )
    ", $pt));

    if ($orphan_count > 0) {
        echo "<p style='color:orange;'>ATTENTION — {$orphan_count} posts de type <strong>{$pt}</strong> sans categorie.</p>";
        $orphan_total += $orphan_count;
    }
}

if ($orphan_total === 0) {
    echo "<p style='color:green;'>OK — Tous les posts publies ont au moins une categorie.</p>";
    $checks_ok++;
} else {
    echo "<p style='color:orange;'>ATTENTION — {$orphan_total} posts publies sans categorie au total. "
        . "Ce n'est pas forcement un probleme si ces post types n'utilisent pas la taxonomie 'category'.</p>";
    $checks_warn++;
}

// ─── CHECK 4 : Compteurs de termes ─────────────────────────
echo '<h2>4. Coherence des compteurs de termes</h2>';

$mismatches = $wpdb->get_results("
    SELECT tt.term_id, t.name, tt.count AS stored_count,
           COALESCE(real_counts.real_count, 0) AS real_count
    FROM {$wpdb->term_taxonomy} tt
    JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
    LEFT JOIN (
        SELECT term_taxonomy_id, COUNT(*) AS real_count
        FROM {$wpdb->term_relationships}
        GROUP BY term_taxonomy_id
    ) real_counts ON real_counts.term_taxonomy_id = tt.term_taxonomy_id
    WHERE tt.taxonomy = 'category'
    AND tt.count != COALESCE(real_counts.real_count, 0)
    LIMIT 20
", ARRAY_A);

if (empty($mismatches)) {
    echo "<p style='color:green;'>OK — Tous les compteurs de termes sont corrects.</p>";
    $checks_ok++;
} else {
    echo "<p style='color:orange;'>ATTENTION — " . count($mismatches) . " categories avec un compteur incorrect :</p>";
    echo '<table style="border-collapse:collapse;">';
    echo '<tr style="background:#f0f0f0;">'
        . '<th style="padding:6px;border:1px solid #ddd;">ID</th>'
        . '<th style="padding:6px;border:1px solid #ddd;">Nom</th>'
        . '<th style="padding:6px;border:1px solid #ddd;">Stocke</th>'
        . '<th style="padding:6px;border:1px solid #ddd;">Reel</th></tr>';
    foreach ($mismatches as $m) {
        echo "<tr>"
            . "<td style='padding:6px;border:1px solid #ddd;'>{$m['term_id']}</td>"
            . "<td style='padding:6px;border:1px solid #ddd;'>{$m['name']}</td>"
            . "<td style='padding:6px;border:1px solid #ddd;'>{$m['stored_count']}</td>"
            . "<td style='padding:6px;border:1px solid #ddd;'>{$m['real_count']}</td>"
            . "</tr>";
    }
    echo '</table>';
    echo '<p>Ceci peut etre corrige en relancant un recalcul des compteurs (inclus dans 04-apply.php).</p>';
    $checks_warn++;
}

// ─── CHECK 5 : Catégories orphelines ────────────────────────
echo '<h2>5. Categories orphelines</h2>';

$orphan_cats = array_filter($by_id, function ($c) use ($by_id) {
    return $c['parent'] !== 0 && !isset($by_id[$c['parent']]);
});

if (empty($orphan_cats)) {
    echo "<p style='color:green;'>OK — Aucune categorie orpheline.</p>";
    $checks_ok++;
} else {
    echo "<p style='color:red;'>ECHEC — " . count($orphan_cats) . " categories ont un parent inexistant :</p><ul>";
    foreach ($orphan_cats as $c) {
        echo "<li>{$c['name']} (ID: {$c['term_id']}, parent: {$c['parent']})</li>";
    }
    echo '</ul>';
    $checks_fail++;
}

// ─── CHECK 6 : Relations vers des termes inexistants ────────
echo '<h2>6. Relations vers des termes supprimes</h2>';

$ghost_rels = (int) $wpdb->get_var("
    SELECT COUNT(*)
    FROM {$wpdb->term_relationships} tr
    WHERE NOT EXISTS (
        SELECT 1 FROM {$wpdb->term_taxonomy} tt
        WHERE tt.term_taxonomy_id = tr.term_taxonomy_id
    )
");

if ($ghost_rels === 0) {
    echo "<p style='color:green;'>OK — Aucune relation orpheline.</p>";
    $checks_ok++;
} else {
    echo "<p style='color:red;'>ECHEC — {$ghost_rels} relations pointent vers des termes supprimes. "
        . "Elles peuvent etre nettoyees avec : <code>DELETE FROM {$wpdb->term_relationships} WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->term_taxonomy} tt WHERE tt.term_taxonomy_id = {$wpdb->term_relationships}.term_taxonomy_id)</code></p>";
    $checks_fail++;
}

// ─── Bilan ──────────────────────────────────────────────────
echo '<h2>Bilan</h2>';

$bg = $checks_fail > 0 ? '#ffebee' : ($checks_warn > 0 ? '#fff3cd' : '#e8f5e9');
echo "<div style='padding:16px;background:{$bg};border-radius:4px;'>"
    . "<strong>{$checks_ok}</strong> OK, "
    . "<strong>{$checks_warn}</strong> avertissements, "
    . "<strong>{$checks_fail}</strong> echecs."
    . "</div>";

if ($checks_fail === 0 && $checks_warn === 0) {
    echo "<p style='margin-top:12px;'>Le nettoyage s'est deroule correctement. Pensez a :"
        . "<ul>"
        . "<li>Reindexer votre plugin SEO (Yoast, RankMath...)</li>"
        . "<li>Vider le cache de votre site (plugin de cache, CDN)</li>"
        . "<li>Verifier quelques pages au hasard sur le frontend</li>"
        . "</ul></p>";
}

echo '</div>';
