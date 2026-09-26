<?php
/* =====================================================================
   MEILLEURTEST — OUTIL : inventaire des comparatifs + propositions de
   multi-comparatifs. ADMIN UNIQUEMENT, NE MODIFIE RIEN (lecture seule).

   Usage : page privée « Outil multi-comparatif » → 1 élément Code Bricks →
   coller ce fichier → approuver (Code review) → afficher la page.
   Les visiteurs et non-admins ne voient rien.

   Règles :
   - comparatifs publiés + privés (les sous-comparatifs déjà passés en privé
     restent visibles) ;
   - « Principal » = aucun attribut produit → seul candidat multi-comparatif ;
   - « Sous-comparatif possible » = a au moins un attribut ET un principal
     existe avec EXACTEMENT le même type de produit → proposé sous ce principal ;
   - « Orphelin » = a des attributs mais aucun principal pour ce type.
   Deux CSV (séparateur « ; », UTF-8, s'ouvrent dans Excel) :
   inventaire complet (1 ligne par comparatif) + synthèse (1 ligne par
   multi-comparatif proposé).
   ===================================================================== */
if ( ! current_user_can( 'manage_options' ) ) { return; }

$CX_POST_TYPE = 'comparatif';
$CX_STATUSES  = array( 'publish', 'private' );
$CX_TAX_PROD  = 'post-type-produit';
$CX_TAX_ATTR  = 'post-type-attribut';
$CX_TAG       = 'multi-comparatif';
$CX_FIELD     = 'mltv5_sous_comparatifs';
$CX_WITH_TV   = true; // nb de produits (top_avis_ids) — mettre false si la page est trop lente

if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }

if ( ! function_exists( 'mtcx_txt' ) ) {
  function mtcx_txt( $s ) { return trim( html_entity_decode( (string) $s, ENT_QUOTES, 'UTF-8' ) ); }
}
if ( ! function_exists( 'mtcx_ids' ) ) {
  /* Valeur d'un champ Relation (IDs, objets ou sérialisé) → liste d'IDs */
  function mtcx_ids( $v ) {
    $v = maybe_unserialize( $v );
    if ( ! is_array( $v ) ) { $v = ( $v === '' || $v === null ) ? array() : array( $v ); }
    $out = array();
    foreach ( $v as $x ) { $x = is_object( $x ) ? (int) $x->ID : (int) $x; if ( $x > 0 ) { $out[] = $x; } }
    return array_values( array_unique( $out ) );
  }
}

$ids = get_posts( array(
  'post_type'        => $CX_POST_TYPE,
  'post_status'      => $CX_STATUSES,
  'posts_per_page'   => -1,
  'fields'           => 'ids',
  'orderby'          => 'ID',
  'order'            => 'ASC',
  'no_found_rows'    => true,
  'suppress_filters' => true,
) );
if ( empty( $ids ) ) { echo '<p>Aucun comparatif trouvé (type « ' . esc_html( $CX_POST_TYPE ) . ' »).</p>'; return; }
if ( function_exists( '_prime_post_caches' ) ) { _prime_post_caches( $ids, true, true ); }

/* ---------------------------------------------------------------------
   1. Lecture : type de produit, attributs, statut, étiquette, champ relation
   --------------------------------------------------------------------- */
$rows       = array();
$principals = array(); // clé type → [ids]
$attached   = array(); // sous-comparatif → [parents qui le listent déjà]
foreach ( $ids as $id ) {
  $p = get_post( $id );
  if ( ! $p ) { continue; }
  $tp = get_the_terms( $id, $CX_TAX_PROD );
  $ta = get_the_terms( $id, $CX_TAX_ATTR );
  $tp = is_array( $tp ) ? $tp : array();
  $ta = is_array( $ta ) ? $ta : array();

  $p_ids = array_map( 'intval', wp_list_pluck( $tp, 'term_id' ) );
  sort( $p_ids );
  $p_names = array_map( 'mtcx_txt', wp_list_pluck( $tp, 'name' ) );
  $a_names = array_map( 'mtcx_txt', wp_list_pluck( $ta, 'name' ) );
  natcasesort( $p_names );
  natcasesort( $a_names );

  $n_prod   = '';
  $type_lbl = '';
  if ( $CX_WITH_TV && function_exists( 'get_all_template_variables' ) ) {
    $tv       = get_all_template_variables( $id );
    $n_prod   = ( isset( $tv['top_avis_ids'] ) && is_array( $tv['top_avis_ids'] ) ) ? count( $tv['top_avis_ids'] ) : 0;
    $type_lbl = isset( $tv['type_de_produit_au_pluriel'] ) ? mtcx_txt( $tv['type_de_produit_au_pluriel'] ) : '';
  }

  $subs_cfg = mtcx_ids( get_post_meta( $id, $CX_FIELD, true ) );
  foreach ( $subs_cfg as $s ) { $attached[ $s ][] = $id; }

  $key = implode( ',', $p_ids );
  $rows[ $id ] = array(
    'id'       => $id,
    'title'    => mtcx_txt( $p->post_title ),
    'status'   => $p->post_status,
    'key'      => $key,
    'type'     => implode( ' | ', $p_names ),
    'type_ids' => $key,
    'type_lbl' => $type_lbl,
    'attrs'    => array_values( $a_names ),
    'n_prod'   => $n_prod,
    'tag'      => has_term( $CX_TAG, 'post_tag', $id ),
    'subs_cfg' => $subs_cfg,
    'url'      => get_permalink( $id ),
    'role'     => '',
    'parent'   => 0,
    'n_subs'   => 0,
    'notes'    => array(),
  );
  if ( $key !== '' && empty( $ta ) ) { $principals[ $key ][] = $id; }
}

