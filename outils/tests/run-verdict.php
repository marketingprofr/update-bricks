<?php
/* Verdict sous le H1, ligne auteur et H1 du hero gauche. Usage : NAVIS=16 php run-verdict.php ;
   AUTEUR="Samuel Petit" pour la ligne auteur ; FICHIER=<chemin> pour tester une copie (V2, réglages activés). */
require __DIR__ . "/wp-stubs.php";
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
$GLOBALS['TV'][100]['template_description'] = 0;
$GLOBALS['TV'][100]['author'] = getenv('AUTEUR') ?: 'Irfann';
$GLOBALS['ACF'][100]['mltv5_criteres_de_choix'] = array( array(), array(), array(), array(), array(), array() );
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
$f = getenv('FICHIER') ?: MT_REPO . '/php-css/hero-gauche.code.php';
ob_start(); ( function() use ( $f ) { include $f; } )(); $h = ob_get_clean();
$txt = function( $re ) use ( $h ) { return preg_match( $re, $h, $m ) ? trim( preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $m[1] ) ) ) ) : '(absent)'; };
echo 'H1      : ', $txt( '#<h1 class="mt-h1">(.*?)</h1>#s' ), "\n";
echo 'verdict : ', $txt( '#<p class="mt-verdict">(.*?)</p>#s' ), "\n";
echo 'auteur  : ', $txt( '#<span class="mt-byline-text">(.*?)</span>\s*</div>#s' ), "\n";
echo 'chapô   : ', ( strpos( $h, 'mt-lede-reco' ) !== false ? 'contient encore la phrase verdict' : 'sans phrase verdict' ), "\n";
echo 'ordre   : ', ( strpos( $h, 'mt-verdict' ) < strpos( $h, 'mt-byline' ) && strpos( $h, '</h1>' ) < strpos( $h, 'mt-verdict' ) ? 'H1 > verdict > ligne auteur' : 'ORDRE INATTENDU' ), "\n";
echo 'title   : ', $GLOBALS['WRITTEN']['rank_math_title'] ?? '-', "\n";
