<?php
/* =====================================================================
   MEILLEURTEST — Résolution automatique des cache IDs
   Snippet WPCodeBox / mu-plugin (PAS un bloc Bricks).

   2 modes (cumulables) :
   • AU FIL DE L'EAU : sur `template_redirect`, chaque comparatif consulté
     est (re)calculé, au plus 1×/12h (verrou transient).
   • BATCH (WP-Cron) : traite TOUS les comparatifs par lots de $BATCH_SIZE
     toutes les 2 minutes, puis s'arrête tout seul. Pilotage (admin
     connecté, sur n'importe quelle URL du site) :
       ?mltv5_batch=start   → (re)lance le batch depuis le début
       ?mltv5_batch=status  → avancement
       ?mltv5_batch=stop    → arrête le batch
     ⚠️ WP-Cron est déclenché par le trafic (ou le cron système Cloudways
     vers wp-cron.php). `start` traite le 1er lot immédiatement.

   Logique de matching (taxonomies post-type-produit + post-type-attribut) :
   1. Match exact : même ensemble de produits ET même ensemble d'attributs
      (un comparatif sans attribut matche d'abord un candidat sans attribut)
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
$BATCH_SIZE  = 20;                      // comparatifs traités par lot de 2 minutes

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
   * Parmi les candidats : d'abord match exact (produits + attributs),
   * sinon fallback = plus petit ID avec le même produit.
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

if ( ! function_exists( 'mltv5_process_comparatif' ) ) {
  /* Recalcule les 8 cache IDs d'UN comparatif. Renvoie le nb de champs modifiés. */
  function mltv5_process_comparatif( $post_id, $tax_product, $tax_attr, $debug = false ) {
    $product_ids = mltv5_get_term_ids( $post_id, $tax_product );
    $attr_ids    = mltv5_get_term_ids( $post_id, $tax_attr );

    if ( empty( $product_ids ) ) {
      if ( $debug ) { error_log( "mltv5_cache: aucun {$tax_product} pour comparatif {$post_id}" ); }
      return 0;
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

    $updated = 0;
    foreach ( $field_mapping as $acf_field => $target_type ) {
      $current = get_field( $acf_field, $post_id );
      if ( is_object( $current ) && isset( $current->ID ) ) {
        $current = (int) $current->ID;
      } elseif ( is_array( $current ) && isset( $current['ID'] ) ) {
        $current = (int) $current['ID'];
      } else {
        $current = $current ? (int) $current : null;
      }

      $expected = mltv5_find_linked_post( $product_ids, $attr_ids, $target_type, $tax_product, $tax_attr, $debug );

      if ( $current !== $expected ) {
        update_field( $acf_field, $expected, $post_id );
        $updated++;
        if ( $debug ) {
          error_log( '  🔄 ' . $acf_field . ': ' . ( $current ?: 'null' ) . ' → ' . ( $expected ?: 'null' ) );
        }
      } elseif ( $debug ) {
        error_log( '  ✓ ' . $acf_field . ' = ' . ( $current ?: 'null' ) );
      }
    }
    return $updated;
  }
}

// ============================================
// MODE 1 — AU FIL DE L'EAU (visite d'un comparatif)
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

  mltv5_process_comparatif( $post_id, $TAX_PRODUCT, $TAX_ATTR, $debug );
} );

// ============================================
// MODE 2 — BATCH WP-CRON (lots de $BATCH_SIZE toutes les 2 min)
// ============================================
add_filter( 'cron_schedules', function( $s ) {
  if ( ! isset( $s['mltv5_2min'] ) ) {
    $s['mltv5_2min'] = array( 'interval' => 120, 'display' => 'Toutes les 2 minutes (mltv5)' );
  }
  return $s;
} );

