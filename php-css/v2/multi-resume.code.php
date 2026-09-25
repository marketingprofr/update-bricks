<?php
/* =====================================================================
   MEILLEURTEST — MULTI-COMPARATIF (V2) : encarts « résumé »
   Remplace top5-resume.code.php dans le template Bricks multi-comparatif.
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   CSS : v2/multi-resume.css (fichier COMPLET = top5-resume.css + ajouts V2)
   à coller dans l'onglet CSS du même élément.

   - Sans sous-comparatif (champ Relation vide) : rendu identique au V1.
   - Avec sous-comparatifs : encart principal, puis 1 encart par
     sous-comparatif (H2 + intro + cartes), ancre stable (#9000-btu).
   - Liens « Lire l'avis complet » -> #test-{slug} (test complet unique).
   - Moteur multi-comparatif inclus dans ce bloc (aucun snippet WPCodeBox).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — noms des champs ACF (note clients) + tag Amazon
   --------------------------------------------------------------------- */
$T5_CUST_RATING_FIELD = 'mltv5_score_avis_clients';    // note clients /5 (étoile)
$T5_CUST_COUNT_FIELD  = 'mltv5_nombre_avis_clients';   // nombre d'avis clients
$T5_AMAZON_TAG        = 'mlt00-21';                     // tag affilié Amazon

/* ---------------------------------------------------------------------
   Helpers (déclarés une fois)
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
  /* Extrait le nom du marchand depuis le domaine d'une URL.
     https://cdiscount.fr/sdf -> "Cdiscount" ; amazon.fr -> "Amazon". */
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
  /* Joint des chaînes : "A", "A et B", "A, B et C". */
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
  /* Repeater de points -> tableau de chaînes nettoyées. */
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
if ( ! function_exists( 'mt_all_scored_avis' ) ) {
  /**
   * Tous les avis publiés partageant le même post-type-produit (et au moins
   * les mêmes post-type-attribut) que le comparatif, triés par score desc.
   * Renvoie count (filtré), total (non filtré), min/max scores, items[].
   * Résultat en cache statique (1 appel = 1 query).
   */
  function mt_all_scored_avis( $page_id ) {
    static $cache = array();
    $key = (int) $page_id;
    if ( isset( $cache[ $key ] ) ) { return $cache[ $key ]; }

    $empty = array( 'count' => 0, 'min' => 0, 'max' => 0, 'items' => array(), 'total' => 0 );

    $prod_terms = get_the_terms( $key, 'post-type-produit' );
    if ( ! is_array( $prod_terms ) || empty( $prod_terms ) ) {
      $cache[ $key ] = $empty;
      return $empty;
    }
    $prod_ids = wp_list_pluck( $prod_terms, 'term_id' );

    $tax_q = array( array( 'taxonomy' => 'post-type-produit', 'terms' => $prod_ids ) );
    $attr_terms = get_the_terms( $key, 'post-type-attribut' );
    if ( is_array( $attr_terms ) && ! empty( $attr_terms ) ) {
      $tax_q['relation'] = 'AND';
      $tax_q[] = array( 'taxonomy' => 'post-type-attribut', 'terms' => wp_list_pluck( $attr_terms, 'term_id' ), 'operator' => 'AND' );
    }

    $q = new WP_Query( array(
      'post_type' => 'avis', 'post_status' => 'publish',
      'tax_query' => $tax_q,
      'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
      'update_post_meta_cache' => true, 'update_post_term_cache' => false,
    ) );
    $avis_ids    = $q->posts;
    $total_count = count( $avis_ids );

    if ( empty( $avis_ids ) ) {
      $cache[ $key ] = $empty;
      return $empty;
    }

    $items = array();
    $seen_ids = array();
    foreach ( $avis_ids as $aid ) {
      $aid = (int) $aid;
      if ( isset( $seen_ids[ $aid ] ) ) { continue; }
      $seen_ids[ $aid ] = true;
      if ( get_post_status( $aid ) !== 'publish' ) { continue; }
      $raw = get_field( 'mltv5_score_recent', $aid );
      $s10 = round( mt5_num( $raw ) / 10, 1 );
      if ( $s10 <= 0 ) { continue; }
      $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $aid ) );
      $brand = trim( (string) get_field( 'mltv5_marque_du_produit', $aid ) );
      $model = trim( (string) get_field( 'mltv5_modele_du_produit', $aid ) );
      if ( $forced !== '' ) { $name = $forced; $brand = ''; }
      else { $name = $model !== '' ? $model : get_the_title( $aid ); }
      $items[] = array( 'id' => $aid, 'brand' => $brand, 'name' => $name, 'score' => $s10 );
    }

    usort( $items, function ( $a, $b ) {
      if ( $a['score'] !== $b['score'] ) { return ( $b['score'] > $a['score'] ) ? 1 : -1; }
      return strcmp( $a['name'], $b['name'] );
    } );

    foreach ( $items as $i => &$it ) { $it['rank'] = $i + 1; }
    unset( $it );

    $scores = array_column( $items, 'score' );
    $result = array(
      'count' => count( $items ),
      'min'   => ! empty( $scores ) ? min( $scores ) : 0,
      'max'   => ! empty( $scores ) ? max( $scores ) : 0,
      'items' => $items,
      'total' => $total_count,
    );

    $cache[ $key ] = $result;
    return $result;
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

