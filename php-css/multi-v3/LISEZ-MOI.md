# Modèle multi-comparatif V3 : ce qu'il faut coller

Ce dossier contient tout ce qui change dans le modèle multi-comparatif par rapport à la version en ligne, qui est la V2. Il rassemble les décisions des 2 et 3 octobre 2026, ainsi que les corrections demandées par Samuel après sa relecture de la page climatiseur mobile, le 3 octobre :
- la ligne auteur sous le titre ;
- l'encart « L'essentiel en 30 secondes » ;
- les liens de « Vos questions » ;
- l'encadré en phrases complètes ;
- la phrase d'affiliation retirée de l'encadré ;
- le débordement sur mobile.

Chaque élément Code du modèle a deux fichiers, avec le même numéro.

| N° | Élément Code du modèle (Bricks) | Onglet **Code** | Onglet **CSS** |
|---|---|---|---|
| 1 | Hero, colonne de gauche (titre, ligne auteur, intro, encart « L'essentiel », photo si pas d'encart) | `multi-v3-1-hero-gauche.code.php` | `multi-v3-1-hero-gauche.css` → **dans le CSS personnalisé du bandeau** (voir plus bas) |
| 2 | Hero, colonne de droite (encadré « Pourquoi nous faire confiance ») | `multi-v3-2-encadre-confiance.code.php` | `multi-v3-2-encadre-confiance.css` → **dans le CSS personnalisé du bandeau** (voir plus bas) |
| 3 | Sommaire | `multi-v3-3-sommaire.code.php` | `multi-v3-3-sommaire.css` |
| 4 | Résumé du top 5 et sections des sous-comparatifs | `multi-v3-4-resume-top5.code.php` | `multi-v3-4-resume-top5.css` |
| 5 | Tests complets | `multi-v3-5-tests-complets.code.php` | `multi-v3-5-tests-complets.css` |
| 6 | Questions fréquentes (FAQ) | `multi-v3-6-faq.code.php` | `multi-v3-6-faq.css` |
| 7 | Tableau comparatif | `multi-v3-7-tableau.code.php` | `multi-v3-7-tableau.css` |
| 8 | « Quel choix faire ? » (duels, par ex. « Climatiseur mobile ou climatiseur fixe ? ») | `multi-v3-8-choix.code.php` | `multi-v3-8-choix.css` |
| 9 | **Nouveau** : colonne de gauche du guide d'achat (sélections de la page, accès rapide, guides du même type, modèles analysés) | `multi-v3-9-colonne-gauche.code.php` | `multi-v3-9-colonne-gauche.css` |
| 10 | **Nouveau** : colonne de droite du guide d'achat (plus consultés de la catégorie, mis à jour récemment, guides de la catégorie et guides spécialisés, questions fréquentes) | `multi-v3-10-colonne-droite.code.php` | **vide** (le CSS des deux colonnes est dans le 9) |

Les autres éléments Code du modèle ne changent pas. Il n'y a rien à coller pour eux, notamment pour le guide d'achat (sauf le bloc des duels, élément 8), les types, les marques, les astuces, « Pourquoi acheter », les comparatifs similaires et l'index des comparatifs.

## Si le modèle V3 est déjà collé

**Disposition validée le 3 octobre au soir** : titre → ligne auteur → intro qui commence par la réponse → un seul encart « L'essentiel en 30 secondes » avec les questions.

**Corrections du 4 octobre** : la réponse forme son propre paragraphe au début de l'intro, les liens de l'encart deviennent « En savoir plus », le lien du bas devient « Voir les N questions-réponses de notre foire aux questions », et le point de vigilance passe dans un encart jaune. Recolle :
- **1**, Code et CSS ;
- **4**, Code et CSS.

**Deuxième passe du 4 octobre** : la méthode vient avant la réponse (« Nous avons analysé 55 climatiseurs mobiles et retenu les 5 meilleurs. Midea PortaSplit… est le meilleur… »). L'intro de la rédaction est repliée sur 2 lignes, avec « Afficher la suite ». L'encart « L'essentiel » devient une liste à puces avec un trait sous le titre. Le texte du point de vigilance passe en 15 px. Si tu as déjà collé les corrections ci-dessus, recolle :
- **1**, Code et CSS ;
- **4**, CSS seulement.

**Troisième passe du 4 octobre** (choix de Samuel) : la phrase d'ouverture devient une seule phrase, « Sur les 55 climatiseurs mobiles que nous avons analysés, Midea PortaSplit 12000 BTU (9,0/10) est le meilleur en 2026, devant … ». Dans l'encadré, « noté principalement sur 4 critères », la ligne « Prévenez-nous » sort de la liste et passe sous « Découvrez notre méthodologie… », et la citation est raccourcie. Si tu as déjà collé la deuxième passe, recolle :
- **1**, Code seulement ;
- **2**, Code seulement.

**Quatrième passe du 4 octobre** (choix de Samuel) : l'encart « L'essentiel » montre les 4 premières questions de la rédaction, sans la question du budget. La photo du haut de page est retirée. L'intro passe de 16,5 à 16 px. Si tu as déjà collé la troisième passe, recolle :
- **1**, Code et CSS.

Puis la 2e ligne de l'intro repliée est estompée, pour montrer que le texte continue : **1**, Code et CSS.

