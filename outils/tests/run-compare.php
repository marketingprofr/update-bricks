<?php
/* Non-régression : sans sous-comparatif, chaque bloc V2 doit produire le même HTML
   que son bloc V1 (résumé, tests, tableau, sommaire).
   Usage : php run-compare.php   → affiche IDENTIQUE ou le nombre de lignes qui diffèrent
   (les fichiers sont écrits dans out/ pour un diff à la main).
   Remarque : quelques écarts sont VOULUS (barre de tri compacte, lien « Comment nous
   évaluons », titre du sommaire…) — lire le diff avant de conclure. */
require __DIR__ . '/wp-stubs.php';
@mkdir( __DIR__ . '/out' );
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
$pairs = array(
  'top5-resume'        => 'v2/multi-resume',
  'top5-tests'         => 'v2/multi-tests',
  'tableau-comparatif' => 'v2/multi-tableau',
  'sommaire'           => 'v2/multi-sommaire',
);
foreach ( $pairs as $v1 => $v2 ) {
  $out = array();
  foreach ( array( $v1, $v2 ) as $f ) {
    $GLOBALS['FOOTER'] = array();
    ob_start();
    ( function ( $file ) { include $file; } )( MT_REPO . '/php-css/' . $f . '.code.php' );
    foreach ( $GLOBALS['FOOTER'] as $cb ) { $cb(); }
    $out[ $f ] = ob_get_clean();
    file_put_contents( __DIR__ . '/out/cmp-' . basename( $f ) . '.html', $out[ $f ] );
  }
  $a = explode( "\n", trim( $out[ $v1 ] ) );
  $b = explode( "\n", trim( $out[ $v2 ] ) );
  $diff = count( array_diff( $a, $b ) ) + count( array_diff( $b, $a ) );
  echo str_pad( $v1, 20 ) . ' vs ' . str_pad( $v2, 20 ) . ' : ' . ( $diff ? $diff . ' ligne(s) différente(s)' : 'IDENTIQUE' ) . "\n";
}
