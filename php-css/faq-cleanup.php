<?php
/* =====================================================================
   MEILLEURTEST — Nettoyage des FAQ manuelles (doublons auto Q/R)
   À coller dans WPCodeBox (Execute = ON, front-end).

   Trois modes, pilotés par ?faq_action= :
     export  → CSV complet de toutes les FAQ (sauvegarde avant modif)
     dryrun  → liste les rows qui SERAIENT supprimées (aucune écriture)
     run     → supprime réellement les rows doublons

   Accès : admin uniquement. Sans paramètre → page d'accueil avec liens.
   ===================================================================== */
if ( ! current_user_can( 'manage_options' ) ) { return; }
if ( ! function_exists( 'get_field' ) )       { echo '<p>ACF requis.</p>'; return; }

$action = isset( $_GET['faq_action'] ) ? sanitize_text_field( $_GET['faq_action'] ) : '';

/* -----------------------------------------------------------------
   CONFIG
   ----------------------------------------------------------------- */
$FAQ_REPEATER = 'mltv5_faq_comparatif';
$FAQ_Q_KEY    = 'mltv5_faq_comparatif_question';
$FAQ_A_KEY    = 'mltv5_faq_comparatif_reponse';
$FAQ_PT       = 'comparatif';

/* Mots-clés de détection (minuscules, sans accents). Si l'un de ces
   mots apparaît dans la question, la row est considérée comme doublon
   potentiel des Q/R automatiques. */
$DOUBLON_WORDS = array(
  'meilleur', 'meilleure', 'meilleurs', 'meilleures',
  'combien',
  'coûte', 'coute', 'coûtent', 'coutent',
  'budget', 'prix',
  'marque', 'marques',
  'choisir',
  'critère', 'critere', 'critères', 'criteres',
);

/* -----------------------------------------------------------------
   HELPERS
   ----------------------------------------------------------------- */
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

function faq_cl_is_doublon( $question, $words ) {
  $q = mb_strtolower( faq_cl_strip_accents( trim( wp_strip_all_tags( $question ) ) ), 'UTF-8' );
  foreach ( $words as $w ) {
    if ( mb_strpos( $q, $w ) !== false ) { return $w; }
  }
  return false;
}

/* Tous les comparatifs publiés qui ont le repeater FAQ. */
function faq_cl_get_posts( $pt ) {
  return get_posts( array(
    'post_type'      => $pt,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'meta_query'     => array( array(
      'key'     => 'mltv5_faq_comparatif',
      'compare' => 'EXISTS',
    ) ),
  ) );
}

/* -----------------------------------------------------------------
   PAGE D'ACCUEIL (sans paramètre)
   ----------------------------------------------------------------- */
if ( $action === '' ) {
  $base = add_query_arg( array(), remove_query_arg( 'faq_action' ) );
  echo '<div style="max-width:700px;margin:40px auto;font:15px/1.6 Inter,system-ui,sans-serif;color:#14181d">';
  echo '<h2 style="font-size:22px;margin:0 0 20px">Nettoyage des FAQ manuelles</h2>';
  echo '<p>Ce script détecte les questions manuelles qui font doublon avec les Q/R automatiques (meilleur produit, budget, marques, critères…) et les supprime du repeater ACF.</p>';
  echo '<ol style="margin:20px 0">';
  echo '<li><a href="' . esc_url( add_query_arg( 'faq_action', 'export', $base ) ) . '"><strong>1. Exporter (CSV)</strong></a> — sauvegarde complète avant modif</li>';
  echo '<li><a href="' . esc_url( add_query_arg( 'faq_action', 'dryrun', $base ) ) . '"><strong>2. Dry-run</strong></a> — voir ce qui serait supprimé (aucune écriture)</li>';
  echo '<li><a href="' . esc_url( add_query_arg( 'faq_action', 'run', $base ) ) . '" onclick="return confirm(\'Lancer la suppression réelle ?\')"><strong>3. Exécuter</strong></a> — supprimer les doublons</li>';
  echo '</ol>';
  echo '<p style="color:#6b7480;font-size:13px">Mots-clés détectés : <code>' . esc_html( implode( ', ', $DOUBLON_WORDS ) ) . '</code></p>';
  echo '</div>';
  return;
}

