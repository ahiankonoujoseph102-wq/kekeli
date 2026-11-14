# Coarena : conditions, limites et place dans Kekeli

Lu le 14 novembre 2026 sur les pages publiques de Coarena (conditions d'utilisation du 25 octobre 2026, politique de confidentialité du 9 novembre 2026, documentation de l'API) et vérifié avec l'interface de la clé (`GET /api/v1/limits`, `GET /api/v1/keys`). Coarena est un service de Coasty Systems, Inc. (États-Unis).

## Ce qu'est Coarena
- Une interface **compatible OpenAI** (`https://coarena.ai/api/v1`, réglage `COARENA_BASE_URL`) et compatible Anthropic (`https://coarena.ai/api`), qui donne accès à environ 20 modèles de plusieurs fabricants : Anthropic (Claude Haiku, Sonnet, Opus, Fable), OpenAI (GPT-6 Luna, GPT-6.1 Terra, GPT-6.1 Sol, GPT-6 Astra), Google (Gemini 3.8 Flash, Gemini 4 Argon, Gemini 4 Carbon), DeepSeek, Kimi, GLM, Grok, MiniMax, Xiaomi, Tencent.
- Liste des modèles : `GET /api/v1/models` (sans clé). Tous acceptent les outils, 128 000 tokens de contexte, 16 384 tokens de réponse au plus.

## Variables de l'environnement (noms seulement)
- `COARENA_API_KEY` : **la seule clé utilisée par Kekeli**.
- `COARENA_BASE_URL` : l'adresse de l'interface.
- `COARENA_API_KEY_2` à `COARENA_API_KEY_11` : clés d'autres comptes. **Jamais lues par Kekeli** (voir « Un seul compte »).

## Limites (offre gratuite)
| Limite | Valeur |
|---|---|
| Usage gratuit | **5 $ par jour et par compte**, payés par Coarena |
| Demandes | 30 par minute (par clé, par compte et par modèle) |
| En même temps | 2 demandes |
| Remise à zéro | chaque jour à minuit UTC (minuit à Lomé) |
| Limite atteinte | erreur 429 `insufficient_quota` (ou 429 de débit) |
| Expiration de la clé | **12 février 2027** (clé créée le 14 novembre 2026, valable 90 jours) |

**Pour avoir plus, officiellement** : après un premier appel, une seule demande à Coarena (`POST /api/v1/limits/requests`) permet de passer à 10 $, puis 25 $, puis 50 $ par jour (et plus), ou à 60, 120 puis 300 demandes par minute (600 au plus). C'est le chemin prévu par Coarena.

## Un seul compte
Les conditions de Coarena interdisent de créer des comptes « sous de faux prétextes » ou par des moyens automatiques, et l'offre gratuite est donnée **par personne**. Utiliser plusieurs comptes pour additionner les 5 $ par jour reviendrait à contourner cette limite, et la règle de Kekeli l'interdit aussi (« Un seul compte par fournisseur », CLAUDE.md). Kekeli n'utilise donc **que `COARENA_API_KEY`**. Quand ses 5 $ du jour sont épuisés, Kekeli passe à OpenAI, puis aux offres gratuites actuelles, sans rien montrer au client. Pour plus de capacité : la demande officielle ci-dessus.

## Usage commercial
Les conditions n'interdisent pas d'utiliser les réponses dans un service commercial. Elles précisent que Coasty **ne donne aucun droit** sur les réponses des modèles : ces droits suivent les conditions de chaque fabricant (Anthropic, OpenAI, Google…), que Kekeli respecte déjà (aucun contenu interdit, pas de service concurrent d'entraînement de modèles).

## Données
- Les questions envoyées par l'API et les réponses **restent chez Coarena** et peuvent entrer dans des **jeux de données vendus à des laboratoires d'IA** ; retirer son accord ne les efface pas (sauf obligation légale). Coarena le dit avant la création de chaque clé.
- **Accord du porteur du projet (14 novembre 2026)** : il accepte que les échanges passent par Coarena et puissent servir à entraîner des modèles. La politique de confidentialité de Kekeli le dit clairement et nomme Coarena.
- Jamais de clé, de mot de passe ni de donnée sensible envoyés à un modèle (déjà masqués par `SecretScanner`).

## Sécurité dans Kekeli
- La clé Coarena reste **dans le Runner seulement**, comme la clé OpenAI : jamais dans les secrets d'E2B ni dans un ordinateur virtuel. L'agent qui construit y passe par le relais de l'ordinateur, que le Runner suit (comme pour OpenAI).
- Seuls les modèles retenus par le banc d'essai peuvent être choisis (liste fermée dans le Runner et dans le site).
