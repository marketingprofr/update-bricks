/**
 * PHASE 5 : VÉRIFICATION POST-OPÉRATION
 * Mode lecture seule — ne modifie rien.
 * Vérifie que le nettoyage s'est bien passé.
 *
 * Utilisation :
 *   1. Coller ce code dans un snippet PHP WPCodeBox (à la suite de la balise <?php)
 *   2. Activer le snippet
 *   3. Visiter : https://votre-site.fr/?catcleanup=verify  (connecté en admin)
 *   4. Désactiver le snippet après usage
 */

// Marqueur de chargement pour le diagnostic ?catcleanup=ping
$GLOBALS['catcleanup_loaded'][] = '05-verify';

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

// ─── Exécution à la demande uniquement ────────────────────────────────
$catcleanup_verify = function () {
    if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'verify') return;
    catcleanup_require_admin();

    global $wpdb;
    if (function_exists('set_time_limit')) @set_time_limit(120);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1000px;margin:20px auto;padding:20px;'>";
    echo '<h1>Verification post-nettoyage</h1>';

    $checks_ok   = 0;
    $checks_warn = 0;
    $checks_fail = 0;

    // ─── CHECK 1 : Profondeur maximale ─────────────────────
    echo '<h2>1. Profondeur maximale des categories</h2>';

    $by_id  = catcleanup_load_categories();
    $depths = [];
    foreach ($by_id as $id => $c) {
        catcleanup_calc_depth($id, $by_id, $depths);
    }

    $max_depth   = empty($depths) ? 0 : max($depths);
    $deep_cats   = array_filter($depths, function ($d) { return $d > 2; });
    $default_cat = (int) get_option('default_category');

    if ($max_depth <= 2) {
        echo "<p style='color:green;'>OK — Profondeur maximale : {$max_depth}. Toutes les categories sont au niveau 1 ou 2.</p>";
        $checks_ok++;
    } else {
        $only_default = (count($deep_cats) === 1 && isset($deep_cats[$default_cat]));
        $color        = $only_default ? 'orange' : 'red';
        echo "<p style='color:{$color};'>" . ($only_default ? 'ATTENTION' : 'ECHEC')
            . " — Profondeur maximale : {$max_depth}. "
            . count($deep_cats) . ' categorie(s) de niveau 3+ :</p><ul>';
        $shown = 0;
        foreach ($deep_cats as $id => $d) {
            if (++$shown > 50) { echo '<li>... et ' . (count($deep_cats) - 50) . ' autres</li>'; break; }
            $is_def = $id === $default_cat ? ' — categorie par defaut (conservee volontairement, deplacez-la manuellement)' : '';
            echo '<li>' . esc_html($by_id[$id]['name']) . " (ID: {$id}, niveau: {$d}){$is_def}</li>";
        }
        echo '</ul>';
        $only_default ? $checks_warn++ : $checks_fail++;
    }

    // ─── CHECK 2 : Nombre de catégories restantes ──────────
    echo '<h2>2. Nombre de categories restantes</h2>';

    $total = count($by_id);
    $l1    = count(array_filter($depths, function ($d) { return $d === 1; }));
    $l2    = count(array_filter($depths, function ($d) { return $d === 2; }));

    echo "<p>{$total} categories au total : {$l1} de niveau 1, {$l2} de niveau 2.</p>";
    $checks_ok++;

    // ─── CHECK 3 : Posts sans catégorie ─────────────────────
    echo '<h2>3. Posts publies sans categorie</h2>';

    $post_types = (array) $wpdb->get_col("
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
            echo "<p style='color:orange;'>ATTENTION — {$orphan_count} posts de type <strong>" . esc_html($pt) . '</strong> sans categorie.</p>';
            $orphan_total += $orphan_count;
        }
    }

    if ($orphan_total === 0) {
        echo "<p style='color:green;'>OK — Tous les posts publies ont au moins une categorie.</p>";
        $checks_ok++;
    } else {
        echo "<p style='color:orange;'>ATTENTION — {$orphan_total} posts publies sans categorie au total. "
            . 'Normal pour les post types qui n\'utilisent pas la taxonomie "category". '
            . 'Comparez avec l\'etat AVANT nettoyage : seuls les posts qui ont PERDU leur categorie posent probleme.</p>';
        $checks_warn++;
    }

    // ─── CHECK 4 : Compteurs de termes ──────────────────────
    // Reproduit la sémantique WordPress : seuls les posts publiés des
    // post types rattachés à la taxonomie sont comptés.
    echo '<h2>4. Coherence des compteurs de termes</h2>';

    $tax          = get_taxonomy('category');
    $object_types = ($tax && !empty($tax->object_type)) ? (array) $tax->object_type : ['post'];
    $types_in     = "'" . implode("','", array_map('esc_sql', $object_types)) . "'";

    $mismatches = $wpdb->get_results("
        SELECT tt.term_id, t.name, tt.count AS stored_count,
               COALESCE(rc.real_count, 0) AS real_count
        FROM {$wpdb->term_taxonomy} tt
        JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
        LEFT JOIN (
            SELECT tr.term_taxonomy_id, COUNT(*) AS real_count
            FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->posts} p ON p.ID = tr.object_id
            WHERE p.post_status = 'publish' AND p.post_type IN ({$types_in})
            GROUP BY tr.term_taxonomy_id
        ) rc ON rc.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tt.taxonomy = 'category'
        AND tt.count != COALESCE(rc.real_count, 0)
        LIMIT 20
    ", ARRAY_A);

    if (empty($mismatches)) {
        echo "<p style='color:green;'>OK — Tous les compteurs de termes sont corrects.</p>";
        $checks_ok++;
    } else {
        echo "<p style='color:orange;'>ATTENTION — " . count($mismatches) . ' categorie(s) avec un compteur divergent '
            . '(post types compares : ' . esc_html(implode(', ', $object_types)) . ') :</p>';
        echo '<table style="border-collapse:collapse;">';
        echo '<tr style="background:#f0f0f0;">'
            . '<th style="padding:6px;border:1px solid #ddd;">ID</th>'
            . '<th style="padding:6px;border:1px solid #ddd;">Nom</th>'
            . '<th style="padding:6px;border:1px solid #ddd;">Stocke</th>'
            . '<th style="padding:6px;border:1px solid #ddd;">Reel</th></tr>';
        foreach ($mismatches as $m) {
            echo '<tr>'
                . "<td style='padding:6px;border:1px solid #ddd;'>{$m['term_id']}</td>"
                . "<td style='padding:6px;border:1px solid #ddd;'>" . esc_html($m['name']) . '</td>'
                . "<td style='padding:6px;border:1px solid #ddd;'>{$m['stored_count']}</td>"
                . "<td style='padding:6px;border:1px solid #ddd;'>{$m['real_count']}</td>"
                . '</tr>';
        }
        echo '</table>';
        echo '<p>Un petit ecart peut etre normal si des post types utilisent la categorie sans etre declares '
            . 'sur la taxonomie. Sinon, relancez le recalcul des compteurs (inclus dans 04-apply.php).</p>';
        $checks_warn++;
    }

    // ─── CHECK 5 : Catégories orphelines ────────────────────
    echo '<h2>5. Categories orphelines</h2>';

    $orphan_cats = array_filter($by_id, function ($c) use ($by_id) {
        return $c['parent'] !== 0 && !isset($by_id[$c['parent']]);
    });

    if (empty($orphan_cats)) {
        echo "<p style='color:green;'>OK — Aucune categorie orpheline.</p>";
        $checks_ok++;
    } else {
        echo "<p style='color:red;'>ECHEC — " . count($orphan_cats) . ' categorie(s) avec un parent inexistant :</p><ul>';
        foreach ($orphan_cats as $c) {
            echo '<li>' . esc_html($c['name']) . " (ID: {$c['term_id']}, parent: {$c['parent']})</li>";
        }
        echo '</ul>';
        $checks_fail++;
    }

    // ─── CHECK 6 : Relations vers des termes supprimés ──────
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
            . 'Nettoyage possible via phpMyAdmin : '
            . "<code>DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->term_taxonomy} tt "
            . 'ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tt.term_taxonomy_id IS NULL</code></p>';
        $checks_fail++;
    }

    // ─── Bilan ──────────────────────────────────────────────
    echo '<h2>Bilan</h2>';

    $bg = $checks_fail > 0 ? '#ffebee' : ($checks_warn > 0 ? '#fff3cd' : '#e8f5e9');
    echo "<div style='padding:16px;background:{$bg};border-radius:4px;'>"
        . "<strong>{$checks_ok}</strong> OK, "
        . "<strong>{$checks_warn}</strong> avertissements, "
        . "<strong>{$checks_fail}</strong> echecs."
        . '</div>';

    if ($checks_fail === 0) {
        echo '<p style="margin-top:12px;">Pensez a :</p>'
            . '<ul>'
            . '<li>Reindexer votre plugin SEO (Yoast, RankMath...)</li>'
            . '<li>Vider le cache du site (plugin de cache, CDN)</li>'
            . '<li>Verifier quelques pages au hasard sur le frontend</li>'
            . '<li>Desactiver tous les snippets catcleanup dans WPCodeBox</li>'
            . '<li>Supprimer les fichiers CSV/SQL generes dans wp-content/uploads/</li>'
            . '</ul>';
    }

    echo '</div>';
    exit;
};

if (did_action('init')) {
    $catcleanup_verify();
} else {
    add_action('init', $catcleanup_verify, 0);
}
