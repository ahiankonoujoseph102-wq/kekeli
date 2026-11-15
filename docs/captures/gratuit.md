# Le meilleur du gratuit, tâche par tâche (15 novembre 2026)

Mesures réelles avec les clés de Kekeli, le 15 novembre 2026 au soir (heure de Lomé), sans aucune dépense : le crédit
d'OpenAI est épuisé et OpenAI reste en dernier partout. Données de test inventées. Détails : `docs/plan.md`, partie 26.40.

Clés présentes ce jour-là (seulement leur présence, jamais leur valeur) : Gemini, OpenRouter, Cloudflare, Groq, Z.ai,
Pixabay, E2B, OpenAI ; LongCat absente.

## 1. Les vraies limites gratuites

| Fournisseur et modèle | Par minute | Par jour | Source |
|---|---|---|---|
| Google AI Studio, Gemini 3.1 Flash-Lite | 15 demandes | **500 demandes** | refus de Google lu le 15 novembre 2026 (`PerMinute…FreeTier=15`, `PerDay…FreeTier=500`) |
| Google AI Studio, Gemini 3.5 Flash-Lite | 15 demandes | GEMINI35 | refus de Google lu le 15 novembre 2026 |
| Google AI Studio, Gemini 3.5 Flash (pas Lite) | — | **20 demandes** | refus de Google (`PerDay…FreeTier=20`) : le soupçon des « 20 par jour » concerne ce modèle, pas les Flash-Lite |
| Google AI Studio, Gemma 4 31B | 30 demandes | non atteint | refus de Google (`PerMinute…FreeTier=30`) |
| Google AI Studio, images (Gemini 3.1 Flash Image et Flash-Lite Image) | 0 | **0** | refus immédiat, quota gratuit nul |
| OpenRouter, modèles « :free » | 20 demandes | **50 demandes** (tous les modèles gratuits ensemble, moins de 10 crédits achetés) | clé lue (`free_model_daily_requests.limit = 50`) et page officielle « Limits » |
| OpenRouter, Gemma 4 31B gratuit | — | — | **souvent refusé** : « temporarily rate-limited upstream » (réserve partagée chez Google AI Studio) |
| Groq (par FreeLLMAPI), gpt-oss-120b, gpt-oss-20b, Qwen 3.8 27B | 8 000 tokens (30 demandes) | 1 000 demandes et **200 000 tokens** par modèle | en-têtes `x-ratelimit-*` lus avec notre clé et page officielle « Rate Limits » |
| Z.ai (par FreeLLMAPI), GLM-4.5-Flash et GLM-4.7-Flash | **une demande à la fois** (une deuxième en même temps est refusée) | aucune limite publiée | page officielle « Pricing » (modèles « Free ») et essais ; GLM-4.7-Flash « temporarily overloaded » ce soir |
| Cloudflare Workers AI | — | **10 000 neurones** pour tout le compte (images et texte), remis à zéro à minuit UTC | page officielle « Workers AI pricing » (mise à jour le 12 novembre 2026) |

Ce que pèse chaque tâche (mesuré) :

| Tâche | Demandes au modèle | Tokens | Durée |
|---|---|---|---|
| Discussion | 1 (plus 1 par recherche) | environ 6 000 à 7 000 par réponse | 2 à 4 secondes (Gemini, Groq), 21 secondes (Z.ai) |
| Document ou présentation | 1 | 4 000 à 8 000 | 2 à 4 secondes (Gemini), 35 à 99 secondes (Z.ai) |
| Site vitrine | 13 à 19 | — | 1,5 à 6 minutes |
| Application | 58 à 70 | — | 15 à 19 minutes (pauses de la limite par minute comprises) |
| Agent IA | 13 à 20 | — | 3,5 à 4,6 minutes |
| Automatisation | 22 | — | 4 minutes |
| Image ou affiche (fond FLUX.1 schnell) | 1 image | 57,6 neurones (4 tuiles × 4,8 + 4 étapes × 9,6) | quelques secondes |

**Conséquences** :
- Groq ne sert qu'une trentaine de réponses de Kekeli par jour et par modèle (200 000 tokens ÷ 6 500) : utile, mais petit.
- Les 10 000 neurones de Cloudflare ne font qu'environ 170 images ; le 15 novembre au soir, des essais d'images
  précédents les avaient **déjà entièrement vidés** (« used up your daily free allocation of 10,000 neurons ») :
  ni image ni texte par Cloudflare jusqu'à minuit UTC. D'où la réserve construite (partie 5).
- La limite par minute de Gemini (15) ralentit les constructions : une application a attendu 4 fois 2 minutes.

