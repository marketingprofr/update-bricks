<?php
/* =====================================================================
   MEILLEURTEST — ARTICLE « LISTE » (idées cadeaux / sélections — cf.
   templates/template-liste.html)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON), placé
   dans : SECTION > CONTAINER > CODE. Le CSS va dans l'onglet CSS du même
   élément (liste.css). Scope : .mt-lp.

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-white)  (blanc)

   ⚠️ DIFFÉRENCE avec les comparatifs : ici on N'utilise PAS des posts
   « avis » liés. Les éléments listés vivent DANS l'article « liste »
   courant, sous forme d'un REPEATER ACF :

     mltv5_elements_liste           (répéteur — 1 ligne = 1 élément)
       . mltv5_nom_element_liste          (nom de l'élément)
       . mltv5_image_element_liste        (image — ID de pièce jointe)
       . mltv5_lien_1_element_liste       (lien 1 = offre principale)
       . mltv5_texte_lien_1_element_liste (libellé du lien 1)
       . mltv5_lien_2_element_liste       (lien 2 = lien secondaire)
       . mltv5_texte_lien_2_element_liste (libellé du lien 2)
       . mltv5_description_element_liste  (description — WYSIWYG)

   Éléments de la maquette SANS donnée source → volontairement ABANDONNÉS :
   badges de prix, étiquettes de budget + filtres, mention « coup de cœur »,
   sous-titre, encart « pourquoi on l'aime ». On garde : nom, image,
   description, bouton « offre » (lien 1) + lien secondaire (lien 2).
   ===================================================================== */

/* ---------------------------------------------------------------------
   Helpers partagés — guardés (byte-identiques aux blocs guide : le 1er
   bloc chargé les définit, les autres réutilisent).
   --------------------------------------------------------------------- */
if ( ! function_exists( 'mt_guide_img_url' ) ) {
  /* Normalise un champ image ACF (array | ID | URL) -> URL. */
  function mt_guide_img_url( $img, $size = 'large' ) {
    if ( is_array( $img ) ) {
      if ( ! empty( $img['sizes'][ $size ] ) ) { return $img['sizes'][ $size ]; }
      return isset( $img['url'] ) ? $img['url'] : '';
    }
    if ( is_numeric( $img ) ) {
      $u = wp_get_attachment_image_url( (int) $img, $size );
      return $u ? $u : '';
    }
    return is_string( $img ) ? $img : '';
  }
}
if ( ! function_exists( 'mt_guide_img_alt' ) ) {
  function mt_guide_img_alt( $img, $fallback = '' ) {
    if ( is_array( $img ) && ! empty( $img['alt'] ) ) { return $img['alt']; }
    if ( is_numeric( $img ) ) {
      $a = get_post_meta( (int) $img, '_wp_attachment_image_alt', true );
      if ( $a !== '' ) { return $a; }
    }
    return $fallback;
  }
}
if ( ! function_exists( 'mt_guide_rich' ) ) {
  /* Contenu éditorial : un WYSIWYG ACF renvoie déjà du HTML formaté ;
     un textarea simple -> on reconstruit les paragraphes (wpautop). */
  function mt_guide_rich( $html ) {
    $html = (string) $html;
    if ( trim( $html ) === '' ) { return ''; }
    if ( ! preg_match( '/<(p|ul|ol|h[1-6]|blockquote|div|table|figure)\b/i', $html ) ) {
      $html = wpautop( $html );
    }
    return $html;
  }
}
if ( ! function_exists( 'mt_liste_primary_cat' ) ) {
  /* Catégorie principale d'un post (Yoast primaire sinon 1re). Renvoie
     array(name, link) ou null. */
  function mt_liste_primary_cat( $post_id ) {
    $terms = get_the_terms( $post_id, 'category' );
    if ( ! is_array( $terms ) || empty( $terms ) ) { return null; }
    $pick = $terms[0];
    $pc   = (int) get_post_meta( $post_id, '_yoast_wpseo_primary_category', true );
    if ( $pc ) { foreach ( $terms as $t ) { if ( (int) $t->term_id === $pc ) { $pick = $t; break; } } }
    $link = get_term_link( $pick );
    return array( 'name' => $pick->name, 'link' => is_wp_error( $link ) ? '' : $link );
  }
}
if ( ! function_exists( 'mt_liste_reading_time' ) ) {
  /* Estimation du temps de lecture (min, mini 1) à ~200 mots/min. */
  function mt_liste_reading_time( $text, $wpm = 200 ) {
    $w = str_word_count( wp_strip_all_tags( (string) $text ) );
    return max( 1, (int) ceil( $w / max( 1, (int) $wpm ) ) );
  }
}

