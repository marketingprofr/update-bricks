<?php
/* =====================================================================
   MEILLEURTEST — Top 5 dynamique (Bricks Builder + ACF)
   ---------------------------------------------------------------------
   À coller dans un élément CODE de Bricks (PHP activé : "Execute code").

   SOURCE DES DONNÉES :
   Un champ REPEATER ACF posé sur la page courante, où CHAQUE LIGNE = 1
   produit du classement (ordre des lignes = ordre du Top 5).
   Renommez simplement les chaînes 'acf_xxx' ci-dessous par les vrais
   noms (slugs) de vos champs/sous-champs ACF. Aucune autre modif requise.
   ===================================================================== */


/* =====================================================================
   1) NOMS DES CHAMPS ACF  —  À PERSONNALISER
   ---------------------------------------------------------------------
   Chaque variable contient le NOM (slug) du champ ACF à lire.
   Remplacez la valeur de droite par le slug de votre champ.
   ===================================================================== */

/* --- Le champ REPEATER qui contient la liste des produits --- */
$repeater_produits   = 'acf_produits';            // champ Repeater (1 ligne = 1 produit)

/* --- Identité du produit (sous-champs du repeater) --- */
$product_rank        = 'acf_product_rank';         // rang éditorial : 1,2,3...  (sert au tri "Notre sélection")
$product_rank_label  = 'acf_product_rank_label';   // libellé court colonne rang : "Top", "Premium", "Petit budget"...
$product_tagline     = 'acf_product_tagline';      // accroche : "La meilleure tablette haut de gamme"
$product_brand       = 'acf_product_brand';        // marque (eyebrow) : "Apple"
$product_name        = 'acf_product_name';         // nom produit : "iPad Pro 13 (M4)"
$product_image       = 'acf_product_image';        // image produit (champ Image -> renvoie l'URL ou un tableau)

/* --- Résumé & verdict --- */
$product_summary     = 'acf_product_summary';      // résumé court (carte classement)

/* --- Points positifs / négatifs --- */
$product_pro_1       = 'acf_product_pro_1';
$product_pro_2       = 'acf_product_pro_2';
$product_pro_3       = 'acf_product_pro_3';
$product_con_1       = 'acf_product_con_1';
$product_con_2       = 'acf_product_con_2';

/* --- Note & avis clients --- */
$product_score       = 'acf_product_score';        // note rédac : "9,4"  (peut contenir une virgule)
$product_score_tag   = 'acf_product_score_tag';    // libellé : "Excellent", "Très bon", "Bon"
$product_rating       = 'acf_product_rating';        // note clients sur 5 : "4,5" (sert au tri "Avis des clients" + étoiles)
$product_reviews_count = 'acf_product_reviews_count'; // nombre d'avis clients : "1248" (affiché "1 248 avis")

/* --- Prix & offre principale (carte classement) --- */
$product_price       = 'acf_product_price';        // prix affiché : "1 620 €"
$product_price_num   = 'acf_product_price_num';     // prix NUMÉRIQUE pur : 1620 (sert au tri "Prix")
$product_merchant    = 'acf_product_merchant';      // marchand principal : "Amazon"
$product_url_1       = 'acf_product_url_1';         // URL offre n°1 (bouton "Voir l'offre" + 1re offre fiche)

/* --- Avis détaillé (fiche) --- */
$product_review_title = 'acf_product_review_title'; // titre de l'avis (h4)
$product_review_body  = 'acf_product_review_body';  // corps de l'avis (Éditeur WYSIWYG)
$product_review_quote = 'acf_product_review_quote'; // citation mise en exergue

/* --- Jauges de notation (fiche) — 5 critères label + valeur /10 --- */
$product_gauges = array(
  array( 'label' => 'acf_gauge_1_label', 'value' => 'acf_gauge_1_value' ),
  array( 'label' => 'acf_gauge_2_label', 'value' => 'acf_gauge_2_value' ),
  array( 'label' => 'acf_gauge_3_label', 'value' => 'acf_gauge_3_value' ),
  array( 'label' => 'acf_gauge_4_label', 'value' => 'acf_gauge_4_value' ),
  array( 'label' => 'acf_gauge_5_label', 'value' => 'acf_gauge_5_value' ),
);

/* --- Offres marchandes (fiche) — 2 offres : marchand + prix + URL --- */
$product_offers = array(
  array( 'merchant' => 'acf_offer_1_merchant', 'price' => 'acf_offer_1_price', 'url' => 'acf_offer_1_url' ),
  array( 'merchant' => 'acf_offer_2_merchant', 'price' => 'acf_offer_2_price', 'url' => 'acf_offer_2_url' ),
);

/* --- Caractéristiques clés (fiche) — 5 lignes clé + valeur --- */
$product_specs = array(
  array( 'key' => 'acf_spec_1_key', 'value' => 'acf_spec_1_value' ),
  array( 'key' => 'acf_spec_2_key', 'value' => 'acf_spec_2_value' ),
  array( 'key' => 'acf_spec_3_key', 'value' => 'acf_spec_3_value' ),
  array( 'key' => 'acf_spec_4_key', 'value' => 'acf_spec_4_value' ),
  array( 'key' => 'acf_spec_5_key', 'value' => 'acf_spec_5_value' ),
);


/* =====================================================================
   2) RÉCUPÉRATION DES DONNÉES + HELPERS
   ===================================================================== */

/* On lit le repeater en une fois -> tableau de lignes (réutilisé 2x). */
$mt_products = function_exists( 'get_field' ) ? get_field( $repeater_produits ) : array();
if ( empty( $mt_products ) || ! is_array( $mt_products ) ) {
  $mt_products = array();
}

