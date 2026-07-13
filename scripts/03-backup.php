/**
 * PHASE 3 : BACKUP DES DONNÉES CATÉGORIES
 * Mode lecture seule — ne modifie rien.
 * Génère un fichier SQL de restauration téléchargeable (écrit en flux,
 * par lots, pour tenir en mémoire même avec des centaines de milliers
 * de relations).
 *
 * EXÉCUTER AVANT 04-apply.php — le script d'application vérifie
 * qu'un backup a été fait.
 *
 * Note restauration : le SQL restaure les catégories supprimées et leurs
 * relations (REPLACE INTO). Les relations L2 ajoutées par 04-apply ne sont
 * pas retirées par la restauration — les posts auront alors les deux
 * (catégorie profonde + L2), ce qui est sans danger.
 *
 * Utilisation :
 *   1. Coller ce code dans un snippet PHP WPCodeBox (à la suite de la balise <?php)
 *   2. Activer le snippet
 *   3. Visiter : https://votre-site.fr/?catcleanup=backup  (connecté en admin)
 *   4. Télécharger le fichier, puis désactiver le snippet
 */

$catcleanup_backup = function () {
    if (!isset($_GET['catcleanup']) || $_GET['catcleanup'] !== 'backup') return;
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    if (function_exists('set_time_limit')) @set_time_limit(600);
    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');

    // ─── IDs des catégories ─────────────────────────────────
    $cat_tt_ids = array_map('intval', (array) $wpdb->get_col("
        SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'category'
    "));

    if (empty($cat_tt_ids)) {
        echo '<p style="color:red;">Aucune categorie trouvee.</p>';
        exit;
    }

    // ─── Ouverture du fichier ───────────────────────────────
    $upload_dir  = wp_upload_dir();
    $backup_path = $upload_dir['basedir'] . '/category-backup-' . gmdate('Y-m-d-His') . '.sql';
    $backup_url  = $upload_dir['baseurl'] . '/' . basename($backup_path);

    $fh = fopen($backup_path, 'wb');
    if (!$fh) {
        echo '<p style="color:red;">Impossible d\'ecrire dans ' . esc_html($backup_path) . '</p>';
        exit;
    }

    // Écrit un lot de lignes VALUES en un seul REPLACE INTO
    $write_batch = function ($sql_prefix, &$values) use ($fh) {
        if (empty($values)) return;
        fwrite($fh, $sql_prefix . "\n" . implode(",\n", $values) . ";\n\n");
        $values = [];
    };

    fwrite($fh, "-- Backup des categories WordPress\n");
    fwrite($fh, "-- Genere le " . current_time('mysql') . "\n");
    fwrite($fh, "-- Pour restaurer : importer ce fichier dans phpMyAdmin.\n");
    fwrite($fh, "-- Les REPLACE INTO ecrasent les lignes existantes et recreent les lignes supprimees.\n\n");

    // ─── wp_terms ───────────────────────────────────────────
    fwrite($fh, "-- wp_terms\n");
    $prefix_terms = "REPLACE INTO `{$wpdb->terms}` (`term_id`, `name`, `slug`, `term_group`) VALUES";
    $terms = $wpdb->get_results("
        SELECT t.term_id, t.name, t.slug, t.term_group
        FROM {$wpdb->terms} t
        JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        WHERE tt.taxonomy = 'category'
    ", ARRAY_A);
    $values = [];
    foreach ((array) $terms as $row) {
        $values[] = '(' . (int) $row['term_id'] . ", '" . esc_sql($row['name']) . "', '" . esc_sql($row['slug']) . "', " . (int) $row['term_group'] . ')';
        if (count($values) >= 500) $write_batch($prefix_terms, $values);
    }
    $write_batch($prefix_terms, $values);
    $terms_count = count($terms);
    unset($terms);

    // ─── wp_term_taxonomy ───────────────────────────────────
    fwrite($fh, "-- wp_term_taxonomy\n");
    $prefix_tt = "REPLACE INTO `{$wpdb->term_taxonomy}` (`term_taxonomy_id`, `term_id`, `taxonomy`, `description`, `parent`, `count`) VALUES";
    $taxos = $wpdb->get_results("
        SELECT term_taxonomy_id, term_id, taxonomy, description, parent, count
        FROM {$wpdb->term_taxonomy}
        WHERE taxonomy = 'category'
    ", ARRAY_A);
    $values = [];
    foreach ((array) $taxos as $row) {
        $values[] = '(' . (int) $row['term_taxonomy_id'] . ', ' . (int) $row['term_id']
            . ", '" . esc_sql($row['taxonomy']) . "', '" . esc_sql($row['description']) . "', "
            . (int) $row['parent'] . ', ' . (int) $row['count'] . ')';
        if (count($values) >= 500) $write_batch($prefix_tt, $values);
    }
    $write_batch($prefix_tt, $values);
    $taxos_count = count($taxos);
    unset($taxos);

    // ─── wp_term_relationships (par lots de ttids) ──────────
    fwrite($fh, "-- wp_term_relationships (categories uniquement)\n");
    $prefix_rel = "REPLACE INTO `{$wpdb->term_relationships}` (`object_id`, `term_taxonomy_id`, `term_order`) VALUES";
    $rel_count  = 0;
    $values     = [];
    foreach (array_chunk($cat_tt_ids, 500) as $chunk) {
        $in   = implode(',', $chunk);
        $rels = $wpdb->get_results(
            "SELECT object_id, term_taxonomy_id, term_order FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($in)",
            ARRAY_A
        );
        foreach ((array) $rels as $row) {
            $values[] = '(' . (int) $row['object_id'] . ', ' . (int) $row['term_taxonomy_id'] . ', ' . (int) $row['term_order'] . ')';
            if (count($values) >= 500) $write_batch($prefix_rel, $values);
        }
        $rel_count += count($rels);
        unset($rels);
    }
    $write_batch($prefix_rel, $values);

    fclose($fh);

    // Marquer le backup comme fait (vérifié par 04-apply.php)
    set_transient('category_cleanup_backup_done', $backup_path, DAY_IN_SECONDS * 7);

    // ─── Sortie HTML ────────────────────────────────────────
    $size_kb = round(filesize($backup_path) / 1024, 1);

    echo "<div style='font-family:-apple-system,BlinkMacSystemFont,sans-serif;max-width:800px;margin:20px auto;padding:20px;'>";
    echo '<h1>Backup des categories</h1>';

    echo "<div style='padding:16px;background:#e8f5e9;border:1px solid #4caf50;border-radius:4px;margin:16px 0;'>"
        . '<strong>Backup genere avec succes</strong><br>'
        . 'Fichier : ' . esc_html($backup_path) . '<br>'
        . "Taille : {$size_kb} Ko<br>"
        . "Contenu : {$terms_count} termes, {$taxos_count} taxonomies, {$rel_count} relations"
        . '</div>';

    echo "<p><a href='" . esc_url($backup_url) . "' download style='padding:10px 20px;background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;display:inline-block;'>Telecharger le backup SQL</a></p>";

    echo "<p style='padding:12px;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;'>"
        . '<strong>Important :</strong> telechargez ce fichier et conservez-le en lieu sur AVANT d\'executer 04-apply.php. '
        . 'Le fichier est accessible publiquement tant qu\'il est sur le serveur — supprimez-le apres telechargement : '
        . esc_html($backup_path) . '</p>';

    echo '</div>';
    exit;
};

if (did_action('init')) {
    $catcleanup_backup();
} else {
    add_action('init', $catcleanup_backup);
}
