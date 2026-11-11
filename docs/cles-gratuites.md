# Clés gratuites des fournisseurs d'IA

Mise à jour : 10 novembre 2026. **Une seule clé par fournisseur**, au nom du porteur du projet : jamais de deuxième compte, jamais la clé d'une autre personne. **Aucune carte bancaire** n'est demandée pour les clés ci-dessous. Une clé ne s'écrit **jamais dans un message ni dans le dépôt** (qui est public) : seulement dans les réglages protégés de l'environnement (environnement de Claude pendant la construction, puis Laravel Cloud pour le Runner).

Si une clé manque, Kekeli passe simplement au fournisseur suivant de la liste : rien ne casse.

## Déjà placées (rien à faire)

| Nom de la variable | Fournisseur | Remarque |
|---|---|---|
| `GEMINI_API_KEY` | Google AI Studio | Fournisseur principal |
| `OPENROUTER_API_KEY` | OpenRouter | Modèles gratuits seulement (« :free ») et seulement ceux permis pour des clients (Gemma 4, servi par Google) |
| `CLOUDFLARE_API_TOKEN` et `CLOUDFLARE_ACCOUNT_ID` | Cloudflare | Sert maintenant aux images **et** aux modèles de Workers AI. Le compte Cloudflare doit **rester sur l'offre gratuite (Workers Free)** : ne jamais passer à « Workers Paid » sans accord, sinon les neurones au-delà de 10 000 par jour seraient facturés. |
| `GROQ_API_KEY` | Groq | Gardée, mais inutilisable aujourd'hui par l'agent (8 000 tokens par minute) |

## À créer : aucune

Toutes les clés utiles sont déjà placées. **Mistral n'a plus d'offre gratuite pour l'API** (vérifié par le porteur du projet le 11 novembre 2026 : le plan « Gratuit » ne permet pas d'utiliser les clés API, la formule « Experiment » n'existe plus) : ne créez pas de clé Mistral. Son code reste dans Kekeli, inactif, comme celui de Groq.

## Écartés : rien à créer

Voir le tableau complet dans `docs/plan.md`, parties 21 et 22 : Mistral (plus d'offre gratuite pour l'API), Cerebras (carte bancaire et crédit unique de 30 jours), NVIDIA (essai seulement, interdit en production ; aussi la version gratuite de Nemotron chez OpenRouter), Cohere (usage commercial interdit ; aussi la version gratuite de North chez OpenRouter), Hugging Face (plus de crédit gratuit), GitHub Models, SambaNova, Z.ai et Zhipu, Ollama Cloud, OVH, AI Horde, OpenCode Zen, les revendeurs et les fournisseurs chinois.