if ( ! function_exists( 'mt_get' ) ) {
  /* Lit une valeur de ligne repeater par son slug, avec valeur par défaut. */
  function mt_get( $row, $key, $default = '' ) {
    return ( is_array( $row ) && isset( $row[ $key ] ) && $row[ $key ] !== '' ) ? $row[ $key ] : $default;
  }
}
if ( ! function_exists( 'mt_num' ) ) {
  /* Convertit "9,8" / "1 620" en float exploitable (virgule -> point). */
  function mt_num( $val ) {
    $val = str_replace( array( ' ', "\xc2\xa0" ), '', (string) $val ); // espaces + nbsp
    $val = str_replace( ',', '.', $val );
    return is_numeric( $val ) ? (float) $val : 0.0;
  }
}
if ( ! function_exists( 'mt_stars' ) ) {
  /* Génère 5 étoiles (pleines/vides) à partir d'une note sur 5. */
  function mt_stars( $rating ) {
    $full = (int) round( mt_num( $rating ) );
    $full = max( 0, min( 5, $full ) );
    return str_repeat( '&#9733;', $full ) . str_repeat( '&#9734;', 5 - $full );
  }
}
if ( ! function_exists( 'mt_img_url' ) ) {
  /* Récupère une URL d'image quel que soit le format de retour ACF. */
  function mt_img_url( $val ) {
    if ( is_array( $val ) ) {
      return isset( $val['url'] ) ? $val['url'] : '';
    }
    if ( is_numeric( $val ) ) {
      $src = wp_get_attachment_image_src( (int) $val, 'large' );
      return $src ? $src[0] : '';
    }
    return (string) $val;
  }
}
if ( ! function_exists( 'mt_reviews_label' ) ) {
  /* "1248" -> "1 248 avis" (vide si 0 ou non renseigné). */
  function mt_reviews_label( $count ) {
    $n = (int) preg_replace( '/[^0-9]/', '', (string) $count );
    if ( $n <= 0 ) { return ''; }
    return number_format( $n, 0, ',', ' ' ) . ' avis';
  }
}
?>
<!-- ============================================================
     MEILLEURTEST — Top 5 complet (dynamique ACF)
     CSS scope (.mt-top5 / .mt-fiche / .mt-reviews) — aucun conflit thème.
     ============================================================ -->
