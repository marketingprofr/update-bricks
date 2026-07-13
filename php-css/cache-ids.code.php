<?php
/* =====================================================================
   MEILLEURTEST — Résolution automatique des cache IDs
   Snippet WPCodeBox / mu-plugin (PAS un bloc Bricks).

   2 modes (cumulables) :
   • AU FIL DE L'EAU : sur `template_redirect`, chaque comparatif consulté
     est (re)calculé, au plus 1×/12h (verrou transient).
   • BATCH PAR PAGE AUTO-RELOAD (pas de cron) : une page admin traite un
     lot de $BATCH_SIZE comparatifs puis se recharge toute seule toutes
     les $BATCH_REFRESH secondes jusqu'à la fin. Laisser l'onglet ouvert.
     Pilotage (admin connecté, sur n'importe quelle URL du site) :
       ?mltv5_batch=start   → (re)démarre depuis le début et enchaîne
       ?mltv5_batch=run     → reprend là où le curseur en est
       ?mltv5_batch=status  → avancement (sans rien traiter)

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
$debug         = true;
$TAX_PRODUCT   = 'post-type-produit';
$TAX_ATTR      = 'post-type-attribut';
$RECHECK_TTL   = 12 * HOUR_IN_SECONDS;  // recalcul max 1×/12h par comparatif (0 = à chaque visite)
$BATCH_SIZE    = 20;                     // comparatifs traités par lot
$BATCH_REFRESH = 5;                      // secondes entre deux lots (reload de la page)

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
// MODE 2 — BATCH PAR PAGE AUTO-RELOAD (sans cron)
// ============================================

/* Traite UN lot. Renvoie array(done, cursor, finished, updated). */
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

    $updated = 0;
    foreach ( $ids as $pid ) {
      $updated += mltv5_process_comparatif( (int) $pid, $tax_product, $tax_attr, $debug );
      /* Pose le même verrou que le mode visite : pas de re-calcul au prochain
         affichage front des pages déjà traitées par le batch. */
      if ( $recheck_ttl > 0 ) { set_transient( 'mltv5_cache_ids_' . $pid, 1, $recheck_ttl ); }
    }

    $done     = count( $ids );
    $cursor  += $done;
    $finished = ( $done < (int) $batch_size );
    update_option( 'mltv5_cache_ids_cursor', $cursor, false );
    if ( $finished ) {
      update_option( 'mltv5_cache_ids_batch_done', current_time( 'mysql' ), false );
      error_log( 'mltv5_batch: TERMINÉ — ' . $cursor . ' comparatifs traités.' );
    } elseif ( $debug ) {
      error_log( 'mltv5_batch: lot de ' . $done . ' traité, cursor=' . $cursor );
    }
    return array( 'done' => $done, 'cursor' => $cursor, 'finished' => $finished, 'updated' => $updated );
  }
}

