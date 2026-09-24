<?php
/* =====================================================================
   MEILLEURTEST — Multi-comparatif (V2) : CŒUR
   Snippet WPCodeBox (PHP, « Run everywhere »). PAS un bloc Bricks.

   Contient :
   1. Les 2 champs ACF (enregistrés en local, rien à créer à la main) :
      - mltv5_sous_comparatifs      (Relation, sur le comparatif PRINCIPAL)
      - mltv5_intro_sous_comparatif (Texte, sur le comparatif SOUS-comparatif)
   2. Le « plan » de la page : liste principale + sous-comparatifs + liste
      des tests complets dédoublonnée (ordre de première apparition).
   3. L'aperçu admin ?preview_v2=1 (sert le template Bricks multi-comparatif
      à la place du template V1, pour les admins uniquement).
   4. Les avertissements admin (écran d'édition + panneau sur la page).

   Principe : un sous-comparatif est un VRAI comparatif (publié, pour que le
   cache top_avis_ids soit calculé par le batch). On en lit :
   titre forcé / type / attributs / intro / top_avis_ids, via
   get_all_template_variables() — aucune logique de sélection recopiée.
   Le champ Relation vide = page identique au V1.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
if ( ! defined( 'MTV2_TEMPLATE_ID' ) ) {
  define( 'MTV2_TEMPLATE_ID', 0 );   // 👉 ID du template Bricks « multi-comparatif » (aperçu ?preview_v2=1)
}
if ( ! defined( 'MTV2_MAX_TESTS' ) ) {
  define( 'MTV2_MAX_TESTS', 30 );    // nb max de tests complets (et de colonnes du tableau)
}
if ( ! defined( 'MTV2_FIELD_SUBS' ) ) {
  define( 'MTV2_FIELD_SUBS', 'mltv5_sous_comparatifs' );
}
if ( ! defined( 'MTV2_FIELD_INTRO' ) ) {
  define( 'MTV2_FIELD_INTRO', 'mltv5_intro_sous_comparatif' );
}
if ( ! defined( 'MTV2_POST_TYPE' ) ) {
  define( 'MTV2_POST_TYPE', 'comparatif' );
}

/* ---------------------------------------------------------------------
   1. CHAMPS ACF (local : visibles dans l'éditeur, pas dans la liste ACF)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mtv2_register_fields' ) ) {
function mtv2_register_fields() {
  if ( ! function_exists( 'acf_add_local_field_group' ) ) { return; }
  acf_add_local_field_group( array(
    'key'      => 'group_mtv2_multi_comparatif',
    'title'    => 'Multi-comparatif',
    'fields'   => array(
      array(
        'key'           => 'field_mtv2_sous_comparatifs',
        'label'         => 'Sous-comparatifs',
        'name'          => MTV2_FIELD_SUBS,
        'type'          => 'relationship',
        'instructions'  => 'Comparatifs à afficher comme sous-comparatifs de cette page, dans cet ordre. Vide = page classique (V1). Chaque sous-comparatif doit rester PUBLIÉ (sinon son cache de produits n\'est pas calculé).',
        'post_type'     => array( MTV2_POST_TYPE ),
        'post_status'   => array( 'publish' ),
        'filters'       => array( 'search', 'taxonomy' ),
        'return_format' => 'id',
        'min'           => 0,
        'max'           => 0,
      ),
      array(
        'key'          => 'field_mtv2_intro_sous_comparatif',
        'label'        => 'Intro quand ce comparatif est affiché comme sous-comparatif',
        'name'         => MTV2_FIELD_INTRO,
        'type'         => 'textarea',
        'instructions' => 'Facultatif (2-3 phrases). Vide = 1re phrase de l\'intro normale, le reste replié derrière « Lire la suite ».',
        'rows'         => 3,
        'new_lines'    => '',
      ),
    ),
    'location' => array(
      array( array( 'param' => 'post_type', 'operator' => '==', 'value' => MTV2_POST_TYPE ) ),
    ),
    'menu_order' => 90,
    'position'   => 'normal',
    'style'      => 'default',
    'active'     => true,
  ) );
}
}
/* WPCodeBox peut exécuter le snippet avant OU après acf/init */
if ( did_action( 'acf/init' ) ) { mtv2_register_fields(); }
else { add_action( 'acf/init', 'mtv2_register_fields' ); }

