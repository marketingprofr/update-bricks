<?php
$MT_SHOW_QUICK_PICKS = false;
$MT_SHOW_BOLD_INTRO  = false;
$MT_SHOW_INTRO_RECO  = true;
$MT_REPONSE_INTRO    = true;   // l'intro commence par la réponse : n°1 et ses suivants avec notes /10, puis N analysés et T retenus (validé par Samuel, 2026-10-03)
$MT_VERDICT_SOUS_H1  = false;  // ancien encart séparé « L'essentiel en 30 secondes » sous la ligne auteur (remplacé par la réponse en tête de l'intro)
$MT_VERIFIE_PAR      = 'Samuel Petit'; // ligne auteur « Vérifié par …, responsable éditorial » (validé par Samuel) ; '' = pas de ligne
$MT_AUTEUR_SOUS_H1   = true;   // ligne auteur et date juste sous le titre (demande de Samuel) ; false = juste après « L'essentiel »
                                // (test Jev : ouverture 0,82 sous le titre, 0,91 après « L'essentiel »)
$MT_H1_EGAL_TITLE    = true;   // sans titre forcé, le H1 reprend le title automatique (validé par Samuel)
$MT_VOS_QUESTIONS    = 'apres_intro'; // encart des questions (questions de la FAQ, réponse d'une phrase) : 'apres_intro' = juste après
                                // l'intro (disposition validée par Samuel, 2026-10-03), 'sous_reponse' = sous la ligne auteur,
                                // 'avant_top5' = juste avant le top 5 ; '' = pas d'encart
$MT_TITRE_QUESTIONS  = 'L’essentiel en 30 secondes'; // titre de l'encart des questions (préféré par Samuel à « Vos questions ») ; '' = sans titre

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

    /* Encart « L'essentiel en 30 secondes » (demande de Samuel, 2026-10-03) : les faits de la réponse courte S2,
       en phrases complètes, sans le libellé « Réponse courte : » ; la date est dans la ligne auteur.
       Titre en <p> et non en h2 (test Jev : ouverture 0,85 contre 0,82). */
    $lien = '<a href="' . esc_url( $url ) . '">' . esc_html( $prods[0]['name'] ) . '</a>' . $note( $prods[0] );
    if ( ! $pl && $sing !== '' ) { $l1 = $lien . ' est ' . ( $fem ? 'la meilleure ' : 'le meilleur ' ) . esc_html( $sing ) . ' en ' . $an; }
    else { $l1 = $lien . ' est notre n°1 parmi les ' . esc_html( $plur !== '' ? $plur : 'produits' ) . ' en ' . $an; }
    $autres = array();
    foreach ( array_slice( $prods, 1 ) as $p ) { if ( $p['name'] !== '' ) { $autres[] = esc_html( $p['name'] ) . $note( $p ); } }
    if ( count( $autres ) === 2 ) { $l1 .= ', devant ' . $autres[0] . ' et ' . $autres[1]; }
    elseif ( count( $autres ) === 1 ) { $l1 .= ', devant ' . $autres[0]; }
    $lignes = array( $l1 . '.' );

    $typ = esc_html( $plur !== '' ? $plur : 'produits' );
    $e   = ( $fem && $plur !== '' ) ? 'e' : '';
    if ( $n > $t ) { $lignes[] = 'Nous avons analysé ' . (int) $n . ' ' . $typ . ' et retenu les ' . $t . ' meilleur' . $e . 's, détaillé' . $e . 's sur cette page.'; }
    else { $lignes[] = 'Nous avons analysé et classé ' . max( (int) $n, $t ) . ' ' . $typ . ', détaillé' . $e . 's sur cette page.'; }
    $profils = array_slice( array_values( array_filter( array_map( 'trim', (array) $profils ) ) ), 0, 3 );
    if ( $profils ) { $lignes[] = 'Notre sélection est aussi classée par profil (' . esc_html( implode( ', ', $profils ) ) . ').'; }
    $lignes[] = 'Chaque fiche est notée sur 10' . ( $k >= 3 ? ', et le guide détaille ' . (int) $k . ' critères de choix' : '' ) . '.';

    return '<div class="mt-essentiel"><p class="mt-essentiel-titre"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>L’essentiel en 30 secondes</p><ul><li>'
      . implode( '</li><li>', $lignes ) . '</li></ul></div>';
  }
}

