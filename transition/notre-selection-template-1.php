<?php
/* =====================================================================
   NOTRE SÉLECTION — Template 1 (liste résumé « Notre sélection »)
   Version « transition ».

   AVANT : les produits venaient du calcul « meilleurs produits de la
   catégorie WordPress » via mlt_get_meilleur_produit($position, …).

   MAINTENANT : la sélection est calculée en amont à partir des taxonomies
   (post-type-produit exact + AU MOINS les mêmes post-type-attribut que la
   page en cours) puis stockée dans les ACF du guide d'achat. On lit donc
   une liste ORDONNÉE d'IDs de produits (1 post = 1 produit), comme le
   nouveau template Top 5.

   Source des produits (par ordre de priorité) :
     1) variable de template  top_avis_ids        (liste ordonnée d'IDs)
     2) champ relation ACF     mltv5_best_products  (repli)
     3) repli LEGACY           mlt_get_meilleur_produit()  (ancien calcul
        par catégorie) — conservé le temps de la migration.

   La sortie HTML et les classes CSS restent identiques à l'ancien
   template : aucun changement visuel à prévoir.

   À coller dans un élément CODE Bricks (Execute code = ON).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIGURATION
   --------------------------------------------------------------------- */
$afficher_type_produit_dans_verdict = true;      // true = garder le type de produit dans le verdict
$icone_lien = 'external';                          // 'external' | 'cart'
$nb_max     = 5;                                   // nombre de produits affichés
$amazon_tag = 'mlt00-21';                          // tag affilié Amazon (liens ASIN)

/* ---------------------------------------------------------------------
   Helper : URL d'un produit
   Priorité : ASIN Amazon (avec tag) > lien perso 1..3 > permalink.
   Cascade IDENTIQUE à l'ancien helper (« Amazon ASIN > Link 1 > Permalink »)
   pour ne rien changer au rendu : chaque produit reste cliquable comme
   aujourd'hui. Le permalink en dernier recours pourra être revu à une
   étape ultérieure de la migration (un lien interne n'a pas à porter
   rel="sponsored"), mais on le conserve ici pour une transition sans casse.
   --------------------------------------------------------------------- */
if ( ! function_exists( 'ns1_url_affilie' ) ) {
  function ns1_url_affilie( $pid, $amazon_tag ) {
    $asin = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
    if ( $asin !== '' ) {
      return 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $amazon_tag;
    }
    for ( $i = 1; $i <= 3; $i++ ) {
      $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
      if ( $u !== '' && strpos( $u, 'http' ) === 0 ) {
        return $u;
      }
    }
    return get_permalink( $pid ); // dernier recours, comme l'ancien helper
  }
}

/* ---------------------------------------------------------------------
   Liste ordonnée des IDs produits du guide
   --------------------------------------------------------------------- */
$page_id = get_the_ID();
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();

// 1) variable de template top_avis_ids (liste ordonnée du guide)
$ids = ( isset( $page_tv['top_avis_ids'] ) && is_array( $page_tv['top_avis_ids'] ) ) ? $page_tv['top_avis_ids'] : array();

// 2) repli : champ relation ACF mltv5_best_products
if ( empty( $ids ) ) {
  $rel = get_field( 'mltv5_best_products', $page_id );
  if ( is_array( $rel ) ) {
    foreach ( $rel as $r ) { $ids[] = is_object( $r ) ? $r->ID : (int) $r; }
  }
}

// 3) repli LEGACY : ancien calcul par catégorie (le temps de la transition)
if ( empty( $ids ) && function_exists( 'mlt_get_meilleur_produit' ) ) {
  for ( $position = 1; $position <= $nb_max; $position++ ) {
    $legacy_id = mlt_get_meilleur_produit( $position, null, 'id' );
    if ( empty( $legacy_id ) ) { break; }
    $ids[] = $legacy_id;
  }
}

// Nettoyage : entiers valides, uniques, limités à $nb_max, et posts existants
$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
$ids = array_slice( $ids, 0, $nb_max );
$ids = array_values( array_filter( $ids, function ( $pid ) { return (bool) get_post( $pid ); } ) );

if ( empty( $ids ) ) { return; } // rien à afficher : on n'imprime même pas le conteneur

