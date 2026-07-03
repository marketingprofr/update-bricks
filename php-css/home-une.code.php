<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §2 NOS GUIDES À LA UNE (feature + grille de 4)
   UN SEUL élément CODE Bricks (Execute code = ON) : SECTION > CONTAINER >
   CODE. CSS dans l'onglet CSS du même élément (home-une.css). Scope : .mt-hu.
   Réf. maquette : templates/Home.html (section « Nos guides à la une »).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-grey-l-5)  (gris clair)

   Données : comparatifs + listes les plus VUS (Independent Analytics,
   meta iawp_total_views). #1 = guide « à la une », #2..#5 = grille.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$HU_POST_TYPES = array( 'comparatif', 'liste' );
$HU_VIEWS_META = 'iawp_total_views';   // meta vues Independent Analytics

/* ---------------------------------------------------------------------
   Helpers accueil — guardés (définition partagée entre blocs, byte-identique)
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
    if ( $pc ) { foreach ( $terms as $t ) { if ( (int) $t->term_id === $pc ) { return $t->name; } } }
    return $terms[0]->name;
  }
}
if ( ! function_exists( 'mt_home_popular_args' ) ) {
  function mt_home_popular_args( $views_meta ) {
    return array(
      'meta_query' => array(
        'relation' => 'OR',
        'hasv' => array( 'key' => $views_meta, 'compare' => 'EXISTS', 'type' => 'NUMERIC' ),
        'nov'  => array( 'key' => $views_meta, 'compare' => 'NOT EXISTS' ),
      ),
      'orderby' => array( 'hasv' => 'DESC', 'date' => 'DESC' ),
    );
  }
}
if ( ! function_exists( 'mt_home_card' ) ) {
  /* Rend une carte guide standard. $a : cat, desc, words, tag, tag_class,
     meta (HTML déjà échappé côté appelant). */
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
   Requête : 5 guides les plus vus (feature + 4 cartes)
   --------------------------------------------------------------------- */
$hu_q = new WP_Query( array_merge(
  array(
    'post_type'           => $HU_POST_TYPES,
    'post_status'         => 'publish',
    'posts_per_page'      => 5,
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
  ),
  mt_home_popular_args( $HU_VIEWS_META )
) );
$hu_ids = wp_list_pluck( $hu_q->posts, 'ID' );
wp_reset_postdata();

if ( empty( $hu_ids ) ) { return; }

$feat_id  = (int) array_shift( $hu_ids );
$grid_ids = array_map( 'intval', $hu_ids );

/* Données du guide à la une */
$f_url    = get_permalink( $feat_id );
$f_title  = get_the_title( $feat_id );
$f_img    = get_the_post_thumbnail_url( $feat_id, 'large' );
$f_cat    = mt_home_primary_cat( $feat_id );
$f_desc   = mt_home_excerpt( $feat_id, 30 );
$f_aid    = (int) get_post_field( 'post_author', $feat_id );
$f_author = get_the_author_meta( 'display_name', $f_aid );
$f_avatar = get_avatar_url( $f_aid, array( 'size' => 60 ) );
$f_date   = get_the_modified_date( 'j M Y', $feat_id );

$f_tv     = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $feat_id ) : array();
$f_nprod  = isset( $f_tv['produits_analyses'] ) ? (int) $f_tv['produits_analyses'] : 0;
$f_heures = isset( $f_tv['heures_investies'] ) ? (int) $f_tv['heures_investies'] : 0;
?>

<section class="mt-hu">
  <div class="mt-sec-head">
    <div>
      <p class="eyebrow">La rédaction recommande</p>
      <h2>Nos guides à la une</h2>
      <p>Nos comparatifs les plus consultés du moment, toutes catégories confondues.</p>
    </div>
  </div>

  <article class="mt-feature">
    <a class="mt-feature-media<?php echo $f_img ? '' : ' ph'; ?>" href="<?php echo esc_url( $f_url ); ?>">
      <span class="mt-feature-flag"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.5 1.6 6.7L12 17l-6.2 3.6 1.6-6.7L2.2 8.9l6.9-.6L12 2z"/></svg> Coup de cœur</span>
      <?php if ( $f_img ) : ?><img src="<?php echo esc_url( $f_img ); ?>" alt="<?php echo esc_attr( $f_title ); ?>"><?php endif; ?>
    </a>
    <div class="mt-feature-body">
      <?php if ( $f_cat !== '' ) : ?><p class="mt-feature-kicker"><?php echo esc_html( $f_cat ); ?></p><?php endif; ?>
      <h3><a href="<?php echo esc_url( $f_url ); ?>"><?php echo esc_html( $f_title ); ?></a></h3>
      <?php if ( $f_desc !== '' ) : ?><p><?php echo esc_html( $f_desc ); ?></p><?php endif; ?>
      <?php if ( $f_nprod || $f_heures ) : ?>
      <div class="mt-feature-chips">
        <?php if ( $f_nprod ) : ?><span class="mt-feature-chip"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/></svg> <?php echo (int) $f_nprod; ?> modèles testés</span><?php endif; ?>
        <?php if ( $f_heures ) : ?><span class="mt-feature-chip"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> <?php echo (int) $f_heures; ?> h de recherche</span><?php endif; ?>
        <span class="mt-feature-chip"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg> 100 % indépendant</span>
      </div>
      <?php endif; ?>
      <div class="mt-feature-foot">
        <span class="mt-feature-byline">
          <?php if ( $f_avatar ) : ?><img class="avatar" src="<?php echo esc_url( $f_avatar ); ?>" alt="" width="30" height="30" loading="lazy"><?php else : ?><span class="avatar"></span><?php endif; ?>
          <span>Par <b><?php echo esc_html( $f_author ); ?></b> · mis à jour le <?php echo esc_html( $f_date ); ?></span>
        </span>
        <a class="mt-btn" href="<?php echo esc_url( $f_url ); ?>">Lire le guide <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></a>
      </div>
    </div>
  </article>

  <?php if ( ! empty( $grid_ids ) ) : ?>
  <div class="mt-guide-grid">
    <?php foreach ( $grid_ids as $pid ) { mt_home_card( $pid, array( 'words' => 15 ) ); } ?>
  </div>
  <?php endif; ?>
</section>
