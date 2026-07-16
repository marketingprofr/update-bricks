<?php
/* =====================================================================
   MEILLEURTEST — Nettoyage des FAQ manuelles (doublons auto Q/R)
   À coller dans WPCodeBox. N'agit QUE si ?faq_cleanup=1 est présent.
   Paginé par lots de 50 pour ne pas exploser la mémoire (6000+ posts).
   ===================================================================== */
if ( ! isset( $_GET['faq_cleanup'] ) ) { return; }
if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) { return; }
if ( ! function_exists( 'get_field' ) ) { echo '<p>ACF requis.</p>'; return; }

$faq_action  = isset( $_GET['faq_action'] ) ? sanitize_text_field( $_GET['faq_action'] ) : '';
$faq_page    = isset( $_GET['faq_page'] ) ? max( 1, (int) $_GET['faq_page'] ) : 1;
$faq_base    = home_url( '/?faq_cleanup=1' );
$PER_PAGE    = 50;

/* CONFIG */
$faq_repeater = 'mltv5_faq_comparatif';
$faq_q_key   = 'mltv5_faq_comparatif_question';
$faq_a_key   = 'mltv5_faq_comparatif_reponse';

$faq_bad_words = array(
  'meilleur','meilleure','meilleurs','meilleures',
  'combien','coute','coutent','budget','prix',
  'marque','marques','choisir','critere','criteres',
);

$faq_strip = function( $s ) {
  return strtr( mb_strtolower( trim( wp_strip_all_tags( $s ) ), 'UTF-8' ), array(
    'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
    'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
    'ç'=>'c','œ'=>'oe','æ'=>'ae',
  ));
};

$faq_match = function( $question ) use ( $faq_strip, $faq_bad_words ) {
  $q = $faq_strip( $question );
  foreach ( $faq_bad_words as $w ) {
    if ( mb_strpos( $q, $w ) !== false ) { return $w; }
  }
  return false;
};

/* Compte total (léger : juste les IDs) */
$total_q = new WP_Query( array(
  'post_type'      => 'faq',
  'post_status'    => array( 'publish', 'draft', 'private' ),
  'posts_per_page' => 1,
  'fields'         => 'ids',
) );
$total_posts = $total_q->found_posts;
$total_pages = max( 1, ceil( $total_posts / $PER_PAGE ) );
wp_reset_postdata();

/* Lot courant */
$faq_posts = get_posts( array(
  'post_type'      => 'faq',
  'post_status'    => array( 'publish', 'draft', 'private' ),
  'posts_per_page' => $PER_PAGE,
  'paged'          => $faq_page,
  'fields'         => 'ids',
  'no_found_rows'  => true,
  'orderby'        => 'ID',
  'order'          => 'ASC',
) );

/* Pagination HTML */
$pager = function( $act ) use ( $faq_base, $faq_page, $total_pages, $total_posts, $PER_PAGE ) {
  $s  = '<div style="margin:12px 0;font-size:13px;color:#666">';
  $s .= $total_posts . ' posts FAQ &mdash; page ' . $faq_page . '/' . $total_pages . ' (lot de ' . $PER_PAGE . ')';
  if ( $faq_page > 1 ) {
    $s .= ' &middot; <a href="' . esc_url( $faq_base . '&faq_action=' . $act . '&faq_page=' . ( $faq_page - 1 ) ) . '">&larr; Pr&eacute;c&eacute;dent</a>';
  }
  if ( $faq_page < $total_pages ) {
    $s .= ' &middot; <a href="' . esc_url( $faq_base . '&faq_action=' . $act . '&faq_page=' . ( $faq_page + 1 ) ) . '">Suivant &rarr;</a>';
  }
  $s .= '</div>';
  return $s;
};

/* ── PAGE D'ACCUEIL ─────────────────────────────────────── */
if ( $faq_action === '' ) {
  echo '<div style="max-width:700px;margin:40px auto;font:15px/1.6 system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="margin:0 0 16px">Nettoyage des FAQ manuelles</h2>';
  echo '<p><strong>' . $total_posts . '</strong> posts FAQ trouv&eacute;s. Traitement par lots de ' . $PER_PAGE . '.</p>';
  echo '<ol style="margin:20px 0;line-height:2">';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=export' ) . '">1. Exporter</a> (v&eacute;rifier les donn&eacute;es, page par page)</li>';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=dryrun' ) . '">2. Dry-run</a> (voir ce qui serait supprim&eacute;, page par page)</li>';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=runall' ) . '" onclick="return confirm(\'Lancer le nettoyage sur les ' . $total_posts . ' posts ?\')">3. Tout nettoyer</a> (traite TOUT, lot par lot, avec auto-reload)</li>';
  echo '</ol></div>';
  return;
}