/* ---------------------------------------------------------------------
   2. Choix du principal quand plusieurs comparatifs sans attribut partagent
      le même type : étiqueté multi-comparatif > publié > plus de produits > plus petit ID
   --------------------------------------------------------------------- */
$chosen = array();
foreach ( $principals as $key => $list ) {
  usort( $list, function ( $a, $b ) use ( $rows ) {
    $ra = $rows[ $a ]; $rb = $rows[ $b ];
    if ( $ra['tag'] !== $rb['tag'] ) { return $ra['tag'] ? -1 : 1; }
    if ( ( $ra['status'] === 'publish' ) !== ( $rb['status'] === 'publish' ) ) { return $ra['status'] === 'publish' ? -1 : 1; }
    if ( (int) $ra['n_prod'] !== (int) $rb['n_prod'] ) { return (int) $rb['n_prod'] - (int) $ra['n_prod']; }
    return $a - $b;
  } );
  $chosen[ $key ] = $list[0];
  if ( count( $list ) > 1 ) {
    foreach ( $list as $pid ) {
      $others = array_diff( $list, array( $pid ) );
      $rows[ $pid ]['notes'][] = 'Doublon possible : autre(s) comparatif(s) sans attribut pour ce type (ID ' . implode( ', ', $others ) . ')'
        . ( $pid === $list[0] ? ' — retenu comme multi-comparatif' : ' — non retenu' );
    }
  }
}

/* ---------------------------------------------------------------------
   3. Rôles + propositions
   --------------------------------------------------------------------- */
foreach ( $rows as $id => &$r ) {
  if ( $r['key'] === '' ) {
    $r['role'] = 'Sans type de produit';
    $r['notes'][] = 'Aucun terme « ' . $CX_TAX_PROD . ' » : impossible à classer';
  } elseif ( empty( $r['attrs'] ) ) {
    $r['role'] = 'Principal';
  } elseif ( isset( $chosen[ $r['key'] ] ) ) {
    $r['role']   = 'Sous-comparatif possible';
    $r['parent'] = $chosen[ $r['key'] ];
    if ( count( $r['attrs'] ) > 1 ) { $r['notes'][] = count( $r['attrs'] ) . ' attributs (variante combinée)'; }
  } else {
    $r['role'] = 'Orphelin';
    $r['notes'][] = 'Aucun comparatif principal (sans attribut) pour ce type';
  }
  if ( $CX_WITH_TV && $r['key'] !== '' && (int) $r['n_prod'] === 0 ) {
    $r['notes'][] = 'Liste de produits vide (top_avis_ids)' . ( $r['status'] === 'private' ? ' — cache non calculé pour les privés ?' : '' );
  }
  if ( isset( $attached[ $id ] ) ) {
    $by = $attached[ $id ];
    if ( $r['parent'] && ! in_array( $r['parent'], $by, true ) ) {
      $r['notes'][] = 'Déjà rattaché à un AUTRE multi-comparatif (ID ' . implode( ', ', $by ) . ') que celui proposé';
    }
    if ( count( $by ) > 1 ) { $r['notes'][] = 'Rattaché à plusieurs multi-comparatifs (ID ' . implode( ', ', $by ) . ')'; }
  }
}
unset( $r );

foreach ( $rows as $id => $r ) {
  if ( $r['parent'] ) { $rows[ $r['parent'] ]['n_subs']++; }
}
foreach ( $rows as $id => &$r ) {
  if ( $r['role'] === 'Principal' && ! empty( $r['subs_cfg'] ) && ! $r['tag'] ) {
    $r['notes'][] = 'Sous-comparatifs configurés mais étiquette « ' . $CX_TAG . ' » absente';
  }
}
unset( $r );

