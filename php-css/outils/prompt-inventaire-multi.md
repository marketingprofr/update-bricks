# Mission : inventaire des comparatifs de meilleurtest.fr + propositions de multi-comparatifs

Tu travailles pour meilleurtest.fr (WordPress + ACF, site en français). Ta mission est **en lecture seule** : tu extrais la liste de tous les comparatifs, tu les classes par type de produit et tu proposes, pour chaque comparatif « principal », les comparatifs qui pourraient devenir ses **sous-comparatifs** dans une page « multi-comparatif ». Tu produis deux fichiers CSV et un court rapport.

**⚠️ INTERDIT : toute écriture sur le site** (aucune requête POST / PUT / PATCH / DELETE, aucune modification de post, de terme ou de champ). Uniquement des GET.

## 1. Contexte métier

- Un **comparatif** est un post du type personnalisé `comparatif` (ex. « Les meilleurs climatiseurs mobiles »).
- Chaque comparatif porte des termes de deux taxonomies :
  - `post-type-produit` = **le type de produit** (ex. « climatiseur mobile ») ;
  - `post-type-attribut` = **les attributs** qui affinent le type (ex. « réversible », « 9000 BTU », « silencieux »).
- Un comparatif **sans aucun attribut** est un comparatif **principal** (ex. « Les meilleurs climatiseurs mobiles » : type = climatiseur mobile, aucun attribut).
- Un comparatif **avec au moins un attribut** est une **variante** (ex. post **ID 38292** « Les meilleurs climatiseurs mobiles réversibles » : type = climatiseur mobile, attribut = réversible).
- Un **multi-comparatif** = un comparatif principal qui affiche aussi, sur la même URL, une section par variante (ses sous-comparatifs). Les sous-comparatifs sont ensuite passés en statut **privé** (c'est pour ça qu'il faut aussi lister les comparatifs privés).
- Déjà en place sur le site :
  - l'étiquette WordPress (post_tag) `multi-comparatif`, posée sur les principaux transformés en multi-comparatif ;
  - le champ ACF **`mltv5_sous_comparatifs`** (Relation → comparatif, format ID), rempli sur le principal avec la liste de ses sous-comparatifs.

**Vérifie d'abord ce modèle sur les exemples** avant de tout lancer :
- le post 38292 doit avoir type « climatiseur mobile » + attribut « réversible » ;
- le comparatif « climatiseurs mobiles » principal doit avoir ce même type et aucun attribut.

Si l'organisation réelle diffère (noms de taxonomies, type de post, attributs rangés ailleurs), **arrête-toi et décris ce que tu observes** au lieu de deviner.

## 2. Accès aux données (API REST WordPress)

1. Découvre les noms exposés :
   - `GET https://meilleurtest.fr/wp-json/wp/v2/types` → trouve le `rest_base` du type `comparatif` ;
   - `GET https://meilleurtest.fr/wp-json/wp/v2/taxonomies` → trouve le `rest_base` de `post-type-produit` et de `post-type-attribut`. Ils peuvent différer du slug : utilise TOUJOURS le `rest_base`.