Puis l'encart « L'essentiel » ne montre plus que les questions écrites par la rédaction, jusqu'à 4, à partir de la première. Il ne montre jamais les questions automatiques de la FAQ (budget, « Comment bien choisir »). Avec moins de 2 questions de la rédaction, il n'y a pas d'encart : **1**, Code seulement.

Puis la pastille « Vérifié le … » au-dessus du titre est retirée, car la ligne auteur sous le titre le dit déjà : **1**, Code seulement.

Puis les 3 produits de la phrase d'ouverture ont chacun leur lien marchand, qui s'ouvre dans un nouvel onglet comme tous les autres liens marchands de la page : **1**, Code seulement.

Puis, dans l'encadré, le bloc de vote dit « Avis des lecteurs » et « Votre note nous aide à améliorer ce comparatif. » : **2**, Code seulement.

Puis les 5 produits du top 5 ont le même format (celui des produits 2 à 5). Le n°1 garde seulement le laurier sur sa photo, et chaque note a sa pastille « Excellent », « Très bon »… dessous : **4**, CSS seulement.

Puis la photo revient entre l'intro et l'encart « L'essentiel » (sauf étiquette « no featured »), le fil d'Ariane passe à 9 px du titre, la colonne des notes du top 5 s'élargit un peu, et l'encadré perd le trait sous les 4 cases (il passe sous les puces) et la marge en trop sous « Découvrez notre méthodologie » : **1**, Code et CSS ; **2**, CSS seulement ; **4**, CSS seulement.

Puis les sous-comparatifs passent à 3 produits partout : leurs sections résumées (sans barre de tri, avec « Consulter le guide d'achat complet des … » si la page du sous-comparatif est publiée), les tests complets, le tableau et le sommaire. Le top 5 principal garde ses 5 produits. Le calcul de la liste des produits est copié dans 5 éléments : **1**, Code ; **3**, Code ; **4**, Code et CSS ; **5**, Code ; **7**, Code.

Puis les 2 puces de l'encadré commencent par une entrée en gras : « **Pour réaliser ce comparatif**, nous avons consulté 27 sources, dont… » et « **Pour établir le classement**, chaque climatiseur mobile a été noté principalement sur 4 critères (…) » : **2**, Code seulement.

Puis la puce 2 passe à la voix active, « **Pour établir le classement**, nous avons noté les climatiseurs mobiles sur 4 critères principaux (…) », et la section des avis détaillés change d'en-tête : « Pour aller plus loin », « Notre avis sur les meilleurs climatiseurs mobiles », « Nous présentons ici les 21 climatiseurs mobiles de notre sélection, choisis parmi les 55 que nous avons analysés, avec leurs caractéristiques, les retours des utilisateurs et leur rapport qualité-prix. » : **1**, Code ; **2**, Code ; **5**, Code.

Puis, dans le sommaire, « Tests complets » devient « Avis détaillés » : **3**, Code seulement.

Puis le chapô des avis détaillés finit par « avec leurs caractéristiques, leur rapport qualité-prix et notre verdict. » : **5**, Code seulement.

Puis l'intro de la rédaction est entière sur ordinateur, et repliée sur 3 lignes sur téléphone (coupée net, sans fondu), avec « Afficher la suite » : **1**, Code et CSS.

Puis la photo passe tout en haut de l'encadré « Pourquoi nous faire confiance », pleine largeur, sans le badge, avec un alt propre ; l'encadré ne reste collant que s'il tient dans la hauteur de l'écran : **1**, Code ; **2**, Code et CSS.

Puis, pour la vitesse : l'image de l'encadré en taille moyenne sur les écrans classiques (9 Ko au lieu de 42), les images Amazon des cartes du top 5 en 200 px (5 Ko au lieu de 22), le badge de la photo du hero chargé tout de suite, et les avis détaillés, le tableau et la FAQ dessinés seulement à l'approche de l'écran : **1**, Code ; **2**, Code ; **4**, Code ; **5**, CSS ; **6**, CSS ; **7**, CSS.

Puis le bloc « Avis des lecteurs » de l'encadré est resserré (14 px de moins sur ordinateur) : **2**, CSS seulement.

Puis la mention sous les produits devient « Pour rappel, Meilleurtest peut toucher une commission si vous achetez via ces liens, sans surcoût pour vous et sans effet sur nos classements. » : **4**, Code seulement.

Puis, dans les duels, les titres d'option (« Climatiseur mobile », « Climatiseur fixe ») passent de H4 en H3, sans changement visible : **8**, Code seulement (dans le modèle où se trouve ce bloc, par exemple le modèle Section du guide d'achat).

Puis les scripts de l'intro (« Afficher la suite ») et de l'encadré collant ne mesurent plus rien pendant le chargement (recalculs forcés relevés par PageSpeed), sans changement visible : **1**, Code ; **2**, Code.

Puis, pour que ce modèle serve à tous les comparatifs : l'encart « L'essentiel » n'apparaît qu'avec au moins 3 vraies questions de la rédaction dans la FAQ ; sans encart, la photo revient dans le hero, après l'intro, au lieu d'être en haut de l'encadré : **1**, Code ; **2**, Code.

Puis la réponse « Comment avons-nous établi ce classement ? » suit l'ordre du travail (sources consultées, produits identifiés, recherches et note sur les critères principaux, produits retenus), et la puce 2 de l'encadré commence par « **Nous avons noté** les climatiseurs mobiles… » : **2**, Code ; **6**, Code.

