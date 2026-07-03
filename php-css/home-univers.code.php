<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §3 EXPLOREZ PAR UNIVERS (cartes catégories)
   UN SEUL élément CODE Bricks (Execute code = ON) : SECTION > CONTAINER >
   CODE. CSS dans l'onglet CSS du même élément (home-univers.css). Scope : .mt-hx.
   Réf. maquette : templates/Home.html (section « Explorez par univers »).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-white)  (blanc)

   Données : catégories WP de 1er niveau (parent = 0), avec le nombre de
   guides (comparatifs + listes) par univers. Icône choisie selon le slug
   (repli icône générique). Lien = archive de la catégorie.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$HX_POST_TYPES = array( 'comparatif', 'liste' );
$HX_MAX        = 8;   // nb d'univers affichés (0 = tous)

/* ---------------------------------------------------------------------
   Icône par univers (inner-SVG) selon un mot-clé du slug — repli générique.
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt_hx_icon' ) ) {
  function mt_hx_icon( $slug ) {
    $map = array(
      'maison'    => '<path d="M3.5 10.5 12 4l8.5 6.5"/><path d="M5.5 9.5V20h13V9.5"/><path d="M10 20v-5h4v5"/>',
      'tech'      => '<rect x="6.5" y="6.5" width="11" height="11" rx="1.5"/><path d="M9.5 2v3M14.5 2v3M9.5 19v3M14.5 19v3M2 9.5h3M2 14.5h3M19 9.5h3M19 14.5h3"/>',
      'high'      => '<rect x="6.5" y="6.5" width="11" height="11" rx="1.5"/><path d="M9.5 2v3M14.5 2v3M9.5 19v3M14.5 19v3M2 9.5h3M2 14.5h3M19 9.5h3M19 14.5h3"/>',
      'beaut'     => '<path d="M12 3.5l1.7 4.8 4.8 1.7-4.8 1.7L12 16.5l-1.7-4.8L5.5 10l4.8-1.7L12 3.5Z"/>',
      'sant'      => '<path d="M3 12h4l2-6 4 12 2-6h6"/>',
      'lifestyle' => '<path d="M4 8.5h12v4.5a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4V8.5Z"/><path d="M16 9.5h2.2a2.3 2.3 0 0 1 0 4.6H16"/><path d="M7 3.5v2M11 3.5v2"/>',
      'loisir'    => '<rect x="2.5" y="8" width="19" height="9" rx="4.5"/><path d="M7 11.3v3.4M5.3 13h3.4"/><circle cx="15.5" cy="12" r="1"/><circle cx="18" cy="14" r="1"/>',
      'sport'     => '<path d="M6.5 7v10M4 9v6M17.5 7v10M20 9v6M6.5 12h11"/>',
      'service'   => '<rect x="3" y="7.5" width="18" height="12" rx="2"/><path d="M8.5 7.5V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v1.5"/><path d="M3 12.5h18"/>',
      'cuisine'   => '<path d="M6 3v7a3 3 0 0 0 6 0V3"/><path d="M9 3v18"/><path d="M17 3c-1.5 0-2.5 2-2.5 5s1 5 2.5 5v8"/>',
      'jardin'    => '<path d="M12 21c0-6 3-10 8-11-1 6-4 9-8 11Z"/><path d="M12 21c0-6-3-10-8-11 1 6 4 9 8 11Z"/><path d="M12 21v-6"/>',
      'auto'      => '<path d="M5 13l1.5-4.5A2 2 0 0 1 8.4 7h7.2a2 2 0 0 1 1.9 1.5L19 13"/><rect x="3" y="13" width="18" height="6" rx="1.5"/><circle cx="7.5" cy="19" r="1.2"/><circle cx="16.5" cy="19" r="1.2"/>',
    );
    $slug = strtolower( (string) $slug );
    foreach ( $map as $key => $svg ) {
      if ( strpos( $slug, $key ) !== false ) { return $svg; }
    }
    /* Repli générique : dossier */
    return '<path d="M3.5 7.5a2 2 0 0 1 2-2h3.2l2 2H18.5a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2V7.5Z"/>';
  }
}

/* ---------------------------------------------------------------------
   Catégories de 1er niveau non vides + comptage des CPT guides
   --------------------------------------------------------------------- */
$hx_terms = get_terms( array(
  'taxonomy'   => 'category',
  'parent'     => 0,
  'hide_empty' => true,
) );
if ( is_wp_error( $hx_terms ) || empty( $hx_terms ) ) { return; }
if ( $HX_MAX > 0 ) { $hx_terms = array_slice( $hx_terms, 0, (int) $HX_MAX ); }
?>

<section class="mt-hx">
  <div class="mt-sec-head">
    <div>
      <p class="eyebrow">Un site généraliste</p>
      <h2>Explorez par univers</h2>
      <p>Nos grandes familles de produits, un seul niveau d'exigence. Choisissez le vôtre.</p>
    </div>
  </div>

  <div class="mt-univ-grid">
    <?php foreach ( $hx_terms as $term ) :
      $link = get_term_link( $term );
      if ( is_wp_error( $link ) ) { continue; }

      /* Nombre de guides (comparatifs + listes) de l'univers */
      $cq = new WP_Query( array(
        'post_type'           => $HX_POST_TYPES,
        'post_status'         => 'publish',
        'posts_per_page'      => 1,
        'fields'              => 'ids',
        'no_found_rows'       => false,
        'ignore_sticky_posts' => true,
        'tax_query'           => array( array(
          'taxonomy' => 'category', 'field' => 'term_id', 'terms' => (int) $term->term_id, 'include_children' => true,
        ) ),
      ) );
      $count = (int) $cq->found_posts;
      wp_reset_postdata();
      if ( $count < 1 ) { $count = (int) $term->count; }
      ?>
      <a class="mt-univ" href="<?php echo esc_url( $link ); ?>">
        <span class="mt-univ-ico"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?php echo mt_hx_icon( $term->slug ); // markup SVG interne contrôlé ?></svg></span>
        <div>
          <div class="mt-univ-name"><?php echo esc_html( $term->name ); ?></div>
          <div class="mt-univ-count"><?php echo esc_html( number_format_i18n( $count ) ); ?> guide<?php echo $count > 1 ? 's' : ''; ?></div>
        </div>
        <span class="mt-univ-arrow"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>
