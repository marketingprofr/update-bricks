#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generateur du Hero 2026 (meilleurtest.fr) au format bricksCopiedElements.
Cible : template-hero.html  — perimetre HERO SEUL.

LECON (v2, apres test reel) :
  Les petits elements granulaires (icones en `code` executeCode:false, `text-basic`
  imbriques) se collent VIDES : un bloc `code` non-PHP n'apparait pas dans la
  Code review Bricks -> il reste bloque et vide tout le sous-arbre du conteneur.
  => On reconstruit les sections "riches" (eyebrow, byline, carte enquete) en UN
     SEUL bloc `code` PHP autonome (signable), avec STYLES INLINE + SVG en chaine
     HTML. C'est le pattern qui marche (cf. titre/chapo/ariane rendus correctement).
  => On garde en NATIF ce qui a fonctionne : grille 2 col, photo (image+badge),
     zone vote (heading + shortcode + texte).

Apres collage : approuver les blocs `code` dans la Code review Bricks.
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

def code(php, noroot=False, extra=None):
    s = {"executeCode": True, "parseDynamicData": True, "code": php, "user_id": 1}
    if noroot: s["noRoot"] = True
    if extra: s.update(extra)
    return s

# --- SVG (chaines, injectees dans le PHP via tokens) --------------------------
SVG = {
 "shield": '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.4-3 7.4-7 9-4-1.6-7-4.6-7-9V6l7-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
 "clock":  '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
 "layers": '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/></svg>',
 "tablet": '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="18" rx="2"/><line x1="10.5" y1="17.5" x2="13.5" y2="17.5"/></svg>',
 "chat":   '<svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-1.1 4.2A8.5 8.5 0 0 1 12.5 20a8.4 8.4 0 0 1-4.2-1.1L3 20l1.1-5.3A8.4 8.4 0 0 1 3 10.5 8.5 8.5 0 0 1 7.3 3a8.4 8.4 0 0 1 4.2-1.1h.5A8.5 8.5 0 0 1 21 11v.5Z"/></svg>',
 "check":  '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.2 2.2L16 9"/></svg>',
 "refresh":'<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg>',
 "book":   '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6c-1.6-1.2-4-2-7-2v13c3 0 5.4.8 7 2 1.6-1.2 4-2 7-2V4c-3 0-5.4.8-7 2Z"/><path d="M12 6v13"/></svg>',
}
def inject(php):
    for k, v in SVG.items():
        php = php.replace("__SVG_%s__" % k.upper(), v)
    return php

# --- Blocs PHP ----------------------------------------------------------------
PHP_CRUMB = ("<?php if (function_exists('rank_math_the_breadcrumbs')) "
             "{ echo rank_math_the_breadcrumbs(); } ?>")

PHP_TITLE = r"""<?php
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

// === REFERENCEMENT (effets de bord SEO rank math) ===
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
?>"""

# Eyebrow : pill "Verifie" + date de modif (1 bloc, styles inline)
PHP_EYEBROW = r"""<?php
$mod = date_i18n('j F Y', get_the_modified_time('U'));
echo '<span style="display:inline-flex;align-items:center;gap:9px;padding:5px 12px 5px 8px;'
   . 'background:#fff;border:1px solid #e8eaed;border-radius:999px;font-size:12px;'
   . 'font-weight:500;color:#2a3038;">';
echo '<span style="padding:2px 8px;background:#e5f1ec;color:#0f6b54;border-radius:999px;'
   . 'font-size:11px;font-weight:600;">Vérifié</span>';
echo 'le ' . $mod;
echo '</span>';
?>"""

# Byline : avatar + Par <auteur> + date (1 bloc, styles inline)
PHP_BYLINE = r"""<?php
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));
$mod = date_i18n('j F Y', get_the_modified_time('U'));
echo '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px;'
   . 'font-size:13px;color:#6b7480;">';
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
?>"""

PHP_LEDE = r"""<?php
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));
?>
<div class="text-content"><?php echo $introduction ?? ''; ?></div>"""