/* ---------------------------------------------------------------------
   2. HELPERS
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
  /* Intro d'un sous-comparatif :
     - champ dédié rempli -> affiché en entier ;
     - sinon intro normale : 1re phrase visible, le reste dans un <details>
       « Lire la suite » (natif, sans JS, contenu indexable). */
  function mtv2_intro_html( $sub_id ) {
    $own = trim( (string) get_post_meta( (int) $sub_id, MTV2_FIELD_INTRO, true ) );
    if ( $own !== '' ) {
      return '<div class="mtv2-intro">' . wp_kses_post( wpautop( $own ) ) . '</div>';
    }
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
   3. LE PLAN DE LA PAGE
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
      $title = $sp ? get_the_title( $sp ) : '#' . $sid;
      if ( ! $sp || $sid === $page_id || $sp->post_type !== MTV2_POST_TYPE ) {
        $plan['warnings'][] = 'Sous-comparatif ignoré (introuvable, pas un comparatif, ou la page elle-même) : ' . $title;
        continue;
      }
      if ( $sp->post_status !== 'publish' ) {
        $plan['warnings'][] = '« ' . $title . ' » n\'est pas publié (' . $sp->post_status . ') : ignoré. Il doit rester publié pour que son cache de produits soit calculé.';
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
          $plan['warnings'][] = '« ' . $title . ' » n\'a aucun produit (cache top_avis_ids pas encore calculé ? lancer le batch) : ignoré.';
        }
        continue;
      }

      $stv       = mtv2_tv( $sid );
      $sub_attr  = mtv2_terms( $sid, 'post-type-attribut' );
      $extra     = array_values( array_diff_key( $sub_attr, $parent_attr ) );
      if ( empty( $extra ) ) {
        $plan['warnings'][] = '« ' . $title . ' » n\'a aucun attribut en plus de ceux de cette page : titre et ancre de repli utilisés.';
      }

      /* Titre H2 : titre forcé, sinon « Les meilleurs {type du principal} ({attributs propres}) » */
      $forced = isset( $stv['forcer_affichage_du_titre'] ) ? trim( (string) $stv['forcer_affichage_du_titre'] ) : '';
      if ( $forced !== '' ) {
        $h2 = $forced;
      } elseif ( ! empty( $extra ) && $type_plur !== '' ) {
        $h2 = 'Les ' . lcfirst( $mf ) . ' ' . $type_plur . ' (' . implode( ', ', $extra ) . ')';
      } else {
        $stype = isset( $stv['type_de_produit_au_pluriel'] ) ? trim( (string) $stv['type_de_produit_au_pluriel'] ) : '';
        $h2    = $stype !== '' ? 'Les ' . lcfirst( $mf ) . ' ' . $stype : $title;
      }
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
   4. APERÇU ADMIN : ?preview_v2=1 -> template Bricks multi-comparatif
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mtv2_is_preview' ) ) {
  function mtv2_is_preview() {
    return isset( $_GET['preview_v2'] ) && (string) $_GET['preview_v2'] === '1'
      && function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' );
  }
}

add_filter( 'bricks/active_templates', function ( $active, $post_id, $content_type ) {
  if ( ! MTV2_TEMPLATE_ID || is_admin() || ! mtv2_is_preview() ) { return $active; }
  if ( ! is_singular( MTV2_POST_TYPE ) ) { return $active; }
  if ( is_array( $active ) ) { $active['content'] = (int) MTV2_TEMPLATE_ID; }
  return $active;
}, 20, 3 );

/* Pas de cache de page pendant l'aperçu */
add_action( 'template_redirect', function () {
  if ( mtv2_is_preview() && ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
}, 1 );

/* Lien d'aperçu dans la barre d'admin */
add_action( 'admin_bar_menu', function ( $bar ) {
  if ( ! MTV2_TEMPLATE_ID || is_admin() || ! is_singular( MTV2_POST_TYPE ) || ! current_user_can( 'edit_posts' ) ) { return; }
  $on = mtv2_is_preview();
  $bar->add_node( array(
    'id'    => 'mtv2-preview',
    'title' => $on ? '&#10005; Quitter l\'aperçu multi-comparatif' : '&#9673; Aperçu multi-comparatif',
    'href'  => $on ? remove_query_arg( 'preview_v2' ) : add_query_arg( 'preview_v2', '1' ),
  ) );
}, 90 );

/* ---------------------------------------------------------------------
   5. AVERTISSEMENTS sur l'écran d'édition du comparatif
   --------------------------------------------------------------------- */
add_action( 'admin_notices', function () {
  if ( ! function_exists( 'get_current_screen' ) ) { return; }
  $screen = get_current_screen();
  if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== MTV2_POST_TYPE ) { return; }
  $pid = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
  if ( $pid <= 0 || empty( mtv2_sub_ids_raw( $pid ) ) ) { return; }
  $plan = mtv2_plan( $pid );
  if ( empty( $plan['warnings'] ) ) { return; }
  echo '<div class="notice notice-warning"><p><b>Multi-comparatif</b></p><ul style="list-style:disc;margin-left:20px">';
  foreach ( $plan['warnings'] as $w ) { echo '<li>' . esc_html( $w ) . '</li>'; }
  echo '</ul></div>';
} );
