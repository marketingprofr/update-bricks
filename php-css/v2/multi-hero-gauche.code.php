<?php
/* =====================================================================
   MEILLEURTEST — MULTI-COMPARATIF (V2) : hero gauche (ariane, titre H1,
   byline, chapô…). Remplace hero-gauche.code.php dans le template Bricks
   multi-comparatif. CSS : v2/multi-hero-gauche.css (copie complète de
   hero-gauche.code.css, inchangée).

   Identique au V1, sauf le titre H1 d'un multi-comparatif :
   « Les 5 meilleurs climatiseurs mobiles en 2026 : Guide ultime
   (N produits comparés) », N = même compteur que l'encart « Pourquoi nous
   faire confiance » (avis publiés du type + attributs, +5 si < 10).
   Titre SEO Rank Math d'un multi-comparatif : « Meilleur climatiseur mobile
   2026 (N produits comparés) ».
   Sans sous-comparatif : H1 et titre SEO V1 inchangés.
   Moteur multi-comparatif inclus dans ce bloc (aucun snippet WPCodeBox).
   ===================================================================== */
$MT_SHOW_QUICK_PICKS = false;
$MT_SHOW_BOLD_INTRO  = false;
$MT_SHOW_INTRO_RECO  = true;
$MT_VERDICT_SOUS_H1  = true;   // verdict (top 3 avec notes /10, N analysés, méthode, date) juste sous le H1 ; false = ancienne phrase dans le chapô
$MT_VERIFIE_PAR      = 'Samuel Petit'; // ligne auteur « Vérifié par …, responsable éditorial » (validé par Samuel) ; '' = pas de ligne
$MT_H1_EGAL_TITLE    = true;   // sans titre forcé, le H1 reprend le title automatique (validé par Samuel)
$MT_VOS_QUESTIONS    = '';     // encart « Vos questions » (questions de la FAQ, réponse d'une phrase) : 'sous_reponse' = juste après la réponse courte,
                                // 'avant_top5' = juste avant le top 5 ; '' = pas d'encart (position en attente du choix de Samuel)

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

