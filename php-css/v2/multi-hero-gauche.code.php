<?php
/* =====================================================================
   MEILLEURTEST — MULTI-COMPARATIF (V2) : hero gauche (ariane, titre H1,
   byline, chapô…). Remplace hero-gauche.code.php dans le template Bricks
   multi-comparatif. CSS : v2/multi-hero-gauche.css (copie complète de
   hero-gauche.code.css, inchangée).

   Identique au V1, sauf le titre H1 d'un multi-comparatif :
   « Les 5 meilleurs climatiseurs mobiles en 2026 : Guide ultime
   (N produits comparés) », N = nombre de produits différents
   des encarts résumé (principal + sous-comparatifs, sans doublon).
   Titre SEO Rank Math d'un multi-comparatif : « Meilleur climatiseur mobile
   2026 (N produits comparés) ».
   Sans sous-comparatif : H1 et titre SEO V1 inchangés.
   Moteur multi-comparatif inclus dans ce bloc (aucun snippet WPCodeBox).
   ===================================================================== */
$MT_SHOW_QUICK_PICKS = false;
$MT_SHOW_BOLD_INTRO  = false;
$MT_SHOW_INTRO_RECO  = true;

$this_id   = get_the_ID();
extract(get_all_template_variables($this_id));
$post_type = get_post_type($this_id);
$total_avis = !empty($top_avis_ids) ? count($top_avis_ids) : 0;
$mod = date_i18n('j F Y', get_the_modified_time('U'));

/* =====================================================================
   MOTEUR MULTI-COMPARATIF — copie IDENTIQUE dans les 4 blocs multi-*
   (multi-resume, multi-tests, multi-tableau, multi-sommaire).
   Chaque fonction est protégée par function_exists : le 1er bloc exécuté
   la définit, les suivants la réutilisent (comme les helpers mt5_*).
   ⚠️ Si on modifie ce moteur, recoller les 4 blocs.

   Lit le champ ACF mltv5_sous_comparatifs (Relation, sur le guide
   principal) : vide = page identique au V1. Pour chaque sous-comparatif
   (vrai comparatif, privé ou publié) : ses produits (top_avis_ids via
   get_all_template_variables), son titre H2, son ancre, son intro normale.
   Puis construit la liste des tests complets SANS DOUBLON (ordre de 1re
   apparition : principal puis sous-comparatifs, limitée à 30).
   ===================================================================== */
/* Version du moteur. Si une ANCIENNE copie (ancien snippet WPCodeBox
   mtv2-core, ou un bloc multi-* pas recollé) a déjà été chargée, ses
   fonctions gagnent (function_exists) : on le détecte ici pour prévenir
   l'éditeur (message rouge en haut des blocs, éditeurs connectés seulement). */
if ( function_exists( 'mtv2_plan' ) && ( ! function_exists( 'mtv2_engine_version' ) || mtv2_engine_version() !== '2026-09-25b' ) ) {
  $GLOBALS['mtv2_stale_engine'] = true;
}
if ( ! function_exists( 'mtv2_engine_version' ) ) {
  function mtv2_engine_version() { return '2026-09-25b'; }
}

/* ---------------------------------------------------------------------
   Réglages
   --------------------------------------------------------------------- */
if ( ! defined( 'MTV2_MAX_TESTS' ) ) {
  define( 'MTV2_MAX_TESTS', 30 );    // nb max de tests complets (et de colonnes du tableau)
}
if ( ! defined( 'MTV2_FIELD_SUBS' ) ) {
  define( 'MTV2_FIELD_SUBS', 'mltv5_sous_comparatifs' );
}
if ( ! defined( 'MTV2_POST_TYPE' ) ) {
  define( 'MTV2_POST_TYPE', 'comparatif' );
}

/* ---------------------------------------------------------------------
   Fonctions utilitaires
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mtv2_tv' ) ) {
  /* Variables de template d'un comparatif (cache statique par ID). */
  function mtv2_tv( $cid ) {
    static $cache = array();
    $cid = (int) $cid;
    if ( ! isset( $cache[ $cid ] ) ) {
      $tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $cid ) : array();
      $cache[ $cid ] = is_array( $tv ) ? $tv : array();
    }
    return $cache[ $cid ];
  }
}

if ( ! function_exists( 'mtv2_guide_ids' ) ) {
  /* Liste ordonnée des produits d'un comparatif : même sourcing que la V1
     (top_avis_ids, repli mltv5_best_products). */
  function mtv2_guide_ids( $cid ) {
    $tv  = mtv2_tv( $cid );
    $ids = isset( $tv['top_avis_ids'] ) && is_array( $tv['top_avis_ids'] ) ? $tv['top_avis_ids'] : array();
    if ( empty( $ids ) && function_exists( 'get_field' ) ) {
      $rel = get_field( 'mltv5_best_products', $cid );
      if ( is_array( $rel ) ) {
        foreach ( $rel as $r ) { $ids[] = is_object( $r ) ? $r->ID : (int) $r; }
      }
    }
    return array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
  }
}

if ( ! function_exists( 'mtv2_terms' ) ) {
  /* Termes d'une taxonomie -> [ term_id => nom ]. */
  function mtv2_terms( $pid, $tax ) {
    $out   = array();
    $terms = get_the_terms( $pid, $tax );
    if ( is_array( $terms ) ) {
      foreach ( $terms as $t ) { $out[ (int) $t->term_id ] = (string) $t->name; }
    }
    return $out;
  }
}

