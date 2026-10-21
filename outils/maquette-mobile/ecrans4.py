from playwright.sync_api import sync_playwright
UA='Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130 Mobile Safari/537.36 mt-templates-lecture'
CSS=open('C:/Webdev/update-bricks-templates/php-css/v2/multi-hero-gauche.css',encoding='utf-8').read()
JS=open('maquette_apres4.js',encoding='utf-8').read()
OUT='C:/Webdev/wp-avis/maquette-mobile-multi/'
with sync_playwright() as pw:
    b=pw.chromium.launch()
    for etat in ('avant','apres'):
        p=b.new_page(viewport={'width':390,'height':844},user_agent=UA,is_mobile=True,has_touch=True,device_scale_factor=2)
        p.goto('https://meilleurtest.fr/comparatif-climatiseur-mobile/?nc=e'+etat,wait_until='load',timeout=90000); p.wait_for_timeout(1200)
        p.evaluate("document.querySelectorAll('.cmplz-cookiebanner, #cmplz-cookiebanner-container, .cmplz-soft-cookiewall').forEach(e => e.remove())")
        if etat=='apres': p.evaluate(JS, CSS)
        p.wait_for_timeout(1500)
        for k,y in enumerate((0, 844)):
            p.evaluate(f"window.scrollTo(0,{y}); window.__mqMaj && window.__mqMaj()"); p.wait_for_timeout(400)
            p.screenshot(path=f'{OUT}v4-{etat}-ecran{k+1}.png')
        p.close()
    b.close()
print('ok')
