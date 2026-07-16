<?php
/**
 * Fix : empêche WordPress de rediriger les pages paginées des archives
 * de catégories vers la page d'accueil.
 *
 * Cause fréquente : redirect_canonical() considère que /page/2/ n'est
 * pas valide pour une archive et envoie un 301 vers la home.
 * Ça arrive souvent quand Bricks gère le template d'archive (la main
 * query WP ne voit pas les posts car c'est une WP_Query custom dans
 * le code element).
 *
 * WPCodeBox : coller tel quel, exécuter sur « Everywhere ».
 */

/* ---------------------------------------------------------------
   1. Empêcher redirect_canonical sur les archives catégories paginées
   --------------------------------------------------------------- */
add_filter( 'redirect_canonical', function ( $redirect_url, $requested_url ) {
    if ( is_category() && is_paged() ) {
        return false;
    }
    return $redirect_url;
}, 10, 2 );

/* ---------------------------------------------------------------
   2. S'assurer que la main query WP reconnaît la pagination
      (utile quand Bricks utilise un template d'archive custom)
   --------------------------------------------------------------- */
add_action( 'pre_get_posts', function ( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_category() ) {
        $paged = get_query_var( 'paged' );
        if ( $paged > 1 ) {
            $query->set( 'paged', $paged );
        }
    }
} );