<style>

  .mt-top5 {
    --ink: #14181d;
    --ink-2: #2a3038;
    --muted: #6b7480;
    --muted-2: #9aa3ad;
    --line: #e8eaed;
    --line-2: #f1f3f5;
    --bg: #ffffff;
    --bg-2: #fafbfc;
    --bg-3: #f5f6f8;
    --accent: #0f6b54;
    --accent-soft: #e5f1ec;
    --gold: #b48a3a;
    --warn: #b54848;

    font-family: "Inter", "Helvetica Neue", system-ui, sans-serif;
    color: var(--ink);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }
  .mt-top5 *, .mt-top5 *::before, .mt-top5 *::after { box-sizing: border-box; }
  .mt-top5 a { color: inherit; text-decoration: none; }
  .mt-top5 button { font-family: inherit; }

  /* ===== Barre de tri ===== */
  .mt-top5 .t5-bar {
    display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
    padding: 14px 18px; margin-bottom: 22px;
    background: var(--bg-2); border: 1px solid var(--line); border-radius: 14px;
    position: sticky; top: 12px; z-index: 20;
    box-shadow: 0 1px 2px rgba(20,24,29,.03);
  }
  .mt-top5 .t5-bar .lbl { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); margin-right: 2px; }
  .mt-top5 .t5-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
  .mt-top5 .t5-tab {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 9px 16px; border-radius: 999px; cursor: pointer;
    font-size: 13.5px; font-weight: 600; color: var(--ink-2);
    background: white; border: 1px solid var(--line);
    transition: all .15s;
  }
  .mt-top5 .t5-tab .ico { font-size: 13px; opacity: .55; transition: opacity .15s; }
  .mt-top5 .t5-tab:hover { border-color: var(--muted-2); }
  .mt-top5 .t5-tab[aria-selected="true"] { background: var(--ink); border-color: var(--ink); color: white; }
  .mt-top5 .t5-tab[aria-selected="true"] .ico { opacity: 1; }
  .mt-top5 .t5-howto {
    margin-left: auto; display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; font-weight: 600; color: var(--accent);
    padding: 8px 4px; border-bottom: 1px solid transparent; transition: border-color .15s;
  }
  .mt-top5 .t5-howto:hover { border-color: var(--accent); }
  .mt-top5 .t5-howto .arr { transition: transform .15s; }
  .mt-top5 .t5-howto:hover .arr { transform: translateX(3px); }

  /* ===== Liste ===== */
  .mt-top5 .t5-list { display: flex; flex-direction: column; gap: 16px; }

  .mt-top5 .t5-card {
    display: grid; grid-template-columns: 96px 200px 1fr 248px;
    background: var(--bg); border: 1px solid var(--line); border-radius: 16px;
    overflow: hidden; transition: box-shadow .2s, border-color .2s;
    box-shadow: 0 1px 2px rgba(20,24,29,.03);
    min-width: 0;
  }
  .mt-top5 .t5-card:hover { box-shadow: 0 1px 2px rgba(20,24,29,.04), 0 14px 40px -20px rgba(20,24,29,.2); border-color: #dcdfe3; }

  /* col 1 : rang */
  .mt-top5 .t5-rankcol {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
    background: var(--bg-2); border-right: 1px solid var(--line-2); padding: 18px 10px;
  }
  .mt-top5 .t5-rankcol .r-num { font-family: ui-monospace, monospace; font-size: 30px; font-weight: 700; letter-spacing: -0.04em; color: var(--ink); line-height: 1; }
  .mt-top5 .t5-rankcol .r-lbl { font-size: 9.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted-2); }
  .mt-top5 .t5-card:first-child .t5-rankcol { background: var(--accent-soft); border-right-color: #d4e6df; }
  .mt-top5 .t5-card:first-child .t5-rankcol .r-num { color: var(--accent); }
  .mt-top5 .t5-card:first-child .t5-rankcol .r-lbl { color: var(--accent); }

  /* col 2 : image */
  .mt-top5 .t5-media { position: relative; padding: 18px; display: grid; place-items: center; border-right: 1px solid var(--line-2); }
  .mt-top5 .ph { position: relative; width: 100%; aspect-ratio: 1; background: var(--bg-3); border-radius: 10px; display: grid; place-items: center; overflow: hidden; }
  .mt-top5 .ph::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(45deg, transparent 0 12px, rgba(0,0,0,0.028) 12px 13px); }
  .mt-top5 .ph img { position: relative; z-index: 1; width: 100%; height: 100%; object-fit: contain; }
  .mt-top5 .ph-cap { position: relative; z-index: 1; font-family: ui-monospace, monospace; font-size: 10px; color: var(--muted-2); text-align: center; padding: 0 8px; }
  .mt-top5 .t5-award { position: absolute; top: 10px; left: 10px; z-index: 2; font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--accent); background: white; border: 1px solid #d4e6df; padding: 4px 9px; border-radius: 999px; }

  /* col 3 : contenu */
  .mt-top5 .t5-body { padding: 20px 24px; min-width: 0; }
  .mt-top5 .t5-eyebrow { font-size: 12px; font-weight: 500; color: var(--muted); text-transform: uppercase; letter-spacing: 0.03em; margin-bottom: 3px; }
  .mt-top5 .t5-name { font-size: 21px; font-weight: 600; letter-spacing: -0.02em; line-height: 1.15; margin: 0 0 7px; }
  .mt-top5 .t5-tagline { font-size: 12.5px; font-weight: 600; color: var(--accent); margin-bottom: 10px; }
  .mt-top5 .t5-summary { font-size: 14px; line-height: 1.6; color: var(--ink-2); margin: 0 0 14px; }
  .mt-top5 .t5-pc { display: flex; flex-wrap: wrap; gap: 6px; }
  .mt-top5 .t5-chip { font-size: 12px; padding: 5px 11px 5px 24px; border-radius: 999px; position: relative; background: var(--bg-3); color: var(--ink-2); }
  .mt-top5 .t5-chip.pro { background: var(--accent-soft); }
  .mt-top5 .t5-chip.con { background: #f7e6e6; }
  .mt-top5 .t5-chip::before { content: ""; position: absolute; left: 9px; top: 50%; width: 9px; height: 9px; border-radius: 50%; transform: translateY(-50%); }
  .mt-top5 .t5-chip.pro::before { background: var(--accent); }
  .mt-top5 .t5-chip.con::before { background: var(--warn); }
  .mt-top5 .t5-chip.pro::after { content: ""; position: absolute; left: 11.5px; top: calc(50% - 1px); width: 4px; height: 2px; border-left: 1.3px solid white; border-bottom: 1.3px solid white; transform: rotate(-45deg); }
  .mt-top5 .t5-chip.con::after { content: ""; position: absolute; left: 11px; top: 50%; width: 5px; height: 1.4px; background: white; transform: translateY(-50%); }

  /* col 4 : notes + offre */
  .mt-top5 .t5-aside { background: var(--bg-2); border-left: 1px solid var(--line-2); padding: 20px 22px; display: flex; flex-direction: column; }

  .mt-top5 .t5-ratings { padding-bottom: 14px; border-bottom: 1px solid var(--line); margin-bottom: 13px; }
  .mt-top5 .t5-ratings .r-lbl { display: block; font-size: 9.5px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--muted-2); margin-bottom: 5px; }

  /* notre note */
  .mt-top5 .t5-r-ed .r-line { display: flex; align-items: center; gap: 10px; }
  .mt-top5 .t5-r-ed .n { font-size: 32px; font-weight: 700; color: var(--accent); letter-spacing: -0.03em; line-height: 1; }
  .mt-top5 .t5-r-ed .n small { font-size: 13px; color: var(--muted); font-weight: 500; }
  .mt-top5 .t5-r-ed .tag { font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: var(--accent); background: var(--accent-soft); padding: 3px 9px; border-radius: 999px; }

  /* avis clients */
  .mt-top5 .t5-r-cust { margin-top: 13px; padding-top: 12px; border-top: 1px dashed var(--line); }
  .mt-top5 .t5-r-cust .r-line { display: flex; align-items: baseline; gap: 7px; flex-wrap: wrap; }
  .mt-top5 .t5-r-cust .stars { color: var(--gold); letter-spacing: 1px; font-size: 13px; line-height: 1; }
  .mt-top5 .t5-r-cust .cust-val { font-size: 14px; font-weight: 700; color: var(--ink); }
  .mt-top5 .t5-r-cust .cust-count { font-size: 11.5px; color: var(--muted); }

  .mt-top5 .t5-price { margin: auto 0 12px; }
  .mt-top5 .t5-price .from { font-size: 11px; color: var(--muted); }
  .mt-top5 .t5-price .amt { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; }
  .mt-top5 .t5-price .merch { font-size: 11.5px; color: var(--muted); }
  .mt-top5 .t5-cta {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 12px 16px; border-radius: 10px; font-size: 13.5px; font-weight: 600;
    background: var(--ink); color: white; border: 1px solid var(--ink); transition: all .15s;
  }
  .mt-top5 .t5-cta:hover { background: var(--accent); border-color: var(--accent); }
  .mt-top5 .t5-cta .arr { transition: transform .15s; }
  .mt-top5 .t5-cta:hover .arr { transform: translateX(3px); }

  /* ===== Responsive ===== */
  @media (max-width: 1080px) {
    .mt-top5 .t5-card { grid-template-columns: 80px 1fr 232px; }
    .mt-top5 .t5-media { display: none; }
  }
  @media (max-width: 820px) {
    .mt-top5 .t5-card { grid-template-columns: 64px 1fr; }
    .mt-top5 .t5-aside { grid-column: 1 / -1; border-left: 0; border-top: 1px solid var(--line-2); flex-direction: row; align-items: center; flex-wrap: wrap; gap: 16px; padding: 16px 22px; }
    .mt-top5 .t5-ratings { padding-bottom: 0; border-bottom: 0; margin-bottom: 0; }
    .mt-top5 .t5-price { margin: 0; }
    .mt-top5 .t5-cta { margin-left: auto; padding: 11px 20px; }
  }
  @media (max-width: 600px) {
    .mt-top5 .t5-bar { padding: 12px 13px; gap: 10px; }
    .mt-top5 .t5-bar .lbl { width: 100%; margin-bottom: 2px; }
    .mt-top5 .t5-howto { margin-left: 0; width: 100%; }
    .mt-top5 .t5-card { grid-template-columns: 52px 1fr; }
    .mt-top5 .t5-rankcol .r-num { font-size: 24px; }
    .mt-top5 .t5-body { padding: 16px 16px; }
    .mt-top5 .t5-name { font-size: 19px; }
    .mt-top5 .t5-aside { padding: 14px 16px; }
    .mt-top5 .t5-cta { width: 100%; margin-left: 0; }
    .mt-top5 .t5-price { width: 100%; display: flex; align-items: baseline; gap: 8px; }
  }


  /* lien avis complet (carte résumé) */
  .mt-top5 .t5-readmore { display:inline-flex; align-items:center; gap:6px; margin-top:14px; font-size:12.5px; font-weight:600; color:var(--accent); border-bottom:1px solid transparent; transition:border-color .15s; }
  .mt-top5 .t5-readmore:hover { border-color:var(--accent); }
  .mt-top5 .t5-readmore .arr { transition:transform .15s; }
  .mt-top5 .t5-readmore:hover .arr { transform:translateY(2px); }

  /* section avis détaillés */
  .mt-reviews { display:flex; flex-direction:column; gap:28px; margin-top:44px; }
  .mt-reviews-head { font-family:"Inter","Helvetica Neue",system-ui,sans-serif; }
  .mt-reviews-head .kick { font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#0f6b54; margin:0 0 6px; }
  .mt-reviews-head h2 { font-size:32px; font-weight:700; letter-spacing:-0.02em; color:#14181d; margin:0; }
  .mt-fiche[id^="avis-"] { scroll-margin-top:88px; }
  @media (max-width:560px){ .mt-reviews-head h2{ font-size:22px; } .mt-reviews{ gap:20px; } }


  .mt-fiche {
    --ink: #14181d;
    --ink-2: #2a3038;
    --muted: #6b7480;
    --muted-2: #9aa3ad;
    --line: #e8eaed;
    --line-2: #f1f3f5;
    --bg: #ffffff;
    --bg-2: #fafbfc;
    --bg-3: #f5f6f8;
    --accent: #0f6b54;
    --accent-soft: #e5f1ec;
    --gold: #b48a3a;
    --warn: #b54848;

    font-family: "Inter", "Helvetica Neue", system-ui, sans-serif;
    color: var(--ink);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;

    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(20,24,29,.04), 0 12px 40px -16px rgba(20,24,29,.14);
  }
  .mt-fiche *, .mt-fiche *::before, .mt-fiche *::after { box-sizing: border-box; }
  .mt-fiche a { color: inherit; text-decoration: none; }

  .mt-fiche .ph { position: relative; background: var(--bg-3); display: grid; place-items: center; overflow: hidden; }
  .mt-fiche .ph::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(45deg, transparent 0 12px, rgba(0,0,0,0.028) 12px 13px); }
  .mt-fiche .ph img { position: relative; z-index: 1; width: 100%; height: 100%; object-fit: contain; padding: 14px; }
  .mt-fiche .ph-cap { position: relative; z-index: 1; font-family: ui-monospace, monospace; font-size: 11px; color: var(--muted-2); letter-spacing: 0.04em; text-align: center; padding: 4px 10px; }
  .mt-fiche .save { position: absolute; top: 14px; right: 14px; z-index: 2; width: 34px; height: 34px; background: white; border: 1px solid var(--line); border-radius: 9px; display: grid; place-items: center; font-size: 15px; color: var(--ink-2); }

  .mt-fiche .pc-pros h5, .mt-fiche .pc-cons h5 { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; margin: 0 0 12px; display: flex; align-items: center; gap: 8px; }
  .mt-fiche .pc-pros h5 { color: var(--accent); }
  .mt-fiche .pc-cons h5 { color: var(--warn); }
  .mt-fiche .pc-pros h5::before, .mt-fiche .pc-cons h5::before { content: ""; width: 18px; height: 2px; border-radius: 2px; }
  .mt-fiche .pc-pros h5::before { background: var(--accent); }
  .mt-fiche .pc-cons h5::before { background: var(--warn); }
  .mt-fiche .pc-pros ul, .mt-fiche .pc-cons ul { list-style: none; margin: 0; padding: 0; }
  .mt-fiche .pc-pros li, .mt-fiche .pc-cons li { padding: 6px 0 6px 22px; position: relative; font-size: 14px; line-height: 1.55; color: var(--ink-2); }
  .mt-fiche .pc-pros li::before { content: ""; position: absolute; left: 0; top: 9px; width: 12px; height: 12px; border-radius: 50%; background: var(--accent-soft); }
  .mt-fiche .pc-pros li::after { content: ""; position: absolute; left: 3px; top: 12px; width: 6px; height: 3px; border-left: 1.5px solid var(--accent); border-bottom: 1.5px solid var(--accent); transform: rotate(-45deg); }
  .mt-fiche .pc-cons li::before { content: ""; position: absolute; left: 0; top: 9px; width: 12px; height: 12px; border-radius: 50%; background: #f7e6e6; }
  .mt-fiche .pc-cons li::after { content: "\2212"; position: absolute; left: 4px; top: 5px; color: var(--warn); font-weight: 700; font-size: 12px; line-height: 1; }

  .mt-fiche .review-lbl { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent); margin-bottom: 10px; }
  .mt-fiche .review p { font-size: 15.5px; line-height: 1.75; color: var(--ink-2); margin: 0 0 14px; }
  .mt-fiche .review p b, .mt-fiche .review p strong { color: var(--ink); font-weight: 600; }
  .mt-fiche .quote { border-left: 3px solid var(--accent); padding: 12px 18px; margin: 18px 0; font-style: italic; color: var(--ink); font-size: 15px; line-height: 1.6; background: var(--accent-soft); border-radius: 0 8px 8px 0; }

  /* ===== Split sticky achat ===== */
  .mt-fiche .v5 { display: grid; grid-template-columns: 1fr 340px; gap: 0; grid-template-areas: "main aside" "review aside"; }
  .mt-fiche .v5-main { grid-area: main; padding: 36px 40px 0; min-width: 0; }
  .mt-fiche .v5-review { grid-area: review; padding: 0 40px 36px; min-width: 0; }
  .mt-fiche .v5-aside { grid-area: aside; }
  .mt-fiche .v5-rank { display: inline-flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--accent); background: var(--accent-soft); padding: 6px 12px; border-radius: 999px; margin-bottom: 20px; }
  .mt-fiche .v5-rank .num { font-family: ui-monospace, monospace; }
  .mt-fiche .v5-eyebrow { font-size: 13px; font-weight: 500; color: var(--muted); letter-spacing: 0.02em; text-transform: uppercase; margin-bottom: 5px; }
  .mt-fiche .v5 h3.name { font-size: 34px; font-weight: 600; letter-spacing: -0.025em; margin: 0 0 18px; line-height: 1.05; }
  .mt-fiche .v5-summary { font-size: 16.5px; line-height: 1.7; color: var(--ink-2); margin: 0 0 26px; max-width: 620px; }
  .mt-fiche .v5-pc { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; padding: 24px 0; border-top: 1px solid var(--line-2); border-bottom: 1px solid var(--line-2); margin-bottom: 28px; }
  .mt-fiche .v5-review h4 { font-size: 21px; font-weight: 600; letter-spacing: -0.015em; line-height: 1.28; margin: 0 0 16px; max-width: 620px; }
  .mt-fiche .v5-review .review { max-width: 640px; }

  .mt-fiche .v5-aside { background: var(--bg-2); border-left: 1px solid var(--line); padding: 28px 26px; }
  .mt-fiche .v5-card { position: sticky; top: 24px; }
  .mt-fiche .v5-card .ph { aspect-ratio: 1; border-radius: 12px; margin-bottom: 18px; background: white; }
  .mt-fiche .v5-card .ph::before { background: repeating-linear-gradient(45deg, transparent 0 12px, rgba(0,0,0,0.03) 12px 13px); }

  /* notre note (rédaction) */
  .mt-fiche .v5-score { display: flex; align-items: center; gap: 12px; padding-bottom: 16px; border-bottom: 1px solid var(--line); margin-bottom: 14px; }
  .mt-fiche .v5-score .n { font-size: 40px; font-weight: 700; color: var(--accent); letter-spacing: -0.03em; line-height: 1; }
  .mt-fiche .v5-score .n small { font-size: 15px; color: var(--muted); font-weight: 500; }
  .mt-fiche .v5-score .meta { display: flex; flex-direction: column; gap: 5px; align-items: flex-start; }
  .mt-fiche .v5-score .r-lbl { font-size: 10px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--muted-2); }
  .mt-fiche .v5-score .tag { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--accent); background: var(--accent-soft); padding: 4px 10px; border-radius: 999px; }

  /* avis clients (encart distinct) */
  .mt-fiche .v5-cust { display: flex; align-items: center; gap: 12px; background: var(--bg-3); border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; }
  .mt-fiche .v5-cust .stars { color: var(--gold); letter-spacing: 2px; font-size: 16px; line-height: 1; }
  .mt-fiche .v5-cust .c-meta { display: flex; flex-direction: column; gap: 3px; }
  .mt-fiche .v5-cust .c-lbl { font-size: 10px; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: var(--muted-2); }
  .mt-fiche .v5-cust .c-val { font-size: 13px; color: var(--ink-2); }
  .mt-fiche .v5-cust .c-val b { font-weight: 700; color: var(--ink); }

  .mt-fiche .v5-gauges { display: flex; flex-direction: column; gap: 11px; padding-bottom: 18px; border-bottom: 1px solid var(--line); margin-bottom: 18px; }
  .mt-fiche .v5-gauge-row .g-top { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; }
  .mt-fiche .v5-gauge-row .g-top .lbl { color: var(--ink-2); }
  .mt-fiche .v5-gauge-row .g-top .val { font-family: ui-monospace, monospace; color: var(--ink); font-weight: 600; }
  .mt-fiche .v5-gauge { height: 5px; background: var(--bg-3); border-radius: 99px; overflow: hidden; }
  .mt-fiche .v5-gauge > span { display: block; height: 100%; background: linear-gradient(90deg, var(--accent), #4ea689); border-radius: 99px; }

  .mt-fiche .v5-offers-lbl { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); margin-bottom: 12px; }
  .mt-fiche .v5-offer-btn { display: flex; align-items: center; gap: 12px; width: 100%; padding: 13px 16px; border-radius: 10px; cursor: pointer; font-family: inherit; text-align: left; transition: all .15s; margin-bottom: 10px; }
  .mt-fiche .v5-offer-btn .m { font-size: 13px; font-weight: 600; }
  .mt-fiche .v5-offer-btn .p { font-size: 18px; font-weight: 700; letter-spacing: -0.01em; margin-left: auto; }
  .mt-fiche .v5-offer-btn .arr { font-weight: 600; font-size: 15px; }
  .mt-fiche .v5-offer-btn.primary { background: var(--ink); color: white; border: 1px solid var(--ink); }
  .mt-fiche .v5-offer-btn.primary:hover { background: var(--accent); border-color: var(--accent); }
  .mt-fiche .v5-offer-btn.secondary { background: white; color: var(--ink); border: 1px solid var(--line); }
  .mt-fiche .v5-offer-btn.secondary .m { color: var(--ink-2); }
  .mt-fiche .v5-offer-btn.secondary .arr { color: var(--accent); }
  .mt-fiche .v5-offer-btn.secondary:hover { border-color: var(--accent); }
  .mt-fiche .v5-offers .note { font-size: 10px; color: var(--muted-2); font-style: italic; text-align: center; margin-top: 4px; }

  .mt-fiche .v5-keyspecs { margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--line); }
  .mt-fiche .v5-keyspecs h6 { font-size: 11px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); margin: 0 0 10px; }
  .mt-fiche .v5-keyspecs .row { display: flex; justify-content: space-between; font-size: 12.5px; padding: 6px 0; border-bottom: 1px solid var(--line-2); }
  .mt-fiche .v5-keyspecs .row:last-child { border-bottom: 0; }
  .mt-fiche .v5-keyspecs .row .k { color: var(--muted); }
  .mt-fiche .v5-keyspecs .row .v { font-weight: 600; }

  /* ===== Responsive ===== */
  @media (max-width: 768px) {
    .mt-fiche .v5 { display: flex; flex-direction: column; align-items: stretch; padding: 32px 28px 36px; }
    .mt-fiche .v5-main, .mt-fiche .v5-aside, .mt-fiche .v5-card { display: contents; }
    .mt-fiche .v5-card { position: static; }
    .mt-fiche .v5-rank     { order: 1; align-self: flex-start; margin-bottom: 18px; }
    .mt-fiche .v5-eyebrow  { order: 2; }
    .mt-fiche .v5 h3.name  { order: 3; }
    .mt-fiche .v5-summary  { order: 4; }
    .mt-fiche .v5-card .ph { order: 5; aspect-ratio: 16 / 10; max-height: 300px; margin-bottom: 0; }
    .mt-fiche .v5-score    { order: 6; margin-top: 26px; }
    .mt-fiche .v5-cust     { order: 6; }
    .mt-fiche .v5-gauges   { order: 7; }
    .mt-fiche .v5-pc       { order: 8; border-top: 0; }
    .mt-fiche .v5-offers   { order: 9; }
    .mt-fiche .v5-keyspecs { order: 10; margin-bottom: 28px; }
    .mt-fiche .v5-review   { order: 11; padding: 28px 0 0; border-top: 1px solid var(--line); }
  }
  @media (min-width: 769px) and (max-width: 1080px) {
    .mt-fiche .v5 { grid-template-columns: 1fr 300px; }
    .mt-fiche .v5-main { padding: 32px 30px 0; }
    .mt-fiche .v5-review { padding: 0 30px 32px; }
    .mt-fiche .v5 h3.name { font-size: 30px; }
  }
  @media (max-width: 560px) {
    .mt-fiche .v5 { padding: 26px 17px 32px; }
    .mt-fiche .v5-card .ph { aspect-ratio: 16 / 11; max-height: 250px; }
    .mt-fiche .v5-review { padding-top: 26px; }
    .mt-fiche .v5-rank { font-size: 11px; padding: 5px 11px; margin-bottom: 16px; }
    .mt-fiche .v5 h3.name { font-size: 26px; margin-bottom: 14px; }
    .mt-fiche .v5-summary { font-size: 15px; line-height: 1.65; margin-bottom: 22px; }
    .mt-fiche .v5-pc { grid-template-columns: 1fr; gap: 18px; padding: 20px 0; margin-bottom: 22px; }
    .mt-fiche .v5-review h4 { font-size: 18.5px; line-height: 1.32; }
    .mt-fiche .review p { font-size: 14.5px; line-height: 1.7; }
    .mt-fiche .quote { font-size: 14.5px; padding: 11px 15px; }
    .mt-fiche .v5-score .n { font-size: 34px; }
    .mt-fiche .v5-offer-btn { padding: 12px 14px; }
    .mt-fiche .v5-offer-btn .p { font-size: 17px; }
  }

