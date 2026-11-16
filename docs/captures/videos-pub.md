# Vidéos publicitaires captivantes et échecs expliqués comme Claude Code (15 et 16 novembre 2026)

Détails et décisions : [`docs/plan.md`, partie 26.41](../plan.md). Aucune dépense : tout a été fait avec les offres gratuites (le crédit OpenAI est épuisé).

## 1. Le banc des 5 publicités

Les mêmes 5 demandes, avant (code de `main`) et après (ce chantier), avec le Runner, les vrais modèles gratuits et de vrais ordinateurs E2B (`runner/scripts/banc-publicites.mjs`, demandes dans `runner/mesures/publicites-5.json`).

**Conditions, à lire avant les notes** : pendant tout le banc, **la création d'images gratuite (Cloudflare) était indisponible** (quota du jour vide, pas revenu à 1 h 30) : avant comme après, les images sont donc des photos Pixabay ou des fonds animés. Les scénarios ont été écrits par Gemini 3.5 Flash puis 3.8 Flash gratuits (les Flash-Lite habituels étaient épuisés par les bancs de la journée), les mêmes avant et après ; les paires du salon de coiffure et du téléphone ont été faites le 16 novembre (vers 6 h et 8 h 20) avec Gemini 3.5 Flash-Lite, le rédacteur habituel, des deux côtés. Le quota de Cloudflare n'était toujours pas revenu à 8 h 20 (il devait revenir à minuit) : à surveiller en ligne. Formule Gratuite (30 secondes au plus).

**Notes** (de 1 à 10, données par Claude en regardant une image toutes les 2 secondes et les sous-titres ; détail dans `runner/mesures/banc-publicites.json`) :

| Publicité | Accroche | Cohérence image et texte | Voix | Rythme | Appel à l'action | Moyenne | Temps de fabrication |
|---|---|---|---|---|---|---|---|
| Pagnes, avant | 3 | 3 | 1 | 3 | 7 | **3,4** | 3 min 35 s |
| Pagnes, après | 5 | 7 | 8 | 6 | 8 | **6,8** | 4 min 43 s |
| Formation en IA, avant | 3 | 3 | 7 | 4 | 7 | **4,8** | 2 min 4 s |
| Formation en IA, après | 8 | 6 | 8 | 6 | 6 | **6,8** | 5 min 28 s |
| Restaurant, avant | 3 | 5 | 1 | 4 | 7 | **4,0** | 5 min 51 s |
| Restaurant, après | 7 | 5 | 8 | 6 | 8 | **6,8** | 8 min 37 s |
| Salon de coiffure, avant | 3 | 4 | 1 | 4 | 7 | **3,8** | 2 min 24 s |
| Salon de coiffure, après | 8 | 5 | 8 | 6 | 9 | **7,2** | 4 min 36 s |
| Offre de téléphone, avant | 4 | 3 | 1 | 4 | 7 | **3,8** | 3 min 20 s |
| Offre de téléphone, après | 5 | 7 | 8 | 6 | 9 | **7,0** | 2 min 22 s |
| **Moyenne des 5** | | | | | | **4,0 → 6,9** | |

**Coût réel : 0 $** (modèles, voix, photos et musique gratuits). En énergie, au prix d'un service payant (estimation, `docs/couts.md`) : surtout le temps de l'ordinateur, environ 2 à 9 minutes, soit environ 3 à 14 FCFA par publicité.

### Ce qui change vraiment

