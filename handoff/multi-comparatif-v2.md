# Mission : créer un template « multi-comparatif » (V2) pour fusionner les guides similaires

Tu es l'instance chargée des templates de meilleurtest.fr. La mission tient en trois points :
- concevoir une V2 du template de comparatif, appelée « multi-comparatif » ;
- la V2 doit pouvoir afficher, sur une seule page, un comparatif principal suivi de plusieurs sous-comparatifs ;
- le template de comparatif actuel (V1) reste en place, intact : c'est notre solution de repli.

## Pourquoi

- **Le problème.** Le site compte environ 5 700 guides. D'après les URL, environ 2 100 d'entre eux (38 %) sont des variantes d'un guide parent : `climatiseur-mobile-9000-btu`, `radiateur-double-coeur-de-chauffe-2000w`, `smartphone-a-moins-de-300-euros`… Google traite ce type de pages quasi identiques comme de la production de contenu en masse (« scaled content »). Il répartit les signaux entre des pages faibles au lieu d'en renforcer une seule.
- **L'objectif.** Regrouper une famille (le guide parent et ses variantes) sur une seule URL forte, qui répond aussi aux requêtes des variantes grâce à des sections dédiées.
- **Ce que montrent les données Search Console** (mars-octobre 2025) :
  - **Climatiseur mobile.** Le parent se classe vers la 50e position (19 clics pour 5 800 impressions). Ses 5 variantes BTU sont faibles : 6 clics pour « 9000 BTU », 29 pour « 12000 BTU ». C'est le cas idéal de fusion.
  - **Radiateur double cœur de chauffe.** Le parent attire déjà les requêtes « 1500 W » et « 1000 W » (126 clics, position 8-9), sans page dédiée. En revanche, la variante « 2000 W » est la meilleure page de la famille (327 clics). Une fusion mal faite lui ferait perdre ce trafic : chaque section doit donc être aussi solide qu'une page dédiée.
  - **Les variantes attirent aussi des requêtes sur des produits précis** (« lennon 9000 btu », « glaziar predator sh+ 12000 », « noirot fusion 2 »). Les tests complets de ces produits doivent donc rester sur la page fusionnée.

## Ce que doit afficher la page multi-comparatif

Dans l'ordre :

1. **Introduction du guide principal**, comme en V1.
2. **Encart résumé du comparatif principal**, comme en V1.
3. **Un bloc par sous-comparatif**, dans l'ordre choisi par l'éditeur. Chaque bloc contient :
   - un titre H2 propre au sous-comparatif, qui reprend la requête visée. Exemple : « Les meilleurs climatiseurs mobiles 9000 BTU ».
   - une courte introduction de 2-3 phrases, facultative ;
   - l'encart résumé de ses meilleurs produits.
   - Le bloc porte une ancre stable, par exemple `#9000-btu`, pour le sommaire et les futures redirections.
4. **Les tests complets, une seule fois par produit.** Un produit peut apparaître dans plusieurs encarts : par exemple, le meilleur climatiseur mobile peut être aussi le meilleur en 9000 BTU. Son test complet n'apparaît qu'une fois, à la place de sa première apparition. Chaque test a une ancre, par exemple `#test-{slug-produit}`, et tous les encarts pointent vers cette même ancre.
5. **Le reste de la page**, comme en V1 : guide d'achat, FAQ, etc.

Le sommaire liste le comparatif principal, chaque sous-comparatif, les tests complets et les sections suivantes.

## Ordre des tests complets

Ton PHP doit construire une seule liste de produits, sans doublons, dans l'ordre de première apparition :

- d'abord les produits de l'encart principal, dans leur ordre ;
- puis ceux de chaque sous-comparatif, dans l'ordre d'affichage des blocs, en sautant ceux déjà vus.

Cette liste pilote l'affichage des tests complets.

Chaque encart garde son propre classement : un produit peut être 4e du classement général et 1er en 9000 BTU. La note affichée reste celle du produit, identique partout.

## Modèle de données

Sur le guide d'achat, on a déjà un champ pour le type de produit et un champ pour l'attribut principal. Ça ne suffit pas pour les sous-comparatifs.

- **Le type de produit.** Il ne change pas : un sous-comparatif reste toujours dans le type de produit du guide principal. Si un sous-comparatif pointe vers un autre type, affiche un avertissement dans l'admin.
- **Les champs de chaque sous-comparatif.** Probablement un répéteur ACF. Au minimum :
  - l'attribut (et sa valeur) qui filtre les produits ;
  - le titre du sous-comparatif, qu'on ne peut pas reconstruire automatiquement à partir de l'attribut ;
  - facultativement : l'introduction, le nombre de produits dans l'encart, et l'ancre si on veut la forcer.