if ( ! function_exists( 'mtv2_sub_ids_raw' ) ) {
  /* IDs bruts du champ Relation (meta brute : pas de formatage ACF). */
  function mtv2_sub_ids_raw( $page_id ) {
    $raw = get_post_meta( (int) $page_id, MTV2_FIELD_SUBS, true );
    if ( is_string( $raw ) && $raw !== '' ) { $raw = maybe_unserialize( $raw ); }
    if ( ! is_array( $raw ) ) { return array(); }
    return array_values( array_unique( array_filter( array_map( 'intval', $raw ) ) ) );
  }
}

if ( ! function_exists( 'mtv2_anchor' ) ) {
  /* Ancre d'un sous-comparatif : slug du sous-comparatif MOINS celui du
     principal (comparatif-climatiseur-mobile-9000-btu -> 9000-btu).
     Replis : attributs propres, puis slug complet. */
  function mtv2_anchor( $sub_slug, $parent_slug, $extra_names ) {
    $a = '';
    if ( $parent_slug !== '' && strpos( $sub_slug, $parent_slug . '-' ) === 0 ) {
      $a = substr( $sub_slug, strlen( $parent_slug ) + 1 );
    }
    if ( $a === '' && ! empty( $extra_names ) ) { $a = implode( '-', $extra_names ); }
    if ( $a === '' ) { $a = preg_replace( '/^comparatif-/', '', $sub_slug ); }
    $a = sanitize_title( $a );
    /* Jamais en collision avec les ancres existantes de la page */
    if ( $a === '' || preg_match( '/^(mt-|partie-|produit-n-|test-|ed-a|tc-)/', $a ) || $a === 'methodologie' ) {
      $a = 'selection-' . $a;
    }
    /* Un id HTML ne peut pas commencer par un chiffre en CSS, mais c'est valide
       en HTML5 et pour les ancres (#9000-btu) : on le garde tel quel. */
    return $a;
  }
}

if ( ! function_exists( 'mtv2_test_anchor' ) ) {
  /* Ancre du test complet d'un produit : test-{slug du post avis}. */
  function mtv2_test_anchor( $pid ) {
    $slug = (string) get_post_field( 'post_name', (int) $pid );
    return 'test-' . ( $slug !== '' ? sanitize_title( $slug ) : (int) $pid );
  }
}

if ( ! function_exists( 'mtv2_paragraphs' ) ) {
  /* HTML d'intro -> tableau de contenus de paragraphes (HTML inline conservé). */
  function mtv2_paragraphs( $html ) {
    $html = trim( (string) $html );
    if ( $html === '' ) { return array(); }
    if ( ! preg_match( '/<p[\s>]/i', $html ) ) { $html = wpautop( $html ); }
    preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $html, $m );
    $out = array();
    foreach ( $m[1] as $p ) {
      $p = trim( $p );
      if ( trim( wp_strip_all_tags( $p ) ) !== '' ) { $out[] = $p; }
    }
    return ! empty( $out ) ? $out : array( $html );
  }
}

if ( ! function_exists( 'mtv2_intro_html' ) ) {
  /* Intro d'un sous-comparatif = son intro NORMALE (`introduction`) :
     1re phrase visible, le reste dans un <details> « Lire la suite »
     (natif, sans JS, contenu indexable). */
  function mtv2_intro_html( $sub_id ) {
    $tv    = mtv2_tv( $sub_id );
    $paras = mtv2_paragraphs( isset( $tv['introduction'] ) ? $tv['introduction'] : '' );
    if ( empty( $paras ) ) { return ''; }

    /* Les 2 premières phrases restent visibles (texte brut, sur 1 ou 2
       paragraphes) ; la suite est repliée derrière « Lire la suite ». */
    $lead = array();
    $rest = array();
    $need = 2;
    foreach ( $paras as $p ) {
      if ( $need <= 0 ) { $rest[] = wp_kses_post( $p ); continue; }
      $txt   = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $p ) ) );
      $raw   = preg_split( '/(?<=[.!?…])\s+(?=\S)/u', $txt );
      /* Recolle à la phrase suivante un fragment coupé après une abréviation
         (« env. », « M. », « cf. », « n°. »…) : ce n'est pas une fin de phrase. */
      $parts = array();
      $buf   = '';
      foreach ( $raw as $r ) {
        $buf = $buf === '' ? $r : $buf . ' ' . $r;
        if ( ! preg_match( '/(?:^|\s)(?:env|M|Mme|Mlle|Dr|cf|ex|p|n°|no|approx|réf|vol|max|min)\.$/iu', $buf ) ) { $parts[] = $buf; $buf = ''; }
      }
      if ( $buf !== '' ) { $parts[] = $buf; }
      $take  = array_slice( $parts, 0, $need );
      $lead  = array_merge( $lead, $take );
      $need -= count( $take );
      $left  = array_slice( $parts, count( $take ) );
      if ( ! empty( $left ) ) { $rest[] = esc_html( implode( ' ', $left ) ); }
    }

    $has_more = ! empty( $rest );
    $out = '<div class="mtv2-intro' . ( $has_more ? ' has-more' : '' ) . '"><p class="mtv2-intro-lead">' . esc_html( implode( ' ', $lead ) ) . '</p>';
    if ( $has_more ) {
      $out .= '<details class="mtv2-intro-more"><summary>Lire la suite</summary>'
        . '<div class="mtv2-intro-rest"><p>' . implode( '</p><p>', $rest ) . '</p></div></details>';
    }
    return $out . '</div>';
  }
}

