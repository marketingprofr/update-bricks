<?php
/* =====================================================================
   MEILLEURTEST — Partie guide d'achat : COLONNE DE DROITE (modèle Game8)
   Demande de Samuel du 2026-10-05 (relayée par la Coordination) : tout
   mettre en place, puis retirer ce qui ne sert pas.

   Où le coller : un élément Code placé JUSTE APRÈS le bloc du guide d'achat,
   dans le même conteneur que la colonne de gauche (multi-colonne-gauche).
   Entre 992 et 1199 px, cette colonne passe sous le guide ; masquée sur mobile.

   Blocs (titres en <p class="mt-side-h">, pas de titre HTML) ; un bloc sans
   données ne s'affiche pas :
     4. « Les plus consultés en {catégorie} » : les 5 comparatifs de la
        catégorie qui ont le plus de clics Google sur 28 jours (champ
        $MT_SIDE_CHAMP_CLICS, importé par l'Architecture) ;
     5. « Mis à jour récemment » : les 5 derniers comparatifs modifiés de la
        catégorie, avec leur date ;
     6. « Les comparatifs populaires » : le top 5 du site, même champ de clics ;
     7. « De saison » : liste choisie par Samuel (champ ACF d'options
        $MT_SIDE_CHAMP_SAISON, 3 à 6 comparatifs) ;
     8. « Dans la même catégorie » : comparatifs principaux des autres types
        de produit de la catégorie (ventilateur, déshumidificateur…) ;
     9. « Questions fréquentes » : 5 questions de la rédaction, avec un lien
        vers leur réponse dans la FAQ de la page.
   Honnêteté : « plus consultés » et « populaires » seulement avec de vrais
   clics, jamais de compteur inventé. Listes en cache 12 h (transients).
   ===================================================================== */
$MT_SIDE_CONSULTES    = true;  // 4. « Les plus consultés en {catégorie} »
$MT_SIDE_RECENTS      = true;  // 5. « Mis à jour récemment »
$MT_SIDE_POPULAIRES   = true;  // 6. « Les comparatifs populaires »
$MT_SIDE_SAISON       = true;  // 7. « De saison »
$MT_SIDE_CATEGORIE    = true;  // 8. « Dans la même catégorie »
$MT_SIDE_FAQ          = true;  // 9. « Questions fréquentes »
$MT_SIDE_CHAMP_CLICS  = 'mltv5_clics_28j';  // clics Google sur 28 jours d'un comparatif (champ numérique, importé par l'Architecture)
$MT_SIDE_CHAMP_SAISON = 'mltv5_de_saison';  // champ ACF d'une page d'options : relation vers 3 à 6 comparatifs
$MT_SIDE_MAX          = 10;    // liens au plus par bloc

/* ---------------------------------------------------------------------
   Aides communes aux deux colonnes (copie IDENTIQUE dans multi-colonne-gauche
   et multi-colonne-droite)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt_side_cache' ) ) {
  /* Liste mise en cache 12 h ; la clé change quand la page est modifiée */
  function mt_side_cache( $page_id, $bloc, $fn ) {
    $k = 'mt_side_' . md5( $page_id . '|' . get_post_modified_time( 'U', true, $page_id ) . '|' . $bloc );
    $v = function_exists( 'get_transient' ) ? get_transient( $k ) : false;
    if ( is_array( $v ) ) { return $v; }
    $v = (array) $fn();
    if ( function_exists( 'set_transient' ) ) { set_transient( $k, $v, 12 * 3600 ); }
    return $v;
  }
}
if ( ! function_exists( 'mt_side_label' ) ) {
  /* Libellé court d'un comparatif : « Les meilleurs climatiseurs mobiles silencieux » → « Climatiseurs mobiles silencieux » */
  function mt_side_label( $title ) {
    $t = trim( wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) );
    $t = preg_replace( '/^(les|le|la)\s+meilleur(e?s?)\s+/iu', '', $t );
    $t = preg_replace( '/\s+en\s+20\d\d\b.*$/u', '', $t );
    return mb_strtoupper( mb_substr( $t, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $t, 1, null, 'UTF-8' );
  }
}
if ( ! function_exists( 'mt_side_cat' ) ) {
  /* Catégorie la plus précise du comparatif (ex. « Climatisation », sous « Maison ») */
  function mt_side_cat( $page_id ) {
    $best = null; $depth = -1;
    foreach ( (array) get_the_category( $page_id ) as $c ) {
      if ( ! is_object( $c ) ) { continue; }
      $d = count( (array) get_ancestors( (int) $c->term_id, 'category' ) );
      if ( $d > $depth ) { $depth = $d; $best = $c; }
    }
    return $best;
  }
}
if ( ! function_exists( 'mt_side_bloc' ) ) {
  /* Un bloc de liens : titre en <p>, liste ; $items = [ [ 't' => texte, 'u' => lien, 'm' => mention, 'cur' => page courante ] ] ;
     liste vide → rien */
  function mt_side_bloc( $titre, $items, $num = false, $apres = '' ) {
    if ( empty( $items ) ) { return ''; }
    $li = '';
    foreach ( $items as $it ) {
      $txt = '<span class="mt-side-t">' . str_replace( ' ?', "Â ?", esc_html( $it['t'] ) ) . '</span>' . ( ( $it['m'] ?? '' ) !== '' ? '<span class="mt-side-m">' . esc_html( $it['m'] ) . '</span>' : '' );
      if ( ( $it['u'] ?? '' ) !== '' && empty( $it['cur'] ) ) { $li .= '<li><a href="' . esc_url( $it['u'] ) . '">' . $txt . '</a></li>'; }
      else { $li .= '<li' . ( ! empty( $it['cur'] ) ? ' class="mt-side-cur" aria-current="page"' : '' ) . '>' . $txt . '</li>'; }
    }
    $tag = $num ? 'ol' : 'ul';
    return '<nav class="mt-side-bloc" aria-label="' . esc_attr( $titre ) . '"><p class="mt-side-h">' . esc_html( $titre ) . '</p>'
         . '<' . $tag . ' class="mt-side-list">' . $li . '</' . $tag . '>' . $apres . '</nav>';
  }
}

