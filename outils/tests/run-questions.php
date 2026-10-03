<?php
/* Encart « Vos questions » (hero gauche, réglage $MT_VOS_QUESTIONS activé dans une copie).
   Usage : php run-questions.php ; POS=apres_intro (après l'intro) ou POS=avant_top5 (juste avant le top 5) ; V2=1 pour les blocs multi-* ;
   PRIX=0 sans prix ni note clients (question « Pourquoi faire confiance ») ; FAQ=0 sans questions de la rédaction.
   PAGES=<dossier> : applique la règle de la phrase aux FAQ de pages HTML enregistrées (<dossier>/<page>/G0.html)
   et écrit out/vos-questions-pages.txt. */
require __DIR__ . '/wp-stubs.php';
if ( ! function_exists( 'remove_accents' ) ) { function remove_accents( $t ) { return iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $t ); } }
$pos = getenv( 'POS' ) ?: 'apres_intro';
$v2  = (bool) getenv( 'V2' );
$GLOBALS['TV'][100]['lalalesmeilleur'] = 'le meilleur';
$GLOBALS['TV'][100]['template_description'] = 0;
$GLOBALS['TV'][100]['author'] = 'Irfann';
unset( $GLOBALS['META'][100]['mltv5_sous_comparatifs'] );
$GLOBALS['ACF'][100]['mltv5_criteres_de_choix'] = array_map( function ( $t ) { return array( 'mltv5_critere_de_choix' => $t ); }, array( 'La puissance frigorifique (exprimée en BTU)', 'Le niveau sonore (exprimé en dB)', 'La consommation énergétique', 'Les options de confort', 'Les différents filtres' ) );
if ( getenv( 'PRIX' ) === '0' ) {
  foreach ( range( 1, 14 ) as $i ) { $GLOBALS['ACF'][ $i ]['mltv5_prix_indicatif'] = ''; $GLOBALS['ACF'][ $i ]['mltv5_score_avis_clients'] = ''; }
}
$faq = array(
  'Quel est le meilleur climatiseur mobile ?' => '<p>Le Midea arrive en tête.</p>',
  'Un climatiseur mobile peut-il rafraîchir plus d’une pièce ?' => '<p>Le climatiseur mobile a été conçu pour rafraîchir et climatiser une seule pièce uniquement.</p>',
  'Est-il possible de rallonger le tuyau d’un climatiseur mobile ?' => '<p>Non&nbsp;! Le fait de rallonger le tuyau d’évacuation entraîne des risques importants tels que la formation de condensation ou de moisissure dans le conduit.</p>',
  'En quoi consiste le contrôle parental ?' => '<p>Vous souhaitez garder le contrôle et empêcher l’accès à certaines fonctionnalités ? Souscrivez à une offre incluant un contrôle parental. Vous pourrez ainsi limiter le temps d’utilisation d’Internet, empêcher l’accès à certains numéros…</p>',
  'Comment entretenir correctement mon matelas ?' => "<ol><li>1. Aérez votre matelas régulièrement en le retournant et en le soulevant de temps en temps.</li><li>2. Nettoyez votre matelas une fois par mois avec un aspirateur.</li></ol>",
);
if ( getenv( 'FAQ' ) !== '0' ) {
  $GLOBALS['ACF'][100]['mltv5_faq_comparatif'] = array();
  foreach ( $faq as $q => $a ) { $GLOBALS['ACF'][100]['mltv5_faq_comparatif'][] = array( 'mltv5_faq_comparatif_question' => $q, 'mltv5_faq_comparatif_reponse' => $a ); }
}
@mkdir( __DIR__ . '/out' );
$hero = MT_REPO . '/php-css/' . ( $v2 ? 'v2/multi-hero-gauche.code.php' : 'hero-gauche.code.php' );
$res  = MT_REPO . '/php-css/' . ( $v2 ? 'v2/multi-resume.code.php' : 'top5-resume.code.php' );
$src  = file_get_contents( $hero );
$src  = preg_replace( "/\\\$MT_VOS_QUESTIONS\s*=\s*'[^']*';/", "\$MT_VOS_QUESTIONS = '" . $pos . "';", $src, 1, $nb );
if ( $nb !== 1 ) { exit( "réglage \$MT_VOS_QUESTIONS introuvable\n" ); }
file_put_contents( __DIR__ . '/out/hero-questions.php', $src );
ob_start(); ( function() { include __DIR__ . '/out/hero-questions.php'; } )(); $h = ob_get_clean();
ob_start(); ( function() use ( $res ) { include $res; } )(); $r = ob_get_clean();