/* ---------------------------------------------------------------------
   Le plan de la page
   ---------------------------------------------------------------------
   Retourne :
   - is_multi   : au moins 1 sous-comparatif valide
   - main       : [ ids, attr_names, title, anchor, label ]
   - subs[]     : [ id, ids, attr_names, extra, title, label, anchor, intro ]
   - tests      : IDs des produits à tester, SANS doublon, dans l'ordre de
                  1re apparition (principal puis sous-comparatifs), coupé à
                  MTV2_MAX_TESTS
   - origin     : [ pid => 'main' | index du sous-comparatif ] (1re apparition)
   - seen_in    : [ pid => [ [ enc => 'main'|index, rank => n ], ... ] ]
   - warnings[] : messages pour l'admin
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mtv2_plan' ) ) {
  function mtv2_plan( $page_id = 0 ) {
    static $cache = array();
    $page_id = (int) ( $page_id ? $page_id : get_the_ID() );
    if ( isset( $cache[ $page_id ] ) ) { return $cache[ $page_id ]; }

    $ptv         = mtv2_tv( $page_id );
    $parent_slug = (string) get_post_field( 'post_name', $page_id );
    $parent_prod = mtv2_terms( $page_id, 'post-type-produit' );
    $parent_attr = mtv2_terms( $page_id, 'post-type-attribut' );
    $type_plur   = isset( $ptv['type_de_produit_au_pluriel'] ) ? trim( (string) $ptv['type_de_produit_au_pluriel'] ) : '';
    $mf          = isset( $ptv['masculinsfeminins'] ) ? trim( (string) $ptv['masculinsfeminins'] ) : '';
    if ( $mf === '' ) { $mf = 'meilleurs'; }

    $plan = array(
      'page_id'  => $page_id,
      'is_multi' => false,
      'main'     => array(
        'ids'        => mtv2_guide_ids( $page_id ),
        'attr_names' => array_values( $parent_attr ),
        'title'      => 'Les ' . lcfirst( $mf ) . ( $type_plur !== '' ? ' ' . $type_plur : '' ),
        'label'      => 'Notre sélection',
        'anchor'     => 'mt-top5-title',
      ),
      'subs'     => array(),
      'tests'    => array(),
      'origin'   => array(),
      'seen_in'  => array(),
      'warnings' => array(),
    );

    $can_check_cache = function_exists( 'get_all_template_variables' );
    $used_anchors    = array();

    foreach ( mtv2_sub_ids_raw( $page_id ) as $sid ) {
      $sp    = get_post( $sid );
      $title = $sp ? $sp->post_title : '#' . $sid; // pas get_the_title() : il préfixe « Privé : »
      if ( ! $sp || $sid === $page_id || $sp->post_type !== MTV2_POST_TYPE ) {
        $plan['warnings'][] = 'Sous-comparatif ignoré (introuvable, pas un comparatif, ou la page elle-même) : ' . $title;
        continue;
      }
      if ( ! in_array( $sp->post_status, array( 'private', 'publish' ), true ) ) {
        $plan['warnings'][] = '« ' . $title . ' » est en statut « ' . $sp->post_status . ' » : ignoré. Un sous-comparatif doit être privé (ou publié).';
        continue;
      }

      /* Même type de produit que le principal ? */
      $sub_prod = mtv2_terms( $sid, 'post-type-produit' );
      $k1 = array_keys( $sub_prod );    sort( $k1 );
      $k2 = array_keys( $parent_prod ); sort( $k2 );
      if ( $k1 !== $k2 ) {
        $plan['warnings'][] = '« ' . $title . ' » n\'a pas le même type de produit (' . implode( ', ', $sub_prod ) . ') que cette page (' . implode( ', ', $parent_prod ) . ').';
      }

      $ids = mtv2_guide_ids( $sid );
      if ( empty( $ids ) ) {
        /* En admin, get_all_template_variables() peut ne pas être chargée :
           pas d'alerte dans ce cas (faux positif). */
        if ( $can_check_cache ) {
          $plan['warnings'][] = '« ' . $title . ' » n\'a aucun produit (cache top_avis_ids pas encore calculé pour ce comparatif ? s\'il est privé, vérifier que le batch traite les privés) : ignoré.';
        }
        continue;
      }

      $stv       = mtv2_tv( $sid );
      $sub_attr  = mtv2_terms( $sid, 'post-type-attribut' );
      $extra     = array_values( array_diff_key( $sub_attr, $parent_attr ) );
      if ( empty( $extra ) ) {
        $plan['warnings'][] = '« ' . $title . ' » n\'a aucun attribut en plus de ceux de cette page : libellé court et ancre de repli utilisés.';
      }

      /* Titre H2 : titre forcé du sous-comparatif s'il existe, sinon son titre d'article */
      $forced = isset( $stv['forcer_affichage_du_titre'] ) ? trim( (string) $stv['forcer_affichage_du_titre'] ) : '';
      if ( $forced === '' && function_exists( 'get_field' ) ) {
        $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $sid ) );
      }
      $h2 = $forced !== '' ? $forced : $title;
      /* Libellé court (sommaire, pastilles) : ses attributs propres, ex. « Réversible » */
      $label = ! empty( $extra ) ? implode( ', ', $extra ) : $h2;

      /* Ancre unique */
      $anchor = mtv2_anchor( (string) $sp->post_name, $parent_slug, $extra );
      $base   = $anchor; $n = 2;
      while ( isset( $used_anchors[ $anchor ] ) ) { $anchor = $base . '-' . $n; $n++; }
      $used_anchors[ $anchor ] = true;

      $plan['subs'][] = array(
        'id'         => $sid,
        'ids'        => $ids,
        'attr_names' => array_values( $sub_attr ),
        'extra'      => $extra,
        'title'      => $h2,
        'label'      => $label,
        'anchor'     => $anchor,
        'intro'      => mtv2_intro_html( $sid ),
      );
    }
    $plan['is_multi'] = ! empty( $plan['subs'] );

    /* Précharge posts + métas + termes de tous les produits en 1 passe */
    $all = $plan['main']['ids'];
    foreach ( $plan['subs'] as $sub ) { $all = array_merge( $all, $sub['ids'] ); }
    $all = array_values( array_unique( $all ) );
    if ( ! empty( $all ) && function_exists( '_prime_post_caches' ) ) {
      _prime_post_caches( $all, true, true );
    }

    /* Sécurité anti-doublon : deux fiches avis différentes avec le MÊME ASIN
       sont le même produit -> un seul test (celui de la 1re apparition) ;
       l'autre ID devient un alias qui pointe vers ce test. */
    $plan['alias'] = array();
    $asin_owner    = array();
    $canon = function ( $pid ) use ( &$plan, &$asin_owner ) {
      if ( isset( $plan['alias'][ $pid ] ) ) { return $plan['alias'][ $pid ]; }
      $asin = strtoupper( trim( (string) get_post_meta( $pid, 'mltv5_asin_amazon', true ) ) );
      if ( $asin === '' ) { return $pid; }
      if ( ! isset( $asin_owner[ $asin ] ) ) { $asin_owner[ $asin ] = $pid; return $pid; }
      if ( $asin_owner[ $asin ] !== $pid ) { $plan['alias'][ $pid ] = $asin_owner[ $asin ]; }
      return $asin_owner[ $asin ];
    };

    /* Liste des tests : principal puis sous-comparatifs, sans doublon */
    $push = function ( $pid, $enc, $rank ) use ( &$plan, $canon ) {
      $pid = $canon( $pid );
      if ( isset( $plan['seen_in'][ $pid ] ) ) {
        foreach ( $plan['seen_in'][ $pid ] as $ap ) { if ( $ap['enc'] === $enc ) { return; } }
      }
      $plan['seen_in'][ $pid ][] = array( 'enc' => $enc, 'rank' => $rank );
      if ( ! isset( $plan['origin'][ $pid ] ) ) {
        $plan['origin'][ $pid ] = $enc;
        if ( count( $plan['tests'] ) < MTV2_MAX_TESTS ) { $plan['tests'][] = $pid; }
      }
    };
    foreach ( $plan['main']['ids'] as $i => $pid ) { $push( $pid, 'main', $i + 1 ); }
    foreach ( $plan['subs'] as $si => $sub ) {
      foreach ( $sub['ids'] as $i => $pid ) { $push( $pid, $si, $i + 1 ); }
    }
    $plan['tests']    = array_values( array_unique( $plan['tests'] ) ); // ceinture + bretelles
    $plan['test_set'] = array_flip( $plan['tests'] );

    $cache[ $page_id ] = $plan;
    return $plan;
  }
}

