<?php
/* Encadré « Pourquoi nous faire confiance » à valeurs réelles (hero-encart, réglage $MT_ENCADRE_REEL activé
   dans une copie). Usage : NAVIS=16 php run-encadre.php ; CASE1="~20" pour la case des sources ;
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
$GLOBALS['TV'][100]['masculinsfeminins'] = 'Meilleurs';
foreach ( range( 1, 14 ) as $i ) { if ( ! isset( $GLOBALS['P'][ $i ] ) ) { continue; }
  $GLOBALS['META'][ $i ]['mltv5_nombre_avis_clients'] = (string) ( getenv( 'AVIS' ) ? intdiv( (int) getenv( 'AVIS' ), 14 ) : 100 );
  $GLOBALS['ACF'][ $i ]['mltv5_lien_du_produit_1'] = 'https://www.marchand' . ( $i % 4 ) . '.fr/p' . $i;
  $GLOBALS['ACF'][ $i ]['mltv5_lien_du_produit_2'] = 'https://www.awin1.com/cread.php?p=' . $i;
  $GLOBALS['P'][ $i ]->post_content = '<p>' . str_repeat( 'mot ', 1700 ) . '<a href="https://source-etude.org/x">étude</a> <img src="https://m.media-amazon.com/i.jpg"></p>';
}
$GLOBALS['ACF'][100]['mltv5_faq_comparatif'] = array( array( 'mltv5_faq_comparatif_question' => 'Q ?', 'mltv5_faq_comparatif_reponse' => 'R.' ), array( 'mltv5_faq_comparatif_question' => 'Q2 ?', 'mltv5_faq_comparatif_reponse' => '' ) );
$src = file_get_contents( MT_REPO . '/php-css/hero-encart.code.php' );
$src = str_replace( '$MT_ENCADRE_REEL = false;', '$MT_ENCADRE_REEL = true;', $src );
if ( getenv( 'CASE1' ) ) { $src = str_replace( '$MT_CASE1_NUM   = \'\';', '$MT_CASE1_NUM   = \'' . getenv( 'CASE1' ) . '\';', $src ); }
file_put_contents( __DIR__ . '/out/encart-reel.php', $src );
ob_start(); ( function() { include __DIR__ . '/out/encart-reel.php'; } )(); $h = ob_get_clean();
preg_match_all( '#<div class="mt-sc-num">([^<]*)</div><div class="mt-sc-lbl">([^<]*)</div>#', $h, $m, PREG_SET_ORDER );
foreach ( $m as $x ) { echo '  ', html_entity_decode( $x[1] ), ' ', $x[2], "\n"; }
echo '  titre de l\'encadré en <p> : ', ( strpos( $h, '<p class="mt-card-h">' ) !== false ? 'oui' : 'NON' ), "\n";