</style>


<?php if ( empty( $mt_products ) ) : ?>
  <!-- Aucun produit : vérifiez le slug du champ repeater "<?php echo esc_html( $repeater_produits ); ?>". -->
<?php else : ?>

<!-- =========================================================
     CLASSEMENT TRIABLE (cartes résumé)
     ========================================================= -->
<div class="mt-top5">
  <div class="t5-bar" role="tablist" aria-label="Trier le classement">
    <span class="lbl">Trier par</span>
    <div class="t5-tabs">
      <button class="t5-tab" role="tab" aria-selected="true"  data-sort="rank"><span class="ico">&#9733;</span> Notre s&eacute;lection</button>
      <button class="t5-tab" role="tab" aria-selected="false" data-sort="price"><span class="ico">&euro;</span> Prix</button>
      <button class="t5-tab" role="tab" aria-selected="false" data-sort="rating"><span class="ico">&#9829;</span> Avis des clients</button>
    </div>
    <a class="t5-howto" href="#methodologie">Comment nous &eacute;valuons <span class="arr">&rarr;</span></a>
  </div>

  <div class="t5-list" id="t5List">

    <?php
    $idx = 0;
    foreach ( $mt_products as $row ) :
      $idx++;
      $anchor   = sprintf( 'avis-%02d', $idx );
      $rank     = mt_get( $row, $product_rank, $idx );
      $rank_lbl = mt_get( $row, $product_rank_label );
      $img_url  = mt_img_url( mt_get( $row, $product_image ) );
      $name     = mt_get( $row, $product_name );
      $brand    = mt_get( $row, $product_brand );
      $tagline  = mt_get( $row, $product_tagline );
      $summary  = mt_get( $row, $product_summary );

      // Chips : 2 points positifs + 1 négatif
      $chips_pro = array_filter( array( mt_get( $row, $product_pro_1 ), mt_get( $row, $product_pro_2 ) ) );
      $chip_con  = mt_get( $row, $product_con_1 );
      $has_chips = ( ! empty( $chips_pro ) || $chip_con !== '' );

      // Notre note (rédac)
      $score     = mt_get( $row, $product_score );
      $score_tag = mt_get( $row, $product_score_tag );

      // Avis clients : on n'affiche QUE si note client ET nombre d'avis sont renseignés
      $rating      = mt_get( $row, $product_rating );
      $reviews_lbl = mt_reviews_label( mt_get( $row, $product_reviews_count ) );
      $has_cust    = ( $rating !== '' && $reviews_lbl !== '' );

      // Bloc notes (aside) : visible seulement si au moins une note existe
      $has_ratings = ( $score !== '' || $has_cust );

      // Prix & offre
      $price    = mt_get( $row, $product_price );
      $merchant = mt_get( $row, $product_merchant );
      $url_1    = mt_get( $row, $product_url_1 );
    ?>
    <article class="t5-card" data-rank="<?php echo esc_attr( mt_num( $rank ) ); ?>" data-price="<?php echo esc_attr( mt_num( mt_get( $row, $product_price_num ) ) ); ?>" data-rating="<?php echo esc_attr( mt_num( mt_get( $row, $product_rating ) ) ); ?>">
      <div class="t5-rankcol"><span class="r-num"><?php echo esc_html( $rank ); ?></span><?php if ( $rank_lbl !== '' ) : ?><span class="r-lbl"><?php echo esc_html( $rank_lbl ); ?></span><?php endif; ?></div>
      <div class="t5-media">
        <div class="ph">
          <?php if ( $idx === 1 ) : ?><span class="t5-award">Choix n&deg;<?php echo esc_html( sprintf( '%02d', $rank ) ); ?></span><?php endif; ?>
          <?php if ( $img_url ) : ?>
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
          <?php else : ?>
            <span class="ph-cap"><?php echo esc_html( $name ); ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="t5-body">
        <?php if ( $brand !== '' ) : ?><div class="t5-eyebrow"><?php echo esc_html( $brand ); ?></div><?php endif; ?>
        <h3 class="t5-name"><?php echo esc_html( $name ); ?></h3>
        <?php if ( $tagline !== '' ) : ?><div class="t5-tagline"><?php echo esc_html( $tagline ); ?></div><?php endif; ?>
        <?php if ( $summary !== '' ) : ?><p class="t5-summary"><?php echo esc_html( $summary ); ?></p><?php endif; ?>
        <?php if ( $has_chips ) : ?>
        <div class="t5-pc">
          <?php foreach ( $chips_pro as $pro ) : ?>
            <span class="t5-chip pro"><?php echo esc_html( $pro ); ?></span>
          <?php endforeach; ?>
          <?php if ( $chip_con !== '' ) : ?><span class="t5-chip con"><?php echo esc_html( $chip_con ); ?></span><?php endif; ?>
        </div>
        <?php endif; ?>
        <a class="t5-readmore" href="#<?php echo esc_attr( $anchor ); ?>">Lire l'avis complet <span class="arr">&darr;</span></a>
      </div>
      <div class="t5-aside">
        <?php if ( $has_ratings ) : ?>
        <div class="t5-ratings">
          <?php if ( $score !== '' ) : ?>
          <div class="t5-r-ed">
            <span class="r-lbl">Notre note</span>
            <div class="r-line"><span class="n"><?php echo esc_html( $score ); ?><small>/10</small></span><?php if ( $score_tag !== '' ) : ?><span class="tag"><?php echo esc_html( $score_tag ); ?></span><?php endif; ?></div>
          </div>
          <?php endif; ?>
          <?php if ( $has_cust ) : ?>
          <div class="t5-r-cust">
            <span class="r-lbl">Avis clients</span>
            <div class="r-line"><span class="stars"><?php echo mt_stars( $rating ); ?></span><span class="cust-val"><?php echo esc_html( $rating ); ?></span><span class="cust-count"><?php echo esc_html( $reviews_lbl ); ?></span></div>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ( $price !== '' ) : ?><div class="t5-price"><div class="from">&agrave; partir de</div><div class="amt"><?php echo esc_html( $price ); ?></div><?php if ( $merchant !== '' ) : ?><div class="merch">chez <?php echo esc_html( $merchant ); ?></div><?php endif; ?></div><?php endif; ?>
        <?php if ( $url_1 !== '' ) : ?><a class="t5-cta" href="<?php echo esc_url( $url_1 ); ?>" target="_blank" rel="nofollow sponsored noopener">Voir l'offre <span class="arr">&rarr;</span></a><?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>

  </div>