### Combien de clients par jour (estimation)

Capacité gratuite du jour, toutes clés de Kekeli ensemble (un seul compte par fournisseur) :

| Tâche | Capacité par jour (estimation) | Calcul |
|---|---|---|
| Discussions | environ 600 à 650 | Gemini 3.1 Flash-Lite 500, Groq environ 30 par modèle (90 pour trois), OpenRouter au plus 50, Workers AI environ 25 (après la réserve des images), Z.ai lent en dernier |
| Documents | environ 100 à 250 | Gemini 3.5 Flash-Lite (part de ses 500 demandes laissée par les constructions), puis Z.ai (une demande à la fois, environ une par minute) |
| Sites vitrines | environ 25 | 500 demandes de Gemini 3.5 Flash-Lite ÷ 19 |
| Applications | environ 7 | 500 ÷ 70 |
| Images et affiches | environ 150 | réglage `images_max_per_day` (10 000 neurones moins la marge) |

Un client gratuit type (8 discussions, 1 document, une création de temps en temps) : **environ 60 à 80 clients par
jour** avant que les offres gratuites soient épuisées, limités surtout par les 500 demandes de chaque Gemini Flash-Lite ;
au-delà, la demande attend (« Kekeli est très sollicité ») ou passe à OpenAI s'il a du crédit. Ce sont des estimations :
à mesurer avec les premiers vrais clients.

## 2. Mesure de chaque modèle gratuit, tâche par tâche

### Réponses (banc de 30 questions de la pull request 71, notées contre les réponses de référence)

| Modèle | Note moyenne | Plus basse | Réussite | Durée moyenne |
|---|---|---|---|---|
| Gemini 3.1 Flash-Lite (réflexion minimale) | **8,28** | 7,5 | 30 sur 30 (5 reprises après une limite par minute) | 2,4 s |
| Gemini 3.5 Flash-Lite | 8,15 | 6,5 | 30 sur 30 | 4,2 s |
| Groq gpt-oss-120b | 7,69 | 6 | 21 sur 30 (limite de tokens du jour) | 1,8 s |
| Z.ai GLM-4.5-Flash | 7,18 | 6 | 30 sur 30 | 21,1 s |
| OpenRouter Gemma 4 31B gratuit | — | — | refusé (réserve partagée saturée) | — |
| Workers AI GLM-4.7-Flash | WORKERSAI | | | |

