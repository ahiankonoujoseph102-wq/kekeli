# Le premier mot en moins d'une seconde et demie (15 novembre 2026) : mesures et arrêt avant fusion

**État : construit et testé, mesure arrêtée et pull request non fusionnée, parce que la règle de coût fixée par le porteur du projet n'est pas respectée.** Proposition en bas de page, à décider par le porteur du projet.

## 1. Modèles Gemini mesurés (offre gratuite de Google, clé `GEMINI_API_KEY`)

Vraie requête du premier répondant (mêmes consignes, mêmes outils), mot par mot, 4 essais par ligne (« Salut » et « Quelle est la capitale du Burkina Faso ? »), 15 novembre 2026, matin. Script : `runner/scripts/mesurer-gemini.mjs` ; résultats : `runner/mesures/premier-mot-gemini.json`.

| Modèle | Réflexion | Réussis | Premier mot (médiane) | Pire cas | Remarque |
|---|---|---|---|---|---|
| Gemini 3.8 Flash | « low » (réglage de Kekeli) | 0 sur 4 | — | — | surchargé (503) ou trop lent |
| Gemini 3.8 Flash | « minimal » | 0 sur 4 | — | — | réglage refusé par ce modèle |
| Gemini 3.7 Flash | « low » | 0 sur 4 | — | — | quota gratuit épuisé ou trop lent |
| Gemini 3.6 Flash | « low » | 4 sur 4 | 2,6 s | 3,9 s | |
| Gemini 3.6 Flash | « minimal » | 3 sur 4 | 4,8 s | 8,5 s | |
| Gemini 3.5 Flash | « low » | 1 sur 4 | 6,3 s | 6,3 s | quota gratuit épuisé |
| Gemini 3.5 Flash-Lite | « low » | 2 sur 4 | 4,0 s | 4,7 s | surchargé (503) |
| Gemini 3.5 Flash-Lite | « minimal » | 4 sur 4 | 1,4 s | 7,0 s | |
| **Gemini 3.1 Flash-Lite** | « low » | 4 sur 4 | 1,3 s | 1,6 s | |
| **Gemini 3.1 Flash-Lite** | **« minimal »** | 4 sur 4 | **0,66 s** | **0,99 s** | le plus rapide et le plus régulier |
| Gemini 3 Flash (version « preview ») | « low » | 4 sur 4 | 0,88 s | 0,93 s | version d'essai, peut changer ou disparaître |
| Gemini Flash-Lite « latest » | par défaut | 4 sur 4 | 0,83 s | 1,4 s | nom qui change de modèle sans prévenir |
| Gemini 2.5 Flash et Flash-Lite | — | 0 sur 4 | — | — | n'existent plus pour cette clé |

**Les 10,2 secondes de la pull request 65** pour Gemini 3.5 Flash-Lite venaient surtout de sa réflexion « low » et de ses surcharges : avec « minimal », la médiane tombe à 1,4 seconde (mais un pire cas de 7 secondes).

**Qualité de Gemini 3.1 Flash-Lite à réflexion minimale** (banc de 20 questions de la pull request 69, `runner/mesures/banc-reponses-gemini-3-1-flash-lite-minimal.json`) : **environ 6,7 sur 10, en dessous des 8 demandés** : réponse en français à une question en anglais, tutoiement, présentation de Kekeli au milieu d'une réponse sur la santé, lien dans une réponse (interdit), prix du ciment à 7 900 FCFA contredisant la source datée trouvée par les autres modèles (environ 4 000 FCFA). **Il n'est donc pas le coureur**, seulement le secours gratuit quand le plafond OpenAI est atteint.

## 2. Coureur choisi : le plus rapide parmi les modèles payants déjà configurés

12 essais par modèle, vraie requête, 15 novembre 2026, matin (`runner/mesures/premier-mot-candidats-matin.json`) :

| Modèle | Premier mot (médiane) | Pire cas | Qualité au banc de 20 questions |
|---|---|---|---|
| GPT-6.1 Terra chez Coarena (premier fournisseur) | 6,2 s | 14,2 s | 8,3 sur 10 |
| GPT-6 Luna chez OpenAI | 0,92 s | 1,45 s | 7,95 sur 10 (une question en anglais répondue en français) |
| GPT-6.1 Terra chez OpenAI | 1,03 s | 3,3 s | même modèle que chez Coarena : 8,3 sur 10 |

Coureur réglé : **GPT-6.1 Terra chez OpenAI** (même qualité quel que soit le gagnant), réglable dans Administration → Réglages.

## 3. Mesure réelle de bout en bout (site local, vrais modèles), arrêtée après le premier essai

Script : `scripts/mesurer-course.sh` (avant et après alternés), analyse `scripts/analyser-course.js` ; résultats bruts : `premier-mot/moment-1/`.

| Question | Premier mot sur le téléphone, avant (sans course) | Après (course à 1 seconde) | Gagnant | Coût enregistré par message, avant | Après |
|---|---|---|---|---|---|
| « Salut » | 15,0 s | 2,4 s | OpenAI | 0,0006 $ | 0,0199 $ |
| Question courte | 30,1 s | 2,8 s | OpenAI | 0,0011 $ | 0,0212 $ |
| Question avec recherche | 16,7 s | 11,6 s | OpenAI | 0,0153 $ | 0,0307 $ |

(Un deuxième essai « avant » a aussi été mesuré : `avant-2.json`.)

**Pourquoi la mesure s'est arrêtée** : la règle du porteur du projet demande de s'arrêter avant de fusionner si le coût de la course dépasse le double du coût actuel par message, ou si le plafond OpenAI serait atteint avant 200 messages par jour. Les deux sont dépassés :

- coût enregistré : environ **0,02 $ par message après, contre 0,0006 à 0,0011 $ avant** (plus de 18 fois plus) ;
- dépense OpenAI réelle : 5 requêtes à GPT-6.1 Terra ont coûté 0,042 $, soit **environ 0,008 à 0,014 $ par message gagné par la course** ; avec Coarena lent presque tout le temps ce jour-là, la course part presque à chaque message, et **le plafond de 1 $ serait atteint vers 100 messages par jour**.

Continuer les 10 essais aurait dépensé environ 0,5 $ de plus sur le plafond réel du jour sans changer la conclusion.

| Coût réel estimé de la course (OpenAI seulement ; Coarena reste gratuit) | 100 messages par jour | 1 000 messages par jour |
|---|---|---|
| GPT-6.1 Terra (mesuré, environ 0,01 $ par message) | environ 1 $ par jour, 30 $ par mois (plafond atteint) | environ 10 $ par jour : impossible avec le plafond de 1 $ |
| GPT-6 Luna (estimation : environ 10 fois moins cher, à mesurer) | environ 0,10 $ par jour, 3 $ par mois | environ 1 $ par jour, 30 $ par mois (plafond atteint) |
| Gemini 3.1 Flash-Lite (offre gratuite) | 0 $ | 0 $, mais quota gratuit d'environ 500 requêtes par jour (à vérifier dans AI Studio) |

Ces coûts sont des **estimations** (la course part presque toujours quand Coarena est lent, comme ce jour-là).
