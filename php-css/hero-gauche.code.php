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
  function mt_intro_reco( $page_id, $ids, $type_plur, $llm = '' ) {
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

    /* Accord en nombre : « les meilleur(e)s » -> le produit se conjugue au
       pluriel (croquettes, couches…). $v( singulier, pluriel ). */
    $pl = ( mb_stripos( trim( (string) $llm ), 'les ' ) === 0 );
    $v  = function ( $sing, $plur ) use ( $pl ) { return $pl ? $plur : $sing; };

    /* Chiffres de l'ID, de droite à gauche */
    $s  = (string) abs( (int) $page_id );
    $d1 = (int) substr( $s, -1 );
    $d2 = strlen( $s ) >= 2 ? (int) substr( $s, -2, 1 ) : 0;
    $d3 = strlen( $s ) >= 3 ? (int) substr( $s, -3, 1 ) : 0;

    /* 1) Accroche (ponctuation incluse, jamais de « : ») */
    $hooks = array(
      'Pour faire simple, ',
      'Pour faire court, ',
      'Sans suspense, ',
      'Pour aller à l\'essentiel, ',
      'Disons-le d\'emblée, ',
      'Pour ne rien vous cacher, ',
      'Inutile de faire durer le suspense, ',
      'Avant d\'entrer dans le détail, ',
      'Disons-le sans détour, ',
      'Si vous cherchez une réponse rapide, ',
    );

    /* 2) Recommandation du n°1 (verbes accordés ; attributs invariables :
       coup de cœur, numéro un, première place…) */
    $mains = array(
      $p1 . ' ' . $v( 'est', 'sont' ) . ' notre coup de cœur ' . $parmi,
      $p1 . ' ' . $v( 's\'impose', 's\'imposent' ) . ' en tête de ce comparatif',
      $p1 . ' ' . $v( 'domine', 'dominent' ) . ' notre classement',
      $p1 . ' ' . $v( 'arrive', 'arrivent' ) . ' en première position de notre sélection',
      $p1 . ' ' . $v( 'décroche', 'décrochent' ) . ' la première place de notre classement',
      'notre préférence va à ' . $p1 . ', numéro un de ce comparatif',
      $p1 . ' ' . $v( 'obtient', 'obtiennent' ) . ' la meilleure note de ce comparatif',
      'nous recommandons ' . $p1 . ' en priorité',
      'notre choix se porte sur ' . $p1 . ', en tête du classement',
      'difficile de faire mieux que ' . $p1 . ' dans ce comparatif',
    );

    /* 3a) Second produit, version « moins cher » — uniquement des tournures
       RELATIVES (moins cher, plus abordable…), valables à tout niveau de prix */
    $budgets = array(
      'Si vous souhaitez dépenser moins, ' . $p2 . ' ' . $v( 'offre', 'offrent' ) . ' le meilleur rapport qualité-prix.',
      'Pour alléger la facture, ' . $p2 . ' ' . $v( 'constitue', 'constituent' ) . ' une excellente alternative.',
      'À budget plus serré, ' . $p2 . ' ' . $v( 'est', 'sont' ) . ' l\'option la plus abordable de notre sélection.',
      'Si le prix pèse dans la balance, ' . $p2 . ' ' . $v( 'propose', 'proposent' ) . ' l\'essentiel pour moins cher.',
      'Pour un budget plus contenu, ' . $p2 . ' ' . $v( 'représente', 'représentent' ) . ' le meilleur compromis.',
      'Si vous comptez dépenser moins, regardez du côté de ' . $p2 . '.',
      'En alternative plus abordable, ' . $p2 . ' ' . $v( 'mérite', 'méritent' ) . ' le détour.',
      'Pour dépenser moins sans sacrifier la qualité, ' . $p2 . ' ' . $v( 'est', 'sont' ) . ' un excellent choix.',
      'Côté tarif, ' . $p2 . ' ' . $v( 'permet', 'permettent' ) . ' de dépenser moins sans grande concession.',
      'Si votre priorité est le prix, ' . $p2 . ' ' . $v( 'est', 'sont' ) . ' l\'alternative la plus accessible du classement.',
    );

    /* 3b) Second produit, version « alternative » (toujours le rang 2 ici,
       donc « deuxième place » est factuel) */
    $alts = array(
      'Si vous hésitez encore, ' . $p2 . ' ' . $v( 'constitue', 'constituent' ) . ' une solide alternative.',
      'Juste derrière, ' . $p2 . ' ' . $v( 'mérite', 'méritent' ) . ' aussi votre attention.',
      $p2 . ' ' . $v( 'tient', 'tiennent' ) . ' la deuxième place de notre classement.',
      'En deuxième position, ' . $p2 . ' ' . $v( 'a', 'ont' ) . ' également convaincu notre équipe.',
      'Autre valeur sûre, ' . $p2 . ' ' . $v( 'arrive', 'arrivent' ) . ' juste derrière.',
      $p2 . ' ' . $v( 'complète', 'complètent' ) . ' notre duo de tête.',
      'Autre option sérieuse, ' . $p2 . ' ' . $v( 'occupe', 'occupent' ) . ' la deuxième place.',
      'Si notre numéro un ne vous convainc pas, ' . $p2 . ' ' . $v( 'est', 'sont' ) . ' une alternative crédible.',
      'En deuxième position, ' . $p2 . ' ne ' . $v( 'démérite', 'déméritent' ) . ' pas.',
      'Notre deuxième choix se porte sur ' . $p2 . '.',
    );

    $out = $hooks[ $d1 ] . $mains[ $d2 ] . '.';
    if ( $p2 !== '' ) {
      $out .= ' ' . ( $is_budget ? $budgets[ $d3 ] : $alts[ $d3 ] );
    }
    return '<p class="mt-lede-reco">' . $out . '</p>';
  }
}

