<?php
$this_id   = get_the_ID();
extract(get_all_template_variables($this_id));
$post_type = get_post_type($this_id);
$total_avis = !empty($top_avis_ids) ? count($top_avis_ids) : 0;
$mod = date_i18n('j F Y', get_the_modified_time('U'));

if ( ! function_exists( 'mt_intro_reco' ) ) {
  /* Phrase de recommandation dynamique en fin d'intro : n°1 du classement
     + second produit (le meilleur pas cher s'il existe, sinon le rang 2).
     Variantes déterministes tirées des chiffres de l'ID du comparatif :
     dernier chiffre -> accroche, avant-dernier -> phrase n°1,
     antépénultième -> phrase budget/alternative (10 variantes chacune). */
  function mt_intro_reco( $page_id, $ids, $type_plur ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( count( $ids ) < 2 ) { return ''; }

    /* Données produits : nom + prix */
    $prods = array();
    foreach ( $ids as $i => $pid ) {
      $brand = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $model === '' ) { $model = (string) get_the_title( $pid ); }
      $name = trim( $brand . ' ' . $model );
      $raw  = get_field( 'mltv5_prix_indicatif', $pid );
      $c    = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $raw );
      $c    = str_replace( ',', '.', $c );
      $prods[] = array( 'rank' => $i + 1, 'name' => $name, 'price' => is_numeric( $c ) ? (float) $c : 0.0 );
    }
    if ( $prods[0]['name'] === '' ) { return ''; }

    /* Second produit : le moins cher hors n°1 (si >= 2 prix et vraiment moins
       cher que le n°1), sinon le rang 2 en simple alternative. */
    $n_price = 0; $budget = null;
    foreach ( $prods as $p ) {
      if ( $p['price'] > 0 ) {
        $n_price++;
        if ( $p['rank'] !== 1 && ( $budget === null || $p['price'] < $budget['price'] ) ) { $budget = $p; }
      }
    }
    $is_budget = ( $n_price >= 2 && $budget !== null && $budget['name'] !== ''
      && ( $prods[0]['price'] <= 0 || $budget['price'] < $prods[0]['price'] ) );
    $second = $is_budget ? $budget : $prods[1];
    if ( $second['name'] === '' ) { $second = null; }

    /* Liens vers les avis détaillés (ancres #produit-n-{rang}) */
    $p1 = '<a href="#produit-n-1">' . esc_html( $prods[0]['name'] ) . '</a>';
    $p2 = '';
    if ( $second ) {
      $p2 = '<a href="#produit-n-' . (int) $second['rank'] . '">' . esc_html( $second['name'] ) . '</a>';
      if ( $is_budget && $second['price'] > 0 ) {
        $p2 .= ' (environ ' . number_format( $second['price'], 0, ',', "\xc2\xa0" ) . "\xc2\xa0€)";
      }
    }

    $type  = trim( (string) $type_plur );
    $parmi = $type !== '' ? 'parmi les ' . esc_html( mb_strtolower( $type, 'UTF-8' ) ) : 'de ce comparatif';

    /* Chiffres de l'ID, de droite à gauche */
    $s  = (string) abs( (int) $page_id );
    $d1 = (int) substr( $s, -1 );
    $d2 = strlen( $s ) >= 2 ? (int) substr( $s, -2, 1 ) : 0;
    $d3 = strlen( $s ) >= 3 ? (int) substr( $s, -3, 1 ) : 0;

    /* 1) Accroche (ponctuation incluse) */
    $hooks = array(
      'Si vous êtes pressé, ',
      'Pour aller droit au but, ',
      'En deux mots, ',
      'Si vous n\'avez qu\'une minute, ',
      'Pour faire simple, ',
      'À retenir : ',
      'L\'essentiel en bref : ',
      'Verdict express : ',
      'Sans suspense, ',
      'Pour résumer, ',
    );

    /* 2) Recommandation du n°1 (%1$s = produit, %2$s = « parmi les {type} ») */
    $mains = array(
      '%1$s est notre coup de cœur %2$s',
      '%1$s s\'impose en tête de ce comparatif',
      '%1$s domine notre classement',
      '%1$s reste notre recommandation numéro un',
      '%1$s arrive en tête de notre sélection',
      '%1$s décroche la première place de notre comparatif',
      '%1$s est notre valeur sûre %2$s',
      '%1$s remporte notre préférence cette année',
      '%1$s signe le meilleur bilan de notre comparatif',
      '%1$s ressort grand vainqueur de ce guide d\'achat',
    );

    /* 3a) Second produit, version « meilleur pas cher » */
    $budgets = array(
      'Si votre budget est serré, %s offre le meilleur rapport qualité-prix.',
      'Côté petit budget, %s est l\'option la plus abordable de notre sélection.',
      'Pour dépenser moins, %s fait le travail sans vous ruiner.',
      'Les budgets serrés se tourneront vers %s, le choix le plus économique.',
      'À prix plus doux, %s constitue une excellente alternative.',
      'Envie d\'économiser ? %s est la meilleure option à petit prix.',
      'Pour un investissement plus léger, %s reste une valeur sûre.',
      'Si le prix compte avant tout, %s est l\'alternative la plus accessible.',
      'Petit budget ? %s offre l\'essentiel pour moins cher.',
      'En version plus économique, %s tire son épingle du jeu.',
    );

    /* 3b) Second produit, version « alternative » (pas de comparaison de prix possible) */
    $alts = array(
      'Si vous hésitez encore, %s constitue une solide alternative.',
      '%s mérite aussi le détour en second choix.',
      'En alternative sérieuse, %s a également convaincu notre équipe.',
      'Juste derrière, %s complète le podium avec brio.',
      'Autre option remarquée : %s, très proche au classement.',
      'Dans son sillage, %s s\'illustre également.',
      'Pour varier, %s représente un second choix pertinent.',
      'À considérer aussi : %s, qui a marqué des points dans notre classement.',
      'En challenger, %s ne démérite pas.',
      'Notre second favori ? %s, tout simplement.',
    );

    $out = $hooks[ $d1 ] . sprintf( $mains[ $d2 ], $p1, $parmi ) . '.';
    if ( $p2 !== '' ) {
      $out .= ' ' . sprintf( $is_budget ? $budgets[ $d3 ] : $alts[ $d3 ], $p2 );
    }
    return '<p class="mt-lede-reco">' . $out . '</p>';
  }
}

