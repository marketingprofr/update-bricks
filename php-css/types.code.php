<?php
/* =====================================================================
   MEILLEURTEST — « Quel type choisir ? » (types de produit)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   CSS dans l'onglet CSS du même élément (types.css).

   Source des données (ACF) :
   - GROUPE mltv5_partie_types_de_produits
       . mltv5_introduction_types_de_produits   (intro — fallback ci-dessous)
   - REPEATER mltv5_types_de_produits   (1 ligne = 1 type)
       . mltv5_type_de_produit                  (nom du type)
       . mltv5_image_type_de_produit            (image)
       . mltv5_description_du_type_de_produit   (description — WYSIWYG)
       . mltv5_points_positifs_type_de_produit  (atouts — WYSIWYG)
       . mltv5_points_negatifs_type_de_produit  (limites — WYSIWYG)
       . mltv5_pour_qui_est_ce_type_de_produit  (pour qui — WYSIWYG)
   Lecture robuste : page courante → repli `mltv5_cached_id_types`.
   Section de texte -> `.contenu-principal` (jauge de lecture) + ancre
   `partie-types` (lien du sommaire / scrollspy).
   ===================================================================== */

if ( ! function_exists( 'mt_guide_rich' ) ) {
  function mt_guide_rich( $html ) {
    $html = (string) $html;
    if ( trim( $html ) === '' ) { return ''; }
    if ( ! preg_match( '/<(p|ul|ol|h[1-6]|blockquote|div|table|figure)\b/i', $html ) ) {
      $html = wpautop( $html );
    }
    return $html;
  }
}
if ( ! function_exists( 'mt_guide_img_url' ) ) {
  function mt_guide_img_url( $img, $size = 'large' ) {
    if ( is_array( $img ) ) {
      if ( ! empty( $img['sizes'][ $size ] ) ) { return $img['sizes'][ $size ]; }
      return isset( $img['url'] ) ? $img['url'] : '';
    }
    if ( is_numeric( $img ) ) { $u = wp_get_attachment_image_url( (int) $img, $size ); return $u ? $u : ''; }
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
if ( ! function_exists( 'mt_types_read' ) ) {
  function mt_types_read( $pid ) {
    $grp  = function_exists( 'get_field' ) ? get_field( 'mltv5_partie_types_de_produits', $pid ) : null;
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_types_de_produits', $pid ) : null;
    return array( 'grp' => is_array( $grp ) ? $grp : array(), 'rows' => is_array( $rows ) ? $rows : array() );
  }
}

$page_id   = get_the_ID();
$page_tv   = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';

$src_id = $page_id;
$data   = mt_types_read( $src_id );
if ( empty( $data['rows'] ) ) {
  $cached = (int) get_field( 'mltv5_cached_id_types', $page_id );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_types_read( $cached );
    if ( ! empty( $alt['rows'] ) ) { $src_id = $cached; $data = $alt; }
  }
}

$types = array();
foreach ( $data['rows'] as $r ) {
  $name = trim( (string) ( isset( $r['mltv5_type_de_produit'] ) ? $r['mltv5_type_de_produit'] : '' ) );
  $desc = mt_guide_rich( isset( $r['mltv5_description_du_type_de_produit'] ) ? $r['mltv5_description_du_type_de_produit'] : '' );
  $pros = mt_guide_rich( isset( $r['mltv5_points_positifs_type_de_produit'] ) ? $r['mltv5_points_positifs_type_de_produit'] : '' );
  $cons = mt_guide_rich( isset( $r['mltv5_points_negatifs_type_de_produit'] ) ? $r['mltv5_points_negatifs_type_de_produit'] : '' );
  $who  = mt_guide_rich( isset( $r['mltv5_pour_qui_est_ce_type_de_produit'] ) ? $r['mltv5_pour_qui_est_ce_type_de_produit'] : '' );
  $img  = mt_guide_img_url( isset( $r['mltv5_image_type_de_produit'] ) ? $r['mltv5_image_type_de_produit'] : '' );
  $imga = mt_guide_img_alt( isset( $r['mltv5_image_type_de_produit'] ) ? $r['mltv5_image_type_de_produit'] : '', $name );
  if ( $name === '' && $desc === '' && $pros === '' && $cons === '' && $who === '' && $img === '' ) { continue; }
  $types[] = compact( 'name', 'desc', 'pros', 'cons', 'who', 'img', 'imga' );
}

$intro = mt_guide_rich( isset( $data['grp']['mltv5_introduction_types_de_produits'] ) ? $data['grp']['mltv5_introduction_types_de_produits'] : '' );
if ( $intro === '' ) { $intro = '<p>Voici les diff&eacute;rents types de produits.</p>'; }

/* Rien à afficher -> diagnostic admin (builder), invisible pour les visiteurs. */
if ( empty( $types ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_types_de_produits', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-types — diagnostic (admin only)</strong> : aucun type trouv&eacute;.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'mltv5_cached_id_types = ' . esc_html( (string) get_field( 'mltv5_cached_id_types', $page_id ) ) . '<br>'
       . 'repeater mltv5_types_de_produits = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' )
       . '</div>';
  }
  return;
}

$title = 'Les ' . ( $type_plur !== '' ? esc_html( $type_plur ) : 'produits' ) . '&nbsp;: quel type choisir&nbsp;?';
?>
<section class="mt-types contenu-principal" id="partie-types" aria-labelledby="mt-types-title">
  <h2 class="mt-types-h2" id="mt-types-title"><?php echo $title; ?></h2>
  <div class="mt-types-intro"><?php echo $intro; ?></div>

  <div class="mt-types-list">
    <?php foreach ( $types as $i => $t ) : ?>
    <article class="mt-type">
      <?php if ( $t['img'] !== '' ) : ?>
      <figure class="mt-type-fig">
        <img src="<?php echo esc_url( $t['img'] ); ?>" alt="<?php echo esc_attr( $t['imga'] ); ?>" loading="lazy">
      </figure>
      <?php endif; ?>
      <div class="mt-type-main">
        <?php if ( $t['name'] !== '' ) : ?>
        <p class="mt-type-eyebrow">Type <?php echo str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ); ?></p>
        <h3 class="mt-type-h"><?php echo esc_html( $t['name'] ); ?></h3>
        <?php endif; ?>
        <?php if ( $t['desc'] !== '' ) : ?><div class="mt-type-desc"><?php echo $t['desc']; ?></div><?php endif; ?>

        <?php if ( $t['pros'] !== '' || $t['cons'] !== '' ) : ?>
        <div class="mt-type-pc">
          <?php if ( $t['pros'] !== '' ) : ?>
          <div class="mt-pc mt-pc-pro">
            <p class="mt-pc-lbl">Points forts</p>
            <div class="mt-pc-body"><?php echo $t['pros']; ?></div>
          </div>
          <?php endif; ?>
          <?php if ( $t['cons'] !== '' ) : ?>
          <div class="mt-pc mt-pc-con">
            <p class="mt-pc-lbl">Points faibles</p>
            <div class="mt-pc-body"><?php echo $t['cons']; ?></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ( $t['who'] !== '' ) : ?>
        <div class="mt-type-who">
          <p class="mt-type-who-lbl">Pour qui&nbsp;?</p>
          <div class="mt-type-who-body"><?php echo $t['who']; ?></div>
        </div>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
</section>
