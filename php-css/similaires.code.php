<?php
/* =====================================================================
   MEILLEURTEST — « Comparatifs similaires »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (similaires.css).
   Emplacement : SOUS le tableau comparatif, AVANT le guide d'achat.

   Recommande jusqu'à 20 comparatifs les plus proches du comparatif courant,
   classés du plus proche au moins proche. Le comparatif courant est INCLUS
   dans la liste et mis en ACCENTUÉ.

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
$CS_MAX         = 20;                     // nb max d'items (comparatif courant INCLUS)
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
   Contexte : comparatif courant + ses termes
   --------------------------------------------------------------------- */
$cur_id   = get_the_ID();
$cur_type = get_post_type( $cur_id );

$cur_prod = mt_sim_term_ids( $cur_id, $CS_TAX_PRODUCT );
$cur_attr = mt_sim_term_ids( $cur_id, $CS_TAX_ATTR );
$cur_cat  = mt_sim_term_ids( $cur_id, $CS_TAX_CAT );
$cur_attr_total = count( $cur_attr );

$seen  = array( (int) $cur_id => true );   // jamais re-proposer le courant
$cands = array();                          // id => données de tri

/* ---------------------------------------------------------------------
   Requête A — même taxonomie produit (paliers 2 & 3)
   --------------------------------------------------------------------- */
if ( ! empty( $cur_prod ) && $cur_type ) {
  $qa = new WP_Query( array(
    'post_type'           => $cur_type,
    'post_status'         => 'publish',
    'posts_per_page'      => 60,          // marge : on trie puis on coupe
    'post__not_in'        => array( (int) $cur_id ),
    'orderby'             => 'date',
    'order'               => 'DESC',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
    'tax_query'           => array( array(
      'taxonomy' => $CS_TAX_PRODUCT,
      'field'    => 'term_id',
      'terms'    => $cur_prod,
    ) ),
  ) );
  foreach ( $qa->posts as $p ) {
    $pid = (int) $p->ID;
    if ( isset( $seen[ $pid ] ) ) { continue; }
    $seen[ $pid ] = true;

    $a           = mt_sim_term_ids( $pid, $CS_TAX_ATTR );
    $attr_shared = count( array_intersect( $cur_attr, $a ) );
    $has_all_att = ( $cur_attr_total === 0 ) ? true : ( $attr_shared === $cur_attr_total );
    $c           = mt_sim_term_ids( $pid, $CS_TAX_CAT );

    $cands[ $pid ] = array(
      'id'   => $pid,
      'tier' => $has_all_att ? 2 : 3,
      'attr' => $attr_shared,
      'cat'  => count( array_intersect( $cur_cat, $c ) ),
      'date' => (int) get_post_time( 'U', true, $pid ),
    );
  }
  wp_reset_postdata();
}

/* ---------------------------------------------------------------------
   Requête B — même catégorie WordPress (palier 4), hors déjà pris
   --------------------------------------------------------------------- */
$need = $CS_MAX - 1 - count( $cands );   // -1 : place réservée au comparatif courant
if ( ! empty( $cur_cat ) && $cur_type && $need > 0 ) {
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
      'taxonomy' => $CS_TAX_CAT,
      'field'    => 'term_id',
      'terms'    => $cur_cat,
    ) ),
  ) );
  foreach ( $qb->posts as $p ) {
    $pid = (int) $p->ID;
    if ( isset( $seen[ $pid ] ) ) { continue; }
    $seen[ $pid ] = true;

    $c = mt_sim_term_ids( $pid, $CS_TAX_CAT );
    $cands[ $pid ] = array(
      'id'   => $pid,
      'tier' => 4,
      'attr' => 0,
      'cat'  => count( array_intersect( $cur_cat, $c ) ),
      'date' => (int) get_post_time( 'U', true, $pid ),
    );
  }
  wp_reset_postdata();
}

/* ---------------------------------------------------------------------
   Tri du plus proche au moins proche + coupe
   palier ↑, puis attributs partagés ↓, catégories partagées ↓, date ↓
   --------------------------------------------------------------------- */
$list = array_values( $cands );
usort( $list, function ( $x, $y ) {
  if ( $x['tier'] !== $y['tier'] ) { return $x['tier'] - $y['tier']; }
  if ( $x['attr'] !== $y['attr'] ) { return $y['attr'] - $x['attr']; }
  if ( $x['cat']  !== $y['cat']  ) { return $y['cat']  - $x['cat']; }
  return $y['date'] - $x['date'];
} );
$list = array_slice( $list, 0, max( 0, $CS_MAX - 1 ) );

/* ---------------------------------------------------------------------
   Rendu — masqué s'il n'y a aucune recommandation (le courant seul est inutile)
   --------------------------------------------------------------------- */
if ( ! empty( $list ) ) :
  $cur_label = mt_sim_label( $cur_id, $CS_LABEL_ACF );
  ?>
  <section class="mt-similar" id="<?php echo esc_attr( $CS_ANCHOR ); ?>">
    <h2 class="mt-similar-h2"><?php echo esc_html( $CS_TITLE ); ?></h2>
    <div class="mt-similar-grid">
      <span class="mt-similar-pill is-current" aria-current="page"><?php echo esc_html( $cur_label ); ?></span>
      <?php foreach ( $list as $it ) :
        $url = get_permalink( $it['id'] );
        if ( ! $url ) { continue; }
        ?>
        <a class="mt-similar-pill" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( mt_sim_label( $it['id'], $CS_LABEL_ACF ) ); ?></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
