<?php
/* =====================================================================
   MEILLEURTEST — « Questions fréquentes » (FAQ)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (faq.css).

   Source des données (ACF) :
   - REPEATER mltv5_faq_comparatif   (1 ligne = 1 Q/R)
       . mltv5_faq_comparatif_question  (question)
       . mltv5_faq_comparatif_reponse   (réponse — WYSIWYG)
   Repli sur le post lié `mltv5_cache_id_faq` (lecture robuste).

   + jusqu'à 5 Q/R AUTOMATIQUES en tête, générées depuis les données dynamiques
   du guide (classement top + points forts/faibles + libellé de note, marques,
   prix / avis clients / confiance, critères du guide d'achat, méthodologie +
   date de mise à jour). Réponses en 2 paragraphes avec renvois d'ancres internes
   (#produit-n-1, #partie-tableau-comparatif, #partie-guide-achat). Conçues pour
   fonctionner sur TOUT type de produit (smartphones, chaussures, huile d'olive,
   couches…) : accords dérivés de `lalalesmeilleur`, tournures neutres, sections
   muettes si la donnée manque.

   STANDARDS WEB :
   - Accordéon natif <details>/<summary> -> accessible, sans JS.
   - Données structurées schema.org **FAQPage** en JSON-LD (recommandé Google).
     Réponses assainies (wp_kses) + encodage durci (tags/ampersands échappés).
   Section -> `.contenu-principal` (jauge de lecture) + ancre `partie-faq`.
   ===================================================================== */

if ( ! function_exists( 'mt_guide_cache_id' ) ) {
  /* Résout l'ID du post lié mis en cache : essaie `mltv5_cache_id_{suffix}`
     puis `mltv5_cached_id_{suffix}` (ancien nom) ; accepte un ID ou un objet post. */
  function mt_guide_cache_id( $page_id, $suffix ) {
    $keys = array( 'mltv5_cached_id_' . $suffix, 'mltv5_cache_id_' . $suffix );
    foreach ( $keys as $f ) {                            /* 1) ACF */
      $v = function_exists( 'get_field' ) ? get_field( $f, $page_id ) : null;
      if ( is_array( $v ) ) { $v = reset( $v ); }
      if ( is_object( $v ) ) { return (int) $v->ID; }
      if ( $v ) { return (int) $v; }
    }
    foreach ( $keys as $f ) {                            /* 2) meta brut (hors ACF) */
      $v = function_exists( 'get_post_meta' ) ? get_post_meta( $page_id, $f, true ) : '';
      if ( is_array( $v ) ) { $v = reset( $v ); }
      if ( is_object( $v ) ) { return (int) $v->ID; }
      if ( $v ) { return (int) $v; }
    }
    return 0;
  }
}
if ( ! function_exists( 'mt_guide_rich' ) ) {
  function mt_guide_rich( $html ) {
    $html = (string) $html;
    if ( trim( $html ) === '' ) { return ''; }
    if ( ! preg_match( '/<(p|ul|ol|h[1-6]|blockquote|div|table|figure)\b/i', $html ) ) {
      $html = wpautop( $html );
    }
    return $html;
  }
}
if ( ! function_exists( 'mt5_num' ) ) {
  function mt5_num( $v ) {
    $v = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $v );
    $v = str_replace( ',', '.', $v );
    return is_numeric( $v ) ? (float) $v : 0.0;
  }
}
if ( ! function_exists( 'mt_faq_join_et' ) ) {
  function mt_faq_join_et( $items ) {
    $items = array_values( array_filter( $items, function ( $v ) { return $v !== ''; } ) );
    $n = count( $items );
    if ( $n === 0 ) { return ''; }
    if ( $n === 1 ) { return $items[0]; }
    $last = array_pop( $items );
    return implode( ', ', $items ) . ' et ' . $last;
  }
}
if ( ! function_exists( 'mt5_points' ) ) {
  /* Lignes d'un repeater de points -> tableau de textes non vides. */
  function mt5_points( $field, $pid, $subkey ) {
    $rows = get_field( $field, $pid );
    $out  = array();
    if ( is_array( $rows ) ) {
      foreach ( $rows as $r ) {
        $p = isset( $r[ $subkey ] ) ? trim( (string) $r[ $subkey ] ) : '';
        if ( $p !== '' ) { $out[] = $p; }
      }
    }
    return $out;
  }
}
if ( ! function_exists( 'mt_faq_read' ) ) {
  function mt_faq_read( $pid ) {
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_faq_comparatif', $pid ) : null;
    return is_array( $rows ) ? $rows : array();
  }
}

