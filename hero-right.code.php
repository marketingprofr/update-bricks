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

// Icones SVG outline (couleur via CSS / currentColor)
$ic_shield  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>';
$ic_clock   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
$ic_layers  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/></svg>';
$ic_tablet  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="2"/><line x1="10.5" y1="17.5" x2="13.5" y2="17.5"/></svg>';
$ic_chat    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-1.1 4.2A8.5 8.5 0 0 1 12.5 20a8.4 8.4 0 0 1-4.2-1.1L3 20l1.1-5.3A8.4 8.4 0 0 1 3 10.5 8.5 8.5 0 0 1 7.3 3a8.4 8.4 0 0 1 4.2-1.1h.5A8.5 8.5 0 0 1 21 11v.5Z"/></svg>';
$ic_check   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg>';
$ic_refresh = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg>';
$ic_book    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6c-1.6-1.2-4-2-7-2v13c3 0 5.4.8 7 2 1.6-1.2 4-2 7-2V4c-3 0-5.4.8-7 2Z"/><path d="M12 6v13"/></svg>';
?>
<div class="mt-card">

  <h3 class="mt-card-h"><span class="mt-card-hi"><?php echo $ic_shield; ?></span>Notre enquête</h3>

  <div class="mt-sc-grid">
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_clock; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($heures_investies ?? ''); ?> h</div><div class="mt-sc-lbl">de recherche</div></div>
    </div>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_layers; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($sources_consultees ?? ''); ?></div><div class="mt-sc-lbl">sources consultées</div></div>
    </div>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_tablet; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($produits_analyses ?? ''); ?></div><div class="mt-sc-lbl"><?php echo esc_html($lblprod); ?></div></div>
    </div>
    <div class="mt-sc-cell">
      <span class="mt-sc-ico"><?php echo $ic_chat; ?></span>
      <div><div class="mt-sc-num"><?php echo esc_html($avis_etudies ?? ''); ?></div><div class="mt-sc-lbl">avis étudiés</div></div>
    </div>
  </div>

  <div class="mt-sc-trust">
    <div class="mt-sc-row"><span class="mt-ti"><?php echo $ic_check; ?></span><span><b>100&nbsp;% indépendant</b> &mdash; sans pub ni sponsor</span></div>
    <div class="mt-sc-row"><span class="mt-ti"><?php echo $ic_refresh; ?></span><span>Mis à jour le <b><?php echo $mod; ?></b></span></div>
    <div class="mt-sc-row"><span class="mt-ti"><?php echo $ic_book; ?></span><span><b><?php echo $rt; ?> min</b> de lecture</span></div>
  </div>

  <div class="mt-sc-vote">
    <h4>Noter ce guide</h4>
    <?php echo do_shortcode('[ratemypost]'); ?>
    <p class="mt-sc-note">Votre note oriente les autres lecteurs et nous aide à améliorer ce contenu. Merci&nbsp;!</p>
  </div>

</div>
