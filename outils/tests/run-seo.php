<?php
/* Title et méta description écrits par le hero gauche V1 (règle des tests Jev du 2026-10-02).
   Usage : NAVIS=16 php run-seo.php   (16 avis publiés : format « N analysés, T retenus »)
           NAVIS=4 php run-seo.php    (N <= T : repli « (N produits comparés) ») */
require __DIR__ . '/wp-stubs.php';
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
function mt_rendu() { ob_start(); ( function() { include MT_REPO . '/php-css/hero-gauche.code.php'; } )(); ob_end_clean(); }

$cas = array(
  'masculin singulier'   => array( 'lalalesmeilleur' => 'le meilleur', 'type_de_produit_au_singulier' => 'climatiseur mobile', 'type_de_produit_au_pluriel' => 'climatiseurs mobiles', 'masculinsfeminins' => 'Meilleurs' ),
  'féminin singulier'    => array( 'lalalesmeilleur' => 'la meilleure', 'type_de_produit_au_singulier' => 'banque en ligne', 'type_de_produit_au_pluriel' => 'banques en ligne', 'masculinsfeminins' => 'Meilleures' ),
  'féminin pluriel'      => array( 'lalalesmeilleur' => 'les meilleures', 'type_de_produit_au_singulier' => 'chaussure de running', 'type_de_produit_au_pluriel' => 'chaussures de running', 'masculinsfeminins' => 'Meilleures' ),
  'sans lalalesmeilleur' => array( 'lalalesmeilleur' => '', 'type_de_produit_au_singulier' => 'mutuelle santé', 'type_de_produit_au_pluriel' => 'mutuelles santé', 'masculinsfeminins' => 'Meilleures' ),
  'titre forcé'          => array( 'lalalesmeilleur' => 'le meilleur', 'type_de_produit_au_singulier' => 'matelas', 'type_de_produit_au_pluriel' => 'matelas', 'masculinsfeminins' => 'Meilleurs', 'forcer_affichage_du_titre' => 'Comparatif matelas 2026 : les meilleurs selon votre profil' ),
);
$GLOBALS['TV'][100]['template_description'] = 0; // description automatique, comme sur le site
$base = $GLOBALS['TV'][100];
foreach ( $cas as $lbl => $v ) {
  $GLOBALS['TV'][100] = array_merge( $base, array( 'forcer_affichage_du_titre' => '' ), $v );
  $GLOBALS['WRITTEN'] = array();
  mt_rendu();
  echo 'title ' . str_pad( $lbl, 22 ) . ': ' . ( $GLOBALS['WRITTEN']['rank_math_title'] ?? '(non écrit)' ) . "\n";
}

$GLOBALS['TV'][100] = $base;
$descs = array( 'vide' => '', 'égale à l\'extrait (auto)' => 'Ancienne intro.', 'saisie à la main' => 'Description écrite à la main.' );
foreach ( $descs as $lbl => $d ) {
  $GLOBALS['META'][100]['rank_math_description'] = $d;
  $GLOBALS['P'][100]->post_excerpt = 'Ancienne intro.';
  $GLOBALS['WRITTEN'] = array();
  mt_rendu();
  echo 'description ' . str_pad( $lbl, 26 ) . ': '
    . ( array_key_exists( 'rank_math_description', $GLOBALS['WRITTEN'] ) ? 'réécrite (« ' . $GLOBALS['WRITTEN']['rank_math_description'] . ' »)' : 'gardée' ) . "\n";
}