Si tu n'avais pas encore collé la version du 3 octobre au soir, recolle aussi :
- **2**, Code seulement (durée de lecture retirée, phrase des critères).

Si tu n'avais collé que la toute première version, recolle aussi :
- **5**, CSS seulement (débordement mobile) ;
- **6**, Code et CSS (ancres des questions).

## Cartes du top 5 dans une colonne étroite (5 octobre)

Quand le sommaire est affiché à côté (écrans de 900 à 1 200 px environ), la carte garde sa forme habituelle, avec une colonne étroite à droite : la note (score et pastille sur une ligne) au-dessus du bouton. À recoller : **4**, CSS seulement.

## Données structurées sans note Amazon (5 octobre)

Les Product du JSON-LD n'ont plus d'`aggregateRating` : c'étaient les notes clients d'Amazon, et Google interdit les notes reprises d'autres sites. Elles restent affichées sur la page. Chaque Product garde la note de la rédaction et les offres ; un produit sans l'une ni l'autre n'est plus décrit (la liste garde son adresse). À recoller : **5**, Code seulement.

## Colonnes du guide d'achat (éléments 9 et 10, depuis le 5 octobre)

Sur le modèle de Game8 : une colonne à gauche et une à droite autour du guide d'achat. Pour les mettre en place, dans le modèle (ou le modèle Section) qui contient le guide :
1. Dans le panneau Structure, repérez le **conteneur** qui contient le **bloc** du guide (ce bloc contient le guide d'achat, les types, les duels, les marques, les astuces, « Pourquoi acheter » et la FAQ).
2. Dans ce conteneur, ajoutez un élément **Code** juste **avant** le bloc : collez `multi-v3-9-colonne-gauche.code.php` (onglet Code) et `multi-v3-9-colonne-gauche.css` (onglet CSS).
3. Ajoutez un élément **Code** juste **après** le bloc : collez `multi-v3-10-colonne-droite.code.php` dans l'onglet Code ; laissez son onglet CSS **vide** (le CSS des deux colonnes est collé une seule fois, dans le 9 ; si vous retirez un jour la colonne de gauche, recollez `multi-v3-9-colonne-gauche.css` dans la colonne de droite).
4. Activez l'exécution du code pour ces deux éléments et validez la signature si Bricks la demande.

Le CSS transforme lui-même le conteneur en 3 colonnes (220 px | guide | 280 px dès 1 200 px ; la colonne de droite passe sous le guide entre 992 et 1 199 px ; colonnes masquées en dessous) : aucun réglage Bricks à changer. Chaque bloc se règle en haut de son fichier (`true` / `false`) pour retirer ce qui ne sert pas.

Tous les blocs restent dans la catégorie ou le type du comparatif (entonnoirs thématiques) : « De saison » et « Les comparatifs populaires » de tout le site ont été retirés.

Depuis le 5 octobre au soir, chaque bloc a sa propre mise en forme (inspirée de Game8, sans la copier) :
- **Accès rapide** : panneau gris, tuiles sur deux colonnes, la partie en cours de lecture assombrie ;
- **Tous les guides** : pastilles en deux niveaux, comme les « Comparatifs similaires » sous le tableau (les guides du même type, page en cours en bleu, puis les autres guides de la catégorie) ;
- **Les modèles analysés** : 10 au plus, rang, nom et note dans une pastille ;
- colonne de droite : chaque bloc dans un cadre, filet bleu en haut, icône devant le titre ;
- **Les plus consultés en …** : numéros 1 à 5 dans des carrés bleus. Il repose sur les vrais clics Google des 28 derniers jours, lus dans la page privée `mt-clics-28j` que l'instance Architecture créera (avec votre accord). Tant que cette page n'existe pas, ou si elle est illisible, le bloc reste masqué ;
- **Mis à jour récemment** : le premier en grande image, les autres en vignette, avec leur date ;
- **Dans la même catégorie** : petite image carrée et nom ;
- **Questions fréquentes** : un « ? » devant chaque question.

À recoller : **9**, Code et CSS ; **10**, Code seulement.

Puis, après la relecture de Samuel (CSS seulement, le HTML ne change pas) :
- Accès rapide sans panneau autour, juste les tuiles gris clair ;
- Tous les guides en simple liste de liens, comme le « Guide Index » de Game8, la page en cours sur fond gris ;
- icônes de la couleur du titre, plus en bleu ;
- colonne de droite plus proche de Game8 : cadre à peine visible, séparations nettes entre les éléments, d'un bord à l'autre ;
- Mis à jour récemment : titre et date centrés en hauteur sur la vignette.

À recoller : **9**, CSS seulement.

## Guides du type à gauche, autres guides de la catégorie à droite (5 octobre)

Le bloc « Tous les guides » mélangeait deux niveaux et répétait le bloc « Dans la même catégorie » de droite. Désormais (demande de Samuel) :
- à gauche, **« Guides climatiseurs mobiles »** (le type du comparatif) : les guides du même type, 10 au plus, la page en cours en tête, en simple liste grise comme le « Guide Index » de Game8, sans bleu au survol ;
- à droite, **« Autres guides climatisation »** (la catégorie) : tout le reste de la catégorie, 20 au plus, avec une petite image.

Les deux blocs suivent le classement des « Comparatifs similaires » sous le tableau. À recoller : **9**, Code et CSS ; **10**, Code seulement.

Puis, 20 guides dans un bloc étant trop gros (Samuel), la droite est coupée en deux blocs de 10 au plus :
- **« Guides climatisation »** : les guides principaux des autres types (sans attribut), avec une petite image ;
- **« Guides spécialisés »** : les guides plus précis (prix, marque, usage…), en simple liste avec une flèche.

Un bloc qui n'aurait qu'un seul lien n'est pas affiché. À recoller : **9**, CSS seulement ; **10**, Code seulement.

## Sélections de la page en haut de la colonne de gauche (7 octobre)

Demande de Samuel : en haut de la colonne de gauche, un bloc pour passer d'une sélection à l'autre, comme le sommaire du haut de page mais dans le style de la colonne (liste grise). Titre accordé comme le sommaire (« Meilleur climatiseur mobile »), puis « En 2026 » et une ligne par sous-comparatif (« Réversible », « 9000 BTU »…), chacune menant à sa partie de la page. Le bloc n'apparaît que sur un multi-comparatif. À recoller : **9**, Code et CSS.

Puis (Samuel, 8 octobre) : ce bloc s'appelle « Classement », sa première ligne « Palmarès 2026 » ; « Les modèles analysés » devient « Modèles analysés ». À recoller : **9**, Code seulement.

Puis : « Classements » au pluriel (il y a toujours au moins le palmarès et une sélection) et une flamme comme icône de « Les plus consultés ». À recoller : **9**, Code et CSS.

## Colonnes sur tout l'article (8 octobre)

Samuel veut les colonnes le long de tout l'article (résumés, avis détaillés, tableau, guide), sans HTML en double. Le CSS du **9** le permet désormais, et donne exactement la même page tant que la structure Bricks n'a pas changé (vérifié sur la page en ligne et sur le banc).

Structure cible dans Bricks :
- une seule Section, un seul Conteneur, qui contient dans l'ordre : l'élément Code de gauche (9), un Bloc « colonne principale », l'élément Code de droite (10) ; la colonne principale contient les modèles des résumés et avis détaillés, du tableau et du guide (structure choisie par Samuel ; les trois modèles directement dans le conteneur marchent aussi) ;
- les trois blocs peuvent rester des modèles de type Section, insérés par des éléments « Modèle », mais **sans** élément Section ni Conteneur à l'intérieur : la racine de chaque modèle est un simple **Bloc** ;
- le bloc du sommaire (à gauche des résumés, déjà masqué sur mobile) est retiré : « Classements » et « Accès rapide » le remplacent ;
- les réglages de largeur posés dans Bricks pour l'ancienne mise en page (bloc du guide en « calc(100% - 220px) », conteneur en ligne avec 56 px d'écart) sont à retirer ;
- les espacements entre les trois blocs se règlent dans Bricks (marges des blocs), puisque les sections ne les donnent plus.

