#!/usr/bin/env bash
# Mesure du premier mot avant et après la course entre fournisseurs (chantier « premier mot », 15 novembre 2026).
# « Avant » = course éteinte (délai 0), « après » = course à 1 000 millisecondes ; les deux sont alternés à chaque
# essai, pour que les deux côtés aient les mêmes conditions du moment. Site local réglé comme en production.
#   scripts/mesurer-course.sh <adresse du site> <dossier de sortie> <nombre d'essais>
# Variables : BASE_SQLITE et COMPTE (comme scripts/mesurer-vitesse.js), ENV_SITE (fichier des réglages du site).
set -euo pipefail
base="$1"; sortie="$2"; essais="${3:-10}"
mkdir -p "$sortie"
regler() { (. "$ENV_SITE" && php artisan tinker --execute="app(\App\Support\Settings::class)->set('answer_race_delay_ms', $1);" >/dev/null); }
for i in $(seq 1 "$essais"); do
  for mode in avant apres; do
    if [ "$mode" = avant ]; then regler 0; else regler 1000; fi
    SEULEMENT=salut,question,longue REPETITIONS=1 node scripts/mesurer-vitesse.js "$base" "$sortie/$mode-$i.json" | grep scenario | sed "s/^/$mode $i /"
  done
done
regler 1000
