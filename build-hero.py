#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generateur du Hero 2026 (meilleurtest.fr) au format bricksCopiedElements.

Cible : template-hero.html
  - fil d'ariane (rank_math)
  - en-tete 2 colonnes :
      gauche  : eyebrow (pill "Verifie" + date) > H1 (+SEO) > byline (auteur+date)
                > chapo ($introduction) > photo ({featured_image} + badge)
      droite  : carte "Notre enquete" = 4 stats + signaux de confiance + note lecteurs

Principes appris (cf. CLAUDE.md) :
  - MISE EN PAGE 100% NATIVE (grille/flex/bordures/pills) -> s'applique au collage.
  - Le DYNAMIQUE reste dans des elements `code` (PHP) reutilisant les variables
    de template (get_all_template_variables) -> donnees reelles.
  - Styles HTML internes aux blocs `code` : ecrits en STYLE INLINE (attribut style)
    -> rendus immediatement, pas de CSS custom inerte a "reveiller".
  - Architecture : section (bande) > container (boxe) > contenu.
  - Classes globales reutilisees (bnxvav, uemizu, wyopqz, vrjaxu) : ce sont les
    classes EXISTANTES du site -> reconnues par ID, deja compilees -> le style
    .titre-principal / .text-content fonctionne sans recompilation.

NB : les elements `code` colles doivent etre re-approuves dans Bricks
     (Code review / signature) car leur contenu change -> nouvelle signature.
"""

import json

# --- Palette maquette ---------------------------------------------------------
INK        = "#14181d"
INK2       = "#2a3038"
MUTED      = "#6b7480"
MUTED2     = "#9aa3ad"
LINE       = "#e8eaed"
LINE2      = "#f1f3f5"
BG2        = "#fafbfc"
BG3        = "#f5f6f8"
ACCENT     = "#0f6b54"
ACCENT_SOFT= "#e5f1ec"
GOLD       = "#b48a3a"
WHITE      = "#ffffff"

# --- Helpers ------------------------------------------------------------------
def col(hex_):  return {"hex": hex_}
def bg(hex_):   return {"color": {"hex": hex_}}
def rad(v):     return {"top": str(v), "right": str(v), "bottom": str(v), "left": str(v)}
def pad(t, r, b, l): return {"top": str(t), "right": str(r), "bottom": str(b), "left": str(l)}

def border_all(width, color_hex, style="solid", radius=None):
    o = {"width": {"top": str(width), "right": str(width), "bottom": str(width), "left": str(width)},
         "style": style, "color": col(color_hex)}
    if radius is not None:
        o["radius"] = rad(radius)
    return o

C = []
def add(eid, name, parent, children, settings, label=None):
    el = {"id": eid, "name": name, "parent": parent, "children": children, "settings": settings}
    if label:
        el["label"] = label
    C.append(el)

# --- SVG icones (lucide-like, scope par currentColor) -------------------------
SVG_SHIELD = ('<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" '
              'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
              '<path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/>'
              '<path d="m9 12 2 2 4-4"/></svg>')
SVG_CLOCK  = ('<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" '
              'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
              '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>')
SVG_LAYERS = ('<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" '
              'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
              '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/></svg>')
SVG_TABLET = ('<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" '
              'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
              '<rect x="6" y="3" width="12" height="18" rx="2"/>'
              '<line x1="10.5" y1="17.5" x2="13.5" y2="17.5"/></svg>')
SVG_CHAT   = ('<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" '
              'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
              '<path d="M21 11.5a8.4 8.4 0 0 1-1.1 4.2A8.5 8.5 0 0 1 12.5 20a8.4 8.4 0 0 1-4.2-1.1L3 20l'
              '1.1-5.3A8.4 8.4 0 0 1 3 10.5 8.5 8.5 0 0 1 7.3 3a8.4 8.4 0 0 1 4.2-1.1h.5A8.5 8.5 0 0 1 21 11v.5Z"/></svg>')
SVG_CHECK  = ('<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" '
              'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
              '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg>')
SVG_REFRESH= ('<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" '
              'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
              '<path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg>')
SVG_BOOK   = ('<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" '
              'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
              '<path d="M12 6c-1.6-1.2-4-2-7-2v13c3 0 5.4.8 7 2 1.6-1.2 4-2 7-2V4c-3 0-5.4.8-7 2Z"/>'
              '<path d="M12 6v13"/></svg>')

# --- Blocs PHP (dynamique, reutilise les variables de template) ---------------
PHP_CRUMB = ("<?php if (function_exists('rank_math_the_breadcrumbs')) "
             "{ echo rank_math_the_breadcrumbs(); } ?>")

PHP_TITLE = r"""<?php
// VARIABLES
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));
$post_type = get_post_type($this_id);
$total_avis = 0;
if (!empty($top_avis_ids)) { $total_avis = count($top_avis_ids); }

