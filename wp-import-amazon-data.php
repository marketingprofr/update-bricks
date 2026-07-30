<?php
/**
 * Import Amazon + Keepa → ACF des posts « avis » (match par ASIN, sécurisé).
 *
 * Usage :
 *   wp eval-file wp-import-amazon-data.php final.json dry   # SIMULATION (défaut) — n'écrit rien
 *   wp eval-file wp-import-amazon-data.php final.json live  # ÉCRITURE réelle (+ sauvegarde auto)
 *   (ajoute le --path=… de ton install si besoin)
 *
 * NE MODIFIE QUE 4 champs ACF, rien d'autre :
 *   - mltv5_nombre_avis_clients   ← nombre d'avis   (remplacé)
 *   - mltv5_score_avis_clients    ← note /5         (remplacée)
 *   - mltv5_prix_indicatif        ← prix            (remplacé SEULEMENT si moins cher ; arrondi)
 *   - mltv5_image_external_url    ← URL image       (remplacée)
 * Le statut / la date Amazon restent dans le JSON, ne sont PAS écrits sur le site.
 */

$JSON_PATH = $args[0] ?? 'final.json';
$MODE      = strtolower($args[1] ?? 'dry');
$LIVE      = ($MODE === 'live');

$POST_TYPE  = 'avis';
$ASIN_FIELD = 'mltv5_asin_amazon';

// Les SEULS champs modifiés
$F_COUNT = 'mltv5_nombre_avis_clients';
$F_SCORE = 'mltv5_score_avis_clients';
$F_PRICE = 'mltv5_prix_indicatif';
$F_IMAGE = 'mltv5_image_external_url';

$BATCH = 500;

// Arrondi prix : > 100 → dizaine la plus proche ; sinon euro le plus proche.
if (!function_exists('amz_round_price')) {
    function amz_round_price($p) {
        $p = (float) $p;
        if ($p <= 0) return null;
        return $p > 100 ? (int) (round($p / 10) * 10) : (int) round($p);
    }
}
// Extrait un nombre depuis une valeur ACF (« 216,25 € » → 216.25).
if (!function_exists('amz_parse_num')) {
    function amz_parse_num($v) {
        if ($v === null || $v === '') return null;
        $s = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', (string) $v));
        return preg_match('/[0-9]+(\.[0-9]+)?/', $s, $m) ? (float) $m[0] : null;
    }
}

// 1) Charger le JSON fusionné, indexé par ASIN
if (!file_exists($JSON_PATH)) { WP_CLI::error("Fichier introuvable : {$JSON_PATH}"); }
$rows = json_decode(file_get_contents($JSON_PATH), true);
if (!is_array($rows)) { WP_CLI::error("JSON invalide : {$JSON_PATH}"); }

$map = [];
foreach ($rows as $r) {
    $a = strtoupper(trim((string) ($r['asin'] ?? '')));
    if ($a !== '') $map[$a] = $r;
}
WP_CLI::log(sprintf("%d ASINs chargés. Mode : %s",
    count($map), $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (dry-run, rien écrit)'));

// 2) Tous les posts « avis » publiés qui ont un ASIN
$ids = get_posts([
    'post_type'      => $POST_TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [[ 'key' => $ASIN_FIELD, 'compare' => 'EXISTS' ]],
]);
WP_CLI::log(sprintf("%d posts « %s » à parcourir.", count($ids), $POST_TYPE));

// 3) Sauvegarde (mode live) : valeurs actuelles AVANT toute modification
$bh = null;
if ($LIVE) {
    $backup = 'acf-backup-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', $F_COUNT, $F_SCORE, $F_PRICE, $F_IMAGE]);
    WP_CLI::log("Sauvegarde des valeurs actuelles → {$backup} (pour rollback éventuel)");
}

wp_suspend_cache_addition(true); // évite le gonflement mémoire sur des milliers de posts

