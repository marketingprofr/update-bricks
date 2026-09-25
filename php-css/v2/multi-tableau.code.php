<?php
/* =====================================================================
   MEILLEURTEST — MULTI-COMPARATIF (V2) : tableau comparatif
   Remplace tableau-comparatif.code.php dans le template Bricks
   multi-comparatif. À coller dans UN SEUL élément CODE Bricks.
   CSS : v2/multi-tableau.css (fichier COMPLET = tableau-comparatif.css +
   ajouts V2) à coller dans l'onglet CSS du même élément.

   - Sans sous-comparatif : rendu identique au V1 (top 5 du guide).
   - Avec sous-comparatifs : TOUS les produits (principal puis
     sous-comparatifs, sans doublon, même liste que les tests complets).
     Les caractéristiques techniques affichées sont choisies UNIQUEMENT sur
     les produits du guide principal (partagées par >= N d'entre eux) ; les
     produits des sous-comparatifs affichent leur valeur s'ils l'ont, sinon —.
     Banderoles (meilleur choix / prix / alternative) : guide principal seul.
   - Liens nom/vignette -> #test-{slug} (test complet).
   - Moteur multi-comparatif inclus dans ce bloc (aucun snippet WPCodeBox).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — noms des champs ACF + tag Amazon
   (specs : noms éprouvés de l'ancien tableau de prod, à conserver tels quels)
   --------------------------------------------------------------------- */
$TC_SPECS_FIELD       = 'mltv5_caracteristiques_du_produit';      // repeater fiche technique
$TC_SPEC_LABEL_KEY    = 'mltv5_caracteristique_produit';          // sous-champ intitulé
$TC_SPEC_VALUE_KEY    = 'mltv5_valeur_caracteristique_produit';   // sous-champ valeur
$TC_CUST_RATING_FIELD = 'mltv5_score_avis_clients';               // note clients /5
$TC_CUST_COUNT_FIELD  = 'mltv5_nombre_avis_clients';              // nombre d'avis clients
$TC_SPEC_MIN_SHARE    = 3;   // une caractéristique n'est affichée que si partagée par >= N produits
$TC_SPEC_VISIBLE      = 10;  // au-delà : repliées derrière le bouton « afficher plus »
$TC_MAX_PRODUCTS      = 5;
$TC_AMAZON_TAG        = 'mlt00-21';

