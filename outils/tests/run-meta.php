<?php
/* Méta description automatique du hero gauche. Usage : NAVIS=16 php run-meta.php ;
   LLM="la meilleure" PLUR="offres box internet" NOM="Nom du n°1" pour varier accords et longueur. */
require __DIR__ . "/wp-stubs.php";
$GLOBALS['TV'][100]['template_description'] = 0;
$GLOBALS['TV'][100]['lalalesmeilleur'] = getenv('LLM') ?: 'le meilleur';
$GLOBALS['TV'][100]['type_de_produit_au_pluriel'] = getenv('PLUR') ?: 'climatiseurs mobiles';
if ( getenv('NOM') ) { $GLOBALS['ACF'][1]['mltv5_forcer_affichage_du_titre'] = getenv('NOM'); }
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
$GLOBALS['META'][100]['rank_math_description'] = '';
ob_start(); ( function() { include MT_REPO . '/php-css/hero-gauche.code.php'; } )(); ob_end_clean();
$d = $GLOBALS['WRITTEN']['rank_math_description'] ?? '(non écrite)';
echo $d, '  [', mb_strlen( $d, 'UTF-8' ), " car.]\n";
