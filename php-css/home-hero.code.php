<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §1 HERO (proposition de valeur + recherche)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON), placé
   dans : SECTION > CONTAINER > CODE. Le CSS va dans l'onglet CSS du même
   élément (home-hero.css). Scope : .mt-hh.
   Réf. maquette : templates/Home.html (section .mt-hero-home).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-white)  (blanc)

   Contenu : eyebrow + H1 + chapô + barre de recherche (recherche WP
   native) + termes populaires + bandeau de stats (guides / univers
   dynamiques). 100 % variables Advanced Themer côté CSS → dark auto.
   ===================================================================== */

/* ---------------------------------------------------------------------
   CONFIG
   --------------------------------------------------------------------- */
$HH_SEARCH_ACTION = home_url( '/' );                 // cible du formulaire (recherche WP)
$HH_YEAR          = '2014';                           // « indépendant depuis … »
$HH_POST_TYPES    = array( 'comparatif', 'liste' );   // CPT comptés pour « guides d'achat »
$HH_POPULAR       = array( 'Aspirateur robot', 'Matelas', 'Tablette', 'Friteuse sans huile', 'Casque Bluetooth' );

/* ---------------------------------------------------------------------
   Stats dynamiques (repli sur les valeurs maquette si vide)
   --------------------------------------------------------------------- */
$hh_total = 0;
foreach ( $HH_POST_TYPES as $pt ) {
  $c = wp_count_posts( $pt );
  if ( $c && isset( $c->publish ) ) { $hh_total += (int) $c->publish; }
}
$hh_total_disp = $hh_total > 0 ? number_format_i18n( $hh_total ) . '+' : '5 000+';

$hh_univ_terms = get_terms( array( 'taxonomy' => 'category', 'parent' => 0, 'hide_empty' => true, 'fields' => 'ids' ) );
$hh_univ       = ( is_array( $hh_univ_terms ) && ! is_wp_error( $hh_univ_terms ) ) ? count( $hh_univ_terms ) : 0;
$hh_univ_disp  = $hh_univ > 0 ? number_format_i18n( $hh_univ ) : '8';
?>

<section class="mt-hh">
  <div class="mt-hh-inner">
    <span class="mt-hh-eyebrow">
      <span class="pill"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg> Indépendant</span>
      Comparatifs sans pub ni sponsor depuis <?php echo esc_html( $HH_YEAR ); ?>
    </span>

    <h1>Le bon choix,<br><em>quel que soit</em> votre achat</h1>
    <p class="mt-hh-lede">De la maison au high-tech, de la beauté au sport — nous testons, comparons et classons des milliers de produits pour vous aider à choisir en toute confiance. Sans pub, sans sponsor, sans cadeau des marques.</p>

    <form class="mt-hh-search" method="get" action="<?php echo esc_url( $HH_SEARCH_ACTION ); ?>" role="search">
      <svg class="si" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="search" name="s" placeholder="Que cherchez-vous à acheter&nbsp;?" aria-label="Rechercher un produit" />
      <button type="submit">Rechercher <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></button>
    </form>

    <div class="mt-hh-pop">
      <span class="lbl">Populaire&nbsp;:</span>
      <?php foreach ( $HH_POPULAR as $term ) : ?>
        <a href="<?php echo esc_url( add_query_arg( 's', rawurlencode( $term ), $HH_SEARCH_ACTION ) ); ?>"><?php echo esc_html( $term ); ?></a>
      <?php endforeach; ?>
    </div>

    <div class="mt-hh-strip">
      <span class="mt-hh-stat"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6c-1.6-1.2-4-2-7-2v13c3 0 5.4.8 7 2 1.6-1.2 4-2 7-2V4c-3 0-5.4.8-7 2Z"/><path d="M12 6v13"/></svg> <b><?php echo esc_html( $hh_total_disp ); ?></b>&nbsp;guides d'achat</span>
      <span class="mt-hh-stat"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> <b><?php echo esc_html( $hh_univ_disp ); ?></b>&nbsp;univers couverts</span>
      <span class="mt-hh-stat"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> Indépendant depuis <b><?php echo esc_html( $HH_YEAR ); ?></b></span>
      <span class="mt-hh-stat"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg> <b>0 %</b>&nbsp;de publicité</span>
    </div>
  </div>
</section>