// === TITRE ET SOUS-TITRE ===
echo "<h1 id='titre-principal' class='titre-principal titre-alternatif gradient-text-dark'>";
if (!empty($forcer_affichage_du_titre ?? '')) {
    echo $forcer_affichage_du_titre ?? '';
} elseif ($post_type === 'comparatif') {
    echo "Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." en 2026";
} else {
    echo get_the_title();
}
if (!empty($sous_titre ?? '')) {
    echo "<span id='sous-titre-principal' class='sous-titre-principal'>" . ($sous_titre ?? '') . "</span>";
} elseif ($post_type === 'comparatif') {
    echo "<span id='sous-titre-principal' class='sous-titre-principal'> : Comparatif et guide d'achat</span>";
}
echo "</h1>";

// === REFERENCEMENT (effets de bord SEO : title + description rank math) ===
$rank_math_title = get_post_meta($this_id ?? '', 'rank_math_title');
$rank_math_description = get_post_meta($this_id ?? '', 'rank_math_description');
if (($template_description ?? '') == 0 || $post_type === 'liste') {
    $new_rank_math_description = intro(50, $this_id ?? '');
    if (($new_rank_math_description <> $rank_math_description) && (($this_id ?? '') <> 4224)) {
        update_post_meta($this_id ?? '', 'rank_math_description', $new_rank_math_description);
    }
    $post = get_post($this_id ?? '');
    $current_excerpt = $post->post_excerpt ?? '';
    if ($current_excerpt !== $new_rank_math_description) {
        wp_update_post(array('ID' => $this_id ?? '', 'post_excerpt' => $new_rank_math_description));
    }
}
if (!empty($forcer_affichage_du_titre ?? '')) {
    $new_rank_math_title = $forcer_affichage_du_titre ?? '';
    if (($new_rank_math_title <> $rank_math_title) && (($this_id ?? '') <> 4224)) {
        update_post_meta($this_id ?? '', 'rank_math_title', $new_rank_math_title);
    }
} else {
    if ($post_type === 'liste') {
        $new_rank_math_title = get_the_title($this_id);
    } else {
        $new_rank_math_title = "Comparatif : Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." 2026";
    }
    if (($new_rank_math_title <> $rank_math_title) && (($this_id ?? '') <> 4224)) {
        update_post_meta($this_id ?? '', 'rank_math_title', $new_rank_math_title);
    }
}
?>"""

PHP_BAUTHOR = r"""<?php
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));
echo '<span style="display:inline-flex;align-items:center;gap:8px;">';
if (!empty($author_avatar_id ?? '')) {
    echo wp_get_attachment_image($author_avatar_id, array(30,30), '', array(
        'style' => 'border-radius:50%;display:block;width:30px;height:30px;object-fit:cover;',
        'alt'   => $author_avatar_alt ?? ''
    ));
}
$aid = get_the_author_meta('ID', $author_id ?? '');
echo '<span>Par&nbsp;<a href="' . esc_url(get_author_posts_url($aid)) . '" style="color:#14181d;font-weight:600;">'
   . esc_html($author ?? '') . '</a></span>';