/* Page de suivi : barre de progression + auto-reload tant que ce n'est pas fini. */
if ( ! function_exists( 'mltv5_batch_render' ) ) {
  function mltv5_batch_render( $cursor, $total, $finished, $refresh, $url, $note = '' ) {
    $pct = $total > 0 ? min( 100, round( $cursor / $total * 100 ) ) : 100;
    header( 'Content-Type: text/html; charset=utf-8' );
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
       . '<meta name="robots" content="noindex">'
       . '<title>Batch cache IDs — ' . (int) $pct . '%</title>';
    if ( ! $finished ) {
      echo '<meta http-equiv="refresh" content="' . (int) $refresh . ';url=' . esc_url( $url ) . '">';
    }
    echo '<style>body{font:15px/1.6 ui-monospace,Menlo,monospace;background:#14181d;color:#e8eaed;'
       . 'display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}'
       . '.card{width:min(560px,90vw);background:#2a3038;border-radius:12px;padding:28px 32px}'
       . 'h1{font-size:17px;margin:0 0 16px}'
       . '.bar{height:14px;background:#14181d;border-radius:999px;overflow:hidden;margin:12px 0}'
       . '.fill{height:100%;background:' . ( $finished ? '#6dd6a8' : '#4a90d9' ) . ';width:' . (int) $pct . '%}'
       . 'p{margin:8px 0;color:#9aa3ad}.ok{color:#6dd6a8;font-weight:700}</style></head><body><div class="card">'
       . '<h1>Batch cache IDs (produit + attributs)</h1>'
       . '<div class="bar"><div class="fill"></div></div>'
       . '<p><b style="color:#e8eaed">' . (int) $cursor . ' / ' . (int) $total . '</b> comparatifs trait&eacute;s (' . (int) $pct . '%)</p>';
    if ( $note !== '' ) { echo '<p>' . $note . '</p>'; }
    if ( $finished ) {
      echo '<p class="ok">✅ TERMIN&Eacute; — vous pouvez fermer cet onglet.</p>';
    } else {
      echo '<p>Prochain lot dans ' . (int) $refresh . '&nbsp;s&hellip; laissez cet onglet ouvert.</p>'
         . '<p style="font-size:12px">Pour arr&ecirc;ter : fermez l&rsquo;onglet. Pour reprendre : <code>?mltv5_batch=run</code></p>';
    }
    echo '</div></body></html>';
    exit;
  }
}

/* Pilotage admin : ?mltv5_batch=start|run|status sur n'importe quelle URL. */
add_action( 'init', function() use ( $debug, $TAX_PRODUCT, $TAX_ATTR, $RECHECK_TTL, $BATCH_SIZE, $BATCH_REFRESH ) {
  if ( ! isset( $_GET['mltv5_batch'] ) ) { return; }
  if ( ! current_user_can( 'manage_options' ) ) { return; }

  nocache_headers();
  $cmd   = sanitize_key( $_GET['mltv5_batch'] );
  $total = wp_count_posts( 'comparatif' );
  $total = isset( $total->publish ) ? (int) $total->publish : 0;
  $url   = home_url( '/?mltv5_batch=run' );

  if ( $cmd === 'status' ) {
    $cursor = (int) get_option( 'mltv5_cache_ids_cursor', 0 );
    $fin    = (string) get_option( 'mltv5_cache_ids_batch_done', '' );
    mltv5_batch_render( $cursor, $total, true, 0, '',
      $fin !== '' ? 'Dernier passage complet : ' . esc_html( $fin ) : 'Batch non termin&eacute; (curseur en cours).' );
  }

  if ( $cmd === 'start' ) {
    update_option( 'mltv5_cache_ids_cursor', 0, false );
    delete_option( 'mltv5_cache_ids_batch_done' );
    wp_clear_scheduled_hook( 'mltv5_cache_ids_batch_event' ); // nettoie l'ancien mode cron s'il traîne
    $cmd = 'run';
  }

  if ( $cmd === 'run' ) {
    /* Verrou anti-doublon : si un lot tourne déjà (2e onglet), on attend. */
    if ( get_transient( 'mltv5_batch_lock' ) ) {
      mltv5_batch_render( (int) get_option( 'mltv5_cache_ids_cursor', 0 ), $total, false, $BATCH_REFRESH, $url,
        'Un lot est d&eacute;j&agrave; en cours dans un autre onglet&hellip;' );
    }
    set_transient( 'mltv5_batch_lock', 1, 300 );
    $res = mltv5_cache_ids_run_batch( $BATCH_SIZE, $TAX_PRODUCT, $TAX_ATTR, $RECHECK_TTL, $debug );
    delete_transient( 'mltv5_batch_lock' );

    mltv5_batch_render( $res['cursor'], $total, $res['finished'], $BATCH_REFRESH, $url,
      'Dernier lot : ' . (int) $res['done'] . ' comparatifs, ' . (int) $res['updated'] . ' champ(s) mis &agrave; jour.' );
  }
} );
