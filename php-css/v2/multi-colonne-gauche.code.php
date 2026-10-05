<?php
/* =====================================================================
   MEILLEURTEST — Partie guide d'achat : COLONNE DE GAUCHE (modèle Game8)
   Demande de Samuel du 2026-10-05 (relayée par la Coordination) : tout
   mettre en place, puis retirer ce qui ne sert pas.

   Où le coller : un élément Code placé JUSTE AVANT le bloc du guide d'achat
   (le bloc qui contient « Guide d'achat », types, duels, marques, astuces,
   « Pourquoi acheter » et la FAQ), dans le même conteneur. Le CSS de cet
   élément transforme ce conteneur en 3 colonnes (aucun réglage Bricks).

   Blocs (titres en <p class="mt-side-h"> : pas de titre HTML, le plan de la
   page reste propre) :
     1. « Accès rapide » : liens vers les parties de la page (les liens dont
        la partie n'existe pas sur la page sont retirés au chargement) ;
     2. « Tous les guides {type} » : comparatifs publiés du même type de
        produit (le principal d'abord), 10 au plus ;
     3. « Les modèles analysés » : produits des avis détaillés, triés par
        note, lien vers leur avis dans la page (#test-…), sinon vers leur
        page produit si l'ID ≥ 250 000, sinon sans lien.
   La colonne entière reste collée en haut de l'écran pendant la lecture du
   guide (hauteur limitée à l'écran, défilement interne si besoin).
   Listes mises en cache 12 h (transients), clé = page + date de modification.
   ===================================================================== */
$MT_SIDE_ACCES   = true;  // « Accès rapide »
$MT_SIDE_GUIDES  = true;  // « Tous les guides {type} »
$MT_SIDE_MODELES = true;  // « Les modèles analysés »
$MT_SIDE_MAX     = 10;    // liens au plus par bloc (sauf « Accès rapide » et « Les modèles analysés »)

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
$mt_sp      = (int) get_the_ID();
$mt_sp_tv   = function_exists( 'get_all_template_variables' ) ? (array) get_all_template_variables( $mt_sp ) : array();
$mt_sp_plur = trim( (string) ( $mt_sp_tv['type_de_produit_au_pluriel'] ?? '' ) );
$mt_sp_html = '';

/* 1. Accès rapide (le script retire les liens dont la partie n'existe pas sur la page) */
if ( $MT_SIDE_ACCES ) {
  $mt_sp_html .= mt_side_bloc( 'Accès rapide', array(
    array( 't' => 'Notre classement',     'u' => '#mt-top5-title' ),
    array( 't' => 'Avis détaillés',       'u' => '#partie-tests-complets' ),
    array( 't' => 'Tableau comparatif',   'u' => '#partie-tableau-comparatif' ),
    array( 't' => 'Guide d’achat',        'u' => '#partie-guide-achat' ),
    array( 't' => 'Les différents types', 'u' => '#partie-types' ),
    array( 't' => 'Quel choix faire ?',   'u' => '#partie-choix' ),
    array( 't' => 'Les marques',          'u' => '#partie-marques' ),
    array( 't' => 'Astuces et conseils',  'u' => '#partie-astuces' ),
    array( 't' => 'Pourquoi acheter',     'u' => '#partie-raisons' ),
    array( 't' => 'Questions fréquentes', 'u' => '#partie-faq' ),
  ) );
}

/* 2. Tous les guides du même type de produit (principal d'abord, puis ordre alphabétique) */
if ( $MT_SIDE_GUIDES ) {
  $mt_sp_guides = mt_side_cache( $mt_sp, 'guides', function () use ( $mt_sp ) {
    $prod = get_the_terms( $mt_sp, 'post-type-produit' );
    $tids = is_array( $prod ) ? array_map( function ( $t ) { return (int) $t->term_id; }, $prod ) : array();
    if ( empty( $tids ) ) { return array(); }
    $ids = get_posts( array(
      'post_type' => 'comparatif', 'post_status' => 'publish', 'posts_per_page' => 60, 'fields' => 'ids', 'no_found_rows' => true,
      'tax_query' => array( array( 'taxonomy' => 'post-type-produit', 'terms' => $tids ) ),
    ) );
    $out = array();
    foreach ( (array) $ids as $id ) {
      $id   = (int) $id;
      $attr = get_the_terms( $id, 'post-type-attribut' );
      $out[] = array( 't' => mt_side_label( get_the_title( $id ) ), 'u' => (string) get_permalink( $id ), 'id' => $id, 'p' => ( is_array( $attr ) && ! empty( $attr ) ) ? 1 : 0 );
    }
    usort( $out, function ( $a, $b ) { return $a['p'] <=> $b['p'] ?: strnatcasecmp( $a['t'], $b['t'] ); } );
    return $out;
  } );
  $mt_sp_plus = count( $mt_sp_guides ) > $MT_SIDE_MAX;
  $mt_sp_list = array_slice( $mt_sp_guides, 0, $MT_SIDE_MAX );
  foreach ( $mt_sp_list as $k => $g ) { $mt_sp_list[ $k ]['cur'] = ( (int) $g['id'] === $mt_sp ); }
  if ( count( $mt_sp_list ) > 1 ) {  // au moins un autre guide que la page elle-même
    $mt_sp_html .= mt_side_bloc( 'Tous les guides ' . ( $mt_sp_plur !== '' ? mb_strtolower( $mt_sp_plur, 'UTF-8' ) : 'du même type' ), $mt_sp_list, false,
      $mt_sp_plus ? '<p class="mt-side-plus"><a href="#partie-guides-similaires">Voir tous les guides</a></p>' : '' );
  }
}

