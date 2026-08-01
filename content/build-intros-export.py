#!/usr/bin/env python3
"""
Merge tous les CSV de content/intros/ qui ont une colonne mltv5_new_intro
en un seul fichier intros-export.csv prêt pour WP-CLI.

Sortie : content/intros-export.csv
Format : ID;mltv5_new_intro (délimiteur ;, QUOTE_ALL)

Usage : python3 content/build-intros-export.py
"""

import csv
import os
import re
import sys

INTROS_DIR = os.path.join(os.path.dirname(__file__), 'intros')
OUTPUT = os.path.join(os.path.dirname(__file__), 'intros-export.csv')
POOL_FILE = os.path.join(INTROS_DIR, 'onlyurlsandcats.csv')
SKIP = {'onlyurlsandcats.csv', 'betterintros.csv'}

# Load URL pool for validation
url_pool = set()
with open(POOL_FILE, newline='', encoding='utf-8') as fh:
    for row in csv.reader(fh, delimiter=';'):
        if row[0] != 'post_title':
            url_pool.add(row[1].strip())

merged = {}  # id -> (title, intro)
errors = []
file_count = 0

for fname in sorted(os.listdir(INTROS_DIR)):
    if not fname.endswith('.csv') or fname in SKIP:
        continue
    path = os.path.join(INTROS_DIR, fname)
    with open(path, newline='', encoding='utf-8') as fh:
        rows = list(csv.reader(fh, delimiter=';'))

    if len(rows[0]) < 6 or rows[0][5] != 'mltv5_new_intro':
        continue

    file_count += 1
    for i, row in enumerate(rows[1:], 2):
        if len(row) < 6 or not row[5].strip():
            continue

        post_id = row[0].strip()
        title = row[1].strip()
        intro = row[5].strip()
        own_url = row[2].strip()

        # Validate
        links = re.findall(r'href="([^"]+)"', intro)
        words = len(re.sub(r'<[^>]+>', '', intro).split())

        if len(links) < 3:
            errors.append(f'{fname}:{i} links={len(links)}')
        if len(links) > 5:
            errors.append(f'{fname}:{i} links={len(links)} (max 5)')
        for l in links:
            if l not in url_pool:
                errors.append(f'{fname}:{i} bad URL: {l}')
            if l == own_url:
                errors.append(f'{fname}:{i} self-link')
        if words > 75:
            errors.append(f'{fname}:{i} {words}w (max 75)')
        if '—' in intro:
            errors.append(f'{fname}:{i} em dash')
        if '!' in intro:
            errors.append(f'{fname}:{i} exclamation')

        if post_id in merged and merged[post_id][1] != intro:
            errors.append(f'{fname}:{i} ID {post_id} duplicate with different intro')

        merged[post_id] = (title, intro)

# Report
print(f'Files scanned: {file_count}')
print(f'Unique intros: {len(merged)}')

if errors:
    print(f'\nERRORS ({len(errors)}):')
    for e in errors[:30]:
        print(f'  {e}')
    if len(errors) > 30:
        print(f'  ... and {len(errors) - 30} more')
    print('\nExport SKIPPED due to errors.')
    sys.exit(1)

# Write export
with open(OUTPUT, 'w', newline='', encoding='utf-8') as fh:
    writer = csv.writer(fh, delimiter=';', quoting=csv.QUOTE_ALL)
    writer.writerow(['ID', 'mltv5_new_intro'])
    for post_id in sorted(merged, key=int):
        writer.writerow([post_id, merged[post_id][1]])

print(f'Written: {OUTPUT} ({len(merged)} rows)')
