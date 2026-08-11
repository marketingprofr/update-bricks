#!/usr/bin/env python3
"""
Export ONLY the new intros from this batch (excludes already-deployed ones).
Output: content/intros-batch-export.csv (same format as intros-export.csv).

Usage: python3 content/build-batch-export.py
"""

import csv
import os
import re
import sys

INTROS_DIR = os.path.join(os.path.dirname(__file__), 'intros')
OUTPUT = os.path.join(os.path.dirname(__file__), 'intros-batch-export.csv')
POOL_FILE = os.path.join(INTROS_DIR, 'onlyurlsandcats.csv')
SKIP = {'onlyurlsandcats.csv', 'betterintros.csv'}

ALREADY_DEPLOYED = {
    '37279','37421','37471','37517','38964','39048','39575',
    '40638','40658','41079','41110','41200','42011','42388',
    '42450','42456','42475','42540','42551',
}

merged = {}

for fname in sorted(os.listdir(INTROS_DIR)):
    if not fname.endswith('.csv') or fname in SKIP:
        continue
    path = os.path.join(INTROS_DIR, fname)
    with open(path, newline='', encoding='utf-8') as fh:
        rows = list(csv.reader(fh, delimiter=';'))

    if len(rows[0]) < 6 or rows[0][5] != 'mltv5_new_intro':
        continue

    for i, row in enumerate(rows[1:], 2):
        if len(row) < 6 or not row[5].strip():
            continue

        post_id = row[0].strip()
        intro = row[5].strip()

        if post_id in ALREADY_DEPLOYED:
            continue

        merged[post_id] = intro

with open(OUTPUT, 'w', newline='', encoding='utf-8') as fh:
    writer = csv.writer(fh, delimiter=';', quoting=csv.QUOTE_ALL)
    writer.writerow(['ID', 'mltv5_new_intro'])
    for post_id in sorted(merged, key=int):
        writer.writerow([post_id, merged[post_id]])

print(f'Written: {OUTPUT} ({len(merged)} rows, {len(ALREADY_DEPLOYED)} already-deployed skipped)')
