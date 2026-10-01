"""Export Search Console pour l'arbitrage des sous-comparatifs de meilleurtest.fr.

Produit deux fichiers (séparateur « ; », UTF-8 avec BOM) :
  - gsc-pages-<date>.csv : clics, impressions et position moyenne par URL sur
    4 fenêtres (3 derniers mois, 16 mois, avant la chute, après la chute).
    Les variantes d'une même page sont additionnées : fragments (#toc-item-5)
    et ancien format /comparatif/{slug}/ ramené à /comparatif-{slug}/.
  - gsc-top-requetes-sections-<date>.csv : les 5 premières requêtes (16 mois)
    de chaque URL listée dans le fichier des sections à discuter.

Usage :
  pip install google-api-python-client google-auth
  python scripts/gsc_export.py --key CHEMIN/cle.json \
      --sections data/2026-10-01/sections-a-discuter-2026-10-01.csv \
      --out data/2026-10-01

La clé est celle du compte de service
claude-seo@spherical-bloom-480416-p6.iam.gserviceaccount.com.
Ne jamais committer la clé.
"""
import argparse
import csv
import datetime as dt
import re
import sys
import time
from collections import defaultdict
from pathlib import Path

from google.oauth2 import service_account
from googleapiclient.discovery import build

PROPERTY = "sc-domain:meilleurtest.fr"
SCOPES = ["https://www.googleapis.com/auth/webmasters.readonly"]
BATCH = 25000


def normalize(url):
    url = url.split("#", 1)[0]
    return re.sub(r"(meilleurtest\.fr)/comparatif/([^/?]+)/?", r"\1/comparatif-\2/", url)


def query(service, body, retries=5):
    for attempt in range(retries):
        try:
            return service.searchanalytics().query(siteUrl=PROPERTY, body=body).execute()
        except Exception as exc:  # quota ou erreur réseau : on réessaie
            wait = 2 ** attempt * 5
            print(f"  erreur API ({exc.__class__.__name__}), nouvel essai dans {wait} s", file=sys.stderr)
            time.sleep(wait)
    raise RuntimeError("API Search Console indisponible")


def pages_for_window(service, start, end):
    """Métriques par URL normalisée sur une fenêtre de dates."""
    agg = defaultdict(lambda: [0.0, 0.0, 0.0])  # clics, impressions, somme(position x impressions)
    start_row = 0
    while True:
        resp = query(service, {
            "startDate": start, "endDate": end, "type": "web",
            "dimensions": ["page"], "rowLimit": BATCH, "startRow": start_row,
        })
        rows = resp.get("rows", [])
        for r in rows:
            a = agg[normalize(r["keys"][0])]
            a[0] += r["clicks"]
            a[1] += r["impressions"]
            a[2] += r["position"] * r["impressions"]
        if len(rows) < BATCH:
            break
        start_row += BATCH
    return agg


def top_queries(service, url, start, end, n=5):
    resp = query(service, {
        "startDate": start, "endDate": end, "type": "web",
        "dimensions": ["query"], "rowLimit": 200,
        "dimensionFilterGroups": [{"filters": [
            {"dimension": "page", "operator": "equals", "expression": url}]}],
    })
    rows = sorted(resp.get("rows", []), key=lambda r: r["impressions"], reverse=True)
    return rows[:n]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--key", required=True, help="chemin du fichier JSON du compte de service")
    ap.add_argument("--sections", help="CSV des sections à discuter (colonne URL)")
    ap.add_argument("--out", default=".", help="dossier de sortie")
    args = ap.parse_args()

    creds = service_account.Credentials.from_service_account_file(args.key, scopes=SCOPES)
    service = build("searchconsole", "v1", credentials=creds, cache_discovery=False)

    end = dt.date.today() - dt.timedelta(days=3)
    windows = {
        "3m": (end - dt.timedelta(days=91), end),
        "16m": (end - dt.timedelta(days=486), end),
        "avant_chute": (end - dt.timedelta(days=486), dt.date(2025, 10, 31)),
        "apres_chute": (dt.date(2025, 12, 1), end),
    }
    data = {}
    for name, (s, e) in windows.items():
        print(f"Fenêtre {name} : {s} -> {e}")
        data[name] = pages_for_window(service, s.isoformat(), e.isoformat())

    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    stamp = dt.date.today().isoformat()

    urls = sorted(set().union(*[d.keys() for d in data.values()]))
    path = out / f"gsc-pages-{stamp}.csv"
    with open(path, "w", newline="", encoding="utf-8-sig") as f:
        w = csv.writer(f, delimiter=";")
        header = ["url"]
        for name in windows:
            header += [f"clics_{name}", f"impressions_{name}", f"position_{name}"]
        w.writerow(header)
        for u in urls:
            row = [u]
            for name in windows:
                c, i, p = data[name].get(u, (0, 0, 0))
                row += [int(c), int(i), f"{p / i:.1f}" if i else ""]
            w.writerow(row)
    print(f"{len(urls)} URL -> {path}")

    if args.sections:
        with open(args.sections, encoding="utf-8-sig") as f:
            section_urls = [r["URL"].strip() for r in csv.DictReader(f, delimiter=";") if r.get("URL")]
        s, e = windows["16m"]
        path = out / f"gsc-top-requetes-sections-{stamp}.csv"
        with open(path, "w", newline="", encoding="utf-8-sig") as f:
            w = csv.writer(f, delimiter=";")
            w.writerow(["url", "requete", "clics", "impressions", "position"])
            for k, u in enumerate(section_urls, 1):
                for r in top_queries(service, u, s.isoformat(), e.isoformat()):
                    w.writerow([u, r["keys"][0], int(r["clicks"]), int(r["impressions"]), f"{r['position']:.1f}"])
                if k % 50 == 0:
                    print(f"  requêtes : {k}/{len(section_urls)}")
                time.sleep(0.1)
        print(f"Requêtes des {len(section_urls)} sections -> {path}")


if __name__ == "__main__":
    main()
