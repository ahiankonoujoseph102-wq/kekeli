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
8. **Aucune dépense sans l'accord du porteur du projet** (règle permanente, 9 novembre 2026) : tout doit être gratuit pendant la construction. Avant de proposer une solution payante, toujours chercher d'abord une solution gratuite. La clé Claude (Anthropic) ne sera achetée qu'à la fin du projet ; la comparaison avec Claude Haiku et Claude Sonnet se fera à ce moment-là.
9. **Captures d'écran à chaque étape** : tant que le site n'est pas hébergé, le résultat de chaque étape est montré par des captures prises sur un écran de téléphone simulé (navigateur en français), rangées dans `docs/captures/etape-N/` avec une page `docs/captures/etape-N.md` qui les affiche toutes. Le script `scripts/captures-telephone.js` sert de point de départ. Donner au porteur du projet les liens GitHub vers ces captures.

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
- Pendant la construction et au début du lancement, Kekeli utilise **les offres gratuites de plusieurs fournisseurs**, rangés dans une **liste réglable sans toucher au code** : quand le premier atteint sa limite, Kekeli passe automatiquement au suivant ; si tous sont épuisés, la demande est mise en attente sans que l'utilisateur sache qu'il s'agit d'offres gratuites ; les requêtes sont comptées par fournisseur et visibles dans l'administration, pour savoir quand passer à une clé payante.
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
- **Ordinateur virtuel** : **E2B** (Daytona en plan de secours), à partir du modèle « opencode ». **Aucune clé dans l'ordinateur virtuel** : **confirmé à l'étape 2**, le Runner range les clés dans les « secrets » d'E2B (noms `kekeli_gemini`, `kekeli_openrouter`, `kekeli_mistral`) et le proxy de sortie d'E2B les ajoute lui-même aux requêtes HTTPS vers le fournisseur (règles par hôte, en bêta publique) ; OpenCode ne reçoit qu'une fausse clé. Secours : passerelle dans le Runner une fois celui-ci hébergé.
- **Version d'OpenCode fixée : 1.19.4** (celle du modèle E2B « opencode » le 1er novembre 2026), avec le kit `@opencode-ai/sdk` 1.19.4 et le kit `e2b` 2.62.0. Les mettre à jour volontairement, ensemble, après essai.
- **Choix du modèle** : un réglage de Kekeli, sous la forme « fournisseur/modèle », avec deux rôles réglables séparément (modèle économique et modèle puissant). Jamais de nom de modèle écrit en dur ailleurs que dans la configuration.
- **Communication** : Laravel envoie les demandes au Runner par HTTPS avec une signature ; le Runner renvoie les étapes de l'agent à Laravel, qui les affiche en direct sur le téléphone.

