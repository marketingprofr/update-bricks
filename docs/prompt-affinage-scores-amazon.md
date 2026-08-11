# Mission : affinage des scores produits via les avis Amazon (WordPress / Bricks)

> Prompt de passation. **Architecture « base figée »** : le calcul du score est une
> fonction pure, **idempotente et rejouable** (aucune dérive, même après un refresh des avis).

## Contexte
- Site **meilleurtest.fr** : WordPress + Bricks Builder, hébergé sur **Cloudways**.
  App WP : `/home/master/applications/meilleurtestbricks/public_html`.
- Produits = **CPT `avis`** (~13 700 ont un ASIN dans l'ACF `mltv5_asin_amazon`).
- **En amont** : le **nombre d'avis** (`mltv5_nombre_avis_clients`) et la **note /5**
  (`mltv5_score_avis_clients`) sont récupérés via l'API **Keepa**.
- Repo : **`marketingprofr/update-bricks`**, branche
  **`claude/amazon-product-data-scraper-k4goq3`**, cloné dans **`~/update-bricks`**.
- Cache : **FlyingPress + Varnish** (PAS Breeze), à purger **une seule fois à la fin**.

## Architecture du score (le point clé : base figée)
Le score global /100 (`mltv5_score_total`) est une **fonction pure** de :
- **`mltv5_score_initial`** = le score **éditorial d'origine, FIGÉ** (ne bouge jamais).
  C'est ce qui rend le calcul idempotent.
- + les avis Amazon (note + nombre).
- − **pénalité DISCO** : −10 pts si le produit porte le tag `DISCO` (arrêt définitif).

```
base    = mltv5_score_initial
          (si absent → CAPTURÉ depuis mltv5_score_total actuel ; 'none' si vide = pas de base)
amazon  = s10(note, nb) * 10                         (score /100)
w       = min(nb, 100) / 100
mélange = base * (1 - w) + amazon * w
baisse (mélange <= base) : score = mélange                       (complet)
hausse (mélange >  base) : score = base + 0.5*(mélange - base)   (50% de l'écart)
DISCO   : score = max(0, score - 10)                             (produit définitivement parti)
score_total = round(score)          ; si base = 'none' → score = round(amazon)
```
Formule `s10` (ecom_score, échelle /10) :
- `nb ≤ 100` : `2 * (3 + (note - 3) * log10(nb) / 2)`
- `nb > 100` : `2 * (note + (5 - note) * log10(nb/100) / (2 + log10(nb/100)))`

**Périmètre = union `(avis Amazon) ∪ (tag DISCO)`.** On score les produits qui ont
des avis Amazon (ASIN + note + nombre) **ET** on pénalise **tous** les produits
tagués `DISCO`, même sans avis Amazon : un DISCO sans avis part de sa **base
éditoriale figée** et perd 10 pts (rien d'autre). C'est ce qui fait que la
pénalité touche les ~581 DISCO et pas seulement la poignée qui a des avis.
⚠️ **Ordre des opérations** : poser les tags `OOS`/`DISCO`
(`wp-set-availability-tags.php`) **AVANT** de (re)lancer le score, sinon la
pénalité DISCO ne voit pas les tags fraîchement posés.
⚠️ **Réversibilité** : un produit **avec avis** se recorrige seul si son tag DISCO
disparaît (il reste dans le périmètre « avis »). Un produit **sans avis** sort du
périmètre si on retire son tag → son score reste à `base−10` (à recaler à la main,
cas rare d'un DISCO qui revient).

**Pourquoi idempotent ET refresh-safe :** la base ne bouge jamais → relancer donne
toujours le même score (avis inchangés) ou le bon score (avis rafraîchis), **sans cumul**.
On peut relancer autant qu'on veut. (L'ancien design mélangeait `score_total` avec lui-même
et dérivait à chaque passage — d'où l'ancien marqueur `mltv5_score_calcule`, désormais **supprimé**.)

## Le code — `~/update-bricks/wp-score-total.php`
Le script complet vit dans le repo (fais `git pull`, cf. plus bas) — pas de copie
dupliquée ici pour éviter toute dérive. Structure :

1. **Deux requêtes** → deux ensembles d'IDs :
   - `eligible` = produits avec avis Amazon (`meta_query` : `mltv5_asin_amazon` +
     `mltv5_score_avis_clients` + `mltv5_nombre_avis_clients` tous `EXISTS`).
   - `disco_ids` = produits tagués `DISCO` (`tax_query` `post_tag` `field=name`
     `terms=DISCO`), aussi utilisés en `array_flip` → `$disco_set` (test d'appartenance).
2. **Périmètre** `$ids = array_unique(array_merge($eligible, $disco_ids))` — l'union.
3. Boucle, par produit :
   - `$has_reviews` = ASIN + note + nombre valides ; `$is_disco = isset($disco_set[$pid])`.
   - `base` = `mltv5_score_initial` (capturé depuis `mltv5_score_total` si absent ;
     `'none'` si vide).
   - `$has_reviews` → mélange pondéré (hausse à 50 %, baisse pleine) ; **sinon**
     (DISCO sans avis) → `$pre = round(base)` (aucun ajustement Amazon).
   - **Pénalité** : `if ($is_disco) $final = max(0, $final - 10);`
   - Écrit `mltv5_score_total` seulement si `kind !== 'unchanged'`.
   - Compteurs de fin : `disco` (total pénalisés) dont `disco_no_reviews` (les DISCO
     sans avis, nouvellement couverts).

Fonction `ecom_s10($r,$n)` et `mt_parse_num($v)` gardées par `function_exists`.
**Ne modifie QUE `mltv5_score_total`** (+ capture `mltv5_score_initial` la 1re fois).

## Comment (re)calculer les scores
La base `mltv5_score_initial` est **déjà établie** → il suffit de relancer le script :
1. `ssh master_cpxwgynxgt@64.226.121.229` (mot de passe Cloudways).
2. `cd ~/update-bricks && git pull`
3. **Simulation** : `wp eval-file wp-score-total.php dry --path=/home/master/applications/meilleurtestbricks/public_html`
   → vérifier que les **`inchangés` dominent** (preuve d'idempotence) ; les changements = produits
   dont les avis ont été rafraîchis, ou nouveaux produits jamais scorés (`base capturée`).
4. **Écriture** : `wp eval-file wp-score-total.php live --path=…` (crée une sauvegarde CSV).
5. **Purger FlyingPress + Varnish** une seule fois à la fin.

## Rafraîchir puis re-scorer (cas typique)
Après avoir mis à jour `mltv5_nombre_avis_clients` / `mltv5_score_avis_clients` (via le pipeline
Amazon/Keepa), **d'abord (re)poser les tags de dispo** (`wp-set-availability-tags.php` →
`OOS`/`DISCO`), **puis relancer** `wp-score-total.php dry` et `live` : seuls les produits
concernés changent, la base figée garantit un recalcul correct sans cumul, et la pénalité
DISCO voit bien les tags à jour.

## Mise en place initiale (déjà faite — pour référence)
`mltv5_score_initial` a été peuplé une fois via **`wp-migrate-score-initial.php`** depuis les
sauvegardes du 1er score (colonne `score_total_avant` = valeur éditoriale d'origine). Pour un
produit jamais scoré, le script principal capture l'initial depuis `score_total` au 1er passage.
**Aucune ré-migration nécessaire.**

## Pièges / points importants
- Le script ne modifie **QUE** `mltv5_score_total` (+ capture `mltv5_score_initial` la 1re fois).
- **Idempotent** : relancer ne dérive jamais (base figée), pénalité DISCO comprise
  (elle s'applique au score FINAL, pas à la base → `−10` une seule fois, jamais cumulée).
- **Pénalité DISCO = union** : les produits DISCO **sans avis Amazon** sont dans le
  périmètre (via `tax_query`) → poser les tags **avant** de scorer, sinon `disco_no_reviews`
  ressort à ~0 et seule la poignée de DISCO ayant des avis est pénalisée.
- **`mltv5_score_recent` n'est JAMAIS touché.**
- Toujours **`dry` avant `live`**, purge **FlyingPress + Varnish** (pas Breeze) une fois à la fin.