$st = ['seen'=>0,'nodata'=>0,'count'=>0,'score'=>0,'price_set'=>0,'price_kept'=>0,'image'=>0];
$examples = 0;

foreach ($ids as $i => $pid) {
    $st['seen']++;

    $meta = get_post_meta($pid); // tous les meta du post en 1 requête
    $asin = strtoupper(trim((string) ($meta[$ASIN_FIELD][0] ?? '')));
    $d = $map[$asin] ?? null;
    if (!$d) { $st['nodata']++; continue; }

    $cur_count = $meta[$F_COUNT][0] ?? '';
    $cur_score = $meta[$F_SCORE][0] ?? '';
    $cur_price = $meta[$F_PRICE][0] ?? '';
    $cur_image = $meta[$F_IMAGE][0] ?? '';
    if ($bh) fputcsv($bh, [$pid, $asin, $cur_count, $cur_score, $cur_price, $cur_image]);

    $changes = [];

    // -- Nombre d'avis (remplace si dispo) --
    if (isset($d['review_count']) && $d['review_count'] !== null && $d['review_count'] !== '') {
        $val = (int) $d['review_count'];
        if ($LIVE) update_post_meta($pid, $F_COUNT, $val);
        $st['count']++;
        $changes[] = "avis {$cur_count}→{$val}";
    }
    // -- Note /5 (remplace si dispo) --
    if (isset($d['review_score']) && $d['review_score'] !== null && $d['review_score'] !== '') {
        $val = $d['review_score'];
        if ($LIVE) update_post_meta($pid, $F_SCORE, $val);
        $st['score']++;
        $changes[] = "note {$cur_score}→{$val}";
    }
    // -- Prix (remplace UNIQUEMENT si moins cher ; arrondi) --
    if (isset($d['price']) && $d['price'] !== null && $d['price'] !== '') {
        $newp = amz_round_price($d['price']);
        if ($newp !== null) {
            $curp = amz_parse_num($cur_price);
            if ($curp === null || $newp < $curp) {
                if ($LIVE) update_post_meta($pid, $F_PRICE, $newp);
                $st['price_set']++;
                $changes[] = "prix {$cur_price}→{$newp}";
            } else {
                $st['price_kept']++;
            }
        }
    }
    // -- Image externe (remplace si URL dispo) --
    if (!empty($d['image'])) {
        if ($LIVE) update_post_meta($pid, $F_IMAGE, $d['image']);
        $st['image']++;
        $changes[] = "image ✓";
    }

    // En simulation : montre le détail des 8 premiers changements
    if (!$LIVE && $changes && $examples < 8) {
        WP_CLI::log("  ex. #{$pid} ({$asin}) : " . implode(' | ', $changes));
        $examples++;
    }

    if (($i + 1) % $BATCH === 0) {
        WP_CLI::log(sprintf("… %d/%d parcourus", $i + 1, count($ids)));
    }
}

if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

$verb = $LIVE ? 'mis à jour' : 'à mettre à jour';
WP_CLI::log(str_repeat('=', 54));
WP_CLI::log(sprintf("Posts parcourus : %d  |  sans données Amazon : %d", $st['seen'], $st['nodata']));
WP_CLI::log(sprintf("  Nombre d'avis %s : %d", $verb, $st['count']));
WP_CLI::log(sprintf("  Note %s        : %d", $verb, $st['score']));
WP_CLI::log(sprintf("  Prix %s        : %d   (gardés car pas moins cher : %d)", $verb, $st['price_set'], $st['price_kept']));
WP_CLI::log(sprintf("  Image %s       : %d", $verb, $st['image']));
WP_CLI::log(str_repeat('=', 54));

if ($LIVE) {
    WP_CLI::success("Import terminé. ⚠️ Purge Varnish + Breeze pour voir les changements en front.");
} else {
    WP_CLI::success("SIMULATION terminée — RIEN n'a été écrit. Relance avec « live » pour appliquer.");
}
