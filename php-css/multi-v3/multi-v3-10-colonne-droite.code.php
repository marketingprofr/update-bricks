<?php
/* =====================================================================
   MEILLEURTEST — Partie guide d'achat : COLONNE DE DROITE (modèle Game8)
   Demande de Samuel du 2026-10-05 (relayée par la Coordination), revue le
   même jour : chaque bloc a sa propre mise en forme, et tout reste dans la
   thématique (le site fonctionne en entonnoirs thématiques).

   Où le coller : un élément Code placé JUSTE APRÈS le bloc du guide d'achat,
   dans le même conteneur que la colonne de gauche (multi-colonne-gauche).
   Onglet CSS VIDE : le CSS des deux colonnes (multi-colonnes.css) est collé
   une seule fois, dans l'élément de la colonne de gauche.
   Entre 992 et 1199 px, cette colonne passe sous le guide ; masquée sur mobile.

   Blocs (titres en <p class="mt-side-h">, pas de titre HTML) ; un bloc sans
   données ne s'affiche pas :
     4. « Les plus consultés en {catégorie} » : les 5 comparatifs de la
        catégorie qui ont le plus de clics Google sur 28 jours, numérotés.
        Source (contrat avec l'Architecture, 2026-10-05) : page privée de
        slug « mt-clics-28j » dont le contenu est un JSON {"id": clics},
        mise à jour chaque semaine. Page absente, vide ou JSON illisible :
        bloc masqué, jamais de classement partiel ;
     5. « Mis à jour récemment » : les 5 derniers comparatifs modifiés de la
        catégorie (le 1er avec une grande image, les autres en vignette) ;
     8. « Dans la même catégorie » : comparatifs principaux des autres types
        de produit de la catégorie, avec une petite image ;
     9. « Questions fréquentes » : 5 questions de la rédaction, avec un lien
        vers leur réponse dans la FAQ de la page.
   Honnêteté : « plus consultés » seulement avec de vrais clics, jamais de
   compteur inventé. Listes en cache 12 h (transients).
   ===================================================================== */
$MT_SIDE_CONSULTES = true;           // 4. « Les plus consultés en {catégorie} »
$MT_SIDE_RECENTS   = true;           // 5. « Mis à jour récemment »
$MT_SIDE_CATEGORIE = true;           // 8. « Dans la même catégorie »
$MT_SIDE_FAQ       = true;           // 9. « Questions fréquentes »
$MT_SIDE_PAGE_CLICS = 'mt-clics-28j'; // slug de la page privée des clics Google (JSON {"id du comparatif": clics sur 28 jours})
$MT_SIDE_MAX       = 10;             // liens au plus par bloc

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
  /* Un bloc de liens : titre en <p>, liste ; $items = [ [ 't' => texte, 'u' => lien, 'm' => mention, 'img' => vignette,
     'cur' => page courante ] ] ; $classe = mise en forme propre au bloc ; liste vide → rien */
  function mt_side_bloc( $titre, $items, $num = false, $apres = '', $classe = '' ) {
    if ( empty( $items ) ) { return ''; }
    $li = '';
    foreach ( $items as $it ) {
      $txt = ( ( $it['img'] ?? '' ) !== '' ? '<img class="mt-side-img" src="' . esc_url( $it['img'] ) . '" alt="" width="56" height="56" loading="lazy" decoding="async">' : '' )
           . '<span class="mt-side-t">' . str_replace( ' ?', "\u{00A0}?", esc_html( $it['t'] ) ) . '</span>'
           . ( ( $it['m'] ?? '' ) !== '' ? '<span class="mt-side-m">' . esc_html( $it['m'] ) . '</span>' : '' );
      if ( ( $it['u'] ?? '' ) !== '' && empty( $it['cur'] ) ) { $li .= '<li><a href="' . esc_url( $it['u'] ) . '">' . $txt . '</a></li>'; }
      else { $li .= '<li' . ( ! empty( $it['cur'] ) ? ' class="mt-side-cur" aria-current="page"' : '' ) . '><span class="mt-side-sans">' . $txt . '</span></li>'; }
    }
    $tag = $num ? 'ol' : 'ul';
    return '<nav class="mt-side-bloc' . ( $classe !== '' ? ' ' . esc_attr( $classe ) : '' ) . '" aria-label="' . esc_attr( $titre ) . '"><p class="mt-side-h">' . esc_html( $titre ) . '</p>'
         . '<' . $tag . ' class="mt-side-list">' . $li . '</' . $tag . '>' . $apres . '</nav>';
  }
}

/* ---------------------------------------------------------------------
   Données de la page
   --------------------------------------------------------------------- */
$mt_sd      = (int) get_the_ID();
$mt_sd_cat  = mt_side_cat( $mt_sd );
$mt_sd_nom  = $mt_sd_cat ? mb_strtolower( html_entity_decode( (string) $mt_sd_cat->name, ENT_QUOTES, 'UTF-8' ), 'UTF-8' ) : '';
$mt_sd_html = '';
$mt_sd_img  = function ( $id, $taille ) { $u = function_exists( 'get_the_post_thumbnail_url' ) ? get_the_post_thumbnail_url( $id, $taille ) : ''; return $u ? (string) $u : ''; };
$mt_sd_lien = function ( $id, $m = '', $img = '' ) { return array( 't' => mt_side_label( get_the_title( $id ) ), 'u' => (string) get_permalink( $id ), 'm' => $m, 'img' => $img ); };

