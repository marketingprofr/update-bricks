"""Vérifie que les fichiers à coller de php-css/multi-v3/ sont des copies exactes des fichiers de référence.
Usage : python outils/verifier-multi-v3.py   (--recopier pour les mettre à jour depuis les références)"""
import shutil
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
]
ecarts = 0
for nom, php, css in BLOCS:
    for src, ext in ((php, '.code.php'), (css, '.css')):
        copie = P / 'multi-v3' / f'multi-v3-{nom}{ext}'
        if '--recopier' in sys.argv:
            shutil.copyfile(P / src, copie)
        same = copie.exists() and copie.read_bytes() == (P / src).read_bytes()
        ecarts += 0 if same else 1
        print(('identique ' if same else 'DIFFÉRENT ') + copie.name + '  <-  ' + src)
sys.exit(1 if ecarts else 0)
