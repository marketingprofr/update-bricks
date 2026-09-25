<?php
/* =====================================================================
   MEILLEURTEST — MULTI-COMPARATIF (V2) : « tests complets »
   Remplace top5-tests.code.php dans le template Bricks multi-comparatif.
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   CSS : v2/multi-tests.css (fichier COMPLET = top5-tests.css + ajouts V2)
   à coller dans l'onglet CSS du même élément.

   - UN test par produit, même s'il figure dans plusieurs encarts : liste
     sans doublon, dans l'ordre de 1re apparition (principal, puis
     sous-comparatifs), limitée à MTV2_MAX_TESTS.
   - Ancre : id="test-{slug de l'avis}" (+ ancre V1 #produit-n-{rang}
     conservée pour les produits de l'encart principal : FAQ, tableau…).
   - Angle d'utilisation : celui de l'encart d'origine du produit.
   - JSON-LD : 1 Product par produit (@id stable) + 1 ItemList par encart.
   - Moteur multi-comparatif inclus dans ce bloc (aucun snippet WPCodeBox).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — noms des champs ACF (à confirmer côté site si besoin)
   --------------------------------------------------------------------- */
$TT_CUST_RATING_FIELD = 'mltv5_score_avis_clients';   // note clients /5
$TT_CUST_COUNT_FIELD  = 'mltv5_nombre_avis_clients';  // nombre d'avis clients
$TT_VERDICT_FIELD     = 'mltv5_verdict_court';        // libellé récompense (eyebrow), ex. « Notre coup de cœur »
$TT_FORWHO_FIELD      = 'mltv5_pour_qui';             // « À qui ça s'adresse » (optionnel, masqué si vide)
$TT_SPECS_FIELD       = 'mltv5_caracteristiques_du_produit'; // repeater fiche technique
$TT_SPEC_LABEL_KEY    = 'mltv5_caracteristique_intitule';    // sous-champ intitulé
$TT_SPEC_VALUE_KEY    = 'mltv5_caracteristique_valeur';      // sous-champ valeur
$TT_AMAZON_TAG        = 'mlt00-21';                          // tag affilié Amazon

