# Plan de Kekeli

- **1er novembre 2026** : plan validé, avec trois corrections du porteur du projet (paiements, publication, coûts).
- **1er novembre 2026, mise à jour** : décision du porteur du projet de ne pas dépendre d'un seul modèle payant. Kekeli repose sur un **moteur multi-modèles** ; pendant toute la construction, le cerveau est **Gemini, avec l'offre gratuite de Google AI Studio**.
- **1er novembre 2026, deuxième mise à jour** : **OpenCode est validé** comme moteur. Pendant la construction et au début du lancement, Kekeli utilise les **offres gratuites de plusieurs fournisseurs**, rangés dans une **liste réglable** : quand le premier atteint sa limite, Kekeli passe automatiquement au suivant. Les requêtes sont comptées par fournisseur.

- **1er novembre 2026, étape 2 livrée** : Runner, agent OpenCode dans E2B, Gemini Flash-Lite, clé injectée par E2B, basculement et comptage. Constats ci-dessous (section « Constats de l'étape 2 »).
- **8 novembre 2026** : étape 2 validée par le porteur du projet. **Étape 3 livrée** : aperçu en direct (onglets « Discussion » et « Aperçu »), essai réel fait le même jour avec E2B et Gemini. Détails dans la section « Aperçu en direct (étape 3) ».

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
| **Mistral** (offre « Experiment ») | Devstral, Codestral, Mistral Small et Medium | Non publiées officiellement (environ une requête par seconde selon des sites spécialisés) ; vérification par téléphone | **Utilisées pour entraîner les modèles**, sauf si on désactive l'option dans la console (menu « Privacy ») | Offre présentée comme un essai : production à faire sur l'offre payante | **Numéro 3** |
| Cerebras | gpt-oss-120b, Qwen 3.8 | 5 par minute, 1 million de tokens par jour, mais seulement **un crédit unique de 5 $ valable 30 jours**, avec carte bancaire | Pas d'entraînement selon des sources secondaires | **« Pas destiné à la production »** | Facultatif, appoint ponctuel |
| Groq | gpt-oss-120b, gpt-oss-20b, Qwen 3.8 | 30 par minute, 1 000 par jour, mais **8 000 tokens par minute** | **Pas d'entraînement** (contrat officiel) | **Autorisé** : intégration dans un produit vendu à des tiers permise (contrat officiel) | Trop petit pour l'agent ; possible plus tard pour les petites tâches |
| GitHub Models | Plusieurs modèles | Environ 150 par jour, mais environ **8 000 tokens par requête** | — | « Pour expérimenter » ; production sur l'offre payante | Écarté |
| OpenCode Zen (modèles gratuits d'OpenCode) | Plusieurs modèles « Free » | Non précisées, « pour une durée limitée » | Variable : certains n'entraînent pas, d'autres si | Non précisé | Seulement pour des essais pendant la construction |

**Attention aux comptes multiples.** Créer plusieurs comptes ou projets chez le même fournisseur pour multiplier les quotas gratuits est en général contraire à leurs conditions et peut entraîner la fermeture du compte. Kekeli utilise **un compte par fournisseur**.

**Plusieurs clés Gemini (vérifié le 9 novembre 2026 dans les pages officielles de Google)** :
- plusieurs clés **dans un même projet** ne donnent rien de plus : « les limites s'appliquent par projet, pas par clé » ;
- les clés **d'autres personnes** (même de la famille) : les conditions des API Google disent que les identifiants d'un développeur « sont destinés à être utilisés par vous », qu'il faut les garder confidentiels, et qu'il ne faut pas tenter de contourner les limites. Utiliser les comptes de proches pour multiplier les quotas gratuits risque la suspension des comptes, y compris celui du porteur du projet. **Recommandation : ne pas le faire** ; rien n'est construit pour cela. Les autres fournisseurs gratuits (OpenRouter, Mistral) restent possibles, avec un compte au nom du porteur du projet.

### Liste de fournisseurs et basculement automatique

- **Liste réglable sans toucher au code** : l'ordre des fournisseurs et le modèle de chacun se règlent dans les variables d'environnement, par exemple `KEKELI_FOURNISSEURS=gemini,openrouter,mistral`. Un fournisseur sans clé est simplement ignoré.
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
| 7 | Publication en un clic sur Cloudflare, **à une adresse gratuite `pages.dev`** | Appuyer sur « Publier » et ouvrir le site en ligne |
| 8 | **Choix final des modèles payants** (un économique, un puissant) pour les abonnés ; offres gratuites réservées à la formule gratuite ; **politique de confidentialité indiquant que, dans l'offre gratuite, les demandes peuvent être utilisées par les fournisseurs d'IA pour améliorer leurs services** ; paiement Mobile Money (Mixx by Yas, Flooz) par capture d'écran validée manuellement par l'administrateur ; conditions d'utilisation et politique de confidentialité ; achat de kekeli.dev ; **mise en ligne sur Laravel Cloud** ; version bêta | Envoyer une capture de paiement, la valider dans l'administration, voir la limite augmenter |

Tant que le site n'est pas hébergé, le résultat de chaque étape est montré par des captures prises sur un écran de téléphone simulé (`docs/captures/`). La mise en ligne sur Laravel Cloud devient **indispensable à l'étape 8** ; elle est **conseillée dès l'étape 6**, pour essayer soi-même l'agent sur son téléphone avant la bêta.

Après l'étape 8 : lancement de la création de sites web. Ensuite : applications, agents IA, automatisations, puis mode développeur (GitHub).

## 8. Comptes à créer et coûts de départ

| Compte | Usage | Quand | Coût au départ |
|---|---|---|---|
| GitHub | Le code | Déjà fait | 0 $ |
| **Google AI Studio** | Gemini, offre gratuite (variable `GEMINI_API_KEY`) | **Étape 2** | 0 $ |
| **OpenRouter** | Modèles gratuits, deuxième de la liste (variable `OPENROUTER_API_KEY`) | Étape 2, facultatif | 0 $ (50 requêtes par jour) ; 10 $ une seule fois pour passer à 1 000 requêtes par jour, facultatif |
| **Mistral** | Offre « Experiment », troisième de la liste (variable `MISTRAL_API_KEY`) | Étape 2, facultatif | 0 $ |
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
- OpenRouter et Mistral sont prêts dans le code mais **pas encore essayés** : leurs clés ne sont pas placées. Le modèle OpenRouter par défaut (`nvidia/nemotron-3-ultra-550b-a55b:free`) a été choisi dans la liste des modèles gratuits capables d'utiliser des outils ; il faudra le vérifier dès que la clé sera placée.
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
- `MISTRAL_API_KEY` : un autre fournisseur de secours (offre « Experiment », vérification par téléphone, limites non publiées).

### Ajouts à l'étape 6 (après validation)
- **Création d'images** : essai réel avec Cloudflare Workers AI (FLUX.1 schnell), offre gratuite, compte réservé à Kekeli. Les images pour des sites togolais sont réalistes et cohérentes (terrasse de restaurant, marché, couturière en tissu wax, salon de tresses), en 2 à 4 secondes chacune. Ce sont des personnes créées, pas de vraies personnes. Limite : des détails peuvent être approximatifs (par exemple l'attiéké absent d'un plat) ; une image créée n'est jamais présentée comme un vrai produit du client.
- **Nombre maximum d'images créées par jour**, pour tous les utilisateurs ensemble, réglable dans Administration → Réglages : 150 au départ, sous la limite gratuite de Cloudflare (10 000 « neurones » par jour, environ 58 par image de 1 024 × 1 024, soit environ 170 images ; estimation). Le compteur repart à minuit UTC, comme Cloudflare.
- **Pas de bonus de bienvenue.**
- **Énergie épuisée en cours de demande** : à chaque événement de l'agent, le site compare l'énergie utilisée (modèle, ordinateur, images, y compris le temps d'ordinateur de la demande en cours) à l'énergie de la formule. Dès qu'elle est épuisée, il répond « arrêter » au Runner, qui arrête l'agent aussitôt, sans vérification ni correction, puis sauvegarde le projet tel quel. L'utilisateur voit : « ☀️ Votre énergie est épuisée : Kekeli a arrêté le travail. Votre projet est sauvegardé tel quel, sans aucune perte. L'énergie se recharge à [heure] (heure de Lomé)… » avec le bouton « Voir les formules ». Après la recharge, ou après la validation d'une formule payante, le bouton « Continuer » reprend la même demande là où elle s'était arrêtée. L'arrêt se fait entre deux requêtes de l'agent : la dernière requête est donc comptée en entier.
- Les clés collées avec des crochets ou des guillemets sont nettoyées par le Runner, et le numéro de compte Cloudflare est extrait même de l'adresse d'une page du tableau de bord.

## 14. Risques et limites

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

## Sources

- Moteur : [OpenCode](https://opencode.ai/docs/), [fournisseurs OpenCode](https://opencode.ai/docs/providers/), [permissions OpenCode](https://opencode.ai/docs/permissions/), [serveur OpenCode](https://opencode.ai/docs/server/), [OpenCode sur E2B](https://docs.e2b.dev/agents/opencode.md), [OpenHands Software Agent SDK](https://docs.openhands.dev/sdk), [Gemini CLI](https://github.com/google-gemini/gemini-cli), [authentification Gemini CLI](https://geminicli.com/docs/get-started/authentication/)
- Gemini : [limites](https://ai.google.dev/gemini-api/docs/rate-limits), [prix](https://ai.google.dev/gemini-api/docs/pricing), [conditions](https://ai.google.dev/gemini-api/terms)
- Chiffres de quotas non officiels : [ScriptByAI](https://www.scriptbyai.com/gemini-api-free-tier-limits/), [Klymentiev](https://klymentiev.com/blog/gemini-api-free-tier)
- E2B : [prix](https://e2b.dev/pricing), [accès à Internet et règles par hôte](https://docs.e2b.dev/network/internet-access.md), [secrets injectés dans les requêtes](https://docs.e2b.dev/secrets/inject.md), [limites des secrets](https://docs.e2b.dev/secrets/limits.md)
- Autres fournisseurs gratuits : [limites Groq](https://console.groq.com/docs/rate-limits), [contrat Groq](https://console.groq.com/docs/legal/services-agreement), [limites OpenRouter](https://openrouter.ai/docs/api-reference/limits), [limites Cerebras](https://inference-docs.cerebras.ai/support/rate-limits), [prix Cerebras](https://www.cerebras.ai/pricing), [Mistral : offre Experiment](https://help.mistral.ai/en/articles/455206-how-can-i-try-the-api-for-free-with-the-experiment-plan), [Mistral : désactiver l'entraînement](https://help.mistral.ai/en/articles/455207-can-i-opt-out-of-my-input-or-output-data-being-used-for-training), [GitHub Models](https://docs.github.com/en/github-models/use-github-models/prototyping-with-ai-models), [OpenCode Zen](https://opencode.ai/docs/zen/)
- Sources secondaires sur les données d'OpenRouter : [TrueFoundry](https://www.truefoundry.com/blog/openrouter-free-models-whats-actually-free-and-what-it-costs-you)
