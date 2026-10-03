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

Les autres éléments Code du modèle ne changent pas. Il n'y a rien à coller pour eux, notamment pour le tableau comparatif, le guide d'achat, les types, les marques, les astuces, « Pourquoi acheter », les comparatifs similaires et l'index des comparatifs.

## Si le modèle V3 est déjà collé

**Disposition validée le 3 octobre au soir** : titre → ligne auteur → intro qui commence par la réponse → un seul encart « L'essentiel en 30 secondes » avec les questions.

Si tu as déjà fait le collage précédent (ligne auteur, encart « L'essentiel » séparé…), recolle seulement :
- **1**, Code et CSS ;
- **2**, Code seulement (durée de lecture retirée).

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
| 1 | `$MT_REPONSE_INTRO` | `true` | L'intro commence par la réponse, par exemple « Midea PortaSplit 12000 BTU (9,0/10) est le meilleur climatiseur mobile en 2026, devant … Nous avons analysé 55 climatiseurs mobiles et retenu les 5 meilleurs. », puis le texte de la rédaction. |
| 1 | `$MT_VERDICT_SOUS_H1` | `false` | L'ancien encart séparé « L'essentiel » (4 phrases) n'est plus affiché. |
| 1 | `$MT_VOS_QUESTIONS` | `'apres_intro'` | Encart des questions juste après l'intro. `'sous_reponse'` le place sous la ligne auteur, `''` le retire. |
| 1 | `$MT_TITRE_QUESTIONS` | `'L’essentiel en 30 secondes'` | Titre de cet encart (test Jev : les trois titres se valent, celui-ci arrive en tête). `''` = sans titre. |
| 1 | `$MT_VERIFIE_PAR` | `'Samuel Petit'` | « Vérifié par Samuel Petit, responsable éditorial ». |
| 2 | `$MT_ENCADRE_REEL` | `true` | Encadré à vrais chiffres. |
| 2 | `$MT_LIGNES_CONFIANCE` | `false` | Retire les lignes « 100 % indépendant » et « Mis à jour le », qui répètent la phrase d'indépendance et la ligne auteur (test Jev neutre). `true` les remet. |
| 2 | `$MT_DUREE_LECTURE` | `false` | Retire « … min de lecture » (test Jev neutre). `true` la remet. |
| 2 | `$MT_TXT_AFFILIATION` | `''` | Plus de phrase d'affiliation dans l'encadré : elle va dans le bandeau du header (voir plus bas). |
| 2 | `$MT_SIGNALEMENT` | `true` | Ligne « Une erreur, ou un produit remplacé par un nouveau modèle ? Prévenez-nous. » vers `/signaler-une-erreur/`. |
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
2. L'intro commence par la réponse : « Midea PortaSplit 12000 BTU (9,0/10) est le meilleur climatiseur mobile en 2026, devant … Nous avons analysé 55 climatiseurs mobiles et retenu les 5 meilleurs. », puis le texte habituel.
3. Juste après l'intro, un seul encart « L'essentiel en 30 secondes » (titre en gras avec une petite icône) : 4 questions, chacune avec sa réponse d'une phrase et « Lire la réponse complète », qui descend à la question dans la FAQ et l'ouvre. En bas, « Voir les N questions de notre FAQ ».
4. Dans l'encadré de droite : les 4 cases, puis une liste à puces (« Nous avons consulté 27 sources, dont… », la phrase des critères, « Prévenez-nous »). Sous la liste, l'engagement d'indépendance signé. Ni phrase d'affiliation, ni « 100 % indépendant », ni date, ni durée de lecture.
5. Juste avant le top 5, le « Point de vigilance » de l'ADEME s'affiche, avec la source en petit.
6. Sur téléphone, la page ne dépasse pas de l'écran : pas de défilement de côté.
7. Dans la FAQ, « Comment avons-nous établi ce classement ? » donne les mêmes chiffres que l'encadré.

## Pas dans ce modèle (à faire à part)

- **Fiches produit (avis)** : `avis-hero.code.php` et `avis-content.code.php` contiennent le prix « à partir de X €/mois » des abonnements et une correction du carrousel de la marque. Ils se collent dans le modèle des fiches avis, pas ici.
- **Modèle comparatif simple (V1)** : les mêmes changements existent dans les blocs V1 (`hero-gauche`, `top5-resume`, `top5-tests`, `sommaire`). Ils ne servent que si ce modèle reste utilisé.

## Pour l'instance Templates

Les fichiers de ce dossier sont des **copies exactes** des fichiers de référence :
- `v2/multi-hero-gauche`, `v2/multi-sommaire`, `v2/multi-resume`, `v2/multi-tests` ;
- `hero-encart` et `faq`, partagés avec le V1.

Toute modification se fait dans le fichier de référence. Ensuite, on lance `python outils/verifier-multi-v3.py --recopier`, puis la même commande sans option, qui doit répondre « identique » pour les 12 fichiers.
