"""Audit jev-seo ciblé : l'accueil d'un site et une liste de pages choisies.

jev-seo (https://github.com/AgriciDaniel/jev-seo) explore normalement un site à partir de
son accueil. Ce script réutilise tel quel son exploration, ses contrôles, les jugements de
Jev et PageSpeed Insights, mais limite l'exploration à l'accueil (contexte du site) et aux
pages de la liste. Les deux contrôles qui supposent une exploration complète du site
(pages orphelines, profondeur de clic) sont retirés.

Usage (depuis n'importe quel dossier, clés dans le .env du dépôt jev-seo) :
  python jev_pages.py --jevseo C:\\Webdev\\jev-seo --urls comparatifs.txt --out <dossier>
puis, depuis le dossier jev-seo :
  python -m jevseo render <dossier> --formats xlsx,md
"""
import argparse
import json
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

GRAPH_RULES = {"orphan_pages", "deep_pages"}


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--jevseo", required=True, help="dossier du dépôt jev-seo")
    ap.add_argument("--urls", required=True, help="fichier texte : une URL par ligne (# pour commenter)")
    ap.add_argument("--home", default="https://meilleurtest.fr/")
    ap.add_argument("--out", required=True)
    ap.add_argument("--jev-budget", type=float, default=0.25, help="plafond de dépense Jev en USD")
    ap.add_argument("--no-psi", action="store_true")
    args = ap.parse_args()

    sys.path.insert(0, str(Path(args.jevseo).resolve()))
    from jevseo import VERSION, checks, cli, crawl, jev, psi, score

    targets = []
    for line in Path(args.urls).read_text(encoding="utf-8-sig").splitlines():
        line = line.strip()
        if line and not line.startswith("#"):
            targets.append(crawl.normalize_url(line))
    if not targets:
        sys.exit("Aucune URL dans le fichier.")

    # La file d'exploration de jev-seo démarre avec la seule page d'accueil : on y ajoute
    # les pages choisies, et le plafond de pages arrête l'exploration juste après elles.
    real_deque = crawl.deque

    def seeded_deque(iterable=(), *rest):
        items = list(iterable)
        d = real_deque(items, *rest)
        if len(items) == 1 and not items[0].endswith(".xml") and items[0] not in targets:
            d.extend(u for u in targets if u != items[0])
        return d

    crawl.deque = seeded_deque

    out = Path(args.out)
    out.mkdir(parents=True, exist_ok=True)
    started = datetime.now(timezone.utc)
    timings = {}

    cli.stage(1, f"accueil + {len(targets)} pages choisies")
    t = time.monotonic()
    site = crawl.crawl(args.home, max_pages=1 + len(targets), max_depth=1, render_mode="auto", time_budget=900, log=cli.log)
    # Une page choisie qui a échoué pour une raison réseau est retentée (2 fois au plus).
    fetcher = crawl.Fetcher()
    for i, p in enumerate(site["pages"]):
        if p.get("url") in targets and p.get("status") is None and p.get("error") == "network error or timeout":
            for _ in range(2):
                rec = crawl.fetch_page(fetcher, p["url"], site["domain"])
                if rec.get("status"):
                    break
            if rec.get("status"):
                rec.update(kind="page", depth=p.get("depth"), in_sitemap=p.get("in_sitemap"),
                           inlinks=p.get("inlinks", 0), inlink_anchors=p.get("inlink_anchors", []))
                site["pages"][i] = rec
                cli.log(f"nouvelle tentative réussie : {p['url']} (HTTP {rec['status']})")
    timings["crawl"] = round(time.monotonic() - t, 1)
    by_url = {p["url"]: p for p in site["pages"]}
    for u in targets:
        p = by_url.get(u)
        if p is None:
            cli.log(f"ATTENTION page non atteinte : {u}")
        elif p.get("kind") == "redirect" or (p.get("final_url") and p["final_url"] != u):
            cli.log(f"ATTENTION page redirigée : {u} -> {p.get('final_url')} (elle n'est plus un comparatif autonome)")
        elif p.get("status") != 200:
            cli.log(f"ATTENTION page en erreur : {u} (HTTP {p.get('status')}, {p.get('error', '')})")
    for p in site["pages"]:
        if p.get("url") in targets and (p.get("depth") is None or p.get("depth", 99) >= 99):
            p["depth"] = 1  # page choisie, pas découverte par les liens

    cli.stage(2)
    findings = [f for f in checks.run_checks(site) if f["id"] not in GRAPH_RULES]
    pages = [p for p in checks.html_pages(site) if checks.indexable(p) or p["url"] == site["final_url"]]
    pages.sort(key=lambda p: (p["url"] != site["final_url"], targets.index(p["url"]) if p["url"] in targets else 99))

    cli.stage(4, f"{len(pages)} pages, budget ${args.jev_budget:.2f}")
    t = time.monotonic()
    judged = jev.judge(site, pages, args.jev_budget, log=cli.log)
    timings["jev"] = round(time.monotonic() - t, 1)
    findings += score.jev_findings(site, judged)

    perf = None
    if not args.no_psi:
        cli.stage(5, f"{len(targets)} pages x mobile et ordinateur")
        t = time.monotonic()
        perf = psi.run([p["url"] for p in pages if p["url"] in targets], log=cli.log)
        findings += cli.perf_findings(perf)
        timings["pagespeed"] = round(time.monotonic() - t, 1)

    cli.stage(6)
    scores = score.score(site, findings, judged, perf, None)
    acts = score.actions(findings, judged, len(checks.html_pages(site)))
    data = {
        "schema_version": "1.0",
        "tool": {"name": "jev-seo", "version": VERSION},
        "run": {
            "started_at": started.isoformat(timespec="seconds"),
            "finished_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
            "timings_s": timings,
            "options": {"url": args.home, "mode": "pages choisies", "pages": targets, "max_pages": 1 + len(targets),
                        "max_depth": 1, "jev_budget": args.jev_budget, "no_jev": False, "no_psi": args.no_psi,
                        "full": False, "removed_rules": sorted(GRAPH_RULES)},
        },
        "site": {k: v for k, v in site.items() if k != "pages"},
        "pages": site["pages"],
        "findings": findings,
        "passed_rules": checks.passed_rules(findings),
        "jev": judged,
        "performance": perf,
        "dataforseo": None,
        "scores": scores,
        "actions": acts,
    }
    (out / "audit.json").write_text(json.dumps(data, indent=1, default=str), encoding="utf-8")
    (out / "digest.md").write_text(cli.digest(data), encoding="utf-8")
    cli.log(f"audit écrit : {out / 'audit.json'}")
    cli.log(f"score {scores['overall']} ({scores['grade']}), {len(acts)} actions, Jev ${judged.get('ledger', {}).get('cost_usd', 0):.4f}")


if __name__ == "__main__":
    main()