/* ---------------------------------------------------------------------
   V2 — Collecte d'un encart (identique à la PASSE 1 du V1, en fonction pour
   être rejouée par chaque sous-comparatif). Contexte post requis pour les
   helpers de score (setup_postdata), restauré en sortie.
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mtv2_resume_collect' ) ) {
  function mtv2_resume_collect( $ids, $cfg ) {
    global $post;
    $saved = $post;

    $products     = array();
    $count_price  = 0;
    $count_rating = 0;
    $pos          = 0;

    foreach ( $ids as $pid ) {
      $p = get_post( $pid );
      if ( ! $p ) { continue; }
      $post = $p;
      setup_postdata( $post );
      $pos++;

      /* Identité */
      $forced  = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
      $brand   = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model   = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $forced !== '' ) { $name = $forced; $brand = ''; }
      else { $name = $model !== '' ? $model : get_the_title( $pid ); }
      $tagline = trim( (string) get_field( 'mltv5_sous_titre', $pid ) );
      $summary = trim( (string) get_field( 'mltv5_resume_produit', $pid ) );

      /* Image : featured en priorite, sinon URL externe ACF (hotlink partenaire) */
      $img = get_the_post_thumbnail_url( $pid, 'medium' );
      if ( ! $img ) {
        $ext = get_field( 'mltv5_image_external_url', $pid );
        if ( is_array( $ext ) ) { $ext = isset( $ext['url'] ) ? $ext['url'] : ''; }
        $ext = trim( (string) $ext );
        if ( $ext !== '' ) { $img = $ext; }
      }

      /* Score rédac /10 + libellés */
      $score10 = function_exists( 'get_acf_score_divided_by_10' )
        ? get_acf_score_divided_by_10()
        : round( mt5_num( get_field( 'mltv5_score_recent', $pid ) ) / 10, 1 );
      $score_tag  = function_exists( 'get_acf_score_label' ) ? get_acf_score_label() : '';
      $prod_label = function_exists( 'get_default_product_label' )
        ? trim( (string) get_default_product_label( $pid, get_field( 'mltv5_score_recent', $pid ) ) )
        : '';

      /* Note clients (champs ACF du produit) */
      $cust_rating = trim( (string) get_field( $cfg['cust_rating'], $pid ) );
      $cust_count  = trim( (string) get_field( $cfg['cust_count'], $pid ) );

      /* Points + / - (max 2 / 1) */
      $pros = array_slice( mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' ), 0, 2 );
      $cons = array_slice( mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' ), 0, 1 );

      /* Offres : lien ASIN Amazon prioritaire, sinon liens perso ACF */
      $asin      = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
      $prix      = get_field( 'mltv5_prix_indicatif', $pid );
      $has_price = ( $prix !== '' && $prix !== null && mt5_num( $prix ) > 0 );

      $offer_urls   = array();
      $btn_fallback = '';
      if ( $asin !== '' ) {
        $offer_urls[] = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $cfg['amazon_tag'];
      }
      for ( $i = 1; $i <= 3; $i++ ) {
        $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
        $t = trim( (string) get_field( 'mltv5_texte_du_bouton_' . $i, $pid ) );
        if ( $u !== '' && strpos( $u, 'http' ) === 0 ) {
          $offer_urls[] = $u;
          if ( $btn_fallback === '' && $t !== '' ) { $btn_fallback = $t; }
        }
      }
      $offer_urls = array_values( array_unique( $offer_urls ) );

      $primary_merchant = '';
      if ( ! empty( $offer_urls ) ) {
        $primary_merchant = $asin !== '' ? 'Amazon' : mt5_merchant_name( $offer_urls[0] );
      }

      $has_rating = ( trim( (string) $cust_rating ) !== '' && mt5_num( $cust_rating ) > 0 );
      if ( $has_price )  { $count_price++; }
      if ( $has_rating ) { $count_rating++; }

      $products[] = array(
        'pid'         => (int) $pid,
        'pos'         => $pos,
        'name'        => $name,
        'brand'       => $brand,
        'tagline'     => $tagline,
        'summary'     => $summary,
        'label'       => $prod_label,
        'img'         => $img,
        'score10'     => $score10,
        'score_tag'   => $score_tag,
        'cust_rating' => $cust_rating,
        'cust_count'  => $cust_count,
        'pros'        => $pros,
        'cons'        => $cons,
        'offer_urls'  => $offer_urls,
        'primary_url' => ! empty( $offer_urls ) ? $offer_urls[0] : '',
        'cta_text'    => $primary_merchant !== '' ? 'Voir sur ' . $primary_merchant : ( $btn_fallback !== '' ? $btn_fallback : "Voir l'offre" ),
        'has_price'   => $has_price,
        'price_num'   => mt5_num( $prix ),
        'rating_num'  => mt5_num( $cust_rating ),
        'modified'    => (int) get_post_modified_time( 'U', true, $pid ),
        'tag_oos'     => has_tag( 'OOS', $pid ),
        'tag_disco'   => has_tag( 'DISCO', $pid ),
      );
    }
    $post = $saved;
    wp_reset_postdata();

    return array( 'products' => $products, 'count_price' => $count_price, 'count_rating' => $count_rating );
  }
}

