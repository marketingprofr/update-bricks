# Passation : instance « SEO - Templates (meilleurtest) », en session locale

Tu reprends le travail de l'instance qui gère les **gabarits (templates) Bricks** de meilleurtest.fr. Elle tournait dans le cloud. Toi, tu tournes sur le poste de Samuel. Tu as donc accès au site, à son API REST, aux fichiers de `C:\Webdev\wp-avis\` et aux autres sessions locales.

Ce document résume ce qui a été fait, comment on travaille avec Samuel et ce qui reste en cours. **Le détail technique complet est dans `CLAUDE.md`, à la racine du dépôt** : lis-le en entier avant toute chose, et tiens-le à jour.

---

## 1. Règles non négociables

1. **Ne modifie rien sur le site sans l'accord explicite de Samuel** : ni post, ni champ, ni réglage, ni appel REST en écriture. Tu prépares le code, Samuel le colle et le valide. Les accès REST sont dans `C:\Webdev\wp-avis\config.json` : ne les affiche jamais, ne les committe jamais.
2. **Réponds en français, en phrases courtes et listes à puces.** Pas de jargon inutile.
3. **Pour chaque livraison :**
   - donne les **liens GitHub** des fichiers ;
   - dis exactement **quoi coller où** (onglet Code ou onglet CSS de quel élément) ;
   - indique les étapes d'après : approbation dans la Code review Bricks, purge du cache, contrôle.
