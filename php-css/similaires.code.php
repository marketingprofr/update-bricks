<?php
/* =====================================================================
   MEILLEURTEST — « Comparatifs similaires »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (similaires.css).
   Emplacement : SOUS le tableau comparatif, AVANT le guide d'achat.

   Recommande jusqu'à 20 comparatifs les plus proches du comparatif courant,
   classés du plus proche au moins proche. Le comparatif courant est affiché
   EN PLUS, en tête et ACCENTUÉ (il n'occupe pas un slot de recommandation).
   ⚠️ Pour que ce bloc et « Tous les guides » (guides-similaires.code.php)
   recommandent EXACTEMENT les mêmes comparatifs, garder $CS_MAX == $GS_MAX.

   Proximité (≤ 3 requêtes — ici 2 WP_Query) :
     0) le comparatif courant                                  (accentué, en tête)
     2) même taxonomie produit + TOUS les mêmes attributs      } Requête A
     3) même taxonomie produit (attributs différents)          } (1 seule requête)
     4) même catégorie WordPress                               > Requête B
   Les termes attributs/catégories des candidats sont lus depuis le cache
   d'objets amorcé par WP_Query (update_post_term_cache) -> aucune requête en +.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG — slugs des taxonomies + réglages (à confirmer côté site)
   --------------------------------------------------------------------- */
$CS_TAX_PRODUCT = 'post-type-produit';    // taxonomie « produit »
$CS_TAX_ATTR    = 'post-type-attribut';   // taxonomie « attributs »
$CS_TAX_CAT     = 'category';             // catégorie principale (WP standard)
$CS_MAX         = 20;                     // nb de comparatifs recommandés (courant affiché EN PLUS) — garder == $GS_MAX
$CS_LABEL_ACF   = '';                     // champ ACF pour un libellé court ; vide = titre du post
$CS_TITLE       = 'Comparatifs similaires';
$CS_ANCHOR      = 'partie-comparatifs-similaires';