2. **Authentification** : les comparatifs **privés** ne sont visibles qu'authentifié. Utilise un **mot de passe d'application** WordPress (Profil → Mots de passe d'application) en Basic Auth, avec `context=edit`. Sans authentification, ne récupère que les publiés et signale-le dans le rapport.
3. **Termes** : récupère TOUS les termes des deux taxonomies (id → nom, slug, parent) :
   - `GET /wp-json/wp/v2/{rest_base_taxo}?per_page=100&page=N&hide_empty=false&_fields=id,name,slug,parent,count`
   - Décode les entités HTML des noms (`&amp;` → `&`, etc.).
4. **Comparatifs** :
   - `GET /wp-json/wp/v2/{rest_base_comparatif}?status=publish,private&context=edit&per_page=100&page=N&_fields=id,title,status,slug,link,modified,tags,{rest_base_produit},{rest_base_attribut},acf`
   - Pagine jusqu'à `X-WP-TotalPages`. Fais une pause de ~0,5 s entre les pages.
   - Titre : prends `title.raw` (en `context=edit`), jamais le titre rendu qui peut commencer par « Privé : ». Sinon, décode `title.rendered` et retire le préfixe « Privé : ».
   - Étiquette : récupère l'id du tag `multi-comparatif` (`GET /wp-json/wp/v2/tags?slug=multi-comparatif`) et teste sa présence dans `tags`.
   - Champ ACF `mltv5_sous_comparatifs` : présent dans `acf` si « Afficher dans l'API REST » est activé sur le groupe ACF. Sinon, laisse la colonne vide et note-le dans le rapport.
5. **Si le type `comparatif` n'est pas exposé dans l'API REST** (404 / absent de `/types`), il y a deux alternatives, à me proposer avant de faire quoi que ce soit :
   - **WP-CLI** en SSH (serveur Cloudways), en lecture seule : `wp post list --post_type=comparatif --post_status=publish,private --fields=ID,post_title,post_status,post_name --format=csv` puis `wp post term list <ID> post-type-produit post-type-attribut --fields=term_id,name,taxonomy --format=csv`, ou un script `wp eval-file` en lecture seule ;
   - l'**outil admin** déjà prêt dans le dépôt : `php-css/outils/inventaire-multi.code.php` (à coller dans un élément Code d'une page privée ; il produit les mêmes CSV).

## 3. Règles de classement (mécaniques, à appliquer strictement)

Pour chaque comparatif, calcule la **clé de type** = ensemble trié des IDs de termes `post-type-produit` (en général un seul terme).

- **Sans type de produit** : aucun terme `post-type-produit` → impossible à classer.
- **Principal** : au moins un type, **aucun** attribut.
- **Sous-comparatif possible** : au moins un attribut ET il existe un principal avec **exactement la même clé de type**. On propose ce principal comme multi-comparatif parent.
- **Orphelin** : au moins un attribut mais **aucun** principal avec la même clé de type.

**Plusieurs principaux pour la même clé de type** (doublons) : un seul est retenu comme parent, par ordre de priorité :
1. celui qui porte déjà l'étiquette `multi-comparatif` ;
2. sinon le publié plutôt que le privé ;
3. sinon le plus récemment modifié ;
4. sinon le plus petit ID.

Signale le doublon sur toutes les lignes concernées.

**Remarques à produire automatiquement** (colonne « Remarques », séparées par « ; ») :
- variante à plusieurs attributs (« 2 attributs : variante combinée ») ;
- déjà présent dans le `mltv5_sous_comparatifs` d'un AUTRE principal que celui proposé ;
- présent dans le champ de plusieurs principaux ;
- principal qui a des sous-comparatifs configurés mais pas l'étiquette `multi-comparatif` ;
- doublon de principal (IDs concernés, retenu / non retenu) ;
- attribut dont le terme a un parent dans la taxonomie (indique le parent, utile pour regrouper).

## 4. Relecture éditoriale (ton avis, en plus de la mécanique)

Pour chaque « Sous-comparatif possible », ajoute une colonne **« Avis »** avec l'une de ces valeurs et une justification courte (« Justification ») :

- **Recommandé** : la variante est un sous-segment du même besoin que le principal (puissance, taille, capacité, fonction, usage, public : « réversible », « 9000 BTU », « silencieux », « pour enfant », « pas cher »…). Un lecteur du guide principal peut raisonnablement vouloir ce choix sur la même page.
- **À discuter** : la variante correspond à une intention de recherche très différente ou à fort volume propre (ex. une marque, un usage professionnel), ou le principal aurait plus de **8** sous-comparatifs. Dans ce dernier cas, classe-les et indique lesquels garder en priorité.
- **Déconseillé** : la variante n'est en réalité pas le même produit (mauvaise étiquette de type), le titre ne correspond pas aux termes, ou c'est un doublon quasi identique du principal. Dans ce dernier cas, suggère plutôt une fusion ou redirection simple.

