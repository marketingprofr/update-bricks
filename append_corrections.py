#!/usr/bin/env python3
"""Append des corrections au CSV avant/apres.
Usage: python3 append_corrections.py <fichier_json>

Le fichier JSON contient une liste de corrections :
[
  {"id": "12345", "titre": "Les meilleurs ...", "avant": "<html avant>", "apres": "<html apres>"},
  ...
]

Les corrections sont ajoutees a la fin de content/intros-corrections.csv.
"""
import csv, json, sys, os

DEST = 'content/intros-corrections.csv'

corrections_file = sys.argv[1]
with open(corrections_file, encoding='utf-8') as f:
    corrections = json.load(f)

write_header = not os.path.exists(DEST) or os.path.getsize(DEST) == 0

with open(DEST, 'a', encoding='utf-8', newline='') as fh:
    writer = csv.writer(fh, delimiter=';', quoting=csv.QUOTE_ALL)
    if write_header:
        writer.writerow(['ID', 'post_title', 'avant', 'apres'])
    for c in corrections:
        writer.writerow([c['id'], c['titre'], c['avant'], c['apres']])

print(f'{len(corrections)} corrections ajoutees a {DEST}')