$page_id = get_the_ID();

/* Balises autorisées par Google dans le texte de réponse FAQPage. */
$faq_schema_tags = array(
  'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'h5' => array(), 'h6' => array(),
  'br' => array(), 'ol' => array(), 'ul' => array(), 'li' => array(),
  'a'  => array( 'href' => array() ), 'p' => array(), 'div' => array(),
  'b'  => array(), 'strong' => array(), 'i' => array(), 'em' => array(),
);

/* =====================================================================
   1) Q/R AUTOMATIQUES (depuis les données dynamiques du guide)
   ===================================================================== */
$page_tv = function_exists( 'get_all_template_variables' ) ? get_all_template_variables( $page_id ) : array();
$tv = function ( $k ) use ( $page_tv, $page_id ) {
  if ( isset( $page_tv[ $k ] ) && trim( (string) $page_tv[ $k ] ) !== '' ) { return trim( (string) $page_tv[ $k ] ); }
  $v = function_exists( 'get_field' ) ? get_field( $k, $page_id ) : '';
  return trim( (string) $v );
};

$type_sing = $tv( 'type_de_produit_au_singulier' );
$type_plur = $tv( 'type_de_produit_au_pluriel' );
$llm       = $tv( 'lalalesmeilleur' );

/* Liste ordonnée des produits du guide (même sourcing que resume/comparatif). */
$ids = ( isset( $page_tv['top_avis_ids'] ) && is_array( $page_tv['top_avis_ids'] ) ) ? $page_tv['top_avis_ids'] : array();
if ( empty( $ids ) ) {
  $rel = get_field( 'mltv5_best_products', $page_id );
  if ( is_array( $rel ) ) { foreach ( $rel as $x ) { $ids[] = is_object( $x ) ? $x->ID : (int) $x; } }
}
$ids = array_slice( array_values( array_filter( array_map( 'intval', $ids ) ) ), 0, 5 );

$prods = array();
if ( ! empty( $ids ) ) {
  global $post;
  $saved = $post;
  foreach ( $ids as $pid ) {
    $p = get_post( $pid );
    if ( ! $p ) { continue; }
    $post = $p;
    setup_postdata( $post );
    $brand = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
    $model = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
    $nm    = $model !== '' ? $model : get_the_title( $pid );
    $disp  = trim( $brand . ' ' . $nm );
    if ( $disp === '' ) { $disp = get_the_title( $pid ); }
    $score = function_exists( 'get_acf_score_divided_by_10' )
      ? (float) get_acf_score_divided_by_10()
      : ( mt5_num( get_field( 'mltv5_score_recent', $pid ) ) / 10 );
    $prods[] = array(
      'name'   => $disp,
      'brand'  => $brand,
      'score'  => $score,
      'price'  => mt5_num( get_field( 'mltv5_prix_indicatif', $pid ) ),
      'crate'  => mt5_num( get_field( 'mltv5_score_avis_clients', $pid ) ),
      'ccount' => (int) preg_replace( '/[^0-9]/', '', (string) get_field( 'mltv5_nombre_avis_clients', $pid ) ),
      'label'  => function_exists( 'get_acf_score_label' ) ? trim( (string) get_acf_score_label() ) : '',
      'pros'   => array_slice( mt5_points( 'mltv5_points_positifs_produit', $pid, 'mltv5_point_positif' ), 0, 3 ),
      'cons'   => array_slice( mt5_points( 'mltv5_points_negatifs_produit', $pid, 'mltv5_point_negatif' ), 0, 1 ),
    );
  }
  $post = $saved;
  wp_reset_postdata();
}

/* Titres des critères du guide d'achat (page courante -> repli post lié) :
   alimentent la question « Comment bien choisir ? » et les renvois d'ancre. */
