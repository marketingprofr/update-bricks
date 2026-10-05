<?php
/* =====================================================================
   MEILLEURTEST — Partie guide d'achat : COLONNE DE GAUCHE (modèle Game8)
   Demande de Samuel du 2026-10-05 (relayée par la Coordination), revue le
   même jour : chaque bloc a sa propre mise en forme.

   Où le coller : un élément Code placé JUSTE AVANT le bloc du guide d'achat
   (le bloc qui contient « Guide d'achat », types, duels, marques, astuces,
   « Pourquoi acheter » et la FAQ), dans le même conteneur. Le CSS de cet
   élément (multi-colonnes.css) transforme ce conteneur en 3 colonnes, pour
   les deux colonnes (aucun réglage Bricks).

   Blocs (titres en <p class="mt-side-h"> : pas de titre HTML, le plan de la
   page reste propre) :
     1. « Accès rapide » (façon Game8 : panneau gris, partie en cours
        assombrie) : liens vers les parties de la page ; les liens dont la
        partie n'existe pas sont retirés au chargement ;
     2. « Guides {type au pluriel} » : simple liste des guides du MÊME type de
        produit (la page en tête), classés par le moteur des « Comparatifs
        similaires » (mt_sim_ranked_ids) ; le reste de la catégorie est dans
        « Autres guides {catégorie} », colonne de droite (Samuel, 2026-10-05) ;
     3. « Les modèles analysés » : les 10 mieux notés des avis détaillés
        (noms en double retirés), lien vers leur avis dans la page (#test-…),
        sinon vers leur page produit si l'ID ≥ 250 000, sinon sans lien.
   La colonne défile avec la page, sans barre de défilement à elle (Samuel,
   2026-10-05 : la colonne collée avec défilement interne n'était pas utile).
   Listes mises en cache 12 h (transients), clé = page + date de modification.
   ===================================================================== */
$MT_SIDE_ACCES     = true;  // « Accès rapide »
$MT_SIDE_GUIDES    = true;  // « Guides {type au pluriel} »
$MT_SIDE_MODELES   = true;  // « Les modèles analysés »
$MT_SIDE_MAX       = 10;    // guides du même type au plus (en plus de la page)
$MT_SIDE_MAX_MOD   = 10;    // modèles analysés au plus

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
  /* Un bloc de liens : titre en <p>, liste ; $items = [ [ 't' => texte, 'u' => lien, 'm' => mention, 'img' => vignette
     (56×56, sinon 'img_w' et 'img_h'), 'cur' => page courante ] ] ; $classe = mise en forme propre au bloc ; liste vide → rien */
  function mt_side_bloc( $titre, $items, $num = false, $apres = '', $classe = '' ) {
    if ( empty( $items ) ) { return ''; }
    $li = '';
    foreach ( $items as $it ) {
      $txt = ( ( $it['img'] ?? '' ) !== '' ? '<img class="mt-side-img" src="' . esc_url( $it['img'] ) . '" alt="" width="' . (int) ( $it['img_w'] ?? 56 ) . '" height="' . (int) ( $it['img_h'] ?? 56 ) . '" loading="lazy" decoding="async">' : '' )
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
  ), false, '', 'mt-side-acces' );
}

