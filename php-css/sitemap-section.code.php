<?php
/* =====================================================================
   MEILLEURTEST — Section « Index des comparatifs » (au-dessus du footer)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   CSS dans l'onglet CSS du même élément (sitemap-section.css). Scope .mt-smap.
   Réf. maquette : templates/Sitemap Section.html (polices/couleurs adaptées
   au site : Source Serif titres, Inter corps, accent --at-primary).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-grey-l-5)
     + filet haut 1px var(--at-grey-l-3) + padding vertical ~72px
     (structure section > container > code, comme guides-similaires :
     la bande couvre toute la largeur, le container borne le contenu).

   Contenu : pour chacune des catégories principales du site (lues dans le
   MENU 13, l'ordre du menu fait foi ; repli = catégories racines par nombre
   de posts), 10 comparatifs AU HASARD + lien « Tous les N comparatifs ».
   La sélection est mise en cache 12h (transient) : pas de requête RAND()
   à chaque affichage, et l'index tourne 2×/jour.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$SM_MENU_ID   = 13;                    // menu WP des catégories (ordre = celui du menu)
$SM_MAX_CATS  = 8;                     // nb de catégories affichées
$SM_PER_CAT   = 10;                    // comparatifs aléatoires par catégorie
$SM_POST_TYPE = 'comparatif';
$SM_TTL       = 12 * HOUR_IN_SECONDS;  // rotation de la sélection aléatoire
$SM_FOOT_URL  = '';                    // lien « tout parcourir » (vide = ligne masquée)
$SM_EXCL_SLUGS = array( 'sexe-et-erotisme' );  // catégories à exclure (descendants inclus)

/* ---------------------------------------------------------------------
   Helpers partagés avec similaires.code.php (byte-identiques, guardés)
   --------------------------------------------------------------------- */
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

/* ---------------------------------------------------------------------
   Données (transient 12h : sélection aléatoire figée entre deux rotations)
   --------------------------------------------------------------------- */
