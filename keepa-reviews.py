#!/usr/bin/env python3
"""
Keepa — Reviews Fetcher (nombre d'avis + note)
==============================================
Complète les données Amazon (script amazon-creators-api.py) avec ce que
l'API Amazon ne donne pas : le **nombre d'avis** et la **note moyenne**,
récupérés via l'API Keepa pour une liste d'ASINs.

Sortie fusionnable par ASIN avec results.json / results.csv.

Prérequis :
  pip install requests python-dotenv

Credentials (.env ou variables d'environnement) :
  KEEPA_API_KEY   Clé API Keepa (keepa.com/#!api)

Usage :
  # 1) Self-test (1-2 ASINs, imprime le JSON brut Keepa)
  python keepa-reviews.py --selftest --asins B0CKPJJ27P,B09Y2MYL5C

  # 2) Run complet
  python keepa-reviews.py --input asins.csv --output keepa-reviews.json

  # 3) Fusion avec les données Amazon
  python keepa-reviews.py --merge results.json keepa-reviews.json --output final.csv
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

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    datefmt="%H:%M:%S",
)
log = logging.getLogger("keepa-reviews")

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

KEEPA_ENDPOINT = "https://api.keepa.com/product"

# Marketplace → domaine Keepa
KEEPA_DOMAINS = {
    "www.amazon.com":    1,
    "www.amazon.co.uk":  2,
    "www.amazon.de":     3,
    "www.amazon.fr":     4,
    "www.amazon.co.jp":  5,
    "www.amazon.ca":     6,
    "www.amazon.it":     8,
    "www.amazon.es":     9,
    "www.amazon.in":    10,
    "www.amazon.com.mx": 11,
}

# Index dans les tableaux Keepa csv / stats.current
KEEPA_IDX_RATING = 16        # note ×10 (47 = 4.7/5)
KEEPA_IDX_REVIEW_COUNT = 17  # nombre d'avis

BATCH_SIZE = 50              # Keepa accepte 100, mais 50 = marge sous le plafond
                             # de tokens + progression plus fine
TOKENS_PER_PRODUCT = 2.2     # coût estimé (base + rating), avec marge
MAX_ERROR_RETRIES = 4        # UNIQUEMENT pour les erreurs réseau/serveur (pas tokens)
RETRY_BACKOFF = 2.0


# ---------------------------------------------------------------------------
# Client Keepa (auto-régulation par tokens)
# ---------------------------------------------------------------------------

class KeepaClient:
    def __init__(self, api_key: str, domain: int, session: requests.Session):
        self.api_key = api_key
        self.domain = domain
        self.session = session
        self.tokens_left = None    # solde connu (peut être négatif chez Keepa)
        self.refill_rate = None    # tokens rechargés par minute

    def _update_token_state(self, body):
        if not isinstance(body, dict):
            return
        if body.get("tokensLeft") is not None:
            self.tokens_left = body["tokensLeft"]
        if body.get("refillRate"):
            self.refill_rate = body["refillRate"]

    def _wait_for_budget(self, target: float):
        """Attend (par tranches, avec logs) que le solde ESTIMÉ atteigne `target`.

        C'est le cœur du correctif : au lieu d'abandonner après quelques essais
        d'1 min, on calcule le temps réel nécessaire pour revenir à `target`
        tokens au rythme de recharge, et on patiente autant qu'il faut.
        """
        if self.tokens_left is None or not self.refill_rate:
            return  # 1er appel : on ne connaît pas encore l'état → on tire
        rate = max(self.refill_rate, 1)
        while self.tokens_left < target:
            deficit = target - self.tokens_left
            eta_min = deficit / rate
            wait_s = min(max(eta_min * 60.0, 5.0), 300.0)  # tranches 5s..5min
            log.info("Tokens %.0f/%.0f (recharge %d/min) → ~%.0f min d'attente. "
                     "Pause %.0fs…", self.tokens_left, target, self.refill_rate,
                     eta_min, wait_s)
            time.sleep(wait_s)
            self.tokens_left += rate * (wait_s / 60.0)  # estimation post-pause

    def get_products(self, asins: list) -> dict:
        """Requête /product pour 1-100 ASINs. Retourne le JSON brut.

        Ne renonce JAMAIS pour cause de tokens : attend le temps nécessaire.
        Seules les erreurs réseau/serveur sont limitées à MAX_ERROR_RETRIES.
        """
        if len(asins) > 100:
            raise ValueError("Maximum 100 ASINs par requête")

        target = len(asins) * TOKENS_PER_PRODUCT  # budget nécessaire pour ce batch
        params = {
            "key": self.api_key,
            "domain": self.domain,
            "asin": ",".join(asins),
            "stats": 180,     # active stats.current (valeurs courantes)
            "rating": 1,      # inclut note + nombre d'avis (coûte +1 token/produit)
            # NB: 'offers' doit valoir 20-100 (jamais 0) → omis (inutile ici).
            #     'update' omis → Keepa sert ses données en CACHE (tokens minimaux).
            #     'history' laissé par défaut (1) → csv dispo en repli du parsing.
        }

        # 1) Attendre d'avoir de quoi payer le batch AVANT de tirer.
        self._wait_for_budget(target)

        # 2) Boucle : tokens insuffisants (429) = on attend et on refait,
        #    SANS consommer de tentative. Seules les vraies erreurs comptent.
        errors = 0
        while True:
            try:
                resp = self.session.get(KEEPA_ENDPOINT, params=params, timeout=60)

                if resp.status_code == 429:
                    body = {}
                    try:
                        body = resp.json()
                    except Exception:
                        pass
                    self._update_token_state(body)
                    if self.tokens_left is None:
                        self.tokens_left = 0
                    self._wait_for_budget(target)
                    continue  # ← jamais compté comme une erreur

                if resp.status_code >= 500:
                    errors += 1
                    if errors > MAX_ERROR_RETRIES:
                        raise RuntimeError(f"HTTP {resp.status_code} après {errors} essais")
                    time.sleep(RETRY_BACKOFF ** errors)
                    continue

                if resp.status_code >= 400:
                    raise RuntimeError(f"HTTP {resp.status_code} : {resp.text[:500]}")

                data = resp.json()
                self._update_token_state(data)
                return data

            except requests.exceptions.RequestException as e:
                errors += 1
                if errors > MAX_ERROR_RETRIES:
                    raise
                log.warning("Erreur réseau : %s. Retry dans %.1fs…",
                            e, RETRY_BACKOFF ** errors)
                time.sleep(RETRY_BACKOFF ** errors)


# ---------------------------------------------------------------------------
# Extraction note + nombre d'avis
# ---------------------------------------------------------------------------

def _current_value(product: dict, idx: int):
    """Valeur courante depuis stats.current[idx], repli sur csv[idx] (dernier point)."""
    stats = product.get("stats") or {}
    current = stats.get("current") or []
    if idx < len(current):
        v = current[idx]
        if v is not None and v != -1:
            return v
    # Repli : dernier point de csv[idx] (format [t, v, t, v, …])
    csv_arr = product.get("csv") or []
    if idx < len(csv_arr) and csv_arr[idx]:
        series = csv_arr[idx]
        if len(series) >= 2 and series[-1] not in (None, -1):
            return series[-1]
    return None


def parse_product(product: dict) -> dict:
    rating_raw = _current_value(product, KEEPA_IDX_RATING)
    count_raw = _current_value(product, KEEPA_IDX_REVIEW_COUNT)

    review_score = round(rating_raw / 10.0, 1) if isinstance(rating_raw, (int, float)) and rating_raw >= 0 else None
    review_count = int(count_raw) if isinstance(count_raw, (int, float)) and count_raw >= 0 else None

    return {
        "asin": product.get("asin"),
        "review_count": review_count,
        "review_score": review_score,
        "keepa_found": True,
    }


# ---------------------------------------------------------------------------
# Entrée CSV / checkpoint / sortie
# ---------------------------------------------------------------------------

def read_from_results(path: str, keep_statuses: set) -> list:
    """Construit la liste depuis un results.json Amazon, filtré par statut.
    Écarte par défaut discontinued / not_found (produits définitivement morts).
    """
    with open(path, encoding="utf-8") as f:
        amazon = json.load(f)
    items, seen, dropped = [], set(), 0
    for r in amazon:
        asin = (r.get("asin") or "").strip().upper()
        if not asin or asin in seen:
            continue
        if r.get("status") not in keep_statuses:
            dropped += 1
            continue
        seen.add(asin)
        items.append({"post_id": r.get("post_id"), "asin": asin})
    log.info("Depuis %s : %d gardés, %d écartés (hors filtre de statut)",
             path, len(items), dropped)
    return items


def read_asins(path: str) -> list:
    items, seen = [], set()
    with open(path, newline="", encoding="utf-8-sig") as f:
        sample = f.read(4096)
        f.seek(0)
        try:
            dialect = csv.Sniffer().sniff(sample, delimiters=",;\t")
        except csv.Error:
            dialect = csv.excel
        reader = csv.DictReader(f, dialect=dialect)
        if reader.fieldnames:
            reader.fieldnames = [n.strip().lower() for n in reader.fieldnames]
        for row in reader:
            asin = (row.get("asin") or "").strip().upper()
            if not asin or asin in seen:
                continue
            seen.add(asin)
            post_id = (row.get("post_id") or row.get("id") or "").strip()
            items.append({"post_id": post_id or None, "asin": asin})
    return items


def load_checkpoint(path: str) -> dict:
    if os.path.exists(path):
        with open(path) as f:
            return json.load(f)
    return {}


def save_checkpoint(path: str, data: dict):
    tmp = path + ".tmp"
    with open(tmp, "w") as f:
        json.dump(data, f)
    os.replace(tmp, path)


def _now_iso() -> str:
    return datetime.datetime.now(datetime.timezone.utc).isoformat()


def process_all(client: KeepaClient, items: list, checkpoint_path: str) -> list:
    checkpoint = load_checkpoint(checkpoint_path)
    results = checkpoint.get("results", {})
    # On re-traite les enregistrements tombés en erreur (échecs transitoires,
    # ex. batch abandonné faute de tokens avant le correctif).
    processed = {a for a, r in results.items() if r.get("status") != "error"}

    remaining = [it for it in items if it["asin"] not in processed]
    total, done = len(items), len(processed)
    if done:
        log.info("Reprise : %d/%d déjà traités, %d restants", done, total, len(remaining))

    post_by_asin = {it["asin"]: it["post_id"] for it in items}
    batches = [remaining[i:i + BATCH_SIZE] for i in range(0, len(remaining), BATCH_SIZE)]

    for bi, batch in enumerate(batches):
        batch_asins = [it["asin"] for it in batch]
        try:
            data = client.get_products(batch_asins)
        except Exception as e:
            log.error("Erreur batch %d/%d : %s", bi + 1, len(batches), e)
            for a in batch_asins:
                results[a] = {"asin": a, "post_id": post_by_asin.get(a),
                              "status": "error", "error": str(e)}
            save_checkpoint(checkpoint_path, {"results": results})
            continue

        products = {p.get("asin"): p for p in data.get("products", []) if p.get("asin")}

        for a in batch_asins:
            p = products.get(a)
            if p is None:
                rec = {"asin": a, "review_count": None, "review_score": None,
                       "keepa_found": False}
            else:
                rec = parse_product(p)
            rec["post_id"] = post_by_asin.get(a)
            rec["fetched_at"] = _now_iso()
            results[a] = rec

        done += len(batch)
        save_checkpoint(checkpoint_path, {"results": results})

        with_reviews = sum(1 for r in results.values() if r.get("review_count"))
        log.info("Batch %d/%d — %d/%d traités [avec avis:%d | tokens restants:%s]",
                 bi + 1, len(batches), done, total, with_reviews, client.tokens_left)

    return [results[it["asin"]] for it in items if it["asin"] in results]


def write_json(results: list, path: str):
    with open(path, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    log.info("JSON écrit : %s (%d produits)", path, len(results))


def write_csv(results: list, path: str):
    if not results:
        return
    fields = ["post_id", "asin", "review_count", "review_score",
              "keepa_found", "fetched_at"]
    with open(path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        for r in results:
            writer.writerow(r)
    log.info("CSV écrit : %s (%d produits)", path, len(results))


def print_summary(results: list):
    total = len(results)
    if not total:
        log.info("Aucun résultat.")
        return
    with_reviews = sum(1 for r in results if r.get("review_count"))
    with_score = sum(1 for r in results if r.get("review_score") is not None)
    not_found = sum(1 for r in results if r.get("keepa_found") is False)
    log.info("=" * 52)
    log.info("RÉSUMÉ Keepa — %d produits", total)
    log.info("  avec nombre d'avis : %6d  (%5.1f%%)", with_reviews, with_reviews / total * 100)
    log.info("  avec note          : %6d  (%5.1f%%)", with_score, with_score / total * 100)
    log.info("  absents de Keepa   : %6d  (%5.1f%%)", not_found, not_found / total * 100)
    log.info("=" * 52)


# ---------------------------------------------------------------------------
# Fusion avec les données Amazon
# ---------------------------------------------------------------------------

def merge_results(amazon_path: str, keepa_path: str, out_path: str):
    with open(amazon_path, encoding="utf-8") as f:
        amazon = json.load(f)
    with open(keepa_path, encoding="utf-8") as f:
        keepa = {r["asin"]: r for r in json.load(f)}

    for row in amazon:
        k = keepa.get(row.get("asin"))
        if k:
            row["review_count"] = k.get("review_count")
            row["review_score"] = k.get("review_score")

    if out_path.endswith(".json"):
        with open(out_path, "w", encoding="utf-8") as f:
            json.dump(amazon, f, ensure_ascii=False, indent=2)
    else:
        fields = ["post_id", "asin", "status", "title", "brand",
                  "review_count", "review_score",
                  "price", "price_currency", "price_display",
                  "image", "image_large",
                  "availability_message", "availability_type",
                  "merchant", "fetched_at"]
        with open(out_path, "w", newline="", encoding="utf-8") as f:
            writer = csv.DictWriter(f, fieldnames=fields, extrasaction="ignore")
            writer.writeheader()
            for row in amazon:
                writer.writerow(row)
    log.info("Fusion écrite : %s (%d produits)", out_path, len(amazon))


# ---------------------------------------------------------------------------
# Self-test
# ---------------------------------------------------------------------------

def run_selftest(client: KeepaClient, asins: list):
    test = asins[:2] or ["B0CKPJJ27P"]
    log.info("=== SELF-TEST Keepa (domaine %d) ===", client.domain)
    log.info("Requête /product sur %s…", test)
    data = client.get_products(test)
    log.info("Tokens restants : %s", client.tokens_left)
    print("\n----- JSON BRUT (1er produit) -----")
    products = data.get("products", [])
    if products:
        p = products[0]
        # On imprime les infos clés + les index note/avis
        stats = (p.get("stats") or {}).get("current") or []
        print(json.dumps({
            "asin": p.get("asin"),
            "title": p.get("title"),
            "stats.current[16]=RATING": stats[16] if len(stats) > 16 else "?",
            "stats.current[17]=COUNT_REVIEWS": stats[17] if len(stats) > 17 else "?",
        }, ensure_ascii=False, indent=2))
    print("----- FIN -----\n")
    for p in products:
        r = parse_product(p)
        log.info("Parsé %s → note=%s nombre_avis=%s", r["asin"],
                 r["review_score"], r["review_count"])
    log.info("Self-test terminé. Vérifie que note/nombre_avis correspondent à "
             "la fiche Amazon ; sinon on ajuste les index KEEPA_IDX_*.")


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Récupère nombre d'avis + note via l'API Keepa",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument("-i", "--input", help="CSV d'entrée (colonnes: asin, post_id)")
    parser.add_argument("-o", "--output", default="keepa-reviews.json")
    parser.add_argument("--asins", help="ASINs séparés par des virgules")
    parser.add_argument("--from-results",
                        help="Construit la liste depuis un results.json Amazon, "
                             "filtré par statut (voir --keep-status)")
    parser.add_argument("--keep-status", default="available,out_of_stock,restricted",
                        help="Statuts à garder avec --from-results "
                             "(défaut: available,out_of_stock,restricted → "
                             "exclut discontinued/not_found/no_data)")
    parser.add_argument("--selftest", action="store_true")
    parser.add_argument("--merge", nargs=2, metavar=("AMAZON_JSON", "KEEPA_JSON"),
                        help="Fusionne les avis Keepa dans les données Amazon")
    parser.add_argument("--no-resume", action="store_true")
    parser.add_argument("--marketplace", default=None)
    args = parser.parse_args()

    # Mode fusion (pas besoin de clé API)
    if args.merge:
        merge_results(args.merge[0], args.merge[1], args.output)
        return

    api_key = os.environ.get("KEEPA_API_KEY", "")
    if not api_key:
        print("Erreur : KEEPA_API_KEY manquante (fichier .env ou environnement).",
              file=sys.stderr)
        sys.exit(1)

    marketplace = args.marketplace or os.environ.get("AMAZON_MARKETPLACE", "www.amazon.fr")
    marketplace = marketplace.strip()
    domain = KEEPA_DOMAINS.get(marketplace)
    if not domain:
        print(f"Marketplace inconnu : {marketplace}. Valeurs : {', '.join(KEEPA_DOMAINS)}",
              file=sys.stderr)
        sys.exit(1)

    session = requests.Session()
    client = KeepaClient(api_key, domain, session)

    # Items
    if args.asins:
        items = [{"post_id": None, "asin": a.strip().upper()}
                 for a in args.asins.split(",") if a.strip()]
    elif args.from_results:
        keep = {s.strip() for s in args.keep_status.split(",") if s.strip()}
        items = read_from_results(args.from_results, keep)
    elif args.input:
        items = read_asins(args.input)
    else:
        items = []

    if args.selftest:
        try:
            run_selftest(client, [it["asin"] for it in items])
        except Exception as e:
            log.error("Self-test échoué : %s", e)
            sys.exit(1)
        return

    if not items:
        print("Erreur : spécifiez --input ou --asins", file=sys.stderr)
        sys.exit(1)

    log.info("Marketplace : %s (domaine Keepa %d) | ASINs : %d | requêtes : %d",
             marketplace, domain, len(items),
             (len(items) + BATCH_SIZE - 1) // BATCH_SIZE)

    checkpoint_path = args.output + ".checkpoint"
    if args.no_resume and os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)

    start = time.time()
    results = process_all(client, items, checkpoint_path)

    write_json(results, args.output)
    write_csv(results, args.output.rsplit(".", 1)[0] + ".csv")
    if os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)

    print_summary(results)
    log.info("Terminé en %.0f s.", time.time() - start)


if __name__ == "__main__":
    main()
