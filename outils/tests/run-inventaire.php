<?php
/* Outil d'inventaire des comparatifs (php-css/outils/inventaire-multi.code.php)
   sur un jeu de comparatifs fictif. Usage : php run-inventaire.php */
require __DIR__ . '/wp-stubs.php';
$GLOBALS['CAN'] = true;
/* Jeu de données : climatiseur mobile (principal) + réversible + silencieux + 9000 BTU, 2 principaux casques (doublon), orphelin, sans type */
$P = &$GLOBALS['P']; $TERMS = &$GLOBALS['TERMS']; $TV = &$GLOBALS['TV']; $META = &$GLOBALS['META'];
$P = array(); $TERMS = array(); $TV = array(); $META = array();
mkp( 38000, 'comparatif', 'climatiseur-mobile', 'Les meilleurs climatiseurs mobiles' );
$TERMS[38000] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseurs mobiles' ) ) );
mkp( 38292, 'comparatif', 'climatiseur-mobile-reversible', 'Les meilleurs climatiseurs mobiles réversibles', 'private' );
$TERMS[38292] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseurs mobiles' ) ), 'post-type-attribut' => tm( array( 21 => 'Réversible' ) ) );
mkp( 38300, 'comparatif', 'climatiseur-mobile-silencieux', 'Les meilleurs climatiseurs mobiles silencieux' );
$TERMS[38300] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseurs mobiles' ) ), 'post-type-attribut' => tm( array( 22 => 'Silencieux' ) ) );
mkp( 38301, 'comparatif', 'x', 'Climatiseurs mobiles silencieux réversibles' );
$TERMS[38301] = array( 'post-type-produit' => tm( array( 10 => 'Climatiseurs mobiles' ) ), 'post-type-attribut' => tm( array( 22 => 'Silencieux', 21 => 'Réversible' ) ) );
mkp( 5000, 'comparatif', 'casques', 'Les meilleurs casques audio' );
$TERMS[5000] = array( 'post-type-produit' => tm( array( 30 => 'Casques audio' ) ) );
mkp( 5001, 'comparatif', 'casques-2', 'Casques audio (ancien)' );
$TERMS[5001] = array( 'post-type-produit' => tm( array( 30 => 'Casques audio' ) ) );
mkp( 5002, 'comparatif', 'casques-bt', 'Les meilleurs casques Bluetooth &amp; sans fil' );
$TERMS[5002] = array( 'post-type-produit' => tm( array( 30 => 'Casques audio' ) ), 'post-type-attribut' => tm( array( 31 => 'Bluetooth' ) ) );
mkp( 6000, 'comparatif', 'friteuse-sans-huile', 'Les meilleures friteuses sans huile' );
$TERMS[6000] = array( 'post-type-produit' => tm( array( 40 => 'Friteuses' ) ), 'post-type-attribut' => tm( array( 41 => 'Sans huile' ) ) );
mkp( 7000, 'comparatif', 'rien', 'Comparatif sans type' );
foreach ( array( 38000, 38300, 38301, 5000, 5002, 6000 ) as $i ) { $TV[ $i ] = array( 'top_avis_ids' => array( 1, 2, 3 ), 'type_de_produit_au_pluriel' => 'x' ); }
$TV[5001] = array( 'top_avis_ids' => array( 1, 2, 3, 4, 5 ) );
$META[38000]['mltv5_sous_comparatifs'] = serialize( array( '38292' ) );
$GLOBALS['TAGGED'][38000] = true;
ob_start(); include MT_REPO . '/php-css/outils/inventaire-multi.code.php'; $h = ob_get_clean();
preg_match( '#var data = (.*?);\n#', $h, $m ); $d = json_decode( $m[1], true );
foreach ( $d['inv'] as $r ) echo implode( ' ; ', array_map( function( $x ) { return (string) $x; }, array( $r[0], $r[2], $r[3], $r[4], $r[5], $r[6], $r[8], $r[10], $r[12], $r[13], $r[14], $r[18] ) ) ) . "\n";
echo "---\n"; foreach ( $d['syn'] as $r ) echo implode( ' ; ', $r ) . "\n";
echo "---\n"; echo trim( preg_replace( '/\s+/', ' ', strip_tags( substr( $h, 0, strpos( $h, '<script' ) ) ) ) ) . "\n";
