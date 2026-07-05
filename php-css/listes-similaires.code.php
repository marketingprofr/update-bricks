<?php
/* =====================================================================
   MEILLEURTEST — « D'autres listes qui pourraient vous plaire »
   (grille de listes proches — cf. bas de templates/template-liste.html)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON), placé
   dans : SECTION > CONTAINER > CODE. CSS dans l'onglet CSS (listes-similaires.css).
   Scope : .mt-lsim. À placer SOUS le bloc « liste » (liste.code.php).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-grey-l-6)  (gris très clair)

   Données : autres articles du post-type « liste » partageant une catégorie
   avec la liste courante (les plus récemment mis à jour), complétés au besoin
   par les listes les plus récentes. Le badge « N idées » compte les lignes du
   répéteur mltv5_elements_liste de chaque liste liée.
   Pas de lien « Toutes nos listes » (décision client : pas de page d'archive
   dédiée « toutes les listes »).
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$LS_POST_TYPE = 'liste';                 // post-type des listes
$LS_MAX       = 3;                       // nb de cartes (grille 3 colonnes)
$LS_REPEATER  = 'mltv5_elements_liste';  // pour compter les éléments (badge)

$cur_id = get_the_ID();

/* ---------------------------------------------------------------------
   Catégories de la liste courante -> listes proches (même catégorie),
   les plus récemment modifiées, courant exclu.
   --------------------------------------------------------------------- */
$cat_ids = wp_get_post_terms( $cur_id, 'category', array( 'fields' => 'ids' ) );
$cat_ids = ( is_array( $cat_ids ) && ! is_wp_error( $cat_ids ) ) ? array_map( 'intval', $cat_ids ) : array();

$ls_ids = array();
if ( ! empty( $cat_ids ) ) {
  $q = new WP_Query( array(
    'post_type'           => $LS_POST_TYPE,
    'post_status'         => 'publish',
    'posts_per_page'      => (int) $LS_MAX,
    'post__not_in'        => array( (int) $cur_id ),
    'fields'              => 'ids',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
    'orderby'             => 'modified',
    'order'               => 'DESC',
    'tax_query'           => array( array(
      'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $cat_ids, 'include_children' => true,
    ) ),
  ) );
  $ls_ids = array_map( 'intval', $q->posts );
  wp_reset_postdata();
}

/* Complément : si pas assez de listes de la même catégorie, on comble avec
   les listes les plus récentes (hors courant et hors déjà retenues). */
if ( count( $ls_ids ) < (int) $LS_MAX ) {
  $exclude = array_merge( array( (int) $cur_id ), $ls_ids );
  $qf = new WP_Query( array(
    'post_type'           => $LS_POST_TYPE,
    'post_status'         => 'publish',
    'posts_per_page'      => (int) $LS_MAX - count( $ls_ids ),
    'post__not_in'        => $exclude,
    'fields'              => 'ids',
    'no_found_rows'       => true,
    'ignore_sticky_posts' => true,
    'orderby'             => 'modified',
    'order'               => 'DESC',
  ) );
  $ls_ids = array_merge( $ls_ids, array_map( 'intval', $qf->posts ) );
  wp_reset_postdata();
}

if ( empty( $ls_ids ) ) { return; }

/* Compte les éléments d'une liste (lignes du répéteur) — repli sur le meta
   brut du répéteur ACF (entier stocké sous la clé du champ). */
if ( ! function_exists( 'mt_lsim_count' ) ) {
  function mt_lsim_count( $pid, $repeater ) {
    if ( function_exists( 'get_field' ) ) {
      $rows = get_field( $repeater, $pid );
      if ( is_array( $rows ) ) { return count( $rows ); }
    }
    $raw = get_post_meta( $pid, $repeater, true );   // ACF stocke le nb de lignes
    return is_numeric( $raw ) ? (int) $raw : 0;
  }
}
if ( ! function_exists( 'mt_lsim_primary_cat' ) ) {
  function mt_lsim_primary_cat( $pid ) {
    $terms = get_the_terms( $pid, 'category' );
    if ( ! is_array( $terms ) || empty( $terms ) ) { return ''; }
    $pc = (int) get_post_meta( $pid, '_yoast_wpseo_primary_category', true );
    if ( $pc ) { foreach ( $terms as $t ) { if ( (int) $t->term_id === $pc ) { return $t->name; } } }
    return $terms[0]->name;
  }
}
if ( ! function_exists( 'mt_lsim_excerpt' ) ) {
  function mt_lsim_excerpt( $pid, $words = 18 ) {
    $text = has_excerpt( $pid ) ? get_the_excerpt( $pid ) : get_post_field( 'post_content', $pid );
    $text = wp_strip_all_tags( strip_shortcodes( (string) $text ) );
    $text = trim( preg_replace( '/\s+/', ' ', $text ) );
    return $text === '' ? '' : wp_trim_words( $text, (int) $words, '…' );
  }
}
?>

<section class="mt-lsim">
  <div class="mt-lsim-head">
    <p class="eyebrow">&Agrave; d&eacute;couvrir aussi</p>
    <h2>D&rsquo;autres listes qui pourraient vous plaire</h2>
  </div>

  <div class="mt-lsim-grid">
    <?php foreach ( $ls_ids as $pid ) :
      $url   = get_permalink( $pid );
      $title = get_the_title( $pid );
      $img   = get_the_post_thumbnail_url( $pid, 'medium_large' );
      $cat   = mt_lsim_primary_cat( $pid );
      $exc   = mt_lsim_excerpt( $pid );
      $cnt   = mt_lsim_count( $pid, $LS_REPEATER );
      $mod   = get_the_modified_date( 'j M Y', $pid );
      ?>
    <article class="mt-lsim-card">
      <a class="mt-lsim-thumb<?php echo $img ? '' : ' ph'; ?>" href="<?php echo esc_url( $url ); ?>">
        <?php if ( $cnt > 0 ) : ?>
        <span class="mt-lsim-count"><svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zM4 11h16v2H4zM4 16h10v2H4z"/></svg> <?php echo (int) $cnt; ?> id&eacute;es</span>
        <?php endif; ?>
        <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy"><?php endif; ?>
      </a>
      <div class="mt-lsim-body">
        <?php if ( $cat !== '' ) : ?><span class="mt-lsim-cat"><?php echo esc_html( $cat ); ?></span><?php endif; ?>
        <h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a></h3>
        <?php if ( $exc !== '' ) : ?><p><?php echo esc_html( $exc ); ?></p><?php endif; ?>
        <div class="mt-lsim-meta"><span>Mis &agrave; jour le <b><?php echo esc_html( $mod ); ?></b></span></div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</section>
