<?php
/**
 * Import des données Amazon dans les champs ACF des produits.
 * Usage : wp eval-file wp-import-amazon-data.php results.json
 *
 * Lit le JSON produit par amazon-product-data.py et met à jour les champs ACF.
 * Adaptez les noms de champs ACF ci-dessous à votre configuration.
 */

// --- Configuration des champs ACF cibles ---
$FIELDS = [
    'review_count'    => 'mltv5_nombre_avis_clients',     // Nombre d'avis Amazon
    'review_score'    => 'mltv5_score_avis_clients',       // Note moyenne /5
    'price'           => 'mltv5_prix_indicatif',           // Prix actuel
    'image_medium'    => 'mltv5_image_amazon',             // URL image
    'status'          => 'mltv5_amazon_status',            // available/out_of_stock/discontinued/not_found
    'fetched_at'      => 'mltv5_amazon_last_check',        // Date de dernière vérification
];
// N'importe que les champs qui existent dans $FIELDS ; commentez une ligne pour ne pas l'importer.
// -------------------------------------------

// Argument : chemin du JSON
$json_path = $args[0] ?? 'results.json';
if (!file_exists($json_path)) {
    WP_CLI::error("Fichier introuvable : {$json_path}");
}

$data = json_decode(file_get_contents($json_path), true);
if (!is_array($data)) {
    WP_CLI::error("JSON invalide : {$json_path}");
}

$updated = 0;
$skipped = 0;
$errors  = 0;

foreach ($data as $product) {
    $post_id = $product['post_id'] ?? null;
    $asin    = $product['asin'] ?? null;

    if (empty($post_id) || empty($asin)) {
        $skipped++;
        continue;
    }

    if (!get_post($post_id)) {
        WP_CLI::warning("Post {$post_id} introuvable (ASIN {$asin})");
        $errors++;
        continue;
    }

    foreach ($FIELDS as $json_key => $acf_field) {
        if (empty($acf_field)) continue;

        $value = $product[$json_key] ?? null;
        if ($value === null) continue;

        update_post_meta($post_id, $acf_field, $value);
    }

    $updated++;

    if ($updated % 500 === 0) {
        WP_CLI::log("  … {$updated} produits mis à jour");
    }
}

WP_CLI::success("Terminé : {$updated} mis à jour, {$skipped} ignorés, {$errors} erreurs");
