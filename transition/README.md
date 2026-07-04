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

- **Liens.** L'URL d'un produit suit : ASIN Amazon (avec `$amazon_tag`) →
  liens perso `mltv5_lien_du_produit_1..3` → *aucun lien*. Contrairement à
  l'ancien helper, on ne retombe plus sur le permalink comme « offre » : un
  lien interne ne doit pas porter `rel="sponsored"`. Sans offre, le titre
  s'affiche en texte simple (la flèche « aller-au-test » assure déjà la
  navigation vers le test).

### Ce qui ne change pas

- **Sortie HTML et classes CSS identiques** (`#bloc-after-featured`,
  `#intro-list`, `.notre-selection-1-verdict`, etc.) → aucun impact visuel.
- Les deux options de config (`$afficher_type_produit_dans_verdict`,
  `$icone_lien`) et tout le traitement du verdict
  (`get_default_product_label` + raccourcis « Le meilleur » → « Meilleur »,
  retrait optionnel du type de produit) sont préservés.

### Installation

À coller dans un élément **CODE Bricks** (Execute code = ON), comme
l'ancienne version qu'il remplace.
