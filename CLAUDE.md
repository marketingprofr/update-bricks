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

**⚠️ Leçon (test réel) : les éléments granulaires se collent VIDES.** Un bloc
`code` `executeCode:false` (ex. une icône SVG seule) **n'apparaît pas dans la
Code review** → reste bloqué et **vide tout le sous-arbre** du conteneur (eyebrow,
byline, grille de stats, signaux… apparaissaient vides). De même, multiplier les
`text-basic`/`code` imbriqués est fragile.
**→ RÈGLE hero : reconstruire chaque section riche en UN SEUL bloc `code` PHP
autonome** (signable, donc rendu), avec **styles inline** + **SVG en chaîne HTML**
dans le `echo`. C'est le pattern qui marche (titre, chapô, ariane rendus OK).
Garder en natif seulement ce qui s'est avéré fiable : grille 2 col, photo
(image+badge), zone vote (heading + shortcode + texte). Classes existantes du site
réutilisées par ID (`bnxvav`→serif titre, `wyopqz`→lien chapô) : OK sans recompil.
**Après collage : approuver les blocs `code` dans la Code review Bricks.**
