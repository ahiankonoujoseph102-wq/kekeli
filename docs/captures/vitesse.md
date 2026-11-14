# Vitesse : aussi rapide et fluide que Claude (15 novembre 2026)

Mesures prises sur un téléphone simulé (Pixel 7, Chrome, en français) contre un site local réglé comme en production : file d'attente `queue:work database --sleep=3`, vrai Runner avec les vraies clés de l'environnement (jamais affichées), modèles choisis par le routeur de Kekeli. Compte et messages inventés. Script : `scripts/mesurer-vitesse.js` ; résultats bruts : `docs/captures/vitesse/avant.json` et `docs/captures/vitesse/apres.json`.

**Limite de la mesure, à dire clairement** : en local, le site et le Runner ne dorment pas et sont sur la même machine. Sur Laravel Cloud, le premier message après une pause ajoute le réveil du site, de la base et du Runner, et le réseau du téléphone ajoute son propre délai.

## Avant (en secondes depuis le toucher sur « Envoyer »)

| Demande | Message affiché | Premier mot | Réponse finie |
|---|---|---|---|
| « Salut » (3 essais) | 0,2 · 0,2 · 0,2 | 4,3 · 8,3 · 2,3 | 4,3 · 8,3 · 2,3 |
| Question courte (3 essais) | 0,2 · 0,2 · 0,3 | 8,3 · 6,3 · 6,4 | 8,3 · 6,3 · 6,4 |
| Demande de site | 0,3 | travail affiché à 4,4 | première étape à 30,4 |

Où passe le temps (étapes notées par le site, en secondes depuis la réception du message) :

| Demande | Tâche reçue (file d'attente) | Appel au modèle → réponse entière | Réponse enregistrée |
|---|---|---|---|
| « Salut » | 2,3 · 2,0 · 0,3 | 1,6 · 4,3 · 1,3 | 4,0 · 6,3 · 1,6 |
| Question courte | 1,6 · 0,2 · 0,7 | 4,6 · 4,5 · 3,8 | 6,2 · 4,7 · 4,6 |
| Demande de site | 0,4 | 2,0 | 2,5 ; travail envoyé au Runner aussitôt, première étape 27,8 s plus tard (création de l'ordinateur virtuel) |

Trois causes : la tâche attend la file d'attente (jusqu'à 3 secondes, `--sleep=3`) ; le Runner attend la réponse entière du modèle avant de la rendre (aucun mot avant la fin) ; la page ne regarde s'il y a du nouveau que toutes les 2 secondes.