if ( ! function_exists( 'mt_top_infos' ) ) {
  /* Nom affiché et note /10 des premiers produits du classement (verdict, méta description).
     Nom : même logique que mt_intro_reco. Note : même calcul que les cartes du résumé. */
  function mt_top_infos( $ids, $max ) {
    $out = array();
    foreach ( array_slice( array_values( array_filter( array_map( 'intval', (array) $ids ) ) ), 0, $max ) as $pid ) {
      $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
      $brand  = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model  = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $forced !== '' ) { $name = $forced; }
      elseif ( $model !== '' ) { $name = trim( $brand . ' ' . $model ); }
      else { $name = (string) get_the_title( $pid ); }
      $mt_keep = $GLOBALS['post'] ?? null;
      $GLOBALS['post'] = get_post( $pid );
      setup_postdata( $GLOBALS['post'] );
      $s10 = function_exists( 'get_acf_score_divided_by_10' ) ? (float) get_acf_score_divided_by_10() : round( (float) get_field( 'mltv5_score_recent', $pid ) / 10, 1 );
      $GLOBALS['post'] = $mt_keep;
      if ( $mt_keep ) { setup_postdata( $mt_keep ); }
      $out[] = array( 'pid' => $pid, 'name' => html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ), 'score' => $s10 );
    }
    return $out;
  }
}
if ( ! function_exists( 'mt_meta_auto' ) ) {
  /* Méta description automatique (tests Jev du 2026-10-02, format M3 de l'Architecture, 2,99/3) :
     « A arrive en tête de notre comparatif 2026 (x/10). N X analysé(e)s, T retenu(e)s : notes,
     avantages et inconvénients. » Au-delà de 158 caractères, la fin « : notes, avantages et
     inconvénients » est retirée. Texte brut (pas de HTML). '' si aucun produit. */
  function mt_meta_auto( $ids, $type_plur, $llm, $n ) {
    $t = count( array_filter( array_map( 'intval', (array) $ids ) ) );
    $p = mt_top_infos( $ids, 1 );
    if ( empty( $p ) || $p[0]['name'] === '' ) { return ''; }
    $fem  = ( mb_strpos( mb_strtolower( (string) $llm, 'UTF-8' ), 'meilleure' ) !== false );
    $plur = mb_strtolower( trim( html_entity_decode( (string) $type_plur, ENT_QUOTES, 'UTF-8' ) ), 'UTF-8' );
    $e    = ( $fem && $plur !== '' ) ? 'e' : '';
    $typ  = $plur !== '' ? $plur : 'produits';
    $note = $p[0]['score'] > 0 ? ' (' . number_format( $p[0]['score'], 1, ',', '' ) . '/10)' : '';
    $tete = $p[0]['name'] . ' arrive en tête de notre comparatif ' . date_i18n( 'Y' ) . $note . '. ';
    $nb   = ( $n > $t ) ? $n . ' ' . $typ . ' analysé' . $e . 's, ' . $t . ' retenu' . $e . 's'
                        : max( (int) $n, $t ) . ' ' . $typ . ' analysé' . $e . 's et classé' . $e . 's';
    $meta = $tete . $nb . ' : notes, avantages et inconvénients.';
    if ( mb_strlen( $meta, 'UTF-8' ) > 158 ) { $meta = $tete . $nb . '.'; }
    return $meta;
  }
}
if ( ! function_exists( 'mt_verdict_ouverture' ) ) {
  /* Ouverture juste sous le H1 (tests Jev du 2026-10-02, variante S2 de l'Architecture : ouverture
     0,95, confirmée sur deux tours) : « Réponse courte : A (9,0/10) est la meilleure X en 2026,
     devant B (8,9/10) et C (8,8/10). Nous avons analysé N X et retenu les T meilleur(e)s : cette
     page détaille notre top T[, les classe aussi par profil (p1, p2, p3)] et vous aide à choisir.
     Chaque fiche est notée sur 10[ et le guide détaille k critères de choix] ; classement mis à
     jour le {date}. » Profils (multi-comparatif) : libellés courts des 3 premières sections.
     Critères : mention seulement si k >= 3.
     Nom du produit et lien du n°1 : même logique que mt_intro_reco. Notes : même calcul que les
     cartes du résumé (get_acf_score_divided_by_10). */
  function mt_verdict_ouverture( $ids, $type_plur, $type_sing, $llm, $n, $date, $k, $profils = array() ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    $t   = count( $ids );
    if ( $t === 0 ) { return ''; }
    $prods = mt_top_infos( $ids, 3 );
    if ( empty( $prods ) || $prods[0]['name'] === '' ) { return ''; }

    /* Lien du n°1 : ASIN Amazon, sinon 1er lien produit, sinon son test complet */
    $p1   = $prods[0]['pid'];
    $asin = trim( (string) get_field( 'mltv5_asin_amazon', $p1 ) );
    $url  = '';
    if ( $asin !== '' ) {
      $url = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=mlt00-21';
    } else {
      for ( $li = 1; $li <= 3; $li++ ) {
        $lu = trim( (string) get_field( 'mltv5_lien_du_produit_' . $li, $p1 ) );
        if ( $lu !== '' && strpos( $lu, 'http' ) === 0 ) { $url = $lu; break; }
      }
    }
    if ( $url === '' ) { $url = '#produit-n-1'; }

    $llm_t = mb_strtolower( trim( (string) $llm ), 'UTF-8' );
    $fem   = ( mb_strpos( $llm_t, 'meilleure' ) !== false );
    $pl    = ( mb_strpos( $llm_t, 'les ' ) === 0 );
    $plur  = mb_strtolower( trim( (string) $type_plur ), 'UTF-8' );
    $sing  = mb_strtolower( trim( (string) $type_sing ), 'UTF-8' );
    $an    = date_i18n( 'Y' );
    $note  = function ( $p ) { return $p['score'] > 0 ? ' (' . number_format( $p['score'], 1, ',', '' ) . '/10)' : ''; };

    $lien = '<a href="' . esc_url( $url ) . '">' . esc_html( $prods[0]['name'] ) . '</a>' . $note( $prods[0] );
    if ( ! $pl && $sing !== '' ) { $out = 'Réponse courte : ' . $lien . ' est ' . ( $fem ? 'la meilleure ' : 'le meilleur ' ) . esc_html( $sing ) . ' en ' . $an; }
    else { $out = 'Réponse courte : ' . $lien . ' est notre n°1 parmi les ' . esc_html( $plur !== '' ? $plur : 'produits' ) . ' en ' . $an; }
    $autres = array();
    foreach ( array_slice( $prods, 1 ) as $p ) { if ( $p['name'] !== '' ) { $autres[] = esc_html( $p['name'] ) . $note( $p ); } }
    if ( count( $autres ) === 2 ) { $out .= ', devant ' . $autres[0] . ' et ' . $autres[1]; }
    elseif ( count( $autres ) === 1 ) { $out .= ', devant ' . $autres[0]; }
    $out .= '.';

    $typ = esc_html( $plur !== '' ? $plur : 'produits' );
    $e   = ( $fem && $plur !== '' ) ? 'e' : '';
    $profils = array_slice( array_values( array_filter( array_map( 'trim', (array) $profils ) ) ), 0, 3 );
    $suite = ' : cette page détaille notre top ' . $t
      . ( $profils ? ', les classe aussi par profil (' . esc_html( implode( ', ', $profils ) ) . ')' : '' )
      . ' et vous aide à choisir.';
    if ( $n > $t ) { $out .= ' Nous avons analysé ' . (int) $n . ' ' . $typ . ' et retenu les ' . $t . ' meilleur' . $e . 's' . $suite; }
    else { $out .= ' Nous avons analysé et classé ' . max( (int) $n, $t ) . ' ' . $typ . $suite; }

    $out .= ' Chaque fiche est notée sur 10'
      . ( $k >= 3 ? ' et le guide détaille ' . (int) $k . ' critères de choix' : '' )
      . ' ; classement mis à jour le ' . esc_html( $date ) . '.';
    return '<p class="mt-verdict">' . $out . '</p>';
  }
}

