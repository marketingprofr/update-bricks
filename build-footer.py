#!/usr/bin/env python3
# =====================================================================
# build-footer.py — Footer meilleurtest.fr : ajoute la rangée de confiance
# (logo + badges SVG) EN HAUT du footer existant (composant « footer-3 »).
#
# Entrées :
#   - templates/footer-base.json      : le footer actuel (bricksCopiedElements)
#   - php-css/footer-top.code.html     : le markup de la rangée (logo + <img> SVG)
# Sortie :
#   - templates/footer-2026-updated.json : footer complet à coller dans Bricks
#
# Modifs appliquées :
#   1. Insère un élément `code` (langage HTML) en 1er enfant du container
#      -> rangée logo + badges, bornée par le container (aligné au reste).
#   2. Retire le `logo` de la colonne gauche (id ddqsop) pour éviter le
#      doublon (le logo vit désormais dans la rangée du haut). La colonne
#      gauche garde description + réseaux sociaux.
#
# ⚠️ L'élément `code` a `executeCode:true` -> à APPROUVER dans la Code review
#    Bricks après collage (rendu HTML). Pas de PHP, pas de SVG inline.
# =====================================================================
import json
import os

HERE = os.path.dirname(os.path.abspath(__file__))
BASE = os.path.join(HERE, "templates", "footer-base.json")
STRIP = os.path.join(HERE, "php-css", "footer-top.code.html")
OUT = os.path.join(HERE, "templates", "footer-2026-updated.json")

CONTAINER_ID = "anjkfm"   # le `container` (boxé) du footer
LEFT_COL_ID = "idmxlu"    # « Container left » (logo + desc + réseaux)
LOGO_ID = "ddqsop"        # le `logo` à retirer de la colonne gauche
STRIP_ID = "ftrust0"      # id de la nouvelle rangée (Bricks régénère au collage)


def main():
    with open(BASE, "r", encoding="utf-8") as f:
        data = json.load(f)
    with open(STRIP, "r", encoding="utf-8") as f:
        strip_html = f.read().strip()

    content = data["content"]

    # 1. Retirer le logo dupliqué de la colonne gauche
    content = [el for el in content if el["id"] != LOGO_ID]
    for el in content:
        if el["id"] == LEFT_COL_ID:
            el["children"] = [c for c in el["children"] if c != LOGO_ID]

    # 2. Élément `code` = rangée de confiance (logo + badges SVG)
    strip_el = {
        "id": STRIP_ID,
        "name": "code",
        "parent": CONTAINER_ID,
        "children": [],
        "settings": {
            "code": strip_html,
            "language": "html",
            "executeCode": True,
        },
        "label": "Footer -- Trust strip",
    }

    # Insérer l'élément juste après le container, et en tête de ses enfants
    for i, el in enumerate(content):
        if el["id"] == CONTAINER_ID:
            el["children"] = [STRIP_ID] + el["children"]
            content.insert(i + 1, strip_el)
            break

    data["content"] = content

    with open(OUT, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, separators=(",", ":"))

    print("OK ->", OUT)
    print("  éléments :", len(data["content"]))
    print("  logo retiré de la colonne gauche :", LOGO_ID)
    print("  rangée de confiance insérée :", STRIP_ID, "(code/html, executeCode)")


if __name__ == "__main__":
    main()
