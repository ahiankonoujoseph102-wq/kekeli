# Plan de Kekeli

- **1er novembre 2026** : plan validé, avec trois corrections du porteur du projet (paiements, publication, coûts).
- **1er novembre 2026, mise à jour** : décision du porteur du projet de ne pas dépendre d'un seul modèle payant. Kekeli repose sur un **moteur multi-modèles** ; pendant toute la construction, le cerveau est **Gemini, avec l'offre gratuite de Google AI Studio**. Le choix du moteur (OpenCode, recommandé ci-dessous) **attend la validation du porteur du projet**.

Les prix et les limites cités ont été relevés dans les documentations officielles à ces dates ; ils peuvent changer.

## 1. Architecture

```
 Téléphone ou ordinateur de l'utilisateur
            │  (site web Kekeli)
            ▼
 ┌──────────────────────────────────────┐
 │ 1. SITE KEKELI — Laravel             │  Laravel Cloud (plus tard)
 │ comptes, conversations, quotas,      │  + PostgreSQL serverless
 │ paiements, administration,           │
 │ RÉGLAGE DES MODÈLES                  │
 └──────────────┬───────────────────────┘
                │ messages signés (HTTPS)
                ▼
 ┌──────────────────────────────────────┐
 │ 2. RUNNER — petit service Node.js    │
 │ - crée et efface les ordinateurs     │
 │ - pilote l'agent OpenCode à distance │
 │ - compte les tokens par utilisateur  │
 │ - seul endroit qui détient les clés  │
 └──────────────┬───────────────────────┘
                │
                ▼
 ┌──────────────────────────────────────┐
 │ 3. ORDINATEUR VIRTUEL (un par projet)│  E2B, modèle « opencode »
 │ agent OpenCode + fichiers du site    │
 │ + serveur d'aperçu → lien en direct  │
 └──────────────┬───────────────────────┘
                │ appels au modèle choisi
                ▼
   Gemini (construction) · Claude · OpenAI · autres (au lancement)
```

1. L'utilisateur écrit sa demande. Laravel l'enregistre, vérifie le quota et l'envoie au Runner avec une signature.
2. Le Runner démarre ou réveille l'ordinateur virtuel, où tourne l'agent **OpenCode** en mode serveur, protégé par un mot de passe.
3. Le Runner envoie la demande à OpenCode en précisant le modèle à utiliser. Chaque étape de l'agent (fichier écrit, commande lancée, tokens consommés) remonte au Runner, puis à Laravel, qui l'affiche en direct sur le téléphone.
4. L'aperçu du site s'affiche grâce au lien fourni par l'ordinateur virtuel.

**Changer de modèle = changer un réglage.** Le modèle est choisi par Kekeli à chaque demande, sous la forme « fournisseur/modèle » (par exemple `google/…`, `anthropic/…`, `openai/…`). Passer de Gemini à Claude ou à OpenAI demande seulement de changer le réglage et d'ajouter la clé du nouveau fournisseur, sans reconstruire Kekeli.

**Les clés ne vont jamais dans l'ordinateur virtuel.** Piste retenue, à confirmer à l'étape 2 : E2B sait ajouter lui-même la clé aux requêtes qui sortent vers le fournisseur du modèle (« règles par hôte », fonction en bêta publique chez E2B). L'agent n'a donc jamais la clé. Solution de secours : une passerelle dans le Runner, une fois celui-ci hébergé.

## 2. Moteur de l'agent : OpenCode (recommandé, en attente de validation)

Comparaison faite le 1er novembre 2026 dans les documentations officielles.