- **La voix off** : avant, une requête gratuite **par phrase** ; la limite de 10 phrases par jour et par voix tombait au milieu de la vidéo, et Kekeli retirait alors toute la voix : **1 publicité sur 7 essais avec voix**. Après, la voix est dite **d'une traite** (une seule requête par vidéo), puis coupée aux pauses : **5 publicités sur 5 avec voix**, synchronisée avec les sous-titres, voix de femme ou d'homme selon le choix.
- **Les images** : avant, une photo était gardée dès que ses étiquettes contenaient un mot de la recherche : un marché de fruits pour une boutique de pagnes, des pois chiches pour le fufu, des hommes barbus pour un salon de tresses, une console de jeu ou un policier armé pour un smartphone, un skateur pour « 120 élèves formés ». Après, le **regard de Kekeli** les refuse ; il reste un défaut honnête (un enfant en toge pour « 120 élèves formés », un plat de viande pour le riz sauce arachide) et, **sans création d'images**, plusieurs scènes finissent en fond animé plutôt qu'avec une mauvaise photo.
- **La structure** : rôle de chaque scène suivi (accroche, solution, preuve, offre, action) ; 2 accroches sur 3 sont des questions (« Apprendre l'IA ? », « Envie d'un bon repas ? »). La voix rallonge encore les scènes (4 secondes environ) : le rythme reste à améliorer.
- **Le temps** : plus long après (les regards) ; jusqu'à 45 regards pour la publicité du restaurant : **limite de 4 photos regardées par scène** ajoutée ensuite.

### Images (une image toutes les 2 secondes)

| Avant | Après |
|---|---|
| ![Pagnes avant](videos-pub/avant/pagnes-planche.jpg) | ![Pagnes après](videos-pub/apres/pagnes-planche.jpg) |
| ![Formation avant](videos-pub/avant/formation-ia-planche.jpg) | ![Formation après](videos-pub/apres/formation-ia-planche.jpg) |
| ![Restaurant avant](videos-pub/avant/restaurant-planche.jpg) | ![Restaurant après](videos-pub/apres/restaurant-planche.jpg) |
| ![Coiffure avant](videos-pub/avant/coiffure-planche.jpg) | ![Coiffure après](videos-pub/apres/coiffure-planche.jpg) |
| ![Téléphone avant](videos-pub/avant/telephone-planche.jpg) | ![Téléphone après](videos-pub/apres/telephone-planche.jpg) |

Les vidéos (allégées pour le dépôt) : [`videos-pub/avant/`](videos-pub/avant/) et [`videos-pub/apres/`](videos-pub/apres/) (sur le téléphone : toucher le fichier, puis « View raw » ou « Download » pour la regarder).

## 2. Les voix off gratuites

Une même publicité de 46 mots dite par chaque voix (`runner/scripts/mesurer-voix.mjs`, `runner/mesures/voix-gratuites.json`). Fichiers à écouter : [`videos-pub/voix/`](videos-pub/voix/).

| Voix | Mots compris par Whisper | Remarque |
|---|---|---|
| Gemini 3.8 Flash TTS, femme (Kore) et homme (Charon) | 91 % et 87 % | consignes de ton acceptées |
| Gemini 3.8 Flash-Lite TTS | 89 % | quota séparé |
| Gemini 3.1 Flash TTS (aperçu) | 87 à 89 % | son brut (en-tête WAV ajouté par Kekeli) |
| Gemini 2.5 Flash TTS (aperçu) | 87 à 89 % | idem |
| Cloudflare MeloTTS | — | retirée par Cloudflare le 20 novembre 2026 |
| Cloudflare Deepgram Aura | — | anglais et espagnol seulement |

Le juge automatique (une IA qui écoute) a noté le naturel 6 à 8 sur 10, mais il s'est trompé de genre une fois : **écoutez vous-même**, c'est le seul avis fiable.

## 3. Les échecs expliqués et la voix demandée (captures)

Téléphone et ordinateur, clair et sombre (`scripts/preparer-captures-videos-pub.php`, `scripts/captures-videos-pub.js`).