/* ---------------------------------------------------------------------
   V2 — Barre de tri + liste de cartes d'un encart (markup identique au V1).
   $uid  : préfixe d'id unique par encart (le V1 = « mt-top5 »)
   $plan : plan multi-comparatif (null = liens V1 #produit-n-{rang})
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mtv2_resume_list' ) ) {
  function mtv2_resume_list( $data, $uid, $plan ) {
    $products    = $data['products'];
    /* Onglets de tri (conditionnels) — mêmes règles que le V1
       - Prix : au moins 3 produits avec prix
       - Avis clients : au moins 3 produits avec note clients
       - Aucun des deux -> bouton "Le plus récent" (tri par date de modif) */
    $show_price  = ( $data['count_price'] >= 3 );
    $show_rating = ( $data['count_rating'] >= 3 );
    $show_recent = ( ! $show_price && ! $show_rating );
    $can_edit    = current_user_can( 'edit_posts' );
?>
  <div class="t5-bar">
    <span class="lbl" id="<?php echo esc_attr( $uid ); ?>-sortlbl">Trier par</span>
    <div class="t5-tabs" role="group" aria-labelledby="<?php echo esc_attr( $uid ); ?>-sortlbl">
      <button type="button" class="t5-tab" aria-pressed="true" data-sort="rank"><span class="ico" aria-hidden="true">&#9733;</span> Notre s&eacute;lection</button>
      <?php if ( $show_price ) : ?>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="price"><span class="ico" aria-hidden="true">&euro;</span> Prix</button>
      <?php endif; ?>
      <?php if ( $show_rating ) : ?>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="rating"><span class="ico" aria-hidden="true">&#9829;</span> Avis des clients</button>
      <?php endif; ?>
      <?php if ( $show_recent ) : ?>
      <button type="button" class="t5-tab" aria-pressed="false" data-sort="recent"><span class="ico" aria-hidden="true">&#8635;</span> Le plus r&eacute;cent</button>
      <?php endif; ?>
    </div>
    <a class="t5-howto" href="#methodologie">Comment nous &eacute;valuons <span class="arr" aria-hidden="true">&rarr;</span></a>
  </div>
  <p class="sr-only" role="status" data-t5-status></p>

  <ol class="t5-list" data-t5-list>
<?php foreach ( $products as $it ) :
  $href = $plan ? mtv2_product_href( $it['pid'], $plan ) : '#produit-n-' . $it['pos'];