| | **OpenCode** | **OpenHands (Software Agent SDK)** | **Gemini CLI** | Claude Agent SDK (ancien choix) |
|---|---|---|---|---|
| Qualité comme agent de programmation | Agent complet de type Claude Code (lire, écrire, commandes, tests, Internet), très utilisé (environ 224 000 étoiles sur GitHub) | Très bon, conçu pour le travail autonome, bons résultats publiés sur SWE-bench | Bon avec Gemini | Excellent avec Claude |
| Modèles acceptés | Plus de 75 fournisseurs : Google Gemini, Anthropic, OpenAI, OpenRouter, DeepSeek, Mistral, modèles locaux, toute API compatible OpenAI | Tous les grands fournisseurs (plus de 100 annoncés) | **Gemini uniquement** | **Claude uniquement** |
| Ordinateurs virtuels E2B | **Modèle officiel « opencode » prêt à l'emploi chez E2B**, avec mode serveur et kit TypeScript | Pas de modèle E2B officiel : son propre serveur d'agent prévu pour Docker, à installer soi-même | Pas de modèle E2B officiel | Modèle officiel chez E2B |
| Licence | MIT : usage commercial autorisé | MIT : usage commercial autorisé | Apache 2.0 : usage commercial autorisé | Conditions commerciales d'Anthropic |
| Permissions avant action risquée | Oui : « autoriser », « demander », « refuser » par outil et par commande, avec réponse possible à distance | Analyse de sécurité annoncée | Dossiers de confiance | Oui |
| Difficulté pour Kekeli | **Faible** : même langage que le Runner (TypeScript), exemple officiel E2B | Moyenne à élevée : ajoute Python et un serveur à empaqueter soi-même | Faible, mais impasse : un seul fournisseur. De plus, depuis le 18 juin 2026, l'accès gratuit individuel a été transféré vers « Antigravity CLI » | Faible, mais un seul fournisseur |