/* Traite UN lot. Renvoie le nb de comparatifs traités dans ce lot. */
if ( ! function_exists( 'mltv5_cache_ids_run_batch' ) ) {
  function mltv5_cache_ids_run_batch( $batch_size, $tax_product, $tax_attr, $recheck_ttl, $debug = false ) {
    $cursor = (int) get_option( 'mltv5_cache_ids_cursor', 0 );

    $ids = get_posts( array(
      'post_type'      => 'comparatif',
      'post_status'    => 'publish',
      'posts_per_page' => (int) $batch_size,
      'offset'         => $cursor,
      'fields'         => 'ids',
      'orderby'        => 'ID',
      'order'          => 'ASC',
      'no_found_rows'  => true,
    ) );

    foreach ( $ids as $pid ) {
      mltv5_process_comparatif( (int) $pid, $tax_product, $tax_attr, $debug );
      /* Pose le même verrou que le mode visite : pas de re-calcul au prochain
         affichage front des pages déjà traitées par le batch. */
      if ( $recheck_ttl > 0 ) { set_transient( 'mltv5_cache_ids_' . $pid, 1, $recheck_ttl ); }
    }

    $done = count( $ids );
    update_option( 'mltv5_cache_ids_cursor', $cursor + $done, false );

    if ( $done < (int) $batch_size ) {           // plus rien à traiter → fin
      wp_clear_scheduled_hook( 'mltv5_cache_ids_batch_event' );
      update_option( 'mltv5_cache_ids_batch_done', current_time( 'mysql' ), false );
      error_log( 'mltv5_batch: TERMINÉ — ' . ( $cursor + $done ) . ' comparatifs traités.' );
    } elseif ( $debug ) {
      error_log( 'mltv5_batch: lot de ' . $done . ' traité, cursor=' . ( $cursor + $done ) );
    }
    return $done;
  }
}

add_action( 'mltv5_cache_ids_batch_event', function() use ( $debug, $TAX_PRODUCT, $TAX_ATTR, $RECHECK_TTL, $BATCH_SIZE ) {
  mltv5_cache_ids_run_batch( $BATCH_SIZE, $TAX_PRODUCT, $TAX_ATTR, $RECHECK_TTL, $debug );
} );

/* Pilotage admin : ?mltv5_batch=start|status|stop sur n'importe quelle URL. */
add_action( 'init', function() use ( $debug, $TAX_PRODUCT, $TAX_ATTR, $RECHECK_TTL, $BATCH_SIZE ) {
  if ( ! isset( $_GET['mltv5_batch'] ) ) { return; }
  if ( ! current_user_can( 'manage_options' ) ) { return; }

  $cmd   = sanitize_key( $_GET['mltv5_batch'] );
  $total = wp_count_posts( 'comparatif' );
  $total = isset( $total->publish ) ? (int) $total->publish : 0;

  if ( $cmd === 'start' ) {
    update_option( 'mltv5_cache_ids_cursor', 0, false );
    delete_option( 'mltv5_cache_ids_batch_done' );
    if ( ! wp_next_scheduled( 'mltv5_cache_ids_batch_event' ) ) {
      wp_schedule_event( time() + 120, 'mltv5_2min', 'mltv5_cache_ids_batch_event' );
    }
    $n = mltv5_cache_ids_run_batch( $BATCH_SIZE, $TAX_PRODUCT, $TAX_ATTR, $RECHECK_TTL, $debug ); // 1er lot tout de suite
    wp_die( 'mltv5_batch : lancé. 1er lot traité (' . (int) $n . '), '
      . (int) get_option( 'mltv5_cache_ids_cursor', 0 ) . '/' . $total
      . ' — la suite tourne en WP-Cron toutes les 2 min. Suivi : ?mltv5_batch=status' );
  }

  if ( $cmd === 'stop' ) {
    wp_clear_scheduled_hook( 'mltv5_cache_ids_batch_event' );
    wp_die( 'mltv5_batch : arrêté (cursor conservé à '
      . (int) get_option( 'mltv5_cache_ids_cursor', 0 ) . '/' . $total . ').' );
  }

  if ( $cmd === 'status' ) {
    $cursor = (int) get_option( 'mltv5_cache_ids_cursor', 0 );
    $next   = wp_next_scheduled( 'mltv5_cache_ids_batch_event' );
    $fin    = get_option( 'mltv5_cache_ids_batch_done', '' );
    wp_die( 'mltv5_batch : ' . $cursor . '/' . $total . ' comparatifs traités. '
      . ( $fin ? 'TERMINÉ le ' . esc_html( $fin ) . '.'
               : ( $next ? 'Prochain lot dans ' . max( 0, $next - time() ) . ' s.'
                         : 'Aucun batch planifié.' ) ) );
  }
} );
