<?php
/* =====================================================================
   MEILLEURTEST — Shortcodes éditoriaux « avis » (style top5-tests)
   ---------------------------------------------------------------------
   [cequonaime]     -> <h4 class="cequonaime">Ce qui nous a convaincus</h4>
   [cequonaimepas]  -> <h4 class="cequonaimepas">Les défauts qu'on pardonne (ou pas)</h4>

   Mise en forme = celle des intertitres du template « top5-tests »
   (cf. php-css/top5-tests.css : .ed-a-body h4 / h4.warn) : Inter 13px 700
   MAJUSCULES, filet 1px à droite ; « convaincus » en primary sombre,
   « défauts » en rouge sombre.

   (L'encart « À qui s'adresse ce {type} ? » n'est PAS un shortcode : il est
   rendu automatiquement par le template top5-tests si mltv5_pour_qui est rempli.)

   ⚠️ CE N'EST PAS un bloc Code Bricks. À coller dans un snippet PHP
   (extension « Code Snippets », ou functions.php du thème enfant).
   Usage dans le contenu WYSIWYG (chaque shortcode sur sa propre ligne) :
       [cequonaime]  ...paragraphes...  [cequonaimepas]

   100 % tokens Advanced Themer (var(--at-*)) -> bascule mode nuit auto.
   ===================================================================== */

if ( ! function_exists( 'mt_cequonaime_styles' ) ) {
  /* CSS imprimé UNE fois dans <head> (statique, minuscule, pas de FOUC,
     et pas d'enrobage <p> par wpautop comme le ferait un <style> inline). */
  function mt_cequonaime_styles() {
    /* Classe doublée (h4.cequonaime.cequonaime) = spécificité (0,2,1) pour
       PASSER DEVANT « .ed-a-body h4 » (0,2,0... = 0,1,1) du template top5-tests,
       sinon l'ordre de chargement l'emporte et « défauts » hérite du bleu de
       base au lieu du rouge. Reste valable hors de .ed-a-body. */
    echo '<style id="mt-cequonaime-css">'
       /* --- intertitres [cequonaime] / [cequonaimepas] --- */
       . 'h4.cequonaime.cequonaime,h4.cequonaimepas.cequonaimepas{'
       .   'font-family:"Inter",sans-serif!important;'
       .   'font-size:13px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;'
       .   'margin:38px 0 14px;display:flex;align-items:center;gap:12px;'
       .   'color:var(--at-primary)'
       . '}'
       . 'h4.cequonaime.cequonaime::after,h4.cequonaimepas.cequonaimepas::after{'
       .   'content:"";flex:1;height:1px;background:var(--at-grey-l-3)'
       . '}'
       . 'h4.cequonaimepas.cequonaimepas{color:var(--at-danger)}'
       . '</style>';
  }
  add_action( 'wp_head', 'mt_cequonaime_styles', 20 );
}

if ( ! function_exists( 'mt_sc_cequonaime' ) ) {
  function mt_sc_cequonaime() {
    return '<h4 class="cequonaime">Ce qui nous a convaincus</h4>';
  }
  add_shortcode( 'cequonaime', 'mt_sc_cequonaime' );
}

if ( ! function_exists( 'mt_sc_cequonaimepas' ) ) {
  function mt_sc_cequonaimepas() {
    return '<h4 class="cequonaimepas">Les d&eacute;fauts qu\'on pardonne (ou pas)</h4>';
  }
  add_shortcode( 'cequonaimepas', 'mt_sc_cequonaimepas' );
}

if ( ! function_exists( 'mt_sc_pourqui' ) ) {
  /* [pourqui] : encart « À qui s'adresse ce {type} ? » construit dynamiquement,
     rendu uniquement si le champ ACF mltv5_pour_qui de la page courante est rempli. */
  function mt_sc_pourqui() {
    $pid = get_the_ID();
    if ( ! $pid && function_exists( 'get_queried_object_id' ) ) { $pid = (int) get_queried_object_id(); }
    if ( ! $pid ) { return ''; }                       // pas de contexte de post -> rien

    $forwho = function_exists( 'get_field' ) ? get_field( 'mltv5_pour_qui', $pid ) : '';
    $forwho = trim( (string) $forwho );
    if ( $forwho === '' ) { return ''; }               // champ vide -> encart masqué

    /* Sous-titre dynamique : « À qui s'adresse ce {type au singulier} ? ». */
    $type_sing = 'produit';
    if ( function_exists( 'get_all_template_variables' ) ) {
      $tv = get_all_template_variables( $pid );
      if ( is_array( $tv ) && ! empty( $tv['type_de_produit_au_singulier'] ) ) {
        $type_sing = trim( (string) $tv['type_de_produit_au_singulier'] );
      }
    }

    return '<div class="mt-forwho">'
         . '<h5>&Agrave; qui s\'adresse ce ' . esc_html( $type_sing ) . '&nbsp;?</h5>'
         . '<p>' . nl2br( esc_html( $forwho ) ) . '</p>'
         . '</div>';
  }
  add_shortcode( 'pourqui', 'mt_sc_pourqui' );
}
