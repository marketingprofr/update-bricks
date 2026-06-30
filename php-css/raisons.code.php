<?php
/* =====================================================================
   MEILLEURTEST — « Pourquoi acheter ? »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (raisons.css).

   Source des données (ACF) — GROUPE mltv5_partie_pourquoi_acheter :
     . mltv5_titre_pourquoi_acheter   (titre de la section)
     . mltv5_image_pourquoi_acheter   (image d'illustration)
     . mltv5_raisons_acheter          (TOUTES les raisons en un seul WYSIWYG)
   Pas de repeater -> on ne reproduit pas les cartes numérotées de la maquette :
   on rend le contenu riche stylé, avec l'image en illustration flottée.

   Le contenu peut vivre sur la page courante OU sur le post lié dont l'ID est
   mis en cache dans `mltv5_cache_id_raisons` -> lecture robuste avec repli.
   Section de texte -> `.contenu-principal` (jauge de lecture) + ancre
   `partie-raisons` (lien du sommaire / scrollspy).
   ===================================================================== */

if ( ! function_exists( 'mt_guide_cache_id' ) ) {
  /* Résout l'ID du post lié mis en cache : essaie `mltv5_cache_id_{suffix}`
     puis `mltv5_cached_id_{suffix}` (ancien nom) ; accepte un ID ou un objet post. */
  function mt_guide_cache_id( $page_id, $suffix ) {
    foreach ( array( 'mltv5_cached_id_' . $suffix, 'mltv5_cache_id_' . $suffix ) as $f ) {
      $v = function_exists( 'get_field' ) ? get_field( $f, $page_id ) : null;
      if ( is_array( $v ) ) { $v = reset( $v ); }       /* relation/post-object multiple */
      if ( is_object( $v ) ) { return (int) $v->ID; }     /* Post Object */
      if ( $v ) { return (int) $v; }                      /* ID scalaire */
    }
    return 0;
  }
}
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
if ( ! function_exists( 'mt_raisons_read' ) ) {
  function mt_raisons_read( $pid ) {
    $g = function_exists( 'get_field' ) ? get_field( 'mltv5_partie_pourquoi_acheter', $pid ) : null;
    return is_array( $g ) ? $g : array();
  }
}

$page_id = get_the_ID();

$has = function ( $g ) {
  $r = isset( $g['mltv5_raisons_acheter'] ) ? trim( (string) $g['mltv5_raisons_acheter'] ) : '';
  $i = isset( $g['mltv5_image_pourquoi_acheter'] ) ? $g['mltv5_image_pourquoi_acheter'] : '';
  return ( $r !== '' || ! empty( $i ) );
};

$src_id = $page_id;
$grp    = mt_raisons_read( $src_id );
if ( ! $has( $grp ) ) {
  $cached = mt_guide_cache_id( $page_id, 'raisons' );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_raisons_read( $cached );
    if ( $has( $alt ) ) { $src_id = $cached; $grp = $alt; }
  }
}

$title_raw   = isset( $grp['mltv5_titre_pourquoi_acheter'] ) ? trim( (string) $grp['mltv5_titre_pourquoi_acheter'] ) : '';
$img_raw     = isset( $grp['mltv5_image_pourquoi_acheter'] ) ? $grp['mltv5_image_pourquoi_acheter'] : '';
$reasons     = mt_guide_rich( isset( $grp['mltv5_raisons_acheter'] ) ? $grp['mltv5_raisons_acheter'] : '' );
$img_url     = mt_guide_img_url( $img_raw );

/* Rien à afficher -> diagnostic admin (builder), invisible pour les visiteurs. */
if ( $reasons === '' && $img_url === '' ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $g = function_exists( 'get_field' ) ? get_field( 'mltv5_partie_pourquoi_acheter', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-raisons — diagnostic (admin only)</strong> : aucun contenu trouvé.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'cache_id r&eacute;solu = ' . mt_guide_cache_id( $page_id, 'raisons' ) . ' (brut mltv5_cached_id_raisons : ' . gettype( get_field( 'mltv5_cached_id_raisons', $page_id ) ) . ')<br>'
       . 'groupe mltv5_partie_pourquoi_acheter = ' . esc_html( gettype( $g ) )
       . ( is_array( $g ) ? ' [' . esc_html( implode( ', ', array_keys( $g ) ) ) . ']' : '' )
       . '</div>';
  }
  return;
}

$title   = $title_raw !== '' ? esc_html( $title_raw ) : 'Pourquoi acheter&nbsp;?';
$img_alt = mt_guide_img_alt( $img_raw, $title_raw !== '' ? $title_raw : 'Pourquoi acheter' );
?>
<section class="mt-raisons contenu-principal" id="partie-raisons" aria-labelledby="mt-raisons-title">
  <h2 class="mt-raisons-h2" id="mt-raisons-title"><?php echo $title; ?></h2>
  <div class="mt-raisons-body">
    <?php if ( $img_url !== '' ) : ?>
    <figure class="mt-raisons-fig">
      <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" loading="lazy">
    </figure>
    <?php endif; ?>
    <?php echo $reasons; ?>
  </div>
</section>
