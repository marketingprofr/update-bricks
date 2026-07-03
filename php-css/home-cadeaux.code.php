<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §8 IDÉES CADEAUX ET LOISIRS (grille de 4)
   UN SEUL élément CODE Bricks (Execute code = ON) : SECTION > CONTAINER >
   CODE. CSS dans l'onglet CSS (home-cadeaux.css). Scope : .mt-hg.
   Réf. maquette : templates/Home.html (section « Les idées cadeaux »).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-white)  (blanc)

   Données : les dernières « listes » (post type liste), toutes catégories,
   classées par date de modification. Étiquette « Idée cadeau ».
   ⚠️ Pas de prix inventé : le prix ne s'affiche que si $HG_PRICE_META est
   renseigné ET présent sur le guide ; sinon on montre le nb de modèles.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$HG_POST_TYPE  = 'liste';   // idées cadeaux/loisirs = uniquement les « listes »
$HG_COUNT      = 4;
$HG_PRICE_META = '';         // meta/ACF « prix à partir de » (vide = pas de prix)

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
   Requête : dernières « listes » modifiées (toutes catégories)
   --------------------------------------------------------------------- */
$hg_q = new WP_Query( array(
  'post_type'           => $HG_POST_TYPE,
  'post_status'         => 'publish',
  'posts_per_page'      => (int) $HG_COUNT,
  'orderby'             => 'modified',
  'order'               => 'DESC',
  'no_found_rows'       => true,
  'ignore_sticky_posts' => true,
) );
if ( ! $hg_q->have_posts() ) { wp_reset_postdata(); return; }
?>

<section class="mt-hg">
  <div class="mt-sec-head">
    <div>
      <p class="eyebrow">Faire plaisir</p>
      <h2>Les idées cadeaux et loisirs</h2>
      <p>Nos meilleures sélections pour offrir — ou se faire plaisir — sans se tromper.</p>
    </div>
  </div>

  <div class="mt-guide-grid">
    <?php while ( $hg_q->have_posts() ) : $hg_q->the_post();
      $pid   = get_the_ID();
      $parts = array();

      if ( $HG_PRICE_META !== '' ) {
        $price = function_exists( 'get_field' ) ? get_field( $HG_PRICE_META, $pid ) : get_post_meta( $pid, $HG_PRICE_META, true );
        $price = is_array( $price ) ? '' : trim( (string) $price );
        if ( $price !== '' ) {
          $num = is_numeric( $price ) ? number_format_i18n( (float) $price ) . ' €' : $price;
          $parts[] = 'Dès <b>' . esc_html( $num ) . '</b>';
        }
      }

      $tv    = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $pid ) : array();
      $nprod = isset( $tv['produits_analyses'] ) ? (int) $tv['produits_analyses'] : 0;
      if ( $nprod > 0 ) { $parts[] = esc_html( $nprod ) . ' modèles'; }

      $meta = ! empty( $parts ) ? implode( '<span class="dot">·</span>', array_map( function ( $p ) { return '<span>' . $p . '</span>'; }, $parts ) ) : '';

      mt_home_card( $pid, array(
        'words'     => 15,
        'tag'       => 'Idée cadeau',
        'meta'      => $meta,
      ) );
    endwhile; ?>
  </div>
</section>
<?php wp_reset_postdata(); ?>
