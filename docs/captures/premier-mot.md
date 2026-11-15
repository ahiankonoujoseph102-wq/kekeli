# Le premier mot en moins d'une seconde et demie (15 novembre 2026)

**Résultat final : OpenAI d'abord, Coarena en tout dernier** (décision du porteur du projet du 15 novembre 2026, Coarena étant trop lent : jusqu'à plusieurs minutes pour une salutation). La course entre fournisseurs, construite puis mesurée (parties 1 à 3 ci-dessous), n'est plus nécessaire : elle reste dans le code, éteinte par défaut (délai 0). Mesures finales : partie 4.

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

## 3. La course avec GPT-6.1 Terra : mesure arrêtée, règle de coût dépassée (historique)

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

**Suite** : le porteur du projet a d'abord choisi GPT-6 Luna comme coureur (moins cher) ; la consigne de langue a été corrigée (partie 4.3) ; puis il a décidé de mettre OpenAI en premier et Coarena en dernier, ce qui rend la course inutile.

## 4. Mesures finales : OpenAI d'abord, Coarena en tout dernier

### 4.1 Le nouvel ordre

| Tâche | Ordre |
|---|---|
| Discussions (premier répondant, salutations, questions) | GPT-6 Luna, GPT-6.1 Terra (OpenAI, tant que le plafond du jour le permet) → Gemini 3.1 Flash-Lite gratuit, réflexion minimale → Gemini 3.5 Flash-Lite → OpenRouter (modèles gratuits) → Cloudflare Workers AI → Coarena |
| Créations (sites, applications, agents, documents, présentations) | GPT-6.1 Terra et GPT-6.1 Sol (OpenAI, selon les règles actuelles) → Gemini 3.5 Flash-Lite puis 3.1 Flash-Lite (meilleurs modèles gratuits mesurés pour construire, `docs/plan.md`, parties 21 et 22) → OpenRouter → Workers AI → Coarena |

Plafond OpenAI du jour : **10 $** (au lieu de 1 $), alertes par e-mail et dans l'administration à 80 % et à 100 % ; une fois atteint, Gemini puis les suivants.

### 4.2 Premier mot sur le téléphone (secondes depuis le toucher sur « Envoyer »)

Site local réglé comme en production, vrais modèles ; « avant » = Coarena en premier (8 essais, le 15 novembre 2026 en fin de matinée, `premier-mot/course-luna/avant-*.json`) ; « après » = nouvel ordre, 10 essais par question à deux moments de la journée (`premier-mot/moment-1.json`, `premier-mot/moment-2.json`). Analyse : `node scripts/analyser-premier-mot.js`.

<!-- premier mot : début -->
<!-- premier mot : fin -->

### 4.3 Qualité de GPT-6 Luna et langue des réponses

Consigne renforcée (« réponds TOUJOURS dans la langue du dernier message du client ») et langue du message reconnue sans modèle (`langueDuMessage` dans `runner/src/repondant.js`) : une question en anglais reçoit la consigne explicite de répondre en anglais. Banc de 20 questions avec GPT-6 Luna : **7,95 sur 10 avant la correction (question en anglais répondue en français), 8,25 sur 10 après** (question en anglais répondue entièrement en anglais). Notes : `runner/mesures/notes-reponses-luna.json` ; réponses : `runner/mesures/banc-reponses-openai-gpt-6-luna-langue.json`.

### 4.4 Coût réel d'un message et d'un site vitrine

| Mesure | Coût réel enregistré |
|---|---|
| « Salut » (moyenne de 10, GPT-6 Luna) | 0,000093 $ (environ 0,06 FCFA) |
| Question courte (moyenne de 10) | 0,000224 $ (environ 0,13 FCFA) |
| Question longue avec tableau (moyenne de 10) | 0,000431 $ (environ 0,26 FCFA) |
| Site vitrine « Le Palmier » (restaurant, demande détaillée) | 62,4 FCFA : modèle 56 FCFA (GPT-6.1 Terra 0,095 $, décision de GPT-6 Luna 0,001 $), ordinateur 6,4 FCFA ; 240 secondes, premier aperçu à 19 secondes |
| Site vitrine « Belle Afi » (salon de coiffure) | 59,8 FCFA : modèle 53,8 FCFA, ordinateur 6 FCFA ; 225 secondes, premier aperçu à 10 secondes |

Coûts par jour et par mois (**estimations** tirées de ces mesures, 600 FCFA pour 1 $, 30 jours ; détails dans `docs/couts.md`) :

| Volume | Par jour | Par mois | Dont OpenAI par jour |
|---|---|---|---|
| 100 messages par jour | environ 0,03 $ (15 FCFA) | environ 0,8 $ | 0,03 $ |
| 1 000 messages par jour | environ 0,25 $ (150 FCFA) | environ 7,5 $ | 0,25 $ |
| 10 sites vitrines par jour | environ 1 $ (610 FCFA) | environ 31 $ | environ 0,9 $ |
| 50 sites vitrines par jour | environ 5,1 $ (3 050 FCFA) | environ 153 $ | environ 4,8 $ |

Avec le plafond OpenAI de 10 $ par jour : environ 100 sites vitrines par jour, ou des dizaines de milliers de messages, avant que Gemini prenne le relais.