/* ---------------------------------------------------------------------
   Helpers (partagés avec top5-resume / top5-tests — guards anti-redéclaration)
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
if ( ! function_exists( 'mtc_score_label' ) ) {
  /* Libellé de note /10 (fallback si get_acf_score_label() indisponible). */
  function mtc_score_label( $s10 ) {
    $s = (float) $s10;
    if ( $s >= 9 ) { return 'Exceptionnel'; }
    if ( $s >= 8 ) { return 'Excellent'; }
    if ( $s >= 7 ) { return 'Tr&egrave;s bien'; }
    if ( $s >= 6 ) { return 'Bien'; }
    if ( $s >= 5 ) { return 'Moyen'; }
    if ( $s >= 4 ) { return 'Passable'; }
    if ( $s >= 2 ) { return 'Mauvais'; }
    return 'Tr&egrave;s mauvais';
  }
}
if ( ! function_exists( 'mtc_score_level' ) ) {
  /* Couleur monochrome de la jauge selon la note /10 :
     >=9 primary | >=8 vert | >=7 jaune | >=6 orange | sinon rouge. */
  function mtc_score_level( $s10 ) {
    $s = (float) $s10;
    if ( $s >= 9 ) { return 'p'; }
    if ( $s >= 8 ) { return 'g'; }
    if ( $s >= 7 ) { return 'y'; }
    if ( $s >= 6 ) { return 'o'; }
    return 'r';
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

/* ---------------------------------------------------------------------
   Liste ordonnée des produits
   - Sans sous-comparatif : top 5 du guide (V1).
   - Multi-comparatif : liste des tests complets (principal puis
     sous-comparatifs, sans doublon, limitée à MTV2_MAX_TESTS).
   --------------------------------------------------------------------- */
$page_id  = get_the_ID();
$page_tv  = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$plan     = mtv2_plan( $page_id );
$is_multi = $plan && $plan['is_multi'];

if ( $plan ) {
  $main_ids = array_slice( $plan['main']['ids'], 0, $TC_MAX_PRODUCTS );
  $ids      = $is_multi ? $plan['tests'] : $main_ids;
  if ( ! $is_multi ) { $main_ids = $ids; }
} else {
  $ids = isset( $page_tv['top_avis_ids'] ) && is_array( $page_tv['top_avis_ids'] ) ? $page_tv['top_avis_ids'] : array();
  if ( empty( $ids ) ) {
    $rel = get_field( 'mltv5_best_products', $page_id ); // fallback : champ relation
    if ( is_array( $rel ) ) {
      foreach ( $rel as $r ) { $ids[] = is_object( $r ) ? $r->ID : (int) $r; }
    }
  }
  $ids      = array_values( array_filter( array_map( 'intval', $ids ) ) );
  $ids      = array_slice( $ids, 0, $TC_MAX_PRODUCTS );
  $main_ids = $ids;
}
if ( count( $ids ) < 2 ) { return; } // un comparatif a besoin d'au moins 2 produits

/* Rang dans le guide principal (banderoles, médailles, specs de référence) */
$main_rank = array();
if ( $plan ) {
  foreach ( $plan['main']['ids'] as $i => $mid ) { $main_rank[ (int) $mid ] = $i + 1; }
} else {
  foreach ( $main_ids as $i => $mid ) { $main_rank[ (int) $mid ] = $i + 1; }
}

/* ---------------------------------------------------------------------
   PASSE 1 : collecte (contexte post requis pour les helpers de score)
   --------------------------------------------------------------------- */
global $post;
$tc_saved_post = $post;

$products    = array();
$specs_order = array(); // intitulés dans l'ordre de première apparition
$specs_count = array(); // intitulé => nb de produits qui le renseignent
$pos         = 0;

foreach ( $ids as $pid ) {
  $p = get_post( $pid );
  if ( ! $p ) { continue; }
  $post = $p;
  setup_postdata( $post );
  $pos++;
  $is_main = isset( $main_rank[ (int) $pid ] );

  /* Identité (marque + modèle séparés pour l'affichage centré) */
  $brand   = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
  $model   = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
  $modname = $model !== '' ? $model : get_the_title( $pid );
  $name    = trim( $brand . ' ' . $modname );

  /* Image : featured en priorite, sinon URL externe ACF (hotlink partenaire) */
  $img = get_the_post_thumbnail_url( $pid, 'medium' );
  if ( ! $img ) {
    $ext = get_field( 'mltv5_image_external_url', $pid );
    if ( is_array( $ext ) ) { $ext = isset( $ext['url'] ) ? $ext['url'] : ''; }
    $ext = trim( (string) $ext );
    if ( $ext !== '' ) { $img = $ext; }
  }

  /* Score rédac /10 + libellés (helpers en contexte post) */
  $raw_score = get_field( 'mltv5_score_recent', $pid );
  $score10   = function_exists( 'get_acf_score_divided_by_10' )
    ? (float) get_acf_score_divided_by_10()
    : round( mt5_num( $raw_score ) / 10, 1 );
  $score_tag = function_exists( 'get_acf_score_label' ) ? trim( (string) get_acf_score_label() ) : '';
  if ( $score_tag === '' ) { $score_tag = mtc_score_label( $score10 ); }
  $label = function_exists( 'get_default_product_label' )
    ? trim( (string) get_default_product_label( $pid, $raw_score ) )
    : '';

  /* Avis clients (note /5 + nombre) */
  $cust_rating = trim( (string) get_field( $TC_CUST_RATING_FIELD, $pid ) );
  $cust_count  = trim( (string) get_field( $TC_CUST_COUNT_FIELD, $pid ) );

  /* Résumé + points +/- */
  $summary = trim( (string) get_field( 'mltv5_resume_produit', $pid ) );
  $pros    = mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' );
  $cons    = mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' );

  /* Caractéristiques techniques (keyées par intitulé + compteur de partage) */
  $specs = array();
  $seen  = array();
  if ( have_rows( $TC_SPECS_FIELD, $pid ) ) {
    while ( have_rows( $TC_SPECS_FIELD, $pid ) ) {
      the_row();
      $sname = trim( (string) get_sub_field( $TC_SPEC_LABEL_KEY ) );
      $sval  = trim( (string) get_sub_field( $TC_SPEC_VALUE_KEY ) );
      if ( $sname === '' || $sval === '' ) { continue; }
      $specs[ $sname ] = $sval;
      if ( ! $is_main ) { continue; } // V2 : seules les specs du guide principal servent de référence
      if ( ! isset( $specs_count[ $sname ] ) ) { $specs_count[ $sname ] = 0; $specs_order[] = $sname; }
      if ( ! in_array( $sname, $seen, true ) ) { $specs_count[ $sname ]++; $seen[] = $sname; }
    }
  }

  /* Offres : ASIN Amazon prioritaire, puis liens perso ACF (prix = banderole seulement) */
  $asin      = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
  $prix      = get_field( 'mltv5_prix_indicatif', $pid );
  $price_num = mt5_num( $prix );

  $offer_urls = array();
  if ( $asin !== '' ) {
    $offer_urls[] = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $TC_AMAZON_TAG;
  }
  for ( $i = 1; $i <= 3; $i++ ) {
    $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $i, $pid ) );
    if ( $u !== '' && strpos( $u, 'http' ) === 0 ) { $offer_urls[] = $u; }
  }
  $offer_urls = array_values( array_unique( $offer_urls ) );

  /* V2 : rang affiché (principal ; sinon rang dans le sous-comparatif d'origine),
     libellé d'origine, lien vers le test complet */
  $rank_n   = $is_main ? $main_rank[ (int) $pid ] : $pos;
  $origin_l = '';
  if ( ! $is_main && $plan && isset( $plan['seen_in'][ $pid ][0] ) ) {
    $rank_n   = (int) $plan['seen_in'][ $pid ][0]['rank'];
    $origin_l = mtv2_encart_label( $plan['seen_in'][ $pid ][0]['enc'], $plan );
  }

  $products[] = array(
    'pid'         => (int) $pid,
    'is_main'     => $is_main,
    'rank_n'      => $rank_n,
    'disp_n'      => $is_multi ? $pos : $rank_n, // V2 : numéros dans l'ordre des colonnes (1, 2, 3…)
    'rank_cls'    => $is_main ? 'r' . min( 5, $rank_n ) : 'r5',
    'origin'      => $origin_l,
    'href'        => $plan ? mtv2_product_href( $pid, $plan ) : '#produit-n-' . $pos,
    'pos'         => $pos,
    'name'        => $name,
    'brand'       => $brand,
    'modname'     => $modname,
    'img'         => $img,
    'score10'     => $score10,
    'score_tag'   => $score_tag,
    'score_pct'   => max( 0, min( 100, (int) round( $score10 * 10 ) ) ),
    'score_lvl'   => mtc_score_level( $score10 ),
    'cust_rating' => $cust_rating,
    'cust_count'  => $cust_count,
    'label'       => $label,
    'summary'     => $summary,
    'pros'        => $pros,
    'cons'        => $cons,
    'specs'       => $specs,
    'offer_urls'  => $offer_urls,
    'primary_url' => ! empty( $offer_urls ) ? $offer_urls[0] : '',
    'price_num'   => $price_num,
  );
}
$post = $tc_saved_post;
wp_reset_postdata();

