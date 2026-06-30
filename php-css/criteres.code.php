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
if ( ! function_exists( 'mt_guide_cache_id' ) ) {
  /* Résout l'ID du post lié mis en cache : essaie `mltv5_cache_id_{suffix}`
     puis `mltv5_cached_id_{suffix}` (ancien nom) ; accepte un ID ou un objet post. */
  function mt_guide_cache_id( $page_id, $suffix ) {
    $keys = array( 'mltv5_cached_id_' . $suffix, 'mltv5_cache_id_' . $suffix );
    foreach ( $keys as $f ) {                            /* 1) ACF */
      $v = function_exists( 'get_field' ) ? get_field( $f, $page_id ) : null;
      if ( is_array( $v ) ) { $v = reset( $v ); }
      if ( is_object( $v ) ) { return (int) $v->ID; }
      if ( $v ) { return (int) $v; }
    }
    foreach ( $keys as $f ) {                            /* 2) meta brut (hors ACF) */
      $v = function_exists( 'get_post_meta' ) ? get_post_meta( $page_id, $f, true ) : '';
      if ( is_array( $v ) ) { $v = reset( $v ); }
      if ( is_object( $v ) ) { return (int) $v->ID; }
      if ( $v ) { return (int) $v; }
    }
    return 0;
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
   ---------------------------------------------------------------------
   Le contenu peut vivre sur la page courante OU sur un post lié dont l'ID
   est mis en cache dans `mltv5_cache_id_criteres`. On lit donc la page
   courante en priorité, puis on bascule sur le post caché si vide.
   --------------------------------------------------------------------- */
$page_id   = get_the_ID();
$page_tv   = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';
$type_sing = isset( $page_tv['type_de_produit_au_singulier'] ) ? trim( (string) $page_tv['type_de_produit_au_singulier'] ) : '';

if ( ! function_exists( 'mt_guide_read' ) ) {
  /* Lit groupe + repeater sur un post donné -> tableau normalisé. */
  function mt_guide_read( $pid ) {
    $grp  = function_exists( 'get_field' ) ? get_field( 'mltv5_partie_criteres_de_choix', $pid ) : null;
    $grp  = is_array( $grp ) ? $grp : array();
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_criteres_de_choix', $pid ) : null;
    $rows = is_array( $rows ) ? $rows : array();
    return array(
      'intro' => isset( $grp['mltv5_introduction_criteres_de_choix'] ) ? $grp['mltv5_introduction_criteres_de_choix'] : '',
      'img'   => isset( $grp['mltv5_image_criteres_de_choix'] ) ? $grp['mltv5_image_criteres_de_choix'] : '',
      'rows'  => $rows,
    );
  }
}

$src_id = $page_id;
$data   = mt_guide_read( $src_id );
if ( empty( $data['rows'] ) && trim( (string) $data['intro'] ) === '' && empty( $data['img'] ) ) {
  $cached = mt_guide_cache_id( $page_id, 'criteres' );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_guide_read( $cached );
    if ( ! empty( $alt['rows'] ) || trim( (string) $alt['intro'] ) !== '' || ! empty( $alt['img'] ) ) {
      $src_id = $cached;
      $data   = $alt;
    }
  }
}

$crits = array();
foreach ( $data['rows'] as $r ) {
  $t = trim( (string) ( isset( $r['mltv5_critere_de_choix'] ) ? $r['mltv5_critere_de_choix'] : '' ) );
  $d = (string) ( isset( $r['mltv5_description_du_critere'] ) ? $r['mltv5_description_du_critere'] : '' );
  if ( $t === '' && trim( $d ) === '' ) { continue; }
  $crits[] = array( 'title' => $t, 'desc' => mt_guide_rich( $d ) );
}

$intro   = mt_guide_rich( $data['intro'] );
$img_raw = $data['img'];
$img_url = mt_guide_img_url( $img_raw );

/* Rien à afficher -> on ne sort rien (la section reste vide / masquée).
   Diagnostic visible UNIQUEMENT pour les éditeurs connectés (builder/preview),
   pour identifier d'où le contenu doit être lu. Invisible pour les visiteurs. */
if ( empty( $crits ) && $intro === '' && $img_url === '' ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $g = function_exists( 'get_field' ) ? get_field( 'mltv5_partie_criteres_de_choix', $page_id ) : null;
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_criteres_de_choix', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-guide — diagnostic (admin only)</strong> : aucun contenu trouvé.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'cache_id r&eacute;solu = ' . mt_guide_cache_id( $page_id, 'criteres' ) . ' (brut mltv5_cached_id_criteres : ' . gettype( get_field( 'mltv5_cached_id_criteres', $page_id ) ) . ')<br>'
       . 'groupe mltv5_partie_criteres_de_choix = ' . esc_html( gettype( $g ) )
       . ( is_array( $g ) ? ' [' . esc_html( implode( ', ', array_keys( $g ) ) ) . ']' : '' ) . '<br>'
       . 'repeater mltv5_criteres_de_choix = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' ) . '<br>'
       . 'ACF actif = ' . ( function_exists( 'get_field' ) ? 'oui' : 'NON' )
       . '</div>';
  }
  return;
}

$title       = 'Guide d&rsquo;achat' . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : '' );
$img_alt     = mt_guide_img_alt( $img_raw, 'Guide d\'achat ' . $type_plur );
$has_img     = ( $img_url !== '' );

/* Titre des critères : « votre/vos {type} ».
   `lalalesmeilleur` donne l'article+accord du type (« le meilleur » / « la
   meilleure » / « les meilleurs » / « les meilleures »). « les … » = le type
   est grammaticalement pluriel (croquettes, chaussures…) -> « vos », sinon
   « votre ». Repli si la variable manque : déduit du couple singulier/pluriel. */
$art       = isset( $page_tv['lalalesmeilleur'] ) ? strtolower( trim( wp_strip_all_tags( (string) $page_tv['lalalesmeilleur'] ) ) ) : '';
$noun      = $type_sing !== '' ? $type_sing : $type_plur;
$is_plural = ( $art !== '' ) ? ( strpos( $art, 'les ' ) === 0 ) : ( $type_sing === '' && $type_plur !== '' );
if ( $noun !== '' ) {
  $crit_title = 'Les crit&egrave;res pour bien choisir ' . ( $is_plural ? 'vos' : 'votre' ) . ' ' . esc_html( $noun );
} else {
  $crit_title = 'Les crit&egrave;res pour bien choisir';
}
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
  <h2 class="mt-guide-h2 mt-guide-h2--crit"><?php echo $crit_title; ?></h2>
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
