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
| 1 | Hero, colonne de gauche (titre, ligne auteur, « L'essentiel », « Vos questions », intro) | `multi-v3-1-hero-gauche.code.php` | `multi-v3-1-hero-gauche.css` |
| 2 | Hero, colonne de droite (encadré « Pourquoi nous faire confiance ») | `multi-v3-2-encadre-confiance.code.php` | `multi-v3-2-encadre-confiance.css` |
| 3 | Sommaire | `multi-v3-3-sommaire.code.php` | `multi-v3-3-sommaire.css` |
| 4 | Résumé du top 5 et sections des sous-comparatifs | `multi-v3-4-resume-top5.code.php` | `multi-v3-4-resume-top5.css` |
| 5 | Tests complets | `multi-v3-5-tests-complets.code.php` | `multi-v3-5-tests-complets.css` |
| 6 | Questions fréquentes (FAQ) | `multi-v3-6-faq.code.php` | `multi-v3-6-faq.css` |
| 7 | Tableau comparatif | `multi-v3-7-tableau.code.php` | `multi-v3-7-tableau.css` (inchangé) |

Les autres éléments Code du modèle ne changent pas. Il n'y a rien à coller pour eux, notamment pour le guide d'achat, les types, les marques, les astuces, « Pourquoi acheter », les comparatifs similaires et l'index des comparatifs.

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

Si tu n'avais pas encore collé la version du 3 octobre au soir, recolle aussi :
- **2**, Code seulement (durée de lecture retirée, phrase des critères).

Si tu n'avais collé que la toute première version, recolle aussi :
- **5**, CSS seulement (débordement mobile) ;
- **6**, Code et CSS (ancres des questions).

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
| 1 | `$MT_PHOTO_HERO` | `true` | La photo (image mise en avant) est entre l'intro et l'encart « L'essentiel », sur ordinateur comme sur téléphone, chargée en priorité. Un comparatif avec l'étiquette « no featured » n'a pas de photo. `false` = jamais de photo. |
| 1 | `$MT_INTRO_REPLIEE` | `true` | Le texte de la rédaction est replié sur 2 lignes, la 2e ligne estompée, avec « Afficher la suite » (puis « Masquer la suite »). Le texte reste entier dans la page. Le lien se cache si l'intro tient sur 2 lignes. `false` = intro entière. |
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
| 6 | `$MT_METHODO_ENCADRE` | `true` | « Comment avons-nous établi ce classement ? » reprend les chiffres de l'encadré. |

Pour couper une fonction, passez son réglage à `false` (ou `''`) dans l'onglet Code, puis enregistrez.

## À faire à part, dans Bricks

- **Bandeau du header** (texte au-dessus du contenu, élément Texte du modèle d'en-tête). Remplacez la phrase d'affiliation actuelle par ta version finale, par exemple : « Nos recommandations sont 100 % indépendantes. Si vous achetez via nos liens, nous pouvons toucher une commission (sans surcoût pour vous). Cela n'a aucun effet sur le classement, mais nous aide à faire vivre le site. » Gardez le lien « En savoir + ».
- **Formulaire de signalement** : dans Fluent Forms, les deux champs cachés doivent avoir le **Name Attribute** `source_id` et `source_url`, et pour valeur par défaut `{get.source_id}` et `{get.source_url}`.
- **CSS du formulaire** : collez `php-css/formulaire-signalement.css` une seule fois dans Bricks → Paramètres → Code personnalisé → CSS personnalisé.
- **Titre forcé** : l'Architecture vide le titre forcé du climatiseur mobile, avec ton accord. Le title et le H1 prennent alors le format « … N analysés, T retenus ».

## À vérifier sur la page climatiseur mobile, après prévisualisation

1. Juste sous le titre, la ligne auteur : « Rédigé et vérifié par Samuel Petit, responsable éditorial • Mis à jour le … ».
2. L'intro commence par une phrase à part : « Sur les 55 climatiseurs mobiles que nous avons analysés, Midea PortaSplit 12000 BTU (9,0/10) est le meilleur en 2026, devant … ». Dessous, le texte habituel tient sur 2 lignes, la 2e ligne estompée, avec « Afficher la suite », qui ouvre et referme.
3. Juste après l'intro, un seul encart « L'essentiel en 30 secondes » (titre en gras avec une petite icône, trait bleu dessous) : jusqu'à 4 questions de la rédaction (2 sur la page climatiseur mobile, qui n'en a que 2) en liste à puces, chacune avec sa réponse d'une phrase et « En savoir plus », qui descend à la question dans la FAQ et l'ouvre. En bas, « Voir les N questions-réponses de notre foire aux questions ».
4. Dans l'encadré de droite : les 4 cases, puis une liste à puces (« Nous avons consulté 27 sources, dont… », « Pour établir le classement, nous avons noté les climatiseurs mobiles sur 4 critères principaux (…) », chacune avec son début en gras). Sous la liste, l'engagement d'indépendance signé (« Aucune marque ne peut payer pour figurer dans nos classements. J'ai refusé des offres publicitaires allant jusqu'à 20 000 € pour garantir l'indépendance du site. »), puis « Découvrez notre méthodologie… » et, juste dessous, « Une erreur, ou un produit remplacé par un nouveau modèle ? Prévenez-nous. » Ni phrase d'affiliation, ni « 100 % indépendant », ni date, ni durée de lecture.
5. Dans le top 5, les 5 produits ont le même format, le laurier sur la photo du n°1, et sous chaque note la pastille « Excellent », « Très bon »… (sur ordinateur). Juste avant le top 5, le « Point de vigilance » de l'ADEME s'affiche dans un encart jaune, avec une icône d'alerte devant le titre et la source en petit.
6. Sur téléphone, la page ne dépasse pas de l'écran : pas de défilement de côté.
7. Dans la FAQ, « Comment avons-nous établi ce classement ? » donne les mêmes chiffres que l'encadré.
8. Plus de pastille « Vérifié le … » au-dessus du titre. La photo est entre l'intro et l'encart « L'essentiel » (sauf étiquette « no featured »). L'intro est en 16 px sur ordinateur (15 px sur téléphone). Le fil d'Ariane est à 9 px du titre.

## Pas dans ce modèle (à faire à part)

- **Fiches produit (avis)** : `avis-hero.code.php` et `avis-content.code.php` contiennent le prix « à partir de X €/mois » des abonnements et une correction du carrousel de la marque. Ils se collent dans le modèle des fiches avis, pas ici.
- **Modèle comparatif simple (V1)** : les mêmes changements existent dans les blocs V1 (`hero-gauche`, `top5-resume`, `top5-tests`, `sommaire`). Ils ne servent que si ce modèle reste utilisé.

## Pour l'instance Templates

Les fichiers de ce dossier sont des **copies exactes** des fichiers de référence :
- `v2/multi-hero-gauche`, `v2/multi-sommaire`, `v2/multi-resume`, `v2/multi-tests`, `v2/multi-tableau` ;
- `hero-encart` et `faq`, partagés avec le V1.

Toute modification se fait dans le fichier de référence. Ensuite, on lance `python outils/verifier-multi-v3.py --recopier`, puis la même commande sans option, qui doit répondre « identique » pour les 14 fichiers.