/* ---------------------------------------------------------------------
   Données : le répéteur vit sur l'article « liste » courant.
   --------------------------------------------------------------------- */
$lp_id   = get_the_ID();
$lp_rows = function_exists( 'get_field' ) ? get_field( 'mltv5_elements_liste', $lp_id ) : null;
$lp_rows = is_array( $lp_rows ) ? $lp_rows : array();

$items = array();
foreach ( $lp_rows as $r ) {
  if ( ! is_array( $r ) ) { continue; }
  $name = trim( (string) ( isset( $r['mltv5_nom_element_liste'] ) ? $r['mltv5_nom_element_liste'] : '' ) );
  $desc = (string) ( isset( $r['mltv5_description_element_liste'] ) ? $r['mltv5_description_element_liste'] : '' );
  $img  = isset( $r['mltv5_image_element_liste'] ) ? $r['mltv5_image_element_liste'] : '';
  if ( $name === '' && trim( $desc ) === '' && empty( $img ) ) { continue; }
  $u1 = trim( (string) ( isset( $r['mltv5_lien_1_element_liste'] ) ? $r['mltv5_lien_1_element_liste'] : '' ) );
  $t1 = trim( (string) ( isset( $r['mltv5_texte_lien_1_element_liste'] ) ? $r['mltv5_texte_lien_1_element_liste'] : '' ) );
  $u2 = trim( (string) ( isset( $r['mltv5_lien_2_element_liste'] ) ? $r['mltv5_lien_2_element_liste'] : '' ) );
  $t2 = trim( (string) ( isset( $r['mltv5_texte_lien_2_element_liste'] ) ? $r['mltv5_texte_lien_2_element_liste'] : '' ) );
  $items[] = array(
    'name'    => $name,
    'desc'    => mt_guide_rich( $desc ),
    'desc_raw'=> $desc,
    'img_url' => mt_guide_img_url( $img, 'large' ),
    'img_alt' => mt_guide_img_alt( $img, $name ),
    'u1' => $u1, 't1' => $t1,
    'u2' => $u2, 't2' => $t2,
  );
}

/* Rien à afficher -> diagnostic éditeurs connectés uniquement, sinon rien. */
if ( empty( $items ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-liste — diagnostic (admin only)</strong> : aucun élément trouvé.<br>'
       . 'get_the_ID() = ' . (int) $lp_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $lp_id ) ) . '<br>'
       . 'répéteur mltv5_elements_liste = ' . esc_html( gettype( $lp_rows ) )
       . ( is_array( $lp_rows ) ? ' (count=' . count( $lp_rows ) . ')' : '' ) . '<br>'
       . 'ACF actif = ' . ( function_exists( 'get_field' ) ? 'oui' : 'NON' )
       . '</div>';
  }
  return;
}

/* ---------------------------------------------------------------------
   Métadonnées d'en-tête
   --------------------------------------------------------------------- */
$count    = count( $items );
$cat      = mt_liste_primary_cat( $lp_id );
$title    = get_the_title( $lp_id );
$lede     = get_the_excerpt( $lp_id );
$aid      = (int) get_post_field( 'post_author', $lp_id );
$author   = get_the_author_meta( 'display_name', $aid );
$avatar   = get_avatar( $aid, 68, '', $author, array( 'class' => 'mt-lp-avatar' ) );
$mod_date = get_the_modified_date( 'j M Y', $lp_id );
$cover    = get_the_post_thumbnail_url( $lp_id, 'large' );