### Modèles
- **Construction et tests (étapes 2 à 7)** : Gemini, offre gratuite de Google AI Studio. Pour faire tourner l'agent, un modèle **Flash-Lite** (environ 500 requêtes par jour, à vérifier dans AI Studio) ; les Gemini Flash plus forts n'ont qu'environ 20 requêtes par jour en gratuit. Les modèles gratuits valident la mécanique ; à l'étape 6, on compare les **meilleurs modèles gratuits**. Aucun budget payant pendant la construction (règle numéro 8) : la comparaison avec Claude Haiku et Claude Sonnet se fera à la fin du projet, quand la clé Claude sera achetée.
- **Offre gratuite = données utilisées par Google** (amélioration des produits, relecture humaine possible) : uniquement des données de test inventées pendant la construction, jamais de données réelles de clients.
- **Liste des fournisseurs gratuits** (détails, limites et conditions dans `docs/plan.md`), dans cet ordre : 1. Google AI Studio (Gemini), 2. OpenRouter (modèles « free »), 3. Mistral (offre « Experiment », entraînement désactivé dans sa console). Écartés pour l'agent : Groq et GitHub Models (environ 8 000 tokens par minute ou par requête, trop peu pour un agent), Cerebras (crédit unique de 5 $, « pas destiné à la production »). Un seul compte par fournisseur : ne jamais multiplier les comptes pour contourner les quotas.
- **Réglages** (variables d'environnement, jamais dans le code) : `KEKELI_FOURNISSEURS` donne l'ordre de la liste (par exemple `gemini,openrouter,mistral`), dans le site ; une entrée peut préciser le modèle (`gemini/gemini-3.1-flash-lite`), utile car les quotas gratuits de Google sont comptés par modèle ; sinon le modèle vient de `KEKELI_MODELE_GEMINI`, `KEKELI_MODELE_OPENROUTER`, `KEKELI_MODELE_MISTRAL` ; les limites affichées dans l'administration viennent de `KEKELI_LIMITE_JOUR_*`. Un fournisseur sans clé dans le Runner est ignoré. Noms des clés (dans le Runner seulement) : `GEMINI_API_KEY`, `OPENROUTER_API_KEY`, `MISTRAL_API_KEY`, et `E2B_API_KEY` pour les ordinateurs virtuels. Secret partagé entre le site et le Runner : `KEKELI_RUNNER_SECRET`.
- **Basculement** : sur une réponse « limite atteinte » (code 429 ou quota du jour épuisé), le fournisseur est mis de côté jusqu'à sa remise à zéro, et la même tâche reprend avec le suivant. Si tous sont épuisés (problème interne), la demande est **mise en attente et reprend automatiquement** ; l'utilisateur voit seulement « Kekeli est très sollicité » et **ne voit jamais qu'il s'agit d'offres gratuites** ; l'administrateur reçoit une alerte dans l'administration.
- **Comptage** : requêtes, refus pour limite atteinte et tokens enregistrés par fournisseur et par jour ; page d'administration avec l'usage du jour comparé à la limite et une alerte à partir de 80 %.
- **Lancement (étape 8)** : les offres gratuites servent seulement la formule gratuite de Kekeli ; les abonnés payants passent par des clés payantes. Choix d'un modèle économique et d'un modèle puissant payants au meilleur rapport qualité-prix (Gemini avec facturation Google Cloud, Claude, OpenAI ou autre). Jamais de Gemini gratuit pour des utilisateurs situés en Europe, en Suisse ou au Royaume-Uni.
- **Politique de confidentialité (étape 8)** : elle doit dire clairement que, **dans l'offre gratuite, les demandes peuvent être utilisées par les fournisseurs d'IA pour améliorer leurs services**, et nommer ces fournisseurs.
- **Les coûts par tâche sont des estimations. Ils seront mesurés à l'étape 5, avant de choisir les modèles et de fixer les prix.**

### Messages de limite (à construire à l'étape 5)
- Image de Kekeli : **« l'énergie qui se recharge »**. **Jamais les expressions de Claude Code.** L'heure affichée est **toujours l'heure de Lomé**.
- Formule gratuite, limite atteinte : « ☀️ Votre énergie du jour est épuisée. Kekeli se recharge et sera de nouveau prêt à [heure]. Vous ne voulez pas attendre ? Passez à une formule payante et continuez tout de suite. » + bouton « Voir les formules ».
- Formule payante, limite atteinte : « ☀️ Vous avez utilisé toute l'énergie de votre formule pour le moment. Elle se recharge à [heure]. Votre projet est sauvegardé : vous reprendrez exactement où vous vous êtes arrêté. Besoin de plus ? Passez à la formule supérieure. » + bouton « Voir les formules ».
- Tous les fournisseurs d'IA épuisés (problème interne) : « ☀️ Kekeli est très sollicité en ce moment. Votre demande est en attente et reprendra automatiquement vers [heure]. » La demande est mise en attente et l'administrateur reçoit une alerte.

### Formules et énergie (décidées le 9 novembre 2026)
- **Gratuite** : environ 1 petite page et 1 modification par jour (30 FCFA d'énergie), avec un **plafond total par jour pour l'ensemble des utilisateurs gratuits** (1 500 FCFA au départ, réglable) ; quand il est atteint, message « ☀️ Kekeli se recharge en ce moment… » avec l'heure de recharge, et alerte pour l'administrateur.
- **Payantes** : Découverte 2 500 FCFA (40 FCFA d'énergie par jour), Pro 7 500 FCFA (130), Entreprise 25 000 FCFA (450) par mois. **Publiées sur la page « Formules » avec l'accord du porteur du projet (9 novembre 2026).**
- **Pas de bonus de bienvenue** (décision du 9 novembre 2026).
- **Énergie épuisée en cours de demande** : le travail s'arrête aussitôt, le projet est sauvegardé tel quel, l'utilisateur voit le message d'arrêt avec l'heure de la recharge (heure de Lomé) et le bouton « Voir les formules » ; après la recharge ou le paiement d'une formule, le bouton « Continuer » reprend la demande là où elle s'était arrêtée.
- Recharge de toutes les formules **chaque jour à minuit, heure de Lomé**. Énergie comptée en coût réel (modèle et ordinateur), donc marge garantie quel que soit le modèle.
- Attente avant la pause de l'ordinateur : **10 minutes**.
- Tout se règle dans **Administration → Réglages** ; détails et marges dans `docs/couts.md`.

### Images, pays et icônes (décidés le 9 novembre 2026)
- **Ordre des images** : 1. les photos du client (bouton « Mes photos », depuis le téléphone) ; 2. sinon, une photo libre cherchée sur plusieurs sources (Wikimedia Commons, Openverse, Pexels si une clé gratuite est placée) et choisie par Kekeli ; 3. sinon, une image créée par un service **gratuit** (Cloudflare Workers AI, dès qu'une clé est placée) ; sinon une illustration en SVG ou en CSS. Jamais rien de payant.
- **Seulement des licences qui n'obligent pas à citer l'auteur** (CC0, domaine public, licence Pexels). Unsplash est écarté (ses règles d'utilisation par programme obligent à citer le photographe et à afficher l'image depuis ses serveurs) ; Pixabay aussi pour le moment (sa clé passe dans l'adresse et ne peut pas rester hors de l'ordinateur virtuel).
- **Kekeli regarde chaque photo** (modèle de vision Gemini gratuit, `KEKELI_MODELE_VISION`) : refus des filigranes, des noms d'auteur effacés ou floutés et des personnes réelles reconnaissables ; marques, enseignes, textes et visages apparus par hasard floutés ; préférence pour les photos conformes au pays du client.
- **Pays du client** : choisi à l'inscription (ou demandé au premier message), modifiable dans le profil ; l'agent l'utilise pour les images, la monnaie et le format des numéros de téléphone.
- **Vrais produits du client** : jamais d'image créée présentée comme une vraie photo ; l'agent propose au client d'envoyer ses photos. Jamais de logo ou de marque d'une autre entreprise, ni de personne réelle reconnaissable.
- **Icônes** : bibliothèque Lucide (licence ISC), jamais d'émojis dans les sites.
- **Chaque image créée est comptée dans l'énergie** (prix réglable dans Administration → Réglages, 0,001 $ par image au départ, estimation).
- **Création d'images** (validée le 9 novembre 2026) : Cloudflare Workers AI, offre gratuite, **compte Cloudflare réservé à Kekeli** (clés `CLOUDFLARE_API_TOKEN` et `CLOUDFLARE_ACCOUNT_ID`). **Nombre maximum d'images créées par jour pour tous les utilisateurs** réglable dans Administration → Réglages (150 au départ, pour rester sous la limite gratuite d'environ 170 images par jour, estimation) ; au-delà, l'agent utilise une photo libre ou une illustration.
- **Aucun texte dans les images créées** (décision du 9 novembre 2026) : jamais de lettres, de mots, de chiffres ni de marque ; Kekeli vérifie chaque image créée, la recrée deux fois au plus, puis floute ce qui resterait ; le texte se met dans la page, en HTML.
- **Clés de Gemini** : les limites gratuites de Google s'appliquent **par projet**, pas par clé (plusieurs clés d'un même projet partagent le même quota). Les conditions des API Google interdisent de contourner les limites et réservent une clé à la personne à qui elle a été donnée. **Recommandation de Claude (9 novembre 2026), à confirmer par le porteur du projet** : ne pas utiliser les clés ni les comptes d'autres personnes (même de la famille) pour multiplier les quotas gratuits ; rien n'est construit pour cela.

### Paiements
- **Au départ, pas d'agrégateur de paiement.** L'utilisateur paie par Mobile Money, envoie la **capture d'écran** de son paiement dans Kekeli, et **l'administrateur valide manuellement** l'abonnement.
- **Construit à l'étape 8** (choix du 9 novembre 2026) : le client choisit son pays, voit les numéros et le nom du titulaire, paie et envoie **seulement la capture d'écran** (aucune référence à taper). Validation dans Administration → Paiements ; formule active **30 jours** (sans perdre les jours restants en cas de renouvellement), rappel 3 jours avant la fin, puis retour à la formule gratuite. Pays et numéros (modifiables dans l'administration) : Togo (titulaire Ahiankonou Kokou Joseph : Mixx by Yas, Flooz), Bénin (KOUAGOU Noutcha Adam Fernando : MTN, Moov, Celtiis), Burkina Faso (Anderson Passoule Oyo : Orange Money, Wave), Côte d'Ivoire (OULOUSSEGUEA CHARLES DANHAN : Orange Money, MTN Mobile Money, Wave). Autres pays : bouton « Écrire sur WhatsApp » (+228 72 24 02 31).
- Au Togo, écrire **« Mixx by Yas »** et **« Flooz »**. Ne jamais écrire « T-Money ».
- Un agrégateur (CinetPay, PayDunya, FedaPay ou autre) sera ajouté plus tard.

### Pays, file d'attente et code du client (étape 8, 9 novembre 2026)
- **Site ouvert à tous les pays du monde** (liste en français dans `resources/data/pays-du-monde.php`). Pendant la bêta, Union européenne, Espace économique européen, Royaume-Uni et Suisse voient « Bientôt disponible dans votre pays » (pays choisi ou pays de connexion) ; ouverts avec la clé Claude, à la fin du projet.
- **10 demandes en même temps au plus** (réglable) ; les suivantes attendent leur tour dans l'ordre, avec leur position, sans rien perdre.
- **Code du client : récupérer son code est GRATUIT et très simple pour tout le monde** (décision du 9 novembre 2026, comme Lovable) : un bouton « Connecter GitHub », la personne accepte, et le code part tout seul dans un dépôt privé de son compte ; le fichier ZIP est gratuit aussi. **Seuls l'énergie et les fonctions avancées sont payants** (par exemple brancher son propre nom de domaine). Une fois le code chez lui, le client l'héberge où il veut, à ses frais. **GitLab et Bitbucket plus tard, avec la même simplicité.**
- **Conditions d'utilisation et politique de confidentialité** : pages `/conditions-d-utilisation` et `/confidentialite`, acceptées à l'inscription. À faire relire par un juriste avant le lancement public.

### Publication des sites des clients
- **Au départ, adresses gratuites de Cloudflare (`pages.dev`).** Pas de deuxième nom de domaine.
- Les sites des clients ne sont jamais publiés sur kekeli.dev.
- **Publication (étape 7)** : un projet Cloudflare Pages par site client, nommé d'après le projet (par exemple `restaurant-le-palmier-qzskk7.pages.dev`) ; on publie la dernière sauvegarde du projet, sans rallumer d'ordinateur. La clé Cloudflare doit avoir les droits « Workers AI » et « Cloudflare Pages : modifier ». **Limite de l'offre gratuite : 100 projets par compte**, à revoir avant la bêta.

### Hébergement
- **Le site n'est pas mis en ligne sur Laravel Cloud tout de suite.** Le porteur du projet le fera plus tard ; en attendant, chaque étape est montrée par des captures d'écran (règle numéro 9).
- **Compte Laravel Cloud du porteur du projet** : déjà payant, avec Talkami et Lomé IA School en production. L'abonnement est payé par organisation (pas de deuxième abonnement pour une application de plus) ; le crédit de 5 $ est déjà utilisé par les deux autres sites. **Ne jamais activer « Stop all compute » dans cette organisation** : la limite de dépense s'applique à toute l'organisation et couperait les autres sites. Solutions et coûts : `docs/mise-en-ligne.md`.
- **Choix du 9 novembre 2026** : Laravel Cloud (compte existant du porteur du projet), adresse gratuite en `laravel.cloud`, deux applications (site et Runner). Essayer d'abord la version **qui s'endort** avec un vrai essai ; si ce n'est pas fiable, passer le Runner en version toujours allumée et le dire. Guide et coûts : `docs/mise-en-ligne.md`. **Rien de payant n'est créé avant la réponse du porteur du projet.**
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
| 2 | Runner + agent OpenCode dans un ordinateur E2B, cerveau Gemini gratuit, liste de fournisseurs gratuits avec basculement automatique, comptage par fournisseur | Terminée, validée le 8 novembre 2026 (captures : `docs/captures/etape-2.md`) |
| 3 | Aperçu en direct : onglets « Discussion » et « Aperçu », serveur d'aperçu dans l'ordinateur virtuel avec rechargement automatique | Terminée, validée le 8 novembre 2026 (captures : `docs/captures/etape-3.md`) |
| 4 | Sécurité : permissions, sites autorisés, effacement, sauvegarde et reprise (pause au lieu d'effacement), aperçu privé, licences vérifiées | Terminée, validée le 8 novembre 2026 (captures : `docs/captures/etape-4.md`) |
| 5 | Consommation, quotas, mesure réelle des coûts, comparaison des modèles, messages de limite « énergie qui se recharge », administration, effacement des ordinateurs en pause après 7 jours | Terminée, validée le 9 novembre 2026 avec les choix de formules (coûts : `docs/couts.md` ; captures : `docs/captures/etape-5.md`) |
| 6 | Qualité « site professionnel » : photos du client d'abord, photos libres choisies et floutées par Kekeli, création d'images gratuite, pays du client, icônes Lucide, comparaison des meilleurs modèles gratuits | Terminée, validée le 9 novembre 2026 ; ajouts : création d'images Cloudflare avec maximum par jour, arrêt et reprise quand l'énergie est épuisée (captures : `docs/captures/etape-6.md` et `docs/captures/etape-6-ajouts.md`) |
| 7 | Publication en un clic sur Cloudflare (`pages.dev`) : bouton « Publier mon site », « Publier à nouveau », publication de la dernière sauvegarde par le Runner | Terminée, validée le 9 novembre 2026 ; corrections du modèle de mise en page (captures : `docs/captures/etape-7.md` et `docs/captures/etape-7-corrections.md`) |
| 8 | Paiement Mobile Money par capture d'écran validée par l'administrateur, prix publiés, tous les pays et pays bientôt disponibles, file d'attente, code du client (GitHub et ZIP gratuits pour tous, nom de domaine payant), conditions d'utilisation et politique de confidentialité, mise en ligne sur Laravel Cloud, version bêta | En cours : tout est construit et testé (captures : `docs/captures/etape-8.md`) ; mise en ligne en attente de l'accord du porteur du projet sur les coûts |
| Plus tard | Envoi vers GitLab et Bitbucket (même simplicité que GitHub) ; interface en anglais ; clé Claude (fin du projet) et ouverture des pays européens ; modèles payants pour les abonnés ; achat de kekeli.dev | À faire |

## Repères techniques

- Pages de l'application : composants Volt dans `resources/views/livewire/` (projets dans `projects/`).
- Modèles : `app/Models/` (`User` avec `is_admin`, `Project` avec identifiant ULID, `Message`, `AgentRun` pour une demande à l'agent, `AgentStep` pour une action affichée en direct, `ProviderUsage` pour le comptage par fournisseur, modèle et jour du quota, `AdminAlert`).
- Agent côté site : `app/Agent/` (liste des fournisseurs, signature, client du Runner, traitement des événements), travail d'envoi `app/Jobs/SendAgentRun.php`, réception `POST /runner/evenements` (signée).
- Runner : dossier `runner/` (Node.js 22, tests avec `npm test`). Voir `runner/README.md`.
- Aperçu en direct : serveur `runner/src/apercu/serveur.mjs` copié dans chaque ordinateur virtuel (port 4100) ; lien reçu par l'événement `apercu` et rangé dans `projects.preview_url` et `projects.preview_expires_at` ; domaines acceptés dans `KEKELI_DOMAINES_APERCU`.
- Sécurité (étape 4) : jeton d'aperçu `app/Agent/PreviewToken.php` (même calcul que `runner/src/apercu/serveur.mjs`), sauvegardes `app/Agent/ProjectBackup.php` (routes signées `runner/projets/{projet}/sauvegarde`), permissions `AgentPermission`, sites autorisés `ProjectSite` (liste par défaut dans `config/kekeli.php`, identique à `runner/src/reseau.js`), effacement `app/Jobs/EraseProject.php`, rallumage `app/Jobs/WakePreview.php`. Côté Runner : `src/reseau.js`, `src/sauvegarde.js`, `src/outils.js` (ripgrep vérifié), `src/licences/verifier.mjs`.
- Licences : tout ce que l'agent ajoute doit venir d'une source libre et être noté dans `LICENCES.md` du projet ; vérifié automatiquement après chaque demande.
- Consommation et énergie (étape 5) : réglages modifiables `app/Support/Settings.php` (table `settings`, valeurs par défaut dans `config/kekeli.php`, clé `settings`), coûts `app/Agent/Costs.php`, énergie `app/Agent/Energy.php` (formule de l'utilisateur dans `users.plan`), compteurs de chaque demande dans `agent_runs`, page `/formules`, effacement nocturne `php artisan kekeli:effacer-ordinateurs-en-pause` (planifié à 3 h 17). Mesures et proposition de formules : `docs/couts.md`. **Les formules et les prix ne sont jamais publiés sans l'accord du porteur du projet.**
- Images (étape 6) : outil `runner/src/outils/images.mjs` (photos du client dans `images/client`, recherche multi-sources avec `photos.mjs`, regard de Gemini, floutage ImageMagick, création Cloudflare), outil d'icônes `runner/src/outils/icones.mjs` (Lucide dans `runner/src/modeles/icones/`), copiés dans chaque ordinateur sous `/home/user/.kekeli/` avec `reglages.json` (pays, services) ; journal des outils `journal.jsonl` lu par le Runner (requêtes de vision, images créées comptées dans l'énergie). Côté site : `ProjectPhoto` (photos du client, routes `projects.photos.show` et `runner.photos.show` signée), `users.country`, liste des pays dans `config/kekeli.php`. Services d'images dans `runner/src/fournisseurs.js` (`SERVICES`, clés `PEXELS_API_KEY`, `CLOUDFLARE_API_TOKEN` et `CLOUDFLARE_ACCOUNT_ID`, jamais dans l'ordinateur). Essais : `scripts/essai-etape-6.js` et `scripts/essai-photos-client.js`.
- Énergie épuisée en cours de demande : `Energy::stopReason`, réponse `arreter` du site à chaque événement du Runner (`RunnerEventHandler::stopRequest`), événement `arretee`, état `stopped` d'une demande, bouton « Continuer » (`continueRun`, reprise avec `SendAgentRun` en mode reprise). Nombre d'images du jour : `app/Agent/ImageQuota.php` (envoyé au Runner dans `images_restantes`).
- Publication (étape 7) : côté Runner `runner/src/publication.js` (envoi « Direct Upload » à Cloudflare Pages, empreintes avec `blake3-wasm`) et adresse signée `POST /publier` ; côté site `app/Jobs/PublishProject.php`, `RunnerClient::publish`, `Project::pagesName()`, colonnes `projects.pages_name`, `publish_status`, `published_url`, `published_at`, `publish_error`, bouton dans `projects/show.blade.php`. Essais : `scripts/essai-etape-7.js` et `scripts/essai-republication.js`.
- Typographie française : `runner/src/qualite/typographie.mjs`, lancé dans l'ordinateur après chaque demande, avant les vérifications (espaces insécables avant ? ! : ; , dans les guillemets et les prix).
- Qualité (étape 6) : modèle de mise en page `runner/src/modeles/base.css`, vérification des pages `runner/src/qualite/verifier.mjs` et des images `runner/src/qualite/images.js` après chaque demande (l'agent corrige une fois tout seul), relance d'un tour terminé sans message. L'agent ne doit jamais inventer une adresse d'image. Essai réel : `scripts/essai-etape-6.js`.
- Étape 8 : paiements `app/Billing/Subscriptions.php`, modèle `Payment`, pages Volt `payments/create` et `admin/payments`, commande `kekeli:expirer-formules` (chaque jour à 0 h 07, heure de Lomé, pour laisser dormir le site et la base), rappel `components/plan-reminder.blade.php` ; pays `app/Support/Availability.php` (`coming_soon_countries`) ; file d'attente `app/Agent/AgentPlaces.php` (colonne `agent_runs.sent_at`, réglage `max_concurrent_runs`) ; code du client `app/Agent/ProjectFiles.php` (lecture des sauvegardes, ZIP), `app/Github/GithubClient.php`, `app/Jobs/ExportToGithub.php`, `GithubController`, `ProjectCodeController`, Runner `src/domaines.js` (adresse `POST /domaine`) ; pages juridiques `resources/views/legal/` ; Runner éveillé pendant le travail (`garderEveille`, `KEKELI_RUNNER_ADRESSE_PUBLIQUE`). Captures : `scripts/preparer-captures-etape-8.php` et `scripts/captures-etape-8.js`.
- Administration : pages `/administration/fournisseurs`, `/administration/projets`, `/administration/consommation`, `/administration/paiements` et `/administration/reglages`, réservées aux comptes administrateurs (`php artisan kekeli:admin adresse`) ; l'administrateur peut consulter un projet en lecture seule.
- Un utilisateur ne voit et ne modifie que ses propres projets : toujours passer par `Auth::user()->projects()` ou vérifier le propriétaire (réponse 404 sinon).
- Commandes utiles :
  - `php artisan test` : tous les tests (SQLite en mémoire par défaut).
  - Tests sur PostgreSQL : définir `DB_CONNECTION=pgsql` et les variables `DB_*`, puis `php artisan test` (c'est ce que fait l'intégration continue GitHub).
  - `vendor/bin/pint` : remettre le code au bon style ; `vendor/bin/pint --test` : vérifier sans modifier.
  - `npm run build` : construire l'interface.
  - `cd runner && npm test` : tests du Runner.
  - Pour faire tourner l'agent en local : `php artisan serve --port=8123`, `php artisan queue:work` et `cd runner && npm start`, avec le même `KEKELI_RUNNER_SECRET` des deux côtés.
