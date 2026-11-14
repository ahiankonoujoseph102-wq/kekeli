# Mise en ligne sur Laravel Cloud (étape 8)

**Pour mettre Kekeli en ligne, suivre la partie 0 ci-dessous, geste par geste.** Les parties suivantes gardent l'étude des coûts et les essais plus longs. **Rien de payant ne sera créé sans l'accord écrit du porteur du projet** (règle numéro 8). Prix et règles relevés le 9 novembre 2026 sur les pages officielles de Laravel Cloud ([prix](https://laravel.com/cloud/docs/pricing), [organisations](https://laravel.com/cloud/docs/organizations), [limite de dépense](https://laravel.com/cloud/docs/spending-limits), [usage](https://laravel.com/cloud/docs/usage), [ordinateurs](https://laravel.com/cloud/docs/compute)). **Ce sont des estimations** tant qu'une vraie facture n'a pas été vue.

## 0. Mise en ligne, geste par geste

Écrite le 14 novembre 2026 pour la mise en ligne de Kekeli à l'adresse **https://kekeli.laravel.cloud**, depuis un téléphone. Claude n'a pas accès à Laravel Cloud : c'est le porteur du projet qui fait chaque geste. **Une étape à la fois** : après chaque étape, envoyez une capture à Claude, il donne la suivante. Les noms exacts des boutons de Laravel Cloud peuvent changer un peu : si un bouton ne correspond pas, envoyez une capture.

### 0.1 Règles strictes, à relire avant chaque geste

1. **Ne jamais toucher aux applications Talkami et Lomé IA School** : ne pas les ouvrir pour les modifier, ne changer ni leurs réglages, ni leurs variables, ni leurs bases de données, ni leurs déploiements.
2. **Ne jamais utiliser « Stop all compute »** (ni aucun réglage qui met en pause toute l'organisation) : il arrêterait Talkami et Lomé IA School. La limite de dépense reste en **alertes par courriel seulement**.
3. **Ne jamais supprimer la base `lome_ia_school`**, ni aucune base qui ne s'appelle pas exactement `kekeli`. En cas de doute sur une base, ne rien toucher et envoyer une capture à Claude.
4. **Aucune clé dans la discussion avec Claude**, ni dans une capture : cacher chaque valeur avant d'envoyer une capture. Les clés se collent seulement dans les variables de Laravel Cloud.
5. Rien de payant en plus sans accord écrit (règle numéro 8) : Kekeli utilise le plus petit ordinateur, une seule copie, et s'endort sans visite (coût estimé : environ 3 à 5 $ par mois au début, partie 2 ; ce sont des estimations).

### 0.2 Ce qui va tourner, et pourquoi c'est sûr

- **Application « kekeli »** (le site, Laravel) : les pages, les comptes, la base PostgreSQL, la file d'attente, les tâches planifiées et le stockage des fichiers.
- **Application « kekeli-runner »** (le Runner, Node.js 22), dans la même organisation : Laravel Cloud accepte les applications Node.js d'un dossier d'un dépôt (dossier `runner`). C'est **le seul endroit qui détient les clés des fournisseurs** (E2B, Gemini, OpenAI, Coarena, Cloudflare, recherche). Décision du 9 novembre 2026 : site et Runner sur Laravel Cloud. **Solution gratuite possible, mais moins fiable** : un hébergeur gratuit qui s'endort lui aussi (par exemple Render, offre gratuite, conditions à vérifier sur leur site) ; démarrage plus lent après le sommeil (environ une minute) et un compte de plus à gérer. Conseil de Claude : rester sur Laravel Cloud, le Runner endormi ne coûte presque rien (environ 0,30 à 1,50 $ par mois, estimation).
- **Comment le site parle au Runner, en sécurité** :
  - toujours en **HTTPS** (adresses en `https://…laravel.cloud`) ;
  - chaque demande, dans les deux sens, porte une **signature** calculée avec le secret `KEKELI_RUNNER_SECRET` (au moins 32 caractères, **le même** dans les deux applications) et l'heure : une demande sans la bonne signature, ou vieille de plus de 5 minutes, est refusée ;
  - la seule adresse du Runner ouverte sans signature est `/sante` (elle répond seulement « ok ») ;
  - le Runner ne garde aucun compte de client et n'écrit rien de durable : les projets vivent dans les ordinateurs E2B, les sauvegardes dans le stockage du site.

### 0.3 Créer l'application « kekeli » (le site)

Si l'application « kekeli » existe déjà dans l'organisation, ouvrez-la et vérifiez chaque point au lieu de la recréer.

1. Sur **cloud.laravel.com**, connectez-vous, choisissez **l'organisation qui contient Talkami et Lomé IA School** (ne rien ouvrir d'autre), puis **« New application »**.
2. Dépôt : `kekeli` (GitHub) ; branche : `main` ; nom : **kekeli** ; région : la même que Talkami (plus proche, et aucun coût de plus).
3. Dossier de départ (« root directory ») : laisser **la racine** du dépôt.
4. **Base de données** : sur le schéma de l'environnement, **« Add database »** → **PostgreSQL serverless**, nom **`kekeli`**, la plus petite taille (0,25 unité), mise en sommeil activée. Laravel Cloud ajoute tout seul les variables `DB_…`. Ne jamais choisir une base existante.
5. **Stockage des fichiers** : **« Add bucket »**, nom **`kekeli-fichiers`**, **privé**, nom de disque **`s3`**, et cochez **« disque par défaut »** (« default disk »). Laravel Cloud ajoute tout seul `FILESYSTEM_DISK` et les variables `AWS_…`. Sans lui, les photos, les sauvegardes, les documents et les reçus seraient effacés à chaque déploiement.
6. **Ordinateur** (« App compute ») : **Flex**, le plus petit (512 Mo), **1 copie**, mise en sommeil (« hibernation ») activée.
7. **File d'attente** : dans le même ordinateur, section **« Background processes »** → **« Add process »** → **Queue worker** (ou « Custom ») avec la commande :
   `php artisan queue:work --tries=3 --timeout=600 --sleep=3 --max-time=3600`
   et **1** processus.
8. **Tâches planifiées** : activez **« Scheduler »**. Quand le site dort, Laravel Cloud met les tâches en pause : c'est prévu, elles passent à chaque heure où le site est éveillé.
9. **Commandes de construction** (« Build commands ») :
   `composer install --no-dev --optimize-autoloader`
   `npm ci --audit false`
   `npm run build`
10. **Commandes de déploiement** (« Deploy commands ») :
    `php artisan migrate --force`
    `php artisan optimize`
11. **Domaine** : gardez l'adresse gratuite **kekeli.laravel.cloud** (sans nom de domaine acheté). HTTPS est donné par Laravel Cloud ; Kekeli force lui-même le HTTPS en production.

### 0.4 Créer l'application « kekeli-runner » (le Runner)

1. **« New application »**, même dépôt `kekeli`, branche `main`, nom **kekeli-runner**, même région.
2. Dossier de départ (« root directory ») : **`runner`**. Environnement d'exécution (« Runtime ») : **Node.js 22**.
3. Commande de construction : `npm ci --omit=dev --audit false`.
4. Commande de démarrage (« Start command ») : `node src/server.js`. Le Runner lit tout seul la variable `PORT` donnée par Laravel Cloud.
5. Ordinateur : le plus petit, **1 copie**, mise en sommeil activée. **Aucune base de données, aucun stockage** pour le Runner.
6. Notez son adresse (par exemple `https://kekeli-runner….laravel.cloud`, celle que Laravel Cloud affiche) : elle sert dans les deux applications.

### 0.5 Les variables, nom par nom (jamais les valeurs dans la discussion)

Dans chaque application : **Settings** → **Environment variables**. « Automatique » = ajoutée par Laravel Cloud, ne pas la toucher.

**Application « kekeli » (le site)**

| Nom | Valeur ou origine | Change en production ? |
|---|---|---|
| `APP_NAME` | `Kekeli` | non |
| `APP_ENV` | `production` | **oui** (en local : `local`) |
| `APP_DEBUG` | `false` | **oui** (en local : `true`) |
| `APP_KEY` | automatique à la création ; sinon, dans « Commands » : `php artisan key:generate --show`, puis la coller | **oui** (une clé propre à la production) |
| `APP_URL` | `https://kekeli.laravel.cloud` | **oui** |
| `APP_LOCALE` | `fr` | non |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | automatiques (base `kekeli`) | **oui** (PostgreSQL au lieu de SQLite) |
| `FILESYSTEM_DISK` et les variables du stockage (`AWS_…`) | automatiques (bucket `kekeli-fichiers`, disque par défaut) ; Kekeli lit aussi bien `AWS_ENDPOINT` que `AWS_ENDPOINT_URL` | **oui** |
| `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER` | `database` (les trois) | non |
| `MAIL_MAILER` | `smtp` | **oui** (les tests restent en envoi simulé) |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` | Brevo → « SMTP et API » → onglet SMTP (les mêmes que dans l'environnement de Claude) | non |
| `MAIL_FROM_ADDRESS` | l'expéditeur validé dans Brevo (« Expéditeurs et domaines ») | non |
| `MAIL_FROM_NAME` | `Kekeli` | non |
| `ADMIN_ALERT_EMAIL` | facultatif : votre adresse pour « Un paiement attend votre vérification » | non |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | console Google Cloud, projet Kekeli → « Clients » → client « Kekeli » (partie 9) | non |
| `GOOGLE_REDIRECT_URI` | `https://kekeli.laravel.cloud/auth/google/retour` | **oui** (en local : `http://127.0.0.1:8123/auth/google/retour`) |
| `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | page de la GitHub App « Kekeli-Lome » (github.com → Settings → Developer settings → GitHub Apps), `docs/github-app.md` | non |
| `KEKELI_RUNNER_URL` | l'adresse de « kekeli-runner », en `https://` | **oui** (en local : `http://127.0.0.1:3100`) |
| `KEKELI_RUNNER_SECRET` | un secret neuf d'au moins 32 caractères (voir 0.6), **le même** que dans « kekeli-runner » | **oui** (jamais celui des essais) |
| `KEKELI_FOURNISSEURS` | `gemini,gemini/gemini-3.1-flash-lite,openrouter,workersai` (comme `.env.example`) | non |
| `KEKELI_DOMAINES_APERCU` | `e2b.app,e2b.dev` | non |
| `LARAVEL_CLOUD_API_TOKEN` | Laravel Cloud → Organisation → Settings → API tokens, **seulement** la permission de lire l'usage | **oui** (seulement en production) |

**Application « kekeli-runner » (le Runner)** : c'est ici que vont les clés des fournisseurs, **jamais dans le site**.

| Nom | Valeur ou origine |
|---|---|
| `NODE_ENV` | `production` (le Runner écoute alors sur toutes les interfaces, derrière le proxy de Laravel Cloud) |
| `KEKELI_RUNNER_SECRET` | **le même** que dans « kekeli » |
| `KEKELI_RUNNER_ADRESSE_PUBLIQUE` | l'adresse de « kekeli-runner », en `https://` (il s'appelle lui-même pour ne pas s'endormir en plein travail) |
| `KEKELI_RUNNER_EVEIL_MAX_MINUTES` | `60` |
| `E2B_API_KEY` | e2b.dev → Dashboard → API Keys |
| `GEMINI_API_KEY` | Google AI Studio → « Get API key » |
| `OPENROUTER_API_KEY` | openrouter.ai → Keys |
| `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` | Cloudflare, compte réservé à Kekeli → « API Tokens » (droits « Workers AI » et « Cloudflare Pages : modifier ») et l'identifiant du compte |
| `OPENAI_API_KEY` | platform.openai.com → API keys (plafond de 1 $ par jour réglé dans Kekeli) |
| `COARENA_BASE_URL`, `COARENA_API_KEY`, `COARENA_API_KEY_2` à `COARENA_API_KEY_11` | vos comptes Coarena (`docs/coarena.md`) |
| `TAVILY_API_KEY`, `EXA_API_KEY` | tavily.com et exa.ai (offres gratuites) |
| `PIXABAY_API_KEY` | pixabay.com → API (facultative) |
| `PEXELS_API_KEY` | pexels.com → API (facultative) |

Les valeurs sont les mêmes que celles déjà placées dans l'environnement de Claude, **sauf** `KEKELI_RUNNER_SECRET` (neuf) et les adresses.

### 0.6 Créer le secret commun du site et du Runner

1. Dans l'application **kekeli** → **Commands**, lancez : `php artisan kekeli:nouveau-secret`
2. Copiez la première ligne affichée (64 caractères) et collez-la dans `KEKELI_RUNNER_SECRET` **des deux applications**. Ne l'envoyez jamais à Claude, ni en capture.

### 0.7 Premier déploiement

1. Déployez **kekeli-runner** d'abord (**« Deploy »**). Quand il est vert, ouvrez `https://<adresse du Runner>/sante` sur le téléphone : la page doit afficher `{"ok":true}`.
2. Déployez **kekeli**. Quand il est vert, ouvrez **https://kekeli.laravel.cloud** : l'accueil « Que voulez-vous créer aujourd'hui ? » doit s'afficher, avec le cadenas du HTTPS.
3. Dans **kekeli** → **Commands**, lancez : `php artisan kekeli:verifier-production`. Chaque ligne doit commencer par `[bon]` ; une ligne `[à corriger]` dit en français quoi changer (aucune valeur secrète n'est affichée). Les lignes `[conseil]` peuvent attendre.
4. Envoyez une capture de ce résultat à Claude.

### 0.8 Créer votre compte administrateur

1. Sur **https://kekeli.laravel.cloud**, touchez **« Créer un compte »**, entrez votre adresse e-mail, puis le code reçu par e-mail (l'essai des e-mails se fait en même temps) ; choisissez votre nom et le pays.
2. Dans **kekeli** → **Commands**, lancez : `php artisan kekeli:admin <votre adresse e-mail>` (remplacez par l'adresse du compte créé ; ne l'écrivez jamais dans le dépôt).
3. Rechargez Kekeli : le menu du compte montre **« Administration »**.
4. Dans **Administration → Paiements** : les pays, les numéros Mobile Money (« Mixx by Yas », « Flooz »), les titulaires et le numéro WhatsApp. **Ils ne sont jamais écrits dans le dépôt.**
5. Dans **Administration → Réglages** : relire le plafond OpenAI du jour (1 $), le plafond des e-mails, l'émetteur des reçus et les formules.

### 0.9 Les essais à faire juste après

Chaque essai : le geste, puis ce que vous devez voir. Envoyez une capture en cas de doute (clés cachées).

1. **Connexion par code e-mail** : déconnectez-vous, « Se connecter », votre adresse, puis le code reçu : vous arrivez sur « Nouvelle discussion ». Vérifiez aussi le lien « Se connecter » de l'e-mail.
2. **« Continuer avec Google »** : sur la page de connexion, le bouton Google doit être là ; touchez-le, choisissez votre compte Google : vous êtes connecté (même compte si l'adresse est la même). Avant, dans la console Google Cloud, client « Kekeli » : l'origine `https://kekeli.laravel.cloud` et l'adresse de retour `https://kekeli.laravel.cloud/auth/google/retour` doivent être présentes (partie 9).
3. **Création d'un site** : sur l'accueil, touchez « Site web », complétez par exemple « … pour un restaurant inventé à Lomé, avec le menu et un bouton WhatsApp », envoyez. Les étapes défilent, puis l'aperçu s'affiche (onglet « Aperçu ») ; touchez « Mettre en ligne » : une adresse en `pages.dev` s'ouvre.
4. **Paiement de test** : avec un **deuxième compte d'essai** (autre adresse), « Formules » → « Passer à Pro » → votre pays → envoyez une capture d'écran inventée (aucun vrai paiement). Avec votre compte administrateur : **Administration → Paiements** → « Valider ». Le compte d'essai passe à **Pro**.
5. **Reçu par e-mail** : quelques minutes après la validation, l'adresse du compte d'essai reçoit l'e-mail avec le reçu PDF (KEK-2026-…) ; il apparaît aussi dans Réglages → Formule → « Mes reçus ». Ensuite, dans Administration → Paiements, annulez ce paiement d'essai (un reçu d'annulation est créé ; rien n'est effacé).
6. **Installation sur le téléphone** : dans Chrome, sur https://kekeli.laravel.cloud, menu ⋮ → **« Ajouter à l'écran d'accueil »** (ou « Installer l'application ») ; l'icône K à la flamme apparaît ; Kekeli s'ouvre sans barre d'adresse. Mode avion : la page « hors connexion » s'affiche.
7. **GitHub App** : d'abord, sur github.com → Settings → Developer settings → GitHub Apps → **Kekeli-Lome** : remplacez l'adresse de retour (« Callback URL ») par `https://kekeli.laravel.cloud/github/retour`, la page d'accueil par `https://kekeli.laravel.cloud`, et l'installation par **« Any account »** (partie 6 et `docs/github-app.md`). Puis dans Kekeli, dans un projet : « Relier à GitHub » → installer l'application, choisir un dépôt d'essai → « Créer un dépôt privé » : le dépôt apparaît sur GitHub avec le code du projet.
8. **Coût** : le lendemain, Laravel Cloud → Usage → application « kekeli » : le coût du jour doit rester de quelques centimes (estimation). Vérifiez aussi que **Talkami et Lomé IA School fonctionnent comme avant**.

Après ces essais : envoyez à Claude « essais faits », avec les captures des points qui posent problème. Les essais plus longs (connecteurs, automatisations par événement) sont décrits dans les parties 7 et 8.

## 1. Ce que dit la documentation officielle (vérifié le 9 novembre 2026)

- **L'abonnement est payé par organisation, pas par application** : « Every Laravel Cloud organization must have an active subscription » ; le nombre d'applications est « Unlimited » pour les formules Starter, Growth et Business. **Ajouter Kekeli dans l'organisation qui contient déjà Talkami et Lomé IA School ne demande pas un deuxième abonnement.**
- **Le crédit de 5 $ par mois appartient à toute l'organisation.** Il est déjà utilisé par Talkami et Lomé IA School : tout ce que Kekeli consomme s'ajoute donc à la facture.
- **La limite de dépense s'applique à toute l'organisation** (Organisation → Réglages → Facturation). Par défaut, elle **envoie seulement des alertes** (50 %, 80 %, 90 %) et ne coupe rien. L'option « Stop all compute » **met en pause toutes les applications et toutes les bases de données de l'organisation** : elle couperait Talkami et Lomé IA School. **Ne jamais activer cette option dans l'organisation actuelle.**
- La page « Usage » montre le coût de chaque application (choix de l'application et de l'environnement) et de chaque base de données : on peut y suivre Kekeli séparément.

## 2. Coût réel de Kekeli, en plus de la facture actuelle

Prix officiels (région des États-Unis, Ohio ; ils peuvent varier selon la région) : petit ordinateur « Flex 512 Mo » 0,00893 $ par heure allumée ; base PostgreSQL serverless 0,25 unité environ 0,027 $ par heure active, plus 0,50 $ par Go et par mois ; espace de fichiers 0,02 $ par Go et par mois. Conversion : 1 $ = 590 FCFA. **Ce sont des estimations.**

| Élément | Début de la bêta (peu d'utilisateurs) | Bêta active | Si l'élément reste allumé tout le temps |
|---|---|---|---|
| Site Kekeli (s'endort sans visite) | 0,50 à 1 $ | 1,50 à 2,50 $ | 6 $ |
| Runner (s'endort sans demande) | 0,30 à 0,50 $ | 0,50 à 1,50 $ | 6 $ |
| Base de données (s'endort sans requête) | 1,50 à 3 $ | 5 à 8 $ | 18 $ |
| Espace de fichiers, stockage de la base | 0,50 $ | 0,60 $ | 0,60 $ |
| **Total par mois** | **environ 3 à 5 $ (1 800 à 3 000 FCFA)** | **environ 8 à 13 $ (4 700 à 7 700 FCFA)** | 30 $ (17 700 FCFA) au plus |

Si la version qui s'endort n'est pas fiable pour le Runner, il faut ajouter environ **5 $ par mois (3 000 FCFA)** pour le garder allumé.

Pour que tout puisse dormir la nuit, la tâche qui termine les formules payées passe d'une fois par heure à une fois par jour (à 0 h 07, heure de Lomé) : sinon elle réveillerait le site et la base de données chaque heure.

## 3. Surveiller le coût de Kekeli sans jamais couper Talkami ni Lomé IA School

**Solution A — Kekeli dans l'organisation actuelle (aucun abonnement en plus)**
- Limite de dépense en mode **alertes seulement** (le réglage par défaut, « Stop all compute » désactivé) : montant = facture habituelle + 10 $. Les alertes arrivent par courriel à 50 %, 80 % et 90 %. Rien n'est jamais coupé.
- Réglages qui plafonnent Kekeli lui-même : plus petit ordinateur (Flex 512 Mo), une seule copie (pas de mise à l'échelle), « Scale to zero » activé, base de données plafonnée à 0,25 unité avec mise en sommeil.
- Chaque semaine : page « Usage », application « kekeli », pour voir son coût exact.

**Solution B — une organisation séparée « Kekeli » (vrai plafond, sans risque pour les autres sites)**
- Elle demande son propre abonnement Starter de 5 $ par mois, **mais celui-ci contient 5 $ de crédit d'utilisation**. Dès que Kekeli consomme au moins 5 $ par mois (la bêta active), **le coût total est le même** qu'avec la solution A.
- On peut alors y activer « Stop all compute » avec une limite de 15 $ : seul Kekeli s'arrête si la limite est atteinte, jamais Talkami ni Lomé IA School. La facture de Kekeli est aussi séparée.
- Inconvénient : au tout début, si Kekeli consomme moins de 5 $, on paie 5 $ quand même (au lieu de 3 à 5 $).

**Choix du porteur du projet (10 novembre 2026) : solution A**, avec ces règles obligatoires :
1. **Ne jamais activer « Stop all compute »** ni aucun réglage qui pourrait arrêter Talkami ou Lomé IA School. **Ne rien modifier dans ces deux applications.**
2. Limite de dépense en **alertes par courriel seulement**, réglée à la facture habituelle plus 10 $.
3. Kekeli réglé au plus petit (Flex 512 Mo, une seule copie, base de données 0,25 unité), et qui s'endort quand personne ne l'utilise.
4. Protections dans Kekeli :
   - le Runner ne se garde jamais éveillé plus de 60 minutes d'affilée (variable `KEKELI_RUNNER_EVEIL_MAX_MINUTES`) : s'il reste allumé sans travail, l'hébergement l'endort tout seul ;
   - chaque nuit, Kekeli lit son propre coût sur Laravel Cloud (interface officielle « usage », clé `LARAVEL_CLOUD_API_TOKEN` avec la seule permission de lire l'usage) et alerte l'administrateur à 80 % puis à 100 % d'un seuil réglable dans **Administration → Réglages** (10 $ par défaut). Seules les applications `kekeli` et `kekeli-runner` et la base `kekeli` sont comptées. Cette lecture ne change jamais rien sur Laravel Cloud.

Rappels : les ordinateurs E2B restent sur leur crédit gratuit unique de 100 $ ; Gemini et Cloudflare restent gratuits ; kekeli.dev n'est pas acheté.

## 4. Version qui s'endort : comment Kekeli reste éveillé quand il le faut

- **Le site** se réveille à chaque visite (moins d'une demi-seconde). Pendant qu'une demande travaille, le Runner envoie sans arrêt les étapes de l'agent au site : il reste donc éveillé. Quand le site dort, Laravel Cloud met en pause la file d'attente et les tâches planifiées (documentation officielle) : les tâches de Kekeli passent donc à chaque heure où le site est éveillé (depuis le 14 novembre 2026), au lieu d'une heure fixe qui serait sautée pendant le sommeil.
- **Le Runner** ne reçoit rien de l'extérieur pendant qu'il travaille. Pour ne pas s'endormir en plein travail, il appelle sa propre adresse publique chaque minute tant qu'une demande est en cours (variable `KEKELI_RUNNER_ADRESSE_PUBLIQUE`), puis il se laisse endormir.
- **Les ordinateurs E2B** ont leur propre minuterie chez E2B : un Runner endormi ne laisse jamais un ordinateur allumé.
- **À vérifier pendant l'essai réel** : qu'une demande complète va jusqu'au bout sans coupure, que la publication fonctionne, et que le réveil après une nuit fonctionne. Si ce n'est pas fiable, on passe le Runner en version toujours allumée (environ 6 $ par mois de plus, estimation) et on prévient le porteur du projet.

## 5. Réglages des deux applications

Remplacée le 14 novembre 2026 par la partie 0 (0.3 pour le site, 0.4 pour le Runner, 0.5 pour toutes les variables). Différences avec l'ancienne version de cette partie : la file d'attente utilise `--timeout=600` (le travail le plus long, l'envoi d'un dépôt GitHub, dure jusqu'à 600 secondes ; `retry_after` est réglé à 660), le Runner se déploie depuis le dossier `runner` avec `NODE_ENV=production` et lit `PORT`, et le stockage objet est le disque par défaut (`FILESYSTEM_DISK`), sans avoir besoin de `KEKELI_DISQUE_SAUVEGARDES`. Le stockage objet de Laravel Cloud efface les fichiers `.html` : Kekeli n'y range que des archives `.tar.gz`, des photos, des documents, des captures et des reçus, jamais de page HTML.

## 6. Application GitHub (gratuite), après la mise en ligne

Kekeli utilise une **GitHub App** (« Kekeli-Lome », déjà créée ; guide complet : `docs/github-app.md`), et non plus une « OAuth App ». À la mise en ligne (essai 7 de la partie 0.9) :
- adresse de retour (« Callback URL ») : `https://kekeli.laravel.cloud/github/retour` ;
- page d'accueil (« Homepage URL ») : `https://kekeli.laravel.cloud` ;
- installation : **« Any account »**, pour que les clients puissent l'installer ;
- variables du site : `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET`. Tant qu'elles manquent, « Relier à GitHub » reste caché (sans message « bientôt »).

## 7. Connecteurs (MCP), après la mise en ligne

Construits le 13 novembre 2026 (`docs/plan.md`, partie 26.16) et essayés pour de vrai seulement avec les serveurs sans compte (DeepWiki, Hugging Face). À faire une fois le site en ligne, sur le téléphone du porteur du projet :

- Vérifier que `KEKELI_RUNNER_URL` et `KEKELI_RUNNER_SECRET` sont réglés sur le site (sinon l'onglet « Connecteurs » reste caché).
- Essai réel de la connexion OAuth : Réglages → Connecteurs → « Connecter » sur **Notion** (Kekeli s'inscrit tout seul auprès de Notion avec l'adresse de retour `https://<adresse du site>/connecteurs/retour`), accepter avec un compte Notion d'essai, puis dans une discussion : « Que dit ma page … ? » (lecture) et « Crée une page … » (« Autoriser », puis vérifier la page dans Notion).
- Faire de même avec Airtable, Jira et Confluence, Linear et Miro (comptes gratuits d'essai du porteur du projet, aucune donnée réelle de client).
- Vérifier le journal des actions et « Déconnecter » (le jeton doit disparaître).
- Plus tard, catégorie (2) : HubSpot, Asana, Dropbox, GitHub (gestes dans `docs/plan.md`, partie 26.16.2).

## 8. Automatisations lancées par un événement, après la mise en ligne

Construites le 13 novembre 2026 (`docs/plan.md`, partie 26.20). Essai réel fait **jusqu'au point d'entrée** : vrai site et vraie automatisation construits par Kekeli, formulaire branché par Kekeli, envoi depuis l'aperçu du site, point d'entrée écrit par Kekeli lancé dans le vrai moteur de Cloudflare (sur l'ordinateur de Claude), automatisation lancée comme le ferait GitHub Actions. **Ce qui reste à faire avec de vrais comptes**, une fois Kekeli en ligne et la GitHub App reliée (partie 6) :

- Avec un compte d'essai du porteur du projet : créer une automatisation « quand le formulaire de contact de mon site est rempli, envoie-moi un résumé par e-mail », la relier à GitHub (« Créer un dépôt privé »).
- Sur le téléphone : créer un compte Cloudflare gratuit (ouvrir une fois « Workers & Pages » pour choisir l'adresse en `workers.dev`), créer la clé « Edit Cloudflare Workers », créer le jeton GitHub à grain fin (seulement ce dépôt, « Contents » en lecture et écriture), puis mettre `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` et `GITHUB_DECLENCHEUR` dans les Variables secrètes de l'automatisation (toujours cacher les clés avant une capture).
- Vérifier sur GitHub, onglet « Actions », que « Point d'entrée » s'est mis en ligne, et copier l'adresse écrite à la fin.
- Dans Kekeli : « Brancher un formulaire » avec cette adresse, puis « Publier à nouveau » le site.
- Remplir le formulaire du site publié depuis le téléphone : vérifier dans « Déclenchements » la ligne « Événement reçu · Réussi », et l'e-mail reçu (clé Resend gratuite du porteur du projet, adresse d'essai).
- Remplir deux fois de suite le même message : un seul lancement. Envoyer depuis un autre site : refusé.

## 9. E-mails (Brevo), reçus et connexion avec Google, après la mise en ligne

Construit et testé le 14 novembre 2026 avec l'envoi simulé et Google simulé (`docs/plan.md`, partie 26.22). Le réseau de l'environnement de Claude ne laisse sortir que le HTTPS : le serveur SMTP de Brevo y est injoignable (ports 587, 465, 2525 et 25 refusés), donc **aucun e-mail réel n'a pu partir pendant la construction**. À faire depuis Laravel Cloud :

1. Vérifier que les réglages `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME` (les mêmes que dans l'environnement de Claude) sont dans l'application « kekeli », et que l'adresse `MAIL_FROM_ADDRESS` est un expéditeur validé dans Brevo (Brevo, « Expéditeurs et domaines »).
2. Facultatif : `ADMIN_ALERT_EMAIL`, l'adresse qui reçoit « Un paiement attend votre vérification » (sinon : les comptes administrateurs).
3. Lancer une seule fois `php artisan tinker --execute="require 'scripts/essai-recu-reel.php';"` dans les commandes de l'application : un reçu d'essai (données inventées) est mis en page, puis **un seul** e-mail part vers l'adresse de l'administrateur. Ouvrir la boîte de réception sur le téléphone, vérifier l'e-mail et le PDF joint.
4. Dans Administration → Réglages, « E-mails et reçus » : relire le plafond (150 par jour au départ, le compte Brevo est partagé avec les autres sites), les téléphones de contact et l'émetteur des reçus (le téléphone de l'émetteur et les numéros légaux restent vides tant qu'ils ne sont pas saisis).
5. Se connecter une fois avec un code reçu par e-mail, puis avec « Se connecter avec un mot de passe » (compte administrateur).

### Créer l'identifiant Google (gratuit), depuis le téléphone

Tant que `GOOGLE_CLIENT_ID` est absent, le bouton « Continuer avec Google » n'apparaît pas : rien d'autre ne change.

1. Dans Chrome, ouvrir **console.cloud.google.com** et se connecter avec le compte Google de Lomé IA School.
2. En haut, toucher le nom du projet (ou « Sélectionner un projet »), puis **« Nouveau projet »** ; nom : **Kekeli** ; toucher **« Créer »**, puis choisir ce projet en haut.
3. Menu ☰ → **« API et services »** → **« Écran de consentement OAuth »** (parfois appelé « Google Auth Platform ») → **« Commencer »** :
   - nom de l'application : **Kekeli** ; adresse e-mail d'assistance : la vôtre ; **« Suivant »** ;
   - public (audience) : **« Externe »** ; **« Suivant »** ;
   - coordonnées : votre adresse e-mail ; **« Suivant »** ; cocher l'accord ; **« Créer »**.
4. Dans le menu de gauche, **« Audience »** → **« Publier l'application »** → confirmer : Kekeli ne demande que le nom, l'adresse e-mail et la photo (informations de base), donc aucune vérification longue par Google n'est demandée.
5. **« Clients »** (ou « Identifiants » → « Créer des identifiants » → « ID client OAuth ») → **« Créer un client »** :
   - type d'application : **« Application Web »** ; nom : **Kekeli** ;
   - **Origines JavaScript autorisées** : toucher « Ajouter un URI » et écrire `https://kekeli.laravel.cloud`, puis de nouveau « Ajouter un URI » et `http://127.0.0.1:8123` (essais de Claude) ;
   - **URI de redirection autorisés** : `https://kekeli.laravel.cloud/auth/google/retour`, puis `http://127.0.0.1:8123/auth/google/retour` ;
   - toucher **« Créer »**.
6. Une fenêtre montre l'**ID client** et le **code secret du client**. **Ne les collez jamais dans la discussion avec Claude.** Toucher l'icône de copie à côté de chacun et les coller directement dans les réglages :
   - application « kekeli » sur Laravel Cloud (Settings, Environment) : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, et `GOOGLE_REDIRECT_URI` = `https://kekeli.laravel.cloud/auth/google/retour` ;
   - environnement « kekeli » de Claude (menu de l'environnement de la session, « Modifier », variables d'environnement) : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, et `GOOGLE_REDIRECT_URI` = `http://127.0.0.1:8123/auth/google/retour`.
7. Écrire à Claude « Google placé » : il fait l'essai réel (nouveau compte et compte existant) et prend les captures.

### Page « Branding » de Google Auth Platform : les valeurs exactes, dans l'ordre des champs

Dans la console Google Cloud, projet **Kekeli** : menu ☰ → **« Google Auth Platform »** → **« Branding »**. Chaque valeur ci-dessous est prête à copier (adresses vérifiées dans les routes du site le 14 novembre 2026 : accueil `home`, politique de confidentialité `legal.privacy`, conditions d'utilisation `legal.terms`).

| Champ (dans l'ordre de la page) | Valeur à saisir |
|---|---|
| Nom de l'application (« App name ») | `Kekeli` |
| Adresse e-mail d'assistance utilisateur (« User support email ») | `kekelilome3@gmail.com` |
| Logo de l'application (facultatif) | ne rien mettre pour l'instant (un logo oblige Google à vérifier l'application, ce qui prend plusieurs jours) |
| Page d'accueil de l'application (« Application home page ») | `https://kekeli.laravel.cloud/` |
| Lien vers les règles de confidentialité (« Application privacy policy link ») | `https://kekeli.laravel.cloud/confidentialite` |
| Lien vers les conditions d'utilisation (« Application terms of service link ») | `https://kekeli.laravel.cloud/conditions-d-utilisation` |
| Domaines autorisés (« Authorized domains »), toucher « Ajouter un domaine » | `kekeli.laravel.cloud` |
| Adresse e-mail du développeur (« Developer contact information ») | `kekelilome3@gmail.com` |

Puis toucher **« Enregistrer »** en bas de la page.

Ensuite, pour que tout le monde puisse se connecter avec Google :

1. Menu de gauche → **« Audience »**.
2. Sous « État de publication » (« Publishing status : Testing »), toucher **« Publier l'application »** (« Publish app »), puis **« Confirmer »**.
3. L'état devient **« En production »**. Kekeli ne demande que le nom, l'adresse e-mail et la photo (informations de base) : Google ne demande pas de vérification longue pour cela.
4. Écrire à Claude « Google publié » : il fait alors le premier essai réel de connexion (aucun essai réel n'est fait avant).

**État au 14 novembre 2026 : application Google « Kekeli » publiée, « En production »** (fait par le porteur du projet). Les identifiants (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`) ne sont jamais écrits dans le dépôt ni dans la discussion. **Le vrai essai de « Continuer avec Google » se fera à la mise en ligne, sur `https://kekeli.laravel.cloud`** (nouveau compte, puis compte existant avec la même adresse), pas avant. Le bouton n'apparaît que lorsque `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` sont réglés dans l'application « kekeli » de Laravel Cloud, avec `GOOGLE_REDIRECT_URI` = `https://kekeli.laravel.cloud/auth/google/retour`.

**Si Google refuse le domaine `laravel.cloud`** (par exemple « le domaine doit être un domaine privé de premier niveau » ou « validez la propriété du domaine »), il faudra passer à **kekeli.dev** (à acheter avec l'accord du porteur du projet), puis changer partout la même adresse :

- page « Branding » : page d'accueil `https://kekeli.dev/`, confidentialité `https://kekeli.dev/confidentialite`, conditions `https://kekeli.dev/conditions-d-utilisation`, domaine autorisé `kekeli.dev` (Google demande de prouver qu'il vous appartient, dans Google Search Console, avec un enregistrement « TXT » chez le vendeur du nom de domaine) ;
- page « Clients », client « Kekeli » : origine JavaScript `https://kekeli.dev` et adresse de redirection `https://kekeli.dev/auth/google/retour` (garder celles de l'essai local) ;
- application « kekeli » sur Laravel Cloud : `APP_URL` = `https://kekeli.dev` et `GOOGLE_REDIRECT_URI` = `https://kekeli.dev/auth/google/retour`, et brancher le nom de domaine dans Laravel Cloud (« Domains »).
