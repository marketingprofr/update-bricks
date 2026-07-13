/**
 * PHASE 3 : BACKUP DES DONNÉES CATÉGORIES
 * Mode lecture seule — ne modifie rien.
 * Génère un fichier SQL de restauration téléchargeable.
 * Copier-coller dans WPCodeBox et exécuter.
 *
 * EXÉCUTER AVANT 04-apply.php — le script d'application vérifie
 * qu'un backup a été fait.
 */

global $wpdb;
set_time_limit(300);

// ─── Récupérer les term_ids des catégories ──────────────────
$cat_term_ids = $wpdb->get_col("
    SELECT t.term_id
    FROM {$wpdb->terms} t
    JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'category'
");

if (empty($cat_term_ids)) {
    echo '<p style="color:red;">Aucune categorie trouvee.</p>';
    return;
}

$ids_str = implode(',', array_map('intval', $cat_term_ids));

// ─── Récupérer les term_taxonomy_ids ────────────────────────
$cat_tt_ids = $wpdb->get_col("
    SELECT term_taxonomy_id
    FROM {$wpdb->term_taxonomy}
    WHERE taxonomy = 'category'
");
$tt_ids_str = implode(',', array_map('intval', $cat_tt_ids));

// ─── Générer le SQL ─────────────────────────────────────────
$sql_parts = [];
$sql_parts[] = "-- Backup des categories WordPress";
$sql_parts[] = "-- Genere le " . current_time('mysql') . "";
$sql_parts[] = "-- " . count($cat_term_ids) . " categories, " . count($cat_tt_ids) . " term_taxonomy";
$sql_parts[] = "";
$sql_parts[] = "-- Pour restaurer : executer ce SQL dans phpMyAdmin";
$sql_parts[] = "-- Les INSERT utilisent REPLACE pour ecraser les donnees existantes";
$sql_parts[] = "";

// wp_terms
$sql_parts[] = "-- ─── wp_terms ────────────────────────────────────────────";
$terms = $wpdb->get_results("SELECT * FROM {$wpdb->terms} WHERE term_id IN ({$ids_str})", ARRAY_A);
foreach ($terms as $row) {
    $term_id    = (int) $row['term_id'];
    $name       = $wpdb->_real_escape($row['name']);
    $slug       = $wpdb->_real_escape($row['slug']);
    $term_group = (int) $row['term_group'];
    $sql_parts[] = "REPLACE INTO `{$wpdb->terms}` (`term_id`, `name`, `slug`, `term_group`) VALUES ({$term_id}, '{$name}', '{$slug}', {$term_group});";
}

$sql_parts[] = "";
$sql_parts[] = "-- ─── wp_term_taxonomy ─────────────────────────────────────";
$taxonomies = $wpdb->get_results("SELECT * FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'category'", ARRAY_A);
foreach ($taxonomies as $row) {
    $tt_id       = (int) $row['term_taxonomy_id'];
    $term_id     = (int) $row['term_id'];
    $taxonomy    = $wpdb->_real_escape($row['taxonomy']);
    $description = $wpdb->_real_escape($row['description']);
    $parent      = (int) $row['parent'];
    $count       = (int) $row['count'];
    $sql_parts[] = "REPLACE INTO `{$wpdb->term_taxonomy}` (`term_taxonomy_id`, `term_id`, `taxonomy`, `description`, `parent`, `count`) VALUES ({$tt_id}, {$term_id}, '{$taxonomy}', '{$description}', {$parent}, {$count});";
}

$sql_parts[] = "";
$sql_parts[] = "-- ─── wp_term_relationships (categories uniquement) ────────";
$rels = $wpdb->get_results("SELECT * FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$tt_ids_str})", ARRAY_A);
foreach ($rels as $row) {
    $object_id = (int) $row['object_id'];
    $tt_id     = (int) $row['term_taxonomy_id'];
    $order     = (int) $row['term_order'];
    $sql_parts[] = "REPLACE INTO `{$wpdb->term_relationships}` (`object_id`, `term_taxonomy_id`, `term_order`) VALUES ({$object_id}, {$tt_id}, {$order});";
}

// ─── Sauvegarder le fichier ─────────────────────────────────
$sql_content = implode("\n", $sql_parts);
$upload_dir  = wp_upload_dir();
$backup_path = $upload_dir['basedir'] . '/category-backup-' . date('Y-m-d-His') . '.sql';
file_put_contents($backup_path, $sql_content);
$backup_url = $upload_dir['baseurl'] . '/' . basename($backup_path);

// Marquer le backup comme fait (vérifié par 04-apply.php)
set_transient('category_cleanup_backup_done', $backup_path, DAY_IN_SECONDS * 7);

// ─── Sortie HTML ────────────────────────────────────────────
$size_kb = round(strlen($sql_content) / 1024, 1);

echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:800px;margin:20px auto;padding:20px;'>";
echo '<h1>Backup des categories</h1>';

echo "<div style='padding:16px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;margin:16px 0;'>"
    . "<strong>Backup genere avec succes</strong><br>"
    . "Fichier : {$backup_path}<br>"
    . "Taille : {$size_kb} Ko<br>"
    . "Contenu : " . count($terms) . " termes, " . count($taxonomies) . " taxonomies, " . count($rels) . " relations"
    . "</div>";

echo "<p><a href='{$backup_url}' download style='padding:10px 20px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;display:inline-block;'>Telecharger le backup SQL</a></p>";

echo "<p style='padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'>"
    . "<strong>Important :</strong> Telechargez ce fichier et conservez-le en lieu sur AVANT d'executer 04-apply.php. "
    . "Pensez aussi a supprimer le fichier du serveur apres telechargement : {$backup_path}</p>";

echo '</div>';
