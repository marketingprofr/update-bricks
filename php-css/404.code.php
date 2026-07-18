<?php
/* =====================================================================
   MEILLEURTEST — Page 404
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON),
   placé dans le template 404. Le CSS va dans l'onglet CSS (404.css).
   Scope : .mt-404.
   ===================================================================== */

$home_url = esc_url( home_url( '/' ) );

/* Catégories populaires (menu 13 = catégories principales du site) */
$menu_items = wp_get_nav_menu_items( 13 );
$cats = array();
if ( $menu_items ) {
    $count = 0;
    foreach ( $menu_items as $item ) {
        if ( (int) $item->menu_item_parent === 0 && $count < 6 ) {
            $cats[] = array(
                'label' => esc_html( $item->title ),
                'url'   => esc_url( $item->url ),
            );
            $count++;
        }
    }
}
?>
<section class="mt-404">

  <div class="mt-404-code">404</div>

  <h1 class="mt-404-title">Page introuvable</h1>

  <p class="mt-404-desc">
    La page que vous cherchez a peut-&ecirc;tre &eacute;t&eacute; d&eacute;plac&eacute;e,
    supprim&eacute;e, ou n&rsquo;a jamais exist&eacute;.
    Essayez une recherche ou explorez nos guides.
  </p>

  <form class="mt-404-search" role="search" method="get" action="<?php echo $home_url; ?>">
    <input type="search" name="s" placeholder="Rechercher un comparatif, un produit&hellip;"
           aria-label="Rechercher" autocomplete="off" />
    <button type="submit" aria-label="Lancer la recherche">
      <i class="fas fa-search"></i>
    </button>
  </form>

  <a href="<?php echo $home_url; ?>" class="mt-404-home">
    <i class="fas fa-arrow-left"></i> Retour &agrave; l&rsquo;accueil
  </a>

  <?php if ( $cats ) : ?>
  <div class="mt-404-cats">
    <p class="mt-404-cats-label">Nos cat&eacute;gories</p>
    <ul>
      <?php foreach ( $cats as $c ) : ?>
        <li><a href="<?php echo $c['url']; ?>"><?php echo $c['label']; ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

</section>
