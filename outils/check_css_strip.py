"""Le CSS sans commentaires doit donner exactement les mêmes règles que la référence, une fois lu par Chrome."""
import re
from pathlib import Path
from playwright.sync_api import sync_playwright

P = Path('C:/Webdev/update-bricks-templates/php-css')
src = open('C:/Webdev/update-bricks-templates/outils/verifier-multi-v3.py', encoding='utf-8').read()
paires = re.findall(r"\('(\d+-[a-z0-9-]+)', '[^']+', '([^']+\.css)'\)", src)
JS = """css => { const s = new CSSStyleSheet(); s.replaceSync(css);
  const out = []; const walk = rs => { for (const r of rs) { if (r.cssRules) { out.push(r.cssText.split('{')[0]); walk(r.cssRules); } else out.push(r.cssText); } };
  walk(s.cssRules); return out; }"""
with sync_playwright() as pw:
    b = pw.chromium.launch()
    p = b.new_page()
    p.set_content('<html><body></body></html>')
    ok = True
    for nom, ref in paires:
        a = p.evaluate(JS, (P / ref).read_text(encoding='utf-8'))
        c = p.evaluate(JS, (P / 'multi-v3' / f'multi-v3-{nom}.css').read_text(encoding='utf-8'))
        same = a == c
        ok &= same
        print(('identique ' if same else 'DIFFÉRENT '), nom, len(a), 'règles')
    print('TOUT IDENTIQUE' if ok else 'ÉCART')
    b.close()