/* Tri : type → principal d'abord → sous-comparatifs (moins d'attributs d'abord) → orphelins */
$role_order = array( 'Principal' => 0, 'Sous-comparatif possible' => 1, 'Orphelin' => 2, 'Sans type de produit' => 3 );
uasort( $rows, function ( $a, $b ) use ( $role_order ) {
  if ( ( $a['key'] === '' ) !== ( $b['key'] === '' ) ) { return $a['key'] === '' ? 1 : -1; }
  $c = strnatcasecmp( $a['type'], $b['type'] );
  if ( $c ) { return $c; }
  $c = $role_order[ $a['role'] ] - $role_order[ $b['role'] ];
  if ( $c ) { return $c; }
  $c = count( $a['attrs'] ) - count( $b['attrs'] );
  if ( $c ) { return $c; }
  $c = strnatcasecmp( implode( ' ', $a['attrs'] ), implode( ' ', $b['attrs'] ) );
  return $c ? $c : $a['id'] - $b['id'];
} );

/* ---------------------------------------------------------------------
   4. CSV
   --------------------------------------------------------------------- */
$csv_inv = array( array(
  'Type de produit', 'ID type', 'Rôle', 'ID comparatif', 'Titre', 'Statut', 'Attributs', 'Nb attributs',
  'ID multi-comparatif proposé', 'Titre multi-comparatif proposé', 'Nb sous-comparatifs proposés',
  'Sous-comparatifs déjà configurés', 'Déjà rattaché à', 'Étiquette multi-comparatif',
  'Nb produits (top_avis_ids)', 'Type (libellé pluriel)', 'URL', 'Édition', 'Remarques',
) );
foreach ( $rows as $id => $r ) {
  $csv_inv[] = array(
    $r['type'], $r['type_ids'], $r['role'], $id, $r['title'], $r['status'],
    implode( ' | ', $r['attrs'] ), count( $r['attrs'] ),
    $r['parent'] ? $r['parent'] : '', $r['parent'] ? $rows[ $r['parent'] ]['title'] : '',
    $r['role'] === 'Principal' ? $r['n_subs'] : '',
    implode( ', ', $r['subs_cfg'] ), isset( $attached[ $id ] ) ? implode( ', ', $attached[ $id ] ) : '',
    $r['tag'] ? 'oui' : 'non', $r['n_prod'], $r['type_lbl'], $r['url'],
    admin_url( 'post.php?post=' . $id . '&action=edit' ), implode( ' ; ', $r['notes'] ),
  );
}

$groups = array(); // principal → [subs]
foreach ( $rows as $id => $r ) { if ( $r['parent'] ) { $groups[ $r['parent'] ][] = $id; } }
$csv_syn = array( array(
  'ID multi-comparatif', 'Titre', 'Type de produit', 'Statut', 'Nb sous-comparatifs proposés',
  'Sous-comparatifs proposés (ID : titre [attributs])', 'IDs à saisir dans « ' . $CX_FIELD . ' »',
  'Déjà configurés', 'Étiquette multi-comparatif', 'Nb produits principal',
) );
foreach ( $rows as $id => $r ) {
  if ( empty( $groups[ $id ] ) ) { continue; }
  $parts = array();
  foreach ( $groups[ $id ] as $s ) { $parts[] = $s . ' : ' . $rows[ $s ]['title'] . ' [' . implode( ', ', $rows[ $s ]['attrs'] ) . ']'; }
  $csv_syn[] = array(
    $id, $r['title'], $r['type'], $r['status'], count( $groups[ $id ] ), implode( ' ; ', $parts ),
    implode( ', ', $groups[ $id ] ), implode( ', ', $r['subs_cfg'] ), $r['tag'] ? 'oui' : 'non', $r['n_prod'],
  );
}

