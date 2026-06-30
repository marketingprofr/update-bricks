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

$page_id = get_the_ID();
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();

/* Type de produit (pour le libellé « Guide d'achat … ») */
$type_plur = isset( $page_tv['type_de_produit_au_pluriel'] ) ? trim( (string) $page_tv['type_de_produit_au_pluriel'] ) : '';

/* ---------------------------------------------------------------------
   CONFIG — sections du sommaire
   - label  : libellé affiché
   - anchor : slug d'ancre = `id` HTML à poser sur la section dans Bricks
   - show   : true (toujours) OU suffixe de cache (« criteres », « types »… ;
              présent => affiché, via le post lié `mltv5_cache_id_{suffixe}`).
   ⚠️ Les slugs d'ancre ci-dessous sont à confirmer / poser dans Bricks.
   --------------------------------------------------------------------- */
$guide_label = "Guide d&rsquo;achat" . ( $type_plur !== '' ? ' ' . esc_html( $type_plur ) : '' );

$sections_cfg = array(
  array( 'label' => 'Notre s&eacute;lection',     'anchor' => 'mt-top5-title',            'show' => true ),
  array( 'label' => 'Tests complets',             'anchor' => 'partie-tests-complets',    'show' => true ),
  array( 'label' => 'Tableau comparatif',         'anchor' => 'partie-tableau-comparatif','show' => true ),
  array( 'label' => $guide_label,                 'anchor' => 'partie-guide-achat',       'show' => 'criteres' ),
  array( 'label' => 'Quel type choisir&nbsp;?',   'anchor' => 'partie-types',             'show' => 'types' ),
  array( 'label' => 'Quelle marque choisir&nbsp;?','anchor' => 'partie-marques',          'show' => 'marques' ),
  array( 'label' => 'Astuces et conseils',        'anchor' => 'partie-astuces',           'show' => 'astuces' ),
  array( 'label' => 'Pourquoi acheter&nbsp;?',    'anchor' => 'partie-raisons',           'show' => 'raisons' ),
  array( 'label' => 'Questions fr&eacute;quentes','anchor' => 'partie-faq',               'show' => 'faq' ),
);

/* Résolution des sections présentes (un suffixe => présent si le post lié existe) */
$sections = array();
foreach ( $sections_cfg as $s ) {
  $present = ( $s['show'] === true ) || ( is_string( $s['show'] ) && mt_guide_cache_id( $page_id, $s['show'] ) > 0 );
  if ( $present ) { $sections[] = $s; }
}
if ( empty( $sections ) ) { return; }

/* ---------------------------------------------------------------------
   Temps de lecture estimé
   10 min pour la partie produit (Notre sélection + Tests complets + Tableau comparatif)
   + 2 min par section supplémentaire présente.
   --------------------------------------------------------------------- */
$T5_READ_BASE = 10; // partie produit
$T5_READ_PER  = 2;  // par section supplémentaire
$product_anchors = array( 'mt-top5-title', 'partie-tests-complets', 'partie-tableau-comparatif' );
$extra = 0;
foreach ( $sections as $s ) {
  if ( ! in_array( $s['anchor'], $product_anchors, true ) ) { $extra++; }
}
$reading_total = $T5_READ_BASE + $T5_READ_PER * $extra;

/* ---------------------------------------------------------------------
   Jalons de minutes cumulées (début de section) pour la jauge de lecture.
   - Notre sélection (1re section) = 0.
   - La partie produit s'étale de 0 à BASE (10) : on ne pose donc PAS de
     jalon sur Tests complets / Tableau comparatif (la rampe 0→10 les couvre
     au prorata de leur hauteur).
   - 1re section supplémentaire = BASE (10), puis +PER (2) à chacune.
   Émis en data-min ; la fin (= total) est gérée en JS via le bas du contenu.
   --------------------------------------------------------------------- */
$cum_opt = $T5_READ_BASE;
$first   = true;
foreach ( $sections as $k => $s ) {
  $is_product = in_array( $s['anchor'], $product_anchors, true );
  if ( $first ) {
    $sections[ $k ]['min'] = 0;
  } elseif ( ! $is_product ) {
    $sections[ $k ]['min'] = $cum_opt;
    $cum_opt += $T5_READ_PER;
  } else {
    $sections[ $k ]['min'] = null; // produit intérieur : pas de jalon
  }
  $first = false;
}
?>
<aside class="mt-toc" data-mt-toc>
  <h4>Sur cette page</h4>
  <ul>
<?php foreach ( $sections as $s ) : ?>
    <li><a href="#<?php echo esc_attr( $s['anchor'] ); ?>"<?php if ( isset( $s['min'] ) && $s['min'] !== null ) { echo ' data-min="' . esc_attr( $s['min'] ) . '"'; } ?>><?php echo $s['label']; ?></a></li>
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
  var HEADER_OFFSET    = 30;                   // marge au-dessus de l'ancre au scroll (px)

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

    /* Barre de progression + minutes courantes.
       Modèle pondéré par section : chaque jalon (data-min) = minutes cumulées
       au DÉBUT de sa section ; on interpole linéairement entre deux jalons
       selon la position de scroll. Ainsi « 10 min » tombe pile au début de la
       1re section supplémentaire, quelle que soit la hauteur réelle en pixels. */
    var content = [].slice.call(document.querySelectorAll(CONTENT_SELECTOR));
    var bar     = root.querySelector('.mt-toc-progress-bar div');
    var elCur   = root.querySelector('.mt-toc-cur');
    var elTotal = root.querySelector('.mt-toc-total');
    var totalMin = parseInt(elTotal && elTotal.textContent, 10) || 0;

    /* Jalons issus de data-min (résolus en position absolue à chaque update) */
    var milestones = targets
      .filter(function (t) { return t.el && t.a.hasAttribute('data-min'); })
      .map(function (t) { return { el: t.el, min: parseFloat(t.a.getAttribute('data-min')) || 0 }; });

    function absTop(el) { return window.scrollY + el.getBoundingClientRect().top; }

    function buildBps() {
      var bps = milestones.map(function (m) { return { y: absTop(m.el), min: m.min }; });
      if (content.length) {
        var lastRect = content[content.length - 1].getBoundingClientRect();
        bps.push({ y: window.scrollY + lastRect.bottom - window.innerHeight, min: totalMin });
      }
      bps.sort(function (a, b) { return a.y - b.y; });
      return bps;
    }

    function minutesAt(y, bps) {
      if (y <= bps[0].y) { return bps[0].min; }
      for (var i = 1; i < bps.length; i++) {
        if (y <= bps[i].y) {
          var seg = bps[i].y - bps[i - 1].y;
          if (seg <= 0) { return bps[i].min; }
          var f = (y - bps[i - 1].y) / seg;
          return bps[i - 1].min + f * (bps[i].min - bps[i - 1].min);
        }
      }
      return bps[bps.length - 1].min;
    }

    var ticking = false;
    function update() {
      ticking = false;
      var bps = buildBps();
      var cur, p;
      if (bps.length >= 2) {
        cur = minutesAt(window.scrollY, bps);          // jalons pondérés
        p   = totalMin ? cur / totalMin : 0;
      } else {                                          // repli : scroll plein page
        var docDist = document.documentElement.scrollHeight - window.innerHeight;
        p   = docDist > 0 ? window.scrollY / docDist : 0;
        cur = p * totalMin;
      }
      p = Math.min(1, Math.max(0, p));
      if (bar)   bar.style.width = (p * 100).toFixed(1) + '%';
      if (elCur) elCur.textContent = Math.min(totalMin, Math.max(0, Math.round(cur)));
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
