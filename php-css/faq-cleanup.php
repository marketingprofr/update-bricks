<?php
/* =====================================================================
   MEILLEURTEST — Nettoyage des FAQ manuelles (doublons auto Q/R)
   À coller dans WPCodeBox (Execute = ON, front-end).

   DÉCLENCHEUR : ?faq_cleanup=1 (sans ce paramètre → ne fait RIEN).
   Puis ?faq_cleanup=1&faq_action=export|dryrun|run

   Accès : admin uniquement.
   ===================================================================== */
if ( ! isset( $_GET['faq_cleanup'] ) )        { return; }
if ( ! current_user_can( 'manage_options' ) )  { return; }
if ( ! function_exists( 'get_field' ) )        { echo '<p>ACF requis.</p>'; return; }

$action = isset( $_GET['faq_action'] ) ? sanitize_text_field( $_GET['faq_action'] ) : '';

/* -----------------------------------------------------------------
   CONFIG
   ----------------------------------------------------------------- */
$FAQ_REPEATER = 'mltv5_faq_comparatif';
$FAQ_Q_KEY    = 'mltv5_faq_comparatif_question';
$FAQ_A_KEY    = 'mltv5_faq_comparatif_reponse';
$FAQ_PT       = 'faq';

$DOUBLON_WORDS = array(
  'meilleur', 'meilleure', 'meilleurs', 'meilleures',
  'combien',
  'coute', 'coutent',
  'budget', 'prix',
  'marque', 'marques',
  'choisir',
  'critere', 'criteres',
);

/* -----------------------------------------------------------------
   HELPERS
   ----------------------------------------------------------------- */
if ( ! function_exists( 'faq_cl_strip_accents' ) ) {
  function faq_cl_strip_accents( $s ) {
    $map = array(
      'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
      'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
      'ç'=>'c','œ'=>'oe','æ'=>'ae',
      'À'=>'A','Â'=>'A','Ä'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
      'Î'=>'I','Ï'=>'I','Ô'=>'O','Ö'=>'O','Ù'=>'U','Û'=>'U','Ü'=>'U',
      'Ç'=>'C','Œ'=>'OE','Æ'=>'AE',
    );
    return strtr( $s, $map );
  }
}

if ( ! function_exists( 'faq_cl_is_doublon' ) ) {
  function faq_cl_is_doublon( $question, $words ) {
    $q = mb_strtolower( faq_cl_strip_accents( trim( wp_strip_all_tags( $question ) ) ), 'UTF-8' );
    foreach ( $words as $w ) {
      if ( mb_strpos( $q, $w ) !== false ) { return $w; }
    }
    return false;
  }
}

if ( ! function_exists( 'faq_cl_get_posts' ) ) {
  function faq_cl_get_posts( $pt ) {
    return get_posts( array(
      'post_type'      => $pt,
      'post_status'    => array( 'publish', 'draft', 'private' ),
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'no_found_rows'  => true,
    ) );
  }
}

/* Construit l'URL de base avec faq_cleanup=1 */
$base_url = add_query_arg( 'faq_cleanup', '1', home_url( '/' ) );

/* -----------------------------------------------------------------
   PAGE D'ACCUEIL (faq_cleanup=1 sans faq_action)
   ----------------------------------------------------------------- */
if ( $action === '' ) {
  echo '<div style="max-width:700px;margin:40px auto;font:15px/1.6 Inter,system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="font-size:22px;margin:0 0 20px">Nettoyage des FAQ manuelles</h2>';
  echo '<p>Ce script d&eacute;tecte les questions manuelles qui font doublon avec les Q/R automatiques (meilleur produit, budget, marques, crit&egrave;res&hellip;) et les supprime du repeater ACF.</p>';
  echo '<ol style="margin:20px 0">';
  echo '<li><a href="' . esc_url( add_query_arg( 'faq_action', 'export', $base_url ) ) . '"><strong>1. Exporter</strong></a> &mdash; sauvegarde compl&egrave;te (tableau HTML)</li>';
  echo '<li><a href="' . esc_url( add_query_arg( 'faq_action', 'dryrun', $base_url ) ) . '"><strong>2. Dry-run</strong></a> &mdash; voir ce qui serait supprim&eacute; (aucune &eacute;criture)</li>';
  echo '<li><a href="' . esc_url( add_query_arg( 'faq_action', 'run', $base_url ) ) . '" onclick="return confirm(\'Lancer la suppression r&eacute;elle ?\')"><strong>3. Ex&eacute;cuter</strong></a> &mdash; supprimer les doublons</li>';
  echo '</ol>';
  echo '<p style="color:#6b7480;font-size:13px">Mots-cl&eacute;s d&eacute;tect&eacute;s (apr&egrave;s suppression des accents) : <code>' . esc_html( implode( ', ', $DOUBLON_WORDS ) ) . '</code></p>';
  echo '</div>';
  return;
}