# Carte "Notre enquete" : en-tete + grille 2x2 stats + signaux de confiance
# (1 bloc PHP autonome, styles inline + SVG en chaine HTML)
PHP_CARDBODY = r"""<?php
$this_id = get_the_ID();
extract(get_all_template_variables($this_id));

$ACC='#0f6b54'; $ACCS='#e5f1ec'; $INK='#14181d'; $INK2='#2a3038'; $MUT='#6b7480'; $LINE='#e8eaed';

// Libelle "produits analyses" (accord en genre)
$tp = $type_de_produit_au_pluriel ?? '';
if (strlen($tp) >= 22) { $lbl_prod = 'Produits analysés'; }
else { $lbl_prod = ($tp ?: 'Produits') . ((($masculinsfeminins ?? '') == 'Meilleures') ? ' analysées' : ' analysés'); }

// En-tete
echo '<div style="display:flex;align-items:center;gap:8px;color:'.$ACC.';margin-bottom:18px;">';
echo '__SVG_SHIELD__';
echo '<h3 style="margin:0;font-size:12px;font-weight:700;letter-spacing:.07em;'
   . 'text-transform:uppercase;color:'.$ACC.';">Notre enquête</h3>';
echo '</div>';

// Grille 2x2 des stats
$cells = array(
  array('__SVG_CLOCK__',  esc_html(($heures_investies ?? '')).' h', 'de recherche'),
  array('__SVG_LAYERS__', esc_html($sources_consultees ?? ''),      'sources consultées'),
  array('__SVG_TABLET__', esc_html($produits_analyses ?? ''),       $lbl_prod),
  array('__SVG_CHAT__',   esc_html($avis_etudies ?? ''),            'avis étudiés'),
);
echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:1px;background:'.$LINE.';'
   . 'border:1px solid '.$LINE.';border-radius:10px;overflow:hidden;">';
foreach ($cells as $c) {
    echo '<div style="background:#fff;padding:14px 16px;display:flex;align-items:center;gap:11px;">';
    echo '<span style="color:'.$ACC.';display:flex;flex-shrink:0;">'.$c[0].'</span>';
    echo '<div><div style="font-family:ui-monospace,monospace;font-size:18px;font-weight:700;'
       . 'letter-spacing:-.02em;line-height:1;color:'.$INK.';">'.$c[1].'</div>';
    echo '<div style="font-size:10.5px;color:'.$MUT.';margin-top:4px;line-height:1.2;">'.$c[2].'</div></div>';
    echo '</div>';
}
echo '</div>';

// Signaux de confiance
$mod = date_i18n('j F Y', get_the_modified_time('U'));
$wc  = str_word_count(strip_tags(strip_shortcodes(get_post_field('post_content', $this_id))));
$rt  = max(1, (int) round($wc / 200));
$rows = array(
  array('__SVG_CHECK__',   '<b>100&nbsp;% indépendant</b> — sans pub ni sponsor'),
  array('__SVG_REFRESH__', 'Mis à jour le <b>'.$mod.'</b>'),
  array('__SVG_BOOK__',    '<b>'.$rt.' min</b> de lecture'),
);
echo '<div style="margin-top:18px;padding-top:16px;border-top:1px solid '.$LINE.';'
   . 'display:flex;flex-direction:column;gap:11px;">';
foreach ($rows as $r) {
    echo '<div style="display:flex;align-items:center;gap:10px;font-size:12.5px;color:'.$INK2.';">';
    echo '<span style="width:30px;height:30px;flex-shrink:0;border-radius:8px;background:'.$ACCS.';'
       . 'color:'.$ACC.';display:flex;align-items:center;justify-content:center;">'.$r[0].'</span>';
    echo '<span>'.$r[1].'</span>';
    echo '</div>';
}
echo '</div>';
?>"""

# ============================================================================
# ARBRE
# ============================================================================
add("heroSec", "section", 0, ["heroCont"], {"_padding": pad(0,0,0,0)}, label="Hero 2026")
add("heroCont", "container", "heroSec", ["heroCrumb", "heroGrid"], {})

add("heroCrumb", "code", "heroCont", [],
    code(PHP_CRUMB, noroot=True,
         extra={"_margin": {"bottom": "4px"},
                "_typography": {"font-size": "13px", "color": col(MUTED)}}))

add("heroGrid", "block", "heroCont", ["heroMain", "heroCard"], {
    "_display": "grid", "_gridTemplateColumns": "minmax(0, 1fr) 350px",
    "_gridGap": "40px", "_alignItems": "start", "_padding": pad(24,0,24,0),
    "_gridTemplateColumns:tablet_portrait": "1fr", "_gridGap:tablet_portrait": "32px"})

# ---- Colonne gauche --------------------------------------------------------
add("heroMain", "block", "heroGrid",
    ["heroEye", "heroTitle", "heroByl", "heroLede", "heroPhoto"],
    {"_display": "flex", "_direction": "column"})

add("heroEye", "code", "heroMain", [],
    code(PHP_EYEBROW, extra={"_alignSelf": "flex-start", "_margin": {"bottom": "20px"}}))

add("heroTitle", "code", "heroMain", [],
    code(PHP_TITLE, extra={"_cssGlobalClasses": ["bnxvav", "uemizu"], "_margin": {"bottom": "22px"}}))

add("heroByl", "code", "heroMain", [], code(PHP_BYLINE, extra={"_margin": {"bottom": "26px"}}))

add("heroLede", "code", "heroMain", [],
    code(PHP_LEDE, extra={"_cssGlobalClasses": ["wyopqz", "vrjaxu"],
                          "_typography": {"font-size": "16px", "color": col(INK2), "line-height": "1.62"},
                          "_margin": {"bottom": "26px"}}))

add("heroPhoto", "block", "heroMain", ["heroImg", "heroBadge"],
    {"_position": "relative", "_overflow": "hidden", "_border": {"radius": rad(12)}})
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
add("heroCard", "block", "heroGrid", ["cardBody", "cardVote"], {
    "_background": bg(BG2), "_border": border_all(1, LINE, radius=16),
    "_padding": pad(24,24,24,24), "_position": "sticky", "_top": "20px",
    "_alignSelf": "start", "_position:tablet_portrait": "static",
    "_widthMax:tablet_portrait": "520px"})

# Corps de carte : en-tete + stats + confiance (1 bloc PHP signable)
add("cardBody", "code", "heroCard", [], code(inject(PHP_CARDBODY)))

# Zone vote (natif : a fonctionne au test)
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
# Classes globales reutilisees (verbatim export site) : reconnues par ID,
# deja compilees -> .titre-principal / .text-content actifs sans recompilation.
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
        "_widthMax": "720px", "_padding:mobile_portrait": {"bottom": "var(--at-space--s)"}},
     "modified": 1761568866298, "user_id": 1},
]

OUT = {"content": C, "source": "bricksCopiedElements", "sourceUrl": "https://meilleurtest.fr",
       "version": "2.0.2", "globalClasses": GLOBAL_CLASSES, "globalElements": []}

if __name__ == "__main__":
    with open("component-hero-2026-updated.json", "w", encoding="utf-8") as f:
        json.dump(OUT, f, ensure_ascii=False, indent=None)
    print("OK -> component-hero-2026-updated.json")
    print("Elements :", len(C), "| Classes globales :", len(GLOBAL_CLASSES))
