# Estimation : modifier le gabarit des comparatifs pour faire monter les notes de Jev

Rédigé le 2026-10-02 par l'instance « SEO-template (meilleurtest) ». **Rien n'a été modifié sur le site.** Toute modification attend l'accord de Samuel.

Destinataire : « SEO - Coordination audits Jev (meilleurtest) ». Aucune autre session n'était joignable au moment de l'envoi. Ce fichier sert donc de dépôt : `audits/estimation-gabarit-jev-2026-10-02.md` (branche `claude/bricks-templates-html-update-i56041`).

## 0. Sources et limites

- **Lu :** la copie GitHub de l'audit (`data/jev-comparatifs-2026-10-01/` : `audit.json`, `report.md`, `digest.md`) et le code des blocs du gabarit (`php-css/`, `php-css/v2/`).
- **Pas lu :** les fichiers de `C:\Webdev\wp-avis\` (rapport par URL, plan d'action G2…G12, tableau de coordination). Ils ne sont pas accessibles depuis le cloud. La correspondance avec les chantiers G2, G3, G4, G6, G7, G8, G9 et G12 reste donc à faire par la coordination. Chaque modification ci-dessous renvoie au numéro de constat du brief (§3).
- **Site injoignable depuis le cloud :** il répond 403 aux adresses IP hors pays francophones. Les chiffres viennent de ce que jev-seo a capturé le 2026-10-01.
- **Structure des 10 pages :** 8 sont déjà des multi-comparatifs (gabarit V2). Mutuelle santé et matelas sont en V1. Toute modification doit donc être faite dans les **deux** jeux de blocs (V1 `php-css/*.code.php` et V2 `php-css/v2/multi-*.code.php`).

## 1. Ce que Jev lit aujourd'hui : constat chiffré

### Notes par page (audit.json, échelle 0-3, sauf answer_first, de 0 à 1)

| Page | Utilité | Spécificité | Confiance | Ouverture | Citabilité | Title | Méta | H1 |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| banque-ligne | 2,49 | 2,29 | 2,00 | 0,58 | 2,98 | 2,83 | 2,51 | 0,98 |
| offre-box-internet | 2,52 | 2,13 | 1,91 | 0,15 | 2,98 | 2,61 | 1,12 | 0,98 |
| assurance-vie | 2,49 | 2,33 | 2,01 | 0,76 | 2,99 | 2,65 | 2,14 | 0,98 |
| vpn | 2,14 | 1,78 | 1,89 | 0,39 | 2,89 | 2,56 | 1,91 | 0,99 |
| mutuelle-sante | 2,15 | 2,19 | 1,89 | 0,49 | 2,91 | 2,61 | 1,45 | 0,99 |
| assurance-auto | 2,06 | 2,05 | 1,86 | 0,31 | 2,90 | 2,54 | 2,11 | 0,98 |
| forfait-mobile | 2,64 | 2,24 | 1,98 | 0,53 | 2,98 | 2,81 | 2,18 | 0,98 |
| climatiseur-mobile | 2,72 | 2,45 | 2,12 | 0,35 | 2,99 | 2,81 | 2,13 | 0,98 |
| matelas | 2,43 | 2,51 | 2,14 | 0,43 | 2,99 | 2,54 | 2,47 | 0,99 |
| pompe-a-chaleur | 2,47 | 2,19 | 1,99 | 0,31 | 2,97 | 2,64 | 2,38 | 0,98 |
| **Moyenne des 10** | **2,41** | **2,22** | **1,98** | **0,43** | **2,96** | **2,66** | **2,08** | **0,98** |

### Ce que le gabarit montre à Jev, dans l'ordre

1. **Environ 560 caractères avant le H1** :
   - bandeau « ✓ 0% pub… » ;
   - la liste des catégories du menu, **répétée 4 fois** (deux `nav-menu`, desktop et mobile, chacun avec sa variante), puis « Cadeaux » ;
   - la mention d'affiliation, le fil d'Ariane et « Vérifié le … ».

   Ces caractères sont pris sur les 6 000 que lit Jev.
2. **H1**, avec « Guide ultime » sur les multis : h1_fit 0,98, à garder.
3. **Les 500 caractères d'ouverture commencent tous par « Par Irfann • Mis à jour le … »**, puis par un préambule de l'introduction (« Vous cherchez une solution plus efficace… », « Depuis 1958, la loi oblige… »).
   - La phrase de verdict (« Revolut est, selon nous, le meilleur choix… », générée par `mt_intro_reco`) vient **après** l'introduction, à environ 700-900 caractères du H1, donc hors de la fenêtre d'ouverture.
   - D'où une ouverture à 0,43 en moyenne.
4. **L'encadré « Pourquoi nous faire confiance »** :
   - **12 heures et 132 avis sur 9 pages sur 10**, des valeurs par défaut : seul climatiseur-mobile affiche 19 h et 597 avis ;
   - « X banques en ligne **testées** » ;
   - aucun nom de relecteur, aucun lien vers l'auteur, aucune source ;
   - une phrase de méthode générique.
5. **« Voter 4.8 / 5. Votes : 271 Pas encore de note ! Soyez le premier à voter. »** : cette contradiction est lue telle quelle. Rate My Post envoie les deux états dans le HTML, et c'est son JavaScript qui en masque un.
6. **Sommaire**, puis l'**encart résumé**.

### Titres

- Ordre actuel : H1, puis H3 « Pourquoi nous faire confiance », puis H4 « Avis des lecteurs », puis H4 « Sommaire », puis H2 résumé.
- Dans les tests : des H5 « Points positifs / négatifs / À qui s'adresse » sous des H3.
- Un H3 caché (`display:none`) « Classement complet des … testés ».
- De 2 à 31 sauts de niveau par page. Trois des 25 titres lus par Jev sont gâchés par l'encadré, les votes et le sommaire.

### Performance (PageSpeed mobile, en labo)

- Scores de 45 à 67.
- **CLS de 0,32 à 0,36 sur 4 pages** (banque-ligne, vpn, matelas, pompe-à-chaleur) et de 0,09 sur 2 autres. À ce niveau, le CLS fait perdre presque tout son poids de 25 % dans le score Lighthouse.
- FCP et LCP autour de 5 s, avec un temps de blocage (TBT) quasi nul.
- Les données réelles du site sont bonnes.

## 2. Modifications proposées, classées par gain sur Jev puis par effort

Légende :
- « V1+V2 » = blocs des deux gabarits ;
- « Tous » = tous les comparatifs, puisque le gabarit est commun.

Effort : h = heures, j = jours, développement et tests sur le faux site compris, hors collage et validation dans Bricks.

| # | Modification | Critère Jev visé (actuel → visé) | Où | Pages | Effort | Risque |
|---|---|---|---|---|---|---|
| T1 | **Ouverture « verdict d'abord »** + ligne auteur déplacée | answer_first 0,43 → ≥ 0,85 ; spécificité et utilité +0,1 à 0,2 | PHP du bloc hero gauche (V1+V2) | Tous | 3-4 h | Faible |
| T2 | **Encadré de confiance factuel** (vrais chiffres, relecteur, lien auteur, méthode, sources) | confiance 1,98 → 2,6-2,8 ; spécificité +0,1 | PHP hero-encart (+ éventuellement 1 champ ACF « sources ») | Tous | 1 j | Faible à moyen (format des champs à confirmer) |
| T3 | **Note des lecteurs sans contradiction** | confiance (supprime un signal négatif) | PHP hero-encart (remplacer `[ratemypost]` en haut) | Tous | 1-2 h | Faible (garder un seul AggregateRating) |
| T4 | **Hiérarchie des titres** | JEV-003 sur les 10 pages ; libère 3 des 25 titres lus par Jev | PHP + CSS : hero-encart, sommaire, tests, résumé (V1+V2) | Tous | 3 h | Moyen (styles ciblés sur h3/h4/h5 à reporter) |
| T5 | **Méta description automatique ≤ 155 caractères**, tirée du verdict | meta_fit 2,08 → 2,6 ; JEV-011 | PHP hero gauche (écrit déjà `rank_math_description`) | Tous en mode auto | 1-2 h | Faible (ne touche que les descriptions automatiques) |
| T6 | **CLS et dimensions des images** | Lighthouse mobile +15 à 20 pts sur 4 pages ; JEV-009 | PHP résumé, tests, tableau, hero (width/height, places réservées) + diagnostic Lighthouse local | Tous | 4 h + diagnostic | Faible |
| T7 | **Un seul format de title** | title_fit 2,66 → 2,85 | PHP hero gauche V1 (le format V2 note déjà 2,81-2,83) | Tous | 1 h | Moyen (CTR : décision de Samuel) |
| T8 | **Accords et phrases générées** (ce/cette, testés/testées, prix, alt) | qualité perçue ; utilité et spécificité + | PHP tests, résumé, FAQ, tableau (V1+V2) | Tous | 4-6 h | Faible (dépend de l'instance Architecture) |
| T9 | **Sections de sous-comparatif allégées** (lignes compactes au lieu de 5 cartes complètes) | JEV-007 (de 499 à 977 Ko) ; performance | PHP + CSS multi-résumé | Multis | 1 j | Moyen (changement visuel, à valider sur maquette) |
| T10 | **Menu répété 4 fois dans le HTML** | budget de 6 000 caractères (~300 caractères récupérés) | Composant header Bricks | Tout le site | 0,5-1 j | Moyen (header de tout le site) |
| T11 | *(hors comparatifs)* **Accueil** : méta, og:title et og:image, H1 descriptif, phrase « qui sommes-nous » | entity_clarity 0,35 → 0,85 ; JEV-001, 006, 010, 012 | Gabarit accueil + Rank Math | Accueil | 3-4 h | Faible |
| — | « Guide ultime » dans le H1 | h1_fit déjà 0,98 | — | — | — | **Aucun changement recommandé** |

### Détail par modification

**T1. Ouverture « verdict d'abord »** (constat 4)
- Nouveau paragraphe `p.mt-verdict`, **juste après le H1** et avant tout le reste, en 2 phrases d'environ 250 à 330 caractères :
  1. le n°1 avec sa note, et le choix petit budget ou l'alternative ;
  2. le nombre de produits comparés et la base de la note.

  Exemple pour climatiseur-mobile : « Le **Midea PortaSplit 12000 BTU (8,8/10)** est le meilleur climatiseur mobile de 2026 ; le **Shinco SPK3S-07C** offre l'essentiel pour moins cher. Nous avons comparé et noté sur 10 **55 climatiseurs mobiles** et analysé **597 avis clients** pour établir ce classement. »
- Il réutilise le générateur existant `mt_intro_reco` (les formulations P1-P9) : **aucune logique du top 5 n'est touchée**, on lit `top_avis_ids` comme aujourd'hui. Sa phrase en fin d'introduction est supprimée, pour éviter le doublon.
- La **ligne auteur et date** passe après ce paragraphe. L'introduction de la rédaction (`mltv5_introduction`) ne change pas et suit.
- **Vérification :** dans le HTML frais, les 500 caractères qui suivent le H1 commencent par « Le … (x,x/10) est le meilleur … ».

**T2. Encadré de confiance factuel** (constat 2)
- **Chiffres réels :** lire `mltv5_la_recherche_comparatif` en priorité, et ne revenir aux valeurs actuelles (`$heures_investies` et `$avis_etudies` de `get_all_template_variables`) que si ce champ est vide.
  - **À confirmer :** la structure du champ (groupe ? quels sous-champs ?).
  - Sur banque-ligne, il contient 19 h et 597 avis, alors que la page affiche 12 et 132.
- **Libellé selon la nature :**
  - « banques en ligne **comparées** » ou « offres **analysées** » pour les services ;
  - « testés » ou « testées » pour les produits.

  Il faut un indicateur « service ou produit » par type. À défaut, la catégorie WordPress « Services » sert de repli, et l'instance Architecture peut fournir mieux.
- **Relecteur :** afficher `mltv5_info_du_correcteur` (« Relu par … »). Format du champ à confirmer.
- **Auteur :** nom cliquable vers sa page auteur (`get_author_posts_url`) et une ligne de biographie (description du profil WordPress).
  - S'il n'y a pas de biographie, ne rien inventer, et le signaler à Samuel.
  - Le schéma Article/Person de Rank Math doit pointer vers la même URL.
- **Méthode, en une phrase précise :** « Chaque {type} est noté sur 10 selon N critères (…) : voir notre méthode ». N et les critères viennent du répéteur `mltv5_criteres_de_choix` s'il est rempli ; sinon, la phrase reste générique, avec le lien.
- **Sources :** une ligne « Sources : … ».
  - Il n'existe aucun champ aujourd'hui. Je propose un champ ACF `mltv5_sources_comparatif` (texte court), à créer à la main par Samuel.
  - Tant qu'il est vide, la ligne n'est pas affichée.
- **Transparence :** garder « 100 % indépendant » et la mention d'affiliation.

**T3. Note des lecteurs** (constat 3)
- En haut de page, une seule ligne : « Note des lecteurs : **4,8/5** (271 votes) », lue dans les métadonnées de Rate My Post (`rmp_avg_rating`, `rmp_vote_count` : clés à vérifier). Elle n'apparaît que s'il y a au moins un vote.
- Le widget de vote interactif est déplacé plus bas (en fin de page ou après le tableau). Sinon, on retire du HTML le bloc « Pas encore de note » dès qu'il y a des votes.
- **Données structurées :** vérifier qu'il n'y a qu'un seul `AggregateRating` pour la page, celui de Rate My Post.

**T4. Titres** (constat 1)
- **Hors arborescence :** « Pourquoi nous faire confiance », « Avis des lecteurs » et « Sommaire » deviennent des `<p>` ou `<div>` stylés comme aujourd'hui, avec `aria-label` sur le `<nav>` du sommaire. Ce ne sont pas des sections de contenu.
- **Tests :** les H5 passent en H4 (sous le H3 du produit).
- **Résumé :** le H3 caché « Classement complet » devient un `<p>`.
- Contrôle aussi des parties du guide, déjà dans l'ordre H2, H3, H4.
- Résultat attendu : **0 saut de niveau** sur les comparatifs.
- Il faut livrer les CSS complets correspondants, puisque les sélecteurs h3, h4 et h5 changent.

**T5. Méta description** (constat 5)
- Aujourd'hui, `rank_math_description` reçoit `intro(50)`, soit 50 mots, ce qui donne 281 à 343 caractères.
- Remplacement par une phrase de 140 à 155 caractères, coupée au mot : « Comparatif {année} : {n°1} ({note}/10) devance {n°2} et {n°3}. {N} {type} notés, {avis} avis clients analysés. »
- La condition d'écriture actuelle ne change pas (`template_description == 0`), donc les descriptions écrites à la main restent intactes.

**T6. CLS et images** (constat 10)
- Ajouter `width` et `height` à toutes les `<img>` générées :
  - vignettes du résumé, du tableau et des tests (dimensions de la pièce jointe, ou carré fixe pour les URL externes) ;
  - badge du hero.
- Réserver la hauteur des zones qui changent après chargement : widget de note, barre de tri.
- **Avant de coder :** faire faire à l'instance locale un passage Lighthouse avec « Layout shift culprits » sur banque-ligne et matelas, pour nommer l'élément qui bouge. Le JSON de l'audit ne le dit pas.
- **Gain :** un CLS de 0,35 à moins de 0,1 rapporte environ 15 à 20 points de score mobile sur ces 4 pages. FCP et LCP (environ 5 s en labo) dépendent surtout du CSS et des polices (FlyingPress) : ce n'est pas le gabarit.

**T7. Title** (constat 5)
- Deux formats coexistent, et le format V2 « Meilleure banque en ligne 2026 (22 produits comparés) » obtient de meilleures notes (2,81-2,83) que le format V1 « Les 5 meilleures … 2026 | Test par Meilleurtest » (2,54-2,65).
- Proposition : le format V2 pour **tous** les comparatifs, avec N = le même compteur que l'encadré.
- **Décision de Samuel.**
- Note : 5 des 8 multis audités (offre-box, assurance-vie, vpn, assurance-auto, pompe-à-chaleur) avaient encore l'ancien title, parce que le title n'est réécrit qu'au rendu de la page et que la page était en cache.

**T8. Accords et phrases générées** (constats 6 et 7)
- **Accords :**
  - « À qui s'adresse ce/cet/cette {type} » ;
  - « Classement complet des {type} testés/testées ».

  Ils sont déduits de `masculinsfeminins` et `lalalesmeilleur`. L'idéal reste un champ genre et nombre par type (instance Architecture).
- **Phrase de prix et question « budget » de la FAQ :**
  - seulement si au moins 2 prix sont supérieurs à 0, et si au moins la moitié des produits ont un prix ;
  - jamais pour un type « service ».

  Les prix à 0 et à -1 sont déjà ignorés, et aucune balise Offer n'est alors produite.
- **Nom affiché :** les `alt` et les phrases générées utilisent `mltv5_forcer_affichage_du_titre` quand il est rempli (110 fiches), comme les titres le font déjà dans le résumé.

**T9. Pages trop lourdes** (constat 9)
- Dans chaque section de sous-comparatif, remplacer les 5 cartes complètes par 5 **lignes compactes** : rang, H3 nom, note, verdict en une ligne, lien « Lire le test », bouton d'offre.
- Le test complet reste une seule fois dans « Tests complets ».
- Les ancres et l'ItemList JSON-LD (par `@id`) ne changent pas.
- Estimation : de -35 à -50 % de HTML sur les multis, à mesurer sur le faux site.
- C'est un **changement visuel** : je livre d'abord une maquette pour validation.

**T10. Menu en double** (constat 10, budget de lecture)
- Le texte du menu apparaît 4 fois avant le H1.
- Rendre le menu mobile uniquement à l'ouverture du burger (contenu chargé à la demande), ou éviter la double sortie des deux `nav-menu`.
- Le gain sur Jev est faible (environ 300 caractères). C'est un composant de tout le site : à faire en dernier.

**T11. Accueil** (constat 11)
- Ce n'est pas le gabarit comparatif, mais c'est le plus gros levier restant sur la catégorie « IA » : entity_clarity est une note **du site**, qui pèse un tiers de la part Jev.
- À faire :
  - une méta description ;
  - og:title et og:image (Rank Math) ;
  - un H1 qui dit ce qu'est le site (« Meilleurtest, comparatifs indépendants de produits et services depuis 2014 ») ;
  - une phrase de présentation : qui, quoi, pour qui, en France.
- **HSTS et en-têtes de sécurité :** c'est un réglage Cloudflare, donc pour l'infrastructure, pas pour le gabarit.

## 3. Proposition concrète pour le haut de page (ce que lit Jev)

L'ordre est celui du **HTML**. La colonne de droite peut garder sa place visuelle, puisque c'est déjà le cas aujourd'hui : le HTML de la colonne gauche passe avant celui de l'encadré.

```
[bandeau + menu + mention d'affiliation]      (inchangé ; T10 plus tard)
Fil d'Ariane · « Vérifié le … »                (inchangé)
H1  Les 5 meilleurs climatiseurs mobiles en 2026 : Guide ultime (55 produits comparés)
P   VERDICT (2 phrases, ~280 car.) : n°1 + note, alternative, N comparés, avis analysés      ← T1
P   Par Samuel Petit (lien page auteur) · Relu par {relecteur} · Mis à jour le 25 sept. 2026 ← T1/T2
DIV Introduction de la rédaction (mltv5_introduction, inchangée)
IMG Photo + badge (avec width/height)                                                       ← T6
ASIDE Encadré (titre en <p>, pas Hn)                                                        ← T2/T4
      19 h de recherche · 12+ ans · 55 climatiseurs mobiles testés · 597 avis clients analysés
      Méthode : chaque climatiseur mobile est noté sur 10 selon 7 critères (puissance, bruit…) → notre méthode
      Sources : {mltv5_sources_comparatif}           (masqué si vide)
      100 % indépendant, aucun produit sponsorisé ; liens d'affiliation expliqués
      Note des lecteurs : 4,8/5 (327 votes)          (une ligne, sans contradiction)         ← T3
NAV Sommaire (titre en <p>, pas Hn)                                                          ← T4
H2  Les 5 meilleurs climatiseurs mobiles en un coup d'œil
H3  produits…   (lignes compactes dans les sous-sections : T9)
```

**Titres attendus :**
- H1 ;
- puis H2 résumé, H3 produits ;
- puis un H2 par sous-comparatif, avec ses H3 produits ;
- puis H2 « Le test complet… », H3 produit et H4 points ;
- puis H2 tableau, H2 guide…

Aucun titre dans l'encadré, les votes ou le sommaire.

**Ouverture attendue (500 caractères après le H1) :** le verdict, les chiffres, la ligne auteur avec le relecteur, puis le début de l'introduction. Elle répond donc dès la première phrase.

## 4. Gain estimé sur les scores jev-seo

**Méthode :** j'ai reconstitué les formules de jev-seo, et elles redonnent exactement les scores actuels.
- **Contenu :** 0,7 × moyenne(utilité, spécificité, confiance) + 0,3 × règles. Ça donne 81, comme le rapport.
- **Performance :** 0,5 × Lighthouse mobile moyen (57,3) + 0,5 × observations. Ça donne 76, comme le rapport.
- **Global :** moyenne pondérée. Ça donne 88,4, comme le rapport.

Ce sont des estimations : Jev reste un modèle, et ses notes sont probabilistes.

| Catégorie | Actuel | Après T1-T9 (gabarit seul) | Après T1-T11 + HSTS (accueil et Cloudflare en plus) |
|---|---:|---:|---:|
| Contenu (utilité, spécificité, confiance) | 81 | ~87 | ~87 |
| Préparation IA (citabilité, ouverture, entity_clarity) | 67 | ~78 | ~90 |
| Balises (on-page) | 93 | ~95 | ~99 |
| Performance | 76 | ~81 | ~81 |
| Données structurées | 99 | 99 | 100 |
| Sécurité | 96 | 96 | 100 |
| **Global** | **88 (B)** | **~91-92** | **~94** |

**Hypothèses par critère (moyenne des 10 pages) :**
- **Ouverture :** de 0,43 à 0,85. C'est le gain le plus sûr, parce qu'il dépend d'un texte que l'on contrôle.
- **Confiance :** de 1,98 à environ 2,6. Le 3/3 suppose des sources réellement citées et un relecteur nommé : il dépend des données que Samuel peut fournir.
- **Spécificité :** de 2,22 à environ 2,45.
- **Utilité :** de 2,41 à environ 2,55.
- **Méta :** de 2,08 à environ 2,6.
- **Title :** de 2,66 à environ 2,85, si T7 est validé.
- **Lighthouse mobile :** de 57 à environ 64 en moyenne, grâce au CLS des 4 pages concernées.

## 5. Dépendances et partage des rôles

- **Architecture :**
  - prix fictifs passés à 0, en cours ; T8 en tient déjà compte ;
  - indicateur « service ou produit » et genre/nombre par type (T2, T8) ;
  - un sous-comparatif qui pointe vers un autre type est déjà signalé dans le panneau jaune.
- **Rédaction :**
  - les introductions restent les leurs, et T1 les fait seulement passer après le verdict ;
  - les biographies d'auteurs et les sources (T2) sont à rédiger si on veut viser 3/3 en confiance.
- **Samuel :**
  - structure de `mltv5_la_recherche_comparatif` et de `mltv5_info_du_correcteur` ;
  - création éventuelle de `mltv5_sources_comparatif` ;
  - choix du format de title (T7) ;
  - validation de la maquette T9.
- **Infrastructure :** HSTS et en-têtes de sécurité (Cloudflare).

## 6. Déploiement et vérification proposés

1. **Lot 1 (environ 1,5 jour, sans risque visuel) :** T1, T2, T3, T4, T5.
   - Test sur le faux site, puis collage sur **un** multi pilote (climatiseur-mobile) et **une** page V1 (matelas).
   - Ensuite, Code review Bricks, purge de FlyingPress et de Cloudflare, puis contrôle avec `?nocache=<horodatage>`.
2. **Contrôle avant l'audit**, dans le HTML frais :
   - les 500 caractères qui suivent le H1 ;
   - la liste des 25 premiers titres ;
   - les chiffres de l'encadré par rapport aux champs ACF ;
   - une seule note des lecteurs ;
   - le Rich Results Test (AggregateRating, Article, FAQPage).
3. **Relance de jev-seo** sur les 10 pages par l'instance de coordination, puis comparaison critère par critère avec le tableau du §1.
4. **Lot 2 :** T6 (après le diagnostic du CLS), T7 (si validé) et T8.
5. **Lot 3 :** T9 (maquette d'abord), puis T10 et T11.

## 7. Décisions attendues de Samuel

1. Feu vert pour le lot 1 (T1 à T5) sur les deux pages pilotes ?
2. Format unique de title (T7) : « Meilleur {type} 2026 (N produits comparés) » pour tous ?
3. Structure de `mltv5_la_recherche_comparatif` et de `mltv5_info_du_correcteur`, et création de `mltv5_sources_comparatif` ?
4. Accord pour déplacer le widget de vote plus bas et garder une seule ligne de note en haut (T3) ?