4. **CSS : toujours des fichiers COMPLETS**, jamais un morceau à ajouter. C'est une demande ferme de Samuel.
5. **N'ajoute rien qui n'a pas été demandé** : pas d'icône, de flèche, de sur-titre ni d'encadré « en bonus ». Quand Samuel dicte une mise en forme, applique-la à la lettre. Le sommaire V2 a été raté trois fois pour cette raison.
6. **Ne te contredis pas d'un message à l'autre.** Si tu changes d'avis, dis-le explicitement et explique pourquoi. Samuel a très mal vécu des allers-retours, par exemple sur le snippet WPCodeBox ou sur un champ ACF créé puis abandonné.
7. **Teste avant de livrer** (banc d'essai, section 4) et dis ce que tu as vérifié. N'affirme jamais que « tout est en ordre » sans l'avoir constaté.
8. **Git :**
   - branche de travail : `claude/bricks-templates-html-update-i56041` ;
   - commits clairs, en français ;
   - push après chaque livraison ;
   - pas de PR sans demande.

## 2. Mise en route

```powershell
cd C:\Webdev
git clone https://github.com/marketingprofr/update-bricks.git   # si pas déjà fait
cd update-bricks
git fetch origin claude/bricks-templates-html-update-i56041
git checkout claude/bricks-templates-html-update-i56041
```

Lis ensuite :
- `CLAUDE.md` : la mémoire du projet ;
- `audits/estimation-gabarit-jev-2026-10-02.md` : le chantier en cours ;
- `outils/tests/README.md` : le banc d'essai.

Prérequis sur le poste : Git connecté à GitHub, PHP 8 en ligne de commande pour le banc d'essai, et, en option, Node avec Playwright pour les captures d'écran.

## 3. Le site et la façon de livrer

- **Stack :** WordPress + **Bricks 2.0.2** + framework **Advanced Themer** (variables `var(--at-*)`). Les champs ACF sont préfixés `mltv5_`.
- **Cache :** **FlyingPress + Cloudflare**. Il n'y a ni Breeze ni Varnish : les mentions « Varnish + Breeze » qui restent plus bas dans `CLAUDE.md` sont anciennes. Cloudflare garde le HTML de 1 à 5 jours. Contrôle toujours une page avec `?nocache=<horodatage>` après une purge.
- **Livraison d'un bloc :**
  1. Samuel colle le PHP dans l'onglet **Code** d'un élément Code Bricks, et le CSS dans l'onglet **CSS** du même élément.
  2. Il **approuve** le bloc dans la Code review : chaque modification invalide la signature.
  3. Le CSS collé reste **inerte** tant que le champ n'a pas été ré-enregistré (couper, coller, sauvegarder).
- **Données de la page :**
  - la fonction du site `get_all_template_variables($id)` fournit `top_avis_ids`, `introduction`, `type_de_produit_au_pluriel` et `au_singulier`, `masculinsfeminins`, `lalalesmeilleur`, `forcer_affichage_du_titre`, `heures_investies`, `avis_etudies`… ;
  - **ne jamais recopier ni modifier la logique du top 5** : elle vient du cache du site, et Samuel règle le top 5 par les notes.
- **Taxonomies :** type de produit = `post-type-produit` ; attributs = `post-type-attribut` ; catégorie = `category`.

## 4. Banc d'essai (faux WordPress)

`outils/tests/` exécute les blocs Code hors du site avec des données fictives.

- **Ce qu'il vérifie :** erreurs PHP, ID en double, ancres sans cible, JSON-LD, titres générés, dédoublonnage, non-régression entre V1 et V2.
- **Ce qu'il ne remplace pas :** la vérification sur une vraie page fraîche. Les données sont fictives, et le rendu Bricks, les plugins et le cache ne sont pas simulés.
- **Commandes :** voir le README. Les principales sont `php run-page.php`, `php run-h1.php` et `php run-compare.php`.

## 5. Ce qui a été construit (le détail est dans `CLAUDE.md`)

- **Gabarit V1 des comparatifs** : un bloc Code par section, dans `php-css/` :
  - en haut : hero gauche, encadré de confiance (`hero-encart`), sommaire ;
  - le classement : résumé top 5 (`top5-resume`), tests complets (`top5-tests`), tableau comparatif ;
  - le guide d'achat : critères, types, choix, marques, astuces, raisons, FAQ ;
  - la navigation : comparatifs similaires, guides similaires, index des comparatifs ;
  - le footer.

  Il faut aussi tenir compte du mode sombre, géré à 100 % par les variables AT.
- **Gabarit V2 « multi-comparatif »** (`php-css/v2/multi-*`) : un guide parent et ses variantes sur **une seule URL**. Il est en production : 8 des 10 comparatifs audités le 2026-10-01 l'utilisent. Décisions clés :
  - un sous-comparatif est un vrai `comparatif` **privé** ;
  - un seul champ ACF : `mltv5_sous_comparatifs` (Relation, IDs), dans le groupe « Multi-comparatif », visible avec l'étiquette `multi-comparatif` ;
  - **pas de snippet WPCodeBox** : le moteur `mtv2_plan()` est copié à l'identique dans les 5 blocs `multi-*` :
    - toute modification du moteur se recopie dans les 5 blocs ;
    - et la date de `mtv2_engine_version()` doit changer à chaque modification ;
  - tests complets dédoublonnés par ID et par ASIN ;
  - H1 « … : Guide ultime (N produits comparés) » et title « Meilleur {type} 2026 (N produits comparés) », avec N = le même compteur que l'encadré de confiance ;
  - tableau : titre « Tableau comparatif : les meilleurs {type} », numéros dans l'ordre des colonnes ;
  - sommaire : mise en forme dictée par Samuel (voir `CLAUDE.md`).
- **Outils :**
  - `php-css/outils/inventaire-multi.code.php` : bloc admin en lecture seule, qui classe les comparatifs (principal, variante, orphelin) et exporte des CSV ;
  - `php-css/outils/prompt-inventaire-multi.md` : la même analyse via l'API REST.

## 6. En cours : audit Jev (jev-seo)

- **Données :** branche `claude/verify-seo-skill-install-u596xu`, dossier `data/jev-comparatifs-2026-10-01/`. Le fichier `audit.json` contient les notes Jev par page et le texte que Jev a lu.
- **Estimation livrée, rien modifié sur le site :** `audits/estimation-gabarit-jev-2026-10-02.md`. Elle propose les modifications T1 à T11, classées par gain. Les priorités :
  1. ouverture « verdict d'abord » juste après le H1 ;
  2. encadré de confiance factuel ;
  3. une seule note des lecteurs, sans contradiction ;
  4. titres sans saut de niveau ;
  5. méta description de 155 caractères au plus.
- **Ce qui attend Samuel :**
  - le feu vert pour le lot 1 (T1 à T5) sur deux pages pilotes, climatiseur-mobile (V2) et matelas (V1) ;
  - le format unique de title (T7) ;
  - la structure des champs `mltv5_la_recherche_comparatif` et `mltv5_info_du_correcteur` ;
  - la création éventuelle de `mltv5_sources_comparatif`.
- **Toute modification doit être faite en V1 et en V2.**
- L'instance « SEO - Coordination audits Jev (meilleurtest) » va te transmettre ses consignes, et elle relancera l'audit après tes modifications et la purge du cache.

## 7. Partage des rôles entre instances

| Instance | Périmètre |
|---|---|
| **Toi (Templates)** | Structure et rendu des gabarits : blocs Code, CSS, titres, encadrés, données structurées produites par les blocs |
| SEO - Architecture des comparatifs | Types, attributs, multis, fusions, noms de produits, prix |
| Rédaction | Introductions, guides, biographies, sources |
| SEO - Coordination audits Jev | Lance jev-seo, consolide, répartit les actions |

## 8. Points ouverts

- **Étiquette dans le code :** faut-il aussi exiger l'étiquette `multi-comparatif` pour activer le V2 ? Pour l'instant, le champ rempli suffit.
- **À faire plus tard :**
  - redirections 301 des variantes fusionnées ;
  - exclusion des sous-comparatifs privés des listes et du sitemap ;
  - bascule du gabarit sur tous les comparatifs (conditions Bricks).
- **Mode sombre :** le header (`build-header.py`) a encore des couleurs codées en dur, et il faut une version claire du logo.
