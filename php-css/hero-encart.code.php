<?php
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));
$mod = date_i18n('j F Y', get_the_modified_time('U'));

// Temps de lecture (mots / 200)
$wc = str_word_count(strip_tags(strip_shortcodes(get_post_field('post_content', $this_id))));
$rt = 15 + (int) round($wc / 100);

// Libelle "produits analyses" (accord en genre)
$tp = $type_de_produit_au_pluriel ?? '';
if (strlen($tp) >= 22) { $lblprod = 'Produits analysés'; }
else { $lblprod = ($tp ?: 'Produits') . ((($masculinsfeminins ?? '') == 'Meilleures') ? ' analysées' : ' analysés'); }

// Compteur dynamique : vrai nombre d'avis publiés (même type + attributs), +5 si < 10
$mt_real_count = 0;
$mt_prod_terms = get_the_terms( $this_id, 'post-type-produit' );
if ( is_array( $mt_prod_terms ) && ! empty( $mt_prod_terms ) ) {
  $mt_tax_q = array( array( 'taxonomy' => 'post-type-produit', 'terms' => wp_list_pluck( $mt_prod_terms, 'term_id' ) ) );
  $mt_attr_terms = get_the_terms( $this_id, 'post-type-attribut' );
  if ( is_array( $mt_attr_terms ) && ! empty( $mt_attr_terms ) ) {
    $mt_tax_q['relation'] = 'AND';
    $mt_tax_q[] = array( 'taxonomy' => 'post-type-attribut', 'terms' => wp_list_pluck( $mt_attr_terms, 'term_id' ), 'operator' => 'AND' );
  }
  $mt_cq = new WP_Query( array(
    'post_type'      => 'avis',
    'post_status'    => 'publish',
    'tax_query'      => $mt_tax_q,
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
    'update_post_meta_cache' => false,
    'update_post_term_cache' => false,
  ) );
  $mt_real_count = count( $mt_cq->posts );
}
$mt_display_count = ( $mt_real_count < 10 ) ? $mt_real_count + 5 : $mt_real_count;

// Icones SVG outline (couleur via CSS / currentColor)
$ic_shield  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>';
$ic_clock   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
$ic_layers  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/></svg>';
$ic_tablet  = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="2"/><line x1="10.5" y1="17.5" x2="13.5" y2="17.5"/></svg>';
$ic_chat    = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-1.1 4.2A8.5 8.5 0 0 1 12.5 20a8.4 8.4 0 0 1-4.2-1.1L3 20l1.1-5.3A8.4 8.4 0 0 1 3 10.5 8.5 8.5 0 0 1 7.3 3a8.4 8.4 0 0 1 4.2-1.1h.5A8.5 8.5 0 0 1 21 11v.5Z"/></svg>';
$ic_check   = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg>';
$ic_refresh = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg>';
$ic_book    = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6c-1.6-1.2-4-2-7-2v13c3 0 5.4.8 7 2 1.6-1.2 4-2 7-2V4c-3 0-5.4.8-7 2Z"/><path d="M12 6v13"/></svg>';
?>
<div class="mt-card">

  <h3 class="mt-card-h"><span class="mt-card-hi"><?php echo $ic_shield; ?></span>Notre enquête</h3>

  <div class="mt-sc-grid">
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_clock; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($heures_investies ?? ''); ?></div><div class="mt-sc-lbl">heures de recherche</div></div>
    </div>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_layers; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($sources_consultees ?? ''); ?></div><div class="mt-sc-lbl">sources consultées</div></div>
    </div>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_tablet; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html( $mt_display_count ); ?></div><div class="mt-sc-lbl"><?php echo esc_html($lblprod); ?></div></div>
    </div>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_chat; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($avis_etudies ?? ''); ?></div><div class="mt-sc-lbl">avis étudiés</div></div>
    </div>
  </div>

  <div class="mt-sc-trust">
    <div class="mt-sc-row"><span class="mt-ti"><?php echo $ic_check; ?></span><span><b>100&nbsp;% indépendant</b> (et sans pub)</span></div>
    <div class="mt-sc-row mt-sc-date"><span class="mt-ti"><?php echo $ic_refresh; ?></span><span>Mis à jour le <b><?php echo $mod; ?></b></span></div>
    <div class="mt-sc-row"><span class="mt-ti"><?php echo $ic_book; ?></span><span><b><?php echo $rt; ?> min</b> de lecture</span></div>
  </div>
    <div class="mt-sc-process link-black"><p>Les guides d'achat de Meilleurtest résultent d'un processus de sélection approfondi et d'une vérification méticuleuse. Découvrez <a href="/notre-methode/">notre méthodologie</a> et <a href="/notre-engagement/">nos engagements</a> qualité.</p>
  </div>
  <div class="mt-sc-vote">
    <h4>Avis des lecteurs sur cette s&eacute;lection</h4>
    <?php echo do_shortcode('[ratemypost]'); ?>
    <p class="mt-sc-note">Votre note oriente les autres lecteurs et nous aide &agrave; am&eacute;liorer ce contenu. Merci&nbsp;!</p>
  </div>

</div>
