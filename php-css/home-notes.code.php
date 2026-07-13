<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §5 GUIDES LES MIEUX NOTÉS (grille de 4)
   UN SEUL élément CODE Bricks (Execute code = ON) : SECTION > CONTAINER >
   CODE. CSS dans l'onglet CSS du même élément (home-notes.css). Scope : .mt-hn.
   Réf. maquette : templates/Home.html (section « Guides les mieux notés »).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-white)  (blanc)

   Données : comparatifs + listes bien notés. On NE trie PAS toute la base
   par note (lourd) : on filtre les guides à NOTE ≥ $HN_MIN_RATING, puis on
   les classe par NOMBRE D'AVIS décroissant.
   ⚠️ CONFIG $HN_RATING_META / $HN_COUNT_META À CONFIRMER côté site
   (clés du plugin de notation, ex. Rate my Post). Si rien ne remonte,
   repli automatique sur les plus vus (Independent Analytics).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$HN_POST_TYPES  = array( 'comparatif', 'liste' );
$HN_COUNT       = 4;
$HN_RATING_META = 'rmp_avg_rating';   // ⚠️ note moyenne /5 (à confirmer)
$HN_COUNT_META  = 'rmp_vote_count';   // ⚠️ nombre d'avis (à confirmer)
$HN_MIN_RATING  = 4.7;                // seuil de note (on classe ensuite par nb d'avis)
$HN_VIEWS_META  = 'iawp_total_views'; // repli si aucune note en base

/* ---------------------------------------------------------------------
   Helpers accueil — guardés (byte-identique entre blocs)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt_home_excerpt' ) ) {
  function mt_home_excerpt( $post_id, $words = 16 ) {
    $text = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : get_post_field( 'post_content', $post_id );
    $text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
    $text = trim( preg_replace( '/\s+/', ' ', $text ) );
    return $text === '' ? '' : wp_trim_words( $text, (int) $words, '…' );
  }
}
if ( ! function_exists( 'mt_home_primary_cat' ) ) {
  function mt_home_primary_cat( $post_id ) {
    $terms = get_the_terms( $post_id, 'category' );
    if ( ! is_array( $terms ) || empty( $terms ) ) { return ''; }
    $pc = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
    if ( $pc ) { foreach ( $terms as $t ) { if ( is_object( $t ) && (int) $t->term_id === $pc ) { return $t->name; } } }
    return ( is_object( $terms[0] ) && isset( $terms[0]->name ) ) ? $terms[0]->name : '';
  }
}
if ( ! function_exists( 'mt_home_popular_args' ) ) {
  /* Tri « plus vus » simple et RAPIDE : un seul JOIN indexe sur meta_key,
     classe par vues decroissantes. On ne remonte que les guides AYANT des
     vues (exactement ce qu'on veut pour « plus vus / a la une »). Surtout PAS
     de OR + NOT EXISTS ici : sans filtre de taxonomie sur tout le site, ca
     genere une double jointure + filesort qui fait timeout le serveur. */
  function mt_home_popular_args( $views_meta ) {
    return array(
      'meta_key' => $views_meta,
      'orderby'  => 'meta_value_num',
      'order'    => 'DESC',
    );
  }
}
if ( ! function_exists( 'mt_home_card' ) ) {
  function mt_home_card( $pid, $a = array() ) {
    $img   = get_the_post_thumbnail_url( $pid, 'medium_large' );
    $url   = get_permalink( $pid );
    $title = get_the_title( $pid );
    $cat   = array_key_exists( 'cat', $a ) ? $a['cat'] : mt_home_primary_cat( $pid );
    $desc  = array_key_exists( 'desc', $a ) ? $a['desc'] : mt_home_excerpt( $pid, isset( $a['words'] ) ? (int) $a['words'] : 16 );
    $tag   = isset( $a['tag'] ) ? $a['tag'] : '';
    $tagc  = isset( $a['tag_class'] ) ? $a['tag_class'] : '';
    $meta  = isset( $a['meta'] ) ? $a['meta'] : '';
    ?>
    <article class="mt-card">
      <a class="mt-card-thumb<?php echo $img ? '' : ' ph'; ?>" href="<?php echo esc_url( $url ); ?>">
        <?php if ( $tag !== '' ) : ?><span class="mt-card-tag<?php echo $tagc ? ' ' . esc_attr( $tagc ) : ''; ?>"><?php echo esc_html( $tag ); ?></span><?php endif; ?>
        <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy"><?php endif; ?>
      </a>
      <div class="mt-card-body">
        <?php if ( $cat !== '' ) : ?><span class="mt-card-cat"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
        <h4><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h4>
        <?php if ( $desc !== '' ) : ?><p><?php echo esc_html( $desc ); ?></p><?php endif; ?>
        <?php if ( $meta !== '' ) : ?><div class="mt-card-meta"><?php echo $meta; /* HTML échappé côté appelant */ ?></div><?php endif; ?>
      </div>
    </article>
    <?php
  }
}

