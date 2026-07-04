# /transition/

Versions mises à jour des templates, migrées de l'ancien fonctionnement
(« meilleurs produits de la **catégorie WordPress** ») vers le nouveau
(sélection calculée en amont à partir des **taxonomies** `post-type-produit`
+ `post-type-attribut`, puis stockée dans les **ACF** du guide d'achat).

## `notre-selection-template-1.php`

Refonte de « NOTRE SELECTION TEMPLATE 1 ».

### Ce qui change

- **Source des produits.** On n'utilise plus `mlt_get_meilleur_produit($position, …)`
  (calcul par catégorie). On lit la liste **ordonnée** d'IDs du guide, dans cet ordre :
  1. variable de template `top_avis_ids` ;
  2. repli : champ relation ACF `mltv5_best_products` ;
  3. repli **legacy** : `mlt_get_meilleur_produit()` (ancien calcul), conservé
     le temps que tous les guides soient migrés — à retirer une fois la
     transition terminée.

  C'est la même source que le nouveau template Top 5, donc les deux blocs
  restent cohérents (1 post = 1 produit).

### Ce qui ne change pas

- **Liens.** L'URL d'un produit suit la **même cascade que l'ancien helper** :
  ASIN Amazon (avec `$amazon_tag`) → liens perso `mltv5_lien_du_produit_1..3`
  → permalink en dernier recours. Chaque produit reste donc cliquable comme
  aujourd'hui. (Le `rel="sponsored"` sur un lien interne, hérité de l'ancienne
  version, pourra être nettoyé à une étape ultérieure de la migration.)

- **Sortie HTML et classes CSS identiques** (`#bloc-after-featured`,
  `#intro-list`, `.notre-selection-1-verdict`, etc.) → aucun impact visuel.
- Les deux options de config (`$afficher_type_produit_dans_verdict`,
  `$icone_lien`) et tout le traitement du verdict
  (`get_default_product_label` + raccourcis « Le meilleur » → « Meilleur »,
  retrait optionnel du type de produit) sont préservés.

### Installation

À coller dans un élément **CODE Bricks** (Execute code = ON), comme
l'ancienne version qu'il remplace.

## `tableau-comparaison.php`

Refonte du « Tableau de comparaison des produits » (style TechGearLab v2).

Ce template lisait déjà la sélection depuis les variables de template et
utilisait des helpers par-produit — il n'y avait donc pas de calcul par
catégorie à remplacer. La version transition se contente d'aligner la
**source des produits** et de **fiabiliser** le bloc.

### Ce qui change

- **Source des produits** — cascade alignée sur les autres templates
  transition, avec deux replis ajoutés :
  1. `top_avis_ids` (sélection taxonomique du guide — **source utilisée
     désormais**) ;
  2. `produits_comparatif` (repli : ancien override éditorial du comparatif) ;
  3. **[ajout]** `mltv5_best_products` (champ relation ACF) ;
  4. **[ajout]** `mlt_get_meilleur_produit()` (repli legacy par catégorie,
     le temps de la transition).

  Les IDs sont ensuite dédupliqués et **validés** (posts existants) avant
  rendu — évite les colonnes vides sur un ID orphelin.

- **Helpers protégés** — `mlt_get_score_color_class`, `mlt_format_score` et
  `mlt_render_stars` sont désormais entourés de `function_exists`. Sans ça,
  deux comparatifs sur la même page provoquaient un fatal « Cannot redeclare
  function ». Comportement inchangé quand ils ne sont définis qu'une fois.

- **Tag Amazon.** Aligné sur `mlt00-21` (ligne « Meilleures offres »),
  comme `notre-selection-template-1.php`.

### Ce qui ne change pas

- **Rendu HTML, classes CSS, offres, toggle JS, options de config** : à
  l'identique. Aucun impact visuel.

### Installation

À coller dans un élément **CODE Bricks** (Execute code = ON), comme
l'ancienne version qu'il remplace.