/* Temps de lecture : chapô + noms + descriptions du répéteur. */
$rt_text = $lede;
foreach ( $items as $it ) { $rt_text .= ' ' . $it['name'] . ' ' . $it['desc_raw']; }
$read_min = mt_liste_reading_time( $rt_text );

/* H1 : on met en valeur un éventuel nombre en tête de titre (« 12 idées… »). */
$title_html = preg_replace( '/^(\s*)(\d+)/u', '$1<span class="n">$2</span>', esc_html( $title ), 1 );

/* Eyebrow : catégorie + « Sélection de la rédaction ». */
$eyebrow = ( $cat && $cat['name'] !== '' ) ? $cat['name'] . ' &middot; S&eacute;lection de la r&eacute;daction' : 'S&eacute;lection de la r&eacute;daction';

/* UID d'instance pour les ancres du sommaire interne. */
if ( ! isset( $GLOBALS['mt_liste_uid'] ) ) { $GLOBALS['mt_liste_uid'] = 0; }
$GLOBALS['mt_liste_uid']++;
$uid   = (int) $GLOBALS['mt_liste_uid'];
$anch  = 'mt-lp-' . $uid . '-idee-';

/* Fil d'ariane. */
$home_url = home_url( '/' );
?>

<section class="mt-lp">

  <!-- Fil d'ariane -->
  <nav class="mt-lp-crumb" aria-label="Fil d'ariane">
    <a href="<?php echo esc_url( $home_url ); ?>">Accueil</a>
    <?php if ( $cat && $cat['link'] !== '' ) : ?>
      &nbsp;&rsaquo;&nbsp; <a href="<?php echo esc_url( $cat['link'] ); ?>"><?php echo esc_html( $cat['name'] ); ?></a>
    <?php endif; ?>
    &nbsp;&rsaquo;&nbsp; <b><?php echo esc_html( $title ); ?></b>
  </nav>

  <!-- Hero éditorial -->
  <header class="mt-lp-hero">
    <span class="mt-lp-eyebrow">
      <svg class="dot" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2z"/><path d="M12 21V7"/><path d="M12 7S11 3.5 8.5 3.5A2.5 2.5 0 0 0 8.5 8.5H12"/><path d="M12 7s1-3.5 3.5-3.5A2.5 2.5 0 0 1 15.5 8.5H12"/></svg>
      <?php echo $eyebrow; // libellé contrôlé ?>
    </span>
    <h1><?php echo $title_html; // titre échappé + nombre mis en valeur ?></h1>
    <?php if ( trim( (string) $lede ) !== '' ) : ?>
    <p class="mt-lp-lede"><?php echo esc_html( $lede ); ?></p>
    <?php endif; ?>

    <div class="mt-lp-byline">
      <span class="who">
        <?php echo $avatar ? $avatar : '<span class="mt-lp-avatar"></span>'; ?>
        <span>Par <b><?php echo esc_html( $author ); ?></b></span>
      </span>
      <span class="sep"></span>
      <span class="meta"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg> Mis &agrave; jour le <?php echo esc_html( $mod_date ); ?></span>
      <span class="meta"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg> <?php echo (int) $read_min; ?> min de lecture</span>
      <span class="meta"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h10"/></svg> <?php echo (int) $count; ?> id&eacute;es</span>
      <span class="spacer"></span>
      <span class="mt-lp-share">
        <a href="#" data-mt-share data-done="Lien copi&eacute;&nbsp;!"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M12 3v13"/><path d="m8 7 4-4 4 4"/></svg> Partager</a>
      </span>
    </div>
  </header>

  <?php if ( $cover ) : ?>
  <!-- Image de couverture -->
  <div class="mt-lp-cover">
    <img src="<?php echo esc_url( $cover ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
  </div>
  <?php endif; ?>

  <!-- Sommaire + note méthode -->
  <div class="mt-lp-tools">
    <div class="mt-lp-toc-wrap">
      <p class="mt-lp-panel-title"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/></svg> Au sommaire</p>
      <nav class="mt-lp-toc">
        <?php $n = 0; foreach ( $items as $it ) : $n++;
          $label = $it['name'] !== '' ? $it['name'] : 'Id&eacute;e ' . $n; ?>
        <a href="#<?php echo esc_attr( $anch . $n ); ?>"><span class="tn"><?php echo (int) $n; ?></span><span class="tt"><?php echo esc_html( $label ); ?></span></a>
        <?php endforeach; ?>
      </nav>
    </div>

    <div class="mt-lp-note-wrap">
      <p class="mt-lp-panel-title"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg> En toute ind&eacute;pendance</p>
      <div class="mt-lp-note">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
        <span><b>Comment on choisit.</b> Ces id&eacute;es sont s&eacute;lectionn&eacute;es par notre r&eacute;daction, &agrave; partir des produits que nous avons test&eacute;s ou longuement recherch&eacute;s. Aucune marque ne paie pour y figurer.</span>
      </div>
    </div>
  </div>

  <!-- Liste -->
  <div class="mt-lp-list">
    <?php $n = 0; foreach ( $items as $it ) : $n++;
      $has_img = ( $it['img_url'] !== '' );
      $num2    = str_pad( (string) $n, 2, '0', STR_PAD_LEFT );
      $btn_lbl = $it['t1'] !== '' ? $it['t1'] : "Voir l'offre";
      ?>
    <article class="mt-lp-item" id="<?php echo esc_attr( $anch . $n ); ?>">
      <div class="mt-lp-item-head">
        <span class="mt-lp-item-num"><?php echo esc_html( $num2 ); ?></span>
        <span class="mt-lp-item-rule"></span>
      </div>
      <div class="mt-lp-item-body">
        <div class="mt-lp-item-media<?php echo $has_img ? '' : ' ph'; ?>">
          <?php if ( $has_img ) : ?><img src="<?php echo esc_url( $it['img_url'] ); ?>" alt="<?php echo esc_attr( $it['img_alt'] ); ?>" loading="lazy"><?php endif; ?>
        </div>
        <div class="mt-lp-item-text">
          <?php if ( $it['name'] !== '' ) : ?>
          <h2><?php if ( $it['u1'] !== '' ) : ?><a href="<?php echo esc_url( $it['u1'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $it['name'] ); ?></a><?php else : echo esc_html( $it['name'] ); endif; ?></h2>
          <?php endif; ?>
          <?php if ( $it['desc'] !== '' ) : ?><div class="mt-lp-desc"><?php echo $it['desc']; // WYSIWYG de confiance ?></div><?php endif; ?>
          <?php if ( $it['u1'] !== '' || ( $it['u2'] !== '' && $it['t2'] !== '' ) ) : ?>
          <div class="mt-lp-item-foot">
            <?php if ( $it['u1'] !== '' ) : ?>
            <a class="mt-lp-buy" href="<?php echo esc_url( $it['u1'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $btn_lbl ); ?> <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7"/><path d="M8 7h9v9"/></svg></a>
            <?php endif; ?>
            <?php if ( $it['u2'] !== '' ) : ?>
            <a class="mt-lp-alt" href="<?php echo esc_url( $it['u2'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><?php echo esc_html( $it['t2'] !== '' ? $it['t2'] : 'Autre offre' ); ?></a>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <!-- Retour haut de liste -->
  <div class="mt-lp-more">
    <a href="#<?php echo esc_attr( $anch . '1' ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg> Revenir en haut de la liste</a>
  </div>

  <script>
  (function () {
    var s = document.currentScript;
    var root = s ? s.closest('.mt-lp') : null;
    if (!root) { return; }
    var btn = root.querySelector('[data-mt-share]');
    if (!btn) { return; }
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var data = { title: document.title, url: location.href };
      if (navigator.share) { navigator.share(data).catch(function () {}); return; }
      if (navigator.clipboard) {
        navigator.clipboard.writeText(location.href).then(function () {
          var done = btn.getAttribute('data-done'); if (!done) { return; }
          var html = btn.innerHTML; btn.textContent = done;
          setTimeout(function () { btn.innerHTML = html; }, 1600);
        }).catch(function () {});
      }
    });
  })();
  </script>

</section>
