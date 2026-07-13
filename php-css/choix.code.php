<?php
/* =====================================================================
   MEILLEURTEST — « Quel choix faire ? » (duels entre 2 alternatives)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   CSS dans l'onglet CSS du même élément (choix.css).

   Source des données (ACF) — REPEATER mltv5_choix_comparatif (1 ligne = 1 duel) :
       . mltv5_choix_1_ou_choix_2          (titre du duel, ex. « Gaz ou charbon ? »)
       . mltv5_introduction_choix_1_2       (intro — WYSIWYG)
       . mltv5_titre_choix_1                (titre option 1)
       . mltv5_description_choix_1          (description option 1 — WYSIWYG)
       . mltv5_titre_choix_2                (titre option 2)
       . mltv5_description_choix_2          (description option 2 — WYSIWYG)
       . mltv5_verdict_choix_1_ou_choix_2   (verdict — WYSIWYG)
   Lecture robuste : page courante → repli `mltv5_cached_id_choix` (le repeater
   des duels vit sur le post `type-de-produit`, comme les types ; `..._vs` = les
   VERSUS de produits, hors périmètre). Section -> `.contenu-principal`.
   ===================================================================== */

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
  function mt_guide_rich( $html ) {
    $html = (string) $html;
    if ( trim( $html ) === '' ) { return ''; }
    if ( ! preg_match( '/<(p|ul|ol|h[1-6]|blockquote|div|table|figure)\b/i', $html ) ) {
      $html = wpautop( $html );
    }
    return $html;
  }
}
if ( ! function_exists( 'mt_choix_read' ) ) {
  /* `mltv5_choix_comparatif` peut être un GROUPE (1 duel = tableau associatif de
     sous-champs) OU un REPEATER (liste de duels). On normalise vers une liste. */
  function mt_choix_read( $pid ) {
    $v = function_exists( 'get_field' ) ? get_field( 'mltv5_choix_comparatif', $pid ) : null;
    if ( ! is_array( $v ) || empty( $v ) ) { return array(); }
    $sub = array( 'mltv5_choix_1_ou_choix_2', 'mltv5_titre_choix_1', 'mltv5_titre_choix_2', 'mltv5_verdict_choix_1_ou_choix_2', 'mltv5_introduction_choix_1_2' );
    foreach ( $sub as $k ) {
      if ( array_key_exists( $k, $v ) ) { return array( $v ); } // groupe -> 1 duel
    }
    return $v; // repeater -> liste de duels
  }
}

$page_id = get_the_ID();
if ( ! $page_id && function_exists( 'get_queried_object_id' ) ) { $page_id = (int) get_queried_object_id(); }
if ( ! $page_id ) { return; }   // pas de contexte de post (builder / archive) -> on ne rend rien

$src_id = $page_id;
$rows   = mt_choix_read( $src_id );
if ( empty( $rows ) ) {
  $cached = mt_guide_cache_id( $page_id, 'choix' );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_choix_read( $cached );
    if ( ! empty( $alt ) ) { $src_id = $cached; $rows = $alt; }
  }
}

$duels = array();
foreach ( $rows as $r ) {
  $title = trim( (string) ( isset( $r['mltv5_choix_1_ou_choix_2'] ) ? $r['mltv5_choix_1_ou_choix_2'] : '' ) );
  $intro = mt_guide_rich( isset( $r['mltv5_introduction_choix_1_2'] ) ? $r['mltv5_introduction_choix_1_2'] : '' );
  $t1 = trim( (string) ( isset( $r['mltv5_titre_choix_1'] ) ? $r['mltv5_titre_choix_1'] : '' ) );
  $d1 = mt_guide_rich( isset( $r['mltv5_description_choix_1'] ) ? $r['mltv5_description_choix_1'] : '' );
  $t2 = trim( (string) ( isset( $r['mltv5_titre_choix_2'] ) ? $r['mltv5_titre_choix_2'] : '' ) );
  $d2 = mt_guide_rich( isset( $r['mltv5_description_choix_2'] ) ? $r['mltv5_description_choix_2'] : '' );
  $verdict = mt_guide_rich( isset( $r['mltv5_verdict_choix_1_ou_choix_2'] ) ? $r['mltv5_verdict_choix_1_ou_choix_2'] : '' );
  if ( $title === '' && $intro === '' && $t1 === '' && $d1 === '' && $t2 === '' && $d2 === '' && $verdict === '' ) { continue; }
  $duels[] = compact( 'title', 'intro', 't1', 'd1', 't2', 'd2', 'verdict' );
}

