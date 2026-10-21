() => {
  const yOf = e => Math.round(e.getBoundingClientRect().top + scrollY);
  const visibles = sel => [...document.querySelectorAll(sel)].filter(e => e.getBoundingClientRect().height > 0 && getComputedStyle(e).visibility !== 'hidden');
  const offres = visibles('a[rel*="sponsored"], a.t5-cta').filter(a => /^https?:/.test(a.getAttribute('href') || ''));
  const premiere = offres.length ? Math.min(...offres.map(yOf)) : -1;
  const ctas = visibles('a.t5-cta');
  const premierBouton = ctas.length ? Math.min(...ctas.map(yOf)) : -1;
  const t = document.querySelector('#partie-tests-complets');
  return { offre_px: premiere, offre_ecrans: +(premiere / 844).toFixed(1), bouton_top5_px: premierBouton, avis_px: yOf(t), avis_ecrans: +(yOf(t) / 844).toFixed(1),
           cartes: visibles('.mt-top5 .t5-list > li').length, liens_offre_avant_avis: offres.filter(a => yOf(a) < yOf(t)).length };
}
