# FreeLLMAPI dans Kekeli

Décision du porteur du projet du 15 novembre 2026 : FreeLLMAPI remplace Coarena comme source de capacité gratuite de plus. Détails du chantier : `docs/plan.md`, partie 26.38. Mesures et captures : `docs/captures/freellmapi.md`.

## Ce que c'est

[FreeLLMAPI](https://github.com/tashfeenahmed/freellmapi) (Tashfeen Ahmed, licence MIT) regroupe les offres gratuites de nombreux fournisseurs d'intelligence artificielle derrière une seule adresse compatible OpenAI. À chaque demande, il prend un fournisseur qui a encore du quota pour le modèle demandé, et passe au suivant en cas de limite ou de panne.

Version figée dans Kekeli : **commit `bce469c3994f460b97cbf0e2b32fc2e47fcd4e88`** (15 novembre 2026), dans `runner/src/freellmapi.js` (`VERSION_FREELLMAPI`). On la met à jour volontairement, après essai, comme OpenCode.

## Architecture

```
Téléphone ─HTTPS─▶ Site Laravel ─HTTPS signé─▶ Runner (Node.js) ─127.0.0.1─▶ FreeLLMAPI ─HTTPS─▶ Groq, LongCat, Z.ai…
                    (énergie, part de chaque     (seul à détenir les clés)   (processus voisin,
                     client, plafond, comptage)                               jamais exposé)
```

- **Processus voisin du Runner**, sur la même machine, qui n'écoute que sur `127.0.0.1` : personne d'autre que le Runner ne peut l'appeler (ni le site, ni un client, ni l'ordinateur virtuel).
- **Clés** : seulement dans les variables d'environnement du Runner. Le Runner donne à FreeLLMAPI les clés de **ses** fournisseurs au démarrage (configuration déclarative), jamais celles d'OpenAI, Gemini, OpenRouter, Cloudflare ou E2B. Sa clé d'accès locale est lue au démarrage et gardée en mémoire. Son chiffrement et son compte d'administration sont tirés du secret du Runner : aucun nouveau secret à créer.
- **Gemini, OpenRouter et Cloudflare restent appelés directement** par Kekeli : un même quota n'est jamais partagé entre deux routeurs.
- **Ordre** : OpenAI, puis les offres gratuites (Gemini, OpenRouter, Workers AI), puis **FreeLLMAPI en dernier**, à la place de Coarena (`ModelRouter::answerRoute` et `route`).
- **Plusieurs clients** : FreeLLMAPI ne connaît qu'un utilisateur, le Runner (son usage prévu). Kekeli gère les clients : énergie (inchangée), **part de chaque client par jour** selon la formule (`freellmapi_requests_per_day`), **plafond global du jour** (`freellmapi_daily_cap`), **une seule demande à la fois par client** et un plafond de demandes simultanées dans le Runner (`PlacesFreellmapi`). Le client est désigné par un identifiant opaque, jamais son numéro, son nom ni son e-mail.
- **Données** : FreeLLMAPI ne garde aucun texte des demandes (seulement fournisseur, modèle, tokens, durée) ; son cache des réponses est coupé (aucune réponse donnée à un autre client) ; l'adresse des clients n'est pas notée.
- **Liste fermée de modèles** (`MODELES_FREELLMAPI` dans le Runner, `FreeLlmApi::MODELS` dans le site) : jamais « auto », jamais un modèle payant (chez Z.ai, seulement les modèles gratuits).
- **Comptage** : l'en-tête `X-Routed-Via` donne le vrai fournisseur et le vrai modèle (par exemple `groq/openai/gpt-oss-120b`), enregistrés par jour dans `provider_usages` ; l'énergie compte le prix estimé de la famille.
- **Sauvegarde des quotas** : la base de FreeLLMAPI (compteurs, pauses des clés) est envoyée toutes les 2 minutes au site et relue au réveil du Runner. Elle est **chiffrée avec une clé que seul le Runner connaît** (`FREELLMAPI_CLE_SAUVEGARDE`) : le site range un fichier qu'il ne peut pas lire. Le nom du fichier porte une empreinte des secrets : si l'un change, FreeLLMAPI repart d'une base neuve au lieu de rester bloqué. Si la restauration échoue deux fois, FreeLLMAPI repart sans sauvegarde.

## Fournisseurs

