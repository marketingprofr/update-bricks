<?php
/* Encadré « Pourquoi nous faire confiance » à valeurs réelles (hero-encart, réglage $MT_ENCADRE_REEL activé
   dans une copie). Usage : NAVIS=16 php run-encadre.php ; SRC=31 ETUD=390 pour le champ « La recherche » (10 / 597 = valeurs par défaut) ;
   AVIS=60000 pour dépasser le seuil des avis clients. */
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'strip_shortcodes' ) ) { function strip_shortcodes( $s ) { return $s; } }
if ( ! function_exists( 'get_the_modified_time' ) ) { function get_the_modified_time( $f = '' ) { return time(); } }
if ( ! function_exists( 'wp_parse_url' ) ) { function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $x ) { return false; } }
if ( ! function_exists( 'get_ancestors' ) ) { function get_ancestors( $id, $tax ) { return $id === 31 ? array( 30 ) : array(); } }
if ( ! function_exists( 'get_term' ) ) { function get_term( $id, $tax = '' ) { $t = new stdClass; $t->term_id = $id; $t->slug = $id === 30 ? 'services' : ( $id === 31 ? 'banques' : 'maison' ); return $t; } }
if ( ! function_exists( 'get_the_category' ) ) { function get_the_category( $id ) { $t = new stdClass; $t->term_id = getenv( 'SERVICE' ) ? 31 : 40; return array( $t ); } }
if ( ! function_exists( 'get_fields' ) ) { function get_fields( $id ) { global $ACF; return $ACF[ $id ] ?? array(); } }
if ( ! function_exists( 'remove_accents' ) ) { function remove_accents( $t ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $t ); } }
$GLOBALS['TV'][100]['masculinsfeminins'] = 'Meilleurs';
$GLOBALS['ACF'][100]['mltv5_criteres_de_choix'] = array_map( function ( $t ) { return array( 'mltv5_critere_de_choix' => $t ); }, array( 'La puissance frigorifique (exprimée en BTU)', 'Le niveau sonore (exprimé en dB)', 'La consommation énergétique', 'Les options de confort', 'Les différents filtres' ) );
foreach ( range( 1, 14 ) as $i ) { if ( ! isset( $GLOBALS['P'][ $i ] ) ) { continue; }
  $GLOBALS['META'][ $i ]['mltv5_nombre_avis_clients'] = (string) ( getenv( 'AVIS' ) ? intdiv( (int) getenv( 'AVIS' ), 14 ) : 100 );
  $GLOBALS['ACF'][ $i ]['mltv5_lien_du_produit_1'] = 'https://www.marchand' . ( $i % 4 ) . '.fr/p' . $i;
  $GLOBALS['ACF'][ $i ]['mltv5_lien_du_produit_2'] = 'https://www.awin1.com/cread.php?p=' . $i;
  $GLOBALS['P'][ $i ]->post_content = '<p>' . str_repeat( 'mot ', 1700 ) . '<a href="https://source-etude.org/x">étude</a> <img src="https://m.media-amazon.com/i.jpg"></p>';
}
$GLOBALS['ACF'][100]['mltv5_faq_comparatif'] = array( array( 'mltv5_faq_comparatif_question' => 'Q ?', 'mltv5_faq_comparatif_reponse' => 'R.' ), array( 'mltv5_faq_comparatif_question' => 'Q2 ?', 'mltv5_faq_comparatif_reponse' => '' ) );
if ( getenv( 'CRIT' ) ) { $GLOBALS['ACF'][100]['mltv5_criteres_courts'] = getenv( 'CRIT' ); }
if ( getenv( 'PHRASE' ) ) { $GLOBALS['ACF'][100]['mltv5_phrase_sources'] = getenv( 'PHRASE' ); }
$src = file_get_contents( MT_REPO . '/php-css/hero-encart.code.php' );
$src = str_replace( '$MT_ENCADRE_REEL = false;', '$MT_ENCADRE_REEL = true;', $src );
if ( getenv( 'SIGNALEMENT' ) ) {  // ligne « Une erreur… ? Prévenez-nous » (réglage activé dans la copie)
  $src = preg_replace( '/\$MT_SIGNALEMENT\s*=\s*false;/', '$MT_SIGNALEMENT = true;', $src, 1 );
  /* comme WordPress : add_query_arg() n'encode pas les valeurs (build_query sans urlencode) */
  if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $a, $u ) { $q = array(); foreach ( $a as $k => $v ) { $q[] = $k . '=' . $v; } return $u . ( strpos( $u, '?' ) === false ? '?' : '&' ) . implode( '&', $q ); } }
  if ( ! function_exists( 'home_url' ) ) { function home_url( $p = '' ) { return 'https://meilleurtest.fr' . $p; } }
}
if ( getenv( 'SRC' ) || getenv( 'ETUD' ) ) {
  $GLOBALS['ACF'][100]['mltv5_la_recherche_comparatif'] = array( 'mltv5_sources_consultees' => (int) getenv( 'SRC' ), 'mltv5_avis_etudies' => (int) getenv( 'ETUD' ) );
}
file_put_contents( __DIR__ . '/out/encart-reel.php', $src );
ob_start(); ( function() { include __DIR__ . '/out/encart-reel.php'; } )(); $h = ob_get_clean();
preg_match_all( '#<div class="mt-sc-num">([^<]*)</div><div class="mt-sc-lbl">([^<]*)</div>#', $h, $m, PREG_SET_ORDER );
foreach ( $m as $x ) { echo '  ', html_entity_decode( $x[1] ), ' ', $x[2], "\n"; }
if ( preg_match( '#<ul class="mt-sc-liste">(.*?)</ul>#s', $h, $u ) ) {
  foreach ( explode( '</li>', $u[1] ) as $li ) {
    $t = trim( html_entity_decode( strip_tags( $li ), ENT_QUOTES, 'UTF-8' ) );
    if ( $t !== '' ) { echo '  • ', $t, "\n"; }
  }
}
echo '  titre de l\'encadré en <p> : ', ( strpos( $h, '<p class="mt-card-h">' ) !== false ? 'oui' : 'NON' ), "\n";
if ( preg_match( '#href="([^"]+)" class="mt-signaler"#', $h, $mt_sig ) ) { echo '  lien de signalement : ', html_entity_decode( $mt_sig[1] ), "\n"; }
