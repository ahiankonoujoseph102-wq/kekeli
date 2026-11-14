# Coarena : conditions, limites et place dans Kekeli

Lu le 14 novembre 2026 sur les pages publiques de Coarena (conditions d'utilisation du 25 octobre 2026, politique de confidentialité du 9 novembre 2026, documentation de l'API) et vérifié avec l'interface de la clé (`GET /api/v1/limits`, `GET /api/v1/keys`). Coarena est un service de Coasty Systems, Inc. (États-Unis).

## Ce qu'est Coarena
- Une interface **compatible OpenAI** (`https://coarena.ai/api/v1`, réglage `COARENA_BASE_URL`) et compatible Anthropic (`https://coarena.ai/api`), qui donne accès à environ 20 modèles de plusieurs fabricants : Anthropic (Claude Haiku, Sonnet, Opus, Fable), OpenAI (GPT-6 Luna, GPT-6.1 Terra, GPT-6.1 Sol, GPT-6 Astra), Google (Gemini 3.8 Flash, Gemini 4 Argon, Gemini 4 Carbon), DeepSeek, Kimi, GLM, Grok, MiniMax, Xiaomi, Tencent.
- Liste des modèles : `GET /api/v1/models` (sans clé). Tous acceptent les outils, 128 000 tokens de contexte, 16 384 tokens de réponse au plus.

## Variables de l'environnement (noms seulement)
- `COARENA_API_KEY` à `COARENA_API_KEY_11` : 11 clés, **chacune sur un compte distinct** (vérifié le 14 novembre 2026 par l'interface de Coarena : les clés d'un même compte se voient entre elles avec `GET /keys`, rien n'est supposé). On en ajoute ou on en retire dans l'environnement, sans toucher au code.
- `COARENA_BASE_URL` : l'adresse de l'interface (seulement `https://coarena.ai/api/v1` est accepté).

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

## Plusieurs comptes
**Ce que disent les textes officiels** (lus le 14 novembre 2026) :
- Conditions d'utilisation (25 octobre 2026), partie « Acceptable use » : interdit de « creating accounts by automated means or under false pretences » (créer des comptes par des moyens automatiques ou sous de faux prétextes).
- Conditions de l'API (7 points, version du 4 novembre 2026, encore en brouillon) : point 6, « Limits can change at any time, access can be revoked » (les limites peuvent changer, l'accès être retiré).
- Documentation : « 5 $ d'usage gratuit par jour », par compte.
- **Aucun texte n'interdit à une personne d'avoir plusieurs comptes ni de les utiliser chacun avec sa clé**, et aucune autorisation particulière n'est demandée.

**Décision du porteur du projet** (14 novembre 2026), propriétaire des 11 comptes, créés à la main : le passage automatique d'un compte à l'autre est **activé**. Quand le quota d'un compte est épuisé (erreur `insufficient_quota`), qu'une clé est refusée ou qu'elle est trop sollicitée, la même demande repart avec le compte suivant ; l'interrupteur « Passer à un autre compte Coarena… » d'Administration → Modèles le coupe. Si Coarena retire l'accès, Kekeli continue avec OpenAI puis les offres gratuites.

**Message de confirmation conseillé** (à envoyer par le porteur du projet à founders@coasty.ai ; Kekeli ne l'envoie pas) :

> Bonjour, je développe Kekeli, un assistant IA créé à Lomé (Togo) par Lomé IA School. J'utilise votre Model API avec plusieurs comptes Coarena que j'ai créés moi-même, chacun avec sa propre clé, et mon application passe automatiquement au compte suivant quand le quota gratuit d'un compte est atteint. Pouvez-vous me confirmer que cet usage est autorisé, ou m'indiquer la bonne façon d'obtenir plus de capacité (par exemple une augmentation sur un seul compte) ? Merci. Joseph Ahiankonou

**Autre chemin officiel** : une demande d'augmentation sur un compte (10 $, 25 $, 50 $ par jour…), bouton « Demander plus de capacité à Coarena » dans Administration → Modèles.

## Usage commercial
Les conditions n'interdisent pas d'utiliser les réponses dans un service commercial. Elles précisent que Coasty **ne donne aucun droit** sur les réponses des modèles : ces droits suivent les conditions de chaque fabricant (Anthropic, OpenAI, Google…), que Kekeli respecte déjà (aucun contenu interdit, pas de service concurrent d'entraînement de modèles).

## Données
- Les questions envoyées par l'API et les réponses **restent chez Coarena** et peuvent entrer dans des **jeux de données vendus à des laboratoires d'IA** ; retirer son accord ne les efface pas (sauf obligation légale). Coarena le dit avant la création de chaque clé.
- **Accord du porteur du projet (14 novembre 2026)** : il accepte que les échanges passent par Coarena et puissent servir à entraîner des modèles. La politique de confidentialité de Kekeli le dit clairement et nomme Coarena.
- Jamais de clé, de mot de passe ni de donnée sensible envoyés à un modèle (déjà masqués par `SecretScanner`).

## Sécurité dans Kekeli
- Les clés Coarena restent **dans le Runner seulement**, comme la clé OpenAI : jamais dans les secrets d'E2B ni dans un ordinateur virtuel. L'agent qui construit y passe par le relais de l'ordinateur, que le Runner suit (comme pour OpenAI).
- Seuls les modèles retenus par le banc d'essai peuvent être choisis (liste fermée dans le Runner, `MODELES_COARENA`, et dans le site, `ModelRouter::COARENA_MODELS`).
- Erreurs classées sans jamais montrer la clé : « cle » (refusée, expirée), « quota » (5 $ du jour), « debit » (30 par minute, 2 en même temps : 20 secondes de pause), « sature » (un autre modèle), « modele » (indisponible longtemps), « fournisseur » (panne : un seul nouvel essai).