/* 4. Les plus consultés de la catégorie : vrais clics Google sur 28 jours (page privée mt-clics-28j), sinon rien */
if ( $MT_SIDE_CONSULTES && $mt_sd_cat ) {
  $mt_sd_l = mt_side_cache( $mt_sd, 'consultes', function () use ( $mt_sd, $mt_sd_cat, $mt_sd_lien, $MT_SIDE_PAGE_CLICS ) {
    $pg = function_exists( 'get_page_by_path' ) ? get_page_by_path( $MT_SIDE_PAGE_CLICS, OBJECT, 'page' ) : null;
    $clics = $pg ? json_decode( trim( (string) $pg->post_content ), true ) : null;
    if ( ! is_array( $clics ) || empty( $clics ) ) { return array(); }  // données absentes ou illisibles : bloc masqué
    $ids = get_posts( array( 'post_type' => 'comparatif', 'post_status' => 'publish', 'posts_per_page' => 400, 'fields' => 'ids', 'no_found_rows' => true, 'cat' => (int) $mt_sd_cat->term_id ) );
    $l = array();
    foreach ( (array) $ids as $id ) { $id = (int) $id; $c = (int) ( $clics[ (string) $id ] ?? 0 ); if ( $id !== $mt_sd && $c > 0 ) { $l[ $id ] = $c; } }
    arsort( $l );
    return array_map( $mt_sd_lien, array_slice( array_keys( $l ), 0, 5 ) );
  } );
  $mt_sd_html .= mt_side_bloc( 'Les plus consultés en ' . $mt_sd_nom, $mt_sd_l, true, '', 'mt-side-top' );
}

/* 5. Mis à jour récemment dans la catégorie : le 1er avec une grande image, les autres en vignette */
if ( $MT_SIDE_RECENTS && $mt_sd_cat ) {
  $mt_sd_l = mt_side_cache( $mt_sd, 'recents3', function () use ( $mt_sd, $mt_sd_cat, $mt_sd_lien, $mt_sd_img ) {
    $ids = get_posts( array( 'post_type' => 'comparatif', 'post_status' => 'publish', 'posts_per_page' => 6, 'fields' => 'ids', 'no_found_rows' => true,
                             'cat' => (int) $mt_sd_cat->term_id, 'orderby' => 'modified', 'order' => 'DESC', 'post__not_in' => array( $mt_sd ) ) );
    $ids = array_slice( array_values( array_diff( array_map( 'intval', (array) $ids ), array( $mt_sd ) ) ), 0, 5 );
    $l = array();
    foreach ( $ids as $i => $id ) {
      $l[] = $mt_sd_lien( $id, date_i18n( 'j F Y', (int) get_post_modified_time( 'U', true, $id ) ), $mt_sd_img( $id, $i === 0 ? 'medium' : 'thumbnail' ) );
    }
    return $l;
  } );
  $mt_sd_html .= mt_side_bloc( 'Mis à jour récemment', $mt_sd_l, false, '', 'mt-side-recents' );
}

/* 8. Dans la même catégorie : comparatifs principaux (sans attribut) des autres types de produit, avec une petite image */
if ( $MT_SIDE_CATEGORIE && $mt_sd_cat ) {
  $mt_sd_l = mt_side_cache( $mt_sd, 'categorie2', function () use ( $mt_sd, $mt_sd_cat, $mt_sd_lien, $mt_sd_img, $MT_SIDE_MAX ) {
    $prod = get_the_terms( $mt_sd, 'post-type-produit' );
    $mien = is_array( $prod ) ? array_map( function ( $t ) { return (int) $t->term_id; }, $prod ) : array();
    $ids  = get_posts( array( 'post_type' => 'comparatif', 'post_status' => 'publish', 'posts_per_page' => 80, 'fields' => 'ids', 'no_found_rows' => true,
                              'cat' => (int) $mt_sd_cat->term_id, 'orderby' => 'title', 'order' => 'ASC' ) );
    $out = array(); $vus = array();
    foreach ( (array) $ids as $id ) {
      $id = (int) $id;
      if ( $id === $mt_sd || count( $out ) >= $MT_SIDE_MAX ) { continue; }
      $attr = get_the_terms( $id, 'post-type-attribut' );
      if ( is_array( $attr ) && ! empty( $attr ) ) { continue; }  // sous-comparatif : pas un type voisin
      $pt   = get_the_terms( $id, 'post-type-produit' );
      $tid  = is_array( $pt ) && ! empty( $pt ) ? (int) $pt[0]->term_id : 0;
      if ( ! $tid || in_array( $tid, $mien, true ) || isset( $vus[ $tid ] ) ) { continue; }
      $vus[ $tid ] = true;
      $out[] = $mt_sd_lien( $id, '', $mt_sd_img( $id, 'thumbnail' ) );
    }
    return $out;
  } );
  $mt_sd_html .= mt_side_bloc( 'Dans la même catégorie', $mt_sd_l, false, '', 'mt-side-cat' );
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
    $mt_sd_html .= mt_side_bloc( 'Questions fréquentes', $mt_sd_l, false, '<p class="mt-side-plus"><a href="#partie-faq">Toutes les questions</a></p>', 'mt-side-faq' );
  }
}

if ( $mt_sd_html !== '' ) :
?>
<aside class="mt-side mt-side-droite" aria-label="Découvrir d’autres comparatifs">
<?php echo $mt_sd_html; ?>
</aside>
<?php endif; ?>