?>
    <li class="t5-item<?php echo ( trim( (string) $it['cust_rating'] ) === '' ? ' t5-no-cust' : '' ); ?>" data-rank="<?php echo esc_attr( $it['pos'] ); ?>" data-price="<?php echo esc_attr( $it['price_num'] ); ?>" data-rating="<?php echo esc_attr( $it['rating_num'] ); ?>" data-modified="<?php echo esc_attr( $it['modified'] ); ?>">
      <article class="t5-card<?php if ( $can_edit ) { if ( $it['tag_disco'] ) { echo ' t5-admin-disco'; } elseif ( $it['tag_oos'] ) { echo ' t5-admin-oos'; } } ?>"><?php if ( $can_edit && ( $it['tag_oos'] || $it['tag_disco'] ) ) : ?><span class="t5-admin-tag"><?php echo $it['tag_disco'] ? 'DISCO' : 'OOS'; ?></span><?php endif; ?>
        <p class="t5-banner"><span class="b-num">N&deg;<?php echo $it['pos']; ?></span> <span class="b-label">Le choix de la r&eacute;daction</span></p>
        <span class="t5-rnum" aria-hidden="true"><?php echo $it['pos']; ?></span>
        <div class="t5-media">
          <div class="ph">
            <?php if ( $it['img'] ) : ?>
              <img src="<?php echo esc_url( $it['img'] ); ?>" alt="<?php echo esc_attr( $it['name'] ); ?>" loading="lazy" decoding="async">
            <?php else : ?>
              <span class="ph-cap"><?php echo esc_html( $it['name'] ); ?></span>
            <?php endif; ?>
            <span class="t5-laurel" aria-label="Meilleur choix"><i class="t5-laurel-ic" aria-hidden="true"></i></span>
          </div>
        </div>
        <div class="t5-body">
          <?php if ( $it['brand'] !== '' ) : ?><p class="t5-eyebrow"><?php echo esc_html( $it['brand'] ); ?></p><?php endif; ?>
          <h3 class="t5-name"><a href="<?php echo esc_attr( $href ); ?>"><?php if ( $it['brand'] !== '' ) : ?><span class="t5-brand-inline"><?php echo esc_html( $it['brand'] ); ?> </span><?php endif; ?><?php echo esc_html( $it['name'] ); ?></a></h3>
          <?php if ( $it['label'] !== '' ) : ?><p class="t5-label"><?php echo esc_html( $it['label'] ); ?></p><?php endif; ?>
          <?php if ( $it['summary'] !== '' ) : ?><p class="t5-summary"><?php echo wp_kses_post( $it['summary'] ); ?></p><?php endif; ?>
          <?php if ( $it['pros'] || $it['cons'] ) : ?>
          <ul class="t5-pc">
            <?php foreach ( $it['pros'] as $p ) : ?>
              <li class="pro"><span class="sr-only">Avantage : </span><?php echo esc_html( $p ); ?></li>
            <?php endforeach; ?>
            <?php foreach ( $it['cons'] as $c ) : ?>
              <li class="con"><span class="sr-only">Inconv&eacute;nient : </span><?php echo esc_html( $c ); ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <div class="t5-rm-wrap"><a class="t5-readmore" href="<?php echo esc_attr( $href ); ?>"><span class="t5-rm-score"><?php echo esc_html( number_format( (float) $it['score10'], 1, ',', '' ) ); ?>/10</span> Lire l'avis complet <span class="arr" aria-hidden="true">&darr;</span></a></div>
        </div>
        <div class="t5-aside">
          <div class="t5-ratings">
            <div class="t5-ed">
              <span class="r-lbl">Notre note</span>
              <div class="r-line"><span class="n"><?php echo esc_html( number_format( (float) $it['score10'], 1, ',', '' ) ); ?><small>/10</small></span><?php if ( $it['score_tag'] !== '' ) : ?><span class="tag"><?php echo esc_html( $it['score_tag'] ); ?></span><?php endif; ?></div>
            </div>
            <?php if ( trim( (string) $it['cust_rating'] ) !== '' ) : ?>
            <div class="t5-cust">
              <span class="r-lbl">Avis clients</span>
              <span class="stars" aria-hidden="true">&#9733;</span> <span class="cust-val"><?php echo esc_html( number_format( mt5_num( $it['cust_rating'] ), 1, ',', '' ) ); ?></span>
              <?php $cl = mt5_reviews_label( $it['cust_count'] ); if ( $cl !== '' ) : ?><span class="cust-count">&middot; <?php echo esc_html( $cl ); ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="t5-buy">
            <?php if ( $it['primary_url'] !== '' ) : ?>
            <a class="t5-cta" href="<?php echo esc_url( $it['primary_url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $it['cta_text'] ); ?><span class="sr-only"> &mdash; <?php echo esc_attr( $it['name'] ); ?> (lien commercial)</span> <span class="arr" aria-hidden="true">&rarr;</span></a>
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
            <div class="t5-merchants">ou sur <?php echo mt5_join_et( $links ); ?></div>
            <?php endif; ?>
          </div>
        </div>
      </article>
    </li>
