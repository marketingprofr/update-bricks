#!/usr/bin/env python3
"""
Amazon Product Data Fetcher
===========================
Récupère les données produits Amazon (avis, prix, dispo, image) via PA-API 5.0
pour une liste d'ASINs.

Prérequis :
  pip install requests python-dotenv

Credentials (dans .env ou variables d'environnement) :
  AMAZON_ACCESS_KEY   Clé d'accès PA-API
  AMAZON_SECRET_KEY   Clé secrète PA-API
  AMAZON_PARTNER_TAG  Tag Associates (ex: monsite-21)
  AMAZON_MARKETPLACE  Marketplace (défaut: www.amazon.fr)

Usage :
  python amazon-product-data.py --input asins.csv --output results.json

  Le CSV d'entrée doit avoir une colonne "asin" (et optionnellement "post_id").
  Le script produit un JSON et un CSV de résultats, avec reprise automatique
  en cas d'interruption (fichier .checkpoint).
"""

import argparse
import csv
import datetime
import hashlib
import hmac
import io
import json
import logging
import os
import sys
import time
from pathlib import Path

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
log = logging.getLogger("amazon-fetch")

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

MARKETPLACES = {
    "www.amazon.fr":    {"host": "webservices.amazon.fr",    "region": "eu-west-1"},
    "www.amazon.com":   {"host": "webservices.amazon.com",   "region": "us-east-1"},
    "www.amazon.co.uk": {"host": "webservices.amazon.co.uk", "region": "eu-west-1"},
    "www.amazon.de":    {"host": "webservices.amazon.de",    "region": "eu-west-1"},
    "www.amazon.es":    {"host": "webservices.amazon.es",    "region": "eu-west-1"},
    "www.amazon.it":    {"host": "webservices.amazon.it",    "region": "eu-west-1"},
}

RESOURCES = [
    "CustomerReviews.Count",
    "CustomerReviews.StarRating",
    "Images.Primary.Medium",
    "Images.Primary.Large",
    "ItemInfo.Title",
    "ItemInfo.ByLineInfo",
    "Offers.Listings.Price",
    "Offers.Listings.Availability.Message",
    "Offers.Listings.Availability.Type",
    "Offers.Listings.DeliveryInfo.IsAmazonFulfilled",
    "Offers.Listings.MerchantInfo",
    "Offers.Listings.Condition",
]

BATCH_SIZE = 10
BASE_DELAY = 1.1  # secondes entre requêtes (1 TPS + marge)
MAX_RETRIES = 4
RETRY_BACKOFF = 2.0

# ---------------------------------------------------------------------------
# AWS Signature V4 pour PA-API 5.0
# ---------------------------------------------------------------------------

def _sign(key: bytes, msg: str) -> bytes:
    return hmac.new(key, msg.encode("utf-8"), hashlib.sha256).digest()


def _get_signature_key(secret: str, date: str, region: str, service: str) -> bytes:
    k_date = _sign(("AWS4" + secret).encode("utf-8"), date)
    k_region = _sign(k_date, region)
    k_service = _sign(k_region, service)
    return _sign(k_service, "aws4_request")


def _aws4_sign(
    access_key: str,
    secret_key: str,
    host: str,
    region: str,
    payload: str,
    target: str,
) -> dict:
    service = "ProductAdvertisingAPI"
    now = datetime.datetime.utcnow()
    amz_date = now.strftime("%Y%m%dT%H%M%SZ")
    date_stamp = now.strftime("%Y%m%d")

    headers_to_sign = {
        "content-encoding": "amz-1.0",
        "content-type": "application/json; charset=utf-8",
        "host": host,
        "x-amz-date": amz_date,
        "x-amz-target": target,
    }
    signed_header_names = ";".join(sorted(headers_to_sign.keys()))
    canonical_headers = "".join(
        f"{k}:{v}\n" for k, v in sorted(headers_to_sign.items())
    )

    payload_hash = hashlib.sha256(payload.encode("utf-8")).hexdigest()

    canonical_request = "\n".join([
        "POST",
        "/paapi5/getitems",
        "",
        canonical_headers,
        signed_header_names,
        payload_hash,
    ])

    credential_scope = f"{date_stamp}/{region}/{service}/aws4_request"
    string_to_sign = "\n".join([
        "AWS4-HMAC-SHA256",
        amz_date,
        credential_scope,
        hashlib.sha256(canonical_request.encode("utf-8")).hexdigest(),
    ])

    signing_key = _get_signature_key(secret_key, date_stamp, region, service)
    signature = hmac.new(
        signing_key, string_to_sign.encode("utf-8"), hashlib.sha256
    ).hexdigest()

    auth = (
        f"AWS4-HMAC-SHA256 Credential={access_key}/{credential_scope}, "
        f"SignedHeaders={signed_header_names}, Signature={signature}"
    )

    return {
        "content-encoding": "amz-1.0",
        "content-type": "application/json; charset=utf-8",
        "host": host,
        "x-amz-date": amz_date,
        "x-amz-target": target,
        "authorization": auth,
    }


