<?php
/* =====================================================================
   MEILLEURTEST — Sommaire « Sur cette page » + encart « Lecture »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (sommaire.css).

   Le sommaire est une LISTE FIXE conditionnelle (pas un scan de H2) :
   chaque section apparaît selon la présence d'un champ ACF. Les ancres
   sont des slugs fixes à reporter en `id` HTML sur chaque section Bricks.
   Le scrollspy + la barre de progression sont gérés en JS (vrai scroll).
   ===================================================================== */

$page_id = get_the_ID();
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();

/* Type de produit (pour le libellé « Guide d'achat … ») */
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';

/* ---------------------------------------------------------------------
   CONFIG — sections du sommaire
   - label  : libellé affiché
   - anchor : slug d'ancre = `id` HTML à poser sur la section dans Bricks
   - show   : true (toujours) OU nom d'un champ ACF (présent => affiché)
   ⚠️ Les slugs d'ancre ci-dessous sont à confirmer / poser dans Bricks.
   --------------------------------------------------------------------- */
$guide_label = "Guide d&rsquo;achat" . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : '' );

$sections_cfg = array(
  array( 'label' => 'Notre s&eacute;lection',     'anchor' => 'mt-top5-title',      'show' => true ),
  array( 'label' => 'Tests complets',             'anchor' => 'tests-complets',     'show' => true ),
  array( 'label' => 'Tableau comparatif',         'anchor' => 'tableau-comparatif', 'show' => true ),
  array( 'label' => $guide_label,                 'anchor' => 'guide-achat',        'show' => 'mltv5_cached_id_criteres' ),
  array( 'label' => 'Quel type choisir&nbsp;?',   'anchor' => 'types',              'show' => 'mltv5_cached_id_types' ),
  array( 'label' => 'Quelle marque choisir&nbsp;?','anchor' => 'marques',           'show' => 'mltv5_cached_id_marques' ),
  array( 'label' => 'Astuces et conseils',        'anchor' => 'astuces',            'show' => 'mltv5_cached_id_astuces' ),
  array( 'label' => 'Pourquoi acheter&nbsp;?',    'anchor' => 'raisons',            'show' => 'mltv5_cached_id_raisons' ),
  array( 'label' => 'Questions fr&eacute;quentes','anchor' => 'faq',                'show' => 'mltv5_cached_id_faq' ),
);

/* Résolution des sections présentes */
$sections = array();
foreach ( $sections_cfg as $s ) {
  $present = ( $s['show'] === true ) || ( is_string( $s['show'] ) && trim( (string) get_field( $s['show'], $page_id ) ) !== '' );
  if ( $present ) { $sections[] = $s; }
}
if ( empty( $sections ) ) { return; }

/* ---------------------------------------------------------------------
   Temps de lecture estimé
   10 min pour la partie produit (Notre sélection + Tests complets)
   + 2 min par section supplémentaire présente.
   --------------------------------------------------------------------- */
$T5_READ_BASE = 10; // partie produit
$T5_READ_PER  = 2;  // par section supplémentaire
$product_anchors = array( 'mt-top5-title', 'tests-complets' );
$extra = 0;
foreach ( $sections as $s ) {
  if ( ! in_array( $s['anchor'], $product_anchors, true ) ) { $extra++; }
}
$reading_total = $T5_READ_BASE + $T5_READ_PER * $extra;
?>
<aside class="mt-toc" data-mt-toc>
  <h4>Sur cette page</h4>
  <ul>
<?php foreach ( $sections as $s ) : ?>
    <li><a href="#<?php echo esc_attr( $s['anchor'] ); ?>"><?php echo $s['label']; ?></a></li>
<?php endforeach; ?>
  </ul>
  <div class="mt-toc-progress">
    <span>Lecture</span>
    <div class="mt-toc-progress-bar"><div></div></div>
    <span><span class="mt-toc-cur">0</span> min sur <span class="mt-toc-total"><?php echo (int) $reading_total; ?></span></span>
  </div>
</aside>

<script>
(function () {
  /* ----------------------------------------------------------------
     CONFIG — à adapter au DOM réel de la page Bricks
     ---------------------------------------------------------------- */
  var CONTENT_SELECTOR = '.contenu-principal'; // 👉 colonnes du contenu (plusieurs autorisées)
  var HEADER_OFFSET    = 100;                  // hauteur du header sticky (px) pour le scroll d'ancre

  function init(root) {
    if (root.dataset.mtTocInit) return;        // garde anti double-init
    root.dataset.mtTocInit = '1';

    var links = [].slice.call(root.querySelectorAll('.mt-toc ul a, ul a'));
    if (!links.length) return;

    /* Cibles = éléments visés par les ancres du sommaire */
    var targets = links.map(function (a) {
      var id = (a.getAttribute('href') || '').replace(/^#/, '');
      var el = id ? document.getElementById(id) : null;
      if (el) el.style.scrollMarginTop = HEADER_OFFSET + 'px';
      return { id: id, el: el, li: a.parentNode, a: a };
    });

    /* Défilement doux au clic */
    targets.forEach(function (t) {
      t.a.addEventListener('click', function (e) {
        if (!t.el) return;
        e.preventDefault();
        t.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + t.id);
      });
    });

    /* Lien actif (scrollspy via IntersectionObserver) */
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          targets.forEach(function (t) {
            t.li.classList.toggle('active', t.el === e.target);
          });
        });
      }, { rootMargin: '-15% 0px -75% 0px' });
      targets.forEach(function (t) { if (t.el) io.observe(t.el); });
    }

    /* Barre de progression + minutes courantes (scroll réel du contenu) */
    var content = [].slice.call(document.querySelectorAll(CONTENT_SELECTOR));
    var bar     = root.querySelector('.mt-toc-progress-bar div');
    var elCur   = root.querySelector('.mt-toc-cur');
    var elTotal = root.querySelector('.mt-toc-total');
    var totalMin = parseInt(elTotal && elTotal.textContent, 10) || 0;
    if (!content.length) return;

    var ticking = false;
    function update() {
      ticking = false;
      var first  = content[0].getBoundingClientRect();
      var last   = content[content.length - 1].getBoundingClientRect();
      var startY = window.scrollY + first.top;
      var endY   = window.scrollY + last.bottom;
      var dist   = Math.max(1, (endY - window.innerHeight) - startY);
      var p      = Math.min(1, Math.max(0, (window.scrollY - startY) / dist));
      if (bar)   bar.style.width = (p * 100).toFixed(1) + '%';
      if (elCur) elCur.textContent = Math.min(totalMin, Math.max(0, Math.round(p * totalMin)));
    }
    function onScroll() {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(update);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    update();
  }

  function boot() {
    document.querySelectorAll('[data-mt-toc]').forEach(init);
  }
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
</script>