if ( ! function_exists( 'mtv2_product_href' ) ) {
  /* Lien d'un produit dans un encart : son test complet sur la page s'il y
     figure (#test-slug), sinon sa page d'avis (au-delà de MTV2_MAX_TESTS). */
  function mtv2_product_href( $pid, $plan ) {
    if ( isset( $plan['alias'][ $pid ] ) ) { $pid = $plan['alias'][ $pid ]; }
    if ( isset( $plan['test_set'][ $pid ] ) ) { return '#' . mtv2_test_anchor( $pid ); }
    $u = get_permalink( $pid );
    return $u ? $u : '#' . mtv2_test_anchor( $pid );
  }
}

if ( ! function_exists( 'mtv2_encart_label' ) ) {
  /* Libellé court d'un encart ('main' ou index de sous-comparatif). */
  function mtv2_encart_label( $enc, $plan ) {
    if ( $enc === 'main' ) { return $plan['main']['label']; }
    return isset( $plan['subs'][ $enc ] ) ? $plan['subs'][ $enc ]['label'] : '';
  }
}

if ( ! function_exists( 'mtv2_admin_panel' ) ) {
  /* Panneau de contrôle (admin/éditeur uniquement) : jamais servi au public. */
  function mtv2_admin_panel( $plan ) {
    if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) { return ''; }
    if ( ! $plan['is_multi'] && empty( $plan['warnings'] ) ) { return ''; }
    $total = 0;
    foreach ( $plan['seen_in'] as $apps ) { $total += count( $apps ); }
    $out  = '<div class="mtv2-admin" role="note">';
    $out .= '<p><b>Multi-comparatif</b> &middot; ' . count( $plan['subs'] ) . ' sous-comparatif(s) &middot; '
      . count( $plan['tests'] ) . ' test(s) complet(s) &middot; ' . max( 0, $total - count( $plan['origin'] ) ) . ' doublon(s) &eacute;vit&eacute;(s)'
      . ( ! empty( $plan['alias'] ) ? ' &middot; ' . count( $plan['alias'] ) . ' fiche(s) avis en double (m&ecirc;me ASIN) regroup&eacute;e(s)' : '' )
      . ( count( $plan['origin'] ) > count( $plan['tests'] ) ? ' &middot; ' . ( count( $plan['origin'] ) - count( $plan['tests'] ) ) . ' produit(s) au-del&agrave; de la limite (' . (int) MTV2_MAX_TESTS . ')' : '' )
      . '</p>';
    if ( ! empty( $plan['subs'] ) ) {
      $out .= '<p>Ancres : ';
      $a = array();
      foreach ( $plan['subs'] as $s ) { $a[] = '<a href="#' . esc_attr( $s['anchor'] ) . '">#' . esc_html( $s['anchor'] ) . '</a>'; }
      $out .= implode( ' &middot; ', $a ) . '</p>';
    }
    foreach ( $plan['warnings'] as $w ) { $out .= '<p class="mtv2-admin-warn">&#9888; ' . esc_html( $w ) . '</p>'; }
    return $out . '</div>';
  }
}

