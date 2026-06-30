<?php
/* =====================================================================
   MEILLEURTEST — « Questions fréquentes » (FAQ)
   À coller dans UN SEUL élément CODE Bricks (Execute code = ON).
   Le CSS correspondant va dans l'onglet CSS du même élément (faq.css).

   Source des données (ACF) :
   - REPEATER mltv5_faq_comparatif   (1 ligne = 1 Q/R)
       . mltv5_faq_comparatif_question  (question)
       . mltv5_faq_comparatif_reponse   (réponse — WYSIWYG)
   Le contenu peut vivre sur la page courante OU sur le post lié dont l'ID est
   mis en cache dans `mltv5_cached_id_faq` -> lecture robuste avec repli.

   STANDARDS WEB :
   - Accordéon natif <details>/<summary> -> accessible (clavier, ARIA implicite),
     sans JS (rien à re-signer côté interaction).
   - Données structurées schema.org **FAQPage** en JSON-LD (format recommandé
     par Google). Réponses assainies (wp_kses, balises autorisées par Google) ;
     encodage durci (tags/ampersands échappés) pour un <script> sûr.
   Section -> `.contenu-principal` (jauge de lecture) + ancre `partie-faq`.
   ===================================================================== */

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
if ( ! function_exists( 'mt_faq_read' ) ) {
  function mt_faq_read( $pid ) {
    $rows = function_exists( 'get_field' ) ? get_field( 'mltv5_faq_comparatif', $pid ) : null;
    return is_array( $rows ) ? $rows : array();
  }
}

$page_id = get_the_ID();

$src_id = $page_id;
$rows   = mt_faq_read( $src_id );
if ( empty( $rows ) ) {
  $cached = (int) get_field( 'mltv5_cached_id_faq', $page_id );
  if ( $cached && $cached !== $page_id ) {
    $alt = mt_faq_read( $cached );
    if ( ! empty( $alt ) ) { $src_id = $cached; $rows = $alt; }
  }
}

/* Balises autorisées par Google dans le texte de réponse FAQPage. */
$faq_schema_tags = array(
  'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'h5' => array(), 'h6' => array(),
  'br' => array(), 'ol' => array(), 'ul' => array(), 'li' => array(),
  'a'  => array( 'href' => array() ), 'p' => array(), 'div' => array(),
  'b'  => array(), 'strong' => array(), 'i' => array(), 'em' => array(),
);

$faqs = array();
foreach ( $rows as $r ) {
  $q = trim( wp_strip_all_tags( (string) ( isset( $r['mltv5_faq_comparatif_question'] ) ? $r['mltv5_faq_comparatif_question'] : '' ) ) );
  $a = (string) ( isset( $r['mltv5_faq_comparatif_reponse'] ) ? $r['mltv5_faq_comparatif_reponse'] : '' );
  if ( $q === '' && trim( $a ) === '' ) { continue; }
  $faqs[] = array(
    'q'        => $q,
    'a'        => mt_guide_rich( $a ),                        // affichage (riche)
    'a_schema' => trim( wp_kses( $a, $faq_schema_tags ) ),     // JSON-LD (assaini)
  );
}

/* Rien à afficher -> diagnostic admin (builder), invisible pour les visiteurs. */
if ( empty( $faqs ) ) {
  if ( function_exists( 'current_user_can' ) && current_user_can( 'edit_posts' ) ) {
    $rr = function_exists( 'get_field' ) ? get_field( 'mltv5_faq_comparatif', $page_id ) : null;
    echo '<div style="border:1px dashed #c0392b;border-radius:8px;padding:12px 14px;margin:8px 0;'
       . 'font:13px/1.5 ui-monospace,Menlo,monospace;color:#7b241c;background:#fdecea">'
       . '<strong>mt-faq — diagnostic (admin only)</strong> : aucune question trouvée.<br>'
       . 'get_the_ID() = ' . (int) $page_id . ' &middot; post_type = ' . esc_html( (string) get_post_type( $page_id ) ) . '<br>'
       . 'mltv5_cached_id_faq = ' . esc_html( (string) get_field( 'mltv5_cached_id_faq', $page_id ) ) . '<br>'
       . 'repeater mltv5_faq_comparatif = ' . esc_html( gettype( $rr ) )
       . ( is_array( $rr ) ? ' (count=' . count( $rr ) . ')' : '' )
       . '</div>';
  }
  return;
}

/* JSON-LD FAQPage : uniquement les Q/R complètes (Question SANS acceptedAnswer
   = invalide pour Google). */
$entities = array();
foreach ( $faqs as $f ) {
  if ( $f['q'] === '' || $f['a_schema'] === '' ) { continue; }
  $entities[] = array(
    '@type'          => 'Question',
    'name'           => $f['q'],
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
  <p class="mt-faq-intro">Voici les questions les plus fr&eacute;quemment pos&eacute;es. Vous avez une question sans r&eacute;ponse&nbsp;? Posez-la &agrave; la communaut&eacute;.</p>

  <div class="mt-faq-list">
    <?php foreach ( $faqs as $i => $f ) : ?>
      <?php if ( $f['a'] !== '' ) : ?>
      <details class="mt-faq-item"<?php echo $i === 0 ? ' open' : ''; ?>>
        <summary class="mt-faq-q">
          <span class="mt-faq-qh"><?php echo esc_html( $f['q'] ); ?></span>
          <span class="mt-faq-icon" aria-hidden="true"></span>
        </summary>
        <div class="mt-faq-a"><?php echo $f['a']; ?></div>
      </details>
      <?php else : ?>
      <div class="mt-faq-item mt-faq-static">
        <span class="mt-faq-qh"><?php echo esc_html( $f['q'] ); ?></span>
      </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if ( $jsonld !== '' ) : ?>
  <script type="application/ld+json"><?php echo $jsonld; ?></script>
  <?php endif; ?>
</section>