<?php endforeach; ?>
  </ol>
<?php
  }
}

/* ---------------------------------------------------------------------
   Plan multi-comparatif (moteur ci-dessus).
   --------------------------------------------------------------------- */
$page_id = get_the_ID();
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$plan    = mtv2_plan( $page_id );

/* Liste ordonnée des produits du guide principal (même sourcing que le V1) */
if ( $plan ) {
  $ids = $plan['main']['ids'];
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

$t5_cfg = array(
  'cust_rating' => $T5_CUST_RATING_FIELD,
  'cust_count'  => $T5_CUST_COUNT_FIELD,
  'amazon_tag'  => $T5_AMAZON_TAG,
);

$main_data = mtv2_resume_collect( $ids, $t5_cfg );
$products  = $main_data['products'];
if ( empty( $products ) ) { return; }

/* ---------------------------------------------------------------------
   Titre dynamique : "Les {n} {meilleures/meilleurs} {type} en un coup d'œil"
   --------------------------------------------------------------------- */
$nb        = count( $products );
$mf        = isset( $page_tv['masculinsfeminins'] ) ? trim( (string) $page_tv['masculinsfeminins'] ) : '';
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';
if ( $mf === '' ) { $mf = 'meilleures'; }
$head_title = 'Les ' . $nb . ' ' . esc_html( lcfirst( $mf ) )
  . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : '' )
  . ' en un coup d&rsquo;&oelig;il';

/* Classement complet : tous les avis du même type/attribut */
$all_avis      = mt_all_scored_avis( $page_id );
$real_count    = $all_avis['count'];
$show_ranking  = ( $real_count >= 10 );
$show_range    = ( $real_count > $nb && $all_avis['min'] < $all_avis['max'] );
$display_count = ( $real_count < 10 ) ? $real_count + 5 : $real_count;
$display_min   = ( $real_count < 10 ) ? max( 0.1, $all_avis['min'] - 0.5 ) : $all_avis['min'];
$display_max   = $all_avis['max'];

$is_multi   = $plan && $plan['is_multi'];
$disclosure = '<p class="t5-disclosure">Liens commerciaux : Meilleurtest peut percevoir une commission sur les achats effectu&eacute;s via ces liens, sans impact sur le prix ni sur nos verdicts.</p>';
$names_line = function ( $list ) {
  return esc_html( implode( ' - ', array_map( function( $p ) { return ( $p['brand'] !== '' ? $p['brand'] . ' ' : '' ) . $p['name']; }, $list ) ) );
};

?>
<div class="mt-top5" aria-labelledby="mt-top5-title">
<?php if ( $plan ) { echo mtv2_admin_panel( $plan ); } ?>
  <header class="t5-head">
    <div>
      <h2 class="t5-h2" id="mt-top5-title"><?php echo $head_title; ?></h2>
<?php if ( $show_range ) : ?>
      <p class="t5-range">Scores de <b><?php echo esc_html( number_format( $display_min, 1, ',', '' ) ); ?> &agrave; <?php echo esc_html( number_format( $display_max, 1, ',', '' ) ); ?> sur <?php echo (int) $display_count; ?> produits</b><?php if ( $display_count > 50 ) { $mt_years = (int) date_i18n('Y') - 2014; echo ' analys&eacute;s en ' . $mt_years . '&nbsp;ans'; } ?>.<?php if ( $show_ranking ) : ?> Classement complet disponible sous notre s&eacute;lection.<?php else : ?> Seuls les <?php echo $nb; ?> meilleurs figurent dans notre s&eacute;lection.<?php endif; ?></p>
