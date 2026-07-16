import json

# ---- Palette de la maquette template-header.html -------------------------
INK         = "#14181d"
INK2        = "#2a3038"
MUTED       = "#6b7480"
LINE        = "#e8eaed"
BG3         = "#f5f6f8"
ACCENT      = "#0f6b54"
ACCENT_SOFT = "#e5f1ec"
UTIL_TEXT   = "#cfd4d9"
WHITE       = "#ffffff"
TRANSPARENT = {"rgb": "rgba(0,0,0,0)"}
SITEBOX     = "var(--at-site-box-max-width)"   # 1140px (token Advanced Themer)

NOMARGIN = {"top": "0", "bottom": "0", "left": "0", "right": "0"}
ZERO_SHADOW = {"values": {"offsetX": "0", "offsetY": "0", "blur": "0", "spread": "0"}, "color": {"rgb": "rgba(0,0,0,0)"}}

# wrapper interne centre a la largeur du site-box (architecture section > conteneur > contenu)
def wrap(extra):
    s = {"_width": "100%", "_widthMax": SITEBOX, "_margin": {"left": "auto", "right": "auto"}}
    s.update(extra)
    return s

def action_btn(extra):
    s = {
        "_background": {"color": TRANSPARENT}, "_background:hover": {"color": TRANSPARENT},
        "_border": {"style": "none"}, "_border:hover": {"style": "none"},
        "_boxShadow": ZERO_SHADOW, "_boxShadow:hover": ZERO_SHADOW,
        "_padding": {"top": "6", "bottom": "6", "left": "6", "right": "6"},
        "_typography": {"color": {"hex": INK2}, "font-size": "13px", "font-weight": "500"},
        "_typography:hover": {"color": {"hex": ACCENT}},
        "_gap": "6",
        "_cssCustom": "@media (max-width:767px){%root% .lbl{display:none;}%root%{column-gap:0;}}",
    }
    s.update(extra)
    return s

