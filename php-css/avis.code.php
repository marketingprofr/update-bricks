<?php
/* =====================================================================
   FICHE PRODUIT / AVIS — avis.code.php
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (avis.css).
   ===================================================================== */

/* ═════════════════════════════════════════════════════════════════════
   1) ORDRE D'AFFICHAGE — colonne principale
   Réorganisez, commentez ou supprimez des lignes pour changer l'ordre.
   ═════════════════════════════════════════════════════════════════════ */
$FP_BLOCKS = array(
  'review',           // Notre avis (contenu éditorial)
  'comparatifs',      // Comparatifs où ce produit apparaît
  'pros_cons',        // Points forts / points faibles
  'audience',         // À qui s'adresse ce produit
  'price_history',    // Évolution du prix
  'specs',            // Fiche technique
  'alternatives',     // Alternatives budget (premium / pas cher)
  'vs',               // Face-à-face concurrent direct
  'carousel_similar', // Vous aimerez aussi
  'carousel_price',   // Dans la même gamme de prix
  'carousel_brand',   // Top produits de la marque
);

/* Blocs à position fixe (true = affiché, false = masqué) */
$FP_SHOW_HERO    = true;
$FP_SHOW_SIDEBAR = true;
$FP_SHOW_GUIDES  = false;

/* ═════════════════════════════════════════════════════════════════════
   2) CHAMPS ACF — modifier ici si les noms diffèrent
   ═════════════════════════════════════════════════════════════════════ */
$FP_BRAND       = 'mltv5_marque_du_produit';
$FP_MODEL       = 'mltv5_modele_du_produit';
$FP_SUBTITLE    = 'mltv5_sous_titre';
$FP_SUMMARY     = 'mltv5_resume_produit';
$FP_SCORE       = 'mltv5_score_recent';
$FP_SCORE_AVIS  = 'mltv5_score_avis_clients';
$FP_NB_AVIS     = 'mltv5_nombre_avis_clients';
$FP_PRICE       = 'mltv5_prix_indicatif';
$FP_ASIN        = 'mltv5_asin_amazon';
$FP_LINK_1      = 'mltv5_lien_du_produit_1';
$FP_LINK_2      = 'mltv5_lien_du_produit_2';
$FP_LINK_3      = 'mltv5_lien_du_produit_3';
$FP_TEXT_1      = 'mltv5_texte_du_bouton_1';
$FP_TEXT_2      = 'mltv5_texte_du_bouton_2';
$FP_TEXT_3      = 'mltv5_texte_du_bouton_3';
$FP_PROS        = 'mltv5_points_positifs_produit';
$FP_PROS_SUB    = 'mltv5_point_positif';
$FP_CONS        = 'mltv5_points_negatifs_produit';
$FP_CONS_SUB    = 'mltv5_point_negatif';
$FP_SPECS       = 'mltv5_caracteristiques_du_produit';
$FP_SPEC_LBL    = 'mltv5_caracteristique_produit';
$FP_SPEC_VAL    = 'mltv5_valeur_caracteristique_produit';
$FP_VERDICT     = 'mltv5_verdict_court';
$FP_AUDIENCE    = 'mltv5_pour_qui';
$FP_IMG_EXT     = 'mltv5_image_external_url';
$FP_CRITERIA    = 'mltv5_scores_des_criteres';
$FP_CRIT_LBL    = 'mltv5_nom_du_critere';
$FP_CRIT_VAL    = 'mltv5_score_du_critere';
$FP_ALT_PREMIUM = 'mltv5_alternative_premium';
$FP_ALT_BUDGET  = 'mltv5_alternative_budget';
$FP_PRICE_HIST  = 'mltv5_prix_historiques';  // format : 210¤190¤230¤210¤205¤180 (1er = plus récent)

/* ═════════════════════════════════════════════════════════════════════
   3) LIMITES & CONFIG
   ═════════════════════════════════════════════════════════════════════ */
$FP_TEST_ID        = 258978;   // ID de test — mettre à 0 pour utiliser le post courant
$FP_RANK_MAX       = 20;
$FP_RANK_VISIBLE   = 8;
$FP_CAROUSEL_MAX   = 5;
$FP_GUIDES_MAX     = 3;
$FP_AMAZON_TAG     = 'mlt00-21';
$FP_TAX_TYPE       = 'post-type-produit';
$FP_COMPARATIF_CPT = 'comparatif';
$FP_COMP_FIELD     = 'mltv5_best_products';
$FP_PRICE_RANGE    = 0.3;
$FP_COMP_VISIBLE   = 3;
$FP_TAX_ATTR       = 'post-type-attribut';
$FP_EYEBROW        = 'Test &amp; Avis';

/* ═════════════════════════════════════════════════════════════════════
   4) HELPERS
   ═════════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'fp_score_label' ) ) {
  function fp_score_label( $s ) {
    if ( $s >= 9 ) return 'Excellent';
    if ( $s >= 8 ) return 'Très bien';
    if ( $s >= 7 ) return 'Bien';
    if ( $s >= 6 ) return 'Correct';
    if ( $s >= 5 ) return 'Moyen';
    return 'Insuffisant';
  }
}
if ( ! function_exists( 'fp_stars' ) ) {
  function fp_stars( $r, $max = 5 ) {
    $full  = (int) floor( $r );
    $empty = $max - $full;
    return str_repeat( '★', $full ) . str_repeat( '☆', $empty );
  }
}
if ( ! function_exists( 'fp_bar_class' ) ) {
  function fp_bar_class( $s ) {
    if ( $s >= 8.5 ) return 'excellent';
    if ( $s >= 7.5 ) return 'good';
    return 'average';
  }
}
if ( ! function_exists( 'fp_medal' ) ) {
  function fp_medal( $r ) {
    if ( $r == 1 ) return 'gold';
    if ( $r == 2 ) return 'silver';
    if ( $r == 3 ) return 'bronze';
    return 'grey';
  }
}
if ( ! function_exists( 'fp_score_class' ) ) {
  function fp_score_class( $s ) {
    if ( $s >= 8.5 ) return 'high';
    if ( $s >= 7 )   return 'mid';
    return 'low';
  }
}
if ( ! function_exists( 'fp_format_price' ) ) {
  function fp_format_price( $p ) {
    if ( ! is_numeric( $p ) || $p <= 0 ) return '';
    return number_format( (float) $p, 0, ',', "\xc2\xa0" ) . "\xc2\xa0€";
  }
}
if ( ! function_exists( 'fp_merchant_name' ) ) {
  function fp_merchant_name( $url ) {
    $host = parse_url( (string) $url, PHP_URL_HOST );
    if ( ! $host ) return '';
    $host  = preg_replace( '/^www\./i', '', $host );
    $parts = explode( '.', $host );
    $label = isset( $parts[0] ) ? $parts[0] : '';
    return $label !== '' ? ucfirst( $label ) : '';
  }
}
if ( ! function_exists( 'fp_product_data' ) ) {
  function fp_product_data( $id, $score_field, $price_field, $brand_field, $model_field, $img_ext_field ) {
    $raw   = get_field( $score_field, $id );
    $score = function_exists( 'mt5_num' ) ? mt5_num( $raw ) / 10 : ( is_numeric( $raw ) ? round( $raw / 10, 1 ) : 0 );
    $price = get_field( $price_field, $id );
    $price = function_exists( 'mt5_num' ) ? mt5_num( $price ) : (float) $price;
    $brand = get_field( $brand_field, $id ) ?: '';
    $model = get_field( $model_field, $id ) ?: '';
    $name  = trim( $brand . ' ' . $model );
    if ( $name === '' ) $name = get_the_title( $id );
    $img = get_the_post_thumbnail_url( $id, 'medium' );
    if ( empty( $img ) ) {
      $ext = get_field( $img_ext_field, $id );
      if ( is_array( $ext ) && ! empty( $ext['url'] ) ) $img = $ext['url'];
      elseif ( is_string( $ext ) && $ext !== '' ) $img = $ext;
    }
    return compact( 'score', 'price', 'brand', 'model', 'name', 'img' );
  }
}

/* SVG icons (inline) */
$FP_SVG_CHECK = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"/><path d="M20 12v6a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>';
$FP_SVG_STAR  = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.5 1.6 6.7L12 17l-6.2 3.6 1.6-6.7L2.2 8.9l6.9-.6L12 2z"/></svg>';
$FP_SVG_EXT   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg>';
$FP_SVG_ARROW = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>';
$FP_SVG_CHEV  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';


