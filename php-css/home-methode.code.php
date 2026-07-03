<?php
/* =====================================================================
   MEILLEURTEST — ACCUEIL · §6 COMMENT NOUS TESTONS (bande méthode)
   UN SEUL élément CODE Bricks : SECTION > CONTAINER > CODE. Contenu
   STATIQUE (aucune donnée) mais placé dans un bloc Code PHP (executeCode
   = ON conseillé) pour que les SVG s'affichent sans passer par la Code
   review d'un bloc HTML. CSS dans l'onglet CSS (home-methode.css).
   Scope : .mt-hm. Réf. maquette : templates/Home.html (.mt-method).

   ▸ FOND DE SECTION À RÉGLER DANS BRICKS : var(--at-black-l-1)  (bande
     sombre — le texte clair est géré par le CSS du bloc).
   ===================================================================== */
?>
<section class="mt-hm">
  <div class="mt-hm-head">
    <p class="eyebrow">Notre méthode</p>
    <h2>Comment nous testons</h2>
    <p>Une méthode identique pour chaque guide, de l'aspirateur au smartphone. Objectif : une recommandation que vous pouvez suivre les yeux fermés.</p>
  </div>

  <div class="mt-hm-grid">
    <div class="mt-step">
      <div class="mt-step-top">
        <span class="mt-step-num">01</span>
        <span class="mt-step-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
      </div>
      <h3>On enquête</h3>
      <p>Des dizaines d'heures de recherche, des sources croisées et les avis de milliers d'utilisateurs réels.</p>
    </div>
    <div class="mt-step">
      <div class="mt-step-top">
        <span class="mt-step-num">02</span>
        <span class="mt-step-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3h6"/><path d="M10 3v5.5L5.2 17A1.6 1.6 0 0 0 6.6 19.5h10.8A1.6 1.6 0 0 0 18.8 17L14 8.5V3"/><path d="M7.5 14h9"/></svg></span>
      </div>
      <h3>On compare</h3>
      <p>Chaque produit retenu est évalué sur des critères concrets — pas sur les fiches marketing des marques.</p>
    </div>
    <div class="mt-step">
      <div class="mt-step-top">
        <span class="mt-step-num">03</span>
        <span class="mt-step-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 20V9"/><path d="M12 20V5"/><path d="M18 20v-8"/></svg></span>
      </div>
      <h3>On classe</h3>
      <p>Un classement clair : le meilleur choix, le meilleur rapport qualité-prix, le meilleur pas cher.</p>
    </div>
    <div class="mt-step">
      <div class="mt-step-top">
        <span class="mt-step-num">04</span>
        <span class="mt-step-ico"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg></span>
      </div>
      <h3>On met à jour</h3>
      <p>Nos guides sont révisés dès qu'un nouveau modèle sort ou qu'un prix bouge sensiblement.</p>
    </div>
  </div>

  <div class="mt-hm-promise">
    <span class="p"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg> <b>0 %</b>&nbsp;de publicité</span>
    <span class="p"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg> <b>0 %</b>&nbsp;sponsorisé</span>
    <span class="p"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg> <b>100 %</b>&nbsp;indépendant</span>
    <span class="p"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg> Aucun cadeau des marques</span>
  </div>
</section>