/* ---------------------------------------------------------------------
   Requête : note ≥ seuil, classés par nombre d'avis décroissant
   (repli sur les plus vus si rien ne remonte)
   --------------------------------------------------------------------- */
$hn_excl = isset( $GLOBALS['mt_home_exclude'] ) ? array_map( 'intval', (array) $GLOBALS['mt_home_exclude'] ) : array();
$hn_args = array(
  'post_type'           => $HN_POST_TYPES,
  'post_status'         => 'publish',
  'posts_per_page'      => (int) $HN_COUNT,
  'no_found_rows'       => true,
  'ignore_sticky_posts' => true,
  'meta_query'          => array(
    'rating' => array( 'key' => $HN_RATING_META, 'value' => $HN_MIN_RATING, 'compare' => '>=', 'type' => 'DECIMAL' ),
    'votes'  => array( 'key' => $HN_COUNT_META, 'compare' => 'EXISTS', 'type' => 'NUMERIC' ),
  ),
  'orderby'             => array( 'votes' => 'DESC' ),
);
if ( ! empty( $hn_excl ) ) { $hn_args['post__not_in'] = $hn_excl; }
$hn_q = new WP_Query( $hn_args );
$hn_has_ratings = $hn_q->have_posts();
if ( ! $hn_has_ratings ) {
  wp_reset_postdata();
  $hn_fallback = array_merge(
    array(
      'post_type'           => $HN_POST_TYPES,
      'post_status'         => 'publish',
      'posts_per_page'      => (int) $HN_COUNT,
      'no_found_rows'       => true,
      'ignore_sticky_posts' => true,
    ),
    mt_home_popular_args( $HN_VIEWS_META )
  );
  if ( ! empty( $hn_excl ) ) { $hn_fallback['post__not_in'] = $hn_excl; }
  $hn_q = new WP_Query( $hn_fallback );
}
if ( ! $hn_q->have_posts() ) { wp_reset_postdata(); return; }
?>

<section class="mt-hn">
  <div class="mt-sec-head">
    <div>
      <p class="eyebrow">Plébiscités</p>
      <h2>Guides les mieux notés</h2>
      <p>Les comparatifs qui récoltent les meilleures notes de nos lecteurs, toutes catégories confondues.</p>
    </div>
  </div>

  <div class="mt-guide-grid">
    <?php while ( $hn_q->have_posts() ) : $hn_q->the_post();
      $pid    = get_the_ID();
      $rating = (float) get_post_meta( $pid, $HN_RATING_META, true );
      $votes  = (int) get_post_meta( $pid, $HN_COUNT_META, true );

      $args = array( 'words' => 15 );
      if ( $hn_has_ratings && $rating > 0 ) {
        $full  = (int) round( $rating );
        $full  = max( 0, min( 5, $full ) );
        $stars = str_repeat( '★', $full ) . str_repeat( '☆', 5 - $full );
        $rdisp = number_format_i18n( $rating, 1 );
        $meta  = '<span class="stars">' . $stars . '</span><span><b>' . esc_html( $rdisp ) . '</b>/5</span>';
        if ( $votes > 0 ) { $meta .= '<span class="dot">·</span><span>' . esc_html( number_format_i18n( $votes ) ) . ' avis</span>'; }
        $args['tag']       = '★ ' . $rdisp;
        $args['tag_class'] = 'score';
        $args['meta']      = $meta;
      }
      mt_home_card( $pid, $args );
    endwhile; ?>
  </div>
</section>
<?php wp_reset_postdata(); ?>
