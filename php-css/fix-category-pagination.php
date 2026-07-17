<?php
/**
 * Fix : pagination des archives de catégories (/page/2/, /page/3/…)
 *
 * Problème : la main query WP ne cherche que le type « post » →
 * 0 résultats pour les catégories qui ne contiennent que des
 * « comparatif » et « liste » → WordPress déclare un 404 →
 * Rank Math redirige le 404 vers la homepage.
 *
 * Solution : injecter les bons post types dans la main query
 * des archives de catégories pour que WordPress trouve les posts
 * et reconnaisse la pagination.
 *
 * WPCodeBox : coller tel quel, exécuter sur « Everywhere ».
 */

add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->is_category() ) {
        $query->set( 'post_type', array( 'comparatif', 'liste' ) );
    }
} );
