<?php
/* Verdict d un multi-comparatif (profils des sections, critères < 3). Usage : NAVIS=16 php run-verdict-multi.php */
require __DIR__ . "/wp-stubs.php";
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
$GLOBALS['TV'][100]['template_description'] = 0;
$GLOBALS['ACF'][100]['mltv5_criteres_de_choix'] = array( array(), array() );
ob_start(); ( function() { include MT_REPO . '/php-css/v2/multi-hero-gauche.code.php'; } )(); $h = ob_get_clean();
preg_match( '#<p class="mt-verdict">(.*?)</p>#s', $h, $m );
echo trim( html_entity_decode( strip_tags( $m[1] ?? '(absent)' ) ) ), "\n";