# ==========================================================================
# ARCHITECTURE : section (fond pleine largeur) > wrapper (max-width site-box) > contenu
# ==========================================================================
content = [

    {"id": "sechdr", "name": "section", "parent": 0,
     "children": ["utilbr", "cnthdr", "catsrw", "trstbn"],
     "settings": {"tag": "section", "_padding": NOMARGIN, "_margin": NOMARGIN,
                  "_background": {"color": {"hex": WHITE}}, "_position": "relative",
                  "_display": "flex", "_direction": "column", "_width": "100%",
                  "_rowGap": "0", "_columnGap": "0"},
     "label": "C - Header -- Main 2026"},

    # ===================== BANDE 1 : barre d'utilite (noire) =====================
    {"id": "utilbr", "name": "block", "parent": "sechdr", "children": ["utilwr"],
     "settings": {"_display": "block", "_width": "100%", "_margin": NOMARGIN,
                  "_background": {"color": {"hex": INK}},
                  "_typography": {"color": {"hex": UTIL_TEXT}, "font-size": "12px"}},
     "label": "Header -- Utility bar (band)"},
    {"id": "utilwr", "name": "block", "parent": "utilbr", "children": ["utiltl", "utiltr"],
     "settings": wrap({"_display": "flex", "_direction": "row", "_justifyContent": "space-between",
                       "_alignItems": "center", "_columnGap": "16", "_flexWrap": "wrap",
                       "_padding": {"top": "8", "bottom": "8", "left": "40", "right": "40"},
                       "_padding:tablet_portrait": {"top": "8", "bottom": "8", "left": "20", "right": "20"},
                       "_justifyContent:mobile_portrait": "flex-start"}),
     "label": "Utility -- Wrap"},
    {"id": "utiltl", "name": "text-basic", "parent": "utilwr", "children": [],
     "settings": {"text": "<b style=\"color:#fff;font-weight:600\">Meilleurtest</b> &mdash; Comparatifs ind&eacute;pendants depuis 2018", "tag": "div"},
     "label": "Utility -- Brand"},
    {"id": "utiltr", "name": "text-basic", "parent": "utilwr", "children": [],
     "settings": {"text": "<span style=\"color:#6dd6a8\">&#10003;</span> 0% pub &nbsp; <span style=\"color:#6dd6a8\">&#10003;</span> 0% sponsoris&eacute; &nbsp; <span style=\"color:#6dd6a8\">&#10003;</span> 100% ind&eacute;pendant",
                  "tag": "div", "_display:mobile_portrait": "none"},
     "label": "Utility -- Promise"},

    # ===================== BANDE 2 : header principal =====================
    {"id": "cnthdr", "name": "block", "parent": "sechdr", "children": ["cntwr"],
     "settings": {"_display": "block", "_width": "100%", "_margin": NOMARGIN,
                  "_background": {"color": {"hex": WHITE}},
                  "_border": {"width": {"bottom": "1"}, "style": "solid", "color": {"hex": LINE}}},
     "label": "Header -- Main row (band)"},
    {"id": "cntwr", "name": "block", "parent": "cnthdr", "children": ["navmob", "lgohdr", "srchdr", "btncnt"],
     "settings": wrap({"_display": "flex", "_direction": "row", "_alignItems": "center", "_flexWrap": "nowrap",
                       "_columnGap": "32",
                       "_padding": {"top": "18", "bottom": "18", "left": "40", "right": "40"},
                       "_padding:tablet_portrait": {"top": "14", "bottom": "14", "left": "20", "right": "20"},
                       "_flexWrap:tablet_portrait": "wrap", "_justifyContent:tablet_portrait": "flex-start",
                       "_columnGap:tablet_portrait": "16", "_rowGap:tablet_portrait": "12",
                       "_justifyContent:mobile_landscape": "space-between"}),
     "label": "Main -- Wrap"},

    {"id": "navmob", "name": "nav-menu", "parent": "cntwr", "children": [],
     "settings": {"menu": "13", "mobileMenu": "tablet_portrait",
                  "menuTypography": {"color": {"hex": INK}},
                  "_display": "none", "_display:tablet_portrait": "flex",
                  "_width": "auto", "_flexShrink": "0",
                  "_order:tablet_portrait": "1", "_justifyContent": "flex-start", "_alignSelf": "center"},
     "label": "Header -- Mobile nav (burger)"},

    {"id": "lgohdr", "name": "logo", "parent": "cntwr", "children": [],
     "settings": {"logo": {"id": 245022, "filename": "merrilowgo.png", "size": "full",
                           "full": "https://meilleurtest.fr/wp-content/uploads/2025/11/merrilowgo.png",
                           "url": "https://meilleurtest.fr/wp-content/uploads/2025/11/merrilowgo.png"},
                  "logoText": "Meilleurtest.fr - Logo",
                  "_widthMax": "180", "_alignSelf": "center", "_widthMax:mobile_landscape": "150",
                  "_flexShrink": "0", "_order:tablet_portrait": "2"}},

    {"id": "srchdr", "name": "search", "parent": "cntwr", "children": [],
     "settings": {
        "icon": {"library": "fontawesomeSolid", "icon": "fas fa-magnifying-glass"},
        "_background": {"color": {"hex": BG3}},
        "_border": {"style": "none", "radius": {"top": "999", "right": "999", "bottom": "999", "left": "999"}},
        "_padding": {"top": "8", "bottom": "8", "left": "20", "right": "20"},
        "placeholder": "Comment pouvons-nous vous aider ?",
        "placeholderColor": {"hex": MUTED}, "iconColor": {"hex": MUTED}, "iconWidth": "16px",
        "inputTypography": {"font-size": "14px", "color": {"hex": INK}},
        "_display": "flex", "_overflow": "hidden", "inputWidth": "100%", "_flexGrow": "1",
        "_order:tablet_portrait": "4", "_width:tablet_portrait": "100%",
        "_cssCustom": "%root% form{display:flex;flex-direction:row-reverse;justify-content:flex-end;align-items:center;gap:10px;}\n%root% input{padding:0;background:transparent;text-align:left;}\n%root% input:focus{outline:none;}\n%root% i,%root% svg{font-size:16px;}\n"}},

    {"id": "btncnt", "name": "block", "parent": "cntwr", "children": ["btnfav", "btncon", "btncad"],
     "settings": {"_display": "flex", "_direction": "row", "_alignItems": "center",
                  "_columnGap": "18", "_width": "auto", "_flexShrink": "0", "_margin": NOMARGIN,
                  "_order:tablet_portrait": "3",
                  "_margin:tablet_portrait": {"left": "auto"}, "_margin:mobile_landscape": {"left": "0"},
                  "_columnGap:mobile_portrait": "10"},
     "label": "Header -- Actions"},
    {"id": "btnfav", "name": "button", "parent": "btncnt", "children": [],
     "settings": action_btn({"link": {"type": "internal", "url": "#", "postId": "144254", "rel": "nofollow"},
                             "text": "<span class=\"lbl\">Favoris</span>",
                             "icon": {"library": "fontawesomeRegular", "icon": "fa fa-heart"}, "iconPosition": "left"}),
     "label": "Action -- Favoris"},
    {"id": "btncon", "name": "button", "parent": "btncnt", "children": [],
     "settings": action_btn({"link": {"type": "internal", "postId": "144228", "url": "#"},
                             "text": "<span class=\"lbl\">Connexion</span>",
                             "icon": {"library": "fontawesomeRegular", "icon": "fa fa-user"}, "iconPosition": "left"}),
     "label": "Action -- Connexion"},
    {"id": "btncad", "name": "button", "parent": "btncnt", "children": [],
     "settings": action_btn({"link": {"type": "external", "url": "#"},
                             "text": "<span class=\"lbl\">Cadeaux</span>",
                             "icon": {"library": "fontawesomeSolid", "icon": "fas fa-gift"}, "iconPosition": "left"}),
     "label": "Action -- Cadeaux"},

    # ===================== BANDE 3 : categories (menu WP 13) =====================
    {"id": "catsrw", "name": "block", "parent": "sechdr", "children": ["catswr"],
     "settings": {"_display": "block", "_display:tablet_portrait": "none",  # cachee <=991 (burger)
                  "_width": "100%", "_margin": NOMARGIN,
                  "_background": {"color": {"hex": WHITE}},
                  "_border": {"width": {"bottom": "1"}, "style": "solid", "color": {"hex": LINE}}},
     "label": "Header -- Categories row (band)"},
    {"id": "catswr", "name": "block", "parent": "catsrw", "children": ["navcat"],
     "settings": wrap({"_display": "flex", "_alignItems": "center", "_justifyContent": "flex-start",
                       "_overflowX": "auto",
                       "_padding": {"top": "8", "bottom": "8", "left": "28", "right": "28"},
                       "_padding:tablet_portrait": {"top": "8", "bottom": "8", "left": "20", "right": "20"}}),
     "label": "Categories -- Wrap"},
    {"id": "navcat", "name": "nav-menu", "parent": "catswr", "children": [],
     "settings": {"menu": "13", "menuGap": "16", "menuMargin": {"left": "0"},
                  "_width": "auto", "_justifyContent": "flex-start",
                  "menuTypography": {"font-size": "14px", "color": {"hex": INK2}, "font-weight": "500"},
                  "menuBorder": {"width": {"bottom": "2"}, "style": "solid", "color": {"hex": WHITE}},
                  "menuBorder:hover": {"width": {"bottom": "2"}, "style": "solid", "color": {"hex": ACCENT}},
                  "mobileMenu": "tablet_portrait",
                  "_cssCustom": "%root% li.current-menu-item > a,%root% li.current_page_item > a,%root% li.current-menu-parent > a{color:#0f6b54;font-weight:600;border-bottom-color:#0f6b54;}\n"}},

    # ===================== BANDE 4 : bandeau de confiance (vert) =====================
    {"id": "trstbn", "name": "block", "parent": "sechdr", "children": ["trstwr"],
     "settings": {"_display": "block", "_width": "100%", "_margin": NOMARGIN,
                  "_background": {"color": {"hex": ACCENT_SOFT}},
                  "_typography": {"color": {"hex": ACCENT}, "font-size": "13px", "text-align": "center"}},
     "label": "Header -- Trust banner (band)"},
    {"id": "trstwr", "name": "block", "parent": "trstbn", "children": ["trsttx"],
     "settings": wrap({"_display": "block",
                       "_padding": {"top": "10", "bottom": "10", "left": "40", "right": "40"},
                       "_padding:tablet_portrait": {"top": "10", "bottom": "10", "left": "20", "right": "20"}}),
     "label": "Trust -- Wrap"},
    {"id": "trsttx", "name": "text-basic", "parent": "trstwr", "children": [],
     "settings": {"text": "Nos recommandations sont 100% ind&eacute;pendantes&nbsp;: sans pub ni cadeau des marques. Nous pouvons toucher une commission si vous achetez via nos liens.", "tag": "div"},
     "label": "Trust -- Text"},
]

