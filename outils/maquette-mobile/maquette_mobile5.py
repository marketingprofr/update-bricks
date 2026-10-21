"""Maquette hors ligne (navigateur de test seulement, rien n'est écrit sur le site) : climatiseur mobile à 390 px,
avant / après (Notre sélection en tête, sommaire collant, un gagnant détaillé par section, les autres en lignes)."""
import json, sys
from playwright.sync_api import sync_playwright

SP = 'C:/Users/Samuel/AppData/Local/Temp/claude/C--Webdev-wp-avis/45747f50-1db6-465a-9518-dd6d5b9da446/scratchpad/'
OUT = 'C:/Webdev/wp-avis/maquette-mobile-multi/v4-'
UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Mobile Safari/537.36 mt-templates-lecture'
HERO_CSS = open('C:/Webdev/update-bricks-templates/php-css/v2/multi-hero-gauche.css', encoding='utf-8').read()
APRES = open(SP + 'maquette_apres4.js', encoding='utf-8').read()
NETTOIE = "document.querySelectorAll('.cmplz-cookiebanner, #cmplz-cookiebanner-container, .cmplz-soft-cookiewall').forEach(e => e.remove())"
MESURE = open(SP + 'maquette_mesure2.js', encoding='utf-8').read()

res = {}
with sync_playwright() as pw:
    b = pw.chromium.launch()
    for etat in ['avant', 'apres']:
        p = b.new_page(viewport={'width': 390, 'height': 844}, user_agent=UA, is_mobile=True, has_touch=True, device_scale_factor=1)
        p.goto('https://meilleurtest.fr/comparatif-climatiseur-mobile/?nc=mq4' + etat, wait_until='load', timeout=90000)
        p.wait_for_timeout(1500)
        p.evaluate(NETTOIE)
        p.evaluate("[...document.querySelectorAll('img[loading=lazy]')].forEach(i => i.loading = 'eager')")
        if etat == 'apres':
            p.evaluate(APRES, HERO_CSS)
        p.wait_for_timeout(2500)
        m = p.evaluate(MESURE)
        res[etat] = m
        print(etat, m)
        p.evaluate("document.querySelectorAll('#mq-som').forEach(e => e.style.display = 'none')")
        p.screenshot(path=OUT + etat + '-page.png', clip={'x': 0, 'y': 0, 'width': 390, 'height': m['avis_px'] + 300}, full_page=True)
        if etat == 'apres':
            p.evaluate("document.querySelectorAll('#mq-som').forEach(e => e.style.display = '')")
            for nomf, ouvert in [('apres-sommaire-ferme', 'false'), ('apres-sommaire-ouvert', 'true')]:
                p.evaluate("window.scrollTo(0, document.querySelector('#silencieux').getBoundingClientRect().top + scrollY - 120); window.__mqMaj();")
                p.evaluate("document.getElementById('mq-som').classList.toggle('ouvert', " + ouvert + ")")
                p.wait_for_timeout(500)
                p.screenshot(path=OUT + nomf + '.png')
        p.close()
    b.close()
json.dump(res, open(OUT + 'mesures.json', 'w'), indent=1)
