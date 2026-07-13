/**
 * PHASE 6 : RÉATTRIBUTION INTERACTIVE DES CATÉGORIES VIA LA TAXONOMIE PRODUIT
 * Page de réparation : liste chaque terme produit ayant des posts SANS
 * catégorie, avec :
 *   - le nombre de posts sans catégorie / total portant ce terme
 *   - la catégorie DÉTECTÉE (celle que portent majoritairement les autres
 *     posts du même terme produit)
 *   - une case à cocher pour valider la détection
 *   - un champ libre pour imposer une autre catégorie (ID, slug ou
 *     chemin "Parent > Enfant")
 *
 * Cliquez sur "Appliquer" : chaque terme validé attribue la catégorie
 * choisie UNIQUEMENT aux posts de ce terme qui n'ont AUCUNE catégorie
 * (tous post types confondus). Purement additif, relançable sans danger.
 *
 * Utilisation :
 *   1. Coller ce code dans un snippet PHP WPCodeBox (à la suite de la balise <?php)
 *   2. Vérifier $catcleanup_produit_tax (slug exact de votre taxonomie)
 *   3. Activer le snippet et visiter : https://votre-site.fr/?catcleanup=remap
 *   4. Cocher/corriger, cliquer sur Appliquer, recommencer si besoin
 *   5. Désactiver le snippet après usage
 */

// Marqueur de chargement pour le diagnostic ?catcleanup=ping
$GLOBALS['catcleanup_loaded'][] = '06-remap';

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

// ─── CONFIGURATION ────────────────────────────────────────────────────
// Slug exact de votre taxonomie produit
$catcleanup_produit_tax = 'post-type-produit';
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