/* ---------------------------------------------------------------------
   Variables utiles au traitement du verdict
   --------------------------------------------------------------------- */
$type_de_produit_au_singulier = isset( $page_tv['type_de_produit_au_singulier'] )
  ? trim( (string) $page_tv['type_de_produit_au_singulier'] )
  : '';

/* Icône du lien selon la configuration */
if ( $icone_lien === 'cart' ) {
  $icone_html = '<i class="fas fa-shopping-cart" style="margin-right: 4px; font-size: 0.75em; vertical-align: middle; position: relative; top: -1px;"></i>';
} else {
  $icone_html = '<i class="fas fa-external-link-alt" style="margin-right: 4px; font-size: 0.75em; vertical-align: middle; position: relative; top: -1px;"></i>';
}

/* Contexte post global (pour les helpers qui en dépendraient) */
global $post;
$ns1_saved_post = $post;
?>
<div id="bloc-after-featured" class="ct-div-block">
    <h2 class="gradient-text-dark no-toc">Notre sélection</h2>
    <ul id="intro-list">
    <?php
    $position = 0;
    foreach ( $ids as $id_produit ) {
        $p = get_post( $id_produit );
        if ( ! $p ) { continue; }
        $post = $p;
        setup_postdata( $post );
        $position++;

        /* Données produit */
        $url_affilie = ns1_url_affilie( $id_produit, $amazon_tag );
        $titre_raw   = get_the_title( $id_produit );

        /* Verdict : get_default_product_label($id, $score) */
        $score         = get_field( 'mltv5_score_recent', $id_produit );
        $verdict_court = function_exists( 'get_default_product_label' )
          ? get_default_product_label( $id_produit, $score )
          : '';

        /* --- Traitement du verdict (identique à l'ancien template) --- */
        $verdict_plus_court = $verdict_court;
        $verdict_plus_court = str_replace( 'Le meilleur',  'Meilleur', $verdict_plus_court );
        $verdict_plus_court = str_replace( 'La meilleur',  'Meilleur', $verdict_plus_court );
        $verdict_plus_court = str_replace( 'Les meilleur', 'Meilleur', $verdict_plus_court );
        $verdict_plus_court = str_replace( '  ', ' ', $verdict_plus_court );

        if ( $afficher_type_produit_dans_verdict ) {
            $verdict_affiche = $verdict_plus_court;
        } else {
            $verdict_affiche = $verdict_plus_court;
            if ( ! empty( $type_de_produit_au_singulier ) ) {
                $verdict_affiche = str_ireplace( ' ' . $type_de_produit_au_singulier, '', $verdict_affiche );
            }
            $verdict_affiche = preg_replace( '/\s+/', ' ', $verdict_affiche );
            $verdict_affiche = trim( $verdict_affiche );
        }
        /* --- fin traitement verdict --- */

        /* Titre tronqué à 11 mots */
        $titrelength = ! empty( $titre_raw ) ? wp_trim_words( $titre_raw, 11 ) : 'Produit sans titre';

        /* --- Sortie HTML (structure inchangée) --- */
        echo "<li>";

        // Ancre de navigation interne vers le test du produit
        echo "<span class='aller-au-test'><a href='#produit-n-" . esc_attr( $position ) . "' class='nounder' title='Voir le test'>";
        echo "<span class='down-arrow'>➔</span></a></span>";

        echo "<div class='li-content'>";

        // Verdict
        echo "<span class='notre-selection-1-verdict'><strong>";
        echo ucfirst( $verdict_affiche ) . "&nbsp;:&nbsp;</strong></span>";

        // Lien produit (ASIN > lien perso > permalink) ; titre simple si vraiment aucune URL
        if ( ! empty( $url_affilie ) ) {
            echo "<a class='secondary product-link-with-icon' ";
            echo "href='" . esc_url( $url_affilie ) . "' target='_blank' rel='sponsored noopener'>";
            echo $icone_html;
            echo esc_html( $titrelength );
            echo "</a>";
        } else {
            echo esc_html( $titrelength );
        }

        echo "</div>"; // .li-content
        echo "</li>";
    }

    /* Restauration du contexte post */
    $post = $ns1_saved_post;
    wp_reset_postdata();
    ?>
    </ul>
</div>