/* ═════════════════════════════════════════════════════════════════════
   5) CHARGEMENT DES DONNÉES
   ═════════════════════════════════════════════════════════════════════ */
$pid       = ( ! empty( $FP_TEST_ID ) && get_post_status( $FP_TEST_ID ) ) ? (int) $FP_TEST_ID : get_the_ID();
if ( ! empty( $FP_TEST_ID ) && $pid == $FP_TEST_ID ) {
  global $post;
  $post = get_post( $pid );
  setup_postdata( $post );
}
$post_type = get_post_type( $pid );
$brand     = get_field( $FP_BRAND, $pid ) ?: '';
$model     = get_field( $FP_MODEL, $pid ) ?: '';
$product_name = trim( $brand . ' ' . $model );
if ( $product_name === '' ) $product_name = get_the_title( $pid );

$subtitle  = get_field( $FP_SUBTITLE, $pid ) ?: '';
$summary   = get_field( $FP_SUMMARY, $pid ) ?: '';
$verdict   = get_field( $FP_VERDICT, $pid ) ?: '';
$audience  = get_field( $FP_AUDIENCE, $pid ) ?: '';

$score_raw = get_field( $FP_SCORE, $pid );
$score     = function_exists( 'get_acf_score_divided_by_10' )
  ? get_acf_score_divided_by_10( $pid )
  : ( function_exists( 'mt5_num' ) ? mt5_num( $score_raw ) / 10 : ( is_numeric( $score_raw ) ? round( $score_raw / 10, 1 ) : '' ) );
$score_lbl = function_exists( 'get_acf_score_label' )
  ? get_acf_score_label( $pid )
  : ( is_numeric( $score ) ? fp_score_label( $score ) : '' );

$score_avis = get_field( $FP_SCORE_AVIS, $pid ) ?: '';
$nb_avis    = get_field( $FP_NB_AVIS, $pid ) ?: '';
$nb_avis_fmt = function_exists( 'mt5_reviews_label' ) ? mt5_reviews_label( $nb_avis ) : ( (int) $nb_avis > 0 ? number_format( (int) $nb_avis, 0, ',', ' ' ) . ' avis' : '' );

$price_raw = get_field( $FP_PRICE, $pid );
$price_num = function_exists( 'mt5_num' ) ? mt5_num( $price_raw ) : (float) $price_raw;
$price_fmt = fp_format_price( $price_num );

$asin = get_field( $FP_ASIN, $pid ) ?: '';

$criteria = get_field( $FP_CRITERIA, $pid ) ?: array();

$pros = function_exists( 'mt5_points' )
  ? mt5_points( $FP_PROS, $pid, $FP_PROS_SUB )
  : array();
$cons = function_exists( 'mt5_points' )
  ? mt5_points( $FP_CONS, $pid, $FP_CONS_SUB )
  : array();

$specs = array();
if ( function_exists( 'mt5_specs' ) ) {
  $specs = mt5_specs( $FP_SPECS, $pid, $FP_SPEC_LBL, $FP_SPEC_VAL );
} else {
  $sp_rows = get_field( $FP_SPECS, $pid );
  if ( ! empty( $sp_rows ) && is_array( $sp_rows ) ) {
    foreach ( $sp_rows as $sp_r ) {
      $sl = isset( $sp_r[ $FP_SPEC_LBL ] ) ? trim( $sp_r[ $FP_SPEC_LBL ] ) : '';
      $sv = isset( $sp_r[ $FP_SPEC_VAL ] ) ? trim( $sp_r[ $FP_SPEC_VAL ] ) : '';
      if ( $sl !== '' && $sv !== '' ) $specs[] = array( $sl, $sv );
    }
  }
}

/* Offres */
$offers     = array();
$link_fs    = array( $FP_LINK_1, $FP_LINK_2, $FP_LINK_3 );
$text_fs    = array( $FP_TEXT_1, $FP_TEXT_2, $FP_TEXT_3 );
if ( ! empty( $asin ) ) {
  $offers[] = array(
    'url'  => 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $FP_AMAZON_TAG,
    'text' => 'Voir sur Amazon',
    'name' => 'Amazon',
  );
}
for ( $i = 0; $i < 3; $i++ ) {
  $lnk = get_field( $link_fs[ $i ], $pid );
  $txt = get_field( $text_fs[ $i ], $pid );
  if ( ! empty( $lnk ) ) {
    $nm = function_exists( 'mt5_merchant_name' ) ? mt5_merchant_name( $lnk ) : fp_merchant_name( $lnk );
    $offers[] = array( 'url' => $lnk, 'text' => $txt ?: 'Voir l\'offre', 'name' => $nm );
  }
}

/* Image produit */
$hero_img = get_the_post_thumbnail_url( $pid, 'large' );
if ( empty( $hero_img ) ) {
  $ext = get_field( $FP_IMG_EXT, $pid );
  if ( is_array( $ext ) && ! empty( $ext['url'] ) ) $hero_img = $ext['url'];
  elseif ( is_string( $ext ) && $ext !== '' ) $hero_img = $ext;
}

$mod_date   = get_the_modified_date( 'j F Y' );


/* Contenu éditorial */
$review_html = apply_filters( 'the_content', get_post_field( 'post_content', $pid ) );

/* Historique des prix (champ texte : 6 prix séparés par ¤, 1er = plus récent) */
$ph_raw_str    = get_field( $FP_PRICE_HIST, $pid ) ?: '';
$ph_vals       = array();
if ( $ph_raw_str !== '' ) {
  $ph_parts = array_map( 'trim', explode( "\xc2\xa4", $ph_raw_str ) );  // ¤ = U+00A4
  $ph_parts = array_filter( $ph_parts, function( $v ) { return is_numeric( $v ) && (float) $v > 0; } );
  if ( count( $ph_parts ) === 6 ) {
    $ph_vals = array_reverse( array_map( 'floatval', array_values( $ph_parts ) ) );  // oldest → newest (gauche → droite)
  }
}

/* Alternatives (premium / budget, optionnels) */
$alt_premium_raw = get_field( $FP_ALT_PREMIUM, $pid );
$alt_premium_id  = is_object( $alt_premium_raw ) ? $alt_premium_raw->ID : ( is_numeric( $alt_premium_raw ) ? (int) $alt_premium_raw : 0 );
$alt_budget_raw  = get_field( $FP_ALT_BUDGET, $pid );
$alt_budget_id   = is_object( $alt_budget_raw ) ? $alt_budget_raw->ID : ( is_numeric( $alt_budget_raw ) ? (int) $alt_budget_raw : 0 );

