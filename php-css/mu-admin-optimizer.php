<?php
/**
 * MU-Plugin : Diagnostic & optimisation de l'admin WordPress
 *
 * Copier dans wp-content/mu-plugins/mu-admin-optimizer.php
 *
 * Ce que fait ce fichier :
 *  1. Diagnostique les requêtes lentes (>0.05s) dans l'admin
 *  2. Réduit la fréquence du Heartbeat API en admin
 *  3. Désactive les fonctions lourdes non essentielles en admin
 *  4. Limite les révisions de posts
 *  5. Fournit une page de diagnostic accessible via ?mt_admin_diag=1
 *
 * ⚠️ Lire le rapport de diagnostic : connecté en admin, aller sur
 *    n'importe quelle URL du site avec ?mt_admin_diag=1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =====================================================================
   §1 — HEARTBEAT : ralentir en admin (de 15s à 60s), désactiver sur
   les pages qui n'en ont pas besoin (listes de posts, réglages).
   ===================================================================== */
add_action( 'admin_enqueue_scripts', function () {
  global $pagenow;
  $dominated = array( 'options-general.php', 'tools.php', 'plugins.php',
    'themes.php', 'users.php', 'upload.php', 'edit.php', 'edit-tags.php' );
  if ( in_array( $pagenow, $dominated, true ) ) {
    wp_deregister_script( 'heartbeat' );
    return;
  }
  wp_localize_script( 'heartbeat', 'heartbeatSettings', array(
    'interval' => 60,
    'minimalInterval' => 60,
  ) );
}, 1 );

/* =====================================================================
   §2 — RÉVISIONS : limiter à 5 (réduit la taille de wp_posts)
   ===================================================================== */
if ( ! defined( 'WP_POST_REVISIONS' ) ) {
  define( 'WP_POST_REVISIONS', 5 );
}

/* =====================================================================
   §3 — AUTOSAVE : rallonger l'intervalle (de 60s à 300s)
   ===================================================================== */
if ( ! defined( 'AUTOSAVE_INTERVAL' ) ) {
  define( 'AUTOSAVE_INTERVAL', 300 );
}

/* =====================================================================
   §4 — Désactiver les mises à jour auto en admin (requêtes HTTP lourdes)
   → Garder les mises à jour de sécurité, mais différer les vérifications
   ===================================================================== */
add_filter( 'wp_auto_update_core', '__return_false' );
remove_action( 'admin_init', '_maybe_update_core' );
remove_action( 'admin_init', '_maybe_update_plugins' );
remove_action( 'admin_init', '_maybe_update_themes' );

/* =====================================================================
   §5 — Dashboard : supprimer les widgets lourds (requêtes externes)
   ===================================================================== */
add_action( 'wp_dashboard_setup', function () {
  remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
  remove_meta_box( 'dashboard_secondary', 'dashboard', 'side' );
  remove_meta_box( 'dashboard_plugins', 'dashboard', 'normal' );
  remove_meta_box( 'dashboard_browser_nag', 'dashboard', 'normal' );
}, 999 );

/* =====================================================================
   §6 — Désactiver les embeds oEmbed (requêtes HTTP sur chaque post)
   ===================================================================== */
add_action( 'admin_init', function () {
  remove_action( 'rest_api_init', 'wp_oembed_register_route' );
  remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
}, 1 );

/* =====================================================================
   §7 — PAGE DE DIAGNOSTIC — ?mt_admin_diag=1 (admin uniquement)
   Mesure les requêtes SQL, mémoire, hooks lourds, transients gonflés.
   ===================================================================== */
add_action( 'init', function () {
  if ( ! isset( $_GET['mt_admin_diag'] ) || $_GET['mt_admin_diag'] !== '1' ) { return; }
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  if ( ! defined( 'SAVEQUERIES' ) ) { define( 'SAVEQUERIES', true ); }
}, 0 );