| Fournisseur | État dans Kekeli | Quota gratuit | Conditions (lues le 15 novembre 2026) |
|---|---|---|---|
| **Groq** | **Actif** (clé `GROQ_API_KEY` présente) | Par modèle : 1 000 demandes et 200 000 tokens par jour, **8 000 tokens par minute** (page officielle et en-têtes de la clé) | Intégration dans une application autorisée ; aucun entraînement sur les demandes |
| **LongCat** (Meituan) | Prêt, **clé à créer** (`LONGCAT_API_KEY`) | Environ 500 000 tokens par jour, jusqu'à 5 millions sur demande (sources secondaires citant la documentation officielle, à confirmer avec la clé) ; modèle LongCat-2.5, contexte d'un million de tokens | Pas de clause trouvée contre l'intégration ; au-delà du quota, refus sans facture (sans recharge) |
| **Z.ai** | Prêt, **clé à créer** (`ZAI_API_KEY`) | GLM-4.7-Flash et GLM-4.5-Flash gratuits (page officielle des prix) ; limite de débit non publiée | Intégration dans une application pour ses utilisateurs autorisée (Additional Terms, 1.a et 1.b) ; l'usage des modèles de Z.ai doit être indiqué (fait dans la politique de confidentialité) ; pas pour des décisions dans la santé ou la finance (1.f) |
| Nara, xKiro, LLM7, Kilo, Requesty, Routeway, SEA-LION, BazaarLink, ElectronHub | **À valider** : branchés seulement quand leurs conditions ont été lues et qu'ils sont nommés dans `FREELLMAPI_PLATEFORMES_VALIDEES` | Selon le catalogue de FreeLLMAPI (par exemple Nara 7 millions de tokens par jour partagés, xKiro 500 000 par jour) | Revendeurs : conditions à lire une par une |
| NVIDIA, Cohere, Zhipu Chine, ModelScope, DreamPrompting, AI Horde | **Écartés** | — | NVIDIA : « without use … in production » ; Cohere : clé d'essai interdite en production ; Zhipu Chine et ModelScope : non commercial ; DreamPrompting : revend les offres gratuites d'autres, dont NVIDIA ; AI Horde : aucun outil, demandes lues par des bénévoles |
| Cerebras | Non branché | Crédit d'essai unique de 5 $, moyen de paiement obligatoire | À décider par le porteur du projet (dépense possible) |
| Mistral | Non branché | « Free mode » réservé à l'évaluation et au prototypage (sources secondaires) | À vérifier dans la console |

## Ce que Groq seul permet (mesuré le 15 novembre 2026)

- **Discussions** : oui. Premier mot de 0,8 à 2,3 secondes, réponse complète en 1,1 à 2,9 secondes à travers le site. Environ 3 400 tokens par question, donc 1 à 2 réponses par minute et par modèle ; Kekeli passe d'une famille à l'autre (Qwen 3.8, gpt-oss-120b, gpt-oss-20b).
- **Documents** : oui pour un petit document (devis d'une page en 3 secondes, structure valide).
- **Salutation ou demande de création** dans une discussion : environ 10 000 tokens avec les outils de création, au-dessus des 8 000 tokens par minute de Groq ; FreeLLMAPI refuse en quelques millisecondes et le fournisseur suivant répond.
- **Construction de sites, d'applications, d'agents et d'automatisations** : non. Une demande de l'agent pèse environ 9 000 tokens ; FreeLLMAPI répond 413 (« trop grande ») en 8 millisecondes. **LongCat et Z.ai** permettront ces travaux dès que leurs clés seront placées (puis banc de construction avant de les garder).

## Variables (Runner seulement, jamais dans le dépôt)

| Nom | Rôle |
|---|---|
| `GROQ_API_KEY` | Groq (déjà présente) |
| `LONGCAT_API_KEY` | LongCat (longcat.chat → plateforme → clé API) |
| `ZAI_API_KEY` | Z.ai (z.ai → API Keys) |
| `FREELLMAPI_CLE_SAUVEGARDE` | 64 caractères hexadécimaux, pour chiffrer la sauvegarde des quotas (facultative ; sans elle, pas de sauvegarde) |
| `KEKELI_SITE_ADRESSE` | l'adresse du site (`https://kekeli.laravel.cloud`), où ranger la sauvegarde |
| `FREELLMAPI_ACTIF` | `non` pour couper FreeLLMAPI sans toucher au code (facultative) |
| `FREELLMAPI_PLATEFORMES_VALIDEES` | revendeurs validés, séparés par des virgules (facultative) |
| `FREELLMAPI_SIMULTANEES` | demandes simultanées au plus (6 par défaut) |

Construction du Runner : `npm ci --omit=dev --audit false && npm run freellmapi:installer` (télécharge l'archive de la version figée et construit le serveur de FreeLLMAPI, environ 15 secondes).

## Administration

Administration → Modèles : interrupteur FreeLLMAPI, état (prêt, version, fournisseurs avec une clé, sauvegarde), chaque fournisseur (actif, en pause, consommation du mois, quota restant), familles de modèles par type de tâche, part de chaque formule, plafond du jour, consommation du jour par vrai modèle avec son coût estimé, alerte à 80 % du plafond. **Jamais une clé**, même masquée.

## Mentions

Licence MIT de FreeLLMAPI : `MENTIONS.md`. Son auteur le présente comme un outil d'expérimentation personnelle (« not production ») et rappelle que les conditions de chaque fournisseur s'appliquent : Kekeli ne branche que les fournisseurs dont les conditions permettent l'usage prévu, et garde OpenAI et Gemini devant lui.