/* ---------------------------------------------------------------------
   Helpers (partagés avec top5-resume — guards anti-redéclaration)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt5_num' ) ) {
  function mt5_num( $v ) {
    $v = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $v );
    $v = str_replace( ',', '.', $v );
    return is_numeric( $v ) ? (float) $v : 0.0;
  }
}
if ( ! function_exists( 'mt5_reviews_label' ) ) {
  function mt5_reviews_label( $c ) {
    $n = (int) preg_replace( '/[^0-9]/', '', (string) $c );
    return $n > 0 ? number_format( $n, 0, ',', ' ' ) . ' avis' : '';
  }
}
if ( ! function_exists( 'mt5_merchant_name' ) ) {
  function mt5_merchant_name( $url ) {
    $host = parse_url( (string) $url, PHP_URL_HOST );
    if ( ! $host ) { return ''; }
    $host  = preg_replace( '/^www\./i', '', $host );
    $parts = explode( '.', $host );
    $label = isset( $parts[0] ) ? $parts[0] : '';
    return $label !== '' ? ucfirst( $label ) : '';
  }
}
if ( ! function_exists( 'mt5_join_et' ) ) {
  function mt5_join_et( $items ) {
    $items = array_values( array_filter( $items, function ( $v ) { return $v !== ''; } ) );
    $n     = count( $items );
    if ( $n === 0 ) { return ''; }
    if ( $n === 1 ) { return $items[0]; }
    $last = array_pop( $items );
    return implode( ', ', $items ) . ' et ' . $last;
  }
}
if ( ! function_exists( 'mt5_points' ) ) {
  function mt5_points( $field, $pid, $subkey ) {
    $rows = get_field( $field, $pid );
    $out  = array();
    if ( is_array( $rows ) ) {
      foreach ( $rows as $r ) {
        $p = isset( $r[ $subkey ] ) ? trim( (string) $r[ $subkey ] ) : '';
        if ( $p !== '' ) { $out[] = $p; }
      }
    }
    return $out;
  }
}
if ( ! function_exists( 'mt5_specs' ) ) {
  /* Repeater fiche technique -> tableau [ [intitulé, valeur], ... ].
     Tolérant : si les sous-clés configurées sont absentes, prend les deux
     premières valeurs scalaires non vides de la ligne (intitulé / valeur). */
  function mt5_specs( $field, $pid, $lbl_key, $val_key ) {
    $rows = get_field( $field, $pid );
    $out  = array();
    if ( ! is_array( $rows ) ) { return $out; }
    foreach ( $rows as $r ) {
      if ( ! is_array( $r ) ) { continue; }
      $lbl = isset( $r[ $lbl_key ] ) ? trim( (string) $r[ $lbl_key ] ) : '';
      $val = isset( $r[ $val_key ] ) ? trim( (string) $r[ $val_key ] ) : '';
      if ( $lbl === '' && $val === '' ) {
        $scal = array();
        foreach ( $r as $vv ) {
          if ( is_scalar( $vv ) && trim( (string) $vv ) !== '' ) { $scal[] = trim( (string) $vv ); }
        }
        if ( count( $scal ) >= 2 )      { $lbl = $scal[0]; $val = $scal[1]; }
        elseif ( count( $scal ) === 1 ) { $lbl = $scal[0]; }
      }
      if ( $lbl !== '' || $val !== '' ) { $out[] = array( $lbl, $val ); }
    }
    return $out;
  }
}
if ( ! function_exists( 'mt5_spec_val' ) ) {
  /* Valeur de spec : rend le HTML léger (icônes FA <i>, <br>, gras…) au lieu
     de l'échapper (sinon « <i class="fa-solid fa-check"></i> » s'affiche en
     texte). Whitelist stricte -> pas de script/style/img/iframe arbitraire. */
  function mt5_spec_val( $html ) {
    return wp_kses( (string) $html, array(
      'i'      => array( 'class' => true, 'aria-hidden' => true, 'title' => true, 'style' => true ),
      'span'   => array( 'class' => true, 'aria-hidden' => true, 'style' => true ),
      'br'     => array(),
      'strong' => array(), 'b' => array(), 'em' => array(),
      'sub'    => array(), 'sup' => array(),
      'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
    ) );
  }
}
if ( ! function_exists( 'mt5_norm_keys' ) ) {
  /* Normalise une liste de libellés d'attributs -> minuscules, sans espace de
     bord, sans vide ni doublon, triés alpha. Sert à comparer le jeu d'attributs
     du comparatif avec celui d'un « angle d'utilisation » d'avis consolidé
     (comparaison insensible à la casse ET à l'ordre). */
  function mt5_norm_keys( $list ) {
    $out = array();
    foreach ( (array) $list as $s ) {
      $s = str_replace( "\xC2\xA0", ' ', (string) $s ); // nbsp -> espace normal
      $s = preg_replace( '/\s+/u', ' ', $s );           // espaces multiples -> un seul
      $s = trim( $s );
      /* mb_strtolower : indispensable pour les accents (« Réduction » -> « réduction »),
         strtolower ASCII ne baisserait pas la casse des lettres accentuées. */
      $s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
      if ( $s !== '' ) { $out[] = $s; }
    }
    $out = array_values( array_unique( $out ) );
    sort( $out, SORT_STRING );
    return $out;
  }
}

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

    /* 1re phrase du 1er paragraphe (texte brut), le reste en suite */
    $first = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $paras[0] ) ) );
    $lead  = $first;
    $rest0 = '';
    if ( preg_match( '/^(.{25,}?[.!?…])\s+(\S.*)$/us', $first, $mm ) ) {
      $lead  = $mm[1];
      $rest0 = $mm[2];
    }
    $rest = array();
    if ( $rest0 !== '' ) { $rest[] = esc_html( $rest0 ); }
    foreach ( array_slice( $paras, 1 ) as $p ) { $rest[] = wp_kses_post( $p ); }

    $out = '<div class="mtv2-intro"><p class="mtv2-intro-lead">' . esc_html( $lead ) . '</p>';
    if ( ! empty( $rest ) ) {
      $out .= '<details class="mtv2-intro-more"><summary><span class="mtv2-more-on">Lire la suite</span><span class="mtv2-more-off">R&eacute;duire</span></summary>'
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

    /* Liste des tests : principal puis sous-comparatifs, sans doublon */
    $push = function ( $pid, $enc, $rank ) use ( &$plan ) {
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
    $plan['test_set'] = array_flip( $plan['tests'] );

    /* Précharge posts + métas + termes de tous les produits en 1 passe */
    $all = array_keys( $plan['origin'] );
    if ( ! empty( $all ) && function_exists( '_prime_post_caches' ) ) {
      _prime_post_caches( $all, true, true );
    }

    $cache[ $page_id ] = $plan;
    return $plan;
  }
}