$crit_titles = array();
$crit_rows   = function_exists( 'get_field' ) ? get_field( 'mltv5_criteres_de_choix', $page_id ) : null;
if ( ! is_array( $crit_rows ) || empty( $crit_rows ) ) {
  $crit_src = mt_guide_cache_id( $page_id, 'criteres' );
  if ( $crit_src && $crit_src !== $page_id ) {
    $crit_rows = function_exists( 'get_field' ) ? get_field( 'mltv5_criteres_de_choix', $crit_src ) : null;
  }
}
if ( is_array( $crit_rows ) ) {
  foreach ( $crit_rows as $r ) {
    $t = ( is_array( $r ) && isset( $r['mltv5_critere_de_choix'] ) ) ? trim( (string) $r['mltv5_critere_de_choix'] ) : '';
    if ( $t !== '' ) { $crit_titles[] = $t; }
  }
}

$autos = array();
if ( ! empty( $prods ) ) {
  /* Accords dérivés de lalalesmeilleur ("le meilleur" / "la meilleure" /
     "les meilleurs" / "les meilleures"). */
  $low    = strtolower( $llm );
  $plural = ( strpos( $low, 'les ' ) === 0 );
  $fem    = $plural ? ( strpos( $low, 'meilleures' ) !== false ) : ( strpos( $low, 'la ' ) === 0 );
  $euro   = function ( $v ) { return number_format( (float) $v, 0, ',', "\xc2\xa0" ) . "\xc2\xa0&euro;"; };
  $note   = function ( $v ) { return number_format( (float) $v, 1, ',', '' ); };
  /* Élision « de » / « d' » devant voyelle ou h (ex. d'huiles d'olive). */
  $de = function ( $w ) {
    $w = ltrim( (string) $w );
    if ( $w === '' ) { return 'de '; }
    $first = function_exists( 'mb_substr' ) ? mb_strtolower( mb_substr( $w, 0, 1, 'UTF-8' ), 'UTF-8' ) : strtolower( $w[0] );
    return ( mb_strpos( 'aàâäeéèêëiîïoôöuùûühyœæ', $first ) !== false ) ? 'd&rsquo;' : 'de ';
  };
  /* Produit « podium » : nom en gras + note entre parenthèses si dispo. */
  $pod = function ( $p ) use ( $note ) {
    $s = '<strong>' . esc_html( $p['name'] ) . '</strong>';
    if ( $p['score'] > 0 ) { $s .= ' (' . $note( $p['score'] ) . '/10)'; }
    return $s;
  };
  $nbp      = count( $prods );
  $p1       = $prods[0];
  $has_crit = ! empty( $crit_titles );

  /* --- Q1 : le meilleur produit --------------------------------------- */
  $best_noun = $plural ? ( $type_plur !== '' ? $type_plur : $type_sing ) : ( $type_sing !== '' ? $type_sing : $type_plur );
  if ( $llm !== '' ) {
    $interro = $plural ? ( $fem ? 'Quelles sont' : 'Quels sont' ) : ( $fem ? 'Quelle est' : 'Quel est' );
    $q1 = $interro . ' ' . esc_html( preg_replace( '/\s+/', ' ', trim( $llm . ' ' . $best_noun ) ) ) . '&nbsp;?';
  } else {
    $noun = $best_noun !== '' ? $best_noun : 'produit';
    $q1 = 'Quel est le meilleur ' . esc_html( $noun ) . '&nbsp;?';
  }
  $a1 = '<p>Au terme de notre comparatif, c&rsquo;est <strong>' . esc_html( $p1['name'] ) . '</strong> qui se classe en t&ecirc;te de notre s&eacute;lection';
  if ( $p1['score'] > 0 ) {
    $a1 .= ', avec une note de <strong>' . $note( $p1['score'] ) . '/10</strong>';
    if ( $p1['label'] !== '' ) { $a1 .= ' (&laquo;&nbsp;' . esc_html( $p1['label'] ) . '&nbsp;&raquo;)'; }
  }
  $a1 .= '.';
  if ( ! empty( $p1['pros'] ) ) {
    $a1 .= ' Ses principaux atouts&nbsp;: ' . esc_html( mt_faq_join_et( $p1['pros'] ) ) . '.';
  }
  if ( ! empty( $p1['cons'] ) ) {
    $a1 .= ' Son principal b&eacute;mol&nbsp;: ' . esc_html( $p1['cons'][0] ) . '.';
  }
  $a1 .= '</p><p>';
  if ( $nbp >= 3 ) {
    $a1 .= 'Sur le podium, on retrouve &eacute;galement ' . $pod( $prods[1] ) . ' et ' . $pod( $prods[2] ) . '. ';
  } elseif ( $nbp === 2 ) {
    $a1 .= 'Juste derri&egrave;re arrive ' . $pod( $prods[1] ) . '. ';
  }
  $a1 .= 'Le &laquo;&nbsp;meilleur&nbsp;&raquo; choix reste toutefois celui qui correspond &agrave; votre usage et &agrave; votre budget&nbsp;: un mod&egrave;le un peu moins bien class&eacute; peut tr&egrave;s bien &ecirc;tre plus adapt&eacute; &agrave; votre situation. Pour trancher, parcourez nos <a href="#produit-n-1">avis d&eacute;taill&eacute;s</a> et le <a href="#partie-tableau-comparatif">tableau comparatif</a>, un peu plus haut sur cette page.</p>';
  $autos[] = array( 'q' => $q1, 'a' => $a1 );

  /* --- Q « marques » : marques distinctes du classement (ordre de rang,
         annotées : n°1 du classement / nb de produits classés) ----------- */
  $brands_ord = array();   // marque -> ['rank' => meilleur rang, 'n' => nb produits]
  foreach ( $prods as $bi => $p ) {
    $bn = trim( (string) $p['brand'] );
    if ( $bn === '' ) { continue; }
    if ( ! isset( $brands_ord[ $bn ] ) ) { $brands_ord[ $bn ] = array( 'rank' => $bi + 1, 'n' => 0 ); }
    $brands_ord[ $bn ]['n']++;
  }
  if ( count( $brands_ord ) >= 2 ) {
    $bstr = array();
    foreach ( array_slice( $brands_ord, 0, 4, true ) as $bn => $binf ) {
      $s = '<strong>' . esc_html( $bn ) . '</strong>';
      if ( $binf['rank'] === 1 )  { $s .= ' (qui signe le num&eacute;ro&nbsp;1 de notre classement)'; }
      elseif ( $binf['n'] >= 2 )  { $s .= ' (' . (int) $binf['n'] . ' produits class&eacute;s)'; }
      $bstr[] = $s;
    }
    $qm = 'Quelles sont les meilleures marques' . ( $type_plur !== '' ? ' ' . $de( $type_plur ) . esc_html( $type_plur ) : '' ) . '&nbsp;?';
    $am = '<p>Les marques qui dominent notre s&eacute;lection sont ' . mt_faq_join_et( $bstr )
        . '. Ce sont elles qui proposent aujourd&rsquo;hui les mod&egrave;les les plus convaincants au regard de nos crit&egrave;res et des retours d&rsquo;utilisateurs.</p>'
        . '<p>Une marque install&eacute;e apporte de vraies garanties&nbsp;: s&eacute;rieux du service client, fiabilit&eacute; &eacute;prouv&eacute;e dans le temps, pi&egrave;ces et accessoires plus faciles &agrave; trouver. Mais elle ne fait pas tout&nbsp;: au sein d&rsquo;un m&ecirc;me catalogue, tous les mod&egrave;les ne se valent pas. Notre conseil&nbsp;: comparez les produits plut&ocirc;t que les logos, en partant du classement ci-dessus.</p>';
    $autos[] = array( 'q' => $qm, 'a' => $am );
  }

  /* --- Slot 2 : budget (prix) -> avis clients -> confiance ------------- */
  $priced = array_values( array_filter( $prods, function ( $p ) { return $p['price'] > 0; } ) );
  $rated  = array_values( array_filter( $prods, function ( $p ) { return $p['crate'] > 0; } ) );

  if ( count( $priced ) >= 2 ) {
    $prices = array_map( function ( $p ) { return $p['price']; }, $priced );
    $min = min( $prices ); $max = max( $prices );
    $cheap = $priced[0];
    foreach ( $priced as $p ) { if ( $p['price'] < $cheap['price'] ) { $cheap = $p; } }
    $indef = $plural
      ? ( 'des ' . ( $type_plur !== '' ? $type_plur : $type_sing ) )
      : ( ( $fem ? 'une' : 'un' ) . ' ' . ( $type_sing !== '' ? $type_sing : $type_plur ) );
    $noun_pl = $type_plur !== '' ? $type_plur : ( $type_sing !== '' ? $type_sing : 'produits' );
    $q2 = 'Quel budget pr&eacute;voir pour ' . esc_html( trim( $indef ) ) . '&nbsp;?';
    $a2 = '<p>Les ' . esc_html( $noun_pl ) . ' de notre s&eacute;lection s&rsquo;&eacute;chelonnent d&rsquo;environ ' . $euro( $min ) . ' &agrave; ' . $euro( $max ) . '.';
    if ( $p1['price'] > 0 && $cheap['name'] !== $p1['name'] ) {
      $a2 .= ' Notre num&eacute;ro&nbsp;1, <strong>' . esc_html( $p1['name'] ) . '</strong>, se situe autour de ' . $euro( $p1['price'] ) . '.';
    }
    $a2 .= '</p><p>';
    if ( $cheap['name'] === $p1['name'] ) {
      $a2 .= 'Bonne nouvelle&nbsp;: l&rsquo;option la plus accessible, <strong>' . esc_html( $cheap['name'] ) . '</strong> (environ ' . $euro( $cheap['price'] ) . '), est aussi la mieux class&eacute;e de notre comparatif.';
    } else {
      $a2 .= 'L&rsquo;option la plus accessible est <strong>' . esc_html( $cheap['name'] ) . '</strong> (environ ' . $euro( $cheap['price'] ) . ')';
      $a2 .= ( $cheap['score'] > 0 )
        ? ', qui obtient tout de m&ecirc;me la note de ' . $note( $cheap['score'] ) . '/10&nbsp;: il n&rsquo;est donc pas indispensable de viser le haut du panier pour &ecirc;tre bien &eacute;quip&eacute;.'
        : '.';
    }
    $a2 .= ' Le bon budget d&eacute;pend surtout de votre usage&nbsp;: mieux vaut payer pour les crit&egrave;res qui comptent vraiment pour vous'
        . ( $has_crit ? ' (nous les passons en revue dans le <a href="#partie-guide-achat">guide d&rsquo;achat</a>)' : '' )
        . ' que pour des options qui ne serviront jamais.</p>';
    $autos[] = array( 'q' => $q2, 'a' => $a2 );
  } elseif ( count( $rated ) >= 2 ) {
    $best_r = $rated[0];
    foreach ( $rated as $p ) {
      if ( $p['crate'] > $best_r['crate'] || ( $p['crate'] === $best_r['crate'] && $p['ccount'] > $best_r['ccount'] ) ) { $best_r = $p; }
    }
    $tot_avis = 0;
    foreach ( $prods as $p ) { $tot_avis += (int) $p['ccount']; }
    $q2 = 'Quel produit a les meilleurs avis clients&nbsp;?';
    $a2 = '<p>Avec une note de <strong>' . $note( $best_r['crate'] ) . '/5</strong>';
    if ( $best_r['ccount'] > 0 ) { $a2 .= ' fond&eacute;e sur ' . number_format( $best_r['ccount'], 0, ',', "\xc2\xa0" ) . ' avis'; }
    $a2 .= ', c&rsquo;est <strong>' . esc_html( $best_r['name'] ) . '</strong> qui recueille les meilleurs retours d&rsquo;utilisateurs.';
    if ( $best_r['name'] === $p1['name'] ) {
      $a2 .= ' Les acheteurs rejoignent donc notre verdict, puisqu&rsquo;il s&rsquo;agit aussi du num&eacute;ro&nbsp;1 de notre classement.';
    } else {
      $a2 .= ' Notre num&eacute;ro&nbsp;1 reste toutefois <strong>' . esc_html( $p1['name'] ) . '</strong>&nbsp;: les avis clients sont un signal pr&eacute;cieux, mais ils refl&egrave;tent parfois davantage le rapport qualit&eacute;-prix que la performance pure.';
    }
    $a2 .= '</p>';
    if ( $tot_avis > 0 ) {
      $a2 .= '<p>Au total, les produits de notre s&eacute;lection cumulent ' . number_format( $tot_avis, 0, ',', "\xc2\xa0" ) . ' avis clients&nbsp;: un volume qui permet de d&eacute;gager des tendances fiables sur la dur&eacute;e (fiabilit&eacute;, service apr&egrave;s-vente, d&eacute;fauts r&eacute;currents), et que nous int&eacute;grons &agrave; notre analyse.</p>';
    }
    $autos[] = array( 'q' => $q2, 'a' => $a2 );
  } else {
    $autos[] = array(
      'q' => 'Pourquoi faire confiance &agrave; ce comparatif&nbsp;?',
      'a' => '<p>Notre r&eacute;daction travaille en toute ind&eacute;pendance&nbsp;: aucune marque ne peut acheter sa place dans un classement, et nous n&rsquo;acceptons ni publicit&eacute; ni cadeau des fabricants. Le classement repose sur des crit&egrave;res objectifs (caract&eacute;ristiques, rapport qualit&eacute;-prix, fiabilit&eacute;) et sur l&rsquo;analyse des retours d&rsquo;utilisateurs.</p>'
         . '<p>En toute transparence&nbsp;: nous pouvons toucher une commission si vous achetez via nos liens. Elle ne change ni le prix que vous payez, ni l&rsquo;ordre de notre s&eacute;lection, et c&rsquo;est ce qui nous permet de rester libres de nos verdicts. Nos comparatifs sont par ailleurs mis &agrave; jour r&eacute;guli&egrave;rement pour suivre les nouveaut&eacute;s et les &eacute;volutions du march&eacute;.</p>',
    );
  }

  /* --- Q « comment choisir » : titres réels des critères du guide ------- */
  if ( $has_crit ) {
    $poss   = $plural ? 'vos' : 'votre';
    $noun_q = $best_noun !== '' ? $best_noun : 'produit';
    $list   = array_map( 'esc_html', array_slice( $crit_titles, 0, 6 ) );
    $qc = 'Comment bien choisir ' . esc_html( $poss . ' ' . $noun_q ) . '&nbsp;?';
    $ac = '<p>Avant de comparer les mod&egrave;les, prenez le temps de cerner vos besoins. Les crit&egrave;res qui font vraiment la diff&eacute;rence sont'
        . ( count( $crit_titles ) > 6 ? ' notamment' : '' ) . '&nbsp;: ' . mt_faq_join_et( $list ) . '.</p>'
        . '<p>Chacun de ces points est d&eacute;taill&eacute; dans notre <a href="#partie-guide-achat">guide d&rsquo;achat</a>, un peu plus haut sur cette page, avec nos conseils pour arbitrer selon votre usage et votre budget. Et si vous ne souhaitez pas entrer dans le d&eacute;tail&nbsp;: notre classement tient d&eacute;j&agrave; compte de l&rsquo;ensemble de ces crit&egrave;res.</p>';
    $autos[] = array( 'q' => $qc, 'a' => $ac );
  }

  /* --- Slot 3 : méthodologie (stats si dispo, sinon générique) --------- */
  $bits = array();
  $s_prod = $tv( 'produits_analyses' );
  $s_avis = $tv( 'avis_etudies' );
  $s_src  = $tv( 'sources_consultees' );
  $s_heu  = $tv( 'heures_investies' );
  if ( $s_prod !== '' ) { $bits[] = 'compar&eacute; ' . esc_html( $s_prod ) . ' ' . esc_html( $type_plur !== '' ? $type_plur : 'produits' ); }
  if ( $s_avis !== '' ) { $bits[] = '&eacute;tudi&eacute; ' . esc_html( $s_avis ) . ' avis clients'; }
  if ( $s_src  !== '' ) { $bits[] = 'consult&eacute; ' . esc_html( $s_src ) . ' sources'; }
  if ( $s_heu  !== '' ) { $bits[] = 'pass&eacute; ' . esc_html( $s_heu ) . ' heures &agrave; les analyser'; }
  if ( ! empty( $bits ) ) {
    $a3 = '<p>Pour &eacute;tablir ce classement, notre &eacute;quipe a ' . mt_faq_join_et( $bits ) . '.</p>';
  } else {
    $a3 = '<p>Ce classement r&eacute;sulte d&rsquo;un travail de recherche et de comparaison men&eacute; par notre r&eacute;daction&nbsp;: analyse des caract&eacute;ristiques et des performances, confrontation des sources sp&eacute;cialis&eacute;es et synth&egrave;se des avis d&rsquo;utilisateurs.</p>';
  }
  $upd = get_the_modified_date( 'F Y', $page_id );
  $a3 .= '<p>Concr&egrave;tement, chaque produit est &eacute;valu&eacute; sur ses caract&eacute;ristiques, son rapport qualit&eacute;-prix et la synth&egrave;se des retours d&rsquo;utilisateurs, puis re&ccedil;oit une note sur 10 qui d&eacute;termine sa place dans la s&eacute;lection. Aucun placement n&rsquo;est sponsoris&eacute;&nbsp;: les marques n&rsquo;interviennent jamais dans nos choix.'
      . ( $upd ? ' Derni&egrave;re mise &agrave; jour de ce comparatif&nbsp;: ' . esc_html( $upd ) . '.' : '' ) . '</p>';
  $autos[] = array( 'q' => 'Comment avons-nous &eacute;tabli ce classement&nbsp;?', 'a' => $a3 );
}

