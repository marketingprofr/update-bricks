<?php
/**
 * MEILLEURTEST — Nettoyage des FAQ manuelles (doublons des Q/R automatiques)
 *
 * Se branche sur `init` et PREND LE CONTRÔLE de la requête (header + exit),
 * comme les scripts catcleanup — PAS d'echo dans le contenu de la page.
 * → header() fonctionne (vrai téléchargement CSV), sortie propre, pas de
 *   pollution des pages normales, fatals non avalés par le thème.
 *
 * Détecte les questions manuelles faisant doublon avec les Q/R auto
 * (meilleur, budget, marque, choisir, critère…) et supprime la row du
 * repeater ACF `mltv5_faq_comparatif` (post type `faq`).
 *
 * URLs (connecté en admin) :
 *   ?faqclean=menu     → menu
 *   ?faqclean=export   → télécharge le CSV complet (sauvegarde, streaming)
 *   ?faqclean=dryrun   → liste ce qui serait supprimé (paginé &p=N)
 *   ?faqclean=run      → supprime réellement (paginé + auto-reload)
 *
 * Après usage : désactiver le snippet.
 */

$faqclean_run = function () {

  if ( ! isset( $_GET['faqclean'] ) ) { return; }
  if ( ! current_user_can( 'manage_options' ) ) { return; }

  global $wpdb;
  if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 600 ); }
  nocache_headers();

  $action  = sanitize_key( $_GET['faqclean'] );
  $page    = isset( $_GET['p'] ) ? max( 1, (int) $_GET['p'] ) : 1;
  $base    = home_url( '/?faqclean=' );
  $PER     = 50;

  /* CONFIG champs ACF */
  $REPEATER = 'mltv5_faq_comparatif';
  $Q_KEY    = 'mltv5_faq_comparatif_question';
  $A_KEY    = 'mltv5_faq_comparatif_reponse';

  $BAD_WORDS = array(
    'meilleur','meilleure','meilleurs','meilleures',
    'combien','coute','coutent','budget','prix',
    'marque','marques','choisir','critere','criteres',
  );

  /* ── Helpers ──────────────────────────────────────────── */
  $strip = function ( $s ) {
    return strtr( strtolower( trim( wp_strip_all_tags( (string) $s ) ) ), array(
      'à'=>'a','â'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
      'î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ü'=>'u',
      'ç'=>'c','œ'=>'oe','æ'=>'ae',
    ) );
  };
  $match = function ( $q ) use ( $strip, $BAD_WORDS ) {
    $n = $strip( $q );
    foreach ( $BAD_WORDS as $w ) { if ( strpos( $n, $w ) !== false ) { return $w; } }
    return false;
  };
  $trunc = function ( $s, $len ) {
    $s = (string) $s;
    if ( function_exists( 'mb_strlen' ) && mb_strlen( $s, 'UTF-8' ) > $len ) {
      return mb_substr( $s, 0, $len, 'UTF-8' ) . '…';
    }
    return strlen( $s ) > $len ? substr( $s, 0, $len ) . '…' : $s;
  };

  /* Reconstruit les rows d'un lot de posts depuis wp_postmeta BRUT
     (1 requête par lot, aucune exécution de shortcode/formatage ACF).
     Retourne [ pid => [ ['i'=>, 'q'=>, 'a'=>], ... ] ]. */
  $rows_for = function ( array $ids ) use ( $wpdb, $REPEATER, $Q_KEY, $A_KEY ) {
    $out = array();
    if ( empty( $ids ) ) { return $out; }
    $ids = array_map( 'intval', $ids );
    $in  = implode( ',', $ids );
    $like = $wpdb->esc_like( $REPEATER . '_' ) . '%';
    $meta = $wpdb->get_results( $wpdb->prepare(
      "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
       WHERE post_id IN ($in) AND meta_key LIKE %s", $like
    ) );
    $q_suf = '_' . $Q_KEY;
    $a_suf = '_' . $A_KEY;
    $tmp = array();  // pid => [ i => ['q'=>,'a'=>] ]
    foreach ( (array) $meta as $m ) {
      $k = $m->meta_key;
      if ( strpos( $k, $REPEATER . '_' ) !== 0 ) { continue; }
      $rest = substr( $k, strlen( $REPEATER . '_' ) );      // "{i}_{subfield}"
      if ( ! preg_match( '/^(\d+)_(.+)$/', $rest, $mm ) ) { continue; }
      $i   = (int) $mm[1];
      $sub = $mm[2];
      if ( $sub === $Q_KEY ) { $tmp[ (int) $m->post_id ][ $i ]['q'] = (string) $m->meta_value; }
      elseif ( $sub === $A_KEY ) { $tmp[ (int) $m->post_id ][ $i ]['a'] = (string) $m->meta_value; }
    }
    foreach ( $ids as $pid ) {
      if ( empty( $tmp[ $pid ] ) ) { continue; }
      ksort( $tmp[ $pid ] );
      $list = array();
      foreach ( $tmp[ $pid ] as $i => $qa ) {
        $list[] = array(
          'i' => $i,
          'q' => isset( $qa['q'] ) ? $qa['q'] : '',
          'a' => isset( $qa['a'] ) ? $qa['a'] : '',
        );
      }
      $out[ $pid ] = $list;
    }
    return $out;
  };

  /* Titres d'un lot (1 requête). */
  $titles_for = function ( array $ids ) use ( $wpdb ) {
    $out = array();
    if ( empty( $ids ) ) { return $out; }
    $in = implode( ',', array_map( 'intval', $ids ) );
    $r  = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ($in)" );
    foreach ( (array) $r as $row ) { $out[ (int) $row->ID ] = (string) $row->post_title; }
    return $out;
  };

  /* Tous les IDs de posts `faq` (ordonnés). */
  $all_ids = array_map( 'intval', (array) $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts}
     WHERE post_type='faq' AND post_status IN ('publish','draft','private')
     ORDER BY ID ASC"
  ) );
  $total   = count( $all_ids );
  $pages   = max( 1, (int) ceil( $total / $PER ) );

  /* ════════════════════════════════════════════════════════
     EXPORT CSV — streaming direct, header() (une seule requête)
     ════════════════════════════════════════════════════════ */
  if ( $action === 'export' ) {
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="faq-export-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" );                          // BOM UTF-8 (Excel)
    fputcsv( $out, array( 'post_id', 'post_title', 'row_index', 'question', 'reponse' ) );

    foreach ( array_chunk( $all_ids, 300 ) as $chunk ) {
      $rows   = $rows_for( $chunk );
      $titles = $titles_for( $chunk );
      foreach ( $chunk as $pid ) {
        if ( empty( $rows[ $pid ] ) ) { continue; }
        $t = isset( $titles[ $pid ] ) ? $titles[ $pid ] : '';
        foreach ( $rows[ $pid ] as $r ) {
          fputcsv( $out, array( $pid, $t, $r['i'], $r['q'], $r['a'] ) ); // valeurs BRUTES (restaurables)
        }
      }
      flush();
    }
    fclose( $out );
    exit;
  }

  /* En-tête HTML commun (dryrun / run / menu). */
  header( 'Content-Type: text/html; charset=utf-8' );
  $pager = function ( $act ) use ( $base, $page, $pages, $total ) {
    $s = '<p style="font-size:13px;color:#666">' . $total . ' posts &mdash; page ' . $page . '/' . $pages;
    if ( $page > 1 )     { $s .= ' | <a href="' . esc_url( $base . $act . '&p=' . ( $page - 1 ) ) . '">Prec.</a>'; }
    if ( $page < $pages ) { $s .= ' | <a href="' . esc_url( $base . $act . '&p=' . ( $page + 1 ) ) . '">Suiv.</a>'; }
    return $s . '</p>';
  };
  $shell = function ( $body ) {
    echo '<!doctype html><meta charset="utf-8"><title>FAQ cleanup</title>'
       . '<div style="max-width:1100px;margin:30px auto;font:14px/1.5 system-ui,-apple-system,sans-serif;color:#14181d;padding:0 16px">'
       . $body . '</div>';
  };

  /* Lot de la page courante. */
  $slice   = array_slice( $all_ids, ( $page - 1 ) * $PER, $PER );
  $rows    = ( $action === 'dryrun' || $action === 'run' ) ? $rows_for( $slice ) : array();
  $titles  = ( $action === 'dryrun' ) ? $titles_for( $slice ) : array();

  /* ════════════════════════════════════════════════════════
     DRY-RUN
     ════════════════════════════════════════════════════════ */
  if ( $action === 'dryrun' ) {
    ob_start();
    echo '<h2 style="font-size:19px">Dry-run &mdash; aucune modification</h2>';
    echo $pager( 'dryrun' );
    echo '<table style="width:100%;border-collapse:collapse">';
    echo '<tr style="background:#eee;text-align:left"><th style="padding:5px 7px">Post</th><th style="padding:5px 7px">Question</th><th style="padding:5px 7px">Mot</th></tr>';
    $n = 0;
    foreach ( $slice as $pid ) {
      if ( empty( $rows[ $pid ] ) ) { continue; }
      $t = isset( $titles[ $pid ] ) ? $titles[ $pid ] : '';
      foreach ( $rows[ $pid ] as $r ) {
        $w = $match( $r['q'] );
        if ( $w === false ) { continue; }
        $n++;
        echo '<tr style="background:#fff8e1">';
        echo '<td style="padding:4px 7px;border-bottom:1px solid #ddd">' . esc_html( $trunc( $t, 38 ) ) . ' <span style="color:#999">#' . $pid . '</span></td>';
        echo '<td style="padding:4px 7px;border-bottom:1px solid #ddd">' . esc_html( $trunc( wp_strip_all_tags( $r['q'] ), 80 ) ) . '</td>';
        echo '<td style="padding:4px 7px;border-bottom:1px solid #ddd;color:#e67e22"><b>' . esc_html( $w ) . '</b></td>';
        echo '</tr>';
      }
    }
    echo '</table>';
    echo '<p><b>' . $n . '</b> row(s) &agrave; supprimer sur cette page.</p>';
    echo $pager( 'dryrun' );
    echo '<p><a href="' . esc_url( $base . 'menu' ) . '">&larr; Menu</a></p>';
    $shell( ob_get_clean() );
    exit;
  }

  /* ════════════════════════════════════════════════════════
     RUN — supprime, puis auto-reload vers le lot suivant
     ════════════════════════════════════════════════════════ */
  if ( $action === 'run' ) {
    $rm = 0; $kept_total = 0; $touched = 0; $errs = array();

    foreach ( $slice as $pid ) {
      if ( empty( $rows[ $pid ] ) ) { continue; }
      try {
        $kept = array();
        $had  = false;
        foreach ( $rows[ $pid ] as $r ) {
          if ( $match( $r['q'] ) !== false ) {
            $rm++; $had = true;
          } else {
            $kept[] = array( $Q_KEY => $r['q'], $A_KEY => $r['a'] );
          }
        }
        if ( $had ) {
          if ( function_exists( 'update_field' ) ) {
            update_field( $REPEATER, array_values( $kept ), $pid );
          } else {
            /* Repli sans ACF : réécrit le meta brut du repeater. */
            $old = (int) get_post_meta( $pid, $REPEATER, true );
            for ( $i = 0; $i < $old; $i++ ) {
              delete_post_meta( $pid, $REPEATER . '_' . $i . '_' . $Q_KEY );
              delete_post_meta( $pid, $REPEATER . '_' . $i . '_' . $A_KEY );
              delete_post_meta( $pid, '_' . $REPEATER . '_' . $i . '_' . $Q_KEY );
              delete_post_meta( $pid, '_' . $REPEATER . '_' . $i . '_' . $A_KEY );
            }
            $j = 0;
            foreach ( $kept as $row ) {
              update_post_meta( $pid, $REPEATER . '_' . $j . '_' . $Q_KEY, $row[ $Q_KEY ] );
              update_post_meta( $pid, $REPEATER . '_' . $j . '_' . $A_KEY, $row[ $A_KEY ] );
              $j++;
            }
            update_post_meta( $pid, $REPEATER, $j );
          }
          $touched++;
        }
        $kept_total += count( $kept );
      } catch ( \Throwable $e ) {
        $errs[] = $pid;
      }
    }

    ob_start();
    echo '<h2 style="font-size:19px">Nettoyage &mdash; lot ' . $page . '/' . $pages . '</h2>';
    echo '<p>' . $touched . ' posts modifi&eacute;s, ' . $rm . ' rows supprim&eacute;es, ' . $kept_total . ' conserv&eacute;es sur ce lot.</p>';
    if ( ! empty( $errs ) ) {
      echo '<p style="color:#c0392b">' . count( $errs ) . ' post(s) ignor&eacute;(s) : #' . esc_html( implode( ', #', $errs ) ) . '</p>';
    }
    if ( $page < $pages ) {
      $next = $base . 'run&p=' . ( $page + 1 );
      echo '<p>Lot suivant dans 2s&hellip; <a href="' . esc_url( $next ) . '">(continuer)</a></p>';
      echo '<meta http-equiv="refresh" content="2;url=' . esc_url( $next ) . '">';
    } else {
      echo '<p style="color:green;font-weight:700">Termin&eacute; ! ' . $total . ' posts trait&eacute;s.</p>';
    }
    echo '<p><a href="' . esc_url( $base . 'menu' ) . '">&larr; Menu</a></p>';
    $shell( ob_get_clean() );
    exit;
  }

  /* ════════════════════════════════════════════════════════
     MENU (défaut)
     ════════════════════════════════════════════════════════ */
  ob_start();
  echo '<h2 style="font-size:20px">Nettoyage des FAQ manuelles</h2>';
  echo '<p><strong>' . $total . '</strong> posts FAQ. Lots de ' . $PER . '.</p>';
  echo '<ol style="line-height:2.2">';
  echo '<li><a href="' . esc_url( $base . 'export' ) . '"><b>T&eacute;l&eacute;charger le CSV complet</b></a> (sauvegarde &mdash; t&eacute;l&eacute;chargement direct)</li>';
  echo '<li><a href="' . esc_url( $base . 'dryrun' ) . '">Dry-run</a> (voir ce qui serait supprim&eacute;, sans rien modifier)</li>';
  echo '<li><a href="' . esc_url( $base . 'run' ) . '" onclick="return confirm(\'Lancer le nettoyage sur les ' . $total . ' posts ?\')">Tout nettoyer</a> (auto-reload par lots)</li>';
  echo '</ol>';
  echo '<p style="font-size:12px;color:#888">Mots-cl&eacute;s d&eacute;tect&eacute;s : <code>' . esc_html( implode( ', ', $BAD_WORDS ) ) . '</code></p>';
  $shell( ob_get_clean() );
  exit;
};

if ( did_action( 'init' ) ) {
  $faqclean_run();
} else {
  add_action( 'init', $faqclean_run, 0 );
}