/* -----------------------------------------------------------------
   MODE EXPORT → tableau HTML + bouton téléchargement CSV (JS)
   (header() impossible : WP a déjà envoyé du HTML)
   ----------------------------------------------------------------- */
if ( $action === 'export' ) {
  $ids = faq_cl_get_posts( $FAQ_PT );

  /* Collecter toutes les données. */
  $csv_lines = array();
  foreach ( $ids as $pid ) {
    $rows = get_field( $FAQ_REPEATER, $pid );
    if ( ! is_array( $rows ) || empty( $rows ) ) { continue; }
    $title = get_the_title( $pid );
    foreach ( $rows as $i => $r ) {
      $q = isset( $r[ $FAQ_Q_KEY ] ) ? trim( wp_strip_all_tags( (string) $r[ $FAQ_Q_KEY ] ) ) : '';
      $a = isset( $r[ $FAQ_A_KEY ] ) ? trim( wp_strip_all_tags( (string) $r[ $FAQ_A_KEY ] ) ) : '';
      $csv_lines[] = array( $pid, $title, $i, $q, $a );
    }
  }

  echo '<div style="max-width:1100px;margin:40px auto;font:14px/1.6 Inter,system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="font-size:20px;margin:0 0 6px">Export des FAQ</h2>';
  echo '<p style="color:#6b7480;margin:0 0 12px">' . count( $ids ) . ' posts FAQ trouv&eacute;s, ' . count( $csv_lines ) . ' questions/r&eacute;ponses au total.</p>';

  if ( empty( $csv_lines ) ) {
    echo '<p style="color:#c0392b"><strong>Aucune donn&eacute;e trouv&eacute;e.</strong> V&eacute;rifiez que le post type <code>faq</code> et le repeater <code>' . esc_html( $FAQ_REPEATER ) . '</code> existent bien.</p>';
    echo '</div>';
    return;
  }

  /* Bouton CSV téléchargeable via JS (data: URI). */
  echo '<button id="faq-dl-csv" style="margin-bottom:16px;padding:8px 16px;background:#14181d;color:#fff;border:0;border-radius:6px;cursor:pointer;font-weight:600">T&eacute;l&eacute;charger le CSV</button>';

  /* Tableau HTML. */
  echo '<div style="overflow-x:auto">';
  echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
  echo '<tr style="background:#f5f6f8;text-align:left">'
     . '<th style="padding:8px;border-bottom:1px solid #e8eaed;white-space:nowrap">ID</th>'
     . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Titre du post</th>'
     . '<th style="padding:8px;border-bottom:1px solid #e8eaed;white-space:nowrap">Row</th>'
     . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Question</th>'
     . '<th style="padding:8px;border-bottom:1px solid #e8eaed">R&eacute;ponse (extrait)</th>'
     . '</tr>';

  foreach ( $csv_lines as $row ) {
    echo '<tr>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5;white-space:nowrap">' . (int) $row[0] . '</td>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5">' . esc_html( mb_strimwidth( $row[1], 0, 45, '…' ) ) . '</td>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5;text-align:center">' . (int) $row[2] . '</td>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5">' . esc_html( mb_strimwidth( $row[3], 0, 70, '…' ) ) . '</td>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5;color:#6b7480">' . esc_html( mb_strimwidth( $row[4], 0, 90, '…' ) ) . '</td>'
       . '</tr>';
  }
  echo '</table></div>';

  /* JS : générer le CSV et déclencher le téléchargement. */
  $csv_data = array();
  $csv_data[] = array( 'post_id', 'post_title', 'row_index', 'question', 'reponse' );
  foreach ( $csv_lines as $row ) { $csv_data[] = $row; }
  echo '<script>';
  echo 'document.getElementById("faq-dl-csv").addEventListener("click",function(){';
  echo 'var d=' . wp_json_encode( $csv_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . ';';
  echo 'var c="\\uFEFF";'; // BOM UTF-8
  echo 'd.forEach(function(r){c+=r.map(function(v){return"\""+String(v).replace(/"/g,\'""\')+"\""}).join(",")+\"\\n\"});';
  echo 'var b=new Blob([c],{type:"text/csv;charset=utf-8"});';
  echo 'var a=document.createElement("a");a.href=URL.createObjectURL(b);';
  echo 'a.download="faq-export-' . date( 'Y-m-d-His' ) . '.csv";a.click()});';
  echo '</script>';

  echo '<p style="margin-top:16px"><a href="' . esc_url( $base_url ) . '">&larr; Retour</a></p>';
  echo '</div>';
  return;
}

/* -----------------------------------------------------------------
   MODE DRYRUN / RUN
   ----------------------------------------------------------------- */
$is_run = ( $action === 'run' );
$ids    = faq_cl_get_posts( $FAQ_PT );

echo '<div style="max-width:1000px;margin:40px auto;font:14px/1.6 Inter,system-ui,sans-serif;color:#14181d">';
echo '<h2 style="font-size:20px;margin:0 0 6px">' . ( $is_run ? 'Ex&eacute;cution' : 'Dry-run' ) . ' &mdash; Nettoyage FAQ</h2>';
echo '<p style="color:#6b7480;margin:0 0 20px">' . count( $ids ) . ' posts FAQ trouv&eacute;s.</p>';

$stats = array( 'posts_touched' => 0, 'rows_removed' => 0, 'rows_kept' => 0 );

echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
echo '<tr style="background:#f5f6f8;text-align:left">'
   . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Post FAQ</th>'
   . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Question</th>'
   . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Mot d&eacute;tect&eacute;</th>'
   . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Action</th>'
   . '</tr>';

foreach ( $ids as $pid ) {
  $rows = get_field( $FAQ_REPEATER, $pid );
  if ( ! is_array( $rows ) || empty( $rows ) ) { continue; }

  $title   = get_the_title( $pid );
  $kept    = array();
  $removed = array();

  foreach ( $rows as $i => $r ) {
    $q    = isset( $r[ $FAQ_Q_KEY ] ) ? trim( (string) $r[ $FAQ_Q_KEY ] ) : '';
    $match = faq_cl_is_doublon( $q, $DOUBLON_WORDS );
    if ( $match !== false ) {
      $removed[] = array( 'q' => $q, 'word' => $match, 'idx' => $i );
    } else {
      $kept[] = $r;
    }
  }

  if ( empty( $removed ) ) { continue; }

  $stats['posts_touched']++;
  $stats['rows_removed'] += count( $removed );
  $stats['rows_kept']    += count( $kept );

  foreach ( $removed as $rm ) {
    echo '<tr style="background:#fdecea">'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5">'
       .   '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '" target="_blank">'
       .   esc_html( mb_strimwidth( $title, 0, 50, '…' ) ) . '</a>'
       .   ' <span style="color:#9aa3ad">#' . (int) $pid . '</span></td>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5">'
       .   esc_html( mb_strimwidth( wp_strip_all_tags( $rm['q'] ), 0, 80, '…' ) ) . '</td>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5;color:#c0392b">'
       .   esc_html( $rm['word'] ) . '</td>'
       . '<td style="padding:6px 8px;border-bottom:1px solid #f1f3f5;font-weight:600;color:'
       .   ( $is_run ? '#c0392b' : '#6b7480' ) . '">'
       .   ( $is_run ? 'SUPPRIM&Eacute;' : '&Agrave; supprimer' ) . '</td>'
       . '</tr>';
  }

  if ( $is_run ) {
    update_field( $FAQ_REPEATER, array_values( $kept ), $pid );
  }
}

echo '</table>';

echo '<div style="margin:20px 0;padding:16px;background:#f5f6f8;border-radius:8px;font-size:14px">';
echo '<strong>R&eacute;sum&eacute;</strong> : ';
echo (int) $stats['posts_touched'] . ' posts touch&eacute;s, ';
echo (int) $stats['rows_removed'] . ' rows ' . ( $is_run ? 'supprim&eacute;es' : '&agrave; supprimer' ) . ', ';
echo (int) $stats['rows_kept'] . ' rows conserv&eacute;es.';
echo '</div>';

if ( ! $is_run && $stats['rows_removed'] > 0 ) {
  $run_url = add_query_arg( 'faq_action', 'run', $base_url );
  echo '<p><a href="' . esc_url( $run_url ) . '" onclick="return confirm(\'Confirmer la suppression de '
     . (int) $stats['rows_removed'] . ' rows ?\')" style="display:inline-block;padding:10px 20px;'
     . 'background:#c0392b;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">'
     . 'Ex&eacute;cuter la suppression</a></p>';
}

echo '<p style="margin-top:16px"><a href="' . esc_url( $base_url ) . '">&larr; Retour</a></p>';
echo '</div>';