Le tableau garde toute la largeur et passe par-dessus les colonnes (fond opaque) ; elles réapparaissent après lui.

Puis (Samuel) :
- une bande blanche de 40 px au-dessus du titre du tableau et de 32 px sous le tableau, pour qu'il apparaisse proprement par-dessus les colonnes ;
- colonne de droite : « Les plus consultés » et « Guides climatisation » sont regroupés à la fin de la colonne et restent à l'écran pendant toute la lecture (s'ils dépassent l'écran, leur bas reste visible) ; les autres blocs défilent avant eux. Réglage `$MT_SIDE_COLLES` en haut du fichier 10 ;
- avis détaillés dans la colonne centrale étroite : image sur un bon tiers, verdict sur le reste en 17,5 px ; encart de note sur une ligne, « Très bien » sous la note, bouton à droite sur une ligne. Mobile inchangé.

À recoller : **9**, CSS ; **10**, Code ; **5**, CSS.

## Encadré : « En plus de nos propres analyses » (8 octobre)

Choix de Samuel du 6 octobre (via la Coordination) : la puce 1 de l'encadré commence par « **En plus de nos propres analyses**, nous avons consulté 27 sources, dont… » au lieu de « Pour réaliser ce comparatif… », qui laissait croire à une simple compilation de sources (Jev : travail d'analyse propre 0,97 contre 0,85). À recoller : **2**, Code seulement. Même fichier pour le modèle V1.

Colonne de droite, même jour (demande de la Coordination) : le classement « Les plus consultés » se met à jour dès que la page des clics change (chaque lundi), et un bloc vide n'est plus gardé que 1 h. À recoller : **9** et **10**, Code.

## Duels l'un sous l'autre (8 octobre)

« Quel choix faire ? » : les deux options sont désormais l'une sous l'autre, le badge VS entre les deux, sur toutes les largeurs (Samuel : côte à côte, les colonnes étaient trop étroites, même en pleine page). Options bornées à 700 px comme le titre et le verdict. Mesuré en ligne à 1440 px : chaque option passe de 285 × 767 px à 656 × 360 px. À recoller : **8**, CSS seulement (même fichier pour le modèle V1).

## Liens vers les sources dans l'encadré (9 octobre)

