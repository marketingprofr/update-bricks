<?php
/* Colonnes gauche et droite de la partie guide d'achat (modèle Game8). Page multi-comparatif du faux site :
   hero gauche (aides), résumé du top 5, avis détaillés, puis le conteneur du guide comme dans Bricks
   (.brxe-container > .brxe-code colonne gauche + .brxe-block guide et FAQ + .brxe-code colonne droite).
   Usage : php run-colonnes.php ; CLICS=1 pose des clics Google sur 3 comparatifs ; SAISON=1 remplit « De saison » ;
   SANSFAQ=1 sans questions de la rédaction. Écrit out/colonnes.html (CSS compris) et affiche les blocs. */
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'remove_accents' ) ) { function remove_accents( $t ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $t ); } }
if ( ! function_exists( 'get_transient' ) ) { function get_transient( $k ) { return $GLOBALS['TRANS'][ $k ] ?? false; } }
if ( ! function_exists( 'set_transient' ) ) { function set_transient( $k, $v, $t ) { $GLOBALS['TRANS'][ $k ] = $v; return true; } }
if ( ! function_exists( 'get_the_category' ) ) { function get_the_category( $id ) { $m = new stdClass; $m->term_id = 40; $m->name = 'Maison'; $c = new stdClass; $c->term_id = 41; $c->name = 'Climatisation'; return array( $m, $c ); } }
if ( ! function_exists( 'get_ancestors' ) ) { function get_ancestors( $id, $tax ) { return $id === 41 ? array( 40 ) : array(); } }
if ( ! function_exists( 'get_the_modified_date' ) ) { function get_the_modified_date( $f = '', $id = 0 ) { return 'octobre 2026'; } }
if ( ! function_exists( 'wpautop' ) ) { function wpautop( $s ) { return '<p>' . $s . '</p>'; } }
if ( ! function_exists( 'wp_kses' ) ) { function wp_kses( $s, $t ) { return $s; } }
/* comparatif d'un autre type, principal, dans la même catégorie (« Dans la même catégorie ») */
mkp( 106, 'comparatif', 'comparatif-ventilateur', 'Les meilleurs ventilateurs en 2026' );
$GLOBALS['TERMS'][106] = array( 'post-type-produit' => tm( array( 12 => 'Ventilateur' ) ) );
$GLOBALS['P'][102]->post_status = 'publish';
if ( getenv( 'CLICS' ) ) { foreach ( array( 102 => 900, 106 => 400, 104 => 120 ) as $i => $c ) { $GLOBALS['META'][ $i ]['mltv5_clics_28j'] = $c; } }
if ( getenv( 'SAISON' ) ) { $GLOBALS['ACF']['option']['mltv5_de_saison'] = array( 106, 102 ); }
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
$GLOBALS['TV'][100]['template_description'] = 0;
if ( ! getenv( 'SANSFAQ' ) ) {
  $GLOBALS['ACF'][100]['mltv5_faq_comparatif'] = array();
  foreach ( array( 'Quelle puissance choisir selon la surface ?', 'Quel niveau sonore viser dans une chambre ?', 'Un climatiseur sans évacuation est-il efficace ?', 'Combien consomme un climatiseur mobile ?', 'Simple ou double gaine ?', 'Un modèle réversible vaut-il la peine ?' ) as $i => $q ) {
    $GLOBALS['ACF'][100]['mltv5_faq_comparatif'][] = array( 'mltv5_faq_comparatif_question' => $q, 'mltv5_faq_comparatif_reponse' => '<p>Réponse ' . ( $i + 1 ) . ' assez longue pour être retenue, avec un chiffre 12 et des détails.</p>' );
  }
}
$B = MT_REPO . '/php-css/';
$rend = function ( $f ) { ob_start(); ( function () use ( $f ) { include $f; } )(); return ob_get_clean(); };
$hero = $rend( $B . 'v2/multi-hero-gauche.code.php' );
$res  = $rend( $B . 'v2/multi-resume.code.php' );
$tes  = $rend( $B . 'v2/multi-tests.code.php' );
$g    = $rend( $B . 'v2/multi-colonne-gauche.code.php' );
$faq  = $rend( $B . 'faq.code.php' );
$d    = $rend( $B . 'v2/multi-colonne-droite.code.php' );
$guide = '<section id="partie-guide-achat" class="mt-guide"><h2>Guide d’achat climatiseurs mobiles</h2>' . str_repeat( '<p>Texte du guide d’achat, critères, puissance, bruit, consommation et confort, pour bien choisir. </p>', 60 ) . '</section>'
       . '<section id="partie-marques"><h2>Quelle marque choisir ?</h2>' . str_repeat( '<p>Texte des marques. </p>', 30 ) . '</section>';