if ( ! function_exists( 'mtv2_product_href' ) ) {
  /* Lien d'un produit dans un encart : son test complet sur la page s'il y
     figure (#test-slug), sinon sa page d'avis (au-delà de MTV2_MAX_TESTS). */
  function mtv2_product_href( $pid, $plan ) {
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

if ( ! function_exists( 'mtv2_product_ld_id' ) ) {
  /* @id stable d'un produit (JSON-LD) : URL de sa page d'avis + #product.
     Identique d'une page à l'autre -> une seule entité par produit. */
  function mtv2_product_ld_id( $pid ) {
    $u = get_permalink( (int) $pid );
    return $u ? $u . '#product' : get_permalink() . '#' . ( function_exists( 'mtv2_test_anchor' ) ? mtv2_test_anchor( $pid ) : 'produit-' . (int) $pid );
  }
}

/* ---------------------------------------------------------------------
   Liste des produits à tester
   - Liste SANS DOUBLON du plan (moteur ci-dessus), dans l'ordre de
     1re apparition (encart principal, puis sous-comparatifs), coupée à
     MTV2_MAX_TESTS.
   --------------------------------------------------------------------- */
$page_id = get_the_ID();
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$plan    = mtv2_plan( $page_id );

if ( $plan ) {
  $ids = $plan['tests'];
} else {
  $ids = isset( $page_tv['top_avis_ids'] ) && is_array( $page_tv['top_avis_ids'] ) ? $page_tv['top_avis_ids'] : array();
  if ( empty( $ids ) ) {
    $rel = get_field( 'mltv5_best_products', $page_id ); // fallback : champ relation
    if ( is_array( $rel ) ) {
      foreach ( $rel as $r ) { $ids[] = is_object( $r ) ? $r->ID : (int) $r; }
    }
  }
  $ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
}
if ( empty( $ids ) ) { return; }

/* ---------------------------------------------------------------------
   Attributs (taxonomie post-type-attribut) qui choisissent l'« angle
   d'utilisation » d'un avis consolidé. V2 : chaque produit prend l'angle de
   l'encart où il apparaît EN PREMIER (principal -> attributs de la page ;
   sous-comparatif -> attributs du sous-comparatif, ex. « 9000 BTU »).
   --------------------------------------------------------------------- */
$comp_names = array();
$comp_terms = get_the_terms( $page_id, 'post-type-attribut' );
if ( is_array( $comp_terms ) ) {
  foreach ( $comp_terms as $t ) { $comp_names[] = isset( $t->name ) ? $t->name : ''; }
}
$attr_keys_by_enc = array( 'main' => mt5_norm_keys( $comp_names ) );
if ( $plan ) {
  foreach ( $plan['subs'] as $si => $sub ) { $attr_keys_by_enc[ $si ] = mt5_norm_keys( $sub['attr_names'] ); }
}

/* Rang affiché, encart d'origine et autres apparitions (« Aussi n°2 en 9000 BTU ») */
$main_rank = array();
if ( $plan ) {
  foreach ( $plan['main']['ids'] as $i => $mid ) { $main_rank[ $mid ] = $i + 1; }
}

/* ---------------------------------------------------------------------
   PASSE 1 : collecte (contexte post requis pour les helpers de score)
   --------------------------------------------------------------------- */
global $post;
$tt_saved_post = $post;

$products = array();
$pos      = 0;

/* Barre d'infos sous le nom : STRICTEMENT réservée à l'admin connecté.
   Rendue seulement si la condition est vraie -> jamais présente dans le HTML
   servi au public (donc jamais mise en cache pour les visiteurs). */
$tt_is_admin = function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );

foreach ( $ids as $pid ) {
  $p = get_post( $pid );
  if ( ! $p ) { continue; }
  $post = $p;
  setup_postdata( $post );
  $pos++;

  /* Encart d'origine (V2) -> rang affiché + angle */
  $enc      = ( $plan && isset( $plan['origin'][ $pid ] ) ) ? $plan['origin'][ $pid ] : 'main';
  $shown_n  = $pos;
  $origin_l = '';
  $also     = array();
  if ( $plan ) {
    foreach ( $plan['seen_in'][ $pid ] as $k => $ap ) {
      if ( $k === 0 ) { $shown_n = $ap['rank']; continue; } // 1re apparition = origine
      $also[] = 'n&deg;' . (int) $ap['rank'] . ' ' . ( $ap['enc'] === 'main' ? 'du classement g&eacute;n&eacute;ral' : 'en ' . esc_html( mtv2_encart_label( $ap['enc'], $plan ) ) );
    }
    if ( $enc !== 'main' ) { $origin_l = mtv2_encart_label( $enc, $plan ); }
  }
  $comp_attr_keys = isset( $attr_keys_by_enc[ $enc ] ) ? $attr_keys_by_enc[ $enc ] : array();

  /* Identité */
  $forced  = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
  $brand   = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
  $model   = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
  if ( $forced !== '' ) { $name = $forced; $brand = ''; }
  else { $name = $model !== '' ? $model : get_the_title( $pid ); }
  $tagline = trim( (string) get_field( 'mltv5_sous_titre', $pid ) );
  $summary = trim( (string) get_field( 'mltv5_resume_produit', $pid ) );
  $verdict = trim( (string) get_field( $TT_VERDICT_FIELD, $pid ) );
  $forwho  = trim( (string) get_field( $TT_FORWHO_FIELD, $pid ) );

  /* Image mise en avant : featured en priorite, sinon URL externe ACF (hotlink partenaire) */
  $img = get_the_post_thumbnail_url( $pid, 'medium' );
  if ( ! $img ) {
    $ext = get_field( 'mltv5_image_external_url', $pid );
    if ( is_array( $ext ) ) { $ext = isset( $ext['url'] ) ? $ext['url'] : ''; }
    $ext = trim( (string) $ext );
    if ( $ext !== '' ) { $img = $ext; }
  }

  /* Score rédac /10 + libellé qualitatif */
  $score10 = function_exists( 'get_acf_score_divided_by_10' )
    ? get_acf_score_divided_by_10()
    : round( mt5_num( get_field( 'mltv5_score_recent', $pid ) ) / 10, 1 );
  $score_tag = function_exists( 'get_acf_score_label' ) ? get_acf_score_label() : '';

  /* Note clients */
  $cust_rating = trim( (string) get_field( $TT_CUST_RATING_FIELD, $pid ) );
  $cust_count  = trim( (string) get_field( $TT_CUST_COUNT_FIELD, $pid ) );

  /* Points + / - (répéteurs) */
  $pros = mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' );
  $cons = mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' );

  /* Fiche technique */
  $specs = mt5_specs( $TT_SPECS_FIELD, $pid, $TT_SPEC_LABEL_KEY, $TT_SPEC_VALUE_KEY );

  /* Corps de l'avis = contenu WordPress du produit */
  $body_html = apply_filters( 'the_content', get_the_content( null, false, $p ) );

  /* Avis consolidés : si l'encart d'origine porte des attributs, chercher dans le
     repeater mltv5_utilisations_du_produit l'« angle d'utilisation » dont les
     attributs (mltv5_nom_utilisation_produit, virgule-séparés) forment EXACTEMENT
     le même jeu (casse/ordre ignorés). Match -> on affiche son wysiwyg à la place
     du contenu principal ; sinon fallback contenu principal. */
  $body_matched = false;
  if ( ! empty( $comp_attr_keys ) ) {
    $uses = get_field( 'mltv5_utilisations_du_produit', $pid );
    if ( is_array( $uses ) ) {
      foreach ( $uses as $u ) {
        if ( ! is_array( $u ) ) { continue; }
        $raw  = isset( $u['mltv5_nom_utilisation_produit'] ) ? (string) $u['mltv5_nom_utilisation_produit'] : '';
        $keys = mt5_norm_keys( explode( ',', $raw ) );
        if ( $keys === $comp_attr_keys ) {
          $rich = isset( $u['mltv5_avantages_inconvenients_utilisation'] ) ? (string) $u['mltv5_avantages_inconvenients_utilisation'] : '';
          if ( trim( $rich ) !== '' ) { $body_html = $rich; $body_matched = true; }
          break; // un seul angle attendu par jeu d'attributs
        }
      }
    }
  }

  /* Méta admin (barre de debug sous le nom) — calculée seulement pour l'admin. */
  $admin_meta = null;
  if ( $tt_is_admin ) {
    $pub_ts  = function_exists( 'get_post_timestamp' ) ? get_post_timestamp( $p )
      : ( ! empty( $p->post_date_gmt ) ? strtotime( $p->post_date_gmt . ' UTC' ) : strtotime( $p->post_date ) );
    $pub_lbl = $pub_ts ? ( function_exists( 'wp_date' ) ? wp_date( 'Y/m', $pub_ts ) : gmdate( 'Y/m', $pub_ts ) ) : '—';
    $recent  = $pub_ts && ( $pub_ts >= strtotime( '-6 months' ) ); // « frais » < 6 mois
    $edit    = function_exists( 'get_edit_post_link' ) ? (string) get_edit_post_link( $pid, 'raw' ) : '';
    if ( $body_matched ) {
      $src = 'Angle : ' . implode( ' + ', $comp_attr_keys );
    } elseif ( ! empty( $comp_attr_keys ) ) {
      $src = 'Contenu principal (aucun angle « ' . implode( ' + ', $comp_attr_keys ) . ' »)';
    } else {
      $src = 'Contenu principal';
    }
    $angles = get_field( 'mltv5_utilisations_du_produit', $pid );
    $admin_meta = array(
      'pid'    => $pid,
      'pub'    => $pub_lbl,
      'recent' => (bool) $recent,
      'edit'   => $edit,
      'src'    => $src,
      'status' => (string) get_post_status( $p ),
      'angles' => is_array( $angles ) ? count( $angles ) : 0,
    );
  }

  /* Offres : ASIN Amazon prioritaire, puis liens perso ACF */
  $asin       = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
  $prix       = get_field( 'mltv5_prix_indicatif', $pid );
  $has_price  = ( $prix !== '' && $prix !== null && mt5_num( $prix ) > 0 );
  $offer_urls = array();
  $btn_first  = '';
  if ( $asin !== '' ) {
    $offer_urls[] = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $TT_AMAZON_TAG;
  }
  for ( $i = 1; $i <= 3; $i++ ) {
    $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
    $t = trim( (string) get_field( 'mltv5_texte_du_bouton_' . $i, $pid ) );
    if ( $u !== '' && strpos( $u, 'http' ) === 0 ) {
      $offer_urls[] = $u;
      if ( $btn_first === '' && $t !== '' ) { $btn_first = $t; }
    }
  }
  $offer_urls = array_values( array_unique( $offer_urls ) );

  $primary_merchant = '';
  if ( ! empty( $offer_urls ) ) {
    $primary_merchant = $asin !== '' ? 'Amazon' : mt5_merchant_name( $offer_urls[0] );
  }

  $products[] = array(
    'pid'         => (int) $pid,
    'pos'         => $pos,
    'shown_n'     => $shown_n,
    'origin'      => $origin_l,
    'also'        => $also,
    'anchor'      => $plan ? mtv2_test_anchor( $pid ) : 'produit-n-' . $pos,
    'legacy_n'    => ( $plan && isset( $main_rank[ $pid ] ) ) ? $main_rank[ $pid ] : 0,
    'name'        => $name,
    'brand'       => $brand,
    'tagline'     => $tagline,
    'summary'     => $summary,
    'verdict'     => $verdict,
    'forwho'      => $forwho,
    'img'         => $img,
    'score10'     => $score10,
    'score_tag'   => $score_tag,
    'cust_rating' => $cust_rating,
    'cust_count'  => $cust_count,
    'pros'        => $pros,
    'cons'        => $cons,
    'specs'       => $specs,
    'body'        => $body_html,
    'offer_urls'  => $offer_urls,
    'primary_url' => ! empty( $offer_urls ) ? $offer_urls[0] : '',
    'cta_text'    => $primary_merchant !== '' ? 'Voir sur ' . $primary_merchant : ( $btn_first !== '' ? $btn_first : "Voir l'offre" ),
    'has_price'   => $has_price,
    'prix'        => $has_price ? mt5_num( $prix ) : 0,
    'admin'       => $admin_meta,
  );
}
$post = $tt_saved_post;
wp_reset_postdata();

if ( empty( $products ) ) { return; }

/* ---------------------------------------------------------------------
   En-tête de section (affiché une seule fois)
   --------------------------------------------------------------------- */
$nb        = count( $products );
$type_sing = isset( $page_tv['type_de_produit_au_singulier'] ) ? trim( (string) $page_tv['type_de_produit_au_singulier'] ) : '';
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';
if ( $type_sing === '' ) { $type_sing = $type_plur; }

$head_h2 = 'Le test complet de chaque' . ( $type_sing !== '' ? ' ' . esc_html( $type_sing ) : ' produit' );
$head_p  = 'Notre r&eacute;daction a pass&eacute; en revue ' . (int) $nb
  . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : ' produits' )
  . ', en analysant leurs caract&eacute;ristiques, les retours utilisateurs et le rapport qualit&eacute;-prix. Voici notre avis complet, produit par produit.';
