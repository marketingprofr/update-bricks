<?php
/**
 * Score global (mltv5_score_total) à partir des avis Amazon — pondéré, idempotent par TAG.
 *
 * Usage :
 *   wp eval-file wp-score-total.php dry    # SIMULATION (défaut) — n'écrit rien, ne tague rien
 *   wp eval-file wp-score-total.php live   # ÉCRITURE (+ sauvegarde + pose le tag "score")
 *
 * Éligibilité (les 3 requis) :
 *   - ASIN rempli (mltv5_asin_amazon)
 *   - note (mltv5_score_avis_clients) ET nombre d'avis (mltv5_nombre_avis_clients) remplis
 *   - PAS déjà le tag "score"  →  garantit qu'un produit n'est calculé qu'UNE fois (idempotent)
 *
 * Calcul :
 *   w        = min(nb_avis, 100) / 100
 *   amazon   = s10(note, nb_avis) * 10                  (score /100)
 *   mélange  = ancien * (1 - w) + amazon * w
 *   baisse (mélange <= ancien) : score = mélange                     (complet)
 *   hausse (mélange >  ancien) : score = ancien + 0.5*(mélange-ancien) (50% de l'écart)
 *   score_total = round(score)                          (entier le plus proche)
 *   → puis on POSE le tag "score".
 *
 * Ne modifie QUE mltv5_score_total + le tag "score". Rien d'autre.
 */

$MODE = strtolower($args[0] ?? 'dry');
$LIVE = ($MODE === 'live');

$POPULATE_EMPTY = true;   // score_total vide → poser le score Amazon direct (round(amazon))

$POST_TYPE = 'avis';
$F_SCORE   = 'mltv5_score_avis_clients';   // note /5  (r)
$F_COUNT   = 'mltv5_nombre_avis_clients';  // nb avis  (n)
$F_TOTAL   = 'mltv5_score_total';          // cible /100
$F_ASIN    = 'mltv5_asin_amazon';

$TAG_TAX  = 'post_tag';   // taxonomie du marqueur "déjà calculé"
$TAG_TERM = 'score';      // terme

$BATCH = 500;

// Formule ecom_score (échelle /10) — parité exacte vérifiée vs Python
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

// Posts "avis" publiés avec ASIN + note + nombre d'avis
$ids = get_posts([
    'post_type'      => $POST_TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => $F_ASIN,  'compare' => 'EXISTS' ],
        [ 'key' => $F_SCORE, 'compare' => 'EXISTS' ],
        [ 'key' => $F_COUNT, 'compare' => 'EXISTS' ],
    ],
]);

// Produits DÉJÀ tagués "score" → à sauter (1 requête ; vide si le terme n'existe pas encore)
$tagged = get_posts([
    'post_type'      => $POST_TYPE,
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'tax_query'      => [[ 'taxonomy' => $TAG_TAX, 'field' => 'slug', 'terms' => $TAG_TERM ]],
]);
$tagged_set = array_flip($tagged);

WP_CLI::log(sprintf("%d posts avec ASIN+note+avis | déjà tagués « %s » : %d | Mode : %s",
    count($ids), $TAG_TERM, count($tagged),
    $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (rien écrit, aucun tag)'));

$bh = null;
if ($LIVE) {
    $backup = 'score-total-backup-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', 'score_total_avant', 'score_total_apres']);
    WP_CLI::log("Sauvegarde → {$backup}");
}

wp_suspend_cache_addition(true);
$st = ['seen'=>0,'already'=>0,'ineligible'=>0,'lowered'=>0,'raised'=>0,'populated'=>0,'kept'=>0];
$ex = 0;

foreach ($ids as $i => $pid) {
    $st['seen']++;
    if (isset($tagged_set[$pid])) { $st['already']++; continue; }  // déjà calculé une fois

    $meta  = get_post_meta($pid);
    $asin  = strtoupper(trim((string) ($meta[$F_ASIN][0] ?? '')));
    $r     = mt_parse_num($meta[$F_SCORE][0] ?? '');
    $n_raw = $meta[$F_COUNT][0] ?? '';
    $n     = ($n_raw === '') ? null : (int) preg_replace('/[^0-9]/', '', (string) $n_raw);
    if ($asin === '' || $r === null || $n === null || $n < 1) { $st['ineligible']++; continue; }

    $amazon  = max(0.0, min(100.0, ecom_s10($r, $n) * 10.0));
    $cur_raw = $meta[$F_TOTAL][0] ?? '';
    $cur     = mt_parse_num($cur_raw);

    $kind = null; $final = null;
    if ($cur === null) {
        if ($POPULATE_EMPTY) { $final = (int) round($amazon); $kind = 'populated'; }
        else { continue; }   // pas d'ancien score et on ne pose pas → on ne touche/tague rien
    } else {
        $w       = min($n, 100) / 100.0;
        $blended = $cur * (1.0 - $w) + $amazon * $w;
        if ($blended > $cur) {                                   // hausse → 50% de l'écart
            $final = (int) round($cur + 0.5 * ($blended - $cur));
            $kind  = ($final > $cur) ? 'raised' : 'kept';
        } else {                                                 // baisse → complet
            $final = (int) round($blended);
            $kind  = ($final < $cur) ? 'lowered' : 'kept';
        }
    }

    // Écriture (score si changé) + pose du tag (toujours, car "calculé une fois")
    if ($LIVE) {
        if ($kind !== 'kept') update_post_meta($pid, $F_TOTAL, $final);
        wp_set_object_terms($pid, $TAG_TERM, $TAG_TAX, true);   // append, crée le terme au besoin
        if ($bh) fputcsv($bh, [$pid, $asin, $cur_raw, $final]);
    }
    $st[$kind]++;

    if (!$LIVE && $ex < 12 && $kind !== 'kept') {
        WP_CLI::log(sprintf("  ex. #%d (%s) : note %.1f, %d avis, w=%.2f, amazon %.1f | total %s → %d (%s)",
            $pid, $asin, $r, $n, min($n, 100) / 100.0, $amazon,
            ($cur_raw === '' ? '(vide)' : $cur_raw), $final, $kind));
        $ex++;
    }
    if (($i + 1) % $BATCH === 0) WP_CLI::log(sprintf("… %d/%d parcourus", $i + 1, count($ids)));
}
if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

WP_CLI::log(str_repeat('=', 56));
WP_CLI::log(sprintf("Éligibles parcourus : %d", $st['seen']));
WP_CLI::log(sprintf("  déjà tagués (sautés)        : %d", $st['already']));
WP_CLI::log(sprintf("  inéligibles (ASIN/avis vide): %d", $st['ineligible']));
WP_CLI::log(sprintf("  abaissés                    : %d", $st['lowered']));
WP_CLI::log(sprintf("  montés (50%% de l'écart)     : %d", $st['raised']));
WP_CLI::log(sprintf("  posés (score_total vide)    : %d", $st['populated']));
WP_CLI::log(sprintf("  inchangés (calculés, tagués): %d", $st['kept']));
WP_CLI::log(str_repeat('=', 56));

if ($LIVE) {
    WP_CLI::success("Fait. Tag « {$TAG_TERM} » posé sur les produits calculés (ils ne seront plus recalculés). ⚠️ Purge Varnish + Breeze.");
} else {
    WP_CLI::success("SIMULATION — rien écrit, aucun tag. Vérifie l'échelle des « total » ci-dessus, puis relance avec « live ».");
}
