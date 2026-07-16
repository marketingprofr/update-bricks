<?php
/**
 * DIAGNOSTIC — Intercepte TOUTES les redirections sur les pages
 * de catégorie paginées et affiche la source exacte.
 *
 * WPCodeBox : coller tel quel, exécuter sur « Everywhere ».
 * Accéder à : https://meilleurtest.fr/high-tech/audio/page/2/
 * → Au lieu de rediriger, la page affichera un rapport de diagnostic.
 *
 * ⚠️ TEMPORAIRE — désactiver après diagnostic.
 */

/* On intercepte TRÈS TÔT (priorité 1) pour tout capter */

/* --- 1. Intercepter redirect_canonical ---------------------------------- */
add_filter( 'redirect_canonical', function ( $redirect_url, $requested_url ) {
    if ( is_paged() && ( is_category() || is_archive() || is_tax() ) ) {
        $GLOBALS['_mt_diag_redirect_canonical'] = array(
            'from' => $requested_url,
            'to'   => $redirect_url,
        );
        return false; // bloquer la redirection pour voir la page
    }
    return $redirect_url;
}, 1, 2 );

/* --- 2. Intercepter wp_redirect / wp_safe_redirect ---------------------- */
add_filter( 'wp_redirect', function ( $location, $status ) {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, '/page/' ) !== false ) {
        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );
        $stack = array();
        foreach ( $trace as $f ) {
            $file = isset( $f['file'] ) ? basename( $f['file'] ) : '?';
            $line = isset( $f['line'] ) ? $f['line'] : '?';
            $func = isset( $f['function'] ) ? $f['function'] : '?';
            $stack[] = "{$file}:{$line} → {$func}()";
        }
        $GLOBALS['_mt_diag_wp_redirect'] = array(
            'to'     => $location,
            'status' => $status,
            'stack'  => $stack,
        );
        return false; // bloquer
    }
    return $location;
}, 1, 2 );

/* --- 3. Intercepter les header() PHP directs (via output buffer) -------- */
add_action( 'send_headers', function () {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, '/page/' ) !== false ) {
        header_remove( 'Location' );
    }
}, 999 );