if ( ! function_exists( 'mt_intro_reco' ) ) {
  function mt_intro_reco( $page_id, $ids, $type_plur, $type_sing = '', $llm = '' ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( count( $ids ) < 2 ) { return ''; }

    $year = (int) date( 'Y' );

    $prods = array();
    foreach ( $ids as $i => $pid ) {
      $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
      $brand  = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model  = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $forced !== '' ) { $name = $forced; }
      elseif ( $model !== '' ) { $name = trim( $brand . ' ' . $model ); }
      else { $name = (string) get_the_title( $pid ); }
      $raw  = get_field( 'mltv5_prix_indicatif', $pid );
      $c    = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $raw );
      $c    = str_replace( ',', '.', $c );
      $asin = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
      $url  = '';
      if ( $asin !== '' ) {
        $url = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=mlt00-21';
      } else {
        for ( $li = 1; $li <= 3; $li++ ) {
          $lu = trim( (string) get_field( 'mltv5_lien_du_produit_' . $li, $pid ) );
          if ( $lu !== '' && strpos( $lu, 'http' ) === 0 ) { $url = $lu; break; }
        }
      }
      $prods[] = array(
        'rank'   => $i + 1,
        'name'   => $name,
        'price'  => is_numeric( $c ) ? (float) $c : 0.0,
        'asin'   => $asin,
        'url'    => $url,
        'no_art' => ( $forced !== '' && $brand !== '' && ( mb_stripos( $forced, $brand ) === 0 || mb_stripos( $forced, $model . ' (' . $brand ) === 0 ) ),
      );
    }
    if ( $prods[0]['name'] === '' ) { return ''; }

    $n_price = 0; $budget = null; $has_asin = ( $prods[0]['asin'] !== '' );
    foreach ( $prods as $p ) {
      if ( $p['price'] > 0 ) {
        $n_price++;
        if ( $p['rank'] !== 1 && ( $budget === null || $p['price'] < $budget['price'] ) ) { $budget = $p; }
      }
    }
    $is_budget = ( $n_price >= 2 && $budget !== null && $budget['name'] !== ''
      && $prods[0]['price'] >= 20 && $budget['price'] < $prods[0]['price'] );
    $second = $is_budget ? $budget : $prods[1];
    if ( $second['name'] === '' ) { $second = null; }

    $type  = trim( (string) $type_plur );
    $type_lc = $type !== '' ? esc_html( mb_strtolower( $type, 'UTF-8' ) ) : '';
    $ts      = trim( (string) $type_sing );
    $type_s  = $ts !== '' ? esc_html( mb_strtolower( $ts, 'UTF-8' ) ) : $type_lc;

    /* Accord genre+nombre depuis $llm ("le meilleur"/"la meilleure"/"les meilleurs"/"les meilleures") */
    $llm_t = mb_strtolower( trim( (string) $llm ), 'UTF-8' );
    $pl    = ( mb_strpos( $llm_t, 'les ' ) === 0 );
    $fem   = ( mb_strpos( $llm_t, 'meilleure' ) !== false );
    /* $llm_s = forme singulière (un produit = une entité, toujours singulier) */
    $llm_s = $fem ? 'la meilleure' : 'le meilleur';

    /* Article défini devant le nom de produit — toujours singulier */
    $mt_art = function ( $name ) use ( $fem ) {
      $fc = mb_strtolower( mb_substr( $name, 0, 1, 'UTF-8' ), 'UTF-8' );
      if ( in_array( $fc, array( 'a', 'à', 'â', 'e', 'é', 'è', 'ê', 'i', 'î', 'o', 'ô', 'u', 'û' ), true ) ) { return 'l\''; }
      return $fem ? 'la ' : 'le ';
    };

    $p1_url = $prods[0]['url'] !== '' ? $prods[0]['url'] : '#produit-n-1';
    $p1 = ( $prods[0]['no_art'] ? '' : $mt_art( $prods[0]['name'] ) ) . '<a href="' . esc_url( $p1_url ) . '">' . esc_html( $prods[0]['name'] ) . '</a>';
    $p2 = '';
    if ( $second ) {
      $p2_url = $second['url'] !== '' ? $second['url'] : '#produit-n-' . (int) $second['rank'];
      $p2 = ( $second['no_art'] ? '' : $mt_art( $second['name'] ) ) . '<a href="' . esc_url( $p2_url ) . '">' . esc_html( $second['name'] ) . '</a>';
    }

    /* Terminaison conditionnelle : prix+ASIN → "à acheter", prix seul → "sur le marché", sinon "du moment"/"à choisir" */
    if ( $n_price >= 1 && $has_asin ) {
      $fins = array( 'que vous pouvez acheter en ' . $year, 'à acheter en ' . $year );
    } elseif ( $n_price >= 1 ) {
      $fins = array( 'sur le marché en ' . $year, 'disponible en ' . $year );
    } else {
      $fins = array( 'du moment', 'en ' . $year );
    }

    /* "nos/les [type] préférés/préférées" — accord complet */
    $pref = $fem ? ( $pl ? 'préférées' : 'préférée' ) : ( $pl ? 'préférés' : 'préféré' );

    $s  = (string) abs( (int) $page_id );
    $d1 = (int) substr( $s, -1 );
    $d2 = strlen( $s ) >= 2 ? (int) substr( $s, -2, 1 ) : 0;
    $d3 = strlen( $s ) >= 3 ? (int) substr( $s, -3, 1 ) : 0;

    $fin = $fins[ $d1 % count( $fins ) ];
    $fin_court = $n_price >= 1 ? 'en ' . $year : 'du moment';

    /* P0 — « notre [type_sing] préféré(e) en 2026 est… » */
    $m0 = $type_s !== '' ? ( $pl
      ? 'nos ' . $type_lc . ' ' . $pref . ' ' . $fin_court . ' sont ' . $p1
      : 'notre ' . $type_s . ' ' . $pref . ' ' . $fin_court . ' est ' . $p1
    ) : '';
    /* Accord singulier du préféré pour les patterns produit */
    $pref_s = $fem ? 'préférée' : 'préféré';

    /* P1 — « est la meilleure [type_sing] [fin] » */
    $m1 = $p1 . ' est ' . $llm_s . ( $type_s !== '' ? ' ' . $type_s : '' ) . ' ' . $fin;
    /* P2 — « est notre [type_sing] préféré(e) » */
    $m2 = $p1 . ' est notre ' . ( $type_s !== '' ? $type_s . ' ' : '' ) . $pref_s;
    /* P3 — « est notre coup de cœur parmi les [type] » */
    $m3 = $p1 . ' est notre coup de cœur parmi ' . ( $type_lc !== '' ? 'les ' . $type_lc : 'ce comparatif' );
    /* P4 — « répond à tous nos critères de sélection » */
    $m4 = $p1 . ' répond à tous nos critères de sélection';
    /* P5 — « nous avons trouvé que… est la meilleure [type_sing] [fin] » */
    $m5 = 'nous avons trouvé que ' . $p1 . ' est ' . $llm_s . ( $type_s !== '' ? ' ' . $type_s : '' ) . ' ' . $fin;
    /* P6 — « après avoir analysé N [type]… la meilleure [fin] » */
    $nb = count( $ids );
    $m6 = 'après avoir analysé ' . $nb . ( $type_lc !== '' ? ' ' . $type_lc : ' produits' ) . ', ' . $p1 . ' est ' . $llm_s . ' ' . $fin;
    /* P7 — « s'impose en tête de notre sélection » */
    $m7 = $p1 . ' s\'impose en tête de notre sélection';
    /* P8 — « décroche la première place » */
    $m8 = $p1 . ' décroche la première place de notre classement';
    /* P9 — « est, selon nous, le meilleur choix parmi tous les [type] [fin] » */
    $m9 = $p1 . ' est, selon nous, le meilleur choix parmi ' . ( $fem ? 'toutes les' : 'tous les' ) . ' ' . ( $type_lc !== '' ? $type_lc : 'produits' ) . ' ' . $fin_court;

    $mains = array( $m0, $m1, $m2, $m3, $m4, $m5, $m6, $m7, $m8, $m9 );
    if ( $m0 === '' ) { $mains[0] = $m3; }

    $budgets = array(
      'Si vous cherchez un peu moins cher, nous recommandons ' . $p2 . '.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' est une excellente alternative.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' offre le meilleur rapport qualité-prix.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' mérite votre attention.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' offre un bon compromis.',
      'Si vous voulez faire des économies, nous recommandons ' . $p2 . '.',
      'En alternative plus abordable, nous recommandons ' . $p2 . '.',
      'Si le prix est un critère important pour vous, ' . $p2 . ' propose l\'essentiel pour moins cher.',
      'Si vous avez un budget plus serré, ' . $p2 . ' est l\'option la plus abordable de notre sélection.',
      'Si votre priorité est le prix, ' . $p2 . ' est l\'alternative la plus accessible de notre classement.',
    );

    $alts = array(
      'Si vous hésitez encore, ' . $p2 . ' constitue une bonne alternative.',
      'Juste derrière, ' . $p2 . ' mérite aussi votre attention.',
      $p2 . ' obtient la deuxième place de notre classement.',
      'En deuxième position, ' . $p2 . ' nous a également convaincus.',
      $p2 . ' est également un choix envisageable.',
      $p2 . ' est tout juste derrière.',
      'Autre option sérieuse, ' . $p2 . ' occupe la deuxième place.',
      $p2 . ' est également un excellent choix.',
      $p2 . ' vaut aussi le détour.',
      'Nous recommandons aussi ' . $p2 . '.',
    );

    $main = $mains[ $d2 ];
    $out = mb_strtoupper( mb_substr( $main, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $main, 1, null, 'UTF-8' ) . '.';
    if ( $p2 !== '' ) {
      $s2 = $is_budget ? $budgets[ $d3 ] : $alts[ $d3 ];
      $out .= ' ' . mb_strtoupper( mb_substr( $s2, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $s2, 1, null, 'UTF-8' );
    }
    return '<p class="mt-lede-reco">' . $out . '</p>';
  }
}

