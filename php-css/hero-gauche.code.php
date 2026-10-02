<?php
$MT_SHOW_QUICK_PICKS = false;
$MT_SHOW_BOLD_INTRO  = false;
$MT_SHOW_INTRO_RECO  = true;
$MT_VERDICT_SOUS_H1  = true;   // verdict (top 3 avec notes /10, N analysés, méthode, date) juste sous le H1 ; false = ancienne phrase dans le chapô
$MT_VERIFIE_PAR      = 'Samuel Petit'; // ligne auteur « Vérifié par …, responsable éditorial » (validé par Samuel) ; '' = pas de ligne
$MT_H1_EGAL_TITLE    = true;   // sans titre forcé, le H1 reprend le title automatique (validé par Samuel)

$this_id   = get_the_ID();
extract(get_all_template_variables($this_id));
$post_type = get_post_type($this_id);
$total_avis = !empty($top_avis_ids) ? count($top_avis_ids) : 0;
$mod = date_i18n('j F Y', get_the_modified_time('U'));

if ( ! function_exists( 'mt_intro_reco' ) ) {
  function mt_intro_reco( $page_id, $ids, $type_plur, $type_sing = '', $llm = '' ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    if ( count( $ids ) < 2 ) { return ''; }

    $year = (int) date( 'Y' );

    $prods = array();
    foreach ( $ids as $i => $pid ) {
      $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
      $brand  = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model  = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $forced !== '' ) { $name = $forced; }
      elseif ( $model !== '' ) { $name = trim( $brand . ' ' . $model ); }
      else { $name = (string) get_the_title( $pid ); }
      $raw  = get_field( 'mltv5_prix_indicatif', $pid );
      $c    = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $raw );
      $c    = str_replace( ',', '.', $c );
      $asin = trim( (string) get_field( 'mltv5_asin_amazon', $pid ) );
      $url  = '';
      if ( $asin !== '' ) {
        $url = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=mlt00-21';
      } else {
        for ( $li = 1; $li <= 3; $li++ ) {
          $lu = trim( (string) get_field( 'mltv5_lien_du_produit_' . $li, $pid ) );
          if ( $lu !== '' && strpos( $lu, 'http' ) === 0 ) { $url = $lu; break; }
        }
      }
      $prods[] = array(
        'rank'   => $i + 1,
        'name'   => $name,
        'price'  => is_numeric( $c ) ? (float) $c : 0.0,
        'asin'   => $asin,
        'url'    => $url,
        'no_art' => ( $forced !== '' && $brand !== '' && ( mb_stripos( $forced, $brand ) === 0 || mb_stripos( $forced, $model . ' (' . $brand ) === 0 ) ),
      );
    }
    if ( $prods[0]['name'] === '' ) { return ''; }

    $n_price = 0; $budget = null; $has_asin = ( $prods[0]['asin'] !== '' );
    foreach ( $prods as $p ) {
      if ( $p['price'] > 0 ) {
        $n_price++;
        if ( $p['rank'] !== 1 && ( $budget === null || $p['price'] < $budget['price'] ) ) { $budget = $p; }
      }
    }
    $is_budget = ( $n_price >= 2 && $budget !== null && $budget['name'] !== ''
      && $prods[0]['price'] >= 20 && $budget['price'] < $prods[0]['price'] );
    $second = $is_budget ? $budget : $prods[1];
    if ( $second['name'] === '' ) { $second = null; }

    $type  = trim( (string) $type_plur );
    $type_lc = $type !== '' ? esc_html( mb_strtolower( $type, 'UTF-8' ) ) : '';
    $ts      = trim( (string) $type_sing );
    $type_s  = $ts !== '' ? esc_html( mb_strtolower( $ts, 'UTF-8' ) ) : $type_lc;

    /* Accord genre+nombre depuis $llm ("le meilleur"/"la meilleure"/"les meilleurs"/"les meilleures") */
    $llm_t = mb_strtolower( trim( (string) $llm ), 'UTF-8' );
    $pl    = ( mb_strpos( $llm_t, 'les ' ) === 0 );
    $fem   = ( mb_strpos( $llm_t, 'meilleure' ) !== false );
    /* $llm_s = forme singulière (un produit = une entité, toujours singulier) */
    $llm_s = $fem ? 'la meilleure' : 'le meilleur';

    /* Article défini devant le nom de produit — toujours singulier */
    $mt_art = function ( $name ) use ( $fem ) {
      $fc = mb_strtolower( mb_substr( $name, 0, 1, 'UTF-8' ), 'UTF-8' );
      if ( in_array( $fc, array( 'a', 'à', 'â', 'e', 'é', 'è', 'ê', 'i', 'î', 'o', 'ô', 'u', 'û' ), true ) ) { return 'l\''; }
      return $fem ? 'la ' : 'le ';
    };

    $p1_url = $prods[0]['url'] !== '' ? $prods[0]['url'] : '#produit-n-1';
    $p1 = ( $prods[0]['no_art'] ? '' : $mt_art( $prods[0]['name'] ) ) . '<a href="' . esc_url( $p1_url ) . '">' . esc_html( $prods[0]['name'] ) . '</a>';
    $p2 = '';
    if ( $second ) {
      $p2_url = $second['url'] !== '' ? $second['url'] : '#produit-n-' . (int) $second['rank'];
      $p2 = ( $second['no_art'] ? '' : $mt_art( $second['name'] ) ) . '<a href="' . esc_url( $p2_url ) . '">' . esc_html( $second['name'] ) . '</a>';
    }

    /* Terminaison conditionnelle : prix+ASIN → "à acheter", prix seul → "sur le marché", sinon "du moment"/"à choisir" */
    if ( $n_price >= 1 && $has_asin ) {
      $fins = array( 'que vous pouvez acheter en ' . $year, 'à acheter en ' . $year );
    } elseif ( $n_price >= 1 ) {
      $fins = array( 'sur le marché en ' . $year, 'disponible en ' . $year );
    } else {
      $fins = array( 'du moment', 'en ' . $year );
    }

    /* "nos/les [type] préférés/préférées" — accord complet */
    $pref = $fem ? ( $pl ? 'préférées' : 'préférée' ) : ( $pl ? 'préférés' : 'préféré' );

    $s  = (string) abs( (int) $page_id );
    $d1 = (int) substr( $s, -1 );
    $d2 = strlen( $s ) >= 2 ? (int) substr( $s, -2, 1 ) : 0;
    $d3 = strlen( $s ) >= 3 ? (int) substr( $s, -3, 1 ) : 0;

    $fin = $fins[ $d1 % count( $fins ) ];
    $fin_court = $n_price >= 1 ? 'en ' . $year : 'du moment';

    /* P0 — « notre [type_sing] préféré(e) en 2026 est… » */
    $m0 = $type_s !== '' ? ( $pl
      ? 'nos ' . $type_lc . ' ' . $pref . ' ' . $fin_court . ' sont ' . $p1
      : 'notre ' . $type_s . ' ' . $pref . ' ' . $fin_court . ' est ' . $p1
    ) : '';
    /* Accord singulier du préféré pour les patterns produit */
    $pref_s = $fem ? 'préférée' : 'préféré';

    /* P1 — « est la meilleure [type_sing] [fin] » */
    $m1 = $p1 . ' est ' . $llm_s . ( $type_s !== '' ? ' ' . $type_s : '' ) . ' ' . $fin;
    /* P2 — « est notre [type_sing] préféré(e) » */
    $m2 = $p1 . ' est notre ' . ( $type_s !== '' ? $type_s . ' ' : '' ) . $pref_s;
    /* P3 — « est notre coup de cœur parmi les [type] » */
    $m3 = $p1 . ' est notre coup de cœur parmi ' . ( $type_lc !== '' ? 'les ' . $type_lc : 'ce comparatif' );
    /* P4 — « répond à tous nos critères de sélection » */
    $m4 = $p1 . ' répond à tous nos critères de sélection';
    /* P5 — « nous avons trouvé que… est la meilleure [type_sing] [fin] » */
    $m5 = 'nous avons trouvé que ' . $p1 . ' est ' . $llm_s . ( $type_s !== '' ? ' ' . $type_s : '' ) . ' ' . $fin;
    /* P6 — « après avoir analysé N [type]… la meilleure [fin] » */
    $nb = count( $ids );
    $m6 = 'après avoir analysé ' . $nb . ( $type_lc !== '' ? ' ' . $type_lc : ' produits' ) . ', ' . $p1 . ' est ' . $llm_s . ' ' . $fin;
    /* P7 — « s'impose en tête de notre sélection » */
    $m7 = $p1 . ' s\'impose en tête de notre sélection';
    /* P8 — « décroche la première place » */
    $m8 = $p1 . ' décroche la première place de notre classement';
    /* P9 — « est, selon nous, le meilleur choix parmi tous les [type] [fin] » */
    $m9 = $p1 . ' est, selon nous, le meilleur choix parmi ' . ( $fem ? 'toutes les' : 'tous les' ) . ' ' . ( $type_lc !== '' ? $type_lc : 'produits' ) . ' ' . $fin_court;

    $mains = array( $m0, $m1, $m2, $m3, $m4, $m5, $m6, $m7, $m8, $m9 );
    if ( $m0 === '' ) { $mains[0] = $m3; }

    $budgets = array(
      'Si vous cherchez un peu moins cher, nous recommandons ' . $p2 . '.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' est une excellente alternative.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' offre le meilleur rapport qualité-prix.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' mérite votre attention.',
      'Si vous cherchez un peu moins cher, ' . $p2 . ' offre un bon compromis.',
      'Si vous voulez faire des économies, nous recommandons ' . $p2 . '.',
      'En alternative plus abordable, nous recommandons ' . $p2 . '.',
      'Si le prix est un critère important pour vous, ' . $p2 . ' propose l\'essentiel pour moins cher.',
      'Si vous avez un budget plus serré, ' . $p2 . ' est l\'option la plus abordable de notre sélection.',
      'Si votre priorité est le prix, ' . $p2 . ' est l\'alternative la plus accessible de notre classement.',
    );

    $alts = array(
      'Si vous hésitez encore, ' . $p2 . ' constitue une bonne alternative.',
      'Juste derrière, ' . $p2 . ' mérite aussi votre attention.',
      $p2 . ' obtient la deuxième place de notre classement.',
      'En deuxième position, ' . $p2 . ' nous a également convaincus.',
      $p2 . ' est également un choix envisageable.',
      $p2 . ' est tout juste derrière.',
      'Autre option sérieuse, ' . $p2 . ' occupe la deuxième place.',
      $p2 . ' est également un excellent choix.',
      $p2 . ' vaut aussi le détour.',
      'Nous recommandons aussi ' . $p2 . '.',
    );

    $main = $mains[ $d2 ];
    $out = mb_strtoupper( mb_substr( $main, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $main, 1, null, 'UTF-8' ) . '.';
    if ( $p2 !== '' ) {
      $s2 = $is_budget ? $budgets[ $d3 ] : $alts[ $d3 ];
      $out .= ' ' . mb_strtoupper( mb_substr( $s2, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $s2, 1, null, 'UTF-8' );
    }
    return '<p class="mt-lede-reco">' . $out . '</p>';
  }
}