if ( ! function_exists( 'mt_criteres_courts' ) ) {
  /* Libellés courts des critères (encadré « Pourquoi nous faire confiance » et encart « Vos questions »).
     Copie IDENTIQUE dans hero-encart et hero-gauche (V1 et V2) : le 1er bloc exécuté la définit.
     Priorité au champ mltv5_criteres_courts rempli à la main (décision de Samuel) ; sinon tirés automatiquement
     des critères du guide (page, sinon annexe en cache) : article et parenthèse retirés
     (« La puissance frigorifique (exprimée en BTU) » → « puissance frigorifique »), doublons fusionnés,
     4 au plus ; '' s'il en reste moins de 3. */
  function mt_criteres_courts( $page_id ) {
    $mt_champ = trim( wp_strip_all_tags( (string) get_field( 'mltv5_criteres_courts', $page_id ) ) );
    if ( $mt_champ !== '' ) { return $mt_champ; }
    $mt_crit = get_field( 'mltv5_criteres_de_choix', $page_id );
    if ( empty( $mt_crit ) ) { $mt_cid = (int) get_field( 'mltv5_cached_id_criteres', $page_id ); $mt_crit = $mt_cid ? get_field( 'mltv5_criteres_de_choix', $mt_cid ) : array(); }
    $mt_vus = array(); $mt_lib = array();
    foreach ( (array) $mt_crit as $mt_r ) {
      $mt_t = html_entity_decode( wp_strip_all_tags( (string) ( $mt_r['mltv5_critere_de_choix'] ?? '' ) ), ENT_QUOTES, 'UTF-8' );
      $mt_t = trim( preg_replace( '/\s*\([^)]*\)/u', '', str_replace( "\u{2019}", "'", $mt_t ) ) );
      $mt_t = trim( preg_replace( "/^(les|le|la|l'|vos|votre|son|sa|ses|un|une|des)\s*/iu", '', $mt_t ) );
      if ( $mt_t === '' || mb_strlen( $mt_t, 'UTF-8' ) > 45 ) { continue; }
      $mt_t = mb_strtolower( mb_substr( $mt_t, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $mt_t, 1, null, 'UTF-8' );
      /* Libellés faibles écartés (règle de l'Architecture, 2026-10-02) : questions, tournures de phrase,
         consignes, mots vagues seuls ; article retiré après « et » / « ou » */
      if ( strpos( $mt_t, '?' ) !== false ) { continue; }
      if ( preg_match( "/^(ne|n'|choisir|choisissez|comment|quel|quelle|pourquoi|optez|privilégiez|vérifiez|faites|pensez|tenez|prenez|à noter|les plus|bon à savoir|attention|facile|bon|bonne|sous|avec|sans|pour|en|à|au|aux|selon|bien|savoir|opter|se|s')(\s|$|')/iu", $mt_t ) ) { continue; }
      if ( preg_match( '/\b(est|sont|doit|peut|vous|votre|vos|il faut)\b/iu', $mt_t ) ) { continue; }
      if ( in_array( mb_strtolower( $mt_t, 'UTF-8' ), array( 'type', 'besoins', 'fonctionnalités', 'modèle', 'options', 'marque', 'design', 'utilisation', 'caractéristiques', 'accessoires', 'critères', 'choix' ), true ) ) { continue; }
      $mt_t = preg_replace( "/\b(et|ou) (le|la|les|l')\s*/iu", '$1 ', $mt_t );
      $mt_k = remove_accents( mb_strtolower( $mt_t, 'UTF-8' ) );
      if ( isset( $mt_vus[ $mt_k ] ) ) { continue; }
      $mt_vus[ $mt_k ] = true; $mt_lib[] = $mt_t;
    }
    return count( $mt_lib ) >= 3 ? implode( ', ', array_slice( $mt_lib, 0, 4 ) ) : '';
  }
}
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
if ( ! function_exists( 'mt5_num' ) ) {
  function mt5_num( $v ) {
    $v = str_replace( array( ' ', "\xc2\xa0", '€' ), '', (string) $v );
    $v = str_replace( ',', '.', $v );
    return is_numeric( $v ) ? (float) $v : 0.0;
  }
}
if ( ! function_exists( 'mt_faq_read' ) ) {
  function mt_faq_read( $pid ) {
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_faq_comparatif', $pid ) : null;
    return is_array( $rows ) ? $rows : array();
  }
}
if ( ! function_exists( 'mt_prix_unite' ) ) {
  /* Unité du prix d'une fiche (2026-10-03, préparé désactivé) : étiquette WordPress (post_tag) « prix-mensuel » →
     'mois', « prix-annuel » → 'an', sinon '' (prix unique : affichage d'aujourd'hui). 'mois' / 'an' = prix d'appel
     d'un abonnement : « à partir de X €/mois » (ou « /an »), minimum seul, jamais de borne haute, au lieu de « environ X € ».
     Copie IDENTIQUE dans les blocs qui affichent un prix (faq, hero gauche V1/V2, tests V1/V2, avis, avis-hero, avis-content). */
  function mt_prix_unite( $post_id ) {
    static $memo = array();
    $post_id = (int) $post_id;
    if ( ! isset( $memo[ $post_id ] ) ) {
      $memo[ $post_id ] = '';
      $tags = get_the_terms( $post_id, 'post_tag' );
      foreach ( is_array( $tags ) ? $tags : array() as $t ) {
        if ( $t->slug === 'prix-mensuel' ) { $memo[ $post_id ] = 'mois'; break; }
        if ( $t->slug === 'prix-annuel' ) { $memo[ $post_id ] = 'an'; break; }
      }
    }
    return $memo[ $post_id ];
  }
}
if ( ! function_exists( 'mt_prix_unite_liste' ) ) {
  /* Unité commune à des fiches (phrase de prix d'un comparatif) : '' si aucune n'a d'étiquette, 'mois' / 'an' si
     toutes ont la même, null si elles diffèrent (alors pas de phrase de prix). */
  function mt_prix_unite_liste( $ids ) {
    $u = array();
    foreach ( (array) $ids as $id ) { $u[ mt_prix_unite( $id ) ] = true; }
    if ( count( $u ) > 1 ) { return null; }
    return count( $u ) === 1 ? (string) key( $u ) : '';
  }
}
if ( ! function_exists( 'mt_prix_par' ) ) {
  /* « 2,49 €/mois », « 49 €/an » (centimes seulement s'il y en a) */
  function mt_prix_par( $v, $unite ) {
    $v = (float) $v;
    return number_format( $v, ( abs( $v - round( $v ) ) < 0.005 ? 0 : 2 ), ',', "\xc2\xa0" ) . "\xc2\xa0€/" . $unite;
  }
}
if ( ! function_exists( 'mt_faq_ancre' ) ) {
  /* Ancre d'une question de la FAQ (« faq-quel-budget-prevoir-pour-un-climatiseur-mobile ») : calculée de la même façon
     par la FAQ (id de la question) et par l'encart « Vos questions » (lien « En savoir plus »).
     Copie IDENTIQUE dans faq et les blocs hero (V1 et V2). */
  function mt_faq_ancre( $q ) {
    $t = str_replace( "\xc2\xa0", ' ', html_entity_decode( wp_strip_all_tags( (string) $q ), ENT_QUOTES, 'UTF-8' ) );
    $s = sanitize_title( $t );
    return 'faq-' . ( $s !== '' ? substr( $s, 0, 80 ) : 'question' );
  }
}
if ( ! function_exists( 'mt_vq_phrase' ) ) {
  /* Encart « Vos questions » : réponse d'une phrase tirée d'une réponse de la FAQ (règle de la Coordination, 2026-10-03).
     1re phrase qui contient un chiffre, un nom propre ou plus de 60 caractères, sinon les deux premières ;
     un « Oui… » / « Non ! » court en tête reste devant ; questions rhétoriques sautées ; numéros de liste retirés ;
     phrase choisie qui renvoie à la précédente (« C'est pourquoi… », « Vous pourrez ainsi… », « Il… ») : la précédente
     est gardée devant si le tout tient ; 220 caractères au plus, coupé sur un mot. */
  function mt_vq_phrase( $html ) {
    $t = preg_replace( '#\R\s*\R|</(p|li|h[1-6]|div|tr)>|<br\s*/?>#iu', "\x1e", (string) $html );
    $t = str_replace( "\xc2\xa0", ' ', html_entity_decode( wp_strip_all_tags( $t ), ENT_QUOTES, 'UTF-8' ) );
    $t = preg_replace( '/\.\.(?!\.)/u', '.', preg_replace( '/\s+\./u', '.', $t ) ); // « électrique. . » → « électrique. »
    $ph = array();
    foreach ( explode( "\x1e", $t ) as $bloc ) {
      $bloc = preg_replace( '/^\d+\s*[.)]\s+/u', '', trim( preg_replace( '/\s+/u', ' ', $bloc ) ) );
      if ( $bloc === '' ) { continue; }
      foreach ( preg_split( '/(?<=[.!?…])\s+(?=[\p{Lu}0-9«])/u', $bloc ) as $p ) {
        if ( trim( $p ) !== '' ) { $ph[] = trim( $p ); }
      }
    }
    $tete = '';
    if ( count( $ph ) > 1 && preg_match( '/^(oui|non)\b/iu', $ph[0] ) && mb_strlen( $ph[0], 'UTF-8' ) <= 40 ) { $tete = array_shift( $ph ); }
    $ph = array_values( array_filter( $ph, function ( $p ) { return substr( $p, -1 ) !== '?'; } ) );
    $choix = '';
    foreach ( $ph as $i => $p ) {
      if ( preg_match( '/\d/u', $p ) || preg_match( "/[\s'’(]\p{Lu}/u", $p ) || mb_strlen( $p, 'UTF-8' ) > 60 ) {
        $choix = $p;
        $liaison = "/^(c['’]est|ainsi|donc|alors|cependant|toutefois|en effet|par contre|de plus|aussi|également|pourtant|néanmoins|mais|ensuite|enfin|par ailleurs|la première|le premier|la seconde|le second|la deuxième|le deuxième|cela|ceci|celui-ci|celle-ci|ceux-ci|celles-ci|il|elle|ils|elles)\b|^(\S+\s+){1,2}ainsi\b/iu";
        if ( $i > 0 && preg_match( $liaison, $p ) && mb_strlen( $tete . ' ' . $ph[ $i - 1 ] . ' ' . $p, 'UTF-8' ) <= 220 ) { $choix = $ph[ $i - 1 ] . ' ' . $p; }
        break;
      }
    }
    if ( $choix === '' ) { $choix = implode( ' ', array_slice( $ph, 0, 2 ) ); }
    $r = trim( $tete . ' ' . $choix );
    if ( mb_strlen( $r, 'UTF-8' ) > 220 ) {
      $r   = mb_substr( $r, 0, 219, 'UTF-8' );
      $esp = mb_strrpos( $r, ' ', 0, 'UTF-8' );
      if ( $esp ) { $r = mb_substr( $r, 0, $esp, 'UTF-8' ); }
      $r = rtrim( $r, ' ,;:-' ) . '…';
    } elseif ( $r !== '' && ! preg_match( '/[.!?…]$/u', $r ) ) {
      $r .= '.';
    }
    return $r;
  }
}

if ( ! function_exists( 'mt_vos_questions' ) ) {
  /* Encart « Vos questions » (tests Jev du 2026-10-03 sur 10 pages : utilité +0,07 avant le top 5, +0,09 après
     la réponse courte), chaque question avec une réponse d'une phrase et un lien vers sa réponse complète.
     Choix des questions (règle de l'instance FAQ optimization, 2026-10-03, notée avec Jev question par question) :
     - au moins 3 questions de la rédaction : les 3 premières du répéteur (l'ordre du répéteur = ordre de qualité,
       rangé par cette instance), puis « Quel budget prévoir… » s'il y a des prix, sinon la 4e question de la rédaction ;
       « Comment bien choisir… » reste dans la FAQ (phrase trop générale) ;
     - moins de 3 (FAQ pas encore refaite) : règle d'avant, budget (ou « Pourquoi faire confiance »), comment choisir,
       puis la rédaction, 5 au plus.
     Sautées, car déjà dites plus haut : « Quel est le meilleur… », « meilleures marques », « meilleurs avis »,
     « Comment avons-nous établi… ». Au moins 3 questions, sinon rien. */
  function mt_vos_questions( $page_id, $ids, $type_sing, $type_plur, $llm, $titre = 'Vos questions' ) {
    $items    = array();
    $budget   = null; $confiance = null; $choisir = null;
    $nb_autos = 0;
    $ids      = array_slice( array_values( array_filter( array_map( 'intval', (array) $ids ) ) ), 0, 5 );
    if ( ! empty( $ids ) ) {
      /* Nombre de questions automatiques de la FAQ (mêmes conditions que faq.code.php) : meilleur produit, budget ou
         avis ou confiance, méthode ; + marques (au moins 3 marques dans le top 5) ; + comment choisir (critères du guide) */
      $marques = array();
      foreach ( $ids as $pid ) { $bn = trim( (string) get_field( 'mltv5_marque_du_produit', $pid ) ); if ( $bn !== '' ) { $marques[ $bn ] = true; } }
      $crit_faq = get_field( 'mltv5_criteres_de_choix', $page_id );
      if ( ! is_array( $crit_faq ) || empty( $crit_faq ) ) {
        $cc = mt_guide_cache_id( $page_id, 'criteres' );
        $crit_faq = ( $cc && $cc !== (int) $page_id ) ? get_field( 'mltv5_criteres_de_choix', $cc ) : array();
      }
      $a_crit = false;
      foreach ( (array) $crit_faq as $r ) { if ( is_array( $r ) && trim( (string) ( $r['mltv5_critere_de_choix'] ?? '' ) ) !== '' ) { $a_crit = true; break; } }
      $nb_autos = 3 + ( count( $marques ) >= 3 ? 1 : 0 ) + ( $a_crit ? 1 : 0 );
      /* Questions automatiques de la FAQ (mêmes conditions et mêmes accords que faq.code.php) */
      $low    = strtolower( (string) $llm );
      $plural = ( strpos( $low, 'les ' ) === 0 );
      $fem    = $plural ? ( strpos( $low, 'meilleures' ) !== false ) : ( strpos( $low, 'la ' ) === 0 );
      $prix   = array();
      $notes  = 0;
      foreach ( $ids as $pid ) {
        $v = mt5_num( get_field( 'mltv5_prix_indicatif', $pid ) );
        if ( $v > 0 ) { $prix[ $pid ] = $v; }
        if ( mt5_num( get_field( 'mltv5_score_avis_clients', $pid ) ) > 0 ) { $notes++; }
      }
      /* Unité du prix (étiquettes prix-mensuel / prix-annuel des fiches avec prix) ; unités différentes : pas de phrase de prix */
      $mens = mt_prix_unite_liste( array_keys( $prix ) );
      if ( $mens === null ) { $prix = array(); }
      if ( count( $prix ) >= 2 ) {
        $euro  = function ( $v ) { return number_format( (float) $v, 0, ',', "\xc2\xa0" ) . "\xc2\xa0€"; };
        if ( $mens !== '' ) { $euro = function ( $v ) use ( $mens ) { return mt_prix_par( $v, $mens ); }; }
        $indef = $plural
          ? ( 'des ' . ( $type_plur !== '' ? $type_plur : $type_sing ) )
          : ( ( $fem ? 'une' : 'un' ) . ' ' . ( $type_sing !== '' ? $type_sing : $type_plur ) );
        $noun  = $type_plur !== '' ? $type_plur : ( $type_sing !== '' ? $type_sing : 'produits' );
        $budget = array( 'Quel budget prévoir pour ' . trim( $indef ) . "\xc2\xa0?",
          $mens  // abonnement : prix d'appel, donc le minimum seul
            ? 'Les ' . $noun . ' de notre sélection sont proposé' . ( $fem ? 'e' : '' ) . 's à partir de ' . $euro( min( $prix ) ) . '.'
            : 'Les ' . $noun . ' de notre sélection s’échelonnent d’environ ' . $euro( min( $prix ) ) . ' à ' . $euro( max( $prix ) ) . '.' );
      } elseif ( $notes < 2 ) {
        $confiance = array( "Pourquoi faire confiance à ce comparatif\xc2\xa0?",
          "Notre rédaction travaille en toute indépendance. Aucune marque ne peut acheter sa place dans un classement, et nous n’acceptons ni publicité ni cadeau des marques." );
      }
      /* « Comment bien choisir… » : les libellés courts des critères, plutôt que la 1re phrase générique de la FAQ */
      $crit = mt_criteres_courts( $page_id );
      if ( $crit !== '' ) {
        $best_noun = $plural ? ( $type_plur !== '' ? $type_plur : $type_sing ) : ( $type_sing !== '' ? $type_sing : $type_plur );
        $choisir = array( 'Comment bien choisir ' . ( $plural ? 'vos' : 'votre' ) . ' ' . ( $best_noun !== '' ? $best_noun : 'produit' ) . "\xc2\xa0?",
          'Pour bien choisir, comparez surtout ces critères (' . preg_replace( '/, ([^,]+)$/u', ' et $1', $crit ) . ').' );
      }
    }
    /* Questions de la rédaction (repeater ACF, page puis annexe en cache) */
    $rows = mt_faq_read( $page_id );
    if ( empty( $rows ) ) {
      $c = mt_guide_cache_id( $page_id, 'faq' );
      if ( $c && $c !== (int) $page_id ) { $rows = mt_faq_read( $c ); }
    }
    $nb_redac = 0;
    foreach ( $rows as $r ) {
      if ( trim( (string) ( $r['mltv5_faq_comparatif_question'] ?? '' ) ) !== '' || trim( (string) ( $r['mltv5_faq_comparatif_reponse'] ?? '' ) ) !== '' ) { $nb_redac++; }
    }
    $saute = '/^(Quel(le)?s? (est|sont) (le|la|les) meilleur|Quelles sont les meilleures marques|Quel produit a les meilleurs avis|Comment avons-nous établi)/u';
    $redac = array();
    foreach ( $rows as $r ) {
      if ( count( $redac ) >= 5 ) { break; }
      $q = trim( html_entity_decode( wp_strip_all_tags( (string) ( $r['mltv5_faq_comparatif_question'] ?? '' ) ), ENT_QUOTES, 'UTF-8' ) );
      if ( $q === '' || preg_match( $saute, $q ) ) { continue; }
      $a = mt_vq_phrase( (string) ( $r['mltv5_faq_comparatif_reponse'] ?? '' ) );
      if ( $a !== '' ) { $redac[] = array( $q, $a ); }
    }
    if ( count( $redac ) >= 3 ) {
      $items = array_slice( $redac, 0, 3 );
      if ( $budget ) { $items[] = $budget; } elseif ( isset( $redac[3] ) ) { $items[] = $redac[3]; }
    } else {
      $items = array_values( array_filter( array( $budget ? $budget : $confiance, $choisir ) ) );
      foreach ( $redac as $x ) { if ( count( $items ) >= 5 ) { break; } $items[] = $x; }
    }
    if ( count( $items ) < 3 ) { return ''; }
    /* Chaque réponse courte mène à sa réponse complète dans la FAQ (« En savoir plus », ancre de la question, qui s'ouvre
       au clic) ; le lien du bas annonce le nombre de questions de la FAQ (automatiques + rédaction, mêmes règles que
       faq.code.php). Libellés choisis par Samuel le 2026-10-04 */
    $li = '';
    foreach ( $items as $it ) {
      $li .= '<li><b>' . esc_html( $it[0] ) . '</b> ' . esc_html( $it[1] )
           . ' <a class="mt-faq-mini-lien" href="#' . esc_attr( mt_faq_ancre( $it[0] ) ) . '">En savoir plus</a></li>';
    }
    $nb   = $nb_autos + $nb_redac;
    $tout = $nb > count( $items ) ? 'Voir les ' . $nb . ' questions-réponses de notre foire aux questions' : 'Voir notre foire aux questions';
    /* Titre en <p> (test Jev : un titre d'encart en h2 coûte un peu d'ouverture) ; '' = sans titre */
    $tit = $titre !== '' ? '<p class="mt-faq-mini-titre"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>' . esc_html( $titre ) . '</p>' : '';
    return '<div class="mt-faq-mini">' . $tit . '<ul>' . $li . '</ul><p class="mt-faq-mini-tout"><a href="#partie-faq">' . esc_html( $tout ) . '</a></p></div>';
  }
}

if ( ! function_exists( 'mt_reponse_intro' ) ) {
  /* Réponse en tête de l'intro (disposition validée par Samuel le 2026-10-03, test Jev « F5 » : ouverture 0,85) :
     « {n°1 en lien} (note) est le meilleur {produit} en 2026, devant {n°2} (note) et {n°3} (note). Nous avons analysé
     {N} {produits} et retenu les {T} meilleur(e)s. » Mêmes données que l'encart « L'essentiel » (mt_verdict_ouverture). */
  function mt_reponse_intro( $ids, $type_plur, $type_sing, $llm, $n ) {
    $ids = array_values( array_filter( array_map( 'intval', (array) $ids ) ) );
    $t   = count( $ids );
    if ( $t === 0 ) { return ''; }
    $prods = mt_top_infos( $ids, 3 );
    if ( empty( $prods ) || $prods[0]['name'] === '' ) { return ''; }
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
    $note  = function ( $p ) { return $p['score'] > 0 ? ' (' . number_format( $p['score'], 1, ',', '' ) . '/10)' : ''; };
    $lien  = '<a href="' . esc_url( $url ) . '">' . esc_html( $prods[0]['name'] ) . '</a>' . $note( $prods[0] );
    if ( ! $pl && $sing !== '' ) { $out = $lien . ' est ' . ( $fem ? 'la meilleure ' : 'le meilleur ' ) . esc_html( $sing ) . ' en ' . date_i18n( 'Y' ); }
    else { $out = $lien . ' est notre n°1 parmi les ' . esc_html( $plur !== '' ? $plur : 'produits' ) . ' en ' . date_i18n( 'Y' ); }
    $autres = array();
    foreach ( array_slice( $prods, 1 ) as $p ) { if ( $p['name'] !== '' ) { $autres[] = esc_html( $p['name'] ) . $note( $p ); } }
    if ( count( $autres ) === 2 ) { $out .= ', devant ' . $autres[0] . ' et ' . $autres[1]; }
    elseif ( count( $autres ) === 1 ) { $out .= ', devant ' . $autres[0]; }
    $typ = esc_html( $plur !== '' ? $plur : 'produits' );
    $e   = ( $fem && $plur !== '' ) ? 'e' : '';
    $out .= '. ' . ( $n > $t ? 'Nous avons analysé ' . (int) $n . ' ' . $typ . ' et retenu les ' . $t . ' meilleur' . $e . 's.'
                             : 'Nous avons analysé et classé ' . max( (int) $n, $t ) . ' ' . $typ . '.' );
    return $out;
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

  <?php /* Ligne auteur et date : préparée ici, affichée sous le titre ou juste après « L'essentiel » ($MT_AUTEUR_SOUS_H1) */
  ob_start(); ?>
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
  <?php $mt_byline_html = ob_get_clean(); if ( $MT_AUTEUR_SOUS_H1 ) { echo $mt_byline_html; } ?>

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

  <?php if ( ! $MT_AUTEUR_SOUS_H1 ) { echo $mt_byline_html; } ?>

  <?php if ( $MT_VOS_QUESTIONS !== '' && $post_type === 'comparatif' ) {
      /* Encart « Vos questions » : affiché ici (après « L'essentiel »), après l'intro de la rédaction (plus bas dans ce bloc), ou confié au bloc
         du top 5 (résumé V1 / multi-resume V2) qui l'affiche juste avant */
      $mt_vq = mt_vos_questions( $this_id, $top_avis_ids ?? array(), $type_de_produit_au_singulier ?? '', $type_de_produit_au_pluriel ?? '', $lalalesmeilleur ?? '', $MT_TITRE_QUESTIONS );
      if ( $MT_VOS_QUESTIONS === 'avant_top5' ) { $GLOBALS['mt_vos_questions'] = $mt_vq; }
      elseif ( $MT_VOS_QUESTIONS === 'apres_intro' ) { $mt_vq_apres_intro = $mt_vq; }
      else { echo $mt_vq; }
  } ?>

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
  if ( $MT_REPONSE_INTRO && $post_type === 'comparatif' ) {
      /* La réponse forme son propre paragraphe, avant le texte de la rédaction (demande de Samuel, 2026-10-04) */
      $mt_rep = mt_reponse_intro( $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $type_de_produit_au_singulier ?? '', $lalalesmeilleur ?? '', $mt_n );
      if ( $mt_rep !== '' ) { $mt_intro_html = '<p class="mt-lede-reponse">' . $mt_rep . '</p>' . $mt_intro_html; }
  }
  echo $mt_intro_html;
  if ( $MT_SHOW_INTRO_RECO && ! $MT_VERDICT_SOUS_H1 && ! $MT_REPONSE_INTRO && $post_type === 'comparatif' ) {
      echo mt_intro_reco( $this_id, $top_avis_ids ?? array(), $type_de_produit_au_pluriel ?? '', $type_de_produit_au_singulier ?? '', $lalalesmeilleur ?? '' );
  } ?></div>

  <?php if ( ! empty( $mt_vq_apres_intro ) ) { echo $mt_vq_apres_intro; } /* encart « Vos questions » réglé sur 'apres_intro' */ ?>

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
