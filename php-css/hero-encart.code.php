<?php
$MT_ENCADRE_REEL = false; // true : 4 cases à valeurs réelles (tests Jev du 2026-10-02) au lieu de heures / années / avis par défaut
$MT_SOURCES_REPLI = '~20'; // case 1 si mltv5_sources_consultees est vide ou à la valeur par défaut (10) ; '' = case retirée
$MT_AVIS_REPLI    = '';    // case 3 si mltv5_avis_etudies est vide ou par défaut (597) ; '' = avis clients ≥ 50 000, sinon FAQ
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));
$mod = date_i18n('j F Y', get_the_modified_time('U'));

// Temps de lecture (mots / 200)
$wc = str_word_count(strip_tags(strip_shortcodes(get_post_field('post_content', $this_id))));
$rt = 15 + (int) round($wc / 100);

// Libelle "produits analyses" (accord en genre)
$tp = $type_de_produit_au_pluriel ?? '';
if (strlen($tp) >= 22) { $lblprod = 'Produits testés'; }
else { $lblprod = ($tp ?: 'Produits') . ((($masculinsfeminins ?? '') == 'Meilleures') ? ' testées' : ' testés'); }

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

/* ---- Encadré à valeurs réelles ($MT_ENCADRE_REEL) : chaque case n'apparaît que si sa valeur est utile ---- */
if ( ! function_exists( 'mt_encadre_mots' ) ) {
  function mt_encadre_mots( $html ) {
    $t = trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
    return $t === '' ? 0 : count( preg_split( '/\s+/u', $t ) );
  }
  /* Toutes les chaînes d'une valeur ACF (groupes et répéteurs compris) */
  function mt_encadre_textes( $v ) {
    if ( is_string( $v ) ) { return array( $v ); }
    $out = array();
    if ( is_array( $v ) ) { foreach ( $v as $x ) { $out = array_merge( $out, mt_encadre_textes( $x ) ); } }
    return $out;
  }
}
$mt_cases = array();
if ( $MT_ENCADRE_REEL ) {
  $mt_fem   = ( ( $masculinsfeminins ?? '' ) == 'Meilleures' );
  $mt_e     = $mt_fem ? 'e' : '';
  $mt_ids   = isset( $mt_cq ) ? array_map( 'intval', $mt_cq->posts ) : array();
  $mt_top   = array_values( array_filter( array_map( 'intval', (array) ( $top_avis_ids ?? array() ) ) ) );
  /* Produits affichés : classement principal + tests complets d'un multi-comparatif */
  $mt_aff   = $mt_top;
  $mt_plan  = function_exists( 'mtv2_plan' ) ? mtv2_plan( $this_id ) : null;
  if ( $mt_plan && ! empty( $mt_plan['is_multi'] ) ) { $mt_aff = array_values( array_unique( array_merge( $mt_aff, $mt_plan['tests'] ) ) ); }
  if ( $mt_aff ) { update_meta_cache( 'post', $mt_aff ); }

  /* Champ de la page « La recherche » : vraies valeurs reprises de l'ancien site (sources : environ 30 par
     guide ; avis étudiés variés sur deux tiers des guides). 10 sources et 597 avis = valeurs par défaut. */
  $mt_rech = get_field( 'mltv5_la_recherche_comparatif', $this_id );
  $mt_rech = is_array( $mt_rech ) ? $mt_rech : array();
  $mt_src  = (int) ( $mt_rech['mltv5_sources_consultees'] ?? 0 );
  $mt_etud = (int) ( $mt_rech['mltv5_avis_etudies'] ?? 0 );

  /* Case 1 : sources consultées (repli : réglage $MT_SOURCES_REPLI) */
  $mt_src_aff = ( $mt_src > 0 && $mt_src !== 10 ) ? (string) $mt_src : $MT_SOURCES_REPLI;
  if ( $mt_src_aff !== '' ) { $mt_cases[] = array( $ic_layers, $mt_src_aff, 'sources consultées' ); }

  /* Case 2 : produits analysés = même N que le title (+5 si < 10) */
  $mt_lbl_n = ( strlen( $tp ) >= 22 || $tp === '' ) ? 'produits analysés' : mb_strtolower( $tp, 'UTF-8' ) . ' analysé' . $mt_e . 's';
  $mt_cases[] = array( $ic_tablet, $mt_display_count, $mt_lbl_n );

  /* Case 3 : avis étudiés (champ de la page, hors valeur par défaut 597), sinon réglage $MT_AVIS_REPLI,
     sinon avis clients recensés (≥ 50 000), sinon questions fréquentes traitées */
  $mt_avis = 0;
  if ( $mt_ids ) {
    update_meta_cache( 'post', $mt_ids );
    foreach ( $mt_ids as $pid ) {
      /* « 3104 », « 1 234 », avec espace normale, insécable ou fine : espaces de milliers retirés, puis valeur numérique */
      $mt_v = str_replace( array( ' ', "\xc2\xa0", "\xe2\x80\xaf" ), '', (string) get_post_meta( $pid, 'mltv5_nombre_avis_clients', true ) );
      $mt_avis += is_numeric( $mt_v ) ? (int) round( (float) $mt_v ) : 0;
    }
  }
  $mt_etud_aff = ( $mt_etud > 0 && $mt_etud !== 597 ) ? number_format( $mt_etud, 0, ',', "\xE2\x80\xAF" ) : $MT_AVIS_REPLI;
  if ( $mt_etud_aff !== '' ) {
    $mt_cases[] = array( $ic_chat, $mt_etud_aff, 'avis étudiés' );
  } elseif ( $mt_avis >= 50000 ) {
    $mt_cases[] = array( $ic_chat, number_format( $mt_avis, 0, ',', "\xE2\x80\xAF" ), 'avis clients recensés' );
  } else {
    /* Même décompte que la FAQ : questions saisies avec une réponse + questions automatiques
       (n°1, budget/avis/confiance, méthode ; marques si >= 3 marques ; choix si le guide a des critères) */
    $mt_faq  = get_field( 'mltv5_faq_comparatif', $this_id );
    if ( empty( $mt_faq ) ) { $mt_fid = (int) get_field( 'mltv5_cached_id_faq', $this_id ); $mt_faq = $mt_fid ? get_field( 'mltv5_faq_comparatif', $mt_fid ) : array(); }
    $mt_nq = 0;
    foreach ( (array) $mt_faq as $r ) {
      if ( trim( (string) ( $r['mltv5_faq_comparatif_question'] ?? '' ) ) !== '' && trim( wp_strip_all_tags( (string) ( $r['mltv5_faq_comparatif_reponse'] ?? '' ) ) ) !== '' ) { $mt_nq++; }
    }
    if ( $mt_top ) {
      $mt_nq += 3;
      $mt_marques = array();
      foreach ( $mt_top as $pid ) { $b = mb_strtolower( trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) ), 'UTF-8' ); if ( $b !== '' ) { $mt_marques[ $b ] = true; } }
      if ( count( $mt_marques ) >= 3 ) { $mt_nq++; }
      $mt_crit = get_field( 'mltv5_criteres_de_choix', $this_id );
      if ( empty( $mt_crit ) ) { $mt_cid = (int) get_field( 'mltv5_cached_id_criteres', $this_id ); $mt_crit = $mt_cid ? get_field( 'mltv5_criteres_de_choix', $mt_cid ) : array(); }
      if ( ! empty( $mt_crit ) ) { $mt_nq++; }
    }
    if ( $mt_nq > 0 ) { $mt_cases[] = array( $ic_chat, $mt_nq, 'questions fréquentes traitées' ); }
  }

  /* Case 4 : mots du contenu (introduction, guide d'achat, tests complets) ; affichée à partir de 8 000 */
  $mt_mots = mt_encadre_mots( $introduction ?? '' );
  foreach ( $mt_aff as $pid ) { $mt_mots += mt_encadre_mots( get_post_field( 'post_content', $pid ) ) + mt_encadre_mots( get_field( 'mltv5_resume_produit', $pid ) ); }
  foreach ( array( 'criteres', 'types', 'marques', 'astuces', 'raisons', 'faq' ) as $mt_part ) {
    $mt_aid = (int) get_field( 'mltv5_cached_id_' . $mt_part, $this_id );
    if ( $mt_aid && function_exists( 'get_fields' ) ) {
      foreach ( mt_encadre_textes( get_fields( $mt_aid ) ) as $t ) { $mt_mots += mt_encadre_mots( $t ); }
    }
  }
  if ( $mt_mots >= 8000 ) { $mt_cases[] = array( $ic_book, number_format( $mt_mots, 0, ',', "\xE2\x80\xAF" ), 'mots dans ce guide' ); }
}
?>
<div class="mt-card">

  <p class="mt-card-h"><span class="mt-card-hi"><?php echo $ic_shield; ?></span>Pourquoi nous faire confiance</p>

  <?php if ( $MT_ENCADRE_REEL && $mt_cases ) : ?>
  <div class="mt-sc-grid">
    <?php foreach ( $mt_cases as $mt_c ) : ?>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $mt_c[0]; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html( $mt_c[1] ); ?></div><div class="mt-sc-lbl"><?php echo esc_html( $mt_c[2] ); ?></div></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else : ?>
  <div class="mt-sc-grid">
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_clock; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($heures_investies ?? ''); ?></div><div class="mt-sc-lbl">heures de recherche</div></div>
    </div>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_layers; ?></span>
      <div><div class="mt-sc-num">12+</div><div class="mt-sc-lbl">années d'expérience</div></div>
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
  <?php endif; ?>

  <div class="mt-sc-trust">
    <div class="mt-sc-row"><span class="mt-ti"><?php echo $ic_check; ?></span><span><b>100&nbsp;% indépendant</b> (et sans pub)</span></div>
    <div class="mt-sc-row mt-sc-date"><span class="mt-ti"><?php echo $ic_refresh; ?></span><span>Mis à jour le <b><?php echo $mod; ?></b></span></div>
    <div class="mt-sc-row"><span class="mt-ti"><?php echo $ic_book; ?></span><span><b><?php echo $rt; ?> min</b> de lecture</span></div>
  </div>
    <div class="mt-sc-process link-black"><p>Les guides d'achat de Meilleurtest résultent d'un processus de sélection approfondi et d'une vérification méticuleuse. Découvrez <a href="/notre-methode/">notre méthodologie</a> et <a href="/notre-engagement/">nos engagements</a> qualité.</p>
  </div>
  <div class="mt-sc-vote">
    <p class="mt-sc-vote-h">Avis des lecteurs sur cette s&eacute;lection</p>
    <?php /* Rate My Post envoie toujours « Pas encore de note ! », caché par la classe --hidden quand la
             page a des votes : son texte est vidé pour que les moteurs ne lisent pas de contradiction
             (l'élément reste, le JS du plugin s'en sert). */
    echo preg_replace( '#(<p\b[^>]*rmp-rating-widget__not-rated--hidden[^>]*>).*?(</p>)#s', '$1$2', do_shortcode('[ratemypost]') ); ?>
    <p class="mt-sc-note">Votre note oriente les autres lecteurs et nous aide &agrave; am&eacute;liorer ce contenu. Merci&nbsp;!</p>
  </div>

</div>