?>
<section class="ed-a contenu-principal" id="partie-tests-complets" aria-labelledby="ed-a-title">
  <div class="ed-a-head">
    <p class="kick">Nos avis d&eacute;taill&eacute;s</p>
    <h2 class="ed-a-serif" id="ed-a-title"><?php echo $head_h2; ?></h2>
    <p><?php echo $head_p; ?></p>
    <div class="rule"></div>
  </div>

<?php foreach ( $products as $it ) :
  $fill = max( 0, min( 100, round( mt5_num( $it['cust_rating'] ) / 5 * 100 ) ) );
?>
  <article class="ed-a-piece" id="<?php echo esc_attr( $it['anchor'] ); ?>">
    <?php if ( $it['legacy_n'] > 0 ) : ?><span class="mtv2-legacy-anchor" id="produit-n-<?php echo (int) $it['legacy_n']; ?>" aria-hidden="true"></span><?php endif; ?>
    <div class="ed-a-col">
      <div class="ed-a-eyebrow">
        <span class="num">N&deg;<?php echo esc_html( sprintf( '%02d', $it['shown_n'] ) ); ?></span>
        <?php if ( $it['origin'] !== '' ) : ?>
        <span class="sep"></span>
        <span class="award mtv2-origin"><?php echo esc_html( $it['origin'] ); ?></span>
        <?php endif; ?>
        <?php if ( $it['verdict'] !== '' ) : ?>
        <span class="sep"></span>
        <span class="award gold"><?php echo esc_html( $it['verdict'] ); ?></span>
        <?php endif; ?>
        <?php if ( ! empty( $it['also'] ) ) : ?>
        <span class="sep"></span>
        <span class="mtv2-also">Aussi <?php echo implode( ', ', $it['also'] ); // libellés échappés à la construction ?></span>
        <?php endif; ?>
      </div>
      <h3 class="ed-a-name"><?php if ( $it['brand'] !== '' ) : ?><?php echo esc_html( $it['brand'] ); ?> <?php endif; ?><span><?php echo esc_html( $it['name'] ); ?></span></h3>
      <?php if ( $it['tagline'] !== '' ) : ?><p class="ed-a-deck"><?php echo esc_html( $it['tagline'] ); ?></p><?php endif; ?>
      <?php if ( ! empty( $it['admin'] ) ) : $am = $it['admin']; ?>
      <div class="ed-a-adminbar" role="note" aria-label="Infos administrateur (avis)">
        <span class="am-tag am-pid" title="ID du post avis">ID <?php echo (int) $am['pid']; ?></span>
        <span class="am-tag am-date<?php echo $am['recent'] ? ' is-recent' : ''; ?>" title="Publication (année/mois) — vert si &lt; 6 mois"><?php echo esc_html( $am['pub'] ); ?></span>
        <?php if ( $am['status'] !== 'publish' ) : ?><span class="am-tag am-status" title="Statut du post">&#9888; <?php echo esc_html( $am['status'] ); ?></span><?php endif; ?>
        <span class="am-tag am-src" title="Source du contenu affiché"><?php echo esc_html( $am['src'] ); ?></span>
        <?php if ( (int) $am['angles'] > 0 ) : ?><span class="am-tag am-angles" title="Nombre d'angles d'utilisation (repeater mltv5_utilisations_du_produit)"><?php echo (int) $am['angles']; ?> angle<?php echo ( (int) $am['angles'] > 1 ? 's' : '' ); ?></span><?php endif; ?>
        <?php if ( $am['edit'] !== '' ) : ?><a class="am-edit" href="<?php echo esc_url( $am['edit'] ); ?>" target="_blank" rel="noopener">&#9998;&nbsp;&Eacute;dition</a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <figure class="ed-a-figure">
      <div class="ph">
        <div class="ph-img">
          <?php if ( $it['img'] ) : ?>
            <img src="<?php echo esc_url( $it['img'] ); ?>" alt="<?php echo esc_attr( trim( $it['brand'] . ' ' . $it['name'] ) ); ?>" loading="lazy" decoding="async">
          <?php else : ?>
            <span class="ph-cap"><?php echo esc_html( $it['name'] ); ?></span>
          <?php endif; ?>
        </div>
        <?php if ( $it['summary'] !== '' ) : ?>
        <div class="ph-info">
          <p class="ph-verdict"><?php echo wp_kses_post( $it['summary'] ); ?></p>
        </div>
        <?php endif; ?>
      </div>
    </figure>
    <div class="ed-a-col">
      <?php
      $has_cust  = ( $it['cust_rating'] !== '' && mt5_num( $it['cust_rating'] ) > 0 );
      /* Pas d'avis clients : on comble le slot avec le 1er point fort. */
      $first_pro = ( ! $has_cust && ! empty( $it['pros'] ) ) ? $it['pros'][0] : '';
      $buy_class = $has_cust ? '' : ( $first_pro !== '' ? ' no-cust has-pro' : ' no-cust' );
      ?>
      <div class="ed-a-buy<?php echo $buy_class; ?>">
        <div class="note-block">
          <span class="r-lbl">Notre note</span>
          <div class="r-line">
            <span class="n"><?php echo esc_html( number_format( (float) $it['score10'], 1, ',', '' ) ); ?><small>/10</small></span>
            <?php if ( $it['score_tag'] !== '' ) : ?><span class="tag"><?php echo esc_html( $it['score_tag'] ); ?></span><?php endif; ?>
          </div>
        </div>
        <?php if ( $has_cust ) : ?>
        <div class="cust-block">
          <span class="r-lbl">Avis clients</span>
          <div class="cust-line">
            <span class="cust-val"><?php echo esc_html( number_format( mt5_num( $it['cust_rating'] ), 1, ',', '' ) ); ?></span>
            <span class="stars-gauge" aria-label="note clients">
              <span>&#9733;&#9733;&#9733;&#9733;&#9733;</span>
              <span class="fill" style="width: <?php echo (int) $fill; ?>%;">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
            </span>
            <?php $cl = mt5_reviews_label( $it['cust_count'] ); if ( $cl !== '' ) : ?><span class="cust-count"><?php echo esc_html( $cl ); ?></span><?php endif; ?>
          </div>
        </div>
        <?php elseif ( $first_pro !== '' ) : ?>
        <div class="pro-block">
          <span class="r-lbl">Son point fort</span>
          <p class="pro-line"><?php echo esc_html( $first_pro ); ?></p>
        </div>
        <?php endif; ?>
        <div class="cta-block">
          <?php if ( $it['primary_url'] !== '' ) : ?>
          <a class="cta" href="<?php echo esc_url( $it['primary_url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $it['cta_text'] ); ?> <span class="arr">&#8594;</span></a>
          <?php endif; ?>
          <?php
          $links = array();
          foreach ( $it['offer_urls'] as $idx => $u ) {
            if ( $idx === 0 ) { continue; }
            $mn = mt5_merchant_name( $u );
            if ( $mn !== '' ) {
              $links[] = '<a href="' . esc_url( $u ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $mn ) . '</a>';
            }
          }
          if ( ! empty( $links ) ) : ?>
          <div class="merchants">ou sur <?php echo mt5_join_et( $links ); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ( $it['pros'] || $it['cons'] ) : ?>
      <div class="ed-a-pc<?php echo ( $it['pros'] && $it['cons'] ) ? '' : ' single'; ?>">
        <?php if ( $it['pros'] ) : ?>
        <div class="col pros">
          <h5>Points positifs</h5>
          <ul>
            <?php foreach ( $it['pros'] as $pp ) : ?><li><?php echo esc_html( $pp ); ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <?php if ( $it['cons'] ) : ?>
        <div class="col cons">
          <h5>Points n&eacute;gatifs</h5>
          <ul>
            <?php foreach ( $it['cons'] as $cc ) : ?><li><?php echo esc_html( $cc ); ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="ed-a-body">
        <?php echo $it['body']; // post_content déjà filtré par the_content ?>

        <?php if ( $it['forwho'] !== '' ) : ?>
        <div class="ed-a-forwho">
          <h5>&Agrave; qui s'adresse ce <?php echo esc_html( $type_sing !== '' ? $type_sing : 'produit' ); ?>&nbsp;?</h5>
          <p><?php echo esc_html( $it['forwho'] ); ?></p>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $it['specs'] ) ) : ?>
        <details class="ed-a-specs">
          <summary>Fiche technique compl&egrave;te <span class="chev" aria-hidden="true">&#8964;</span></summary>
          <dl>
            <?php foreach ( $it['specs'] as $sp ) : ?>
            <div class="row"><dt><?php echo esc_html( $sp[0] ); ?></dt><dd><?php echo mt5_spec_val( $sp[1] ); ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </details>
        <?php endif; ?>
      </div>
    </div>
  </article>