if (!function_exists('catcleanup_normalize')) {
    // Minuscules, sans accents, sans ponctuation, pluriels simples retirés
    function catcleanup_normalize($s) {
        $s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'œ' => 'oe', 'æ' => 'ae', 'ñ' => 'n',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        $s = preg_replace('/s\b/', '', $s); // casques -> casque
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}

// ─── Exécution à la demande uniquement ────────────────────────────────
$catcleanup_remap_run = function () use ($catcleanup_produit_tax) {
    if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'remap') return;
    catcleanup_require_admin();

    global $wpdb;
    $PRODUIT_TAX = (string) $catcleanup_produit_tax;

    if (function_exists('set_time_limit')) @set_time_limit(600);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    // ─── Chargement des catégories ──────────────────────────
    $by_id = catcleanup_load_categories();

    $cat_by_slug = [];
    $cat_by_norm = [];
    foreach ($by_id as $id => $c) {
        $cat_by_slug[$c['slug']] = $id;
        $cat_by_norm[catcleanup_normalize($c['name'])][] = $id;
    }

    // Résout "152", "audio", "Audio" ou "High-Tech > Audio" vers un term_id
    $resolve_category = function ($ref) use (&$by_id, &$cat_by_slug, &$cat_by_norm) {
        $ref = trim((string) $ref);
        if ($ref === '') return ['error' => 'valeur vide'];
        if (ctype_digit($ref)) {
            $id = (int) $ref;
            return isset($by_id[$id]) ? ['id' => $id] : ['error' => "aucune categorie avec l'ID {$id}"];
        }
        if (strpos($ref, '>') !== false || strpos($ref, '/') !== false) {
            $parts = array_values(array_filter(array_map('trim', preg_split('/[>\/]/', $ref))));
            if (count($parts) !== 2) return ['error' => "chemin invalide \"{$ref}\" (attendu : Parent > Enfant)"];
            list($p_ref, $c_ref) = $parts;
            $p_id = null;
            if (isset($cat_by_slug[$p_ref])) $p_id = $cat_by_slug[$p_ref];
            else {
                $n = catcleanup_normalize($p_ref);
                if (isset($cat_by_norm[$n]) && count($cat_by_norm[$n]) === 1) $p_id = $cat_by_norm[$n][0];
            }
            if ($p_id === null) return ['error' => "parent \"{$p_ref}\" introuvable"];
            $c_norm = catcleanup_normalize($c_ref);
            foreach ($by_id as $id => $c) {
                if ($c['parent'] === $p_id && ($c['slug'] === $c_ref || catcleanup_normalize($c['name']) === $c_norm)) {
                    return ['id' => $id];
                }
            }
            return ['error' => "\"{$c_ref}\" introuvable sous \"{$p_ref}\". Si elle a ete supprimee, recreez-la dans l'admin WordPress puis utilisez son ID"];
        }
        if (isset($cat_by_slug[$ref])) return ['id' => $cat_by_slug[$ref]];
        $n = catcleanup_normalize($ref);
        if (isset($cat_by_norm[$n])) {
            if (count($cat_by_norm[$n]) === 1) return ['id' => $cat_by_norm[$n][0]];
            $cands = array_map(function ($id) use (&$by_id) {
                return $by_id[$id]['name'] . ' (ID ' . $id . ')';
            }, $cat_by_norm[$n]);
            return ['error' => "\"{$ref}\" est ambigu : " . implode(', ', $cands) . " — utilisez l'ID"];
        }
        return ['error' => "categorie \"{$ref}\" introuvable (ni ID, ni slug, ni nom)"];
    };

    // ─── Chargement des termes produit ──────────────────────
    $produit_terms = $wpdb->get_results($wpdb->prepare("
        SELECT t.term_id, t.name, t.slug, tt.term_taxonomy_id, tt.count
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = %s
    ", $PRODUIT_TAX), ARRAY_A);

    echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:1300px;margin:20px auto;padding:20px;'>";
    echo "<h1>Reattribution des categories via \"" . esc_html($PRODUIT_TAX) . "\"</h1>";

    if (empty($produit_terms)) {
        $taxos = (array) $wpdb->get_col("SELECT DISTINCT taxonomy FROM {$wpdb->term_taxonomy} ORDER BY taxonomy");
        echo "<p style='color:red;'><strong>Aucun terme trouve pour la taxonomie \"" . esc_html($PRODUIT_TAX) . "\".</strong> "
            . 'Verifiez <code>$catcleanup_produit_tax</code> en haut du snippet.</p>';
        echo '<p>Taxonomies presentes dans la base : ' . esc_html(implode(', ', $taxos)) . '</p>';
        echo '</div>';
        exit;
    }

    $prod_by_ttid = [];
    foreach ($produit_terms as $pt) {
        $pt['term_id']          = (int) $pt['term_id'];
        $pt['term_taxonomy_id'] = (int) $pt['term_taxonomy_id'];
        $pt['count']            = (int) $pt['count'];
        $prod_by_ttid[$pt['term_taxonomy_id']] = $pt;
    }
    $produit_in = implode(',', array_keys($prod_by_ttid));

    // ─── Traitement du formulaire (POST) ────────────────────
    if (!empty($_POST['catcleanup_do'])) {
        $nonce_ok = isset($_POST['_catcleanup_nonce'])
            && wp_verify_nonce($_POST['_catcleanup_nonce'], 'catcleanup_remap');

        if (!$nonce_ok) {
            echo "<p style='padding:12px;background:#ffebee;border:1px solid #f44336;border-radius:4px;'>"
                . 'Session expiree (nonce invalide) — rechargez la page et recommencez.</p>';
        } else {
            $apply    = isset($_POST['apply']) && is_array($_POST['apply']) ? $_POST['apply'] : [];
            $manual   = isset($_POST['manual']) && is_array($_POST['manual']) ? $_POST['manual'] : [];
            $detected = isset($_POST['detected']) && is_array($_POST['detected']) ? $_POST['detected'] : [];

            $done          = [];
            $post_errors   = [];
            $affected_tt   = [];
            $total_added   = 0;

            $all_ttids = array_unique(array_merge(array_keys($apply), array_keys($manual)));
            foreach ($all_ttids as $pt_ttid_raw) {
                $pt_ttid = (int) $pt_ttid_raw;
                if (!isset($prod_by_ttid[$pt_ttid])) continue;
                $prod = $prod_by_ttid[$pt_ttid];

                // Choix : champ manuel prioritaire, sinon détection validée
                $cat_id = null;
                $manual_val = isset($manual[$pt_ttid_raw]) ? trim(wp_unslash((string) $manual[$pt_ttid_raw])) : '';
                if ($manual_val !== '') {
                    $res = $resolve_category($manual_val);
                    if (isset($res['error'])) {
                        $post_errors[] = esc_html($prod['name']) . ' : ' . esc_html($res['error']);
                        continue;
                    }
                    $cat_id = $res['id'];
                } elseif (!empty($apply[$pt_ttid_raw])) {
                    $det = isset($detected[$pt_ttid_raw]) ? (int) $detected[$pt_ttid_raw] : 0;
                    if ($det > 0 && isset($by_id[$det])) $cat_id = $det;
                }
                if ($cat_id === null) continue;

                $cat_ttid = (int) $by_id[$cat_id]['term_taxonomy_id'];

                // Attribue la catégorie UNIQUEMENT aux posts de ce terme
                // qui n'ont encore AUCUNE catégorie
                $added = (int) $wpdb->query("
                    INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order)
                    SELECT DISTINCT tr.object_id, {$cat_ttid}, 0
                    FROM {$wpdb->term_relationships} tr
                    JOIN {$wpdb->posts} p ON p.ID = tr.object_id
                    WHERE tr.term_taxonomy_id = {$pt_ttid}
                    AND NOT EXISTS (
                        SELECT 1 FROM {$wpdb->term_relationships} tr2
                        JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
                        WHERE tr2.object_id = tr.object_id AND tt2.taxonomy = 'category'
                    )
                ");
                $total_added   += $added;
                $affected_tt[]  = $cat_ttid;
                $done[]         = esc_html($prod['name']) . ' → ' . esc_html(catcleanup_full_path($cat_id, $by_id)) . " : +{$added} posts";
            }

            if (!empty($affected_tt)) {
                wp_update_term_count_now(array_values(array_unique($affected_tt)), 'category');
                clean_taxonomy_cache('category');
                wp_cache_flush();
            }

            if (!empty($done)) {
                echo "<div style='padding:12px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;margin-bottom:16px;'>"
                    . "<strong>{$total_added} posts categorises :</strong><ul style='margin:8px 0 0 0;'>";
                foreach ($done as $d) echo "<li>{$d}</li>";
                echo '</ul></div>';
            }
            if (!empty($post_errors)) {
                echo "<div style='padding:12px;background:#ffebee;border:1px solid #f44336;border-radius:4px;margin-bottom:16px;'>"
                    . '<strong>Erreurs (lignes ignorees) :</strong><ul style="margin:8px 0 0 0;">';
                foreach ($post_errors as $e) echo "<li>{$e}</li>";
                echo '</ul></div>';
            }
            if (empty($done) && empty($post_errors)) {
                echo "<p style='padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'>Aucune ligne validee.</p>";
            }
        }
    }

    // ─── État : posts sans catégorie par terme produit ──────
    // Total de posts par terme
    $totals = [];
    $rows = $wpdb->get_results("
        SELECT tr.term_taxonomy_id AS ttid, COUNT(DISTINCT tr.object_id) AS n
        FROM {$wpdb->term_relationships} tr
        JOIN {$wpdb->posts} p ON p.ID = tr.object_id
        WHERE tr.term_taxonomy_id IN ({$produit_in})
        GROUP BY tr.term_taxonomy_id
    ", ARRAY_A);
    foreach ((array) $rows as $r) $totals[(int) $r['ttid']] = (int) $r['n'];

    // Posts sans catégorie par terme
    $uncat = [];
    $rows = $wpdb->get_results("
        SELECT tr.term_taxonomy_id AS ttid, COUNT(DISTINCT tr.object_id) AS n
        FROM {$wpdb->term_relationships} tr
        JOIN {$wpdb->posts} p ON p.ID = tr.object_id
        WHERE tr.term_taxonomy_id IN ({$produit_in})
        AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr2
            JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
            WHERE tr2.object_id = tr.object_id AND tt2.taxonomy = 'category'
        )
        GROUP BY tr.term_taxonomy_id
    ", ARRAY_A);
    foreach ((array) $rows as $r) $uncat[(int) $r['ttid']] = (int) $r['n'];

    // Termes à afficher : ceux qui ont au moins 1 post sans catégorie
    $display = [];
    foreach ($uncat as $ttid => $n) {
        if ($n > 0 && isset($prod_by_ttid[$ttid])) $display[] = $ttid;
    }
    usort($display, function ($a, $b) use (&$uncat) { return $uncat[$b] - $uncat[$a]; });

    $clean_terms = count($prod_by_ttid) - count($display);

    if (empty($display)) {
        echo "<div style='padding:16px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;'>"
            . '<strong>Rien a faire :</strong> tous les posts portant un terme produit ont au moins une categorie.'
            . '</div>';
    } else {
        echo '<p><strong>' . count($display) . '</strong> termes produit ont des posts sans categorie '
            . "({$clean_terms} termes deja propres). "
            . 'La categorie detectee est celle que portent deja les autres posts du meme terme.</p>';

        // ─── Formulaire ─────────────────────────────────────
        echo "<form method='post' action='?catcleanup=remap'>";
        echo wp_nonce_field('catcleanup_remap', '_catcleanup_nonce', true, false);
        echo "<input type='hidden' name='catcleanup_do' value='1'>";

        echo "<p><button type='submit' style='padding:10px 24px;background:#0073aa;color:#fff;border:none;border-radius:4px;font-size:15px;cursor:pointer;'>Appliquer les lignes validees</button> "
            . "<label style='margin-left:16px;'><input type='checkbox' onclick='document.querySelectorAll(\"input[name^=apply]\").forEach(c => c.checked = this.checked);' checked> tout cocher / decocher</label></p>";

        echo '<table style="border-collapse:collapse;width:100%;">';
        echo '<tr style="background:#f0f0f0;">'
            . '<th style="padding:8px;border:1px solid #ddd;">Terme produit</th>'
            . '<th style="padding:8px;border:1px solid #ddd;">Sans categorie</th>'
            . '<th style="padding:8px;border:1px solid #ddd;">Categorie detectee</th>'
            . '<th style="padding:8px;border:1px solid #ddd;">Valider</th>'
            . '<th style="padding:8px;border:1px solid #ddd;">Choix manuel (ID, slug ou Parent &gt; Enfant)</th></tr>';

        foreach ($display as $pt_ttid) {
            $prod  = $prod_by_ttid[$pt_ttid];
            $total = isset($totals[$pt_ttid]) ? $totals[$pt_ttid] : 0;
            $nu    = $uncat[$pt_ttid];

            // Catégorie majoritaire parmi les posts déjà classés de ce terme
            $det = $wpdb->get_results("
                SELECT tt2.term_id AS cat_id, COUNT(DISTINCT tr.object_id) AS n
                FROM {$wpdb->term_relationships} tr
                JOIN {$wpdb->term_relationships} trc ON trc.object_id = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = trc.term_taxonomy_id AND tt2.taxonomy = 'category'
                WHERE tr.term_taxonomy_id = {$pt_ttid}
                GROUP BY tt2.term_id
                ORDER BY n DESC
                LIMIT 1
            ", ARRAY_A);

            $det_id    = null;
            $det_label = '<em>aucune (aucun post de ce terme n\'est classe)</em>';
            if (!empty($det) && isset($by_id[(int) $det[0]['cat_id']])) {
                $det_id    = (int) $det[0]['cat_id'];
                $det_votes = (int) $det[0]['n'];
                $classified = max(1, $total - $nu);
                $det_label = '<strong>' . esc_html(catcleanup_full_path($det_id, $by_id)) . '</strong>'
                    . " <span style='color:#666;'>({$det_votes}/{$classified} posts classes, ID {$det_id})</span>";
            }

            $checkbox = $det_id !== null
                ? "<input type='checkbox' name='apply[{$pt_ttid}]' value='1' checked>"
                  . "<input type='hidden' name='detected[{$pt_ttid}]' value='{$det_id}'>"
                : '<span style="color:#999;">—</span>';

            echo '<tr>'
                . "<td style='padding:8px;border:1px solid #ddd;'>" . esc_html($prod['name']) . " <code>({$prod['slug']})</code></td>"
                . "<td style='padding:8px;border:1px solid #ddd;text-align:center;'><strong>{$nu}</strong>/{$total}</td>"
                . "<td style='padding:8px;border:1px solid #ddd;'>{$det_label}</td>"
                . "<td style='padding:8px;border:1px solid #ddd;text-align:center;'>{$checkbox}</td>"
                . "<td style='padding:8px;border:1px solid #ddd;'><input type='text' name='manual[{$pt_ttid}]' value='' placeholder='ex : 152, audio, High-Tech &gt; Audio' style='width:100%;box-sizing:border-box;'></td>"
                . '</tr>';
        }
        echo '</table>';

        echo "<p><button type='submit' style='padding:10px 24px;background:#0073aa;color:#fff;border:none;border-radius:4px;font-size:15px;cursor:pointer;'>Appliquer les lignes validees</button></p>";
        echo '</form>';

        echo "<p style='color:#666;'>Le champ manuel est prioritaire sur la case a cocher. "
            . 'Seuls les posts SANS AUCUNE categorie recoivent la categorie choisie — rien n\'est retire. '
            . 'Si la bonne categorie a ete supprimee, recreez-la dans Articles &rarr; Categories puis utilisez son ID ici.</p>';
    }

    // ─── Posts sans catégorie ET sans terme produit ─────────
    $lost = (int) $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$wpdb->posts} p
        WHERE p.post_status = 'publish'
        AND p.post_type NOT IN ('attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'wp_global_styles', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_font_face', 'wp_font_family')
        AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tr.object_id = p.ID AND tt.taxonomy = 'category'
        )
        AND NOT EXISTS (
            SELECT 1 FROM {$wpdb->term_relationships} tr3
            WHERE tr3.object_id = p.ID AND tr3.term_taxonomy_id IN ({$produit_in})
        )
    ");
    if ($lost > 0) {
        echo "<p style='padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'>"
            . "<strong>{$lost} posts publies</strong> n'ont ni categorie ni terme produit — cette page ne peut pas les traiter. "
            . 'Ils devront etre categorises a la main (ou via une autre taxonomie).</p>';
    }

    echo '</div>';
    exit;
};

if (did_action('init')) {
    $catcleanup_remap_run();
} else {
    add_action('init', $catcleanup_remap_run, 0);
}