if ( count( $products ) < 2 ) { return; }

/* ---------------------------------------------------------------------
   Caractéristiques de référence : partagées par >= TC_SPEC_MIN_SHARE produits,
   dans l'ordre de première apparition (lecture plus naturelle).
   --------------------------------------------------------------------- */
$ref_specs = array();
foreach ( $specs_order as $sname ) {
  if ( $specs_count[ $sname ] >= $TC_SPEC_MIN_SHARE ) { $ref_specs[] = $sname; }
}
$nb_specs        = count( $ref_specs );
$has_hidden_spec = ( $nb_specs > $TC_SPEC_VISIBLE );

/* ---------------------------------------------------------------------
   Banderoles : « Meilleur choix » (rang 1, primary) + « Meilleur prix »
   (le moins cher hors rang 1, autre couleur). Sans prix exploitable ->
   « Meilleure alternative » sur le rang 2.
   --------------------------------------------------------------------- */
$price_rank = null; $cheapest = PHP_INT_MAX; $n_price = 0;
foreach ( $products as $it ) {
  if ( ! $it['is_main'] ) { continue; } // V2 : banderoles calculées sur le guide principal
  if ( $it['price_num'] > 0 ) {
    $n_price++;
    if ( $it['rank_n'] !== 1 && $it['price_num'] < $cheapest ) { $cheapest = $it['price_num']; $price_rank = $it['rank_n']; }
  }
}
$show_price = ( $n_price >= 2 && $price_rank !== null );
$alt_rank   = $show_price ? null : 2;

