#!/usr/bin/env python3
"""
Keepa — Historique de prix (6 points mensuels)
==============================================
Pour chaque ASIN : prix actuel + prix à −30j, −60j, −90j, −120j, −150j,
extraits de l'historique de prix Keepa (séries csv).

Prérequis : pip install requests python-dotenv
Credentials : KEEPA_API_KEY (.env)

Usage :
  # 1) Self-test : imprime l'historique BRUT (séries New/Amazon/BuyBox) + les 6 points
  python keepa-price-history.py --selftest --asins B0CKPJJ27P,B09Y2MYL5C

  # 2) Run complet (depuis les résultats Amazon, ou un CSV d'ASINs)
  python keepa-price-history.py --from-results results.json --output keepa-prices.json
  python keepa-price-history.py --input asins.csv --output keepa-prices.json
"""

import argparse
import csv
import datetime
import json
import logging
import os
import sys
import time

try:
    import requests
except ImportError:
    print("Erreur : pip install requests", file=sys.stderr)
    sys.exit(1)
try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

logging.basicConfig(level=logging.INFO,
                    format="%(asctime)s [%(levelname)s] %(message)s", datefmt="%H:%M:%S")
log = logging.getLogger("keepa-prices")

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

KEEPA_ENDPOINT = "https://api.keepa.com/product"
KEEPA_DOMAINS = {
    "www.amazon.com":1, "www.amazon.co.uk":2, "www.amazon.de":3, "www.amazon.fr":4,
    "www.amazon.co.jp":5, "www.amazon.ca":6, "www.amazon.it":8, "www.amazon.es":9,
    "www.amazon.in":10, "www.amazon.com.mx":11,
}

# Index des séries de prix dans product["csv"]
CSV_AMAZON   = 0    # prix quand vendu par Amazon
CSV_NEW      = 1    # meilleure offre neuve (marketplace)
CSV_BUYBOX   = 18   # prix Buy Box (souvent vide sans param buybox)
# Série(s) utilisée(s) pour le prix « client » : New en priorité, repli Amazon.
PRICE_INDICES = [CSV_NEW, CSV_AMAZON]

# Conversion temps Keepa (minutes depuis 2011-01-01) → Unix ms
KEEPA_EPOCH_OFFSET_MIN = 21564000
def keepa_minute_to_ms(kt):
    return (int(kt) + KEEPA_EPOCH_OFFSET_MIN) * 60000

# Points mensuels demandés (en jours avant la date de référence)
MONTH_OFFSETS = [0, 30, 60, 90, 120, 150]
POINT_LABELS  = ["price_now", "price_30d", "price_60d", "price_90d", "price_120d", "price_150d"]

BATCH_SIZE = 50
TOKENS_PER_PRODUCT = 1.5     # pas de rating ici → coût réduit
MAX_ERROR_RETRIES = 4
RETRY_BACKOFF = 2.0


# ---------------------------------------------------------------------------
# Client Keepa (régulation tokens robuste — identique au script avis)
# ---------------------------------------------------------------------------

