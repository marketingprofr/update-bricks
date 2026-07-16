<?php
/* =====================================================================
   MEILLEURTEST — Nettoyage des FAQ manuelles (doublons auto Q/R)
   À coller dans WPCodeBox. N'agit QUE si ?faq_cleanup=1 est présent.

   LECTURE via post meta BRUT (pas get_field) : évite le formatage ACF
   (wptexturize + do_shortcode sur les WYSIWYG) qui faisait fataler/
   timeouter sur 50 posts. L'écriture (runall) utilise update_field.
   Paginé par lots pour la mémoire (6300+ posts).
   ===================================================================== */
if ( ! isset( $_GET['faq_cleanup'] ) ) { return; }
if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) { return; }

/* DEBUG : capture le vrai fatal que WPCodeBox avale et l'affiche. */
@ini_set( 'display_errors', '1' );
register_shutdown_function( function () {
  $e = error_get_last();
  if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
    echo '<pre style="background:#fdecea;border:2px solid #c0392b;padding:12px;margin:20px;white-space:pre-wrap;font:13px monospace;color:#7b241c;position:relative;z-index:99999">'
       . 'FAQ CLEANUP — FATAL CAPTURÉ :' . "\n"
       . htmlspecialchars( (string) $e['message'] ) . "\n"
       . 'Fichier : ' . htmlspecialchars( (string) $e['file'] ) . ':' . (int) $e['line'] . "\n"
       . 'Mémoire pic : ' . round( memory_get_peak_usage( true ) / 1048576, 1 ) . ' Mo / limite ' . ini_get( 'memory_limit' )
       . '</pre>';
  }
} );

$faq_action  = isset( $_GET['faq_action'] ) ? sanitize_text_field( $_GET['faq_action'] ) : '';
$faq_page    = isset( $_GET['faq_page'] ) ? max( 1, (int) $_GET['faq_page'] ) : 1;
$faq_base    = home_url( '/?faq_cleanup=1' );
$PER_PAGE    = 25;

/* CONFIG */
$faq_repeater = 'mltv5_faq_comparatif';
$faq_q_key   = 'mltv5_faq_comparatif_question';
$faq_a_key   = 'mltv5_faq_comparatif_reponse';

$faq_bad_words = array(
  'meilleur','meilleure','meilleurs','meilleures',
  'combien','coute','coutent','budget','prix',
  'marque','marques','choisir','critere','criteres',
);

/* Troncature safe */
$trunc = function( $s, $len ) {
  $s = (string) $s;
  if ( function_exists( 'mb_strlen' ) && mb_strlen( $s, 'UTF-8' ) > $len ) {
    return mb_substr( $s, 0, $len, 'UTF-8' ) . '...';
  }
  if ( strlen( $s ) > $len ) { return substr( $s, 0, $len ) . '...'; }
  return $s;
};

/* Strip accents pour comparaison */
$faq_strip = function( $s ) {
  return strtr( strtolower( trim( wp_strip_all_tags( (string) $s ) ) ), array(
    'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
    'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
    'ç'=>'c','œ'=>'oe','æ'=>'ae',
  ));
};

$faq_match = function( $question ) use ( $faq_strip, $faq_bad_words ) {
  $q = $faq_strip( $question );
  foreach ( $faq_bad_words as $w ) {
    if ( strpos( $q, $w ) !== false ) { return $w; }
  }
  return false;
};

/* Reconstruit les rows du repeater depuis le meta BRUT (pas get_field).
   ACF stocke :  {repeater} = count ; {repeater}_{i}_{subfield} = valeur.
   get_post_meta($pid) prime tout le meta en 1 requête (cache). */
$faq_rows = function( $pid ) use ( $faq_repeater, $faq_q_key, $faq_a_key ) {
  $count = (int) get_post_meta( $pid, $faq_repeater, true );
  if ( $count < 1 ) { return array(); }
  $out = array();
  for ( $i = 0; $i < $count; $i++ ) {
    $out[] = array(
      'i' => $i,
      'q' => (string) get_post_meta( $pid, $faq_repeater . '_' . $i . '_' . $faq_q_key, true ),
      'a' => (string) get_post_meta( $pid, $faq_repeater . '_' . $i . '_' . $faq_a_key, true ),
    );
  }
  return $out;
};

