<?php
/* Réponse FAQ « Comment avons-nous établi ce classement ? » : mêmes chiffres que l'encadré (hero-encart puis faq,
   comme sur la page). Mêmes variables que run-encadre.php (SRC, ETUD, AVIS, NAVIS…) ; SANSENCADRE=1 = FAQ seule
   (repli sans chiffres) ; ANCIEN=1 = réglage $MT_METHODO_ENCADRE à false (anciennes valeurs). */
if ( ! getenv( 'SANSENCADRE' ) ) {
  ob_start(); include __DIR__ . '/run-encadre.php'; $enc = ob_get_clean();
  echo "Encadré :\n", $enc;
} else {
  require __DIR__ . '/wp-stubs.php';
  echo "Encadré : absent\n";
}
if ( ! function_exists( 'get_the_modified_date' ) ) { function get_the_modified_date( $f = '', $id = 0 ) { return 'octobre 2026'; } }
if ( ! function_exists( 'wpautop' ) ) { function wpautop( $s ) { return '<p>' . $s . '</p>'; } }
if ( ! function_exists( 'wp_kses' ) ) { function wp_kses( $s, $t ) { return $s; } }
$GLOBALS['TV'][100]['produits_analyses'] = '10'; $GLOBALS['TV'][100]['avis_etudies'] = '132';
$GLOBALS['TV'][100]['sources_consultees'] = '7'; $GLOBALS['TV'][100]['heures_investies'] = '12';
$src = file_get_contents( MT_REPO . '/php-css/faq.code.php' );
if ( getenv( 'ANCIEN' ) ) { $src = preg_replace( '/\$MT_METHODO_ENCADRE = true;/', '$MT_METHODO_ENCADRE = false;', $src, 1 ); }
file_put_contents( __DIR__ . '/out/faq-methodo.php', $src );
ob_start(); ( function() { include __DIR__ . '/out/faq-methodo.php'; } )(); $faq = ob_get_clean();
$plat = function ( $h ) { return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $h ), ENT_QUOTES, 'UTF-8' ) ) ); };
echo "FAQ « Comment avons-nous établi ce classement ? » :\n";
echo '  ', preg_match( '#Comment avons-nous.*?</summary>\s*<div class="mt-faq-a">(.*?)</div>#s', $faq, $m ) ? $plat( $m[1] ) : 'absente', "\n";
if ( preg_match( '#<script type="application/ld\+json">(.*?)</script>#s', $faq, $j ) ) {
  foreach ( (array) ( json_decode( $j[1], true )['mainEntity'] ?? array() ) as $q ) {
    if ( strpos( $q['name'], 'Comment avons-nous' ) === 0 ) { echo '  JSON-LD : ', $plat( $q['acceptedAnswer']['text'] ), "\n"; }
  }
}