class KeepaClient:
    def __init__(self, api_key, domain, session):
        self.api_key = api_key
        self.domain = domain
        self.session = session
        self.tokens_left = None
        self.refill_rate = None

    def _update_token_state(self, body):
        if not isinstance(body, dict):
            return
        if body.get("tokensLeft") is not None:
            self.tokens_left = body["tokensLeft"]
        if body.get("refillRate"):
            self.refill_rate = body["refillRate"]

    def _wait_for_budget(self, target):
        if self.tokens_left is None or not self.refill_rate:
            return
        rate = max(self.refill_rate, 1)
        while self.tokens_left < target:
            deficit = target - self.tokens_left
            eta_min = deficit / rate
            wait_s = min(max(eta_min * 60.0, 5.0), 300.0)
            log.info("Tokens %.0f/%.0f (recharge %d/min) → ~%.0f min. Pause %.0fs…",
                     self.tokens_left, target, self.refill_rate, eta_min, wait_s)
            time.sleep(wait_s)
            self.tokens_left += rate * (wait_s / 60.0)

    def get_products(self, asins):
        if len(asins) > 100:
            raise ValueError("Maximum 100 ASINs par requête")
        target = len(asins) * TOKENS_PER_PRODUCT
        params = {
            "key": self.api_key, "domain": self.domain, "asin": ",".join(asins),
            "history": 1,   # inclut l'historique (csv)
            "update": 0,    # données en cache → tokens minimaux
            # pas de rating / offers / stats → coût réduit
        }
        self._wait_for_budget(target)
        errors = 0
        while True:
            try:
                resp = self.session.get(KEEPA_ENDPOINT, params=params, timeout=90)
                if resp.status_code == 429:
                    body = {}
                    try: body = resp.json()
                    except Exception: pass
                    self._update_token_state(body)
                    if self.tokens_left is None: self.tokens_left = 0
                    self._wait_for_budget(target)
                    continue
                if resp.status_code >= 500:
                    errors += 1
                    if errors > MAX_ERROR_RETRIES:
                        raise RuntimeError(f"HTTP {resp.status_code} après {errors} essais")
                    time.sleep(RETRY_BACKOFF ** errors); continue
                if resp.status_code >= 400:
                    raise RuntimeError(f"HTTP {resp.status_code} : {resp.text[:500]}")
                data = resp.json()
                self._update_token_state(data)
                return data
            except requests.exceptions.RequestException as e:
                errors += 1
                if errors > MAX_ERROR_RETRIES:
                    raise
                log.warning("Erreur réseau : %s. Retry %.1fs…", e, RETRY_BACKOFF ** errors)
                time.sleep(RETRY_BACKOFF ** errors)


# ---------------------------------------------------------------------------
# Extraction de l'historique
# ---------------------------------------------------------------------------

def pick_series(product):
    """Choisit la 1re série de prix disponible (New puis Amazon). Retourne (index, série)."""
    csv_arr = product.get("csv") or []
    for idx in PRICE_INDICES:
        if idx < len(csv_arr) and csv_arr[idx] and len(csv_arr[idx]) >= 2:
            return idx, csv_arr[idx]
    return None, None


def price_at(series, target_ms):
    """Dernier prix RÉEL (≠ -1) à la date `target_ms` (ou avant). En euros, sinon None."""
    best = None
    for i in range(0, len(series) - 1, 2):
        t_ms = keepa_minute_to_ms(series[i])
        price = series[i + 1]
        if t_ms <= target_ms:
            if price != -1:
                best = price
        else:
            break  # série chronologique → inutile d'aller plus loin
    return round(best / 100.0, 2) if best is not None else None


def extract_points(product, target_ms_list):
    idx, series = pick_series(product)
    if series is None:
        return {"price_series": None, **{lbl: None for lbl in POINT_LABELS}}
    out = {"price_series": {CSV_NEW: "new", CSV_AMAZON: "amazon"}.get(idx, str(idx))}
    for lbl, tms in zip(POINT_LABELS, target_ms_list):
        out[lbl] = price_at(series, tms)
    return out


# ---------------------------------------------------------------------------
# Entrée / checkpoint / sortie
# ---------------------------------------------------------------------------

def read_asins(path):
    items, seen = [], set()
    with open(path, newline="", encoding="utf-8-sig") as f:
        sample = f.read(4096); f.seek(0)
        try:
            dialect = csv.Sniffer().sniff(sample, delimiters=",;\t")
        except csv.Error:
            dialect = csv.excel
        reader = csv.DictReader(f, dialect=dialect)
        if reader.fieldnames:
            reader.fieldnames = [n.strip().lower() for n in reader.fieldnames]
        for row in reader:
            asin = (row.get("asin") or "").strip().upper()
            if not asin or asin in seen: continue
            seen.add(asin)
            items.append({"post_id": (row.get("post_id") or row.get("id") or "").strip() or None,
                          "asin": asin})
    return items


def read_from_results(path):
    with open(path, encoding="utf-8") as f:
        rows = json.load(f)
    items, seen = [], set()
    for r in rows:
        asin = (r.get("asin") or "").strip().upper()
        if not asin or asin in seen: continue
        seen.add(asin)
        items.append({"post_id": r.get("post_id"), "asin": asin})
    return items


def load_checkpoint(path):
    if os.path.exists(path):
        with open(path) as f: return json.load(f)
    return {}

