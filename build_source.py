#!/usr/bin/env python3
"""Consolide les 142 CSV de content/intros/ en un seul fichier source."""
import csv, os

INTROS_DIR = 'content/intros'
DEST = 'content/intros-all.csv'

seen = set()
rows_out = []

for f in sorted(os.listdir(INTROS_DIR)):
    if not f.endswith('.csv'):
        continue
    with open(os.path.join(INTROS_DIR, f), encoding='utf-8') as fh:
        reader = csv.reader(fh, delimiter=';')
        next(reader)
        for row in reader:
            if len(row) < 6 or not row[5].strip():
                continue
            pid = row[0].strip()
            if pid in seen:
                continue
            seen.add(pid)
            rows_out.append([pid, row[1].strip(), row[5].strip()])

rows_out.sort(key=lambda r: int(r[0]))

with open(DEST, 'w', encoding='utf-8', newline='') as fh:
    writer = csv.writer(fh, delimiter=';', quoting=csv.QUOTE_ALL)
    writer.writerow(['ID', 'post_title', 'mltv5_new_intro'])
    for r in rows_out:
        writer.writerow(r)

print(f'{len(rows_out)} intros ecrites dans {DEST}')