if ( ! function_exists( 'mt_quick_picks' ) ) {
  function mt_quick_picks( $ids, $max = 5 ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( empty( $ids ) ) { return ''; }
    $tag  = 'mlt00-21';
    $items = array();
    foreach ( array_slice( $ids, 0, $max ) as $i => $pid ) {
      if ( get_post_status( $pid ) !== 'publish' ) { continue; }
      $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
      $brand  = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model  = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $forced !== '' ) { $name = $forced; }
      else { $name = $model !== '' ? trim( $brand . ' ' . $model ) : get_the_title( $pid ); }
      $asin = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
      $url  = '';
      if ( $asin !== '' ) {
        $url = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $tag;
      } else {
        for ( $j = 1; $j <= 3; $j++ ) {
          $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $j, $pid ) );
          if ( $u !== '' && strpos( $u, 'http' ) === 0 ) { $url = $u; break; }
        }
      }
      $items[] = array( 'rank' => $i + 1, 'name' => $name, 'url' => $url );
    }
    if ( empty( $items ) ) { return ''; }
    $out = '<p class="mt-picks-intro"><strong>Notre s&eacute;lection&nbsp;:</strong></p>';
    $out .= '<ol class="mt-picks">';
    foreach ( $items as $it ) {
      $out .= '<li>';
      if ( $it['url'] !== '' ) {
        $out .= '<a href="' . esc_url( $it['url'] ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $it['name'] ) . '</a>';
      } else {
        $out .= esc_html( $it['name'] );
      }
      $out .= ' <a class="mt-pick-r" href="#produit-n-' . (int) $it['rank'] . '">(avis)</a>';
      $out .= '</li>';
    }
    $out .= '</ol>';
    return $out;
  }
}