/* ---------------------------------------------------------------------
   Données de la page
   --------------------------------------------------------------------- */
$mt_sd     = (int) get_the_ID();
$mt_sd_cat = mt_side_cat( $mt_sd );
$mt_sd_nom = $mt_sd_cat ? mb_strtolower( html_entity_decode( (string) $mt_sd_cat->name, ENT_QUOTES, 'UTF-8' ), 'UTF-8' ) : '';
$mt_sd_html = '';

/* Comparatifs publiés (ids), avec un tri ; $cat = 0 pour tout le site ; $clics = tri par le champ de clics (> 0 seulement) */
$mt_sd_req = function ( $cat, $clics, $n ) use ( $mt_sd, $MT_SIDE_CHAMP_CLICS ) {
  $a = array( 'post_type' => 'comparatif', 'post_status' => 'publish', 'posts_per_page' => $n + 1, 'fields' => 'ids', 'no_found_rows' => true, 'post__not_in' => array( $mt_sd ) );
  if ( $cat ) { $a['cat'] = (int) $cat; }
  if ( $clics ) {
    $a['meta_key'] = $MT_SIDE_CHAMP_CLICS; $a['orderby'] = 'meta_value_num'; $a['order'] = 'DESC';
    $a['meta_query'] = array( array( 'key' => $MT_SIDE_CHAMP_CLICS, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) );
  } else {
    $a['orderby'] = 'modified'; $a['order'] = 'DESC';
  }
  return array_slice( array_values( array_diff( array_map( 'intval', (array) get_posts( $a ) ), array( $mt_sd ) ) ), 0, $n );
};
$mt_sd_lien = function ( $id, $m = '' ) { return array( 't' => mt_side_label( get_the_title( $id ) ), 'u' => (string) get_permalink( $id ), 'm' => $m ); };

/* 4. Les plus consultés de la catégorie (vrais clics seulement) */
if ( $MT_SIDE_CONSULTES && $mt_sd_cat ) {
  $mt_sd_l = mt_side_cache( $mt_sd, 'consultes', function () use ( $mt_sd_req, $mt_sd_lien, $mt_sd_cat ) {
    return array_map( $mt_sd_lien, $mt_sd_req( (int) $mt_sd_cat->term_id, true, 5 ) );
  } );
  $mt_sd_html .= mt_side_bloc( 'Les plus consultés en ' . $mt_sd_nom, $mt_sd_l, true );
}

/* 5. Mis à jour récemment dans la catégorie */
if ( $MT_SIDE_RECENTS && $mt_sd_cat ) {
  $mt_sd_l = mt_side_cache( $mt_sd, 'recents', function () use ( $mt_sd_req, $mt_sd_lien, $mt_sd_cat ) {
    return array_map( function ( $id ) use ( $mt_sd_lien ) { return $mt_sd_lien( $id, date_i18n( 'j F Y', (int) get_post_modified_time( 'U', true, $id ) ) ); }, $mt_sd_req( (int) $mt_sd_cat->term_id, false, 5 ) );
  } );
  $mt_sd_html .= mt_side_bloc( 'Mis à jour récemment', $mt_sd_l );
}