/* ── EXPORT (page par page) ──────────────────────────────── */
if ( $faq_action === 'export' ) {
  echo '<div style="max-width:1200px;margin:40px auto;font:13px/1.5 system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="margin:0 0 4px;font-size:18px">Export FAQ</h2>';
  echo $pager( 'export' );

  echo '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">';
  echo '<tr style="background:#f5f6f8"><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">ID</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Post</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">#</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Question</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">R&eacute;ponse (extrait)</th></tr>';

  $n = 0;
  foreach ( $faq_posts as $pid ) {
    $rows = get_field( $faq_repeater, $pid );
    if ( ! is_array( $rows ) || empty( $rows ) ) { continue; }
    $t = get_the_title( $pid );
    foreach ( $rows as $i => $r ) {
      $q = isset( $r[ $faq_q_key ] ) ? trim( wp_strip_all_tags( (string) $r[ $faq_q_key ] ) ) : '';
      $a = isset( $r[ $faq_a_key ] ) ? trim( wp_strip_all_tags( (string) $r[ $faq_a_key ] ) ) : '';
      echo '<tr><td style="padding:4px 8px;border-bottom:1px solid #eee">' . (int) $pid . '</td>';
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . esc_html( mb_strimwidth( $t, 0, 40, '...' ) ) . '</td>';
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . (int) $i . '</td>';
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . esc_html( $q ) . '</td>';
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee;color:#666">' . esc_html( mb_strimwidth( $a, 0, 100, '...' ) ) . '</td></tr>';
      $n++;
    }
  }
  echo '</table></div>';
  echo '<p>' . $n . ' Q/R sur cette page.</p>';
  echo $pager( 'export' );
  echo '<p><a href="' . esc_url( $faq_base ) . '">&larr; Retour</a></p>';
  echo '</div>';
  return;
}

/* ── DRYRUN (page par page) ──────────────────────────────── */
if ( $faq_action === 'dryrun' ) {
  echo '<div style="max-width:1000px;margin:40px auto;font:13px/1.5 system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="margin:0 0 4px;font-size:18px">DRY-RUN</h2>';
  echo $pager( 'dryrun' );

  $n_rm = 0;
  echo '<table style="width:100%;border-collapse:collapse">';
  echo '<tr style="background:#f5f6f8"><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Post</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Question</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Mot</th></tr>';

  foreach ( $faq_posts as $pid ) {
    $rows = get_field( $faq_repeater, $pid );
    if ( ! is_array( $rows ) || empty( $rows ) ) { continue; }
    $t = get_the_title( $pid );
    foreach ( $rows as $r ) {
      $q = isset( $r[ $faq_q_key ] ) ? trim( (string) $r[ $faq_q_key ] ) : '';
      $w = $faq_match( $q );
      if ( $w === false ) { continue; }
      $n_rm++;
      echo '<tr style="background:#fff8e1">';
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . esc_html( mb_strimwidth( $t, 0, 45, '...' ) ) . ' <span style="color:#999">#' . (int) $pid . '</span></td>';
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . esc_html( mb_strimwidth( wp_strip_all_tags( $q ), 0, 80, '...' ) ) . '</td>';
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee;color:#e67e22"><strong>' . esc_html( $w ) . '</strong></td>';
      echo '</tr>';
    }
  }
  echo '</table>';
  echo '<p><strong>' . $n_rm . '</strong> rows &agrave; supprimer sur cette page.</p>';
  echo $pager( 'dryrun' );
  echo '<p><a href="' . esc_url( $faq_base ) . '">&larr; Retour</a></p>';
  echo '</div>';
  return;
}

/* ── RUNALL : traite un lot, puis auto-reload vers le suivant ── */
if ( $faq_action === 'runall' ) {
  $n_rm = 0; $n_kept = 0; $n_touched = 0;

  foreach ( $faq_posts as $pid ) {
    $rows = get_field( $faq_repeater, $pid );
    if ( ! is_array( $rows ) || empty( $rows ) ) { continue; }

    $kept = array();
    $had_remove = false;
    foreach ( $rows as $r ) {
      $q = isset( $r[ $faq_q_key ] ) ? trim( (string) $r[ $faq_q_key ] ) : '';
      if ( $faq_match( $q ) !== false ) {
        $n_rm++;
        $had_remove = true;
      } else {
        $kept[] = $r;
      }
    }
    if ( $had_remove ) {
      update_field( $faq_repeater, array_values( $kept ), $pid );
      $n_touched++;
    }
    $n_kept += count( $kept );
  }

  echo '<div style="max-width:700px;margin:40px auto;font:14px/1.6 system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="margin:0 0 8px;font-size:18px">Lot ' . $faq_page . '/' . $total_pages . ' termin&eacute;</h2>';
  echo '<p>' . $n_touched . ' posts modifi&eacute;s, ' . $n_rm . ' rows supprim&eacute;es, ' . $n_kept . ' conserv&eacute;es.</p>';

  if ( $faq_page < $total_pages ) {
    $next = $faq_base . '&faq_action=runall&faq_page=' . ( $faq_page + 1 );
    echo '<p>Passage au lot suivant dans 3 secondes...</p>';
    echo '<meta http-equiv="refresh" content="3;url=' . esc_url( $next ) . '">';
    echo '<p><a href="' . esc_url( $next ) . '">Cliquer si pas de redirection</a></p>';
  } else {
    echo '<p style="color:#27ae60;font-weight:700">Termin&eacute; ! Tous les ' . $total_posts . ' posts ont &eacute;t&eacute; trait&eacute;s.</p>';
  }

  echo '<p><a href="' . esc_url( $faq_base ) . '">&larr; Retour</a></p>';
  echo '</div>';
  return;
}
