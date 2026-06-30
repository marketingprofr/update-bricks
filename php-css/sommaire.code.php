<?php
/* =====================================================================
   MEILLEURTEST — Sommaire « Sur cette page » + encart « Lecture »
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (sommaire.css).

   ⚠️ Pas de logique serveur ici : le sommaire est 100 % dynamique côté
   client (lecture du vrai DOM Bricks). Ce fichier = markup + JS.
   Approche B du brief : la liste est générée à partir des <h2> du contenu.
   ===================================================================== */
?>
<aside class="mt-toc" data-mt-toc>
  <h4>Sur cette page</h4>
  <ul></ul><!-- rempli depuis les H2 du contenu -->
  <div class="mt-toc-progress">
    <span>Lecture</span>
    <div class="mt-toc-progress-bar"><div></div></div>
    <span><span class="mt-toc-cur">0</span> min sur <span class="mt-toc-total">0</span></span>
  </div>
</aside>

<script>
(function () {
  /* ----------------------------------------------------------------
     CONFIG — à adapter au DOM réel de la page Bricks
     ---------------------------------------------------------------- */
  var CONTENT_SELECTOR = '.mt-content'; // 👉 conteneur des articles (colonne droite)
  var WPM           = 200;              // mots / minute (temps de lecture)
  var HEADER_OFFSET = 100;              // hauteur du header sticky (px) pour le scroll d'ancre

  function init(root) {
    if (root.dataset.mtTocInit) return;      // garde anti double-init
    root.dataset.mtTocInit = '1';

    var content = document.querySelector(CONTENT_SELECTOR)
               || document.querySelector('main')
               || document.querySelector('article');
    var list = root.querySelector('ul');
    if (!content || !list) return;

    var headings = [].slice.call(content.querySelectorAll('h2'));
    if (!headings.length) { root.style.display = 'none'; return; }

    /* --- Sommaire à partir des H2 --- */
    list.innerHTML = '';
    headings.forEach(function (h, i) {
      if (!h.id) h.id = 'sec-' + i;
      h.style.scrollMarginTop = HEADER_OFFSET + 'px'; // ancre sous le header sticky
      var li = document.createElement('li');
      var a  = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent.trim();
      a.addEventListener('click', function (e) {
        e.preventDefault();
        h.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + h.id);
      });
      li.appendChild(a);
      list.appendChild(li);
    });
    var items = [].slice.call(list.querySelectorAll('li'));

    /* --- Temps de lecture total --- */
    var words    = (content.innerText || '').trim().split(/\s+/).filter(Boolean).length;
    var totalMin = Math.max(1, Math.round(words / WPM));
    var elTotal  = root.querySelector('.mt-toc-total');
    if (elTotal) elTotal.textContent = totalMin;

    /* --- Lien actif (scrollspy via IntersectionObserver) --- */
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (!e.isIntersecting) return;
          items.forEach(function (li) {
            var on = li.querySelector('a').getAttribute('href') === '#' + e.target.id;
            li.classList.toggle('active', on);
          });
        });
      }, { rootMargin: '-15% 0px -75% 0px' });
      headings.forEach(function (h) { io.observe(h); });
    }

    /* --- Barre de progression + minutes courantes --- */
    var bar   = root.querySelector('.mt-toc-progress-bar div');
    var elCur = root.querySelector('.mt-toc-cur');
    var ticking = false;
    function update() {
      ticking = false;
      var rect   = content.getBoundingClientRect();
      var startY = window.scrollY + rect.top;
      var dist   = Math.max(1, content.offsetHeight - window.innerHeight);
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
    var roots = document.querySelectorAll('[data-mt-toc]');
    roots.forEach(init);
  }
  if (document.readyState !== 'loading') boot();
  else document.addEventListener('DOMContentLoaded', boot);
})();
</script>
