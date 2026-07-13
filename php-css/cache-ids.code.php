<?php
/* =====================================================================
   MEILLEURTEST — Résolution automatique des cache IDs
   Snippet WPCodeBox / mu-plugin (PAS un bloc Bricks).
   Tourne sur `template_redirect` pour chaque comparatif consulté.

   Logique de matching (taxonomies post-type-produit + post-type-attribut) :
   1. Match exact : même produit ET mêmes attributs (ensemble identique)
   2. Fallback   : même produit (peu importe les attributs)
   Départage : plus petit ID.

   Mapping ACF → post types :
     mltv5_cached_id_astuces  → astuce
     mltv5_cached_id_criteres → critere
     mltv5_cached_id_faq      → faq
     mltv5_cached_id_marques  → marque
     mltv5_cached_id_raisons  → raison
     mltv5_cached_id_choix    → type-de-produit
     mltv5_cached_id_types    → type-de-produit
     mltv5_cached_id_vs       → vs
   ===================================================================== */

// ========================================
// CONFIGURATION
// ========================================
$debug       = true;
$TAX_PRODUCT = 'post-type-produit';
$TAX_ATTR    = 'post-type-attribut';
$RECHECK_TTL = 12 * HOUR_IN_SECONDS;   // recalcul max 1×/12h par comparatif (0 = à chaque visite)

// ============================================
// UTILITY FUNCTIONS
// ============================================

if ( ! function_exists( 'mltv5_get_term_ids' ) ) {
  function mltv5_get_term_ids( $post_id, $taxonomy ) {
    $terms = get_the_terms( $post_id, $taxonomy );
    if ( ! is_array( $terms ) || empty( $terms ) ) { return array(); }
    $ids = array();
    foreach ( $terms as $t ) {
      if ( is_object( $t ) && isset( $t->term_id ) ) {
        $ids[] = (int) $t->term_id;
      }
    }
    sort( $ids );
    return $ids;
  }
}

if ( ! function_exists( 'mltv5_find_linked_post' ) ) {
  /**
   * Trouve le meilleur post lié de type $target_type pour un comparatif.
   *
   * 1 seule WP_Query : tous les posts du type cible ayant le même produit.
   * Parmi les candidats : d'abord match exact sur les attributs, sinon
   * fallback = plus petit ID avec le même produit.
   */
  function mltv5_find_linked_post( $product_ids, $attr_ids, $target_type, $tax_product, $tax_attr, $debug = false ) {
    if ( empty( $product_ids ) ) { return null; }

    static $cache = array();
    $ck = implode( ',', $product_ids ) . '|' . implode( ',', $attr_ids ) . '|' . $target_type;
    if ( array_key_exists( $ck, $cache ) ) { return $cache[ $ck ]; } // null aussi mis en cache

    $candidates = get_posts( array(
      'post_type'      => $target_type,
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'orderby'        => 'ID',
      'order'          => 'ASC',
      'no_found_rows'  => true,
      'tax_query'      => array( array(
        'taxonomy' => $tax_product,
        'field'    => 'term_id',
        'terms'    => $product_ids,
        'operator' => 'AND',
      ) ),
    ) );

    if ( $debug ) {
      error_log( "mltv5_find: {$target_type} produit=[" . implode( ',', $product_ids )
        . '] attr=[' . implode( ',', $attr_ids ) . '] → ' . count( $candidates ) . ' candidat(s)' );
    }

    if ( empty( $candidates ) ) {
      $cache[ $ck ] = null;
      return null;
    }

    // Pré-charge les termes de tous les candidats (1 requête au lieu de N)
    update_object_term_cache( $candidates, $target_type );

    /* Match exact : même ensemble de produits ET même ensemble d'attributs.
       Un comparatif SANS attribut matche donc d'abord un candidat sans attribut
       (plutôt qu'un candidat spécialisé qui aurait juste un ID plus petit). */
    foreach ( $candidates as $cid ) {
      if ( mltv5_get_term_ids( (int) $cid, $tax_attr ) === $attr_ids
        && mltv5_get_term_ids( (int) $cid, $tax_product ) === $product_ids ) {
        if ( $debug ) { error_log( "  → exact: {$cid}" ); }
        $cache[ $ck ] = (int) $cid;
        return (int) $cid;
      }
    }
    if ( $debug ) { error_log( '  → pas de match exact, fallback produit seul (plus petit ID)' ); }

    // Fallback : premier candidat (plus petit ID)
    $result = (int) $candidates[0];
    if ( $debug ) { error_log( "  → fallback: {$result}" ); }
    $cache[ $ck ] = $result;
    return $result;
  }
}

// ============================================
// MAIN EXECUTION
// ============================================
add_action( 'template_redirect', function() use ( $debug, $TAX_PRODUCT, $TAX_ATTR, $RECHECK_TTL ) {

  if ( ! is_singular() ) { return; }

  $post_id = get_the_ID();
  if ( get_post_type( $post_id ) !== 'comparatif' ) { return; }

  /* Verrou anti-recalcul : une fois traité, on ne refait le travail qu'après
     expiration du transient (posé AVANT le traitement pour éviter les rafales
     si plusieurs visites arrivent en même temps). */
  if ( $RECHECK_TTL > 0 ) {
    $lock = 'mltv5_cache_ids_' . $post_id;
    if ( get_transient( $lock ) ) { return; }
    set_transient( $lock, 1, $RECHECK_TTL );
  }

  $product_ids = mltv5_get_term_ids( $post_id, $TAX_PRODUCT );
  $attr_ids    = mltv5_get_term_ids( $post_id, $TAX_ATTR );

  if ( empty( $product_ids ) ) {
    if ( $debug ) { error_log( "mltv5_cache: aucun {$TAX_PRODUCT} pour comparatif {$post_id}" ); }
    return;
  }

  if ( $debug ) {
    error_log( 'mltv5_cache: comparatif ' . $post_id
      . ' produit=[' . implode( ',', $product_ids ) . ']'
      . ' attr=[' . implode( ',', $attr_ids ) . ']' );
  }

  $field_mapping = array(
    'mltv5_cached_id_astuces'  => 'astuce',
    'mltv5_cached_id_criteres' => 'critere',
    'mltv5_cached_id_faq'      => 'faq',
    'mltv5_cached_id_marques'  => 'marque',
    'mltv5_cached_id_raisons'  => 'raison',
    'mltv5_cached_id_choix'    => 'type-de-produit',
    'mltv5_cached_id_types'    => 'type-de-produit',
    'mltv5_cached_id_vs'       => 'vs',
  );

  foreach ( $field_mapping as $acf_field => $target_type ) {
    $current = get_field( $acf_field, $post_id );
    if ( is_object( $current ) && isset( $current->ID ) ) {
      $current = (int) $current->ID;
    } elseif ( is_array( $current ) && isset( $current['ID'] ) ) {
      $current = (int) $current['ID'];
    } else {
      $current = $current ? (int) $current : null;
    }

    $expected = mltv5_find_linked_post( $product_ids, $attr_ids, $target_type, $TAX_PRODUCT, $TAX_ATTR, $debug );

    if ( $current !== $expected ) {
      update_field( $acf_field, $expected, $post_id );
      if ( $debug ) {
        error_log( '  🔄 ' . $acf_field . ': ' . ( $current ?: 'null' ) . ' → ' . ( $expected ?: 'null' ) );
      }
    } elseif ( $debug ) {
      error_log( '  ✓ ' . $acf_field . ' = ' . ( $current ?: 'null' ) );
    }
  }

  if ( $debug ) { error_log( 'mltv5_cache: terminé pour comparatif ' . $post_id ); }
} );