Décision de Samuel : un seul champ, celui de la phrase des sources (mltv5_phrase_sources). Architecture y écrira la même phrase, avec un lien sur le nom de chaque publication citée (jusqu'à 4 sites internationaux et un grand site français) vers la page consultée pour ce guide. Reddit, YouTube et « des sites français » restent sans lien. L'encadré affiche ces liens (nouvel onglet, jamais d'affiliation) ; la réponse « Comment avons-nous établi ce classement ? » de la FAQ garde le texte seul. Tant que la phrase n'a pas de lien, rien ne change à l'écran. À recoller : **2**, Code ; **6**, Code.

## Nom de Samuel relié à sa biographie (9 octobre)

La biographie de Samuel est en ligne (page À propos, section #samuel-petit). Dans la ligne auteur, son nom renvoie maintenant à cette section (souligné discret). Rank Math décrit l'auteur dans les données structurées sans adresse ni biographie : le petit code `rank-math-person-bio.php` (WPCodeBox, PHP, « Frontend ») ajoute la biographie du compte de chaque rédacteur, et pour Samuel l'adresse de sa section et sa fonction « Fondateur et responsable éditorial ». À recoller : **1**, Code ; CSS du bandeau (fichier 1 puis fichier 2) ; nouveau code WPCodeBox.

## Premières pages passées en V3 (12 octobre)

Draps en coton, poussettes Lorelli, assurance auto et pompe à chaleur passées en V3 par Samuel. Corrigé après vérification :
- encadré : 4e case « mots dans ce guide » dès 1 000 mots (8 000 avant : elle manquait sur les petits guides) ; nombre impair de cases : la dernière prend toute la ligne ; photo dans un cadre 16/9 (une photo carrée faisait 376 px de haut) ;
- « 5 critères principaux » pour 4 libellés (« taille et poids de l'enfant » comptait pour 2) : décompte sur les virgules seulement (encadré et FAQ) ;
- noms de marque abîmés (« poussettes lorelli ») : le type de produit n'est plus mis entièrement en minuscules, seulement l'initiale ;
- guide d'achat avec photo : texte et photo l'un sous l'autre quand le guide est étroit (criteres.css).
Hors code : ces pages n'ont pas le style de thème de Bricks qui donne 1 220 px de large (elles restent à 1 100 px, avec des titres et des marges différents). À régler par Samuel dans les conditions du style de thème.
À recoller : **1** Code ; **2** Code ; CSS du bandeau (fichier 1 puis 2) ; **6** Code ; **9** Code ; CSS de l'élément « Critères » du guide (criteres.css).

## Cartes du top 5 dans la colonne étroite : mise en page « F » (12 octobre)

Choisie par Samuel parmi 9 propositions (fichier temporaire wp-avis/propositions-carte-top5.html). Quand la liste fait 700 px ou moins (colonne centrale entre les deux colonnes) : bandeau en tête de carte (« N°1 · Le choix de la rédaction » à gauche, note et avis clients à droite), image et texte au milieu, bouton sur toute la largeur en bas, sans flèche. Mesuré en ligne : texte du produit 158 → 406 px à 536 px de large, 278 → 526 px à 656 px. De 701 à 820 px (tablette) : forme habituelle, note et bouton à droite. Mobile inchangé. À recoller : **4**, CSS seulement.

Puis (Samuel, option 1) : le bandeau du n°1 dit « Le choix de la rédaction », celui des suivants porte leur verdict (« La meilleure pompe à chaleur haut de gamme »), qui n'est alors plus répété sous le nom ; à droite, la note seule (ni « Très bien », ni note Amazon). Verdict trop long : retour à la ligne serré (interligne 1,15), centré verticalement avec le rang et la note (les « … » essayés d'abord ne plaisaient pas à Samuel). Bouton à la taille de son texte, aligné sur le nom du produit (pleine largeur jugé trop grand). Puis (décision de Samuel) : le résumé du produit remplace toujours les points forts et faibles, sur 4 lignes au plus, à toutes les largeurs ; un produit sans résumé garde ses points. Le PHP n'écrit les points que s'il n'y a pas de résumé (rien de caché dans la page). À recoller : **4**, Code et CSS.

Smartphone (Samuel) : la note (et sa mention) à droite, sous l'image ; « Lire l'avis complet » en simple lien comme sur ordinateur, plus en bouton ; le bouton d'achat reste en bas sur toute la largeur. À recoller : **4**, CSS. Le tri garde la logique (le 1er prend le libellé du tri). À recoller : **4**, Code et CSS.

## Encadré et colonne de gauche : deux retouches (12 octobre)

- **Encadré « Pourquoi nous faire confiance »** : la puce « **Nous avons noté** les … sur N critères principaux » passe en premier, celle des sources (« En plus de nos propres analyses, nous avons consulté … ») en second. Nos analyses d'abord, les sources au second plan. Testé par la Coordination avec Jev sur 11 formulations : « En plus de nos propres analyses » reste la meilleure (0,91 pour « vrai travail d'analyse », 0,79 pour « Nous avons cherché des informations complémentaires »). Rien d'autre à changer. À recoller : **2**, Code seulement. Même fichier pour le modèle V1.
- **Liens des sources soulignés deux fois** : la règle qui soulignait les liens de la liste s'ajoutait au trait déjà posé par la classe `link-black` de l'encadré. Règle retirée : les sources ont maintenant le même trait unique que « notre méthodologie » et « Prévenez-nous ». À recoller : **2**, onglet CSS du bandeau (fichier 2).
- **Colonne de gauche** : le bloc « Modèles analysés » prend le nom du type, comme la case de l'encadré (« Huiles d'olive analysées », « Assurances auto analysées »). Type vide ou trop long : « Produits analysés ». À recoller : **9**, Code seulement.

## FAQ : réponse « Quel est le meilleur … ? » (13 octobre)

Accord de Samuel le 13 octobre, après les tests de la Coordination (Jev, français 0,91 → 0,96 sur climatiseur mobile et 0,84 → 0,89 sur assurance auto).
- La réponse commence par reprendre la question : « Le meilleur climatiseur mobile de notre comparatif est **Midea PortaSplit 12000 BTU**, noté **9,0/10** (« Excellent »). » Elle remplace « Au terme de notre comparatif, c'est … qui se classe en tête… ».
- « notée » au féminin (« La meilleure assurance auto… »). Question au pluriel : le type au singulier (« La meilleure chaussure de running… »), ou « Le n°1 de notre comparatif » s'il manque.
- Atouts et bémol en minuscule au milieu de la phrase (« pas de perçage requis », « classe A++ »). Restent intacts les sigles (« SEER »), « Wi-Fi » et la marque du produit.
- Le reste ne change pas : atouts, bémol, podium et les deux phrases de fin.

À recoller : **6**, Code seulement. Même fichier pour le modèle V1.

## Barre de tri sur une ligne, « en 12 ans » retiré (14 octobre)

- Le bouton de tri « Avis des clients » devient « Avis », et « Trier par » est un peu plus petit. Dans la colonne centrale (656 px), la barre tient sur une ligne avec « Comment nous évaluons » : 48 px de haut au lieu de 86 sur matelas.
- Sous le titre : « Scores de 5,8 à 9,2 sur 177 produits analysés. » La durée (« en 12 ans ») est retirée, et « analysés » s'affiche maintenant pour tous les comparatifs, pas seulement au-delà de 50 produits.

À recoller : **4**, onglet Code ET onglet CSS. Même changement dans `top5-resume.code.php` pour le modèle V1.

## Cartes du top 5 : lien à droite du bouton, plus de grands blancs sur téléphone (14 octobre)

- **Colonne centrale étroite** (mise en page « F ») : « Lire l'avis complet » passe à droite du bouton d'achat, en lien texte sans flèche, centré sur la hauteur du bouton. Les cartes perdent 25 px.
- **Téléphones et écrans de 600 à 767 px** : le nom, le verdict et le résumé ne sont plus écartés par la hauteur de l'image (jusqu'à 48 et 54 px de blanc à 740 px). L'image et la note restent à droite.
- La pastille OOS / DISCO, visible seulement connecté, ne cache plus la note.
- Puis (Samuel) : la carte téléphone (image et note à droite, bouton sur toute la largeur) n'est plus utilisée que pour une liste de moins de 460 px, soit les vrais téléphones. De 460 à 820 px de liste, quel que soit l'écran, c'est la carte avec le bouton et le lien côte à côte. Au-delà, la carte pleine page ne change pas.