$css  = file_get_contents( $B . 'v2/multi-colonnes.css' );
$page = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><style>body{margin:0;font-family:Inter,sans-serif}'
      . '.brxe-container{display:flex;flex-direction:column;width:1220px;max-width:calc(100% - 32px);margin:0 auto}#brxe-yasoqe{width:1000px;margin:0 auto}' . $css . '</style></head><body>'
      . '<div class="top" style="height:300px;background:#eef">(haut de page, top 5, avis détaillés…)</div>' . $tes
      . '<section class="brxe-section"><div class="brxe-container" id="brxe-hnpoyu"><div class="brxe-code">' . $g . '</div><div class="brxe-block" id="brxe-yasoqe">' . $guide . $faq . '</div><div class="brxe-code">' . $d . '</div></div></section>'
      . '<section id="partie-guides-similaires"><h2>Tous les guides climatiseurs mobiles</h2></section></body></html>';
@mkdir( __DIR__ . '/out' );
file_put_contents( __DIR__ . '/out/colonnes.html', $page );
foreach ( array( 'GAUCHE' => $g, 'DROITE' => $d ) as $nom => $h ) {
  echo "== colonne $nom\n";
  preg_match_all( '#<nav class="mt-side-bloc" aria-label="[^"]*"><p class="mt-side-h">(.*?)</p><(ol|ul) class="mt-side-list">(.*?)</\2>(.*?)</nav>#s', $h, $bl, PREG_SET_ORDER );
  if ( ! $bl ) { echo "  (aucun bloc)\n"; }
  foreach ( $bl as $b ) {
    preg_match_all( '#<li([^>]*)>(.*?)</li>#s', $b[3], $li, PREG_SET_ORDER );
    echo '  ', html_entity_decode( $b[1] ), ' (', count( $li ), ( $b[2] === 'ol' ? ', numérotés' : '' ), ")\n";
    foreach ( array_slice( $li, 0, 12 ) as $x ) {
      $href = preg_match( '#href="([^"]*)"#', $x[2], $hm ) ? $hm[1] : '(sans lien)';
      echo '     - ', html_entity_decode( trim( preg_replace( '/\s+/', ' ', strip_tags( str_replace( '</span><span', '</span> · <span', $x[2] ) ) ) ), ENT_QUOTES, 'UTF-8' ), '  →  ', $href, strpos( $x[1], 'aria-current' ) !== false ? '  (page courante)' : '', "\n";
    }
    if ( trim( $b[4] ) !== '' ) { echo '     + ', html_entity_decode( strip_tags( $b[4] ) ), "\n"; }
  }
}
/* Liens internes de la page : chaque #ancre des colonnes doit exister dans la page */
preg_match_all( '#href="\#([^"]+)"#', $g . $d, $an );
preg_match_all( '#id="([^"]+)"#', $page, $ids );
$manq = array_diff( array_unique( $an[1] ), $ids[1] );
echo 'ancres des colonnes : ', count( array_unique( $an[1] ) ), ' | absentes de la page : ', $manq ? implode( ', ', $manq ) : 'aucune', "\n";
