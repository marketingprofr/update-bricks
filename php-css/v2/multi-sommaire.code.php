<?php
/* =====================================================================
   MEILLEURTEST — MULTI-COMPARATIF (V2) : sommaire « Sur cette page »
   Remplace sommaire.code.php dans le template Bricks multi-comparatif.
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   CSS : v2/multi-sommaire.css (copie complète de sommaire.css, inchangée)
   à coller dans l'onglet CSS du même élément.

   Identique au V1, plus une entrée par sous-comparatif (ancre stable
   #9000-btu), insérée juste après « Notre sélection ». Chaque
   sous-comparatif ajoute 1 min au temps de lecture de la partie produit.
   Moteur multi-comparatif inclus dans ce bloc (aucun snippet WPCodeBox).
   ===================================================================== */

if ( ! function_exists( 'mt_guide_cache_id' ) ) {
  /* Résout l'ID du post lié mis en cache : essaie `mltv5_cache_id_{suffix}`
     puis `mltv5_cached_id_{suffix}` (ancien nom) ; accepte un ID ou un objet post. */
  function mt_guide_cache_id( $page_id, $suffix ) {
    $keys = array( 'mltv5_cached_id_' . $suffix, 'mltv5_cache_id_' . $suffix );
    foreach ( $keys as $f ) {                            /* 1) ACF */
      $v = function_exists( 'get_field' ) ? get_field( $f, $page_id ) : null;
      if ( is_array( $v ) ) { $v = reset( $v ); }
      if ( is_object( $v ) ) { return (int) $v->ID; }
      if ( $v ) { return (int) $v; }
    }
    foreach ( $keys as $f ) {                            /* 2) meta brut (hors ACF) */
      $v = function_exists( 'get_post_meta' ) ? get_post_meta( $page_id, $f, true ) : '';
      if ( is_array( $v ) ) { $v = reset( $v ); }
      if ( is_object( $v ) ) { return (int) $v->ID; }
      if ( $v ) { return (int) $v; }
    }
    return 0;
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

$page_id = get_the_ID();
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();

/* Type de produit (pour le libellé « Guide d'achat … ») */
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';

/* ---------------------------------------------------------------------
   CONFIG — sections du sommaire
   - label  : libellé affiché
   - anchor : slug d'ancre = `id` HTML à poser sur la section dans Bricks
   - show   : true (toujours) OU suffixe de cache (« criteres », « types »… ;
              présent => affiché, via le post lié `mltv5_cache_id_{suffixe}`).
   ⚠️ Les slugs d'ancre ci-dessous sont à confirmer / poser dans Bricks.
   --------------------------------------------------------------------- */
$guide_label = "Guide d&rsquo;achat" . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : '' );

$sections_cfg = array(
  array( 'label' => 'Notre s&eacute;lection',     'anchor' => 'mt-top5-title',            'show' => true ),
  array( 'label' => 'Tests complets',             'anchor' => 'partie-tests-complets',    'show' => true ),
  array( 'label' => 'Tableau comparatif',         'anchor' => 'partie-tableau-comparatif','show' => true ),
  array( 'label' => $guide_label,                 'anchor' => 'partie-guide-achat',       'show' => 'criteres' ),
  array( 'label' => 'Quel type choisir&nbsp;?',   'anchor' => 'partie-types',             'show' => 'choix' ),
  array( 'label' => 'Quelle marque choisir&nbsp;?','anchor' => 'partie-marques',          'show' => 'marques' ),
  array( 'label' => 'Astuces et conseils',        'anchor' => 'partie-astuces',           'show' => 'astuces' ),
  array( 'label' => 'Pourquoi acheter&nbsp;?',    'anchor' => 'partie-raisons',           'show' => 'raisons' ),
  array( 'label' => 'Questions fr&eacute;quentes','anchor' => 'partie-faq',               'show' => 'faq' ),
);

/* Résolution des sections présentes (un suffixe => présent si le post lié existe) */
$sections = array();
foreach ( $sections_cfg as $s ) {
  $present = ( $s['show'] === true ) || ( is_string( $s['show'] ) && mt_guide_cache_id( $page_id, $s['show'] ) > 0 );
  if ( $present ) { $sections[] = $s; }
}
if ( empty( $sections ) ) { return; }

/* V2 : une entrée par sous-comparatif, juste après « Notre sélection » */
$plan        = mtv2_plan( $page_id );
$sub_anchors = array();
if ( $plan && $plan['is_multi'] ) {
  $sub_items = array();
  foreach ( $plan['subs'] as $sb ) {
    $lbl = ! empty( $sb['extra'] ) ? 'S&eacute;lection ' . esc_html( $sb['label'] ) : esc_html( $sb['title'] );
    $sub_items[]   = array( 'label' => $lbl, 'anchor' => $sb['anchor'], 'show' => true );
    $sub_anchors[] = $sb['anchor'];
  }
  $at = 0;
  foreach ( $sections as $k => $s ) {
    if ( $s['anchor'] === 'mt-top5-title' ) { $at = $k + 1; break; }
  }
  array_splice( $sections, $at, 0, $sub_items );
}

/* ---------------------------------------------------------------------
   Temps de lecture estimé
   10 min pour la partie produit (Notre sélection + Tests complets + Tableau comparatif)
   + 2 min par section supplémentaire présente.
   --------------------------------------------------------------------- */
$T5_READ_BASE = 10 + count( $sub_anchors ); // partie produit (+1 min par sous-comparatif)
$T5_READ_PER  = 2;  // par section supplémentaire
$product_anchors = array_merge( array( 'mt-top5-title', 'partie-tests-complets', 'partie-tableau-comparatif' ), $sub_anchors );
$extra = 0;
foreach ( $sections as $s ) {
  if ( ! in_array( $s['anchor'], $product_anchors, true ) ) { $extra++; }
}
$reading_total = $T5_READ_BASE + $T5_READ_PER * $extra;

/* ---------------------------------------------------------------------
   Jalons de minutes cumulées (début de section) pour la jauge de lecture.
   - Notre sélection (1re section) = 0.
   - La partie produit s'étale de 0 à BASE (10) : on ne pose donc PAS de
     jalon sur Tests complets / Tableau comparatif (la rampe 0→10 les couvre
     au prorata de leur hauteur).
   - 1re section supplémentaire = BASE (10), puis +PER (2) à chacune.
   Émis en data-min ; la fin (= total) est gérée en JS via le bas du contenu.
   --------------------------------------------------------------------- */
$cum_opt = $T5_READ_BASE;
$first   = true;
foreach ( $sections as $k => $s ) {
  $is_product = in_array( $s['anchor'], $product_anchors, true );
  if ( $first ) {
    $sections[ $k ]['min'] = 0;
  } elseif ( ! $is_product ) {
    $sections[ $k ]['min'] = $cum_opt;
    $cum_opt += $T5_READ_PER;
  } else {
    $sections[ $k ]['min'] = null; // produit intérieur : pas de jalon
  }
  $first = false;
}
?>
<aside class="mt-toc" data-mt-toc>
  <h4>Sur cette page</h4>
  <ul>
<?php foreach ( $sections as $s ) : ?>
    <li><a href="#<?php echo esc_attr( $s['anchor'] ); ?>"<?php if ( isset( $s['min'] ) && $s['min'] !== null ) { echo ' data-min="' . esc_attr( $s['min'] ) . '"'; } ?>><?php echo $s['label']; ?></a></li>
<?php endforeach; ?>
  </ul>
  <div class="mt-toc-progress">
    <span>Lecture</span>
    <div class="mt-toc-progress-bar"><div></div></div>
    <span><span class="mt-toc-cur">0</span> min sur <span class="mt-toc-total"><?php echo (int) $reading_total; ?></span></span>
  </div>
</aside>

<script>
(function () {
  /* ----------------------------------------------------------------
     CONFIG — à adapter au DOM réel de la page Bricks
     ---------------------------------------------------------------- */
  var CONTENT_SELECTOR = '.contenu-principal'; // 👉 colonnes du contenu (plusieurs autorisées)
  var HEADER_OFFSET    = 30;                   // marge au-dessus de l'ancre au scroll (px)

  function init(root) {
    if (root.dataset.mtTocInit) return;        // garde anti double-init
    root.dataset.mtTocInit = '1';

    var links = [].slice.call(root.querySelectorAll('.mt-toc ul a, ul a'));
    if (!links.length) return;

    /* Cibles = éléments visés par les ancres du sommaire */
    var targets = links.map(function (a) {
      var id = (a.getAttribute('href') || '').replace(/^#/, '');
      var el = id ? document.getElementById(id) : null;
      if (el) el.style.scrollMarginTop = HEADER_OFFSET + 'px';
      return { id: id, el: el, li: a.parentNode, a: a };
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
            t.li.classList.toggle('active', t.el === e.target);
          });
        });
      }, { rootMargin: '-15% 0px -75% 0px' });
      targets.forEach(function (t) { if (t.el) io.observe(t.el); });
    }

    /* Barre de progression + minutes courantes.
       Modèle pondéré par section : chaque jalon (data-min) = minutes cumulées
       au DÉBUT de sa section ; on interpole linéairement entre deux jalons
       selon la position de scroll. Ainsi « 10 min » tombe pile au début de la
       1re section supplémentaire, quelle que soit la hauteur réelle en pixels. */
    var content = [].slice.call(document.querySelectorAll(CONTENT_SELECTOR));
    var bar     = root.querySelector('.mt-toc-progress-bar div');
    var elCur   = root.querySelector('.mt-toc-cur');
    var elTotal = root.querySelector('.mt-toc-total');
    var totalMin = parseInt(elTotal && elTotal.textContent, 10) || 0;

    /* Jalons issus de data-min (résolus en position absolue à chaque update) */
    var milestones = targets
      .filter(function (t) { return t.el && t.a.hasAttribute('data-min'); })
      .map(function (t) { return { el: t.el, min: parseFloat(t.a.getAttribute('data-min')) || 0 }; });

    function absTop(el) { return window.scrollY + el.getBoundingClientRect().top; }

    function buildBps() {
      var bps = milestones.map(function (m) { return { y: absTop(m.el), min: m.min }; });
      if (content.length) {
        var lastRect = content[content.length - 1].getBoundingClientRect();
        bps.push({ y: window.scrollY + lastRect.bottom - window.innerHeight, min: totalMin });
      }
      bps.sort(function (a, b) { return a.y - b.y; });
      return bps;
    }

    function minutesAt(y, bps) {
      if (y <= bps[0].y) { return bps[0].min; }
      for (var i = 1; i < bps.length; i++) {
        if (y <= bps[i].y) {
          var seg = bps[i].y - bps[i - 1].y;
          if (seg <= 0) { return bps[i].min; }
          var f = (y - bps[i - 1].y) / seg;
          return bps[i - 1].min + f * (bps[i].min - bps[i - 1].min);
        }
      }
      return bps[bps.length - 1].min;
    }

    var ticking = false;
    function update() {
      ticking = false;
      var bps = buildBps();
      var cur, p;
      if (bps.length >= 2) {
        cur = minutesAt(window.scrollY, bps);          // jalons pondérés
        p   = totalMin ? cur / totalMin : 0;
      } else {                                          // repli : scroll plein page
        var docDist = document.documentElement.scrollHeight - window.innerHeight;
        p   = docDist > 0 ? window.scrollY / docDist : 0;
        cur = p * totalMin;
      }
      p = Math.min(1, Math.max(0, p));
      if (bar)   bar.style.width = (p * 100).toFixed(1) + '%';
      if (elCur) elCur.textContent = Math.min(totalMin, Math.max(0, Math.round(cur)));
    }
    function onScroll() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(update);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    update();
  }

  function boot() {
    document.querySelectorAll('[data-mt-toc]').forEach(init);
  }
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
</script>