/* --- 4. Afficher le rapport en pied de page ----------------------------- */
add_action( 'wp_footer', function () {
    $uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
    if ( strpos( $uri, '/page/' ) === false ) { return; }

    global $wp_query;
    $diag = array();

    $diag['url']          = $uri;
    $diag['is_category']  = is_category() ? 'OUI' : 'NON';
    $diag['is_archive']   = is_archive() ? 'OUI' : 'NON';
    $diag['is_tax']       = is_tax() ? 'OUI' : 'NON';
    $diag['is_paged']     = is_paged() ? 'OUI' : 'NON';
    $diag['is_404']       = is_404() ? 'OUI ← PROBLÈME' : 'NON';
    $diag['is_home']      = is_home() ? 'OUI ← PROBLÈME' : 'NON';
    $diag['is_front_page']= is_front_page() ? 'OUI ← PROBLÈME' : 'NON';
    $diag['paged_var']    = get_query_var( 'paged' );
    $diag['page_var']     = get_query_var( 'page' );
    $diag['found_posts']  = isset( $wp_query ) ? $wp_query->found_posts : '?';
    $diag['max_num_pages'] = isset( $wp_query ) ? $wp_query->max_num_pages : '?';
    $diag['post_count']   = isset( $wp_query ) ? $wp_query->post_count : '?';
    $diag['queried_object'] = '';
    if ( isset( $wp_query ) && $wp_query->get_queried_object() ) {
        $obj = $wp_query->get_queried_object();
        $diag['queried_object'] = isset( $obj->name ) ? $obj->taxonomy . ':' . $obj->slug : get_class( $obj );
    }

    $rc = isset( $GLOBALS['_mt_diag_redirect_canonical'] ) ? $GLOBALS['_mt_diag_redirect_canonical'] : null;
    $wr = isset( $GLOBALS['_mt_diag_wp_redirect'] ) ? $GLOBALS['_mt_diag_wp_redirect'] : null;

    echo '<div style="position:fixed;bottom:0;left:0;right:0;z-index:999999;background:#1a1a2e;color:#e0e0e0;font:13px/1.6 monospace;padding:20px 24px;max-height:60vh;overflow:auto;border-top:3px solid #e94560;">';
    echo '<h3 style="color:#e94560;margin:0 0 12px;">DIAGNOSTIC PAGINATION CATÉGORIE</h3>';

    echo '<table style="border-collapse:collapse;width:100%;">';
    foreach ( $diag as $k => $v ) {
        $color = ( strpos( (string) $v, 'PROBLÈME' ) !== false ) ? '#e94560' : '#e0e0e0';
        echo "<tr><td style='padding:3px 12px 3px 0;color:#aaa;white-space:nowrap;'>{$k}</td><td style='padding:3px 0;color:{$color};font-weight:bold;'>{$v}</td></tr>";
    }
    echo '</table>';

    if ( $rc ) {
        echo '<h4 style="color:#f5a623;margin:16px 0 6px;">⚠️ redirect_canonical INTERCEPTÉ (= la cause probable)</h4>';
        echo '<p style="margin:0;">Voulait rediriger <code>' . esc_html( $rc['from'] ) . '</code><br>→ <code style="color:#e94560;">' . esc_html( $rc['to'] ) . '</code></p>';
        echo '<p style="color:#6dd6a8;margin:4px 0 0;">✓ Bloqué par ce diagnostic. Le fix <code>fix-category-pagination.php</code> corrigera ça.</p>';
    } else {
        echo '<p style="color:#6dd6a8;margin:12px 0 4px;">✓ redirect_canonical : aucune redirection captée sur cette URL.</p>';
    }

    if ( $wr ) {
        echo '<h4 style="color:#f5a623;margin:16px 0 6px;">⚠️ wp_redirect INTERCEPTÉ</h4>';
        echo '<p style="margin:0;">Vers : <code style="color:#e94560;">' . esc_html( $wr['to'] ) . '</code> (HTTP ' . (int) $wr['status'] . ')</p>';
        echo '<p style="margin:4px 0 0;">Stack trace :</p><ol style="margin:4px 0;padding-left:20px;">';
        foreach ( $wr['stack'] as $s ) {
            echo '<li style="color:#ccc;">' . esc_html( $s ) . '</li>';
        }
        echo '</ol>';
    } else {
        echo '<p style="color:#6dd6a8;">✓ wp_redirect : aucune redirection captée.</p>';
    }

    if ( ! $rc && ! $wr ) {
        if ( is_404() ) {
            echo '<h4 style="color:#e94560;margin:16px 0 6px;">🔴 WordPress traite cette URL comme un 404</h4>';
            echo '<p>La main query WP ne trouve rien → WP renvoie un 404 → un thème ou plugin le redirige vers la home.</p>';
            echo '<p>Causes possibles :<br>';
            echo '• La main query WP a <code>posts_per_page</code> trop bas ou <code>paged</code> dépasse <code>max_num_pages</code><br>';
            echo '• Le post type des comparatifs n\'est pas dans la requête d\'archive de cette taxonomie<br>';
            echo '• Bricks ne passe pas le bon template pour cette archive</p>';
        } elseif ( is_home() || is_front_page() ) {
            echo '<h4 style="color:#e94560;margin:16px 0 6px;">🔴 WordPress traite cette URL comme la page d\'accueil</h4>';
            echo '<p>Les rewrite rules ne reconnaissent pas le pattern <code>/categorie/page/N/</code>.<br>';
            echo 'Essayez : <strong>Réglages → Permaliens → Enregistrer</strong> (flush des rewrite rules).</p>';
        }
    }

    echo '<p style="color:#888;margin:12px 0 0;font-size:11px;">Snippet temporaire — désactiver après diagnostic.</p>';
    echo '</div>';
}, 9999 );
