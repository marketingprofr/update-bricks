<?php
/* =====================================================================
   MEILLEURTEST — Archive de catégorie (comparatifs + listes)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON), placé
   dans le template d'archive assigné aux catégories WP.
   Le CSS va dans l'onglet CSS du même élément (category.css).
   Réf. maquette : templates/template-category.html. Scope : .mt-arch.

   Affiche, pour la catégorie courante :
     - fil d'ariane + en-tête (titre, description, stats) ;
     - chips de sous-catégories ;
     - guide « à la une » = le plus récemment mis à jour (page 1 uniquement) ;
     - grille de cartes (comparatifs + listes) paginée + triable ;
     - pagination.
   Tri (?orderby=) : date (récents) · popular (vues Independent Analytics,
   meta `iawp_total_views`) · az (A→Z).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$CAT_POST_TYPES = array( 'comparatif', 'liste' ); // CPT affichés dans l'archive
$CAT_TAXONOMY   = 'category';                     // taxonomie d'archive (WP standard)
$CAT_PER_PAGE   = 12;                             // cartes par page (4 col × 3)
$CAT_VIEWS_META = 'iawp_total_views';             // meta vues (Independent Analytics)
$CAT_TYPE_LABELS = array( 'comparatif' => 'Comparatif', 'liste' => 'Liste' );

/* ---------------------------------------------------------------------
   Helpers (guardés — cohabitent avec les autres blocs Code)
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt_arch_excerpt' ) ) {
  /* Extrait court, sans HTML ni shortcodes, tronqué à $words mots. */
  function mt_arch_excerpt( $post_id, $words = 20 ) {
    $text = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : get_post_field( 'post_content', $post_id );
    $text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
    $text = trim( preg_replace( '/\s+/', ' ', $text ) );
    return $text === '' ? '' : wp_trim_words( $text, (int) $words, '…' );
  }
}
if ( ! function_exists( 'mt_arch_orderby_args' ) ) {
  /* Traduit un slug de tri en arguments WP_Query. « popular » range les
     posts sans meta de vues en dernier (meta_query OR) au lieu de les exclure. */
  function mt_arch_orderby_args( $orderby, $views_meta ) {
    switch ( $orderby ) {
      case 'az':
        return array( 'orderby' => 'title', 'order' => 'ASC' );
      case 'popular':
        return array(
          'meta_query' => array(
            'relation' => 'OR',
            'iawp_has' => array( 'key' => $views_meta, 'compare' => 'EXISTS', 'type' => 'NUMERIC' ),
            'iawp_no'  => array( 'key' => $views_meta, 'compare' => 'NOT EXISTS' ),
          ),
          'orderby' => array( 'iawp_has' => 'DESC', 'date' => 'DESC' ),
        );
      case 'date':
      default:
        return array( 'orderby' => 'date', 'order' => 'DESC' );
    }
  }
}

/* ---------------------------------------------------------------------
   Contexte : terme de la catégorie courante (+ repli builder)
   --------------------------------------------------------------------- */
$term = get_queried_object();
if ( ! ( $term instanceof WP_Term ) ) {
  // Aperçu builder : on prend une catégorie non vide pour ne pas afficher un bloc vide.
  $prev = get_terms( array( 'taxonomy' => $CAT_TAXONOMY, 'hide_empty' => true, 'number' => 1 ) );
  if ( ! empty( $prev ) && ! is_wp_error( $prev ) ) {
    $term = $prev[0];
  } else {
    return;
  }
}
$term_id = (int) $term->term_id;

/* Tri courant */
$allowed_sort = array( 'date', 'popular', 'az' );
$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'date';
if ( ! in_array( $orderby, $allowed_sort, true ) ) { $orderby = 'date'; }

/* Page courante */
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

/* ---------------------------------------------------------------------
   Guide à la une = le plus récemment mis à jour (pour l'exclure de la grille
   partout, et l'afficher en tête en page 1 seulement)
   --------------------------------------------------------------------- */
