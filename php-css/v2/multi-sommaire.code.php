<?php
/* =====================================================================
   MEILLEURTEST — MULTI-COMPARATIF (V2) : sommaire « Sur cette page »
   Remplace sommaire.code.php dans le template Bricks multi-comparatif.
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   CSS : v2/multi-sommaire.css à coller dans l'onglet CSS du même élément.

   Titre = « Meilleur » + type de produit (ex. « Meilleur climatiseur
   mobile »). Liste courte (décision client) : « En 2026 », puis une entrée par
   sous-comparatif (libellé court : « Réversible », « 7000 BTU »…), Tests
   complets, Tableau comparatif. Plus d'entrées du guide d'achat (il sera
   séparé) ni de jauge de temps de lecture. Scrollspy + défilement doux.
   Moteur multi-comparatif inclus dans ce bloc (aucun snippet WPCodeBox).
   ===================================================================== */

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

$page_id = get_the_ID();
$plan    = mtv2_plan( $page_id );

/* Titre du sommaire : « Meilleur » + type de produit, accordé via
   lalalesmeilleur (« le meilleur » -> « Meilleur climatiseur mobile »,
   « les meilleures » -> « Meilleures … » au pluriel). Replis :
   masculinsfeminins + type pluriel, puis « Sur cette page ». */
$tv        = mtv2_tv( $page_id );
$llm       = isset( $tv['lalalesmeilleur'] ) ? trim( (string) $tv['lalalesmeilleur'] ) : '';
$type_sing = isset( $tv['type_de_produit_au_singulier'] ) ? trim( (string) $tv['type_de_produit_au_singulier'] ) : '';
$type_plur = isset( $tv['type_de_produit_au_pluriel'] ) ? trim( (string) $tv['type_de_produit_au_pluriel'] ) : '';
$toc_title = '';
if ( $llm !== '' ) {
  $adj    = trim( preg_replace( '/^(le|la|les)\s+/iu', '', $llm ) );        // meilleur / meilleure / meilleurs / meilleures
  $plural = (bool) preg_match( '/^les\s/iu', $llm );
  $type   = $plural ? $type_plur : ( $type_sing !== '' ? $type_sing : $type_plur );
  if ( $adj !== '' && $type !== '' ) { $toc_title = $adj . ' ' . $type; }
}
if ( $toc_title === '' && $type_plur !== '' ) {
  $mf        = isset( $tv['masculinsfeminins'] ) ? trim( (string) $tv['masculinsfeminins'] ) : '';
  $toc_title = ( $mf !== '' ? $mf : 'meilleurs' ) . ' ' . $type_plur;
}
if ( $toc_title === '' ) { $toc_title = 'Sur cette page'; }
$toc_title = mb_strtoupper( mb_substr( $toc_title, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $toc_title, 1, null, 'UTF-8' );

/* Entrées : En {année} (encart principal), sous-comparatifs, Tests complets, Tableau */
$sections = array( array( 'label' => 'En ' . esc_html( date_i18n( 'Y' ) ), 'anchor' => 'mt-top5-title' ) );
if ( $plan['is_multi'] ) {
  foreach ( $plan['subs'] as $sb ) {
    /* Libellé court : attributs propres, 1re lettre en capitale (« Réversible ») */
    $lbl = trim( (string) $sb['label'] );
    $lbl = esc_html( mb_strtoupper( mb_substr( $lbl, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $lbl, 1, null, 'UTF-8' ) );
    $sections[] = array( 'label' => $lbl, 'anchor' => $sb['anchor'] );
  }
}
/* Raccourcis mis en avant (pastilles) */
$pills = array(
  array( 'label' => 'Tests complets',     'anchor' => 'partie-tests-complets',     'cls' => 'is-tests' ),
  array( 'label' => 'Tableau comparatif', 'anchor' => 'partie-tableau-comparatif', 'cls' => 'is-table' ),
);
?>
<aside class="mt-toc" data-mt-toc>
<?php if ( ! empty( $GLOBALS['mtv2_stale_engine'] ) && current_user_can( 'edit_posts' ) ) : ?>
<p class="mtv2-stale" style="margin:0 0 12px;padding:10px 14px;border:2px solid #c0392b;border-radius:8px;background:#fdecea;color:#c0392b;font:600 14px/1.5 Inter,sans-serif">&#9888; Multi-comparatif : une ANCIENNE version du code tourne encore sur cette page. Supprimez le snippet WPCodeBox « mtv2-core » s'il existe, recollez les 4 blocs multi-* (résumé, tests, tableau, sommaire), puis videz le cache. (Message visible des éditeurs uniquement.)</p>
<?php endif; ?>
  <h4 class="mt-toc-title"><?php echo esc_html( $toc_title ); ?></h4>
  <ul class="mt-toc-list">
<?php foreach ( $sections as $s ) : ?>
    <li><a class="mt-toc-link" href="#<?php echo esc_attr( $s['anchor'] ); ?>"><?php echo $s['label']; ?></a></li>
<?php endforeach; ?>
  </ul>
  <div class="mt-toc-pills">
<?php foreach ( $pills as $s ) : ?>
    <a class="mt-toc-link mt-toc-pill <?php echo esc_attr( $s['cls'] ); ?>" href="#<?php echo esc_attr( $s['anchor'] ); ?>"><?php echo esc_html( $s['label'] ); ?></a>
<?php endforeach; ?>
  </div>
</aside>

<script>
(function () {
  var HEADER_OFFSET = 30; // marge au-dessus de l'ancre au scroll (px)

  function init(root) {
    if (root.dataset.mtTocInit) return;        // garde anti double-init
    root.dataset.mtTocInit = '1';

    var links = [].slice.call(root.querySelectorAll('a.mt-toc-link'));
    if (!links.length) return;

    /* Cibles = éléments visés par les ancres du sommaire */
    var targets = links.map(function (a) {
      var id = (a.getAttribute('href') || '').replace(/^#/, '');
      var el = id ? document.getElementById(id) : null;
      if (el) el.style.scrollMarginTop = HEADER_OFFSET + 'px';
      return { id: id, el: el, a: a };
    });

    /* Défilement doux au clic */
    targets.forEach(function (t) {
      t.a.addEventListener('click', function (e) {
        if (!t.el) return;
        e.preventDefault();
        t.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + t.id);
      });
    });

    /* Lien actif (scrollspy via IntersectionObserver) */
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          targets.forEach(function (t) {
            t.a.classList.toggle('is-active', t.el === e.target);
          });
        });
      }, { rootMargin: '-15% 0px -75% 0px' });
      targets.forEach(function (t) { if (t.el) io.observe(t.el); });
    }
  }

  function boot() {
    document.querySelectorAll('[data-mt-toc]').forEach(init);
  }
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
</script>