<?php endif; ?>
<?php if ( current_user_can( 'edit_posts' ) ) : ?>
      <p class="t5-admin-debug" style="margin:6px 0 0;font-size:11px;color:#888;font-style:italic"><?php echo $names_line( $products ); ?></p>
<?php endif; ?>
    </div>
  </header>

<?php mtv2_resume_list( $main_data, 'mt-top5', $plan ); ?>

<?php if ( $show_ranking ) : ?>
  <div class="t5-allrank" data-page="<?php echo esc_attr( $page_id ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'mt_ranking' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
    <h3 class="t5-allrank-h" style="display:none">Classement complet<?php echo ( $type_plur !== '' ? ' des ' . esc_html( $type_plur ) : '' ); ?> test&eacute;s</h3>
    <div class="t5-ar-head" style="display:none"><span>#</span><span>Produit</span><span>Note</span></div>
    <div class="t5-ar-body"></div>
    <button type="button" class="t5-ar-toggle" data-label-hide="Masquer le classement">Voir le classement complet : <span class="t5-ar-u">afficher les <?php echo (int) $all_avis['count']; ?> produits test&eacute;s</span></button>
  </div>
<?php endif; ?>

<?php if ( ! $is_multi ) { echo '  ' . $disclosure . "\n"; } ?>
</div>
<?php
/* ---------------------------------------------------------------------
   V2 — Un encart par sous-comparatif (ordre du champ Relation) :
   H2 propre + intro + même encart résumé, ancre stable (#9000-btu).
   Chaque encart garde SON classement ; la note reste celle du produit.
   --------------------------------------------------------------------- */
if ( $is_multi ) :
  foreach ( $plan['subs'] as $si => $sub ) :
    $sub_data = mtv2_resume_collect( $sub['ids'], $t5_cfg );
    if ( empty( $sub_data['products'] ) ) { continue; }
    $aid = $sub['anchor'];
?>
<section class="mt-top5 mtv2-sub" id="<?php echo esc_attr( $aid ); ?>" aria-labelledby="<?php echo esc_attr( $aid ); ?>-title">
  <header class="t5-head">
    <div>
<?php
    /* Éditeurs connectés : titre cliquable -> écran d'édition du sous-comparatif
       (nouvel onglet). Jamais rendu pour les visiteurs. */
    $sub_edit = current_user_can( 'edit_post', $sub['id'] ) ? (string) get_edit_post_link( $sub['id'], 'raw' ) : '';
?>
      <h2 class="t5-h2" id="<?php echo esc_attr( $aid ); ?>-title"><?php if ( $sub_edit !== '' ) : ?><a class="mtv2-edit-link" href="<?php echo esc_url( $sub_edit ); ?>" target="_blank" rel="noopener" title="Modifier ce sous-comparatif"><?php echo esc_html( $sub['title'] ); ?></a><?php else : ?><?php echo esc_html( $sub['title'] ); ?><?php endif; ?></h2>
      <?php echo $sub['intro']; // HTML assaini dans mtv2_intro_html() ?>
<?php if ( current_user_can( 'edit_posts' ) ) : ?>
      <p class="t5-admin-debug" style="margin:6px 0 0;font-size:11px;color:#888;font-style:italic"><?php echo $names_line( $sub_data['products'] ); ?></p>
<?php endif; ?>
    </div>
  </header>

<?php mtv2_resume_list( $sub_data, $aid, $plan ); ?>

</section>
<?php
  endforeach;
?>
<div class="mt-top5 mtv2-end">
  <?php echo $disclosure; ?>