<?php endforeach; ?>
</section>
<?php
/* ---------------------------------------------------------------------
   Données structurées (JSON-LD)
   - Chaque produit testé = UNE entité Product, @id stable
     ({URL de l'avis}#product), mêmes règles que le V1 (Offer si 1 offre,
     AggregateOffer sinon, brand renseigné, Review de la rédaction).
   - Chaque encart (principal + sous-comparatifs) = une ItemList qui
     référence les produits par @id (au-delà de la limite de tests : url).
   --------------------------------------------------------------------- */
$author_name = 'Meilleurtest.fr';
$author_type = 'Organization';
$ld_products = array();
foreach ( $products as $it ) {
  $ld = array(
    '@type' => 'Product',
    '@id'   => mtv2_product_ld_id( $it['pid'] ),
    'name'  => trim( $it['brand'] . ' ' . $it['name'] ),
  );
  if ( $it['img'] )             { $ld['image'] = $it['img']; }
  if ( $it['summary'] !== '' )  { $ld['description'] = wp_strip_all_tags( $it['summary'] ); }
  $ld_brand = $it['brand'];
  if ( $ld_brand === '' ) {
    $words = explode( ' ', trim( $it['brand'] . ' ' . $it['name'] ), 2 );
    if ( count( $words ) >= 2 && mb_strlen( $words[0] ) >= 2 ) { $ld_brand = $words[0]; }
  }
  if ( $ld_brand !== '' ) { $ld['brand'] = array( '@type' => 'Brand', 'name' => $ld_brand ); }

  if ( $it['score10'] > 0 ) {
    $ld['review'] = array(
      '@type'        => 'Review',
      'author'       => array( '@type' => $author_type, 'name' => $author_name ),
      'reviewRating' => array(
        '@type'      => 'Rating',
        'ratingValue' => number_format( (float) $it['score10'], 1, '.', '' ),
        'bestRating'  => '10',
        'worstRating' => '1',
      ),
    );
  }

  $cust_r = mt5_num( $it['cust_rating'] );
  $cust_c = (int) preg_replace( '/[^0-9]/', '', (string) $it['cust_count'] );
  if ( $cust_r > 0 && $cust_c > 0 ) {
    $ld['aggregateRating'] = array(
      '@type'       => 'AggregateRating',
      'ratingValue' => number_format( $cust_r, 1, '.', '' ),
      'bestRating'  => '5',
      'reviewCount' => $cust_c,
    );
  }

  $offer_count = count( $it['offer_urls'] );
  if ( $it['prix'] > 0 && $offer_count > 1 ) {
    $formatted_price = number_format( $it['prix'], 2, '.', '' );
    $ld['offers'] = array(
      '@type'         => 'AggregateOffer',
      'lowPrice'      => $formatted_price,
      'highPrice'     => $formatted_price,
      'priceCurrency' => 'EUR',
      'offerCount'    => $offer_count,
      'url'           => $it['primary_url'],
    );
  } elseif ( $it['prix'] > 0 && $offer_count === 1 ) {
    $ld['offers'] = array(
      '@type'         => 'Offer',
      'price'         => number_format( $it['prix'], 2, '.', '' ),
      'priceCurrency' => 'EUR',
      'url'           => $it['primary_url'],
      'availability'  => 'https://schema.org/InStock',
    );
  }
  $ld_products[ $it['pid'] ] = $ld;
}

