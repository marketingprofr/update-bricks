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

1. **Réglages natifs vs CSS perso :**
   - Les **réglages natifs** (`_background`, `_padding`, `_margin`, `_display`,
     `_direction`, flex/grid, `_typography`, `menuBorder:hover`, visibilité
     responsive `_display:breakpoint`, etc.) → appliqués **dès le collage**.
   - Le **CSS personnalisé** (`_cssCustom`) est **INERTE après collage** : il
     n'est compilé qu'après avoir édité le champ, ou **Save + recharge**
     (+ purge de cache). Chaque nouveau collage = nouveaux IDs = CSS de nouveau
     inerte. **→ Minimiser le CSS perso, tout faire en natif si possible.**
   - Chez ce client, même après Save, il faut souvent **couper/coller le champ
     CSS puis ré-enregistrer** pour que ça « prenne ».

2. **Classes globales : pas écrasées au re-collage.** Bricks les reconnaît par
   **ID** et garde l'ancienne définition. Pour itérer le style, soit **renommer**
   (nouveaux IDs), soit **supprimer** les anciennes d'abord. → **Préférer 100%
   inline (zéro classe globale)** pour que « ce qu'on colle = ce qu'on voit ».

3. **`block` Bricks = `flex-direction: column` par défaut.** Toujours mettre
   `_direction:"row"` explicitement pour un layout horizontal (sinon empilement
   vertical, notamment sur les overrides responsive).

4. **`container` = boxé** (max-width centré). Pour du pleine largeur → utiliser
   **`block`**.

5. **CSS perso d'élément → utiliser `%root%`** (résolu par Bricks, survit à la
   régénération d'ID), JAMAIS `.brxe-<id>` (l'ID change au collage).

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
