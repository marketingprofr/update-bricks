#!/usr/bin/env python3
"""
Amazon Creators API — Product Data Fetcher
==========================================
Récupère les données produits Amazon (avis, prix, dispo, image) via la
**Creators API** (successeur officiel de PA-API 5.0, dépréciée le 15/05/2026)
pour une liste d'ASINs.

Différences clés vs PA-API 5.0 :
  - Auth OAuth2 client-credentials (Bearer token, ~1h) au lieu d'AWS SigV4.
  - Host unifié https://creatorsapi.amazon (plus de host par marketplace).
  - Champs en lowerCamelCase ; offres via offersV2.

Prérequis :
  pip install requests python-dotenv

Credentials (dans .env ou variables d'environnement) — Associates Central →
Outils → Creators API → Create Application → Add New Credential :
  AMAZON_CREDENTIAL_ID      Credential ID
  AMAZON_CREDENTIAL_SECRET  Credential Secret
  AMAZON_PARTNER_TAG        Tag Associates (ex: monsite-21)
  AMAZON_MARKETPLACE        Marketplace (défaut: www.amazon.fr)

Usage :
  # 1) TOUJOURS commencer par un self-test (auth + 2 ASINs, imprime le JSON brut)
  python amazon-creators-api.py --selftest --asins B08N5WRWNW,B09V3KXJPB

  # 2) Puis le run complet
  python amazon-creators-api.py --input asins.csv --output results.json

═══════════════════════════════════════════════════════════════════════════
⚠️  3 DÉTAILS INFÉRÉS (doc officielle Amazon bloquée en 403 côté fetcher).
    Le self-test les confirme en 30 s. Si le 1er appel échoue, basculez la
    constante correspondante ci-dessous et relancez `--selftest`.
═══════════════════════════════════════════════════════════════════════════
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
log = logging.getLogger("amazon-creators")

# ═══════════════════════════════════════════════════════════════════════════
# CONSTANTES À CONFIRMER AU 1er SELF-TEST  (valeurs = hypothèse la plus probable)
# ═══════════════════════════════════════════════════════════════════════════

# [1] Encodage de la requête de token OAuth.
#     "form" = application/x-www-form-urlencoded (comportement LWA/SP-API classique).
#     "json" = corps JSON. Si le token échoue en 400/415, basculez sur l'autre.
TOKEN_STYLE = "form"

# [2] Comment le marketplace est transmis à getItems.
#     "header" = en-tête HTTP `x-marketplace` (le plus rapporté).
#     "body"   = champ `marketplace` dans le corps JSON (comme PA-API 5.0).
MARKETPLACE_MODE = "header"

# [3] En-tête d'autorisation des appels API.
#     False = `Authorization: Bearer <token>` (v3.x ne porterait plus la version).
#     True  = ajoute un en-tête `x-amz-creators-api-version: <version>`.
AUTH_HEADER_INCLUDES_VERSION = False
CREDENTIAL_VERSION = "3.2"  # 3.1=NA, 3.2=EU (France), 3.3=Far East

# ═══════════════════════════════════════════════════════════════════════════

# Endpoints token OAuth par région (la région dépend du marketplace).
TOKEN_ENDPOINTS = {
    "na": "https://api.amazon.com/auth/o2/token",
    "eu": "https://api.amazon.co.uk/auth/o2/token",
    "fe": "https://api.amazon.co.jp/auth/o2/token",
}
TOKEN_SCOPE = "creatorsapi::default"

# Host + endpoint unifiés de la Creators API.
API_HOST = "https://creatorsapi.amazon"
GETITEMS_PATH = "/catalog/v1/getItems"

# Marketplace → région (pour choisir l'endpoint token).
MARKETPLACE_REGION = {
    "www.amazon.fr":    "eu",
    "www.amazon.co.uk": "eu",
    "www.amazon.de":    "eu",
    "www.amazon.es":    "eu",
    "www.amazon.it":    "eu",
    "www.amazon.com":   "na",
    "www.amazon.ca":    "na",
    "www.amazon.co.jp": "fe",
}

# Ressources demandées (lowerCamelCase — Creators API).
RESOURCES = [
    "customerReviews.count",
    "customerReviews.starRating",
    "images.primary.medium",
    "images.primary.large",
    "images.primary.highRes",
    "itemInfo.title",
    "itemInfo.byLineInfo",
    "offersV2.listings.price",
    "offersV2.listings.availability",
    "offersV2.listings.condition",
    "offersV2.listings.merchantInfo",
    # NB: OffersV2 n'expose PAS deliveryInfo (contrairement à Offers v1).
    # Ressources OffersV2 valides : price, availability, condition,
    # merchantInfo, type, isBuyBoxWinner, dealDetails, loyaltyPoints.
]

BATCH_SIZE = 10
BASE_DELAY = 1.1
MAX_RETRIES = 4
RETRY_BACKOFF = 2.0


# ---------------------------------------------------------------------------
# Petit utilitaire : creuser un dict par plusieurs chemins possibles
# ---------------------------------------------------------------------------

def dig(obj, *paths, default=None):
    """
    Renvoie la 1re valeur non-None trouvée parmi plusieurs chemins pointés.
    Ex: dig(item, "offersV2.listings", "offers.listings") tolère les 2 formes.
    Rend le parsing robuste face aux incertitudes de shape (offersV2, price…).
    """
    for path in paths:
        cur = obj
        ok = True
        for key in path.split("."):
            if isinstance(cur, dict) and key in cur:
                cur = cur[key]
            else:
                ok = False
                break
        if ok and cur is not None:
            return cur
    return default


# ---------------------------------------------------------------------------
# Auth OAuth2 (client-credentials) avec cache du token
# ---------------------------------------------------------------------------

class CreatorsAuth:
    def __init__(self, credential_id: str, credential_secret: str, region: str,
                 session: requests.Session):
        self.credential_id = credential_id
        self.credential_secret = credential_secret
        self.endpoint = TOKEN_ENDPOINTS[region]
        self.session = session
        self._token = None
        self._expires_at = 0.0

    def token(self) -> str:
        # Réutilise le token tant qu'il reste > 5 min de validité.
        if self._token and time.time() < self._expires_at - 300:
            return self._token

        params = {
            "grant_type": "client_credentials",
            "client_id": self.credential_id,
            "client_secret": self.credential_secret,
            "scope": TOKEN_SCOPE,
        }

        if TOKEN_STYLE == "json":
            resp = self.session.post(self.endpoint, json=params, timeout=15)
        else:
            resp = self.session.post(
                self.endpoint, data=params, timeout=15,
                headers={"Content-Type": "application/x-www-form-urlencoded"},
            )

        if resp.status_code != 200:
            raise RuntimeError(
                f"Échec d'authentification ({resp.status_code}) sur {self.endpoint}\n"
                f"Réponse : {resp.text[:500]}\n"
                f"→ Vérifiez AMAZON_CREDENTIAL_ID / _SECRET, l'éligibilité du compte "
                f"(10 ventes / 30 j), et la constante TOKEN_STYLE (actuel: {TOKEN_STYLE!r})."
            )

        data = resp.json()
        self._token = data["access_token"]
        self._expires_at = time.time() + float(data.get("expires_in", 3600))
        log.info("Token OAuth obtenu (valide %ds).", int(data.get("expires_in", 3600)))
        return self._token


# ---------------------------------------------------------------------------
# Client Creators API
# ---------------------------------------------------------------------------

class CreatorsAPI:
    def __init__(self, credential_id: str, credential_secret: str,
                 partner_tag: str, marketplace: str = "www.amazon.fr"):
        self.partner_tag = partner_tag
        self.marketplace = marketplace

        region = MARKETPLACE_REGION.get(marketplace)
        if not region:
            raise ValueError(
                f"Marketplace inconnu : {marketplace}. "
                f"Valeurs : {', '.join(MARKETPLACE_REGION)}"
            )

        self.session = requests.Session()
        self.auth = CreatorsAuth(credential_id, credential_secret, region, self.session)
        self.endpoint = API_HOST + GETITEMS_PATH

    def _headers(self) -> dict:
        h = {
            "Authorization": f"Bearer {self.auth.token()}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        }
        if MARKETPLACE_MODE == "header":
            h["x-marketplace"] = self.marketplace
        if AUTH_HEADER_INCLUDES_VERSION:
            h["x-amz-creators-api-version"] = CREDENTIAL_VERSION
        return h

    def _payload(self, asins: list) -> str:
        body = {
            "itemIds": asins,
            "resources": RESOURCES,
            "partnerTag": self.partner_tag,
            "partnerType": "Associates",
        }
        if MARKETPLACE_MODE == "body":
            body["marketplace"] = self.marketplace
        return json.dumps(body)

    def get_items(self, asins: list) -> dict:
        """Appelle getItems pour 1-10 ASINs. Retourne le JSON brut."""
        if len(asins) > 10:
            raise ValueError("Maximum 10 ASINs par requête")

        payload = self._payload(asins)

        for attempt in range(MAX_RETRIES + 1):
            try:
                resp = self.session.post(
                    self.endpoint, data=payload,
                    headers=self._headers(), timeout=20,
                )

                if resp.status_code == 401:
                    # Token expiré/invalide → force un refresh et réessaie une fois.
                    self.auth._token = None
                    if attempt < MAX_RETRIES:
                        continue

                if resp.status_code == 429:
                    wait = float(resp.headers.get(
                        "Retry-After", BASE_DELAY * (RETRY_BACKOFF ** attempt)))
                    log.warning("Throttled (429). Attente %.1fs…", wait)
                    time.sleep(wait)
                    continue

                if resp.status_code >= 500:
                    wait = BASE_DELAY * (RETRY_BACKOFF ** attempt)
                    log.warning("Erreur serveur %d. Retry dans %.1fs…",
                                resp.status_code, wait)
                    time.sleep(wait)
                    continue

                if resp.status_code >= 400:
                    # Erreur client non transitoire : on remonte le message serveur.
                    raise RuntimeError(
                        f"HTTP {resp.status_code} : {resp.text[:500]}"
                    )

                return resp.json()

            except requests.exceptions.RequestException as e:
                if attempt < MAX_RETRIES:
                    wait = BASE_DELAY * (RETRY_BACKOFF ** attempt)
                    log.warning("Erreur réseau : %s. Retry dans %.1fs…", e, wait)
                    time.sleep(wait)
                else:
                    raise

        raise RuntimeError(f"Échec après {MAX_RETRIES} tentatives pour {asins}")


# ---------------------------------------------------------------------------
# Classification (parsing camelCase, défensif)
# ---------------------------------------------------------------------------

def classify_product(item: dict | None, error: dict | None) -> dict:
    """
    Statuts :
      available     En stock, achetable
      out_of_stock  Temporairement indisponible
      discontinued  Page existe mais aucune offre
      not_found     ASIN invalide / page supprimée (erreur explicite)
      restricted    Existant mais accès API restreint
      no_data       Ni item ni erreur renvoyés (⚠ NE PAS confondre avec not_found)
    """
    result = {
        "status": None, "title": None, "brand": None,
        "review_count": None, "review_score": None,
        "price": None, "price_currency": None, "price_display": None,
        "image": None, "image_large": None,
        "availability_message": None, "availability_type": None,
        "merchant": None, "is_amazon_fulfilled": None,
    }

    # Erreur explicite (code Amazon) → statut déterministe.
    if error and not item:
        code = dig(error, "code", "Code", default="")
        msg = dig(error, "message", "Message", default=code)
        if code in ("InvalidParameterValue", "ItemNotFound"):
            result["status"] = "not_found"
        elif code in ("ItemNotAccessible", "AccessDenied"):
            result["status"] = "restricted"
        else:
            result["status"] = "not_found"
        result["availability_message"] = msg
        return result

    # ⚠ Correctif review : absence d'item SANS erreur ≠ page supprimée.
    if not item:
        result["status"] = "no_data"
        return result

    # Titre / marque
    result["title"] = dig(item, "itemInfo.title.displayValue")
    result["brand"] = dig(item, "itemInfo.byLineInfo.brand.displayValue")

    # Avis (peuvent être absents si le compte n'est pas éligible aux reviews)
    result["review_count"] = dig(item, "customerReviews.count")
    result["review_score"] = dig(
        item, "customerReviews.starRating.value", "customerReviews.starRating")

    # Images — "image" = image principale (primary), meilleure qualité dispo
    # (highRes → large → medium). image_large = 500px, si tu préfères plus léger.
    result["image"] = dig(item, "images.primary.highRes.url",
                          "images.primary.large.url", "images.primary.medium.url")
    result["image_large"] = dig(item, "images.primary.large.url")

    # Offres (offersV2, avec repli sur l'ancienne forme offers par prudence)
    listings = dig(item, "offersV2.listings", "offers.listings", default=[])
    if listings:
        listing = listings[0]

        # Prix : offersV2 restructure en price.money.{amount,currencyCode} ;
        # on tente plusieurs formes pour rester robuste.
        result["price"] = dig(
            listing, "price.money.amount", "price.amount", "price.value")
        result["price_currency"] = dig(
            listing, "price.money.currencyCode", "price.currency")
        result["price_display"] = dig(
            listing, "price.money.displayAmount", "price.displayAmount",
            "price.displayString")

        avail_msg = dig(listing, "availability.message", default="")
        avail_type = dig(listing, "availability.type", default="")
        result["availability_message"] = avail_msg
        result["availability_type"] = avail_type

        result["merchant"] = dig(listing, "merchantInfo.name")
        # OffersV2 n'expose pas deliveryInfo → on ne peut pas savoir
        # "fulfilled by Amazon". Le vendeur reste dispo via `merchant`.
        result["is_amazon_fulfilled"] = None

        # OffersV2 renvoie availability.type en MAJUSCULES (IN_STOCK,
        # OUT_OF_STOCK, IN_STOCK_SCARCE, PREORDER, BACKORDER…).
        t = (avail_type or "").upper()
        msg = (avail_msg or "").lower()
        if "OUT_OF_STOCK" in t or any(w in msg for w in ("rupture", "indispon", "unavailable")):
            result["status"] = "out_of_stock"
        elif t.startswith("IN_STOCK") or t in ("NOW", "PREORDER", "BACKORDER", "LEADTIME"):
            result["status"] = "available"
        elif result["price"] is not None:
            result["status"] = "available"
        else:
            result["status"] = "out_of_stock"
    else:
        result["status"] = "discontinued"
        result["availability_message"] = "Aucune offre disponible"

    return result


# ---------------------------------------------------------------------------
# Entrée CSV / checkpoint / sortie  (identiques à la version PA-API)
# ---------------------------------------------------------------------------

def read_asins(path: str) -> list:
    items, seen = [], set()
    with open(path, newline="", encoding="utf-8-sig") as f:
        sample = f.read(4096)
        f.seek(0)
        try:
            dialect = csv.Sniffer().sniff(sample, delimiters=",;\t")
        except csv.Error:
            dialect = csv.excel  # repli : CSV standard (1 colonne, etc.)
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


def process_all(api: CreatorsAPI, items: list, checkpoint_path: str) -> list:
    checkpoint = load_checkpoint(checkpoint_path)
    results = checkpoint.get("results", {})
    processed = set(results.keys())

    remaining = [it for it in items if it["asin"] not in processed]
    total, done = len(items), len(processed)
    if done:
        log.info("Reprise : %d/%d déjà traités, %d restants", done, total, len(remaining))

    batches = [remaining[i:i + BATCH_SIZE] for i in range(0, len(remaining), BATCH_SIZE)]

    for bi, batch in enumerate(batches):
        batch_asins = [it["asin"] for it in batch]
        try:
            response = api.get_items(batch_asins)
        except Exception as e:
            log.error("Erreur batch %d/%d (%s) : %s", bi + 1, len(batches), batch_asins, e)
            for it in batch:
                results[it["asin"]] = {
                    "asin": it["asin"], "post_id": it["post_id"],
                    "status": "error", "error": str(e),
                }
            save_checkpoint(checkpoint_path, {"results": results})
            time.sleep(BASE_DELAY)
            continue

        items_data = {}
        for item in dig(response, "itemsResult.items", "ItemsResult.Items", default=[]):
            asin = dig(item, "asin", "ASIN")
            if asin:
                items_data[asin] = item

        # Erreurs : on récupère l'ASIN dans le champ dédié si présent, sinon
        # repli sur la recherche par sous-chaîne dans le message.
        errors_data = {}
        for err in dig(response, "errors", "Errors", default=[]):
            asin = dig(err, "itemId", "ItemId", "asin", "ASIN")
            if asin and asin in batch_asins:
                errors_data[asin] = err
            else:
                msg = dig(err, "message", "Message", default="")
                for a in batch_asins:
                    if a in msg:
                        errors_data[a] = err

        for it in batch:
            asin = it["asin"]
            result = classify_product(items_data.get(asin), errors_data.get(asin))
            result["asin"] = asin
            result["post_id"] = it["post_id"]
            result["fetched_at"] = _now_iso()
            results[asin] = result

        done += len(batch)
        save_checkpoint(checkpoint_path, {"results": results})

        counts = {s: sum(1 for r in results.values() if r.get("status") == s)
                  for s in ("available", "out_of_stock", "discontinued",
                            "not_found", "no_data")}
        log.info(
            "Batch %d/%d — %d/%d [dispo:%d oos:%d arrêté:%d introuvable:%d no_data:%d]",
            bi + 1, len(batches), done, total,
            counts["available"], counts["out_of_stock"], counts["discontinued"],
            counts["not_found"], counts["no_data"],
        )
        if bi < len(batches) - 1:
            time.sleep(BASE_DELAY)

    return [results[it["asin"]] for it in items if it["asin"] in results]


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
        "image", "image_large",
        "availability_message", "availability_type",
        "merchant", "is_amazon_fulfilled", "fetched_at",
    ]
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
    statuses = {}
    for r in results:
        s = r.get("status", "unknown")
        statuses[s] = statuses.get(s, 0) + 1
    with_reviews = sum(1 for r in results if r.get("review_count"))
    with_price = sum(1 for r in results if r.get("price") is not None)
    with_image = sum(1 for r in results if r.get("image"))

    log.info("=" * 52)
    log.info("RÉSUMÉ — %d produits", total)
    log.info("-" * 52)
    for s, c in sorted(statuses.items()):
        log.info("  %-14s : %6d  (%5.1f%%)", s, c, c / total * 100)
    log.info("-" * 52)
    log.info("  avec avis    : %6d  (%5.1f%%)", with_reviews, with_reviews / total * 100)
    log.info("  avec prix    : %6d  (%5.1f%%)", with_price, with_price / total * 100)
    log.info("  avec image   : %6d  (%5.1f%%)", with_image, with_image / total * 100)
    log.info("=" * 52)
    if with_reviews == 0:
        log.warning(
            "Aucun avis récupéré → compte probablement NON éligible aux "
            "customerReviews. Tes besoins n°1/n°2 nécessiteront un autre canal.")


# ---------------------------------------------------------------------------
# Self-test : auth + 1 appel, imprime le JSON brut pour valider les 3 constantes
# ---------------------------------------------------------------------------

def run_selftest(api: CreatorsAPI, asins: list):
    log.info("=== SELF-TEST Creators API ===")
    log.info("TOKEN_STYLE=%r  MARKETPLACE_MODE=%r  AUTH_HEADER_INCLUDES_VERSION=%s",
             TOKEN_STYLE, MARKETPLACE_MODE, AUTH_HEADER_INCLUDES_VERSION)
    test_asins = asins[:2] or ["B08N5WRWNW"]
    log.info("1) Authentification…")
    api.auth.token()
    log.info("   ✓ token OK")
    log.info("2) getItems sur %s…", test_asins)
    response = api.get_items(test_asins)
    log.info("   ✓ réponse reçue")
    print("\n----- JSON BRUT (vérifie les chemins de champs) -----")
    print(json.dumps(response, ensure_ascii=False, indent=2)[:4000])
    print("----- FIN -----\n")
    for item in dig(response, "itemsResult.items", "ItemsResult.Items", default=[]):
        asin = dig(item, "asin", "ASIN")
        parsed = classify_product(item, None)
        log.info("Parsé %s → status=%s prix=%s avis=%s note=%s img=%s",
                 asin, parsed["status"], parsed["price"],
                 parsed["review_count"], parsed["review_score"],
                 "oui" if parsed["image"] else "non")
    log.info("Self-test terminé. Si un champ clé est None mais présent dans le "
             "JSON brut ci-dessus, ajuste le chemin dans classify_product().")


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main():
    global BASE_DELAY
    parser = argparse.ArgumentParser(
        description="Récupère les données produits Amazon via la Creators API",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Étapes recommandées :
  1) python amazon-creators-api.py --selftest --asins B08N5WRWNW,B09V3KXJPB
     → confirme auth + shape des champs. Ajuste les 3 constantes si besoin.
  2) python amazon-creators-api.py --input asins.csv --output results.json
     → run complet (reprise auto via .checkpoint si interrompu).

Variables d'environnement :
  AMAZON_CREDENTIAL_ID      Credential ID (Creators API)
  AMAZON_CREDENTIAL_SECRET  Credential Secret
  AMAZON_PARTNER_TAG        Tag Associates (ex: monsite-21)
  AMAZON_MARKETPLACE        Marketplace (défaut: www.amazon.fr)
        """,
    )
    parser.add_argument("-i", "--input", help="CSV d'entrée (colonnes: asin, post_id)")
    parser.add_argument("-o", "--output", default="results.json")
    parser.add_argument("--csv", help="Fichier CSV de sortie (défaut: <output>.csv)")
    parser.add_argument("--asins", help="ASINs séparés par des virgules")
    parser.add_argument("--selftest", action="store_true",
                        help="Teste auth + 1 appel et imprime le JSON brut")
    parser.add_argument("--delay", type=float, default=BASE_DELAY)
    parser.add_argument("--no-resume", action="store_true")
    parser.add_argument("--marketplace", default=None)
    args = parser.parse_args()
    BASE_DELAY = args.delay

    credential_id = os.environ.get("AMAZON_CREDENTIAL_ID", "")
    credential_secret = os.environ.get("AMAZON_CREDENTIAL_SECRET", "")
    partner_tag = os.environ.get("AMAZON_PARTNER_TAG", "")
    marketplace = args.marketplace or os.environ.get("AMAZON_MARKETPLACE", "www.amazon.fr")

    if not all([credential_id, credential_secret, partner_tag]):
        print(
            "Erreur : variables manquantes.\n"
            "Configurez AMAZON_CREDENTIAL_ID, AMAZON_CREDENTIAL_SECRET et "
            "AMAZON_PARTNER_TAG (fichier .env ou environnement).",
            file=sys.stderr)
        sys.exit(1)

    api = CreatorsAPI(credential_id, credential_secret, partner_tag, marketplace)

    # Items
    if args.asins:
        items = [{"post_id": None, "asin": a.strip().upper()}
                 for a in args.asins.split(",") if a.strip()]
    elif args.input:
        items = read_asins(args.input)
    else:
        items = []

    # Self-test
    if args.selftest:
        try:
            run_selftest(api, [it["asin"] for it in items])
        except Exception as e:
            log.error("Self-test échoué : %s", e)
            log.error("→ Ajuste une des 3 constantes en tête de fichier puis relance.")
            sys.exit(1)
        return

    if not items:
        print("Erreur : spécifiez --input ou --asins", file=sys.stderr)
        sys.exit(1)

    log.info("Marketplace : %s | ASINs : %d | batches : %d",
             marketplace, len(items), (len(items) + BATCH_SIZE - 1) // BATCH_SIZE)
    log.info("Durée estimée : ~%.0f min", len(items) / BATCH_SIZE * args.delay / 60)

    checkpoint_path = args.output + ".checkpoint"
    if args.no_resume and os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)

    start = time.time()
    results = process_all(api, items, checkpoint_path)

    write_json(results, args.output)
    write_csv(results, args.csv or args.output.rsplit(".", 1)[0] + ".csv")
    if os.path.exists(checkpoint_path):
        os.remove(checkpoint_path)

    print_summary(results)
    log.info("Terminé en %.0f s.", time.time() - start)


if __name__ == "__main__":
    main()