| | Téléphone, clair | Téléphone, sombre |
|---|---|---|
| Question de la voix avant une publicité | ![](videos-pub/ecrans/1-question-voix-telephone-clair.png) | ![](videos-pub/ecrans/1-question-voix-telephone-sombre.png) |
| Demande impossible (« Appelle mon client ») | ![](videos-pub/ecrans/2-demande-impossible-telephone-clair.png) | ![](videos-pub/ecrans/2-demande-impossible-telephone-sombre.png) |
| Tous les modèles très sollicités | ![](videos-pub/ecrans/3-tres-sollicite-telephone-clair.png) | ![](videos-pub/ecrans/3-tres-sollicite-telephone-sombre.png) |
| Création d'images indisponible | ![](videos-pub/ecrans/4-images-indisponibles-telephone-clair.png) | ![](videos-pub/ecrans/4-images-indisponibles-telephone-sombre.png) |
| Construction échouée, « Réessayer » | ![](videos-pub/ecrans/5-construction-echouee-telephone-clair.png) | ![](videos-pub/ecrans/5-construction-echouee-telephone-sombre.png) |

| | Ordinateur, clair | Ordinateur, sombre |
|---|---|---|
| Question de la voix | ![](videos-pub/ecrans/1-question-voix-ordinateur-clair.png) | ![](videos-pub/ecrans/1-question-voix-ordinateur-sombre.png) |
| Demande impossible | ![](videos-pub/ecrans/2-demande-impossible-ordinateur-clair.png) | ![](videos-pub/ecrans/2-demande-impossible-ordinateur-sombre.png) |
| Très sollicité | ![](videos-pub/ecrans/3-tres-sollicite-ordinateur-clair.png) | ![](videos-pub/ecrans/3-tres-sollicite-ordinateur-sombre.png) |
| Images indisponibles | ![](videos-pub/ecrans/4-images-indisponibles-ordinateur-clair.png) | ![](videos-pub/ecrans/4-images-indisponibles-ordinateur-sombre.png) |
| Construction échouée | ![](videos-pub/ecrans/5-construction-echouee-ordinateur-clair.png) | ![](videos-pub/ecrans/5-construction-echouee-ordinateur-sombre.png) |

## 4. Les demandes impossibles, avant et après

Vrai premier répondant, Gemini 3.1 puis 3.5 Flash-Lite gratuits (`runner/scripts/banc-impossibles.mjs`, `runner/mesures/banc-impossibles-apres.json` ; l'« avant » est dans le dossier temporaire de la séance).

| Demande | Avant | Après |
|---|---|---|
| « Appelle mon client Koffi… » | « Je ne peux pas passer d'appels téléphoniques, car je suis une intelligence artificielle », puis une automatisation proposée | « Je ne peux pas appeler votre client : je n'ai pas accès au téléphone », puis le message prêt à envoyer |
| « Envoie un SMS à toutes mes clientes… » | questions pour une automatisation (ou création lancée tout de suite au premier essai) | la raison, puis le texte du SMS, une liste WhatsApp ou une automatisation |
| « Publie cette annonce sur ma page Facebook à 18 h » | questions pour une automatisation | questions pour une automatisation (elle publiera avec le compte du client) |
| « Mets mon application sur le Play Store » | un long guide, sans dire que Kekeli ne peut pas le faire | la raison (pas d'accès au compte développeur), puis l'application web installable |
| « Encaisse 5 000 FCFA sur le Flooz de ma cliente » | « Je suis Kekeli, votre assistant IA. Je ne peux pas… » | la raison, puis le message de demande de paiement prêt à envoyer |
| « Compose-moi une chanson en MP3 » | **une vidéo lancée** | la raison, les paroles, et la vidéo avec musique proposée |
| « Va au marché acheter du tissu » | **réponse vide** au premier essai | la raison et une aide concrète (liste d'achats, message au vendeur) |
| Publicité vidéo détaillée | vidéo lancée tout de suite | question de la voix (femme, homme, sa propre voix) |

## 5. Vitesse et poids

Lighthouse téléphone : accueil 59, discussion 57, accessibilité 100 (mêmes valeurs qu'avant : ce chantier ne change ni le CSS ni le JavaScript). CSS 353 Ko (41,7 Ko compressé), JavaScript 17,4 Ko (6,2 Ko compressé), fichiers identiques à ceux de `main`.