if ( ! function_exists( 'mt_bold_intro' ) ) {
  function mt_bold_intro( $html, $vars ) {
    $ts  = mb_strtolower( trim( isset( $vars['sing'] ) ? $vars['sing'] : '' ), 'UTF-8' );
    $tp  = mb_strtolower( trim( isset( $vars['plur'] ) ? $vars['plur'] : '' ), 'UTF-8' );
    $llm = mb_strtolower( trim( isset( $vars['llm'] )  ? $vars['llm']  : '' ), 'UTF-8' );
    $mf  = mb_strtolower( trim( isset( $vars['mf'] )   ? $vars['mf']   : '' ), 'UTF-8' );

    if ( ( $ts === '' && $tp === '' ) || trim( $html ) === '' ) { return $html; }
    if ( $ts === '' ) { $ts = rtrim( $tp, 's' ); }
    if ( $tp === '' ) { $tp = $ts . 's'; }

    $is_fem  = ( mb_strpos( $mf, 'meilleure' ) !== false );
    $adj_sing = $is_fem ? 'meilleure' : 'meilleur';
    $adj_plur = ( $mf !== '' ) ? $mf : ( $is_fem ? 'meilleures' : 'meilleurs' );

    $patterns = array();

    // « les meilleurs climatiseurs » (article + adj + type)
    if ( $llm !== '' ) {
      $llm_plur = ( mb_strpos( $llm, 'les ' ) === 0 );
      $t = $llm_plur ? $tp : $ts;
      if ( $t !== '' ) { $patterns[] = $llm . ' ' . $t; }
    }
    // « meilleurs climatiseurs » / « meilleur climatiseur »
    if ( $adj_plur !== '' && $tp !== '' ) { $patterns[] = $adj_plur . ' ' . $tp; }
    if ( $adj_sing !== '' && $ts !== '' ) { $patterns[] = $adj_sing . ' ' . $ts; }

    // Combinaisons adjectivales fréquentes
    // [avant_nom?, masc_sing, fem_sing, masc_plur, fem_plur]
    $adjs = array(
      array( false, 'idéal',   'idéale',   'idéaux',   'idéales' ),
      array( false, 'parfait', 'parfaite', 'parfaits', 'parfaites' ),
      array( true,  'bon',     'bonne',    'bons',     'bonnes' ),
      array( false, 'adapté',  'adaptée',  'adaptés',  'adaptées' ),
      array( false, 'incontournable', 'incontournable', 'incontournables', 'incontournables' ),
    );
    foreach ( $adjs as $a ) {
      $as = $is_fem ? $a[2] : $a[1];
      $ap = $is_fem ? $a[4] : $a[3];
      if ( $a[0] ) {
        $patterns[] = $as . ' ' . $ts;
        $patterns[] = $ap . ' ' . $tp;
      } else {
        $patterns[] = $ts . ' ' . $as;
        $patterns[] = $tp . ' ' . $ap;
      }
    }

    // Mot nu (priorité la plus basse)
    $patterns[] = $tp;
    if ( $ts !== $tp ) { $patterns[] = $ts; }

    // Dédoublonner + trier du plus long au plus court
    $patterns = array_values( array_unique( array_filter( $patterns, function( $p ) { return trim( $p ) !== ''; } ) ) );
    usort( $patterns, function( $a, $b ) { return mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' ); } );

    $escaped = array_map( function( $p ) { return preg_quote( $p, '/' ); }, $patterns );
    $regex = '/(?<!\w)(' . implode( '|', $escaped ) . ')(?!\w)/iu';

    // Remplacer uniquement dans les nœuds texte (pas dans les balises HTML),
    // et pas dans du texte déjà en <strong>/<b>
    $in_bold = 0;
    return preg_replace_callback(
      '#(</?(?:strong|b)\b[^>]*>)|(<[^>]*>)|([^<]+)#iu',
      function( $m ) use ( $regex, &$in_bold ) {
        if ( $m[1] !== '' ) {
          if ( $m[1][1] === '/' ) { $in_bold = max( 0, $in_bold - 1 ); } else { $in_bold++; }
          return $m[1];
        }
        if ( $m[2] !== '' ) { return $m[2]; }
        if ( $in_bold > 0 ) { return $m[3]; }
        return preg_replace( $regex, '<strong>$1</strong>', $m[3] );
      },
      $html
    );
  }
}
?>
<div class="mt-left">

  <?php // Fil d'ariane (Rank Math) avec separateur ›
  $bc = do_shortcode('[rank_math_breadcrumb]');
  if (!empty(trim($bc))) {
      $bc = preg_replace('#(<span class="separator">).*?(</span>)#', '$1&nbsp;&rsaquo;&nbsp;$2', $bc);
      echo '<div class="mt-crumb">' . $bc . '</div>';
  } ?>

  <div class="mt-eyebrow">
    <span class="pill">Vérifié</span>
    <span>le <?php echo $mod; ?></span>
  </div>

  <h1 class="mt-h1">
  <?php
    if (!empty($forcer_affichage_du_titre ?? '')) {
        echo esc_html($forcer_affichage_du_titre);
    } elseif ($post_type === 'comparatif') {
        echo 'Les <em>' . $total_avis . ' ' . lcfirst($masculinsfeminins ?? 'meilleures') . ' ' . $type_de_produit_au_pluriel . '</em> en 2026';
        /* Multi-comparatif : « : Guide ultime (N produits comparés) »,
           N = nombre de produits différents présents dans l'ensemble des
           encarts résumé (principal + sous-comparatifs, sans doublon). */
        $mtv2_hplan = function_exists( 'mtv2_plan' ) ? mtv2_plan( $this_id ) : null;
        $mtv2_hnb   = $mtv2_hplan ? count( $mtv2_hplan['origin'] ) : 0;
        if ( $mtv2_hplan && $mtv2_hplan['is_multi'] && $mtv2_hnb > 0 ) {
            echo ' : Guide ultime (' . (int) $mtv2_hnb . ' produits compar&eacute;s)';
        } else {
            echo !empty($sous_titre ?? '') ? ' : ' . $sous_titre : ' : comparatif et guide d\'achat';
        }
    } else {
        echo esc_html(get_the_title());
    }
  ?>
  </h1>

  <?php // Effets SEO rank math
  $rank_math_title = get_post_meta($this_id, 'rank_math_title');
  $rank_math_description = get_post_meta($this_id, 'rank_math_description');
  if (($template_description ?? '') == 0 || $post_type === 'liste') {
      $new_desc = intro(50, $this_id);
      if (($new_desc <> $rank_math_description) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_description', $new_desc); }
      $p = get_post($this_id);
      if (($p->post_excerpt ?? '') !== $new_desc) { wp_update_post(array('ID'=>$this_id,'post_excerpt'=>$new_desc)); }
  }
  if (!empty($forcer_affichage_du_titre ?? '')) { $new_title = $forcer_affichage_du_titre; }
  elseif ($post_type === 'liste') { $new_title = get_the_title($this_id); }
  else { $new_title = "Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." 2026 | Test par Meilleurtest"; }
  /* Multi-comparatif : « Meilleur {type} 2026 (N produits comparés) »
     « Meilleur » accordé via lalalesmeilleur (le meilleur / la meilleure → type au
     singulier ; les … → pluriel). N = produits différents des encarts résumé.
     Le titre forcé reste prioritaire. */
  if ( empty( $forcer_affichage_du_titre ?? '' ) && $post_type === 'comparatif' ) {
    $mtv2_tplan = function_exists( 'mtv2_plan' ) ? mtv2_plan( $this_id ) : null;
    $mtv2_tnb   = $mtv2_tplan ? count( $mtv2_tplan['origin'] ) : 0;
    if ( $mtv2_tplan && $mtv2_tplan['is_multi'] && $mtv2_tnb > 0 ) {
      $mtv2_llm  = trim( (string) ( $lalalesmeilleur ?? '' ) );
      $mtv2_adj  = trim( preg_replace( '/^(le|la|les)\s+/iu', '', $mtv2_llm ) );
      $mtv2_plu  = (bool) preg_match( '/^les\s/iu', $mtv2_llm );
      $mtv2_sing = trim( (string) ( $type_de_produit_au_singulier ?? '' ) );
      $mtv2_type = ( $mtv2_plu || $mtv2_sing === '' ) ? $type_de_produit_au_pluriel : $mtv2_sing;
      if ( $mtv2_adj === '' ) { $mtv2_adj = lcfirst( $masculinsfeminins ?? 'meilleurs' ); $mtv2_type = $type_de_produit_au_pluriel; }
      $mtv2_t    = $mtv2_adj . ' ' . $mtv2_type;
      $new_title = mb_strtoupper( mb_substr( $mtv2_t, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $mtv2_t, 1, null, 'UTF-8' )
        . ' 2026 (' . (int) $mtv2_tnb . ' produits compar&eacute;s)';
      $new_title = html_entity_decode( $new_title, ENT_QUOTES, 'UTF-8' );
    }
  }
  if (($new_title <> $rank_math_title) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_title', $new_title); }
  ?>

  <div class="mt-byline">
    <?php if (!empty($author_avatar_id ?? '')) {
        echo '<span class="mt-avatar">' . wp_get_attachment_image($author_avatar_id, array(30,30), '', array('alt'=>$author_avatar_alt ?? '')) . '</span>';
    } ?>
    <span class="mt-byline-text">
      <span>Par <b><?php echo esc_html($author ?? ''); ?></b></span>
      <span class="mt-dot">&bull;</span>
      <span>Mis à jour le <?php echo $mod; ?></span>
    </span>
  </div>

  <div class="mt-lede"><?php
  $mt_intro_html = $introduction ?? '';
  if ( $MT_SHOW_BOLD_INTRO ) {
      $mt_intro_html = mt_bold_intro( $mt_intro_html, array(
        'sing' => $type_de_produit_au_singulier ?? '',
        'plur' => $type_de_produit_au_pluriel ?? '',
        'llm'  => $lalalesmeilleur ?? '',
        'mf'   => $masculinsfeminins ?? '',
      ) );
  }
  echo $mt_intro_html;
  if ( $MT_SHOW_INTRO_RECO && $post_type === 'comparatif' ) {
      echo mt_intro_reco( $this_id, $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $type_de_produit_au_singulier ?? '', $lalalesmeilleur ?? '' );
  } ?></div>

  <?php if ( $MT_SHOW_QUICK_PICKS && $post_type === 'comparatif' && ! empty( $top_avis_ids ) ) {
    echo mt_quick_picks( $top_avis_ids );
  } ?>

  <div class="mt-photo">
    <?php echo get_the_post_thumbnail($this_id, 'large', array('class'=>'mt-photo-img')); ?>
    <?php if ($post_type === 'comparatif') {
        echo '<img class="mt-badge" src="https://meilleurtest.fr/wp-content/uploads/2026/07/badge-mt3.png" alt="" style="position:absolute;top:0;left:0;max-width:130px;height:auto;">';
    } ?>
  </div>

</div>