$cols = get_transient( 'mt_smap_data_v2' );
if ( ! is_array( $cols ) || empty( $cols ) ) {

  /* 1) Catégories principales : items « category » du menu 13, dans l'ordre
        du menu (uniquement le 1er niveau du menu). Repli : catégories racines
        triées par nombre de posts. */
  $cat_ids = array();
  $items   = wp_get_nav_menu_items( $SM_MENU_ID );
  if ( is_array( $items ) ) {
    foreach ( $items as $it ) {
      if ( isset( $it->object ) && $it->object === 'category'
        && empty( $it->menu_item_parent ) ) {
        $cat_ids[] = (int) $it->object_id;
      }
    }
  }
  $cat_ids = array_values( array_unique( array_filter( $cat_ids ) ) );
  if ( empty( $cat_ids ) ) {
    $terms = get_categories( array( 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC', 'hide_empty' => true ) );
    foreach ( $terms as $t ) { $cat_ids[] = (int) $t->term_id; }
  }
  $cat_ids = array_slice( $cat_ids, 0, (int) $SM_MAX_CATS );

  /* Catégories exclues (slug -> term_id, descendants inclus) : leurs
     comparatifs ne sont jamais listés, dans aucune colonne. */
  $excl_ids = array();
  foreach ( $SM_EXCL_SLUGS as $slug ) {
    $t = get_term_by( 'slug', $slug, 'category' );
    if ( $t && ! is_wp_error( $t ) ) {
      $excl_ids[] = (int) $t->term_id;
      $kids = get_term_children( (int) $t->term_id, 'category' );
      if ( is_array( $kids ) ) { foreach ( $kids as $k ) { $excl_ids[] = (int) $k; } }
    }
  }

  /* 2) Par catégorie : tous les IDs de comparatifs (enfants de catégorie
        inclus), mélange PHP (pas de ORDER BY RAND()), coupe à $SM_PER_CAT. */
  $cols = array();
  foreach ( $cat_ids as $cid ) {
    if ( in_array( (int) $cid, $excl_ids, true ) ) { continue; }
    $term = get_term( $cid, 'category' );
    if ( ! $term || is_wp_error( $term ) ) { continue; }

    $q_args = array(
      'post_type'      => $SM_POST_TYPE,
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'no_found_rows'  => true,
      'cat'            => $cid,          // inclut les sous-catégories
    );
    if ( ! empty( $excl_ids ) ) { $q_args['category__not_in'] = $excl_ids; }
    $ids = get_posts( $q_args );
    if ( empty( $ids ) ) { continue; }

    $total = count( $ids );
    shuffle( $ids );
    $pick = array_slice( $ids, 0, (int) $SM_PER_CAT );

    $links = array();
    foreach ( $pick as $pid ) {
      $links[] = array(
        't' => mt_sim_clean_label( get_the_title( $pid ) ),
        'u' => get_permalink( $pid ),
      );
    }

    $turl = get_term_link( $term );
    $cols[] = array(
      'name'  => $term->name,
      'url'   => is_wp_error( $turl ) ? '' : $turl,
      'count' => $total,
      'items' => $links,
    );
  }

  if ( ! empty( $cols ) ) { set_transient( 'mt_smap_data_v2', $cols, (int) $SM_TTL ); }
}

/* Rien à afficher -> diagnostic admin, invisible pour les visiteurs. */
if ( empty( $cols ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-smap — diagnostic (admin only)</strong> : aucune cat&eacute;gorie/comparatif trouv&eacute;.<br>'
       . 'menu ' . (int) $SM_MENU_ID . ' = ' . ( is_array( wp_get_nav_menu_items( $SM_MENU_ID ) ) ? 'ok' : 'introuvable' )
       . ' &middot; post_type = ' . esc_html( $SM_POST_TYPE ) . '</div>';
  }
  return;
}

$sm_total = wp_count_posts( $SM_POST_TYPE );
$sm_total = isset( $sm_total->publish ) ? (int) $sm_total->publish : 0;
?>
<section class="mt-smap" id="partie-index-comparatifs" aria-labelledby="mt-smap-title">
  <div class="mt-smap-head">
    <div>
      <h2 class="mt-smap-h2" id="mt-smap-title">Tous nos comparatifs, <em>&agrave; port&eacute;e de clic</em>.</h2>
    </div>
    <p class="mt-smap-lead"><?php echo (int) $sm_total; ?> comparatifs class&eacute;s par univers, pour aller droit &agrave; celui qui r&eacute;pond &agrave; votre question.</p>
  </div>

  <div class="mt-smap-grid">
    <?php foreach ( $cols as $c ) : ?>
    <div class="mt-smap-col">
      <h3>
        <?php if ( $c['url'] !== '' ) : ?>
        <a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['name'] ); ?></a>
        <?php else : ?>
        <span><?php echo esc_html( $c['name'] ); ?></span>
        <?php endif; ?>
        <span class="num"><?php echo (int) $c['count']; ?></span>
      </h3>
      <ul>
        <?php foreach ( $c['items'] as $l ) : ?>
        <li><a href="<?php echo esc_url( $l['u'] ); ?>"><?php echo esc_html( $l['t'] ); ?></a></li>
        <?php endforeach; ?>
        <?php if ( $c['url'] !== '' ) : ?>
        <li><a class="all" href="<?php echo esc_url( $c['url'] ); ?>">Tous les <?php echo (int) $c['count']; ?> comparatifs &rarr;</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ( $SM_FOOT_URL !== '' ) : ?>
  <div class="mt-smap-foot">
    <span>Vous ne trouvez pas votre bonheur&nbsp;? <a href="<?php echo esc_url( $SM_FOOT_URL ); ?>">Parcourez toutes les cat&eacute;gories</a></span>
  </div>
  <?php endif; ?>
</section>