/* Compteurs */
$cnt = array_fill_keys( array_keys( $role_order ), 0 );
foreach ( $rows as $r ) { $cnt[ $r['role'] ]++; }
$n_multi = count( $groups );
$uid     = 'mtcx-' . wp_create_nonce( 'mtcx' );
?>
<div class="mtcx" id="<?php echo esc_attr( $uid ); ?>" style="font:14px/1.5 Inter,system-ui,sans-serif;color:#14181d;max-width:1100px;margin:0 auto">
  <h2 style="font-size:24px;margin:0 0 6px">Inventaire des comparatifs &amp; multi-comparatifs proposés</h2>
  <p style="margin:0 0 14px;color:#666">Outil admin, lecture seule. <?php echo count( $rows ); ?> comparatifs (publiés + privés).</p>
  <ul style="margin:0 0 16px;padding-left:18px">
    <li><b><?php echo (int) $n_multi; ?></b> multi-comparatifs proposés (principaux ayant au moins un sous-comparatif)</li>
    <li><?php echo (int) $cnt['Principal']; ?> principaux (sans attribut) · <?php echo (int) $cnt['Sous-comparatif possible']; ?> sous-comparatifs possibles · <?php echo (int) $cnt['Orphelin']; ?> orphelins · <?php echo (int) $cnt['Sans type de produit']; ?> sans type de produit</li>
  </ul>
  <p style="display:flex;gap:10px;flex-wrap:wrap;margin:0 0 22px">
    <button type="button" data-csv="inv" style="padding:9px 16px;border:0;border-radius:8px;background:#1f5fbf;color:#fff;font-weight:600;cursor:pointer">Télécharger l'inventaire complet (CSV)</button>
    <button type="button" data-csv="syn" style="padding:9px 16px;border:0;border-radius:8px;background:#0f6b54;color:#fff;font-weight:600;cursor:pointer">Télécharger la synthèse des multi-comparatifs (CSV)</button>
  </p>

  <h3 style="font-size:18px;margin:0 0 8px">Multi-comparatifs proposés</h3>
  <table style="width:100%;border-collapse:collapse;font-size:13px;margin:0 0 28px">
    <thead><tr style="text-align:left;border-bottom:2px solid #d8dde3">
      <th style="padding:6px">Multi-comparatif (principal)</th><th style="padding:6px">Sous-comparatifs proposés</th><th style="padding:6px">Remarques</th>
    </tr></thead>
    <tbody>
    <?php foreach ( $rows as $id => $r ) : if ( empty( $groups[ $id ] ) ) { continue; } ?>
      <tr style="border-bottom:1px solid #e8eaed;vertical-align:top">
        <td style="padding:6px"><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['title'] ); ?></a><br><span style="color:#666">ID <?php echo (int) $id; ?> · <?php echo esc_html( $r['type'] ); ?></span></td>
        <td style="padding:6px"><?php foreach ( $groups[ $id ] as $s ) : ?>
          <div><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $s . '&action=edit' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $rows[ $s ]['title'] ); ?></a> <span style="color:#666">(ID <?php echo (int) $s; ?> · <?php echo esc_html( implode( ', ', $rows[ $s ]['attrs'] ) ); ?><?php echo $rows[ $s ]['status'] === 'private' ? ' · privé' : ''; ?>)</span></div>
        <?php endforeach; ?></td>
        <td style="padding:6px;color:#666"><?php
          $n = array_merge( $r['notes'], array() );
          foreach ( $groups[ $id ] as $s ) { foreach ( $rows[ $s ]['notes'] as $sn ) { $n[] = 'ID ' . $s . ' : ' . $sn; } }
          echo esc_html( implode( ' · ', $n ) );
        ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <?php if ( $cnt['Orphelin'] || $cnt['Sans type de produit'] ) : ?>
  <h3 style="font-size:18px;margin:0 0 8px">À vérifier (orphelins, sans type)</h3>
  <table style="width:100%;border-collapse:collapse;font-size:13px">
    <tbody>
    <?php foreach ( $rows as $id => $r ) : if ( $r['role'] !== 'Orphelin' && $r['role'] !== 'Sans type de produit' ) { continue; } ?>
      <tr style="border-bottom:1px solid #e8eaed">
        <td style="padding:6px"><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $id . '&action=edit' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['title'] ); ?></a> <span style="color:#666">(ID <?php echo (int) $id; ?>)</span></td>
        <td style="padding:6px"><?php echo esc_html( $r['type'] !== '' ? $r['type'] : '—' ); ?></td>
        <td style="padding:6px"><?php echo esc_html( implode( ', ', $r['attrs'] ) ); ?></td>
        <td style="padding:6px;color:#666"><?php echo esc_html( $r['role'] ); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<script>
(function () {
  var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
  if (!root) return;
  var data = <?php echo wp_json_encode( array( 'inv' => $csv_inv, 'syn' => $csv_syn ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
  var names = { inv: 'comparatifs-inventaire', syn: 'multi-comparatifs-synthese' };
  function cell(v) {
    v = (v === null || v === undefined) ? '' : String(v);
    return /[";\n\r]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
  }
  root.querySelectorAll('button[data-csv]').forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-csv');
      var csv = data[k].map(function (r) { return r.map(cell).join(';'); }).join('\r\n');
      var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = names[k] + '-' + new Date().toISOString().slice(0, 10) + '.csv';
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
    });
  });
})();
</script>
