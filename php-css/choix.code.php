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
   Lecture robuste : page courante → repli `mltv5_cached_id_types` (même source
   que les types de produit). Section -> `.contenu-principal` (jauge de lecture).
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
if ( ! function_exists( 'mt_choix_read' ) ) {
  function mt_choix_read( $pid ) {
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_choix_comparatif', $pid ) : null;
    return is_array( $rows ) ? $rows : array();
  }
}

$page_id = get_the_ID();

$src_id = $page_id;
$rows   = mt_choix_read( $src_id );
if ( empty( $rows ) ) {
  $cached = (int) get_field( 'mltv5_cached_id_types', $page_id );
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
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_choix_comparatif', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-choix — diagnostic (admin only)</strong> : aucun duel trouv&eacute;.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'mltv5_cached_id_types = ' . esc_html( (string) get_field( 'mltv5_cached_id_types', $page_id ) ) . '<br>'
       . 'repeater mltv5_choix_comparatif = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' )
       . '</div>';
  }
  return;
}
?>
<section class="mt-choix contenu-principal" id="partie-choix" aria-labelledby="mt-choix-title">
  <h2 class="mt-choix-h2" id="mt-choix-title">Quel choix faire&nbsp;?</h2>

  <div class="mt-choix-list">
    <?php foreach ( $duels as $d ) :
      $has1 = ( $d['t1'] !== '' || $d['d1'] !== '' );
      $has2 = ( $d['t2'] !== '' || $d['d2'] !== '' );
      $both = ( $has1 && $has2 );
    ?>
    <article class="mt-duel">
      <?php if ( $d['title'] !== '' ) : ?><h3 class="mt-duel-title"><?php echo esc_html( $d['title'] ); ?></h3><?php endif; ?>
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