/* Taxonomie type produit (pour queries) */
$type_terms = wp_get_post_terms( $pid, $FP_TAX_TYPE, array( 'fields' => 'ids' ) );
if ( is_wp_error( $type_terms ) ) $type_terms = array();
$type_names = wp_get_post_terms( $pid, $FP_TAX_TYPE, array( 'fields' => 'names' ) );
$type_label = ( ! is_wp_error( $type_names ) && ! empty( $type_names ) ) ? $type_names[0] : '';

/* ── Queries conditionnelles ── */

/* Comparatifs où ce produit apparaît (always queried for hero badge) */
$fp_comparatifs = array();
{
  $cq = new WP_Query( array(
    'post_type'      => $FP_COMPARATIF_CPT,
    'posts_per_page' => 5,
    'post_status'    => 'publish',
    'meta_query'     => array( array( 'key' => $FP_COMP_FIELD, 'value' => '"' . $pid . '"', 'compare' => 'LIKE' ) ),
  ) );
  if ( $cq->have_posts() ) {
    while ( $cq->have_posts() ) {
      $cq->the_post();
      $cid      = get_the_ID();
      $products = get_field( $FP_COMP_FIELD, $cid );
      $rank     = 0;
      if ( is_array( $products ) ) {
        foreach ( $products as $idx => $p ) {
          $p_id = is_object( $p ) ? $p->ID : (int) $p;
          if ( $p_id == $pid ) { $rank = $idx + 1; break; }
        }
      }
      $fp_comparatifs[] = array(
        'id'      => $cid,
        'title'   => get_the_title( $cid ),
        'url'     => get_permalink( $cid ),
        'thumb'   => get_the_post_thumbnail_url( $cid, 'medium' ),
        'excerpt' => wp_trim_words( get_the_excerpt( $cid ), 20, '…' ),
        'rank'    => $rank,
      );
    }
    wp_reset_postdata();
  }
}

/* Best rank for hero badge */
$fp_best_rank = 0;
$fp_best_comp = null;
foreach ( $fp_comparatifs as $c ) {
  if ( $c['rank'] > 0 && ( $fp_best_rank === 0 || $c['rank'] < $fp_best_rank ) ) {
    $fp_best_rank = $c['rank'];
    $fp_best_comp = $c;
  }
}

/* Badge label: type + attributes from the COMPARATIF (not the product) */
$fp_badge_label = '';
if ( $fp_best_rank > 0 && $fp_best_comp ) {
  $comp_id = $fp_best_comp['id'];
  $comp_type_names = wp_get_post_terms( $comp_id, $FP_TAX_TYPE, array( 'fields' => 'names' ) );
  if ( is_wp_error( $comp_type_names ) ) $comp_type_names = array();
  $comp_attr_names = wp_get_post_terms( $comp_id, $FP_TAX_ATTR, array( 'fields' => 'names' ) );
  if ( is_wp_error( $comp_attr_names ) ) $comp_attr_names = array();
  $parts = array();
  foreach ( $comp_type_names as $ct ) $parts[] = mb_convert_case( mb_strtolower( $ct ), MB_CASE_TITLE, 'UTF-8' );
  if ( ! empty( $comp_attr_names ) ) {
    $attr_str = array();
    foreach ( $comp_attr_names as $ca ) $attr_str[] = mb_convert_case( mb_strtolower( $ca ), MB_CASE_TITLE, 'UTF-8' );
    $parts[] = implode( ', ', $attr_str );
  }
  $fp_badge_label = 'N°' . $fp_best_rank;
  if ( ! empty( $parts ) ) $fp_badge_label .= ' · ' . implode( ' - ', $parts );
}

/* Idealo URL (search page with product name) */
$fp_idealo_url = '';
if ( $price_fmt !== '' ) {
  $idealo_slug = sanitize_title( $product_name );
  $fp_idealo_url = 'https://www.idealo.fr/prechcat.html?q=' . $idealo_slug;
}

/* Reference comparatif for sidebar (first comparatif matching the product type) */
$fp_ref_comp = null;
if ( ! empty( $type_terms ) ) {
  $rcq = new WP_Query( array(
    'post_type'      => $FP_COMPARATIF_CPT,
    'post_status'    => 'publish',
    'posts_per_page' => 1,
    'tax_query'      => array( array( 'taxonomy' => $FP_TAX_TYPE, 'terms' => $type_terms ) ),
    'orderby'        => 'date',
    'order'          => 'DESC',
  ) );
  if ( $rcq->have_posts() ) {
    $rcq->the_post();
    $fp_ref_comp = array(
      'title' => get_the_title(),
      'url'   => get_permalink(),
    );
    wp_reset_postdata();
  }
}

/* Produits similaires / même prix / même marque */
$fp_similar    = array();
$fp_same_price = array();
$fp_brand_top  = array();

$need_type_q = ( in_array( 'carousel_similar', $FP_BLOCKS, true )
  || in_array( 'carousel_price', $FP_BLOCKS, true )
  || $FP_SHOW_SIDEBAR || $FP_SHOW_GUIDES );