/* 2. Guides du même type de produit : même classement que les « Comparatifs similaires » (la page courante en tête) */
if ( $MT_SIDE_GUIDES ) {
  $mt_sp_g = mt_side_cache( $mt_sp, 'guides3', function () use ( $mt_sp, $MT_SIDE_MAX ) {
    $prod = get_the_terms( $mt_sp, 'post-type-produit' );
    $mien = is_array( $prod ) ? array_map( function ( $t ) { return (int) $t->term_id; }, $prod ) : array();
    if ( empty( $mien ) ) { return array(); }
    if ( function_exists( 'mt_sim_ranked_ids' ) ) {
      $ids = mt_sim_ranked_ids( $mt_sp, array( 'max' => $MT_SIDE_MAX + 10 ) );
    } else {
      $ids = get_posts( array( 'post_type' => 'comparatif', 'post_status' => 'publish', 'posts_per_page' => 40, 'fields' => 'ids', 'no_found_rows' => true,
                               'tax_query' => array( array( 'taxonomy' => 'post-type-produit', 'terms' => $mien ) ) ) );
    }
    $lab = function ( $id ) { return function_exists( 'mt_sim_label' ) ? (string) mt_sim_label( $id, '' ) : mt_side_label( get_the_title( $id ) ); };
    $out = array( array( 't' => $lab( $mt_sp ), 'u' => '', 'cur' => true ) );
    foreach ( (array) $ids as $id ) {
      $id = (int) $id;
      if ( $id === $mt_sp || count( $out ) > $MT_SIDE_MAX ) { continue; }
      $pt = get_the_terms( $id, 'post-type-produit' );
      if ( is_array( $pt ) && array_intersect( $mien, array_map( function ( $t ) { return (int) $t->term_id; }, $pt ) ) ) {
        $out[] = array( 't' => $lab( $id ), 'u' => (string) get_permalink( $id ) );
      }
    }
    return count( $out ) > 1 ? $out : array();  // la page seule ne sert à rien
  } );
  $mt_sp_titre = $mt_sp_plur !== '' ? 'Guides ' . mb_strtolower( $mt_sp_plur, 'UTF-8' ) : 'Tous les guides';
  $mt_sp_html .= mt_side_bloc( $mt_sp_titre, $mt_sp_g, false, '', 'mt-side-guides' );
}

/* 3. Les modèles analysés : les 10 mieux notés des avis détaillés, sans nom en double */
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
  $mt_sp_items = array(); $mt_sp_vus = array();
  foreach ( $mt_sp_mod as $p ) {
    $k = mb_strtolower( trim( $p['t'] ), 'UTF-8' );
    if ( $k === '' || isset( $mt_sp_vus[ $k ] ) ) { continue; }  // même produit sous deux fiches : une seule ligne (la mieux notée)
    $mt_sp_vus[ $k ] = true;
    $u = '';
    if ( $mt_sp_plan && isset( $mt_sp_plan['test_set'][ $p['pid'] ] ) && function_exists( 'mtv2_test_anchor' ) ) { $u = '#' . mtv2_test_anchor( $p['pid'] ); }
    elseif ( $p['pid'] >= 250000 ) { $u = (string) get_permalink( $p['pid'] ); }  // même règle que les fiches (FP_LINK_MIN_ID)
    $mt_sp_items[] = array( 't' => $p['t'], 'u' => $u, 'm' => $p['s'] > 0 ? number_format( $p['s'], 1, ',', '' ) : '' );
    if ( count( $mt_sp_items ) >= $MT_SIDE_MAX_MOD ) { break; }
  }
  $mt_sp_html .= mt_side_bloc( 'Les modèles analysés', $mt_sp_items, true, '', 'mt-side-modeles' );
}

if ( $mt_sp_html !== '' ) :
?>
<aside class="mt-side mt-side-gauche" aria-label="Se repérer dans le guide">
<?php echo $mt_sp_html; ?>
</aside>
<script>(function(){
  /* Accès rapide : retire les liens vers une partie absente de la page, et assombrit la partie en cours de lecture
     (IntersectionObserver, aucune mesure forcée) ; lancé après la lecture de toute la page, car le guide et la FAQ
     viennent APRÈS cette colonne */
  function go(){
    var a=document.querySelector('.mt-side-gauche .mt-side-acces'); if(!a){return;}
    var liens=[].slice.call(a.querySelectorAll('a[href^="#"]')), cibles=[];
    liens.forEach(function(l){ var c=document.getElementById(l.getAttribute('href').slice(1)); if(!c){ l.parentNode.remove(); } else { cibles.push([c,l]); } });
    if(!('IntersectionObserver' in window) || !cibles.length){return;}
    var io=new IntersectionObserver(function(es){ es.forEach(function(e){ if(e.isIntersecting){ cibles.forEach(function(x){ x[1].classList.toggle('mt-side-actif', x[0]===e.target); }); } }); }, { rootMargin: '-20% 0px -70% 0px' });
    cibles.forEach(function(x){ io.observe(x[0]); });
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', go); } else { go(); }
})();</script>
<?php endif; ?>