Autres options examinées : **Goose** (Apache 2.0, géré par la Linux Foundation, une quinzaine de fournisseurs, pas de modèle E2B officiel), **Aider** (Apache 2.0, excellent pour des modifications relues une à une, moins autonome, entretien incertain en 2026), **Codex CLI** (Apache 2.0, pensé d'abord pour OpenAI).

**Recommandation : OpenCode.** C'est la seule option qui réunit les trois exigences : vraiment multi-modèles, prête à l'emploi sur E2B, et licence libre pour un usage commercial. OpenHands est le plan de secours.

Points de vigilance : OpenCode évolue très vite. On fixera une version précise et on la mettra à jour volontairement. La configuration exacte du fournisseur Google sera vérifiée à l'étape 2.

## 3. Ordinateur virtuel : E2B

Choix inchangé : **E2B** (Daytona en plan de secours).
- Modèle « opencode » prêt à l'emploi.
- Liste des sites autorisés modifiable pendant que l'ordinateur tourne.
- Lien d'aperçu immédiat pour chaque port.
- Formule gratuite avec 100 $ de crédit unique ; une session dure au plus une heure (on met en pause puis on reprend). Formule Pro à 150 $ par mois seulement quand il y aura des clients payants.
- Prix à l'usage : environ 0,17 $ de l'heure pour 2 processeurs et 4 Go de mémoire (estimation).

## 4. Modèles

### Pendant la construction : Gemini, offre gratuite de Google AI Studio

Modèles Gemini disponibles **gratuitement** (page officielle des prix, mise à jour le 30 octobre 2026) : Gemini 3.8 Flash, 3.6 Flash, 3.5 Flash-Lite, 3.1 Flash-Lite, 3 Flash Preview, 2.5 Pro, 2.5 Flash, 2.5 Flash-Lite. **Pas gratuits** : Gemini 4 Argon et Gemini 3.1 Pro Preview.

**Limites de l'offre gratuite.** Google ne publie plus de tableau de chiffres : les limites exactes s'affichent dans Google AI Studio, page « Rate limit », pour chaque projet. Elles s'appliquent **par projet** (pas par clé), et le quota du jour repart à zéro à **minuit, heure du Pacifique** (8 heures du matin à Lomé). Chiffres relevés par des sites spécialisés en octobre 2026, **à vérifier dans AI Studio** :

| Modèle gratuit | Requêtes par minute | Requêtes par jour |
|---|---|---|
| Gemini 3.8 Flash et 3.6 Flash | environ 5 | environ 20 |
| Gemini 3.5 Flash-Lite et 3.1 Flash-Lite | environ 10 | environ 500 |

**Ce que ça représente.** Un agent fait une requête au modèle à chaque petite action (lire un fichier, écrire, lancer une commande). Une tâche simple, comme une page web, demande **environ 15 à 40 requêtes** (estimation, à mesurer à l'étape 2) :
- avec un modèle Flash-Lite (environ 500 requêtes par jour) : **environ 12 à 30 tâches de test simples par jour** ;
- avec Gemini 3.8 Flash (environ 20 requêtes par jour) : **moins d'une tâche par jour**, donc inutilisable pour l'agent, seulement pour un essai ponctuel ;
- la limite par minute ralentit l'agent : il attend entre deux actions.

Conséquence : pendant la construction, on utilisera **un modèle Flash-Lite pour faire tourner l'agent**, et on réservera les modèles plus forts à quelques essais. Les Flash-Lite suffisent pour tester la mécanique (étapes 2 à 5), mais **pas pour juger la qualité d'un vrai site professionnel** (étape 6) : il faudra alors un petit budget payant pour comparer de vrais modèles.

### Au lancement : modèles payants au meilleur rapport qualité-prix

Deux rôles, chacun réglable séparément :
- un **modèle économique** pour les petites tâches ;
- un **modèle puissant** pour les tâches difficiles.

Candidats (prix officiels par million de tokens, entrée / sortie, relevés le 1er novembre 2026) :

| Fournisseur | Modèle économique | Modèle puissant |
|---|---|---|
| Google | Gemini 3.5 Flash-Lite : 0,30 $ / 2,50 $ | Gemini 4 Argon : 2 $ / 10 $ jusqu'au 30 novembre 2026, puis 4 $ / 20 $ |
| Anthropic | Claude Haiku 5.5 : 0,10 $ / 0,50 $ | Claude Sonnet 5.5 : 2 $ / 10 $ ; Claude Opus 5.5 : 4 $ / 20 $ |
| OpenAI et autres | à comparer à l'étape 5 | à comparer à l'étape 5 |

**Les coûts par tâche sont des estimations** (par exemple environ 0,50 à 1,50 $ pour une tâche simple et 5 à 15 $ pour une grosse tâche avec un modèle du niveau de Claude Sonnet 5.5). **Ils seront mesurés à l'étape 5, avant de choisir les modèles et de fixer les prix.**

## 5. Utilisation des données dans l'offre gratuite de Gemini

Ce que disent les conditions officielles de l'API Gemini :
- Dans l'offre gratuite, Google **utilise les demandes et les réponses pour améliorer ses produits**, et **des personnes peuvent les lire** (après dissociation du compte et de la clé).
- Google demande de **ne pas envoyer d'informations sensibles, confidentielles ou personnelles** dans l'offre gratuite.
- Dans l'offre payante (projet lié à un compte de facturation Google Cloud), Google **n'utilise pas** les demandes ni les réponses pour améliorer ses produits ; il les garde un temps limité, seulement pour détecter les abus.
- Pour des utilisateurs situés dans l'Espace économique européen, en Suisse ou au Royaume-Uni, **seule l'offre payante** est autorisée.
- Il faut avoir **au moins 18 ans** pour utiliser l'API.

Ce que ça impose :
- **Pendant la construction** : uniquement des données de test inventées. Aucune donnée réelle de client.
- **Au lancement (étape 8), l'offre gratuite est interdite pour les vrais utilisateurs** : on passe sur une offre payante, que ce soit Gemini avec facturation Google Cloud, Claude, OpenAI ou un autre fournisseur, et la politique de confidentialité de Kekeli indique quels fournisseurs traitent les demandes.

## 6. Sécurité

1. Un ordinateur virtuel isolé par projet, effacé à la fin ; les fichiers du projet sont sauvegardés à part.
2. Aucune clé dans l'ordinateur virtuel (voir l'architecture). Les clés sont uniquement dans les variables d'environnement protégées.
3. Internet limité à une liste de sites autorisés, modifiable par l'utilisateur, chaque ajout étant enregistré.
4. Boutons « Autoriser » et « Refuser » avant toute action risquée, grâce aux permissions d'OpenCode (« demander » pour les actions risquées).
5. Nombre maximal d'étapes, budget maximal par tâche, quotas par utilisateur.
6. Le serveur OpenCode de chaque ordinateur est protégé par un mot de passe propre à ce projet.
7. Les sites des clients ne sont jamais publiés sur kekeli.dev.
8. Conditions d'utilisation : piratage, spam et hameçonnage interdits ; suspension possible.
9. Le contenu lu sur Internet est traité comme une information, jamais comme un ordre.

## 7. Étapes

| Numéro | Étape | Ce que l'on montre (captures sur téléphone simulé) |
|---|---|---|
| 1 | Fondations : site Laravel en français, PostgreSQL, comptes, page « Mes projets » | Terminée |
| 2 | **Runner + agent OpenCode dans un ordinateur E2B, cerveau Gemini gratuit (Flash-Lite)** ; réglage du modèle dans la configuration ; clé jamais dans l'ordinateur virtuel ; premier comptage des requêtes et des tokens | Demander « crée un fichier bonjour.txt » et voir l'agent travailler en direct |
| 3 | Aperçu en direct (onglets « Discussion » et « Aperçu ») | Voir une page simple apparaître pendant sa construction |
| 4 | Sécurité : boutons « Autoriser » et « Refuser » (permissions OpenCode), sites autorisés, effacement, sauvegarde et reprise | Refuser une action, ajouter un site, reprendre un projet le lendemain |
| 5 | Consommation et quotas par utilisateur, **mesure réelle des tokens et des coûts**, **grille de prix par modèle réglable**, comparaison chiffrée des modèles candidats, administration | Atteindre la limite gratuite et voir le compteur ; tableau des coûts par modèle |
| 6 | Qualité « site professionnel » : modèles de départ soignés, images libres de droits, vérifications automatiques ; **essais avec des modèles plus forts (petit budget payant)** | Demander un vrai site de restaurant et juger le résultat selon le modèle |
| 7 | Publication en un clic sur Cloudflare, **à une adresse gratuite `pages.dev`** | Appuyer sur « Publier » et ouvrir le site en ligne |
| 8 | **Choix final des modèles payants** (un économique, un puissant) et **sortie de l'offre gratuite** ; paiement Mobile Money (Mixx by Yas, Flooz) par capture d'écran validée manuellement par l'administrateur ; conditions d'utilisation et politique de confidentialité ; achat de kekeli.dev ; **mise en ligne sur Laravel Cloud** ; version bêta | Envoyer une capture de paiement, la valider dans l'administration, voir la limite augmenter |

Tant que le site n'est pas hébergé, le résultat de chaque étape est montré par des captures prises sur un écran de téléphone simulé (`docs/captures/`). La mise en ligne sur Laravel Cloud devient **indispensable à l'étape 8** ; elle est **conseillée dès l'étape 6**, pour essayer soi-même l'agent sur son téléphone avant la bêta.

Après l'étape 8 : lancement de la création de sites web. Ensuite : applications, agents IA, automatisations, puis mode développeur (GitHub).

## 8. Comptes à créer et coûts de départ

| Compte | Usage | Quand | Coût au départ |
|---|---|---|---|
| GitHub | Le code | Déjà fait | 0 $ |
| **Google AI Studio** | Gemini, offre gratuite | **Étape 2** | 0 $ |
| **E2B** | Les ordinateurs virtuels | **Étape 2** | 0 $ (100 $ de crédit offert, sans carte) |
| Fournisseurs payants (Google Cloud avec facturation, Anthropic, OpenAI ou autre) | Essais de qualité, puis lancement | Étapes 6 et 8 | Petit budget d'essai à l'étape 6 (estimation : 10 à 20 $), puis selon l'usage |
| Laravel Cloud | Le site Kekeli et PostgreSQL | Étape 8 (conseillé dès l'étape 6) | Premier mois gratuit, puis 5 $ par mois (5 $ d'usage inclus) |
| Hébergement du Runner (par exemple Railway) | Le Runner | En même temps que Laravel Cloud | Environ 5 $ par mois, à vérifier |
| Cloudflare | Publication des sites des clients (`pages.dev`) et gestion de kekeli.dev | Étape 7 | 0 $ |
| Nom de domaine kekeli.dev | Le site Kekeli | Étape 8 | Environ 12 à 15 $ par an, à vérifier |
| Pexels, Unsplash, Pixabay | Images libres de droits | Étape 6 | 0 $ |

Pas d'agrégateur de paiement au départ, et pas de deuxième nom de domaine.

**Coût pendant les étapes 2 à 5 : 0 $** (Gemini gratuit, crédit E2B offert). Une carte bancaire internationale (Visa ou Mastercard acceptant les dollars) sera nécessaire à partir de l'étape 6.

## 9. Risques et limites

1. **Offre gratuite de Gemini très limitée** : quelques dizaines de tâches de test par jour au mieux avec Flash-Lite, et Google peut changer ces limites sans prévenir. Si elles deviennent trop basses, il faudra un petit budget payant plus tôt que prévu.
2. **La qualité ne peut pas être jugée avec un modèle gratuit léger** : les tests des étapes 2 à 5 valident la mécanique, pas la qualité finale des sites.
3. **Un agent multi-modèles ne se comporte pas pareil selon le modèle** : chaque changement de modèle devra être revérifié sur les mêmes tâches de test.
4. Le prix reste le premier risque au lancement : les limites et les prix seront fixés après les mesures de l'étape 5.
5. Données : aucune donnée réelle dans l'offre gratuite ; offre payante obligatoire au lancement.
6. La validation manuelle des captures de paiement prend du temps et expose aux fausses captures : vérifier chaque paiement dans l'historique Mixx by Yas ou Flooz avant de valider.
7. Dépendance à plusieurs entreprises (Google, E2B, Laravel Cloud, Cloudflare, et le projet OpenCode) dont les prix et les règles peuvent changer ; le moteur multi-modèles réduit ce risque côté modèles.
8. Abus : modération nécessaire, suspension des comptes et des sites.
9. Les grosses tâches durent plusieurs minutes : le téléphone doit pouvoir être fermé puis rouvert sans rien perdre.
10. Les adresses `pages.dev` sont gratuites mais portent le nom de Cloudflare ; un nom de domaine personnalisé pour les clients pourra venir plus tard.
11. Une politique de confidentialité conforme à la loi togolaise sur la protection des données personnelles sera nécessaire avant le lancement.

## Sources

- Moteur : [OpenCode](https://opencode.ai/docs/), [fournisseurs OpenCode](https://opencode.ai/docs/providers/), [permissions OpenCode](https://opencode.ai/docs/permissions/), [serveur OpenCode](https://opencode.ai/docs/server/), [OpenCode sur E2B](https://docs.e2b.dev/agents/opencode.md), [OpenHands Software Agent SDK](https://docs.openhands.dev/sdk), [Gemini CLI](https://github.com/google-gemini/gemini-cli), [authentification Gemini CLI](https://geminicli.com/docs/get-started/authentication/)
- Gemini : [limites](https://ai.google.dev/gemini-api/docs/rate-limits), [prix](https://ai.google.dev/gemini-api/docs/pricing), [conditions](https://ai.google.dev/gemini-api/terms)
- Chiffres de quotas non officiels : [ScriptByAI](https://www.scriptbyai.com/gemini-api-free-tier-limits/), [Klymentiev](https://klymentiev.com/blog/gemini-api-free-tier)
- E2B : [prix](https://e2b.dev/pricing), [accès à Internet](https://docs.e2b.dev/sandbox/internet-access)