# ---------------------------------------------------------------------------
# Client PA-API
# ---------------------------------------------------------------------------

class AmazonPAAPI:
    def __init__(self, access_key: str, secret_key: str, partner_tag: str,
                 marketplace: str = "www.amazon.fr"):
        self.access_key = access_key
        self.secret_key = secret_key
        self.partner_tag = partner_tag
        self.marketplace = marketplace

        mp = MARKETPLACES.get(marketplace)
        if not mp:
            raise ValueError(
                f"Marketplace inconnu : {marketplace}. "
                f"Valeurs possibles : {', '.join(MARKETPLACES)}"
            )
        self.host = mp["host"]
        self.region = mp["region"]
        self.endpoint = f"https://{self.host}/paapi5/getitems"
        self.target = "com.amazon.paapi5.v1.ProductAdvertisingAPIv1.GetItems"
        self.session = requests.Session()

    def get_items(self, asins: list) -> dict:
        """Appelle GetItems pour un batch de 1-10 ASINs. Retourne le JSON brut."""
        if len(asins) > 10:
            raise ValueError("Maximum 10 ASINs par requête")

        payload_obj = {
            "ItemIds": asins,
            "Resources": RESOURCES,
            "PartnerTag": self.partner_tag,
            "PartnerType": "Associates",
            "Marketplace": self.marketplace,
        }
        payload = json.dumps(payload_obj)

        headers = _aws4_sign(
            self.access_key, self.secret_key,
            self.host, self.region,
            payload, self.target,
        )

        for attempt in range(MAX_RETRIES + 1):
            try:
                resp = self.session.post(
                    self.endpoint, data=payload, headers=headers, timeout=15
                )

                if resp.status_code == 429:
                    wait = float(resp.headers.get("Retry-After", BASE_DELAY * (RETRY_BACKOFF ** attempt)))
                    log.warning("Throttled (429). Attente %.1fs…", wait)
                    time.sleep(wait)
                    headers = _aws4_sign(
                        self.access_key, self.secret_key,
                        self.host, self.region,
                        payload, self.target,
                    )
                    continue

                if resp.status_code >= 500:
                    wait = BASE_DELAY * (RETRY_BACKOFF ** attempt)
                    log.warning("Erreur serveur %d. Retry dans %.1fs…", resp.status_code, wait)
                    time.sleep(wait)
                    headers = _aws4_sign(
                        self.access_key, self.secret_key,
                        self.host, self.region,
                        payload, self.target,
                    )
                    continue

                resp.raise_for_status()
                return resp.json()

            except requests.exceptions.RequestException as e:
                if attempt < MAX_RETRIES:
                    wait = BASE_DELAY * (RETRY_BACKOFF ** attempt)
                    log.warning("Erreur réseau : %s. Retry dans %.1fs…", e, wait)
                    time.sleep(wait)
                    headers = _aws4_sign(
                        self.access_key, self.secret_key,
                        self.host, self.region,
                        payload, self.target,
                    )
                else:
                    raise

        raise RuntimeError(f"Échec après {MAX_RETRIES} tentatives pour {asins}")


# ---------------------------------------------------------------------------
# Classification de la disponibilité
# ---------------------------------------------------------------------------