if ( ! function_exists( 'mt_criteres_courts' ) ) {
  /* Libellés courts des critères (encadré « Pourquoi nous faire confiance » et encart « Vos questions »).
     Copie IDENTIQUE dans hero-encart et hero-gauche (V1 et V2) : le 1er bloc exécuté la définit.
     Priorité au champ mltv5_criteres_courts rempli à la main (décision de Samuel) ; sinon tirés automatiquement
     des critères du guide (page, sinon annexe en cache) : article et parenthèse retirés
     (« La puissance frigorifique (exprimée en BTU) » → « puissance frigorifique »), doublons fusionnés,
     4 au plus ; '' s'il en reste moins de 3. */
  function mt_criteres_courts( $page_id ) {
    $mt_champ = trim( wp_strip_all_tags( (string) get_field( 'mltv5_criteres_courts', $page_id ) ) );
    if ( $mt_champ !== '' ) { return $mt_champ; }
    $mt_crit = get_field( 'mltv5_criteres_de_choix', $page_id );
    if ( empty( $mt_crit ) ) { $mt_cid = (int) get_field( 'mltv5_cached_id_criteres', $page_id ); $mt_crit = $mt_cid ? get_field( 'mltv5_criteres_de_choix', $mt_cid ) : array(); }
    $mt_vus = array(); $mt_lib = array();
    foreach ( (array) $mt_crit as $mt_r ) {
      $mt_t = html_entity_decode( wp_strip_all_tags( (string) ( $mt_r['mltv5_critere_de_choix'] ?? '' ) ), ENT_QUOTES, 'UTF-8' );
      $mt_t = trim( preg_replace( '/\s*\([^)]*\)/u', '', str_replace( "\u{2019}", "'", $mt_t ) ) );
      $mt_t = trim( preg_replace( "/^(les|le|la|l'|vos|votre|son|sa|ses|un|une|des)\s*/iu", '', $mt_t ) );
      if ( $mt_t === '' || mb_strlen( $mt_t, 'UTF-8' ) > 45 ) { continue; }
      $mt_t = mb_strtolower( mb_substr( $mt_t, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $mt_t, 1, null, 'UTF-8' );
      /* Libellés faibles écartés (règle de l'Architecture, 2026-10-02) : questions, tournures de phrase,
         consignes, mots vagues seuls ; article retiré après « et » / « ou » */
      if ( strpos( $mt_t, '?' ) !== false ) { continue; }
      if ( preg_match( "/^(ne|n'|choisir|choisissez|comment|quel|quelle|pourquoi|optez|privilégiez|vérifiez|faites|pensez|tenez|prenez|à noter|les plus|bon à savoir|attention|facile|bon|bonne|sous|avec|sans|pour|en|à|au|aux|selon|bien|savoir|opter|se|s')(\s|$|')/iu", $mt_t ) ) { continue; }
      if ( preg_match( '/\b(est|sont|doit|peut|vous|votre|vos|il faut)\b/iu', $mt_t ) ) { continue; }
      if ( in_array( mb_strtolower( $mt_t, 'UTF-8' ), array( 'type', 'besoins', 'fonctionnalités', 'modèle', 'options', 'marque', 'design', 'utilisation', 'caractéristiques', 'accessoires', 'critères', 'choix' ), true ) ) { continue; }
      $mt_t = preg_replace( "/\b(et|ou) (le|la|les|l')\s*/iu", '$1 ', $mt_t );
      $mt_k = remove_accents( mb_strtolower( $mt_t, 'UTF-8' ) );
      if ( isset( $mt_vus[ $mt_k ] ) ) { continue; }
      $mt_vus[ $mt_k ] = true; $mt_lib[] = $mt_t;
    }
    return count( $mt_lib ) >= 3 ? implode( ', ', array_slice( $mt_lib, 0, 4 ) ) : '';
  }
}
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
if ( ! function_exists( 'mt5_num' ) ) {
  function mt5_num( $v ) {
    $v = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $v );
    $v = str_replace( ',', '.', $v );
    return is_numeric( $v ) ? (float) $v : 0.0;
  }
}
if ( ! function_exists( 'mt_faq_read' ) ) {
  function mt_faq_read( $pid ) {
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_faq_comparatif', $pid ) : null;
    return is_array( $rows ) ? $rows : array();
  }
}
if ( ! function_exists( 'mt_vq_phrase' ) ) {
  /* Encart « Vos questions » : réponse d'une phrase tirée d'une réponse de la FAQ (règle de la Coordination, 2026-10-03).
     1re phrase qui contient un chiffre, un nom propre ou plus de 60 caractères, sinon les deux premières ;
     un « Oui… » / « Non ! » court en tête reste devant ; questions rhétoriques sautées ; numéros de liste retirés ;
     phrase choisie qui renvoie à la précédente (« C'est pourquoi… », « Vous pourrez ainsi… », « Il… ») : la précédente
     est gardée devant si le tout tient ; 220 caractères au plus, coupé sur un mot. */
  function mt_vq_phrase( $html ) {
    $t = preg_replace( '#\R\s*\R|</(p|li|h[1-6]|div|tr)>|<br\s*/?>#iu', "\x1e", (string) $html );
    $t = str_replace( "\xc2\xa0", ' ', html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' ) );
    $t = preg_replace( '/\.\.(?!\.)/u', '.', preg_replace( '/\s+\./u', '.', $t ) ); // « électrique. . » → « électrique. »
    $ph = array();
    foreach ( explode( "\x1e", $t ) as $bloc ) {
      $bloc = preg_replace( '/^\d+\s*[.)]\s+/u', '', trim( preg_replace( '/\s+/u', ' ', $bloc ) ) );
      if ( $bloc === '' ) { continue; }
      foreach ( preg_split( '/(?<=[.!?…])\s+(?=[\p{Lu}0-9«])/u', $bloc ) as $p ) {
        if ( trim( $p ) !== '' ) { $ph[] = trim( $p ); }
      }
    }
    $tete = '';
    if ( count( $ph ) > 1 && preg_match( '/^(oui|non)\b/iu', $ph[0] ) && mb_strlen( $ph[0], 'UTF-8' ) <= 40 ) { $tete = array_shift( $ph ); }
    $ph = array_values( array_filter( $ph, function ( $p ) { return substr( $p, -1 ) !== '?'; } ) );
    $choix = '';
    foreach ( $ph as $i => $p ) {
      if ( preg_match( '/\d/u', $p ) || preg_match( "/[\s'’(]\p{Lu}/u", $p ) || mb_strlen( $p, 'UTF-8' ) > 60 ) {
        $choix = $p;
        $liaison = "/^(c['’]est|ainsi|donc|alors|cependant|toutefois|en effet|par contre|de plus|aussi|également|pourtant|néanmoins|mais|ensuite|enfin|par ailleurs|la première|le premier|la seconde|le second|la deuxième|le deuxième|cela|ceci|celui-ci|celle-ci|ceux-ci|celles-ci|il|elle|ils|elles)\b|^(\S+\s+){1,2}ainsi\b/iu";
        if ( $i > 0 && preg_match( $liaison, $p ) && mb_strlen( $tete . ' ' . $ph[ $i - 1 ] . ' ' . $p, 'UTF-8' ) <= 220 ) { $choix = $ph[ $i - 1 ] . ' ' . $p; }
        break;
      }
    }
    if ( $choix === '' ) { $choix = implode( ' ', array_slice( $ph, 0, 2 ) ); }
    $r = trim( $tete . ' ' . $choix );
    if ( mb_strlen( $r, 'UTF-8' ) > 220 ) {
      $r   = mb_substr( $r, 0, 219, 'UTF-8' );
      $esp = mb_strrpos( $r, ' ', 0, 'UTF-8' );
      if ( $esp ) { $r = mb_substr( $r, 0, $esp, 'UTF-8' ); }
      $r = rtrim( $r, ' ,;:-' ) . '…';
    } elseif ( $r !== '' && ! preg_match( '/[.!?…]$/u', $r ) ) {
      $r .= '.';
    }
    return $r;
  }
}

if ( ! function_exists( 'mt_vos_questions' ) ) {
  /* Encart « Vos questions » (tests Jev du 2026-10-03 sur 10 pages : utilité +0,07 avant le top 5, +0,09 après
     la réponse courte) : 4 ou 5 questions de la FAQ de la page, dans le même ordre (questions automatiques puis
     questions ACF), chacune avec une réponse d'une phrase, et un lien vers la FAQ complète.
     Sautées, car déjà dites plus haut : « Quel est le meilleur… », « meilleures marques », « meilleurs avis »,
     « Comment avons-nous établi… ». Au moins 3 questions, sinon rien. */
  function mt_vos_questions( $page_id, $ids, $type_sing, $type_plur, $llm ) {
    $items = array();
    $ids   = array_slice( array_values( array_filter( array_map( 'intval', (array) $ids ) ) ), 0, 5 );
    if ( ! empty( $ids ) ) {
      /* Questions automatiques de la FAQ (mêmes conditions et mêmes accords que faq.code.php) */
      $low    = strtolower( (string) $llm );
      $plural = ( strpos( $low, 'les ' ) === 0 );
      $fem    = $plural ? ( strpos( $low, 'meilleures' ) !== false ) : ( strpos( $low, 'la ' ) === 0 );
      $prix   = array();
      $notes  = 0;
      foreach ( $ids as $pid ) {
        $v = mt5_num( get_field( 'mltv5_prix_indicatif', $pid ) );
        if ( $v > 0 ) { $prix[] = $v; }
        if ( mt5_num( get_field( 'mltv5_score_avis_clients', $pid ) ) > 0 ) { $notes++; }
      }
      if ( count( $prix ) >= 2 ) {
        $euro  = function ( $v ) { return number_format( (float) $v, 0, ',', "\xc2\xa0" ) . "\xc2\xa0€"; };
        $indef = $plural
          ? ( 'des ' . ( $type_plur !== '' ? $type_plur : $type_sing ) )
          : ( ( $fem ? 'une' : 'un' ) . ' ' . ( $type_sing !== '' ? $type_sing : $type_plur ) );
        $noun  = $type_plur !== '' ? $type_plur : ( $type_sing !== '' ? $type_sing : 'produits' );
        $items[] = array( 'Quel budget prévoir pour ' . trim( $indef ) . "\xc2\xa0?",
          'Les ' . $noun . ' de notre sélection s’échelonnent d’environ ' . $euro( min( $prix ) ) . ' à ' . $euro( max( $prix ) ) . '.' );
      } elseif ( $notes < 2 ) {
        $items[] = array( "Pourquoi faire confiance à ce comparatif\xc2\xa0?",
          "Notre rédaction travaille en toute indépendance\xc2\xa0: aucune marque ne peut acheter sa place dans un classement, et nous n’acceptons ni publicité ni cadeau des marques." );
      }
      /* « Comment bien choisir… » : les libellés courts des critères, plutôt que la 1re phrase générique de la FAQ */
      $crit = mt_criteres_courts( $page_id );
      if ( $crit !== '' ) {
        $best_noun = $plural ? ( $type_plur !== '' ? $type_plur : $type_sing ) : ( $type_sing !== '' ? $type_sing : $type_plur );
        $items[] = array( 'Comment bien choisir ' . ( $plural ? 'vos' : 'votre' ) . ' ' . ( $best_noun !== '' ? $best_noun : 'produit' ) . "\xc2\xa0?",
          "Les critères qui font vraiment la différence\xc2\xa0: " . preg_replace( '/, ([^,]+)$/u', ' et $1', $crit ) . '.' );
      }
    }
    /* Questions de la rédaction (repeater ACF, page puis annexe en cache) */
    $rows = mt_faq_read( $page_id );
    if ( empty( $rows ) ) {
      $c = mt_guide_cache_id( $page_id, 'faq' );
      if ( $c && $c !== (int) $page_id ) { $rows = mt_faq_read( $c ); }
    }
    $saute = '/^(Quel(le)?s? (est|sont) (le|la|les) meilleur|Quelles sont les meilleures marques|Quel produit a les meilleurs avis|Comment avons-nous établi)/u';
    foreach ( $rows as $r ) {
      if ( count( $items ) >= 5 ) { break; }
      $q = trim( html_entity_decode( wp_strip_all_tags( (string) ( $r['mltv5_faq_comparatif_question'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
      if ( $q === '' || preg_match( $saute, $q ) ) { continue; }
      $a = mt_vq_phrase( (string) ( $r['mltv5_faq_comparatif_reponse'] ?? '' ) );
      if ( $a !== '' ) { $items[] = array( $q, $a ); }
    }
    if ( count( $items ) < 3 ) { return ''; }
    $li = '';
    foreach ( $items as $it ) { $li .= '<li><b>' . esc_html( $it[0] ) . '</b> ' . esc_html( $it[1] ) . '</li>'; }
    return '<div class="mt-faq-mini"><h2>Vos questions</h2><ul>' . $li . '</ul><p><a href="#partie-faq">Toutes les réponses</a></p></div>';
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

  <?php
  /* Multi-comparatif : nombre de produits comparés = MÊME compteur que l'encart
     « Pourquoi nous faire confiance » (hero-encart) : avis publiés du même type
     + attributs du parent (+5 si < 10). Jamais inférieur au nombre de produits
     affichés dans les encarts résumé. */
  if ( ! function_exists( 'mtv2_hero_count' ) ) {
    function mtv2_hero_count( $id, $plan ) {
      static $memo = array();
      if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
      $n = 0;
      $prod = get_the_terms( $id, 'post-type-produit' );
      if ( is_array( $prod ) && ! empty( $prod ) ) {
        $tq = array( array( 'taxonomy' => 'post-type-produit', 'terms' => wp_list_pluck( $prod, 'term_id' ) ) );
        $attr = get_the_terms( $id, 'post-type-attribut' );
        if ( is_array( $attr ) && ! empty( $attr ) ) {
          $tq['relation'] = 'AND';
          $tq[] = array( 'taxonomy' => 'post-type-attribut', 'terms' => wp_list_pluck( $attr, 'term_id' ), 'operator' => 'AND' );
        }
        $q = new WP_Query( array(
          'post_type'              => 'avis',
          'post_status'            => 'publish',
          'tax_query'              => $tq,
          'posts_per_page'         => -1,
          'fields'                 => 'ids',
          'no_found_rows'          => true,
          'update_post_meta_cache' => false,
          'update_post_term_cache' => false,
        ) );
        $n = count( $q->posts );
        if ( $n < 10 ) { $n += 5; }
      }
      $shown = $plan ? count( $plan['origin'] ) : 0;
      return $memo[ $id ] = max( $n, $shown );
    }
  }
  ?>
  <?php // Effets SEO Rank Math
  /* Title (tests Jev du 2026-10-02, voir CLAUDE.md) : titre forcé s'il est rempli ;
     sinon « Meilleur(e) X 2026 : N analysé(e)s, T retenu(e)s », N = vrai nombre d'avis
     publiés du type + attributs, +5 si < 10 comme l'encadré, T = produits du classement ; si
     N <= T, repli sur « Meilleur(e) X 2026 (N produits comparés) ».
     Méta description : une description saisie à la main (différente de l'extrait) n'est
     plus écrasée ; la description automatique est mt_meta_auto() sur un comparatif (format M3
     des tests Jev), les 50 premiers mots de l'introduction sinon. L'extrait reste l'introduction. */
  if ( ! function_exists( 'mt_avis_count' ) ) {
    /* Avis publiés du même type de produit et de TOUS les attributs du comparatif. */
    function mt_avis_count( $id ) {
      static $memo = array();
      if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
      $n = 0;
      $prod = get_the_terms( $id, 'post-type-produit' );
      if ( is_array( $prod ) && ! empty( $prod ) ) {
        $tq = array( array( 'taxonomy' => 'post-type-produit', 'terms' => wp_list_pluck( $prod, 'term_id' ) ) );
        $attr = get_the_terms( $id, 'post-type-attribut' );
        if ( is_array( $attr ) && ! empty( $attr ) ) {
          $tq['relation'] = 'AND';
          $tq[] = array( 'taxonomy' => 'post-type-attribut', 'terms' => wp_list_pluck( $attr, 'term_id' ), 'operator' => 'AND' );
        }
        $q = new WP_Query( array(
          'post_type'              => 'avis',
          'post_status'            => 'publish',
          'tax_query'              => $tq,
          'posts_per_page'         => -1,
          'fields'                 => 'ids',
          'no_found_rows'          => true,
          'update_post_meta_cache' => false,
          'update_post_term_cache' => false,
        ) );
        $n = count( $q->posts );
      }
      return $memo[ $id ] = $n;
    }
  }
  if ( ! function_exists( 'mt_title_auto' ) ) {
    /* « Meilleur(e) X » accordé via lalalesmeilleur (le meilleur / la meilleure → type au
       singulier ; les … → pluriel), repli masculinsfeminins + type au pluriel. */
    function mt_title_auto( $n, $t, $llm, $sing, $plur, $mf ) {
      $llm  = trim( (string) $llm );
      $adj  = trim( preg_replace( '/^(le|la|les)\s+/iu', '', $llm ) );
      $sing = trim( (string) $sing );
      $plur = trim( (string) $plur );
      $type = ( preg_match( '/^les\s/iu', $llm ) || $sing === '' ) ? $plur : $sing;
      if ( $adj === '' ) { $adj = lcfirst( trim( (string) $mf ) !== '' ? trim( (string) $mf ) : 'meilleurs' ); $type = $plur; }
      $e    = preg_match( '/^meilleures?$/iu', $adj ) ? 'e' : '';
      $tete = trim( $adj . ' ' . $type );
      $tete = mb_strtoupper( mb_substr( $tete, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $tete, 1, null, 'UTF-8' );
      $an   = date_i18n( 'Y' );
      if ( $t > 0 && $n > $t ) { $titre = $tete . ' ' . $an . ' : ' . $n . ' analysé' . $e . 's, ' . $t . ' retenu' . $e . 's'; }
      else { $titre = $tete . ' ' . $an . ' (' . max( $n, $t ) . ' produits comparés)'; }
      return html_entity_decode( $titre, ENT_QUOTES, 'UTF-8' );
    }
  }
  /* N = produits analysés (title, méta, verdict) = MÊME compteur que l'encadré « Pourquoi nous faire
     confiance » : avis publiés du type + attributs, +5 si < 10 (décision de Samuel : la rédaction analyse
     au moins 5 produits de plus que ceux publiés). Multi-comparatif : jamais moins que les produits affichés. */
  $mt_n = 0;
  $mt_pl = null;
  if ($post_type === 'comparatif') {
      $mt_n = mt_avis_count( $this_id );
      if ( $mt_n < 10 ) { $mt_n += 5; }
      $mt_pl = function_exists( 'mtv2_plan' ) ? mtv2_plan( $this_id ) : null;
      if ( $mt_pl && ! empty( $mt_pl['is_multi'] ) ) { $mt_n = max( $mt_n, count( $mt_pl['origin'] ) ); }
  }
  if (($template_description ?? '') == 0 || $post_type === 'liste') {
      $rank_math_description = (string) get_post_meta($this_id, 'rank_math_description', true);
      $mt_meta_prec = (string) get_post_meta($this_id, '_mt_meta_auto', true);
      $p = get_post($this_id);
      $excerpt = (string) ($p->post_excerpt ?? '');
      $intro50 = intro(50, $this_id);
      /* Méta automatique si elle est vide, égale à la dernière méta générée (_mt_meta_auto, caché) ou à
         l'extrait (ancien mode : méta = extrait). Toute autre valeur a été saisie à la main : gardée. */
      if ($rank_math_description === '' || $rank_math_description === $mt_meta_prec || $rank_math_description === $excerpt) {
          /* Comparatif : méta tirée des données (repli : 50 premiers mots de l'introduction). */
          $new_desc = ($post_type === 'comparatif') ? mt_meta_auto( $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $lalalesmeilleur ?? '', $mt_n ) : '';
          if ($new_desc === '') { $new_desc = $intro50; }
          if (($new_desc !== $rank_math_description) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_description', $new_desc); }
          if ($new_desc !== $mt_meta_prec) { update_post_meta($this_id, '_mt_meta_auto', $new_desc); }
      }
      /* Extrait : toujours les 50 premiers mots de l'introduction (affiché dans les cartes du site). */
      if ($excerpt !== $intro50) { wp_update_post(array('ID'=>$this_id,'post_excerpt'=>$intro50)); }
  }
  if (!empty($forcer_affichage_du_titre ?? '')) { $new_title = $forcer_affichage_du_titre; }
  elseif ($post_type === 'liste') { $new_title = get_the_title($this_id); }
  elseif ($post_type === 'comparatif') {
      $new_title = mt_title_auto( $mt_n, $total_avis, $lalalesmeilleur ?? '', $type_de_produit_au_singulier ?? '', $type_de_produit_au_pluriel ?? '', $masculinsfeminins ?? '' );
  }
  else { $new_title = "Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." 2026 | Test par Meilleurtest"; }
  $rank_math_title = (string) get_post_meta($this_id, 'rank_math_title', true);
  if (($new_title !== $rank_math_title) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_title', $new_title); }
  ?>

  <h1 class="mt-h1">
  <?php
    if (!empty($forcer_affichage_du_titre ?? '')) {
        echo esc_html($forcer_affichage_du_titre);
    } elseif ($MT_H1_EGAL_TITLE && $post_type === 'comparatif') {
        echo esc_html($new_title);
    } elseif ($post_type === 'comparatif') {
        echo 'Les <em>' . $total_avis . ' ' . lcfirst($masculinsfeminins ?? 'meilleures') . ' ' . $type_de_produit_au_pluriel . '</em> en 2026';
        /* Multi-comparatif : « : Guide ultime (N produits comparés) »,
           N = mtv2_hero_count() (même chiffre que l'encart de confiance). */
        $mtv2_hplan = function_exists( 'mtv2_plan' ) ? mtv2_plan( $this_id ) : null;
        $mtv2_hnb   = ( $mtv2_hplan && $mtv2_hplan['is_multi'] ) ? mtv2_hero_count( $this_id, $mtv2_hplan ) : 0;
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


  <?php if ( $MT_SHOW_INTRO_RECO && $MT_VERDICT_SOUS_H1 && $post_type === 'comparatif' ) {
      /* k = critères de choix du guide (page, sinon annexe en cache) */
      $mt_crit = get_field( 'mltv5_criteres_de_choix', $this_id );
      if ( empty( $mt_crit ) ) {
          $mt_cid  = (int) get_field( 'mltv5_cached_id_criteres', $this_id );
          $mt_crit = $mt_cid ? get_field( 'mltv5_criteres_de_choix', $mt_cid ) : array();
      }
      /* Profils = libellés courts des sections du multi-comparatif (comme le sommaire), 1re lettre en minuscule */
      $mt_profils = array();
      if ( ! empty( $mt_pl['is_multi'] ) ) {
          foreach ( $mt_pl['subs'] as $mt_sb ) {
              $mt_l = trim( (string) $mt_sb['label'] );
              if ( $mt_l !== '' ) { $mt_profils[] = mb_strtolower( mb_substr( $mt_l, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $mt_l, 1, null, 'UTF-8' ); }
          }
      }
      echo mt_verdict_ouverture( $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $type_de_produit_au_singulier ?? '', $lalalesmeilleur ?? '', $mt_n, $mod, is_array( $mt_crit ) ? count( $mt_crit ) : 0, $mt_profils );
  } ?>

  <?php if ( $MT_VOS_QUESTIONS !== '' && $post_type === 'comparatif' ) {
      /* Encart « Vos questions » : affiché ici, ou confié au bloc du top 5 (résumé V1 / multi-resume V2) qui l'affiche juste avant */
      $mt_vq = mt_vos_questions( $this_id, $top_avis_ids ?? array(), $type_de_produit_au_singulier ?? '', $type_de_produit_au_pluriel ?? '', $lalalesmeilleur ?? '' );
      if ( $MT_VOS_QUESTIONS === 'avant_top5' ) { $GLOBALS['mt_vos_questions'] = $mt_vq; } else { echo $mt_vq; }
  } ?>

  <div class="mt-byline">
    <?php if (!empty($author_avatar_id ?? '')) {
        echo '<span class="mt-avatar">' . wp_get_attachment_image($author_avatar_id, array(30,30), '', array('alt'=>$author_avatar_alt ?? '')) . '</span>';
    } ?>
    <span class="mt-byline-text">
      <?php if ( $MT_VERIFIE_PAR !== '' && trim( (string) ( $author ?? '' ) ) === $MT_VERIFIE_PAR ) { ?>
      <span>Rédigé et vérifié par <b><?php echo esc_html( $MT_VERIFIE_PAR ); ?></b>, responsable éditorial</span>
      <?php } else { ?>
      <span>Par <b><?php echo esc_html($author ?? ''); ?></b></span>
      <?php if ( $MT_VERIFIE_PAR !== '' ) {
          /* Forme courte (« S. Petit, resp. éditorial ») affichée sur mobile par le CSS via data-court :
             seule la forme complète est dans le texte de la page (lue par Jev et les lecteurs d'écran). */
          $mt_vp = preg_split( '/\s+/u', trim( $MT_VERIFIE_PAR ), 2 );
          $mt_vp_court = count( $mt_vp ) === 2 ? mb_substr( $mt_vp[0], 0, 1, 'UTF-8' ) . '. ' . $mt_vp[1] : $MT_VERIFIE_PAR; ?>
      <span class="mt-dot">&bull;</span>
      <span class="mt-verif" data-court="<?php echo esc_attr( 'Vérifié par ' . $mt_vp_court . ', resp. éditorial' ); ?>">Vérifié par <b><?php echo esc_html( $MT_VERIFIE_PAR ); ?></b>, responsable éditorial</span>
      <?php } } ?>
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
  if ( $MT_SHOW_INTRO_RECO && ! $MT_VERDICT_SOUS_H1 && $post_type === 'comparatif' ) {
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