if ( ! function_exists( 'mt_quick_picks' ) ) {
  function mt_quick_picks( $ids, $max = 5 ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( empty( $ids ) ) { return ''; }
    $tag  = 'mlt00-21';
    $items = array();
    foreach ( array_slice( $ids, 0, $max ) as $i => $pid ) {
      if ( get_post_status( $pid ) !== 'publish' ) { continue; }
      $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
      $brand  = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model  = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $forced !== '' ) { $name = $forced; }
      else { $name = $model !== '' ? trim( $brand . ' ' . $model ) : get_the_title( $pid ); }
      $asin = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
      $url  = '';
      if ( $asin !== '' ) {
        $url = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=' . $tag;
      } else {
        for ( $j = 1; $j <= 3; $j++ ) {
          $u = trim( (string) get_field( 'mltv5_lien_du_produit_' . $j, $pid ) );
          if ( $u !== '' && strpos( $u, 'http' ) === 0 ) { $url = $u; break; }
        }
      }
      $items[] = array( 'rank' => $i + 1, 'name' => $name, 'url' => $url );
    }
    if ( empty( $items ) ) { return ''; }
    $out = '<ol class="mt-picks">';
    foreach ( $items as $it ) {
      $out .= '<li>';
      if ( $it['url'] !== '' ) {
        $out .= '<a href="' . esc_url( $it['url'] ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html( $it['name'] ) . '</a>';
      } else {
        $out .= esc_html( $it['name'] );
      }
      $out .= ' <a class="mt-pick-r" href="#produit-n-' . (int) $it['rank'] . '">(avis)</a>';
      $out .= '</li>';
    }
    $out .= '</ol>';
    return $out;
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
      echo mt_intro_reco( $this_id, $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $lalalesmeilleur ?? '' );
  } ?></div>

  <?php if ( $post_type === 'comparatif' && ! empty( $top_avis_ids ) ) {
    echo mt_quick_picks( $top_avis_ids );
  } ?>

  <div class="mt-photo">
    <?php echo get_the_post_thumbnail($this_id, 'large', array('class'=>'mt-photo-img')); ?>
    <?php if ($post_type === 'comparatif') {
        echo '<img class="mt-badge" src="https://meilleurtest.fr/wp-content/uploads/2026/07/badge-mt3.png" alt="" style="position:absolute;top:0;left:0;max-width:130px;height:auto;">';
    } ?>
  </div>

</div>