/* =====================================================================
   2) Q/R MANUELLES (repeater ACF) — lecture robuste
   ===================================================================== */
$src_id = $page_id;
$rows   = mt_faq_read( $src_id );
if ( empty( $rows ) ) {
  $cached = mt_guide_cache_id( $page_id, 'faq' );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_faq_read( $cached );
    if ( ! empty( $alt ) ) { $src_id = $cached; $rows = $alt; }
  }
}

$manual = array();
foreach ( $rows as $r ) {
  $q = trim( wp_strip_all_tags( (string) ( isset( $r['mltv5_faq_comparatif_question'] ) ? $r['mltv5_faq_comparatif_question'] : '' ) ) );
  $a = (string) ( isset( $r['mltv5_faq_comparatif_reponse'] ) ? $r['mltv5_faq_comparatif_reponse'] : '' );
  if ( $q === '' && trim( $a ) === '' ) { continue; }
  $manual[] = array( 'q' => esc_html( $q ), 'a' => mt_guide_rich( $a ) );
}

/* =====================================================================
   3) Fusion (autos en tête) + normalisation (a + a_schema)
   ===================================================================== */
$faqs = array();
foreach ( array_merge( $autos, $manual ) as $f ) {
  $faqs[] = array(
    'q'        => $f['q'],
    'a'        => $f['a'],
    'a_schema' => trim( wp_kses( $f['a'], $faq_schema_tags ) ),
  );
}

