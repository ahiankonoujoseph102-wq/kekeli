# Plan de Kekeli

- **1er novembre 2026** : plan validé, avec trois corrections du porteur du projet (paiements, publication, coûts).
- **1er novembre 2026, mise à jour** : décision du porteur du projet de ne pas dépendre d'un seul modèle payant. Kekeli repose sur un **moteur multi-modèles** ; pendant toute la construction, le cerveau est **Gemini, avec l'offre gratuite de Google AI Studio**.
- **1er novembre 2026, deuxième mise à jour** : **OpenCode est validé** comme moteur. Pendant la construction et au début du lancement, Kekeli utilise les **offres gratuites de plusieurs fournisseurs**, rangés dans une **liste réglable** : quand le premier atteint sa limite, Kekeli passe automatiquement au suivant. Les requêtes sont comptées par fournisseur.

- **1er novembre 2026, étape 2 livrée** : Runner, agent OpenCode dans E2B, Gemini Flash-Lite, clé injectée par E2B, basculement et comptage. Constats ci-dessous (section « Constats de l'étape 2 »).
- **8 novembre 2026** : étape 2 validée par le porteur du projet. **Étape 3 livrée** : aperçu en direct (onglets « Discussion » et « Aperçu »), essai réel fait le même jour avec E2B et Gemini. Détails dans la section « Aperçu en direct (étape 3) ».

- **9 novembre 2026, étape 7 validée**, avec 4 corrections du modèle de mise en page (textes lisibles sur photo, menu mobile en panneau, ☰ à droite, typographie française corrigée automatiquement après chaque demande), vérifiées sur « Le Palmier » publié à nouveau (`docs/captures/etape-7-corrections.md`). Préparation de l'étape 8 : plan à valider par le porteur du projet avant toute construction.
- **9 novembre 2026, étape 7 livrée** : publication en un clic sur Cloudflare Pages (adresse gratuite en `pages.dev`), essai réel avec le site du restaurant « Le Palmier » en ligne, correction puis nouvelle publication ; règle « aucun texte dans les images créées ». Détails dans la section « Publication en un clic (étape 7) ».
- **9 novembre 2026, étape 6 validée**, avec des ajouts : création d'images avec Cloudflare Workers AI (offre gratuite, compte réservé à Kekeli) et nombre maximum d'images par jour réglable ; pas de bonus de bienvenue ; arrêt immédiat du travail quand l'énergie est épuisée, projet sauvegardé, message avec l'heure de recharge, bouton « Continuer ». Lancement de l'étape 7. Détails dans la section « Ajouts à l'étape 6 ».
- **9 novembre 2026, règle permanente** : aucune dépense sans l'accord du porteur du projet ; tout est gratuit pendant la construction. La clé Claude (Anthropic) ne sera achetée qu'à la fin du projet : **la comparaison avec Claude Haiku et Claude Sonnet se fera à ce moment-là**.
- **9 novembre 2026, fin de l'étape 6** : comparaison réelle des modèles gratuits, photos du client d'abord, photos libres regardées et floutées par Kekeli, pays du client, icônes Lucide, création d'images gratuite prête (clé Cloudflare à placer). Détails dans la section « Qualité site professionnel (étape 6) ».
- **9 novembre 2026, étape 6 livrée (première partie)** : outil de photos libres vérifiées, vérification automatique de la qualité et des images, modèle de mise en page, relance d'un agent arrêté en silence ; essai réel d'un site de restaurant. Détails dans la section « Qualité site professionnel (étape 6) ». (La comparaison payante prévue alors est remplacée par la règle « aucune dépense » du même jour.)
- **9 novembre 2026** : étape 5 validée avec les choix de formules (gratuite avec plafond total par jour, Découverte 2 500, Pro 7 500, Entreprise 25 000 FCFA par mois, prix provisoires non publiés, recharge chaque jour à minuit, pause après 10 minutes). Lancement de l'étape 6.
- **8 novembre 2026** : étape 4 validée. **Étape 5 livrée** : mesure des coûts, énergie qui se recharge, administration, effacement des ordinateurs en pause après 7 jours. Mesures et proposition de formules dans [`docs/couts.md`](couts.md) (rien n'est fixé sans l'accord du porteur du projet).
- **8 novembre 2026** : étape 3 validée. **Étape 4 livrée** (sécurité, sauvegarde et reprise), essai réel le même jour. Détails dans la section « Sécurité, sauvegarde et reprise (étape 4) ».

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

**Les clés ne vont jamais dans l'ordinateur virtuel.** **Confirmé à l'étape 2** : le Runner range les clés dans les « secrets » d'E2B ; une règle par hôte (fonction en bêta publique chez E2B) demande au proxy de sortie d'E2B d'ajouter la clé aux requêtes HTTPS vers le fournisseur. Dans l'ordinateur, OpenCode n'a qu'une fausse clé, et aucune variable d'environnement ne contient de clé (vérifié). Solution de secours si E2B retire cette fonction : une passerelle dans le Runner, une fois celui-ci hébergé.

## 2. Moteur de l'agent : OpenCode (validé le 1er novembre 2026)

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

**Choix validé : OpenCode.** C'est la seule option qui réunit les trois exigences : vraiment multi-modèles, prête à l'emploi sur E2B, et licence libre pour un usage commercial. OpenHands est le plan de secours.

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

Conséquence : pendant la construction, on utilise **un modèle Flash-Lite pour faire tourner l'agent**. La comparaison réelle de l'étape 6 (section 13) confirme que **Gemini 3.5 Flash-Lite est le meilleur modèle gratuit** pour un agent ; les modèles gratuits plus forts n'ont pas assez de requêtes par jour. Aucun budget payant pendant la construction : la comparaison avec des modèles payants (Claude Haiku, Claude Sonnet) se fera à la fin du projet.

### Offres gratuites de plusieurs fournisseurs

Recherche faite le 1er novembre 2026. Les chiffres viennent des pages officielles quand elles les publient ; sinon ils sont marqués « à vérifier » et se lisent dans la console de chaque fournisseur.

**Le piège des offres gratuites pour un agent.** Un agent renvoie toute la conversation à chaque action : une seule requête pèse souvent 10 000 à 30 000 tokens (estimation). Un fournisseur qui limite les tokens par minute à 8 000 est donc **inutilisable pour l'agent**, même s'il annonce beaucoup de requêtes par jour.

| Fournisseur | Modèles gratuits capables de programmer | Limites gratuites | Données | Usage commercial | Verdict |
|---|---|---|---|---|---|
| **Google AI Studio (Gemini)** | Gemini Flash-Lite, Flash, 2.5 Pro | Flash-Lite : environ 10 par minute et 500 par jour (à vérifier dans AI Studio) ; par projet ; remise à zéro à 8 heures à Lomé | **Utilisées pour améliorer les produits**, relecture humaine possible ; ne rien envoyer de personnel | Autorisé pour un usage professionnel ; **interdit pour des utilisateurs en Europe, en Suisse et au Royaume-Uni** | **Numéro 1** |
| **OpenRouter** (modèles marqués « free ») | Plusieurs modèles ouverts de programmation (liste qui change souvent) | **50 requêtes par jour**, 20 par minute ; **1 000 par jour** une fois 10 $ de crédits achetés (achat unique) | **Dépend de l'hébergeur de chaque modèle** : certains peuvent entraîner leurs modèles sur les demandes ; réglage de confidentialité à régler dans le compte | À vérifier dans les conditions d'OpenRouter | **Numéro 2** |
| **Mistral** (offre « Experiment ») | Devstral, Codestral, Mistral Small et Medium | Non publiées officiellement (environ une requête par seconde selon des sites spécialisés) ; vérification par téléphone | **Utilisées pour entraîner les modèles**, sauf si on désactive l'option dans la console (menu « Privacy ») | Offre présentée comme un essai : production à faire sur l'offre payante | **Écarté le 11 novembre 2026** : plus d'offre gratuite pour l'API (partie 22) |
| Cerebras | gpt-oss-120b, Qwen 3.8 | 5 par minute, 1 million de tokens par jour, mais seulement **un crédit unique de 5 $ valable 30 jours**, avec carte bancaire | Pas d'entraînement selon des sources secondaires | **« Pas destiné à la production »** | Facultatif, appoint ponctuel |
| Groq | gpt-oss-120b, gpt-oss-20b (Qwen 3.8 : 1 000 tokens de réponse par minute, inutilisable) | 30 par minute, 1 000 par jour, mais **8 000 tokens par minute** (mesuré le 10 novembre 2026) | **Pas d'entraînement** (contrat officiel), conservation limitée au service | **Autorisé** : intégration dans un produit pour ses propres clients (contrat officiel, article 3.1) ; interdit de revendre l'accès ou de faire un service concurrent | **Essayé le 10 novembre 2026 : impossible** (une demande de l'agent pèse environ 9 000 tokens) ; gardé dans le code, retiré de la liste |
| GitHub Models | Plusieurs modèles | Environ 150 par jour, mais environ **8 000 tokens par requête** | — | « Pour expérimenter » ; production sur l'offre payante | Écarté |
| OpenCode Zen (modèles gratuits d'OpenCode) | Plusieurs modèles « Free » | Non précisées, « pour une durée limitée » | Variable : certains n'entraînent pas, d'autres si | Non précisé | Seulement pour des essais pendant la construction |

**Attention aux comptes multiples.** Créer plusieurs comptes ou projets chez le même fournisseur pour multiplier les quotas gratuits est en général contraire à leurs conditions et peut entraîner la fermeture du compte. Kekeli utilise **un compte par fournisseur**.

**Plusieurs clés Gemini (vérifié le 9 novembre 2026 dans les pages officielles de Google)** :
- plusieurs clés **dans un même projet** ne donnent rien de plus : « les limites s'appliquent par projet, pas par clé » ;
- les clés **d'autres personnes** (même de la famille) : les conditions des API Google disent que les identifiants d'un développeur « sont destinés à être utilisés par vous », qu'il faut les garder confidentiels, et qu'il ne faut pas tenter de contourner les limites. Utiliser les comptes de proches pour multiplier les quotas gratuits risque la suspension des comptes, y compris celui du porteur du projet. **Recommandation : ne pas le faire** ; rien n'est construit pour cela. Les autres fournisseurs gratuits (OpenRouter, Mistral) restent possibles, avec un compte au nom du porteur du projet.

### Liste de fournisseurs et basculement automatique

- **Liste réglable sans toucher au code** : l'ordre des fournisseurs et le modèle de chacun se règlent dans les variables d'environnement, par exemple `KEKELI_FOURNISSEURS=gemini,openrouter,workersai`. Un fournisseur sans clé est simplement ignoré.
- **Basculement** : quand un fournisseur répond « limite atteinte » (code 429 ou quota du jour épuisé), Kekeli le met de côté jusqu'à l'heure de remise à zéro et **reprend la même tâche avec le fournisseur suivant**. OpenCode permet de choisir le modèle à chaque message, donc la conversation continue sans repartir de zéro.
- **Tous épuisés (problème interne)** : l'utilisateur **ne voit jamais qu'il s'agit d'offres gratuites**. Sa demande est **mise en attente** et **reprend automatiquement** quand un fournisseur redevient disponible ; il voit le message « Kekeli est très sollicité » (voir les messages de limite ci-dessous), et l'administrateur reçoit une **alerte dans l'administration**.
- **Comptage par fournisseur** : chaque requête, chaque refus pour limite atteinte et les tokens consommés sont enregistrés par fournisseur et par jour. Une page d'administration montre, pour chaque fournisseur, l'usage du jour comparé à sa limite, avec une alerte à partir de 80 % : c'est le signal qu'il devient nécessaire de passer à une clé payante.
- **Qualité variable** : les modèles ne se valent pas. Un même jeu de tâches de test sera passé sur chaque fournisseur de la liste, pour vérifier que le basculement ne dégrade pas trop le résultat.

### Messages de limite (à construire à l'étape 5)

L'image de Kekeli est **« l'énergie qui se recharge »**. On n'emploie jamais les expressions de Claude Code. **L'heure affichée est toujours l'heure de Lomé.**

| Situation | Message affiché | Bouton |
|---|---|---|
| Utilisateur de la formule gratuite, limite atteinte | « ☀️ Votre énergie du jour est épuisée. Kekeli se recharge et sera de nouveau prêt à [heure]. Vous ne voulez pas attendre ? Passez à une formule payante et continuez tout de suite. » | « Voir les formules » |
| Utilisateur d'une formule payante, limite atteinte | « ☀️ Vous avez utilisé toute l'énergie de votre formule pour le moment. Elle se recharge à [heure]. Votre projet est sauvegardé : vous reprendrez exactement où vous vous êtes arrêté. Besoin de plus ? Passez à la formule supérieure. » | « Voir les formules » |
| Tous les fournisseurs d'IA épuisés (problème interne) | « ☀️ Kekeli est très sollicité en ce moment. Votre demande est en attente et reprendra automatiquement vers [heure]. » La demande est mise en attente, et l'administrateur reçoit une alerte dans l'administration. | Aucun |

Le troisième message ne dit jamais que Kekeli utilise des offres gratuites.

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

## 5. Utilisation des données dans les offres gratuites

Ce que disent les conditions officielles de l'API Gemini :
- Dans l'offre gratuite, Google **utilise les demandes et les réponses pour améliorer ses produits**, et **des personnes peuvent les lire** (après dissociation du compte et de la clé).
- Google demande de **ne pas envoyer d'informations sensibles, confidentielles ou personnelles** dans l'offre gratuite.
- Dans l'offre payante (projet lié à un compte de facturation Google Cloud), Google **n'utilise pas** les demandes ni les réponses pour améliorer ses produits ; il les garde un temps limité, seulement pour détecter les abus.
- Pour des utilisateurs situés dans l'Espace économique européen, en Suisse ou au Royaume-Uni, **seule l'offre payante** est autorisée.
- Il faut avoir **au moins 18 ans** pour utiliser l'API.

Chez les autres fournisseurs gratuits : Mistral entraîne ses modèles sur les demandes sauf désactivation dans sa console ; chez OpenRouter, cela dépend de l'hébergeur de chaque modèle gratuit ; Groq n'entraîne pas ses modèles sur les demandes.

Ce que ça impose :
- **Pendant la construction** : uniquement des données de test inventées. Aucune donnée réelle de client.
- **Au début du lancement**, les offres gratuites peuvent servir la **formule gratuite** de Kekeli, avec trois conditions : **la politique de confidentialité de Kekeli dit clairement que, dans l'offre gratuite, les demandes peuvent être utilisées par les fournisseurs d'IA pour améliorer leurs services** (et nomme ces fournisseurs) ; les utilisateurs situés en Europe, en Suisse ou au Royaume-Uni ne passent jamais par Gemini gratuit ; on désactive l'entraînement partout où c'est possible (Mistral, réglages de confidentialité d'OpenRouter).
- **Les abonnés payants passent par des clés payantes.** Les quotas gratuits sont partagés entre tous les utilisateurs de Kekeli (par exemple environ 500 requêtes par jour au total chez Gemini Flash-Lite, soit seulement 12 à 30 tâches simples par jour pour tout le monde) : ils ne suffisent pas pour des clients qui paient.

## 6. Sécurité

1. Un ordinateur virtuel isolé par projet, mis en pause après 30 minutes sans demande, effacé avec le projet ; les fichiers du projet sont sauvegardés à part après chaque demande.
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
| 2 | **Runner + agent OpenCode dans un ordinateur E2B, cerveau Gemini gratuit (Flash-Lite)** ; **liste de fournisseurs gratuits réglable avec basculement automatique** ; si tous sont épuisés, demande mise en attente et reprise automatique, alerte pour l'administrateur ; **comptage des requêtes par fournisseur** avec une première page d'administration ; clé jamais dans l'ordinateur virtuel | Demander « crée un fichier bonjour.txt » et voir l'agent travailler en direct ; page d'administration des fournisseurs |
| 3 | Aperçu en direct (onglets « Discussion » et « Aperçu ») | Voir une page simple apparaître pendant sa construction |
| 4 | Sécurité : boutons « Autoriser » et « Refuser » (permissions OpenCode), sites autorisés, effacement, sauvegarde et reprise | Refuser une action, ajouter un site, reprendre un projet le lendemain |
| 5 | Consommation et quotas par utilisateur, **mesure réelle des tokens et des coûts**, **grille de prix par modèle réglable**, comparaison chiffrée des modèles candidats (gratuits et payants), **messages de limite « énergie qui se recharge »** (heure de Lomé), administration complète | Atteindre la limite gratuite et voir le compteur ; tableau des coûts par modèle |
| 6 | Qualité « site professionnel » : modèles de départ soignés, photos du client d'abord, images libres de droits choisies et floutées par Kekeli, création d'images gratuite, pays du client, icônes, vérifications automatiques ; **comparaison des meilleurs modèles gratuits** (les modèles payants seront comparés à la fin du projet) | Demander un vrai site de restaurant et juger le résultat selon le modèle |
| 7 | Publication en un clic sur Cloudflare, **à une adresse gratuite `pages.dev`** | Appuyer sur « Publier » et ouvrir le site en ligne (livrée le 9 novembre 2026) |
| 8 | **Choix final des modèles payants** (un économique, un puissant) pour les abonnés ; offres gratuites réservées à la formule gratuite ; **politique de confidentialité indiquant que, dans l'offre gratuite, les demandes peuvent être utilisées par les fournisseurs d'IA pour améliorer leurs services** ; paiement Mobile Money (Mixx by Yas, Flooz) par capture d'écran validée manuellement par l'administrateur ; conditions d'utilisation et politique de confidentialité ; achat de kekeli.dev ; **mise en ligne sur Laravel Cloud** ; version bêta | Envoyer une capture de paiement, la valider dans l'administration, voir la limite augmenter |

Tant que le site n'est pas hébergé, le résultat de chaque étape est montré par des captures prises sur un écran de téléphone simulé (`docs/captures/`). La mise en ligne sur Laravel Cloud devient **indispensable à l'étape 8** ; elle est **conseillée dès l'étape 6**, pour essayer soi-même l'agent sur son téléphone avant la bêta.

Après l'étape 8 : lancement de la création de sites web. Ensuite : applications, agents IA, automatisations, puis mode développeur (GitHub).

## 8. Comptes à créer et coûts de départ

| Compte | Usage | Quand | Coût au départ |
|---|---|---|---|
| GitHub | Le code | Déjà fait | 0 $ |
| **Google AI Studio** | Gemini, offre gratuite (variable `GEMINI_API_KEY`) | **Étape 2** | 0 $ |
| **OpenRouter** | Modèles gratuits, deuxième de la liste (variable `OPENROUTER_API_KEY`) | Étape 2, facultatif | 0 $ (50 requêtes par jour) ; 10 $ une seule fois pour passer à 1 000 requêtes par jour, facultatif |
| **Mistral** | Plus d'offre gratuite pour l'API depuis le 11 novembre 2026 : code gardé, inactif (variable `MISTRAL_API_KEY`) | — | Payant, non utilisé |
| **E2B** | Les ordinateurs virtuels (variable `E2B_API_KEY`) | **Étape 2** | 0 $ (100 $ de crédit offert, sans carte) |
| Fournisseurs payants (Google Cloud avec facturation, Anthropic, OpenAI ou autre) | Comparaison finale, puis abonnés payants | Fin du projet (étape 8) | Rien avant l'accord du porteur du projet ; la clé Claude est achetée à la fin (estimation de l'essai : 10 à 20 $) |
| Laravel Cloud | Le site Kekeli et PostgreSQL | Étape 8 (conseillé dès l'étape 6) | Premier mois gratuit, puis 5 $ par mois (5 $ d'usage inclus) |
| Hébergement du Runner (par exemple Railway) | Le Runner | En même temps que Laravel Cloud | Environ 5 $ par mois, à vérifier |
| Cloudflare | Publication des sites des clients (`pages.dev`) et gestion de kekeli.dev | Étape 7 | 0 $ |
| Nom de domaine kekeli.dev | Le site Kekeli | Étape 8 | Environ 12 à 15 $ par an, à vérifier |
| Pexels, Unsplash, Pixabay | Images libres de droits | Étape 6 | 0 $ |

Pas d'agrégateur de paiement au départ, et pas de deuxième nom de domaine.

**Coût pendant les étapes 2 à 5 : 0 $** (Gemini gratuit, crédit E2B offert). Une carte bancaire internationale (Visa ou Mastercard acceptant les dollars) sera nécessaire à partir de l'étape 6.

## 9. Constats de l'étape 2 (1er novembre 2026)

Mesures faites avec Gemini 3.5 Flash-Lite, offre gratuite, sur de petites tâches de test. **Ce sont des premières mesures, pas encore des moyennes.**
- **Ordinateur virtuel prêt en 6 à 7 secondes** (création, configuration, démarrage d'OpenCode). La toute première requête vers un nouvel ordinateur reste parfois bloquée : chaque essai est limité à 5 secondes, et un ordinateur qui ne démarre pas est effacé puis recréé une fois.
- **Petite tâche** (« crée un fichier bonjour.txt puis affiche-le ») : **3 à 4 requêtes au modèle**, environ **12 000 à 18 000 tokens envoyés** et 1 000 tokens reçus, en une dizaine de secondes. Une vraie page web demandera beaucoup plus : mesure à l'étape 5.
- Chaque requête renvoie toute la conversation : environ 4 000 à 9 000 tokens envoyés par requête dès le départ (consignes et outils d'OpenCode compris).
- **Basculement vérifié pour de vrai** : avec un modèle Gemini que l'offre gratuite refuse placé en premier, Google répond « limite atteinte » avec « Please retry in 3h2m10s » ; le Runner met ce modèle de côté jusqu'à sa remise à zéro et la même tâche continue avec le modèle suivant, sans que l'utilisateur voie quoi que ce soit.
- **Les quotas gratuits de Google sont comptés par modèle** : une liste comme `gemini/gemini-3.5-flash-lite,gemini/gemini-3.1-flash-lite,openrouter,mistral` additionne légalement les quotas des deux Flash-Lite (un seul compte, un seul projet).
- **Tous épuisés vérifié** : la demande passe « en attente », l'utilisateur voit « ☀️ Kekeli est très sollicité… reprendra automatiquement vers 07:00 », l'administrateur reçoit une alerte, et la demande est renvoyée automatiquement à l'heure prévue.
- **Remise à zéro de Gemini** : minuit, heure du Pacifique, soit **8 heures à Lomé en hiver et 7 heures en été**. Le 1er novembre, jour du changement d'heure américain, le quota du jour de Google repart à zéro à 07:00 (heure de Lomé) ; à partir du 2 novembre, à 08:00. Le calcul est automatique.
- **OpenRouter essayé pour de vrai le 10 novembre 2026** (clé gratuite placée, aucun crédit acheté) : meilleur site vitrine des essais, mais 50 requêtes par jour seulement. **Groq essayé** : impossible pour l'agent (8 000 tokens par minute). Mistral : écarté le 11 novembre 2026, plus d'offre gratuite pour l'API (partie 22). Détails dans la partie 20 et dans `docs/couts.md`.
- Les permissions d'OpenCode sont toutes accordées dans l'ordinateur isolé (sauf sortir du dossier du projet) : les boutons « Autoriser » et « Refuser » arrivent à l'étape 4.
- L'ordinateur virtuel est gardé 30 minutes après la dernière demande, puis effacé par E2B. La sauvegarde et la reprise des projets arrivent à l'étape 4.

## 10. Aperçu en direct (étape 3, 8 novembre 2026)

- **Un serveur d'aperçu dans chaque ordinateur virtuel** (`runner/src/apercu/serveur.mjs`, sans dépendance, port 4100), lancé par le Runner à côté d'OpenCode. Il montre les fichiers du dossier du projet comme un vrai site et ajoute aux pages un petit script qui **recharge la page dès qu'un fichier change** : l'utilisateur voit son site prendre forme pendant que l'agent travaille.
- Tant qu'il n'y a pas de page d'accueil (`index.html`), l'aperçu affiche « Votre site apparaîtra ici ». Les consignes de l'agent lui demandent de créer `index.html` en premier, avec des chemins relatifs, sans lancer de serveur lui-même.
- **Sécurité** : le serveur d'aperçu refuse les fichiers cachés (`.env`, `.git`…) et tout chemin qui sort du dossier du projet, liens symboliques compris. Le site Kekeli n'accepte que des liens HTTPS vers les domaines des ordinateurs virtuels (réglage `KEKELI_DOMAINES_APERCU`, par défaut `e2b.app,e2b.dev`) et affiche l'aperçu dans un cadre isolé. Les adresses d'E2B sont rendues publiques (`allowPublicTraffic`) pour que le cadre puisse les ouvrir : elles sont longues et impossibles à deviner, et le serveur OpenCode reste protégé par son mot de passe. Un accès à l'aperçu réservé au propriétaire du projet pourra être ajouté à l'étape 4.
- **Page du projet** : sur le téléphone, deux onglets « Discussion » et « Aperçu » ; sur un ordinateur, les deux côte à côte. Boutons « Recharger l'aperçu » et « Ouvrir l'aperçu dans un nouvel onglet ». À la fin d'une demande, un bouton « Voir l'aperçu » apparaît dans la discussion.
- **Mise en veille** : l'ordinateur reste allumé 30 minutes après la fin de la dernière demande ; l'heure (heure de Lomé) est affichée sous l'aperçu. Ensuite, l'aperçu passe « en pause ». Pour l'instant, une nouvelle demande rallume un ordinateur **vide** : la sauvegarde et la reprise des fichiers arrivent à l'étape 4.
- **Essai réel du 8 novembre 2026** (E2B, OpenCode, Gemini 3.5 Flash-Lite gratuit), demande « Crée une page d'accueil pour un restaurant à Lomé, avec le menu et les horaires » : ordinateur prêt en 10 secondes, aperçu affiché après 14 secondes, demande terminée en 33 secondes, **5 requêtes au modèle**, environ 22 300 tokens envoyés et 3 800 reçus (une seule mesure, pas encore une moyenne). L'aperçu s'est rechargé tout seul 3 fois pendant la construction ; l'adresse d'aperçu d'E2B s'ouvre bien dans le cadre de la page. Captures : `docs/captures/etape-3.md`.
- **Correction trouvée pendant l'essai** : une fois la demande terminée, la page ne se rafraîchissait plus, donc l'aperçu ne passait pas « en pause » tout seul. La page vérifie maintenant chaque minute tant qu'un aperçu est allumé.
- **Mise en veille vérifiée pour de vrai** : l'ordinateur s'est éteint 30 minutes après la fin de la demande (11 h 28, heure de Lomé) ; son adresse ne répond plus (erreur 502), et la page restée ouverte est passée toute seule « en pause » à 11 h 29. Pendant cette minute au plus, l'aperçu peut afficher une erreur avant de passer en pause.
- Limite connue : l'aperçu convient aux sites simples (HTML, CSS, JavaScript). Les applications qui demandent leur propre serveur (étape « Applications ») auront besoin d'un aperçu de leur port.

## 11. Sécurité, sauvegarde et reprise (étape 4, 8 novembre 2026)

### Ce qui a été construit
- **Pause au lieu d'effacement** : au bout de 30 minutes sans demande, E2B met l'ordinateur en pause (disque seulement) au lieu de l'effacer. Selon la documentation d'E2B, un ordinateur en pause est gardé sans limite de durée et **n'est pas facturé en temps de calcul** ; le prix du stockage n'est pas indiqué, **à vérifier** sur la facture d'E2B.
- **Sauvegarde à part** : après chaque demande, une copie compressée du projet est envoyée au site Kekeli (morceaux signés de 4 Mo, 50 Mo au plus). Si l'ordinateur ne peut pas être réveillé, un nouvel ordinateur reçoit la copie, et l'agent reçoit les 10 derniers échanges pour le contexte. Une fois le site hébergé, la copie ira dans un stockage objet (réglage `KEKELI_DISQUE_SAUVEGARDES`).
- **Aperçu privé** : chaque adresse d'aperçu porte une clé temporaire (valable jusqu'à la pause, plus 5 minutes), fabriquée par le site pour le propriétaire et l'administrateur ; sans elle, « Aperçu privé ». L'adresse ne change pas tant que l'heure de pause ne change pas : le cadre ne se recharge pas inutilement.
- **« Kekeli se met en pause… »** pendant les 90 secondes qui précèdent la pause, puis « L'aperçu est en pause » avec le bouton « Rallumer l'aperçu » (sans demande à l'agent, donc sans requête au modèle).
- **Licences** : consignes de l'agent (sources libres seulement : Unsplash, Pexels, Pixabay, Wikimedia Commons, Google Fonts, bibliothèques sous licence libre ; illustrations SVG faites par l'agent), fichier `LICENCES.md` obligatoire, vérification automatique après chaque demande, une correction demandée à l'agent si besoin, résultat affiché à l'utilisateur. Une carte ou une vidéo intégrée (Google Maps, YouTube) n'est pas comptée comme une ressource copiée.
- **Permissions** : les commandes risquées (effacer des dossiers, envoyer des données ou du code vers l'extérieur, changer les droits du système) demandent l'accord de l'utilisateur. Sans réponse en 10 minutes, l'action est refusée. Après un refus, l'agent est relancé pour en tenir compte et l'expliquer.
- **Sites autorisés** : l'ordinateur ne joint que les services indispensables (fournisseurs d'IA, dépôts de paquets npm et PyPI, liste des modèles d'OpenCode), les sources libres de droits, et les sites ajoutés par l'utilisateur pour son projet (30 au plus). Chaque ajout et chaque retrait est enregistré dans l'historique.
- **Effacement** : supprimer un projet efface ses ordinateurs (en marche ou en pause) et sa sauvegarde.
- **Administration** : page « Projets » avec tous les projets, et consultation d'un projet en lecture seule (conversation et aperçu).

### Constats de l'essai réel
- Pause automatique vérifiée : l'ordinateur est bien passé « en pause » chez E2B après 30 minutes ; la page restée ouverte a affiché « Kekeli se met en pause… » 90 secondes avant, puis « en pause », **sans aucune erreur**.
- Rallumage de l'aperçu : **43 secondes** (réveil de l'ordinateur, puis redémarrage d'OpenCode et du serveur d'aperçu). Reprise par une nouvelle demande : l'agent a retrouvé et modifié le site existant. Remise en place depuis la sauvegarde dans un nouvel ordinateur : le site était intact.
- Licences : à la première demande d'un essai, l'agent a oublié deux adresses dans `LICENCES.md` ; la vérification l'a vu, l'agent a corrigé, la seconde vérification a réussi. Au deuxième essai, tout était juste du premier coup (photos Unsplash avec adresse exacte, polices Google Fonts).
- 21 requêtes à Gemini pour l'essai complet de 5 demandes (une seule mesure).
- **Deux problèmes trouvés et corrigés pendant l'essai** : (1) avec Internet limité, OpenCode ne pouvait plus télécharger son outil de recherche (ripgrep, sur GitHub) : le Runner le dépose maintenant lui-même, après vérification de son empreinte ; (2) quand la toute première action de l'agent était refusée, le Runner attendait sans fin : il ne manque plus la fin du travail, et vérifie lui-même l'état de la conversation après 30 secondes sans nouvelle.

### Limites connues
- Les adresses de l'ordinateur restent techniquement publiques chez E2B (nécessaire pour l'aperçu dans la page) : l'aperçu est protégé par sa clé temporaire, OpenCode par son mot de passe ; un serveur lancé par l'agent sur un autre port (futures « Applications ») ne serait pas protégé : à traiter à cette étape.
- Le lien « Ouvrir l'aperçu dans un nouvel onglet » contient la clé temporaire : quelqu'un qui le reçoit peut voir l'aperçu jusqu'à la pause.
- Les ordinateurs en pause sont gardés tant que le projet existe ; un effacement automatique des projets inactifs depuis longtemps (la sauvegarde suffisant alors) pourra être ajouté à l'étape 5, quand on mesurera les coûts.
- La vérification des licences contrôle les sources et `LICENCES.md`, mais ne peut pas prouver qu'un texte ou un morceau de code n'a pas été recopié : c'est une règle donnée à l'agent.

## 12. Consommation, coûts et énergie (étape 5, 8 novembre 2026)

Détails, chiffres et proposition de formules : [`docs/couts.md`](couts.md).

- **Réglages dans l'administration** (page « Réglages ») : minutes avant la pause, jours en pause avant l'effacement (7), taux du dollar en FCFA (590), taille et prix de l'ordinateur, grille de prix des modèles, formules et case « Publier les formules ».
- **Mesure de chaque demande** : requêtes, tokens envoyés, reçus, de réflexion et lus depuis le cache, coût du modèle au prix de son offre payante, temps et coût de l'ordinateur au travail. Page « Consommation » : totaux du jour ou du mois, comparaison des mêmes demandes sur chaque modèle, formule et énergie de chaque utilisateur.
- **Énergie** : chaque formule donne une énergie en FCFA de coût réel, rechargée chaque jour à minuit (heure de Lomé) ou après un nombre d'heures. Messages de limite exacts du cahier des charges, heure de Lomé, bouton « Voir les formules ». Les formules payantes restent « en préparation » tant qu'elles ne sont pas validées et publiées.
- **Effacement automatique** : chaque nuit, les ordinateurs en pause depuis plus de 7 jours sont effacés, seulement si la sauvegarde du projet est à jour ; le projet revient dans un nouvel ordinateur à la demande suivante (vérifié en réel : 14 secondes, sans perte).
- **Constats** : un ordinateur Kekeli a 2 processeurs et 4 Go (0,166 $ de l'heure) ; **l'attente de 30 minutes avant la pause coûte plus que le modèle** pour une petite page (49 FCFA contre 18,5) : recommandation de passer à 10 minutes. Selon la documentation officielle d'E2B, un ordinateur en pause ne coûte rien (stockage non facturé, à vérifier sur la facture). Gemini gratuit a refusé plusieurs requêtes pour limite par minute pendant les mesures : la demande a été mise en attente puis reprise toute seule (vérifié en réel).
- **Défaut de qualité trouvé, pour l'étape 6** : l'agent invente parfois une adresse d'image Unsplash qui n'existe pas (image cassée) ; il faudra vérifier automatiquement que chaque image répond (corrigé à l'étape 6).

## 13. Qualité « site professionnel » (étape 6, 9 novembre 2026)

Captures et résultats : [`docs/captures/etape-6.md`](captures/etape-6.md).

### Ce qui a été construit
- **Images, dans cet ordre** :
  1. les **photos du client**, envoyées depuis son téléphone (bouton « Mes photos », 20 photos de 8 Mo au plus par projet), copiées dans l'ordinateur et utilisées en priorité ;
  2. sinon, une **photo libre** cherchée sur plusieurs sources et choisie par Kekeli (outil `runner/src/outils/images.mjs`) : Wikimedia Commons (CC0 et domaine public), Openverse (photos Flickr en CC0 ou domaine public) et Pexels (licence Pexels, dès qu'une clé gratuite est placée). **Seulement des licences qui n'obligent pas à citer l'auteur.** Un modèle de vision gratuit (Gemini 3.1 Flash-Lite, réglage `KEKELI_MODELE_VISION`) regarde chaque photo : refus des filigranes, des noms d'auteur effacés ou floutés, des personnes réelles reconnaissables et des photos d'un autre pays ou d'une autre culture ; marques, enseignes, textes et visages apparus par hasard floutés avec ImageMagick. La photo est enregistrée dans le projet, allégée, et notée dans `LICENCES.md` ;
  3. sinon, une **image créée** par Cloudflare Workers AI (modèle FLUX.1 schnell), gratuit dans la limite de 10 000 « neurones » par jour (de l'ordre de 190 images par jour, estimation) ; en attente de la clé ;
  4. sinon, une illustration en SVG ou en CSS.
- **Sources écartées** : Unsplash (ses règles d'utilisation par programme obligent à citer le photographe et à afficher l'image depuis ses serveurs, donc pas de floutage possible) ; Pixabay pour le moment (sa clé passe dans l'adresse des requêtes et ne peut pas rester hors de l'ordinateur virtuel ; possible plus tard par le Runner hébergé) ; génération d'images de Google (Nano Banana) : **pas d'offre gratuite** dans l'API selon la page officielle des prix.
- **Vrais produits du client** : jamais une photo libre ou une image créée à côté du nom d'un plat ou d'un article précis ; l'agent propose au client d'envoyer ses photos. Jamais de logo ou de marque d'une autre entreprise, ni de personne réelle reconnaissable.
- **Pays du client** : choisi à l'inscription (Togo par défaut), modifiable dans le profil, demandé avant le premier message aux anciens comptes ; l'agent reçoit le pays, la ville, la monnaie et le format des numéros de téléphone.
- **Icônes** : bibliothèque Lucide (licence ISC, plus de 2 300 icônes) copiée dans chaque ordinateur avec l'outil `icones.mjs` ; les émojis dans un site sont signalés par la vérification de qualité et corrigés par l'agent.
- **Énergie** : chaque image créée est comptée (0,001 $ par image au départ, réglable dans l'administration, estimation) ; les requêtes du modèle de vision sont comptées comme les autres.
- Déjà livré au début de l'étape : vérification automatique de la qualité et des images, modèle de mise en page `base.css`, ordinateur gardé allumé pendant le travail, relance d'un agent arrêté en silence.

### Comparaison réelle des modèles gratuits (même site de restaurant)

| Modèle gratuit | Résultat | Requêtes | Coût estimé au prix payant équivalent |
|---|---|---|---|
| **Gemini 3.5 Flash-Lite** | Site complet de 4 pages, le plus riche, en 2 minutes 30 | 22 | environ 75 FCFA |
| Gemini 3.1 Flash-Lite | Site complet, pages courtes et pauvres | 22 | environ 32 FCFA |
| Gemini 3 Flash Preview | Beau style, non terminé (limite du jour atteinte) | 23 | environ 66 FCFA (partiel) |
| Gemini 3.8 Flash et 3.6 Flash | Non terminé (environ 20 requêtes par jour) | 8 à 15 | — |
| Gemma 4 31B et 26B | Rien construit en 30 minutes (17 refus pour limite chacun) | 3 à 11 | gratuit seulement |

Écartés après vérification : Gemini 3.5 Flash et les Gemini 2.5 (pas d'offre gratuite pour un nouveau compte), les modèles gratuits d'OpenCode Zen (refusés depuis les ordinateurs E2B : « Free usage limit reached »).

### Recommandation : le meilleur modèle gratuit pour chaque formule (pendant la construction et la bêta gratuite)

| Formule | Modèle conseillé | Secours automatique |
|---|---|---|
| Gratuite | Gemini 3.5 Flash-Lite | Gemini 3.1 Flash-Lite, puis OpenRouter si une clé gratuite est placée |
| Découverte | Gemini 3.5 Flash-Lite | Gemini 3.1 Flash-Lite |
| Pro | Gemini 3.5 Flash-Lite | Gemini 3.1 Flash-Lite |
| Entreprise | Gemini 3.5 Flash-Lite | Gemini 3.1 Flash-Lite |

Aujourd'hui, **un seul modèle gratuit sait finir un vrai site** : Gemini 3.5 Flash-Lite. Les modèles gratuits plus forts (Flash) n'ont qu'environ 20 requêtes par jour, alors qu'un site en demande plus de 20 : ils ne peuvent pas servir une formule. La différence entre les formules se fait donc par l'énergie, pas par le modèle. Réglage conseillé : `KEKELI_FOURNISSEURS=gemini/gemini-3.5-flash-lite,gemini/gemini-3.1-flash-lite`. Les formules payantes recevront un modèle plus fort à la fin du projet, après la comparaison avec Claude Haiku et Claude Sonnet.

**Conséquence sur l'énergie** : un site de 4 pages avec photos choisies par Kekeli coûte environ 75 FCFA (estimation), plus que l'énergie gratuite d'un jour (30 FCFA, environ 1 petite page). À revoir avec le porteur du projet au moment de fixer les prix.

### Clés gratuites qui ajouteraient des possibilités (aucune n'est obligatoire, aucune ne demande de payer)
- `PEXELS_API_KEY` : plus de photos libres (Pexels).
- `CLOUDFLARE_API_TOKEN` et `CLOUDFLARE_ACCOUNT_ID` : création d'images gratuite (et utile à l'étape 7 pour publier sur `pages.dev`).
- `OPENROUTER_API_KEY` : un fournisseur de secours (50 requêtes gratuites par jour, environ 2 sites).
- `MISTRAL_API_KEY` : plus utile (Mistral n'a plus d'offre gratuite pour l'API depuis le 11 novembre 2026 ; code gardé, inactif).

### Ajouts à l'étape 6 (après validation)
- **Création d'images** : essai réel avec Cloudflare Workers AI (FLUX.1 schnell), offre gratuite, compte réservé à Kekeli. Les images pour des sites togolais sont réalistes et cohérentes (terrasse de restaurant, marché, couturière en tissu wax, salon de tresses), en 2 à 4 secondes chacune. Ce sont des personnes créées, pas de vraies personnes. Limite : des détails peuvent être approximatifs (par exemple l'attiéké absent d'un plat) ; une image créée n'est jamais présentée comme un vrai produit du client.
- **Nombre maximum d'images créées par jour**, pour tous les utilisateurs ensemble, réglable dans Administration → Réglages : 150 au départ, sous la limite gratuite de Cloudflare (10 000 « neurones » par jour, environ 58 par image de 1 024 × 1 024, soit environ 170 images ; estimation). Le compteur repart à minuit UTC, comme Cloudflare.
- **Pas de bonus de bienvenue.**
- **Énergie épuisée en cours de demande** : à chaque événement de l'agent, le site compare l'énergie utilisée (modèle, ordinateur, images, y compris le temps d'ordinateur de la demande en cours) à l'énergie de la formule. Dès qu'elle est épuisée, il répond « arrêter » au Runner, qui arrête l'agent aussitôt, sans vérification ni correction, puis sauvegarde le projet tel quel. L'utilisateur voit : « ☀️ Votre énergie est épuisée : Kekeli a arrêté le travail. Votre projet est sauvegardé tel quel, sans aucune perte. L'énergie se recharge à [heure] (heure de Lomé)… » avec le bouton « Voir les formules ». Après la recharge, ou après la validation d'une formule payante, le bouton « Continuer » reprend la même demande là où elle s'était arrêtée. L'arrêt se fait entre deux requêtes de l'agent : la dernière requête est donc comptée en entier.
- Les clés collées avec des crochets ou des guillemets sont nettoyées par le Runner, et le numéro de compte Cloudflare est extrait même de l'adresse d'une page du tableau de bord.

## 14. Publication en un clic (étape 7, 9 novembre 2026)

Captures et résultats : [`docs/captures/etape-7.md`](captures/etape-7.md).

### Ce qui a été construit
- **Bouton « Publier mon site »** dans chaque projet, dès que Kekeli a construit le site ; puis **« Publier à nouveau »** pour mettre en ligne la dernière version, à la même adresse. L'adresse du site en ligne s'affiche avec la date de publication (heure de Lomé).
- **Le site publié est la dernière sauvegarde du projet** (envoyée par le Runner après chaque demande) : aucun ordinateur virtuel n'est rallumé pour publier, donc aucun coût d'ordinateur.
- **Le Runner publie lui-même** (il est le seul à détenir la clé Cloudflare) par l'interface « Direct Upload » de Cloudflare Pages, la même que l'outil officiel : empreinte de chaque fichier (blake3, comme l'outil officiel), envoi des seuls fichiers que Cloudflare n'a pas déjà, puis déploiement en production. Les fichiers cachés et les dossiers techniques ne sont jamais publiés.
- **Nom de l'adresse** tiré du nom du projet, rendu unique par la fin de son identifiant (par exemple `restaurant-le-palmier-qzskk7.pages.dev`), gardé pour toutes les publications suivantes. Une adresse qui n'est pas en `pages.dev` est refusée.
- En cas d'échec : message simple pour l'utilisateur (« Réessayez dans un moment ») et alerte pour l'administrateur.
- La clé Cloudflare doit avoir deux droits : **Workers AI** (création d'images) et **Cloudflare Pages : modifier** (publication).

### Constats de l'essai réel
- Publication acceptée par Cloudflare en **11 secondes** ; nouvelle publication en quelques secondes.
- **La toute première adresse d'un compte neuf a mis une dizaine de minutes à s'ouvrir** (réponse « 522 » en attendant) : la page de Kekeli prévient l'utilisateur pendant 15 minutes après une publication.
- Défaut trouvé sur le site en ligne : les noms de plats longs passaient par-dessus les prix sur téléphone. Corrigé dans le modèle de mise en page ; essai complet « demande de correction, puis Publier à nouveau » réussi en environ une minute (7 requêtes).

### Limites de l'offre gratuite de Cloudflare Pages (page officielle, 5 septembre 2026)
- 20 000 fichiers par site, 25 Mo par fichier, **100 projets par compte**. Les publications directes ne sont pas comptées dans les 500 constructions par mois.
- **Conséquence** : avec un projet Cloudflare par site publié, le compte gratuit de Kekeli ne peut héberger que 100 sites clients. À revoir avant la bêta (étape 8) : par exemple un compte Cloudflare par tranche de clients, ou un autre hébergement gratuit, à décider avec le porteur du projet.

### Règle ajoutée : aucun texte dans les images créées
La description envoyée au service de création interdit tout texte ; Kekeli regarde chaque image créée avec le modèle de vision gratuit et la recrée (deux fois au plus) s'il voit des lettres, de faux mots ou une marque ; ce qui resterait est flouté. Chaque création compte dans le maximum du jour et dans l'énergie.

## 15. Version bêta (étape 8, 9 novembre 2026)

### Ce qui a été construit
- **Paiement Mobile Money par capture d'écran** : pays, numéros et titulaire affichés, envoi de la capture seule, validation ou refus dans Administration → Paiements, formule active 30 jours, rappel 3 jours avant la fin, retour à la formule gratuite. Numéros modifiables dans l'administration. Prix publiés.
- **Tous les pays du monde** ; Union européenne, Espace économique européen, Royaume-Uni et Suisse « bientôt disponibles » pendant la bêta (offre gratuite de Gemini interdite).
- **File d'attente** : 10 demandes en même temps au plus (offre « Hobby » d'E2B : 10 ordinateurs allumés), les suivantes attendent leur tour, dans l'ordre, avec leur position.
- **Code du client, gratuit pour tous** : un bouton « Connecter GitHub », la personne accepte sur GitHub, et le code part aussitôt dans un dépôt privé créé automatiquement ; fichier ZIP gratuit aussi. Seul le nom de domaine du client (fonction avancée) est payant ; essai réel du rattachement d'un nom de domaine chez Cloudflare réussi.
- **Comparaison avec les outils connus** (documentation officielle lue le 9 novembre 2026) : Lovable propose GitHub, GitLab et Bitbucket sur toutes ses formules, y compris la gratuite, avec création automatique d'un dépôt privé et synchronisation dans les deux sens ; il passe par une « application GitHub » installée sur le compte (et par une connexion OAuth pour GitLab et Bitbucket) ; son téléchargement direct du code est réservé aux formules payantes. v0 crée aussi un dépôt privé en quelques gestes. Kekeli fait aussi simple pour l'envoi, et va plus loin pour le ZIP (gratuit).
- **Conditions d'utilisation et politique de confidentialité** (loi togolaise numéro 2019-014), acceptées à l'inscription. À faire relire par un juriste.
- **Mise en ligne préparée** : `docs/mise-en-ligne.md` (réglages, prix officiels, coût réel en plus de la facture actuelle du porteur du projet, surveillance du coût sans jamais couper ses autres sites). Rien n'est créé sans l'accord du porteur du projet.

### Avant le lancement public (obligatoire)
- **Limiter l'accès GitHub aux seuls dépôts choisis par le client**, comme Lovable : passer de l'application OAuth (droit « repo », qui donne accès à tous les dépôts du compte) à une « application GitHub » que le client installe en choisissant « Seulement les dépôts choisis » ; Kekeli ne voit alors que les dépôts qu'il a créés ou que le client a choisis.
- Relecture des conditions d'utilisation et de la politique de confidentialité par un juriste.

### Reste à faire pour la bêta
1. Accord du porteur du projet sur les coûts, puis création des deux applications sur Laravel Cloud.
2. Vrai essai de la version qui s'endort (demande complète, publication, réveil après une nuit) ; sinon Runner toujours allumé.
3. Application OAuth GitHub de Kekeli (gratuite), puis vrai essai de l'envoi du code.
4. Relecture des pages juridiques par un juriste.

### Plus tard
- Envoi vers **GitLab** et **Bitbucket**, avec la même simplicité qu'avec GitHub : un bouton « Connecter GitLab » ou « Connecter Bitbucket », la personne accepte, et le code part tout seul (connexion OAuth gratuite, comme Lovable).
- Plus tard aussi : synchroniser dans les deux sens comme Lovable (les modifications faites sur GitHub reviennent dans Kekeli).
- Interface en **anglais** (fichier `lang/en.json` et pages juridiques en anglais).
- **Clé Claude** à la fin du projet : modèles payants pour les abonnés et ouverture des pays européens.
- Achat de **kekeli.dev**.

## 16. Galerie de modèles de sites (10 novembre 2026)

### Ce qui a été construit
- **Page « Modèles de sites »** (`/modeles`), ouverte à tous, même sans compte, avec un aperçu de chaque modèle dans un cadre de téléphone ; il faut un compte pour s'en servir (le visiteur crée son compte, puis revient sur le modèle). Gratuit pour toutes les formules.
- **12 modèles pour l'Afrique**, construits avec le modèle de mise en page de Kekeli et les icônes Lucide : restaurant ou maquis, boutique de pagnes et de vêtements, salon de coiffure et de tresses, atelier de couture, école ou centre de formation, église, ONG ou association, entreprise de BTP, cabinet de conseil, CV et portfolio, hôtel ou résidence, « Mon activité » (modèle simple). Contenu d'exemple inventé mais réaliste (Togo, FCFA, numéros au format local) ; 60 images créées gratuitement, sans texte.
- **« Utiliser ce modèle »** : le modèle devient un vrai projet (ses fichiers forment la première sauvegarde), puis Kekeli pose six questions simples, une à la fois (nom, ville et quartier, numéro WhatsApp, prix ou services, couleurs, photos), et personnalise tout le site. Ensuite, le client demande les modifications qu'il veut, comme pour un site normal.
- **Contenu d'exemple** : Kekeli refuse de publier un site qui contient encore le nom, l'adresse, le numéro, l'adresse électronique, un témoignage ou le texte de présentation d'exemple, et dit exactement ce qu'il reste à remplacer, avec un bouton « Demander à Kekeli de les remplacer ». L'agent a pour consigne de ne jamais inventer de témoignage, de chiffre ni de date.
- **Essai réel** : deux modèles personnalisés de bout en bout (restaurant et salon de coiffure), 28 et 42 FCFA d'énergie (voir `docs/couts.md`).

### Plus tard
- **Partager son site comme modèle (« remix »)** : avec son accord, un client pourra proposer son site comme modèle, et d'autres pourront le reprendre, comme le « remix » de Lovable. Ses informations personnelles (nom, numéro, adresse, photos) seront retirées et remplacées par du contenu d'exemple, avec la même vérification avant toute publication.
- D'autres modèles selon les demandes (pharmacie, garage, agence de voyage, événementiel…), et des modèles de plusieurs pages.

## 17. Mode développeur (10 novembre 2026)

### Ce qui a été construit
- **« Ouvrir un dépôt GitHub »** : la liste des dépôts du client (après « Connecter GitHub »), ou n'importe quel dépôt public ouvert en lecture seule, sans compte GitHub.
- **Dans l'ordinateur virtuel** : le dépôt est ouvert sur une branche de Kekeli (`kekeli/date-heure`). Kekeli reconnaît le type de projet (Laravel, PHP, Node.js, Python, site simple) et installe ce qu'il faut :
  - la bonne version de PHP, depuis packages.sury.org quand le projet demande plus que PHP 8.2 ;
  - Node 22 et Composer ;
  - les dépendances, puis la construction de l'interface.

  L'agent lit d'abord CLAUDE.md, AGENTS.md et README.md, travaille directement sur les fichiers, lance les tests et la vérification du style, corrige ses erreurs et enregistre son travail dans des commits. Consignes : jamais de « git push », jamais d'historique réécrit, jamais de test désactivé, jamais de secret. Les fichiers créés par l'installation (par exemple un composer.lock que le dépôt ne suit pas) ne sont jamais mêlés au travail.
- **La clé GitHub du client ne quitte jamais le site Kekeli** :
  - le site télécharge l'archive du dépôt et la transmet au Runner par une adresse signée ;
  - après chaque demande, le Runner renvoie la branche, les commits et les fichiers modifiés, et sauvegarde le dépôt sans les fichiers ignorés par le projet ;
  - le site envoie lui-même les fichiers modifiés sur la branche de Kekeli (interface « Git Data » de GitHub), puis ouvre la pull request.
- **Vérification automatique de GitHub** : Kekeli la surveille. Si elle est verte, il le dit en français simple. Si elle échoue, il lit les erreurs et la fin du journal, corrige seul (2 fois au plus, réglable, chaque fois comptée dans l'énergie du client) et renvoie dans la même pull request. Ensuite, il demande au client.
- **Fusion** : seulement si le client la demande (bouton et confirmation), et jamais pendant que la vérification tourne ou est rouge.
- **Actions risquées** : elles demandent l'accord du client, comme pour les sites. Envoyer sur GitHub et fusionner sont des gestes du client.

### Essais
- **Essais réels** (E2B et Gemini 3.1 Flash-Lite, offre gratuite) sur deux dépôts publics en lecture seule :
  - `laravel/laravel` : 1 minute 51 secondes et environ 35 FCFA, ajout d'une page et de son test, tests et style verts ;
  - `jonschlinkert/is-number` : 1 minute 10 secondes et environ 33 FCFA, ajout d'une option et de ses tests, 114 tests verts.
- **Défauts trouvés pendant les essais et corrigés** :
  - PHP trop ancien pour Laravel 13 : PHP 8.3 est installé depuis sury.org ;
  - Node trop ancien pour Vite : Node 22 est installé ;
  - la police de Laravel est téléchargée sur fonts.bunny.net : le site est ajouté aux sources libres ;
  - des fichiers d'installation se mêlaient au travail : ils sont maintenant ignorés.
- **Avec un faux GitHub, dans les tests automatiques** : l'envoi des seuls fichiers modifiés, la pull request, la surveillance (verte, rouge, correction automatique, maximum de corrections), la fusion sur demande et la lecture seule.
- **À essayer pour de vrai juste après la mise en ligne**, avec l'application GitHub de Kekeli et un dépôt de test du porteur du projet : « Connecter GitHub », un dépôt privé, l'envoi, la pull request, la surveillance de la vérification et la fusion.

### Limites de Gemini gratuit sur un gros projet, comparé à Claude Code
- **Lecture du projet** : l'agent lit environ 100 000 tokens pour un petit projet. Sur un gros projet (des centaines de fichiers), il ne peut pas tout garder en tête : il risque d'oublier une règle, une partie du projet ou l'effet d'une modification ailleurs. Claude Code s'en sort beaucoup mieux sur les longues tâches qui touchent de nombreux fichiers.
- **Raisonnement** : les modèles gratuits Flash-Lite font bien les modifications simples et bien décrites (une page, une option, un test). Ils sont nettement moins fiables pour une refonte, un bogue difficile, une migration de base de données délicate ou une architecture à concevoir.
- **Quotas** : environ 500 requêtes par jour et par modèle Flash-Lite, partagées par tous les utilisateurs gratuits. Une tâche de développeur en demande 14 à 20, parfois beaucoup plus sur un gros projet. Pendant l'essai Laravel, le quota du premier modèle s'est épuisé en plein travail : Kekeli a basculé automatiquement sur l'autre modèle et a terminé, mais ce sera fréquent avec de vrais utilisateurs.
- **Recommandation** : en formule gratuite, le mode développeur convient aux petites modifications. Pour un vrai travail de développeur sur un gros projet, il faudra le modèle puissant payant (Claude Sonnet ou équivalent, à la fin du projet) dans les formules payantes.

### Avant le lancement public
- Limiter l'accès GitHub aux seuls dépôts choisis par le client (application GitHub), comme Lovable.

## 18. Messages clairs de Kekeli (10 novembre 2026)

### Ce qui a été construit
- **Pendant le travail** : avant chaque petit groupe d'actions, l'agent écrit une phrase courte en français simple qui dit ce qu'il fait et pourquoi (par exemple « J'ajoute la section livraison et je change la couleur principale en vert. »). Les phrases s'affichent au fur et à mesure ; les détails techniques (commandes, fichiers) restent visibles en petit, repliés sous chaque phrase. Quand quelque chose échoue, l'agent le dit franchement, avec la cause et ce qu'il fait pour corriger.
- **Seulement du français** : une réflexion de l'agent écrite en anglais n'est jamais montrée au client (filtre dans le Runner).
- **Aucune requête de plus** : les phrases sont écrites par l'agent dans ses réponses habituelles ; le Runner les envoie au site dès que l'action qui suit commence.
- **À la fin, un compte rendu toujours dans le même ordre** : ce qui a été fait ; les fichiers modifiés ; les tests et vérifications ; ce qui n'a pas marché ou reste à faire ; ce que le client doit vérifier lui-même. Il est construit par le site à partir de ce que Kekeli sait déjà (fichiers comparés, commandes de test, vérifications automatiques, échecs), complété par les mots de l'agent. Une demande qui échoue a aussi son compte rendu, qui dit franchement pourquoi.
- **« Voir les modifications »** : Kekeli photographie les fichiers du projet avant chaque demande, dans un dépôt Git à lui rangé à part (il ne touche jamais au dépôt du client), puis compare après la demande. Le client voit chaque fichier ajouté, modifié ou supprimé, avec les lignes ajoutées en vert et les lignes retirées en rouge.
- **« Copier le compte rendu »** et **« Partager sur WhatsApp »** : le compte rendu en texte simple, dans le même ordre.
- **Historique** : bouton « Comptes rendus » dans chaque projet, avec tous les comptes rendus du plus récent au plus ancien.
- **Fiabilité** : une erreur passagère du modèle (par exemple « Requests ending with a model turn are not supported » chez Gemini, ou un serveur surchargé) relance le même travail deux fois au plus au lieu de faire échouer la demande ; après une correction automatique, l'agent réécrit le compte rendu de toute la demande.

### Coût
Mesuré par des essais réels : voir `docs/couts.md`, partie « Messages clairs ».

## 19. Sites avec base de données (10 novembre 2026)

Des sites professionnels avec base de données, qui s'ouvrent dans le navigateur (pas des applications mobiles) : comptes des utilisateurs, formulaires enregistrés, espace administrateur, tests. Appelés d'abord « applications web » ; depuis la décision du 10 novembre 2026, le client ne voit plus ce mot (voir « Un seul parcours « Nouveau site » » ci-dessous).

### Un seul parcours « Nouveau site » (décision du 10 novembre 2026)
Kekeli crée des **sites web**, comme Claude Code, Cursor, Codex, Lovable ou Manus : site vitrine **ou** site professionnel avec base de données, dans un seul parcours.
- **« Nouveau site »** (bouton principal de « Mes projets », page `/projets/nouveau-site`) : le nom et la description du site, avec ses propres mots. Pendant qu'il écrit, le client voit « Kekeli a compris : site vitrine » ou « site avec base de données : comptes des utilisateurs, espace administrateur, informations enregistrées ». Il peut corriger (« Site vitrine » ou « Site avec base de données ») et, pour un site avec base de données, choisir la technologie (facultatif, replié). Kekeli commence aussitôt avec la description, comme Lovable ; s'il manque le pays ou l'énergie, la description attend dans la page du projet, rien n'est perdu.
- **Comment Kekeli comprend** : des mots de la description (comptes, connexion, inscriptions, administrateur, tableau de bord, valider, commandes enregistrées, suivi, base de données…), sans requête au modèle, donc instantané et gratuit. Des comptes, ou deux indices différents (par exemple administration et informations enregistrées), suffisent ; « réservations par WhatsApp » seul reste un site vitrine ; « site vitrine » écrit par le client l'emporte toujours.
- **Un site vitrine qui demande ensuite des comptes ou un espace administrateur** : Kekeli explique qu'il faut une base de données et propose « Créer un site avec base de données » (nouveau projet à partir de la demande, le site vitrine reste tel quel) ou « Envoyer quand même à ce site ».
- La page séparée « Nouvelle application web » est supprimée (son ancienne adresse renvoie vers « Nouveau site ») ; le mot « application » n'apparaît plus pour le client : pages, étapes du travail, compte rendu, guides de mise en ligne, consignes de l'agent (qui doit dire « votre site »), nom du site de départ (« Mon site »).
- **Galerie** : « Sites vitrines » (12 modèles) et « Sites avec base de données » (5 modèles), sur la même page ; un modèle avec base de données ouvre « Nouveau site » avec le modèle déjà choisi.
- **Tout est gardé** : Laravel, Next.js, base de données, comptes, espace administrateur, aperçu, guides d'hébergement, fichier ZIP pour cPanel, priorité des clients payants.

### Ce qui a été construit
- **Deux technologies, aussi solides l'une que l'autre** :
  - **Laravel 12 (PHP 8.3) et MySQL** : conseillée par défaut, la plus simple à mettre en ligne, même sur un hébergement mutualisé avec cPanel ;
  - **Next.js 15.5 (Node) et PostgreSQL** : pour Vercel et Render ; PostgreSQL partout (PGlite, le vrai PostgreSQL dans Node, pour l'essai et les tests ; la base du client en ligne) ; requêtes SQL toujours avec des paramètres.

  Le client choisit, ou « Je ne sais pas » : Kekeli propose Laravel, ou Next.js si la description parle de Node, Next.js, Vercel ou JavaScript.
- **Socle et modèles fabriqués, pas improvisés** : une seule description (`runner/src/applications/modeles.json`) produit la même application en Laravel et en Next.js (`laravel.js`, `nextjs.js`). Le socle « demandes » et 5 modèles de la galerie (réservations d'un restaurant, d'un hôtel, commandes d'une boutique, inscriptions des élèves d'une école, inscriptions d'une association) ont chacun leurs tests (16 en Laravel, 13 en Next.js), tous verts. Versions exactes des paquets fixées (`composer.lock`, `package-lock.json`). Gemini n'a plus qu'à ajouter le métier du client : moins d'énergie et moins d'erreurs.
- **Sécurité dans chaque application** :
  - mots de passe chiffrés (cast « hashed » ou bcrypt) ;
  - formulaires protégés (@csrf ou actions serveur de Next.js) ;
  - essais de connexion limités ;
  - données de chaque utilisateur séparées, vérifiées par des tests ;
  - le rôle d'administrateur ne vient jamais d'un formulaire (variable `ADMIN_EMAIL`) ;
  - aucun secret dans le code ; en-têtes de sécurité.

  Après chaque demande, Kekeli lance les tests, cherche les secrets écrits dans le code, vérifie la protection des formulaires et que l'application répond ; en cas de problème, l'agent corrige une fois.
- **Aperçu en direct de l'application qui tourne** :
  - le serveur d'aperçu lance l'application (base d'essai, administrateur d'essai propre à chaque projet) et la relance si elle s'arrête ;
  - le lien privé dépose un cookie protégé, puis l'application est vue à son adresse normale ;
  - les cookies de l'application sont adaptés au cadre de la page (SameSite=None, Secure, Partitioned) ;
  - un bouton ouvre l'aperçu dans un nouvel onglet (utile sur iPhone).
- **Code du client** : GitHub ou ZIP, gratuits, sans `.env` ni base d'essai.
- **Mise en ligne, à la charge du client** : page « Mettre en ligne », guide pas à pas en français simple pour Laravel Cloud, Hostinger, l'hébergement mutualisé avec cPanel et Render (Laravel), Vercel et Render (Next.js). Elle comprend :
  - les étapes à cocher ;
  - les réglages à remplir ;
  - les fichiers prêts (`.htaccess`, `Dockerfile`, `render.yaml`, `.env.example`) ;
  - la clé APP_KEY créée sur la page ;
  - les rappels de sécurité ;
  - le coût estimé de chaque hébergeur.

  Pour cPanel et Hostinger : **fichier ZIP prêt** (application et bibliothèques, sans secret), déposé avec le gestionnaire de fichiers, et page `/installation` (ouverte seulement quand `INSTALLATION_AUTORISEE=oui`) pour créer les tables sans terminal.
- **Formule gratuite** : la construction à partir de rien est ouverte à tous avec l'énergie du jour (le travail reprend le lendemain) ; Kekeli propose d'abord les modèles, qui coûtent moins cher.
- **Clients payants d'abord** : dans la file d'attente, les demandes payantes passent devant les gratuites ; quand le quota gratuit du jour atteint 85 % (réglable), les demandes gratuites n'utilisent plus que la moitié des places (réglable).

### Plus tard
- Essayer pour de vrai chaque guide chez chaque hébergeur, avec un compte de test du porteur du projet (les écrans des hébergeurs changent).
- Avant le lancement public, faire relire par un juriste le partage des responsabilités : le client est responsable des données personnelles de ses utilisateurs.

## 20. Fournisseurs gratuits Groq et OpenRouter (10 novembre 2026)

### Conditions officielles (lues le 10 novembre 2026)
- **Groq** ([contrat](https://console.groq.com/docs/legal/services-agreement), [limites](https://console.groq.com/docs/rate-limits)) : intégration dans un produit pour ses propres clients **autorisée** (article 3.1) ; interdit de revendre l'accès ou de construire un service concurrent ; **les demandes ne servent pas à entraîner les modèles** (article 4.2) et ne sont gardées que pour fournir le service ; pays exclus : ceux sous sanctions des États-Unis (selon le forum de Groq : Chine, Russie, Syrie, Iran, Corée du Nord, Cuba) ; offre gratuite mesurée : 30 requêtes par minute, 1 000 par jour, **8 000 tokens par minute**.
- **OpenRouter** ([conditions](https://openrouter.ai/terms), [confidentialité](https://openrouter.ai/privacy), [limites](https://openrouter.ai/docs/api-reference/limits)) : intégration dans ses propres produits **autorisée** (article 5.1) ; interdit de revendre l'accès aux modèles ou de faire un service concurrent (article 7.4) ; OpenRouter ne garde pas les demandes et ne s'en sert pas pour entraîner, **mais l'entreprise qui fait tourner chaque modèle gratuit applique ses propres règles** et peut conserver ou entraîner (article 6.1) ; certains modèles excluent des pays, et passer par un VPN pour les contourner est interdit (article 5.7) ; offre gratuite : 20 requêtes par minute, **50 par jour** (1 000 par jour après un achat unique de 10 $ de crédits, non fait).
- Les deux autorisent l'usage de Kekeli. Ni l'un ni l'autre ne l'interdit pour le Togo.

### Ce qui a été construit
- **Groq** ajouté au catalogue (clé `GROQ_API_KEY`, secret E2B `kekeli_groq`, modèle `KEKELI_MODELE_GROQ`, par défaut `openai/gpt-oss-120b`), avec la même protection que les autres : la clé ne va jamais dans l'ordinateur.
- **Aucun modèle payant chez OpenRouter, même par erreur** : trois verrous. Le site refuse tout modèle OpenRouter qui ne finit pas par `:free` (`ProviderList::isAllowed`) ; le Runner le refuse aussi (`modeleAutorise`, avant chaque appel) ; OpenCode ne connaît que les modèles gratuits de la liste (liste blanche) et son « petit modèle » (titres) est fixé, pour qu'il n'en choisisse jamais un payant de lui-même. Le routeur `openrouter/free` (modèle au hasard) est refusé.
- **Demande trop grande pour un modèle** (cas de Groq gratuit) : Kekeli passe au fournisseur suivant au lieu d'échouer.
- **Quota du jour d'OpenRouter** (« free-models-per-day ») reconnu : le fournisseur est mis de côté jusqu'à minuit (heure de Lomé, UTC) au lieu d'être réessayé toutes les quelques minutes.
- Le détail d'un refus d'OpenCode est gardé dans le journal du Runner (pour l'administrateur ; le client voit un message général).

### Essais et ordre choisi
Tableau complet dans `docs/couts.md` (captures : `docs/captures/fournisseurs-gratuits.md`). Ordre de la liste à ce moment-là : **`gemini,openrouter,mistral`**, remplacé le 11 novembre 2026 (partie 21) (Mistral ignoré tant que sa clé n'est pas placée ; Groq retiré). Gemini d'abord : beaucoup plus de requêtes par jour et il termine les sites avec base de données ; OpenRouter ensuite : le meilleur résultat, mais seulement 50 requêtes par jour, il prend le relais quand Gemini est limité.

## 21. Fournisseurs gratuits repris de freellmapi (10 novembre 2026)

### Ce que fait le dépôt freellmapi
[FreeLLMAPI](https://github.com/tashfeenahmed/freellmapi) (Tashfeen Ahmed, licence MIT) est un relais à installer chez soi : on y place ses propres clés gratuites de dizaines de fournisseurs d'IA (34 annoncés, environ 635 modèles), et il présente une seule adresse compatible OpenAI. À chaque demande, il choisit le meilleur modèle disponible, passe au suivant en cas de limite (code 429) ou d'erreur du serveur, compte les requêtes et les tokens par fournisseur, par modèle et par clé, et garde le même modèle pendant 30 minutes pour une même conversation. Son catalogue se met à jour depuis un service en ligne de l'auteur.
- **Il ne contient aucune clé ni aucun token gratuit** : les « 7,4 milliards de tokens par mois » sont la somme des offres gratuites de tous les fournisseurs, chacune demandant son propre compte.
- **Son auteur le réserve à un usage personnel et expérimental** (« not production ») et rappelle que les conditions de chaque fournisseur s'appliquent : Kekeli ne l'installe pas. Kekeli reprend la liste des fournisseurs et des modèles comme point de départ, puis vérifie les conditions officielles de chacun, et réécrit les bonnes idées dans son propre code (mentions : `MENTIONS.md`).
- Il contient aussi des services de recherche sur Internet (Tavily, Exa, Brave, Linkup, Firecrawl), d'images (Pollinations), de voix (Speechify) et de vision : **notés pour les étapes suivantes** (agents IA, recherches, affiches, vidéos), rien n'est construit maintenant (une étape à la fois).

### Ce qui a été repris dans Kekeli
- **Cloudflare Workers AI** devient un fournisseur de modèles (`workersai`), avec la même clé que les images : 10 000 neurones gratuits par jour, partagés avec la création d'images, remise à zéro à minuit UTC ; le compte doit rester sur l'offre gratuite (au-delà de la limite, les demandes échouent, aucune facture).
- **Catalogue de modèles gratuits par fournisseur et blocage de tout modèle payant**, sur le site (`ProviderList::isAllowed`) et dans le Runner (`modeleAutorise`, avant chaque appel) : OpenRouter seulement « :free » ; Cloudflare seulement sa liste de modèles gratuits (les modèles réservés à l'offre payante, comme Kimi K2.6, GLM-5.3 ou DeepSeek V4, sont refusés) ; jamais un Gemini payant (Pro, Argon, Ultra).
- **Même modèle pour un même projet pendant 30 minutes** (`MemoireModeles`) : la conversation reste cohérente d'une demande à l'autre ; il cède la place dès que ce modèle est limité.
- Déjà présents et gardés : passage au suivant sur limite, erreur du serveur ou demande trop grande, comptage par fournisseur, par modèle et par jour (`ProviderUsage`), mise en attente quand tout est épuisé.

### Conditions officielles de chaque fournisseur (lues le 10 novembre 2026)
Qualité pour écrire du code avec les outils de l'agent : mesurée par les essais réels quand une clé était placée (voir `docs/couts.md`), sinon estimée.

| Fournisseur | Limites gratuites | Usage commercial dans Kekeli | Données envoyées | Pays exclus | Qualité pour l'agent | Décision |
|---|---|---|---|---|---|---|
| **Google AI Studio** (Gemini 3.5 et 3.1 Flash-Lite) | Environ 10 par minute et 500 par jour **pour chaque modèle**, par projet ; remise à zéro à 8 heures à Lomé | Autorisé (usage professionnel) | Utilisées pour améliorer les produits, relecture humaine possible | Offre gratuite interdite pour des utilisateurs dans l'Union européenne, l'Espace économique européen, la Suisse et le Royaume-Uni | Bonne : termine les sites vitrines et les sites avec base de données | **Intégré** |
| **OpenRouter** (modèles « :free ») | 20 par minute, **50 par jour pour tous les modèles gratuits ensemble** | Autorisé (article 5.1), interdit de revendre l'accès (article 7.4) | OpenRouter ne garde rien ; l'entreprise qui fait tourner chaque modèle gratuit applique ses règles et peut entraîner | Certains modèles excluent des pays (VPN interdit) | La meilleure en site vitrine (Nemotron), mais Nemotron et North gratuits ne sont pas permis pour des clients (partie 22) | **Intégré**, avec Gemma 4 (servi par Google) depuis le 11 novembre 2026 |
| **Cloudflare Workers AI** | 10 000 neurones par jour, partagés avec les images | Autorisé (service commercial de Cloudflare) | Aucun entraînement, rien gardé après la demande | Pays sous sanctions | Non mesurée : la réserve du jour était déjà épuisée par les images (voir les essais) | **Intégré**, en dernier recours |
| **Mistral AI** (offre « Experiment ») | Environ une requête par seconde, quota mensuel de tokens (non publié) ; vérification par SMS | Autorisé selon les conditions commerciales | Entraînement **sauf si on le désactive** dans la console (à faire obligatoirement) | Pays sous sanctions | Non essayé (pas de clé) ; Devstral est fait pour le code | **Écarté le 11 novembre 2026** : plus d'offre gratuite pour l'API (formule « Experiment » supprimée, plan gratuit sans clé API) |
| Groq | 30 par minute, 1 000 par jour, **8 000 tokens par minute** | Autorisé (article 3.1) | Aucun entraînement | Pays sous sanctions des États-Unis | **Inutilisable** : une demande de l'agent pèse 8 788 tokens (mesuré) | Gardé dans le code, hors de la liste |
| Cerebras | Crédit **unique** de 5 $ valable 30 jours, **carte bancaire obligatoire** | Intégration autorisée, interdit de concurrencer | Aucun entraînement | Pays sous sanctions | Bonne (estimation) | **Écarté** : pas gratuit durablement, carte bancaire |
| NVIDIA (build.nvidia.com) | Environ 40 par minute | **Interdit en production** (essai et évaluation seulement) | Flou | — | — | **Écarté** |
| Cohere (clé d'essai) | 1 000 appels par mois | **Interdit en production et en usage commercial** | — | — | — | **Écarté** |
| Hugging Face | Plus de crédit gratuit pour les comptes gratuits depuis octobre 2026 | — | — | — | — | **Écarté** (payant) |
| GitHub Models | Environ 8 000 tokens par requête | « Pour expérimenter » | — | — | Trop petit pour l'agent | **Écarté** |
| SambaNova | 20 requêtes par jour | Conditions commerciales non trouvées | — | — | Trop peu (un site demande plus de 20 requêtes) | **Écarté** |
| Z.ai et Zhipu (GLM Flash gratuit) | Une demande à la fois | **Douteux** : conditions commerciales floues, clause contre la redirection de trafic (Z.ai), usage personnel ou de recherche (Zhipu) | Entraînement non exclu pour tous | — | — | **À décider** (rien intégré) |
| Ollama Cloud | Limites en sessions de 5 heures et par semaine, non publiées | **Douteux** : conditions commerciales non trouvées | — | — | — | **À décider** (rien intégré) |
| OVH AI Endpoints (sans compte) | 2 requêtes par minute par adresse | Non adapté à un service | — | — | Trop lent | **Écarté** |
| AI Horde | File d'attente de bénévoles | — | **Les demandes passent par des ordinateurs de bénévoles inconnus** | — | — | **Écarté** (confidentialité) |
| OpenCode Zen (gratuit) | « Durée limitée » | Non précisé | Variable | — | Refusé depuis les ordinateurs E2B | **Écarté** |
| Revendeurs (LLM7, Kilo, Routeway, Navy, AnyAPI, PowerTokens, Requesty, BazaarLink, OrcaRouter, UnoRouter, xKiro, Agnes et autres) | Variables | **Douteux** : ils revendent l'accès aux modèles d'autres entreprises, conditions absentes ou floues | Inconnu | — | — | **À décider** (rien intégré) |
| Fournisseurs chinois (Baidu Qianfan, Volcengine, iFlytek, ModelScope, SiliconFlow) | Variables | Compte et identité chinois exigés | Données en Chine | — | — | **Écarté** |
| Moonshot et MiniMax (en direct) | Plus d'offre gratuite | — | — | — | — | **Écarté** |

### Essais réels et ordre choisi (nuit du 10 au 11 novembre 2026)
Même restaurant à Lomé pour chaque fournisseur, puis le pressing en Laravel avec la chaîne Gemini ; tableau complet dans `docs/couts.md`, captures dans `docs/captures/fournisseurs-gratuits.md`.
- **Gemini 3.5 Flash-Lite** : site vitrine réussi en 2 minutes 12 (19 requêtes), le plus rapide.
- **Gemini 3.1 Flash-Lite** : réussi mais lent (12 minutes, 40 requêtes) et de qualité faible (menu vide dans l'aperçu). Son intérêt : **un quota gratuit séparé** de celui de Gemini 3.5 (Google compte par modèle), donc il prend le relais au lieu d'une pause.
- **Chaîne Gemini 3.5 puis 3.1, pressing en Laravel** : réussi en 5 minutes 45 au lieu de 27 minutes avec Gemini 3.5 seul, 17 tests réussis.
- **OpenRouter `cohere/north-mini-code:free`** : site vitrine réussi en 6 minutes 29 (48 requêtes), belle qualité (photo libre, icônes Lucide, bouton WhatsApp). Il partage les 50 requêtes par jour avec Nemotron : il sert de secours si Nemotron est indisponible.
- **Cloudflare Workers AI** : **non mesuré**. À minuit UTC passé, Cloudflare répondait encore « 10 000 neurones du jour utilisés » (la création d'images des essais précédents avait tout pris). Cela confirme qu'au-delà de la limite **Cloudflare refuse sans rien facturer**. Kekeli l'a reconnu comme une limite et a mis la demande en attente, comme prévu. À essayer un jour où aucune image n'a été créée.
- **Groq** : impossible (8 788 tokens pour une limite de 8 000 par minute).

**Ordre choisi ce jour-là : `gemini,gemini/gemini-3.1-flash-lite,openrouter,openrouter/cohere/north-mini-code:free,workersai,mistral`**, corrigé le 11 novembre 2026 (partie 22) : sans Mistral, sans North, et Gemma 4 à la place de Nemotron.
1. Gemini 3.5 d'abord : le plus de requêtes par jour (environ 500) et le plus rapide.
2. Gemini 3.1 ensuite : environ 500 requêtes de plus, quota séparé ; il évite les pauses quand Gemini 3.5 est limité à la minute.
3. OpenRouter Nemotron : la meilleure qualité, mais seulement 50 requêtes par jour.
4. OpenRouter North : même quota, utile seulement si Nemotron est indisponible.
5. Workers AI en dernier : sa réserve est petite (environ 2 sites vitrines par jour, estimation) et **elle sert d'abord aux images**.
6. Mistral : ignoré tant que sa clé n'est pas placée ; il pourra monter dans la liste après son essai réel.

## 22. Nettoyage du 11 novembre 2026 : Mistral, modèles gratuits d'OpenRouter, compte d'essai dans l'aperçu

### Mistral écarté
Le porteur du projet a vérifié sur console.mistral.ai : **il n'y a plus d'offre gratuite pour l'API**. Le plan « Gratuit » ne permet pas d'utiliser les clés API, l'API est payante (formule Pro ou crédits à l'usage), et la formule « Experiment » n'existe plus. Mistral sort de la liste par défaut, de la liste des clés à créer et des pages juridiques ; **son code reste, inactif**, comme pour Groq.

### Modèles gratuits d'OpenRouter : chacun suit les conditions de son hébergeur
- **OpenRouter** ([conditions, article 5.1](https://openrouter.ai/terms)) : « By accessing or using any Model through the Service, you agree, and will ensure that your Authorized Users and customers agree, to comply with the applicable terms for each Model ("Model Terms") ». Ce sont donc les conditions de l'entreprise qui fait tourner le modèle gratuit qui comptent. Leurs liens sont donnés par l'interface d'OpenRouter (`/api/v1/providers`).
- **North (`cohere/north-mini-code:free`)** : servi par Cohere, dont les [conditions](https://cohere.com/terms-of-use) disent qu'on ne doit pas « use, copy, distribute, or make available the Cohere Solution to permit timesharing, service bureau use or commercially exploit the Cohere Solution ». Le poids du modèle est sous licence Apache 2.0, mais la version gratuite passe par le service de Cohere. **Douteux pour des clients : retiré de la liste** (code gardé).
- **Nemotron 3 Ultra (`nvidia/nemotron-3-ultra-550b-a55b:free`)**, l'ancien modèle par défaut : servi par NVIDIA, sous les [conditions d'essai de NVIDIA](https://assets.ngc.nvidia.com/products/api-catalog/legal/NVIDIA%20API%20Trial%20Terms%20of%20Service.pdf) : « NVIDIA will provide you access to the API Service for limited trial purposes only and without use of the API Service or Generated Content in production ». **Interdit pour des clients : retiré** (code gardé). C'est la même raison que l'écart de NVIDIA dans la partie 21.
- **Remplaçant : Gemma 4 31B (`google/gemma-4-31b-it:free`)**, servi par Google AI Studio (conditions de Google Cloud, usage commercial permis ; poids sous licence Apache 2.0), sait utiliser des outils. **Qualité non mesurée** : au premier essai, Google le limitait (« temporarily rate-limited upstream », réserve partagée par tous les utilisateurs gratuits d'OpenRouter). Secours possible du même hébergeur : Gemma 4 26B. Écartés aussi : Thinking Machines (offre gratuite « de recherche »), Liquid (modèles trop petits pour coder).

### Nouvel ordre par défaut
**`gemini,gemini/gemini-3.1-flash-lite,openrouter,workersai`**, avec `KEKELI_MODELE_OPENROUTER=google/gemma-4-31b-it:free`.

### Compte d'essai dans l'aperçu (vrai défaut, corrigé)
- **Cause** : pendant l'essai du pressing, l'agent a lancé `php artisan migrate:fresh`, qui vide toute la base d'essai ; le compte administrateur d'essai affiché au client avait disparu (« Adresse électronique ou mot de passe incorrect »). Un client aurait eu le même problème.
- **Correction** : après chaque demande, avant les vérifications, le Runner recrée le compte d'essai (même adresse, même mot de passe, sans doublon) : `php artisan migrate --force` puis un petit script qui fait `updateOrCreate` sans passer par le `DatabaseSeeder` que l'agent a pu modifier (`retablirAdminEssai` dans `runner/src/application.js`) ; pour Next.js, `node scripts/donnees-essai.mjs`. Les consignes de l'agent interdisent `migrate:fresh`, `migrate:reset` et `db:wipe`. Essayé pour de vrai sur le socle Laravel : compte absent après `migrate:fresh`, puis présent avec le bon mot de passe, une seule fois, même après `db:wipe`.
- **Message pendant le redémarrage** : une erreur du serveur (500 ou plus) sur une page n'est plus jamais montrée telle quelle (souvent en anglais). Dans les 90 secondes après un lancement du site : « Votre site redémarre » avec rechargement automatique ; sinon : « Cette page a rencontré un problème », avec le conseil de le dire à Kekeli dans la discussion. Le mot « application » a aussi disparu de ces pages (« Votre site s'installe »).

## 23. Analyse : un Kekeli aussi professionnel que Bolt.new, Lovable, Claude Code, Codex, Cursor et Manus (11 novembre 2026)

**Étape d'analyse seulement : aucun code n'est modifié avant l'accord du porteur du projet.** Demande du porteur du projet : retirer la galerie des modèles de sites (jugée médiocre) au profit d'une simple zone « Décrivez votre site web » ; deux façons de travailler avec GitHub (dépôt d'abord, ou site d'abord) ; une présentation et un parcours professionnels partout. Dans cette partie, on dit « sites web » (site vitrine ou site avec base de données), jamais « applications ».

### 23.1 Comment travaillent les six outils (documentation lue le 11 novembre 2026)

| Thème | Bolt.new | Lovable | Claude Code (version web) | Codex (version web) | Cursor (agents sur le web et le téléphone) | Manus |
|---|---|---|---|---|---|---|
| Premier écran | Une grande zone de saisie, au centre ; importer un dépôt GitHub juste en dessous | Une grande zone de saisie sur le tableau de bord, avec pièces jointes (images, PDF, documents) et choix du mode | La zone de saisie, avec le choix du dépôt et de la branche juste en dessous | Une zone de saisie et le choix de l'« environnement » (le dépôt) | Liste des agents et zone pour en lancer un nouveau, sur un dépôt | Une zone de saisie unique pour toutes les tâches |
| Décrire le projet | Une phrase suffit, rien d'autre à remplir | Une phrase suffit ; trois modes : **construire**, **discuter** (sans toucher au code, moins cher) et **planifier** | Une demande en langage courant | Une demande, éventuellement plusieurs en parallèle | Une demande | Une demande |
| Discussion avec l'agent | Le fil à gauche, le résultat à droite ; le travail s'affiche pas à pas | Fil de discussion, plan proposé avant de construire en mode « planifier » | Fil de discussion, actions de l'agent repliables | Journal du travail, puis les différences du code | Fil et journal de l'agent | Étapes affichées une à une |
| Aperçu | Aperçu en direct à côté du fil | Aperçu en direct ; **sélection d'un élément dans l'aperçu** pour demander une modification | Pas d'aperçu de site : on regarde le code et la pull request | Différences du code | Différences du code | Aperçu du site, publication en un geste |
| Historique des versions | Versions enregistrées toutes seules, avec aperçu, nom et **restauration** | Historique par l'icône d'horloge : aperçu d'une version puis **restaurer** (la version actuelle n'est pas perdue) | Les branches et les commits de Git | Les branches et les commits de Git | Points de retour en arrière dans l'éditeur sur ordinateur | Points de sauvegarde (« checkpoints ») ; la publication prend le dernier |
| Retour en arrière | Bouton dans l'historique ou dans le fil | Bouton « restaurer » | Revenir à un commit | Revenir à un commit | Dans l'éditeur sur ordinateur seulement | Retour à un point de sauvegarde |
| Connexion GitHub | Importer un dépôt existant en nouveau projet ; créer un dépôt depuis un projet | **Application GitHub** ; crée un dépôt neuf (pas d'import d'un dépôt existant) ; synchronisation dans les deux sens sur la branche principale | **Application GitHub de Claude** ; on choisit le dépôt et la branche **avant** d'écrire ; l'agent pousse sur sa propre branche et propose la pull request | Connexion du compte GitHub ; pull request à partir du travail | Pull request ouverte toute seule ; revue et fusion depuis le web | Export qui crée un dépôt privé, puis synchronisation dans les deux sens sur la branche principale |
| Publication | Publication en un geste | Publication en un geste | Pas de publication (c'est le travail du dépôt) | Pas de publication | Pas de publication | Publication en un geste, et publication automatique de chaque version acceptée ; si un travail échoue, le site en ligne reste sur la dernière version stable |
| Messages d'attente | Étapes en direct | Étapes en direct, plan d'abord | Actions en direct, notification à la fin | Notification à la fin | **Notification à la fin**, sur le téléphone | Étapes en direct |
| Erreurs | Bouton pour demander à l'agent de corriger l'erreur affichée | Bouton « corriger » sur l'erreur | L'agent lit l'erreur et corrige | Idem | Idem | Idem |

Sources principales : [synchronisation Git de Lovable](https://docs.lovable.dev/integrations/git-sync-overview), [modes de discussion de Lovable](https://docs.lovable.dev/features/chats), [barre d'outils de l'aperçu de Lovable](https://docs.lovable.dev/features/preview-toolbar), [historique des versions de Bolt](https://support.bolt.new/concepts/version-history-github), [retour en arrière de Bolt](https://support.bolt.new/building/using-bolt/rollback-backup), [GitHub dans Bolt](https://support.bolt.new/integrations/git), [Claude Code sur le web](https://support.claude.com/en/articles/12618689-claude-code-on-the-web), [Codex sur le web](https://developers.openai.com/codex/cloud), [Cursor sur le web et le téléphone](https://docs.cursor.com/background-agent/web-and-mobile), [GitHub dans Manus](https://manus.im/docs/website-builder/github-integration), [publication automatique de Manus](https://manus.im/blog/manus-auto-publish). Plusieurs détails viennent de guides non officiels : les noms exacts des boutons peuvent changer.

**Ce que font tous les bons outils :** une seule zone pour commencer ; aucune question technique avant de commencer ; un espace de travail où la discussion et l'aperçu sont toujours à un geste ; un historique des versions avec retour en arrière ; GitHub par une application GitHub limitée aux dépôts choisis ; des messages d'attente vivants et une notification à la fin ; l'erreur accompagnée d'un bouton « corriger ».

### 23.2 Kekeli aujourd'hui, écran par écran

| Écran | Aujourd'hui | Ce qui fait « amateur » |
|---|---|---|
| Accueil public (`/`) | Titre, phrase, trois étapes, boutons « Créer mon compte gratuit » et « Voir les modèles de sites », badge « Version d'essai en construction » | Pas de zone pour décrire son site ; le visiteur doit d'abord créer un compte sans avoir rien essayé ; le badge « en construction » inquiète |
| Inscription et connexion | Pages standard du kit Laravel, en français | Correctes ; la description tapée avant l'inscription n'est pas gardée (il n'y en a pas) |
| Mes projets | Titre, trois boutons (« Nouveau site », « Partir d'un modèle », « Ouvrir un dépôt GitHub »), liste des projets | Trois portes d'entrée concurrentes pour un débutant ; pas de zone « Décrivez votre site web » |
| Nouveau site | Formulaire long : nom obligatoire, description, « Kekeli a compris », trois choix de type de site, technologie, avertissement sur l'énergie, encadré « Partir d'un modèle — Moins d'énergie » | Trop de questions avant de commencer ; jargon (Laravel, MySQL, Next.js) ; le nom peut être déduit de la description |
| Galerie de modèles (12 sites vitrines et 5 sites avec base de données) | Pages des modèles, six questions une à une, refus de publier tant qu'il reste du contenu d'exemple | Jugée médiocre par le porteur du projet ; questionnaire lourd ; à retirer |
| Page du projet | Sur le téléphone, de haut en bas : nom et état, liens « Mes photos », « Sites autorisés », « Comptes rendus », « Mon code », carte de publication (ou carte « Site avec base de données » avec le compte d'essai et son mot de passe), puis les onglets « Discussion » et « Aperçu », puis le fil, puis la zone de saisie | La discussion commence sous le premier écran ; réglages techniques (« Sites autorisés ») mis au même niveau que l'essentiel ; compte d'essai et technologie affichés en grand ; la zone de saisie n'est pas collée en bas de l'écran |
| Pendant le travail | Phrases courtes en français, actions repliées, page rafraîchie toutes les 2 à 4 secondes | Pas de plan annoncé avant de construire ; pas de notification à la fin ; le client doit garder la page ouverte |
| Aperçu | Cadre de l'aperçu avec « Recharger » et « Ouvrir » ; pages françaises quand le site démarre ou a un problème | Pas de choix téléphone ou ordinateur ; impossible de toucher un élément pour le modifier ; pas de bouton « Corriger cette erreur » |
| Compte rendu et modifications | Compte rendu en cinq titres, « Voir les modifications », « Copier », partage WhatsApp, historique des comptes rendus | Bon niveau ; mais **aucun retour en arrière possible** : une seule sauvegarde par projet, remplacée à chaque demande |
| Publication | « Publier mon site » et « Publier à nouveau » (site vitrine), guide de mise en ligne (site avec base de données) | Correcte ; à intégrer dans la barre du haut, comme les autres outils |
| Mon code et GitHub | ZIP gratuit, « Connecter GitHub » par une application OAuth qui demande l'accès à **tous** les dépôts du compte, envoi en une fois | Accès trop large (les clients prudents refuseront) ; pas de synchronisation : chaque envoi est une copie isolée |
| Ouvrir un dépôt GitHub (mode développeur) | Page séparée, choix d'un dépôt, branche `kekeli/…`, pull request, surveillance de la vérification | Le moteur est bon, mais c'est un parcours à part : on ne peut pas construire un **nouveau** site directement dans un dépôt |
| Énergie et limites | Barre d'énergie, messages « l'énergie qui se recharge », bouton « Continuer » | Bon niveau, propre à Kekeli |
| Erreurs | Messages en français, détails techniques repliés | Pas de bouton « Corriger » ; certaines erreurs ne disent pas quoi faire ensuite |

### 23.3 Les 10 points qui font le plus « amateur »

1. **Pas de zone « Décrivez votre site web » au premier écran** (ni sur l'accueil public, ni sur « Mes projets »), alors que c'est le cœur de Bolt.new et de Lovable.
2. **Formulaire « Nouveau site » trop long et technique** : nom obligatoire, type de site, technologie, avertissements.
3. **La galerie de modèles** et son questionnaire en six questions.
4. **Page du projet mal organisée sur le téléphone** : la discussion et la zone de saisie sont sous plusieurs cartes ; les réglages sont mélangés à l'essentiel.
5. **Aucun historique des versions et aucun retour en arrière.**
6. **GitHub en accès à tous les dépôts, sans synchronisation**, et deux parcours séparés (envoi du code, mode développeur).
7. **Détails techniques montrés au client** : « Laravel (PHP) et MySQL », compte d'essai et mot de passe en haut de page, « Sites autorisés » à côté de « Mon code ».
8. **Pas de plan annoncé avant de construire, pas de notification à la fin** : il faut rester sur la page.
9. **Chaque message lance un travail complet** : pas de mode « Discuter » pour poser une question sans dépenser d'énergie.
10. **Pas de pièce jointe dans la zone de saisie, pas de bouton « Corriger cette erreur », pas de sélection d'un élément dans l'aperçu** ; plus le badge « Version d'essai en construction » sur l'accueil.

### 23.4 Changements proposés, du plus important au moins important

Les coûts sont des **estimations**. Tout reste gratuit, sauf la clé Claude prévue en fin de projet.

| Rang | Changement | Ce que le client verra | Difficulté | Coût | Supprimé ou remplacé |
|---|---|---|---|---|---|
| 1 | **Zone « Décrivez votre site web »** sur l'accueil public et sur « Mes projets » | Une grande zone au centre, avec des exemples de phrases à toucher, un bouton « + » (photos, document) et un bouton « Construire ». Sans compte, la description est gardée pendant l'inscription, puis Kekeli commence tout seul | Moyenne | Gratuit | Remplace le formulaire « Nouveau site » (nom, type, technologie), les trois boutons de « Mes projets » et le badge « en construction ». Le nom du site est déduit de la description, modifiable ensuite. Le type de site reste détecté tout seul (`SiteNeeds`) ; le choix manuel passe dans un petit menu « Options » |
| 2 | **Retrait de la galerie** | Plus de « Partir d'un modèle » nulle part | Petite | Gratuit | Supprime les pages et les routes `/modeles` (renvoyées vers l'accueil), `TemplateGallery`, les questions une à une, la vérification du contenu d'exemple, les 12 modèles de sites vitrines (7,3 Mo) et les 5 modèles de la galerie « Sites avec base de données » (**à confirmer** ; le socle Laravel et Next.js est gardé, il sert à construire tout site avec base de données) |
| 3 | **Espace de travail du projet refait pour le téléphone** | En haut, une barre fine : nom du site, bouton « Aperçu » ou « Discussion », bouton « Publier », menu « ⋯ ». La zone de saisie reste collée en bas de l'écran. Sur ordinateur, la discussion à gauche et l'aperçu à droite | Moyenne | Gratuit | Remplace la rangée de liens et les grandes cartes du haut. « Mon code », « GitHub », « Comptes rendus », « Sites autorisés » et le compte d'essai passent dans le menu « ⋯ » ; la technologie n'est plus affichée en grand |
| 4 | **Historique des versions et retour en arrière** | Une icône d'horloge : la liste des versions (« Version 3 — Ajout du menu, il y a 2 heures »), l'aperçu d'une version, puis « Revenir à cette version » (la version actuelle reste dans la liste) | Moyenne | Gratuit (stockage de quelques Mo par projet, estimation) | Remplace la sauvegarde unique : une sauvegarde par demande réussie, les 20 dernières gardées (réglable) |
| 5 | **Application GitHub de Kekeli** (accès limité aux dépôts choisis) | Une page GitHub officielle « Installer Kekeli » où le client choisit « Seulement les dépôts choisis » | Moyenne | Gratuit | Remplace l'application OAuth qui demande le droit « repo » sur tous les dépôts |
| 6 | **Parcours « site d'abord »** : relier un projet à GitHub | Menu « ⋯ », puis « GitHub », puis « Relier à un nouveau dépôt privé ». Ensuite, chaque version devient un commit, et les changements faits sur GitHub sont repris avant la demande suivante | Moyenne | Gratuit | Remplace l'envoi en une fois (`ExportToGithub`) |
| 7 | **Parcours « dépôt d'abord »** | Sous la zone « Décrivez votre site web », un bouton « GitHub » : « Nouveau dépôt » ou « Dépôt existant » et sa branche, puis Kekeli construit dedans | Grande | Gratuit | Remplace la page séparée « Ouvrir un dépôt GitHub » ; le moteur du mode développeur est gardé |
| 8 | **Plan avant de construire, et notification à la fin** | « Voici ce que je vais faire » (trois à six points) avant un grand travail ; à la fin, une notification sur le téléphone (navigateur) et un courriel si le client a quitté la page | Moyenne | Gratuit (notifications du navigateur ; courriel par une offre gratuite à choisir, estimation) | Rien de supprimé |
| 9 | **Mode « Discuter »** à côté de « Construire » | Un choix dans la zone de saisie : « Discuter » pour poser une question sans modifier le site, avec moins d'énergie | Petite | Gratuit | Rien de supprimé |
| 10 | **Bouton « Corriger cette erreur »** | Quand l'aperçu affiche un problème ou qu'une vérification échoue, un bouton qui envoie l'erreur à Kekeli | Petite | Gratuit | Rien de supprimé |
| 11 | **Aperçu téléphone ou ordinateur** | Deux petites icônes au-dessus de l'aperçu | Petite | Gratuit | Rien de supprimé |
| 12 | **Sélection d'un élément dans l'aperçu** | « Toucher un élément » dans l'aperçu, puis écrire « change ce titre » ; Kekeli sait de quel élément il s'agit | Grande | Gratuit | Rien de supprimé |
| 13 | **Identité visuelle soignée** (logo, typographie, couleurs, état vide, chargements) | Des écrans plus nets, cohérents d'une page à l'autre | Moyenne | Gratuit | Remplace des réglages par défaut du kit Flux |

### 23.5 GitHub : les deux parcours

**À créer une seule fois du côté du porteur du projet : l'application GitHub de Kekeli** (gratuite). Elle se crée après la mise en ligne, car GitHub doit connaître l'adresse publique du site.

Gestes sur le téléphone, dans Chrome :
1. Ouvrir **github.com**, se connecter, puis activer « Version pour ordinateur » (menu ⋮ de Chrome), car l'application GitHub ne montre pas ces réglages.
2. Toucher la photo du profil, puis **Settings**, puis tout en bas **Developer settings**, puis **GitHub Apps**, puis **New GitHub App**.
3. **GitHub App name** : « Kekeli » (ou « Kekeli Lome » si le nom est pris). **Homepage URL** : l'adresse du site Kekeli. **Callback URL** : l'adresse du site suivie de `/github/retour`. Cocher **Request user authorization (OAuth) during installation**.
4. **Webhook** : décocher « Active » au départ (Kekeli lit lui-même l'état des vérifications).
5. **Repository permissions** :
   - « Contents » : Read and write ;
   - « Pull requests » : Read and write ;
   - « Checks » et « Actions » : Read-only ;
   - « Metadata » : Read-only ;
   - si GitHub le propose, la nouvelle permission de création de dépôt (« Repository creation ») ;
   - **rien d'autre**.
6. **Where can this GitHub App be installed?** : « Any account ». Toucher **Create GitHub App**.
7. Sur la page de l'application, noter l'**App ID** et le **Client ID**, puis :
   - toucher **Generate a new client secret** ;
   - toucher **Generate a private key** (un fichier `.pem` se télécharge).
8. Placer ces valeurs dans les variables d'environnement de Laravel Cloud (noms exacts donnés dans la pull request concernée). **Jamais dans un message, jamais dans le dépôt.** Cacher toute clé avant une capture.

**Parcours A, « dépôt d'abord »** (comme Claude Code sur le web) :
1. Sur l'accueil, le client touche **GitHub** sous la zone « Décrivez votre site web ».
2. La première fois, il touche **Connecter GitHub**. GitHub affiche « Installer Kekeli ». Il choisit **Seulement les dépôts choisis** et coche ses dépôts (il peut aussi tout autoriser), puis revient dans Kekeli.
3. Kekeli propose :
   - **Nouveau dépôt** : nom proposé d'après la description, privé par défaut ;
   - **un de ses dépôts**, avec sa branche.
4. Il décrit son site et touche **Construire**.
   - Dépôt neuf : Kekeli construit et envoie chaque version comme un commit sur la branche principale, comme Lovable.
   - Dépôt qui contient déjà du code : Kekeli travaille sur sa branche `kekeli/…` et ouvre une pull request ; il surveille la vérification de GitHub, la corrige deux fois au plus, et **ne fusionne que si le client touche « Fusionner »**. C'est le mode développeur actuel.
5. Si GitHub refuse que Kekeli crée lui-même un dépôt dans un compte personnel (**point à vérifier pour de vrai**), Kekeli explique en trois gestes comment créer un dépôt vide sur GitHub, puis le client le choisit.

**Parcours B, « site d'abord »** (comme Lovable et Manus) :
1. Le client crée son site avec la zone « Décrivez votre site web », sans GitHub.
2. Quand il le veut, il touche **⋯**, puis **GitHub**, puis **Relier à un dépôt GitHub** (avec l'installation de l'application la première fois).
3. Kekeli propose **Créer un dépôt privé** (nom tiré du site, par exemple `restaurant-le-palmier`) ou un dépôt vide existant.
4. Kekeli y envoie la version actuelle. Ensuite, **chaque demande réussie devient un commit**, avec le titre du compte rendu.
5. Avant chaque demande, Kekeli reprend les changements faits directement sur GitHub : la synchronisation marche dans les deux sens, sur la branche principale.
6. Un bouton **Délier** arrête la synchronisation, sans effacer le dépôt.

Dans les deux cas : le ZIP reste gratuit ; la clé GitHub du client ne quitte jamais le site (règle actuelle) ; l'aperçu, la publication sur `pages.dev` et les guides de mise en ligne ne changent pas.

### 23.6 Ce qui dépend du modèle d'IA, et ce qui dépend de l'interface

- **Dépend du modèle** (gratuit aujourd'hui : Gemini Flash-Lite surtout) :
  - le goût du design et la richesse des pages ;
  - la justesse du code et le nombre de corrections nécessaires ;
  - le respect des consignes ;
  - la capacité à tenir une longue tâche (site avec base de données) ;
  - la vitesse.

  Mesures réelles : un site vitrine réussi en 2 à 12 minutes selon le modèle ; Gemini 3.1 a laissé un menu vide ; les meilleurs résultats gratuits venaient de modèles qui ne sont pas permis pour des clients (Nemotron, North). **Aucun changement d'interface ne rendra un modèle faible plus intelligent.**
- **Dépend de l'interface et du parcours, donc de nous** :
  - le premier écran ;
  - le nombre de questions avant de commencer ;
  - la place de la discussion et de l'aperçu sur le téléphone ;
  - les versions et le retour en arrière ;
  - GitHub ;
  - les messages d'attente et les notifications ;
  - les boutons « Corriger » ;
  - la clarté des erreurs ;
  - l'identité visuelle.

  **C'est là que se trouve l'essentiel de l'impression « amateur » aujourd'hui**, et tout est gratuit à corriger.
- **Ce que la clé Claude changerait** (fin du projet, règle numéro 8) : un design et un code nettement meilleurs du premier coup, moins de corrections, des sites avec base de données plus fiables, moins de pauses (les limites gratuites disparaissent pour les abonnés payants). Le coût par site sera **mesuré à ce moment-là**, comme prévu. Repère mesuré aujourd'hui : un site vitrine consomme environ 0,7 million de tokens en entrée et 15 000 en sortie (essai du 11 novembre 2026). Les modèles gratuits resteraient pour la formule gratuite.

### 23.7 Découpage en petites pull requests, une à la fois

Chaque pull request arrive avec ses tests, ses captures sur un téléphone simulé et l'accord du porteur du projet avant la suivante.

| Ordre | Pull request | Contenu | Dépend de |
|---|---|---|---|
| 1 | Cette analyse | `docs/plan.md` et `REPRISE.md` seulement | — |
| 2 | Zone « Décrivez votre site web » | Accueil public et « Mes projets » avec la zone unique, exemples, pièces jointes, description gardée pendant l'inscription, nom déduit, options repliées ; ancien formulaire supprimé | Accord sur cette analyse |
| 3 | Retrait de la galerie | Pages, routes renvoyées vers l'accueil, données des modèles, code, tests et documents | 2 |
| 4 | Espace de travail du projet | Barre du haut, menu « ⋯ », zone de saisie collée en bas, discussion et aperçu côte à côte sur ordinateur, aperçu téléphone ou ordinateur | 2 |
| 5 | Versions et retour en arrière | Une sauvegarde par demande, liste des versions, aperçu d'une version, « Revenir à cette version » | 4 |
| 6 | Application GitHub | Connexion par l'application GitHub (dépôts choisis), remplacement de l'application OAuth ; création de l'application par le porteur du projet (gestes ci-dessus) | Mise en ligne sur Laravel Cloud |
| 7 | GitHub « site d'abord » | Relier un projet à un dépôt, un commit par version, reprise des changements faits sur GitHub, « Délier » | 5 et 6 |
| 8 | GitHub « dépôt d'abord » | Bouton « GitHub » sous la zone, nouveau dépôt ou dépôt existant avec sa branche ; la page « Ouvrir un dépôt GitHub » disparaît | 6 et 7 |
| 9 | Plan, notifications et « Corriger cette erreur » | Plan avant un grand travail, notification à la fin, bouton « Corriger » | 4 |
| 10 | Mode « Discuter » | Questions sans modifier le site, moins d'énergie | 4 |
| 11 | Identité visuelle | Logo, typographie, états vides et chargements | 4 |
| 12 | Sélection d'un élément dans l'aperçu | Toucher un élément pour le modifier | 4 |

**Questions au porteur du projet avant de commencer :**
1. Valider cet ordre.
2. Confirmer que les 5 modèles de la galerie « Sites avec base de données » disparaissent aussi (le socle Laravel et Next.js reste).
3. Dire si la mise en ligne sur Laravel Cloud se fait avant la pull request numéro 6 : l'application GitHub en a besoin.

### 23.8 Réalisé : pull requests 2 et 3 réunies (11 novembre 2026)

Décisions du porteur du projet : analyse acceptée ; pull requests 2 et 3 faites ensemble (même écran) ; **toute la galerie part**, y compris les 5 modèles « Sites avec base de données » (le socle Laravel et Next.js reste) ; mise en ligne sur Laravel Cloud **après la pull request 5 et avant la pull request 6**.

**Ce que le client voit maintenant** (captures : `docs/captures/premier-ecran.md`) :
- sur l'accueil et en haut de « Mes projets », une seule question en grand, **« Que voulez-vous créer aujourd’hui ? »**, et une seule zone de saisie, avec le texte grisé **« Demandez à Kekeli de créer un site web pour… »** ;
- des idées à toucher sous la zone (« mon restaurant », « mon école », « ma boutique », « mon salon de coiffure »), qui complètent la phrase ; un bouton photo pour joindre ses photos (avec un compte) ; un bouton rond pour envoyer ;
- sans compte, le texte est **gardé pendant l'inscription** (« Votre idée est gardée »), puis Kekeli commence tout seul ;
- **Kekeli devine seul le nom du site** (par exemple « Restaurant Le Palmier », sinon « Mon restaurant », modifiable ensuite), **le type de site et la technologie**, sans rien demander ni montrer ;
- sur la page du projet, plus de « Site avec base de données · Laravel (PHP) et MySQL » ; « Sites autorisés » est rangé dans le menu « ⋯ » sous le nom « Accès à Internet de Kekeli » ; le champ de discussion propose « Demandez une modification à Kekeli… » ;
- plus de badge « Version d'essai en construction », plus de lien « Modèles de sites » ni de bouton « Partir d'un modèle ».

**Supprimé** :
- la galerie : pages, adresses (renvoyées vers l'accueil), `TemplateGallery`, `TemplatePreviewController`, questions une à une, vérification du contenu d'exemple, 12 modèles de sites vitrines (`resources/modeles-sites`, 7,3 Mo), 5 modèles de sites avec base de données dans le Runner (`modeles.json` ne garde que le socle), images, scripts de fabrication et d'essai, colonnes `projects.template`, `template_answers` et `template_step` (migration) ;
- le formulaire « Nouveau site » (nom, type de site, technologie, « Kekeli a compris ») et son adresse.

**Formulation** : la question et le texte grisé du porteur du projet sont gardés tels quels dans le code. Les étapes de l'accueil sont écrites sans impératif (« Vous partagez votre idée », « Kekeli le construit », « Vous le voyez en direct »).

**Suivant** : pull request 4, l'espace de travail du projet pour le téléphone (partie 23.7).

### 23.9 Pull request 4 : où chaque chose est placée (étude du 11 novembre 2026, écrite avant de coder)

Le design (couleurs, identité) viendra avec la pull request 11 : ici, seulement la **place** de chaque élément. Captures AVANT et APRÈS : `docs/captures/organisation.md`.

**Comment les outils placent les éléments** (documentation officielle et guides lus le 11 novembre 2026 : [éditeur de Lovable](https://docs.lovable.dev/features/projects/editor), [application mobile de Lovable](https://docs.lovable.dev/integrations/lovable-mobile-app), [vue du code de Bolt](https://support.bolt.new/building/using-bolt/code-view), [Claude Code sur le web](https://code.claude.com/docs/en/web-quickstart), [Cursor sur le web et le téléphone](https://docs.cursor.com/background-agent/web-and-mobile)) :

| Élément | Les meilleurs outils | Kekeli avant | Kekeli après (cette pull request) |
|---|---|---|---|
| Barre du haut | Une barre fine : nom du projet (qui ouvre un petit menu : crédits, réglages, renommer), onglets Aperçu et Code, bouton « Publish » à droite (Lovable, Bolt) | Grand titre, badge, rangée de liens (« Mes photos », « Comptes rendus », « Mon code », « ⋯ »), puis une grande carte de publication | Une barre fine : retour, nom du site, petit état, « Mettre en ligne » à droite, menu « ⋯ » |
| Discussion et aperçu | Ordinateur : discussion à gauche, aperçu ou code à droite. Téléphone : on glisse entre la discussion et l'aperçu (application Lovable) ; un seul panneau à la fois | Deux onglets sous de grandes cartes ; sur ordinateur, la discussion commence sous la carte de publication | Téléphone : trois vues (Discussion, Aperçu, Code) juste sous la barre ; ordinateur : discussion à gauche, Aperçu ou Code à droite, sur toute la hauteur |
| Code | Un onglet « Code » à côté de l'aperçu (Lovable, Bolt), lecture seule sur téléphone (Bolt) ; les modifications de chaque demande (Claude Code, Codex) | « Mon code » deux fois (lien et bouton), dans une fenêtre | Un onglet « Code » : les fichiers du site (lecture seule), le fichier ZIP et GitHub |
| Travail de l'agent | Étapes affichées une à une dans la discussion, repliables (Manus, Claude Code) ; aucune page à part | Phrases et actions dans une carte grise ; une page « Comptes rendus » à part | Étapes cochées dans la discussion, chacune dépliable ; le compte rendu final est le dernier message de Kekeli ; plus de page « Comptes rendus » (l'ancienne adresse renvoie vers le projet) |
| Pièces jointes | Bouton « + » dans la zone de saisie ; les fichiers apparaissent dans le message envoyé (Lovable, Claude Code) | Une fenêtre « Mes photos » à part | Bouton photo dans la zone de saisie ; les photos apparaissent dans le message, dans la discussion |
| Zone de saisie | La même partout, collée en bas, avec les boutons à l'intérieur | Un champ différent de l'accueil, avec un avion en papier | La même zone que l'accueil (bouton rond orange avec une flèche, bouton photo), collée en bas |
| Publication | Bouton « Publish » en haut à droite, qui ouvre une petite fenêtre avec l'adresse et les réglages | Grande carte en haut de page, toujours visible | Bouton « Mettre en ligne » ; les explications, l'adresse, la publication et le nom de domaine s'affichent seulement après l'avoir touché |
| GitHub | Pastille du dépôt sous la zone de saisie (Claude Code) ; bouton GitHub dans la barre (Lovable) | Lien gris « Ouvrir un dépôt GitHub » ; envoi dans la fenêtre « Mon code » | Pastille « GitHub » dans la zone de saisie de l'accueil et de « Mes projets » ; l'envoi du code est dans l'onglet « Code » |
| Crédits | Discrets : dans le menu du nom du projet ou du compte (Lovable) | Une barre « Énergie » au-dessus de la zone de saisie | Un petit indicateur (soleil et pourcentage) dans la zone de saisie ; le détail dans le menu du compte |
| Attente | Une ligne calme dans la discussion | Gros texte orange « Beaucoup de personnes… » | Une petite ligne grise, avec la position dans la file |
| Réglages | Dans un menu (nom du projet ou « ⋯ ») | Menu « ⋯ » avec un seul réglage | Menu « ⋯ » : accès à Internet de Kekeli, renommer, compte |
| Erreurs | Ton aimable, couleur douce, jamais de message technique | Rouge vif, certaines phrases à l'impératif | Couleur douce, ton aimable, jamais de message technique ni anglais |

**Accueil** : Bolt.new et Lovable ne mettent presque rien sous la zone de saisie (des idées, puis les projets de la personne connectée). Les trois grandes cartes « Vous partagez votre idée / Kekeli le construit / Vous le voyez » deviennent une ligne courte de trois points, plus discrète.

**Réalisé (pull request 4, 11 novembre 2026)** : tout le tableau ci-dessus, plus :
- un espace de travail en plein écran pour le projet (`components/layouts/workspace.blade.php`), sans menu latéral ; vue demandable dans l'adresse (`?vue=apercu`, `?vue=code`) ;
- la zone de saisie commune `x-prompt-box` (accueil, « Mes projets », projet) ; les photos jointes sont rangées avec leur message (colonne `project_photos.message_id`) ;
- l'onglet « Code » lit les fichiers de la dernière sauvegarde, sans les secrets (`ProjectFiles`), seulement quand on l'ouvre ;
- renommer le site depuis le menu « ⋯ » ;
- pages de compte sans phrases à l'impératif (titres seuls), erreurs de connexion aimables ; erreurs en orangé doux (`.kekeli-erreur`, `[data-flux-error]`) ; plus de message anglais de GitHub ; formule gratuite affichée « 0 FCFA » ;
- les anciens scripts de captures des étapes 2 à 8 visent les anciens écrans : gardés comme trace.

**Reste à améliorer, en dehors du design** :
1. Versions et retour en arrière (pull request 5).
2. Plan annoncé avant un grand travail, notification à la fin, bouton « Corriger cette erreur » (pull request 9).
3. Mode « Discuter » (pull request 10).
4. Aperçu en format téléphone ou ordinateur, et sélection d'un élément dans l'aperçu (pull requests 11 et 12).
5. Mise à jour en direct : la page se rafraîchit toutes les 2 secondes pendant le travail ; un vrai flux en direct (comme Claude Code) viendra plus tard.
6. Le compte rendu final reste long sur un téléphone : on pourra replier ses parties.
7. L'onglet « Code » est en lecture seule, sans couleurs ni recherche.
8. Une photo déjà envoyée ne peut plus être retirée (comme chez Lovable).

### 23.10 Nettoyage professionnel (11 novembre 2026)

**Règle du porteur du projet** : Kekeli devient un outil international, pour tout le monde, débutants compris. Si Claude Code (web), Lovable ou Bolt.new n'ont pas un élément, ou le montrent autrement, on l'enlève ou on fait comme eux. Claude tranche seul. Captures avant et après : `docs/captures/nettoyage.md`.

**Messages de Kekeli dans la discussion** (comme Lovable et Claude Code) :
- le dernier message est court et en langage simple (« Votre site est prêt : le menu avec les prix, les horaires, le plan et le bouton WhatsApp pour réserver. ») ; le compte rendu en cinq parties est supprimé (consignes de l'agent dans `runner/src/ordinateurs.js`, rappel `RAPPEL_COMPTE_RENDU`) ;
- `RunReport::plain` retire tout ce qu'un client ne doit pas lire, même dans les anciennes réponses : espaces insécables, emoji, titres, phrases avec un nom de fichier, une commande, une adresse ou un code d'état (« code 200 »), lignes en double ; si rien ne reste, « C'est fait : votre site est à jour. » ;
- plus de rubrique « À vérifier de votre côté » : s'il faut dire quelque chose, une phrase polie (consigne de l'agent), et une phrase de Kekeli quand une partie n'a pas pu être terminée ;
- les actions restent repliées dans « Kekeli a fait X actions », avec des libellés simples (« Fichier créé », « Travail technique », « Droits des images vérifiés ») : plus de nom de fichier ni de commande, même replié ; une phrase dite deux fois n'apparaît qu'une fois ; pendant le travail, la phrase en cours s'affiche à côté de l'animation ;
- les fichiers modifiés n'apparaissent que dans l'onglet Code (« Dernières modifications », chaque fichier ouvre ses modifications) : « Voir les modifications », « Copier le compte rendu » et « Partager sur WhatsApp » sont supprimés ;
- plus aucun emoji dans les messages système (attente, énergie, formules, GitHub, page « Votre site redémarre » de l'aperçu).

**Aperçu** : il se rallume tout seul quand il devient visible (onglet Aperçu sur le téléphone, panneau de droite sur l'ordinateur), avec une animation « Chargement de l'aperçu… » ; plus d'écran « L'aperçu est en pause » ni de bouton, plus de phrase sur l'heure de la pause. Seul le propriétaire le rallume.

**Fonctions « bientôt disponible » cachées** : envoi vers GitHub (caché tant que l'application GitHub n'est pas réglée), message « Les formules payantes sont en préparation ».

**Écran par écran** :

| Écran | Changement | Raison |
|---|---|---|
| Accueil public | Gardés : la question, la zone de saisie (avec la pastille GitHub), les idées à toucher ; retirés : le sous-titre, les trois étapes, « Kekeli veut dire la lumière », la phrase sur Lomé IA School ; pied de page réduit aux deux liens juridiques | Bolt.new et Lovable ne montrent que la zone de saisie et des idées |
| Inscription | Plus de « Confirmer le mot de passe » ni d'explication sous le pays | Lovable et Bolt demandent le minimum |
| Connexion | Plus de case « Se souvenir de moi » (la session est gardée) | Lovable et Bolt n'en ont pas |
| Mes projets | Cartes : nom, état et date sur une ligne ; plus de description répétée | Lovable affiche le nom et la date |
| Page du projet | Énergie retirée de la zone de saisie, rangée dans le menu « ⋯ » ; « Mon compte » et « Mes projets » retirés du menu « ⋯ » (déjà dans la flèche de retour et le menu du compte) ; attente en une ligne sans icône en double ; plus d'explication sous « La conversation commencera ici » | Lovable range les crédits dans le menu du projet |
| Code | Une barre « Télécharger » (et GitHub quand il fonctionne), « Dernières modifications », puis les fichiers ; plus de cartes « Gratuit, pour toutes les formules » ni de tailles en octets | Bolt et Lovable : un onglet Code sobre |
| Mettre en ligne | Titre, adresse, bouton « Publier mon site » ou « Mettre à jour » ; nom de domaine en une ligne avec « Formules payantes » ; plus de sous-titres ni de phrase sur le délai | Fenêtre « Publish » de Lovable |
| Formules | Une seule phrase sur la recharge ; formule actuelle marquée sur sa carte ; plus de carte « Votre formule » en double ni de phrase répétée sur chaque carte | Pages de prix de Lovable et Bolt |
| Paiement | « Aucune référence à taper » retiré | Texte inutile |
| Réglages | Sous-titres retirés (profil, mot de passe, apparence, en-tête) | Lovable affiche seulement les titres |
| Guide de mise en ligne | Sous-titre et note de fin retirés ; « Mon code » devient l'onglet « Code » | Texte inutile, ancien nom |

**Tests** : 228 tests du site et 147 du Runner ; tests adaptés aux nouveaux textes et nouveaux tests (aucun jargon, emoji ni espace insécable lu par le client ; fichiers modifiés seulement dans l'onglet Code ; aperçu qui se rallume tout seul ; GitHub caché tant qu'il ne fonctionne pas).

### 23.11 Nouvel ordre de la suite (décidé le 11 novembre 2026)

1. Ce nettoyage (fait).
2. La mise en ligne de Kekeli sur Laravel Cloud (`docs/mise-en-ligne.md`, solution A).
3. L'application GitHub, puis **les deux façons de travailler avec GitHub, aussi importantes l'une que l'autre et livrées ensemble** :
   - **dépôt d'abord** : le client crée son dépôt sur GitHub, le relie à Kekeli, et Kekeli pousse chaque changement dessus au fur et à mesure ;
   - **site d'abord** : le client crée son site dans Kekeli, puis le relie à GitHub quand il veut, et Kekeli pousse ensuite chaque changement dessus.
4. Ensuite seulement : les versions et le retour en arrière pour les sites sans GitHub (ancienne pull request 5), puis le reste (plan et notifications, fin du mode « Discuter » commencé le 12 novembre 2026 (partie 23.12), identité visuelle, sélection d'un élément dans l'aperçu).

Cet ordre remplace celui de la partie 23.7.

### 23.12 Publier et récupérer son site, conseils dans la discussion (12 novembre 2026)

**Demande du porteur du projet** : publier et récupérer son site comme Lovable, Bolt.new et Manus ; plus de long guide technique pour un site avec base de données ; conseils d'hébergement à la demande, dans la discussion. Captures avant et après : `docs/captures/publier.md`.

**Pour tous les sites** (comme Lovable et Bolt) : le client choisit librement de publier (site vitrine), de télécharger son code ou de le relier à GitHub. « Relier à GitHub » (`projects/partials/github-link.blade.php`) est prêt dans le code et **n'apparaît que lorsque l'accès à GitHub est réglé** (`GithubClient::configured`, dès que l'application GitHub sera configurée après la mise en ligne) ; sinon il reste caché, sans message « bientôt ». On le trouve dans la fenêtre du bouton du haut et dans l'onglet Code.

**Site vitrine** : « Mettre en ligne » en un geste, comme avant ; en plus, « Télécharger le code » et « Relier à GitHub » en bas de la fenêtre.

**Site avec base de données** : Kekeli ne l'héberge pas pour l'instant.
- Supprimés : la page du guide pas à pas (Laravel Cloud, Hostinger, cPanel, Render, Vercel), `DeployGuides`, le fichier ZIP pour l'hébergement mutualisé (`HostingPackage`, `BuildHostingPackage`, `RunnerPackageController`, adresse `POST /paquet` et `paquetHebergement` dans le Runner). L'ancienne adresse `/projets/{projet}/mise-en-ligne` renvoie vers le projet. Les colonnes `package_*` restent dans la base, inutilisées.
- Le bouton du haut devient « Récupérer le code » : une fenêtre courte, « Ce site a besoin d'un hébergeur avec base de données. Son code vous appartient : vous pouvez le confier à l'hébergeur de votre choix. », puis « Télécharger le code » et « Relier à GitHub » (dès qu'il fonctionne). Aucune promesse de publication.
- Le README et le fichier `.env.example` préparés par Kekeli restent dans le code ; la partie « Mise en ligne » du README explique maintenant en quelques lignes ce qu'il faut à la personne qui hébergera le site.

**Questions dans la discussion (mode « Discuter » de la pull request 10, en partie fait)** :
- `app/Agent/MessageIntent.php` reconnaît, sans requête au modèle, une **question** (« Où héberger mon site ? »), une **demande de modification** (« Ajoute… », « Peux-tu ajouter… ? ») ou un **doute** (« Faut-il changer la couleur ? ») ; une photo jointe est toujours une demande de modification.
- Une question ou un doute crée une demande `purpose = question` et la tâche `AnswerQuestion` : le Runner répond avec **une seule requête au modèle, sans ordinateur virtuel et sans toucher aux fichiers** (`runner/src/questions.js`, adresse signée `POST /question`, même liste de fournisseurs et même basculement). Requêtes, tokens et énergie sont comptés comme pour une demande ; une question ne prend aucune place dans la file d'attente.
- En cas de doute, Kekeli répond puis demande « Voulez-vous que je modifie votre site dans ce sens ? » ; pour une vraie question, cette phrase est retirée si le modèle l'ajoute quand même.
- Conseil d'hébergement selon le code du site (site vitrine, Laravel, Next.js) et le pays du client : 2 ou 3 choix honnêtes, ordre de grandeur du prix « à vérifier sur le site de l'hébergeur », aussi en FCFA ; aucun lien, aucune publicité, aucune affiliation.
- Énergie : environ 0,3 à 0,4 FCFA par question, aucun temps d'ordinateur (mesure réelle : `docs/couts.md`, dernière partie).
- Le texte grisé de la zone de saisie du projet devient « Une modification ou une question pour Kekeli… ».

**Pages juridiques** : conditions d'utilisation (sites avec base de données non publiés par Kekeli ; conseils indicatifs, sans commission) ; politique de confidentialité (les questions et le nom du pays partent aussi chez le fournisseur d'intelligence artificielle).

**Reste à faire pour le mode « Discuter »** : un bouton pour choisir soi-même « Discuter » ou « Modifier » (comme Lovable), des réponses qui lisent le contenu des fichiers (aujourd'hui seulement leur liste).

## 24. Risques et limites

1. **Offres gratuites très limitées et changeantes** : quelques dizaines de tâches de test par jour au mieux, même en additionnant les fournisseurs, et chacun peut changer ses limites ou supprimer son offre gratuite sans prévenir. Si elles deviennent trop basses, il faudra proposer un budget payant au porteur du projet plus tôt que prévu (jamais sans son accord).
2. **Le basculement change de cerveau en cours de route** : un modèle plus faible peut reprendre une tâche commencée par un modèle plus fort. Le résultat doit être revérifié sur les mêmes tâches de test pour chaque fournisseur.
3. **La qualité reste limitée par les modèles gratuits** : Gemini 3.5 Flash-Lite fait des sites propres, mais les modèles plus forts ne sont pas utilisables gratuitement ; la comparaison avec des modèles payants se fera à la fin du projet, quand la clé Claude sera achetée.
4. **Un agent multi-modèles ne se comporte pas pareil selon le modèle** : chaque changement de modèle devra être revérifié sur les mêmes tâches de test.
5. Le prix reste le premier risque au lancement : les limites et les prix seront fixés après les mesures de l'étape 5.
6. Données : aucune donnée réelle pendant la construction ; au lancement, offres gratuites réservées à la formule gratuite, avec une information claire dans la politique de confidentialité, et clés payantes pour les abonnés.
7. La validation manuelle des captures de paiement prend du temps et expose aux fausses captures : vérifier chaque paiement dans l'historique Mixx by Yas ou Flooz avant de valider.
8. Dépendance à plusieurs entreprises (Google, E2B, Laravel Cloud, Cloudflare, et le projet OpenCode) dont les prix et les règles peuvent changer ; le moteur multi-modèles réduit ce risque côté modèles.
9. Abus : modération nécessaire, suspension des comptes et des sites.
10. Les grosses tâches durent plusieurs minutes : le téléphone doit pouvoir être fermé puis rouvert sans rien perdre.
11. Les adresses `pages.dev` sont gratuites mais portent le nom de Cloudflare ; un nom de domaine personnalisé pour les clients pourra venir plus tard.
12. Une politique de confidentialité conforme à la loi togolaise sur la protection des données personnelles sera nécessaire avant le lancement.

## 25. Nouvelles formules (proposées et acceptées le 12 novembre 2026, codées : partie 25.7)

**Décisions du porteur du projet** : la formule gratuite reste, avec 30 FCFA d'énergie par jour ; les formules payantes commencent à 7 500 FCFA par mois ; Claude propose les formules au-dessus. Cette partie est une **proposition** : aucun code n'est modifié avant l'accord du porteur du projet. Tous les coûts sont des **estimations** calculées à partir des mesures de `docs/couts.md` (taux : 1 $ = 590 FCFA, 1 € = 655,957 FCFA).

### 25.1 Les formules d'aujourd'hui (publiées le 9 novembre 2026)

| Formule | Prix par mois | Énergie | Avantages |
|---|---|---|---|
| Gratuite | 0 FCFA | 30 FCFA par jour | Environ 1 petite page et 1 modification par jour ; code (ZIP et GitHub) gratuit ; plafond total de 1 500 FCFA par jour pour tous les utilisateurs gratuits |
| Découverte | 2 500 FCFA | 40 FCFA par jour | Un peu plus d'énergie ; nom de domaine à soi ; passe avant la formule gratuite |
| Pro | 7 500 FCFA | 130 FCFA par jour | Nom de domaine à soi ; passe avant la formule gratuite quand Kekeli est très sollicité |
| Entreprise | 25 000 FCFA | 450 FCFA par jour | Comme Pro, avec plus d'énergie |

L'énergie se recharge **chaque jour à minuit** (heure de Lomé) et se compte en **coût réel** (modèle d'IA et ordinateur au travail) : la marge reste la même quel que soit le modèle. Paiement par Mobile Money avec capture d'écran, formule active 30 jours.

**Limite constatée** : un site avec base de données coûte plus que l'énergie d'une journée de Découverte ou de Pro ; le travail s'arrête et reprend le lendemain, ce qui frustre un client qui paie.

### 25.2 Le coût réel d'un site pour Kekeli

**Mesures réelles avec Gemini 3.5 Flash-Lite** (essais du 8 au 11 novembre 2026, offre gratuite, compté au prix payant équivalent ; modèle et ordinateur au travail) :

| Demande | Mesures | Valeur retenue |
|---|---|---|
| Site vitrine (création) | 37,8 ; 41 ; 44,7 ; 59,8 ; 69 ; 75 FCFA | **environ 55 FCFA** (modèle 50, ordinateur 5) |
| Site avec base de données (création) | 73,6 ; 80,6 ; 135,5 ; 143,5 ; 167,1 FCFA | **environ 120 FCFA** (modèle 100, ordinateur 20) |
| Modification d'un site vitrine | 11,6 ; 17,8 ; 13 FCFA | **environ 18 FCFA** |
| Modification d'un site avec base de données | 25,1 à 52,7 FCFA | **environ 40 FCFA** |
| Question dans la discussion | 0,3 à 0,4 FCFA | **moins de 1 FCFA** |

**Aujourd'hui, la dépense réelle de Kekeli est presque nulle** : les modèles sont gratuits (offres gratuites) ; seul l'ordinateur E2B coûte, environ 5 FCFA par site vitrine et 20 FCFA par site avec base de données, plus environ **16 FCFA d'attente** (10 minutes avant la pause) après chaque séance de travail. Cette attente n'est pas comptée dans l'énergie du client : elle est à la charge de Kekeli.

**Avec la clé Claude (prévue à la fin du projet) : estimation, rien n'a été mesuré.** Prix officiels vérifiés le 12 novembre 2026, par million de tokens (entrée / sortie / lecture du cache) : **Claude Haiku 5.5** 0,10 $ / 0,50 $ / 0,01 $ ; **Claude Sonnet 5.5** 2 $ / 10 $ / 0,10 $ ; **Claude Opus 5.5** 4 $ / 20 $ / 0,20 $. L'écriture dans le cache coûte environ 1,25 fois le prix d'entrée. Méthode : les tokens mesurés avec Gemini (étape 5 : petite page, site de 5 pages, modification) sont recalculés aux prix de Claude ; Claude coûte alors environ **0,27 fois** Gemini 3.5 Flash-Lite avec Haiku 5.5, **5 fois** avec Sonnet 5.5 et **10 fois** avec Opus 5.5 pour la partie modèle. L'ordinateur ne change pas. L'**estimation prudente** multiplie la partie modèle par 1,5 : Claude réfléchit avant de répondre (tokens de réflexion payés comme des tokens écrits) et ne découpe pas le texte en tokens comme Gemini.

| Coût d'un site pour Kekeli (FCFA) | Site vitrine | Site avec base de données | Modification (site vitrine) |
|---|---|---|---|
| Gemini 3.5 Flash-Lite payant (mesuré) | 55 | 120 | 18 |
| Claude Haiku 5.5 (estimation / prudente) | 19 / 25 | 47 / 60 | 6 / 8 |
| **Mélange conseillé : 80 % Haiku 5.5, 20 % Sonnet 5.5** (estimation / prudente) | 66 / **100** | 142 / **200** | 22 / **35** |
| Claude Sonnet 5.5 seul (estimation / prudente) | 255 / 380 | 520 / 770 | 86 / 129 |
| Claude Opus 5.5 seul (estimation / prudente) | 505 / 755 | 1 020 / 1 520 | 171 / 256 |

**Ce que j'en retiens** :
- Le **mélange conseillé** (Haiku 5.5 pour les petites tâches, Sonnet 5.5 pour les tâches difficiles, comme prévu dans le cahier des charges) coûte à peu près **comme Gemini payant aujourd'hui, un peu plus en estimation prudente** : environ **100 FCFA (0,17 $) par site vitrine** et **200 FCFA (0,34 $) par site avec base de données**. C'est la base des équivalences ci-dessous.
- Sonnet 5.5 seul ou Opus 5.5 seul coûteraient 4 à 8 fois plus : à réserver aux formules hautes ou aux demandes difficiles.
- Ces chiffres seront **mesurés pour de vrai** quand la clé Claude sera achetée, puis la grille ajustée.
- **À corriger dans les réglages au moment de la clé Claude** (petit changement de code, pas maintenant) : la lecture du cache de Sonnet 5.5 est comptée à 0,20 $ au lieu de 0,10 $ ; l'écriture dans le cache (environ 1,25 fois le prix d'entrée) n'est pas encore comptée.

### 25.3 La grille proposée

Comme Lovable et Bolt.new : une formule gratuite avec une petite énergie **par jour**, et des formules payantes avec une énergie **par mois**. Un client payant peut alors construire un grand site d'une traite, sans attendre le lendemain ; le coût maximal du mois reste connu d'avance, donc la marge aussi. Le client voit un pourcentage et des équivalences simples, jamais des FCFA d'énergie.

**Équivalences**, avec le mélange Claude conseillé en estimation prudente : création d'un site vitrine 100 FCFA, d'un site avec base de données 200 FCFA, une retouche 35 FCFA (site vitrine) ou 70 FCFA (site avec base de données). Un « site complet » = la création et environ 5 retouches : environ **275 FCFA** (site vitrine) et **550 FCFA** (site avec base de données).

| Formule | Prix par mois | Énergie | Ce que le client peut faire (environ) | Avantages |
|---|---|---|---|---|
| **Gratuite** | 0 FCFA | 30 FCFA par jour | 1 site vitrine en 2 jours, puis 1 retouche par jour ; questions sans limite pratique | Publication gratuite, code (ZIP et GitHub) gratuit |
| **Pro** | 7 500 FCFA | 4 000 FCFA par mois | **14 sites vitrines complets** par mois, ou 7 sites avec base de données complets, ou 110 retouches | Nom de domaine à soi ; passe avant la formule gratuite ; modèle plus puissant pour les tâches difficiles (dès la clé Claude) |
| **Expert** | 20 000 FCFA | 11 000 FCFA par mois | **40 sites vitrines complets**, ou 20 sites avec base de données complets, ou 310 retouches | Tout Pro ; pour les indépendants qui créent des sites pour leurs clients |
| **Agence** | 50 000 FCFA | 28 000 FCFA par mois | **100 sites vitrines complets**, ou 50 sites avec base de données complets, ou 800 retouches | Tout Expert ; passe en premier ; pour les agences web (plus tard : plusieurs personnes dans un même compte) |

Les noms restent simples et compris partout (« Pro », « Expert », « Agence ») ; Découverte (2 500 FCFA) et Entreprise (25 000 FCFA) disparaissent, puisque les formules payantes commencent à 7 500 FCFA. Aujourd'hui, avec Gemini compté au prix payant, les mêmes énergies donnent à peu près **deux fois plus** de sites vitrines ; avec Haiku 5.5 seul, environ quatre fois plus.

**Marges avec la clé Claude.** Hypothèses : l'énergie est comptée au prix réel du modèle utilisé, donc la marge ne dépend pas du modèle ; attente de 10 minutes après chaque séance (30, 60 et 90 séances par mois selon la formule) ; **3 % de frais de paiement** (estimation prudente pour un futur agrégateur de paiement). **TVA non comptée : à vérifier avec un comptable.**

| Formule | Prix | Coût maximal (toute l'énergie utilisée) | **Marge minimale** | Coût probable (30 % utilisé) | **Marge probable** |
|---|---|---|---|---|---|
| Pro | 7 500 FCFA | 4 705 FCFA | **37 %** | 1 569 FCFA | **79 %** |
| Expert | 20 000 FCFA | 12 560 FCFA | **37 %** | 4 188 FCFA | **79 %** |
| Agence | 50 000 FCFA | 30 940 FCFA | **38 %** | 10 332 FCFA | **79 %** |

Les coûts fixes (Laravel Cloud, Runner, nom de domaine : environ 7 000 à 16 000 FCFA par mois, `docs/couts.md`) sont couverts par **environ 3 abonnements Pro** à l'utilisation probable.

**Pour comparer** : Lovable et Bolt.new commencent autour de 25 $ par mois (environ 15 000 FCFA, prix à vérifier sur leurs sites) ; Kekeli Pro coûte environ deux fois moins, en français, avec Mobile Money.

### 25.4 Clients hors zone FCFA

- **Zone FCFA** (franc CFA d'Afrique de l'Ouest et d'Afrique centrale, même valeur) : prix en FCFA, comme aujourd'hui.
- **Ailleurs** : prix en **dollars** par défaut, en **euros** pour l'Europe quand elle ouvrira (fin du projet). Prix ronds, proches de la conversion :

| Formule | FCFA | Dollars | Euros |
|---|---|---|---|
| Pro | 7 500 | 13 $ | 12 € |
| Expert | 20 000 | 34 $ | 31 € |
| Agence | 50 000 | 85 $ | 76 € |

- L'énergie reste comptée en FCFA dans Kekeli ; le client ne voit qu'un pourcentage : rien ne change pour lui selon sa monnaie.
- **Je conseille le même prix partout au lancement** (simple et juste). Un prix plus élevé hors d'Afrique, plus proche de Lovable et Bolt.new, pourra être décidé plus tard, avec de vrais chiffres de ventes.

**Le paiement** : Mobile Money avec capture d'écran suffit pour la bêta dans quelques pays (validation à la main, quelques dizaines de paiements par mois au plus). Pour un Kekeli international, il faudra plus tard :
1. **un agrégateur africain** (par exemple CinetPay, FedaPay, PayDunya ou Flutterwave, déjà prévu) : Mobile Money payé et validé automatiquement, dans plusieurs pays, et cartes bancaires en Afrique ;
2. **un paiement par carte international** pour les clients hors d'Afrique, de préférence un « vendeur officiel » qui gère aussi les taxes de chaque pays (par exemple Paddle ou Lemon Squeezy) : important quand l'Europe ouvrira (TVA).

Ces services prennent en général un pourcentage par paiement, sans abonnement : **conditions, frais et possibilité de les ouvrir depuis le Togo à vérifier** avant de choisir. Rien n'est ouvert ni payé sans l'accord du porteur du projet.

### 25.5 La formule gratuite peut-elle ruiner Kekeli ?

**Non, telle qu'elle est construite, mais elle coûte un peu, et je propose de la resserrer au lancement.**
- **Le modèle ne coûte rien** : la formule gratuite n'utilise que les offres gratuites des fournisseurs ; elle n'utilisera **jamais** la clé Claude payante. Quand les offres gratuites sont épuisées, les demandes gratuites attendent (« Kekeli est très sollicité ») au lieu de payer.
- **Seul l'ordinateur coûte vraiment** : environ 5 à 20 FCFA de travail et 16 FCFA d'attente par séance, soit environ 25 FCFA par séance.
- **Le plafond total** (1 500 FCFA d'énergie par jour pour tous les utilisateurs gratuits) correspond à environ 30 à 50 séances par jour, soit **au plus environ 750 à 1 250 FCFA de dépense réelle par jour** (22 500 à 37 500 FCFA par mois, environ 40 à 65 $, si le plafond est atteint tous les jours). Les offres gratuites des fournisseurs limitent aussi le nombre de sites (environ 32 à 35 sites vitrines par jour, `docs/couts.md`).
- **Les payants passent d'abord** quand le quota gratuit du jour devient bas (à 85 %, les demandes gratuites n'ont plus que la moitié des places) : déjà construit.
- **L'adresse électronique est vérifiée** à l'inscription, ce qui freine les comptes multiples ; le plafond total protège de toute façon Kekeli.

**Ce que je propose de changer** (petits changements de code, après accord) :
1. Garder **30 FCFA par jour** (décision du porteur du projet).
2. **Plafond total de 1 000 FCFA par jour au lancement** (au lieu de 1 500), puis l'augmenter quand les ventes le permettent ; règle simple : la dépense réelle de la formule gratuite ne dépasse pas **20 % des abonnements du mois précédent**.
3. **Attente de 5 minutes avant la pause pour la formule gratuite** (10 minutes pour les payants) : divise par deux le poste le plus cher de la formule gratuite. L'aperçu se rallume tout seul quand le client revient.
4. Garder l'énergie **par jour** pour la gratuite (comme les crédits du jour de Lovable), l'énergie **par mois** pour les payants.

### 25.6 Ce qu'il faudra coder après l'accord (prochaine étape, pas maintenant)

1. Formules Gratuite, Pro, Expert et Agence dans les réglages ; retrait de Découverte et d'Entreprise.
2. Énergie par mois pour les formules payantes (en gardant la recharge chaque jour à minuit pour la gratuite).
3. Page « Formules » avec les équivalences simples et les prix en FCFA, en dollars ou en euros selon le pays du client.
4. Plafond total de la formule gratuite à 1 000 FCFA ; attente de 5 minutes pour la formule gratuite.
5. Au moment de la clé Claude : corriger le prix de lecture du cache de Sonnet 5.5 (0,10 $), compter l'écriture dans le cache, mesurer pour de vrai et ajuster la grille.

### 25.7 Réalisé (12 novembre 2026)

Proposition acceptée par le porteur du projet et codée. Captures avant et après : `docs/captures/formules.md`.
- **Formules** (`config/kekeli.php`, `settings.plans`) : Gratuite (0 FCFA, 30 FCFA par jour), Pro (7 500 FCFA, 13 $, 4 000 FCFA par mois), Expert (20 000 FCFA, 34 $, 11 000 FCFA par mois), Agence (50 000 FCFA, 85 $, 28 000 FCFA par mois). Chaque formule a une période (`period` : `day` ou `month`) et un prix en dollars (`price_usd`), réglables dans Administration → Réglages.
- **Énergie par mois** (`Energy::monthStart`) : chaque tranche de 30 jours de l'abonnement, comptée depuis sa fin ; sans date de fin (formule donnée par l'administrateur), le mois du calendrier. Les messages disent « elle revient le 12/12/2026 à 10:00 » au lieu d'une heure.
- **Abonnés de Découverte et d'Entreprise** (migration `2026_11_12_000001_move_to_new_plans`, `Subscriptions::moveToNewPlans`) : Découverte → Pro avec les mêmes jours restants ; Entreprise → Expert avec les jours restants multipliés par 25 000 / 20 000 ; formule déjà finie → Gratuite ; paiement en attente → nouvelle formule, avec des jours en proportion du montant payé à sa validation. Avant même la migration, une ancienne formule compte comme celle qui la remplace (`replaced_plans`).
- **Changement de formule payante** : les jours restants de l'ancienne sont gardés et convertis à leur valeur (`Subscriptions::validate`).
- **Formule gratuite** : plafond total de 1 000 FCFA par jour ; attente de 5 minutes avant la pause (`free_pause_minutes`, `Energy::pauseMinutes`, envoyée au Runner).
- **Prix du cache de Claude Sonnet 5.5** corrigé à 0,10 $ (aussi dans les réglages déjà enregistrés).
- **Page « Formules »** : une carte par formule (prix, « par mois », ce que le client peut faire, bouton « Choisir Pro »…), quatre colonnes sur ordinateur ; prix en dollars hors de la zone FCFA, avec « Payé 7 500 FCFA par Mobile Money » ; le paiement reste Mobile Money avec capture d'écran.
- Conditions d'utilisation, `docs/couts.md`, `CLAUDE.md` et `REPRISE.md` mis à jour.

## 26. Kekeli, agent général : étude et plan (12 novembre 2026)

Demande du porteur du projet : faire de Kekeli un agent général comme Manus, ChatGPT et Claude, qui sait aussi construire des sites web comme Claude Code, Lovable, Cursor, Codex et Bolt.new, avec un design à leur niveau. Cette partie est une **étude et un plan** : rien n'est construit ici. **Tous les coûts sont des estimations** tant qu'ils n'ont pas été mesurés (seuls les chiffres marqués « mesuré » viennent d'essais réels).

### 26.1 Ce que font les meilleurs outils

| Outil | Organisation | Outils de l'agent | Ordinateur | Connecteurs | Sécurité |
|---|---|---|---|---|---|
| **Manus** | Une zone de saisie ; chaque demande devient une tâche avec son journal d'étapes et ses fichiers livrés | Navigateur, terminal, fichiers, recherche, diapositives, tâches programmées | Un ordinateur virtuel isolé par tâche, gardé quelques jours | Google Workspace, Gmail, Agenda | Reprise de la main par le client pour se connecter à un site |
| **ChatGPT** (recherche approfondie, mode agent devenu « ChatGPT Work ») | Une discussion ; l'agent choisit seul entre répondre, chercher et agir | Recherche avec sources, navigateur, exécution de code, fichiers Word, Excel, PowerPoint | Navigateur et bac à sable distants | Connecteurs (Drive, Gmail, et d'autres) | Confirmation avant toute action importante, surveillance des consignes cachées dans les pages |
| **Claude** | Une discussion, des « artefacts » ouverts à côté | Recherche avec citations, lecture de pages, exécution de code, création de fichiers | Bac à sable d'exécution de code | Connecteurs au protocole MCP | Permission par outil, connecteurs choisis par le client |
| **Claude Code, Codex, Cursor** | Une tâche par dépôt et par branche ; le résultat est une pull request | Lecture, écriture, commandes, tests, Git | Ordinateur distant par tâche (ou la machine du développeur) | GitHub d'abord | Réseau limité à une liste, permissions avant les actions risquées |
| **Lovable, Bolt.new** | Une zone de saisie, puis discussion à gauche et aperçu à droite | Écriture du code, aperçu, publication | Bac à sable (dans le navigateur pour Bolt.new) | GitHub, base de données | Le code appartient au client |

Trois leçons : 1. **une seule zone de saisie**, et l'outil comprend seul ce qu'il doit faire ; 2. **le résultat est un objet que l'on ouvre** (site, document, présentation, pull request), pas un long texte dans la discussion ; 3. **toute action vers l'extérieur** (envoyer un courriel, pousser du code) **demande une confirmation**.

Design (observé ; les codes de couleur publiés par des tiers sont approximatifs) : Claude utilise un fond crème chaud, une seule couleur d'accent (terre cuite) employée très peu, un caractère à empattements pour les titres et des boutons noirs en forme de pilule. ChatGPT est presque sans couleur : blanc, gris, noir, zone de saisie très arrondie. Manus, Cursor et Codex sont sobres et monochromes. Lovable et Bolt.new gardent un dégradé coloré seulement sur l'accueil. Points communs : beaucoup de vide, bordures d'un pixel au lieu d'ombres, un seul accent, icônes au trait fin, animations courtes (150 à 200 millisecondes), mode sombre « chaud » plutôt que noir pur.

### 26.2 Architecture de Kekeli agent général

On garde tout l'existant (Runner, E2B, OpenCode, passage entre fournisseurs, énergie au coût réel, questions sans ordinateur). On ajoute **trois niveaux de travail**, du moins cher au plus cher, et Kekeli choisit seul :

1. **Répondre** (existe déjà, `runner/src/questions.js`) : une requête au modèle, sans ordinateur.
2. **Chercher** (nouveau) : une petite boucle dans le Runner avec deux outils, « chercher sur Internet » et « lire une page », toujours sans ordinateur ; la réponse cite ses sources.
3. **Fabriquer** (existe pour les sites web) : OpenCode dans un ordinateur E2B, avec des **boîtes à outils** copiées selon la demande : sites web (existant), documents, présentations, affiches, automatisations, code.

**Comprendre la demande.** `app/Agent/MessageIntent.php` distingue déjà question, modification et doute, sans requête au modèle. On l'étend en un aiguilleur qui rend une intention parmi : répondre, chercher, rédiger, présenter, créer une image, construire, automatiser. Des règles simples d'abord (gratuit) ; en cas de doute seulement, une très courte requête de classement au modèle économique (environ 0,05 FCFA, estimation). Le client peut toujours corriger d'un mot.

**Ce que le client voit.** Une seule zone de saisie. Une demande devient une **tâche** (le « projet » actuel, généralisé avec `projects.kind`) : discussion à gauche, **résultat à droite** (l'aperçu du site web, ou le document, la présentation, l'image, avec « Télécharger »). Une simple question n'allume aucun ordinateur et ne crée pas de site. Le titre du premier écran passera de « Que voulez-vous créer aujourd’hui ? » à une question qui couvre aussi les questions et les recherches, au moment où la première capacité hors site web sera livrée (pas dans la pull request du design).

**Livrables.** Les fichiers produits (Word, PDF, PowerPoint, image) sont rangés avec la sauvegarde de la tâche et téléchargés par une adresse signée, comme le fichier ZIP du code.

**Connecteurs.** Les jetons des clients restent **dans le site, chiffrés, jamais dans l'ordinateur virtuel ni chez le modèle** (même règle que la clé GitHub). Le site exécute lui-même l'action demandée par l'agent, après confirmation du client.

### 26.3 Nouvelle identité visuelle (appliquée le 12 novembre 2026, captures : `docs/captures/design.md`)

- **Idée** : « la lumière » sobre. Un fond clair et chaud comme du papier, de l'encre, et **une seule touche de lumière** (ambre doré) réservée au logo et aux moments importants (envoi, énergie, lien actif).
- **Couleurs, mode clair** : fond `#FAF8F4`, surfaces `#FFFFFF`, bordures `#E7E2D9`, texte `#1C1A17`, texte secondaire `#6B665E`, accent `#E3A008` (texte d'accent `#B45309`). Boutons principaux : encre, en pilule, texte blanc.
- **Mode sombre** : fond `#171513`, surfaces `#201D1A`, bordures `#34302B`, texte `#F3EFE8`, accent `#F5B83D`. Jamais de noir pur.
- **Typographie** : Inter pour toute l'interface (licence OFL, très lisible sur téléphone) ; Instrument Serif (licence OFL) seulement pour les grands titres, par exemple la question de l'accueil. Polices servies par Kekeli, sans appel à un service extérieur.
- **Logo** : le soleil reste, simplifié en un disque et huit rayons courts au trait régulier, lisible en 16 pixels et en une seule couleur ; le mot « Kekeli » en minuscules, en Inter demi-gras.
- **Formes** : zone de saisie arrondie de 24 pixels, cartes de 14 pixels, bordures d'un pixel, presque pas d'ombres. Icônes Lucide au trait de 1,5. Animations de 150 à 200 millisecondes, désactivées si le téléphone demande moins de mouvement.
- **Écrans** : accueil, inscription et connexion, « Mes projets », espace de travail (discussion, aperçu, code), fenêtres, formules, paiement, compte, messages de limite. Téléphone d'abord, puis ordinateur.
- **Comment c'est construit** : les gris et l'ambre de Tailwind sont remplacés dans `resources/css/app.css` (bloc `@theme`), donc tous les écrans changent ensemble ; classes `kekeli-fond` (fond des pages) et `kekeli-titre` (grands titres) ; polices installées avec npm (`@fontsource-variable/inter`, `@fontsource/instrument-serif`, licence OFL) et servies par Kekeli ; le service de polices extérieur est retiré. Tests : `tests/Feature/DesignTest.php`.

### 26.4 Les dix capacités

Licences vérifiées le 12 novembre 2026 sur npm et PyPI ; toutes permettent l'usage commercial. Coût par utilisation avec le modèle gratuit d'aujourd'hui compté à son prix payant (Gemini 3.5 Flash-Lite), estimation.

| Numéro | Capacité | Comment | Outils (licence) | Difficulté | Coût estimé | Risques |
|---|---|---|---|---|---|---|
| 1 | Répondre, avec recherche et sources | Niveaux 1 et 2 ; sources affichées sous la réponse | Lecture de page : `@mozilla/readability` (Apache 2.0) ; recherche : voir 26.5 | Moyenne | 0,4 FCFA sans recherche ; environ 9 FCFA avec 3 recherches | Réponse fausse présentée avec assurance ; consignes cachées dans une page |
| 2 | Sites web (fait), applications et logiciels | Mode développeur étendu, travail en plusieurs demandes, tests | OpenCode (MIT), Git | Élevée | Site web : 55 FCFA (mesuré) ; logiciel : 200 à 2 000 FCFA | Les modèles gratuits se perdent sur les grands projets |
| 3 | Automatisations | Kekeli écrit le script et le fichier GitHub Actions dans le dépôt du client, puis vérifie la première exécution | GitHub Actions (gratuit pour les dépôts publics, environ 2 000 minutes par mois en privé, à vérifier) | Moyenne, après l'application GitHub | Environ 65 FCFA | Clés du client : uniquement dans les « secrets » de son GitHub, jamais dans Kekeli |
| 4 | Agents IA | Un projet de code (agent avec ses outils) que le client héberge ; Kekeli fournit le modèle de départ et les tests | `@modelcontextprotocol/sdk` (MIT), kits des fournisseurs | Élevée | 150 à 600 FCFA | Le client paie son propre modèle ; l'agent créé doit respecter les mêmes interdits |
| 5 | Documents, rapports, livres | Plan, puis rédaction section par section, puis mise en page par un script | `docx` (MIT), `python-docx` (MIT), WeasyPrint (BSD) ou Typst (Apache 2.0) pour le PDF | Moyenne | 20 pages : environ 65 FCFA ; livre de 200 pages : environ 700 FCFA | Faits inventés ; cohérence d'un long texte avec un petit modèle |
| 6 | Présentations | Plan, puis diapositives à partir de gabarits soignés | PptxGenJS (MIT), `python-pptx` (MIT) | Moyenne | 12 diapositives : environ 36 FCFA | Mise en page qui déborde : vérification automatique par image |
| 7 | Affiches et images | Image créée (Cloudflare, existant) et texte posé en HTML, puis export en image ou en PDF | Playwright (Apache 2.0), `sharp` (Apache 2.0), `satori` (MPL 2.0) | Moyenne | Environ 15 FCFA, plus 0,6 FCFA par image | Jamais de texte dans l'image créée (règle existante), marques et personnes réelles |
| 8 | Prompts | Niveau 1, avec des consignes par type d'outil (vidéo, image) | Aucun | Faible | Environ 0,5 FCFA | Aucun particulier |
| 9 | Prospects | Recherche, lecture des sites des entreprises, tableau avec la source de chaque ligne | `exceljs` (MIT), recherche (26.5) | Élevée | 20 entreprises : environ 80 FCFA | Voir 26.8 : entreprises et contacts professionnels publics seulement |
| 10 | Connecteurs | Jetons dans le site ; action exécutée après confirmation | OAuth de chaque service | Très élevée | Comme une demande ordinaire | Voir 26.8 : vérification par Google, compte Meta |

### 26.5 La recherche sur Internet : le point à décider

- **La recherche Google intégrée à Gemini n'existe pas dans l'offre gratuite** (compte payant : 5 000 requêtes gratuites par mois, puis 14 $ pour 1 000). L'outil de recherche de Claude coûte 10 $ pour 1 000 recherches.
- **Sans aucun compte** : lire une page dont le client donne l'adresse, et Wikipédia (interface libre). C'est utile mais ce n'est pas une vraie recherche.
- **Interroger un moteur sans passer par son interface officielle** (bibliothèques du type `duckduckgo-search`, ou SearXNG hébergé par nous, licence AGPL 3) : gratuit, mais contraire aux conditions des moteurs et bloqué depuis les serveurs. **Écarté.**
- **Interfaces de recherche avec quota gratuit** : Tavily (environ 1 000 recherches par mois), Exa (crédits gratuits), Brave (crédit de 5 $ par mois, carte bancaire demandée). Chiffres de sources tierces, conditions à lire sur les sites officiels avant tout.
- **Recommandation** : un compte gratuit, sans carte bancaire, chez un seul fournisseur de recherche, avec un nombre maximum de recherches par jour pour tous les utilisateurs (comme pour les images). **C'est un compte à créer : j'attends l'accord du porteur du projet avant cette pull request.** Au lancement payant, la recherche est comptée dans l'énergie (environ 6 à 8 FCFA par recherche).

### 26.6 Le cerveau : gratuit ou payant

| Tâche | Modèles gratuits d'aujourd'hui | Modèle payant nécessaire |
|---|---|---|
| Questions, prompts, courts textes | Bien | Non |
| Recherche avec 3 à 5 sources | Correct | Pour la recherche approfondie (20 sources et plus) |
| Site web vitrine, site avec base de données simple | Correct (mesuré) | Pour un design vraiment fin |
| Document jusqu'à 20 pages, présentation | Correct, section par section | Livre entier cohérent |
| Automatisation simple | Correct | Dès qu'il faut déboguer |
| Applications, logiciels, agents IA | Insuffisant | Oui |
| Connecteurs (courriels, fichiers du client) | **Interdit** : les offres gratuites utilisent les données | Oui, obligatoire |

Limite de volume : environ 500 requêtes gratuites par jour **pour tous les utilisateurs ensemble** ; un document de 20 pages en prend environ 30.

Coût par tâche type, estimation (1 $ = 590 FCFA) :

| Tâche | Gemini 3.5 Flash-Lite | Claude Haiku 5.5 | Claude Sonnet 5.5 |
|---|---|---|---|
| Question avec 3 recherches (40 000 tokens lus, 1 500 écrits), hors prix de la recherche | 9 FCFA | 3 FCFA | 56 FCFA |
| Document de 20 pages (150 000 lus, 25 000 écrits) | 65 FCFA | 16 FCFA | 325 FCFA |
| Présentation de 12 diapositives (120 000 lus, 10 000 écrits) | 36 FCFA | 10 FCFA | 200 FCFA |
| Automatisation testée (300 000 lus, 8 000 écrits) | 65 FCFA | 20 FCFA | 400 FCFA |
| Site web vitrine | 55 FCFA (mesuré) | 5,5 FCFA | 90 FCFA (255 en estimation prudente, partie 25) |

### 26.7 Kekeli peut-il remplacer l'abonnement Claude Code du porteur du projet ?

**Non, pas à qualité égale et pour moins cher.** Chiffres officiels de Claude Code : un développeur consomme en moyenne environ 13 $ par jour actif au prix de l'interface de programmation, soit **150 à 250 $ par mois** ; l'abonnement coûte **20 $** (Pro), **100 $** ou **200 $** (Max) par mois et inclut Claude Code (prix des abonnements : sources tierces). Un abonnement donne donc beaucoup plus d'usage que la même somme payée au token.

- Kekeli avec **Sonnet 5.5** payé au token pour le même travail : environ 150 à 250 $ par mois (90 000 à 150 000 FCFA), plus cher que l'abonnement.
- Kekeli avec **Haiku 5.5** : environ 10 à 15 $ par mois, mais la qualité n'est pas celle qu'il faut pour construire Kekeli lui-même.
- Kekeli avec les **modèles gratuits** : 0 FCFA, mais qualité nettement plus basse et environ 500 requêtes par jour ; une seule longue séance de Claude Code en fait plusieurs centaines.

Conclusion : garder l'abonnement pour construire Kekeli. Kekeli pourra remplacer Claude Code pour les **petites tâches** (une page, une correction, un document), pas pour le gros travail quotidien. À recalculer avec des mesures réelles quand la clé Claude sera achetée.

### 26.8 Sécurité et légalité

- **Clés des clients** : jamais dans Kekeli pour les automatisations et les agents (elles vont dans les « secrets » du GitHub du client). Jetons des connecteurs chiffrés dans le site, droits les plus petits possibles, bouton « Déconnecter » qui efface tout.
- **Consignes cachées** dans une page lue ou un courriel : le texte lu est une donnée, jamais un ordre ; aucune action vers l'extérieur sans confirmation du client.
- **Google** : `drive.file` (seulement les fichiers choisis par le client) est classé non sensible ; `gmail.send` est « sensible » et demande une vérification de l'application par Google ; lire ou modifier la boîte est « restreint » et demande une évaluation de sécurité payante chaque année (coût cité par des tiers : 500 à 4 500 $, non vérifié). **On commence par Drive, puis l'envoi seul ; la lecture de Gmail est une dépense à décider.** Il faut un projet Google Cloud : compte à créer, donc accord à demander.
- **WhatsApp** : l'interface officielle exige un compte Meta Business vérifié et des messages payants. Au début : seulement des liens « Écrire sur WhatsApp » préparés par Kekeli, gratuits.
- **Données personnelles** : avec les offres gratuites, les demandes peuvent servir aux fournisseurs ; donc **aucun connecteur avec un modèle gratuit**. Togo : loi 2019-014 (consentement). Europe : RGPD.
- **Prospection** : uniquement des entreprises et leurs contacts professionnels publiés par elles-mêmes (site, page « Contact »), avec la source de chaque ligne. Jamais de particuliers, jamais d'adresses devinées ou reconstituées, jamais d'aspiration de réseaux sociaux. D'après la CNIL, la prospection entre professionnels par courriel est possible si la personne est informée et peut s'y opposer simplement, et si le message concerne sa profession. **Kekeli prépare la liste et le message ; il n'envoie jamais en masse.**
- **Images et textes** : règles existantes (licences sans obligation de citer, pas de marque ni de personne réelle). Documents : pas de copie de textes protégés, sources citées.
- **À faire relire par un juriste** avant le lancement public, avec les conditions d'utilisation.

### 26.9 Découpage en pull requests

Une capacité à la fois, chacune avec ses tests, ses captures avant et après (téléphone et ordinateur) et ses documents.

**Nouvel ordre décidé par le porteur du projet le 12 novembre 2026** (après la validation de la pull request 40). Déjà faits : nouveau design, une seule zone pour tout, navigation professionnelle, recherche sur Internet avec sources, finitions, qualité des réponses.

| Ordre | Pull request | Dépend de |
|---|---|---|
| 1 | **Applications** (partie 27) : applications web installables, mode plan, construction comme Claude Code, Variables secrètes, services choisis et branchés par l'utilisateur | Validée (pull request 41) |
| 2 | **Agents IA** (partie 28) : agents écrits comme Claude Code, LangGraph ou CrewAI, essai gratuit dans « Tester l'agent » | Validée (pull request 42) |
| 3 | **GitHub et automatisations, comme Claude Code** (partie 29) : GitHub App de Kekeli, « dépôt d'abord » et « projet d'abord », envoi après chaque travail, automatisations écrites par Kekeli et lancées par GitHub Actions sur le dépôt du client, « Lancer maintenant » (l'ancienne étape « application GitHub » et l'étape « Automatisations » réunies, décision du 12 novembre 2026) | Construite et testée avec un GitHub simulé ; essai réel avec la vraie GitHub App après sa création par le porteur du projet |
| 4 | **Documents et présentations** (partie 30) : de vrais fichiers Word, PowerPoint et PDF, aperçu des pages, versions, fichiers joints corrigés dans leur format (décision du 12 novembre 2026 : on continue les fonctions avant la mise en ligne) | Validée (pull request 45) |
| 5 | **Images et affiches** (partie 31) : images et affiches en PNG et PDF, formats selon l'usage, versions, logo joint, texte exact (relu, ou posé par le code) | Validée (pull request 46) |
| 6 | **Connecteurs (MCP)** (partie 26.16) : étude des serveurs officiels, catalogue de la catégorie (1), connecteur personnalisé, OAuth, permissions comme Claude | Validée (pull request 47) |

**Nouvel ordre décidé par le porteur du projet le 13 novembre 2026** (après la validation de la pull request 47) :

| Ordre | Pull request | État |
|---|---|---|
| 1 | **Versions et retour en arrière** des sites, applications, agents et automatisations, comme Lovable et Bolt.new (partie 26.17) | Construite le 13 novembre 2026 |
| 2 | **Étude des coûts de mise en ligne** (Laravel Cloud, Runner, stockage, E2B, OpenAI) | **Ne pas commencer sans l'accord du porteur du projet** |
| 3 | **Mise en ligne** sur Laravel Cloud (liste des parties 29.6 et `docs/mise-en-ligne.md`, partie 7) | Après l'étude des coûts |
| 4 | **Prospects**, après le lancement | Plus tard |

**Pull request « Optimisations internes »** (13 novembre 2026, partie 26.19), après la validation de la pull request 49 (vidéo).

**Décision du porteur du projet le 13 novembre 2026** (après la validation de la pull request 48) : l'étude des coûts de mise en ligne est **repoussée** ; nouvelle pull request **« Montage vidéo et motion design »** (partie 26.18), construite le 13 novembre 2026. L'étude des coûts de mise en ligne, puis la mise en ligne et les prospects, restent à faire ensuite, **toujours seulement après l'accord du porteur du projet**.

Restent aussi, sans date : connecteurs des catégories (2) et (3) (applications à créer, validations), relecture juridique.

Ancien ordre (avant le 12 novembre 2026), gardé pour l'historique : design ; une seule zone ; navigation ; recherche ; documents ; présentations ; affiches ; mise en ligne ; application GitHub ; automatisations ; versions puis applications ; prospects ; agents IA ; connecteurs.

### 26.10 Effet sur les formules

- **Aucun changement de prix ni d'énergie maintenant.** L'énergie est comptée au coût réel : une nouvelle capacité consomme simplement son coût, et la marge reste la même.
- Repères avec le modèle d'aujourd'hui : la formule gratuite (30 FCFA par jour) permet environ 3 questions avec recherche par jour, ou une présentation tous les deux jours ; Pro (4 000 FCFA par mois) environ 60 documents de 20 pages ; Expert environ 170 ; Agence environ 430.
- À faire dans la pull request 2 : ajouter ces équivalences sur la page des formules (réglables, comme `site_estimate_fcfa`).
- **À revoir avec la clé Claude** : avec Sonnet 5.5, un document de 20 pages coûte environ 325 FCFA, soit seulement 12 par mois en Pro. Le mélange conseillé (modèle économique pour l'essentiel, modèle puissant pour le difficile) reste donc la règle ; les prix ne changent que si les mesures réelles le demandent.
- Deux plafonds par jour à ajouter, réglables dans Administration → Réglages : nombre de recherches et nombre de livrables pour l'ensemble des utilisateurs gratuits.

### 26.11 Pull request 2 réalisée : « Une seule zone pour tout » (12 novembre 2026)

**Décisions du porteur du projet** : pas de « devineur » à règles ni de bouton de mode, c'est le modèle qui décide avec les outils qu'on lui donne, comme Claude, ChatGPT et Manus. La détection par règles de la partie 23.12 (`MessageIntent`) est supprimée.

**Comment ça marche**
1. Chaque message du client (accueil, discussion ou projet) va au **premier répondant** : le Runner appelle le modèle gratuit (Gemini 3.5 Flash-Lite, puis les autres fournisseurs de la liste en cas d'échec), **sans ordinateur virtuel** (`runner/src/repondant.js`, adresse signée `POST /repondre`).
2. Le modèle reçoit une consigne, l'historique récent (10 messages au plus, 1 500 caractères chacun) et **un seul outil selon le lieu** : « creer_site » dans une discussion, « modifier_site » dans un projet de site. Les outils sont rangés dans un registre (`OUTILS` dans le Runner, `FirstResponder::TOOLS` dans le site) : en ajouter un plus tard (documents, présentations, images) ne demande pas de tout refaire. Aucun n'est annoncé à l'écran.
3. Une question : réponse directe. Une demande claire : l'outil est appelé, et le travail habituel part avec **le message du client tel quel** (E2B et OpenCode, choix site vitrine ou site avec base de données inchangé, proposition d'un site avec base de données gardée). Un doute ou une demande trop vague : **une seule question courte**.
4. **Sécurité** : le texte du client part seulement dans les tours « client » de la conversation, jamais dans la consigne. Le nom de l'outil est vérifié par le Runner puis par le site (`FirstResponder::tool`) : un outil non proposé dans le lieu n'est jamais exécuté. Les outils ne prennent aucun argument.
5. **Discussions** : une question posée depuis l'accueil crée une discussion (`Project::KIND_CHAT`), avec un titre court donné sans requête au modèle (`app/WebApps/ChatTitles.php`), une bulle dans « Mes projets », seulement la discussion et la zone de saisie. Quand le client demande un site, la discussion devient un projet de site (`NewSite::convert`) et garde tout son historique ; l'agent reçoit aussi les échanges précédents, comme une information.
6. **Tous les fournisseurs occupés** : le message attend et le premier répondant réessaie seul toutes les 5 minutes (6 essais), avec le message « Kekeli est très sollicité en ce moment… » et une alerte pour l'administrateur ; après le dernier essai, une phrase polie, et le message reste.

**Mesure réelle (gratuite, Gemini 3.5 Flash-Lite, 12 novembre 2026)** : 33 phrases réalistes, dont certaines comme dictées à la voix (`runner/mesures/phrases-repondant.json`, script `runner/scripts/mesurer-repondant.mjs`, résultat `runner/mesures/resultats-repondant.json`).
- Premier passage : 28 sur 32. Deux erreurs venaient de l'outil de mesure (« Comment puis-je vous aider ? » comptée comme un doute) ; deux étaient de vraies erreurs (le modèle ajoutait « Voulez-vous que je crée le site… ? » après une bonne réponse). Consigne corrigée : la question de doute remplace la réponse.
- Ensuite 31 sur 32, deux fois : « la page contact » lançait une modification. Consigne renforcée : un travail coûte de l'énergie, l'outil n'est appelé que si Kekeli sait quoi faire. Le même cas, avec l'historique d'un vrai projet, a été ajouté à la liste.
- **Dernier passage : 33 sur 33** (16 outils, 12 réponses, 5 questions de doute).
- **Coût mesuré** : environ 1 190 tokens lus et 47 écrits par message, soit environ **0,0005 $ (0,3 FCFA)** au prix payant de Gemini 3.5 Flash-Lite, et 0 FCFA avec l'offre gratuite. Une demande de site coûte donc environ 0,3 FCFA de plus qu'avant, pour un premier tri fiable.

**Tests** : modèle simulé (`tests/Support/FakeFirstResponder.php`) et `tests/Feature/FirstResponderTest.php` (question sans ordinateur, outil appelé, doute, outil interdit, échec des fournisseurs, discussion qui devient un site, demande signée au Runner) ; `runner/tests/repondant.test.js`. Captures : `docs/captures/une-seule-zone.md`.

**Reste ouvert** : la mesure porte sur un seul modèle gratuit (Gemini 3.5 Flash-Lite) ; à refaire avec les autres fournisseurs de la liste et, à la fin du projet, avec la clé Claude.

### 26.12 Pull request « Navigation professionnelle » réalisée (12 novembre 2026)

Insérée avant les documents, à la demande du porteur du projet, pour ranger Kekeli comme Claude, ChatGPT, Lovable, Bolt.new, Cursor et Manus avant d'ajouter des capacités. **Règle : rien qui ne fonctionne pas encore**, pas de « bientôt disponible », pas de section vide.

1. **Apparence** : « Système » par défaut, « Clair » ou « Sombre » dans Réglages → Apparence. Le choix est gardé pour le compte (`users.appearance`) et donné à Flux dans l'en-tête de la page (`partials/head.blade.php`) avant tout affichage : aucun flash de la mauvaise couleur. Plus de classe sombre écrite en dur dans les gabarits.
2. **Barre de navigation** (`components/navigation.blade.php`), la même sur toutes les pages connectées, page du projet comprise : à gauche sur un ordinateur, dans le menu ☰ sur un téléphone. En haut « Nouveau » (la page `projects.index`, réduite à « Que voulez-vous créer aujourd’hui ? »), « Rechercher » (titres et contenu des messages des projets du client, `navigation/search`), « Formules » ; puis « Récents » (`navigation/recents`, du plus récent au plus ancien, filtres « Tous / Sites / Discussions », renommer et supprimer depuis « ⋯ », section cachée quand il n'y a encore rien) ; en bas le menu du compte. La liste « Mes projets » est remplacée par « Récents ». L'accueil connecté mène à « Nouveau » : plus de bouton « Mes projets » à la place du menu ☰ sur le téléphone.
3. **Bouton « + »** : un seul bouton à gauche de la zone de saisie (`components/prompt-box.blade.php`), qui ouvre un petit menu avec ce qui fonctionne déjà : « Ajouter une image », et « GitHub » sur l'accueil. À côté de la zone, seulement le bouton d'envoi. Le menu accueillera plus tard documents, présentations et connecteurs.
4. **Réglages par onglets** : Profil, Mot de passe, Apparence, Formule (formule actuelle, énergie, lien vers les formules). Emplacement « Connecteurs » prêt dans le code (`app/Support/Connectors.php`, réglage `kekeli.connectors`, page `settings/connecteurs`) : l'onglet et la page n'existent que lorsqu'au moins un connecteur fonctionne vraiment ; aujourd'hui, aucun.
5. **Nombres en chiffres** dans les réponses du premier répondant et dans le message final : « 10 $ », « 6 000 FCFA », extensions « .com » et « .tg », jamais en lettres. Trois phrases de plus dans la mesure, avec une vérification de l'écriture des nombres. **Mesure gratuite (Gemini 3.5 Flash-Lite) : 35 bonnes décisions sur 36** ; les trois réponses avec des prix sont bien écrites. Le cas compté faux, « j'ai un restaurant », reçoit bien une proposition de créer le site, mais au milieu de la réponse au lieu d'une seule question courte à la fin.

Tests : `tests/Feature/NavigationTest.php` (Système par défaut et sans flash, même barre partout, recherche, filtres de « Récents », menu « + », onglet Connecteurs caché) et `runner/tests/repondant.test.js`. Captures : `docs/captures/navigation.md`.

### 26.13 Pull request « Recherche sur Internet avec sources » réalisée (12 novembre 2026)

Avancée avant les documents par le porteur du projet. Clés gratuites placées par lui (`TAVILY_API_KEY`, `EXA_API_KEY`, ajoutées vides dans `.env.example`).

**Principe**, comme Claude et ChatGPT : le modèle reste le même ; le premier répondant reçoit un nouvel outil « rechercher_web » dans son registre (`runner/src/repondant.js`), proposé sur l'accueil, dans les discussions et dans les projets. Il cherche seulement quand la réponse dépend d'informations actuelles ou vérifiables (prix, actualités, lois, personnes en poste, horaires, dates, produits récents) ou quand le client le demande ; jamais pour une définition, une explication ou un conseil général. La recherche est faite par le Runner (`runner/src/recherche.js`), puis le modèle termine sa réponse.

**Fournisseurs, offres gratuites seulement** : Tavily en principal (recherche « basic », 1 crédit), Exa en secours ; un fournisseur qui demande de payer (402, 432, 433) ou qui a atteint sa limite (429) est laissé de côté, jamais payé. **La recherche Google intégrée à Gemini n'est pas ajoutée** : la page officielle des prix (aistudio.google.com/docs/pricing, lue le 12 novembre 2026) indique « Not available » dans l'offre gratuite pour Gemini 3.5 et 3.1 Flash-Lite (5 000 recherches gratuites par mois seulement sur un compte payant, puis 14 $ pour 1 000).

**Réponses** : courtes, avec leurs propres mots, nombres en chiffres, date de l'information quand les pages la donnent (et avertissement si elle peut être ancienne), contradictions signalées. Citations [1], [2]… renumérotées dans l'ordre des sources affichées ; section « Sources » ajoutée par Kekeli (2 à 5 liens cliquables, titre et nom du site), construite à partir des résultats, jamais des adresses écrites par le modèle. Pendant la recherche, la ligne « Recherche sur Internet… » ; ensuite, la recherche est rangée dans les actions repliées.

**Sécurité** : le contenu des pages est remis au modèle comme une information, jamais une consigne, avec la règle d'ignorer toute instruction qu'il contient ; jamais de recherche ni de résultat vers une adresse interne (localhost, adresses privées, `.local`, `.internal`) ; extraits raccourcis ; clés seulement dans le Runner.

**Limites et coût** : au plus 2 recherches par message ; par jour selon la formule (Gratuite 3, Pro 20, Expert 40, Agence 80) et 900 par mois pour tous les clients ensemble, sous les offres gratuites (`app/Agent/WebSearchQuota.php`, Administration → Réglages). Chaque recherche est comptée dans l'énergie à 0,008 $ (prix d'un crédit Tavily payant, estimation), même pendant l'offre gratuite. Limite atteinte : l'outil n'est pas proposé et Kekeli dit poliment qu'il n'a pas pu vérifier sur Internet.

**Mesure réelle économe (12 novembre 2026, Gemini 3.5 Flash-Lite, Tavily et Exa gratuits)** : 13 phrases de plus dans `runner/mesures/phrases-repondant.json` (7 demandent une recherche, 6 non), mode `node runner/scripts/mesurer-repondant.mjs --recherche`, résultat `runner/mesures/resultats-recherche.json`.
- Décisions : **12 sur 13** au dernier passage (11 sur 13, puis 10 sur 13 avant les corrections). L'erreur restante : « est-ce que WhatsApp Business est toujours gratuit cette année » est répondu sans recherche.
- Les 36 phrases sans recherche : **36 sur 36**.
- Sources : pertinentes et de sites togolais ou spécialisés (canalplus.com, bceao.int, xe.com, wikipedia.org, aéroport de Lomé, togofirst.com), parfois des pages Facebook ou des vidéos. Une vérification à la main a montré que les dates données correspondent bien aux pages.
- Corrections trouvées pendant la mesure : quota de recherches commun à tous les modèles d'un message (3 recherches avaient été faites au lieu de 2), date de l'information, citations renumérotées (« [3, 4] » compris).
- Crédits consommés : **22 recherches Tavily pour la mesure** (budget de 40), plus **4 pour les captures** ; Exa jamais utilisé (Tavily a toujours répondu). Quota gratuit de Tavily : environ 1 000 par mois.
- **Coût d'une question avec recherche** (estimation au prix payant) : environ 5 400 tokens lus et 120 écrits, soit environ 1,1 FCFA de modèle, plus 4,7 FCFA de recherche : **environ 5,9 FCFA** ; sans recherche, environ 0,3 FCFA. Avec les offres gratuites, le coût réel est de 0 FCFA, mais l'énergie compte ces montants.

**Finitions de la navigation** dans la même pull request : onglets des réglages en deux colonnes de pastilles sur téléphone, extrait de la recherche sur un mot entier sans marque de liste, une seule question courte à la toute fin quand le client parle seulement de son activité (jamais de proposition de site après des conseils ou un texte). Correction trouvée en passant : une longue source faisait déborder la discussion sur téléphone ; « 4 500 FCFA » n'est plus coupé en fin de ligne.

Tests : `tests/Feature/WebSearchTest.php` et `runner/tests/repondant.test.js` (fournisseur simulé : recherche ou non, passage de Tavily à Exa, limite atteinte, sources, adresses internes refusées, instruction cachée dans une page sans effet). Captures : `docs/captures/recherche.md`.

### 26.14 Pull request « Finitions professionnelles de la discussion et de la recherche » réalisée (12 novembre 2026)

Demandée par le porteur du projet avant les documents. Règle : si Claude, ChatGPT, Perplexity, Manus, Lovable ou Bolt.new ont un élément, Kekeli l'a ; sinon il est enlevé. Chaque point a été vérifié dans leurs pages publiques (sources ci-dessous).

**Réponses avec recherche**
1. **Date de l'information** : le Runner donne au modèle la date d'aujourd'hui et la date de chaque page quand le fournisseur la connaît (`dateEnClair`) ; la consigne demande de toujours dater (« selon un article de juin 2025 »), de dire quand la page ne donne pas de date, et de prévenir quand l'information a plus de 6 mois. La date est aussi affichée à côté de chaque source.
2. **Sources fiables d'abord** (`classerResultats` dans `runner/src/recherche.js`) : réseaux sociaux, vidéos et forums (`SITES_DERNIER_RECOURS`) sont écartés dès qu'il y a 2 sources fiables, et passent toujours après elles sinon. Tavily reçoit une demande de 8 résultats (même prix) pour qu'il en reste assez.
3. **Pastilles** : les renvois « [1] » deviennent de petites pastilles rondes et cliquables (nouvel onglet), en clair et en sombre (`RunReport::display` avec les sources, classe `kekeli-renvoi`). L'adresse vient toujours des sources gardées par Kekeli ; un numéro sans source disparaît.
4. **« A cherché sur Internet · N sources »** à la place de « Kekeli a fait 1 action » ; déplié : la recherche faite et les sites consultés (`RespondToMessage::searchSummary`, `report.searches`).
5. **Mots « toujours », « encore », « cette année », « actuellement », « en ce moment »** : ils déclenchent une recherche (règle en tête de `CONSIGNES_RECHERCHE` et dans la description de l'outil). 3 phrases ajoutées à la mesure.

**Limite de recherche atteinte** : Kekeli répond normalement, sans jamais parler de limite, de quota ni de recherche impossible (`CONSIGNE_SANS_RECHERCHE`) ; pour une information qui change, il donne ce qu'il sait avec son époque et conseille de vérifier au moment de l'achat. Filet de sécurité `sansAveuDeRecherche` : une phrase d'aveu écrite malgré la consigne est enlevée. Les recherches restantes du jour apparaissent seulement dans le menu du compte, sous l'énergie.

**Discussions**
- **Titres** : le modèle écrit une première ligne « TITRE : … » (3 à 6 mots) avec sa première réponse, sans requête de plus ; le Runner l'enlève du texte (`titreEtTexte`) et le site remplace le titre provisoire (`ChatTitles::isTemporary`, `ChatTitles::fromModel`), jamais un titre choisi par le client. Si le modèle ne donne pas de titre, le titre provisoire reste et la demande est refaite au message suivant.
- **« Copier » et « Régénérer »** sous les réponses écrites (pas sous le travail sur un site) : « Copier » sous chacune (avec les adresses des sources), « Régénérer » sous la dernière seulement. « Régénérer » renvoie le même message au premier répondant, remplace la réponse, et coûte de l'énergie comme un nouveau message.
- **« Oui, créer le site »** : bouton sous la question quand la réponse d'une discussion se termine par une proposition de créer le site (`RespondToMessage::offersSite`, `report.offer`) ; il transforme la discussion en site et lance le travail sans redemander au modèle.
- **« Arrêter »** : constat, **le bouton n'existait pas**. Ajouté : pendant que Kekeli répond ou travaille, le bouton d'envoi devient « Arrêter ». Une réponse en cours est abandonnée ; un travail sur un site s'arrête au prochain signe de vie de l'agent (même mécanisme que l'arrêt faute d'énergie, raison `client`), ce qui est déjà fait est gardé, l'énergie déjà utilisée reste comptée, et « Continuer » n'est pas proposé.

**Navigation** : icône du menu à 2 barres gardée ; sur téléphone, ouvrir « Rechercher » referme le menu latéral.

**Revue générale** (toutes les pages connectées, téléphone et ordinateur, clair et sombre ; aucune page ne déborde) : « à minuit » au lieu de « à 00:00 » ; sur « Ouvrir un dépôt GitHub », plus de message « disponible très bientôt » (contraire à la règle), plus de lien « Mes projets » vers une liste disparue, exemple d'adresse qui n'est plus coupé ; texte grisé de « Rechercher » raccourci (il était coupé sur téléphone) ; « 50 kg », « 9 h » jamais coupés en fin de ligne ; sur téléphone, le site et la date d'une source passent sous son titre ; « Recherches du jour » sur une seule ligne.

**Mesure réelle (12 novembre 2026)** : 16 phrases avec l'outil de recherche (10 demandent une recherche, 6 non) : **16 sur 16** (premier passage : 11 sur 16, avant le renforcement de la consigne). Les 36 phrases sans recherche : **36 sur 36**. Titres : 13 sur 14 premières réponses de discussion (le 14e est « Bonjour, ça va ? »). **15 recherches Tavily au total** (8, puis 7 ; au dernier passage le modèle en a demandé 10, les 3 dernières n'ont pas été faites pour respecter le budget, et Kekeli a répondu sans en parler). **Limite de cette mesure** : le quota gratuit du jour de Gemini 3.5 Flash-Lite (500 requêtes) était épuisé ; presque tout a tourné sur **Gemini 3.1 Flash-Lite**, le deuxième de la liste. Ce modèle proposait un site à la fin de conseils ou d'explications : la consigne a été précisée. À refaire sur Gemini 3.5 Flash-Lite.

**Ce qui n'a pas pu être vérifié dans une documentation officielle** : le comportement exact de ChatGPT quand sa limite de recherche est atteinte (aucune page d'OpenAI trouvée ; la règle appliquée est celle demandée par le porteur du projet) ; les titres automatiques de Claude et de ChatGPT (comportement visible dans les produits, non documenté) ; un bouton d'arrêt chez Bolt.new et dans la discussion de Manus (documenté chez ChatGPT et Lovable).

Tests : `tests/Feature/ProFinishesTest.php` et `runner/tests/repondant.test.js`. Captures avant et après : `docs/captures/finitions-pro.md`.

### 26.15 Pull request « Qualité des réponses comme Claude » réalisée (12 novembre 2026)

Demandée par le porteur du projet après la validation de la pull request 39. Objectif : des réponses de la qualité, de la clarté et de la mise en forme de Claude ou de ChatGPT ; le quota gratuit ne bride plus la qualité : le client consomme son énergie, et s'il en manque, il prend une formule. **Accord du porteur du projet pour la clé payante OpenAI** (exception à la règle numéro 8, limitée aux réponses et à la dictée, avec un plafond par jour). Clé `OPENAI_API_KEY` présente, placée par lui, ajoutée vide dans `.env.example`, lue seulement par le Runner.

**1. Style « comme Claude »** (`CONSIGNES_REPONDANT` dans `runner/src/repondant.js`, questions et discussions seulement ; le message final après un travail sur un site garde sa règle courte) : commencer par la réponse sans préambule ; longueur adaptée (simple : 1 à 3 phrases, riche : réponse complète et structurée) ; Markdown seulement quand il aide (gras, titres courts, listes, tableaux, blocs de code ; jamais de lien ni d'émoji) ; expliquer le pourquoi, exemples concrets du pays du client, calculs montrés, « **Ma recommandation** » pour un conseil ; textes à rédiger complets ; nombres en chiffres, montants en FCFA ; « Mixx by Yas » et « Flooz », jamais « T-Money » (filet `nomsActuels`, car deux modèles Gemini l'écrivaient pendant la mesure).

**Affichage** (`app/Agent/Markdown.php`, classe `kekeli-markdown` dans `resources/css/app.css`) : titres, gras, italique, listes, tableaux dans une boîte qui défile sur le côté sur téléphone, blocs de code avec un bouton « Copier », en clair et en sombre. **Sûr** : le HTML écrit par le modèle est échappé (jamais interprété), adresses dangereuses refusées, liens et images du modèle changés en texte (seules les sources gardées par Kekeli deviennent des liens, en pastilles). La typographie française (espaces insécables) ne touche jamais le code.

**2. Comparaison réelle sur 12 questions** (`runner/scripts/comparer-modeles.mjs`, réponses complètes dans `runner/mesures/comparaison-modeles.json`) : 2 conseils d'entreprise, 1 explication, 1 question simple, 2 rédactions, 1 calcul simple, 1 calcul en plusieurs étapes, 1 question avec recherche, 2 questions dictées sans ponctuation, 1 comparaison. Prix officiels lus le 12 novembre 2026 (developers.openai.com/api/docs/pricing ; aistudio.google.com/docs/pricing). Coût par réponse au prix payant, 1 $ = 590 FCFA. Gemini 3.8 Flash, le meilleur Gemini de l'offre gratuite, répondait « 503, high demand » aux 12 questions : remplacé par Gemini 3.6 Flash (même prix, aussi gratuit).

| Modèle | Qualité (jugée par Claude, sur 10) | Rapidité moyenne | Coût moyen par réponse | Coût le plus haut | Réussite |
|---|---|---|---|---|---|
| Gemini 3.5 Flash-Lite (gratuit, actuel) | 6,5 : bien structuré mais générique, a écrit « T-Money » | 5,0 s | 2,3 FCFA | 4,0 FCFA | 11 sur 12 (Google surchargé) |
| Gemini 3.6 Flash (gratuit, environ 20 requêtes par jour) | 8,5 : le plus fouillé et le plus local, parfois trop long, a écrit « TMoney » | 7,7 s | 3,6 FCFA | 5,3 FCFA | 12 sur 12 |
| GPT-6 Luna (OpenAI, 0,10 $ et 0,50 $ par million) | 7 : juste et clair, parfois trop bref (explication, publication Facebook) | 5,9 s | 0,14 FCFA | 0,5 FCFA | 12 sur 12 |
| GPT-6.1 Terra (OpenAI, 1 $ et 5 $ par million) | 8,5 : le plus juste et le plus équilibré, prudent sur les chiffres, bons tableaux et recommandations | 7,9 s | 1,5 FCFA | 6,7 FCFA | 12 sur 12 |

**Choix appliqué** (`config/kekeli.php`, clé `answer_models`, modifiable par formule dans Administration → Réglages) :
- **Pro, Expert et Agence : GPT-6.1 Terra.** Meilleur en qualité à égalité avec Gemini 3.6 Flash, mais fiable (Gemini 3.x a répondu « surchargé » plusieurs fois pendant la mesure), sans plafond de 20 requêtes par jour, et OpenAI n'utilise pas les demandes faites par son interface pour entraîner ses modèles (contrairement à l'offre gratuite de Google). Gemini 3.6 Flash payant demanderait la facturation Google Cloud, non autorisée.
- **Gratuite : GPT-6 Luna.** Terra coûterait environ 1,5 FCFA par réponse, soit 20 réponses pour les 30 FCFA du jour, et vider le plafond commun de 1 $ au détriment des clients payants ; Luna, 10 fois moins cher (0,14 FCFA), reste juste et clair.
- **Secours** : les fournisseurs gratuits suivent derrière, dans leur ordre habituel (`AnswerModels::forRunner`). Si OpenAI refuse (limite, panne) ou si le plafond du jour est atteint, Kekeli répond avec eux **sans rien dire au client**.
- **Garde-fous** : seuls GPT-6 Luna, GPT-6.1 Terra et GPT-6.1 Sol peuvent être choisis (`AnswerModels::OPENAI_MODELS` et `MODELES_OPENAI` dans le Runner) ; jamais OpenAI pour l'agent qui construit les sites ni dans l'ordinateur virtuel (la clé n'entre pas dans les secrets d'E2B) ; **plafond de dépense OpenAI par jour** réglable (`openai_daily_cap_usd`, 1 $ au départ, dépense du jour affichée), compté dans `provider_usages.cost_usd` ; rien n'est gardé chez OpenAI (`store: false`).
- **Énergie** : le coût réel de chaque réponse (tokens lus, en cache, écrits et de réflexion, chacun à son prix) est décompté de l'énergie, comme avant.
- **Crédit OpenAI dépensé pour les essais** : environ **0,06 $** (0,033 $ pour la comparaison, le reste pour les essais de format et la recherche d'un défaut), sous le plafond de 0,50 $ ; estimation calculée à partir des tokens.
- **Défaut trouvé pendant la mesure et corrigé** : GPT-6.1 Terra demandait parfois 2 recherches en même temps ; OpenAI exige alors un résultat pour chacune et refusait la suite. Kekeli lui demande maintenant une recherche à la fois (`parallel_tool_calls: false`).

**3. Réflexion visible** : une question riche (conseil, comparaison, calcul, texte à rédiger, plan ; `niveauReflexion`, sans requête de plus) laisse le modèle réfléchir (« medium ») et lui demande une ligne « RÉFLEXION : » courte, dans la langue du client ; une question simple va vite (« none » pour Luna et Terra, « low » pour Gemini). Le résumé natif d'OpenAI est en anglais et technique (« Clarifying pricing calculations… ») : il n'est pas montré. La ligne repliable « Réflexion · 6 s » n'apparaît que si le modèle a vraiment réfléchi (tokens de réflexion) ; pendant la réponse, « Réflexion… » avec un minuteur, comme Claude.

**4. Saisie vocale** (`resources/js/dictee.js`, bouton dans `components/prompt-box.blade.php`) : micro à côté du bouton d'envoi ; d'abord la dictée du navigateur (gratuite, Chrome sur Android et sur ordinateur), en français par défaut puis dans la langue de l'interface ; le texte s'écrit dans la zone et le client le corrige avant d'envoyer. Sinon (navigateur sans dictée, ou service injoignable), un enregistrement de 2 minutes au plus est transcrit par OpenAI **gpt-transcribe** (0,0045 $ par minute, prix officiel), par le Runner (`runner/src/transcription.js`, adresse signée `POST /transcrire`) ; coût compté dans l'énergie (table `dictations`) et dans le plafond OpenAI ; l'enregistrement n'est jamais gardé. Le micro n'est demandé qu'au toucher ; refus : « Kekeli n'a pas accès au micro. Pour dicter, autorisez le micro pour ce site dans les réglages du navigateur… ».

**5. Vérifications des mots** (pages publiques lues le 12 novembre 2026) :
- Réflexion : Claude en français affiche « Réflexion », avec un minuteur pendant le travail, puis une section repliable au-dessus de la réponse (support.claude.com, article 10574485) ; ChatGPT affiche « Thought for 16 seconds » (sources secondaires, aucune page d'OpenAI lisible). Kekeli : « Réflexion… » puis « Réflexion · 6 s ».
- Recherche : Claude parle de « recherche web » avec un indicateur et des citations (support.claude.com, article 10684626). Kekeli passe de « Internet » à « le Web » : « Recherche sur le Web… », puis « A cherché sur le Web · 3 sources ».
- Micro : OpenAI l'appelle « Voice dictation » (« dictée vocale »), le texte reste modifiable avant l'envoi (help.openai.com, article 12168547, page bloquée pour les robots, lue par son résumé de recherche). Kekeli : bouton « Dictée vocale ». Cursor et Codex : dictée ou conversation vocale selon les versions ; Perplexity, Manus et Bolt.new : aucune page officielle trouvée.
- « Administration » n'apparaît dans le menu que pour un compte administrateur (`@can('admin')`), vérifié par un test.

Tests : `tests/Feature/AnswerQualityTest.php`, `tests/Feature/DictationTest.php`, `runner/tests/qualite-reponses.test.js`. Captures avant et après : `docs/captures/qualite-reponses.md`.

### 26.16 Connecteurs (MCP) : étude du 13 novembre 2026 (sources officielles uniquement)

**Demande du porteur du projet** (après la validation de la pull request 46) : des connecteurs comme Claude et ChatGPT, au protocole MCP (Model Context Protocol). Étude d'abord, **arrêt obligatoire avant toute création de compte ou dépense** ; construction seulement avec la catégorie (1).

**Méthode.** Pour chaque service : la documentation officielle de l'éditeur, puis une vérification directe des **métadonnées publiées par son serveur** (`/.well-known/oauth-protected-resource` et `/.well-known/oauth-authorization-server`, lues le 13 novembre 2026) : un `registration_endpoint` veut dire que n'importe quel client peut s'inscrire tout seul (« Dynamic Client Registration », RFC 7591), sans application à créer. Quand la documentation dit autre chose que les métadonnées (liste fermée de clients, publication obligatoire), c'est la documentation qui compte. Les conditions d'utilisation générales de chaque éditeur n'ont pas toutes été lues : **à faire relire par un juriste avant le lancement public**, avec les nôtres.

**Rappel de la règle de la partie 26.6** : les données lues dans le compte d'un client (courriels, fichiers, tableaux) ne vont **jamais** à une offre gratuite de modèle (qui peut utiliser les données). Une discussion avec un connecteur actif passe donc seulement par le modèle payant de la formule (OpenAI) ; plafond du jour atteint : « Kekeli est très sollicité · Reprise à … », sans secours gratuit.

#### Le protocole et les outils

- **Spécification MCP** : révision courante du 28 juillet 2026 ; autorisation OAuth 2.1 avec PKCE (méthode S256 obligatoire), métadonnées de ressource protégée (RFC 9728, obligatoires pour le serveur), paramètre `resource` (RFC 8707) ; inscription du client par document de métadonnées (« Client ID Metadata Document », conseillé) ou inscription dynamique (RFC 7591, permise).
- **Kit officiel pour le Runner** : `@modelcontextprotocol/client` **2.4.0** (23 octobre 2026, licence Apache 2.0, Node 20 ou plus, ligne stable « v2 » qui suit la révision du 28 juillet 2026), avec le transport « Streamable HTTP ». L'ancien paquet `@modelcontextprotocol/sdk` 1.32.2 (licence MIT) reste maintenu au moins six mois après le 27 juillet 2026 mais tire Express et Hono, inutiles au Runner. **Choix : `@modelcontextprotocol/client` 2.4.0**, version fixée.
- **OAuth** : fait par le site (qui garde les jetons chiffrés, comme la clé GitHub), avec découverte, inscription automatique et PKCE ; le Runner ne reçoit qu'un jeton d'accès de courte durée pour la requête en cours, jamais l'ordinateur virtuel.
- **OpenCode** (moteur des constructions) sait utiliser des serveurs MCP distants (`"type": "remote"`, OAuth automatique avec identifiant fixe, document de métadonnées ou inscription dynamique). **Pas pour le moment** : le jeton du client entrerait dans l'ordinateur E2B, ce que nos règles interdisent. Plus tard, un relais du Runner (comme pour OpenAI) pourrait le permettre.

#### Les serveurs officiels étudiés

| Service | Adresse officielle | Connexion | Validation par l'éditeur | Outils (lecture / écriture) | Formule de l'utilisateur | Catégorie |
|---|---|---|---|---|---|---|
| Notion | `https://mcp.notion.com/mcp` | OAuth, inscription automatique ou document de métadonnées (vérifié dans les métadonnées) | aucune ; l'administrateur d'un espace Entreprise peut bloquer | recherche, lecture de pages et de bases / créer et modifier des pages, commentaires | toutes (« works on every plan ») | **1** |
| Linear | `https://mcp.linear.app/mcp` | OAuth 2.1 avec inscription automatique (documentation et métadonnées) ; lecture seule possible (`/mcp/readonly`) | aucune indiquée | trouver / créer et modifier tickets, projets, documents, commentaires | non indiqué | **1** |
| Jira et Confluence (Atlassian) | `https://mcp.atlassian.com/v2/mcp` | OAuth 2.1, inscription automatique (métadonnées) | aucune indiquée ; suppression et administration de Jira désactivées par défaut | recherche et résumé / créer et modifier tickets et pages | certains outils consomment des « crédits Rovo » (1 à 10 par appel) du client | **1** |
| Airtable | `https://mcp.airtable.com/mcp` | OAuth avec inscription automatique (« supports Dynamic Client Registration ») | aucune indiquée | bases, tables, enregistrements / créer et modifier enregistrements, tables, commentaires | « all users on all plans » | **1** |
| Miro | `https://mcp.miro.com/` | OAuth 2.1 avec inscription automatique | aucune indiquée | tableaux, recherche, commentaires / créer des tableaux, diagrammes, tableaux de données, commentaires ; `section_delete` | gratuite comprise (100 appels par jour, 500 à 10 000 en payant) | **1** |
| Sentry | `https://mcp.sentry.dev/mcp` | OAuth, inscription automatique (métadonnées) | aucune indiquée | erreurs, performances / triage | non indiqué | **1** (pour les développeurs) |
| Hugging Face | `https://huggingface.co/mcp` | **sans connexion** (outils standard pour les anonymes) ; jeton ou `?login` pour plus | aucune | recherche de modèles, jeux de données, articles, documentation (lecture) ; générateur d'images | gratuite | **1** (sans compte) |
| DeepWiki (Cognition) | `https://mcp.deepwiki.com/mcp` | **sans connexion** (« free, remote, no-authentication-required ») | aucune | lire la documentation d'un dépôt public, poser une question (lecture) | gratuite | **1** (sans compte) |
| monday.com | `https://mcp.monday.com/mcp` | inscription automatique ou document de métadonnées | aucune chez monday.com, mais **l'administrateur de chaque compte client doit approuver** un client qui n'est pas dans leur liste (fonction en accès anticipé) | plus de 60 outils (tableaux, éléments, documents / création et modification) | non indiqué | 1, **hors du catalogue** pour le moment (approbation par compte) |
| Vercel, Cloudflare, Webflow | `https://mcp.vercel.com`, `https://mcp.cloudflare.com`, `https://mcp.webflow.com/mcp` | inscription automatique (métadonnées) | non indiquée | outils de développeurs (déploiements, sites) | non indiqué | 1, hors du catalogue (développeurs ; utilisables avec « Ajouter un connecteur personnalisé ») |
| PayPal | `https://mcp.paypal.com/http` | inscription automatique (métadonnées) ou identifiants d'application | non indiquée | lecture / **paiements réels**, remboursements, factures | compte PayPal | 1 techniquement, **écarté** : actions sur de l'argent réel, conditions développeur non lues |
| HubSpot | `https://mcp.hubspot.com` | OAuth 2.1 et PKCE avec un **connecteur MCP créé** dans un compte HubSpot (identifiant et secret), pas d'inscription automatique | non indiquée | CRM, conversations / `manage_crm_objects` (avec confirmation) et autres | certains outils demandent Marketing Hub ou Revenue Hub Professional | **2** |
| Asana | `https://mcp.asana.com/v2/mcp` | « Dynamic client registration is not supported » : **application « MCP app »** à créer dans la console développeur Asana, distribution « Any workspace » | non indiquée | non listés (`tools/list`) | non indiqué | **2** |
| Dropbox | `https://mcp.dropbox.com/mcp` (bêta) | inscription automatique **seulement pour une liste de clients** (Claude, ChatGPT, Cursor) ; sinon **application Dropbox** à créer (App Console), ou demander l'ajout à la liste au support | non indiquée | fichiers, recherche / créer, déplacer, **supprimer**, partager | non indiqué | **2** |
| GitHub | `https://api.githubcopilot.com/mcp/` | OAuth avec une GitHub App ou une OAuth App propre à l'hôte, pas d'inscription automatique | non indiquée | dépôts, tickets / pull requests, fichiers, workflows | non indiqué | **2** (la GitHub App « Kekeli-Lome » existe déjà) |
| Canva | adresse non publiée dans les pages lues | **application** à créer dans le Developer Portal (Canva MCP activé) | **vérification avant tout lancement public** (marque, noms, OAuth) ; règles d'usage (pas d'entraînement de modèle avec les données Canva) | designs, modèles / créer, modifier, exporter | certaines fonctions demandent Canva Pro | **3** (validation) |
| Google Workspace (Gmail, Drive, Agenda, Docs, Sheets…) | `https://gmailmcp.googleapis.com/mcp/v1`, `https://drivemcp.googleapis.com/mcp/v1`, `https://calendarmcp.googleapis.com/mcp/v1`… | projet Google Cloud et client OAuth « pour agent IA » ; **« Developer Preview », accès réservé au programme d'aperçu** | écran de consentement et règles « User Data Policy » ; vérification de Google pour un usage public (coût non indiqué dans les pages lues ; voir 26.8 pour Gmail) | Gmail : lecture et **brouillons seulement** (pas d'envoi) ; Drive : lire, créer, copier ; Agenda : créer, modifier, supprimer des événements | non indiqué | **3** |
| Microsoft 365 (Outlook, OneDrive, Teams) | serveurs « Work IQ » (aperçu) ; page officielle non lisible le 13 novembre 2026 | application Entra et consentement de l'administrateur ; licence Microsoft 365 Copilot par utilisateur (source secondaire) | non vérifiée | non vérifiés | licence payante | **3** |
| Slack | `https://mcp.slack.com/mcp` | « We do not support … Dynamic Client Registration » ; application Slack avec identifiant fixe ; **seules les applications publiées dans le Slack Marketplace** (ou internes) peuvent utiliser MCP | publication au Marketplace (délai et coût non indiqués) | recherche, lecture / **envoyer un message**, créer un canal, canevas | non indiqué | **3** |
| Figma | `https://mcp.figma.com/mcp` | **seuls les clients du catalogue Figma** (sinon liste d'attente) | entrée au catalogue | design / écriture sur le canevas (gratuite pendant la bêta, puis payante) | 20 appels par mois avec un siège gratuit | **3** |
| Square | `https://mcp.squareup.com/mcp` (bêta) | **liste blanche** de clients, demande sur le forum Square | demande d'ajout | toute l'interface Square | non indiqué | **3** |
| Stripe | `https://mcp.stripe.com` | OAuth sans inscription automatique (métadonnées) ou clé « agent » ; anciennes clés refusées après le 30 novembre 2026 | non indiquée | lecture / `stripe_api_write`, envois d'argent | compte Stripe | **3** (paiements, inscription non publique) |
| Box | `https://mcp.box.com` | identifiants créés par l'**administrateur de chaque entreprise** cliente | non indiquée | fichiers, IA de Box / envoi, dossiers | Enterprise Advanced pour une partie | **3** |
| Supabase | `https://mcp.supabase.com/mcp` | inscription automatique | — | base de données / `execute_sql`, migrations | — | **écarté** : Supabase demande de ne pas le donner aux clients finaux |

Context7 (`https://mcp.context7.com/mcp`) fonctionne sans clé, mais avec 1 000 appels par mois pour un compte gratuit et des limites non publiées pour les anonymes : utilisable seulement comme connecteur personnalisé.

#### Classement

1. **Branchables sans compte ni dépense** (rien à créer par le porteur du projet) : Notion, Linear, Jira et Confluence, Airtable, Miro, Sentry ; sans aucune connexion : Hugging Face et DeepWiki. Techniquement aussi monday.com, Vercel, Cloudflare et Webflow (hors du catalogue, voir le tableau), et tout serveur ajouté par le client avec « Ajouter un connecteur personnalisé » s'il permet l'inscription automatique ou s'il est sans connexion.
2. **Gratuits, mais avec une application développeur à créer par le porteur du projet** : HubSpot, Asana, Dropbox, GitHub (application existante à compléter). Détail des gestes en 26.16.2.
3. **Payants ou validation longue** : Google Workspace (programme d'aperçu, projet Google Cloud, vérification par Google), Microsoft 365 (licence Copilot, application Entra), Slack (publication au Marketplace), Canva (vérification avant lancement public), Figma (catalogue), Square (liste blanche), Stripe, Box. **Rien n'est fait pour ces services sans l'accord du porteur du projet.**

#### 26.16.2 Ce que le porteur du projet devra créer pour la catégorie (2) (plus tard, rien maintenant)

Chaque création est gratuite d'après les pages officielles lues ; **les identifiants ne se collent jamais dans une discussion** : ils iront dans les variables d'environnement protégées du site, quand la mise en ligne sera faite (l'adresse de retour exacte n'existe qu'à ce moment-là : `https://<adresse du site>/connecteurs/retour`).

- **HubSpot** : un compte développeur HubSpot gratuit, puis « Développement » → « MCP Auth Apps » (ou « MCP Connectors ») → créer un connecteur nommé « Kekeli », avec l'adresse de retour ci-dessus. Il donne un identifiant client et un secret (variables `KEKELI_MCP_HUBSPOT_CLIENT_ID` et `KEKELI_MCP_HUBSPOT_CLIENT_SECRET`).
- **Asana** : la console développeur `https://app.asana.com/0/my-apps` → nouvelle application de type « MCP app » nommée « Kekeli », adresse de retour ci-dessus, puis « Manage distribution » → « Any workspace ». Variables `KEKELI_MCP_ASANA_CLIENT_ID` et `KEKELI_MCP_ASANA_CLIENT_SECRET`.
- **Dropbox** : l'App Console de Dropbox → « Create app » → « Scoped access », « Full Dropbox », nom « Kekeli », les permissions de lecture d'abord (`files.metadata.read`, `files.content.read`, `account_info.read`), adresse de retour ci-dessus. Variables `KEKELI_MCP_DROPBOX_CLIENT_ID` et `KEKELI_MCP_DROPBOX_CLIENT_SECRET`. Une application Dropbox commence en mode « développement » (peu d'utilisateurs) : la demande de passage en production est à faire ensuite.
- **GitHub** : rien de nouveau à créer ; la GitHub App « Kekeli-Lome » servira aussi au connecteur GitHub (ajouter l'adresse de retour des connecteurs dans ses réglages à la mise en ligne).

Le code saura brancher un service de la catégorie (2) dès que ses deux variables sont présentes (identifiant fixe au lieu de l'inscription automatique) ; tant qu'elles manquent, le service n'apparaît pas dans le catalogue (règle : rien qui ne fonctionne pas encore).

#### 26.16.3 Construit (13 novembre 2026) : catégorie (1) seulement

**Finition des affiches (commit séparé)** : pour une modification du texte d'une affiche (taille, couleur, prix, date), Kekeli garde l'image de la version précédente et **repose lui-même le texte** (`modificationDeTexte`, `inviteNettoyage`, champ `styles` de la structure, dans `runner/src/documents/images.js` ; taille et couleur par texte dans `kekeli_images.py`). Une affiche dessinée voit son texte **effacé une seule fois** (relu : il ne doit rester aucun texte, sinon nouveau fond), puis l'image propre sert aux versions suivantes sans appel au générateur. Le prix normal occupe au plus 60 % de la largeur, pour que « plus gros » se voie. Essai réel : Gratuite (8 secondes, sans aucun nouveau dessin) et Pro (30 secondes, environ 0,09 $) ; captures avant et après : `docs/captures/connecteurs.md`.

**Connecteurs** :
- **Réglages → Connecteurs** (onglet visible dès que le Runner est réglé) : catalogue de 7 connecteurs (Notion, Airtable, Jira et Confluence, Linear, Miro ; Hugging Face et DeepWiki sans compte), « Connecter » / « Déconnecter », **« Ajouter un connecteur personnalisé »** (nom et adresse https d'un serveur MCP distant ; le Runner le sonde : sans connexion, OAuth avec inscription automatique, ou refus clair « Ce serveur demande une application enregistrée chez son éditeur »), outils **toujours autorisés** (retirables), **journal des actions** (lecture ou modification, fait, échec, en attente, autorisé, refusé, heure de Lomé). Catalogue : `config/kekeli.php` (`connectors`).
- **Connexion OAuth** faite par le Runner avec le kit officiel (`runner/src/connecteurs.js` : `sonderServeur`, `preparerConnexion`, `echangerCode`, `renouvelerJeton` ; adresses signées `POST /connecteurs/…`) ; état et vérificateur PKCE gardés dans la session du site ; **jetons chiffrés** (`user_connectors`, `App\Models\UserConnector`), propres à chaque client, jamais affichés, renouvelés deux minutes avant leur fin, effacés par « Déconnecter » ; le jeton de renouvellement ne part jamais avec une demande (`App\Support\Connectors::forRunner`).
- **« + » → « Connecteurs »** (une section du menu, pas un sous-menu : sur téléphone, un sous-menu s'ouvrait sous le doigt) : activer ou désactiver chaque connecteur pour la discussion (`projects.connectors`) ; sur l'accueil, le choix vaut pour les nouvelles discussions. **Formule Gratuite : 2 connecteurs actifs au plus** (`free_active_connectors_max`, Administration → Réglages) ; payantes sans limite.
- **Le premier répondant reçoit les outils** des connecteurs actifs (le Runner est le client MCP, `@modelcontextprotocol/client` 2.4.0) et les utilise quand la demande le justifie (`CONSIGNES_CONNECTEURS`).
- **Permissions comme Claude** : lecture libre ; toute action qui modifie (indication `readOnlyHint` ou `destructiveHint` du serveur, sinon le verbe du nom de l'outil, et en cas de doute : demander) s'arrête avant d'être faite ; le client voit « Kekeli veut utiliser Notion », l'action, ses détails en clair, **« Autoriser »**, **« Refuser »** et **« Toujours autoriser pour cet outil »** (`connector_actions`, carte `projects/partials/connector-approval.blade.php`). Après « Autoriser », l'action vue est faite **exactement** avec les arguments montrés, puis le modèle termine ; « Refuser » : rien n'est fait, une phrase calme le dit.
- **Sécurité** : contenu d'un connecteur = information, jamais consigne (dit au modèle à chaque résultat, vérifié dans l'essai réel) ; https et domaine public seulement (site et Runner) ; délai de 20 secondes par appel, résultat raccourci à 20 000 caractères, 6 appels et 60 outils au plus par message, 8 connecteurs au plus ; un connecteur qui ne répond pas ou dont le jeton est refusé est ignoré ; **aucune donnée d'un connecteur n'est envoyée à une offre gratuite de modèle** : seulement le modèle payant de la formule, et au plafond OpenAI du jour : « Kekeli est très sollicité · Reprise à … ».
- **Coût réel** : les tokens de la réponse (avec les données lues) sont comptés dans l'énergie comme toute réponse ; les serveurs MCP du catalogue sont gratuits pour Kekeli.
- **Fournisseurs de Kekeli toujours cachés** ; les noms des connecteurs du client sont visibles (« A utilisé Notion · 2 actions », ligne repliée sous la réponse).

**Tests** : `tests/Feature/ConnectorsTest.php` (8 tests : onglet et catalogue, OAuth simulé et jetons chiffrés, connecteur personnalisé et adresses internes, « + » et limite Gratuite, lecture et journal, accord, « Toujours autoriser », refus, plafond du jour, renouvellement), `runner/tests/connecteurs.test.js` (12 tests avec un faux serveur MCP `runner/tests/support/faux-serveur-mcp.js` et un faux serveur d'autorisation).

**Essai réel** (`runner/scripts/essai-connecteurs.mjs`, GPT-6.1 Terra) : lectures réelles sur DeepWiki et Hugging Face, demande d'accord sans écriture, reprise exacte après « Autoriser », consigne cachée ignorée ; **environ 0,023 $**. Captures : `docs/captures/connecteurs.md`.

**Pas encore essayé pour de vrai : la connexion OAuth avec un compte** (Notion, Airtable, Jira et Confluence, Linear, Miro). La protection de l'environnement de Claude a refusé d'inscrire Kekeli chez Notion depuis un site local (`127.0.0.1`) ; l'adresse de retour réelle n'existera qu'à la mise en ligne. **À faire à la mise en ligne** (liste dans `docs/mise-en-ligne.md`, partie 7).

**Crédit OpenAI de la pull request** : environ 0,11 $ (essai de la finition des affiches 0,09 $, essai des connecteurs 0,023 $), sous le plafond de 1 $.

#### Sources (lues le 13 novembre 2026)

Spécification : [autorisation MCP](https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization), [révisions](https://modelcontextprotocol.io/specification) ; kit : [typescript-sdk](https://github.com/modelcontextprotocol/typescript-sdk), [versions](https://github.com/modelcontextprotocol/typescript-sdk/releases) ; [OpenCode, serveurs MCP](https://opencode.ai/docs/mcp-servers/) ; [Notion](https://developers.notion.com/docs/mcp), [Notion, clients](https://developers.notion.com/guides/mcp/build-mcp-client) ; [Linear](https://linear.app/docs/mcp) ; [Atlassian](https://developer.atlassian.com/cloud/rovo-mcp/), [Atlassian, démarrage](https://developer.atlassian.com/cloud/rovo-mcp/guides/getting-started/) ; [Airtable](https://airtable.com/developers/agents/mcp/getting-started.md) ; [Miro](https://developers.miro.com/docs/miro-mcp.md), [Miro, limites](https://developers.miro.com/docs/mcp-usage-and-daily-limits.md), [Miro, outils](https://developers.miro.com/docs/miro-mcp-tools.md) ; [Sentry](https://mcp.sentry.dev/) ; [Hugging Face](https://huggingface.co/docs/hub/hf-mcp-server), [Hugging Face, accès anonyme](https://huggingface.co/blog/building-hf-mcp) ; [DeepWiki](https://docs.devin.ai/work-with-devin/deepwiki-mcp) ; [monday.com](https://developer.monday.com/api-reference/docs/mondaycom-mcp.md), [monday.com, inscription automatique](https://developer.monday.com/api-reference/docs/mcp-dynamic-client-registration.md), [monday.com, approbation](https://developer.monday.com/api-reference/docs/mcp-dynamic-connectors.md) ; [Webflow](https://developers.webflow.com/mcp/) ; [PayPal](https://developer.paypal.com/tools/mcp-server/), [PayPal, outils](https://developer.paypal.com/ai-tools/agent-tools/) ; [HubSpot](https://developers.hubspot.com/docs/apps/developer-platform/build-apps/integrate-with-the-remote-hubspot-mcp-server), [HubSpot, disponibilité](https://developers.hubspot.com/changelog/remote-hubspot-mcp-server-is-now-generally-available) ; [Asana](https://developers.asana.com/docs/using-asanas-mcp-server), [Asana, intégration](https://developers.asana.com/docs/integrating-with-asanas-mcp-server) ; [Dropbox](https://help.dropbox.com/integrations/connect-dropbox-mcp-server) ; [GitHub](https://github.com/github/github-mcp-server/blob/main/docs/remote-server.md) ; [Canva, accès](https://www.canva.dev/docs/apps/mcp/access.md), [Canva, règles d'usage](https://www.canva.dev/docs/apps/mcp/usage-policy.md) ; [Google Workspace](https://developers.google.com/workspace/guides/configure-mcp-servers), [Gmail MCP](https://developers.google.com/workspace/gmail/api/reference/mcp) ; [Slack](https://docs.slack.dev/ai/mcp-server/) ; [Figma](https://developers.figma.com/docs/figma-mcp-server/remote-server-installation/), [Figma, limites](https://developers.figma.com/docs/figma-mcp-server/rate-limits-access/) ; [Square](https://developer.squareup.com/docs/mcp) ; [Stripe](https://docs.stripe.com/mcp) ; [Box](https://developer.box.com/guides/box-mcp/remote), [Box, outils](https://developer.box.com/guides/box-mcp/tools.md) ; [Supabase](https://supabase.com/docs/guides/getting-started/mcp) ; [Context7](https://context7.com/docs). Microsoft 365 : la page officielle n'a pas pu être lue (erreur 404) ; les informations viennent de sources secondaires et restent à vérifier.

### 26.17 Versions et retour en arrière, comme Lovable et Bolt.new (13 novembre 2026)

**Demande du porteur du projet** (après la validation de la pull request 47) : chaque travail réussi de Kekeli sur un site, une application, un agent ou une automatisation crée une version ; on la regarde, on la restaure, rien n'est jamais effacé.

#### Ce que voit le client
- **« Versions »** (icône horloge dans la barre du projet, comme pour les documents) : la liste, de la plus récente à la plus ancienne, avec « Version 5 · Ajout de la page Contact », la date et l'heure de Lomé, l'étiquette « Actuelle ».
- **« Voir »** : l'aperçu montre l'ancienne version sans rien changer, avec un bandeau clair « Vous regardez la version 3 » et les boutons « Revenir à la version actuelle » et « Restaurer cette version ». Pendant ce temps, Kekeli ne reçoit aucun message (il travaille toujours sur la version actuelle).
- **« Restaurer »**, avec une confirmation : une **nouvelle** version « Version 8 · Retour à la version 3 » ; la version d'avant reste dans la liste, la restauration s'annule en restaurant la version précédente.
- **Travail échoué ou arrêté** (« Arrêter », énergie épuisée, plafond du jour) : le projet revient à sa dernière version qui fonctionnait, et la discussion le dit : « Le projet est resté sur la version 4, la dernière qui fonctionnait ». « Continuer » refait alors la demande à partir de cette version (au lieu de reprendre des fichiers à moitié modifiés). **Ceci remplace la règle d'avant** (« ce qui est déjà fait est gardé ») pour les sites, applications, agents et automatisations.
- Dans la discussion, sous le travail : **« Version 5 créée »**.

#### Méthode choisie : une archive figée et nettoyée par version, gardée par le site
Trois méthodes ont été comparées :
1. **Un historique Git dans la sauvegarde** : il prend peu de place (les fichiers identiques ne sont gardés qu'une fois), mais tout l'historique tient dans un seul fichier : s'il est abîmé ou tronqué (limite de 50 Mo des sauvegardes), toutes les versions sont perdues d'un coup ; il fait grossir chaque sauvegarde envoyée après chaque travail ; et le dépôt Git du client (mode développeur) s'y mêlerait.
2. **L'historique Git que le Runner tient déjà dans l'ordinateur** (`/home/user/.kekeli/historique.git`, pour « Voir les modifications ») : il disparaît quand l'ordinateur est effacé (pause de plus de 7 jours) ; impossible d'y compter.
3. **Retenue : une archive par version, figée, rangée par le site à côté de la sauvegarde** (`versions/<projet>/<numéro>.tar.gz`, même disque). Chaque version se suffit à elle-même : une archive abîmée ne touche qu'elle ; aucune dépendance à l'ordinateur ; la restauration est un simple dépôt de fichiers ; et c'est le site, pas l'ordinateur, qui décide ce qui entre dans une version. Le prix : plus de place (chaque version pèse à peu près la taille de la sauvegarde, sans les dépendances) ; d'où la limite par formule.

**Rien de secret** : l'archive d'une version est faite à partir de la sauvegarde **sans** `.env` (seul `.env.example` reste), sans base d'essai (SQLite, PGlite), sans dépendances ni fichiers construits (`node_modules`, `vendor`, `.next`…), sans fichier de clés, et sans aucun fichier dans lequel une clé est trouvée (`App\Projects\ProjectVersions::cleanArchive`, mêmes règles que pour GitHub). Un travail qui ne change aucun fichier ne crée pas de version.

**Remise dans l'ordinateur** (`runner/src/versions.js`, demandée par l'adresse `/apercus` avec `version`) : les fichiers de la version remplacent ceux du projet ; `.env`, la base d'essai et les dépendances restent en place (l'aperçu continue de marcher). « Voir » met d'abord de côté les fichiers actuels (une seule fois), « Revenir » les remet ; « Remettre » (restauration, ou retour après un échec) envoie ensuite une nouvelle sauvegarde au site. L'ordinateur est réveillé si besoin (même chemin que le rallumage de l'aperçu). **Aucun modèle n'est appelé : aucune énergie de modèle** n'est utilisée.

**Titre de la version** : donné par le premier répondant avec l'outil « modifier_site » (argument `version`, dans la même requête, sans coût de plus) ; à défaut, « Création du site » pour la première, puis la première phrase de la demande.

**GitHub** : un projet relié reçoit la restauration comme un **nouveau commit** « Retour à la version 3 », lu directement dans l'archive de la version, avec les fichiers ajoutés depuis retirés ; **jamais d'envoi forcé** (`force` à faux), selon le mode du projet (pull request par défaut).

**Limite** (Administration → Réglages) : **30 versions par projet en Gratuite, 200 en payant**. Au-delà, les plus anciennes sont effacées, **jamais** la version actuelle, une version créée par une restauration ni une version qui a été restaurée. Raison : une version d'un site vitrine pèse de quelques centaines de Ko à quelques Mo (images comprises) ; 30 versions restent sous environ 60 Mo par projet gratuit, une taille raisonnable pour le stockage de Laravel Cloud ; les payants en gardent beaucoup plus, comme chez Lovable.

**Essai réel** (13 novembre 2026, vrai Runner, vrais ordinateurs E2B, vrai agent ; `scripts/essai-versions.php`, résultats dans `docs/captures/versions.md`) : un site vitrine (modèle gratuit) et une application Laravel (GPT-6.1 Terra), une création et 3 modifications chacun ; la version 2 vue dans l'aperçu, le retour à la version actuelle, la restauration de la version 2 puis son annulation : tout est juste, environ 5 secondes par remise dans l'aperçu. Un travail arrêté après l'écriture d'une nouvelle page : le projet est resté sur sa dernière version, la page a disparu de l'aperçu. **Défauts trouvés et corrigés** : avec l'application, le serveur lancé dans `public/` servait encore l'ancien dossier effacé (erreur 500) : les dossiers restent maintenant en place, seuls les fichiers sont remplacés ; les fichiers ignorés par Git (fichiers construits comme `public/build`, absents des sauvegardes) restent en place ; les fichiers remis prennent la date du moment (Laravel ne garde plus ses vues compilées d'avant) ; la phrase « Ce qui était déjà fait est gardé » ne s'affiche plus quand le projet est revenu à sa dernière version. **Crédit OpenAI de la pull request : environ 0,09 $** (plafond 0,50 $).

Repères : `app/Projects/ProjectVersions.php`, `app/Projects/TarArchive.php`, `app/Models/ProjectVersion.php`, `app/Jobs/ApplyProjectVersion.php`, `RunnerClient::applyVersion`, route signée `runner.project-versions.archive`, événement `version_appliquee`, `projects/partials/versions.blade.php`, colonnes `projects.viewing_version`, `projects.version_busy_at`, `agent_runs.version_title`. Tests : `tests/Feature/VersionsTest.php`, `runner/tests/versions.test.js`. Essai réel : `scripts/essai-versions.php`.

### 26.18 Montage vidéo et motion design, comme Claude Code (13 novembre 2026)

**Demande du porteur du projet** (après la validation de la pull request 48) : Kekeli écrit le code qui fabrique la vidéo et le lance dans un ordinateur E2B isolé ; le client reçoit un MP4 (jamais un fichier ZIP), le regarde dans Kekeli, le télécharge, le fait modifier en écrivant.

#### Ce que reçoit le client
- Un **fichier MP4** (H.264 et AAC, lisible partout), dans l'onglet **« Vidéo »** (lecteur avec une image de la vidéo en attendant), la **carte du fichier** dans la discussion (« A créé la vidéo · MP4, Sous-titres »), **« Télécharger »** (MP4, et le fichier de sous-titres SRT quand il y en a), les **versions** comme pour les documents et les images (chaque modification écrite en fait une nouvelle ; les anciennes restent visibles et téléchargeables).
- Sous le lecteur : « Voix off créée par intelligence artificielle » quand c'est le cas (règle d'utilisation d'OpenAI), et **« Médias utilisés et leur licence »** (source, licence, lien vers la page d'origine).
- Types : publicité courte, vidéo promotionnelle animée (motion design), présentation animée, montage des vidéos et des photos du client (couper, assembler, musique, voix, sous-titres), vidéo tirée d'un texte ou d'un document joint (Word, PDF, PowerPoint, lu dans l'atelier des documents).
- **Formats selon l'usage**, choisis par Kekeli si le client ne dit rien : vertical 9:16 (TikTok, Reels, statut WhatsApp), carré 1:1 (publication Facebook ou Instagram), horizontal 16:9 (YouTube, écran, présentation), toujours en 1080 points de large ou de haut, 30 images par seconde.
- **Progression** dans la discussion : « J'écris le script de la vidéo », « J'enregistre la voix off », « Je prépare les images », « Je choisis la musique », « Je monte la vidéo », puis **« Rendu… 60 % »** mis à jour en direct (une seule ligne), avec « Arrêter » qui coupe le rendu dans l'ordinateur.

#### Fonctionnement
- Outil **« creer_video »** du premier répondant (discussion) ; une seule question courte en cas de doute entre une vidéo et une image ; **plus de 30 secondes : plan court d'abord** (scènes et durées, texte affiché, voix off, musique, format) avec « Construire ce plan ». Dans un projet de vidéo, « modifier_document » fait la version suivante (même durée, mêmes images quand la scène ne change pas : les images créées sont gardées avec la version et relues par le Runner, sans les recréer ni les repayer).
- **Le modèle écrit le scénario, jamais le code** (comme pour les documents) : un JSON (scènes, textes affichés, phrases de la voix, médias, musique, couleurs, format), nettoyé par `scenarioPropre` (valeurs connues, textes courts, durées bornées, fichiers du client vérifiés, rien d'écrit deux fois). **Kekeli écrit ensuite le code de la vidéo** (`runner/src/videos/code.js`) : un projet Revideo complet, scène par scène, qui appelle sa bibliothèque de montage (`socle/src/kekeli.tsx`) ; chaque texte y est recopié comme une donnée (`JSON.stringify`), jamais comme du code.
- **Le texte affiché est toujours posé par le code** (police Inter), jamais dessiné par un modèle d'images : montants en FCFA, « Mixx by Yas » et « Flooz », numéros exacts. Chaque texte est **réduit jusqu'à tenir** dans sa place (jamais coupé ni hors de l'écran) ; un texte encore trop grand est signalé dans les vérifications.
- **Rendu dans un « atelier vidéo » E2B** (modèle « opencode », 2 processeurs et 4 Go : le modèle de base, avec 512 Mo, ne peut pas faire tourner le navigateur de rendu) : Node 22, FFmpeg, ImageMagick, police Inter, Revideo 0.11.0 et Chrome sans écran 153.0.8010.12, installés en environ 55 secondes, **puis Internet coupé**. **Aucune clé n'y entre** : le Runner écrit le scénario, crée la voix, cherche et télécharge les médias, puis les copie dans l'atelier ; l'atelier fait le rendu des images, le mixage du son et les vérifications. Un atelier sert 20 minutes à un même projet (une modification se rend en environ 1 minute).
- **Durée la plus longue par formule** (Administration → Réglages) : **30 secondes en Gratuite, 3 minutes en payant** au départ. **Durée visée tenue** : le modèle reçoit un nombre de mots maximum pour la voix ; une voix encore trop longue est réécrite une fois par le modèle, puis légèrement accélérée (15 % au plus), puis les scènes sans voix raccourcissent.
- **Coût réel compté dans l'énergie** : modèle (tokens), voix (durée réelle × prix par minute réglable), images créées (prix réglable), ordinateur (temps de marche). Énergie épuisée ou « Arrêter » : tout s'arrête aussitôt (« Énergie épuisée · Recharge à 00 h 00 »). Plafond OpenAI du jour atteint : pas de voix off (textes et musique seulement). **Fournisseurs cachés** au client.

#### Le moteur : comparaison (sources officielles lues le 13 novembre 2026)
| Moteur | Licence | Rendu sans écran depuis un programme | Décision |
|---|---|---|---|
| **Remotion** (remotion.dev) | Licence propre à Remotion : gratuite pour les particuliers et les entreprises de 3 personnes au plus ; au-delà, licence d'entreprise payante ; les applications qui créent des vidéos pour leurs utilisateurs (« Automators ») paient **0,01 $ par rendu, 100 $ par mois au minimum** (remotion.dev/license, remotion.pro) | Oui (`renderMedia`) | **Écarté** : payant pour un service comme Kekeli |
| **Motion Canvas** (motioncanvas.io) | MIT | Non prévu : le rendu passe par l'éditeur dans un navigateur ; dernière version stable du 14 décembre 2024 | **Écarté** |
| **Revideo** (github.com/midrender/revideo, docs midrender.com/revideo/docs) | **MIT** (fichier LICENSE du dépôt ; paquets npm `@revideo/*` 0.11.0 du 10 juillet 2026, licence MIT) | **Oui** : `renderVideo()` depuis Node, avec Chrome sans écran (Puppeteer) et FFmpeg | **Retenu** |

Revideo est un dérivé de Motion Canvas (même façon d'écrire les animations en TypeScript), pensé pour le rendu par programme. **La licence MIT permet un service commercial comme Kekeli**, sans redevance. Son envoi de statistiques est coupé (`DISABLE_TELEMETRY=true`, et l'atelier n'a pas Internet). FFmpeg (licence LGPL ou GPL selon la version de Debian) est utilisé comme un programme séparé, sans être modifié ni distribué. Versions fixées : Revideo 0.11.0, Chrome sans écran 153.0.8010.12, à mettre à jour volontairement après essai. **Deux réglages trouvés à l'essai** : Chrome doit être la version « headless shell » (le Chrome complet ne démarre pas en processus unique, que Revideo impose) ; les modules du moteur sont préparés dès le départ (sinon le premier rendu est interrompu une fois).

#### Les médias, dans cet ordre
1. **Les fichiers du client** joints avec « + » : photos (« Ajouter une image »), vidéos et sons (« Ajouter un fichier » : MP4, MOV, WebM, MP3, M4A, WAV, OGG ; 50 Mo au plus par fichier, 3 fichiers par message) ; **« Enregistrer ma voix »** dans le même menu (micro du téléphone, 3 minutes au plus). Les vidéos du téléphone (souvent HEVC ou tournées) sont converties en H.264 dans l'atelier ; leur son d'origine est gardé pour un montage sans voix off.
2. **Les images créées par Kekeli** : générateur gratuit (Cloudflare Workers AI, FLUX.1 schnell), **sans texte** (consignes : pas d'enseigne, d'écran, de panneau), au plus 6 par vidéo (réglable), comptées dans le maximum du jour ; quand le quota gratuit est épuisé, **fond animé** aux couleurs de la vidéo (le travail continue).
3. **Pexels et Pixabay**, par leurs interfaces officielles, **seulement depuis le Runner** (clés `PEXELS_API_KEY` et `PIXABAY_API_KEY`, jamais dans l'ordinateur ; la clé de Pixabay passe dans l'adresse, c'est pourquoi l'ordinateur ne l'appelle jamais). Licence Pexels : utilisation gratuite, commerciale permise, sans obligation de citer (pexels.com/license ; l'interface demande un lien visible vers Pexels, donné dans « Médias utilisés »). Licence du contenu Pixabay : gratuite, commerciale permise, sans obligation de citer ; l'interface demande de télécharger les fichiers sur son propre serveur (pas de lien direct) et de ne pas garder les résultats de recherche plus de 24 heures (pixabay.com/api/docs, pixabay.com/service/license-summary). **Clés absentes le 13 novembre 2026** : tout a été construit et testé avec des réponses simulées ; **l'émission de nouvelles clés Pexels est suspendue** par Pexels à cette date (page officielle de l'interface) ; la clé Pixabay se crée gratuitement.
4. **La musique** : **musique libre CC0** (domaine public, aucune obligation, usage commercial permis) cherchée avec **Openverse** (api.openverse.org, sans compte), surtout sur **Freesound** ; seules les pistes marquées « cc0 », assez longues et qui sont de la musique sont gardées (titre, auteur, page et licence notés). Openverse précise qu'il ne vérifie pas lui-même les licences : la licence affichée est celle de Freesound, la source d'origine. Pixabay Music n'a pas d'interface officielle : écarté. Si rien n'est trouvé, **musique écrite par Kekeli** (accords et rythme produits par FFmpeg, aucun droit). Jamais de téléchargement libre « de tout Internet ».
5. **La liste des médias** avec leur source et leur licence est gardée avec chaque version (`medias.json`) et affichée sous le lecteur.

#### Voix et sous-titres
- **Voix off de Kekeli** : `gpt-4o-mini-tts` d'OpenAI (voix « marin », recommandée avec « cedar » par OpenAI pour la meilleure qualité ; ton donné par des consignes : français naturel, accent neutre), **environ 0,015 $ la minute** (estimation officielle ; 0,60 $ par million de tokens de texte, 12 $ par million de tokens audio). Ce modèle sera arrêté par OpenAI le 6 janvier 2027 : réglage `video_voice_model` à changer d'ici là. Une phrase par scène : la **durée réelle** de chaque phrase donne des **sous-titres exacts**. Les montants restent en chiffres dans les sous-titres et sont dits comme on les dit (« francs CFA »).
- **Voix du client** (fichier ou « Enregistrer ma voix ») : placée telle quelle, les scènes suivent sa durée ; sous-titres par **transcription mot à mot** (`whisper-1`, 0,006 $ la minute ; c'est le modèle d'OpenAI qui donne le temps de chaque mot).
- **Sous-titres** : grands, blancs sur fond sombre arrondi, en bas (au centre bas pour une vidéo verticale, au-dessus des boutons des réseaux sociaux), par morceaux de 7 mots au plus, jamais un numéro ou un montant coupé ; désactivables en le demandant ; livrés aussi en fichier SRT.
- **Son propre** : la musique baisse toute seule sous la voix (compression par la voix), volume final réglé (−15 LUFS), limiteur à environ −2 dB : **aucune saturation**.

#### Qualité vérifiée
Accroche dans les 2 premières secondes pour une publicité verticale ; textes courts et lisibles, jamais coupés ; fondus enchaînés sobres ; couleurs cohérentes (un fond profond, un texte blanc, une couleur d'accent) ; fin avec un appel à l'action (commander, s'inscrire, appeler) ; contenu interdit (personne réelle identifiable, personnage protégé, marque d'une autre entreprise, contenu choquant ou trompeur) : refus poli et une autre proposition. **Après chaque rendu, l'atelier vérifie** : durée, crête du son, silences anormaux, textes trop longs, et extrait 3 images (aperçu).

#### Essai réel (13 novembre 2026, `docs/captures/videos.md`)
Quatre vidéos, vrai modèle, vraie voix, vrais ordinateurs E2B : (a) publicité TikTok verticale de 25 secondes pour une boutique de pagnes, de bout en bout par le site (vrai premier répondant), puis une version 2 (« prix plus gros, sans sous-titres ») ; (b) motion design de 50 secondes pour une école, avec son plan ; (c) montage de 3 vidéos et 2 photos avec une musique africaine ; (d) introduction YouTube de 10 secondes. **Défauts trouvés et corrigés pendant l'essai** : vidéos plus longues que demandé (limite de mots, réécriture une fois, voix légèrement accélérée) ; premier rendu interrompu une fois (modules préparés dès le départ) ; mixage impossible sans voix (disposition stéréo précisée pour FFmpeg 5.1) ; limiteur qui remontait le son jusqu'à 0 dB ; promesses inventées par le modèle (« livraison rapide », « livraison gratuite ») ; prix et numéro écrits deux fois ; numéro coupé dans les sous-titres ; images créées avec des enseignes.

**Coût par vidéo** (estimation, au prix payant, 1 $ = 590 FCFA) : publicité de 25 secondes environ **9 à 16 FCFA** ; modification environ **7 FCFA** ; motion design de 50 secondes avec voix environ **24 FCFA** ; montage de 25 secondes sans voix environ **7 FCFA** ; introduction de 10 secondes environ **5 FCFA**. Le plus gros poste est la voix (environ 9 FCFA la minute) puis l'ordinateur (environ 1,6 FCFA la minute de marche). **Durée de rendu** : 1 à 3 minutes (dont environ 55 secondes pour préparer un nouvel atelier). **Crédit OpenAI dépensé pour toute la pull request : environ 0,25 $** (plafond 1 $).

Repères : `runner/src/videos/` (`index.js`, `scenario.js`, `code.js`, `medias.js`, `voix.js`, `socle/`), `app/Agent/Tools/CreateVideo.php`, `RunnerClient::videoRequest`, `DocumentController::video`, `RunnerDocumentController::store` (réception en morceaux) et `media`, `projects/partials/document-preview.blade.php`, `resources/js/enregistreur.js`, colonne `agent_runs.voice_cost_usd`, réglages `video_*`. Tests : `tests/Feature/VideosTest.php`, `runner/tests/videos.test.js`. Essais : `runner/scripts/essai-video.mjs`, `scripts/essai-videos.php`.

### 26.19 Optimisations internes (13 novembre 2026)

**Demande du porteur du projet** (après la validation de la pull request 49) : finitions des vidéos, identité de Kekeli, et quatre optimisations, chacune **mesurée avant et après** (qualité et coût) sur les jeux d'essai ; ce qui n'améliore rien est annulé. Captures, vidéos et tableau : `docs/captures/optimisations.md`. Crédit OpenAI de la pull request : environ 0,74 $ (plafond 1 $).

#### Finitions des vidéos
- **Images** : formules payantes, le générateur des affiches payantes (`image_models`, GPT Image 2.6 « flare ») en qualité « basse » (0,018 $ l'image, mesuré ; la qualité « moyenne », 0,032 $, ne se distingue pas dans une vidéo), puis les banques libres, puis le générateur gratuit ; Gratuite, le générateur gratuit puis Pixabay (clé `PIXABAY_API_KEY` présente). **Le fond animé n'est plus que le dernier recours** : une scène qui décrit une image en reçoit une. Pixabay : photos seulement (les vidéos commençaient parfois noires), mots-clés de la demande vérifiés dans les étiquettes, jamais de marque, de logo, d'écran ni de panneau, jamais deux fois la même image.
- **Sous-titres** : une phrase entière à la fois (coupée seulement à une virgule si elle est très longue, jamais dans un montant ou un numéro) ; jamais le texte déjà affiché à l'écran dans la même scène ; le bloc du titre remonte toujours au-dessus de la zone des sous-titres. Mesure sur les scénarios réels : 12 coupures et 11 répétitions avant, aucune après.
- **Voix** (demande du porteur du projet pendant la pull request : « Utilise les voix libres de Gemini ») : voix gratuites de Gemini (`gemini-3.8-flash-tts` puis `gemini-3.8-flash-lite-tts`, voix « Kore », interface « Interactions » de Google AI Studio, vérifiée sur les pages officielles le 13 novembre 2026), comprises mot pour mot à la retranscription ; la voix d'OpenAI ne sert plus qu'en secours. **Limite de l'offre gratuite constatée à l'essai : 3 phrases par minute et 10 phrases par jour par voix**, soit environ 3 vidéos par jour pour tout Kekeli ; une seule voix par vidéo (même timbre), et si la voix gratuite ne peut pas finir, toute la voix off est refaite par la voix payante (si le plafond du jour le permet), sinon la vidéo n'a pas de voix (jamais une voix à moitié). Offre gratuite : les textes peuvent servir à Google pour améliorer ses produits (comme le reste de l'offre gratuite).
- **Remplacement de la voix d'OpenAI avant le 6 janvier 2027** (page officielle des retraits d'OpenAI, lue le 13 novembre 2026) : `gpt-4o-mini-tts`, `tts-1` et `tts-1-hd` s'arrêtent le **6 janvier 2027** ; le remplaçant recommandé est **`gpt-realtime-2.1-mini`**, qui **ne fonctionne pas** avec l'adresse actuelle (`/v1/audio/speech`) : il faut l'interface « Realtime » (connexion WebSocket), et sa sortie audio coûte 20 $ par million de tokens au lieu de 12 $. **Pas fait maintenant** (changement d'interface, pas un simple réglage). À faire avant le 6 janvier 2027 : soit passer la voix de secours à l'interface « Realtime », soit la remplacer par une voix payante de Gemini (`gemini-3.8-flash-lite-tts` payant : 6 $ par million de tokens audio jusqu'au 31 décembre 2026, puis 12 $), à mesurer et à décider avec le porteur du projet.

#### Identité
« Qui es-tu ? » : « Je suis Kekeli, un assistant IA créé à Lomé par Lomé IA School… » ; « Qui t'a créé ? » : Lomé IA School, école de formation en IA à Lomé ; « Qui est ton fondateur ? » : Joseph Ahiankonou (Coach Joseph), fondateur de Lomé IA School ; « Quel modèle ? Es-tu ChatGPT ou Claude ? » : « Je suis Kekeli ; j'utilise plusieurs technologies d'IA choisies pour chaque tâche. » (`IDENTITE` dans `runner/src/repondant.js`, filet de sécurité `identiteSure` qui remplace « Je suis ChatGPT » ou « développé par OpenAI »). Mesure (6 phrases, GPT-6 Luna, GPT-6.1 Terra, Gemini 3.5 Flash-Lite) : 6 sur 18 avant, 18 sur 18 après (`runner/mesures/identite-*.json`).

#### Optimisations
| Optimisation | Qualité avant → après | Coût avant → après | Décision |
|---|---|---|---|
| **Compétences** : les règles du premier répondant rangées par capacité (`runner/src/competences.js`), chaque lieu ne charge que celles de ses outils, texte inchangé (vérifié : sans filtre, exactement les consignes d'avant) | 34 → 35 bonnes décisions sur 36 (même jour, mêmes modèles gratuits) | 4 372 → 2 704 tokens par message dans un projet de site (38 % de moins), 8 411 caractères de consignes dans un projet de document au lieu de 16 796 | Gardée |
| **Modèle selon la difficulté** : argument « ampleur » de « modifier_document » ; une petite retouche (couleur, texte, prix, date, faute) passe par `small_edit_models` (Terra en payant, Luna en Gratuite) | 8 sur 8 retouches justes avec chaque modèle ; classement « petite » ou « grande » juste 6 fois sur 6 | 0,056 → 0,027 $ (payant), 0,017 → 0,003 $ (Gratuite) pour les 8 retouches | Gardée pour les documents, présentations, images et vidéos ; **pas pour les sites** (non mesuré : le banc des sites avec GPT-6.1 Sol dépassait le budget de la pull request) |
| **Résumé des longues discussions** : au-delà de 10 messages, les 6 derniers entiers et le début résumé par le modèle économique (noms, prix, numéros, décisions mot pour mot), refait par paquets de 4, invisible pour le client (`runner/src/resume.js`, `RunnerClient::historyWithSummary`, colonnes `projects.conversation_summary`, `summary_until_message_id`) | 0 → 3 faits retrouvés sur 3 (avant, le début était perdu) | 5 528 → 5 265 tokens par message (5 % de moins), plus 0,00026 $ par résumé | Gardée |
| **Relecteur à neuf des vidéos** : un modèle qui n'a pas écrit le scénario le relit avec la seule demande ; un défaut (promesse inventée, prix, numéro, date faux) : correction une fois avant le rendu (`runner/src/videos/relecture.js`) | 0 → 4 défauts trouvés sur 4, aucune fausse alerte sur 2 scénarios justes | + 0,0001 $ par vidéo (modèle économique) | Gardé |
| **Relecteur des faits des sites** : les prix, numéros et adresses e-mail de la demande doivent apparaître sur le site | 0 fait oublié sur 40 sans lui (3 constructions et 2 modifications réelles, modèle gratuit) | 0 | **Annulé** : n'améliore rien de mesurable (mesures gardées dans `runner/mesures/relecteur-sites/`) |
| Relecteurs des applications, des agents et des automatisations | Déjà en place : tests, sécurité et application qui répond (applications) ; tests de l'agent avec ses refus (agents) ; **« Lancer maintenant » n'est jamais lancé tout seul** : il enverrait de vrais messages avec les clés du client sans son accord | — | Rien de plus |

### 26.20 Automatisations par événement et équipes d'agents (13 novembre 2026)

**Demande du porteur du projet** (après la validation de la pull request 50) : images libres plus fréquentes dans les vidéos de la Gratuite, automatisations lancées par un événement, équipes d'agents ; mesures avant et après, et ce qui n'améliore rien est annulé. Rappel : **Kekeli écrit le code ; tout tourne chez l'utilisateur** (son GitHub, son compte Cloudflare, ses clés), jamais chez Kekeli. Captures : `docs/captures/evenements-equipes.md`.

#### Finition : images libres des vidéos en Gratuite
- Le réalisateur donne pour chaque scène, en plus de sa recherche précise, 2 ou 3 **recherches larges** (texture ou tissu, objet du sujet, lieu, ambiance) ; Kekeli essaie jusqu'à 6 recherches, de la plus précise à la plus large, chacune jugée sur ses propres mots (étiquettes de Pixabay), toujours sans marque, sans écran, sans panneau ni texte, jamais deux fois la même image ; puis une **vidéo Pixabay** si aucune photo ne convient (sa première seconde est sautée, parfois noire). Le fond animé reste le tout dernier recours.
- **Mesure** (5 sujets variés : boutique de pagnes, école, maquis, garage, formation en couture ; 15 scènes écrites par le modèle gratuit, `runner/scripts/mesurer-pixabay.mjs`, `runner/mesures/pixabay-mesure.json`) : **11 scènes sur 15 avec une vraie image avant, 15 sur 15 après** (toutes des photos, toutes vérifiées à l'œil : tissus, salle de classe, poisson grillé, atelier de moto, machine à coudre). Gardée.

#### Automatisations déclenchées par un événement : étude (sources officielles, lues le 13 novembre 2026)
Trois façons gratuites, sur les comptes du client :

| Façon | Sécurité | Délai | Limites gratuites | Simplicité pour un débutant |
|---|---|---|---|---|
| **A. Appel direct de GitHub** (`repository_dispatch` ou `workflow_dispatch`) par le service | Il faut un jeton GitHub du client chez l'expéditeur : impossible dans un formulaire de site (le navigateur montrerait le jeton) | Le temps de démarrage de GitHub Actions | Celles de GitHub Actions (ci-dessous) | Simple seulement pour un serveur qui sait ajouter un en-tête |
| **B. Point d'entrée sur Cloudflare Workers** (compte du client) qui vérifie l'appel puis lance `repository_dispatch` | Le secret et le jeton GitHub restent dans les « secrets » du Worker ; signature HMAC avec heure (rejeu refusé), ou secret, ou formulaire d'un site permis avec champ piège et limite d'envois ; jamais deux fois le même événement | En général moins d'une minute (point d'entrée immédiat, puis démarrage de GitHub Actions) | Workers Free : **100 000 appels par jour**, 10 ms de calcul par appel, 50 sous-requêtes ; Workers KV : 100 000 lectures et **1 000 écritures par jour** (environ 500 événements par jour) | Un compte Cloudflare, une clé Cloudflare, un jeton GitHub : trois gestes guidés pas à pas, puis tout se met en ligne tout seul |
| **C. Vérification régulière** (`schedule` toutes les X minutes) | Rien d'ouvert sur Internet ; les identifiants déjà traités gardés par `actions/cache` | X minutes (5 au minimum), retards possibles aux heures chargées | Celles de GitHub Actions ; chaque vérification consomme des minutes | Le plus simple (aucun compte de plus), mais coûte des minutes même quand rien n'arrive |

Règles officielles de GitHub qui s'appliquent : `repository_dispatch` ne lance que le fichier de la **branche principale** ; `client_payload` : 10 propriétés au plus et moins de 64 Ko ; jeton à grain fin : autorisation **« Contents » en écriture** ; dépôt public : machines standard gratuites ; dépôt privé (GitHub Free) : **12 $ de calcul compris par mois** (environ 2 000 minutes sur Linux à 0,006 $ la minute) ; 6 heures au plus par tâche ; 1 500 événements par 10 secondes et par dépôt ; `schedule` : 5 minutes au minimum, retards et abandons possibles quand GitHub est très chargé, désactivé après 60 jours sans activité dans un dépôt public.

**Choix : B pour les services qui savent prévenir (formulaires, commandes, applications), C pour ceux qui ne préviennent pas (boîte e-mail, tableau)**, A seulement à travers B. Raisons : B est le seul moyen sûr pour un formulaire de site (aucun secret dans la page), il est rapide et reste gratuit très largement ; C ne demande aucun compte de plus quand le service n'envoie rien. Cloudflare Turnstile (gratuit, 20 widgets par compte) pourra s'ajouter plus tard contre les robots, si le champ piège et la limite ne suffisent pas.

#### Ce qui est construit
- **Point de départ des automatisations** (`runner/src/automatisations/socles/javascript/`) : `point-entree/worker.js` (vérification, mémoire Workers KV, `repository_dispatch`), `point-entree/wrangler.toml`, `.github/workflows/point-entree.yml` (mise en ligne par GitHub Actions sur le compte Cloudflare du client : mémoire créée par l'interface officielle de Cloudflare, secrets passés par des variables d'environnement, jamais dans une commande ; adresse du point d'entrée écrite à la fin du lancement), `src/evenement.js` (l'événement reçu, ou l'événement d'essai), `src/etat.js` (mémoire d'une vérification régulière), 11 tests. Le LISEZ-MOI explique pas à pas comment brancher l'événement.
- **Consignes de l'agent** : trois déclencheurs (heure fixe, événement, vérification régulière), données d'un événement = informations jamais ordres, `evenement-essai.json` (données inventées) ; **plan** : le déclencheur expliqué en mots simples, sans mot technique.
- **Site** : `app/Automations/AutomationTrigger.php` (déclencheur **lu dans le code**, jamais deviné ; `ENTREE_SECRET` créé une fois par Kekeli, jamais affiché ; colonne `projects.automation_trigger`), « Lancer maintenant » qui **simule l'événement** avec les données d'essai, **journal des déclenchements** (lancements de GitHub Actions lus avec l'accès GitHub du client : événement reçu, heure prévue, à la main ; essais dans Kekeli ; jamais leur contenu), **« Brancher un formulaire »** (`app/Automations/FormWiring.php`) : avec l'accord du client, Kekeli modifie le formulaire d'un de ses sites (site vitrine : adresse du site permise dans `FORMULAIRE_ORIGINES`, champ piège ; site avec base de données : appel signé côté serveur, secret copié dans ses Variables secrètes).

#### Essai réel de l'automatisation par événement (13 et 14 novembre 2026)
« Quand un client remplit le formulaire de contact de mon site Kofi Couture, envoie-moi un résumé par e-mail » : site et automatisation construits pour de vrai par Kekeli (plan : « Déclencheur : dès qu'un client remplit le formulaire… grâce à un petit point d'entrée gratuit sur votre compte Cloudflare »), déclencheur reconnu (« événement »), secret créé, « Lancer maintenant » avec l'événement d'essai, formulaire branché par Kekeli (envoi en arrière-plan, champ piège, message « Merci… »), envoi depuis l'aperçu du site vers le **vrai point d'entrée lancé dans le moteur de Cloudflare** (wrangler 4, sur l'ordinateur de Claude), automatisation lancée comme par GitHub Actions. **Un seul lancement par envoi, envoi rejoué ignoré, appel d'un autre site refusé (401)**. Seul l'e-mail n'est pas parti (pas de clé Resend pendant l'essai). Trois défauts trouvés et corrigés : le fichier principal d'un Worker ne peut exporter que son point d'entrée (`worker.js` et `coeur.js`) ; le site envoyait « message » alors que l'automatisation lisait « demande » (Kekeli donne maintenant au site les noms exacts des champs de l'événement d'essai) ; nouvelles classes de mise en page à construire avec l'interface. Ce qui reste à faire avec de vrais comptes : `docs/mise-en-ligne.md`, partie 8. Captures : `docs/captures/evenements-equipes.md`.

**Crédit OpenAI dépensé pour cette pull request** : environ 0,35 $ (deux agents, un site, une automatisation et deux branchements construits avec GPT-6.1 Terra, plus les plans), sous le plafond de 0,75 $ ; toutes les réponses des agents et la mesure Pixabay avec les offres gratuites.

#### Équipes d'agents
- **Plan** : pour une demande à plusieurs rôles bien distincts, Kekeli propose une équipe (un agent par rôle avec sa mission, un coordinateur, le chemin d'une demande) ; pour une demande simple, un seul agent. Technologie : LangGraph (JavaScript) par défaut, CrewAI si l'utilisateur veut Python, technologie imposée respectée.
- **Point de départ LangGraph** : `agent/equipe.js` (liste `MEMBRES` à remplir ; coordinateur et graphe déjà écrits et testés : le coordinateur choisit qui agit, chaque membre fait seulement sa mission avec ses propres outils, la réponse vient toujours du membre « final », jamais deux fois de suite le même membre, 6 actions au plus par message) ; le serveur passe à l'équipe dès 2 membres. Tests de l'équipe avec pièges (consigne à révéler, article inconnu, quantité au-delà du stock).
- **« Tester l'agent »** : sous chaque réponse, une ligne repliable « Équipe : Prise de commande → Stock → … » (seulement dans l'aperçu de Kekeli, réglage `AFFICHER_PARCOURS`), aussi pour CrewAI.
- **Essai gratuit** : une équipe appelle le modèle plusieurs fois par question ; l'essai compte **un seul message par question du visiteur** (le relais reconnaît le même message, 12 appels au plus), et chaque appel reste compté pour le fournisseur dans l'administration.
- **Défaut corrigé, trouvé pendant l'essai** (existait avant cette pull request) : Gemini 3 refuse la suite d'une conversation quand on ne lui renvoie pas la « signature » de chaque appel d'outil, que LangChain efface. **Tout agent avec des outils échouait** avec le modèle gratuit de Kekeli et avec une clé Gemini du client. Corrigé dans le relais du Runner (essai gratuit) et dans le point de départ (`agent/signatures.js`).
- **Défaut corrigé, trouvé par la mesure** : « Êtes-vous ouverts le dimanche ? » ne retrouvait pas « Ouvert du lundi au samedi » dans les documents (pluriel) ; les documents des agents reconnaissent maintenant le singulier simple et le sujet des horaires (LangGraph et CrewAI).

**Mesure** (`scripts/essai-equipe.js`, `runner/mesures/equipe-vs-seul.json`) : la même demande (boutique Ama Pagnes : commande, stock, facture, réponse, catalogue et stock donnés), construite pour de vrai par Kekeli avec GPT-6.1 Terra, une fois avec le plan d'équipe proposé par Kekeli (4 agents : commande, stock, facture, clientèle), une fois avec un seul agent ; 10 questions d'essai avec des pièges, posées dans une conversation neuve chacune, avec le modèle gratuit de l'essai.

| | Agent seul | Équipe |
|---|---|---|
| Réponses justes | **8 sur 10** | **9 sur 10** |
| Erreurs | dimanche (horaires non retrouvés) ; réduction refusée mais total de 46 000 FCFA non donné | dimanche (horaires non retrouvés) |
| Pièges (stock dépassé, rupture, consigne secrète, réduction, article absent) | tous refusés ou bien traités | tous refusés ou bien traités |
| Construction | 3,1 minutes | 3,8 minutes |
| Appels au modèle par question (estimation) | 1 ou 2 | 2 ou 3 (le coordinateur, puis le membre) |

**Décision : l'équipe est gardée** (elle fait mieux sur la mesure, comme le demande la règle). **À savoir honnêtement** : l'écart n'est que d'une question, et pendant ces 10 questions le coordinateur a tout confié à l'agent clientèle (avec l'outil de stock) ; l'écart vient donc sans doute de la manière dont l'équipe a été écrite plus que du passage d'un agent à l'autre. Une équipe coûte environ deux fois plus d'appels au modèle (payés par le client avec sa propre clé). À mesurer de nouveau sur plus de questions, avec de vraies commandes enregistrées.

### 26.21 Mémoire, comme Claude et ChatGPT, et finitions de la pull request 51 (14 novembre 2026)

**Demande du porteur du projet** (après la validation de la pull request 51) : finitions des agents, puis la mémoire ; mesures avant et après, et ce qui n'améliore rien est annulé. Captures : `docs/captures/memoire.md`. **Crédit OpenAI dépensé pour cette pull request : environ 0,20 $** (plafond 0,50 $) : 0,146 $ pour deux constructions réelles de l'agent Ama Pagnes avec GPT-6.1 Terra et leurs plans, 0,055 $ pour la mesure du choix d'équipe, 0,001 $ pour la mesure de l'extraction ; la mesure de la mémoire n'a rien coûté (modèles gratuits).

#### Finition 1 : réponses des agents sans astérisques
- La page de discussion des agents créés (et la bulle, qui l'ouvre dans un cadre) affichait le texte brut : « **25 000 FCFA** ». `public/texte.js` (points de départ LangGraph et CrewAI) met maintenant en forme le gras, l'italique, les listes à puces ou numérotées et les retours à la ligne ; chaque morceau est posé comme du texte (jamais de HTML accepté, aucun `innerHTML`) ; titres, lignes de séparation et signes restés seuls disparaissent. Tests dans le point de départ (`tests/page.test.js`, et le test du serveur CrewAI).
- Consigne des agents : écrire simplement, en phrases courtes, gras et courtes listes permis, jamais de tableau ni de titre ; **répondre d'abord** (un total ou une facture tout de suite, puis demander ce qui manque) ; après un refus (réduction, stock), proposer aussitôt la solution chiffrée.
- Vérifié sur l'agent « Boutique Ama Pagnes » reconstruit pour de vrai : **aucune bulle avec un astérisque visible** (téléphone et ordinateur, clair et sombre).

#### Finition 2 : une équipe seulement pour des rôles vraiment séparés
- Nouvelle règle : **un seul agent par défaut**, même quand la demande cite plusieurs tâches faites pendant la même conversation (répondre, prendre la commande, vérifier le stock, calculer la facture : ce sont ses outils) ; une équipe seulement pour des rôles distincts qui travaillent séparément, pour des personnes ou à des moments différents, ou quand le client demande plusieurs agents.
- **Mesure** (`runner/scripts/mesurer-choix-equipe.mjs`, `runner/mesures/demandes-equipe.json`) : 6 demandes (3 simples, dont la boutique de pagnes « Il prend les commandes, vérifie le stock, prépare la facture et répond » ; 3 vraiment complexes : supermarché avec service client, bilan du soir et publications de la semaine, cabinet de recrutement, journal en ligne), avec Gemini 3.5 Flash-Lite, GPT-6 Luna et GPT-6.1 Terra : **15 bons choix sur 18 avant, 18 sur 18 après** (avant, la boutique recevait une équipe avec les trois modèles, car l'ancienne règle citait justement cet exemple).
- **Qualité de l'essai Ama Pagnes** (mêmes 10 questions avec pièges, modèle gratuit de l'essai, `scripts/essai-ama-pagnes.js`) : l'équipe faisait 9 sur 10 ; un premier agent seul construit avec la nouvelle règle faisait **8 sur 10** (la facture et la réduction refusée sans total) : baisse refusée, d'où la règle « répondre d'abord » ; reconstruit, **10 sur 10** (`runner/mesures/ama-pagnes-apres.json` ; premier essai gardé dans `ama-pagnes-un-agent-essai-1.json`).

#### Mémoire
- **Ce qui est retenu** : les informations utiles et durables que le client dit lui-même (nom et activité, adresse, numéros, Mixx by Yas et Flooz avec leurs numéros, horaires, produits et prix habituels, couleurs, logo, style, préférences). **Jamais** : mot de passe, code, clé, jeton, numéro de carte ou de compte bancaire, IBAN (même sur demande) ; une information sensible (santé, religion, opinions politiques, origine, vie sexuelle, justice, syndicat) seulement si le client demande explicitement de la retenir ; rien sur une autre personne ; rien de ponctuel ; **rien de déduit** (chaque nombre et chaque e-mail d'un souvenir doivent être écrits dans le message).
- **Deux verrous après le modèle** : `runner/src/memoire.js` (`filtrerOperations` : secrets, valeurs qui suivent un mot de secret, cartes avec la vérification de Luhn, sensible sans demande, nombres inventés, numéros de souvenirs inconnus) puis `app/Memory/UserMemory.php` sur le site.
- **Extraction** : seulement pour un message qui peut contenir une telle information (filtre sans modèle : 18 messages ordinaires sur 52 du jeu du premier répondant), **en même temps que la réponse** (aucune attente de plus), appliquée avant la réponse ou le travail (une affiche demandée dans le même message en profite déjà) ; une réponse illisible fait passer au fournisseur suivant. **Modèle le moins cher qui fait bien le travail**, mesuré sur 14 messages pièges (`runner/scripts/mesurer-memoire.mjs`) : Gemini 3.5 Flash-Lite gratuit 14 sur 14, GPT-6 Luna 14 sur 14, Gemini 3.1 Flash-Lite 12 sur 14 ; réglage `memory_models` : **modèle gratuit pour la Gratuite, GPT-6 Luna pour les formules payantes** (leurs données ne vont jamais à une offre gratuite). Coût compté dans l'énergie.
- **Rappel** : seuls les souvenirs utiles au message vont au modèle (`souvenirsUtiles`) : tous pour une création ou « qu'est-ce que tu sais de moi ? », ceux qui partagent un mot avec le message sinon, **aucun** pour une petite question sans rapport ; bloc de 1 500 caractères au plus. Le modèle ne dit jamais lui-même qu'il a retenu quelque chose (défaut constaté à l'essai : sans mémoire, Kekeli répondait « Votre logo est également enregistré » alors que rien n'était gardé).
- **Réutilisation** : souvenirs ajoutés après la demande du client pour les sites, applications, agents, automatisations, documents, présentations, images et vidéos (jamais pour un dépôt du mode développeur) ; logo gardé, posé tel quel sur les affiches (adresse signée `runner.memory.logo`). Correction faite pendant l'essai : avec un logo, le texte d'une affiche ne monte plus dans la zone du logo (le titre rapetisse s'il le faut).
- **Écrans** : ligne discrète « Mémoire mise à jour », dépliable (« Retenu : … », « Modifié : … », « Oublié : … », « Logo enregistré », « Gérer la mémoire ») ; « retiens que… », « oublie… », « qu'est-ce que tu sais de moi ? » ; **Réglages → Mémoire** : souvenirs, Modifier, Supprimer, logo, « Tout effacer » avec confirmation, interrupteur (désactivée : rien n'est retenu ni utilisé).
- **Sécurité** : souvenirs et lignes « Mémoire mise à jour » **chiffrés en base**, propres à chaque client (jamais un souvenir d'un autre, vérifié par les tests et par la mesure), effacés avec le compte (logo compris).
- **Politique de confidentialité** : un paragraphe sur la mémoire (ce qui est retenu, jamais les secrets, fournisseur qui lit le message, Réglages → Mémoire pour tout lire, modifier, effacer ou désactiver) et sa durée de conservation. **À faire relire par un juriste avant le lancement public.**

#### Mesure de la mémoire (14 novembre 2026)
Un compte inventé (Ama Kodjo, boutique Ama Pagnes) donne une seule fois ses informations, logo joint, avec un mot de passe et une information de santé comme pièges ; puis 6 demandes dans 6 nouvelles discussions sans rien répéter. « Avant » : le même scénario, mémoire désactivée (Kekeli tel qu'il était). Un second compte (Kossi Motos, Kara) vérifie le mélange. Formule Gratuite, modèles gratuits (`scripts/preparer-essai-memoire.php`, `scripts/essai-memoire.js`, `scripts/evaluer-essai-memoire.php`, résultats `runner/mesures/memoire-essai.json`).

| Demande (sans rien redonner) | Avant : informations reprises | Après : informations reprises | Inventé avant | Inventé après |
|---|---|---|---|---|
| Affiche des nouveaux pagnes wax | 0 sur 2, sans logo, couleurs de Kekeli | **2 sur 2** (nom, WhatsApp), prix, Mixx by Yas et Flooz, vert et or, **logo posé** | rien | rien |
| Devis pour 3 pagnes wax livrés | 0 sur 4 (crochets à compléter) | **4 sur 4** (nom, prix 15 000 FCFA, numéros Mixx by Yas et Flooz) | rien | rien |
| Vidéo publicitaire de 15 secondes | 0 sur 1 (« Découvrez notre boutique ») | **1 sur 1** (« Ama Pagnes au grand marché de Lomé »), vert émeraude et or | rien | rien |
| Petite question (encaisser plus vite) | réponse générale | réponse adaptée (« vos numéros Mixx by Yas et Flooz »), sans recopier de numéro inutile | rien | rien |
| Site vitrine | 0 sur 4 | **4 sur 4** (nom, WhatsApp, Mixx by Yas, Flooz), prix et adresse | **un nom (« Boutique Élégance Lomé ») et un numéro (90 12 34 56)** | rien |
| Agent qui répond aux clientes | 0 sur 5 | **5 sur 5** (nom, WhatsApp, deux numéros de paiement, prix) | rien | rien |

- Souvenirs retenus : **8 justes et le logo** ; **ni le mot de passe ni l'information de santé**.
- **Aucun mélange** : rien de Kossi chez Ama, rien d'Ama chez Kossi (5 souvenirs justes ; son affiche reprend Kara, son WhatsApp, son numéro Flooz et ses couleurs rouge et noir).
- Défaut trouvé et corrigé pendant la mesure : la première extraction de Kossi a échoué (réponse illisible deux fois, sans passer au fournisseur suivant) et Kekeli disait quand même « je connais désormais vos informations » ; corrigé (fournisseur suivant, règle « ne jamais annoncer soi-même un souvenir »), puis refait : 5 souvenirs sur 5.

**Coût de la mémoire par message (estimation à partir des mesures)** : une extraction pèse environ 800 tokens d'entrée et 70 de sortie, pour environ 35 % des messages ; le rappel ajoute environ 170 tokens en moyenne (bloc complet : environ 310). Formule Gratuite : environ **0,09 FCFA par message** compté dans l'énergie (au prix payant de Gemini ; 0 réellement dépensé avec l'offre gratuite) ; formules payantes : environ **0,12 FCFA par message** (rappel lu par GPT-6.1 Terra et extraction par GPT-6 Luna), soit environ 4 à 6 % de tokens en plus par message.

### 26.22 E-mails avec Brevo, reçus et connexion comme Claude (14 novembre 2026)

**Demande du porteur du projet** (après la validation de la pull request 52). Captures : `docs/captures/emails-connexion.md`. **Crédit OpenAI dépensé pour cette pull request : environ 0,008 $** (plafond 0,20 $), seulement les réponses de Kekeli pendant les captures.

#### Finitions de la pull request 52
- **Réglages → Mémoire** : le logo s'affichait en géant et le texte, écrasé en colonne, chevauchait « Supprimer ». Sur téléphone, le logo en haut puis le texte et le bouton dessous ; sur ordinateur, sur une ligne ; image de taille fixe.
- **Mot de passe ou code annoncé** (« mon mot de passe est… », « le code SMS : 482 913 », « code de vérification ») : remplacé par « [mot de passe masqué] » ou « [code masqué] » avant tout enregistrement (`SecretScanner::redactMessage`), donc jamais envoyé au modèle ni retenu en mémoire. La valeur doit suivre « est », « : » ou « = » et ressembler à un secret ; prix, montants, téléphones, codes promo, codes postaux et couleurs ne sont jamais touchés. Mesure : 15 phrases, 7 secrets masqués et 8 pièges intacts ; tests : 12 phrases dont 5 pièges.

#### E-mails (Brevo, réglages SMTP `MAIL_*`, présents dans l'environnement de Claude)
- Huit e-mails en français, sobres, au design de Kekeli (version HTML lisible sur téléphone, mode sombre, version texte) : code de connexion, bienvenue, paiement à vérifier (administrateur : `ADMIN_ALERT_EMAIL`, sinon les comptes administrateurs), paiement refusé avec la raison, paiement validé avec le reçu en pièce jointe, formule finie demain, formule terminée, mot de passe oublié (seulement pour les comptes qui ont un mot de passe). Jamais de mot de passe ni de clé dans un e-mail ; pied : Lomé IA School, adresse, téléphones de contact, e-mail.
- **File d'attente** (`App\Jobs\SendEmail`, 4 essais : 1, 5 puis 15 minutes) ; **plafond par jour** réglable (`email_daily_cap`, 150 au départ, heure de Lomé) car l'offre gratuite de Brevo (300 par jour) est partagée avec d'autres sites ; au-delà, les e-mails non urgents attendent le lendemain ; **codes, liens de mot de passe et reçus passent toujours**.
- **Journal** : Administration → E-mails (date, type, destinataire masqué « a•••@e•••.tg », réussite ou échec, jamais le contenu ; liste compacte sur téléphone).
- **Envoi réel impossible depuis l'environnement de Claude** : seul le HTTPS sort, le serveur SMTP de Brevo est injoignable (ports 587, 465, 2525 et 25 refusés). L'unique e-mail réel tenté (reçu d'essai vers l'administrateur) n'est pas parti ; essai réel ajouté à `docs/mise-en-ligne.md`, partie 9. Défaut trouvé en chemin : les réglages réels de l'environnement passaient avant ceux des tests ; l'envoi simulé est maintenant forcé dans `tests/TestCase.php`.

#### Reçus d'abonnement
- Un reçu par paiement validé : **numéro KEK-année-000123** (index unique sur l'année et le rang, nouvel essai en cas de collision, jamais deux reçus pour un paiement), date, nom et e-mail du client, formule, période couverte, montant en FCFA (en dollars hors zone FCFA, avec le montant payé en FCFA), moyen de paiement et référence de la transaction (saisis par l'administrateur en validant), statut « Payé », « Kekeli, un produit de Lomé IA School », émetteur et contact.
- **Jamais modifié ni effacé** après sa création (le modèle le refuse) ; une erreur donne un **reçu d'annulation** lié. Informations figées au moment du paiement ; reçus gardés pour la comptabilité même après la suppression du compte (politique de confidentialité).
- **PDF d'une page par le moteur de documents** (python-docx et LibreOffice) : structure écrite par le site, jamais par un modèle, rendue par le Runner dans l'atelier E2B isolé (adresse signée `POST /recus`). Essai réel : reçu KEK-2026-000001 en 23 secondes (`docs/captures/emails-connexion/recu-essai-KEK-2026-000001.pdf`).
- Client : **Réglages → Formule → « Mes reçus »**, téléchargeables (seulement les siens). Administrateur : voir, **renvoyer**, annuler (Administration → Paiements).
- **Émetteur** et **téléphones de contact** (un par ligne, affichés sur les reçus et dans le pied des e-mails ; valeurs de départ +228 72 24 02 31, +228 79 16 23 90, +228 79 74 21 27, visibles dans le dépôt public avec l'accord du porteur du projet) dans Administration → Réglages. Aucun numéro légal inventé : vide tant qu'il n'est pas saisi.

#### Connexion et inscription comme Claude et ChatGPT
- **Une seule page** : « Continuer avec Google » (seulement quand `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` sont réglés), « ou », l'e-mail et « Continuer avec l'e-mail ». Un **code à 6 chiffres** et un **lien** : 10 minutes, usage unique, 5 essais, 5 envois par heure et par adresse (20 par connexion), message identique que le compte existe ou non ; seules les empreintes du code et du lien sont gardées. Nouveau compte : le nom, le pays et l'accord des conditions en une étape ; e-mail de bienvenue. L'ancienne adresse d'inscription mène à cette page ; l'inscription ne demande plus de mot de passe.
- **Google** : Laravel Socialite 5.32.0 (licence MIT, paquet officiel), seulement le nom, l'e-mail et la photo ; adresse non vérifiée par Google refusée ; un compte existant est relié, sinon un compte est créé après le pays et les conditions. **Sécurité** : une adresse jamais prouvée perd son ancien mot de passe quand son vrai propriétaire se connecte par code ou par Google.
- **Anciens comptes avec mot de passe** : lien discret « Se connecter avec un mot de passe » (compte administrateur compris) ; « Mot de passe oublié » seulement pour eux ; l'onglet « Mot de passe » des réglages n'apparaît que pour eux.
- **Défaut trouvé à l'essai dans le vrai navigateur** : en passant de l'étape « e-mail » à l'étape « code », le champ était réutilisé et le code tapé partait dans l'e-mail ; chaque étape a maintenant sa propre clé.
- **Google absent** (`GOOGLE_CLIENT_ID`) : bouton caché, construit et testé avec Google simulé ; gestes pour créer l'identifiant gratuit : `docs/mise-en-ligne.md`, partie 9.
- **Politique de confidentialité** : connexion Google (nom, e-mail, photo), codes par e-mail, Brevo, journal des e-mails, reçus conservés ; **à faire relire par un juriste**.

### 26.23 Nouveau design de Kekeli, partout (14 novembre 2026)

**Demande du porteur du projet** (après la pull request 53). Étude : [`docs/design-etude.md`](design-etude.md) ; captures avant et après (téléphone et ordinateur, clair et sombre) : `docs/captures/nouveau-design.md`. **Crédit OpenAI dépensé : 0 $** (plafond 0,20 $) : aucune requête à un modèle, les données des captures sont écrites à la main.

#### Finitions de la pull request 53 (commit séparé)
- L'adresse « bonjour@kekeli.dev » est retirée partout (le domaine n'est pas acheté) : valeur par défaut, tests, script d'essai, captures (reçu et e-mails refaits).
- **Contact de Kekeli** donné par le porteur du projet, avec son accord pour le dépôt public : **kekelilome3@gmail.com** et **+228 72 24 02 31**, valeurs de départ des champs « E-mail » et « Téléphone » de l'émetteur (Administration → Réglages, toujours modifiables) ; « Téléphones de contact » garde ses trois numéros ; le téléphone principal n'apparaît jamais deux fois.
- Une seule source, `App\Support\Contact` : pied des e-mails, **adresse de réponse (Reply-To)** des e-mails (ils partent toujours de `MAIL_FROM_ADDRESS`), reçus PDF, formules, paiement, pages légales, pages d'erreur et de maintenance, pied de l'accueil, « Nous contacter » du menu du compte, e-mail « paiement refusé ». Un e-mail de contact vidé fait disparaître la ligne « E-mail ».
- Chaque numéro reste sur une seule ligne (espaces insécables, `App\Support\Phones`), dans les e-mails et sur les reçus.

#### Le système de design (un seul endroit)
- `resources/css/app.css` : gris chauds (texte secondaire assombri en clair et éclairci en sombre pour WCAG AA), ambre, **états doux** (vert sauge, terre cuite, bleu ardoise à la place des couleurs vives de Tailwind), échelle de texte, **coins** (6, 8, 10, 14, 18, 24 pixels et pilule), **ombres** très douces seulement pour ce qui flotte, **mouvement** (`--ease-kekeli`, 160 à 220 millisecondes), jetons de sens `--k-…` en clair et en sombre, classes communes (`kekeli-carte`, `kekeli-pastille`, `kekeli-vide`, `kekeli-squelette`, `kekeli-scintille`, `kekeli-apparition`, `kekeli-lien`, `kekeli-surtitre`, `kekeli-contact`), contour clavier visible.
- E-mails et reçu PDF : `App\Support\Design` reprend les jetons ; `tests/Feature/DesignTest.php` vérifie qu'ils sont identiques à ceux du CSS et que les contrastes atteignent 4,5 pour 1.
- **Logo provisoire** dans un seul fichier `public/logo.svg` et un seul composant `<x-app-logo-icon>` ; `node scripts/logo.js` en tire `public/logo.png` (e-mails, apple-touch-icon) et `public/favicon.ico` ; l'ancien `favicon.svg` et deux gabarits de connexion inutilisés sont supprimés.

#### Appliqué partout
- Pages d'erreur et de maintenance en français (`resources/views/errors/` : 403, 404, 419, 429, 500, 503) avec le soleil, une phrase simple, un bouton et le contact ; elles s'affichent même sans base de données.
- Connexion : grand titre à empattements, étape qui apparaît en fondu. Discussion : messages qui apparaissent, soleil qui tourne lentement tant que Kekeli travaille (comme le logo de Claude), texte « Réflexion… » et « Kekeli travaille… » qui scintille, squelette pendant que l'aperçu se prépare. Menus et fenêtres qui s'ouvrent en fondu.
- États vides avec une icône et une phrase (mémoire, reçus, journal des connecteurs, paiements, e-mails, projets, alertes). Formules et paiement en cartes, pastille « Votre formule », contact en bas. Pages légales avec un grand titre et des liens soulignés discrets.
- Animations : CSS seulement, seulement `opacity` et `transform`, désactivées avec « réduire les animations » ; aucune bibliothèque.

#### Mesures (Lighthouse téléphone, réseau lent simulé)
- **Accessibilité : 95 → 100** sur l'accueil, la connexion, la discussion et les formules (contraste des petits textes gris corrigé).
- **Performance : pas d'écart mesurable** (60 à 80 avant, 60 à 77 après ; le serveur de développement ne compresse pas les fichiers, les résultats varient de 15 points d'un essai à l'autre pour la même version).
- **Poids** : CSS 40,0 → 41,1 ko compressé ; JavaScript inchangé (1,8 ko compressé). Rien d'annulé : chaque ajout corrige l'accessibilité ou remplace un écran absent.

#### Reste à faire
- Validation par le porteur du projet ; nouveau logo plus tard (remplacer `public/logo.svg`, lancer `node scripts/logo.js`).

## 27. Création d'applications comme Claude Code (12 novembre 2026)

**Décision du porteur du projet** : Kekeli fonctionne exactement comme Claude Code, Codex et Cursor. Il écrit le code de l'application dans le projet de l'utilisateur ; c'est l'utilisateur qui choisit et branche ses propres services (base de données, hébergement, comptes). **Kekeli n'héberge aucune donnée des utilisateurs et ne paie aucun service pour eux.** Il n'impose aucun tutoriel : il aide seulement quand l'utilisateur le demande.

### 27.1 Ce qu'est une application
- Une application web **installable** (manifeste, icônes 192 et 512 pixels, petit service de mise en route qui ne garde jamais de page privée hors ligne), avec comptes, base de données, pages privées, rôles, formulaires et listes. Les deux socles (Laravel et Next.js) la livrent dès le départ (`runner/src/applications/index.js`, `ajouterInstallation`).
- Le premier répondant reçoit l'outil **« creer_application »** (plan, technologie, confirmation), distinct de « creer_site ». En cas de doute : une seule question courte (« Voulez-vous un site qui présente votre salon, ou une application avec des comptes pour gérer les rendez-vous ? »).
- **Technologie imposée par l'utilisateur, toujours respectée** : « en Laravel » → Laravel ; « en Next.js », Node, React, Vercel → Next.js ; « avec Supabase » → Next.js avec la base PostgreSQL de Supabase branchée par `DATABASE_URL` (`CreateApplication::stackFor`). Pour une autre technologie, Kekeli le dit honnêtement dans le plan et propose la plus proche.

**Pile par défaut : Laravel 12 (PHP 8.3), vues Blade, SQLite pour l'aperçu, MySQL ou PostgreSQL en ligne.** Raisons :
1. **Simple** : un seul langage côté serveur, des pages rendues par le serveur, aucune chaîne de construction JavaScript obligatoire ; c'est la pile la plus facile à reprendre par un développeur local.
2. **Sûre par défaut** : comptes, mots de passe chiffrés, protection des formulaires (CSRF), middleware d'accès et politiques d'autorisation sont fournis par le cadre ; Kekeli les vérifie après chaque demande.
3. **Facile et peu chère à héberger** : un hébergement mutualisé PHP et MySQL (souvent 3 $ à 10 $ par mois, prix à vérifier chez l'hébergeur) existe partout, y compris chez des hébergeurs africains, en plus de Laravel Cloud ou d'un conteneur.
4. **Déjà éprouvée dans Kekeli** : socle testé depuis le 10 novembre 2026, aperçu, base d'essai et administrateur d'essai en place ; c'est aussi la pile qui a donné les meilleurs résultats au banc d'essai.

### 27.2 Fonctionnement comme Claude Code
- **Mode plan** : avant une construction, Kekeli propose un plan court (« ## Pages », « ## Données », « ## Rôles », « ## Fonctions », puis « Technologie : … ») avec le bouton **« Construire ce plan »** ; le client peut le corriger en écrivant (nouveau plan) ou l'accepter en écrivant (« oui, vas-y »). Rien n'est construit avant (`app/Agent/Tools/CreateApplication.php`, action `acceptPlan`). Le nom est deviné dès le plan (« Salon de coiffure Belle Afi »).
- **Petite modification** : pas de plan, Kekeli agit directement (« modifier_site »).
- Dans l'ordinateur E2B, l'agent lit les fichiers, modifie le code, lance les tests et corrige ses erreurs tout seul ; Kekeli vérifie ensuite (tests, sécurité, application qui répond) et fait corriger une fois.
- **Actions repliées détaillées** pour une application (et le mode développeur) : « Lu · routes/web.php », « Modifié · app/… », « Commande · php artisan test » (`AgentStep::DETAILS`, `AgentStep::cleanTarget`) ; le message final reste court et clair.
- **Questions sur le code** sans rien modifier : outil interne « lire_fichier » (3 lectures au plus par message) ; le site donne une adresse signée valable 10 minutes (`runner.file.show`), qui ne lit que la dernière sauvegarde, jamais `.env` ni la base d'essai, et seulement du texte.

### 27.3 Les services de l'utilisateur
- **Aperçu** : base d'essai locale dans l'ordinateur E2B (SQLite pour Laravel, PGlite pour Next.js), gratuite, avec des données inventées et un administrateur d'essai ; aucun compte extérieur.
- **Pour sa vraie application**, l'utilisateur branche ses services quand il veut : le code est prêt (variables d'environnement expliquées dans `.env.example`) ; Kekeli explique pas à pas seulement si on le lui demande, en vérifiant les étapes sur les sites officiels.
- **Variables secrètes** du projet (menu « ⋯ ») : formulaire qui chiffre les valeurs (`ProjectSecret`, cast « encrypted »), ne les réaffiche jamais, refuse les noms réservés (`APP_KEY`, compte d'essai…), et les transmet seulement à l'ordinateur E2B du projet (`/home/user/.kekeli/secrets.json`, droits 600, hors du projet donc jamais sauvegardé ni envoyé sur GitHub) ; le serveur d'aperçu les donne à l'application par son environnement et la relance quand elles changent. Le premier répondant ne les reçoit jamais.
- **Clé collée dans la discussion** : reconnue (OpenAI, Google, GitHub, Supabase, Stripe, Slack, AWS, adresses de connexion avec mot de passe, clés privées, jetons), remplacée par « [clé masquée] » avant d'être enregistrée ou envoyée au modèle, avec un message poli qui propose les Variables secrètes (`app/Support/SecretScanner.php`).
- **Code complet** : « Récupérer le code » (ZIP, gratuit) et GitHub quand l'application GitHub sera prête ; toujours avec **LISEZ-MOI.md** en français (installation, variables à remplir, lancement, tests, installation sur le téléphone), jamais `.env`.

### 27.4 Modèle de construction : banc d'essai réel
La même application (prise de rendez-vous du salon « Belle Afi » : comptes, liste des rendez-vous, page administrateur, règles d'horaires) construite par chaque modèle, chacun dans son ordinateur E2B, avec les consignes de Kekeli (`runner/scripts/banc-construction.mjs`, résultats `runner/mesures/banc-construction.json`, captures `docs/captures/applications.md`). Prix officiels lus le 12 novembre 2026 (developers.openai.com/api/docs/pricing ; aistudio.google.com/docs/pricing). Coût au prix payant, 1 $ = 590 FCFA, pour une seule construction mesurée par modèle (estimation à confirmer sur plus d'essais).

| Modèle | Résultat | Qualité (jugée par Claude, sur 10) | Durée | Requêtes | Coût de l'application |
|---|---|---|---|---|---|
| Gemini 3.5 Flash-Lite (gratuit, actuel) | Inachevé après 9 reprises (limite par minute de Google), nom resté « Mon site » | 4 | 26 minutes et plus | 83 | 146 FCFA (0,25 $), compté dans l'énergie même s'il est gratuit pour Kekeli |
| Gemini 3.6 Flash (meilleur Gemini gratuit disponible ; Gemini 3.8 Flash surchargé) | Arrêté par la limite gratuite du jour (environ 20 requêtes) | non jugeable | — | 20 | — |
| GPT-6.1 Terra (1 $ et 5 $ par million de tokens) | Terminé, 24 tests réussis, sécurité sans remarque, pages privées protégées, installable | 7,5 : propre, clair sur téléphone ; un reste du socle (« Anciennes demandes ») | 5 minutes | 20 | 66 FCFA (0,11 $) |
| GPT-6.1 Sol (2 $ et 10 $ par million de tokens) | Terminé, 36 tests réussis, sécurité sans remarque, pages privées protégées, installable | 9 : le plus complet (tableau de bord, horaires et chevauchements bloqués, socle nettoyé), très beau sur téléphone | 7,7 minutes | 23 | 276 FCFA (0,47 $) |

**Choix appliqué** (`config/kekeli.php`, clé `build_models`, modifiable par formule dans Administration → Réglages) :
- **Pro, Expert, Agence : GPT-6.1 Sol**, le meilleur en qualité ; environ 276 FCFA par application (la formule Pro, 4 000 FCFA par mois, en permet environ 14).
- **Gratuite : GPT-6.1 Terra**, le meilleur abordable : il finit l'application en 5 minutes et coûte **moins d'énergie** que Gemini 3.5 Flash-Lite (66 FCFA contre 146 FCFA pour un travail inachevé). Avec 30 FCFA par jour, une application se construit en plusieurs jours (le travail s'arrête, puis « Continuer » après la recharge) : à revoir avec le porteur du projet.
- **Secours** : les fournisseurs gratuits suivent derrière ; si OpenAI refuse ou si le plafond OpenAI du jour est atteint, Kekeli continue avec eux sans rien dire au client.
- **Plafond OpenAI** : le même plafond de 1 $ par jour couvre les réponses, la dictée et maintenant les constructions, soit environ 2 applications avec Sol ou 9 avec Terra par jour pour tout Kekeli. **À relever par le porteur du projet** dans Administration → Réglages quand de vrais clients arriveront (aucune dépense de plus sans son accord).
- **La clé OpenAI n'entre jamais dans l'ordinateur E2B** : OpenCode y parle à un **relais** sans clé (`runner/src/relais/relais-openai.mjs`, port 4200), avec un jeton propre au projet ; le Runner vient chercher les demandes en attente, vérifie le jeton et le modèle (seulement GPT-6 Luna, GPT-6.1 Terra et GPT-6.1 Sol), ajoute la clé et envoie la demande à OpenAI (`runner/src/passerelle.js`, `runner/src/relais.js`), puis dépose la réponse. Le Runner n'a pas besoin d'adresse publique ; une passerelle directe `POST /passerelle/<projet>/openai/v1/…` existe aussi pour un Runner hébergé. Les fichiers d'installation (manifeste, service, icônes) passent sans le cookie de l'aperçu, car ils ne contiennent aucune donnée.
- **Crédit OpenAI dépensé pour cette pull request** : environ **0,71 $** (Terra 0,11 $, Sol 0,47 $, essai réel de bout en bout 0,13 $), sous le plafond de 2 $ ; estimation calculée à partir des tokens.
- **Précaution de sécurité de la séance** : l'outil de sécurité de Claude a refusé de lancer un agent autonome sur la machine de Claude ; tous les essais ont donc tourné dans des ordinateurs E2B isolés, effacés à la fin.

### 27.5 Sécurité des applications créées
Ajoutées aux consignes de l'agent (`consignesApplication`) et vérifiées dans l'application d'essai : mots de passe chiffrés (cast « hashed », vérifié après chaque demande) ; pages privées protégées côté serveur (au banc, `/mes-rendez-vous` et `/admin` ouvertes sans connexion renvoient vers `/connexion` pour Terra et Sol) ; droits vérifiés côté serveur à chaque lecture et modification (politiques, test « une cliente ne voit pas les rendez-vous d'une autre ») ; aucune clé dans le code envoyé au navigateur ni dans Git (recherche de clés après chaque demande : aucune trouvée) ; formulaires protégés (CSRF).

Tests : `tests/Feature/ApplicationsTest.php` (12 tests) et `runner/tests/applications-claude-code.test.js` (11 tests). Essai réel : `scripts/essai-application.js` ; captures : `scripts/captures-applications.js`.

**Reste à faire, hors de cette pull request** : essayer l'application en Next.js et avec Supabase dans un vrai ordinateur (testés par les tests seulement) ; mesurer plusieurs constructions par modèle ; envoi sur GitHub avec l'application GitHub ; versions et retour en arrière.

## 28. Agents IA, comme Claude Code (12 novembre 2026)

**Décision du porteur du projet** : Kekeli fonctionne comme Claude Code, Codex et Cursor. Il **écrit le code de l'agent** dans le projet de l'utilisateur. L'utilisateur branche **sa propre clé de modèle** dans les Variables secrètes et **héberge son agent où il veut**. **Kekeli ne paie pas les messages des agents de ses utilisateurs.**

### 28.1 Finitions de la pull request 41 et énergie épuisée (commit séparé)
1. « Lu · . » devient « Lu · dossier du projet » (Runner `cheminLisible`, site `AgentStep::cleanTarget`).
2. Les commandes internes de Kekeli (dossier `/home/user/.kekeli`, par exemple `touch /home/user/.kekeli/relancer`) ne sont plus montrées ni comptées ; une commande mixte garde seulement sa partie du projet (`commandeDuProjet`, `AgentStep::projectCommand`, `isInternal`), comme Claude Code.
3. Consigne de construction : phrases adaptées à la situation réelle ; pour une construction neuve, jamais « votre application actuelle », « les comptes existants » ni « sans toucher à… » (`CreateApplication::NEW_PROJECT`, `consignesApplication`).
4. Sur téléphone, « Récupérer le code » devient une icône avec son libellé accessible ; bouton complet sur ordinateur.
5. Titre de la discussion : le nom deviné dès que le plan est proposé (nom écrit par le client, sinon nom court donné par le modèle avec l'argument « nom » de l'outil), jamais le début coupé du message ni un titre choisi par le client.
6. Consigne de construction : retirer tout ce qui vient du point de départ et n'est pas dans le plan (« Anciennes demandes »…) (`CreateApplication::NO_LEFTOVERS`).
- **Énergie épuisée, comme Claude Code** : une ligne sobre « Énergie épuisée · Recharge à 00 h 00 » (ou « Recharge le 12/12/2026 » pour une formule au mois) avec un lien discret « Voir les formules », une seule fois ; le travail fait est sauvegardé et « Continuer » reprend après la recharge. Ces lignes remplacent les longs messages de limite.
- **Plafond OpenAI du jour pendant une construction** (site, application, agent) : la construction s'arrête (« Kekeli est très sollicité · Reprise à 00 h 00 ») au lieu de passer à Gemini, qui ne sait pas finir une construction (essais réels de la partie 27.4). Avant l'envoi, la demande s'arrête tout de suite (`SendAgentRun`) ; pendant le travail, le site demande l'arrêt au Runner (`RunnerEventHandler::stopRequest`, raison `plafond_modele`). Les simples questions gardent le secours gratuit (`AnswerModels::forRunner`). « Continuer » reprend quand le plafond est remis à zéro.
- Tests : `tests/Feature/ApplicationFinishesTest.php`, tests d'énergie mis à jour. Captures avant et après : `docs/captures/agents.md`.

### 28.2 Ce que fait Kekeli pour un agent
- **Outil « creer_agent »** du premier répondant, distinct de « creer_site » et « creer_application » ; en cas de doute entre les trois, **une seule question courte** (« Voulez-vous un site qui présente votre salon, une application pour gérer les rendez-vous, ou un assistant qui répond à vos clientes ? »).
- **Mode plan**, comme pour les applications : « ## Rôle », « ## Ce qu'il sait », « ## Ce qu'il peut faire », « ## Où il sera utilisé », « ## Modèle prévu », puis « Technologie : … », avec **« Construire ce plan »** ; correction ou accord possibles en écrivant (`app/Agent/Tools/CreateAgent.php`, offre `construire_agent`).
- **Technologie** (`CreateAgent::agentStackFor`) : **LangGraph en JavaScript par défaut** ; **CrewAI** quand le client veut une équipe de plusieurs agents en Python ou nomme CrewAI ; **une technologie imposée par le client est toujours respectée** (par exemple Mastra, LlamaIndex, Python) : l'agent part du point de départ le plus proche et le remplace, avec les mêmes fonctions et les mêmes variables.
- **Ce que l'agent construit sait faire** (points de départ testés `runner/src/agents/socles/`) : un rôle et des instructions claires en français (`agent/role.md`) ; des documents de l'entreprise (`documents/`, découpés en passages, les plus proches de la question donnés au modèle : simple, gratuit, sans base de vecteurs) ; des outils quand le client les demande (recherche sur Internet avec la clé Tavily du client, prise de rendez-vous, envoi d'e-mail…) ; la mémoire de chaque conversation (MemorySaver de LangGraph, identifiant de conversation ; historique pour CrewAI) ; une page de discussion et une **bulle à intégrer** sur un site (une ligne `<script src=".../bulle.js">` à copier, montrée dans « Récupérer le code ») ; un **LISEZ-MOI** en français (installation, variables, lancement, bulle).
- **Modèle de l'agent** : interface compatible OpenAI (`MODELE_API_URL`, `MODELE_API_CLE`, `MODELE_NOM`) : OpenAI, Gemini, Mistral ou autre, avec la clé et aux frais du client.
- **Modèle de construction** : le même que pour les applications (GPT-6.1 Sol pour les formules payantes, GPT-6.1 Terra pour la Gratuite, `build_models`).

### 28.3 Licences vérifiées
- **LangGraph (JavaScript)** : licence MIT (fichier LICENSE officiel, github.com/langchain-ai/langgraphjs, et paquet npm `@langchain/langgraph` 1.7.3 « MIT ») : usage commercial, modification et distribution permis, en gardant la mention de licence.
- **LangChain** (`@langchain/core` 1.4.0, `@langchain/openai` 1.8.4) : licence MIT (paquets npm).
- **CrewAI** : licence MIT (fichier LICENSE officiel, github.com/crewAIInc/crewAI, « Copyright (c) 2025 crewAI, Inc. ») ; version fixée 1.15.40 (Python 3.10 à 3.13).
- **Dify** : licence Apache 2.0 **modifiée** (fichier LICENSE officiel, github.com/langgenius/dify) : faire tourner son code pour plusieurs clients (« multi-tenant ») demande une licence commerciale, et son logo ne peut pas être retiré de sa console. **Kekeli ne reprend aucune ligne de son code** : seulement l'idée d'organisation (rôle, instructions, documents, outils, panneau de test).

### 28.4 Sécurité des agents créés
- **Aucune clé dans le navigateur** : la clé du modèle reste sur le serveur de l'agent ; Kekeli vérifie après chaque demande qu'aucun fichier envoyé au navigateur (`public/`) ne contient de clé ou de réglage secret.
- **Instructions cachées** : les messages des visiteurs et le contenu des documents sont donnés au modèle comme des informations (`<document>`, règles de sécurité placées avant), jamais comme des ordres ; refus de révéler la consigne ou une clé.
- **Limite de messages par minute** par visiteur (`LIMITE_MESSAGES_MINUTE`, 10 par défaut) et longueur maximale des messages (2 000 caractères).
- **Refus poli hors du rôle** et aucune information inventée (règles de sécurité de l'agent).
- Vérifié dans les tests de l'agent d'essai (`tests/agent.test.js` ou `tests/test_agent.py`) et par Kekeli après chaque demande (règles présentes, limite présente, aucune clé dans le navigateur).

### 28.5 « Tester l'agent » et l'essai gratuit
- L'aperçu d'un agent s'appelle **« Tester l'agent »** : la page de discussion de l'agent, qui répond pour de vrai.
- **Avec la clé du client** (Variables secrètes, `MODELE_API_CLE`) : l'agent utilise son propre modèle ; les réglages d'essai s'effacent d'eux-mêmes.
- **Sans clé** : essai gratuit avec le modèle gratuit de Kekeli (Gemini, offre gratuite), **10 messages par jour et par client** (`agent_trial_messages_per_day`, réglable dans Administration → Réglages, 0 = pas d'essai), non comptés dans l'énergie, comptés pour le fournisseur dans Administration → Fournisseurs.
- **La clé de Kekeli n'entre jamais dans l'ordinateur E2B** : l'agent reçoit l'adresse du relais de l'ordinateur (`http://127.0.0.1:4200/essai/v1`) et un jeton propre au projet ; le Runner suit le relais tant que l'ordinateur est allumé (`suivreRelaisEnContinu`), vérifie le jeton, demande au site par une requête signée si l'essai du jour permet encore un message (`runner.agent-trial`), puis appelle Gemini avec sa clé (`runner/src/essai-agent.js`). Sans réponse claire du site, l'essai est refusé.
- **Essai terminé** : l'agent répond « L'essai gratuit du jour est terminé. Ajoutez votre clé dans les Variables secrètes pour continuer. », et la ligne du panneau ouvre les Variables secrètes.

### 28.6 Essai réel de bout en bout
« L'assistante du Salon Belle Afi », construite pour de vrai le 12 novembre 2026 depuis « Que voulez-vous créer aujourd’hui ? », sur un téléphone simulé, avec un compte inventé de formule Pro (`scripts/essai-agent.js`, journal `docs/captures/agents/essai.json`) :
- **Plan** : proposé en 9 secondes, avec le nom deviné (« Salon de coiffure Belle Afi ») et « Technologie : LangGraph (JavaScript) ».
- **Construction** : 4 minutes et 15 secondes dans un ordinateur E2B, GPT-6.1 Sol par le relais du Runner, 17 requêtes. Kekeli a écrit le rôle, la FAQ, l'enregistrement des demandes de rendez-vous, un espace privé du salon (code à mettre dans les Variables secrètes), une page de discussion aux couleurs sobres avec des questions à toucher ; **15 tests réussis**, sécurité sans remarque, agent qui répond (code 200).
- **Coût de construction** (estimation à partir des tokens, 1 $ = 590 FCFA) : modèle 0,17 $ et ordinateur 0,01 $, soit **environ 108 FCFA**.
- **Tester l'agent, essai gratuit** (Gemini 3.5 Flash-Lite gratuit, par le relais) : « Combien coûtent les tresses africaines longues ? » → « Les tresses africaines longues coûtent 15 000 FCFA. Souhaitez-vous que je vous aide à faire une demande de rendez-vous ? » ; « Je voudrais un rendez-vous samedi à 10 h pour un brushing, je m'appelle Ama. » → l'agent demande le numéro de téléphone avant d'enregistrer la demande ; « Quels sont vos horaires ? » → « du lundi au samedi, de 9 h à 19 h ». Coût pour Kekeli : 0 FCFA (offre gratuite).
- **Essai terminé** : une fois la limite du jour atteinte, l'agent répond « L'essai gratuit du jour est terminé. Ajoutez votre clé dans les Variables secrètes pour continuer. », sans appeler le modèle.
- **Crédit OpenAI dépensé pour toute la pull request** : **environ 0,18 $** (plafond fixé à 2 $), dont 0,007 $ pour un premier lancement interrompu (adresse du site mal réglée dans l'essai local, corrigée).
- **Défaut trouvé et corrigé pendant le travail** : dans le point de départ, la liaison vers l'étape des outils de LangGraph portait un mauvais nom (« tools » au lieu de « outils ») ; un test avec un outil l'a montré avant tout essai réel.

Tests : `tests/Feature/AgentsTest.php` (9 tests), `tests/Feature/ApplicationFinishesTest.php` (7 tests), `runner/tests/agents.test.js` (8 tests), tests propres aux points de départ (8 en JavaScript, 6 en Python). Captures : `docs/captures/agents.md`.

**Reste à faire, hors de cette pull request** : essayer CrewAI dans un vrai ordinateur E2B (essayé ici avec un faux service compatible OpenAI ; son installation pèse environ 1 Go) ; relancer automatiquement un agent dans l'aperçu après l'effacement de son ordinateur (comme pour les applications) ; mesurer plusieurs constructions d'agents.

## 29. GitHub et automatisations, comme Claude Code (12 novembre 2026)

**Décision du porteur du projet** (après la validation de la pull request 42) : l'ancienne étape « application GitHub » (« dépôt d'abord » et « site d'abord ») et l'étape « Automatisations » sont réunies dans une seule pull request. Kekeli travaille sur GitHub comme Claude Code ; les automatisations tournent sur le dépôt GitHub de l'utilisateur, **jamais chez Kekeli**.

### 29.1 Finitions (commit séparé)
1. **« Tester l'agent » sur téléphone** : la bulle du client ne passe plus sous les boutons de suggestion (dans une colonne qui défile, plus rien ne rétrécit : `flex-shrink: 0` dans les points de départ des agents, et consigne de l'agent mise à jour).
2. **Une seule ligne d'état** pendant une construction : « Kekeli travaille… » devient le titre de la liste des actions, au lieu de deux lignes l'une sous l'autre.
3. **Fournisseurs cachés, comme Bolt.new** : le client ne voit jamais le nom des modèles ni des fournisseurs de Kekeli (OpenAI, GPT, Gemini, Tavily, Exa, Cloudflare…) dans les plans, les actions, les messages, les formules ou les réglages ; seul l'administrateur les voit. Exception : quand le client branche **sa propre clé** pour son agent ou son automatisation, il choisit son fournisseur. Les pages juridiques gardent les noms (la politique de confidentialité doit nommer les fournisseurs). Test : `tests/Feature/HiddenProvidersTest.php`.
4. **Choix guidé du fournisseur de la clé du client** (Variables secrètes et LISEZ-MOI) : OpenAI, Google Gemini, Mistral, Groq, OpenRouter ; l'adresse se remplit seule (vérifiée sur les sites officiels, liste unique `runner/src/fournisseurs-client.json`) ; une phrase dit quand l'offre gratuite peut utiliser les messages pour améliorer les modèles (Gemini, Mistral, OpenRouter d'après leurs conditions officielles).
5. **Politique de confidentialité** : phrase ajoutée « Vos échanges avec Kekeli (vos demandes, ses réponses et le contenu de vos projets) peuvent être utilisés par Kekeli et par ses fournisseurs d'intelligence artificielle pour améliorer les modèles. » **À relire par un juriste** avant le lancement public.

### 29.2 GitHub, comme Claude Code
- **GitHub App de Kekeli** (`app/Github/GithubClient.php`) : le client l'installe sur son compte, **choisit sur GitHub les dépôts que Kekeli peut voir**, et accepte que Kekeli agisse pour lui. Kekeli reçoit un jeton de l'utilisateur (8 heures) et un jeton de renouvellement (6 mois), **chiffrés**, jamais affichés ni envoyés à l'ordinateur virtuel ; il les renouvelle seul. Trois réglages seulement : `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` (aucune clé privée nécessaire). Guide pour le téléphone : `docs/github-app.md`.
- **Autorisations minimales** : Contents, Pull requests, Workflows, Secrets, Actions et Administration (créer le dépôt privé) en écriture ; Checks et Metadata en lecture ; aucun webhook.
- **Dépôt d'abord** : la page « Ouvrir un dépôt GitHub » liste seulement les dépôts choisis pour Kekeli (`/user/installations/{id}/repositories`), avec le lien « Choisir les dépôts que Kekeli peut voir » ; un dépôt public reste ouvrable en lecture seule.
- **Projet d'abord** : « Créer un dépôt privé » (vue Code et « Récupérer le code ») crée le dépôt dans le compte du client à partir du projet, l'ajoute à l'installation et y envoie le code (`app/Jobs/PushProjectToGithub.php`).
- **Après chaque travail réussi**, Kekeli envoie les changements : **sur sa branche avec une pull request** (par défaut, comme Claude Code) ou **directement sur la branche principale** si le client le choisit dans le projet (`projects.github_push_mode`). Une simple question n'envoie rien.
- **Messages de commit clairs, en français** (« Kekeli : chaque matin à 7 h, envoie-moi… », la demande complète si elle est longue, les fichiers gardés hors du dépôt).
- **Jamais de .env, de base d'essai ni de clé envoyés** (`app/Github/GithubFiles.php`) : `.env` et ses variantes (sauf `.env.example`), bases SQLite et PGlite, dossiers de dépendances, fichiers de clés, et tout fichier où une clé est trouvée ; ces fichiers ne sont jamais supprimés non plus du dépôt du client.
- **Pastille GitHub** dans la barre du projet : le dépôt et le lien de la dernière pull request (icône et numéro seulement sur téléphone).
- **Questions sur un dépôt relié** : le premier répondant lit les fichiers (`lire_fichier`) sans rien modifier ; les fichiers de secrets ne sont jamais lus et une clé écrite dans un fichier est masquée.
- **Réglages → Connecteurs** : GitHub y apparaît dès que la GitHub App est réglée (Relier, Choisir les dépôts, Délier).
- **Connexion par code court** (flux « appareil » officiel de GitHub, commande `php artisan kekeli:github-appareil <adresse>`) : pour l'essai réel tant que le site n'est pas en ligne, GitHub ne pouvant pas revenir sur Kekeli.

### 29.3 Automatisations
- **Outil « creer_automatisation »** du premier répondant ; en cas de doute entre site, application, agent et automatisation, **une seule question courte**.
- **Mode plan** : « ## Déclencheur » (heure, jours, événement), « ## Étapes », « ## Services utilisés », « ## Résultat attendu », « ## Envoyé à », puis « Technologie : … », avec **« Construire ce plan »** (`app/Agent/Tools/CreateAutomation.php`, offre `construire_automatisation`).
- **Kekeli écrit le code** (JavaScript et Node 22 par défaut, sans dépendance ; technologie imposée par l'utilisateur respectée, par exemple Python) **et le fichier GitHub Actions planifié** (`cron` avec `timezone: 'Africa/Lome'`, bouton « Run workflow ») : point de départ testé `runner/src/automatisations/socles/javascript/` (journal lisible, modèle compatible OpenAI, e-mail avec Resend, tests avec un faux service, LISEZ-MOI, fichier `LANCEMENT`).
- **Les clés de l'utilisateur** passent des Variables secrètes aux **secrets GitHub du dépôt**, chiffrés avec la clé publique du dépôt (« sealed box » de libsodium, comme le demande GitHub, interface officielle) ; jamais écrites dans le code.
- **« Lancer maintenant »** (onglet « Lancer », qui remplace l'aperçu) : l'automatisation tourne une fois dans l'ordinateur E2B avec les Variables secrètes du client (sans clé de modèle : l'essai gratuit par le relais du Runner), au plus 5 minutes ; le **journal lisible** (étapes, réussite, point à régler, erreur en français) s'affiche en direct puis reste replié sous le résumé dans la discussion ; aucune valeur secrète n'y apparaît (masquée par le Runner et par le site). Seuls les services du client (Resend, adresse de son modèle) sont ajoutés aux sites autorisés pour ce lancement.
- **Modèle de construction** : le même que pour les applications (`build_models`).

### 29.4 Règles officielles de GitHub Actions (vérifiées le 12 novembre 2026)
- **Tâches planifiées** : intervalle le plus court de 5 minutes ; lancement **retardé possible** aux heures chargées (surtout au début de chaque heure), et un lancement peut être **abandonné** en cas de forte charge ; seulement sur la branche par défaut ; heure UTC par défaut, ou fuseau choisi avec `timezone` (source : « Events that trigger workflows », docs.github.com).
- **Dépôt public** : sans activité pendant 60 jours, les tâches planifiées sont désactivées (à réactiver d'un geste) ; minutes gratuites illimitées sur les machines standard de GitHub.
- **Dépôt privé** (compte GitHub Free) : un crédit de calcul mensuel inclus (12 $ par mois, soit environ 2 000 minutes Linux, estimation) ; une fois épuisé, les lancements s'arrêtent si aucun moyen de paiement n'est enregistré (source : « GitHub Actions billing », docs.github.com). Une automatisation d'une minute par jour consomme environ 30 minutes par mois.
- Ces règles sont expliquées dans le LISEZ-MOI de chaque automatisation.

### 29.5 Essai réel (12 novembre 2026)
« Conseils quotidiens boutique pagnes », construite pour de vrai depuis « Que voulez-vous créer aujourd’hui ? » sur un téléphone simulé, avec un compte inventé de formule Pro (`scripts/essai-automatisation.js`, `docs/captures/github-automatisations/essai.json`) :
- **Plan** proposé en 9 secondes (déclencheur 7 h heure de Lomé, étapes, Resend, résultat, destinataire, technologie).
- **Construction** : 2 minutes et 31 secondes dans un ordinateur E2B, GPT-6.1 Sol par le relais du Runner, 13 requêtes ; **17 tests réussis** ; fichier GitHub Actions planifié à 7 h avec le fuseau `Africa/Lome`, clés lues dans les secrets du dépôt.
- **« Lancer maintenant »** (une adresse d'e-mail inventée dans les Variables secrètes, sans clé Resend) : Démarrage, Préparer le résumé, Choisir les conseils du jour réussis ; « Envoyer l'e-mail » arrêté proprement avec « Ajoutez votre clé Resend dans la Variable secrète RESEND_API_KEY. ». L'e-mail réel partira dès que la clé Resend gratuite du porteur du projet sera placée.
- **Coût** (estimation à partir des tokens, 1 $ = 590 FCFA) : modèle 0,106 $ (plan 0,007 $ et construction 0,099 $), ordinateur 0,006 $ ; **environ 66 FCFA**. **Crédit OpenAI dépensé pour toute la pull request : environ 0,11 $** (plafond fixé à 2 $).
- **GitHub** : construit et testé avec un GitHub simulé (13 tests) ; l'essai réel (relier le compte, dépôt privé, pull request, automatisation lancée depuis GitHub Actions) se fera après la création de la GitHub App par le porteur du projet (`docs/github-app.md`).

Tests : `tests/Feature/AutomationsTest.php` (7 tests), `tests/Feature/GithubAppTest.php` (13 tests), `tests/Feature/HiddenProvidersTest.php`, `runner/tests/lancement.test.js` (6 tests), `runner/tests/finitions-fournisseurs.test.js`, tests du point de départ (3). Captures : `docs/captures/github-automatisations.md`.

### 29.6 Mise en ligne : à faire avec la GitHub App Kekeli-Lome
La GitHub App « Kekeli-Lome » a été créée par le porteur du projet le 12 novembre 2026 (nom court `kekeli-lome`, installée seulement sur `ahiankonoujoseph102-wq/lang`), et ses trois réglages sont dans l'environnement « kekeli ». L'essai réel n'a pas pu se faire depuis l'environnement de Claude : son réseau n'autorise GitHub que pour les dépôts attachés à la séance et bloque la connexion (`github.com/login/device`). La pull request 43 a été fusionnée avec l'accord du porteur du projet, sur la base des tests avec un GitHub simulé. À faire au moment de la mise en ligne :
- Remplacer l'adresse de redirection (« Callback URL ») `http://127.0.0.1:8123/github/retour` par l'adresse réelle du site : `https://<adresse du site>/github/retour`.
- Passer l'installation de « Uniquement sur ce compte » à « N'importe quel compte » (« Any account »), pour que les clients puissent installer l'application.
- Faire l'essai réel complet : relier le compte, projet d'abord avec un dépôt privé, pousser les changements (pull request), automatisation lancée depuis GitHub Actions.
- Vérifier si la création d'un dépôt fonctionne quand l'installation est limitée à certains dépôts (aujourd'hui, l'installation de « Kekeli-Lome » ne voit que `ahiankonoujoseph102-wq/lang`).

## 30. Documents et présentations, comme Claude et ChatGPT (12 novembre 2026)

**Décision du porteur du projet** (après la validation des pull requests 43 et 44) : on continue les fonctions avant la mise en ligne ; ordre : 1. documents et présentations, 2. images et affiches.

### 30.1 Ce que reçoit le client
- **De vrais fichiers** : Word (.docx) et PDF pour un document ; PowerPoint (.pptx) et PDF pour une présentation ; le PDF seul si le client le demande.
- **Un aperçu fidèle** : les images des vraies pages (ou diapositives) du fichier, rendues depuis le fichier lui-même, l'une sous l'autre, en papier blanc même en mode sombre ; lisible sur téléphone. Onglet « Document » ou « Présentation » à la place de l'aperçu ; ni code, ni « Mettre en ligne ».
- **« Télécharger »** dans la barre du projet et au-dessus de l'aperçu, avec le choix du format (« Word (.docx) », « PDF (.pdf) »).
- **Versions** : chaque modification écrite dans la discussion (« mets le titre en bleu », « ajoute une diapositive sur les prix ») fait une nouvelle version ; les précédentes restent consultables (« Vous regardez une ancienne version ») et téléchargeables.
- **Fichiers joints** (bouton « + » → « Ajouter un fichier (Word, PDF, PowerPoint) », 3 fichiers et 20 Mo au plus) : corriger, résumer, traduire ou transformer (par exemple un PDF en présentation). Un Word ou un PowerPoint corrigé ou traduit **garde son format et sa mise en page** : seuls les textes changent, et le gras, l'italique et les couleurs de chaque morceau sont gardés. Un PDF corrigé redevient un PDF.
- Aucun éditeur intégré : tout passe par la discussion.

### 30.2 Fonctionnement
- Outils du premier répondant : **« creer_document »** et **« creer_presentation »** dans une discussion, **« modifier_document »** dans un projet de document ; en cas de doute avec un site, une application, un agent ou une automatisation, **une seule question courte**. Une question sur le document reçoit une réponse directe (le premier répondant reçoit le texte de la version actuelle), sans nouvelle version.
- **Petit document clair** (lettre, CV, devis, présentation de 10 diapositives ou moins) : créé tout de suite. **Gros document** (rapport, mémoire, plus de 4 pages) ou **présentation de plus de 10 diapositives** : un plan court d'abord, avec **« Construire ce plan »** (`app/Agent/Tools/CreateDocument.php`, `CreatePresentation.php`, offres `construire_document` et `construire_presentation`).
- **Rédaction** : le modèle de construction de la formule (GPT-6.1 Sol pour les payantes, GPT-6.1 Terra pour la Gratuite, `build_models`), appelé **depuis le Runner** (les clés y restent), écrit une **structure JSON** du document (blocs : titres, paragraphes, listes, tableaux, lettre, en-tête de CV, signature, sources ; diapositives : titre, section, points, deux colonnes, chiffres clés, citation, tableau, fin). Kekeli ne garde que les types connus et des textes bornés (`structurePropre`) ; le modèle n'écrit jamais de code. Fichier : `runner/src/documents/redaction.js`.
- **Mise en page** : notre moteur `runner/src/documents/rendu/kekeli_documents.py` fabrique les fichiers à partir de la structure, puis LibreOffice fait le PDF et Poppler les images d'aperçu.
- **Plafond OpenAI du jour atteint** : un gros document s'arrête (« Kekeli est très sollicité · Reprise à … ») ; un petit document passe aux fournisseurs gratuits, qui savent écrire une lettre ou un CV (`app/Jobs/GenerateDocument.php`).
- **Énergie** : coût réel (modèle, ordinateur, recherche) décompté comme ailleurs ; « Énergie épuisée » arrête le travail avant la mise en page.
- **Recherche sur Internet** : si le document a besoin d'informations actuelles, l'outil donne la recherche à faire ; le Runner cherche (quota du client) et Kekeli ajoute lui-même le bloc « Sources » (jamais d'adresse inventée).
- **Fournisseurs toujours cachés** au client (testé).

### 30.3 Où se fait la génération : un ordinateur E2B isolé (« atelier »), pas le Runner
- **Les fichiers joints ne sont pas sûrs** (un Word, un PDF ou un PowerPoint peut être piégé) : ils sont ouverts seulement dans un ordinateur isolé, jamais sur le Runner, qui détient les clés.
- **L'atelier ne reçoit aucune clé** et **n'a plus Internet** une fois ses outils installés (`updateNetwork` : tout refusé) : il ne fait que mettre en page et lire des fichiers.
- **LibreOffice** (PDF fidèle et images d'aperçu) est lourd à installer sur le Runner (hébergé sur Laravel Cloud) ; dans l'atelier, l'installation mesurée prend environ 16 à 29 secondes, une fois par projet (`INSTALLATION`, versions exactes), sans modèle E2B sur mesure.
- Un atelier par projet, effacé après 10 minutes sans travail (`creerAtelier`).

### 30.4 Bibliothèques et licences (vérifiées sur les sources officielles le 12 novembre 2026)
| Outil | Rôle | Licence |
|---|---|---|
| python-docx 1.2.0 | fichiers Word | MIT (pypi.org/project/python-docx) |
| python-pptx 1.0.2 | fichiers PowerPoint | MIT (pypi.org/project/python-pptx) |
| pypdf 6.21.4 | lire les PDF joints, compter les pages | BSD 3 (pypi.org/project/pypdf) |
| LibreOffice (paquets Debian « nogui ») | PDF et conversion avant l'aperçu | MPL 2.0 (libreoffice.org/about-us/licenses) |
| Poppler (pdftoppm) | images des pages | GPL 2 ou 3, utilisé comme programme séparé, jamais intégré au code de Kekeli |
| Carlito et Caladea | polices compatibles avec Calibri et Cambria (mêmes mesures : un document ouvert dans Word garde sa mise en page) | SIL Open Font License 1.1 |
| DejaVu, Liberation | polices de secours | licences libres (Bitstream Vera, OFL 1.1) |

### 30.5 Qualité « comme Claude »
- Documents : page A4, marges propres, titres hiérarchisés à la couleur du thème, police Calibri (Carlito dans l'aperçu), numéros de page « 2 / 6 », sommaire et page de titre pour les longs documents, tableaux soignés (ligne de titres répétée sur chaque page, lignes alternées, montants alignés à droite, ligne de total), largeur des colonnes selon leur contenu, langue du document réglée (correcteur de Word en français).
- Présentations 16:9 : une idée par diapositive, titres qui disent l'idée, peu de texte, mises en page variées ; 5 thèmes sobres (« sobre », « lumière », « océan », « terre », « forêt ») et une couleur au choix ; taille du texte calculée pour que rien ne déborde ; aucune ombre ; numéro et titre en bas ; notes de l'orateur.
- Contenu : français correct ou la langue demandée, chiffres en chiffres, FCFA par défaut, « Mixx by Yas » et « Flooz », jamais « T-Money » ; calculs des tableaux vérifiés ; rien d'inventé (ce qui manque reste entre crochets, au plus 2 crochets dans une présentation) ; aucun logo ni image de personne réelle.

### 30.6 Mesure réelle (12 novembre 2026, formule Pro, GPT-6.1 Sol, téléphone simulé)
Toutes les demandes sont parties de « Que voulez-vous créer aujourd’hui ? » (`scripts/essai-documents.js`) ; chaque fichier a été converti en images et relu page par page (rien de coupé, rien qui déborde, accents corrects).

| Demande | Durée | Résultat | Coût (estimation, 1 $ = 590 FCFA) |
|---|---|---|---|
| CV | 50 s | 1 page, propre | 0,010 $ ≈ 6 FCFA |
| Lettre de motivation | 40 s | 1 page | 0,009 $ ≈ 5,5 FCFA |
| Devis en FCFA (commerçant de Lomé) | 40 s | 1 page, total 690 000 FCFA et acompte de 345 000 FCFA justes | 0,012 $ ≈ 7 FCFA |
| Rapport de 6 pages avec tableau (plan accepté) | 119 s | 8 pages : titre, sommaire, 6 pages, 3 tableaux | 0,054 $ ≈ 32 FCFA |
| Présentation de 8 diapositives (école de formation) | 40 s | 8 diapositives variées | 0,015 $ ≈ 9 FCFA |
| Correction d'un Word joint | 38 s | fautes corrigées, mise en page et gras gardés | 0,006 $ ≈ 4 FCFA |
| Modification (« titre et objet en bleu ») | 39 s | nouvelle version, l'ancienne gardée | 0,011 $ ≈ 6 FCFA |

- **Coût moyen** (estimation à partir des tokens mesurés et du temps d'ordinateur) : **environ 11 FCFA par document** (5 à 7 FCFA pour une page, 32 FCFA pour un rapport de 6 pages), **environ 9 FCFA par présentation** de 8 diapositives. L'installation de l'atelier (une fois par projet) compte pour l'essentiel du temps d'ordinateur.
- **Défauts trouvés pendant la mesure et corrigés** : l'interface d'OpenAI exige le mot « JSON » dans le message (sinon refus) ; un CV prenait le nom d'un ancien employeur comme nom du projet (le nom vient maintenant du modèle) ; le rapport faisait 11 pages avec des pages presque vides (les sauts de page du modèle sont ignorés et la longueur est guidée : environ 400 mots par page) ; une couleur demandée pour l'objet d'une lettre n'était pas possible (champ « couleur » par élément) ; une correction perdait le gras d'une partie de phrase (le texte corrigé est maintenant réparti sur les morceaux d'origine) ; texte trop petit dans les tableaux des diapositives.
- **Crédit OpenAI dépensé pour toute la pull request : environ 0,21 $** (plafond fixé à 2 $), mesures refaites comprises.

Tests : `tests/Feature/DocumentsTest.php` (11 tests), `runner/tests/documents.test.js` (9 tests). Captures et fichiers produits : `docs/captures/documents.md`.

**Reste à faire, hors de cette pull request** : faire relire le moteur de rendu sur d'autres modèles de documents (factures numérotées, contrats, tableaux très larges) ; afficher un aperçu pendant la fabrication (aujourd'hui, l'aperçu arrive à la fin) ; garder l'atelier en pause entre deux demandes pour éviter la réinstallation.

## 31. Images et affiches, comme Claude et ChatGPT (13 novembre 2026)

**Décision du porteur du projet** (après la validation de la pull request 45) : images et affiches, en commençant par six finitions des documents (commit séparé).

### 31.1 Les six finitions des documents (commit séparé)
1. **Carte du fichier** sous la réponse de Kekeli, comme Claude : icône du type, nom, formats, « Ouvrir » (l'onglet du fichier, à cette version) et « Télécharger » (`resources/views/projects/partials/file-card.blade.php`).
2. **« Récents »** : une icône par type (site, application, agent, automatisation, document, présentation, image, discussion : `Project::TYPES`, `typeKey`, `typeIcon`) et des filtres seulement pour les types présents ; sur téléphone, les filtres défilent de côté.
3. Plus de statut « Brouillon » sous le titre d'un fichier (il ne concerne que la mise en ligne).
4. **Un seul « Télécharger »** par écran : celui du panneau du fichier (le doublon de l'en-tête est retiré).
5. **Ligne d'actions d'un fichier** : « A créé le document · Word, PDF », « A modifié la présentation · version 2 », dépliable comme avant.
6. **Majuscule** au début de chaque cellule de tableau, de chaque puce et de chaque point (consigne et filet de sécurité `majuscule` dans `runner/src/documents/redaction.js`).

Captures « avant / après » : `docs/captures/images.md`. Tests : `tests/Feature/DocumentFinishesTest.php`, `runner/tests/documents.test.js`.

### 31.2 Ce que reçoit le client
- **Des images** (photo de produit, illustration, visuel ou bannière pour les réseaux sociaux) et **des affiches** (avec titre, prix, date, lieu, contact).
- **Formats selon l'usage**, choisis par Kekeli si le client ne dit rien : carré 1 080 × 1 080 (Facebook, Instagram), vertical 1 080 × 1 920 (statut WhatsApp, TikTok), paysage 1 200 × 630 (bannière), A4 et A3 pour imprimer (200 points par pouce).
- **Un aperçu**, **« Télécharger »** (PNG, et PDF à la vraie taille pour une affiche A4 ou A3), **des versions** comme les documents, et **la carte du fichier** dans la discussion. Onglet « Image ».
- **Modification par la discussion** (« mets le prix en plus gros », « fond vert ») : une nouvelle version, l'ancienne reste.
- **Photo ou logo du client** joint avec « + » : posé tel quel sur l'image, jamais redessiné.

### 31.3 Fonctionnement
- Outil **« creer_image »** du premier répondant (discussion), **« modifier_document »** dans un projet d'image ; en cas de doute avec un document ou une présentation, **une seule question courte**. Toujours fait tout de suite, sans plan (`app/Agent/Tools/CreateImage.php`).
- **Structure** écrite par le modèle de construction (`CONSIGNES_IMAGE`, `runner/src/documents/images.js`) : usage, format, disposition, couleur, description du fond en anglais **sans aucun texte**, et les **textes exacts** repris du message du client (rien d'inventé : un prix, un numéro ou une date qui manque n'est pas écrit).
- **Deux méthodes** (choix après l'essai de la partie 31.5) :
  - **« modele »** (formules payantes) : le générateur d'OpenAI dessine toute l'affiche, texte compris ; puis un modèle de vision **relit chaque texte** (`lireTextes`, `texteConforme`) : chaque texte attendu doit y être exactement (lettres, accents, prix, numéros) et **aucun texte non demandé** ne doit apparaître (slogan inventé, enseigne, faux logo). Sinon, une deuxième affiche ; si elle est encore fausse, la méthode « code ».
  - **« code »** (formule Gratuite, logo ou photo du client, et secours) : le générateur crée seulement le fond, sans texte ; **le texte est posé par le code** (`runner/src/documents/rendu/kekeli_images.py`, Pillow et la police libre Inter), avec un voile sombre exactement derrière le bloc de texte : **toujours exact et lisible**.
- **Une modification** garde le dessin : la version précédente (son fond, ou l'affiche dessinée) est relue par le Runner (adresse signée `runner.versions.background`) ; l'affiche dessinée est modifiée par le générateur (« images/edits ») puis relue ; un fond inchangé n'est pas recréé.
- **Où** : les clés restent dans le Runner (générateur, relecture) ; la composition se fait dans l'**atelier E2B isolé** des documents (sans clé, sans Internet après l'installation de `fonts-inter` et de Pillow 12.3.0).
- **Coût réel** décompté de l'énergie : tokens de l'image (prix officiels dans `config/kekeli.php`, `image_prices`, événement `image_payante`), image gratuite au prix réglé (`image_price_usd`), rédaction, relecture et ordinateur ; compté aussi dans le **plafond OpenAI du jour**. **Plafond atteint** : l'image se fait avec le générateur gratuit (une image, contrairement à une construction, se termine bien avec lui). **« Énergie épuisée »** arrête le travail comme ailleurs.
- **Générateur par formule** dans Administration → Réglages (`image_models`, liste fermée `AnswerModels::IMAGE_MODELS`, vérifiée aussi par le Runner, `GENERATEURS`). **Fournisseurs toujours cachés** au client (testé).
- **Images interdites** (consignes du premier répondant et du graphiste) : personne réelle identifiable, personnage protégé, logo ou marque réelle non fournis par le client, contenu choquant ou sexuel : **refus poli en une phrase et une autre proposition** (par exemple une personne imaginaire, ou son propre logo à joindre avec « + »). Le générateur d'OpenAI garde aussi sa propre modération.

### 31.4 Bibliothèques et licences (vérifiées le 13 novembre 2026)
| Outil | Rôle | Licence |
|---|---|---|
| Pillow 12.3.0 | composition, PNG, PDF | HPND (libre) |
| Inter (paquet Debian « fonts-inter ») | police des textes posés | SIL Open Font License 1.1 |
| FLUX.1 schnell (Cloudflare Workers AI) | fond, formule Gratuite | images utilisables commercialement selon les conditions de Cloudflare et de Black Forest Labs |

### 31.5 Essai comparatif (13 novembre 2026)
Cinq jeux : affiche de promotion d'une boutique de pagnes à Lomé (prix en FCFA, « Mixx by Yas » et Flooz), affiche d'inscription d'une école de formation, photo de produit (chaussures), bannière carrée pour Facebook, image avec un logo joint (`runner/scripts/comparer-images.mjs`, images dans `docs/captures/images/comparaison/`). Prix officiels lus le 13 novembre 2026 : OpenAI « GPT Image 2.6 » 5 $ (texte), 8 $ (image donnée) et 30 $ (image produite) par million de tokens ; Cloudflare 0,011 $ pour 1 000 neurones au-delà de 10 000 gratuits par jour.

| Générateur | Qualité (jugée image par image) | Texte dessiné par le modèle | Rapidité | Coût par image (estimation, 1 $ = 590 FCFA) |
|---|---|---|---|---|
| Cloudflare FLUX.1 schnell | bonne pour un fond ; produits parfois bancals (une seule chaussure) | non essayé : le texte est toujours posé par le code | 1,6 à 2,4 s | 0,0006 $ ≈ 0,4 FCFA (gratuit dans les 10 000 neurones du jour) |
| Cloudflare FLUX.2 klein 4B | la plus faible (chaussures usées, matières floues) | non essayé | 4,6 à 7,9 s | 0,0011 à 0,0017 $ ≈ 0,7 à 1 FCFA |
| OpenAI GPT Image 2.6 « flare » (qualité moyenne) | la meilleure : vraies affiches de graphiste, lumière, mise en page | **exact dans les 5 essais**, mais 1 affiche sur 4 avec du texte non demandé (slogan et faux logo de l'école) : d'où la relecture | 6,3 à 10 s | 0,034 à 0,044 $ ≈ 20 à 26 FCFA |
| OpenAI GPT Image 2.6 « sunburst » (qualité moyenne) | identique à « flare » à l'œil | exact | 24,6 s | même prix ≈ 20 FCFA |

**Choix** : formules payantes (Pro, Expert, Agence) : **GPT Image 2.6 « flare »**, affiche dessinée puis relue (le meilleur en qualité, aussi rapide que les autres et 3 fois plus rapide que « sunburst » au même prix) ; Gratuite : **FLUX.1 schnell** de Cloudflare avec le texte posé par le code (le meilleur abordable : gratuit, le plus rapide, et texte toujours exact). Logo ou photo du client : toujours la méthode « code » (le générateur redessinait le logo).

### 31.6 Essai réel de bout en bout (13 novembre 2026, téléphone simulé, `scripts/essai-images.js`)
| Demande | Formule | Durée totale | Méthode | Coût réel (estimation) |
|---|---|---|---|---|
| Affiche pagnes (statut WhatsApp) | Pro | 55 s | affiche dessinée, relue | 0,051 $ ≈ 30 FCFA |
| Affiche école A4 (PNG et PDF) | Pro | 63 s | affiche dessinée, relue | 0,049 $ ≈ 29 FCFA |
| Photo de produit (chaussures) | Pro | 55 s | image seule | 0,058 $ ≈ 34 FCFA |
| Bannière carrée Facebook | Pro | 59 s | affiche dessinée, relue | 0,055 $ ≈ 32 FCFA |
| Publication avec logo joint | Pro | 46 s | fond et texte par le code | 0,058 $ ≈ 34 FCFA |
| « Mets le prix en plus gros » | Pro | 58 s | affiche modifiée, relue | 0,070 $ ≈ 41 FCFA |
| Affiche avec une célébrité | Pro | 7 s | refus poli, autre proposition | 0,001 $ |
| Affiche pagnes, bannière, logo, chaussures | Gratuite | 35 à 60 s | fond et texte par le code | 0,005 à 0,007 $ ≈ 3 à 4 FCFA |

- Les textes de toutes les affiches sont exacts. Le premier répondant a même repéré une erreur dans la demande d'essai (« lundi 1er décembre 2026 », qui est un mardi) et a posé une seule question avant de créer l'affiche.
- **Défauts trouvés pendant l'essai et corrigés** : la modification d'une affiche dessinée était refusée (type d'image manquant dans l'envoi) ; une erreur passagère de Cloudflare laissait un fond uni (deuxième essai ajouté) ; le texte posé sur un fond chargé était peu lisible (voile placé exactement derrière le bloc de texte).
- **Crédit OpenAI dépensé pour toute la pull request : environ 0,89 $** (plafond 1 $) : essai comparatif 0,44 $, essais de relecture et de modification 0,05 $, essai réel de bout en bout 0,39 $.

Tests : `tests/Feature/ImagesTest.php` (8 tests), `runner/tests/images.test.js` (12 tests). Captures et images produites : `docs/captures/images.md`.

**Reste à faire, hors de cette pull request** : la modification d'une affiche dessinée change parfois peu (le générateur garde le dessin très fidèlement) ; garder l'atelier en pause entre deux demandes ; essayer d'autres formats (bannière YouTube, carte de visite) si les clients les demandent.

## Sources

- Moteur : [OpenCode](https://opencode.ai/docs/), [fournisseurs OpenCode](https://opencode.ai/docs/providers/), [permissions OpenCode](https://opencode.ai/docs/permissions/), [serveur OpenCode](https://opencode.ai/docs/server/), [OpenCode sur E2B](https://docs.e2b.dev/agents/opencode.md), [OpenHands Software Agent SDK](https://docs.openhands.dev/sdk), [Gemini CLI](https://github.com/google-gemini/gemini-cli), [authentification Gemini CLI](https://geminicli.com/docs/get-started/authentication/)
- Gemini : [limites](https://ai.google.dev/gemini-api/docs/rate-limits), [prix](https://ai.google.dev/gemini-api/docs/pricing), [conditions](https://ai.google.dev/gemini-api/terms)
- Chiffres de quotas non officiels : [ScriptByAI](https://www.scriptbyai.com/gemini-api-free-tier-limits/), [Klymentiev](https://klymentiev.com/blog/gemini-api-free-tier)
- E2B : [prix](https://e2b.dev/pricing), [accès à Internet et règles par hôte](https://docs.e2b.dev/network/internet-access.md), [secrets injectés dans les requêtes](https://docs.e2b.dev/secrets/inject.md), [limites des secrets](https://docs.e2b.dev/secrets/limits.md)
- Autres fournisseurs gratuits : [conditions OpenRouter](https://openrouter.ai/terms), [confidentialité OpenRouter](https://openrouter.ai/privacy), [limites Groq](https://console.groq.com/docs/rate-limits), [contrat Groq](https://console.groq.com/docs/legal/services-agreement), [limites OpenRouter](https://openrouter.ai/docs/api-reference/limits), [limites Cerebras](https://inference-docs.cerebras.ai/support/rate-limits), [prix Cerebras](https://www.cerebras.ai/pricing), [Mistral : offre Experiment](https://help.mistral.ai/en/articles/455206-how-can-i-try-the-api-for-free-with-the-experiment-plan), [Mistral : désactiver l'entraînement](https://help.mistral.ai/en/articles/455207-can-i-opt-out-of-my-input-or-output-data-being-used-for-training), [GitHub Models](https://docs.github.com/en/github-models/use-github-models/prototyping-with-ai-models), [OpenCode Zen](https://opencode.ai/docs/zen/)
- Sources secondaires sur les données d'OpenRouter : [TrueFoundry](https://www.truefoundry.com/blog/openrouter-free-models-whats-actually-free-and-what-it-costs-you)
- Agent général (partie 26) : [prix de Claude](https://platform.claude.com/docs/en/about-claude/pricing), [coûts de Claude Code](https://code.claude.com/docs/en/costs), [prix de Gemini](https://aistudio.google.com/docs/pricing), [droits de Google Drive](https://developers.google.com/workspace/drive/api/guides/api-specific-auth), [droits de Gmail](https://developers.google.com/workspace/gmail/api/auth/scopes), [CNIL, communications électroniques](https://www.cnil.fr/fr/communication-electronique-quelles-regles), [CNIL, adresses professionnelles](https://cnil.fr/fr/node/169044), [fin de l'offre gratuite de Brave](https://www.implicator.ai/brave-drops-free-search-api-tier-puts-all-developers-on-metered-billing.md), [design de Claude, extraction non officielle](https://open-design.ai/systems/claude/)
- Recherche sur Internet (partie 26.13) : [prix de Gemini et recherche Google intégrée](https://aistudio.google.com/docs/pricing), [Tavily](https://docs.tavily.com/), [Exa](https://docs.exa.ai/)
- Finitions de la discussion et de la recherche (partie 26.14) : [ChatGPT, recherche et citations](https://help.openai.com/articles/9237897-chatgpt-search), [ChatGPT, « Stop generating » et « Regenerate »](https://help.openai.com/en/articles/7996703-troubleshooting-chatgpt-error-messages), [notes de version de ChatGPT](https://help.openai.com/en/articles/6825453-chatgpt-release-notes), [Claude, recherche sur Internet et citations](https://support.claude.com/en/articles/10684626-enable-and-use-web-search), [Claude, renommer une conversation](https://support.claude.com/en/articles/8230524-how-can-i-delete-or-rename-a-conversation), [Perplexity, fonctionnement et citations numérotées](https://www.perplexity.ai/help-center/en/articles/10352895-how-does-perplexity-work), [Lovable, mode discussion et carte « Start building »](https://docs.lovable.dev/features/chat-mode), [Lovable, bouton d'arrêt](https://docs.lovable.dev/features/projects/chat), [Bolt.new, bouton « Implement this plan »](https://support.bolt.new/best-practices/discussion-mode), [Manus, arrêter une tâche](https://manus.im/docs/features/desktop)
- Agents IA (partie 28) : [licence de LangGraph en JavaScript](https://github.com/langchain-ai/langgraphjs/blob/main/LICENSE), [licence de CrewAI](https://github.com/crewAIInc/crewAI/blob/main/LICENSE), [licence de Dify](https://github.com/langgenius/dify/blob/main/LICENSE), [paquet @langchain/langgraph](https://www.npmjs.com/package/@langchain/langgraph), [paquet crewai](https://pypi.org/project/crewai/), [interface compatible OpenAI de Gemini](https://ai.google.dev/gemini-api/docs/openai)
- GitHub et automatisations (partie 29) : [événements qui lancent un workflow, tâches planifiées](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows#schedule), [syntaxe des workflows](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax), [facturation de GitHub Actions](https://docs.github.com/en/billing/concepts/product-billing/github-actions), [jetons des GitHub Apps et flux appareil](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/generating-a-user-access-token-for-a-github-app), [autorisations des GitHub Apps](https://docs.github.com/en/rest/authentication/permissions-required-for-github-apps), [secrets des dépôts](https://docs.github.com/en/rest/actions/secrets), [Resend, envoi d'e-mail](https://resend.com/docs/api-reference/emails/send-email), [Resend, domaine d'essai](https://resend.com/docs/knowledge-base/403-error-resend-dev-domain)
- Documents et présentations (partie 30) : [python-docx](https://pypi.org/project/python-docx/), [python-pptx](https://pypi.org/project/python-pptx/), [pypdf](https://pypi.org/project/pypdf/), [licence de LibreOffice](https://www.libreoffice.org/about-us/licenses/), [police Carlito](https://github.com/googlefonts/carlito)
