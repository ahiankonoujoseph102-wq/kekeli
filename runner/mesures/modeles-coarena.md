# Banc d'essai des modèles de Coarena (14 novembre 2026)

Script : `node runner/scripts/banc-coarena.mjs <modèle>…` (variables `TACHES` et `BUDGET_COARENA`) ; réponses complètes dans `runner/mesures/modeles-coarena.json`. Une seule clé (`COARENA_API_KEY`) ; aucune clé n'apparaît dans les résultats. **Dépense : 0,73 $ de crédits Coarena** (plafond : 1 $) et **0 $ chez OpenAI** (plafond : 0,10 $). Les coûts viennent de l'interface de Coarena (« utilisé aujourd'hui », lu avant et après chaque tâche) : ils comptent ce que Coarena aurait facturé, alors que l'usage est gratuit dans la limite de 5 $ par jour et par compte.

## Les 7 tâches, toutes vérifiées par le code
Les mêmes tâches pour chaque modèle, en français, tirées du travail réel de Kekeli :

| Tâche | Ce qui est vérifié automatiquement |
|---|---|
| Raisonnement | Budget d'une boutique de pagnes à Lomé : la vraie consigne du premier répondant ; reste exact de 115 000 FCFA, montants en FCFA, conseil, aucun emoji |
| Site | Site complet du salon « Belle Afi » en un fichier : ouvert dans Chromium au format téléphone, sans erreur JavaScript, sans débordement, menu qui s'ouvre, WhatsApp, 4 prix, `lang="fr"`, rien d'inventé du type « lorem » |
| Modification | Prix changé et section « Contact » ajoutée dans un site existant : tout le reste gardé à l'identique (plats, horaires, script, pied de page) |
| Application | Application de rendez-vous en Node 22 avec SQLite : **lancée pour de vrai** et interrogée (création 201, liste, refus 400 en français, injection SQL sans effet, 404, requêtes préparées) |
| Document | Devis en JSON (structure de la rédaction de Kekeli) : JSON valide, lignes et total justes, acompte de 50 % |
| Outils | Vraie consigne et vrais outils du premier répondant, 3 messages : « creer_site », réponse écrite sans outil, « creer_application » ; arguments en JSON valide |
| Consignes | Exactement 3 lignes numérotées de moins de 20 mots, aucun emoji, un prix en FCFA, rien avant ni après |

## Résultats

| Modèle | Raisonnement | Site | Modification | Application | Document | Outils | Consignes | Total (sur 70) | Durée totale | Coût (crédits Coarena) | Erreurs |
|---|---|---|---|---|---|---|---|---|---|---|---|
| GPT-6.1 Sol | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 110 s | 0,148 $ | 0 |
| GPT-6.1 Terra | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 105 s | 0,060 $ | 0 |
| Kimi K3.1 Pro | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 111 s | 0,243 $ | 0 |
| GPT-6 Luna | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 77 s | 0,006 $ | 0 |
| Gemini 3.8 Flash | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 89 s | 0,088 $ | 0 |
| GLM 5.3 Flash | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 84 s | 0,005 $ | 0 |
| Gemini 4 Argon | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 220 s | 0,131 $ | 0 |
| MiniMax M3.1 Flash | 10 | 10 | 10 | 10 | 10 | 10 | 10 | 70 | 120 s | 0,013 $ | 0 |
| DeepSeek V4.1 Flash | 10 | 9 | 10 | 10 | 8 | 10 | 10 | 67 | 44 s | 0,031 $ | 2 (saturé), réussis au nouvel essai |
| Claude Haiku, Sonnet, Opus 5.5 et Fable 5.2 | — | — | — | — | — | — | — | non testés | — | 0 $ | indisponibles chez Coarena jusqu'au 1er décembre 2026 (son compte chez Anthropic a atteint sa limite) |

**Les vérifications automatiques ne départagent presque personne** : tous les modèles disponibles savent faire ces tâches. La note de qualité ci-dessous vient donc de la relecture des réponses et de l'affichage des sites (captures : `docs/captures/modeles/sites/`), comparés à l'identique.

## Classement (qualité sur 10, avec justification)

| Rang | Modèle | Qualité | Pourquoi |
|---|---|---|---|
| 1 | GPT-6.1 Sol | 10 | Le plus beau site (illustration, mise en page soignée) ; raisonnement nuancé (il distingue une avance de loyer d'une caution, calcule ce que la réserve permet par mois) ; vouvoiement ; prix moyen. |
| 2 | GPT-6.1 Terra | 9,5 | Presque aussi bon que Sol (site élégant avec illustration, exemple chiffré utile), **2,5 fois moins cher**. |
| 3 | GPT-6 Luna | 9 | Juste, clair et court ; site élégant ; **le moins cher** (0,006 $ les 7 tâches) et le plus rapide. Réponses parfois un peu courtes pour un conseil riche. |
| 4 | Kimi K3.1 Pro | 9 | Site très raffiné et bon raisonnement, mais **tutoie le client** (8 fois) et c'est **le plus cher** (0,24 $). Gardé pour le code et les constructions en Pro, jamais pour répondre au client. |
| 5 | Gemini 3.8 Flash | 8,5 | Complet, bien structuré, mais long et 15 fois plus cher que Luna ; site correct mais banal. |
| 6 | GLM 5.3 Flash | 8 | Tout juste, tableaux clairs, **presque gratuit** (0,005 $) ; site très sobre (4 900 caractères contre 15 000 à 27 000 pour les autres). Bon pour les réponses simples et l'extraction. |
| 7 | Gemini 4 Argon | 7,5 | Juste, mais **très lent** (99 s pour une question), ajoute des dollars que personne n'a demandés, site aux couleurs criardes. **Écarté.** |
| 8 | DeepSeek V4.1 Flash | 7 | Rapide et peu cher, mais **saturé 2 fois sur 9 demandes**, laisse « ```html » visible en haut du site, invente des chiffres de dépenses. **Écarté.** |
| 9 | MiniMax M3.1 Flash | 6 | **Caractères chinois au milieu du français** (« les是根据 clients »), page d'accueil presque vide à l'écran. **Écarté.** |
| — | Claude (Haiku, Sonnet, Opus, Fable) | non noté | Indisponible jusqu'au 1er décembre 2026 : **aucune note sans essai**. Permis dans la liste fermée, dans aucune route ; à repasser au banc à son retour (`node runner/scripts/banc-coarena.mjs claude-opus-5-5 …`), puis à ajouter dans Administration → Modèles s'il réussit. |

## Choix du routeur (config/kekeli.php, `model_routes`, modifiable dans Administration → Modèles)

Une seule expérience pour tous (décision du porteur du projet, 14 novembre 2026, comme Lovable et Manus) : les modèles de « Qualité » servent **tous les clients** ; ceux d'« Économie » seulement quand il reste moins de 15 % de crédit (`economy_below_percent`), pour finir le travail au lieu de l'arrêter.


| Tâche | Économie (seulement quand le crédit est presque épuisé) | Qualité (tous les clients, formule Gratuite comprise) |
|---|---|---|
| Réponse simple | GPT-6 Luna, GLM 5.3 Flash | GPT-6.1 Terra, GPT-6 Luna |
| Réponse avec réflexion | GPT-6 Luna, Gemini 3.8 Flash | GPT-6.1 Sol, GPT-6.1 Terra |
| Rédaction | GPT-6 Luna, Gemini 3.8 Flash | GPT-6.1 Terra, GPT-6.1 Sol |
| Traduction | Gemini 3.8 Flash, GPT-6 Luna | GPT-6.1 Terra, Gemini 3.8 Flash |
| Extraction | GPT-6 Luna, GLM 5.3 Flash | GPT-6.1 Terra, GPT-6 Luna |
| Programmation | GLM 5.3 Flash, GPT-6 Luna | GPT-6.1 Sol, Kimi K3.1 Pro |
| Nouveau site | GPT-6.1 Terra, GPT-6 Luna | GPT-6.1 Sol, Kimi K3.1 Pro |
| Modification | GPT-6.1 Terra, GPT-6 Luna | GPT-6.1 Sol, GPT-6.1 Terra |
| Application, agent | GPT-6.1 Terra, Gemini 3.8 Flash | GPT-6.1 Sol, Kimi K3.1 Pro, GPT-6.1 Terra |
| Automatisation, document | GPT-6.1 Terra, GPT-6 Luna | GPT-6.1 Sol, GPT-6.1 Terra |

Après ces modèles viennent toujours, sans changement, OpenAI (clé payante, plafond du jour) puis les offres gratuites actuelles. **Claude Opus pour les tâches les plus difficiles** : prévu par le porteur du projet, à placer en tête des listes « Qualité » d'« Application » et d'« Agent » seulement après son passage au banc.

## Essai réel du basculement entre comptes
Première clé remplacée par une fausse dans un environnement d'essai, avec deux vraies clés derrière : la demande « Quelle est la capitale du Togo ? » est refusée (erreur classée « clé »), la clé est mise hors service, et la même demande repart toute seule avec le compte suivant, qui répond « Lomé » (10,6 s en tout). Les tests du Runner simulent aussi un quota épuisé (5 $ du jour) : la demande passe au compte suivant ; plus aucun compte, OpenAI puis les offres gratuites prennent le relais.

## Contrôle des 11 clés (14 novembre 2026)
Les 11 clés sont valides, chacune sur **un compte distinct** (reconnu par l'interface de Coarena, rien de supposé), 20 modèles accessibles chacune, quota de 5 $ par jour, expiration le **12 février 2027** ; petite demande entre 0,9 et 3,6 s. Détail à jour : Administration → Modèles, bouton « Tester ».
