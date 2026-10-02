<?php
/* Prix d'abonnement (liste ACF « mltv5_unite_du_prix » du type de produit : unique / mois / an) : réponse « budget » de la FAQ (et son JSON-LD
   FAQPage), encart « Vos questions », offres JSON-LD des tests V1/V2, prix des fiches avis.
   Usage : php run-prix.php ; UNITE=mois ou UNITE=an règle l'unité du prix du type du faux comparatif (MENSUEL=1 = UNITE=mois) ; PRIX0=1 met tous les prix à 0 ;
   FEM=1 pour un type au féminin (accords). */
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'get_the_modified_date' ) ) { function get_the_modified_date( $f = '', $id = 0 ) { return 'octobre 2026'; } }
if ( ! function_exists( 'wpautop' ) ) { function wpautop( $s ) { return '<p>' . $s . '</p>'; } }
if ( ! function_exists( 'wp_kses' ) ) { function wp_kses( $s, $t ) { return $s; } }
if ( ! function_exists( 'remove_accents' ) ) { function remove_accents( $t ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $t ); } }
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
$GLOBALS['TV'][100]['template_description'] = 0;
if ( getenv( 'FEM' ) ) { $GLOBALS['TV'][100]['lalalesmeilleur'] = 'la meilleure'; $GLOBALS['TV'][100]['type_de_produit_au_singulier'] = 'mutuelle'; $GLOBALS['TV'][100]['type_de_produit_au_pluriel'] = 'mutuelles'; }
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
$GLOBALS['ACF'][100]['mltv5_criteres_de_choix'] = array_map( function ( $t ) { return array( 'mltv5_critere_de_choix' => $t ); }, array( 'Le débit', 'La latence', 'Le prix', 'Le service client' ) );
$GLOBALS['ACF'][100]['mltv5_faq_comparatif'] = array(
  array( 'mltv5_faq_comparatif_question' => 'Q1 ?', 'mltv5_faq_comparatif_reponse' => '<p>Réponse numéro 1 assez longue pour être retenue par la règle.</p>' ),
  array( 'mltv5_faq_comparatif_question' => 'Q2 ?', 'mltv5_faq_comparatif_reponse' => '<p>Réponse numéro 2 assez longue pour être retenue par la règle.</p>' ),
);
$prix = getenv( 'PRIX0' ) ? array_fill( 1, 14, '0' ) : array( 1 => '19,99', 2 => '2.49', 3 => '', 4 => '35', 5 => '9.9' );
foreach ( $prix as $i => $p ) { $GLOBALS['ACF'][ $i ]['mltv5_prix_indicatif'] = $p; }
$unite = getenv( 'UNITE' ) ?: ( getenv( 'MENSUEL' ) ? 'mois' : '' );
if ( $unite !== '' ) { $GLOBALS['ACF']['term_10']['mltv5_unite_du_prix'] = $unite; }
echo 'mode : ', $unite !== '' ? 'unité du prix « ' . $unite . ' »' : 'champ absent (comme aujourd\'hui)', getenv( 'PRIX0' ) ? ', tous les prix à 0' : '', "\n";
$plat = function ( $h ) { return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $h ), ENT_QUOTES, 'UTF-8' ) ) ); };

/* 1) FAQ */
ob_start(); ( function() { include MT_REPO . '/php-css/faq.code.php'; } )(); $faq = ob_get_clean();
if ( preg_match( '#Quel budget pr.{1,12}voir.*?</summary>\s*<div class="mt-faq-a">(.*?)</div>#s', $faq, $m ) ) {
  echo "FAQ, réponse budget :\n  ", $plat( $m[1] ), "\n";
} else {
  echo "FAQ, réponse budget : absente\n";
}
if ( preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $faq, $j ) ) {
  $d = json_decode( $j[1], true );
  $t = '';
  foreach ( (array) ( $d['mainEntity'] ?? array() ) as $q ) { if ( strpos( $q['name'], 'budget' ) !== false ) { $t = $plat( $q['acceptedAnswer']['text'] ); } }
  echo 'FAQ, JSON-LD budget : ', $t !== '' ? $t : 'absent', "\n";
}

/* 2) Encart « Vos questions » (hero V1, réglage activé dans une copie) */
$src = preg_replace( "/\\\$MT_VOS_QUESTIONS\s*=\s*'[^']*';/", "\$MT_VOS_QUESTIONS = 'sous_reponse';", file_get_contents( MT_REPO . '/php-css/hero-gauche.code.php' ), 1 );
file_put_contents( __DIR__ . '/out/hero-prix.php', $src );
ob_start(); ( function() { include __DIR__ . '/out/hero-prix.php'; } )(); $h = ob_get_clean();
echo 'Vos questions, budget : ', preg_match( '#<li><b>Quel budget[^<]*</b> ([^<]*)</li>#u', $h, $m ) ? html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) : 'absent', "\n";

/* 3) Offres JSON-LD des tests V1 et V2 */
foreach ( array( 'top5-tests.code.php', 'v2/multi-tests.code.php' ) as $f ) {
  ob_start(); ( function() use ( $f ) { include MT_REPO . '/php-css/' . $f; } )(); $t = ob_get_clean();
  $offres = array();
  preg_match_all( '#<script type="application/ld\+json">(.*?)</script>#s', $t, $sc );
  foreach ( $sc[1] as $js ) {
    $d = json_decode( $js, true );
    $pile = array( $d );
    while ( $pile ) {
      $n = array_pop( $pile );
      if ( ! is_array( $n ) ) { continue; }
      if ( isset( $n['@type'] ) && $n['@type'] === 'Product' ) {
        $o = $n['offers'] ?? null;
        $offres[ $n['name'] ] = $o ? ( $o['@type'] . ' ' . ( $o['price'] ?? $o['lowPrice'] ) . ( isset( $o['highPrice'] ) ? ' high ' . $o['highPrice'] : '' ) . ( isset( $o['priceSpecification'] ) ? ' + ' . $o['priceSpecification']['@type'] . ' /' . $o['priceSpecification']['referenceQuantity']['unitCode'] : '' ) ) : 'sans offre';
        continue;
      }
      foreach ( $n as $v ) { $pile[] = $v; }
    }
  }
  ksort( $offres );
  echo 'JSON-LD ', $f, " :\n";
  foreach ( array_slice( $offres, 0, 5, true ) as $k => $v ) { echo '  ', $k, ' : ', $v, "\n"; }
}

/* 4) Fiches avis : format du prix (fonctions des blocs avis) */
$GLOBALS['CUR'] = 1;
$GLOBALS['TERMS'][1]['post-type-produit'] = $GLOBALS['TERMS'][100]['post-type-produit'];
$code = file_get_contents( MT_REPO . '/php-css/avis-hero.code.php' );
preg_match( "#if \( ! function_exists\( 'fp_format_price' \) \).*?\R\}\R#s", $code, $fp );
eval( $fp[0] );
$GLOBALS['fp_prix_unite'] = mt_prix_unite( 1 );
echo 'Fiche avis : ', ( ! empty( $GLOBALS['fp_prix_unite'] ) ? 'À partir de' : 'Prix moyen constaté :' ), ' ', fp_format_price( mt5_num( $GLOBALS['ACF'][1]['mltv5_prix_indicatif'] ) ), ' | 2,49 → ', fp_format_price( 2.49 ), ' | 0 → « ', fp_format_price( 0 ), " »\n";