À recoller : **4**, onglet CSS.

## Encadré confiance plus court : citation retirée, votes resserrés (14 octobre)

Demande de Samuel : l'encadré de droite dépassait le hero de gauche sur plusieurs comparatifs.
- La citation signée (« Aucune marque ne peut payer… J'ai refusé des offres… ») est retirée de l'encadré. Elle ira sur la page « Nos engagements » (Architecture).
- Le bloc des votes est resserré : moins d'espace sous « Avis des lecteurs » et au-dessus de « Votre note nous aide… » (92 → 77 px).
- Gain mesuré à 1366 px : 137 px sur chaque page. L'encadré n'est plus plus haut que le hero, sauf drap coton (+8 px au lieu de +145).

Puis « Découvrez notre méthodologie… » et « Une erreur… Prévenez-nous » prennent l'interligne de la liste à puces (1,5 au lieu de 22 px) : encore −8 px, drap coton à égalité avec le hero. À recoller : CSS du bandeau (fichier 2).

À recoller : **2**, onglet Code ET CSS du bandeau (fichier 2). Même fichier pour le modèle V1.

## CSS sans commentaires (5 octobre)

Les fichiers CSS à coller n'ont plus de commentaires : Bricks imprimait ces explications dans chaque page, pour rien (suggestion de l'instance Technique). Les règles sont exactement les mêmes (vérifié en faisant lire les deux versions par Chrome). Rien à recoller exprès : chaque CSS s'allègera la prochaine fois qu'il sera recollé pour une autre raison. Le CSS du **9** est déjà dans ce cas.

## CSS des éléments 1 et 2 : dans le bandeau (depuis le 5 octobre)

Bricks imprime l'onglet CSS d'un élément Code juste après son contenu, au milieu de la page : pendant le chargement, le hero et l'encadré s'affichaient un instant sans mise en forme, puis se réarrangeaient (décalage mesuré par PageSpeed). Le CSS des éléments natifs, lui, est rassemblé dans la tête de la page. Depuis le 5 octobre (idée de Samuel) :
- le contenu complet de `multi-v3-1-hero-gauche.css` puis de `multi-v3-2-encadre-confiance.css` est collé, à la suite, dans le CSS personnalisé de l'élément Texte du bandeau d'affiliation du modèle (Style → CSS) ;
- les onglets CSS des éléments 1 et 2 restent **vides** (sinon les règles seraient en double).

