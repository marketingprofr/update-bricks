<?php
/**
 * Tags de disponibilité OOS / DISCO à partir des statuts Amazon (results.json).
 *
 * Usage :
 *   wp eval-file wp-set-availability-tags.php results.json dry   # SIMULATION (défaut)
 *   wp eval-file wp-set-availability-tags.php results.json live  # ÉCRITURE (+ sauvegarde)
 *   (ajoute --path=/home/master/applications/meilleurtestbricks/public_html)
 *
 * Mapping :
 *   OOS   ← status "out_of_stock"                (rupture temporaire)
 *   DISCO ← status "not_found" ou "discontinued" (définitivement parti)
 *   available / restricted / autre → AUCUN tag
 *
 * SYNCHRONISE (idempotent) : ajoute le bon tag ET retire l'autre / les obsolètes.
 * Ne modifie QUE les tags OOS et DISCO (taxonomie post_tag). Rien d'autre.
 */

$JSON = $args[0] ?? 'results.json';
$MODE = strtolower($args[1] ?? 'dry');
$LIVE = ($MODE === 'live');

$POST_TYPE  = 'avis';
$ASIN_FIELD = 'mltv5_asin_amazon';
$TAX        = 'post_tag';
$TAG_OOS    = 'OOS';
$TAG_DISCO  = 'DISCO';
$BATCH      = 500;

// 1) Charger results.json → statut par ASIN
if (!file_exists($JSON)) { WP_CLI::error("Fichier introuvable : {$JSON}"); }
$rows = json_decode(file_get_contents($JSON), true);
if (!is_array($rows)) { WP_CLI::error("JSON invalide : {$JSON}"); }
$status_by_asin = [];
foreach ($rows as $r) {
    $a = strtoupper(trim((string) ($r['asin'] ?? '')));
    if ($a !== '') $status_by_asin[$a] = (string) ($r['status'] ?? '');
}
WP_CLI::log(sprintf("%d ASINs avec statut chargés depuis %s. Mode : %s",
    count($status_by_asin), $JSON, $LIVE ? '*** ÉCRITURE RÉELLE ***' : 'SIMULATION (rien écrit)'));

// 2) Posts "avis" publiés ayant un ASIN
$ids = get_posts([
    'post_type'      => $POST_TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => [[ 'key' => $ASIN_FIELD, 'compare' => 'EXISTS' ]],
]);

// 3) Tags actuels (1 requête chacun ; vides si les termes n'existent pas encore)
function _tagged_set($tax, $term) {
    $p = get_posts([
        'post_type' => 'avis', 'post_status' => 'any', 'posts_per_page' => -1,
        'fields' => 'ids', 'tax_query' => [[ 'taxonomy' => $tax, 'field' => 'name', 'terms' => $term ]],
    ]);
    return array_flip($p);
}
$oos_set   = _tagged_set($TAX, $TAG_OOS);
$disco_set = _tagged_set($TAX, $TAG_DISCO);
WP_CLI::log(sprintf("%d posts avec ASIN | déjà taggés OOS : %d, DISCO : %d",
    count($ids), count($oos_set), count($disco_set)));

// 4) Sauvegarde (mode live) : état AVANT des tags, pour chaque post modifié
$bh = null;
if ($LIVE) {
    $backup = 'availability-tags-backup-' . date('Ymd-His') . '.csv';
    $bh = fopen($backup, 'w');
    fputcsv($bh, ['post_id', 'asin', 'status', 'avait_OOS', 'avait_DISCO', 'action']);
    WP_CLI::log("Sauvegarde → {$backup}");
}

wp_suspend_cache_addition(true);
$st = ['seen'=>0,'nodata'=>0,'oos_add'=>0,'oos_del'=>0,'disco_add'=>0,'disco_del'=>0,'unchanged'=>0];
$ex = 0;

foreach ($ids as $i => $pid) {
    $st['seen']++;
    $asin = strtoupper(trim((string) get_post_meta($pid, $ASIN_FIELD, true)));
    if ($asin === '' || !isset($status_by_asin[$asin])) { $st['nodata']++; continue; }
    $status = $status_by_asin[$asin];

    $want_oos   = ($status === 'out_of_stock');
    $want_disco = ($status === 'not_found' || $status === 'discontinued');
    $has_oos    = isset($oos_set[$pid]);
    $has_disco  = isset($disco_set[$pid]);

    $acts = [];
    if ($want_oos   && !$has_oos)   { $acts[] = '+OOS';   $st['oos_add']++; }
    if (!$want_oos  &&  $has_oos)   { $acts[] = '-OOS';   $st['oos_del']++; }
    if ($want_disco && !$has_disco) { $acts[] = '+DISCO'; $st['disco_add']++; }
    if (!$want_disco &&  $has_disco){ $acts[] = '-DISCO'; $st['disco_del']++; }

    if (!$acts) { $st['unchanged']++; continue; }

    if ($LIVE) {
        if ($want_oos   && !$has_oos)    wp_set_object_terms($pid, $TAG_OOS,   $TAX, true);
        if (!$want_oos  &&  $has_oos)    wp_remove_object_terms($pid, $TAG_OOS,   $TAX);
        if ($want_disco && !$has_disco)  wp_set_object_terms($pid, $TAG_DISCO, $TAX, true);
        if (!$want_disco &&  $has_disco) wp_remove_object_terms($pid, $TAG_DISCO, $TAX);
        if ($bh) fputcsv($bh, [$pid, $asin, $status, $has_oos ? 1 : 0, $has_disco ? 1 : 0, implode(' ', $acts)]);
    }

    if (!$LIVE && $ex < 12) {
        WP_CLI::log(sprintf("  ex. #%d (%s) : status=%s → %s", $pid, $asin, $status, implode(' ', $acts)));
        $ex++;
    }
    if (($i + 1) % $BATCH === 0) WP_CLI::log(sprintf("… %d/%d parcourus", $i + 1, count($ids)));
}
if ($bh) fclose($bh);
wp_suspend_cache_addition(false);

$v = $LIVE ? '' : '(simulation) ';
WP_CLI::log(str_repeat('=', 54));
WP_CLI::log(sprintf("Posts avec ASIN parcourus : %d  |  sans statut connu : %d", $st['seen'], $st['nodata']));
WP_CLI::log(sprintf("  %sOOS   : +%d  / -%d", $v, $st['oos_add'], $st['oos_del']));
WP_CLI::log(sprintf("  %sDISCO : +%d  / -%d", $v, $st['disco_add'], $st['disco_del']));
WP_CLI::log(sprintf("  %sinchangés : %d", $v, $st['unchanged']));
WP_CLI::log(str_repeat('=', 54));

if ($LIVE) {
    WP_CLI::success("Tags de disponibilité synchronisés. ⚠️ Purge Varnish + Breeze.");
} else {
    WP_CLI::success("SIMULATION — rien écrit. Relance avec « live » pour appliquer.");
}
