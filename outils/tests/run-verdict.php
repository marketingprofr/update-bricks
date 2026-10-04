<?php
/* H1, ligne auteur et intro qui commence par la réponse (disposition validée le 2026-10-03). Usage : NAVIS=16 php run-verdict.php ;
   AUTEURAPRES=1 : ligne auteur après « L'essentiel » ($MT_AUTEUR_SOUS_H1 = false dans une copie) ;
   AUTEUR="Samuel Petit" pour la ligne auteur ; FICHIER=<chemin> pour tester une copie (V2, réglages activés). */
require __DIR__ . "/wp-stubs.php";
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
$GLOBALS['TV'][100]['template_description'] = 0;
$GLOBALS['TV'][100]['author'] = getenv('AUTEUR') ?: 'Irfann';
$GLOBALS['ACF'][100]['mltv5_criteres_de_choix'] = array( array(), array(), array(), array(), array(), array() );
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
$f = getenv('FICHIER') ?: MT_REPO . '/php-css/hero-gauche.code.php';
if ( getenv( 'AUTEURAPRES' ) ) { @mkdir( __DIR__ . '/out' ); file_put_contents( __DIR__ . '/out/hero-auteur-apres.php', str_replace( '$MT_AUTEUR_SOUS_H1   = true;', '$MT_AUTEUR_SOUS_H1   = false;', file_get_contents( $f ) ) ); $f = __DIR__ . '/out/hero-auteur-apres.php'; }
ob_start(); ( function() use ( $f ) { include $f; } )(); $h = ob_get_clean();
$txt = function( $re ) use ( $h ) { return preg_match( $re, $h, $m ) ? trim( preg_replace( '/\s+/', ' ', html_entity_decode( strip_tags( $m[1] ) ) ) ) : '(absent)'; };
echo 'H1      : ', $txt( '#<h1 class="mt-h1">(.*?)</h1>#s' ), "\n";
echo 'réponse : ', $txt( '#<div class="mt-lede"><p class="mt-lede-reponse">(.*?)</p>#s' ), "\n";
echo 'puis    : ', mb_substr( $txt( '#<p class="mt-lede-reponse">.*?</p>(.*?)</div>#s' ), 0, 120 ), "\n";
echo 'repliée : ', ( strpos( $h, '<div class="mt-lede-texte" id="mt-lede-texte">' ) !== false && strpos( $h, '>Afficher la suite</button>' ) !== false ? 'oui, avec « Afficher la suite »' : 'non' ), "\n";
echo 'pastille: ', strpos( $h, 'class="mt-eyebrow"' ) !== false ? 'affichée' : 'retirée', "\n";
echo 'photo   : ', strpos( $h, 'class="mt-photo"' ) !== false ? 'affichée' : 'retirée', "\n";
echo 'encart séparé « L\'essentiel » : ', strpos( $h, 'class="mt-essentiel"' ) !== false ? 'présent' : 'absent', "\n";
echo 'auteur  : ', $txt( '#<span class="mt-byline-text">(.*?)</span>\s*</div>#s' ), "\n";
echo 'chapô   : ', ( strpos( $h, 'mt-lede-reco' ) !== false ? 'contient encore la phrase verdict' : 'sans phrase verdict' ), "\n";
$pl = strpos( $h, 'class="mt-lede"' ); $pb = strpos( $h, 'mt-byline' ); $ph = strpos( $h, '</h1>' );
echo 'ordre   : ', ( $ph < $pb && $pb < $pl ? 'H1 > ligne auteur > intro' : ( $ph < $pl && $pl < $pb ? 'H1 > intro > ligne auteur' : 'ORDRE INATTENDU' ) ), "\n";
echo 'title   : ', $GLOBALS['WRITTEN']['rank_math_title'] ?? '-', "\n";