if ( $need_type_q && ! empty( $type_terms ) ) {
  $base_args = array(
    'post_type'      => $post_type,
    'post__not_in'   => array( $pid ),
    'post_status'    => 'publish',
    'posts_per_page' => $FP_CAROUSEL_MAX,
    'tax_query'      => array( array( 'taxonomy' => $FP_TAX_TYPE, 'terms' => $type_terms ) ),
    'meta_key'       => $FP_SCORE,
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
  );

  if ( in_array( 'carousel_similar', $FP_BLOCKS, true ) ) {
    $sq = new WP_Query( $base_args );
    while ( $sq->have_posts() ) {
      $sq->the_post();
      $sid = get_the_ID();
      $d = fp_product_data( $sid, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
      $d['id']  = $sid;
      $d['url'] = get_permalink( $sid );
      $fp_similar[] = $d;
    }
    wp_reset_postdata();
  }

  if ( in_array( 'carousel_price', $FP_BLOCKS, true ) && $price_num > 0 ) {
    $pq_args = $base_args;
    $pq_args['meta_query'] = array(
      array( 'key' => $FP_PRICE, 'value' => array( $price_num * ( 1 - $FP_PRICE_RANGE ), $price_num * ( 1 + $FP_PRICE_RANGE ) ), 'type' => 'NUMERIC', 'compare' => 'BETWEEN' ),
    );
    $pq = new WP_Query( $pq_args );
    while ( $pq->have_posts() ) {
      $pq->the_post();
      $sid = get_the_ID();
      $d = fp_product_data( $sid, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
      $d['id']    = $sid;
      $d['url']   = get_permalink( $sid );
      $d['delta'] = $d['price'] - $price_num;
      $fp_same_price[] = $d;
    }
    wp_reset_postdata();
  }
}

if ( in_array( 'carousel_brand', $FP_BLOCKS, true ) && $brand !== '' ) {
  $bq = new WP_Query( array(
    'post_type'      => $post_type,
    'post_status'    => 'publish',
    'posts_per_page' => $FP_CAROUSEL_MAX + 1,
    'meta_query'     => array( array( 'key' => $FP_BRAND, 'value' => $brand, 'compare' => '=' ) ),
    'meta_key'       => $FP_SCORE,
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
  ) );
  $brank = 0;
  while ( $bq->have_posts() ) {
    $bq->the_post();
    $brank++;
    $sid = get_the_ID();
    $d = fp_product_data( $sid, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
    $d['id']      = $sid;
    $d['url']     = get_permalink( $sid );
    $d['rank']    = $brank;
    $d['current'] = ( $sid == $pid );
    $fp_brand_top[] = $d;
  }
  wp_reset_postdata();
}

/* Ranking sidebar */
$fp_ranking    = array();
$fp_rank_total = 0;
if ( $FP_SHOW_SIDEBAR && ! empty( $type_terms ) ) {
  $rq = new WP_Query( array(
    'post_type'      => $post_type,
    'post_status'    => 'publish',
    'posts_per_page' => $FP_RANK_MAX,
    'tax_query'      => array( array( 'taxonomy' => $FP_TAX_TYPE, 'terms' => $type_terms ) ),
    'meta_key'       => $FP_SCORE,
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
  ) );
  $fp_rank_total = $rq->found_posts;
  $rk = 0;
  while ( $rq->have_posts() ) {
    $rq->the_post();
    $rk++;
    $sid = get_the_ID();
    $d = fp_product_data( $sid, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
    $d['id']      = $sid;
    $d['url']     = get_permalink( $sid );
    $d['rank']    = $rk;
    $d['current'] = ( $sid == $pid );
    $fp_ranking[] = $d;
  }
  wp_reset_postdata();
}

/* Guides associés */
$fp_guides = array();
if ( $FP_SHOW_GUIDES && ! empty( $type_terms ) ) {
  $gq = new WP_Query( array(
    'post_type'      => $FP_COMPARATIF_CPT,
    'post_status'    => 'publish',
    'posts_per_page' => $FP_GUIDES_MAX,
    'tax_query'      => array( array( 'taxonomy' => $FP_TAX_TYPE, 'terms' => $type_terms ) ),
  ) );
  while ( $gq->have_posts() ) {
    $gq->the_post();
    $gid = get_the_ID();
    $fp_guides[] = array(
      'id'      => $gid,
      'title'   => get_the_title( $gid ),
      'url'     => get_permalink( $gid ),
      'thumb'   => get_the_post_thumbnail_url( $gid, 'medium' ),
      'excerpt' => wp_trim_words( get_the_excerpt( $gid ), 18, '…' ),
      'cat'     => ( ! empty( $type_label ) ) ? $type_label : '',
    );
  }
  wp_reset_postdata();
}

/* VS alternatives (premium + budget) */
$fp_vs_list = array();
if ( in_array( 'vs', $FP_BLOCKS, true ) ) {
  $vs_candidates = array();
  if ( $alt_premium_id > 0 ) $vs_candidates[] = $alt_premium_id;
  if ( $alt_budget_id > 0 )  $vs_candidates[] = $alt_budget_id;
  foreach ( $vs_candidates as $vc_id ) {
    $vd = fp_product_data( $vc_id, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
    $vd['id']         = $vc_id;
    $vd['url']        = get_permalink( $vc_id );
    $vd['score_avis'] = get_field( $FP_SCORE_AVIS, $vc_id ) ?: '';
    $vd['nb_avis']    = get_field( $FP_NB_AVIS, $vc_id ) ?: '';
    if ( $vd['score'] > 0 ) $fp_vs_list[] = $vd;
  }
}

/* UID unique pour les IDs HTML (checkbox expand/collapse) */
$fp_uid = 'fp' . substr( md5( $pid . 'avis' ), 0, 5 );

/* ═════════════════════════════════════════════════════════════════════
   6) RENDU HTML
   ═════════════════════════════════════════════════════════════════════ */
?>
<div class="fp-avis">

  <?php /* ── Fil d'ariane ── */
  $bc = function_exists( 'rank_math_the_breadcrumbs' ) ? do_shortcode( '[rank_math_breadcrumb]' ) : '';
  if ( ! empty( trim( $bc ) ) ) {
    $bc = preg_replace( '#(<span class="separator">).*?(</span>)#', '$1&nbsp;&rsaquo;&nbsp;$2', $bc );
    $bc .= ' &nbsp;&rsaquo;&nbsp; <b>' . esc_html( $product_name ) . '</b>';
    echo '<div class="fp-crumb">' . $bc . '</div>';
  } ?>

  <?php /* ════════════ HERO ════════════ */
  if ( $FP_SHOW_HERO ) : ?>
  <section class="fp-hero">
    <div class="fp-media">
      <?php if ( $fp_badge_label !== '' ) : ?>
        <span class="badge-rank"><?php echo $FP_SVG_STAR; ?> <?php echo esc_html( $fp_badge_label ); ?></span>
      <?php endif; ?>
      <?php if ( ! empty( $hero_img ) ) : ?>
        <img src="<?php echo esc_url( $hero_img ); ?>" alt="<?php echo esc_attr( $product_name ); ?>" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply">
      <?php endif; ?>
    </div>
    <div class="fp-info">
      <h1>
        <span class="h1-eyebrow"><?php echo $FP_SVG_CHECK; ?> <?php echo $FP_EYEBROW; ?></span>
        <?php echo esc_html( $product_name ); ?>
        <?php if ( $subtitle !== '' ) : ?><span class="fp-sub"><?php echo esc_html( $subtitle ); ?></span><?php endif; ?>
      </h1>
      <div class="fp-byline">Mis à jour le <?php echo esc_html( $mod_date ); ?></div>
      <?php if ( $summary !== '' ) : ?>
        <p class="fp-verdict"><?php echo wp_kses_post( $summary ); ?></p>
      <?php endif; ?>

      <?php if ( is_numeric( $score ) && $score > 0 ) : ?>
      <div class="fp-scorepanel">
        <div class="fp-scorepanel-top">
          <div class="fp-score-cell">
            <span class="sc-label">Notre score</span>
            <div class="sc-main">
              <span class="sc-num editorial"><?php echo number_format( $score, 1, ',', '' ); ?><small>/10</small></span>
              <?php if ( $score_lbl ) : ?><span class="sc-tag"><?php echo esc_html( $score_lbl ); ?></span><?php endif; ?>
            </div>
          </div>
          <?php if ( is_numeric( $score_avis ) && $score_avis > 0 ) : ?>
          <div class="fp-score-cell">
            <span class="sc-label">Avis clients</span>
            <div class="sc-main">
              <span class="sc-num users"><?php echo number_format( (float) $score_avis, 1, ',', '' ); ?><small>/5</small></span>
              <span class="sc-sub"><span class="stars"><?php echo fp_stars( (float) $score_avis ); ?></span> <?php echo esc_html( $nb_avis_fmt ); ?></span>
            </div>
          </div>
          <?php endif; ?>
        </div>
        <?php if ( ! empty( $criteria ) && is_array( $criteria ) ) : ?>
        <div class="fp-criteria">
          <?php foreach ( $criteria as $cr ) :
            $cl = isset( $cr[ $FP_CRIT_LBL ] ) ? trim( $cr[ $FP_CRIT_LBL ] ) : '';
            $cv_raw = isset( $cr[ $FP_CRIT_VAL ] ) ? (float) $cr[ $FP_CRIT_VAL ] : 0;
            if ( $cl === '' || $cv_raw <= 0 ) continue;
            $cv  = round( $cv_raw / 10, 1 );
            $pct = min( 100, $cv * 10 );
          ?>
          <div class="fp-ci">
            <span class="ci-lbl"><?php echo esc_html( $cl ); ?></span>
            <div class="ci-bar"><span class="<?php echo fp_bar_class( $cv ); ?>" style="width:<?php echo $pct; ?>%"></span></div>
            <span class="ci-val"><?php echo number_format( $cv, 1, ',', '' ); ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ( ! empty( $offers ) ) : ?>
      <div class="fp-buy">
        <?php $primary = $offers[0]; $others = array_slice( $offers, 1 ); ?>
        <div class="fp-buy-opt">
          <a class="fp-buy-btn" href="<?php echo esc_url( $primary['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $primary['text'] ); ?> <?php echo $FP_SVG_EXT; ?></a>
          <?php if ( ! empty( $others ) ) : ?>
            <div class="fp-also">Également sur <?php
              $names = array();
              foreach ( $others as $o ) { $names[] = '<a href="' . esc_url( $o['url'] ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $o['name'] ?: $o['text'] ) . '</a>'; }
              echo implode( ', ', $names );
            ?></div>
          <?php endif; ?>
        </div>
        <?php if ( $fp_idealo_url !== '' ) : ?>
        <div class="fp-buy-or"><span>ou</span></div>
        <div class="fp-buy-opt">
          <a class="fp-buy-btn secondary" href="<?php echo esc_url( $fp_idealo_url ); ?>" target="_blank" rel="nofollow noopener">Meilleur prix sur Idealo <?php echo $FP_SVG_ARROW; ?></a>
          <div class="fp-price-avg">Prix moyen constaté : <b><?php echo $price_fmt; ?></b></div>
        </div>
        <?php elseif ( $price_fmt !== '' ) : ?>
        <div class="fp-price-avg">Prix moyen constaté : <b><?php echo $price_fmt; ?></b></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; /* /HERO */ ?>

  <?php /* ════════════ BODY ════════════ */ ?>
  <div class="fp-body">
    <div class="fp-main">

    <?php foreach ( $FP_BLOCKS as $fp_b ) : switch ( $fp_b ) :

      /* ─── REVIEW ─── */
      case 'review':
        if ( empty( trim( strip_tags( $review_html ) ) ) ) break;
        ?>
        <h2 class="fp-stitle lead">Notre avis sur <?php echo esc_html( mb_strtolower( $brand ) !== '' ? 'le ' . $product_name : get_the_title( $pid ) ); ?></h2>
        <div class="fp-review"><?php echo $review_html; ?></div>
        <?php break;

      /* ─── COMPARATIFS ─── */
      case 'comparatifs':
        if ( empty( $fp_comparatifs ) ) break;
        $nb_comp = count( $fp_comparatifs );
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle"><?php echo esc_html( $product_name ); ?> est dans le top de <?php echo $nb_comp; ?> comparatif<?php echo $nb_comp > 1 ? 's' : ''; ?></h3>
          <div class="fp-comp-list">
            <?php foreach ( $fp_comparatifs as $ci => $c ) : ?>
            <a class="fp-comp-card<?php echo $ci >= $FP_COMP_VISIBLE ? ' fp-comp-extra' : ''; ?>" href="<?php echo esc_url( $c['url'] ); ?>"<?php echo $ci >= $FP_COMP_VISIBLE ? ' style="display:none"' : ''; ?>>
              <?php if ( ! empty( $c['thumb'] ) ) : ?>
              <div class="comp-thumb"><img src="<?php echo esc_url( $c['thumb'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover"></div>
              <?php endif; ?>
              <div class="comp-info">
                <?php if ( $c['rank'] > 0 ) : ?>
                  <span class="comp-rank <?php echo fp_medal( $c['rank'] ); ?>"><?php echo $FP_SVG_STAR; ?> Classé n°<?php echo $c['rank']; ?> dans</span>
                <?php endif; ?>
                <h4 class="comp-title"><?php echo esc_html( $c['title'] ); ?></h4>
                <?php if ( ! empty( $c['excerpt'] ) ) : ?>
                  <span class="comp-excerpt"><?php echo esc_html( $c['excerpt'] ); ?></span>
                <?php endif; ?>
                <span class="comp-cta">Voir le comparatif →</span>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php if ( $nb_comp > $FP_COMP_VISIBLE ) : ?>
          <button type="button" class="fp-comp-toggle" onclick="(function(b){var e=b.closest('.fp-interblock').querySelectorAll('.fp-comp-extra'),v=e[0]&&e[0].style.display==='none';e.forEach(function(c){c.style.display=v?'flex':'none'});b.querySelector('.show-t').style.display=v?'none':'inline';b.querySelector('.hide-t').style.display=v?'inline':'none'})(this)">
            <span class="show-t">Afficher les <?php echo $nb_comp - $FP_COMP_VISIBLE; ?> autres comparatifs <?php echo $FP_SVG_CHEV; ?></span>
            <span class="hide-t" style="display:none">Masquer <?php echo $FP_SVG_CHEV; ?></span>
          </button>
          <?php endif; ?>
        </div>
        <?php break;

      /* ─── PROS / CONS ─── */
      case 'pros_cons':
        if ( empty( $pros ) && empty( $cons ) ) break;
        ?>
        <h3 class="fp-stitle">Points forts et points faibles</h3>
        <div class="fp-pc-grid">
          <?php if ( ! empty( $pros ) ) : ?>
          <div class="fp-pc pros">
            <h4>Points forts</h4>
            <ul><?php foreach ( $pros as $p ) : ?><li><?php echo esc_html( $p ); ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
          <?php if ( ! empty( $cons ) ) : ?>
          <div class="fp-pc cons">
            <h4>Points faibles</h4>
            <ul><?php foreach ( $cons as $c ) : ?><li><?php echo esc_html( $c ); ?></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
        </div>
        <?php break;

      /* ─── AUDIENCE ─── */
      case 'audience':
        if ( $audience === '' ) break;
        ?>
        <h3 class="fp-stitle">À qui s'adresse ce produit ?</h3>
        <div class="fp-review"><p><?php echo wp_kses_post( $audience ); ?></p></div>
        <?php break;

      /* ─── PRICE HISTORY ─── */
      case 'price_history':
        if ( count( $ph_vals ) !== 6 ) break;
        $ph_min = min( $ph_vals );
        $ph_max = max( $ph_vals );
        $ph_cur = end( $ph_vals );
        $ph_avg = round( array_sum( $ph_vals ) / 6 );
        $ph_months = array();
        $ph_month_names = array( 1=>'Jan', 2=>'Fév', 3=>'Mar', 4=>'Avr', 5=>'Mai', 6=>'Juin', 7=>'Juil', 8=>'Août', 9=>'Sep', 10=>'Oct', 11=>'Nov', 12=>'Déc' );
        for ( $mi = 5; $mi >= 0; $mi-- ) {
          $m = (int) date( 'n', strtotime( '-' . $mi . ' months' ) );
          $ph_months[] = $ph_month_names[ $m ];
        }
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle">Évolution du prix sur les 6 derniers mois</h3>
          <div class="fp-price-hist">
            <div class="fp-ph-summary">
              <div class="fp-ph-stat"><div class="k">Prix actuel</div><div class="v<?php echo $ph_cur <= $ph_min ? ' low' : ''; ?>"><?php echo fp_format_price( $ph_cur ); ?></div></div>
              <div class="fp-ph-stat"><div class="k">Plus bas</div><div class="v"><?php echo fp_format_price( $ph_min ); ?></div></div>
              <div class="fp-ph-stat"><div class="k">Plus haut</div><div class="v high"><?php echo fp_format_price( $ph_max ); ?></div></div>
              <div class="fp-ph-stat"><div class="k">Moyenne</div><div class="v"><?php echo fp_format_price( $ph_avg ); ?></div></div>
            </div>
            <?php /* Chart SVG — 6 points, oldest (gauche) → newest (droite) */
            $w   = 760;
            $h   = 130;
            $pad = 40;
            $range = max( 1, $ph_max - $ph_min );
            $points = array();
            for ( $i = 0; $i < 6; $i++ ) {
              $x = $pad + ( $i / 5 ) * ( $w - 2 * $pad );
              $y = $h - 10 - ( ( $ph_vals[ $i ] - $ph_min ) / $range ) * ( $h - 30 );
              $points[] = array( $x, $y, $ph_vals[ $i ] );
            }
            $line_d = 'M' . implode( ' L', array_map( function( $p ) { return round( $p[0], 1 ) . ',' . round( $p[1], 1 ); }, $points ) );
            $area_d = $line_d . ' L' . round( end( $points )[0], 1 ) . ',' . $h . ' L' . round( $points[0][0], 1 ) . ',' . $h . ' Z';
            ?>
            <div class="fp-ph-chart">
              <svg viewBox="0 0 <?php echo $w; ?> <?php echo $h + 20; ?>" role="img" aria-label="Évolution du prix">
                <path class="area" d="<?php echo $area_d; ?>"/>
                <path class="line" d="<?php echo $line_d; ?>"/>
                <?php foreach ( $points as $i => $p ) :
                  $is_now = ( $i === 5 );
                ?>
                <text class="val<?php echo $is_now ? ' now' : ''; ?>" x="<?php echo round( $p[0], 1 ); ?>" y="<?php echo round( $p[1] - 10, 1 ); ?>"><?php echo fp_format_price( $p[2] ); ?></text>
                <circle class="dot<?php echo $is_now ? ' now' : ''; ?>" cx="<?php echo round( $p[0], 1 ); ?>" cy="<?php echo round( $p[1], 1 ); ?>" r="<?php echo $is_now ? 5 : 4; ?>"/>
                <?php endforeach; ?>
              </svg>
            </div>
            <div class="fp-ph-labels"><?php foreach ( $ph_months as $ml ) : ?><span><?php echo $ml; ?></span><?php endforeach; ?></div>
            <?php if ( $ph_cur <= $ph_min ) : ?>
            <p class="fp-ph-foot">→ <b>C'est le moment d'acheter :</b> le prix est actuellement au plus bas.</p>
            <?php endif; ?>
            <?php if ( ! empty( $offers ) ) : ?>
            <div class="fp-buy">
              <?php $ph_primary = $offers[0]; $ph_others = array_slice( $offers, 1 ); ?>
              <div class="fp-buy-opt">
                <a class="fp-buy-btn" href="<?php echo esc_url( $ph_primary['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $ph_primary['text'] ); ?> <?php echo $FP_SVG_EXT; ?></a>
                <?php if ( ! empty( $ph_others ) ) : ?>
                  <div class="fp-also">Également sur <?php
                    $ph_names = array();
                    foreach ( $ph_others as $o ) { $ph_names[] = '<a href="' . esc_url( $o['url'] ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $o['name'] ?: $o['text'] ) . '</a>'; }
                    echo implode( ', ', $ph_names );
                  ?></div>
                <?php endif; ?>
              </div>
              <?php if ( $fp_idealo_url !== '' ) : ?>
              <div class="fp-buy-or"><span>ou</span></div>
              <div class="fp-buy-opt">
                <a class="fp-buy-btn secondary" href="<?php echo esc_url( $fp_idealo_url ); ?>" target="_blank" rel="nofollow noopener">Meilleur prix sur Idealo <?php echo $FP_SVG_ARROW; ?></a>
                <div class="fp-price-avg">Prix moyen constaté : <b><?php echo $price_fmt; ?></b></div>
              </div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php break;

      /* ─── SPECS ─── */
      case 'specs':
        if ( empty( $specs ) ) break;
        $half = (int) ceil( count( $specs ) / 2 );
        ?>
        <h3 class="fp-stitle">Fiche technique</h3>
        <div class="fp-specs">
          <div class="fp-specs-group-title">Caractéristiques principales</div>
          <div class="fp-specs-cols">
            <div class="fp-specs-col">
              <?php for ( $i = 0; $i < $half; $i++ ) : if ( ! isset( $specs[ $i ] ) ) continue; ?>
              <div class="fp-spec-row"><span class="k"><?php echo esc_html( $specs[ $i ][0] ); ?></span><span class="v"><?php echo wp_kses_post( $specs[ $i ][1] ); ?></span></div>
              <?php endfor; ?>
            </div>
            <div class="fp-specs-col">
              <?php for ( $i = $half; $i < count( $specs ); $i++ ) : if ( ! isset( $specs[ $i ] ) ) continue; ?>
              <div class="fp-spec-row"><span class="k"><?php echo esc_html( $specs[ $i ][0] ); ?></span><span class="v"><?php echo wp_kses_post( $specs[ $i ][1] ); ?></span></div>
              <?php endfor; ?>
            </div>
          </div>
        </div>
        <?php break;

      /* ─── ALTERNATIVES ─── */
      case 'alternatives':
        if ( $alt_premium_id <= 0 && $alt_budget_id <= 0 ) break;
        $alts = array();
        if ( $alt_premium_id > 0 ) {
          $d = fp_product_data( $alt_premium_id, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
          $d['type'] = 'premium'; $d['kicker'] = 'Choix haut de gamme'; $d['url'] = get_permalink( $alt_premium_id );
          $alts[] = $d;
        }
        if ( $alt_budget_id > 0 ) {
          $d = fp_product_data( $alt_budget_id, $FP_SCORE, $FP_PRICE, $FP_BRAND, $FP_MODEL, $FP_IMG_EXT );
          $d['type'] = 'budget'; $d['kicker'] = 'Choix pas cher'; $d['url'] = get_permalink( $alt_budget_id );
          $alts[] = $d;
        }
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle"><?php echo count( $alts ); ?> alternative<?php echo count( $alts ) > 1 ? 's' : ''; ?> à considérer en fonction de votre budget</h3>
          <div class="fp-pick-row">
            <?php foreach ( $alts as $a ) : ?>
            <a class="fp-pick-card" href="<?php echo esc_url( $a['url'] ); ?>">
              <?php if ( ! empty( $a['img'] ) ) : ?>
              <div class="pthumb"><img src="<?php echo esc_url( $a['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply"></div>
              <?php else : ?>
              <div class="pthumb"></div>
              <?php endif; ?>
              <div class="pinfo">
                <span class="kicker <?php echo $a['type']; ?>"><?php echo esc_html( $a['kicker'] ); ?></span>
                <h4><?php echo esc_html( $a['name'] ); ?></h4>
                <?php if ( $a['price'] > 0 ) : ?><span class="pprice">À partir de <b><?php echo fp_format_price( $a['price'] ); ?></b></span><?php endif; ?>
              </div>
              <?php if ( $a['score'] > 0 ) : ?>
              <div class="fp-pick-score"><span class="n"><?php echo number_format( $a['score'], 1, ',', '' ); ?></span><span class="l">/10</span></div>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php break;

      /* ─── VS (un bloc par alternative) ─── */
      case 'vs':
        if ( empty( $fp_vs_list ) ) break;
        foreach ( $fp_vs_list as $vs ) :
          $cur_wins_score = ( $score >= $vs['score'] );
        ?>
        <div class="fp-interblock">
          <h3 class="fp-stitle"><?php echo esc_html( $product_name ); ?> ou <?php echo esc_html( $vs['name'] ); ?> ?</h3>
          <div class="fp-vs">
            <div class="fp-vs-head">
              <div class="fp-vs-prod<?php echo $cur_wins_score ? ' win' : ''; ?>">
                <?php if ( ! empty( $hero_img ) ) : ?><div class="vthumb"><img src="<?php echo esc_url( $hero_img ); ?>" alt="" style="width:100%;height:100%;object-fit:contain"></div><?php else : ?><div class="vthumb"></div><?php endif; ?>
                <span class="fp-vs-tag this">Ce produit</span>
                <h4><?php echo esc_html( $product_name ); ?></h4>
                <div class="vscore"><span class="n"><?php echo number_format( $score, 1, ',', '' ); ?></span><span class="d">/10</span></div>
              </div>
              <div class="fp-vs-badge">VS</div>
              <div class="fp-vs-prod<?php echo ! $cur_wins_score ? ' win' : ''; ?>">
                <?php if ( ! empty( $vs['img'] ) ) : ?><div class="vthumb"><img src="<?php echo esc_url( $vs['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain"></div><?php else : ?><div class="vthumb"></div><?php endif; ?>
                <h4><?php echo esc_html( $vs['name'] ); ?></h4>
                <div class="vscore"><span class="n"><?php echo number_format( $vs['score'], 1, ',', '' ); ?></span><span class="d">/10</span></div>
              </div>
            </div>
            <div class="fp-vs-rows">
              <?php if ( $price_num > 0 && $vs['price'] > 0 ) :
                $pw = ( $price_num <= $vs['price'] );
              ?>
              <div class="fp-vs-row">
                <div class="fp-vs-side left<?php echo $pw ? ' win' : ''; ?>"><span class="val"><?php echo $price_fmt; ?></span></div>
                <div class="lbl">Prix</div>
                <div class="fp-vs-side<?php echo ! $pw ? ' win' : ''; ?>"><span class="val"><?php echo fp_format_price( $vs['price'] ); ?></span></div>
              </div>
              <?php endif; ?>
              <div class="fp-vs-row">
                <div class="fp-vs-side left<?php echo $cur_wins_score ? ' win' : ''; ?>"><span class="val"><?php echo number_format( $score, 1, ',', '' ); ?></span><span class="mbar"><span style="width:<?php echo min( 100, $score * 10 ); ?>%"></span></span></div>
                <div class="lbl">Score global</div>
                <div class="fp-vs-side<?php echo ! $cur_wins_score ? ' win' : ''; ?>"><span class="val"><?php echo number_format( $vs['score'], 1, ',', '' ); ?></span><span class="mbar"><span style="width:<?php echo min( 100, $vs['score'] * 10 ); ?>%"></span></span></div>
              </div>
              <?php if ( is_numeric( $score_avis ) && $score_avis > 0 && is_numeric( $vs['score_avis'] ) && $vs['score_avis'] > 0 ) :
                $aw = ( (float) $score_avis >= (float) $vs['score_avis'] );
              ?>
              <div class="fp-vs-row avis">
                <div class="fp-vs-side left<?php echo $aw ? ' win' : ''; ?>"><span class="val"><?php echo number_format( (float) $score_avis, 1, ',', '' ); ?><small style="color:var(--muted);font-weight:400"> /5</small></span><span class="stars"><?php echo fp_stars( (float) $score_avis ); ?></span><span class="nb"><?php echo esc_html( $nb_avis_fmt ); ?></span></div>
                <div class="lbl">Avis clients</div>
                <?php $vs_nb = function_exists( 'mt5_reviews_label' ) ? mt5_reviews_label( $vs['nb_avis'] ) : ''; ?>
                <div class="fp-vs-side<?php echo ! $aw ? ' win' : ''; ?>"><span class="val"><?php echo number_format( (float) $vs['score_avis'], 1, ',', '' ); ?><small style="color:var(--muted);font-weight:400"> /5</small></span><span class="stars"><?php echo fp_stars( (float) $vs['score_avis'] ); ?></span><span class="nb"><?php echo esc_html( $vs_nb ); ?></span></div>
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; break;

      /* ─── CAROUSEL : similaires ─── */
      case 'carousel_similar':
        if ( empty( $fp_similar ) ) break;
        ?>
        <div class="fp-interblock">
          <div class="fp-carousel-head">
            <h3 class="fp-stitle">Vous aimerez aussi</h3>
            <div class="fp-carousel-nav">
              <button type="button" class="fp-carousel-btn" data-dir="-1" aria-label="Précédent">‹</button>
              <button type="button" class="fp-carousel-btn" data-dir="1" aria-label="Suivant">›</button>
            </div>
          </div>
          <div class="fp-carousel"><div class="fp-carousel-track">
            <?php foreach ( $fp_similar as $s ) : ?>
            <a class="fp-mini-card" href="<?php echo esc_url( $s['url'] ); ?>">
              <div class="mthumb"><?php if ( ! empty( $s['img'] ) ) : ?><img src="<?php echo esc_url( $s['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply"><?php endif; ?></div>
              <div class="minfo"><h4><?php echo esc_html( $s['name'] ); ?></h4><?php if ( $s['price'] > 0 ) : ?><div class="mprice">À partir de <b><?php echo fp_format_price( $s['price'] ); ?></b></div><?php endif; ?></div>
              <?php if ( $s['score'] > 0 ) : ?><div class="fp-mini-score"><span class="n"><?php echo number_format( $s['score'], 1, ',', '' ); ?></span><span class="l">/10</span></div><?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div></div>
        </div>
        <?php break;

      /* ─── CAROUSEL : même gamme de prix ─── */
      case 'carousel_price':
        if ( empty( $fp_same_price ) ) break;
        ?>
        <div class="fp-interblock">
          <div class="fp-carousel-head">
            <h3 class="fp-stitle">Dans la même gamme de prix</h3>
            <div class="fp-carousel-nav">
              <button type="button" class="fp-carousel-btn" data-dir="-1" aria-label="Précédent">‹</button>
              <button type="button" class="fp-carousel-btn" data-dir="1" aria-label="Suivant">›</button>
            </div>
          </div>
          <div class="fp-carousel"><div class="fp-carousel-track">
            <?php foreach ( $fp_same_price as $s ) : ?>
            <a class="fp-mini-card" href="<?php echo esc_url( $s['url'] ); ?>">
              <div class="mthumb"><?php if ( ! empty( $s['img'] ) ) : ?><img src="<?php echo esc_url( $s['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply"><?php endif; ?></div>
              <div class="minfo">
                <h4><?php echo esc_html( $s['name'] ); ?></h4>
                <?php if ( $s['price'] > 0 ) : ?>
                <div class="mprice"><b><?php echo fp_format_price( $s['price'] ); ?></b><?php
                  if ( isset( $s['delta'] ) && $s['delta'] != 0 ) {
                    $cls = $s['delta'] < 0 ? 'down' : 'up';
                    $sign = $s['delta'] < 0 ? '' : '+';
                    echo ' · <span class="mdelta ' . $cls . '">' . $sign . fp_format_price( abs( $s['delta'] ) ) . '</span>';
                  }
                ?></div>
                <?php endif; ?>
              </div>
              <?php if ( $s['score'] > 0 ) : ?><div class="fp-mini-score"><span class="n"><?php echo number_format( $s['score'], 1, ',', '' ); ?></span><span class="l">/10</span></div><?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div></div>
        </div>
        <?php break;

      /* ─── CAROUSEL : top de la marque ─── */
      case 'carousel_brand':
        if ( empty( $fp_brand_top ) || count( $fp_brand_top ) < 2 ) break;
        ?>
        <div class="fp-interblock">
          <div class="fp-carousel-head">
            <h3 class="fp-stitle">Nos <?php echo count( $fp_brand_top ); ?> produits <?php echo esc_html( $brand ); ?> préférés</h3>
            <div class="fp-carousel-nav">
              <button type="button" class="fp-carousel-btn" data-dir="-1" aria-label="Précédent">‹</button>
              <button type="button" class="fp-carousel-btn" data-dir="1" aria-label="Suivant">›</button>
            </div>
          </div>
          <div class="fp-carousel"><div class="fp-carousel-track">
            <?php foreach ( $fp_brand_top as $s ) : ?>
            <a class="fp-mini-card<?php echo $s['current'] ? ' current' : ''; ?>" href="<?php echo esc_url( $s['url'] ); ?>">
              <span class="fp-mini-badge <?php echo fp_medal( $s['rank'] ); ?>"><?php echo $s['rank']; ?></span>
              <div class="mthumb"><?php if ( ! empty( $s['img'] ) ) : ?><img src="<?php echo esc_url( $s['img'] ); ?>" alt="" style="width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply"><?php endif; ?></div>
              <div class="minfo">
                <h4><?php echo esc_html( $s['name'] ); ?></h4>
                <div class="mprice"><?php echo $s['current'] ? 'Ce produit · ' : 'À partir de '; ?><b><?php echo fp_format_price( $s['price'] ); ?></b></div>
              </div>
              <?php if ( $s['score'] > 0 ) : ?><div class="fp-mini-score"><span class="n"><?php echo number_format( $s['score'], 1, ',', '' ); ?></span><span class="l">/10</span></div><?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div></div>
        </div>
        <?php break;

    endswitch; endforeach; ?>

    </div><?php /* /fp-main */ ?>

    <?php /* ════════════ SIDEBAR ════════════ */
    if ( $FP_SHOW_SIDEBAR && ! empty( $fp_ranking ) ) : ?>
    <aside class="fp-side">
      <div class="fp-side-block">
        <div class="fp-side-title">Classement<?php echo $type_label !== '' ? ' des ' . esc_html( mb_strtolower( $type_label ) ) : ''; ?></div>
        <div class="fp-side-count"><?php echo $fp_rank_total; ?> produit<?php echo $fp_rank_total > 1 ? 's' : ''; ?> testé<?php echo $fp_rank_total > 1 ? 's' : ''; ?></div>
        <table class="fp-rank-table">
          <thead><tr><th></th><th>Produit</th><th>Score</th></tr></thead>
          <tbody>
            <?php foreach ( $fp_ranking as $r ) : ?>
            <tr class="<?php echo $r['current'] ? 'current' : ''; ?> <?php echo $r['rank'] > $FP_RANK_VISIBLE ? 'extra' : ''; ?>">
              <td class="rk<?php echo $r['rank'] <= 3 ? ' top' : ''; ?>"><?php echo $r['rank']; ?></td>
              <td class="pname"><a href="<?php echo esc_url( $r['url'] ); ?>"><?php echo esc_html( $r['name'] ); ?></a></td>
              <td class="pscore <?php echo fp_score_class( $r['score'] ); ?>"><?php echo number_format( $r['score'], 1, ',', '' ); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:14px">
          <?php if ( count( $fp_ranking ) > $FP_RANK_VISIBLE ) : ?>
          <button type="button" class="fp-rank-collapse" onclick="var p=this.closest('.fp-side-block'),ex=p.querySelectorAll('tr.extra'),on=ex[0]&&ex[0].style.display!=='table-row';ex.forEach(function(r){r.style.display=on?'table-row':'none'});this.querySelector('.show-txt').style.display=on?'none':'inline';this.querySelector('.hide-txt').style.display=on?'inline':'none';var sv=this.querySelector('svg');if(sv)sv.style.transform=on?'rotate(180deg)':''">
            <span class="show-txt">Afficher le classement complet</span>
            <span class="hide-txt">Réduire le classement</span>
            <?php echo $FP_SVG_CHEV; ?>
          </button>
          <?php endif; ?>
          <?php if ( $fp_ref_comp ) : ?>
          <a class="fp-rank-viewall" href="<?php echo esc_url( $fp_ref_comp['url'] ); ?>">Voir le comparatif <?php echo esc_html( $type_label !== '' ? 'des ' . mb_strtolower( $type_label ) : '' ); ?> <?php echo $FP_SVG_ARROW; ?></a>
          <?php endif; ?>
        </div>
      </div>
    </aside>
    <?php endif; ?>

  </div><?php /* /fp-body */ ?>

  <?php /* ════════════ GUIDES ASSOCIÉS ════════════ */
  if ( $FP_SHOW_GUIDES && ! empty( $fp_guides ) ) : ?>
  <section class="fp-fw">
    <div class="fp-fw-head">
      <h2>Nos guides sur le même sujet</h2>
    </div>
    <div class="fp-guide-grid">
      <?php foreach ( $fp_guides as $g ) : ?>
      <a class="fp-gcard" href="<?php echo esc_url( $g['url'] ); ?>">
        <div class="fp-gcard-thumb"><?php if ( ! empty( $g['thumb'] ) ) : ?><img src="<?php echo esc_url( $g['thumb'] ); ?>" alt="" style="width:100%;height:100%;object-fit:cover"><?php endif; ?></div>
        <div class="fp-gcard-body">
          <?php if ( $g['cat'] !== '' ) : ?><span class="fp-gcard-cat"><?php echo esc_html( $g['cat'] ); ?></span><?php endif; ?>
          <h3><?php echo esc_html( $g['title'] ); ?></h3>
          <?php if ( ! empty( $g['excerpt'] ) ) : ?><p><?php echo esc_html( $g['excerpt'] ); ?></p><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div><?php /* /fp-avis */ ?>

<script>
(function(){
  document.querySelectorAll('.fp-avis .fp-interblock').forEach(function(b){
    var t=b.querySelector('.fp-carousel-track'),n=b.querySelector('.fp-carousel-nav');
    if(!t||!n)return;
    var bs=n.querySelectorAll('.fp-carousel-btn');
    function s(){var c=t.querySelector('.fp-mini-card');return c?c.offsetWidth+12:200}
    function u(){var m=t.scrollWidth-t.clientWidth-1;bs[0].disabled=t.scrollLeft<=0;bs[1].disabled=t.scrollLeft>=m}
    bs.forEach(function(b){b.addEventListener('click',function(){t.scrollBy({left:+b.dataset.dir*s(),behavior:'smooth'})})});
    t.addEventListener('scroll',u,{passive:true});window.addEventListener('resize',u);u();
  });
})();
</script>