Quand l'un de ces deux fichiers CSS change, on recolle les deux fichiers, à la suite, dans le bandeau.

## Pas à pas

1. Dupliquez le modèle multi-comparatif dans Bricks (Modèles → … → Dupliquer). Donnez-lui un nom clair, par exemple « Multi-comparatif V3 ».
2. Ouvrez la copie dans l'éditeur Bricks.
3. Pour chaque ligne du tableau :
   - cliquez sur l'élément Code concerné ;
   - remplacez tout le contenu de l'onglet **Code** par le fichier `.code.php`, en entier ;
   - remplacez tout le contenu de l'onglet **CSS** par le fichier `.css`, en entier. Les CSS sont toujours des fichiers complets ;
   - si Bricks demande de signer le code (Code review ou Signer), validez.
4. Enregistrez le modèle.
5. Prévisualisez un multi-comparatif avec ce modèle, par exemple le climatiseur mobile, et vérifiez la liste plus bas.
6. Videz le cache de FlyingPress puis celui de Cloudflare.

Sur GitHub, chaque fichier s'ouvre avec un bouton « Copier » (icône de deux carrés, en haut à droite du fichier). Il copie le fichier entier d'un coup.

## Réglages en tête des fichiers

| Fichier | Réglage | Valeur | Effet |
|---|---|---|---|
| 1 | `$MT_AUTEUR_SOUS_H1` | `true` | Ligne auteur et date juste sous le titre. |
| 1 | `$MT_REPONSE_INTRO` | `true` | L'intro commence par une phrase à part, par exemple « Sur les 55 climatiseurs mobiles que nous avons analysés, Midea PortaSplit 12000 BTU (9,0/10) est le meilleur en 2026, devant … », puis vient le texte de la rédaction. |
| 1 | `$MT_PASTILLE_VERIFIE` | `false` | Plus de pastille « Vérifié le … » au-dessus du titre : la ligne auteur dit déjà qui a vérifié, et la date. `true` la remet. |
| 1 | `$MT_PHOTO_HERO` | `'auto'` | La photo va après l'intro quand l'encart « L'essentiel » n'est pas affiché (moins de 3 vraies questions dans la FAQ) ; sinon elle est en haut de l'encadré. `true` = toujours après l'intro, `false` = jamais. |
| 2 | `$MT_PHOTO_ENCADRE` | `true` | Image mise en avant en haut de l'encadré, quand l'élément 1 ne l'a pas déjà placée après l'intro. Chargée en priorité, alt « Climatiseurs mobiles ». Pas d'image avec l'étiquette « no featured ». `false` = jamais dans l'encadré. |
| 1 | `$MT_INTRO_REPLIEE` | `true` | Sur téléphone, le texte de la rédaction est replié sur 3 lignes, avec « Afficher la suite » (puis « Masquer la suite »). Sur ordinateur, l'intro est entière, sans bouton. Le texte reste entier dans la page. Le lien se cache si l'intro tient sur 3 lignes. `false` = intro entière partout. |
| 1 | `$MT_VERDICT_SOUS_H1` | `false` | L'ancien encart séparé « L'essentiel » (4 phrases) n'est plus affiché. |
| 1 | `$MT_VOS_QUESTIONS` | `'apres_intro'` | Encart des questions juste après l'intro. `'sous_reponse'` le place sous la ligne auteur, `''` le retire. |
| 1 | `$MT_TITRE_QUESTIONS` | `'L’essentiel en 30 secondes'` | Titre de cet encart (test Jev : les trois titres se valent, celui-ci arrive en tête). `''` = sans titre. |
| 1 | `$MT_VERIFIE_PAR` | `'Samuel Petit'` | « Vérifié par Samuel Petit, responsable éditorial ». |
| 2 | `$MT_ENCADRE_REEL` | `true` | Encadré à vrais chiffres. |
| 2 | `$MT_LIGNES_CONFIANCE` | `false` | Retire les lignes « 100 % indépendant » et « Mis à jour le », qui répètent la phrase d'indépendance et la ligne auteur (test Jev neutre). `true` les remet. |
| 2 | `$MT_DUREE_LECTURE` | `false` | Retire « … min de lecture » (test Jev neutre). `true` la remet. |
| 2 | `$MT_TXT_AFFILIATION` | `''` | Plus de phrase d'affiliation dans l'encadré : elle va dans le bandeau du header (voir plus bas). |
| 2 | `$MT_SIGNALEMENT` | `true` | Ligne « Une erreur, ou un produit remplacé par un nouveau modèle ? Prévenez-nous. » vers `/signaler-une-erreur/`, sous « Découvrez notre méthodologie… ». |
| 4 | `$MT_POINT_VIGILANCE` | `true` | Point de vigilance juste avant le top 5. Seuls les types de produit dont les champs sont remplis l'affichent : pour l'instant, le climatiseur mobile. |
| 6 | `$MT_METHODO_ENCADRE` | `true` | « Comment avons-nous établi ce classement ? » reprend les chiffres, les sources et les critères de l'encadré, dans l'ordre du travail : « Nous avons d'abord consulté 27 sources, dont … Elles nous ont permis d'identifier 55 climatiseurs mobiles parmi les plus populaires. Nous avons ensuite mené nos propres recherches et donné à chaque climatiseur mobile une note sur 10, selon 4 critères principaux (…). Enfin, nous avons retenu les 5 meilleurs pour ce classement. … » |

