<?php
$this_id   = get_the_ID();
extract(get_all_template_variables($this_id));
$post_type = get_post_type($this_id);
$total_avis = !empty($top_avis_ids) ? count($top_avis_ids) : 0;
$mod = date_i18n('j F Y', get_the_modified_time('U'));
?>
<div class="mt-left">

  <?php if (function_exists('rank_math_the_breadcrumbs')) {
      echo '<div class="mt-crumb">';
      echo rank_math_the_breadcrumbs();
      echo '</div>';
  } ?>

  <div class="mt-eyebrow">
    <span class="pill">Vérifié</span>
    <span>le <?php echo $mod; ?></span>
  </div>

  <h1 class="mt-h1">
  <?php
    if (!empty($forcer_affichage_du_titre ?? '')) { echo $forcer_affichage_du_titre; }
    elseif ($post_type === 'comparatif') { echo "Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." en 2026"; }
    else { echo get_the_title(); }
    if (!empty($sous_titre ?? '')) { echo "<span class='mt-sub'>".$sous_titre."</span>"; }
    elseif ($post_type === 'comparatif') { echo "<span class='mt-sub'> : Comparatif et guide d'achat</span>"; }
  ?>
  </h1>

  <?php // ---- Effets SEO rank math (title + description) ----
    $rank_math_title = get_post_meta($this_id, 'rank_math_title');
    $rank_math_description = get_post_meta($this_id, 'rank_math_description');
    if (($template_description ?? '') == 0 || $post_type === 'liste') {
        $new_desc = intro(50, $this_id);
        if (($new_desc <> $rank_math_description) && ($this_id <> 4224)) {
            update_post_meta($this_id, 'rank_math_description', $new_desc);
        }
        $p = get_post($this_id);
        if (($p->post_excerpt ?? '') !== $new_desc) {
            wp_update_post(array('ID' => $this_id, 'post_excerpt' => $new_desc));
        }
    }
    if (!empty($forcer_affichage_du_titre ?? '')) {
        $new_title = $forcer_affichage_du_titre;
    } elseif ($post_type === 'liste') {
        $new_title = get_the_title($this_id);
    } else {
        $new_title = "Comparatif : Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." 2026";
    }
    if (($new_title <> $rank_math_title) && ($this_id <> 4224)) {
        update_post_meta($this_id, 'rank_math_title', $new_title);
    }
  ?>

  <div class="mt-byline">
    <?php if (!empty($author_avatar_id ?? '')) {
        echo wp_get_attachment_image($author_avatar_id, array(30,30), '', array('class'=>'mt-avatar','alt'=>$author_avatar_alt ?? ''));
    } ?>
    <span>Par <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID', $author_id ?? ''))); ?>"><?php echo esc_html($author ?? ''); ?></a></span>
    <span class="mt-dot">&bull;</span>
    <span>Mis à jour le <?php echo $mod; ?></span>
  </div>

  <div class="mt-lede text-content"><?php echo $introduction ?? ''; ?></div>

  <div class="mt-photo">
    <?php echo get_the_post_thumbnail($this_id, 'large'); ?>
    <?php if ($post_type === 'comparatif') {
        echo '<img class="mt-badge" src="https://meilleurtest.fr/wp-content/uploads/2025/11/badge-mt.png" alt="">';
    } ?>
  </div>

</div>