/* 3. Les modèles analysés : produits des avis détaillés, du mieux noté au moins bien noté */
if ( $MT_SIDE_MODELES ) {
  $mt_sp_plan = function_exists( 'mtv2_plan' ) ? mtv2_plan( $mt_sp ) : null;
  $mt_sp_ids  = $mt_sp_plan ? (array) $mt_sp_plan['tests'] : (array) ( $mt_sp_tv['top_avis_ids'] ?? array() );
  $mt_sp_ids  = array_values( array_filter( array_map( 'intval', $mt_sp_ids ) ) );
  $mt_sp_mod  = mt_side_cache( $mt_sp, 'modeles', function () use ( $mt_sp_ids ) {
    $out = array();
    if ( function_exists( 'mt_top_infos' ) ) {
      foreach ( mt_top_infos( $mt_sp_ids, count( $mt_sp_ids ) ) as $p ) { $out[] = array( 'pid' => (int) $p['pid'], 't' => (string) $p['name'], 's' => (float) $p['score'] ); }
    } else {
      foreach ( $mt_sp_ids as $pid ) { $out[] = array( 'pid' => $pid, 't' => html_entity_decode( (string) get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ), 's' => 0.0 ); }
    }
    usort( $out, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
    return $out;
  } );
  $mt_sp_items = array();
  foreach ( $mt_sp_mod as $p ) {
    if ( $p['t'] === '' ) { continue; }
    $u = '';
    if ( $mt_sp_plan && isset( $mt_sp_plan['test_set'][ $p['pid'] ] ) && function_exists( 'mtv2_test_anchor' ) ) { $u = '#' . mtv2_test_anchor( $p['pid'] ); }
    elseif ( $p['pid'] >= 250000 ) { $u = (string) get_permalink( $p['pid'] ); }  // même règle que les fiches (FP_LINK_MIN_ID)
    $mt_sp_items[] = array( 't' => $p['t'], 'u' => $u, 'm' => $p['s'] > 0 ? number_format( $p['s'], 1, ',', '' ) . '/10' : '' );
  }
  $mt_sp_html .= mt_side_bloc( 'Les modèles analysés', $mt_sp_items, true );
}

if ( $mt_sp_html !== '' ) :
?>
<aside class="mt-side mt-side-gauche" aria-label="Se repérer dans le guide">
<?php echo $mt_sp_html; ?>
</aside>
<script>(function(){
  /* Accès rapide : retire les liens vers une partie absente de la page, et souligne la partie en cours de lecture
     (IntersectionObserver, aucune mesure forcée) */
  /* après la lecture de toute la page : le guide et la FAQ viennent APRÈS cette colonne */
  function go(){
    var a=document.querySelector('.mt-side-gauche'); if(!a){return;}
    var liens=[].slice.call(a.querySelectorAll('.mt-side-bloc:first-child a[href^="#"]')), cibles=[];
    liens.forEach(function(l){ var c=document.getElementById(l.getAttribute('href').slice(1)); if(!c){ l.parentNode.remove(); } else { cibles.push([c,l]); } });
    if(!('IntersectionObserver' in window) || !cibles.length){return;}
    var io=new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ cibles.forEach(function(x){ x[1].classList.toggle('mt-side-actif', x[0]===e.target); }); } }); }, { rootMargin: '-20% 0px -70% 0px' });
    cibles.forEach(function(x){ io.observe(x[0]); });
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', go); } else { go(); }
})();</script>
<?php endif; ?>