/* Rien à afficher -> diagnostic admin (builder), invisible pour les visiteurs. */
if ( empty( $faqs ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_faq_comparatif', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-faq — diagnostic (admin only)</strong> : aucune question (ni auto ni manuelle).<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; produits trouv&eacute;s = ' . count( $prods ) . '<br>'
       . 'cache_id r&eacute;solu = ' . mt_guide_cache_id( $page_id, 'faq' ) . ' (brut mltv5_cached_id_faq : ' . gettype( get_field( 'mltv5_cached_id_faq', $page_id ) ) . ')<br>'
       . 'repeater mltv5_faq_comparatif = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' )
       . '</div>';
  }
  return;
}

/* JSON-LD FAQPage : uniquement les Q/R complètes (acceptedAnswer obligatoire). */
$entities = array();
foreach ( $faqs as $f ) {
  if ( $f['q'] === '' || $f['a_schema'] === '' ) { continue; }
  $entities[] = array(
    '@type'          => 'Question',
    'name'           => html_entity_decode( wp_strip_all_tags( $f['q'] ), ENT_QUOTES, 'UTF-8' ),
    'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a_schema'] ),
  );
}
$jsonld = '';
if ( ! empty( $entities ) ) {
  $schema = array(
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => $entities,
  );
  /* JSON_HEX_TAG/AMP : échappe <, >, & -> embarquable sans danger dans <script>. */
  $jsonld = wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
}
?>
<section class="mt-faq contenu-principal" id="partie-faq" aria-labelledby="mt-faq-title">
  <h2 class="mt-faq-h2" id="mt-faq-title">Questions fr&eacute;quentes</h2>
  <p class="mt-faq-intro">Voici les questions les plus fr&eacute;quemment pos&eacute;es par nos lecteurs et la communaut&eacute;.</p>

  <div class="mt-faq-list">
    <?php foreach ( $faqs as $i => $f ) : ?>
      <?php if ( $f['a'] !== '' ) : ?>
      <details class="mt-faq-item"<?php echo $i === 0 ? ' open' : ''; ?>>
        <summary class="mt-faq-q">
          <span class="mt-faq-qh"><?php echo $f['q']; ?></span>
          <span class="mt-faq-icon" aria-hidden="true"></span>
        </summary>
        <div class="mt-faq-a"><?php echo $f['a']; ?></div>
      </details>
      <?php else : ?>
      <div class="mt-faq-item mt-faq-static">
        <span class="mt-faq-qh"><?php echo $f['q']; ?></span>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ( $jsonld !== '' ) : ?>
  <script type="application/ld+json"><?php echo $jsonld; ?></script>
  <?php endif; ?>
</section>
