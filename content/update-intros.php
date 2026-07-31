<?php
/**
 * Met à jour le champ ACF mltv5_introduction pour chaque post.
 * Usage : wp eval-file update-intros.php [--dry-run]
 *
 * Le CSV doit être dans le même dossier que ce script.
 */

$dry_run = in_array('--dry-run', $args ?? []);
$csv_path = __DIR__ . '/intros-export.csv';

if (!file_exists($csv_path)) {
    WP_CLI::error("Fichier introuvable : $csv_path");
}

$handle = fopen($csv_path, 'r');
$header = fgetcsv($handle, 0, ';', '"');
$updated = 0;
$skipped = 0;
$errors  = 0;

while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
    $post_id   = (int) $row[0];
    $new_intro = $row[1];

    if (!$post_id || empty(trim($new_intro))) {
        WP_CLI::warning("Ligne ignorée (ID vide ou intro vide) : {$row[0]}");
        $skipped++;
        continue;
    }

    if (!get_post($post_id)) {
        WP_CLI::warning("Post $post_id introuvable, ignoré.");
        $skipped++;
        continue;
    }

    if ($dry_run) {
        $old = get_post_meta($post_id, 'mltv5_introduction', true);
        $preview = mb_substr(strip_tags($new_intro), 0, 80) . '...';
        WP_CLI::log("DRY-RUN $post_id : $preview");
        $updated++;
        continue;
    }

    $result = update_post_meta($post_id, 'mltv5_introduction', $new_intro);
    if ($result !== false) {
        $updated++;
    } else {
        WP_CLI::warning("Échec update_post_meta pour $post_id");
        $errors++;
    }
}

fclose($handle);

$mode = $dry_run ? '[DRY-RUN] ' : '';
WP_CLI::success("{$mode}Terminé. Mis à jour : $updated | Ignorés : $skipped | Erreurs : $errors");