/* Compte total */
$count_q = new WP_Query( array(
  'post_type'      => 'faq',
  'post_status'    => array( 'publish', 'draft', 'private' ),
  'posts_per_page' => 1,
  'fields'         => 'ids',
) );
$total_posts = (int) $count_q->found_posts;
$total_pages = max( 1, (int) ceil( $total_posts / $PER_PAGE ) );
wp_reset_postdata();

/* Lot courant (IDs + cache meta amorcé pour éviter le N+1) */
$faq_posts = get_posts( array(
  'post_type'              => 'faq',
  'post_status'            => array( 'publish', 'draft', 'private' ),
  'posts_per_page'         => $PER_PAGE,
  'paged'                  => $faq_page,
  'fields'                 => 'ids',
  'no_found_rows'          => true,
  'orderby'                => 'ID',
  'order'                  => 'ASC',
  'update_post_meta_cache' => true,
  'update_post_term_cache' => false,
) );

/* Pagination */
$pager = function( $act ) use ( $faq_base, $faq_page, $total_pages, $total_posts ) {
  $s  = '<p style="font-size:13px;color:#666">';
  $s .= $total_posts . ' posts &mdash; page ' . $faq_page . '/' . $total_pages;
  if ( $faq_page > 1 ) {
    $s .= ' | <a href="' . esc_url( $faq_base . '&faq_action=' . $act . '&faq_page=' . ( $faq_page - 1 ) ) . '">Prec.</a>';
  }
  if ( $faq_page < $total_pages ) {
    $s .= ' | <a href="' . esc_url( $faq_base . '&faq_action=' . $act . '&faq_page=' . ( $faq_page + 1 ) ) . '">Suiv.</a>';
  }
  $s .= '</p>';
  return $s;
};

/* ── PAGE D'ACCUEIL ─────────────────────────────────────── */
if ( $faq_action === '' ) {
  echo '<div style="max-width:700px;margin:40px auto;font:15px/1.6 system-ui,sans-serif">';
  echo '<h2>Nettoyage FAQ</h2>';
  echo '<p>' . $total_posts . ' posts FAQ. Lots de ' . $PER_PAGE . '.</p>';
  echo '<ol style="line-height:2.2">';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=export' ) . '">Exporter</a></li>';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=dryrun' ) . '">Dry-run</a></li>';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=runall' ) . '" onclick="return confirm(\'OK ?\')">Tout nettoyer (auto-reload)</a></li>';
  echo '</ol></div>';
  return;
}

/* ── EXPORT ──────────────────────────────────────────────── */
if ( $faq_action === 'export' ) {
  echo '<div style="max-width:1200px;margin:20px auto;font:13px/1.5 system-ui,sans-serif">';
  echo '<h2 style="font-size:18px">Export</h2>';
  echo $pager( 'export' );
  echo '<table style="width:100%;border-collapse:collapse">';
  echo '<tr style="background:#eee"><th style="padding:4px 6px;text-align:left">ID</th><th style="padding:4px 6px;text-align:left">Post</th><th style="padding:4px 6px;text-align:left">#</th><th style="padding:4px 6px;text-align:left">Question</th><th style="padding:4px 6px;text-align:left">Reponse</th></tr>';

  $n = 0;
  foreach ( $faq_posts as $pid ) {
    try {
      $rows = $faq_rows( (int) $pid );
      if ( empty( $rows ) ) { continue; }
      $t = (string) get_the_title( (int) $pid );
      foreach ( $rows as $r ) {
        $q = trim( wp_strip_all_tags( $r['q'] ) );
        $a = trim( wp_strip_all_tags( $r['a'] ) );
        echo '<tr>';
        echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd">' . (int) $pid . '</td>';
        echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd">' . esc_html( $trunc( $t, 35 ) ) . '</td>';
        echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd">' . (int) $r['i'] . '</td>';
        echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd">' . esc_html( $trunc( $q, 70 ) ) . '</td>';
        echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd;color:#888">' . esc_html( $trunc( $a, 80 ) ) . '</td>';
        echo '</tr>';
        $n++;
      }
    } catch ( \Throwable $e ) {
      echo '<tr><td colspan="5" style="padding:6px;background:#fdecea;color:#c0392b">'
         . 'ERREUR post #' . (int) $pid . ' : ' . esc_html( $e->getMessage() )
         . ' (' . esc_html( basename( (string) $e->getFile() ) ) . ':' . (int) $e->getLine() . ')</td></tr>';
    }
  }
  echo '</table>';
  echo '<p>' . $n . ' Q/R. ' . $pager( 'export' ) . '</p>';
  echo '<p><a href="' . esc_url( $faq_base ) . '">Retour</a></p></div>';
  return;
}

