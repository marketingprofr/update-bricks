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

```
base    = mltv5_score_initial
          (si absent → CAPTURÉ depuis mltv5_score_total actuel ; 'none' si vide = pas de base)
amazon  = s10(note, nb) * 10                         (score /100)
w       = min(nb, 100) / 100
mélange = base * (1 - w) + amazon * w
baisse (mélange <= base) : score = mélange                       (complet)
hausse (mélange >  base) : score = base + 0.5*(mélange - base)   (50% de l'écart)
score_total = round(score)          ; si base = 'none' → score = round(amazon)
```
Formule `s10` (ecom_score, échelle /10) :
- `nb ≤ 100` : `2 * (3 + (note - 3) * log10(nb) / 2)`
- `nb > 100` : `2 * (note + (5 - note) * log10(nb/100) / (2 + log10(nb/100)))`

**Pourquoi idempotent ET refresh-safe :** la base ne bouge jamais → relancer donne
toujours le même score (avis inchangés) ou le bon score (avis rafraîchis), **sans cumul**.
On peut relancer autant qu'on veut. (L'ancien design mélangeait `score_total` avec lui-même
et dérivait à chaque passage — d'où l'ancien marqueur `mltv5_score_calcule`, désormais **supprimé**.)

## Le code — `~/update-bricks/wp-score-total.php`
```php
<?php
/**
 * Score global (mltv5_score_total) — pondéré, IDEMPOTENT par base figée mltv5_score_initial.
 * Usage : wp eval-file wp-score-total.php dry|live
 */
$MODE = strtolower($args[0] ?? 'dry');
$LIVE = ($MODE === 'live');

$POST_TYPE = 'avis';
$F_SCORE   = 'mltv5_score_avis_clients';   // note /5  (r)
$F_COUNT   = 'mltv5_nombre_avis_clients';  // nb avis  (n)
$F_TOTAL   = 'mltv5_score_total';          // cible /100
$F_INITIAL = 'mltv5_score_initial';        // base figée (num | 'none' | absent)
$F_ASIN    = 'mltv5_asin_amazon';
$BATCH = 500;

if (!function_exists('ecom_s10')) {
    function ecom_s10($r, $n) {
        if ($n < 1) return 0.0;
        if ($n <= 100) return 2.0 * (3.0 + ($r - 3.0) * log10((float) $n) / 2.0);
        $lr = log10($n / 100.0);
        return 2.0 * ($r + (5.0 - $r) * $lr / (2.0 + $lr));
    }
}
if (!function_exists('mt_parse_num')) {
    function mt_parse_num($v) {
        if ($v === null || $v === '') return null;
        $s = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string) $v));
        return preg_match('/[0-9]+(\.[0-9]+)?/', $s, $m) ? (float) $m[0] : null;
    }
}

$ids = get_posts([
    'post_type' => $POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
    'meta_query' => ['relation' => 'AND',
        [ 'key' => $F_ASIN,  'compare' => 'EXISTS' ],
        [ 'key' => $F_SCORE, 'compare' => 'EXISTS' ],
        [ 'key' => $F_COUNT, 'compare' => 'EXISTS' ],
    ],
]);
WP_CLI::log(sprintf("%d posts avec ASIN+note+avis | Mode : %s",
    count($ids), $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION'));

$bh = null;
if ($LIVE) {
    $backup = 'score-total-backup-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', 'score_initial', 'score_total_avant', 'score_total_apres']);
    WP_CLI::log("Sauvegarde → {$backup}");
}

wp_suspend_cache_addition(true);
$st = ['seen'=>0,'ineligible'=>0,'captured'=>0,'lowered'=>0,'raised'=>0,'unchanged'=>0,'set'=>0];
$ex = 0;

foreach ($ids as $i => $pid) {
    $st['seen']++;
    $meta  = get_post_meta($pid);
    $asin  = strtoupper(trim((string) ($meta[$F_ASIN][0] ?? '')));
    $r     = mt_parse_num($meta[$F_SCORE][0] ?? '');
    $n_raw = $meta[$F_COUNT][0] ?? '';
    $n     = ($n_raw === '') ? null : (int) preg_replace('/[^0-9]/', '', (string) $n_raw);
    if ($asin === '' || $r === null || $n === null || $n < 1) { $st['ineligible']++; continue; }

    $cur_total_raw = $meta[$F_TOTAL][0] ?? '';
    $cur_total     = mt_parse_num($cur_total_raw);

    // Base figée : mltv5_score_initial (capture si absent)
    $initial_raw = $meta[$F_INITIAL][0] ?? '';
    if ($initial_raw === '') {
        if ($cur_total === null) { $base = null; $init_store = 'none'; }
        else                     { $base = $cur_total; $init_store = (string) $cur_total; }
        if ($LIVE) update_post_meta($pid, $F_INITIAL, $init_store);
        $st['captured']++;
    } elseif ($initial_raw === 'none') {
        $base = null;
    } else {
        $base = mt_parse_num($initial_raw);
    }

    $amazon = max(0.0, min(100.0, ecom_s10($r, $n) * 10.0));
    if ($base === null) {
        $final = (int) round($amazon);
    } else {
        $w = min($n, 100) / 100.0;
        $blended = $base * (1.0 - $w) + $amazon * $w;
        if ($blended > $base) $final = (int) round($base + 0.5 * ($blended - $base));
        else                  $final = (int) round($blended);
    }

    if ($cur_total === null)                   $kind = 'set';
    elseif ($final < (int) round($cur_total))  $kind = 'lowered';
    elseif ($final > (int) round($cur_total))  $kind = 'raised';
    else                                       $kind = 'unchanged';

    if ($LIVE && $kind !== 'unchanged') update_post_meta($pid, $F_TOTAL, $final);
    if ($LIVE && $bh) fputcsv($bh, [$pid, $asin, ($base === null ? 'none' : $base), $cur_total_raw, $final]);
    $st[$kind]++;

    if (($i + 1) % $BATCH === 0) WP_CLI::log(sprintf("… %d/%d", $i + 1, count($ids)));
}
if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

WP_CLI::log(sprintf("Parcourus %d | inéligibles %d | capturés %d | abaissés %d | montés %d | posés %d | inchangés %d",
    $st['seen'], $st['ineligible'], $st['captured'], $st['lowered'], $st['raised'], $st['set'], $st['unchanged']));
WP_CLI::success($LIVE ? "Score recalculé. Purge FlyingPress + Varnish." : "SIMULATION — rien écrit.");
```

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
Amazon/Keepa), **relancer simplement** `wp-score-total.php dry` puis `live` : seuls les produits
concernés changent, la base figée garantit un recalcul correct sans cumul.

## Mise en place initiale (déjà faite — pour référence)
`mltv5_score_initial` a été peuplé une fois via **`wp-migrate-score-initial.php`** depuis les
sauvegardes du 1er score (colonne `score_total_avant` = valeur éditoriale d'origine). Pour un
produit jamais scoré, le script principal capture l'initial depuis `score_total` au 1er passage.
**Aucune ré-migration nécessaire.**

## Pièges / points importants
- Le script ne modifie **QUE** `mltv5_score_total` (+ capture `mltv5_score_initial` la 1re fois).
- **Idempotent** : relancer ne dérive jamais (base figée).
- **`mltv5_score_recent` n'est JAMAIS touché.**
- Toujours **`dry` avant `live`**, purge **FlyingPress + Varnish** (pas Breeze) une fois à la fin.
