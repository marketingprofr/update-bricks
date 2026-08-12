#!/usr/bin/env python3
"""Append des corrections au CSV avant/apres.
Usage: python3 append_corrections.py <numero_batch>

Lit corrections-<batch>.json et ajoute les corrections a content/intros-corrections.csv.

corrections-<batch>.json contient une liste :
[
  {"id": "12345", "titre": "Les meilleurs ...", "avant": "<html avant>", "apres": "<html apres>"},
  ...
]
"""
import csv, json, sys

batch = sys.argv[1]
SOURCE = f'corrections-{batch}.json'
DEST = 'content/intros-corrections.csv'

with open(SOURCE, encoding='utf-8') as f:
    corrections = json.load(f)

with open(DEST, 'a', encoding='utf-8', newline='') as fh:
    writer = csv.writer(fh, delimiter=';', quoting=csv.QUOTE_ALL)
    for c in corrections:
        writer.writerow([c['id'], c['titre'], c['avant'], c['apres']])

print(f'{len(corrections)} corrections ajoutees a {DEST}')