def classify_product(item: dict | None, error: dict | None) -> dict:
    """
    Classifie un produit à partir de la réponse PA-API.

    Statuts possibles :
      available          En stock, achetable
      out_of_stock       Temporairement indisponible
      discontinued       Page existe mais plus en vente
      not_found          ASIN invalide ou page supprimée
      restricted         Produit existant mais accès API restreint
    """
    result = {
        "status": None,
        "title": None,
        "review_count": None,
        "review_score": None,
        "price": None,
        "price_currency": None,
        "price_display": None,
        "image_medium": None,
        "image_large": None,
        "availability_message": None,
        "brand": None,
        "is_amazon_fulfilled": None,
        "merchant": None,
    }

    if error and not item:
        code = error.get("Code", "")
        if code == "InvalidParameterValue":
            result["status"] = "not_found"
        elif code == "ItemNotAccessible":
            result["status"] = "restricted"
        else:
            result["status"] = "not_found"
        result["availability_message"] = error.get("Message", code)
        return result

    if not item:
        result["status"] = "not_found"
        return result

    # Titre
    title_info = item.get("ItemInfo", {}).get("Title", {})
    result["title"] = title_info.get("DisplayValue")

    # Marque
    byline = item.get("ItemInfo", {}).get("ByLineInfo", {})
    brand = byline.get("Brand", {})
    result["brand"] = brand.get("DisplayValue")

    # Avis
    reviews = item.get("CustomerReviews", {})
    result["review_count"] = reviews.get("Count")
    star_rating = reviews.get("StarRating", {})
    result["review_score"] = star_rating.get("Value")

    # Images
    images = item.get("Images", {}).get("Primary", {})
    medium = images.get("Medium", {})
    result["image_medium"] = medium.get("URL")
    large = images.get("Large", {})
    result["image_large"] = large.get("URL")

    # Offres et disponibilité
    offers = item.get("Offers", {})
    listings = offers.get("Listings", [])

    if listings:
        listing = listings[0]  # première offre (généralement Amazon ou meilleure)

        # Prix
        price = listing.get("Price", {})
        result["price"] = price.get("Amount")
        result["price_currency"] = price.get("Currency")
        result["price_display"] = price.get("DisplayAmount")

        # Disponibilité
        avail = listing.get("Availability", {})
        avail_msg = avail.get("Message", "")
        avail_type = avail.get("Type", "")
        result["availability_message"] = avail_msg

        # Merchant
        merchant = listing.get("MerchantInfo", {})
        result["merchant"] = merchant.get("Name")

        # Fulfillment
        delivery = listing.get("DeliveryInfo", {})
        result["is_amazon_fulfilled"] = delivery.get("IsAmazonFulfilled")

        if avail_type == "Now":
            result["status"] = "available"
        elif "rupture" in avail_msg.lower() or "unavailable" in avail_msg.lower():
            result["status"] = "out_of_stock"
        elif result["price"] is not None:
            result["status"] = "available"
        else:
            result["status"] = "out_of_stock"
    else:
        result["status"] = "discontinued"
        result["availability_message"] = "Aucune offre disponible"

    return result


# ---------------------------------------------------------------------------
# Lecture du CSV d'entrée
# ---------------------------------------------------------------------------

def read_asins(path: str) -> list:
    """Lit le CSV et retourne une liste de dicts {post_id, asin}."""
    items = []
    seen = set()

    with open(path, newline="", encoding="utf-8-sig") as f:
        sample = f.read(4096)
        f.seek(0)
        dialect = csv.Sniffer().sniff(sample, delimiters=",;\t")
        reader = csv.DictReader(f, dialect=dialect)

        # Normalisation des noms de colonnes
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


# ---------------------------------------------------------------------------
# Checkpoint (reprise sur interruption)
# ---------------------------------------------------------------------------

def load_checkpoint(path: str) -> dict:
    if os.path.exists(path):
        with open(path, "r") as f:
            return json.load(f)
    return {}


def save_checkpoint(path: str, data: dict):
    tmp = path + ".tmp"
    with open(tmp, "w") as f:
        json.dump(data, f)
    os.replace(tmp, path)


# ---------------------------------------------------------------------------
# Traitement principal
# ---------------------------------------------------------------------------

