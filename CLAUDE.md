# Projet — Mise à jour de templates Bricks (meilleurtest.fr)

Mémoire de projet : mettre à jour des templates **Bricks Builder** à partir de
designs HTML, en construisant des éléments **natifs et fonctionnels** (pas de
bloc de code statique).

## Stack du site

- **WordPress + Bricks Builder `2.0.2`**, hébergé sur **Cloudways** (cache
  **Varnish + Breeze** → à purger pour voir les changements en front-end).
- Framework **Advanced Themer** : design tokens `--at-*`
  (ex. `var(--at-space--s/m)`, `var(--at-text--s)`, `var(--at-white)`…).
  ⚠️ Ces tokens (spacing/typo) sont des `clamp()` parfois **gros** → pour coller
  fidèlement à une maquette, préférer des **valeurs px explicites**.
- **Breakpoints** (suffixes de réglages Bricks pour ce site) :
  `tablet_portrait` ≤991px, `mobile_landscape` ≤767px, `mobile_portrait` ≤478px
  (+ `hover`). Cascade desktop-first (un petit breakpoint hérite du plus grand).
- Le **header** est un **Composant Bricks** (instance via `cid`). Menu WP des
  catégories = **id `13`**.

## Workflow : design HTML → Bricks

1. On génère un JSON au format **`bricksCopiedElements`**
   (`{content, source:"bricksCopiedElements", version:"2.0.2", globalClasses, globalElements}`).
2. Dans le builder : clic droit → **Paste** (colle les éléments). Bricks
   régénère les IDs.
3. Générateur versionné : **`build-header.py`** → produit
   `component-header-2026-updated.json`.

Pour récupérer la **vraie structure** d'un composant : ouvrir le composant dans
le builder, clic droit sur l'élément racine → **« Copy elements »**.
(Un export de template ne contient que l'instance du composant + les variables
du framework, PAS l'arbre interne.)

## ⚠️ Pièges Bricks appris (IMPORTANT)

1. **Réglages natifs vs CSS perso (LE point central) :**
   - Les **réglages natifs** (`_background`, `_padding`, `_margin`, `_display`,
     `_direction`, flex/grid, `_typography`, `menuBorder:hover`, visibilité
     responsive `_display:breakpoint`, etc.) → appliqués **immédiatement dès le
     collage**. Rien à « réveiller ». ✅
   - Le **CSS personnalisé** (`_cssCustom`) est **INERTE après collage** : il
     n'est compilé qu'après avoir **édité le champ** (chez ce client il faut
     souvent **couper/coller le contenu du champ CSS puis ré-enregistrer**).
     Chaque nouveau collage = nouveaux IDs = CSS de nouveau inerte.
   - **⚠️ Conséquence piège :** un CSS perso inerte peut **casser le layout
     indirectement**. Ex. vécu : le masquage des libellés d'icônes
     (`.lbl{display:none}`) non compilé → libellés visibles → boutons trop
     larges → passage à la ligne qui ressemblait à un empilement vertical. Ce
     n'était PAS un souci de `flex-direction` natif.
   - **→ RÈGLE : tout ce qui touche au layout (masquer/afficher, dimensions)
     doit être fait en NATIF** (visibilité responsive, éléments dédoublés
     icône-seule, etc.). Réserver le CSS perso à du purement cosmétique non
     structurel, et le minimiser au maximum.

2. **Classes globales : pas écrasées au re-collage.** Bricks les reconnaît par
   **ID** et garde l'ancienne définition. Pour itérer le style, soit **renommer**
   (nouveaux IDs), soit **supprimer** les anciennes d'abord. → **Préférer 100%
   inline (zéro classe globale)** pour que « ce qu'on colle = ce qu'on voit ».

3. **`block` Bricks = `flex-direction: column` par défaut.** Toujours mettre
   `_direction:"row"` explicitement pour un layout horizontal (sinon empilement
   vertical, notamment sur les overrides responsive).

4. **`container` = boxé** (max-width centré). Pour du pleine largeur → utiliser
   **`block`**.

   **→ Architecture à respecter : `section` (fond pleine largeur) > `wrapper`
   (max-width centré) > contenu.** La section/bande porte le **fond pleine
   largeur** (barre noire, bandeau vert…) ; un **wrapper interne** limite le
   contenu et le centre :
   `_widthMax:"var(--at-site-box-max-width)"` (= 1140px) + `_margin` left/right
   `auto` + `_width:"100%"`. La **mise en page (flex/grid) et le padding
   horizontal** vont sur le **wrapper**, pas sur la bande. Mettre un wrapper dès
   qu'une bande contient du contenu à borner. (Bordures basses : sur la bande =
   pleine largeur ; sur le wrapper = largeur du contenu.)

5. **CSS perso d'élément → utiliser `%root%`** (résolu par Bricks, survit à la
   régénération d'ID), JAMAIS `.brxe-<id>` (l'ID change au collage).

   **→ Astuce blocs `code` : styler en `style="…"` INLINE dans le HTML PHP**
   (attribut HTML, pas `_cssCustom`) → rendu **immédiat dès le collage**, jamais
   inerte. Idéal pour le markup généré en PHP (avatar arrondi, liens, etc.).
   Et **réutiliser les classes globales EXISTANTES du site par leur ID**
   (`bnxvav`, `wyopqz`, `uemizu`…) : reconnues, déjà compilées → leur CSS
   (`.titre-principal`, `.text-content`) marche **sans recompilation**.

   **⚠️ Signature des blocs `code` :** un `code` (PHP) collé avec un contenu
   modifié a une **nouvelle signature invalide** → Bricks demande de le
   **ré-approuver** (Code review / signature) avant exécution. Normal.