if ( ! empty( $ld_products ) ) :
  $page_url = get_permalink( $page_id );
  $graph    = array_values( $ld_products );

  /* Une ItemList par encart, dans l'ordre d'affichage */
  $encarts = array( array(
    'id'   => $page_url . '#liste-principale',
    'name' => 'Les meilleurs' . ( $type_plur !== '' ? ' ' . $type_plur : ' produits' ),
    'ids'  => $plan ? $plan['main']['ids'] : wp_list_pluck( $products, 'pid' ),
  ) );
  if ( $plan ) {
    foreach ( $plan['subs'] as $sub ) {
      $encarts[] = array( 'id' => $page_url . '#liste-' . $sub['anchor'], 'name' => $sub['title'], 'ids' => $sub['ids'] );
    }
  }
  foreach ( $encarts as $enc ) {
    $items = array();
    $n     = 0;
    foreach ( $enc['ids'] as $pid ) {
      $n++;
      $li = array( '@type' => 'ListItem', 'position' => $n );
      if ( isset( $ld_products[ $pid ] ) ) {
        $li['item'] = array( '@id' => $ld_products[ $pid ]['@id'] );
      } else {
        $u = get_permalink( $pid );
        if ( ! $u ) { $n--; continue; }
        $li['url'] = $u;
      }
      $items[] = $li;
    }
    if ( empty( $items ) ) { continue; }
    $graph[] = array(
      '@type'           => 'ItemList',
      '@id'             => $enc['id'],
      'name'            => $enc['name'],
      'numberOfItems'   => count( $items ),
      'itemListElement' => $items,
    );
  }

  $ld_json = array( '@context' => 'https://schema.org', '@graph' => $graph );
?>
<script type="application/ld+json"><?php echo wp_json_encode( $ld_json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?></script>
<?php endif; ?>