def process_all(api: AmazonPAAPI, items: list, output_path: str,
                checkpoint_path: str) -> list:
    """Traite tous les ASINs par batch. Retourne la liste de résultats."""

    checkpoint = load_checkpoint(checkpoint_path)
    results = checkpoint.get("results", {})
    processed_asins = set(results.keys())

    remaining = [it for it in items if it["asin"] not in processed_asins]
    total = len(items)
    done = len(processed_asins)

    if done > 0:
        log.info("Reprise : %d/%d déjà traités, %d restants", done, total, len(remaining))

    # Map asin → post_id
    asin_to_post = {it["asin"]: it["post_id"] for it in items}

    batches = [remaining[i:i + BATCH_SIZE] for i in range(0, len(remaining), BATCH_SIZE)]

    for batch_idx, batch in enumerate(batches):
        batch_asins = [it["asin"] for it in batch]
        batch_num = batch_idx + 1
        total_batches = len(batches)

        try:
            response = api.get_items(batch_asins)
        except Exception as e:
            log.error("Erreur batch %d/%d (%s) : %s", batch_num, total_batches,
                      batch_asins, e)
            for it in batch:
                results[it["asin"]] = {
                    "asin": it["asin"],
                    "post_id": it["post_id"],
                    "status": "error",
                    "error": str(e),
                }
            save_checkpoint(checkpoint_path, {"results": results})
            time.sleep(BASE_DELAY)
            continue

        # Parser la réponse
        items_data = {}
        for item in response.get("ItemsResult", {}).get("Items", []):
            items_data[item["ASIN"]] = item

        errors_data = {}
        for err in response.get("Errors", []):
            msg = err.get("Message", "")
            for asin in batch_asins:
                if asin in msg:
                    errors_data[asin] = err

        for it in batch:
            asin = it["asin"]
            item_data = items_data.get(asin)
            error_data = errors_data.get(asin)

            result = classify_product(item_data, error_data)
            result["asin"] = asin
            result["post_id"] = it["post_id"]
            result["fetched_at"] = datetime.datetime.utcnow().isoformat() + "Z"
            results[asin] = result

        done += len(batch)
        save_checkpoint(checkpoint_path, {"results": results})

        available = sum(1 for r in results.values() if r.get("status") == "available")
        oos = sum(1 for r in results.values() if r.get("status") == "out_of_stock")
        disc = sum(1 for r in results.values() if r.get("status") == "discontinued")
        nf = sum(1 for r in results.values() if r.get("status") == "not_found")

        log.info(
            "Batch %d/%d — %d/%d traités "
            "[dispo:%d oos:%d arrêté:%d introuvable:%d]",
            batch_num, total_batches, done, total,
            available, oos, disc, nf,
        )

        if batch_idx < len(batches) - 1:
            time.sleep(BASE_DELAY)

    # Convertir en liste ordonnée
    result_list = []
    for it in items:
        if it["asin"] in results:
            result_list.append(results[it["asin"]])

    return result_list


# ---------------------------------------------------------------------------
# Sortie
# ---------------------------------------------------------------------------

def write_json(results: list, path: str):
    with open(path, "w", encoding="utf-8") as f:
        json.dump(results, f, ensure_ascii=False, indent=2)
    log.info("JSON écrit : %s (%d produits)", path, len(results))


