<?php
/**
 * Score global (mltv5_score_total) à partir des avis Amazon — pondéré, IDEMPOTENT par base figée.
 *
 * Usage :
 *   wp eval-file wp-score-total.php dry    # SIMULATION (défaut)
 *   wp eval-file wp-score-total.php live   # ÉCRITURE (+ sauvegarde)
 *
 * Principe (rejouable sans dérive) :
 *   base = mltv5_score_initial  (score ÉDITORIAL figé)
 *     - si absent → on le CAPTURE depuis mltv5_score_total actuel (éditorial pour un
 *       produit jamais scoré) ; si ce dernier est vide → base 'none' (pas de base).
 *     - 'none' → pas de base éditoriale → score = Amazon direct.
 *   amazon  = s10(note, nb_avis) * 10                       (score /100)
 *   mélange = base * (1 - w) + amazon * w        où w = min(nb,100)/100
 *   baisse (mélange <= base) : score = mélange                       (complet)
 *   hausse (mélange >  base) : score = base + 0.5*(mélange - base)   (50% de l'écart)
 *   score_total = round(score)                              (entier le plus proche)
 *
 * → Comme la base ne bouge jamais, on peut relancer autant qu'on veut (y compris
 *   après un refresh des avis) : le résultat est toujours correct, sans cumul.
 *   Éligibilité : ASIN + note + nombre d'avis remplis.
 *   Ne modifie QUE mltv5_score_total (+ capture mltv5_score_initial la 1re fois).
 */

$MODE = strtolower($args[0] ?? 'dry');
$LIVE = ($MODE === 'live');

$POST_TYPE = 'avis';
$F_SCORE   = 'mltv5_score_avis_clients';   // note /5  (r)
$F_COUNT   = 'mltv5_nombre_avis_clients';  // nb avis  (n)
$F_TOTAL   = 'mltv5_score_total';          // cible /100
$F_INITIAL = 'mltv5_score_initial';        // base figée (num | 'none' | absent)
$F_ASIN    = 'mltv5_asin_amazon';

// Pénalité : -X pts si le produit porte le tag DISCO (définitivement parti)
$DISCO_PENALTY = 10;
$DISCO_TAG     = 'DISCO';
$DISCO_TAX     = 'post_tag';

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
    count($ids), $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (rien écrit)'));

// Produits tagués DISCO (pour la pénalité) — 1 requête
$disco_set = array_flip(get_posts([
    'post_type' => $POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
    'tax_query' => [[ 'taxonomy' => $DISCO_TAX, 'field' => 'name', 'terms' => $DISCO_TAG ]],
]));
WP_CLI::log(sprintf("Produits tagués %s : %d (pénalité -%d au score)", $DISCO_TAG, count($disco_set), $DISCO_PENALTY));

$bh = null;
if ($LIVE) {
    $backup = 'score-total-backup-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', 'score_initial', 'score_total_avant', 'score_total_apres']);
    WP_CLI::log("Sauvegarde → {$backup}");
}

wp_suspend_cache_addition(true);
$st = ['seen'=>0,'ineligible'=>0,'captured'=>0,'lowered'=>0,'raised'=>0,'unchanged'=>0,'set'=>0,'disco'=>0];
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

    // --- Base figée : mltv5_score_initial (capture si absent) ---
    $initial_raw = $meta[$F_INITIAL][0] ?? '';
    if ($initial_raw === '') {
        // Jamais capturé → on prend le score_total actuel comme base éditoriale.
        if ($cur_total === null) { $base = null; $init_store = 'none'; }
        else                     { $base = $cur_total; $init_store = (string) $cur_total; }
        if ($LIVE) update_post_meta($pid, $F_INITIAL, $init_store);
        $st['captured']++;
    } elseif ($initial_raw === 'none') {
        $base = null;
    } else {
        $base = mt_parse_num($initial_raw);
    }

    // --- Calcul ---
    $amazon = max(0.0, min(100.0, ecom_s10($r, $n) * 10.0));
    if ($base === null) {
        $final = (int) round($amazon);                       // pas de base → Amazon direct
    } else {
        $w = min($n, 100) / 100.0;
        $blended = $base * (1.0 - $w) + $amazon * $w;
        if ($blended > $base) $final = (int) round($base + 0.5 * ($blended - $base)); // hausse 50%
        else                  $final = (int) round($blended);                        // baisse pleine
    }

    // Pénalité DISCO (produit définitivement parti) : -X pts, plancher 0.
    // Appliquée au score FINAL (pas à la base) → reste idempotent, et se corrige
    // tout seul si le tag DISCO disparaît (produit revenu en stock).
    $disco = isset($disco_set[$pid]);
    if ($disco) { $final = max(0, $final - $DISCO_PENALTY); $st['disco']++; }

    // --- Net vs score_total actuel ---
    if ($cur_total === null)          $kind = 'set';
    elseif ($final < (int) round($cur_total)) $kind = 'lowered';
    elseif ($final > (int) round($cur_total)) $kind = 'raised';
    else                              $kind = 'unchanged';

    if ($LIVE && $kind !== 'unchanged') update_post_meta($pid, $F_TOTAL, $final);
    if ($LIVE && $bh) fputcsv($bh, [$pid, $asin, ($base === null ? 'none' : $base), $cur_total_raw, $final]);
    $st[$kind]++;

    if (!$LIVE && $ex < 12 && $kind !== 'unchanged') {
        WP_CLI::log(sprintf("  ex. #%d (%s) : base %s, note %.1f, %d avis (w=%.2f), amazon %.1f | total %s → %d (%s)%s",
            $pid, $asin, ($base === null ? 'none' : (string) $base), $r, $n, min($n, 100) / 100.0, $amazon,
            ($cur_total_raw === '' ? '(vide)' : $cur_total_raw), $final, $kind,
            $disco ? " [DISCO -{$DISCO_PENALTY}]" : ''));
        $ex++;
    }
    if (($i + 1) % $BATCH === 0) WP_CLI::log(sprintf("… %d/%d parcourus", $i + 1, count($ids)));
}
if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

$v = $LIVE ? '' : '(simulation) ';
WP_CLI::log(str_repeat('=', 56));
WP_CLI::log(sprintf("Éligibles parcourus : %d  |  inéligibles : %d", $st['seen'], $st['ineligible']));
WP_CLI::log(sprintf("  %sbase capturée (1re fois) : %d", $v, $st['captured']));
WP_CLI::log(sprintf("  %sabaissés                 : %d", $v, $st['lowered']));
WP_CLI::log(sprintf("  %smontés (50%% de l'écart)  : %d", $v, $st['raised']));
WP_CLI::log(sprintf("  %sposés (score_total vide)  : %d", $v, $st['set']));
WP_CLI::log(sprintf("  %sinchangés                 : %d", $v, $st['unchanged']));
WP_CLI::log(sprintf("  %sdont pénalité DISCO (-%d)  : %d", $v, $DISCO_PENALTY, $st['disco']));
WP_CLI::log(str_repeat('=', 56));

if ($LIVE) WP_CLI::success("Score global recalculé (base figée mltv5_score_initial). Purge cache.");
else       WP_CLI::success("SIMULATION — rien écrit. Vérifie, puis « live ».");
