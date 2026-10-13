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
 * Même nombre par page que la grille du modèle de catégorie ($CAT_PER_PAGE de category.code.php, 60 depuis le
 * 2026-10-13) : sinon WordPress compte ses pages avec le réglage « Lecture » (10) et les pages au-delà de la
 * dernière page de la grille répondent 200 avec une grille vide au lieu d'une 404 (cas de /maison/page/150/).
 * Seulement les catégories : les autres archives gardent le réglage « Lecture ».
 *
 * WPCodeBox : coller tel quel, exécuter sur « Everywhere ».
 */

add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->is_category() ) {
        $query->set( 'post_type', array( 'comparatif', 'liste' ) );
        $query->set( 'posts_per_page', 60 );
    }
} );
