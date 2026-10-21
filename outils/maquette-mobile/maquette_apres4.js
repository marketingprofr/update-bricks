heroCss => {
  const st = document.createElement('style');
  st.textContent = heroCss + `
  .mq-sel-titre { margin: 4px 0 10px; font: 700 22px/1.25 Inter, system-ui, sans-serif; color: #14181d; }
  .mq-sel { list-style: none; margin: 0 0 22px; padding: 0; }
  .mq-sel li { position: relative; padding: 5px 0 5px 18px; font: 16px/1.5 Inter, system-ui, sans-serif; color: #14181d; }
  .mq-sel .puce { position: absolute; left: 0; top: 50%; width: 12px; height: 24px; margin-top: -12px; display: flex; align-items: center; }
  .mq-sel .puce::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: #1a73c9; }
  .mq-sel .offre { color: #1a73c9; text-decoration: none; }
  .mq-sel .offre:hover span, .mq-sel .offre:hover strong { text-decoration: underline; }
  .mq-sel .offre svg { width: 17px; height: 17px; vertical-align: -3px; margin-right: 6px; }
  .mq-sel .offre strong { font-weight: 700; }
  .mq-sel .offre span { font-weight: 400; color: #14181d; }
  .mq-lignes { list-style: none; margin: 4px 0 0; padding: 0; border-top: 1px solid #e3e6ea; }
  .mq-ligne { display: grid; grid-template-columns: 30px 1fr; gap: 4px 10px; padding: 11px 4px; border-bottom: 1px solid #e3e6ea; font: 14px/1.4 Inter, system-ui, sans-serif; color: #2b3138; }
  .mq-ligne .r { grid-row: 1 / 4; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; background: #eef4fb; color: #1a73c9; font-size: 12px; font-weight: 700; }
  .mq-ligne b { color: #14181d; font-weight: 600; }
  .mq-ligne .v { color: #5b6470; font-size: 13px; }
  .mq-ligne .act { display: flex; align-items: center; gap: 14px; }
  .mq-ligne .prix { display: inline-flex; padding: 5px 11px; border-radius: 8px; background: #1a73c9; color: #fff; font-size: 12.5px; font-weight: 700; text-decoration: none; white-space: nowrap; }
  .mq-ligne .avis { color: #1a73c9; font-weight: 600; font-size: 13px; text-decoration: none; white-space: nowrap; }
  .mq-deja { font-size: 12px; color: #8a929c; }
  #mq-som { position: fixed; top: 0; left: 0; right: 0; z-index: 99999; background: #fff; border-bottom: 1px solid #dfe3e8; box-shadow: 0 2px 8px rgba(20,24,29,.08); font: 14px/1.3 Inter, system-ui, sans-serif; transform: translateY(-110%); }
  #mq-som.vu { transform: none; }
  #mq-som .barre { display: flex; align-items: center; gap: 10px; padding: 11px 16px; }
  #mq-som .barre b { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #8a929c; }
  #mq-som .barre span { flex: 1; font-weight: 600; color: #14181d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  #mq-som .barre i { font-style: normal; color: #1a73c9; font-weight: 700; }
  #mq-som ol { display: none; margin: 0; padding: 4px 16px 12px 36px; max-height: 60vh; overflow: auto; }
  #mq-som.ouvert ol { display: block; }
  #mq-som li { padding: 7px 0; border-top: 1px solid #f0f2f5; }
  #mq-som li a { color: #14181d; text-decoration: none; }
  #mq-som li.cur a { color: #1a73c9; font-weight: 700; }`;
  document.body.appendChild(st);

  // Prix et offres lus dans les données structurées de la page (Product → offers)
  const prix = {};
  document.querySelectorAll('script[type="application/ld+json"]').forEach(s => {
    let d; try { d = JSON.parse(s.textContent); } catch (e) { return; }
    const walk = o => { if (Array.isArray(o)) { o.forEach(walk); return; } if (!o || typeof o !== 'object') return;
      if (o['@type'] === 'Product' && o.name && o.offers) { const of = Array.isArray(o.offers) ? o.offers[0] : o.offers; const p = of.price || of.lowPrice; if (p) prix[o.name.trim()] = Math.round(parseFloat(p)); }
      Object.values(o).forEach(walk); };
    walk(d);
  });
  const euros = n => prix[n] ? prix[n].toLocaleString('fr-FR') + ' €' : '';

  const top = [...document.querySelector('#mt-top5-title').closest('.mt-top5').querySelectorAll('.t5-list > li')];
  const nom = li => li.querySelector('.t5-name').textContent.trim();
  const verdict = li => (li.dataset.verdict || ((li.querySelector('.t5-label') || {}).textContent) || '').trim();
  const note = li => { const n = li.querySelector('.t5-ed .n'); return n ? n.childNodes[0].textContent.trim() : ''; };
  const avis = li => { const a = li.querySelector('.t5-readmore'); return a ? a.getAttribute('href') : '#'; };
  const offre = li => { const a = li.querySelector('.t5-cta'); return a ? a.getAttribute('href') : ''; };
  const marchand = li => { const a = li.querySelector('.t5-cta'); return a ? a.textContent.replace(/^\s*Voir sur\s*/, '').replace(/[→\s]+$/, '').trim() : ''; };
  const boutonPrix = (li, cls) => offre(li) ? '<a class="' + cls + '" href="' + offre(li) + '" target="_blank" rel="nofollow sponsored noopener">Voir le prix' + (euros(nom(li)) ? ' <small>' + euros(nom(li)) + '</small>' : '') + '</a>' : '';

  // 1. Haut de page : phrase-réponse, questions et ancienne liste retirées ; « Notre sélection » avec liens vers l'offre
  document.querySelectorAll('.mt-lede-reponse, .mt-vq, .mt-faq-mini, .mt-picks-intro, .mt-picks').forEach(e => e.remove());
  const panier = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.6l2.3 11.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.2L21 8H6"/></svg>';
  const sel = '<p class="mq-sel-titre">Notre sélection</p><ul class="mq-sel">' + top.map(li =>
    '<li><a class="puce" href="' + avis(li) + '" aria-label="Voir notre avis"></a><a class="offre" href="' + offre(li) + '" target="_blank" rel="nofollow sponsored noopener">' + panier
    + '<strong>' + nom(li) + '</strong>' + (euros(nom(li)) ? '<span> à ' + euros(nom(li)) + '</span>' : '') + '</a></li>').join('') + '</ul>';
  const lede = document.querySelector('.mt-lede');
  lede.insertAdjacentHTML('beforebegin', sel);   // la liste avant l'intro (repliée sur mobile)

  // 2. Sur mobile : encadré de confiance (avec sa photo) et encadré ADEME sous le top 5
  const finTop5 = document.querySelector('#mt-top5-title').closest('.mt-top5');
  const vig = document.querySelector('.mt-vigilance');
  const conf = document.querySelector('.mt-card');
  const confBloc = conf ? (conf.closest('.brxe-code') || conf) : null;
  if (vig) finTop5.after(vig);
  if (confBloc) finTop5.after(confBloc);

  // 3. Sections par usage : le n°1 en carte (s'il n'est pas déjà présenté), les autres en lignes avec « Voir le prix »
  const detailles = new Set(top.map(nom));
  document.querySelectorAll('section.mtv2-sub').forEach(sec => {
    const ol = sec.querySelector('.t5-list');
    const items = [...ol.querySelectorAll(':scope > li')];
    const ul = document.createElement('ul');
    ul.className = 'mq-lignes';
    items.forEach((li, i) => {
      const n = nom(li);
      if (i === 0 && !detailles.has(n)) { detailles.add(n); return; }
      const deja = detailles.has(n) ? ' <span class="mq-deja">· présenté plus haut</span>' : '';
      const ligne = document.createElement('li');
      ligne.className = 'mq-ligne';
      ligne.innerHTML = '<span class="r">' + (i + 1) + '</span><div><b>' + n + '</b>' + deja + '</div>'
        + '<span class="v">' + (i === 0 ? 'N°1 de cette sélection' : verdict(li)) + (note(li) ? ' · ' + note(li) + '/10' : '') + '</span>'
        + '<div class="act">' + boutonPrix(li, 'prix') + '<a class="avis" href="' + avis(li) + '">➔ Voir son avis</a></div>';
      ul.appendChild(ligne);
      li.remove();
    });
    ol.after(ul);
    if (!ol.querySelector('li')) ol.remove();
    sec.querySelectorAll('.t5-bar, .t5-range').forEach(e => e.remove());
  });

  // 4. Sommaire collant
  const parts = [['Notre sélection', '.mq-sel-titre'], ['Top 5', '#mt-top5-title']]
    .concat([...document.querySelectorAll('section.mtv2-sub')].map(s => [s.querySelector('h2').textContent.replace(/^Les meilleurs climatiseurs mobiles /, '').replace(/^./, c => c.toUpperCase()), '#' + s.id]))
    .concat([['Avis détaillés', '#partie-tests-complets'], ['Tableau comparatif', '#partie-tableau-comparatif'], ["Guide d'achat", '#mt-guide-title'], ['Questions fréquentes', '#mt-faq-title']]);
  const som = document.createElement('nav');
  som.id = 'mq-som';
  som.innerHTML = '<div class="barre"><b>Sommaire</b><span>Top 5</span><i>▾</i></div><ol>' + parts.map(p => '<li><a href="' + p[1] + '">' + p[0] + '</a></li>').join('') + '</ol>';
  document.body.appendChild(som);
  window.__mqMaj = () => {
    const y = scrollY + 160;
    let cur = 0;
    parts.forEach((p, i) => { const e = document.querySelector(p[1].charAt(0) === '#' ? '#' + CSS.escape(p[1].slice(1)) : p[1]); if (e && e.getBoundingClientRect().top + scrollY <= y) cur = i; });
    som.querySelector('.barre span').textContent = parts[cur][0];
    som.querySelectorAll('li').forEach((l, i) => l.classList.toggle('cur', i === cur));
    som.classList.toggle('vu', scrollY > 300);
  };
  return Object.keys(prix).length;
}
