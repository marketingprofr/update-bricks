#!/usr/bin/env python3
"""
Rewrites guide introductions with 3-5 internal links via claude CLI.
Usage:
  python3 process_intros.py [--all] [file1.csv file2.csv ...]
  --all : process all category CSVs sorted by row count (asc)
"""

import csv
import re
import sys
import subprocess
import json
from pathlib import Path

INTRO_DIR = Path(__file__).parent
URLS_CSV = INTRO_DIR / "onlyurlsandcats.csv"
MODEL = "claude-haiku-4-5-20251001"
MAX_CANDIDATES = 14   # max links shown to the model
SKIP_FILES = {"betterintros.csv", "onlyurlsandcats.csv", "process_intros.py"}

# ── helpers ──────────────────────────────────────────────────────────────────

def clean_url(url: str) -> str:
    return re.sub(r'[?#].*$', '', url).rstrip('/')

def is_meilleurtest(url: str) -> bool:
    return 'meilleurtest.fr' in url

def extract_links(html: str):
    """Return list of (href, anchor_text) from HTML."""
    return [
        (m.group(1).strip(), re.sub(r'<[^>]+>', '', m.group(2)).strip())
        for m in re.finditer(
            r'<a\s[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)</a>',
            html, re.I | re.S
        )
    ]

def load_valid_urls() -> dict:
    """Return {clean_url -> {title, category}}."""
    d = {}
    with open(URLS_CSV, encoding='utf-8') as f:
        for row in csv.DictReader(f, delimiter=';'):
            url = clean_url(row['_post_permalink'])
            d[url] = {'title': row['post_title'], 'category': row['category']}
    return d

def load_category_rows(filepath: Path) -> list[dict]:
    with open(filepath, encoding='utf-8') as f:
        return list(csv.DictReader(f, delimiter=';'))

# ── core rewrite ─────────────────────────────────────────────────────────────

SYSTEM = (
    "Tu es un rédacteur web expert pour meilleurtest.fr (guides d'achat en français).\n"
    "Règles ABSOLUES :\n"
    "• Ne jamais mentionner de produit, modèle ou marque spécifique.\n"
    "• Utiliser « vous » et le futur pour l'expérience utilisateur ; présent pour les faits.\n"
    "• Voix active. Phrases complètes avec verbe. Pas de phrases creuses.\n"
    "• Ne jamais écrire « Nous l'avons testé », « notre sélection », « notre expérience ».\n"
    "• Longueur similaire à l'originale, texte dense.\n"
    "• Liens HTML : <a href=\"URL\">texte d'ancre naturel</a>.\n"
    "• Retourner UNIQUEMENT le HTML brut de la nouvelle introduction, sans balise englobante."
)

def build_prompt(title, category, original, existing_links, candidates):
    existing_str = "\n".join(
        f'- <a href="{l["url"]}">{l["anchor"]}</a> — « {l["title"]} »'
        for l in existing_links
    ) or "Aucun"

    cand_str = "\n".join(
        f'- {c["url"]} — « {c["title"]} »'
        for c in candidates
    )

    return f"""Réécris cette introduction de guide d'achat en y intégrant 3 à 5 liens internes meilleurtest.fr.

GUIDE : {title}
CATÉGORIE : {category}

INTRODUCTION ORIGINALE :
{original}

LIENS DÉJÀ PRÉSENTS À CONSERVER en priorité (valides, meilleurtest.fr) :
{existing_str}

LIENS CANDIDATS SUPPLÉMENTAIRES (choisis ceux qui s'intègrent le plus naturellement) :
{cand_str}

CONSIGNES :
- Réécris l'intro en partant de l'idée développée dans l'originale.
- Intègre 3 à 5 liens au total ; garde les existants, complète avec des candidats pertinents.
- Texte d'ancre = expression naturelle tirée du contexte de la phrase (jamais l'URL brute).
- Ne mentionne aucun produit ni marque spécifique.
- Respecte les règles système (vous, voix active, longueur dense, pas de fausses prétentions de test).
- Retourne UNIQUEMENT le HTML de la nouvelle introduction."""


