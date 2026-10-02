<?php
/* « Point de vigilance » (champs du type de produit) juste avant le top 5, réglage $MT_POINT_VIGILANCE activé dans
   une copie. Usage : php run-vigilance.php ; V2=1 pour multi-resume ; SANSDATE=1 sans date de la page ;
   INCOMPLET=1 sans adresse de la source (rien ne doit s'afficher) ; AVANTTOP5=1 avec l'encart « Vos questions »
   réglé sur 'avant_top5' (ordre attendu : Vos questions, point de vigilance, top 5). */
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'remove_accents' ) ) { function remove_accents( $t ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $t ); } }
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
$GLOBALS['TV'][100]['template_description'] = 0;
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
$GLOBALS['ACF'][100]['mltv5_faq_comparatif'] = array(
  array( 'mltv5_faq_comparatif_question' => 'Q1 ?', 'mltv5_faq_comparatif_reponse' => '<p>Réponse numéro 1 assez longue pour être retenue par la règle.</p>' ),
  array( 'mltv5_faq_comparatif_question' => 'Q2 ?', 'mltv5_faq_comparatif_reponse' => '<p>Réponse numéro 2 assez longue pour être retenue par la règle.</p>' ),
  array( 'mltv5_faq_comparatif_question' => 'Q3 ?', 'mltv5_faq_comparatif_reponse' => '<p>Réponse numéro 3 assez longue pour être retenue par la règle.</p>' ),
);
$GLOBALS['ACF']['term_10'] = array(
  'mltv5_vigilance_titre'      => 'Point de vigilance : la consommation d’un climatiseur mobile',
  'mltv5_vigilance_texte'      => 'Selon l’ADEME (conseils publiés le 29 mai 2026), les climatiseurs mobiles sont « généralement moins efficaces, plus bruyants » et consomment beaucoup plus d’électricité qu’une climatisation fixe.',
  'mltv5_vigilance_autorite'   => 'ADEME',
  'mltv5_vigilance_url'        => getenv( 'INCOMPLET' ) ? '' : 'https://agirpourlatransition.ademe.fr/particuliers/amenager-maison/climatiser-rafraichir/climatisation-limiter-consommation-electricite',
  'mltv5_vigilance_date_page'  => getenv( 'SANSDATE' ) ? '' : '20260529',
  'mltv5_vigilance_date_verif' => '20261002',
);
$v2  = (bool) getenv( 'V2' );
$res = file_get_contents( MT_REPO . '/php-css/' . ( $v2 ? 'v2/multi-resume.code.php' : 'top5-resume.code.php' ) );
$res = preg_replace( '/\$MT_POINT_VIGILANCE\s*=\s*false;/', '$MT_POINT_VIGILANCE = true;', $res, 1, $nb );
if ( $nb !== 1 ) { exit( "réglage \$MT_POINT_VIGILANCE introuvable\n" ); }
@mkdir( __DIR__ . '/out' );
file_put_contents( __DIR__ . '/out/resume-vigilance.php', $res );
if ( getenv( 'AVANTTOP5' ) ) {
  $hero = preg_replace( "/\\\$MT_VOS_QUESTIONS\s*=\s*'[^']*';/", "\$MT_VOS_QUESTIONS = 'avant_top5';", file_get_contents( MT_REPO . '/php-css/' . ( $v2 ? 'v2/multi-hero-gauche.code.php' : 'hero-gauche.code.php' ) ), 1 );
  file_put_contents( __DIR__ . '/out/hero-vigilance.php', $hero );
  ob_start(); ( function() { include __DIR__ . '/out/hero-vigilance.php'; } )(); ob_end_clean();
}
ob_start(); ( function() { include __DIR__ . '/out/resume-vigilance.php'; } )(); $r = ob_get_clean();

echo 'bloc    : ', $v2 ? 'multi-resume (V2)' : 'top5-resume (V1)', "\n";
$pv = strpos( $r, 'class="mt-vigilance"' );
$pt = strpos( $r, '<div class="mt-top5"' );
if ( $pv === false ) { echo "point   : absent\n"; exit; }
$pq = strpos( $r, 'mt-faq-mini' );
echo 'place   : ', $pv < $pt ? 'avant le top 5' : 'APRÈS LE TOP 5 (inattendu)', $pq !== false ? ( $pq < $pv ? ', après « Vos questions »' : ', AVANT « Vos questions » (inattendu)' ) : '', "\n";
preg_match( '#<section class="mt-vigilance".*?</section>#s', $r, $m );
libxml_use_internal_errors( true );
$d = new DOMDocument(); $d->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $m[0] . '</body></html>' );
echo 'HTML    : ', count( array_filter( libxml_get_errors(), function ( $e ) { return $e->level > LIBXML_ERR_WARNING && strpos( $e->message, 'Tag ' ) === false; } ) ), " erreur(s) (balises HTML5 comme <section> ignorées, comme run-page)\n";
$x = new DOMXPath( $d );
echo 'titre   : <', $x->query( '//h2' )->item( 0 )->nodeName, '> ', $x->query( '//h2' )->item( 0 )->textContent, "\n";
echo 'source  : ', trim( $x->query( '//p[@class="mt-vigilance-source"]' )->item( 0 )->textContent ), "\n";
$a = $x->query( '//a' )->item( 0 );
echo 'lien    : class="', $a->getAttribute( 'class' ), '" rel="', $a->getAttribute( 'rel' ), '"', "\n";
