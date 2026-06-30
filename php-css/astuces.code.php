<?php
/* =====================================================================
   MEILLEURTEST — « Astuces et conseils »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (astuces.css).

   Source des données (ACF) :
   - mltv5_introduction_astuces            (intro — fallback ci-dessous)
   - REPEATER mltv5_astuces_comparatif     (1 ligne = 1 astuce)
       . mltv5_titre_de_lastuce            (titre)
       . mltv5_contenu_de_lastuce           (contenu — WYSIWYG)
   Le contenu peut vivre sur la page courante OU sur le post lié dont l'ID est
   mis en cache dans `mltv5_cache_id_astuces` -> lecture robuste avec repli.

   UI : 1re astuce en vedette (« Conseil principal ») + suivantes en accordéon
   natif <details> (titre + contenu déroulant ; ligne simple si pas de contenu).
   Section de texte -> `.contenu-principal` (jauge de lecture) + ancre
   `partie-astuces` (lien du sommaire / scrollspy).
   ===================================================================== */

if ( ! function_exists( 'mt_guide_cache_id' ) ) {
  /* Résout l'ID du post lié mis en cache : essaie `mltv5_cache_id_{suffix}`
     puis `mltv5_cached_id_{suffix}` (ancien nom) ; accepte un ID ou un objet post. */
  function mt_guide_cache_id( $page_id, $suffix ) {
    foreach ( array( 'mltv5_cache_id_' . $suffix, 'mltv5_cached_id_' . $suffix ) as $f ) {
      $v = function_exists( 'get_field' ) ? get_field( $f, $page_id ) : null;
      if ( $v ) { return (int) ( is_object( $v ) ? $v->ID : $v ); }
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

if ( ! function_exists( 'mt_astuces_read' ) ) {
  function mt_astuces_read( $pid ) {
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_astuces_comparatif', $pid ) : null;
    return is_array( $rows ) ? $rows : array();
  }
}

$page_id = get_the_ID();

$src_id = $page_id;
$rows   = mt_astuces_read( $src_id );
if ( empty( $rows ) ) {
  $cached = mt_guide_cache_id( $page_id, 'astuces' );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_astuces_read( $cached );
    if ( ! empty( $alt ) ) { $src_id = $cached; $rows = $alt; }
  }
}

$tips = array();
foreach ( $rows as $r ) {
  $title = trim( (string) ( isset( $r['mltv5_titre_de_lastuce'] ) ? $r['mltv5_titre_de_lastuce'] : '' ) );
  $body  = (string) ( isset( $r['mltv5_contenu_de_lastuce'] ) ? $r['mltv5_contenu_de_lastuce'] : '' );
  if ( $title === '' && trim( $body ) === '' ) { continue; }
  $tips[] = array( 'title' => $title, 'body' => mt_guide_rich( $body ) );
}

/* Rien à afficher -> diagnostic admin (builder), invisible pour les visiteurs. */
if ( empty( $tips ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_astuces_comparatif', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-astuces — diagnostic (admin only)</strong> : aucune astuce trouvée.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'cache_id r&eacute;solu = ' . mt_guide_cache_id( $page_id, 'astuces' ) . '<br>'
       . 'repeater mltv5_astuces_comparatif = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' )
       . '</div>';
  }
  return;
}

/* Intro : champ + fallback, puis phrase d'appel à la communauté. */
$intro_field = trim( wp_strip_all_tags( (string) get_field( 'mltv5_introduction_astuces', $src_id ) ) );
if ( $intro_field === '' ) {
  $intro_field = 'D&eacute;couvrez nos astuces et conseils pour mieux utiliser vos produits.';
} else {
  $intro_field = esc_html( $intro_field );
}
$intro_html = $intro_field . ' Vous connaissez une astuce int&eacute;ressante&nbsp;? Partagez-la avec la communaut&eacute;.';
?>
<section class="mt-astuces contenu-principal" id="partie-astuces" aria-labelledby="mt-astuces-title">
  <h2 class="mt-astuces-h2" id="mt-astuces-title">Astuces et conseils</h2>
  <p class="mt-astuces-intro"><?php echo $intro_html; ?></p>

  <div class="mt-tips">
    <?php foreach ( $tips as $i => $t ) : ?>
      <?php if ( $i === 0 ) : ?>
        <article class="mt-tip featured">
          <p class="mt-tip-kicker">Conseil principal</p>
          <?php if ( $t['title'] !== '' ) : ?><h3 class="mt-tip-h"><?php echo esc_html( $t['title'] ); ?></h3><?php endif; ?>
          <?php if ( $t['body'] !== '' ) : ?><div class="mt-tip-body"><?php echo $t['body']; ?></div><?php endif; ?>
        </article>
      <?php elseif ( $t['body'] !== '' ) : ?>
        <details class="mt-tip">
          <summary>
            <span class="mt-tip-num"><?php echo str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ); ?></span>
            <span class="mt-tip-title"><?php echo esc_html( $t['title'] ); ?></span>
            <span class="mt-tip-arrow" aria-hidden="true">&rarr;</span>
          </summary>
          <div class="mt-tip-body"><?php echo $t['body']; ?></div>
        </details>
      <?php else : ?>
        <div class="mt-tip static">
          <span class="mt-tip-num"><?php echo str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ); ?></span>
          <span class="mt-tip-title"><?php echo esc_html( $t['title'] ); ?></span>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</section>
