<?php
/* =====================================================================
   MEILLEURTEST — Guide d'achat « Critères de choix »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (criteres.css).

   Source des données (ACF de la page courante) :
   - GROUPE   mltv5_partie_criteres_de_choix
       . mltv5_image_criteres_de_choix        (image d'illustration)
       . mltv5_introduction_criteres_de_choix (chapô du guide)
   - REPEATER mltv5_criteres_de_choix   (1 ligne = 1 critère)
       . mltv5_critere_de_choix       (titre du critère)
       . mltv5_description_du_critere (description — WYSIWYG)

   Section pleine de texte -> porte `.contenu-principal` (jauge de lecture du
   sommaire) ET l'ancre `partie-guide-achat` (lien du sommaire / scrollspy).
   ===================================================================== */

/* ---------------------------------------------------------------------
   Helpers (déclarés une fois — cohabitent avec les autres blocs Code)
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

/* ---------------------------------------------------------------------
   Données
   --------------------------------------------------------------------- */
$page_id   = get_the_ID();
$page_tv   = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';

/* Groupe « Partie critères de choix » (image + intro) */
$grp = function_exists( 'get_field' ) ? get_field( 'mltv5_partie_criteres_de_choix', $page_id ) : array();
$grp = is_array( $grp ) ? $grp : array();
$intro_raw = isset( $grp['mltv5_introduction_criteres_de_choix'] ) ? $grp['mltv5_introduction_criteres_de_choix'] : '';
$img_raw   = isset( $grp['mltv5_image_criteres_de_choix'] ) ? $grp['mltv5_image_criteres_de_choix'] : '';

/* Repeater des critères */
$rows = function_exists( 'get_field' ) ? get_field( 'mltv5_criteres_de_choix', $page_id ) : array();
$rows = is_array( $rows ) ? $rows : array();

$crits = array();
foreach ( $rows as $r ) {
  $t = trim( (string) ( isset( $r['mltv5_critere_de_choix'] ) ? $r['mltv5_critere_de_choix'] : '' ) );
  $d = (string) ( isset( $r['mltv5_description_du_critere'] ) ? $r['mltv5_description_du_critere'] : '' );
  if ( $t === '' && trim( $d ) === '' ) { continue; }
  $crits[] = array( 'title' => $t, 'desc' => mt_guide_rich( $d ) );
}

$intro   = mt_guide_rich( $intro_raw );
$img_url = mt_guide_img_url( $img_raw );

/* Rien à afficher -> on ne sort rien (la section reste vide / masquée). */
if ( empty( $crits ) && $intro === '' ) { return; }

$title       = 'Guide d&rsquo;achat' . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : '' );
$img_alt     = mt_guide_img_alt( $img_raw, 'Guide d\'achat ' . $type_plur );
$has_img     = ( $img_url !== '' );
?>
<section class="mt-guide contenu-principal" id="partie-guide-achat" aria-labelledby="mt-guide-title">
  <div class="mt-guide-lead<?php echo $has_img ? '' : ' no-img'; ?>">
    <div class="mt-guide-head">
      <h2 class="mt-guide-h2" id="mt-guide-title"><?php echo $title; ?></h2>
      <?php if ( $intro !== '' ) : ?>
      <div class="mt-guide-intro"><?php echo $intro; ?></div>
      <?php endif; ?>
    </div>
    <?php if ( $has_img ) : ?>
    <figure class="mt-guide-fig">
      <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" loading="lazy">
    </figure>
    <?php endif; ?>
  </div>

  <?php if ( ! empty( $crits ) ) : ?>
  <div class="mt-guide-list">
    <?php $n = 0; foreach ( $crits as $c ) : $n++; ?>
    <article class="mt-crit">
      <div class="mt-crit-num">
        <span class="lbl">Crit&egrave;re</span>
        <span class="big"><?php echo str_pad( (string) $n, 2, '0', STR_PAD_LEFT ); ?></span>
      </div>
      <div class="mt-crit-body">
        <?php if ( $c['title'] !== '' ) : ?><h3><?php echo esc_html( $c['title'] ); ?></h3><?php endif; ?>
        <?php echo $c['desc']; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