Défauts relevés : Gemini 3.5 Flash-Lite a commencé une réponse par « **Prompt and clear answer:** » en anglais ;
Groq invente des chiffres juridiques (« capital minimum 1 000 FCFA », « amendes jusqu'à 30 000 FCFA par image ») ;
Z.ai donne des ordres de grandeur douteux (restaurant de 5 à 15 millions, automatisation à 5 000 FCFA) et répond
lentement. Notes question par question : `runner/mesures/notes-reponses-gratuit.json` ; réponses complètes :
`runner/mesures/banc-reponses-g30-*.json`.

### Décisions (banc de 30 décisions)

| Modèle | Bonnes décisions |
|---|---|
| Gemini 3.1 Flash-Lite | **30 sur 30** (12 dernières reprises après une limite par minute) |
| Gemini 3.5 Flash-Lite | **30 sur 30** (3 reprises après une limite par minute) |

### Constructions (même demande pour chaque modèle, dans un ordinateur E2B, comme pour un client)

| Tâche | Gemini 3.5 Flash-Lite | Gemini 3.1 Flash-Lite | Z.ai GLM-4.5-Flash | OpenRouter Gemma 4 |
|---|---|---|---|---|
| Site vitrine « Chez Ama » | **7 sur 10** · 1,5 min · 19 demandes : tous les plats et prix justes ; invente des moyens de paiement | 4 sur 10 · 6,2 min · 13 demandes : prix illisibles (blanc sur blanc), titres invisibles, pas de plan | **échec** : refusé à chaque demande (9 attentes, 0 demande servie) | OPENROUTER |
| Application « Belle Afi » (Laravel) | 5 sur 10 · 19 min · 70 demandes : 16 tests réussis, aucune faille, mais aucune prestation affichée et l'espace connecté plante | **3 sur 10** · 15,5 min · 58 demandes : 19 tests réussis, mais **accueil et administration du point de départ** présentés comme finis | — | — |
| Agent « Ama Pagnes » (LangGraph) | **8 sur 10** · 3,5 min : rôle, 4 documents, LISEZ-MOI | 7,5 sur 10 · 4,6 min : rôle et 4 documents | — | — |
| Automatisation « Résumé des ventes » | **7,5 sur 10** · 4,1 min : lecture du CSV, calculs, envoi Telegram, tests | 6 sur 10 · 4,1 min : fichier Telegram absent | — | — |

Captures sur téléphone simulé : `docs/captures/gratuit/constructions/`.

### Documents (5 demandes réelles : devis, plan d'affaires, rapport, CV, présentation)

| Modèle | Note | Réussite | Durée |
|---|---|---|---|
| Gemini 3.5 Flash-Lite | **8 sur 10** : calculs justes, rapport et présentation riches (tableau des tarifs, chiffres clés) | 5 sur 5 | 2 à 4 s |
| Z.ai GLM-4.5-Flash | 7,3 sur 10 : calculs justes, analyses plus maigres, diapositive « Contactez-nous » sans contact, ligne « Livraison » marquée comme total | 5 sur 5 après correction du délai (1 sur 5 avant) | 35 à 99 s |

Pages rendues : `docs/captures/gratuit/documents/`.

### Affiche et image (fond créé, texte posé par le code)

IMAGES

## 3. Le meilleur ordre pour chaque tâche (OpenAI toujours en dernier)

Réglage `free_order` de `config/kekeli.php`, appliqué par `App\Agent\ModelRouter` :

| Groupe | Ordre des gratuits | Pourquoi |
|---|---|---|
| Discussions et questions | Gemini 3.1 Flash-Lite → Groq gpt-oss-120b → Gemini 3.5 Flash-Lite → OpenRouter → Workers AI → Z.ai → autres familles de FreeLLMAPI → OpenAI | meilleure note et le plus rapide d'abord ; Groq rapide mais petit quota ; Gemini 3.5 gardé pour les créations ; Z.ai trop lent pour passer avant |
| Créations (site, application, agent, automatisation) | Gemini 3.5 Flash-Lite → Gemini 3.1 Flash-Lite → les autres → OpenAI | 3.5 nettement meilleur pour construire ; les autres gratuits n'ont pas su construire |
| Documents et présentations | Gemini 3.5 Flash-Lite → Z.ai GLM-4.5-Flash → Gemini 3.1 Flash-Lite → les autres → OpenAI | meilleur document d'abord ; Z.ai juste mais lent ; 3.1 non mesuré sur les documents ce soir (quota du jour utilisé pour la mesure de sa limite) |

**Répartition de la charge** : les discussions commencent sur Gemini 3.1 Flash-Lite, les créations et les documents sur
Gemini 3.5 Flash-Lite : deux quotas de 500 demandes séparés au lieu d'un seul vidé par tout le monde. Les familles de
FreeLLMAPI suivent le même principe (`freellmapi_routes` : gpt-oss-120b d'abord pour les discussions, GLM-4.5-Flash
d'abord pour les documents).

## 4. Construction trop lourde : arrêt propre

- Une construction neuve où le modèle n'a écrit **aucun fichier** du projet s'arrête avec le message « cette création est
  trop lourde pour Kekeli en ce moment : rien d'inachevé n'a été livré… » (`travailVide`, raison `trop_lourd`).
- Une **application** neuve dont l'**accueil du point de départ** n'a pas été remplacé (le défaut de Gemini 3.1
  Flash-Lite ci-dessus) reçoit une seule relance pour le remplacer ; sinon, même arrêt clair (`accueilDuDepart`,
  `TEXTE_ACCUEIL`). Jamais l'écran de départ présenté comme fini, jamais de boutons de suite sur un échec.

## 5. Réserve de Cloudflare pour les images des clients

- Le texte de Workers AI n'a droit qu'à ce qui reste des 10 000 neurones après **toutes les images permises du jour**
  (`images_max_per_day` × 58) et une marge de 500 : aujourd'hui 800 neurones (environ 25 réponses). Au-delà, Workers AI
  sort des listes de texte (`App\Agent\WorkersAiReserve`).
- Les scripts d'essai d'images comptent leurs appels à Cloudflare : **20 par jour au plus** (`ESSAIS_CLOUDFLARE_MAX`,
  `runner/scripts/reserve-essais.mjs`).

## 6. Corrections trouvées pendant la mesure

- FreeLLMAPI coupait après 60 secondes : Z.ai, plus lent, ne rédigeait qu'un document sur cinq et sa clé était mise en
  pause. Délai porté à 4 minutes pour Z.ai (`PROVIDER_TIMEOUT_ZHIPU`).
- Bancs : FreeLLMAPI démarré pendant les bancs, pause réglable entre deux questions (`PAUSE_S`), banc de construction
  pour les quatre types (`TACHE`).

## 7. Poids et Lighthouse

LIGHTHOUSE