def rewrite(title, category, original, existing_links, candidates):
    prompt = build_prompt(title, category, original, existing_links, candidates)
    result = subprocess.run(
        ["claude", "--print", "--model", MODEL, "-p", SYSTEM],
        input=prompt,
        capture_output=True,
        text=True,
        timeout=120,
    )
    if result.returncode != 0:
        raise RuntimeError(f"claude CLI error: {result.stderr[:300]}")
    return result.stdout.strip()


# ── file processing ───────────────────────────────────────────────────────────

def process_file(filepath: Path, valid_urls: dict):
    rows = load_category_rows(filepath)
    if not rows:
        print(f"  skip (empty): {filepath.name}")
        return

    # Check if already processed (column exists)
    if 'mltv5_new_intro' in rows[0]:
        # Only process rows where new intro is missing
        to_do = [r for r in rows if not r.get('mltv5_new_intro', '').strip()]
        if not to_do:
            print(f"  already done: {filepath.name}")
            return
    else:
        to_do = rows

    # Category link pool (all guides in this file)
    cat_pool = {
        clean_url(r['_post_permalink']): r['post_title']
        for r in rows
    }

    for row in rows:
        if row.get('mltv5_new_intro', '').strip():
            continue  # already processed

        current_url = clean_url(row['_post_permalink'])
        original = row.get('mltv5_introduction', '')
        title = row.get('post_title', '')
        category = row.get('category', '')

        # Existing valid links from the intro
        existing_links = []
        for href, anchor in extract_links(original):
            if not is_meilleurtest(href):
                continue
            cleaned = clean_url(href)
            if cleaned in valid_urls:
                existing_links.append({
                    'url': cleaned,
                    'title': valid_urls[cleaned]['title'],
                    'anchor': anchor,
                })
        existing_set = {l['url'] for l in existing_links}

        # Candidates: same category first
        candidates = [
            {'url': url, 'title': t}
            for url, t in cat_pool.items()
            if url != current_url and url not in existing_set
        ]

        # Fill from global pool if needed
        if len(existing_links) + len(candidates) < 5:
            for url, info in valid_urls.items():
                if url == current_url or url in existing_set:
                    continue
                if any(c['url'] == url for c in candidates):
                    continue
                candidates.append({'url': url, 'title': info['title']})
                if len(candidates) >= MAX_CANDIDATES:
                    break

        candidates = candidates[:MAX_CANDIDATES]

        try:
            new_intro = rewrite(title, category, original, existing_links, candidates)
        except Exception as e:
            print(f"  ✗ {title[:50]}: {e}")
            new_intro = original  # fallback

        row['mltv5_new_intro'] = new_intro

    # Write output (same file, with new column)
    fieldnames = list(rows[0].keys())
    if 'mltv5_new_intro' not in fieldnames:
        fieldnames.append('mltv5_new_intro')

    with open(filepath, 'w', encoding='utf-8', newline='') as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames, delimiter=';')
        writer.writeheader()
        writer.writerows(rows)

    print(f"  ✓ {filepath.name}: {len(rows)} intro(s) rewritten")


# ── entry point ───────────────────────────────────────────────────────────────

def get_sorted_files() -> list[Path]:
    files = [
        p for p in INTRO_DIR.glob("*.csv")
        if p.name not in SKIP_FILES
    ]
    def row_count(p):
        with open(p, encoding='utf-8') as f:
            return sum(1 for _ in f) - 1  # minus header
    return sorted(files, key=row_count)


if __name__ == "__main__":
    args = sys.argv[1:]

    valid_urls = load_valid_urls()
    print(f"Loaded {len(valid_urls)} valid URLs")

    if "--all" in args:
        files = get_sorted_files()
        print(f"Processing {len(files)} files (smallest first)")
    elif args:
        files = [Path(a) if Path(a).is_absolute() else INTRO_DIR / a for a in args if not a.startswith("--")]
    else:
        # Default: 5 smallest for testing
        files = get_sorted_files()[:5]
        print("TEST MODE: processing 5 smallest files")

    for f in files:
        print(f"\n→ {f.name}")
        process_file(f, valid_urls)

    print("\nDone.")