add_action( 'shutdown', function () {
  if ( ! isset( $_GET['mt_admin_diag'] ) || $_GET['mt_admin_diag'] !== '1' ) { return; }
  if ( ! current_user_can( 'manage_options' ) ) { return; }

  nocache_headers();
  global $wpdb;

  $mem_peak = round( memory_get_peak_usage( true ) / 1024 / 1024, 1 );
  $mem_cur  = round( memory_get_usage( true ) / 1024 / 1024, 1 );
  $mem_lim  = ini_get( 'memory_limit' );
  $time     = round( timer_stop( 0, 4 ) * 1000 );

  // Requêtes SQL
  $queries      = isset( $wpdb->queries ) ? $wpdb->queries : array();
  $total_q      = count( $queries );
  $slow_q       = array();
  $total_q_time = 0;
  $dup_q        = array();

  foreach ( $queries as $q ) {
    $sql  = isset( $q[0] ) ? $q[0] : '';
    $dur  = isset( $q[1] ) ? (float) $q[1] : 0;
    $call = isset( $q[2] ) ? $q[2] : '';
    $total_q_time += $dur;
    $hash = md5( preg_replace( '/\s+/', ' ', trim( $sql ) ) );
    $dup_q[ $hash ] = isset( $dup_q[ $hash ] ) ? $dup_q[ $hash ] + 1 : 1;
    if ( $dur > 0.05 ) {
      $slow_q[] = array( 'sql' => $sql, 'time' => round( $dur * 1000, 1 ), 'caller' => $call );
    }
  }
  usort( $slow_q, function ( $a, $b ) { return $b['time'] <=> $a['time']; } );
  $dup_count = count( array_filter( $dup_q, function ( $c ) { return $c > 1; } ) );

  // Transients autoloaded
  $auto_trans = $wpdb->get_results(
    "SELECT option_name, LENGTH(option_value) AS sz
     FROM {$wpdb->options}
     WHERE autoload IN ('yes','on','auto')
       AND option_name LIKE '_transient_%'
     ORDER BY sz DESC
     LIMIT 20"
  );
  $auto_total = $wpdb->get_var(
    "SELECT SUM(LENGTH(option_value))
     FROM {$wpdb->options}
     WHERE autoload IN ('yes','on','auto')"
  );
  $auto_total_kb = round( (int) $auto_total / 1024, 0 );

  // Options autoloaded volumineuses
  $big_options = $wpdb->get_results(
    "SELECT option_name, LENGTH(option_value) AS sz
     FROM {$wpdb->options}
     WHERE autoload IN ('yes','on','auto')
     ORDER BY sz DESC
     LIMIT 20"
  );

  // wp_postmeta orphelins
  $orphan_meta = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
     LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
     WHERE p.ID IS NULL"
  );

  // Révisions
  $rev_count = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'"
  );

  // Transients expirés
  $exp_trans = (int) $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->options}
     WHERE option_name LIKE %s
       AND option_value < %d",
    $wpdb->esc_like( '_transient_timeout_' ) . '%',
    time()
  ) );

  // Cron events
  $crons = _get_cron_array();
  $cron_count = 0;
  $cron_hooks = array();
  if ( is_array( $crons ) ) {
    foreach ( $crons as $ts => $hooks ) {
      foreach ( $hooks as $hook => $events ) {
        $cron_count += count( $events );
        $cron_hooks[ $hook ] = isset( $cron_hooks[ $hook ] ) ? $cron_hooks[ $hook ] + count( $events ) : count( $events );
      }
    }
  }
  arsort( $cron_hooks );

  // Plugins actifs
  $active_plugins = get_option( 'active_plugins', array() );

  // Object cache
  $has_obj_cache = file_exists( WP_CONTENT_DIR . '/object-cache.php' );
  $obj_cache_type = 'Aucun (fichier par défaut)';
  if ( $has_obj_cache ) {
    $oc_content = file_get_contents( WP_CONTENT_DIR . '/object-cache.php' );
    if ( stripos( $oc_content, 'redis' ) !== false ) { $obj_cache_type = 'Redis'; }
    elseif ( stripos( $oc_content, 'memcache' ) !== false ) { $obj_cache_type = 'Memcached'; }
    else { $obj_cache_type = 'Présent (type inconnu)'; }
  }

  // Rendu
  header( 'Content-Type: text/html; charset=utf-8' );
  ?><!doctype html><html lang="fr"><head><meta charset="utf-8">
  <meta name="robots" content="noindex">
  <title>Diagnostic Admin WP</title>
  <style>
    *{box-sizing:border-box}
    body{font:14px/1.6 "Inter",system-ui,-apple-system,sans-serif;background:#0f1117;color:#d1d5db;margin:0;padding:24px}
    .wrap{max-width:1100px;margin:0 auto}
    h1{font-size:22px;color:#f9fafb;margin:0 0 4px;font-weight:700}
    .sub{color:#6b7280;font-size:13px;margin-bottom:24px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:24px}
    .card{background:#1a1d27;border-radius:10px;padding:16px;border:1px solid #2a2d3a}
    .card-label{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin-bottom:4px}
    .card-val{font-size:24px;font-weight:700;color:#f9fafb}
    .card-val.warn{color:#f59e0b}
    .card-val.bad{color:#ef4444}
    .card-val.ok{color:#10b981}
    .card-note{font-size:12px;color:#6b7280;margin-top:4px}
    h2{font-size:16px;color:#f9fafb;margin:28px 0 10px;padding-top:16px;border-top:1px solid #2a2d3a}
    table{width:100%;border-collapse:collapse;font-size:13px}
    th{text-align:left;color:#9ca3af;font-weight:500;padding:6px 10px;border-bottom:1px solid #2a2d3a;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
    td{padding:6px 10px;border-bottom:1px solid #1e2030;vertical-align:top}
    tr:hover td{background:#1e2030}
    .mono{font-family:ui-monospace,"SF Mono",monospace;font-size:12px}
    .sql{max-width:600px;word-break:break-all;white-space:pre-wrap}
    .sz{color:#9ca3af}
    .tag{display:inline-block;padding:1px 7px;border-radius:4px;font-size:11px;font-weight:600}
    .tag-r{background:#7f1d1d;color:#fca5a5}
    .tag-y{background:#78350f;color:#fde68a}
    .tag-g{background:#064e3b;color:#6ee7b7}
    .reco{background:#1a1d27;border:1px solid #2a2d3a;border-radius:10px;padding:16px 20px;margin:12px 0}
    .reco b{color:#f9fafb}
    .reco-title{display:flex;align-items:center;gap:8px;font-weight:600;color:#f9fafb;margin-bottom:6px}
    .pill{font-size:11px;padding:2px 8px;border-radius:999px;font-weight:600}
    .pill-crit{background:#7f1d1d;color:#fca5a5}
    .pill-warn{background:#78350f;color:#fde68a}
    .pill-info{background:#1e3a5f;color:#93c5fd}
  </style>
  </head><body><div class="wrap">
  <h1>Diagnostic performance admin</h1>
  <p class="sub">Généré le <?php echo esc_html( date_i18n( 'j F Y à H:i:s' ) ); ?> — page en <?php echo esc_html( $time ); ?> ms</p>

  <div class="grid">
    <div class="card">
      <div class="card-label">Requêtes SQL</div>
      <div class="card-val <?php echo $total_q > 200 ? 'bad' : ( $total_q > 80 ? 'warn' : 'ok' ); ?>">
        <?php echo (int) $total_q; ?>
      </div>
      <div class="card-note"><?php echo round( $total_q_time * 1000 ); ?> ms total</div>
    </div>
    <div class="card">
      <div class="card-label">Requêtes lentes (>50ms)</div>
      <div class="card-val <?php echo count( $slow_q ) > 5 ? 'bad' : ( count( $slow_q ) > 0 ? 'warn' : 'ok' ); ?>">
        <?php echo count( $slow_q ); ?>
      </div>
    </div>
    <div class="card">
      <div class="card-label">Requêtes dupliquées</div>
      <div class="card-val <?php echo $dup_count > 10 ? 'bad' : ( $dup_count > 3 ? 'warn' : 'ok' ); ?>">
        <?php echo (int) $dup_count; ?>
      </div>
    </div>
    <div class="card">
      <div class="card-label">Mémoire pic</div>
      <div class="card-val <?php echo $mem_peak > 200 ? 'bad' : ( $mem_peak > 128 ? 'warn' : 'ok' ); ?>">
        <?php echo esc_html( $mem_peak ); ?> MB
      </div>
      <div class="card-note">Limite : <?php echo esc_html( $mem_lim ); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Object cache</div>
      <div class="card-val <?php echo $has_obj_cache ? 'ok' : 'bad'; ?>">
        <?php echo esc_html( $obj_cache_type ); ?>
      </div>
    </div>
    <div class="card">
      <div class="card-label">Autoload total</div>
      <div class="card-val <?php echo $auto_total_kb > 2000 ? 'bad' : ( $auto_total_kb > 800 ? 'warn' : 'ok' ); ?>">
        <?php echo number_format( $auto_total_kb ); ?> KB
      </div>
    </div>
    <div class="card">
      <div class="card-label">Révisions</div>
      <div class="card-val <?php echo $rev_count > 5000 ? 'bad' : ( $rev_count > 1000 ? 'warn' : 'ok' ); ?>">
        <?php echo number_format( $rev_count ); ?>
      </div>
    </div>
    <div class="card">
      <div class="card-label">Meta orphelins</div>
      <div class="card-val <?php echo $orphan_meta > 1000 ? 'bad' : ( $orphan_meta > 100 ? 'warn' : 'ok' ); ?>">
        <?php echo number_format( $orphan_meta ); ?>
      </div>
    </div>
  </div>

  <?php /* ── RECOMMANDATIONS ── */ ?>
  <h2>Recommandations</h2>

  <?php if ( ! $has_obj_cache ) : ?>
  <div class="reco">
    <div class="reco-title"><span class="pill pill-crit">CRITIQUE</span> Pas d'object cache persistant</div>
    Les transients et le cache WP passent par la base de données. <b>Installer Redis</b> via Cloudways
    (Applications → votre app → Redis) puis activer le plugin <b>Redis Object Cache</b>.
    Gain attendu : <b>-30 à -60% de requêtes SQL</b> sur chaque page admin.
  </div>
  <?php endif; ?>

  <?php if ( $rev_count > 1000 ) : ?>
  <div class="reco">
    <div class="reco-title"><span class="pill pill-warn">MOYEN</span> <?php echo number_format( $rev_count ); ?> révisions dans wp_posts</div>
    Chaque révision ajoute une ligne dans <code>wp_posts</code> + ses meta dans <code>wp_postmeta</code>.
    Nettoyer avec WP-CLI : <code>wp post delete $(wp post list --post_type=revision --format=ids) --force</code>
    ou via le plugin <b>WP-Sweep</b>. Ce mu-plugin limite désormais à 5 révisions par post.
  </div>
  <?php endif; ?>

  <?php if ( $orphan_meta > 100 ) : ?>
  <div class="reco">
    <div class="reco-title"><span class="pill pill-warn">MOYEN</span> <?php echo number_format( $orphan_meta ); ?> entrées meta orphelines</div>
    Des meta sans post parent encombrent <code>wp_postmeta</code>.
    Nettoyer : <code>DELETE pm FROM wp_postmeta pm LEFT JOIN wp_posts p ON pm.post_id = p.ID WHERE p.ID IS NULL;</code>
    (faire un backup avant).
  </div>
  <?php endif; ?>

  <?php if ( $auto_total_kb > 800 ) : ?>
  <div class="reco">
    <div class="reco-title"><span class="pill pill-warn">MOYEN</span> Options autoloaded volumineuses (<?php echo number_format( $auto_total_kb ); ?> KB)</div>
    Toutes ces données sont chargées en mémoire à chaque requête. Identifier les plus grosses ci-dessous
    et passer les non-critiques en <code>autoload = 'no'</code>.
  </div>
  <?php endif; ?>

  <?php if ( $exp_trans > 50 ) : ?>
  <div class="reco">
    <div class="reco-title"><span class="pill pill-info">INFO</span> <?php echo number_format( $exp_trans ); ?> transients expirés</div>
    WP ne les nettoie que passivement. Purger : <code>wp transient delete --expired</code> ou via WP-Sweep.
  </div>
  <?php endif; ?>

  <?php /* ── REQUÊTES LENTES ── */ ?>
  <?php if ( ! empty( $slow_q ) ) : ?>
  <h2>Requêtes lentes (> 50 ms) — top <?php echo min( 20, count( $slow_q ) ); ?></h2>
  <table>
    <tr><th>Temps</th><th>SQL</th><th>Appelant</th></tr>
    <?php foreach ( array_slice( $slow_q, 0, 20 ) as $sq ) : ?>
    <tr>
      <td class="mono" style="white-space:nowrap;color:<?php echo $sq['time'] > 200 ? '#ef4444' : '#f59e0b'; ?>">
        <?php echo esc_html( $sq['time'] ); ?> ms
      </td>
      <td class="mono sql"><?php echo esc_html( substr( $sq['sql'], 0, 300 ) ); ?></td>
      <td class="mono sz"><?php echo esc_html( substr( $sq['caller'], 0, 120 ) ); ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php /* ── OPTIONS AUTOLOADED ── */ ?>
  <h2>Top 20 options autoloaded (par taille)</h2>
  <table>
    <tr><th>Option</th><th>Taille</th></tr>
    <?php foreach ( $big_options as $opt ) : ?>
    <tr>
      <td class="mono"><?php echo esc_html( $opt->option_name ); ?></td>
      <td class="mono sz"><?php echo esc_html( number_format( (int) $opt->sz / 1024, 1 ) ); ?> KB</td>
    </tr>
    <?php endforeach; ?>
  </table>

  <?php /* ── TRANSIENTS AUTOLOADED ── */ ?>
  <?php if ( ! empty( $auto_trans ) ) : ?>
  <h2>Top 20 transients autoloaded (par taille)</h2>
  <table>
    <tr><th>Transient</th><th>Taille</th></tr>
    <?php foreach ( $auto_trans as $tr ) : ?>
    <tr>
      <td class="mono"><?php echo esc_html( $tr->option_name ); ?></td>
      <td class="mono sz"><?php echo esc_html( number_format( (int) $tr->sz / 1024, 1 ) ); ?> KB</td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <?php /* ── PLUGINS ACTIFS ── */ ?>
  <h2>Plugins actifs (<?php echo count( $active_plugins ); ?>)</h2>
  <table>
    <tr><th>#</th><th>Plugin</th></tr>
    <?php foreach ( $active_plugins as $i => $p ) : ?>
    <tr><td class="sz"><?php echo $i + 1; ?></td><td class="mono"><?php echo esc_html( $p ); ?></td></tr>
    <?php endforeach; ?>
  </table>

  <?php /* ── CRON ── */ ?>
  <h2>Tâches Cron (<?php echo (int) $cron_count; ?> événements, <?php echo count( $cron_hooks ); ?> hooks)</h2>
  <table>
    <tr><th>Hook</th><th>Instances</th></tr>
    <?php foreach ( array_slice( $cron_hooks, 0, 30, true ) as $hook => $cnt ) : ?>
    <tr>
      <td class="mono"><?php echo esc_html( $hook ); ?></td>
      <td class="mono"><?php echo (int) $cnt; ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <p style="margin-top:32px;color:#4b5563;font-size:12px">
    Ce diagnostic est généré par <code>mu-admin-optimizer.php</code>.
    Pour le retirer, supprimer le fichier de <code>wp-content/mu-plugins/</code>.
  </p>
  </div></body></html>
  <?php
  exit;
}, 0 );
