# Clés gratuites des fournisseurs d'IA

Mise à jour : 10 novembre 2026. **Une seule clé par fournisseur**, au nom du porteur du projet : jamais de deuxième compte, jamais la clé d'une autre personne. **Aucune carte bancaire** n'est demandée pour les clés ci-dessous. Une clé ne s'écrit **jamais dans un message ni dans le dépôt** (qui est public) : seulement dans les réglages protégés de l'environnement (environnement de Claude pendant la construction, puis Laravel Cloud pour le Runner).

Si une clé manque, Kekeli passe simplement au fournisseur suivant de la liste : rien ne casse.

## Déjà placées (rien à faire)

| Nom de la variable | Fournisseur | Remarque |
|---|---|---|
| `GEMINI_API_KEY` | Google AI Studio | Fournisseur principal |
| `OPENROUTER_API_KEY` | OpenRouter | Modèles gratuits seulement (« :free ») |
| `CLOUDFLARE_API_TOKEN` et `CLOUDFLARE_ACCOUNT_ID` | Cloudflare | Sert maintenant aux images **et** aux modèles de Workers AI. Le compte Cloudflare doit **rester sur l'offre gratuite (Workers Free)** : ne jamais passer à « Workers Paid » sans accord, sinon les neurones au-delà de 10 000 par jour seraient facturés. |
| `GROQ_API_KEY` | Groq | Gardée, mais inutilisable aujourd'hui par l'agent (8 000 tokens par minute) |

## À créer : une seule clé

### `MISTRAL_API_KEY` (Mistral AI, offre gratuite « Experiment »)

Site : **console.mistral.ai**

Gestes sur le téléphone :
1. Ouvrez Chrome et allez sur **console.mistral.ai**.
2. Touchez **Sign up** (s'inscrire) et créez le compte avec l'adresse de Lomé IA School ou votre compte Google.
3. Quand Mistral propose une formule, choisissez **Experiment** (gratuite). Mistral envoie un code par SMS : tapez-le pour vérifier votre numéro.
4. **Important, avant tout essai** : touchez votre nom en haut, puis **Admin** (ou **Organisation**), puis **Privacy** (confidentialité), et **désactivez l'utilisation de vos données pour l'entraînement** (option du type « Allow training on my data » : mettez-la sur non). Faites une capture de cet écran si vous voulez que je vérifie (cachez toute clé).
5. Dans le menu, touchez **API Keys**, puis **Create new key**. Donnez-lui le nom `kekeli`. Laissez la date d'expiration vide ou la plus longue.
6. Touchez **Copier**. La clé ne s'affiche qu'une fois.
7. Placez-la dans les réglages de l'environnement, sous le nom exact **`MISTRAL_API_KEY`**, comme les autres clés (jamais dans un message).
8. Écrivez-moi « clé Mistral placée » : je ferai l'essai réel.

## Écartés : rien à créer

Voir le tableau complet dans `docs/plan.md`, partie 21 : Cerebras (carte bancaire et crédit unique de 30 jours), NVIDIA (essai seulement, interdit en production), Cohere (clé d'essai interdite en usage commercial), Hugging Face (plus de crédit gratuit), GitHub Models, SambaNova, Z.ai et Zhipu, Ollama Cloud, OVH, AI Horde, OpenCode Zen, les revendeurs et les fournisseurs chinois.