</div>
<?php
endif;
?>
<?php
add_action( 'wp_footer', function () {
  static $done = false;
  if ( $done ) { return; }
  $done = true;
?>
<script>
(function () {
  var roots = document.querySelectorAll('.mt-top5');
  roots.forEach(function (root) {
    if (root.dataset.t5Init) return;
    root.dataset.t5Init = '1';
    var list = root.querySelector('[data-t5-list]');
    if (!list) return; /* V2 : bloc sans liste (mention finale) */
    var tabs = root.querySelectorAll('.t5-tab');
    var status = root.querySelector('[data-t5-status]');
    var items = Array.prototype.slice.call(list.querySelectorAll('.t5-item'));
    var labels = { rank: 'notre sélection', price: 'prix croissant', rating: 'avis des clients', recent: 'le plus récent' };
    var bannerLabels = { rank: 'Le choix de la rédaction', price: 'Le meilleur pas cher', rating: 'Le choix de la communauté', recent: 'Le plus récent' };
    function num(el, key) { return parseFloat(el.getAttribute('data-' + key)) || 0; }
    var comparators = {
      rank:   function (a, b) { return num(a, 'rank') - num(b, 'rank'); },
      price:  function (a, b) { return num(a, 'price') - num(b, 'price'); },
      rating: function (a, b) { return num(b, 'rating') - num(a, 'rating'); },
      recent: function (a, b) { return num(b, 'modified') - num(a, 'modified'); }
    };
    function sortBy(key) {
      var first = items.map(function (c) { return c.getBoundingClientRect().top; });
      var ordered = items.slice().sort(comparators[key] || comparators.rank);
      var banner = bannerLabels[key] || bannerLabels.rank;
      ordered.forEach(function (c, i) {
        list.appendChild(c);
        var pos = i + 1;
        var bnum = c.querySelector('.t5-banner .b-num');
        if (bnum) bnum.textContent = 'N°' + pos;
        var blabel = c.querySelector('.t5-banner .b-label');
        if (blabel) blabel.textContent = banner;
        var rnum = c.querySelector('.t5-rnum');
        if (rnum) rnum.textContent = pos;
      });
      var moved = [];
      items.forEach(function (c, i) {
        var dy = first[i] - c.getBoundingClientRect().top;
        if (!dy) return;
        c.style.transition = 'none';
        c.style.transform = 'translateY(' + dy + 'px)';
        moved.push(c);
      });
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          moved.forEach(function (c) {
            c.style.transition = 'transform .45s cubic-bezier(.22,.61,.36,1)';
            c.style.transform = '';
          });
        });
      });
      setTimeout(function () { moved.forEach(function (c) { c.style.transition = ''; c.style.transform = ''; }); }, 600);
      if (status) status.textContent = 'Classement trié par ' + (labels[key] || labels.rank) + '.';
    }
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.setAttribute('aria-pressed', 'false'); });
        tab.setAttribute('aria-pressed', 'true');
        sortBy(tab.getAttribute('data-sort'));
      });
    });
  });
  document.querySelectorAll('.t5-allrank').forEach(function (wrap) {
    var btn = wrap.querySelector('.t5-ar-toggle');
    var body = wrap.querySelector('.t5-ar-body');
    if (!btn || !body) return;
    var loaded = false;
    var visible = false;
    var heading = wrap.querySelector('.t5-allrank-h');
    var colHead = wrap.querySelector('.t5-ar-head');
    var showHtml = btn.innerHTML;
    var hideText = btn.getAttribute('data-label-hide') || 'Masquer le classement';
    btn.addEventListener('click', function () {
      if (!loaded) {
        btn.disabled = true;
        btn.textContent = 'Chargement…';
        var url = wrap.getAttribute('data-ajax')
          + '?action=mt_ranking&page_id=' + encodeURIComponent(wrap.getAttribute('data-page'))
          + '&_n=' + encodeURIComponent(wrap.getAttribute('data-nonce'));
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.onload = function () {
          btn.disabled = false;
          try {
            var res = JSON.parse(xhr.responseText);
            if (res.success && res.data && res.data.html) {
              body.innerHTML = res.data.html;
              loaded = true;
              visible = true;
              body.style.display = '';
              if (heading) heading.style.display = '';
              if (colHead) colHead.style.display = '';
              wrap.classList.add('is-open');
              btn.textContent = hideText;
            } else {
              btn.textContent = 'Erreur de chargement';
            }
          } catch (e) {
            btn.textContent = 'Erreur de chargement';
          }
        };
        xhr.onerror = function () {
          btn.disabled = false;
          btn.innerHTML = showHtml;
        };
        xhr.send();
      } else {
        visible = !visible;
        body.style.display = visible ? '' : 'none';
        if (heading) heading.style.display = visible ? '' : 'none';
        if (colHead) colHead.style.display = visible ? '' : 'none';
        wrap.classList.toggle('is-open', visible);
        if (visible) { btn.textContent = hideText; } else { btn.innerHTML = showHtml; }
      }
    });
  });
})();
</script>
<?php }, 99 ); ?>