def save_checkpoint(path, data):
    tmp = path + ".tmp"
    with open(tmp, "w") as f: json.dump(data, f)
    os.replace(tmp, path)

def _now_iso():
    return datetime.datetime.now(datetime.timezone.utc).isoformat()


def process_all(client, items, checkpoint_path, target_ms_list):
    checkpoint = load_checkpoint(checkpoint_path)
    results = checkpoint.get("results", {})
    processed = set(results.keys())
    remaining = [it for it in items if it["asin"] not in processed]
    total, done = len(items), len(processed)
    if done:
        log.info("Reprise : %d/%d déjà traités, %d restants", done, total, len(remaining))

    post_by_asin = {it["asin"]: it["post_id"] for it in items}
    batches = [remaining[i:i+BATCH_SIZE] for i in range(0, len(remaining), BATCH_SIZE)]

    for bi, batch in enumerate(batches):
        batch_asins = [it["asin"] for it in batch]
        try:
            data = client.get_products(batch_asins)
        except Exception as e:
            log.error("Erreur batch %d/%d : %s", bi+1, len(batches), e)
            for a in batch_asins:
                results[a] = {"asin": a, "post_id": post_by_asin.get(a), "status": "error", "error": str(e)}
            save_checkpoint(checkpoint_path, {"results": results, "anchor": target_ms_list})
            continue

        products = {p.get("asin"): p for p in data.get("products", []) if p.get("asin")}
        for a in batch_asins:
            p = products.get(a)
            if p is None:
                rec = {"asin": a, "keepa_found": False, "price_series": None,
                       **{lbl: None for lbl in POINT_LABELS}}
            else:
                rec = {"asin": a, "keepa_found": True, **extract_points(p, target_ms_list)}
            rec["post_id"] = post_by_asin.get(a)
            rec["fetched_at"] = _now_iso()
            results[a] = rec

        done += len(batch)
        save_checkpoint(checkpoint_path, {"results": results, "anchor": target_ms_list})
        with_price = sum(1 for r in results.values() if r.get("price_now") is not None)
        log.info("Batch %d/%d — %d/%d [avec prix actuel:%d | tokens:%s]",
                 bi+1, len(batches), done, total, with_price, client.tokens_left)

    return [results[it["asin"]] for it in items if it["asin"] in results]


def write_outputs(results, out_path):
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    log.info("JSON écrit : %s (%d produits)", out_path, len(results))
    csv_path = out_path.rsplit(".", 1)[0] + ".csv"
    fields = ["post_id", "asin", "price_series"] + POINT_LABELS + ["keepa_found", "fetched_at"]
    with open(csv_path, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=fields, extrasaction="ignore")
        w.writeheader()
        for r in results: w.writerow(r)
    log.info("CSV écrit : %s", csv_path)


def print_summary(results):
    total = len(results)
    if not total:
        log.info("Aucun résultat."); return
    have = {lbl: sum(1 for r in results if r.get(lbl) is not None) for lbl in POINT_LABELS}
    log.info("=" * 52)
    log.info("RÉSUMÉ prix — %d produits", total)
    for lbl in POINT_LABELS:
        log.info("  %-11s : %6d  (%5.1f%%)", lbl, have[lbl], have[lbl]/total*100)
    log.info("=" * 52)


# ---------------------------------------------------------------------------
# Self-test : imprime l'historique brut pour choisir la bonne série
# ---------------------------------------------------------------------------