echo '</span>';
?>"""

PHP_BDATE  = ("<?php echo 'Mis \xc3\xa0 jour le ' . date_i18n('j F Y', get_the_modified_time('U')); ?>"
              ).encode('latin-1').decode('utf-8')
PHP_EYEDATE= ("<?php echo 'le ' . date_i18n('j F Y', get_the_modified_time('U')); ?>")

PHP_LEDE = r"""<?php
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));
?>
<div class="text-content"><?php echo $introduction ?? ''; ?></div>"""

def php_num(var, suffix=""):
    s = " . ' %s'" % suffix if suffix else ""
    return ("<?php extract(get_all_template_variables()); "
            "echo esc_html($%s ?? '')%s; ?>" % (var, s))

PHP_C3LBL = r"""<?php extract(get_all_template_variables());
$t = $type_de_produit_au_pluriel ?? '';
if (strlen($t) >= 22) { echo 'Produits analyses'; }
else {
    echo esc_html($t ?: 'Produits')
       . ((($masculinsfeminins ?? '') == 'Meilleures') ? ' analysees' : ' analyses');
}
?>"""

PHP_T2 = ("<?php echo 'Mis \xc3\xa0 jour le <b>' . date_i18n('j F Y', get_the_modified_time('U')) . '</b>'; ?>"
          ).encode('latin-1').decode('utf-8')
PHP_T3 = r"""<?php
$c = get_post_field('post_content', get_the_ID());
$w = str_word_count(strip_tags(strip_shortcodes($c)));
$min = max(1, (int) round($w / 200));
echo '<b>' . $min . ' min</b> de lecture';
?>"""

def code(php, execute=True, noroot=False, extra=None):
    s = {"executeCode": bool(execute), "parseDynamicData": True, "code": php, "user_id": 1}
    if not execute:
        s["executeCode"] = False
    if noroot:
        s["noRoot"] = True
    if extra:
        s.update(extra)
    return s

# ============================================================================
# ARBRE
# ============================================================================

# Section > Container (boxe)
add("heroSec", "section", 0, ["heroCont"],
    {"_padding": pad(0, 0, 0, 0)}, label="Hero 2026")
add("heroCont", "container", "heroSec", ["heroCrumb", "heroGrid"], {})

# Fil d'ariane (pleine largeur du container)
add("heroCrumb", "code", "heroCont", [],
    code(PHP_CRUMB, noroot=True, extra={
        "_margin": {"bottom": "4px"},
        "_typography": {"font-size": "13px", "color": col(MUTED)}}))

# Grille 2 colonnes
add("heroGrid", "block", "heroCont", ["heroMain", "heroCard"], {
    "_display": "grid",
    "_gridTemplateColumns": "minmax(0, 1fr) 350px",
    "_gridGap": "40px",
    "_alignItems": "start",
    "_padding": pad(24, 0, 24, 0),
    "_gridTemplateColumns:tablet_portrait": "1fr",
    "_gridGap:tablet_portrait": "32px"})

# ---- Colonne gauche --------------------------------------------------------
add("heroMain", "block", "heroGrid",
    ["heroEye", "heroTitle", "heroByl", "heroLede", "heroPhoto"],
    {"_display": "flex", "_direction": "column"})

# Eyebrow (pill + date)
add("heroEye", "block", "heroMain", ["heroEyePill", "heroEyeDate"], {
    "_display": "inline-flex", "_direction": "row", "_alignItems": "center",
    "_alignSelf": "flex-start", "_gap": "9px",
    "_padding": pad(5, 12, 5, 8),
    "_background": bg(WHITE),
    "_border": border_all(1, LINE, radius=999),
    "_typography": {"font-size": "12px", "font-weight": "500", "color": col(INK2)},
    "_margin": {"bottom": "20px"}})
add("heroEyePill", "text-basic", "heroEye", [], {
    "text": "Vérifié", "tag": "span",
    "_padding": pad(2, 8, 2, 8),
    "_background": bg(ACCENT_SOFT),
    "_border": {"radius": rad(999)},
    "_typography": {"font-size": "11px", "font-weight": "600", "color": col(ACCENT)}})
add("heroEyeDate", "code", "heroEye", [], code(PHP_EYEDATE, noroot=True))

# Titre H1 (+ SEO) — classes existantes bnxvav/uemizu (deja compilees sur le site)
add("heroTitle", "code", "heroMain", [],
    code(PHP_TITLE, extra={
        "_cssGlobalClasses": ["bnxvav", "uemizu"],
        "_margin": {"bottom": "22px"}}))

# Byline : auteur + separateur + date de modif
add("heroByl", "block", "heroMain", ["heroBylAuthor", "heroBylSep", "heroBylDate"], {
    "_display": "flex", "_direction": "row", "_alignItems": "center", "_flexWrap": "wrap",
    "_gap": "12px", "_rowGap": "8px",
    "_typography": {"font-size": "13px", "color": col(MUTED)},
    "_margin": {"bottom": "26px"}})
add("heroBylAuthor", "code", "heroByl", [], code(PHP_BAUTHOR, noroot=True))
add("heroBylSep", "text-basic", "heroByl", [], {
    "text": "•", "tag": "span", "_typography": {"color": col(MUTED2)}})
add("heroBylDate", "code", "heroByl", [], code(PHP_BDATE, noroot=True))

# Chapo ($introduction) — classes existantes wyopqz/vrjaxu
add("heroLede", "code", "heroMain", [],
    code(PHP_LEDE, extra={
        "_cssGlobalClasses": ["wyopqz", "vrjaxu"],
        "_typography": {"font-size": "16px", "color": col(INK2), "line-height": "1.62"},
        "_margin": {"bottom": "26px"}}))

# Photo (image dynamique {featured_image} + badge comparatif)
add("heroPhoto", "block", "heroMain", ["heroImg", "heroBadge"], {
    "_position": "relative", "_overflow": "hidden",
    "_border": {"radius": rad(12)}})
add("heroImg", "image", "heroPhoto", [], {
    "image": {"useDynamicData": "{featured_image}", "size": "large", "filename": "",
              "id": 229176,
              "url": "https://meilleurtest.fr/wp-content/uploads/2025/11/87856-Les-meilleurs-appareils-photo-compacts.jpg"},
    "stretch": True, "_width": "100%", "_height": "340px", "_objectFit": "cover",
    "_border": {"radius": rad(12)}})
add("heroBadge", "image", "heroPhoto", [], {
    "image": {"id": 245248, "filename": "badge-mt.png", "size": "full",
              "full": "https://meilleurtest.fr/wp-content/uploads/2025/11/badge-mt.png",
              "url": "https://meilleurtest.fr/wp-content/uploads/2025/11/badge-mt.png"},
    "_widthMax": "130", "_position": "absolute", "_top": "10px", "_left": "10px",
    "_conditions": [[{"id": "hckmdn", "key": "dynamic_data",
                      "dynamic_data": "{post_type}", "value": "comparatif"}]]})

# ---- Colonne droite : carte "Notre enquete" --------------------------------
add("heroCard", "block", "heroGrid", ["cardHead", "cardGrid", "cardTrust", "cardVote"], {
    "_background": bg(BG2),
    "_border": border_all(1, LINE, radius=16),
    "_padding": pad(24, 24, 24, 24),
    "_position": "sticky", "_top": "20px", "_alignSelf": "start",
    "_position:tablet_portrait": "static",
    "_widthMax:tablet_portrait": "520px"})

# En-tete carte
add("cardHead", "block", "heroCard", ["cardHeadIco", "cardHeadH"], {
    "_display": "flex", "_direction": "row", "_alignItems": "center", "_gap": "8px",
    "_typography": {"color": col(ACCENT)}, "_margin": {"bottom": "18px"}})
add("cardHeadIco", "code", "cardHead", [], code(SVG_SHIELD, execute=False, noroot=True))
add("cardHeadH", "heading", "cardHead", [], {
    "text": "Notre enquête", "tag": "h3",
    "_typography": {"font-size": "12px", "font-weight": "700", "letter-spacing": "0.07em",
                    "text-transform": "uppercase", "color": col(ACCENT)},
    "_margin": {"top": "0", "bottom": "0"}})

# Grille 2x2 des stats (natif) ; chiffres dynamiques en `code`
add("cardGrid", "block", "heroCard", ["cellA", "cellB", "cellC", "cellD"], {
    "_display": "grid", "_gridTemplateColumns": "1fr 1fr", "_gridGap": "1px",
    "_background": bg(LINE),
    "_border": border_all(1, LINE, radius=10),
    "_overflow": "hidden"})

def stat_cell(cid, svg, num_php, lbl, lbl_is_code=False):
    ico, txt, num, lblid = cid+"Ico", cid+"Txt", cid+"Num", cid+"Lbl"
    add(cid, "block", "cardGrid", [ico, txt], {
        "_background": bg(WHITE), "_display": "flex", "_direction": "row",
        "_alignItems": "center", "_gap": "11px", "_padding": pad(14, 16, 14, 16)})
    add(ico, "code", cid, [],
        code(svg, execute=False, extra={"_typography": {"color": col(ACCENT)},
                                        "_display": "flex", "_alignItems": "center"}))
    add(txt, "block", cid, [num, lblid], {"_display": "flex", "_direction": "column"})
    add(num, "code", txt, [], code(num_php, extra={
        "_typography": {"font-family": "monospace", "font-size": "18px", "font-weight": "700",
                        "line-height": "1", "letter-spacing": "-0.02em", "color": col(INK)}}))
    lbl_set = {"_typography": {"font-size": "10.5px", "color": col(MUTED), "line-height": "1.2"},
               "_margin": {"top": "4px"}}
    if lbl_is_code:
        add(lblid, "code", txt, [], code(lbl, extra=lbl_set))
    else:
        s = {"text": lbl, "tag": "div"}; s.update(lbl_set)
        add(lblid, "text-basic", txt, [], s)

stat_cell("cellA", SVG_CLOCK,  php_num("heures_investies", "h"),  "de recherche")
stat_cell("cellB", SVG_LAYERS, php_num("sources_consultees"),     "sources consultées")
stat_cell("cellC", SVG_TABLET, php_num("produits_analyses"),      PHP_C3LBL, lbl_is_code=True)
stat_cell("cellD", SVG_CHAT,   php_num("avis_etudies"),           "avis étudiés")

# Signaux de confiance
add("cardTrust", "block", "heroCard", ["trA", "trB", "trC"], {
    "_display": "flex", "_direction": "column", "_gap": "11px",
    "_margin": {"top": "18px"}, "_padding": {"top": "16px"},
    "_border": {"width": {"top": "1"}, "style": "solid", "color": col(LINE)}})

def trust_row(rid, svg, text, is_code=False):
    tile, ico, txt = rid+"Tile", rid+"Ico", rid+"Txt"
    add(rid, "block", "cardTrust", [tile, txt], {
        "_display": "flex", "_direction": "row", "_alignItems": "center", "_gap": "10px",
        "_typography": {"font-size": "12.5px", "color": col(INK2)}})
    add(tile, "block", rid, [ico], {
        "_width": "30px", "_height": "30px", "_flexShrink": "0",
        "_border": {"radius": rad(8)}, "_background": bg(ACCENT_SOFT),
        "_typography": {"color": col(ACCENT)},
        "_display": "flex", "_alignItems": "center", "_justifyContent": "center"})
    add(ico, "code", tile, [], code(svg, execute=False, noroot=True))
    if is_code:
        add(txt, "code", rid, [], code(text, noroot=True))
    else:
        add(txt, "text-basic", rid, [], {"text": text, "tag": "span"})

trust_row("trA", SVG_CHECK,   "<b>100&nbsp;% indépendant</b> — sans pub ni sponsor")
trust_row("trB", SVG_REFRESH, PHP_T2, is_code=True)
trust_row("trC", SVG_BOOK,    PHP_T3, is_code=True)

# Note lecteurs ([ratemypost])
add("cardVote", "block", "heroCard", ["voteH", "voteShort", "voteNote"], {
    "_display": "flex", "_direction": "column",
    "_margin": {"top": "16px"}, "_padding": {"top": "16px"},
    "_border": {"width": {"top": "1"}, "style": "solid", "color": col(LINE)}})
add("voteH", "heading", "cardVote", [], {
    "text": "Noter ce guide", "tag": "h4",
    "_typography": {"font-size": "13.5px", "font-weight": "600", "color": col(INK)},
    "_margin": {"top": "0", "bottom": "9px"}})
add("voteShort", "shortcode", "cardVote", [], {"shortcode": "[ratemypost]", "showPlaceholder": True})
add("voteNote", "text-basic", "cardVote", [], {
    "text": ("Votre note oriente les autres lecteurs et nous aide à améliorer "
             "ce contenu. Merci&nbsp;!"),
    "tag": "p",
    "_typography": {"font-size": "11.5px", "color": col(MUTED), "line-height": "1.5"},
    "_margin": {"top": "7px", "bottom": "0"}})

# ============================================================================
# Classes globales reutilisees (definitions verbatim de l'export du site).
# Reconnues par ID au collage -> deja compilees -> styles actifs sans recompil.
# ============================================================================
GLOBAL_CLASSES = [
    {"id": "bnxvav", "name": "title-meta", "settings": {
        "_cssCustom": (
            "\n/* ### \n   ### Blocks > Breadcrumbs\n   ###\n*/\n\n"
            ".rank-math-breadcrumb p {\n    font-size: var(--at-text--2xs);\n    font-weight: 300;\n}\n\n"
            ".rank-math-breadcrumb a::first-letter {\n  text-transform: uppercase;\n}\n\n"
            ".rank-math-breadcrumb a {\n\tcolor: #333;\n  display: inline-block;\n}\n\n"
            ".rank-math-breadcrumb a:hover {\n\tcolor: #666;\n}\n\n"
            "/* ### \n   ### COMPARATIF PRODUITS\n   ###\n*/\n\n"
            ".comparatif-up {\n  color:var(--at-secondary);\n  font-size:var(--at-text--xs);\n      margin: 0;\n}\n\n"
            "/* ### \n   ### Blocks > Post-meta\n   ###\n*/\n\n"
            ".titre-principal {\n  line-height: 1.3;\n  text-align:left;\n  text-wrap: auto;\n  margin:0;\n  font-family: 'source serif 4';\n}\n\n"
            ".titre-principal span {\n    font-weight:300;\n}\n\n"
            "p.marques-evaluees {\n  font-size: var(--at-text--xl);\n}\n\n"),
        "_display": "flex", "_flexDirection": "column", "_gap": "var(--at-space--xs)"},
     "modified": 1773218959851, "user_id": 1},
    {"id": "uemizu", "name": "main-align", "settings": {
        "_alignSelf": "flex-start", "_alignItems": "flex-start",
        "_typography": {"text-align": "left"}}, "modified": 1761667990, "user_id": 1},
    {"id": "wyopqz", "name": "text-content", "settings": {
        "_cssCustom": (
            ".text-content {\n\n  h2, h3, h4, h5, h6{\n  \tfont-weight: 700;\n    color: var(--at-black);\n"
            "    margin-bottom: var(--at-space--s);\n  }\n\n  h2{\n  \tfont-size: var(--at-heading--m);\n  }\n\n"
            "  h3{\n    font-size: var(--at-heading--s);\n  }\n\n  h4, h5, h6{\n  \tfont-size: var(--at-text--s);\n  }\n\n"
            "  p {\n\n    strong, bold{\n      color: var(--at-black);\n    }\n\n    a{\n     \tcolor: var(--at-primary);\n"
            "      transition: color .2s ease;\n\n      &:hover, &:focus{\n      \tcolor: var(--at-primary-d-1);\n"
            "        text-decoration: underline;\n      }\n    }\n\n  }\n\n  ul, ol{\n    margin:var(--at-space--m) 0;\n\n"
            "    ::marker{\n      color: var(--at-black);\n    }\n\n    li:not(:last-child){\n    \tmargin-bottom: 8px;\n    }\n  }\n}\n")},
     "modified": 1764669813888, "user_id": 1},
    {"id": "vrjaxu", "name": "small-width", "settings": {
        "_widthMax": "720px",
        "_padding:mobile_portrait": {"bottom": "var(--at-space--s)"}},
     "modified": 1761568866298, "user_id": 1},
]

# ============================================================================
OUT = {
    "content": C,
    "source": "bricksCopiedElements",
    "sourceUrl": "https://meilleurtest.fr",
    "version": "2.0.2",
    "globalClasses": GLOBAL_CLASSES,
    "globalElements": [],
}

if __name__ == "__main__":
    out_path = "component-hero-2026-updated.json"
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(OUT, f, ensure_ascii=False, indent=None)
    print("OK ->", out_path)
    print("Elements :", len(C))
    print("Classes globales :", len(GLOBAL_CLASSES))