/* Lignes éditoriales : n'afficher une rangée que si au moins un produit a la donnée */
$any_verdict = false; $any_summary = false; $any_pros = false; $any_cons = false; $any_offer = false; $any_cust = false;
foreach ( $products as $it ) {
  if ( $it['label'] !== '' )     { $any_verdict = true; }
  if ( $it['summary'] !== '' )   { $any_summary = true; }
  if ( ! empty( $it['pros'] ) )  { $any_pros = true; }
  if ( ! empty( $it['cons'] ) )  { $any_cons = true; }
  if ( $it['primary_url'] !== '' ) { $any_offer = true; }
  if ( $it['cust_rating'] !== '' && mt5_num( $it['cust_rating'] ) > 0 ) { $any_cust = true; }
}

/* Libellé de la ligne d'achat : « Où l'acheter » si au moins un prix ACF,
   sinon « Meilleures offres » (classements sans prix : séries, etc.). */
$buy_label = ( $n_price >= 1 ) ? "O&ugrave; l'acheter" : 'Meilleures offres';

/* ---------------------------------------------------------------------
   Titre + sous-titre (adaptés au type de produit)
   --------------------------------------------------------------------- */
$nb        = count( $products );
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';
$head_title = 'Tableau comparatif' . ( $type_plur !== '' ? ' : top ' . $nb . ' ' . esc_html( lcfirst( $type_plur ) ) : '' );
$sub = ( $type_plur !== '' )
  ? 'Nos ' . $nb . ' ' . esc_html( lcfirst( $type_plur ) ) . ', en face &agrave; face sur les crit&egrave;res qui comptent vraiment.'
  : 'Nos ' . $nb . ' laur&eacute;ats, en face &agrave; face sur les crit&egrave;res qui comptent vraiment.';
if ( $is_multi ) {
  /* V2 : « Tableau comparatif : les meilleurs climatiseurs mobiles », sans sous-titre */
  $mf_adj     = isset( $page_tv['masculinsfeminins'] ) ? trim( (string) $page_tv['masculinsfeminins'] ) : '';
  $mf_adj     = ( mb_stripos( $mf_adj, 'meilleures' ) !== false ) ? 'meilleures' : 'meilleurs';
  $head_title = 'Tableau comparatif' . ( $type_plur !== '' ? ' : les ' . $mf_adj . ' ' . esc_html( lcfirst( $type_plur ) ) : '' );
  $sub        = '';
}

