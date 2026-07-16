<?php
/* =====================================================================
   MEILLEURTEST — Nettoyage des FAQ manuelles (doublons auto Q/R)
   À coller dans WPCodeBox. N'agit QUE si ?faq_cleanup=1 est présent.
   ===================================================================== */
if ( ! isset( $_GET['faq_cleanup'] ) ) { return; }
if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ) { return; }
if ( ! function_exists( 'get_field' ) ) { echo '<p>ACF requis.</p>'; return; }

$faq_action  = isset( $_GET['faq_action'] ) ? sanitize_text_field( $_GET['faq_action'] ) : '';
$faq_base    = home_url( '/?faq_cleanup=1' );

/* CONFIG */
$faq_repeater = 'mltv5_faq_comparatif';
$faq_q_key   = 'mltv5_faq_comparatif_question';
$faq_a_key   = 'mltv5_faq_comparatif_reponse';

$faq_bad_words = array(
  'meilleur','meilleure','meilleurs','meilleures',
  'combien','coute','coutent','budget','prix',
  'marque','marques','choisir','critere','criteres',
);

/* Strip accents pour la comparaison */
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

/* Récupère tous les posts FAQ */
$faq_posts = get_posts( array(
  'post_type'      => 'faq',
  'post_status'    => array( 'publish', 'draft', 'private' ),
  'posts_per_page' => -1,
  'fields'         => 'ids',
  'no_found_rows'  => true,
) );

/* ── PAGE D'ACCUEIL ─────────────────────────────────────── */
if ( $faq_action === '' ) {
  echo '<div style="max-width:700px;margin:40px auto;font:15px/1.6 system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="margin:0 0 16px">Nettoyage des FAQ manuelles</h2>';
  echo '<p>' . count( $faq_posts ) . ' posts FAQ trouv&eacute;s.</p>';
  echo '<ol style="margin:20px 0;line-height:2">';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=export' ) . '">1. Exporter</a> (tableau HTML, sauvegarde)</li>';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=dryrun' ) . '">2. Dry-run</a> (voir sans modifier)</li>';
  echo '<li><a href="' . esc_url( $faq_base . '&faq_action=run' ) . '" onclick="return confirm(\'Lancer ?\')">3. Ex&eacute;cuter</a> (supprimer les doublons)</li>';
  echo '</ol></div>';
  return;
}

/* ── EXPORT ──────────────────────────────────────────────── */
if ( $faq_action === 'export' ) {
  echo '<div style="max-width:1200px;margin:40px auto;font:13px/1.5 system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="margin:0 0 8px;font-size:18px">Export FAQ — ' . count( $faq_posts ) . ' posts</h2>';

  $total = 0;
  echo '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">';
  echo '<tr style="background:#f5f6f8"><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">ID</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Post</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">#</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Question</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">R&eacute;ponse (extrait)</th></tr>';

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
      echo '<td style="padding:4px 8px;border-bottom:1px solid #eee;color:#666">' . esc_html( mb_strimwidth( $a, 0, 120, '...' ) ) . '</td></tr>';
      $total++;
    }
  }
  echo '</table></div>';
  echo '<p style="margin-top:12px"><strong>' . $total . '</strong> Q/R au total. <a href="' . esc_url( $faq_base ) . '">&larr; Retour</a></p>';
  echo '</div>';
  return;
}

/* ── DRYRUN / RUN ────────────────────────────────────────── */
$is_run = ( $faq_action === 'run' );
echo '<div style="max-width:1000px;margin:40px auto;font:13px/1.5 system-ui,sans-serif;color:#14181d">';
echo '<h2 style="margin:0 0 8px;font-size:18px">' . ( $is_run ? 'EX&Eacute;CUTION' : 'DRY-RUN' ) . '</h2>';

$n_touched = 0; $n_removed = 0; $n_kept = 0;

echo '<table style="width:100%;border-collapse:collapse">';
echo '<tr style="background:#f5f6f8"><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Post FAQ</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Question</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Mot</th><th style="padding:6px 8px;text-align:left;border-bottom:2px solid #ddd">Action</th></tr>';

foreach ( $faq_posts as $pid ) {
  $rows = get_field( $faq_repeater, $pid );
  if ( ! is_array( $rows ) || empty( $rows ) ) { continue; }

  $t = get_the_title( $pid );
  $kept = array();
  $removed = array();

  foreach ( $rows as $r ) {
    $q = isset( $r[ $faq_q_key ] ) ? trim( (string) $r[ $faq_q_key ] ) : '';
    $w = $faq_match( $q );
    if ( $w !== false ) {
      $removed[] = array( $q, $w );
    } else {
      $kept[] = $r;
    }
  }
  if ( empty( $removed ) ) { continue; }

  $n_touched++;
  $n_removed += count( $removed );
  $n_kept    += count( $kept );

  foreach ( $removed as $rm ) {
    $bg = $is_run ? '#fdecea' : '#fff8e1';
    echo '<tr style="background:' . $bg . '">';
    echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . esc_html( mb_strimwidth( $t, 0, 45, '...' ) ) . ' <span style="color:#999">#' . (int) $pid . '</span></td>';
    echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . esc_html( mb_strimwidth( wp_strip_all_tags( $rm[0] ), 0, 80, '...' ) ) . '</td>';
    echo '<td style="padding:4px 8px;border-bottom:1px solid #eee;color:#c0392b"><strong>' . esc_html( $rm[1] ) . '</strong></td>';
    echo '<td style="padding:4px 8px;border-bottom:1px solid #eee">' . ( $is_run ? '<span style="color:#c0392b;font-weight:700">SUPPRIM&Eacute;</span>' : '<span style="color:#e67e22">A supprimer</span>' ) . '</td>';
    echo '</tr>';
  }

  if ( $is_run ) {
    update_field( $faq_repeater, array_values( $kept ), $pid );
  }
}
echo '</table>';

echo '<div style="margin:16px 0;padding:12px;background:#f5f6f8;border-radius:6px">';
echo '<strong>' . $n_touched . '</strong> posts, <strong>' . $n_removed . '</strong> rows ' . ( $is_run ? 'supprim&eacute;es' : '&agrave; supprimer' ) . ', <strong>' . $n_kept . '</strong> conserv&eacute;es.';
echo '</div>';

if ( ! $is_run && $n_removed > 0 ) {
  echo '<p><a href="' . esc_url( $faq_base . '&faq_action=run' ) . '" onclick="return confirm(\'Supprimer ' . $n_removed . ' rows ?\')" style="display:inline-block;padding:8px 18px;background:#c0392b;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">Lancer la suppression</a></p>';
}
echo '<p><a href="' . esc_url( $faq_base ) . '">&larr; Retour</a></p>';
echo '</div>';
