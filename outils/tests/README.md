# Banc d'essai des blocs Code (faux WordPress)

Ce dossier simule juste assez WordPress pour exécuter les blocs Code (`php-css/…code.php`) **hors du site**, avec un jeu de données fictif.

## À quoi ça sert (et à quoi ça ne sert pas)

**Ça vérifie la logique PHP et le HTML produit**, avant de coller un bloc dans Bricks :
- pas d'erreur PHP ;
- HTML bien formé, sans `id` en double ;
- toutes les ancres `#…` ont une cible ;
- JSON-LD valide (Product, ItemList, références `@id`) ;
- titres H1, H2 et title SEO générés (accords, compteurs) ;
- dédoublonnage des tests par ID et par ASIN ;
- sommaire, banderoles, pastilles « Sélection … » ;
- non-régression V1 / V2.

**Ça ne remplace pas une vérification sur le vrai site.** Les données sont fictives, et le rendu Bricks, le CSS compilé, les plugins (Rank Math, Rate My Post), le cache et la vraie fonction `get_all_template_variables` ne sont pas simulés. Après chaque collage, contrôler une page réelle fraîche (`?nocache=<horodatage>`).

## Prérequis

- PHP 8 en ligne de commande, avec l'extension DOM (`php -m` doit lister `dom`).
- Facultatif : Node + Playwright, pour les captures d'écran (`shot.js`).

## Jeu de données (`wp-stubs.php`)

- **Comparatif parent : ID 100** « climatiseur mobile », avec 5 produits (avis 1-5).
- **Sous-comparatifs** listés dans `mltv5_sous_comparatifs` du parent :
  - 101 : 9000 BTU, **privé**, attribut « réversible » ;
  - 102 : 12000 BTU ;
  - 103 : **brouillon**, doit être ignoré ;
  - 104 : **autre type de produit**, doit déclencher un avertissement ;
  - 105 : **liste vide**.
- **14 fiches avis** (ID 1-14), avec ASIN, prix, specs et points +/-. **L'avis 12 a le même ASIN que l'avis 3**, pour tester le dédoublonnage par ASIN.
- **Variables d'environnement :**
  - `AS_ADMIN=1` simule un éditeur connecté (panneau jaune, liens d'édition) ;
  - `NAVIS=55` fait renvoyer 55 avis publiés par `WP_Query`, pour le compteur du hero.

## Scripts

Tous se lancent depuis ce dossier : `cd outils/tests`. Les sorties HTML vont dans `out/`, qui est ignoré par Git.

| Script | Ce qu'il teste |
|---|---|
| `php run-page.php` | Page multi-comparatif complète (sommaire, résumé, tests, tableau V2) + contrôles (ID, ancres, H2, sommaire, colonnes du tableau, banderoles, JSON-LD). `AS_ADMIN=1` pour la vue éditeur. |
| `php run-h1.php` | H1 et title SEO du hero gauche V2. Variantes : `NAVIS=55 php run-h1.php`, `NOSUB=1 php run-h1.php` (sans sous-comparatif, c'est-à-dire en V1). |
| `php run-compare.php` | V1 face à V2 **sans** sous-comparatif, bloc par bloc. Les fichiers vont dans `out/cmp-*.html`. |
| `NAVIS=16 php run-seo.php` | Title et méta description écrits par le hero gauche : accords (masculin, féminin, pluriel), titre forcé, repli quand N <= produits retenus (`NAVIS=4`), description saisie à la main gardée. |
| `php run-inventaire.php` | Outil d'inventaire `php-css/outils/inventaire-multi.code.php` sur un jeu de comparatifs fictif (principal, variantes, doublon, orphelin, sans type). |
| `php run-sommaire-apercu.php > out/sommaire.html` | Aperçu visuel du sommaire V2, avec les variables AT simulées et des styles « thème » parasites. |
| `node shot.js out/sommaire.html out/sommaire.png` | Capture Playwright de l'aperçu (360 px de large). |

**Écarts V1 / V2 normaux** (`run-compare.php`) :
- ancres `#test-{slug}` et `<span id="produit-n-X">` de compatibilité ;
- `decoding="async"` sur les images ;
- sommaire et titre du tableau entièrement revus ;
- barre de tri compacte ;
- lien « Comment nous évaluons ».

Toujours lire le diff avant de conclure à une régression.

## Ajouter un test

1. Charger `wp-stubs.php`.
2. Modifier les données si besoin (`$GLOBALS['TV'][id]`, `$GLOBALS['ACF'][id]`, `$GLOBALS['META'][id]`, `$GLOBALS['TERMS'][id]`).
3. Inclure le bloc avec `MT_REPO . '/php-css/…code.php'` dans un `ob_start()`.
4. Contrôler le HTML (DOMDocument / DOMXPath, comme dans `run-page.php`).

Si un bloc appelle une fonction WordPress qui n'est pas encore simulée, PHP s'arrête sur « Call to undefined function » : ajouter un stub minimal dans `wp-stubs.php`.