Pour couper une fonction, passez son réglage à `false` (ou `''`) dans l'onglet Code, puis enregistrez.

## À faire à part, dans Bricks

- **Bandeau du header** (texte au-dessus du contenu, élément Texte du modèle d'en-tête). Remplacez la phrase d'affiliation actuelle par ta version finale, par exemple : « Nos recommandations sont 100 % indépendantes. Si vous achetez via nos liens, nous pouvons toucher une commission (sans surcoût pour vous). Cela n'a aucun effet sur le classement, mais nous aide à faire vivre le site. » Gardez le lien « En savoir + ».
- **Formulaire de signalement** : dans Fluent Forms, les deux champs cachés doivent avoir le **Name Attribute** `source_id` et `source_url`, et pour valeur par défaut `{get.source_id}` et `{get.source_url}`.
- **CSS du formulaire** : collez `php-css/formulaire-signalement.css` une seule fois dans Bricks → Paramètres → Code personnalisé → CSS personnalisé.
- **Titre forcé** : l'Architecture vide le titre forcé du climatiseur mobile, avec ton accord. Le title et le H1 prennent alors le format « … N analysés, T retenus ».

## À vérifier sur la page climatiseur mobile, après prévisualisation

1. Juste sous le titre, la ligne auteur : « Rédigé et vérifié par Samuel Petit, responsable éditorial • Mis à jour le … ».
2. L'intro commence par une phrase à part : « Sur les 55 climatiseurs mobiles que nous avons analysés, Midea PortaSplit 12000 BTU (9,0/10) est le meilleur en 2026, devant … ». Dessous, le texte habituel : entier sur ordinateur ; sur téléphone, 3 lignes puis « Afficher la suite », qui ouvre et referme.
3. Juste après l'intro, un seul encart « L'essentiel en 30 secondes » (titre en gras avec une petite icône, trait bleu dessous) : jusqu'à 4 questions de la rédaction (2 sur la page climatiseur mobile, qui n'en a que 2) en liste à puces, chacune avec sa réponse d'une phrase et « En savoir plus », qui descend à la question dans la FAQ et l'ouvre. En bas, « Voir les N questions-réponses de notre foire aux questions ».
4. Dans l'encadré de droite : les 4 cases, puis une liste à puces (« Nous avons consulté 27 sources, dont… », « Pour établir le classement, nous avons noté les climatiseurs mobiles sur 4 critères principaux (…) », chacune avec son début en gras). Sous la liste, l'engagement d'indépendance signé (« Aucune marque ne peut payer pour figurer dans nos classements. J'ai refusé des offres publicitaires allant jusqu'à 20 000 € pour garantir l'indépendance du site. »), puis « Découvrez notre méthodologie… » et, juste dessous, « Une erreur, ou un produit remplacé par un nouveau modèle ? Prévenez-nous. » Ni phrase d'affiliation, ni « 100 % indépendant », ni date, ni durée de lecture.
5. Dans le top 5, les 5 produits ont le même format, le laurier sur la photo du n°1, et sous chaque note la pastille « Excellent », « Très bon »… (sur ordinateur). Juste avant le top 5, le « Point de vigilance » de l'ADEME s'affiche dans un encart jaune, avec une icône d'alerte devant le titre et la source en petit.
6. Sur téléphone, la page ne dépasse pas de l'écran : pas de défilement de côté.
7. Dans la FAQ, « Comment avons-nous établi ce classement ? » donne les mêmes chiffres que l'encadré.
8. Plus de pastille « Vérifié le … » au-dessus du titre. La photo est tout en haut de l'encadré de droite (sauf étiquette « no featured »), et l'encadré ne reste collant que sur un écran assez haut pour le montrer en entier. L'intro est entière sur ordinateur, sur 3 lignes avec « Afficher la suite » sur téléphone. Le fil d'Ariane est à 9 px du titre.

## Pas dans ce modèle (à faire à part)

- **Fiches produit (avis)** : `avis-hero.code.php` et `avis-content.code.php` contiennent le prix « à partir de X €/mois » des abonnements et une correction du carrousel de la marque. Ils se collent dans le modèle des fiches avis, pas ici.
- **Modèle comparatif simple (V1)** : les mêmes changements existent dans les blocs V1 (`hero-gauche`, `top5-resume`, `top5-tests`, `sommaire`). Ils ne servent que si ce modèle reste utilisé.

## Pour l'instance Templates

Les fichiers de ce dossier sont des **copies exactes** des fichiers de référence. Les CSS sont copiés **sans leurs commentaires** (depuis le 5 octobre), car Bricks les imprime tels quels dans chaque page ; les commentaires restent dans les fichiers de référence. Les fichiers de référence sont :
- `v2/multi-hero-gauche`, `v2/multi-sommaire`, `v2/multi-resume`, `v2/multi-tests`, `v2/multi-tableau` ;
- `choix` (duels), partagé avec le V1 ;
- `hero-encart` et `faq`, partagés avec le V1.

Toute modification se fait dans le fichier de référence. Ensuite, on lance `python outils/verifier-multi-v3.py --recopier`, puis la même commande sans option, qui doit répondre « identique » pour les 19 fichiers.