$featured_id = 0;
$fq = new WP_Query( array(
  'post_type'           => $CAT_POST_TYPES,
  'post_status'         => 'publish',
  'posts_per_page'      => 1,
  'orderby'             => 'modified',
  'order'               => 'DESC',
  'no_found_rows'       => true,
  'ignore_sticky_posts' => true,
  'tax_query'           => array( array(
    'taxonomy' => $CAT_TAXONOMY, 'field' => 'term_id', 'terms' => $term_id,
  ) ),
) );
if ( $fq->have_posts() ) { $featured_id = (int) $fq->posts[0]->ID; }
wp_reset_postdata();

/* ---------------------------------------------------------------------
   Grille paginée (comparatifs + listes), featured exclu partout
   --------------------------------------------------------------------- */
$grid_args = array_merge(
  array(
    'post_type'           => $CAT_POST_TYPES,
    'post_status'         => 'publish',
    'posts_per_page'      => (int) $CAT_PER_PAGE,
    'paged'               => $paged,
    'ignore_sticky_posts' => true,
    'tax_query'           => array( array(
      'taxonomy' => $CAT_TAXONOMY, 'field' => 'term_id', 'terms' => $term_id,
    ) ),
  ),
  mt_arch_orderby_args( $orderby, $CAT_VIEWS_META )
);
if ( $featured_id ) { $grid_args['post__not_in'] = array( $featured_id ); }
$grid = new WP_Query( $grid_args );

$max_pages = max( 1, (int) $grid->max_num_pages );
$total_all = (int) $grid->found_posts + ( $featured_id ? 1 : 0 );

/* Stats d'en-tête */
$last_update = $featured_id ? get_the_modified_date( 'M Y', $featured_id ) : '';

/* Sous-catégories (chips) */
$children = get_terms( array(
  'taxonomy'   => $CAT_TAXONOMY,
  'parent'     => $term_id,
  'hide_empty' => true,
) );
if ( is_wp_error( $children ) ) { $children = array(); }

/* Fil d'ariane : ancêtres */
$ancestors = array_reverse( get_ancestors( $term_id, $CAT_TAXONOMY, 'taxonomy' ) );
$parent_id = (int) $term->parent;
$parent    = $parent_id ? get_term( $parent_id, $CAT_TAXONOMY ) : null;

/* Titre de section / eyebrow */
$eyebrow_ctx = ( $parent && ! is_wp_error( $parent ) ) ? $parent->name : 'Comparatifs & sélections';
$term_link   = get_term_link( $term );
if ( is_wp_error( $term_link ) ) { $term_link = home_url( '/' ); }
$term_desc = trim( (string) term_description( $term_id, $CAT_TAXONOMY ) );
?>