/* Rien à afficher -> diagnostic admin (builder), invisible pour les visiteurs. */
if ( empty( $duels ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $cached_id = mt_guide_cache_id( $page_id, 'choix' );
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_choix_comparatif', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-choix — diagnostic (admin only)</strong> : aucun duel trouv&eacute;.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'mltv5_cached_id_choix = ' . (int) $cached_id . '<br>'
       . 'repeater mltv5_choix_comparatif = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' );
    if ( $cached_id && $cached_id !== $page_id ) {
      $crr = function_exists( 'get_field' ) ? get_field( 'mltv5_choix_comparatif', $cached_id ) : null;
      echo '<br><b>--- post li&eacute; ' . (int) $cached_id . ' (type=' . esc_html( (string) get_post_type( $cached_id ) ) . ', status=' . esc_html( (string) get_post_status( $cached_id ) ) . ') ---</b><br>'
         . 'get_field repeater = ' . esc_html( gettype( $crr ) ) . ( is_array( $crr ) ? ' (count=' . count( $crr ) . ')' : '' );
      if ( is_array( $crr ) && ! empty( $crr ) ) {
        echo '<br>row[0] keys = [' . esc_html( implode( ', ', array_keys( $crr[0] ) ) ) . ']';
      }
    }
    echo '</div>';
  }
  return;
}
?>
<section class="mt-choix contenu-principal" id="partie-choix" aria-label="Quel choix faire">
  <div class="mt-choix-list">
    <?php foreach ( $duels as $d ) :
      $has1 = ( $d['t1'] !== '' || $d['d1'] !== '' );
      $has2 = ( $d['t2'] !== '' || $d['d2'] !== '' );
      $both = ( $has1 && $has2 );
    ?>
    <article class="mt-duel">
      <?php if ( $d['title'] !== '' ) : ?><h2 class="mt-duel-title"><?php echo esc_html( $d['title'] ); ?></h2><?php endif; ?>
      <?php if ( $d['intro'] !== '' ) : ?><div class="mt-duel-intro"><?php echo $d['intro']; ?></div><?php endif; ?>

      <?php if ( $has1 || $has2 ) : ?>
      <div class="mt-duel-grid<?php echo $both ? '' : ' single'; ?>">
        <?php if ( $has1 ) : ?>
        <div class="mt-duel-col">
          <?php if ( $d['t1'] !== '' ) : ?><h4 class="mt-duel-ch"><?php echo esc_html( $d['t1'] ); ?></h4><?php endif; ?>
          <?php if ( $d['d1'] !== '' ) : ?><div class="mt-duel-d"><?php echo $d['d1']; ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ( $both ) : ?><div class="mt-duel-vs" aria-hidden="true"><span>VS</span></div><?php endif; ?>
        <?php if ( $has2 ) : ?>
        <div class="mt-duel-col">
          <?php if ( $d['t2'] !== '' ) : ?><h4 class="mt-duel-ch"><?php echo esc_html( $d['t2'] ); ?></h4><?php endif; ?>
          <?php if ( $d['d2'] !== '' ) : ?><div class="mt-duel-d"><?php echo $d['d2']; ?></div><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ( $d['verdict'] !== '' ) : ?>
      <div class="mt-duel-verdict">
        <p class="mt-duel-verdict-lbl">Notre verdict</p>
        <div class="mt-duel-verdict-body"><?php echo $d['verdict']; ?></div>
      </div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
</section>