</div>


<!-- =========================================================
     AVIS DÉTAILLÉS (fiches)
     ========================================================= -->
<div class="mt-reviews">
  <div class="mt-reviews-head">
    <p class="kick">Nos avis d&eacute;taill&eacute;s</p>
    <h2>Le test complet de chaque tablette</h2>
  </div>

  <?php
  $idx = 0;
  foreach ( $mt_products as $row ) :
    $idx++;
    $anchor  = sprintf( 'avis-%02d', $idx );
    $rank    = mt_get( $row, $product_rank, $idx );
    $img_url = mt_img_url( mt_get( $row, $product_image ) );
    $name    = mt_get( $row, $product_name );
    $brand   = mt_get( $row, $product_brand );
    $tagline = mt_get( $row, $product_tagline );
    $summary = mt_get( $row, $product_summary );

    $pros  = array_filter( array( mt_get( $row, $product_pro_1 ), mt_get( $row, $product_pro_2 ), mt_get( $row, $product_pro_3 ) ) );
    $cons  = array_filter( array( mt_get( $row, $product_con_1 ), mt_get( $row, $product_con_2 ) ) );
    $has_pc = ( ! empty( $pros ) || ! empty( $cons ) );

    $review_title = mt_get( $row, $product_review_title );
    $review_body  = mt_get( $row, $product_review_body );
    $quote        = mt_get( $row, $product_review_quote );
    $has_review   = ( $review_title !== '' || trim( wp_strip_all_tags( $review_body ) ) !== '' );

    // Notre note (rédac)
    $score     = mt_get( $row, $product_score );
    $score_tag = mt_get( $row, $product_score_tag );

    // Avis clients : visibles seulement si note client ET nombre d'avis renseignés
    $rating      = mt_get( $row, $product_rating );
    $reviews_lbl = mt_reviews_label( mt_get( $row, $product_reviews_count ) );
    $has_cust    = ( $rating !== '' && $reviews_lbl !== '' );

    // Jauges / offres / specs : on ne garde que les lignes complètes
    $gauges = array();
    foreach ( $product_gauges as $g ) {
      $g_label = mt_get( $row, $g['label'] );
      $g_val   = mt_get( $row, $g['value'] );
      if ( $g_label !== '' && $g_val !== '' ) {
        $gauges[] = array( 'label' => $g_label, 'value' => $g_val, 'pct' => max( 0, min( 100, mt_num( $g_val ) * 10 ) ) );
      }
    }
    $offers = array();
    foreach ( $product_offers as $o ) {
      $o_merch = mt_get( $row, $o['merchant'] );
      $o_price = mt_get( $row, $o['price'] );
      $o_url   = mt_get( $row, $o['url'] );
      if ( $o_merch !== '' && $o_price !== '' && $o_url !== '' ) {
        $offers[] = array( 'merchant' => $o_merch, 'price' => $o_price, 'url' => $o_url );
      }
    }
    $specs = array();
    foreach ( $product_specs as $s ) {
      $s_key = mt_get( $row, $s['key'] );
      $s_val = mt_get( $row, $s['value'] );
      if ( $s_key !== '' && $s_val !== '' ) {
        $specs[] = array( 'key' => $s_key, 'value' => $s_val );
      }
    }
  ?>
  <article class="mt-fiche" id="<?php echo esc_attr( $anchor ); ?>">
  <div class="v5">
    <div class="v5-main">
      <span class="v5-rank"><span class="num">N&deg;<?php echo esc_html( sprintf( '%02d', $rank ) ); ?></span><?php if ( $tagline !== '' ) : ?> &#9733; <?php echo esc_html( $tagline ); ?><?php endif; ?></span>
      <?php if ( $brand !== '' ) : ?><div class="v5-eyebrow"><?php echo esc_html( $brand ); ?></div><?php endif; ?>
      <h3 class="name"><?php echo esc_html( $name ); ?></h3>
      <?php if ( $summary !== '' ) : ?><p class="v5-summary"><?php echo esc_html( $summary ); ?></p><?php endif; ?>
      <?php if ( $has_pc ) : ?>
      <div class="v5-pc">
        <?php if ( ! empty( $pros ) ) : ?>
        <div class="pc-pros"><h5>Points positifs</h5><ul>
          <?php foreach ( $pros as $pro ) : ?><li><?php echo esc_html( $pro ); ?></li><?php endforeach; ?>
        </ul></div>
        <?php endif; ?>
        <?php if ( ! empty( $cons ) ) : ?>
        <div class="pc-cons"><h5>Points n&eacute;gatifs</h5><ul>
          <?php foreach ( $cons as $con ) : ?><li><?php echo esc_html( $con ); ?></li><?php endforeach; ?>
        </ul></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ( $has_review ) : ?>
    <div class="v5-review">
      <div class="review-lbl">&mdash; Avis complet de la r&eacute;daction</div>
      <?php if ( $review_title !== '' ) : ?><h4><?php echo esc_html( $review_title ); ?></h4><?php endif; ?>
      <div class="review">
        <?php
          // Corps WYSIWYG : on insère la citation après le 1er paragraphe si elle existe.
          $body = $review_body;
          if ( $quote !== '' ) {
            $quote_html = '<div class="quote">' . esc_html( $quote ) . '</div>';
            if ( strpos( $body, '</p>' ) !== false ) {
              $pos  = strpos( $body, '</p>' ) + 4;
              $body = substr( $body, 0, $pos ) . $quote_html . substr( $body, $pos );
            } else {
              $body .= $quote_html;
            }
          }
          echo $body; // WYSIWYG : HTML autorisé
        ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="v5-aside">
      <div class="v5-card">
        <div class="ph">
          <span class="save">&#9829;</span>
          <?php if ( $img_url ) : ?>
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
          <?php else : ?>
            <span class="ph-cap">&mdash; <?php echo esc_html( $name ); ?> &mdash;</span>
          <?php endif; ?>
        </div>
        <?php if ( $score !== '' ) : ?><div class="v5-score"><span class="n"><?php echo esc_html( $score ); ?><small>/10</small></span><span class="meta"><span class="r-lbl">Notre note</span><?php if ( $score_tag !== '' ) : ?><span class="tag"><?php echo esc_html( $score_tag ); ?></span><?php endif; ?></span></div><?php endif; ?>
        <?php if ( $has_cust ) : ?><div class="v5-cust"><span class="stars"><?php echo mt_stars( $rating ); ?></span><span class="c-meta"><span class="c-lbl">Avis clients</span><span class="c-val"><b><?php echo esc_html( $rating ); ?></b>/5 &middot; <?php echo esc_html( $reviews_lbl ); ?></span></span></div><?php endif; ?>

        <?php if ( ! empty( $gauges ) ) : ?>
        <div class="v5-gauges">
          <?php foreach ( $gauges as $g ) : ?>
          <div class="v5-gauge-row"><div class="g-top"><span class="lbl"><?php echo esc_html( $g['label'] ); ?></span><span class="val"><?php echo esc_html( $g['value'] ); ?></span></div><div class="v5-gauge"><span style="width:<?php echo esc_attr( $g['pct'] ); ?>%"></span></div></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $offers ) ) : ?>
        <div class="v5-offers">
          <div class="v5-offers-lbl">Meilleures offres</div>
          <?php foreach ( $offers as $offer_i => $o ) :
            $o_class = ( $offer_i === 0 ) ? 'primary' : 'secondary';
          ?>
          <a class="v5-offer-btn <?php echo $o_class; ?>" href="<?php echo esc_url( $o['url'] ); ?>" target="_blank" rel="nofollow sponsored noopener"><span class="m"><?php echo esc_html( $o['merchant'] ); ?></span><span class="p"><?php echo esc_html( $o['price'] ); ?></span><span class="arr">&rarr;</span></a>
          <?php endforeach; ?>
          <div class="note">*Prix au moment de la r&eacute;daction</div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $specs ) ) : ?>
        <div class="v5-keyspecs">
          <h6>Caract&eacute;ristiques cl&eacute;s</h6>
          <?php foreach ( $specs as $s ) : ?>
          <div class="row"><span class="k"><?php echo esc_html( $s['key'] ); ?></span><span class="v"><?php echo esc_html( $s['value'] ); ?></span></div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  </article>
  <?php endforeach; ?>