/* -----------------------------------------------------------------
   MODE EXPORT → CSV
   ----------------------------------------------------------------- */
if ( $action === 'export' ) {
  $ids = faq_cl_get_posts( $FAQ_PT );
  header( 'Content-Type: text/csv; charset=UTF-8' );
  header( 'Content-Disposition: attachment; filename="faq-export-' . date( 'Y-m-d-His' ) . '.csv"' );
  $out = fopen( 'php://output', 'w' );
  fprintf( $out, "\xEF\xBB\xBF" ); // BOM UTF-8 pour Excel
  fputcsv( $out, array( 'post_id', 'post_title', 'row_index', 'question', 'reponse' ) );

  $total_rows = 0;
  foreach ( $ids as $pid ) {
    $rows = get_field( $FAQ_REPEATER, $pid );
    if ( ! is_array( $rows ) ) { continue; }
    $title = get_the_title( $pid );
    foreach ( $rows as $i => $r ) {
      $q = isset( $r[ $FAQ_Q_KEY ] ) ? trim( wp_strip_all_tags( (string) $r[ $FAQ_Q_KEY ] ) ) : '';
      $a = isset( $r[ $FAQ_A_KEY ] ) ? trim( wp_strip_all_tags( (string) $r[ $FAQ_A_KEY ] ) ) : '';
      fputcsv( $out, array( $pid, $title, $i, $q, $a ) );
      $total_rows++;
    }
  }
  fclose( $out );
  exit;
}

/* -----------------------------------------------------------------
   MODE DRYRUN / RUN
   ----------------------------------------------------------------- */
$is_run = ( $action === 'run' );
$ids    = faq_cl_get_posts( $FAQ_PT );

echo '<div style="max-width:900px;margin:40px auto;font:14px/1.6 Inter,system-ui,sans-serif;color:#14181d">';
echo '<h2 style="font-size:20px;margin:0 0 6px">' . ( $is_run ? 'Exécution' : 'Dry-run' ) . ' — Nettoyage FAQ</h2>';
echo '<p style="color:#6b7480;margin:0 0 20px">' . count( $ids ) . ' comparatifs avec FAQ trouvés.</p>';

$stats = array( 'posts_touched' => 0, 'rows_removed' => 0, 'rows_kept' => 0 );

echo '<table style="width:100%;border-collapse:collapse;font-size:13px">';
echo '<tr style="background:#f5f6f8;text-align:left">'
   . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Comparatif</th>'
   . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Question</th>'
   . '<th style="padding:8px;border-bottom:1px solid #e8eaed">Mot détecté</th>'
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
       .   ( $is_run ? 'SUPPRIMÉ' : 'À supprimer' ) . '</td>'
       . '</tr>';
  }

  /* Écriture réelle : update_field avec le tableau filtré (rows gardées). */
  if ( $is_run ) {
    update_field( $FAQ_REPEATER, array_values( $kept ), $pid );
  }
}

echo '</table>';
echo '<div style="margin:20px 0;padding:16px;background:#f5f6f8;border-radius:8px;font-size:14px">';
echo '<strong>Résumé</strong> : ';
echo (int) $stats['posts_touched'] . ' comparatifs touchés, ';
echo (int) $stats['rows_removed'] . ' rows ' . ( $is_run ? 'supprimées' : 'à supprimer' ) . ', ';
echo (int) $stats['rows_kept'] . ' rows conservées.';
echo '</div>';

if ( ! $is_run && $stats['rows_removed'] > 0 ) {
  $run_url = add_query_arg( 'faq_action', 'run', remove_query_arg( 'faq_action' ) );
  echo '<p><a href="' . esc_url( $run_url ) . '" onclick="return confirm(\'Confirmer la suppression de '
     . (int) $stats['rows_removed'] . ' rows ?\')" style="display:inline-block;padding:10px 20px;'
     . 'background:#c0392b;color:#fff;border-radius:6px;text-decoration:none;font-weight:600">'
     . 'Exécuter la suppression</a></p>';
}

echo '</div>';
