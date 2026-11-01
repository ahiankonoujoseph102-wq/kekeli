# Kekeli — consignes de travail

Kekeli veut dire « la lumière » en éwé. Ce fichier rassemble le cahier des charges, les décisions déjà prises et les règles à suivre à chaque séance de travail. Il fait foi : en cas de doute, on suit ce fichier, et on le met à jour quand une décision change.

## Règles de travail

1. **Langue** : toujours répondre en français, y compris dans les messages de progression, les messages de commit et les descriptions de pull request. Un français correct, complet, **sans abréviations** (écrire « par exemple », « c'est-à-dire », « numéro », jamais « ex. », « càd », « n° »).
2. **Une étape à la fois** : on ne commence une étape que lorsque la précédente est terminée, testée et validée par le porteur du projet. On ne construit rien qui appartient à une étape suivante.
3. **Tests avant chaque envoi** : avant chaque `git push`, lancer `php artisan test` (tous les tests doivent passer) et `vendor/bin/pint --test` (style du code). Toute nouvelle fonctionnalité arrive avec ses tests.
4. **Aucune clé ni aucun secret** dans le code, dans les fichiers versionnés, dans les commits, dans les messages ou dans les réponses. Les clés vont uniquement dans les variables d'environnement protégées (Laravel Cloud, Runner). Ne jamais afficher une clé, même partiellement.
5. **Le porteur du projet est débutant et travaille uniquement depuis son téléphone** : expliquer en français simple, donner des gestes précis à faire sur le téléphone, et vérifier soi-même l'affichage en format téléphone avant de livrer.
6. Les coûts annoncés (modèles, serveurs) sont des **estimations** tant qu'ils n'ont pas été mesurés : le dire clairement à chaque fois.
7. **Pull request de chaque étape** : Claude ouvre la pull request, surveille lui-même la vérification automatique de GitHub (tests et style), corrige et renvoie si elle échoue, et **fusionne lui-même dans `main` quand tout est vert**. Il ne fusionne jamais tant qu'une vérification est rouge ou en cours. Il prévient le porteur du projet une fois la fusion faite.
8. **Captures d'écran à chaque étape** : tant que le site n'est pas hébergé, le résultat de chaque étape est montré par des captures prises sur un écran de téléphone simulé (navigateur en français), rangées dans `docs/captures/etape-N/` avec une page `docs/captures/etape-N.md` qui les affiche toutes. Le script `scripts/captures-telephone.js` sert de point de départ. Donner au porteur du projet les liens GitHub vers ces captures.

## Cahier des charges

### Le porteur du projet
Joseph Ahiankonou, fondateur de Lomé IA School (formation en intelligence artificielle, création d'agents IA et d'automatisations pour les particuliers et les entreprises). Bureau : Adidogomé, Lycée Moderne 2, Lomé (Togo). Il travaille uniquement depuis son téléphone.

### L'objectif
« Kekeli — l'agent IA qui code pour vous » : un agent de programmation en ligne, de très haut niveau, qui travaille comme Claude Code. Il lit, crée, modifie et corrige les fichiers, lance les commandes, teste son travail, corrige ses erreurs tout seul et accède à Internet. Les gens doivent pouvoir construire de vrais projets professionnels avec lui. Le projet doit devenir rentable.

### Pour qui
Tout le monde : débutants, entreprises et développeurs.
- **Mode simple** (construit en premier) : on décrit son projet, l'agent le construit, on voit le résultat en direct.
- **Mode développeur** (ajouté ensuite) : accès au code, aux fichiers et à GitHub.

### Ce qu'il sait faire
Phase 1, construite dans cet ordre, une étape à la fois, chacune terminée et testée avant la suivante :
1. Sites web professionnels
2. Applications
3. Agents IA
4. Automatisations

Phase 2 : montage vidéo, affiches, textes.

### Fonctionnement technique
- Moteur de l'agent : **moteur multi-modèles** (décision du 1er novembre 2026, qui remplace le Claude Agent SDK prévu au départ). On doit pouvoir changer de modèle (Gemini, Claude, OpenAI ou autre) par un simple réglage, sans reconstruire Kekeli.
- Pendant toute la construction et les tests, le cerveau est **Gemini, avec l'offre gratuite de Google AI Studio**.
- Pendant la construction et au début du lancement, Kekeli utilise **les offres gratuites de plusieurs fournisseurs**, rangés dans une **liste réglable sans toucher au code** : quand le premier atteint sa limite, Kekeli passe automatiquement au suivant ; l'utilisateur voit un message clair si tous sont épuisés ; les requêtes sont comptées par fournisseur et visibles dans l'administration, pour savoir quand passer à une clé payante.
- Au lancement, on choisit le ou les modèles payants au meilleur rapport qualité-prix : un modèle économique pour les petites tâches, un modèle puissant pour les tâches difficiles.
- Un ordinateur virtuel isolé par utilisateur, sur un service spécialisé.
- L'utilisateur voit son projet en direct pendant la construction.
- Publication du projet en ligne en un clic.
- Site de Kekeli (comptes, conversations, paiements, administration) : Laravel, hébergé sur Laravel Cloud.
- Base de données : PostgreSQL serverless sur Laravel Cloud.
- Code rangé dans le dépôt GitHub privé « kekeli ».
- Utilisable sur le web, depuis le téléphone et l'ordinateur. Application à télécharger plus tard.

### Sécurité (comme Claude Code)
- Ordinateur virtuel isolé, effacé à la fin.
- Internet limité à une liste de sites autorisés, modifiable par l'utilisateur.
- Permission demandée avant une action risquée.
- Conditions d'utilisation : piratage et spam interdits ; l'utilisateur est responsable de ce qu'il utilise.
- Images prises par défaut sur des sites libres de droits (Pexels, Unsplash, Pixabay).
- Aucune clé secrète dans le code ni dans les messages.

### Argent
- Formule gratuite limitée pour essayer.
- Abonnements payés par Mobile Money, avec une limite d'utilisation qui se recharge (comme Claude Code).
- Formules supérieures pour les gros utilisateurs.
- Suivi de la consommation de chaque utilisateur.
- Prix exacts calculés avant le lancement.

### Langue
Français d'abord, anglais ensuite.

### Nom de domaine
kekeli.dev, à acheter avant la mise en ligne.

## Décisions prises

### Architecture
- **Site Kekeli** : Laravel 12 avec le kit officiel Livewire (Livewire 4, Volt, Flux en version gratuite), hébergé sur Laravel Cloud, base PostgreSQL serverless.
- **Moteur de l'agent** : **OpenCode** (licence MIT, plus de 75 fournisseurs de modèles, modèle officiel « opencode » chez E2B). **Validé par le porteur du projet le 1er novembre 2026.** Plan de secours : OpenHands. Comparaison complète dans `docs/plan.md`. Fixer une version précise d'OpenCode et la mettre à jour volontairement.
- **Runner** (à partir de l'étape 2) : un petit service Node.js séparé, qui crée et efface les ordinateurs virtuels, pilote OpenCode à distance (mode serveur, protégé par un mot de passe propre à chaque projet) et compte les requêtes et les tokens de chaque utilisateur. C'est le seul endroit qui détient les clés.
- **Ordinateur virtuel** : **E2B** (Daytona en plan de secours), à partir du modèle « opencode ». **Aucune clé dans l'ordinateur virtuel** : piste retenue, E2B ajoute lui-même la clé aux requêtes sortantes vers le fournisseur du modèle (règles par hôte, en bêta publique, à confirmer à l'étape 2) ; secours : passerelle dans le Runner une fois celui-ci hébergé.
- **Choix du modèle** : un réglage de Kekeli, sous la forme « fournisseur/modèle », avec deux rôles réglables séparément (modèle économique et modèle puissant). Jamais de nom de modèle écrit en dur ailleurs que dans la configuration.
- **Communication** : Laravel envoie les demandes au Runner par HTTPS avec une signature ; le Runner renvoie les étapes de l'agent à Laravel, qui les affiche en direct sur le téléphone.

### Modèles
- **Construction et tests (étapes 2 à 7)** : Gemini, offre gratuite de Google AI Studio. Pour faire tourner l'agent, un modèle **Flash-Lite** (environ 500 requêtes par jour, à vérifier dans AI Studio) ; les Gemini Flash plus forts n'ont qu'environ 20 requêtes par jour en gratuit. Les modèles gratuits valident la mécanique, pas la qualité finale : petit budget payant prévu à l'étape 6 pour comparer de vrais modèles.
- **Offre gratuite = données utilisées par Google** (amélioration des produits, relecture humaine possible) : uniquement des données de test inventées pendant la construction, jamais de données réelles de clients.
- **Liste des fournisseurs gratuits** (détails, limites et conditions dans `docs/plan.md`), dans cet ordre : 1. Google AI Studio (Gemini), 2. OpenRouter (modèles « free »), 3. Mistral (offre « Experiment », entraînement désactivé dans sa console). Écartés pour l'agent : Groq et GitHub Models (environ 8 000 tokens par minute ou par requête, trop peu pour un agent), Cerebras (crédit unique de 5 $, « pas destiné à la production »). Un seul compte par fournisseur : ne jamais multiplier les comptes pour contourner les quotas.
- **Réglages** (variables d'environnement, jamais dans le code) : `KEKELI_FOURNISSEURS` donne l'ordre de la liste (par exemple `gemini,openrouter,mistral`) ; le modèle de chaque fournisseur est réglable de la même façon ; un fournisseur sans clé est ignoré. Noms des clés : `GEMINI_API_KEY`, `OPENROUTER_API_KEY`, `MISTRAL_API_KEY`, et `E2B_API_KEY` pour les ordinateurs virtuels.
- **Basculement** : sur une réponse « limite atteinte » (code 429 ou quota du jour épuisé), le fournisseur est mis de côté jusqu'à sa remise à zéro, et la même tâche reprend avec le suivant. Si tous sont épuisés, message clair à l'utilisateur ; son projet reste sauvegardé.
- **Comptage** : requêtes, refus pour limite atteinte et tokens enregistrés par fournisseur et par jour ; page d'administration avec l'usage du jour comparé à la limite et une alerte à partir de 80 %.
- **Lancement (étape 8)** : les offres gratuites servent seulement la formule gratuite de Kekeli ; les abonnés payants passent par des clés payantes. Choix d'un modèle économique et d'un modèle puissant payants au meilleur rapport qualité-prix (Gemini avec facturation Google Cloud, Claude, OpenAI ou autre). Jamais de Gemini gratuit pour des utilisateurs situés en Europe, en Suisse ou au Royaume-Uni.
- **Politique de confidentialité (étape 8)** : elle doit dire clairement que, **dans l'offre gratuite, les demandes peuvent être utilisées par les fournisseurs d'IA pour améliorer leurs services**, et nommer ces fournisseurs.
- **Les coûts par tâche sont des estimations. Ils seront mesurés à l'étape 5, avant de choisir les modèles et de fixer les prix.**

### Paiements
- **Au départ, pas d'agrégateur de paiement.** L'utilisateur paie par Mobile Money, envoie la **capture d'écran** de son paiement dans Kekeli, et **l'administrateur valide manuellement** l'abonnement.
- Au Togo, écrire **« Mixx by Yas »** et **« Flooz »**. Ne jamais écrire « T-Money ».
- Un agrégateur (CinetPay, PayDunya, FedaPay ou autre) sera ajouté plus tard.

### Publication des sites des clients
- **Au départ, adresses gratuites de Cloudflare (`pages.dev`).** Pas de deuxième nom de domaine.
- Les sites des clients ne sont jamais publiés sur kekeli.dev.

### Hébergement
- **Le site n'est pas mis en ligne sur Laravel Cloud tout de suite.** Le porteur du projet le fera plus tard ; en attendant, chaque étape est montrée par des captures d'écran (règle numéro 8).
- L'hébergement devient **indispensable à l'étape 8** (version bêta avec de vrais utilisateurs et des paiements). Il est **conseillé dès l'étape 6**, pour que le porteur du projet puisse essayer lui-même l'agent sur son téléphone avant la bêta.
- Sans hébergement, les étapes 2 à 7 se construisent et se testent dans l'environnement de développement de Claude. À partir de l'étape 2, cela demande une clé Gemini (Google AI Studio) et une clé E2B, placées par le porteur du projet dans les réglages de l'environnement sous les noms `GEMINI_API_KEY` et `E2B_API_KEY` (et, s'il le souhaite, `OPENROUTER_API_KEY` et `MISTRAL_API_KEY`), jamais dans un message. **Ne pas commencer l'étape 2 avant que le porteur du projet ait écrit « clés placées ».**

### Langue de l'interface
- Les textes de l'interface sont écrits avec `__('Texte anglais')` et traduits dans `lang/fr.json` ; les messages d'erreur sont dans `lang/fr/`. La langue par défaut est le français. Ajouter l'anglais plus tard ne demandera qu'un fichier de traduction.
- Toute nouvelle phrase affichée doit être ajoutée à `lang/fr.json` (et chaque nouvelle règle de validation à `lang/fr/validation.php`).

## Plan des étapes

Le plan détaillé et à jour est dans [`docs/plan.md`](docs/plan.md).

| Numéro | Étape | État |
|---|---|---|
| 1 | Fondations : site Laravel en français, PostgreSQL, comptes, « Mes projets » | Terminée (captures : `docs/captures/etape-1.md`) |
| 2 | Runner + agent OpenCode dans un ordinateur E2B, cerveau Gemini gratuit, liste de fournisseurs gratuits avec basculement automatique, comptage par fournisseur | À faire (attend « clés placées ») |
| 3 | Aperçu en direct | À faire |
| 4 | Sécurité : permissions, sites autorisés, effacement, sauvegarde et reprise | À faire |
| 5 | Consommation, quotas, mesure réelle des coûts, comparaison des modèles, administration | À faire |
| 6 | Qualité « site professionnel », essais avec des modèles plus forts | À faire |
| 7 | Publication en un clic sur Cloudflare (`pages.dev`) | À faire |
| 8 | Choix des modèles payants pour les abonnés, politique de confidentialité (offre gratuite : demandes utilisables par les fournisseurs d'IA), paiement Mobile Money par capture d'écran validée par l'administrateur, conditions d'utilisation, achat de kekeli.dev, mise en ligne sur Laravel Cloud, version bêta | À faire |

## Repères techniques

- Pages de l'application : composants Volt dans `resources/views/livewire/` (projets dans `projects/`).
- Modèles : `app/Models/` (`User`, `Project` avec identifiant ULID, `Message`).
- Un utilisateur ne voit et ne modifie que ses propres projets : toujours passer par `Auth::user()->projects()` ou vérifier le propriétaire (réponse 404 sinon).
- Commandes utiles :
  - `php artisan test` : tous les tests (SQLite en mémoire par défaut).
  - Tests sur PostgreSQL : définir `DB_CONNECTION=pgsql` et les variables `DB_*`, puis `php artisan test` (c'est ce que fait l'intégration continue GitHub).
  - `vendor/bin/pint` : remettre le code au bon style ; `vendor/bin/pint --test` : vérifier sans modifier.
  - `npm run build` : construire l'interface.