def run_selftest(client, asins, target_ms_list):
    test = asins[:2] or ["B0CKPJJ27P"]
    log.info("=== SELF-TEST historique prix (domaine %d) ===", client.domain)
    data = client.get_products(test)
    log.info("Tokens restants : %s", client.tokens_left)
    for p in data.get("products", []):
        asin = p.get("asin")
        csv_arr = p.get("csv") or []
        print(f"\n===== {asin} — {p.get('title','')[:60]} =====")
        for idx, name in [(CSV_AMAZON,"Amazon(0)"), (CSV_NEW,"New(1)"), (CSV_BUYBOX,"BuyBox(18)")]:
            series = csv_arr[idx] if idx < len(csv_arr) else None
            if not series:
                print(f"  {name:12} : (vide)")
                continue
            # 5 derniers points en clair
            pts = []
            for i in range(max(0, len(series)-10), len(series)-1, 2):
                d = datetime.datetime.utcfromtimestamp(keepa_minute_to_ms(series[i])/1000).strftime("%Y-%m-%d")
                v = series[i+1]
                pts.append(f"{d}={'—' if v==-1 else f'{v/100:.2f}€'}")
            print(f"  {name:12} : … {'  '.join(pts)}")
        pts = extract_points(p, target_ms_list)
        print(f"  → série choisie : {pts['price_series']}")
        print("  → 6 points   : " + ", ".join(f"{lbl}={pts[lbl]}" for lbl in POINT_LABELS))
    log.info("Compare 'price_now' au prix affiché sur amazon.fr et choisis la série "
             "(New/Amazon/BuyBox) via PRICE_INDICES si besoin.")


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main():
    ap = argparse.ArgumentParser(description="Historique de prix Keepa (6 points mensuels)")
    ap.add_argument("-i", "--input", help="CSV d'ASINs (asin, post_id)")
    ap.add_argument("--from-results", help="results.json Amazon (source d'ASINs)")
    ap.add_argument("-o", "--output", default="keepa-prices.json")
    ap.add_argument("--asins", help="ASINs séparés par des virgules")
    ap.add_argument("--selftest", action="store_true")
    ap.add_argument("--as-of", help="Date de référence AAAA-MM-JJ (défaut: aujourd'hui)")
    ap.add_argument("--no-resume", action="store_true")
    ap.add_argument("--marketplace", default=None)
    args = ap.parse_args()

    api_key = os.environ.get("KEEPA_API_KEY", "")
    if not api_key:
        print("Erreur : KEEPA_API_KEY manquante.", file=sys.stderr); sys.exit(1)

    marketplace = (args.marketplace or os.environ.get("AMAZON_MARKETPLACE", "www.amazon.fr")).strip()
    domain = KEEPA_DOMAINS.get(marketplace)
    if not domain:
        print(f"Marketplace inconnu : {marketplace}", file=sys.stderr); sys.exit(1)

    session = requests.Session()
    client = KeepaClient(api_key, domain, session)

    if args.asins:
        items = [{"post_id": None, "asin": a.strip().upper()} for a in args.asins.split(",") if a.strip()]
    elif args.from_results:
        items = read_from_results(args.from_results)
    elif args.input:
        items = read_asins(args.input)
    else:
        items = []

    checkpoint_path = args.output + ".checkpoint"

    # Date de référence : --as-of, sinon reprise depuis checkpoint, sinon aujourd'hui.
    anchor = None
    cp = load_checkpoint(checkpoint_path)
    if args.as_of:
        ref = datetime.datetime.strptime(args.as_of, "%Y-%m-%d").replace(tzinfo=datetime.timezone.utc)
        anchor = [int((ref - datetime.timedelta(days=d)).timestamp() * 1000) for d in MONTH_OFFSETS]
    elif cp.get("anchor") and not args.no_resume:
        anchor = cp["anchor"]  # cohérence sur reprise
    else:
        now = datetime.datetime.now(datetime.timezone.utc)
        anchor = [int((now - datetime.timedelta(days=d)).timestamp() * 1000) for d in MONTH_OFFSETS]

    if args.selftest:
        try:
            run_selftest(client, [it["asin"] for it in items], anchor)
        except Exception as e:
            log.error("Self-test échoué : %s", e); sys.exit(1)
        return

    if not items:
        print("Erreur : --input, --from-results ou --asins requis", file=sys.stderr); sys.exit(1)

    ref_str = datetime.datetime.utcfromtimestamp(anchor[0]/1000).strftime("%Y-%m-%d")
    log.info("Marketplace %s (domaine %d) | ASINs : %d | réf : %s | requêtes : %d",
             marketplace, domain, len(items), ref_str, (len(items)+BATCH_SIZE-1)//BATCH_SIZE)

    if args.no_resume and os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)

    start = time.time()
    results = process_all(client, items, checkpoint_path, anchor)
    write_outputs(results, args.output)
    if os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)
    print_summary(results)
    log.info("Terminé en %.0f s.", time.time() - start)


if __name__ == "__main__":
    main()
