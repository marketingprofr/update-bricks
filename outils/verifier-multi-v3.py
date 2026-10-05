"""Vérifie que les fichiers à coller de php-css/multi-v3/ sont des copies exactes des fichiers de référence.
Les CSS à coller sont livrés SANS commentaires (Bricks les imprime tels quels dans chaque page ; suggestion de
l'instance Technique et de Samuel, 2026-10-05) : les commentaires restent dans les fichiers de référence.
Usage : python outils/verifier-multi-v3.py   (--recopier pour les mettre à jour depuis les références)"""
import re
import sys
from pathlib import Path

P = Path(__file__).resolve().parent.parent / 'php-css'
BLOCS = [
    ('1-hero-gauche', 'v2/multi-hero-gauche.code.php', 'v2/multi-hero-gauche.css'),
    ('2-encadre-confiance', 'hero-encart.code.php', 'hero-encart.code.css'),
    ('3-sommaire', 'v2/multi-sommaire.code.php', 'v2/multi-sommaire.css'),
    ('4-resume-top5', 'v2/multi-resume.code.php', 'v2/multi-resume.css'),
    ('5-tests-complets', 'v2/multi-tests.code.php', 'v2/multi-tests.css'),
    ('6-faq', 'faq.code.php', 'faq.css'),
    ('7-tableau', 'v2/multi-tableau.code.php', 'v2/multi-tableau.css'),  # ajouté le 2026-10-04 (3 produits par sous-comparatif)
    ('8-choix', 'choix.code.php', 'choix.css'),  # ajouté le 2026-10-05 (titres d'option en H3)
    ('9-colonne-gauche', 'v2/multi-colonne-gauche.code.php', 'v2/multi-colonnes.css'),  # 2026-10-05, colonnes du guide (Game8)
    ('10-colonne-droite', 'v2/multi-colonne-droite.code.php', None),  # onglet CSS vide : le CSS est dans l'élément 9
]


def sans_commentaires(css):
    """Retire les commentaires /* */ hors des chaînes, puis les lignes devenues vides et les blancs de fin de ligne."""
    out, i, n = [], 0, len(css)
    while i < n:
        c = css[i]
        if c in '"\'':
            j = i + 1
            while j < n and css[j] != c:
                j += 2 if css[j] == '\\' else 1
            out.append(css[i:j + 1])
            i = j + 1
        elif css.startswith('/*', i):
            j = css.find('*/', i + 2)
            assert j >= 0, 'commentaire non fermé'
            i = j + 2
        else:
            out.append(c)
            i += 1
    nl = '\r\n' if '\r\n' in css else '\n'
    lignes = [l.rstrip() for l in ''.join(out).replace('\r\n', '\n').split('\n')]
    return nl.join(l for l in lignes if l.strip()) + nl


ecarts = 0
for nom, php, css in BLOCS:
    for src, ext in ((php, '.code.php'), (css, '.css')):
        if src is None:
            continue
        copie = P / 'multi-v3' / f'multi-v3-{nom}{ext}'
        attendu = (P / src).read_bytes()
        if ext == '.css':
            attendu = sans_commentaires(attendu.decode('utf-8')).encode('utf-8')
        if '--recopier' in sys.argv:
            copie.write_bytes(attendu)
        same = copie.exists() and copie.read_bytes() == attendu
        ecarts += 0 if same else 1
        print(('identique ' if same else 'DIFFÉRENT ') + copie.name + '  <-  ' + src)
sys.exit(1 if ecarts else 0)
