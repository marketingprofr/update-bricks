#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generateur du Hero 2026 (meilleurtest.fr) au format bricksCopiedElements.
Cible : template-hero.html  — perimetre HERO SEUL.

METHODE (v4, validee client) :
  - COLONNE GAUCHE = UN SEUL gros bloc `code` PHP autonome (breadcrumb inclus) :
    breadcrumb + eyebrow + titre(+SEO) + byline + chapo + photo. Styles inline,
    et classes globales existantes (bnxvav/wyopqz) portees par l'element code pour
    que .titre-principal (serif) et .text-content (typo chapo) soient emis.
    => 1 SEULE signature a approuver.
  - COLONNE DROITE (encart "Notre enquete") = ELEMENTS NATIFS Bricks
    (block/grid/heading/text-basic/shortcode). PAS d'element `icon` : sous AT,
    Bricks 2.0 rend les icones FA en SVG inline traite comme du CODE a signer.
    => On met Font Awesome en <i class="fas fa-..."> (CSS, comme deja fait sur le
       site) DANS des text-basic natifs : pas de SVG, RIEN a signer cote encart.
    Valeurs dynamiques via dynamic data : {post_modified_date}, {post_reading_time}
    (natifs) et {acf_*} pour les 4 stats.

=> 1 seul bloc code au total ; zero SVG ; on n'emiette plus en petits code blocks.
"""

import json

# --- Palette ------------------------------------------------------------------
INK="#14181d"; INK2="#2a3038"; MUTED="#6b7480"; MUTED2="#9aa3ad"
LINE="#e8eaed"; BG2="#fafbfc"; ACCENT="#0f6b54"; ACCENT_SOFT="#e5f1ec"; WHITE="#ffffff"

def col(h):  return {"hex": h}
def bg(h):   return {"color": {"hex": h}}
def rad(v):  return {"top": str(v), "right": str(v), "bottom": str(v), "left": str(v)}
def pad(t,r,b,l): return {"top": str(t), "right": str(r), "bottom": str(b), "left": str(l)}
def border_all(w, c, style="solid", radius=None):
    o = {"width": {"top": str(w), "right": str(w), "bottom": str(w), "left": str(w)},
         "style": style, "color": col(c)}
    if radius is not None: o["radius"] = rad(radius)
    return o

C = []
def add(eid, name, parent, children, settings, label=None):
    el = {"id": eid, "name": name, "parent": parent, "children": children, "settings": settings}
    if label: el["label"] = label
    C.append(el)

# ============================================================================
# COLONNE GAUCHE : un seul gros bloc PHP
# ============================================================================
PHP_LEFT = r"""<?php
$this_id   = get_the_ID();
extract(get_all_template_variables($this_id));
$post_type = get_post_type($this_id);
$total_avis = !empty($top_avis_ids) ? count($top_avis_ids) : 0;
$mod = date_i18n('j F Y', get_the_modified_time('U'));

// ----- Fil d'ariane -----
if (function_exists('rank_math_the_breadcrumbs')) { echo rank_math_the_breadcrumbs(); }

// ----- Eyebrow (pill "Verifie" + date) -----
echo '<div style="display:inline-flex;align-items:center;gap:9px;padding:5px 12px 5px 8px;'
   . 'background:#fff;border:1px solid #e8eaed;border-radius:999px;font-size:12px;font-weight:500;'
   . 'color:#2a3038;margin:16px 0 20px;">';
echo '<span style="padding:2px 8px;background:#e5f1ec;color:#0f6b54;border-radius:999px;'
   . 'font-size:11px;font-weight:600;">Vérifié</span>le ' . $mod;
echo '</div>';

// ----- Titre (+ effets SEO rank math) -----
echo "<h1 id='titre-principal' class='titre-principal titre-alternatif gradient-text-dark' style='margin:0 0 22px;'>";
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
    if ($post_type === 'liste') { $new_rank_math_title = get_the_title($this_id); }
    else { $new_rank_math_title = "Comparatif : Les ".$total_avis." ".lcfirst($masculinsfeminins ?? 'meilleurs')." ".$type_de_produit_au_pluriel." 2026"; }
    if (($new_rank_math_title <> $rank_math_title) && (($this_id ?? '') <> 4224)) {
        update_post_meta($this_id ?? '', 'rank_math_title', $new_rank_math_title);
    }
}

// ----- Byline (avatar + auteur + date) -----
echo '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px;'
   . 'font-size:13px;color:#6b7480;margin:0 0 26px;">';