/* ── DRYRUN ──────────────────────────────────────────────── */
if ( $faq_action === 'dryrun' ) {
  echo '<div style="max-width:1000px;margin:20px auto;font:13px/1.5 system-ui,sans-serif">';
  echo '<h2 style="font-size:18px">Dry-run</h2>';
  echo $pager( 'dryrun' );
  echo '<table style="width:100%;border-collapse:collapse">';
  echo '<tr style="background:#eee"><th style="padding:4px 6px;text-align:left">Post</th><th style="padding:4px 6px;text-align:left">Question</th><th style="padding:4px 6px;text-align:left">Mot</th></tr>';

  $n_rm = 0;
  foreach ( $faq_posts as $pid ) {
    $rows = $faq_rows( (int) $pid );
    if ( empty( $rows ) ) { continue; }
    $t = (string) get_the_title( (int) $pid );
    foreach ( $rows as $r ) {
      $w = $faq_match( $r['q'] );
      if ( $w === false ) { continue; }
      $n_rm++;
      echo '<tr style="background:#fff8e1">';
      echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd">' . esc_html( $trunc( $t, 40 ) ) . ' #' . (int) $pid . '</td>';
      echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd">' . esc_html( $trunc( wp_strip_all_tags( $r['q'] ), 75 ) ) . '</td>';
      echo '<td style="padding:3px 6px;border-bottom:1px solid #ddd;color:#e67e22"><b>' . esc_html( $w ) . '</b></td>';
      echo '</tr>';
    }
  }
  echo '</table>';
  echo '<p><b>' . $n_rm . '</b> a supprimer sur cette page.</p>';
  echo $pager( 'dryrun' );
  echo '<p><a href="' . esc_url( $faq_base ) . '">Retour</a></p></div>';
  return;
}

/* ── RUNALL (lot par lot, auto-reload) ───────────────────── */
if ( $faq_action === 'runall' ) {
  if ( ! function_exists( 'update_field' ) ) { echo '<p>ACF requis pour l\'ecriture.</p>'; return; }

  $n_rm = 0; $n_kept = 0; $n_touched = 0;

  foreach ( $faq_posts as $pid ) {
    $rows = $faq_rows( (int) $pid );
    if ( empty( $rows ) ) { continue; }

    $kept = array();
    $had  = false;
    foreach ( $rows as $r ) {
      if ( $faq_match( $r['q'] ) !== false ) {
        $n_rm++;
        $had = true;
      } else {
        /* Row reconstruite au format ACF (clés = noms de sous-champs). */
        $kept[] = array(
          $faq_q_key => $r['q'],
          $faq_a_key => $r['a'],
        );
      }
    }
    if ( $had ) {
      update_field( $faq_repeater, array_values( $kept ), (int) $pid );
      $n_touched++;
    }
    $n_kept += count( $kept );
  }

  echo '<div style="max-width:700px;margin:40px auto;font:14px/1.6 system-ui,sans-serif">';
  echo '<h2 style="font-size:18px">Lot ' . $faq_page . '/' . $total_pages . '</h2>';
  echo '<p>' . $n_touched . ' posts modifies, ' . $n_rm . ' rows supprimees, ' . $n_kept . ' conservees sur ce lot.</p>';

  if ( $faq_page < $total_pages ) {
    $next = $faq_base . '&faq_action=runall&faq_page=' . ( $faq_page + 1 );
    echo '<p>Lot suivant dans 2s...</p>';
    echo '<meta http-equiv="refresh" content="2;url=' . esc_url( $next ) . '">';
    echo '<p><a href="' . esc_url( $next ) . '">Cliquer si pas de redirection</a></p>';
  } else {
    echo '<p style="color:green"><b>Termine ! ' . $total_posts . ' posts traites.</b></p>';
  }
  echo '<p><a href="' . esc_url( $faq_base ) . '">Retour</a></p></div>';
  return;
}