payload = {
    "content": content,
    "source": "bricksCopiedElements",
    "sourceUrl": "https://meilleurtest.fr",
    "version": "2.0.2",
    "globalClasses": [],
    "globalElements": []
}

out = "/home/user/update-bricks/component-header-2026-updated.json"
with open(out, "w", encoding="utf-8") as f:
    json.dump(payload, f, ensure_ascii=False, separators=(",", ":"))

# ---- validations ----------------------------------------------------------
reparsed = json.load(open(out, encoding="utf-8"))
ids = [e["id"] for e in reparsed["content"]]
assert len(ids) == len(set(ids)), "IDs dupliques"
idset = set(ids) | {0}
for e in reparsed["content"]:
    for c in e["children"]:
        assert c in idset, f"child manquant: {c}"
    assert e["parent"] in idset, f"parent manquant: {e['parent']}"
raw = open(out, encoding="utf-8").read()
assert "header-15" not in raw and "_cssGlobalClasses" not in raw
n_wrap = sum(1 for e in reparsed["content"] if e["settings"].get("_widthMax") == SITEBOX)
print("OK - JSON valide")
print("elements:", len(reparsed["content"]), "| wrappers max-width:", n_wrap, "| classes:", len(reparsed["globalClasses"]))
print("arbre coherent: OK")
