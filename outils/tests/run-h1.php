<?php
/* H1 + title SEO Rank Math du hero gauche V2.
   Usage : php run-h1.php            (multi-comparatif)
           NOSUB=1 php run-h1.php    (sans sous-comparatif = V1)
           NAVIS=55 php run-h1.php   (55 avis publiés pour le compteur) */
require __DIR__ . '/wp-stubs.php';
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
foreach ( ( getenv('NOSUB') ? array( 'sans sous-comparatif' => false ) : array( 'multi' => true ) ) as $lbl => $multi ) {
  if ( ! $multi ) { unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] ); }
  ob_start(); (function(){ include MT_REPO . '/php-css/v2/multi-hero-gauche.code.php'; })(); $h = ob_get_clean();
  preg_match( '#<h1 class="mt-h1">(.*?)</h1>#s', $h, $m );
  echo "title SEO : " . ( $GLOBALS['WRITTEN']['rank_math_title'] ?? '-' ) . "\n";
  echo str_pad( $lbl, 22 ) . ': ' . trim( preg_replace( '/\s+/', ' ', strip_tags( html_entity_decode( $m[1] ?? '(pas de H1)' ) ) ) ) . "\n";
  /* le plan est mis en cache statique : on recharge pour le 2e cas */
  break;
}