/* 6. Les comparatifs populaires du site (vrais clics seulement) */
if ( $MT_SIDE_POPULAIRES ) {
  $mt_sd_l = mt_side_cache( 0, 'populaires', function () use ( $mt_sd_req, $mt_sd_lien ) {
    return array_map( $mt_sd_lien, $mt_sd_req( 0, true, 5 ) );
  } );
  $mt_sd_html .= mt_side_bloc( 'Les comparatifs populaires', $mt_sd_l, true );
}

/* 7. De saison : liste choisie par Samuel (champ d'options), 6 au plus */
if ( $MT_SIDE_SAISON && function_exists( 'get_field' ) ) {
  $mt_sd_s = get_field( $MT_SIDE_CHAMP_SAISON, 'option' );
  $mt_sd_l = array();
  foreach ( array_slice( is_array( $mt_sd_s ) ? $mt_sd_s : array(), 0, 6 ) as $p ) {
    $id = is_object( $p ) ? (int) $p->ID : (int) $p;
    if ( $id && $id !== $mt_sd && get_post_status( $id ) === 'publish' ) { $mt_sd_l[] = $mt_sd_lien( $id ); }
  }
  $mt_sd_html .= mt_side_bloc( 'De saison', $mt_sd_l );
}

/* 8. Dans la même catégorie : comparatifs principaux (sans attribut) des autres types de produit */
if ( $MT_SIDE_CATEGORIE && $mt_sd_cat ) {
  $mt_sd_l = mt_side_cache( $mt_sd, 'categorie', function () use ( $mt_sd, $mt_sd_cat, $mt_sd_lien, $MT_SIDE_MAX ) {
    $prod  = get_the_terms( $mt_sd, 'post-type-produit' );
    $mien  = is_array( $prod ) ? array_map( function ( $t ) { return (int) $t->term_id; }, $prod ) : array();
    $ids   = get_posts( array( 'post_type' => 'comparatif', 'post_status' => 'publish', 'posts_per_page' => 80, 'fields' => 'ids', 'no_found_rows' => true,
                               'cat' => (int) $mt_sd_cat->term_id, 'orderby' => 'title', 'order' => 'ASC' ) );
    $out = array(); $vus = array();
    foreach ( (array) $ids as $id ) {
      $id   = (int) $id;
      if ( $id === $mt_sd || count( $out ) >= $MT_SIDE_MAX ) { continue; }
      $attr = get_the_terms( $id, 'post-type-attribut' );
      if ( is_array( $attr ) && ! empty( $attr ) ) { continue; }  // sous-comparatif : pas un type voisin
      $pt   = get_the_terms( $id, 'post-type-produit' );
      $tid  = is_array( $pt ) && ! empty( $pt ) ? (int) $pt[0]->term_id : 0;
      if ( ! $tid || in_array( $tid, $mien, true ) || isset( $vus[ $tid ] ) ) { continue; }
      $vus[ $tid ] = true;
      $out[] = $mt_sd_lien( $id );
    }
    return $out;
  } );
  $mt_sd_html .= mt_side_bloc( 'Dans la même catégorie', $mt_sd_l );
}

/* 9. Questions fréquentes : 5 questions de la rédaction, lien vers leur réponse dans la FAQ de la page */
if ( $MT_SIDE_FAQ && function_exists( 'mt_faq_read' ) && function_exists( 'mt_faq_ancre' ) ) {
  $rows = mt_faq_read( $mt_sd );
  if ( empty( $rows ) && function_exists( 'mt_guide_cache_id' ) ) {
    $c = mt_guide_cache_id( $mt_sd, 'faq' );
    if ( $c && $c !== $mt_sd ) { $rows = mt_faq_read( $c ); }
  }
  $mt_sd_l = array();
  foreach ( (array) $rows as $r ) {
    if ( count( $mt_sd_l ) >= 5 ) { break; }
    $q = trim( html_entity_decode( wp_strip_all_tags( (string) ( $r['mltv5_faq_comparatif_question'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
    if ( $q === '' || trim( (string) ( $r['mltv5_faq_comparatif_reponse'] ?? '' ) ) === '' ) { continue; }
    $mt_sd_l[] = array( 't' => $q, 'u' => '#' . mt_faq_ancre( $q ) );
  }
  if ( count( $mt_sd_l ) >= 3 ) {
    $mt_sd_html .= mt_side_bloc( 'Questions fréquentes', $mt_sd_l, false, '<p class="mt-side-plus"><a href="#partie-faq">Toutes les questions</a></p>' );
  }
}

if ( $mt_sd_html !== '' ) :
?>
<aside class="mt-side mt-side-droite" aria-label="Découvrir d’autres comparatifs">
<?php echo $mt_sd_html; ?>
</aside>
<?php endif; ?>