- **Remplissage semi-automatique.** Pour une famille existante, les sous-guides existent déjà : `climatiseur-mobile-9000-btu`, etc. Évalue une option « guide source » : un champ qui pointe vers le guide existant, et dont on reprend automatiquement le titre, l'attribut, l'introduction (par exemple depuis le dossier `/content/intros/` du dépôt, s'il contient celles des sous-guides), voire le classement de produits s'il est stocké par guide. Les autres champs servent alors seulement à surcharger ces valeurs. Compare cette option avec une saisie manuelle, et recommande la meilleure au vu du code existant.
- **Sélection et classement des produits.** Réutilise la logique de la V1, avec l'attribut du sous-comparatif, plutôt que de la dupliquer.

## Exigences techniques

- **V1 intacte.** Ne modifie pas et ne supprime pas le template V1, ni ses fonctions et champs actuels. La V2 s'ajoute à côté.
- **Passage en V2 réversible.** Un guide passe en V2 seulement s'il a au moins un sous-comparatif et que la case « Activer le multi-comparatif » est cochée. Décocher la case le ramène en V1. Prévois aussi un aperçu réservé aux administrateurs (par exemple `?preview_v2=1`), pour tester sur le vrai guide pendant que les visiteurs voient encore la V1.
- **Même URL.** La page multi-comparatif est le guide parent, à son URL actuelle, pour garder son historique.
- **Données structurées.**
  - Chaque produit apparaît une seule fois comme entité `Product`, avec un `@id` stable.
  - Chaque encart (principal et sous-comparatifs) est une `ItemList` qui référence les produits par leur `@id`.
  - Respecte les choix déjà faits : `Offer` plutôt que `AggregateOffer` quand il n'y a qu'une offre ; `brand` renseigné si possible.
- **Titres.** Un seul H1. Un H2 par sous-comparatif et pour les tests complets. Garde la hiérarchie cohérente avec la V1.
- **Performance.** La page sera longue : garde des Core Web Vitals bons.
  - Mets en cache le calcul des listes (transient par guide, invalidé à chaque modification du guide ou d'un produit concerné).
  - Charge les images en différé (lazy-load).
  - Propose une limite raisonnable au nombre de tests complets.

## Hors périmètre pour l'instant

Ces étapes seront validées séparément. Tu peux prévoir la structure pour les faciliter, mais ne les exécute pas :

- fusionner réellement les familles ;
- mettre en place les redirections 301 des variantes vers `parent/#ancre` ;
- dépublier les sous-guides ;
- corriger les liens internes.

## Pilote

- **Famille pilote :** `comparatif-climatiseur-mobile` avec ses 5 variantes : `-7000-btu`, `-9000-btu`, `-12000-btu`, `-14000-btu`, `-16-000-btu`.
- **À ne pas fusionner tant que le pilote n'a pas fait ses preuves :** les variantes qui performent seules, comme `radiateur-double-coeur-de-chauffe-2000w`.
- **Autres familles chiffrées candidates, plus tard :** lave-linge (9, 12, 18, 20 kg), disque dur externe (1, 2, 4 To), aquarium (20, 60, 100 L), écran PC (24, 27 pouces, 144, 240 Hz), tablette (8 à 12 pouces), tranches de prix des smartphones (16 variantes).
- **Les variantes « qualitatives »** (lit coffre, aspirateur sans fil…) correspondent souvent à une autre intention de recherche. Elles ne sont pas concernées par défaut.

## Méthode de travail et livrables

1. **Analyse d'abord.** Lis le template V1, les fonctions PHP de sélection et de classement des produits, et les groupes de champs ACF. Présente-moi une proposition courte, en français et en listes à puces :
   - les champs à créer ;
   - l'algorithme ;
   - la structure de la page ;
   - le mode de bascule V1/V2 ;
   - la liste des fichiers.

   Attends mon OK avant d'écrire le code.
2. **Code.** Écris les fichiers dans le dépôt GitHub : snippets PHP pour WPCodeBox, groupe de champs ACF (export JSON ou `acf_add_local_field_group`), export du template Bricks V2 si c'est ainsi que tu travailles. Donne-moi ensuite, dans la conversation, le code prêt à copier-coller, avec l'endroit où coller chaque bloc et l'ordre d'installation.
3. **Outil de remplissage.** Prépare un petit outil (page d'admin ou snippet) qui propose, pour un guide parent, les sous-comparatifs à partir des sous-guides existants : titre et attribut pré-remplis. Je valide avant d'enregistrer.
4. **Test.** Installe le pilote en aperçu (`?preview_v2=1`) sur `comparatif-climatiseur-mobile`. Vérifie :
   - l'absence de doublons dans les tests complets et le bon ordre ;
   - les ancres et le sommaire ;
   - les données structurées (test des résultats enrichis de Google) ;
   - les performances (PageSpeed).

   Ensuite, on active la V2 pour les visiteurs.

**Communication.** Réponds en français, de façon concise, avec des listes à puces. Ne me renvoie pas vers des fichiers CSV. Pour le code, donne-moi directement les blocs à copier-coller.