</div>

<?php endif; ?>


<script>
/* Tri du classement (FLIP). Inchangé : opère sur les cartes générées. */
(function () {
  var roots = document.querySelectorAll('.mt-top5');
  roots.forEach(function (root) {
    if (root.dataset.t5Init) return;
    root.dataset.t5Init = '1';

    var list = root.querySelector('.t5-list');
    var tabs = root.querySelectorAll('.t5-tab');
    var cards = Array.prototype.slice.call(list.querySelectorAll('.t5-card'));

    var comparators = {
      rank:   function (a, b) { return num(a, 'rank') - num(b, 'rank'); },
      price:  function (a, b) { return num(a, 'price') - num(b, 'price'); },
      rating: function (a, b) { return num(b, 'rating') - num(a, 'rating'); }
    };
    function num(el, key) { return parseFloat(el.getAttribute('data-' + key)) || 0; }

    function sortBy(key) {
      var first = {};
      cards.forEach(function (c, i) { first[i] = c.getBoundingClientRect().top; });

      var ordered = cards.slice().sort(comparators[key] || comparators.rank);
      ordered.forEach(function (c) { list.appendChild(c); });

      cards.forEach(function (c, i) {
        var last = c.getBoundingClientRect().top;
        var dy = first[i] - last;
        if (!dy) return;
        c.style.transition = 'none';
        c.style.transform = 'translateY(' + dy + 'px)';
        requestAnimationFrame(function () {
          c.style.transition = 'transform .42s cubic-bezier(.22,.61,.36,1)';
          c.style.transform = '';
        });
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
        tab.setAttribute('aria-selected', 'true');
        sortBy(tab.getAttribute('data-sort'));
      });
    });
  });
})();
</script>