if (!empty($author_avatar_id ?? '')) {
    echo wp_get_attachment_image($author_avatar_id, array(30,30), '', array(
        'style' => 'border-radius:50%;display:block;width:30px;height:30px;object-fit:cover;',
        'alt'   => $author_avatar_alt ?? ''));
}
$aid = get_the_author_meta('ID', $author_id ?? '');
echo '<span>Par <a href="' . esc_url(get_author_posts_url($aid)) . '" '
   . 'style="color:#14181d;font-weight:600;">' . esc_html($author ?? '') . '</a></span>';
echo '<span style="color:#9aa3ad;">•</span>';
echo '<span>Mis à jour le ' . $mod . '</span>';
echo '</div>';

// ----- Chapo ($introduction) -----
echo '<div class="text-content" style="font-size:16px;line-height:1.62;color:#2a3038;'
   . 'max-width:720px;margin:0 0 26px;">' . ($introduction ?? '') . '</div>';

// ----- Photo (image mise en avant + badge comparatif) -----
echo '<div style="position:relative;border-radius:12px;overflow:hidden;">';
echo get_the_post_thumbnail($this_id, 'large', array(
    'style' => 'display:block;width:100%;height:340px;object-fit:cover;border-radius:12px;'));
if ($post_type === 'comparatif') {
    echo '<img src="https://meilleurtest.fr/wp-content/uploads/2026/07/badge-meilleurtest.png" alt="" '
       . 'style="position:absolute;top:10px;left:10px;max-width:130px;height:auto;">';
}
echo '</div>';
?>"""

# ============================================================================
# ARBRE
# ============================================================================
add("heroSec", "section", 0, ["heroCont"], {"_padding": pad(0,0,0,0)}, label="Hero 2026")
add("heroCont", "container", "heroSec", ["heroGrid"], {})
add("heroGrid", "block", "heroCont", ["heroLeft", "heroCard"], {
    "_display": "grid", "_gridTemplateColumns": "minmax(0, 1fr) 350px",
    "_gridGap": "40px", "_alignItems": "start", "_padding": pad(24,0,24,0),
    "_gridTemplateColumns:tablet_portrait": "1fr", "_gridGap:tablet_portrait": "32px"})

# --- Colonne gauche : 1 gros bloc code (classes globales pour emettre le CSS) ---
add("heroLeft", "code", "heroGrid", [], {
    "executeCode": True, "parseDynamicData": True, "code": PHP_LEFT, "user_id": 1,
    "_cssGlobalClasses": ["bnxvav", "wyopqz"]})

# --- Colonne droite : encart "Notre enquete" 100% natif ----------------------
add("heroCard", "block", "heroGrid", ["cardHead", "cardGrid", "cardTrust", "cardVote"], {
    "_background": bg(BG2), "_border": border_all(1, LINE, radius=16),
    "_padding": pad(24,24,24,24), "_position": "sticky", "_top": "20px",
    "_alignSelf": "start", "_position:tablet_portrait": "static",
    "_widthMax:tablet_portrait": "520px"})

def fa(eid, parent, cls, size, color, extra=None):
    # Icone Font Awesome via <i class> (CSS, comme dans le site) DANS un text-basic
    # natif -> pas d'element `icon` -> pas de SVG inline -> rien a signer.
    s = {"text": '<i class="%s"></i>' % cls, "tag": "span",
         "_typography": {"font-size": size, "color": col(color)},
         "_display": "flex", "_alignItems": "center"}
    if extra: s.update(extra)
    add(eid, "text-basic", parent, [], s)

# En-tete
add("cardHead", "block", "heroCard", ["cardHeadIco", "cardHeadH"], {
    "_display": "flex", "_direction": "row", "_alignItems": "center", "_gap": "8px",
    "_margin": {"bottom": "18px"}})
fa("cardHeadIco", "cardHead", "fas fa-shield-alt", "16px", ACCENT)
add("cardHeadH", "heading", "cardHead", [], {
    "text": "Notre enquête", "tag": "h3",
    "_typography": {"font-size": "12px", "font-weight": "700", "letter-spacing": "0.07em",
                    "text-transform": "uppercase", "color": col(ACCENT)},
    "_margin": {"top": "0", "bottom": "0"}})

# Grille 2x2 des stats
add("cardGrid", "block", "heroCard", ["cellA", "cellB", "cellC", "cellD"], {
    "_display": "grid", "_gridTemplateColumns": "1fr 1fr", "_gridGap": "1px",
    "_background": bg(LINE), "_border": border_all(1, LINE, radius=10), "_overflow": "hidden"})

def stat_cell(cid, fa_cls, value, label):
    add(cid, "block", "cardGrid", [cid+"i", cid+"t"], {
        "_background": bg(WHITE), "_display": "flex", "_direction": "row",
        "_alignItems": "center", "_gap": "11px", "_padding": pad(14,16,14,16)})
    fa(cid+"i", cid, fa_cls, "19px", ACCENT, extra={"_flexShrink": "0"})
    add(cid+"t", "block", cid, [cid+"n", cid+"l"], {"_display": "flex", "_direction": "column"})
    add(cid+"n", "text-basic", cid+"t", [], {
        "text": value, "tag": "div",
        "_typography": {"font-family": "monospace", "font-size": "18px", "font-weight": "700",
                        "line-height": "1", "letter-spacing": "-0.02em", "color": col(INK)}})
    add(cid+"l", "text-basic", cid+"t", [], {
        "text": label, "tag": "div",
        "_typography": {"font-size": "10.5px", "color": col(MUTED), "line-height": "1.2"},
        "_margin": {"top": "4px"}})

stat_cell("cellA", "fas fa-clock",        "{acf_heures_investies} h",         "de recherche")
stat_cell("cellB", "fas fa-layer-group",  "{acf_sources_consultees}",         "sources consultées")
stat_cell("cellC", "fas fa-tablet-alt",   "{acf_produits_analyses}",          "{acf_type_de_produit_au_pluriel} analysés")
stat_cell("cellD", "fas fa-comments",     "{acf_avis_etudies}",               "avis étudiés")

# Signaux de confiance
add("cardTrust", "block", "heroCard", ["trA", "trB", "trC"], {
    "_display": "flex", "_direction": "column", "_gap": "11px",
    "_margin": {"top": "18px"}, "_padding": {"top": "16px"},
    "_border": {"width": {"top": "1"}, "style": "solid", "color": col(LINE)}})

def trust_row(rid, fa_cls, text):
    add(rid, "block", "cardTrust", [rid+"tile", rid+"t"], {
        "_display": "flex", "_direction": "row", "_alignItems": "center", "_gap": "10px",
        "_typography": {"font-size": "12.5px", "color": col(INK2)}})
    add(rid+"tile", "block", rid, [rid+"i"], {
        "_width": "30px", "_height": "30px", "_flexShrink": "0",
        "_border": {"radius": rad(8)}, "_background": bg(ACCENT_SOFT),
        "_display": "flex", "_alignItems": "center", "_justifyContent": "center"})
    fa(rid+"i", rid+"tile", fa_cls, "16px", ACCENT)
    add(rid+"t", "text-basic", rid, [], {"text": text, "tag": "span"})

trust_row("trA", "fas fa-check-circle", "<b>100&nbsp;% indépendant</b> — sans pub ni sponsor")
trust_row("trB", "fas fa-sync-alt",     "Mis à jour le <b>{post_modified_date:'j F Y'}</b>")
trust_row("trC", "fas fa-book-open",    "<b>{post_reading_time}</b> de lecture")

# Note lecteurs (natif : heading + shortcode + texte)
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
    "text": "Votre note oriente les autres lecteurs et nous aide à améliorer ce contenu. Merci&nbsp;!",
    "tag": "p",
    "_typography": {"font-size": "11.5px", "color": col(MUTED), "line-height": "1.5"},
    "_margin": {"top": "7px", "bottom": "0"}})

# ============================================================================
# Classes globales reutilisees (verbatim export site) : portees par le bloc
# gauche pour que .titre-principal (serif) et .text-content (typo chapo) soient
# emises. Reconnues par ID au collage -> deja compilees, pas de recompilation.
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
]

OUT = {"content": C, "source": "bricksCopiedElements", "sourceUrl": "https://meilleurtest.fr",
       "version": "2.0.2", "globalClasses": GLOBAL_CLASSES, "globalElements": []}

if __name__ == "__main__":
    with open("component-hero-2026-updated.json", "w", encoding="utf-8") as f:
        json.dump(OUT, f, ensure_ascii=False, indent=None)
    n_code = sum(1 for e in C if e["name"] == "code")
    print("OK -> component-hero-2026-updated.json")
    print("Elements :", len(C), "| dont code blocks :", n_code, "| classes globales :", len(GLOBAL_CLASSES))