6. **Recherche (search) :** position de la loupe = pas de réglage natif →
   `%root% form{flex-direction:row-reverse}` (loupe à gauche). Pilule = natif
   (`_background`, `_border` radius 999, padding).

7. **Boutons (button) :** le framework AT ajoute une **ombre au survol**. Pour un
   lien icône+texte plat : `_background`/`_border`/`_boxShadow` transparents +
   `_boxShadow:hover` à zéro, en **natif**.

8. **Burger mobile dans l'en-tête :** le burger fait partie de l'élément
   `nav-menu`. Pour l'avoir dans la barre d'en-tête en mobile tout en gardant le
   menu en rangée séparée sur desktop → utiliser **deux `nav-menu`** (même menu
   13) : un desktop (rangée catégories, visible >991), un mobile (burger seul
   dans l'en-tête, `_display:none` + `_display:tablet_portrait:flex`).

## État du header (composant « Header 2026 »)

Structure cible (cf. `template-header.html`) : barre d'utilité noire → rangée
principale (logo | recherche | actions Favoris/Connexion/Cadeaux) → rangée
catégories (menu 13) → bandeau de confiance vert. Responsive : ≤991 burger +
recherche pleine largeur ; <768 burger | logo | icônes (sans libellé).

Palette maquette : ink `#14181d`, ink2 `#2a3038`, muted `#6b7480`,
line `#e8eaed`, bg pilule `#f5f6f8`, accent `#0f6b54`, accent-soft `#e5f1ec`,
texte barre noire `#cfd4d9`, check vert `#6dd6a8`.

Logo réel = image `merrilowgo.png` (id `245022`). Boutons : Favoris postId
`144254`, Connexion postId `144228`, Cadeaux = lien à définir.

À partir de juin 2026, le calage fin se fait **à la main dans le builder**
(le cycle collage↔régénération CSS étant trop lent pour les micro-ajustements).

## État du hero (cf. `template-hero.html`)

Générateur : **`build-hero.py`** → `component-hero-2026-updated.json`. Périmètre =
**hero seul** (les blocs hors maquette — sélection produits, TOC, favoris, partage,
méthodo — ont été **abandonnés** sur décision client).

Structure : `section > container` (boxé) → fil d'ariane (`rank_math`) → grille
**2 colonnes** `minmax(0,1fr) 350px` (≤991 → 1 col) :
- **gauche** : eyebrow (pill « Vérifié » + date modif) → H1 (code titre + effets
  SEO `update_post_meta`, classes `bnxvav`/`uemizu`) → byline (auteur+avatar+date)
  → chapô (`$introduction`, classes `wyopqz`/`vrjaxu`) → photo (`{featured_image}`
  + badge comparatif) ;
- **droite** : carte « Notre enquête » sticky = grille **2×2** native (4 stats :
  `$heures_investies`, `$sources_consultees`, `$produits_analyses`,
  `$avis_etudies`) → 3 signaux de confiance (indépendance / date / temps de
  lecture calculé) → note lecteurs `[ratemypost]`.

**⚠️ Règle de méthode (validée client) : NE PAS émietter en multiples petits
blocs `code`.** Deux options propres, jamais l'entre-deux :
- soit **un (ou quelques) GROS bloc(s) `code`** PHP autonome(s) ;
- soit des **éléments natifs Bricks spécifiques** (`heading`, `text-basic`,
  `image`, `icon`, `shortcode`, `block`/`grid`…).

Piège vérifié : une **icône SVG en bloc `code` `executeCode:false`** n'apparaît
PAS dans la Code review → reste bloquée et **vide tout le sous-arbre** parent.

**⚠️ Icônes : NE PAS utiliser l'élément `icon` Font Awesome.** Sous Advanced
Themer, Bricks 2.0 rend les icônes FA en **SVG inline**, et le SVG inline est
**traité comme du code à signer** → les éléments « sautent » au collage.
**→ Mettre Font Awesome en `<i class="fas fa-...">` (CSS) DANS un `text-basic`
natif** (couleur/taille via `_typography` natif). FA est déjà chargé sur le site
(cf. `<i class="far fa-heart">` du bouton favoris) → rendu immédiat, **rien à
signer, pas de SVG**.

**Architecture hero retenue :**
- **Colonne gauche = 1 seul gros bloc `code`** (breadcrumb inclus) : ariane +
  eyebrow + titre(+SEO) + byline + chapô + photo, en **styles inline**. L'élément
  code porte les classes globales **`bnxvav`/`wyopqz`** pour émettre leur CSS
  (`.titre-principal` serif, `.text-content`) sans recompilation.
- **Colonne droite (encart) = 100 % natif** : `block` grid 2×2, `heading`,
  `text-basic` (icônes FA en `<i class="fas">` dedans, pas d'élément `icon`),
  `shortcode [ratemypost]`. Valeurs dynamiques via **dynamic data** :
  `{post_modified_date}`, `{post_reading_time}` (natifs) et `{acf_*}` pour les
  4 stats (vérifier les vrais noms de champs).

**Après collage : approuver le bloc `code` gauche dans la Code review Bricks.**

## État du sommaire (« Sur cette page » + encart Lecture)

Livrables : **`php-css/sommaire.code.php`** (onglet Code = markup + JS) +
**`php-css/sommaire.css`** (onglet CSS). UN seul élément Code Bricks. Approche
retenue = **liste fixe conditionnelle pilotée en PHP** (PAS un scan des H2).

Sections (ordre figé) — chacune affichée selon un champ ACF, et pointant vers
un **`id` HTML d'ancre à poser sur le wrapper de section dans Bricks** :

| Section | Condition (ACF) | `id` d'ancre à poser |
|---|---|---|
| Notre sélection | toujours | `mt-top5-title` *(existe déjà = `<h2>` du top5)* |
| Tests complets | toujours | `partie-tests-complets` |
| Tableau comparatif | toujours | `partie-tableau-comparatif` |
| Guide d'achat {type pluriel} | `mltv5_cached_id_criteres` | `partie-guide-achat` |
| Quel type choisir ? | `mltv5_cached_id_types` | `partie-types` |
| Quelle marque choisir ? | `mltv5_cached_id_marques` | `partie-marques` |
| Astuces et conseils | `mltv5_cached_id_astuces` | `partie-astuces` |
| Pourquoi acheter ? | `mltv5_cached_id_raisons` | `partie-raisons` |
| Questions fréquentes | `mltv5_cached_id_faq` | `partie-faq` |

⚠️ **Préfixe `partie-` obligatoire** (≠ des sections Bricks pleine largeur).
**TODO quand on construira ces parties : poser ces `id` sur chaque section**,
sinon les liens du sommaire et le scrollspy ne s'accrochent à rien.

- **`CONTENT_SELECTOR = '.contenu-principal'`** : classe à mettre sur **toutes**
  les colonnes de contenu (le texte est coupé par le tableau pleine page).
- **Temps de lecture** : base **10 min** = Notre sélection + Tests complets +
  Tableau comparatif ; **+2 min** par section supplémentaire présente.
- **Jauge pondérée par section** (pas linéaire au scroll) : chaque section porte
  `data-min` = minutes cumulées à son début ; le JS interpole entre jalons →
  « 10 min » pile au début de Guide d'achat. Repli scroll plein page tant que
  les sections `partie-*` / `.contenu-principal` n'existent pas encore.
- **Sticky** : porté par `%root%` (wrapper Bricks) ET `.mt-toc`. ⚠️ **Piège
  résolu** : pour que le sticky « voyage », la colonne gauche doit être plus
  haute que l'aside → mettre **`align-items: stretch`** sur le wrapper des 2
  colonnes (sinon la colonne épouse la hauteur du sommaire = aucune marge pour
  coller). Vérifier aussi qu'aucun parent n'a `overflow:hidden/auto`.
- Accent sur **`--at-primary`** (cohérent top5). `HEADER_OFFSET = 30` (pas de
  header sticky sur le site).

## État des « tests complets » (avis détaillés — cf. `template-top5-tests.html`)

Livrables : **`php-css/top5-tests.code.php`** (onglet Code = markup PHP + boucle)
+ **`php-css/top5-tests.css`** (onglet CSS). UN seul élément Code Bricks.
Version éditoriale « magazine » (scope `.ed-a`), **un article par produit**.

- **Sourcing des IDs identique au top5-resume / build-hero** :
  `get_all_template_variables($page_id)['top_avis_ids']` (liste ordonnée),
  fallback `mltv5_best_products`. **Helpers partagés** avec `top5-resume.code.php`
  (`mt5_num`, `mt5_merchant_name`, `mt5_join_et`, `mt5_points`) → tous protégés
  par `function_exists` (les deux blocs cohabitent sans redéclaration).
- **Ancre par produit = `id="produit-n-{rang}"`** → cible des liens « Lire l'avis
  complet » du top5-resume (`#produit-n-1`…). Cohérence cross-section.
- **Ancre de section** : la `<section class="ed-a contenu-principal"
  id="partie-tests-complets">` porte **déjà** l'`id` du sommaire **et** la classe
  `.contenu-principal` (jauge de lecture) → rien à reposer en natif pour cette
  partie.
- **Champs** : identité (`mltv5_marque/modele_du_produit`, `mltv5_sous_titre`,
  `mltv5_resume_produit`), score `mltv5_score_recent` (via
  `get_acf_score_divided_by_10` + `get_acf_score_label`), avis clients
  `mltv5_score_avis_clients` /5 + `mltv5_nombre_avis_clients`, points +/-
  (répéteurs `mltv5_points_positifs/negatifs_produit`), offres (ASIN Amazon
  prioritaire puis `mltv5_lien/texte_du_bouton_1..3`).
  **Corps de l'avis = `post_content`** du produit (filtré `the_content`) ; le 1er
  paragraphe reçoit chapô + lettrine **en CSS** (pas de classe à poser).
- **Champs à confirmer côté site** (constantes en tête du fichier, sections
  masquées si vides) : `mltv5_verdict_court` (libellé récompense eyebrow),
  `mltv5_public_cible` (encart « À qui ça s'adresse »), répéteur fiche technique
  `mltv5_caracteristiques_du_produit` (sous-clés `_intitule`/`_valeur` ;
  `mt5_specs` retombe sur les 2 premières valeurs scalaires si les sous-clés
  diffèrent).
- **Typo** : design serif (Source Serif) pour titres/nom/chapô/corps, Inter pour
  labels/points/specs. `!important` sur les familles Inter des titres internes
  (h2/h3/h4 du `post_content`, h5) pour battre le serif du thème.
- **Images produit en `mix-blend-mode: multiply`** (règle récurrente du site :
  laisser transparaître le fond gris clair). ⚠️ **Piège vérifié** : si le
  conteneur de l'image crée un **contexte d'empilement** (`position` + `z-index`),
  le multiply se mélange avec le fond de CE conteneur, pas avec le gris d'un
  parent → mettre le **fond gris directement sur le conteneur de l'image**
  (`.ph-img { background: var(--bg-3) }`), sinon le gris ne transparaît jamais.
  (Invisible en maquette tant qu'on a un placeholder ; n'apparaît qu'avec une
  vraie image.)

## État du tableau comparatif (cf. `template-tableau-comparatif.html`)

Livrables : **`php-css/tableau-comparatif.code.php`** (onglet Code = markup PHP +
boucle) + **`php-css/tableau-comparatif.css`** (onglet CSS). UN seul élément Code
Bricks. Refonte éditoriale (scope `.mt-cmp-root`) de l'ancien tableau de prod
« TechGearLab » : même palette/typo que hero/resume/tests (Source Serif titres,
Inter corps, accent `--at-primary-*`).

- **Sourcing des IDs identique** à top5-resume / top5-tests :
  `get_all_template_variables($page_id)['top_avis_ids']`, fallback
  `mltv5_best_products`, **passe 1 `setup_postdata`** (contexte post requis pour
  `get_acf_score_divided_by_10` / `get_acf_score_label` / `get_default_product_label`).
  ⚠️ On a **abandonné l'ancienne priorité `produits_comparatif`** pour que les
  ancres `#produit-n-{rang}` restent cohérentes avec resume/tests. Helpers
  partagés (`mt5_num`, `mt5_merchant_name`, `mt5_join_et`, `mt5_points`) +
  `mtc_score_label` (fallback libellé), tous `function_exists`.
- **Ancre de section** : la `<section class="mt-cmp-root"
  id="partie-tableau-comparatif">` porte déjà l'`id` du sommaire. **Pas** de
  `.contenu-principal` ici : le tableau est pleine page, il *coupe* le contenu, ce
  n'est pas une colonne de texte de la jauge. Nom produit + vignette = liens vers
  `#produit-n-{rang}` (avis détaillé) ; bouton « Voir l'offre » = lien affilié.
- **Caractéristiques techniques (le cœur de la demande client)** : seules les
  specs **partagées par ≥ 3 produits** sont affichées (`$TC_SPEC_MIN_SHARE = 3` ;
  l'ancien tableau était à 2). Collecte keyée par intitulé + compteur de partage ;
  ordre = **première apparition** (plus lisible que tri par fréquence). Au-delà de
  10 specs → repliées derrière un bouton « afficher plus » (JS vanilla, `data-uid`).
- ⚠️ **Sous-clés specs éprouvées** (issues de l'ancien tableau de PROD, donc
  fiables) : repeater `mltv5_caracteristiques_du_produit`, intitulé
  `mltv5_caracteristique_produit`, valeur `mltv5_valeur_caracteristique_produit`.
  **Divergent des noms devinés dans `top5-tests.code.php`**
  (`_caracteristique_intitule`/`_caracteristique_valeur`) → si la fiche technique
  n'apparaît pas dans les tests complets, recaler ces deux sous-clés sur les noms
  ci-dessus.
- **Lignes conditionnelles** : verdict / résumé / positif / négatif / offres ne
  s'affichent que si ≥ 1 produit a la donnée (pas de rangée vide). Specs absentes
  d'un produit → cellule `—`.
- **Images en `mix-blend-mode: multiply`** sur fond gris porté par `.product-thumb`
  (même règle que partout), avec **`padding:12px`** pour ne pas coller au bord gris.
- **Layout colonnes** : `table-layout:fixed` → 1re colonne (labels) fixe **160px**
  (font 11px), toutes les **colonnes produits à largeur égale et ≥ 190px**. La
  largeur mini de la table (`160 + nb×190`) est posée **en inline** sur `<table>`
  (le `nb` est dynamique) → les colonnes ne descendent jamais sous 190px, sinon
  scroll. **1re colonne `position:sticky; left:0`** (sauf `.section-head`) : les
  libellés restent visibles pendant le scroll horizontal mobile. Indice
  « glisser pour comparer » + scrollbar visibles ≤767px.
- **Banderoles « rubans »** : collées **en haut de la `.pos-cell`** (pleine largeur,
  petite flèche `::after` vers le bas), dans une `.banner-slot` de **hauteur fixe
  rendue dans TOUTES les colonnes** (slot vide si pas de ruban) → les médailles
  restent **alignées verticalement**. « ★ Meilleur choix » (rang 1, primary) +
  « € Meilleur prix » (moins cher hors rang 1, bleu) ; sans prix → « ♥ Meilleure
  alternative » (rang 2, orange).
- **Rang = médaille** (double cercle via `box-shadow inset` + dégradé radial) :
  or (r1) / argent (r2) / bronze (r3) / gris clair (r4-r5).
- **1re colonne** = laurier `.t5-laurel` (réutilisé du resume mais **recoloré en
  primary via `mask`**, pas gold) + mention « aucun produit sponsorisé ».
- **Note globale monochrome** (classe `.sc-{p/g/y/o/r}`) : ≥9 primary, ≥8 vert,
  ≥7 jaune, ≥6 orange, sinon rouge (jauge + chiffre + libellé, **centrés**). Track
  de jauge assombri (`--track:#d8dde3`) pour la visibilité.
- **Verdict** = texte « quote » serif italique primary (`«  »`), pas de bouton.
- **Ligne « Avis clients »** (note `mltv5_score_avis_clients` /5 + nombre
  `mltv5_nombre_avis_clients`, helper `mt5_reviews_label`) — masquée si aucun avis.
- **Points +/-** = texte coloré (vert sombre / rouge sombre) + **puces `•` de
  séparation** (inline). Les **icônes FA des cellules specs** (`fa-check` /
  `fa-xmark` issues des valeurs ACF) sont recolorées vert / rouge.
- **Ligne d'achat** = bouton primary « Voir l'offre » + marchands sous le bouton,
  **sans prix affiché**. Label conditionnel : **« Où l'acheter »** si ≥ 1 produit a
  un prix ACF, sinon **« Meilleures offres »** (classements sans prix : séries…).
- **Nom produit centré** : marque uppercase grise (13px) au-dessus, modèle dessous.

## ⚠️ Convention typo/espacement commune aux parties du guide d'achat

Toutes les parties du guide (`criteres`, `types`, `choix`, `marques`, `astuces`,
`raisons`, `faq`) partagent **la même échelle de titres** (Source Serif 4, 600,
`--ink`), marges en **`em`** (auto-scaling responsive) :
- **h2** : `32px` / lh `1.15` / ls `-0.025em` — `margin: 1.5em 0 0.75em`
- **h3** : `26px` / lh `1.2` / ls `-0.015em` — `margin: 1em 0 0.5em`
- **h4** : `20px` / lh `1.2` / ls `-0.015em` — `margin: 0.5em 0 0.5em`
S'applique aussi aux titres internes des WYSIWYG (`.mt-*-desc/-body/-content/-a`).

**Marge entre chaque partie ≈ 30px** : portée par la **racine de scope**
(`.mt-guide`, `.mt-types`, `.mt-choix`, `.mt-marques`, `.mt-astuces`,
`.mt-raisons`, `.mt-faq`) via `margin-top: 30px`, avec `<scope> > :first-child {
margin-top: 0 }` pour neutraliser la marge haute du 1er élément. ⚠️ Les Code
elements du guide sont dans une **colonne Bricks flex avec `gap`** : le gap
s'additionne au `margin-top` (d'où 30px et non 50px). Ne pas confondre avec le
`scroll-margin-top` (inline via JS du sommaire, `HEADER_OFFSET = 30`) qui n'est
qu'un décalage d'ancre au clic, sans effet sur l'espacement visuel.

### Largeur de lecture : 700px centrés + `.breakout` (comme « tests complets »)

La colonne Bricks du guide fait **~954px**. Le contenu « normal » (texte) est
**borné à `700px` et centré** → `(954-700)/2 ≈ 127px` de marge de chaque côté
(même logique que `.ed-a-col { max-width:700px; margin:0 auto }` des tests
complets, où seule la figure image déborde). Mécanisme : sur chaque scope à
enfants directs (`types`, `marques`, `raisons`, `faq`, `astuces`) →
`<scope> > * { max-width:700px; margin-left/right:auto }`, placé **après** les
règles d'éléments (même spécificité → l'ordre source gagne).
- **Débordent en pleine largeur (954px)** : la grille VS de `choix`
  (`.mt-duel-grid`), les blocs d'astuces (`.mt-tips`), l'en-tête image+intro de
  `criteres` (`.mt-guide-lead`), et **tout élément `.breakout`** (classe à poser
  à la main ; `<scope> .breakout { max-width:none }`).
- **`criteres`** : pas de `> *` (structure à gouttière). La **colonne numéro
  passe à `97px`** + `gap 30px` = **127px** à gauche ; `.mt-crit-body` +
  `.mt-guide-h2--crit` bornés à `700px` → contenu centré (127px chaque côté),
  numéro dans la gouttière gauche. `choix` : bornage ciblé sur
  `.mt-duel-title/-intro/-verdict` (le contenu vit dans `.mt-duel`, pas en
  enfant direct du scope).

## État du guide d'achat « Critères de choix » (cf. `template-criteres.html`)

Livrables : **`php-css/criteres.code.php`** (onglet Code = markup PHP + boucle) +
**`php-css/criteres.css`** (onglet CSS). UN seul élément Code Bricks. Scope
`.mt-guide`. Refonte éditoriale de la maquette (intro + critères numérotés) avec
la palette/typo des autres sections (Source Serif titres, Inter corps, accent
`--at-primary-*`).

- **Source des données (ACF de la page courante, pas un post lié)** — les `cached_id`
  servent juste de condition d'affichage dans le sommaire ; le contenu vit
  directement sur la page :
  - GROUPE `mltv5_partie_criteres_de_choix` → `mltv5_image_criteres_de_choix`
    (image d'illustration) + `mltv5_introduction_criteres_de_choix` (chapô).
  - REPEATER `mltv5_criteres_de_choix` (1 ligne = 1 critère) →
    `mltv5_critere_de_choix` (titre) + `mltv5_description_du_critere` (description
    WYSIWYG). Numérotation = index de boucle (`01`, `02`… via `str_pad`).
- **Ancre de section** : la `<section class="mt-guide contenu-principal"
  id="partie-guide-achat">` porte **déjà** l'`id` du sommaire **et** la classe
  `.contenu-principal` (jauge de lecture) → rien à reposer en natif ici (≠ tableau
  comparatif qui est pleine page). Couvre le TODO « poser l'ancre `partie-guide-achat` ».
- **En-tête** : intro + image **côte à côte** (`grid minmax(0,1fr) 380px`, image
  en `aspect-ratio:4/3` `object-fit:cover`) → 1 colonne ≤991px. L'image vient des
  données (absente de la maquette) ; `.no-img` si vide.
- **Critères** : grille `92px | 1fr`, eyebrow mono « Critère » + gros numéro Inter
  `tabular-nums`, titre `h3` serif, description rendue en HTML. ≤767px → 1 colonne,
  numéro en ligne (label + chiffre côte à côte).
- **Description = WYSIWYG** rendu tel quel (HTML de confiance) ; repli `wpautop` si
  textarea sans balise de bloc. Pas de champ « À savoir » dédié (encart de la
  maquette **abandonné**) — mais un `<blockquote>` dans la description est stylé en
  encart accent au cas où l'éditeur en pose un.
- Helpers `mt_guide_img_url` / `mt_guide_img_alt` / `mt_guide_rich` (gèrent image
  ACF array|ID|URL), tous `function_exists`.

## État de « Quelle marque choisir ? » (cf. `template-marques.html`)

Livrables : **`php-css/marques.code.php`** (onglet Code = markup PHP + boucle +
JS onglets) + **`php-css/marques.css`** (onglet CSS). UN seul élément Code Bricks.
Scope `.mt-marques`. Reprend l'interaction de la maquette (grille de marques
cliquables → panneau détail), palette/typo des autres sections (Source Serif
titres, Inter corps, accent `--at-primary-*`).

- **Source** : REPEATER `mltv5_marques_comparatif` (1 ligne = 1 marque) →
  `mltv5_nom_de_la_marque` + `mltv5_description_de_la_marque` (WYSIWYG). **Pas**
  d'intro ni de méta « pays · segment » dans les données → ces éléments de la
  maquette sont **abandonnés** (carte = nom seul).
- **Lecture robuste** (comme critères) : page courante en priorité, **repli sur
  `mltv5_cached_id_marques`** (le contenu vit souvent sur le post lié). Diagnostic
  admin-only si rien trouvé.
- **Ancre** : `<section class="mt-marques contenu-principal" id="partie-marques">`
  porte l'`id` du sommaire + `.contenu-principal` (jauge de lecture). Couvre le
  TODO « poser l'ancre `partie-marques` ».
- **UI onglets accessibles** : `role=tablist/tab/tabpanel`, `aria-selected`,
  navigation clavier (flèches/Home/End), 1re marque active par défaut. Tous les
  panneaux sont rendus dans le DOM (WYSIWYG préservé), masqués via `hidden`.
- Helper `mt_guide_rich` partagé (guardé `function_exists`, cohabite avec le bloc
  critères). UID unique par instance (`$GLOBALS['mt_marques_uid']`) pour les ids
  tab/panel.
- **Responsive** : grille 4 → 3 (≤991px) → 2 colonnes (≤767px).

## État de « Astuces et conseils » (cf. `template-astuces.html`)

Livrables : **`php-css/astuces.code.php`** (onglet Code = markup PHP + boucle) +
**`php-css/astuces.css`** (onglet CSS). UN seul élément Code Bricks. Scope
`.mt-astuces`. Reprend l'esprit de la maquette (1 conseil vedette + astuces
compactes), palette/typo des autres sections.

- **Source** : intro `mltv5_introduction_astuces` (fallback + phrase d'appel
  communauté ajoutée en dur) + REPEATER `mltv5_astuces_comparatif` →
  `mltv5_titre_de_lastuce` + `mltv5_contenu_de_lastuce` (WYSIWYG).
- **Lecture robuste** : page courante → repli `mltv5_cached_id_astuces` (+ diag admin).
  L'intro est lue sur le **même** post source que le repeater (`$src_id`).
- **Ancre** : `<section class="mt-astuces contenu-principal" id="partie-astuces">`
  → ancre sommaire + jauge de lecture. Couvre le TODO « poser l'ancre `partie-astuces` ».
- **UI** : 1re astuce = carte **vedette** « Conseil principal » (contenu déplié) ;
  suivantes = **accordéon natif `<details>/<summary>`** (titre + flèche → contenu),
  donc accessible et **sans JS** (rien à re-signer). Astuce sans contenu = ligne
  simple non dépliable. Numérotation `02`, `03`… (la vedette = 1re).
- Helper `mt_guide_rich` partagé (`function_exists`). Responsive : paddings réduits ≤767px.

## État de « Pourquoi acheter ? » (cf. `template-raisons.html`)

Livrables : **`php-css/raisons.code.php`** + **`php-css/raisons.css`**. UN seul
élément Code Bricks. Scope `.mt-raisons`.

- **Source** : GROUPE `mltv5_partie_pourquoi_acheter` → `mltv5_titre_pourquoi_acheter`
  (titre) + `mltv5_image_pourquoi_acheter` (image) + `mltv5_raisons_acheter`
  (**TOUTES les raisons en un seul WYSIWYG**, pas de repeater).
- **Pas de cartes numérotées** (maquette = 8 raisons en grille, mais on n'a qu'un
  bloc unique) → décision client « fais au mieux » : on rend le **contenu riche
  stylé** + l'**image en illustration flottée à droite** (le texte l'enrobe ;
  mobile → image pleine largeur en haut). Les titres internes du WYSIWYG (1 par
  raison si posés) sont stylés serif.
