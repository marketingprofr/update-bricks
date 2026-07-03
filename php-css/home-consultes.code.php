<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §7 LES PLUS CONSULTÉS (top classé, 2 colonnes)
   UN SEUL élément CODE Bricks (Execute code = ON) : SECTION > CONTAINER >
   CODE. CSS dans l'onglet CSS (home-consultes.css). Scope : .mt-hc.
   Réf. maquette : templates/Home.html (section « Les plus consultés »).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-grey-l-5)  (gris clair)

   Données : comparatifs + listes les plus vus (Independent Analytics,
   meta iawp_total_views). Numérotés 1..N, nombre de vues abrégé (k).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$HC_POST_TYPES = array( 'comparatif', 'liste' );
$HC_COUNT      = 10;
$HC_VIEWS_META = 'iawp_total_views';

/* ---------------------------------------------------------------------
   Helpers accueil — guardés (byte-identique entre blocs)
   --------------------------------------------------------------------- */
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

/* ---------------------------------------------------------------------
   Requête : top vues
   --------------------------------------------------------------------- */
$hc_q = new WP_Query( array_merge(
  array(
    'post_type'           => $HC_POST_TYPES,
    'post_status'         => 'publish',
    'posts_per_page'      => (int) $HC_COUNT,
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
  ),
  mt_home_popular_args( $HC_VIEWS_META )
) );
if ( ! $hc_q->have_posts() ) { wp_reset_postdata(); return; }

$eye = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
$rank = 0;
?>

<section class="mt-hc">
  <div class="mt-sec-head">
    <div>
      <p class="eyebrow">Le palmarès des lecteurs</p>
      <h2>Les guides les plus consultés</h2>
      <p>Les <?php echo (int) $HC_COUNT; ?> guides que nos lecteurs ont ouverts le plus souvent.</p>
    </div>
  </div>

  <div class="mt-rank">
    <?php while ( $hc_q->have_posts() ) : $hc_q->the_post();
      $pid   = get_the_ID();
      $rank++;
      $cat   = mt_home_primary_cat( $pid );
      $views = (int) get_post_meta( $pid, $HC_VIEWS_META, true );
      if ( $views >= 1000 ) {
        $vdisp = number_format_i18n( round( $views / 1000 ) ) . ' k';
      } else {
        $vdisp = $views > 0 ? number_format_i18n( $views ) : '';
      }
      ?>
      <a class="mt-rank-item" href="<?php the_permalink(); ?>">
        <span class="mt-rank-num"><?php echo (int) $rank; ?></span>
        <span class="mt-rank-body">
          <?php if ( $cat !== '' ) : ?><span class="mt-rank-tag"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
          <span class="mt-rank-title"><?php echo esc_html( get_the_title() ); ?></span>
        </span>
        <?php if ( $vdisp !== '' ) : ?><span class="mt-rank-meta"><?php echo $eye; /* SVG contrôlé */ ?> <?php echo esc_html( $vdisp ); ?></span><?php endif; ?>
      </a>
    <?php endwhile; ?>
  </div>
</section>
<?php wp_reset_postdata(); ?>
