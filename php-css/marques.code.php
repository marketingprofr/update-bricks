<?php
/* =====================================================================
   MEILLEURTEST — « Quelle marque choisir ? »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (marques.css).

   Source des données (ACF) :
   - REPEATER mltv5_marques_comparatif   (1 ligne = 1 marque)
       . mltv5_nom_de_la_marque          (nom)
       . mltv5_description_de_la_marque   (description — WYSIWYG)
   Le contenu peut vivre sur la page courante OU sur le post lié dont l'ID est
   mis en cache dans `mltv5_cache_id_marques` -> lecture robuste avec repli.

   UI : grille de marques cliquables (onglets) -> panneau détail (description).
   Section de texte -> porte `.contenu-principal` (jauge de lecture du sommaire)
   ET l'ancre `partie-marques` (lien du sommaire / scrollspy).
   ===================================================================== */

/* Contenu éditorial : WYSIWYG déjà formaté ; textarea -> wpautop. */
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

if ( ! function_exists( 'mt_marques_read' ) ) {
  /* Lit le repeater des marques sur un post donné -> tableau de lignes. */
  function mt_marques_read( $pid ) {
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_marques_comparatif', $pid ) : null;
    return is_array( $rows ) ? $rows : array();
  }
}

$page_id = get_the_ID();

$src_id = $page_id;
$rows   = mt_marques_read( $src_id );
if ( empty( $rows ) ) {
  $cached = mt_guide_cache_id( $page_id, 'marques' );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_marques_read( $cached );
    if ( ! empty( $alt ) ) { $src_id = $cached; $rows = $alt; }
  }
}

$brands = array();
foreach ( $rows as $r ) {
  $name = trim( (string) ( isset( $r['mltv5_nom_de_la_marque'] ) ? $r['mltv5_nom_de_la_marque'] : '' ) );
  $desc = (string) ( isset( $r['mltv5_description_de_la_marque'] ) ? $r['mltv5_description_de_la_marque'] : '' );
  if ( $name === '' && trim( $desc ) === '' ) { continue; }
  $brands[] = array( 'name' => $name, 'desc' => mt_guide_rich( $desc ) );
}

/* Rien à afficher -> diagnostic admin (builder), invisible pour les visiteurs. */
if ( empty( $brands ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_marques_comparatif', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-marques — diagnostic (admin only)</strong> : aucune marque trouvée.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'cache_id r&eacute;solu = ' . mt_guide_cache_id( $page_id, 'marques' ) . '<br>'
       . 'repeater mltv5_marques_comparatif = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' )
       . '</div>';
  }
  return;
}

$GLOBALS['mt_marques_uid'] = isset( $GLOBALS['mt_marques_uid'] ) ? $GLOBALS['mt_marques_uid'] + 1 : 1;
$uid = 'mq' . $GLOBALS['mt_marques_uid'];
?>
<section class="mt-marques contenu-principal" id="partie-marques" aria-labelledby="<?php echo esc_attr( $uid ); ?>-title">
  <h2 class="mt-marques-h2" id="<?php echo esc_attr( $uid ); ?>-title">Quelle marque choisir&nbsp;?</h2>

  <div class="mt-brands" role="tablist" aria-label="Marques">
    <?php foreach ( $brands as $i => $b ) : if ( $b['name'] === '' ) { continue; } ?>
    <button type="button" class="mt-brand<?php echo $i === 0 ? ' active' : ''; ?>" role="tab"
            id="<?php echo esc_attr( $uid ); ?>-tab-<?php echo (int) $i; ?>"
            aria-controls="<?php echo esc_attr( $uid ); ?>-panel-<?php echo (int) $i; ?>"
            aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
            tabindex="<?php echo $i === 0 ? '0' : '-1'; ?>"
            data-idx="<?php echo (int) $i; ?>">
      <span class="mt-brand-name"><?php echo esc_html( $b['name'] ); ?></span>
    </button>
    <?php endforeach; ?>
  </div>

  <div class="mt-brand-panels">
    <?php foreach ( $brands as $i => $b ) : ?>
    <div class="mt-brand-content" role="tabpanel"
         id="<?php echo esc_attr( $uid ); ?>-panel-<?php echo (int) $i; ?>"
         aria-labelledby="<?php echo esc_attr( $uid ); ?>-tab-<?php echo (int) $i; ?>"
         data-idx="<?php echo (int) $i; ?>"<?php echo $i === 0 ? '' : ' hidden'; ?>>
      <?php if ( $b['name'] !== '' ) : ?><h3><?php echo esc_html( $b['name'] ); ?></h3><?php endif; ?>
      <?php echo $b['desc']; ?>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<script>
(function () {
  document.querySelectorAll('.mt-marques').forEach(function (root) {
    if (root.dataset.mqInit) return;
    root.dataset.mqInit = '1';
    var tabs   = [].slice.call(root.querySelectorAll('.mt-brand'));
    var panels = [].slice.call(root.querySelectorAll('.mt-brand-content'));
    if (!tabs.length) return;

    function activate(idx, focus) {
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-idx') === idx;
        t.classList.toggle('active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        if (on && focus) t.focus();
      });
      panels.forEach(function (p) { p.hidden = (p.getAttribute('data-idx') !== idx); });
    }

    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { activate(t.getAttribute('data-idx')); });
      t.addEventListener('keydown', function (e) {
        var ni = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') ni = (i + 1) % tabs.length;
        else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') ni = (i - 1 + tabs.length) % tabs.length;
        else if (e.key === 'Home') ni = 0;
        else if (e.key === 'End') ni = tabs.length - 1;
        if (ni !== null) { e.preventDefault(); activate(tabs[ni].getAttribute('data-idx'), true); }
      });
    });
  });
})();
</script>
