#!/usr/bin/env python3
"""Extrait 10 intros depuis intros-all.csv pour un agent.
Usage: python3 extract_intros.py <numero_batch>
Batch 0 = intros 1-10, batch 1 = intros 11-20, etc.
"""
import csv, sys

SOURCE = 'content/intros-all.csv'
BATCH_SIZE = 10

batch = int(sys.argv[1])
start = batch * BATCH_SIZE

with open(SOURCE, encoding='utf-8') as fh:
    reader = csv.reader(fh, delimiter=';')
    header = next(reader)
    rows = list(reader)

total = len(rows)
end = min(start + BATCH_SIZE, total)

if start >= total:
    print(f'Batch {batch} : hors limites (total = {total} intros, max batch = {(total - 1) // BATCH_SIZE})')
    sys.exit(1)

print(f'Batch {batch} : intros {start + 1} a {end} sur {total}')
print(f'Batches restants apres celui-ci : {max(0, (total - end + BATCH_SIZE - 1) // BATCH_SIZE)}')
print()

for i in range(start, end):
    pid, title, intro = rows[i][0], rows[i][1], rows[i][2]
    print(f'=== {pid} | {title} ===')
    print(intro)
    print()
