# Modèle multi-comparatif V3 : ce qu'il faut coller

Ce dossier contient tout ce qui change dans le modèle multi-comparatif par rapport à la version en ligne, qui est la V2. Il rassemble les décisions des 2 et 3 octobre 2026 :
- title et méta automatiques ;
- réponse courte sous le H1 ;
- ligne « Vérifié par » ;
- encart « Vos questions » ;
- encadré à vrais chiffres, avec affiliation et signalement ;
- point de vigilance ;
- prix par mois ou par an ;
- FAQ alignée sur l'encadré ;
- images du top 5 ;
- tri par prix.

Chaque élément Code du modèle a deux fichiers, avec le même numéro.

| N° | Élément Code du modèle (Bricks) | Onglet **Code** | Onglet **CSS** |
|---|---|---|---|
| 1 | Hero, colonne de gauche (fil d'Ariane, H1, réponse courte, « Vos questions », ligne auteur, intro) | `multi-v3-1-hero-gauche.code.php` | `multi-v3-1-hero-gauche.css` |
| 2 | Hero, colonne de droite (encadré « Pourquoi nous faire confiance ») | `multi-v3-2-encadre-confiance.code.php` | `multi-v3-2-encadre-confiance.css` |
| 3 | Sommaire | `multi-v3-3-sommaire.code.php` | `multi-v3-3-sommaire.css` |
| 4 | Résumé du top 5 et sections des sous-comparatifs | `multi-v3-4-resume-top5.code.php` | `multi-v3-4-resume-top5.css` |
| 5 | Tests complets | `multi-v3-5-tests-complets.code.php` | `multi-v3-5-tests-complets.css` |
| 6 | Questions fréquentes (FAQ) | `multi-v3-6-faq.code.php` | `multi-v3-6-faq.css` |

Les autres éléments Code du modèle ne changent pas. Il n'y a rien à coller pour eux, notamment pour le tableau comparatif, le guide d'achat, les types, les marques, les astuces, « Pourquoi acheter », les comparatifs similaires et l'index des comparatifs.

## Pas à pas

1. Dupliquez le modèle multi-comparatif dans Bricks (Modèles → … → Dupliquer). Donnez-lui un nom clair, par exemple « Multi-comparatif V3 ».
2. Ouvrez la copie dans l'éditeur Bricks.
3. Pour chaque ligne du tableau, de 1 à 6 :
   - cliquez sur l'élément Code concerné ;
   - remplacez tout le contenu de l'onglet **Code** par le fichier `.code.php`, en entier ;
   - remplacez tout le contenu de l'onglet **CSS** par le fichier `.css`, en entier. Les CSS sont toujours des fichiers complets ;
   - si Bricks demande de signer le code (Code review ou Signer), validez.
4. Enregistrez le modèle.
5. Prévisualisez un multi-comparatif avec ce modèle, par exemple le climatiseur mobile, et vérifiez la liste plus bas.
6. Quand tout est bon, appliquez le modèle aux multi-comparatifs. Videz ensuite le cache de FlyingPress puis celui de Cloudflare.

Sur GitHub, chaque fichier s'ouvre avec un bouton « Copier » (icône de deux carrés, en haut à droite du fichier). Il copie le fichier entier d'un coup.

## Réglages en tête des fichiers (déjà réglés pour la mise en ligne)

| Fichier | Réglage | Valeur | Effet |
|---|---|---|---|
| 1 | `$MT_VOS_QUESTIONS` | `'sous_reponse'` | Encart « Vos questions » juste après la réponse courte. `'apres_intro'` le place après l'intro, `''` le retire. |
| 1 | `$MT_VERDICT_SOUS_H1`, `$MT_H1_EGAL_TITLE` | `true` | Réponse courte sous le H1 ; le H1 reprend le title automatique. |
| 1 | `$MT_VERIFIE_PAR` | `'Samuel Petit'` | Ligne « Vérifié par Samuel Petit, responsable éditorial ». |
| 2 | `$MT_ENCADRE_REEL` | `true` | Encadré à vrais chiffres. |
| 2 | `$MT_TXT_AFFILIATION` | ta phrase finale | « Si vous achetez via nos liens… ». |
| 2 | `$MT_SIGNALEMENT` | `true` | Ligne « Une erreur, ou un produit remplacé par un nouveau modèle ? Prévenez-nous. » vers `/signaler-une-erreur/`. |
| 4 | `$MT_POINT_VIGILANCE` | `true` | Point de vigilance juste avant le top 5. Seuls les types de produit dont les champs sont remplis l'affichent : pour l'instant, le climatiseur mobile. |
| 6 | `$MT_METHODO_ENCADRE` | `true` | « Comment avons-nous établi ce classement ? » reprend les chiffres de l'encadré. |

Pour couper une fonction, passez son réglage à `false` (ou `''`) dans l'onglet Code, puis enregistrez.

## Avant d'appliquer le modèle

- **Formulaire de signalement** : dans Fluent Forms, les deux champs cachés doivent avoir le **Name Attribute** `source_id` et `source_url`, et pour valeur par défaut `{get.source_id}` et `{get.source_url}`. Sinon, les signalements arrivent sans la page d'origine.
- **CSS du formulaire** (hors modèle) : collez `php-css/formulaire-signalement.css` une seule fois dans Bricks → Paramètres → Code personnalisé → CSS personnalisé.

## À vérifier sur la page climatiseur mobile, après prévisualisation

1. Sous le H1, un paragraphe commence par « Réponse courte : » et donne les 3 premiers avec leur note.
2. Juste dessous, l'encart « Vos questions » montre 4 ou 5 questions et le lien « Toutes les réponses ».
3. La ligne auteur dit « Vérifié par Samuel Petit, responsable éditorial ».
4. Dans l'encadré de droite, on voit les 4 cases, la liste avec la phrase d'affiliation et la ligne « Prévenez-nous ».
5. Juste avant le top 5, le « Point de vigilance » de l'ADEME s'affiche, avec la source en petit.
6. Dans le top 5, le tri « Prix » ne met pas un produit sans prix en premier.
7. Dans la FAQ, « Comment avons-nous établi ce classement ? » donne les mêmes chiffres que l'encadré.

## Pas dans ce modèle (à faire à part)

- **Fiches produit (avis)** : `avis-hero.code.php` et `avis-content.code.php` contiennent le prix « à partir de X €/mois » des abonnements et une correction du carrousel de la marque. Ils se collent dans le modèle des fiches avis, pas ici.
- **Modèle comparatif simple (V1)** : les mêmes changements existent dans les blocs V1 (`hero-gauche`, `top5-resume`, `top5-tests`, `sommaire`). Ils ne servent que si ce modèle reste utilisé.

## Pour l'instance Templates

Les fichiers de ce dossier sont des **copies exactes** des fichiers de référence :
- `v2/multi-hero-gauche`, `v2/multi-sommaire`, `v2/multi-resume`, `v2/multi-tests` ;
- `hero-encart` et `faq`, partagés avec le V1.

Toute modification se fait dans le fichier de référence. Ensuite, on recopie ici et on lance `python outils/verifier-multi-v3.py`, qui doit répondre « identique » pour les 12 fichiers.