- **Lecture robuste** : page courante → repli `mltv5_cached_id_raisons` (+ diag admin).
- **Ancre** : `<section class="mt-raisons contenu-principal" id="partie-raisons">`
  → ancre sommaire + jauge de lecture. Couvre le TODO « poser l'ancre `partie-raisons` ».
- Titre = champ `mltv5_titre_pourquoi_acheter` (repli « Pourquoi acheter ? »).
  Helpers partagés `mt_guide_rich`/`mt_guide_img_url`/`mt_guide_img_alt` (`function_exists`).

## État de la « FAQ » (cf. `template-faq.html`)

Livrables : **`php-css/faq.code.php`** + **`php-css/faq.css`**. UN seul élément
Code Bricks. Scope `.mt-faq`.

- **Source** : REPEATER `mltv5_faq_comparatif` → `mltv5_faq_comparatif_question`
  + `mltv5_faq_comparatif_reponse` (WYSIWYG). Lecture robuste page courante →
  repli `mltv5_cached_id_faq` (+ diag admin).
- **Standards web** :
  - **Accordéon natif `<details>/<summary>`** (1re question ouverte, icône +/−
    qui devient sombre à l'ouverture cf. maquette) → accessible, **sans JS**.
  - **Données structurées schema.org `FAQPage` en JSON-LD** (format recommandé
    Google). Réponses assainies par `wp_kses` (balises autorisées Google :
    h1-h6, br, ol/ul/li, a[href], p, div, b/strong, i/em). Encodage durci
    `JSON_HEX_TAG|JSON_HEX_AMP` (+`UNESCAPED_UNICODE`) → `<`/`>`/`&` échappés,
    `</script>` neutralisé, **pas de breakout** possible dans le `<script>`.
  - Une `Question` n'entre dans le schema que si elle a une réponse non vide
    (`acceptedAnswer` obligatoire). Question sans réponse = ligne simple visible,
    exclue du JSON-LD.
- **Ancre** : `<section class="mt-faq contenu-principal" id="partie-faq">` →
  ancre sommaire + jauge de lecture. Couvre le TODO « poser l'ancre `partie-faq` ».
- Intro statique : « Voici les questions les plus fréquemment posées par nos
  lecteurs et la communauté. »
- **Q/R AUTOMATIQUES en tête** (avant les manuelles), pensées **type-agnostiques**
  (smartphones, chaussures, huile d'olive, couches…) — ne s'affichent que si le
  guide a des produits (`top_avis_ids` → fallback `mltv5_best_products`, même
  sourcing + passe `setup_postdata` que resume/comparatif) :
  - **Q1** « Quel(le) est (le/la/les) meilleur(e)(s) {type} ? » : interrogatif +
    accord dérivés de **`lalalesmeilleur`** (« le meilleur »/« la meilleure »/
    « les meilleurs »/« les meilleures » → Quel est/Quelle est/Quels sont/Quelles
    sont, + nom singulier/pluriel assorti). Réponse = #1 du classement (note /10)
    + podium #2/#3. Repli si `lalalesmeilleur` vide.
  - **Q marques** (si ≥2 marques distinctes dans le top) : « Quelles sont les
    meilleures marques [de/d'] {type} ? » (élision gérée ; « marque » toujours
    féminin → accord safe). Réponse = marques distinctes du classement (ordre de
    rang, max 4) ; phrasé neutre, pas d'accord genré sur le type.
  - **Q2** = budget si **≥2 prix** (`mltv5_prix_indicatif` : fourchette + option la
    plus accessible ; article un/une/des dérivé du genre/nombre) → sinon avis
    clients si **≥2 notes** (`mltv5_score_avis_clients`) → sinon « confiance /
    indépendance » (générique).
  - **Q3** = méthodologie : stats `produits_analyses`/`avis_etudies`/
    `sources_consultees`/`heures_investies` si présentes (assemblées via
    `mt_faq_join_et`), sinon générique.
  - Tournures choisies pour **éviter les accords genrés piégeux**, normalisation
    des espaces, montants `number_format` + nbsp + €. Les 3 autos sont **incluses
    dans le JSON-LD FAQPage** (`name` re-décodé en texte brut pour le schema).

## État de « Quel type choisir ? » (types de produit — sans maquette)

Livrables : **`php-css/types.code.php`** + **`php-css/types.css`**. UN seul élément
Code Bricks. Scope `.mt-types`. Conçu from scratch (pas de template HTML), style
cohérent avec les autres sections.

- **Source** : GROUPE `mltv5_partie_types_de_produits` → `mltv5_introduction_types_de_produits`
  (intro, fallback « Voici les différents types de produits. ») + REPEATER
  `mltv5_types_de_produits` → `mltv5_type_de_produit` (nom), `mltv5_image_type_de_produit`,
  `mltv5_description_du_type_de_produit`, `mltv5_points_positifs_type_de_produit`,
  `mltv5_points_negatifs_type_de_produit`, `mltv5_pour_qui_est_ce_type_de_produit`
  (tous WYSIWYG sauf nom/image).
- **Lecture robuste** : page courante → repli `mltv5_cached_id_types` (+ diag admin).
- **Ancre** : `<section class="mt-types contenu-principal" id="partie-types">` →
  ancre sommaire + jauge de lecture. Couvre le TODO « poser l'ancre `partie-types` ».
- **Titre** : « Les {type pluriel} : quel type choisir ? ».
- **Carte par type** : image (gauche, `aspect-ratio 4/3`) + contenu (eyebrow « Type 01 »,
  `h3` serif, description) ; **Points forts / Points faibles** en 2 encarts vert/rouge
  (puces ✓/✕ sur les `<ul>`) ; encart accent **« Pour qui ? »**. ≤767px → 1 colonne.

## État de « Quel choix faire ? » (duels entre 2 alternatives — sans maquette)

Livrables : **`php-css/choix.code.php`** + **`php-css/choix.css`**. UN seul élément
Code Bricks. Scope `.mt-choix`. Conçu from scratch.

- **Source** : REPEATER `mltv5_choix_comparatif` (1 ligne = 1 duel) →
  `mltv5_choix_1_ou_choix_2` (titre du duel), `mltv5_introduction_choix_1_2`,
  `mltv5_titre_choix_1` + `mltv5_description_choix_1`, `mltv5_titre_choix_2` +
  `mltv5_description_choix_2`, `mltv5_verdict_choix_1_ou_choix_2`.
- **Lecture robuste** : page courante → repli `mltv5_cached_id_types` (même post
  source que les types ; + diag admin).
- **Layout** : par duel = titre serif + intro, puis grille **option 1 / badge VS /
  option 2** (`1fr auto 1fr`, empilée ≤767px), puis encart accent **« Notre verdict »**.
  Si une seule option présente → grille `single` (pleine largeur, sans VS).
- **Ancre** : `<section class="mt-choix contenu-principal" id="partie-choix">`.
  ⚠️ **Pas d'entrée dans le sommaire** (le sommaire ne liste pas « choix ») — à
  ajouter dans `sommaire.code.php` si on veut un lien TOC. `.contenu-principal`
  posée pour la jauge de lecture.

## État de « Comparatifs similaires » (cf. capture client, sans template HTML)

Livrables : **`php-css/similaires.code.php`** + **`php-css/similaires.css`**. UN
seul élément Code Bricks. Scope `.mt-similar`. Emplacement : **sous le tableau
comparatif, avant le guide d'achat**. Grille de pastilles cliquables vers les
comparatifs les plus proches, classés du plus proche au moins proche ; le
**comparatif courant est inclus en tête et accentué** (`.is-current`, non
cliquable, `aria-current="page"`).

- **Taxonomies (confirmées client)** : produit = `post-type-produit`, attributs =
  `post-type-attribut`, catégorie principale = `category` (WP standard). Slugs en
  **CONFIG en tête de fichier** (`$CS_TAX_*`).
- **Ordonnancement en ≤ 3 requêtes (2 WP_Query réelles)** :
  - **Requête A** — `tax_query` sur `post-type-produit` (mêmes termes produit) →
    ramène **paliers 2 & 3 d'un coup** ; départage en PHP : palier **2** si le
    candidat contient **tous** les attributs du courant (superset,
    `attr_shared === total`), sinon palier **3**.
  - **Requête B** — `tax_query` sur `category`, en excluant `array_keys($seen)`
    (palier **4**).
  - Palier **0** = comparatif courant, ajouté en tête. Termes attributs/catégories
    des candidats lus depuis le **cache d'objets** amorcé par WP_Query
    (`update_post_term_cache`, défaut) → **aucune requête en plus**.
- **Tri** : palier ↑ → nb attributs partagés ↓ → nb catégories partagées ↓ →
  **ID ↑** (départage à similarité égale : du plus petit au plus grand ID).
  **Dédoublonnage** par ID (`$seen`). **Coupe à `$CS_MAX = 20`** items
  (courant inclus). Section **masquée** si aucune recommandation.
- **Post type agnostique** : `get_post_type(get_the_ID())` → pas besoin du slug du
  CPT comparatif (la page courante EST un comparatif).
- **Libellé des pastilles** : titre du post par défaut ; CONFIG `$CS_LABEL_ACF`
  pour pointer un champ de libellé court si les titres sont trop longs.
- **Accent** = palette du site (`--at-primary-*`, vert), pas le bleu de la capture
  (simple référence de style). Ancre `id="partie-comparatifs-similaires"` (pas
  d'entrée sommaire, pas `.contenu-principal` : bloc de nav, pas une colonne de
  texte).

## Moteur de similarité partagé (`mt_sim_ranked_ids`)

Le classement des comparatifs proches est **factorisé** dans une fonction gardée
`function_exists` **`mt_sim_ranked_ids($cur_id, $opts)`**, incluse **à l'identique**
(byte-identical, vérifié) dans `similaires.code.php` ET `guides-similaires.code.php`
→ le 1er bloc chargé la définit, les autres réutilisent. Dépend de `mt_sim_term_ids`
(idem, gardée). Renvoie les **IDs** des comparatifs proches (courant **exclu**),
classés du plus proche au moins proche, en **≤ 2 WP_Query** (produit → paliers 2/3,
catégorie → palier 4 ; tri palier ↑ / attributs partagés ↓ / catégories ↓ / ID ↑).
`$opts` : `tax_product`, `tax_attr`, `tax_cat`, `max`.
- **Pastilles** (`similaires.code.php`) appelle avec `max = $CS_MAX - 1` (réserve la
  place du comparatif courant, ajouté en tête accentué) + libellés **nettoyés**
  (`mt_sim_clean_label` : strip « Les meilleur(e)s… » + capitale).
- **Grille** (`guides-similaires.code.php`) appelle avec `max = $GS_MAX` (courant
  exclu) + titres **complets**.

## État de « Tous les guides {type} » (grille — cf. `template-guides-similaires.html`)

Livrables : **`php-css/guides-similaires.code.php`** + **`php-css/guides-similaires.css`**.
UN seul élément Code Bricks. Scope `.mt-gsim`. Même moteur de similarité que les
pastilles, mais présentation **cartes** (vignette image à la une + titre complet +
extrait). Comparatif **courant exclu** (« N autres guides »).

- **Titre** : « Tous les guides {`type_de_produit_au_pluriel`} » (via
  `get_all_template_variables`), repli « Guides similaires ». **Sous-titre** :
  « N autre(s) guide(s) plus spécifique(s) pour affiner votre choix » (accord auto).
- **Vignette** : `get_the_post_thumbnail_url($pid,'medium')` ; si absente →
  placeholder texturé (`.is-empty::after`, rayures diagonales comme la maquette).
- **Extrait** : `mt_gsim_excerpt` (champ ACF `$GS_DESC_ACF` si fourni, sinon extrait
  WP manuel, sinon début du contenu) tronqué à `$GS_DESC_WORDS = 22` mots, sans
  HTML/shortcodes. Carte entière cliquable (`<a>`).
- **Bande** pleine largeur fond gris (`--bg-2`) + filet haut + padding — placer dans
  une section pleine largeur pour le rendu maquette. Grille 4 → 3 (≤991) → 2 (≤767)
  → 1 (≤420). Ancre `id="partie-guides-similaires"` (bloc de nav, hors sommaire,
  pas `.contenu-principal`). `$GS_MAX = 20` guides.