def write_csv(results: list, path: str):
    if not results:
        return

    fields = [
        "post_id", "asin", "status", "title", "brand",
        "review_count", "review_score",
        "price", "price_currency", "price_display",
        "image_medium", "image_large",
        "availability_message", "merchant", "is_amazon_fulfilled",
        "fetched_at",
    ]

    with open(path, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        for r in results:
            writer.writerow(r)

    log.info("CSV écrit : %s (%d produits)", path, len(results))


def print_summary(results: list):
    total = len(results)
    if total == 0:
        log.info("Aucun résultat.")
        return

    statuses = {}
    for r in results:
        s = r.get("status", "unknown")
        statuses[s] = statuses.get(s, 0) + 1

    with_reviews = sum(1 for r in results if r.get("review_count"))
    with_price = sum(1 for r in results if r.get("price") is not None)
    with_image = sum(1 for r in results if r.get("image_medium"))

    log.info("=" * 50)
    log.info("RÉSUMÉ — %d produits traités", total)
    log.info("-" * 50)
    for status, count in sorted(statuses.items()):
        pct = count / total * 100
        log.info("  %-15s : %5d  (%5.1f%%)", status, count, pct)
    log.info("-" * 50)
    log.info("  Avec avis       : %5d  (%5.1f%%)", with_reviews, with_reviews / total * 100)
    log.info("  Avec prix       : %5d  (%5.1f%%)", with_price, with_price / total * 100)
    log.info("  Avec image      : %5d  (%5.1f%%)", with_image, with_image / total * 100)
    log.info("=" * 50)


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(
        description="Récupère les données produits Amazon via PA-API 5.0",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Exemples :
  # Traiter un fichier CSV
  python amazon-product-data.py --input asins.csv --output results.json

  # Avec marketplace US
  AMAZON_MARKETPLACE=www.amazon.com python amazon-product-data.py -i asins.csv

  # Tester avec quelques ASINs en ligne de commande
  python amazon-product-data.py --asins B08N5WRWNW,B09V3KXJPB --output test.json

Formats d'entrée CSV acceptés :
  post_id,asin        (avec identifiant WordPress)
  asin                (ASINs seuls)
  id,asin             (variante)

Variables d'environnement requises :
  AMAZON_ACCESS_KEY   Clé d'accès PA-API
  AMAZON_SECRET_KEY   Clé secrète PA-API
  AMAZON_PARTNER_TAG  Tag Associates (ex: monsite-21)
  AMAZON_MARKETPLACE  Marketplace (défaut: www.amazon.fr)
        """,
    )
    parser.add_argument("-i", "--input", help="CSV d'entrée (colonnes: asin, post_id)")
    parser.add_argument("-o", "--output", default="results.json",
                        help="Fichier de sortie JSON (défaut: results.json)")
    parser.add_argument("--csv", help="Fichier de sortie CSV en plus du JSON")
    parser.add_argument("--asins", help="ASINs séparés par des virgules (test rapide)")
    parser.add_argument("--delay", type=float, default=BASE_DELAY,
                        help=f"Délai entre requêtes en secondes (défaut: {BASE_DELAY})")
    parser.add_argument("--no-resume", action="store_true",
                        help="Ne pas reprendre depuis le checkpoint")
    parser.add_argument("--marketplace", default=None,
                        help="Marketplace Amazon (défaut: www.amazon.fr)")

    args = parser.parse_args()

    # Credentials
    access_key = os.environ.get("AMAZON_ACCESS_KEY", "")
    secret_key = os.environ.get("AMAZON_SECRET_KEY", "")
    partner_tag = os.environ.get("AMAZON_PARTNER_TAG", "")
    marketplace = args.marketplace or os.environ.get("AMAZON_MARKETPLACE", "www.amazon.fr")

    if not all([access_key, secret_key, partner_tag]):
        print(
            "Erreur : variables d'environnement manquantes.\n"
            "Configurez AMAZON_ACCESS_KEY, AMAZON_SECRET_KEY et AMAZON_PARTNER_TAG\n"
            "(dans un fichier .env ou en variables d'environnement).",
            file=sys.stderr,
        )
        sys.exit(1)

    # Items à traiter
    if args.asins:
        items = [
            {"post_id": None, "asin": a.strip().upper()}
            for a in args.asins.split(",") if a.strip()
        ]
    elif args.input:
        items = read_asins(args.input)
    else:
        print("Erreur : spécifiez --input ou --asins", file=sys.stderr)
        sys.exit(1)

    if not items:
        print("Aucun ASIN à traiter.", file=sys.stderr)
        sys.exit(1)

    log.info("Marketplace : %s", marketplace)
    log.info("ASINs à traiter : %d", len(items))
    log.info("Batches estimés : %d", (len(items) + BATCH_SIZE - 1) // BATCH_SIZE)
    est_minutes = len(items) / BATCH_SIZE * args.delay / 60
    log.info("Durée estimée : ~%.0f minutes", est_minutes)

    global BASE_DELAY
    BASE_DELAY = args.delay

    # Checkpoint
    checkpoint_path = args.output + ".checkpoint"
    if args.no_resume and os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)

    # API
    api = AmazonPAAPI(access_key, secret_key, partner_tag, marketplace)

    # Traitement
    start = time.time()
    results = process_all(api, items, args.output, checkpoint_path)
    elapsed = time.time() - start

    # Sortie
    write_json(results, args.output)
    csv_path = args.csv or args.output.rsplit(".", 1)[0] + ".csv"
    write_csv(results, csv_path)

    # Nettoyage checkpoint
    if os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)

    # Résumé
    print_summary(results)
    log.info("Terminé en %.0f secondes.", elapsed)


if __name__ == "__main__":
    main()
