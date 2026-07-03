<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §4 GUIDES D'ACHAT RÉCENTS (grille de 4)
   UN SEUL élément CODE Bricks (Execute code = ON) : SECTION > CONTAINER >
   CODE. CSS dans l'onglet CSS du même élément (home-recents.css). Scope : .mt-hr.
   Réf. maquette : templates/Home.html (section « Guides d'achat récents »).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-grey-l-5)  (gris clair)

   Données : comparatifs + listes les plus récemment mis à jour. Étiquette
   « Nouveau » (publié récemment, jamais réédité) ou « Mis à jour ».
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$HR_POST_TYPES = array( 'comparatif', 'liste' );
$HR_COUNT      = 4;

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
   Requête : derniers guides modifiés
   --------------------------------------------------------------------- */
$hr_q = new WP_Query( array(
  'post_type'           => $HR_POST_TYPES,
  'post_status'         => 'publish',
  'posts_per_page'      => (int) $HR_COUNT,
  'orderby'             => 'modified',
  'order'               => 'DESC',
  'no_found_rows'       => true,
  'ignore_sticky_posts' => true,
) );
if ( ! $hr_q->have_posts() ) { wp_reset_postdata(); return; }

$clock = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
?>

<section class="mt-hr">
  <style>/* dimensionnement SVG immédiat (anti-FOUC, indépendant de l'onglet CSS) */
    .mt-hr svg{width:14px;height:14px;flex-shrink:0}</style>
  <div class="mt-sec-head">
    <div>
      <p class="eyebrow">Fraîchement publiés</p>
      <h2>Guides d'achat récents</h2>
      <p>Nos derniers comparatifs créés ou réactualisés — parce qu'un bon conseil, c'est un conseil à jour.</p>
    </div>
  </div>

  <div class="mt-guide-grid">
    <?php while ( $hr_q->have_posts() ) : $hr_q->the_post();
      $pid = get_the_ID();
      $pub = (int) get_post_time( 'U', true, $pid );
      $mod = (int) get_post_modified_time( 'U', true, $pid );
      $is_upd = ( $mod - $pub ) > DAY_IN_SECONDS;
      $when   = $is_upd ? 'Mis à jour le ' : 'Ajouté le ';
      $date   = $is_upd ? get_the_modified_date( 'j M Y', $pid ) : get_the_date( 'j M Y', $pid );
      $meta   = $clock . '<span>' . esc_html( $when . $date ) . '</span>';
      mt_home_card( $pid, array(
        'words'     => 16,
        'tag'       => $is_upd ? 'Mis à jour' : 'Nouveau',
        'tag_class' => $is_upd ? 'upd' : '',
        'meta'      => $meta,
      ) );
    endwhile; ?>
  </div>
</section>
<?php wp_reset_postdata(); ?>
