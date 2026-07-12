<?php
/**
 * MU-Plugin : AJAX endpoint for the full ranking table.
 *
 * Copy this file to wp-content/mu-plugins/mt-ranking-ajax.php
 * (mu-plugins are auto-loaded, no activation needed).
 *
 * The ranking is NOT rendered in the initial DOM (SEO: Google only scrapes
 * the first ~N characters; 500 products before editorial content would hurt).
 * Instead, top5-resume.code.php outputs a button that triggers an XHR here,
 * which returns the HTML rows in JSON.
 */

add_action( 'wp_ajax_mt_ranking',        'mt_ranking_ajax_handler' );
add_action( 'wp_ajax_nopriv_mt_ranking', 'mt_ranking_ajax_handler' );

function mt_ranking_ajax_handler() {
  check_ajax_referer( 'mt_ranking', '_n' );

  $page_id = isset( $_GET['page_id'] ) ? (int) $_GET['page_id'] : 0;
  if ( $page_id <= 0 ) {
    wp_send_json_error( 'missing page_id' );
  }

  /* ---- helpers (same as top5-resume, guarded) ---- */
  if ( ! function_exists( 'mt5_num' ) ) {
    function mt5_num( $v ) {
      $v = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $v );
      $v = str_replace( ',', '.', $v );
      return is_numeric( $v ) ? (float) $v : 0.0;
    }
  }

  /* ---- get top-5 IDs (for highlighting) ---- */
  $page_tv = function_exists( 'get_all_template_variables' )
    ? get_all_template_variables( $page_id ) : array();
  $top_ids = isset( $page_tv['top_avis_ids'] ) && is_array( $page_tv['top_avis_ids'] )
    ? $page_tv['top_avis_ids'] : array();
  if ( empty( $top_ids ) ) {
    $rel = get_field( 'mltv5_best_products', $page_id );
    if ( is_array( $rel ) ) {
      foreach ( $rel as $r ) { $top_ids[] = is_object( $r ) ? $r->ID : (int) $r; }
    }
  }
  $top5_set = array_flip( array_map( 'intval', $top_ids ) );

  /* ---- query all scored avis (same logic as mt_all_scored_avis) ---- */
  $prod_terms = get_the_terms( $page_id, 'post-type-produit' );
  if ( ! is_array( $prod_terms ) || empty( $prod_terms ) ) {
    wp_send_json_success( array( 'html' => '', 'count' => 0 ) );
  }
  $prod_ids = wp_list_pluck( $prod_terms, 'term_id' );

  $tax_q = array( array( 'taxonomy' => 'post-type-produit', 'terms' => $prod_ids ) );
  $attr_terms = get_the_terms( $page_id, 'post-type-attribut' );
  if ( is_array( $attr_terms ) && ! empty( $attr_terms ) ) {
    $tax_q['relation'] = 'AND';
    $tax_q[] = array( 'taxonomy' => 'post-type-attribut', 'terms' => wp_list_pluck( $attr_terms, 'term_id' ), 'operator' => 'AND' );
  }

  $q = new WP_Query( array(
    'post_type'              => 'avis',
    'post_status'            => 'publish',
    'tax_query'              => $tax_q,
    'posts_per_page'         => -1,
    'fields'                 => 'ids',
    'no_found_rows'          => true,
    'update_post_meta_cache' => true,
    'update_post_term_cache' => false,
  ) );

  $items    = array();
  $seen_ids = array();
  foreach ( $q->posts as $aid ) {
    $aid = (int) $aid;
    if ( isset( $seen_ids[ $aid ] ) ) { continue; }
    $seen_ids[ $aid ] = true;
    if ( get_post_status( $aid ) !== 'publish' ) { continue; }
    $raw = get_field( 'mltv5_score_recent', $aid );
    $s10 = round( mt5_num( $raw ) / 10, 1 );
    if ( $s10 <= 0 ) { continue; }
    $brand = trim( (string) get_field( 'mltv5_marque_du_produit', $aid ) );
    $model = trim( (string) get_field( 'mltv5_modele_du_produit', $aid ) );
    $name  = $model !== '' ? $model : get_the_title( $aid );
    $items[] = array( 'id' => $aid, 'brand' => $brand, 'name' => $name, 'score' => $s10 );
  }

  usort( $items, function ( $a, $b ) {
    if ( $a['score'] !== $b['score'] ) { return ( $b['score'] > $a['score'] ) ? 1 : -1; }
    return strcmp( $a['name'], $b['name'] );
  } );

  foreach ( $items as $i => &$it ) { $it['rank'] = $i + 1; }
  unset( $it );

  $is_admin = current_user_can( 'edit_posts' );

  /* ---- build HTML rows ---- */
  $html = '';
  foreach ( $items as $ar ) {
    $ar_top5 = isset( $top5_set[ $ar['id'] ] );
    $cls     = 't5-ar-row' . ( $ar_top5 ? ' is-top5' : '' );
    $edit    = '';
    if ( $is_admin ) {
      $edit = ' <a class="t5-ar-edit" href="' . esc_url( get_edit_post_link( $ar['id'] ) ) . '" target="_blank" title="Modifier (ID ' . $ar['id'] . ')">&#9998;</a>';
    }
    $brand_html = '';
    if ( $ar['brand'] !== '' ) {
      $brand_html = '<span class="t5-ar-brand">' . esc_html( $ar['brand'] ) . '</span> ';
    }
    $html .= '<div class="' . $cls . '">'
      . '<span class="t5-ar-rank">' . (int) $ar['rank'] . '</span>'
      . '<span class="t5-ar-name">' . $brand_html . esc_html( $ar['name'] ) . $edit . '</span>'
      . '<span class="t5-ar-score">' . esc_html( number_format( $ar['score'], 1, ',', '' ) ) . '<small>/10</small></span>'
      . '</div>';
  }

  wp_send_json_success( array( 'html' => $html, 'count' => count( $items ) ) );
}