echo 'blocs   : ', $v2 ? 'V2 (multi-hero-gauche + multi-resume)' : 'V1 (hero-gauche + top5-resume)', ', position ', $pos, "\n";
$dans_hero = strpos( $h, 'mt-faq-mini' );
$dans_res  = strpos( $r, 'mt-faq-mini' );
if ( $dans_hero === false && $dans_res === false ) {
  echo "place   : aucune (moins de 3 questions : pas d'encart)\n";
  $bloc = '';
} elseif ( $pos === 'apres_intro' ) {
  $ok = $dans_hero !== false && strpos( $h, 'class="mt-lede"' ) < $dans_hero && $dans_hero < strpos( $h, 'mt-photo' ) && $dans_res === false;
  echo 'place   : ', $ok ? 'intro de la rédaction > Vos questions > photo (absent du top 5)' : 'PLACE INATTENDUE', "\n";
  $bloc = $h;
} elseif ( $pos === 'sous_reponse' ) {
  $ok = $dans_hero !== false && strpos( $h, 'mt-byline' ) < $dans_hero && $dans_hero < strpos( $h, 'class="mt-lede"' ) && $dans_res === false;
  echo 'place   : ', $ok ? 'ligne auteur > encart > intro (absent du top 5)' : 'PLACE INATTENDUE', "\n";
  $bloc = $h;
} else {
  $ok = $dans_hero === false && $dans_res !== false && $dans_res < strpos( $r, '<div class="mt-top5"' ) && ! isset( $GLOBALS['mt_vos_questions'] );
  echo 'place   : ', $ok ? 'juste avant le top 5 (absent du hero, variable vidée)' : 'PLACE INATTENDUE', "\n";
  $bloc = $r;
}
if ( preg_match( '#<div class="mt-faq-mini">.*?</div>#s', $bloc, $m ) ) {
  libxml_use_internal_errors( true );
  $d = new DOMDocument(); $d->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $m[0] . '</body></html>' );
  $mt_err = array_filter( libxml_get_errors(), function ( $e ) { return $e->level > LIBXML_ERR_WARNING && strpos( $e->message, 'Tag ' ) === false; } );
  echo 'HTML    : ', count( $mt_err ), " erreur(s) (balises SVG/HTML5 ignorées, comme run-page)", $mt_err ? ' : ' . trim( reset( $mt_err )->message ) : '', "\n";
  preg_match_all( '#<li><b>(.*?)</b> (.*?)</li>#s', $m[0], $li, PREG_SET_ORDER );
  foreach ( $li as $x ) { echo '  • ', html_entity_decode( $x[1], ENT_QUOTES, 'UTF-8' ), "\n      ", html_entity_decode( strip_tags( $x[2] ), ENT_QUOTES, 'UTF-8' ), "\n"; }
  echo 'lien du bas : ', preg_match( '#<p class="mt-faq-mini-tout"><a href="([^"]+)">([^<]+)</a>#', $m[0], $tout ) ? $tout[2] . ' → ' . $tout[1] : 'ABSENT', "\n";
  /* Chaque « En savoir plus » doit mener à une question de la FAQ (même page, bloc faq rendu ensuite) */
  if ( ! function_exists( 'get_the_modified_date' ) ) { function get_the_modified_date( $f = '', $id = 0 ) { return 'octobre 2026'; } }
  if ( ! function_exists( 'wpautop' ) ) { function wpautop( $s ) { return '<p>' . $s . '</p>'; } }
  if ( ! function_exists( 'wp_kses' ) ) { function wp_kses( $s, $t ) { return $s; } }
  ob_start(); ( function() { include MT_REPO . '/php-css/faq.code.php'; } )(); $faq = ob_get_clean();
  preg_match_all( '#class="mt-faq-mini-lien" href="\#([^"]+)"#', $m[0], $liens );
  preg_match_all( '#class="mt-faq-item[^"]*" id="([^"]+)"#', $faq, $ids_faq );
  $manquants = array_diff( $liens[1], $ids_faq[1] );
  echo 'liens vers la FAQ : ', count( $liens[1] ), ' | trouvés dans la FAQ : ', count( $liens[1] ) - count( $manquants ), ( $manquants ? ' | MANQUANTS : ' . implode( ', ', $manquants ) : '' ), ' | questions dans la FAQ : ', count( $ids_faq[1] ), "\n";
} else {
  echo "encart  : absent\n";
}

/* Règle de la phrase sur les FAQ de pages enregistrées (questions de la rédaction seulement) */
if ( getenv( 'PAGES' ) ) {
  $saute = '/^(Quel(le)?s? (est|sont) (le|la|les) meilleur|Quelles sont les meilleures marques|Quel produit a les meilleurs avis|Comment avons-nous établi|Quel budget prévoir|Comment bien choisir|Pourquoi faire confiance)/u';
  $out = '';
  foreach ( glob( rtrim( getenv( 'PAGES' ), '/\\' ) . '/*/G0.html' ) as $g ) {
    $s = file_get_contents( $g );
    preg_match_all( '#<summary class="mt-faq-q">\s*<span class="mt-faq-qh">(.*?)</span>.*?</summary>\s*<div class="mt-faq-a">(.*?)</div>\s*</details>#s', $s, $qa, PREG_SET_ORDER );
    $out .= "##### " . basename( dirname( $g ) ) . "\n";
    foreach ( $qa as $x ) {
      $q = trim( html_entity_decode( strip_tags( $x[1] ), ENT_QUOTES, 'UTF-8' ) );
      if ( preg_match( $saute, $q ) ) { continue; }
      $out .= '• ' . $q . "\n    " . mt_vq_phrase( $x[2] ) . "\n";
    }
  }
  file_put_contents( __DIR__ . '/out/vos-questions-pages.txt', $out );
  echo "\nrègle de la phrase : out/vos-questions-pages.txt (", substr_count( $out, '• ' ), " réponses)\n";
}