Ne modifie jamais la proposition mécanique : l'avis est une colonne en plus.

## 5. Livrables

Écris les fichiers dans le dossier courant, en **CSV séparateur « ; »**, encodage **UTF-8 avec BOM** (pour Excel), guillemets autour des cellules contenant « ; », « " » ou un retour à la ligne.

### 5.1 `comparatifs-inventaire-AAAA-MM-JJ.csv` (une ligne par comparatif)

Colonnes, dans cet ordre :
`Type de produit` ; `ID type` ; `Rôle` ; `ID comparatif` ; `Titre` ; `Statut` ; `Attributs` (noms séparés par « | ») ; `Nb attributs` ; `ID multi-comparatif proposé` ; `Titre multi-comparatif proposé` ; `Nb sous-comparatifs proposés` (sur les lignes Principal) ; `Sous-comparatifs déjà configurés` (IDs du champ ACF) ; `Déjà rattaché à` (IDs des principaux qui le listent déjà) ; `Étiquette multi-comparatif` (oui/non) ; `Avis` ; `Justification` ; `URL` ; `Édition` (`https://meilleurtest.fr/wp-admin/post.php?post={ID}&action=edit`) ; `Dernière modification` ; `Remarques`

Tri :
1. par type de produit (ordre alphabétique naturel) ;
2. puis Principal → Sous-comparatif possible → Orphelin → Sans type ;
3. puis par nombre d'attributs croissant, par noms d'attributs, et enfin par ID.

Exemple de lignes attendues :
```
Climatiseur mobile;12;Principal;38000;Les meilleurs climatiseurs mobiles;publish;;0;;;3;38292;;oui;;;https://…;https://…;2026-09-20;
Climatiseur mobile;12;Sous-comparatif possible;38292;Les meilleurs climatiseurs mobiles réversibles;private;Réversible;1;38000;Les meilleurs climatiseurs mobiles;;;38000;non;Recommandé;Sous-segment fonctionnel du même besoin;https://…;https://…;2026-09-18;
```

### 5.2 `multi-comparatifs-synthese-AAAA-MM-JJ.csv` (une ligne par principal ayant ≥ 1 sous-comparatif proposé)

Colonnes :
`ID multi-comparatif` ; `Titre` ; `Type de produit` ; `Statut` ; `Nb sous-comparatifs proposés` ; `Dont recommandés` ; `Sous-comparatifs proposés (ID : titre [attributs] {avis})` (séparés par « ; ») ; `IDs à saisir dans mltv5_sous_comparatifs` (**uniquement les « Recommandé »**, séparés par des virgules, dans l'ordre conseillé d'affichage) ; `Déjà configurés` ; `Étiquette multi-comparatif`

Tri : par nombre de sous-comparatifs recommandés décroissant.

### 5.3 `rapport-inventaire.md` (court)

- La méthode d'accès utilisée (REST authentifié ou non, WP-CLI…) et les limites (privés absents ? champ ACF non exposé ?).
- Les compteurs : total ; principaux ; sous-comparatifs possibles (dont recommandés / à discuter / déconseillés) ; orphelins ; sans type ; doublons.
- Les **20 meilleurs candidats** multi-comparatif (le plus de sous-comparatifs recommandés).
- Les anomalies à corriger à la main :
  - orphelins (proposer un parent plausible ou la création d'un principal) ;
  - comparatifs sans type ;
  - doublons de principaux ;
  - titres incohérents avec leurs termes.

## 6. Contrôles avant de rendre

- Le nombre de lignes de l'inventaire = nombre total de comparatifs publiés + privés récupérés (vérifie avec `X-WP-Total`).
- 38292 est bien « Sous-comparatif possible » sous le principal climatiseur mobile.
- Aucun ID n'apparaît comme sous-comparatif de deux principaux différents dans tes propositions.
- Les accents et « & » s'affichent correctement quand le CSV est ouvert dans Excel.
- Aucune requête d'écriture n'a été envoyée.