$colspan = $nb + 1;
$uid     = 'tc-' . substr( md5( (string) $page_id . '-' . $nb ), 0, 8 );
?>
<section class="mt-cmp-root" id="partie-tableau-comparatif" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
<?php if ( ! empty( $GLOBALS['mtv2_stale_engine'] ) && current_user_can( 'edit_posts' ) ) : ?>
<p class="mtv2-stale" style="margin:0 0 12px;padding:10px 14px;border:2px solid #c0392b;border-radius:8px;background:#fdecea;color:#c0392b;font:600 14px/1.5 Inter,sans-serif">&#9888; Multi-comparatif : une ANCIENNE version du code tourne encore sur cette page. Supprimez le snippet WPCodeBox « mtv2-core » s'il existe, recollez les 4 blocs multi-* (résumé, tests, tableau, sommaire), puis videz le cache. (Message visible des éditeurs uniquement.)</p>
<?php endif; ?>
  <header class="mt-cmp-head">
    <h2 class="mt-cmp-h2" id="<?php echo esc_attr( $uid ); ?>-title"><?php echo $head_title; ?></h2>
    <?php if ( $sub !== '' ) : ?><p class="mt-cmp-sub"><?php echo $sub; ?></p><?php endif; ?>
  </header>

  <p class="mt-cmp-hint" aria-hidden="true">Faites glisser le tableau pour comparer &rarr;</p>

  <div class="mt-cmp-wrap">
    <table class="mt-cmp" style="min-width: <?php echo (int) ( 160 + $nb * 190 ); ?>px;">
      <tbody>

        <!-- Rang (médaille + banderoles) + pastille engagement (1re colonne, rowspan 2) -->
        <tr>
          <td class="row-label brand-cell" rowspan="2">
            <div class="mt-cmp-brand">
              <span class="t5-laurel" aria-label="Notre engagement"><i class="t5-laurel-ic" aria-hidden="true"></i></span>
              <p class="mt-cmp-brand-note">Ce comparatif ne contient aucun produit sponsoris&eacute;</p>
            </div>
          </td>
          <?php foreach ( $products as $it ) :
            $banner = '';
            if ( ! $it['is_main'] )                                   { $banner = ''; }
            elseif ( $it['rank_n'] === 1 )                            { $banner = 'best'; }
            elseif ( $show_price && $it['rank_n'] === $price_rank )   { $banner = 'price'; }
            elseif ( $alt_rank !== null && $it['rank_n'] === $alt_rank ) { $banner = 'alt'; }
          ?>
          <td class="pos-cell">
            <div class="banner-slot">
              <?php if ( $banner === 'best' ) : ?><span class="col-banner b-best">&#9733; Meilleur choix</span>
              <?php elseif ( $banner === 'price' ) : ?><span class="col-banner b-price">&euro; Meilleur prix</span>
              <?php elseif ( $banner === 'alt' ) : ?><span class="col-banner b-alt">&#9829; Meilleure alternative</span>
              <?php endif; ?>
            </div>
            <span class="rank <?php echo esc_attr( $it['rank_cls'] ); ?>"><span class="rank-n"><?php echo (int) $it['disp_n']; ?></span></span>
          </td>
          <?php endforeach; ?>
        </tr>

        <!-- Image + nom (marque au-dessus, modèle en dessous) -->
        <tr>
          <?php foreach ( $products as $it ) : ?>
          <td class="product-cell">
            <a class="product-thumb<?php echo $it['img'] ? '' : ' empty'; ?>" href="<?php echo esc_attr( $it['href'] ); ?>">
              <?php if ( $it['img'] ) : ?><img src="<?php echo esc_url( $it['img'] ); ?>" alt="<?php echo esc_attr( $it['name'] ); ?>" loading="lazy" decoding="async"><?php endif; ?>
            </a>
            <a class="product-name" href="<?php echo esc_attr( $it['href'] ); ?>">
              <?php if ( $it['brand'] !== '' ) : ?><span class="pn-brand"><?php echo esc_html( $it['brand'] ); ?></span><?php endif; ?>
              <span class="pn-model"><?php echo esc_html( $it['modname'] ); ?></span>
            </a>
            <?php if ( $it['origin'] !== '' ) : ?><span class="pn-origin">S&eacute;lection <?php echo esc_html( $it['origin'] ); ?></span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>

        <?php if ( $any_verdict ) : ?>
        <!-- Verdict (texte « quote », primary) -->
        <tr>
          <td class="row-label">Verdict</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="verdict-cell">
            <?php if ( $it['label'] !== '' ) : ?><span class="verdict-txt"><?php echo esc_html( $it['label'] ); ?></span>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <!-- Note globale (jauge monochrome) -->
        <tr>
          <td class="row-label">Note globale</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="score-cell sc-<?php echo esc_attr( $it['score_lvl'] ); ?>">
            <div class="score-block"><b><?php echo esc_html( number_format( (float) $it['score10'], 1, ',', '' ) ); ?></b><small>/10</small></div>
            <div class="score-gauge"><div class="score-gauge-fill" style="width: <?php echo (int) $it['score_pct']; ?>%;"></div></div>
            <?php if ( $it['score_tag'] !== '' ) : ?><div class="score-tag"><?php echo $it['score_tag']; ?></div><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>

        <?php if ( $any_cust ) : ?>
        <!-- Avis clients (note /5 + nombre) -->
        <tr>
          <td class="row-label">Avis clients</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="cust-cell">
            <?php if ( $it['cust_rating'] !== '' && mt5_num( $it['cust_rating'] ) > 0 ) : ?>
              <div class="cust-line"><span class="cust-star" aria-hidden="true">&#9733;</span> <span class="cust-val"><?php echo esc_html( number_format( mt5_num( $it['cust_rating'] ), 1, ',', '' ) ); ?><small>/5</small></span></div>
              <?php $cl = mt5_reviews_label( $it['cust_count'] ); if ( $cl !== '' ) : ?><div class="cust-count"><?php echo esc_html( $cl ); ?></div><?php endif; ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_summary ) : ?>
        <!-- Résumé -->
        <tr>
          <td class="row-label">R&eacute;sum&eacute;</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="pp"><?php echo $it['summary'] !== '' ? wp_kses_post( $it['summary'] ) : '<span class="mt-cmp-empty">&mdash;</span>'; ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_pros ) : ?>
        <!-- Points positifs (vert sombre, puces de séparation) -->
        <tr>
          <td class="row-label">Positif</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="pp pos">
            <?php if ( ! empty( $it['pros'] ) ) : ?>
              <?php echo implode( ' <span class="dot">&bull;</span> ', array_map( 'esc_html', $it['pros'] ) ); ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_cons ) : ?>
        <!-- Points négatifs (rouge sombre, puces de séparation) -->
        <tr>
          <td class="row-label">N&eacute;gatif</td>
          <?php foreach ( $products as $it ) : ?>
          <td class="pp neg">
            <?php if ( ! empty( $it['cons'] ) ) : ?>
              <?php echo implode( ' <span class="dot">&bull;</span> ', array_map( 'esc_html', $it['cons'] ) ); ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $any_offer ) : ?>
        <!-- Achat (bouton + marchands, sans prix) -->
        <tr>
          <td class="row-label"><?php echo $buy_label; ?></td>
          <?php foreach ( $products as $it ) :
            $links = array();
            foreach ( $it['offer_urls'] as $u ) {
              $m = mt5_merchant_name( $u );
              if ( $m !== '' ) { $links[] = '<a href="' . esc_url( $u ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $m ) . '</a>'; }
            }
          ?>
          <td class="buy-cell">
            <?php if ( $it['primary_url'] !== '' ) : ?>
            <a class="cmp-btn" href="<?php echo esc_url( $it['primary_url'] ); ?>" target="_blank" rel="nofollow sponsored noopener">Voir l'offre <span aria-hidden="true">&rarr;</span></a>
            <?php if ( ! empty( $links ) ) : ?><div class="buy-merchants">chez <?php echo mt5_join_et( $links ); ?></div><?php endif; ?>
            <?php else : ?><span class="mt-cmp-empty">&mdash;</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endif; ?>

        <?php if ( $nb_specs > 0 ) : ?>
        <!-- Séparateur caractéristiques techniques -->
        <tr class="mt-cmp-sep"><td class="row-label section-head" colspan="<?php echo (int) $colspan; ?>">Caract&eacute;ristiques techniques</td></tr>

        <?php foreach ( $ref_specs as $i => $sname ) :
          $hidden = ( $i >= $TC_SPEC_VISIBLE );
        ?>
        <tr class="spec-row<?php echo $hidden ? ' spec-hidden' : ''; ?>"<?php echo $hidden ? ' style="display:none;"' : ''; ?> data-uid="<?php echo esc_attr( $uid ); ?>">
          <td class="row-label"><?php echo esc_html( $sname ); ?></td>
          <?php foreach ( $products as $it ) :
            $sv = isset( $it['specs'][ $sname ] ) ? $it['specs'][ $sname ] : '';
          ?>
          <td class="spec-cell"><?php echo $sv !== '' ? wp_kses_post( $sv ) : '<span class="mt-cmp-empty">&mdash;</span>'; ?></td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>

      </tbody>
    </table>
  </div>

  <?php if ( $has_hidden_spec ) : ?>
  <div class="mt-cmp-more">
    <button type="button" class="mt-cmp-more-btn" data-uid="<?php echo esc_attr( $uid ); ?>" aria-expanded="false">
      <span class="more-on">Afficher toutes les caract&eacute;ristiques (<?php echo (int) $nb_specs; ?>)</span>
      <span class="more-off" style="display:none;">Masquer les caract&eacute;ristiques</span>
      <i class="fas fa-chevron-down chevron" aria-hidden="true"></i>
    </button>
  </div>
  <script>
  (function () {
    var uid = <?php echo wp_json_encode( $uid ); ?>;
    function init() {
      var btn = document.querySelector('.mt-cmp-more-btn[data-uid="' + uid + '"]');
      if (!btn) { return; }
      var rows = document.querySelectorAll('.spec-row.spec-hidden[data-uid="' + uid + '"]');
      var on = btn.querySelector('.more-on'), off = btn.querySelector('.more-off'), chev = btn.querySelector('.chevron');
      var open = false;
      btn.addEventListener('click', function () {
        open = !open;
        for (var i = 0; i < rows.length; i++) { rows[i].style.display = open ? 'table-row' : 'none'; }
        if (on)  { on.style.display  = open ? 'none' : 'inline'; }
        if (off) { off.style.display = open ? 'inline' : 'none'; }
        if (chev) { chev.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)'; }
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
  })();
  </script>
  <?php endif; ?>
</section>