if ( ! function_exists( 'mt_top_infos' ) ) {
  /* Nom affiché et note /10 des premiers produits du classement (verdict, méta description).
     Nom : même logique que mt_intro_reco. Note : même calcul que les cartes du résumé. */
  function mt_top_infos( $ids, $max ) {
    $out = array();
    foreach ( array_slice( array_values( array_filter( array_map( 'intval', (array) $ids ) ) ), 0, $max ) as $pid ) {
      $forced = trim( (string) get_field( 'mltv5_forcer_affichage_du_titre', $pid ) );
      $brand  = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) );
      $model  = trim( (string) get_field( 'mltv5_modele_du_produit', $pid ) );
      if ( $forced !== '' ) { $name = $forced; }
      elseif ( $model !== '' ) { $name = trim( $brand . ' ' . $model ); }
      else { $name = (string) get_the_title( $pid ); }
      $mt_keep = $GLOBALS['post'] ?? null;
      $GLOBALS['post'] = get_post( $pid );
      setup_postdata( $GLOBALS['post'] );
      $s10 = function_exists( 'get_acf_score_divided_by_10' ) ? (float) get_acf_score_divided_by_10() : round( (float) get_field( 'mltv5_score_recent', $pid ) / 10, 1 );
      $GLOBALS['post'] = $mt_keep;
      if ( $mt_keep ) { setup_postdata( $mt_keep ); }
      $out[] = array( 'pid' => $pid, 'name' => html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ), 'score' => $s10 );
    }
    return $out;
  }
}
if ( ! function_exists( 'mt_meta_auto' ) ) {
  /* Méta description automatique (tests Jev du 2026-10-02, format M3 de l'Architecture, 2,99/3) :
     « A arrive en tête de notre comparatif 2026 (x/10). N X analysé(e)s, T retenu(e)s : notes,
     avantages et inconvénients. » Au-delà de 158 caractères, la fin « : notes, avantages et
     inconvénients » est retirée. Texte brut (pas de HTML). '' si aucun produit. */
  function mt_meta_auto( $ids, $type_plur, $llm, $n ) {
    $t = count( array_filter( array_map( 'intval', (array) $ids ) ) );
    $p = mt_top_infos( $ids, 1 );
    if ( empty( $p ) || $p[0]['name'] === '' ) { return ''; }
    $fem  = ( mb_strpos( mb_strtolower( (string) $llm, 'UTF-8' ), 'meilleure' ) !== false );
    $plur = mb_strtolower( trim( html_entity_decode( (string) $type_plur, ENT_QUOTES, 'UTF-8' ) ), 'UTF-8' );
    $e    = ( $fem && $plur !== '' ) ? 'e' : '';
    $typ  = $plur !== '' ? $plur : 'produits';
    $note = $p[0]['score'] > 0 ? ' (' . number_format( $p[0]['score'], 1, ',', '' ) . '/10)' : '';
    $tete = $p[0]['name'] . ' arrive en tête de notre comparatif ' . date_i18n( 'Y' ) . $note . '. ';
    $nb   = ( $n > $t ) ? $n . ' ' . $typ . ' analysé' . $e . 's, ' . $t . ' retenu' . $e . 's'
                        : max( (int) $n, $t ) . ' ' . $typ . ' analysé' . $e . 's et classé' . $e . 's';
    $meta = $tete . $nb . ' : notes, avantages et inconvénients.';
    if ( mb_strlen( $meta, 'UTF-8' ) > 158 ) { $meta = $tete . $nb . '.'; }
    return $meta;
  }
}
if ( ! function_exists( 'mt_verdict_ouverture' ) ) {
  /* Ouverture juste sous le H1 (tests Jev du 2026-10-02, variante S2 de l'Architecture : ouverture
     0,95, confirmée sur deux tours) : « Réponse courte : A (9,0/10) est la meilleure X en 2026,
     devant B (8,9/10) et C (8,8/10). Nous avons analysé N X et retenu les T meilleur(e)s : cette
     page détaille notre top T[, les classe aussi par profil (p1, p2, p3)] et vous aide à choisir.
     Chaque fiche est notée sur 10[ et le guide détaille k critères de choix] ; classement mis à
     jour le {date}. » Profils (multi-comparatif) : libellés courts des 3 premières sections.
     Critères : mention seulement si k >= 3.
     Nom du produit et lien du n°1 : même logique que mt_intro_reco. Notes : même calcul que les
     cartes du résumé (get_acf_score_divided_by_10). */
  function mt_verdict_ouverture( $ids, $type_plur, $type_sing, $llm, $n, $date, $k, $profils = array() ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    $t   = count( $ids );
    if ( $t === 0 ) { return ''; }
    $prods = mt_top_infos( $ids, 3 );
    if ( empty( $prods ) || $prods[0]['name'] === '' ) { return ''; }

    /* Lien du n°1 : ASIN Amazon, sinon 1er lien produit, sinon son test complet */
    $p1   = $prods[0]['pid'];
    $asin = trim( (string) get_field( 'mltv5_asin_amazon', $p1 ) );
    $url  = '';
    if ( $asin !== '' ) {
      $url = 'https://www.amazon.fr/dp/' . rawurlencode( $asin ) . '?tag=mlt00-21';
    } else {
      for ( $li = 1; $li <= 3; $li++ ) {
        $lu = trim( (string) get_field( 'mltv5_lien_du_produit_' . $li, $p1 ) );
        if ( $lu !== '' && strpos( $lu, 'http' ) === 0 ) { $url = $lu; break; }
      }
    }
    if ( $url === '' ) { $url = '#produit-n-1'; }

    $llm_t = mb_strtolower( trim( (string) $llm ), 'UTF-8' );
    $fem   = ( mb_strpos( $llm_t, 'meilleure' ) !== false );
    $pl    = ( mb_strpos( $llm_t, 'les ' ) === 0 );
    $plur  = mb_strtolower( trim( (string) $type_plur ), 'UTF-8' );
    $sing  = mb_strtolower( trim( (string) $type_sing ), 'UTF-8' );
    $an    = date_i18n( 'Y' );
    $note  = function ( $p ) { return $p['score'] > 0 ? ' (' . number_format( $p['score'], 1, ',', '' ) . '/10)' : ''; };

    $lien = '<a href="' . esc_url( $url ) . '">' . esc_html( $prods[0]['name'] ) . '</a>' . $note( $prods[0] );
    if ( ! $pl && $sing !== '' ) { $out = 'Réponse courte : ' . $lien . ' est ' . ( $fem ? 'la meilleure ' : 'le meilleur ' ) . esc_html( $sing ) . ' en ' . $an; }
    else { $out = 'Réponse courte : ' . $lien . ' est notre n°1 parmi les ' . esc_html( $plur !== '' ? $plur : 'produits' ) . ' en ' . $an; }
    $autres = array();
    foreach ( array_slice( $prods, 1 ) as $p ) { if ( $p['name'] !== '' ) { $autres[] = esc_html( $p['name'] ) . $note( $p ); } }
    if ( count( $autres ) === 2 ) { $out .= ', devant ' . $autres[0] . ' et ' . $autres[1]; }
    elseif ( count( $autres ) === 1 ) { $out .= ', devant ' . $autres[0]; }
    $out .= '.';

    $typ = esc_html( $plur !== '' ? $plur : 'produits' );
    $e   = ( $fem && $plur !== '' ) ? 'e' : '';
    $profils = array_slice( array_values( array_filter( array_map( 'trim', (array) $profils ) ) ), 0, 3 );
    $suite = ' : cette page détaille notre top ' . $t
      . ( $profils ? ', les classe aussi par profil (' . esc_html( implode( ', ', $profils ) ) . ')' : '' )
      . ' et vous aide à choisir.';
    if ( $n > $t ) { $out .= ' Nous avons analysé ' . (int) $n . ' ' . $typ . ' et retenu les ' . $t . ' meilleur' . $e . 's' . $suite; }
    else { $out .= ' Nous avons analysé et classé ' . max( (int) $n, $t ) . ' ' . $typ . $suite; }

    $out .= ' Chaque fiche est notée sur 10'
      . ( $k >= 3 ? ' et le guide détaille ' . (int) $k . ' critères de choix' : '' )
      . ' ; classement mis à jour le ' . esc_html( $date ) . '.';
    return '<p class="mt-verdict">' . $out . '</p>';
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
    $out = '<p class="mt-picks-intro"><strong>Notre s&eacute;lection&nbsp;:</strong></p>';
    $out .= '<ol class="mt-picks">';
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

  <?php // Effets SEO Rank Math
  /* Title (tests Jev du 2026-10-02, voir CLAUDE.md) : titre forcé s'il est rempli ;
     sinon « Meilleur(e) X 2026 : N analysé(e)s, T retenu(e)s », N = vrai nombre d'avis
     publiés du type + attributs, +5 si < 10 comme l'encadré, T = produits du classement ; si
     N <= T, repli sur « Meilleur(e) X 2026 (N produits comparés) ».
     Méta description : une description saisie à la main (différente de l'extrait) n'est
     plus écrasée ; la description automatique est mt_meta_auto() sur un comparatif (format M3
     des tests Jev), les 50 premiers mots de l'introduction sinon. L'extrait reste l'introduction. */
  if ( ! function_exists( 'mt_avis_count' ) ) {
    /* Avis publiés du même type de produit et de TOUS les attributs du comparatif. */
    function mt_avis_count( $id ) {
      static $memo = array();
      if ( isset( $memo[ $id ] ) ) { return $memo[ $id ]; }
      $n = 0;
      $prod = get_the_terms( $id, 'post-type-produit' );
      if ( is_array( $prod ) && ! empty( $prod ) ) {
        $tq = array( array( 'taxonomy' => 'post-type-produit', 'terms' => wp_list_pluck( $prod, 'term_id' ) ) );
        $attr = get_the_terms( $id, 'post-type-attribut' );
        if ( is_array( $attr ) && ! empty( $attr ) ) {
          $tq['relation'] = 'AND';
          $tq[] = array( 'taxonomy' => 'post-type-attribut', 'terms' => wp_list_pluck( $attr, 'term_id' ), 'operator' => 'AND' );
        }
        $q = new WP_Query( array(
          'post_type'              => 'avis',
          'post_status'            => 'publish',
          'tax_query'              => $tq,
          'posts_per_page'         => -1,
          'fields'                 => 'ids',
          'no_found_rows'          => true,
          'update_post_meta_cache' => false,
          'update_post_term_cache' => false,
        ) );
        $n = count( $q->posts );
      }
      return $memo[ $id ] = $n;
    }
  }
  if ( ! function_exists( 'mt_title_auto' ) ) {
    /* « Meilleur(e) X » accordé via lalalesmeilleur (le meilleur / la meilleure → type au
       singulier ; les … → pluriel), repli masculinsfeminins + type au pluriel. */
    function mt_title_auto( $n, $t, $llm, $sing, $plur, $mf ) {
      $llm  = trim( (string) $llm );
      $adj  = trim( preg_replace( '/^(le|la|les)\s+/iu', '', $llm ) );
      $sing = trim( (string) $sing );
      $plur = trim( (string) $plur );
      $type = ( preg_match( '/^les\s/iu', $llm ) || $sing === '' ) ? $plur : $sing;
      if ( $adj === '' ) { $adj = lcfirst( trim( (string) $mf ) !== '' ? trim( (string) $mf ) : 'meilleurs' ); $type = $plur; }
      $e    = preg_match( '/^meilleures?$/iu', $adj ) ? 'e' : '';
      $tete = trim( $adj . ' ' . $type );
      $tete = mb_strtoupper( mb_substr( $tete, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $tete, 1, null, 'UTF-8' );
      $an   = date_i18n( 'Y' );
      if ( $t > 0 && $n > $t ) { $titre = $tete . ' ' . $an . ' : ' . $n . ' analysé' . $e . 's, ' . $t . ' retenu' . $e . 's'; }
      else { $titre = $tete . ' ' . $an . ' (' . max( $n, $t ) . ' produits comparés)'; }
      return html_entity_decode( $titre, ENT_QUOTES, 'UTF-8' );
    }
  }
  /* N = produits analysés (title, méta, verdict) = MÊME compteur que l'encadré « Pourquoi nous faire
     confiance » : avis publiés du type + attributs, +5 si < 10 (décision de Samuel : la rédaction analyse
     au moins 5 produits de plus que ceux publiés). Multi-comparatif : jamais moins que les produits affichés. */
  $mt_n = 0;
  $mt_pl = null;
  if ($post_type === 'comparatif') {
      $mt_n = mt_avis_count( $this_id );
      if ( $mt_n < 10 ) { $mt_n += 5; }
      $mt_pl = function_exists( 'mtv2_plan' ) ? mtv2_plan( $this_id ) : null;
      if ( $mt_pl && ! empty( $mt_pl['is_multi'] ) ) { $mt_n = max( $mt_n, count( $mt_pl['origin'] ) ); }
  }
  if (($template_description ?? '') == 0 || $post_type === 'liste') {
      $rank_math_description = (string) get_post_meta($this_id, 'rank_math_description', true);
      $mt_meta_prec = (string) get_post_meta($this_id, '_mt_meta_auto', true);
      $p = get_post($this_id);
      $excerpt = (string) ($p->post_excerpt ?? '');
      $intro50 = intro(50, $this_id);
      /* Méta automatique si elle est vide, égale à la dernière méta générée (_mt_meta_auto, caché) ou à
         l'extrait (ancien mode : méta = extrait). Toute autre valeur a été saisie à la main : gardée. */
      if ($rank_math_description === '' || $rank_math_description === $mt_meta_prec || $rank_math_description === $excerpt) {
          /* Comparatif : méta tirée des données (repli : 50 premiers mots de l'introduction). */
          $new_desc = ($post_type === 'comparatif') ? mt_meta_auto( $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $lalalesmeilleur ?? '', $mt_n ) : '';
          if ($new_desc === '') { $new_desc = $intro50; }
          if (($new_desc !== $rank_math_description) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_description', $new_desc); }
          if ($new_desc !== $mt_meta_prec) { update_post_meta($this_id, '_mt_meta_auto', $new_desc); }
      }
      /* Extrait : toujours les 50 premiers mots de l'introduction (affiché dans les cartes du site). */
      if ($excerpt !== $intro50) { wp_update_post(array('ID'=>$this_id,'post_excerpt'=>$intro50)); }
  }
  if (!empty($forcer_affichage_du_titre ?? '')) { $new_title = $forcer_affichage_du_titre; }
  elseif ($post_type === 'liste') { $new_title = get_the_title($this_id); }
  elseif ($post_type === 'comparatif') {
      $new_title = mt_title_auto( $mt_n, $total_avis, $lalalesmeilleur ?? '', $type_de_produit_au_singulier ?? '', $type_de_produit_au_pluriel ?? '', $masculinsfeminins ?? '' );
  }
  else { $new_title = "Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." 2026 | Test par Meilleurtest"; }
  $rank_math_title = (string) get_post_meta($this_id, 'rank_math_title', true);
  if (($new_title !== $rank_math_title) && ($this_id <> 4224)) { update_post_meta($this_id, 'rank_math_title', $new_title); }
  ?>

  <h1 class="mt-h1">
  <?php
    if (!empty($forcer_affichage_du_titre ?? '')) {
        echo esc_html($forcer_affichage_du_titre);
    } elseif ($MT_H1_EGAL_TITLE && $post_type === 'comparatif') {
        echo esc_html($new_title);
    } elseif ($post_type === 'comparatif') {
        echo 'Les <em>' . $total_avis . ' ' . lcfirst($masculinsfeminins ?? 'meilleures') . ' ' . $type_de_produit_au_pluriel . '</em> en 2026';
        echo !empty($sous_titre ?? '') ? ' : ' . $sous_titre : ' : comparatif et guide d\'achat';
    } else {
        echo esc_html(get_the_title());
    }
  ?>
  </h1>


  <?php if ( $MT_SHOW_INTRO_RECO && $MT_VERDICT_SOUS_H1 && $post_type === 'comparatif' ) {
      /* k = critères de choix du guide (page, sinon annexe en cache) */
      $mt_crit = get_field( 'mltv5_criteres_de_choix', $this_id );
      if ( empty( $mt_crit ) ) {
          $mt_cid  = (int) get_field( 'mltv5_cached_id_criteres', $this_id );
          $mt_crit = $mt_cid ? get_field( 'mltv5_criteres_de_choix', $mt_cid ) : array();
      }
      /* Profils = libellés courts des sections du multi-comparatif (comme le sommaire), 1re lettre en minuscule */
      $mt_profils = array();
      if ( ! empty( $mt_pl['is_multi'] ) ) {
          foreach ( $mt_pl['subs'] as $mt_sb ) {
              $mt_l = trim( (string) $mt_sb['label'] );
              if ( $mt_l !== '' ) { $mt_profils[] = mb_strtolower( mb_substr( $mt_l, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $mt_l, 1, null, 'UTF-8' ); }
          }
      }
      echo mt_verdict_ouverture( $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $type_de_produit_au_singulier ?? '', $lalalesmeilleur ?? '', $mt_n, $mod, is_array( $mt_crit ) ? count( $mt_crit ) : 0, $mt_profils );
  } ?>

  <div class="mt-byline">
    <?php if (!empty($author_avatar_id ?? '')) {
        echo '<span class="mt-avatar">' . wp_get_attachment_image($author_avatar_id, array(30,30), '', array('alt'=>$author_avatar_alt ?? '')) . '</span>';
    } ?>
    <span class="mt-byline-text">
      <?php if ( $MT_VERIFIE_PAR !== '' && trim( (string) ( $author ?? '' ) ) === $MT_VERIFIE_PAR ) { ?>
      <span>Rédigé et vérifié par <b><?php echo esc_html( $MT_VERIFIE_PAR ); ?></b>, responsable éditorial</span>
      <?php } else { ?>
      <span>Par <b><?php echo esc_html($author ?? ''); ?></b></span>
      <?php if ( $MT_VERIFIE_PAR !== '' ) {
          /* Forme courte (« S. Petit, resp. éditorial ») affichée sur mobile par le CSS via data-court :
             seule la forme complète est dans le texte de la page (lue par Jev et les lecteurs d'écran). */
          $mt_vp = preg_split( '/\s+/u', trim( $MT_VERIFIE_PAR ), 2 );
          $mt_vp_court = count( $mt_vp ) === 2 ? mb_substr( $mt_vp[0], 0, 1, 'UTF-8' ) . '. ' . $mt_vp[1] : $MT_VERIFIE_PAR; ?>
      <span class="mt-dot">&bull;</span>
      <span class="mt-verif" data-court="<?php echo esc_attr( 'Vérifié par ' . $mt_vp_court . ', resp. éditorial' ); ?>">Vérifié par <b><?php echo esc_html( $MT_VERIFIE_PAR ); ?></b>, responsable éditorial</span>
      <?php } } ?>
      <span class="mt-dot">&bull;</span>
      <span>Mis à jour le <?php echo $mod; ?></span>
    </span>
  </div>

  <div class="mt-lede"><?php
  $mt_intro_html = $introduction ?? '';
  if ( $MT_SHOW_BOLD_INTRO ) {
      $mt_intro_html = mt_bold_intro( $mt_intro_html, array(
        'sing' => $type_de_produit_au_singulier ?? '',
        'plur' => $type_de_produit_au_pluriel ?? '',
        'llm'  => $lalalesmeilleur ?? '',
        'mf'   => $masculinsfeminins ?? '',
      ) );
  }
  echo $mt_intro_html;
  if ( $MT_SHOW_INTRO_RECO && ! $MT_VERDICT_SOUS_H1 && $post_type === 'comparatif' ) {
      echo mt_intro_reco( $this_id, $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $type_de_produit_au_singulier ?? '', $lalalesmeilleur ?? '' );
  } ?></div>

  <?php if ( $MT_SHOW_QUICK_PICKS && $post_type === 'comparatif' && ! empty( $top_avis_ids ) ) {
    echo mt_quick_picks( $top_avis_ids );
  } ?>

  <div class="mt-photo">
    <?php echo get_the_post_thumbnail($this_id, 'large', array('class'=>'mt-photo-img')); ?>
    <?php if ($post_type === 'comparatif') {
        echo '<img class="mt-badge" src="https://meilleurtest.fr/wp-content/uploads/2026/07/badge-mt3.png" alt="" style="position:absolute;top:0;left:0;max-width:130px;height:auto;">';
    } ?>
  </div>

</div>