<div class="mt-arch">

  <!-- Fil d'Ariane -->
  <nav class="mt-crumb" aria-label="Fil d'Ariane">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
    <?php foreach ( $ancestors as $anc_id ) :
      $anc = get_term( (int) $anc_id, $CAT_TAXONOMY );
      if ( ! $anc || is_wp_error( $anc ) ) { continue; }
      $anc_link = get_term_link( $anc );
      ?>
      &nbsp;›&nbsp; <a href="<?php echo esc_url( is_wp_error( $anc_link ) ? '#' : $anc_link ); ?>"><?php echo esc_html( $anc->name ); ?></a>
    <?php endforeach; ?>
    &nbsp;›&nbsp; <b><?php echo esc_html( $term->name ); ?></b>
  </nav>

  <!-- En-tête de catégorie -->
  <header class="mt-cat-head">
    <div>
      <span class="mt-cat-eyebrow"><span class="pill">Catégorie</span> <?php echo esc_html( $eyebrow_ctx ); ?></span>
      <h1><?php echo esc_html( $term->name ); ?></h1>
      <?php if ( $term_desc !== '' ) : ?>
        <p class="mt-cat-lede"><?php echo wp_kses_post( $term_desc ); ?></p>
      <?php endif; ?>
    </div>
    <div class="mt-cat-stats">
      <div class="mt-cat-stat">
        <span class="si"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16"/><path d="M4 12h16"/><path d="M4 19h10"/></svg></span>
        <span><b><?php echo (int) $total_all; ?></b> guide<?php echo $total_all > 1 ? 's' : ''; ?> publié<?php echo $total_all > 1 ? 's' : ''; ?></span>
      </div>
      <?php if ( $last_update !== '' ) : ?>
      <div class="mt-cat-stat">
        <span class="si"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg></span>
        <span><b><?php echo esc_html( $last_update ); ?></b><br>dernière mise à jour</span>
      </div>
      <?php endif; ?>
      <div class="mt-cat-stat">
        <span class="si"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg></span>
        <span><b>100 %</b> indépendant</span>
      </div>
    </div>
  </header>

  <?php if ( ! empty( $children ) ) : ?>
  <!-- Sous-catégories -->
  <nav class="mt-subcats" aria-label="Sous-catégories">
    <a class="mt-subcat active" href="<?php echo esc_url( $term_link ); ?>">Tout voir</a>
    <?php foreach ( $children as $child ) :
      $child_link = get_term_link( $child );
      if ( is_wp_error( $child_link ) ) { continue; }
      ?>
      <a class="mt-subcat" href="<?php echo esc_url( $child_link ); ?>"><?php echo esc_html( $child->name ); ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>

  <?php
  /* ---------- Guide à la une (page 1 uniquement) ---------- */
  if ( $paged === 1 && $featured_id ) :
    $f_url    = get_permalink( $featured_id );
    $f_title  = get_the_title( $featured_id );
    $f_img    = get_the_post_thumbnail_url( $featured_id, 'large' );
    $f_desc   = mt_arch_excerpt( $featured_id, 30 );
    $f_author = get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $featured_id ) );
    $f_avatar = get_avatar_url( (int) get_post_field( 'post_author', $featured_id ), array( 'size' => 60 ) );
    $f_date   = get_the_modified_date( 'j M Y', $featured_id );

    /* Chips optionnelles depuis les template variables du guide */
    $f_tv    = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $featured_id ) : array();
    $f_nprod = isset( $f_tv['produits_analyses'] ) ? (int) $f_tv['produits_analyses'] : 0;
    $f_heures = isset( $f_tv['heures_investies'] ) ? (int) $f_tv['heures_investies'] : 0;
    ?>
  <div class="mt-feature-wrap">
    <article class="mt-feature">
      <a class="mt-feature-media<?php echo $f_img ? '' : ' ph'; ?>" href="<?php echo esc_url( $f_url ); ?>">
        <span class="mt-feature-flag"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.6-5.2 4.5 1.6 6.7L12 17l-6.2 3.6 1.6-6.7L2.2 8.9l6.9-.6L12 2z"/></svg> Guide à la une</span>
        <?php if ( $f_img ) : ?><img src="<?php echo esc_url( $f_img ); ?>" alt="<?php echo esc_attr( $f_title ); ?>"><?php endif; ?>
      </a>
      <div class="mt-feature-body">
        <p class="mt-feature-kicker">Notre recommandation principale</p>
        <h2><a href="<?php echo esc_url( $f_url ); ?>"><?php echo esc_html( $f_title ); ?></a></h2>
        <?php if ( $f_desc !== '' ) : ?><p><?php echo esc_html( $f_desc ); ?></p><?php endif; ?>
        <?php if ( $f_nprod || $f_heures ) : ?>
        <div class="mt-feature-chips">
          <?php if ( $f_nprod ) : ?>
          <span class="mt-feature-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="2"/><line x1="10.5" y1="17.5" x2="13.5" y2="17.5"/></svg> <?php echo (int) $f_nprod; ?> modèles analysés</span>
          <?php endif; ?>
          <?php if ( $f_heures ) : ?>
          <span class="mt-feature-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> <?php echo (int) $f_heures; ?> h de recherche</span>
          <?php endif; ?>
          <span class="mt-feature-chip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg> 100 % indépendant</span>
        </div>
        <?php endif; ?>
        <div class="mt-feature-foot">
          <span class="mt-feature-byline">
            <?php if ( $f_avatar ) : ?><img class="avatar" src="<?php echo esc_url( $f_avatar ); ?>" alt="" width="30" height="30" loading="lazy"><?php else : ?><span class="avatar"></span><?php endif; ?>
            <span>Par <b><?php echo esc_html( $f_author ); ?></b> · mis à jour le <?php echo esc_html( $f_date ); ?></span>
          </span>
          <a class="mt-btn" href="<?php echo esc_url( $f_url ); ?>">Lire le guide <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></a>
        </div>
      </div>
    </article>
  </div>
  <?php endif; ?>

  <!-- Barre d'outils -->
  <div class="mt-toolbar">
    <h2>Tous les guides</h2>
    <div class="mt-toolbar-right">
      <span class="mt-toolbar-count"><b><?php echo (int) $total_all; ?></b> guide<?php echo $total_all > 1 ? 's' : ''; ?> · page <?php echo (int) $paged; ?> sur <?php echo (int) $max_pages; ?></span>
      <form class="mt-sort" method="get">
        <label for="mt-orderby">Trier par</label>
        <select id="mt-orderby" name="orderby" onchange="this.form.submit()">
          <option value="date"<?php selected( $orderby, 'date' ); ?>>Les plus récents</option>
          <option value="popular"<?php selected( $orderby, 'popular' ); ?>>Les plus populaires</option>
          <option value="az"<?php selected( $orderby, 'az' ); ?>>A → Z</option>
        </select>
      </form>
    </div>
  </div>

  <!-- Grille de cartes -->
  <?php if ( $grid->have_posts() ) : ?>
  <div class="mt-cat-grid">
    <?php while ( $grid->have_posts() ) : $grid->the_post();
      $pid       = get_the_ID();
      $c_type    = get_post_type( $pid );
      $c_label   = isset( $CAT_TYPE_LABELS[ $c_type ] ) ? $CAT_TYPE_LABELS[ $c_type ] : '';
      $c_img     = get_the_post_thumbnail_url( $pid, 'medium_large' );
      $c_desc    = mt_arch_excerpt( $pid, 18 );
      $c_auth_id = (int) get_post_field( 'post_author', $pid );
      $c_author  = get_the_author_meta( 'display_name', $c_auth_id );
      $c_avatar  = get_avatar_url( $c_auth_id, array( 'size' => 44 ) );
      $c_date    = get_the_modified_date( 'j M Y', $pid );
      ?>
      <article class="mt-card">
        <a class="mt-card-thumb<?php echo $c_img ? '' : ' ph'; ?>" href="<?php the_permalink(); ?>">
          <?php if ( $c_label !== '' ) : ?><span class="mt-card-tag"><?php echo esc_html( $c_label ); ?></span><?php endif; ?>
          <?php if ( $c_img ) : ?><img src="<?php echo esc_url( $c_img ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy"><?php endif; ?>
        </a>
        <div class="mt-card-body">
          <h3><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
          <?php if ( $c_desc !== '' ) : ?><p><?php echo esc_html( $c_desc ); ?></p><?php endif; ?>
          <div class="mt-card-meta">
            <?php if ( $c_avatar ) : ?><img class="avatar" src="<?php echo esc_url( $c_avatar ); ?>" alt="" width="22" height="22" loading="lazy"><?php else : ?><span class="avatar"></span><?php endif; ?>
            <span>Par <b><?php echo esc_html( $c_author ); ?></b></span>
            <span class="dot">·</span><span><?php echo esc_html( $c_date ); ?></span>
          </div>
        </div>
      </article>
    <?php endwhile; ?>
  </div>
  <?php else : ?>
  <p class="mt-cat-empty">Aucun guide dans cette catégorie pour le moment.</p>
  <?php endif; ?>
  <?php wp_reset_postdata(); ?>

  <?php
  /* ---------- Pagination ---------- */
  if ( $max_pages > 1 ) :
    $links = paginate_links( array(
      'base'      => trailingslashit( $term_link ) . 'page/%#%/',
      'format'    => '',
      'current'   => $paged,
      'total'     => $max_pages,
      'mid_size'  => 1,
      'end_size'  => 1,
      'prev_text' => '‹ <span class="lbl">Précédent</span>',
      'next_text' => '<span class="lbl">Suivant</span> ›',
      'add_args'  => ( $orderby !== 'date' ) ? array( 'orderby' => $orderby ) : false,
      'type'      => 'array',
    ) );
    if ( ! empty( $links ) ) : ?>
    <nav class="mt-pager" aria-label="Pagination">
      <?php echo implode( '', $links ); // paginate_links génère un balisage échappé ?>
    </nav>
    <?php endif; ?>
  <?php endif; ?>

</div>