if ( ! function_exists( 'mt_bold_intro' ) ) {
  function mt_bold_intro( $html, $vars ) {
    $ts  = mb_strtolower( trim( isset( $vars['sing'] ) ? $vars['sing'] : '' ), 'UTF-8' );
    $tp  = mb_strtolower( trim( isset( $vars['plur'] ) ? $vars['plur'] : '' ), 'UTF-8' );
    $llm = mb_strtolower( trim( isset( $vars['llm'] )  ? $vars['llm']  : '' ), 'UTF-8' );
    $mf  = mb_strtolower( trim( isset( $vars['mf'] )   ? $vars['mf']   : '' ), 'UTF-8' );

    if ( ( $ts === '' && $tp === '' ) || trim( $html ) === '' ) { return $html; }
    if ( $ts === '' ) { $ts = rtrim( $tp, 's' ); }
    if ( $tp === '' ) { $tp = $ts . 's'; }

    $is_fem  = ( mb_strpos( $mf, 'meilleure' ) !== false );
    $adj_sing = $is_fem ? 'meilleure' : 'meilleur';
    $adj_plur = ( $mf !== '' ) ? $mf : ( $is_fem ? 'meilleures' : 'meilleurs' );

    $patterns = array();

    // « les meilleurs climatiseurs » (article + adj + type)
    if ( $llm !== '' ) {
      $llm_plur = ( mb_strpos( $llm, 'les ' ) === 0 );
      $t = $llm_plur ? $tp : $ts;
      if ( $t !== '' ) { $patterns[] = $llm . ' ' . $t; }
    }
    // « meilleurs climatiseurs » / « meilleur climatiseur »
    if ( $adj_plur !== '' && $tp !== '' ) { $patterns[] = $adj_plur . ' ' . $tp; }
    if ( $adj_sing !== '' && $ts !== '' ) { $patterns[] = $adj_sing . ' ' . $ts; }

    // Combinaisons adjectivales fréquentes
    // [avant_nom?, masc_sing, fem_sing, masc_plur, fem_plur]
    $adjs = array(
      array( false, 'idéal',   'idéale',   'idéaux',   'idéales' ),
      array( false, 'parfait', 'parfaite', 'parfaits', 'parfaites' ),
      array( true,  'bon',     'bonne',    'bons',     'bonnes' ),
      array( false, 'adapté',  'adaptée',  'adaptés',  'adaptées' ),
      array( false, 'incontournable', 'incontournable', 'incontournables', 'incontournables' ),
    );
    foreach ( $adjs as $a ) {
      $as = $is_fem ? $a[2] : $a[1];
      $ap = $is_fem ? $a[4] : $a[3];
      if ( $a[0] ) {
        $patterns[] = $as . ' ' . $ts;
        $patterns[] = $ap . ' ' . $tp;
      } else {
        $patterns[] = $ts . ' ' . $as;
        $patterns[] = $tp . ' ' . $ap;
      }
    }

    // Mot nu (priorité la plus basse)
    $patterns[] = $tp;
    if ( $ts !== $tp ) { $patterns[] = $ts; }

    // Dédoublonner + trier du plus long au plus court
    $patterns = array_values( array_unique( array_filter( $patterns, function( $p ) { return trim( $p ) !== ''; } ) ) );
    usort( $patterns, function( $a, $b ) { return mb_strlen( $b, 'UTF-8' ) - mb_strlen( $a, 'UTF-8' ); } );

    $escaped = array_map( function( $p ) { return preg_quote( $p, '/' ); }, $patterns );
    $regex = '/(?<!\w)(' . implode( '|', $escaped ) . ')(?!\w)/iu';

    // Remplacer uniquement dans les nœuds texte (pas dans les balises HTML),
    // et pas dans du texte déjà en <strong>/<b>
    $in_bold = 0;
    return preg_replace_callback(
      '#(</?(?:strong|b)\b[^>]*>)|(<[^>]*>)|([^<]+)#iu',
      function( $m ) use ( $regex, &$in_bold ) {
        if ( $m[1] !== '' ) {
          if ( $m[1][1] === '/' ) { $in_bold = max( 0, $in_bold - 1 ); } else { $in_bold++; }
          return $m[1];
        }
        if ( $m[2] !== '' ) { return $m[2]; }
        if ( $in_bold > 0 ) { return $m[3]; }
        return preg_replace( $regex, '<strong>$1</strong>', $m[3] );
      },
      $html
    );
  }
}
?>
<div class="mt-left">

  <?php // Fil d'ariane (Rank Math) avec separateur ›
  $bc = do_shortcode('[rank_math_breadcrumb]');
  if (!empty(trim($bc))) {
      $bc = preg_replace('#(<span class="separator">).*?(</span>)#', '$1&nbsp;&rsaquo;&nbsp;$2', $bc);
      echo '<div class="mt-crumb">' . $bc . '</div>';
  } ?>

  <div class="mt-eyebrow">
    <span class="pill">Vérifié</span>
    <span>le <?php echo $mod; ?></span>
  </div>

  <h1 class="mt-h1">
  <?php
    if (!empty($forcer_affichage_du_titre ?? '')) {
        echo esc_html($forcer_affichage_du_titre);
    } elseif ($post_type === 'comparatif') {
        echo 'Les <em>' . $total_avis . ' ' . lcfirst($masculinsfeminins ?? 'meilleures') . ' ' . $type_de_produit_au_pluriel . '</em> en 2026';
        echo !empty($sous_titre ?? '') ? ' : ' . $sous_titre : ' : comparatif et guide d\'achat';
    } else {
        echo esc_html(get_the_title());
    }
  ?>
  </h1>

  <?php // Effets SEO rank math
  $rank_math_title = get_post_meta($this_id, 'rank_math_title');
  $rank_math_description = get_post_meta($this_id, 'rank_math_description');
  if (($template_description ?? '') == 0 || $post_type === 'liste') {
      $new_desc = intro(50, $this_id);
      if (($new_desc <> $rank_math_description) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_description', $new_desc); }
      $p = get_post($this_id);
      if (($p->post_excerpt ?? '') !== $new_desc) { wp_update_post(array('ID'=>$this_id,'post_excerpt'=>$new_desc)); }
  }
  if (!empty($forcer_affichage_du_titre ?? '')) { $new_title = $forcer_affichage_du_titre; }
  elseif ($post_type === 'liste') { $new_title = get_the_title($this_id); }
  else { $new_title = "Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." 2026 | Test par Meilleurtest"; }
  if (($new_title <> $rank_math_title) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_title', $new_title); }
  ?>

  <div class="mt-byline">
    <?php if (!empty($author_avatar_id ?? '')) {
        echo '<span class="mt-avatar">' . wp_get_attachment_image($author_avatar_id, array(30,30), '', array('alt'=>$author_avatar_alt ?? '')) . '</span>';
    } ?>
    <span class="mt-byline-text">
      <span>Par <b><?php echo esc_html($author ?? ''); ?></b></span>
      <span class="mt-dot">&bull;</span>
      <span>Mis à jour le <?php echo $mod; ?></span>
    </span>
  </div>

  <div class="mt-lede"><?php echo mt_bold_intro( $introduction ?? '', array(
    'sing' => $type_de_produit_au_singulier ?? '',
    'plur' => $type_de_produit_au_pluriel ?? '',
    'llm'  => $lalalesmeilleur ?? '',
    'mf'   => $masculinsfeminins ?? '',
  ) );
  if ( $post_type === 'comparatif' ) {
      echo mt_intro_reco( $this_id, $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '' );
  } ?></div>

  <div class="mt-photo">
    <?php echo get_the_post_thumbnail($this_id, 'large', array('class'=>'mt-photo-img')); ?>
    <?php if ($post_type === 'comparatif') {
        echo '<img class="mt-badge" src="https://meilleurtest.fr/wp-content/uploads/2026/07/badge-mt3.png" alt="" style="position:absolute;top:0;left:0;max-width:130px;height:auto;">';
    } ?>
  </div>

</div>