/* ---------------------------------------------------------------------
   Helpers (déclarés une fois — cohabitent avec les autres blocs Code)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt_sim_term_ids' ) ) {
  /* IDs des termes d'un post pour une taxonomie (array vide si aucun). */
  function mt_sim_term_ids( $post_id, $tax ) {
    $terms = get_the_terms( $post_id, $tax );
    if ( ! is_array( $terms ) ) { return array(); }
    $ids = array();
    foreach ( $terms as $t ) {
      if ( is_object( $t ) && isset( $t->term_id ) ) { $ids[] = (int) $t->term_id; }
    }
    return $ids;
  }
}
if ( ! function_exists( 'mt_sim_ranked_ids' ) ) {
  /* MOTEUR DE SIMILARITÉ PARTAGÉ (identique dans similaires.code.php et
     guides-similaires.code.php ; le 1er bloc chargé le définit, les autres
     réutilisent). Renvoie les IDs des comparatifs les plus proches de $cur_id
     (comparatif courant EXCLU), classés du plus proche au moins proche, en
     ≤ 2 WP_Query.
     $opts : tax_product, tax_attr, tax_cat (slugs) + max (nb max renvoyé).
     Paliers : 2 = même produit + TOUS les mêmes attributs (superset) ;
               3 = même produit (attributs différents) ; 4 = même catégorie.
     Tri : palier ↑, attributs partagés ↓, catégories partagées ↓, ID ↑. */
  function mt_sim_ranked_ids( $cur_id, $opts = array() ) {
    $tax_prod = isset( $opts['tax_product'] ) ? $opts['tax_product'] : 'post-type-produit';
    $tax_attr = isset( $opts['tax_attr'] )    ? $opts['tax_attr']    : 'post-type-attribut';
    $tax_cat  = isset( $opts['tax_cat'] )     ? $opts['tax_cat']     : 'category';
    $max      = isset( $opts['max'] )         ? (int) $opts['max']   : 20;

    $cur_id   = (int) $cur_id;
    $cur_type = get_post_type( $cur_id );
    if ( ! $cur_type || $max < 1 ) { return array(); }

    $cur_prod = mt_sim_term_ids( $cur_id, $tax_prod );
    $cur_attr = mt_sim_term_ids( $cur_id, $tax_attr );
    $cur_cat  = mt_sim_term_ids( $cur_id, $tax_cat );
    $cur_attr_total = count( $cur_attr );

    $seen  = array( $cur_id => true );   // jamais re-proposer le courant
    $cands = array();                    // id => données de tri

    /* Requête A — même taxonomie produit (paliers 2 & 3) */
    if ( ! empty( $cur_prod ) ) {
      $qa = new WP_Query( array(
        'post_type'           => $cur_type,
        'post_status'         => 'publish',
        'posts_per_page'      => 60,          // marge : on trie puis on coupe
        'post__not_in'        => array( $cur_id ),
        'orderby'             => 'date',
        'order'               => 'DESC',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'tax_query'           => array( array(
          'taxonomy' => $tax_prod, 'field' => 'term_id', 'terms' => $cur_prod,
        ) ),
      ) );
      foreach ( $qa->posts as $p ) {
        $pid = (int) $p->ID;
        if ( isset( $seen[ $pid ] ) ) { continue; }
        $seen[ $pid ] = true;

        $a           = mt_sim_term_ids( $pid, $tax_attr );
        $attr_shared = count( array_intersect( $cur_attr, $a ) );
        $has_all_att = ( $cur_attr_total === 0 ) ? true : ( $attr_shared === $cur_attr_total );
        $c           = mt_sim_term_ids( $pid, $tax_cat );

        $cands[ $pid ] = array(
          'id'   => $pid,
          'tier' => $has_all_att ? 2 : 3,
          'attr' => $attr_shared,
          'cat'  => count( array_intersect( $cur_cat, $c ) ),
        );
      }
      wp_reset_postdata();
    }

    /* Requête B — même catégorie WordPress (palier 4), hors déjà pris */
    $need = $max - count( $cands );
    if ( ! empty( $cur_cat ) && $need > 0 ) {
      $qb = new WP_Query( array(
        'post_type'           => $cur_type,
        'post_status'         => 'publish',
        'posts_per_page'      => $need,
        'post__not_in'        => array_keys( $seen ),
        'orderby'             => 'date',
        'order'               => 'DESC',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'tax_query'           => array( array(
          'taxonomy' => $tax_cat, 'field' => 'term_id', 'terms' => $cur_cat,
        ) ),
      ) );
      foreach ( $qb->posts as $p ) {
        $pid = (int) $p->ID;
        if ( isset( $seen[ $pid ] ) ) { continue; }
        $seen[ $pid ] = true;

        $c = mt_sim_term_ids( $pid, $tax_cat );
        $cands[ $pid ] = array(
          'id'   => $pid,
          'tier' => 4,
          'attr' => 0,
          'cat'  => count( array_intersect( $cur_cat, $c ) ),
        );
      }
      wp_reset_postdata();
    }

    /* Tri du plus proche au moins proche + coupe */
    $list = array_values( $cands );
    usort( $list, function ( $x, $y ) {
      if ( $x['tier'] !== $y['tier'] ) { return $x['tier'] - $y['tier']; }
      if ( $x['attr'] !== $y['attr'] ) { return $y['attr'] - $x['attr']; }
      if ( $x['cat']  !== $y['cat']  ) { return $y['cat']  - $x['cat']; }
      return $x['id'] - $y['id'];
    } );
    $list = array_slice( $list, 0, $max );

    $ids = array();
    foreach ( $list as $it ) { $ids[] = $it['id']; }
    return $ids;
  }
}
if ( ! function_exists( 'mt_sim_ucfirst' ) ) {
  /* ucfirst multi-octets (accents FR : « épilateurs » -> « Épilateurs »). */
  function mt_sim_ucfirst( $s ) {
    if ( $s === '' ) { return $s; }
    $first = mb_substr( $s, 0, 1, 'UTF-8' );
    $rest  = mb_substr( $s, 1, null, 'UTF-8' );
    return mb_strtoupper( $first, 'UTF-8' ) . $rest;
  }
}
if ( ! function_exists( 'mt_sim_clean_label' ) ) {
  /* Retire le préfixe « (Le/La/Les) meilleur(e)(s) … » puis force la capitale.
     « Les meilleurs barbecues » -> « Barbecues » ;
     « Les meilleures chaussures de sport » -> « Chaussures de sport » ;
     « Les meilleurs VTT pas chers » -> « VTT pas chers ». */
  function mt_sim_clean_label( $label ) {
    $label = trim( wp_strip_all_tags( (string) $label ) );
    $stripped = preg_replace( '/^\s*l(?:a|es?)\s+meilleure?s?\s+/iu', '', $label );
    $stripped = trim( (string) $stripped );
    if ( $stripped === '' ) { $stripped = $label; }   // titre entièrement consommé -> repli
    return mt_sim_ucfirst( $stripped );
  }
}
if ( ! function_exists( 'mt_sim_label' ) ) {
  /* Libellé de la pastille : champ ACF court si défini, sinon titre du post,
     nettoyé du préfixe « Les meilleur(e)s… » et capitalisé. */
  function mt_sim_label( $post_id, $acf_field ) {
    if ( $acf_field !== '' && function_exists( 'get_field' ) ) {
      $v = get_field( $acf_field, $post_id );
      if ( is_string( $v ) && trim( $v ) !== '' ) { return mt_sim_clean_label( $v ); }
    }
    return mt_sim_clean_label( get_the_title( $post_id ) );
  }
}

/* ---------------------------------------------------------------------
   Classement via le moteur de similarité partagé.
   max = $CS_MAX recommandations (identiques à la grille si $CS_MAX == $GS_MAX) ;
   le comparatif courant est ajouté EN PLUS, en tête (accentué).
   --------------------------------------------------------------------- */
$cur_id = get_the_ID();
$ids    = mt_sim_ranked_ids( $cur_id, array(
  'tax_product' => $CS_TAX_PRODUCT,
  'tax_attr'    => $CS_TAX_ATTR,
  'tax_cat'     => $CS_TAX_CAT,
  'max'         => (int) $CS_MAX,
) );

/* ---------------------------------------------------------------------
   Rendu — masqué s'il n'y a aucune recommandation (le courant seul est inutile)
   --------------------------------------------------------------------- */
if ( ! empty( $ids ) ) :
  $cur_label = mt_sim_label( $cur_id, $CS_LABEL_ACF );
  ?>
  <section class="mt-similar" id="<?php echo esc_attr( $CS_ANCHOR ); ?>">
    <h2 class="mt-similar-h2"><?php echo esc_html( $CS_TITLE ); ?></h2>
    <div class="mt-similar-grid">
      <span class="mt-similar-pill is-current" aria-current="page"><?php echo esc_html( $cur_label ); ?></span>
      <?php foreach ( $ids as $pid ) :
        $url = get_permalink( $pid );
        if ( ! $url ) { continue; }
        ?>
        <a class="mt-similar-pill" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( mt_sim_label( $pid, $CS_LABEL_ACF ) ); ?></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
