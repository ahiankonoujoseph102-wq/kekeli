# Mise en ligne sur Laravel Cloud (étape 8)

Rien n'est encore créé sur Laravel Cloud. **Rien de payant ne sera créé sans l'accord écrit du porteur du projet** (règle numéro 8). Prix et règles relevés le 9 novembre 2026 sur les pages officielles de Laravel Cloud ([prix](https://laravel.com/cloud/docs/pricing), [organisations](https://laravel.com/cloud/docs/organizations), [limite de dépense](https://laravel.com/cloud/docs/spending-limits), [usage](https://laravel.com/cloud/docs/usage), [ordinateurs](https://laravel.com/cloud/docs/compute)). **Ce sont des estimations** tant qu'une vraie facture n'a pas été vue.

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

- **Le site** se réveille à chaque visite (moins d'une demi-seconde). Pendant qu'une demande travaille, le Runner envoie sans arrêt les étapes de l'agent au site : il reste donc éveillé. Le planificateur de Laravel Cloud réveille le site pour ses deux tâches de nuit (à 0 h 07, heure de Lomé, et à 3 h 17).
- **Le Runner** ne reçoit rien de l'extérieur pendant qu'il travaille. Pour ne pas s'endormir en plein travail, il appelle sa propre adresse publique chaque minute tant qu'une demande est en cours (variable `KEKELI_RUNNER_ADRESSE_PUBLIQUE`), puis il se laisse endormir.
- **Les ordinateurs E2B** ont leur propre minuterie chez E2B : un Runner endormi ne laisse jamais un ordinateur allumé.
- **À vérifier pendant l'essai réel** : qu'une demande complète va jusqu'au bout sans coupure, que la publication fonctionne, et que le réveil après une nuit fonctionne. Si ce n'est pas fiable, on passe le Runner en version toujours allumée (environ 6 $ par mois de plus, estimation) et on prévient le porteur du projet.

## 5. Réglages des deux applications

### Application « kekeli » (le site)
- Dépôt : `kekeli`, branche `main`.
- Construction : `composer install --no-dev --optimize-autoloader` puis `npm ci && npm run build`.
- Déploiement : `php artisan migrate --force`.
- Processus en arrière-plan : `php artisan queue:work --tries=3 --timeout=300` (les longues publications ne tiennent pas dans les files gérées, limitées à 90 secondes par travail).
- Planificateur : activé.
- Base de données : PostgreSQL serverless, 0,25 unité.
- Espace de fichiers : un « bucket » privé rattaché à l'application, puis `KEKELI_DISQUE_SAUVEGARDES=s3`. Les fichiers `.html` y sont effacés automatiquement par Laravel Cloud : Kekeli n'y range que des archives `.tar.gz`, des photos et des captures, jamais de page HTML.
- Variables (valeurs placées par le porteur du projet, jamais dans un message) : `APP_KEY`, `APP_URL`, `KEKELI_RUNNER_URL`, `KEKELI_RUNNER_SECRET`, `KEKELI_FOURNISSEURS`, `KEKELI_DOMAINES_APERCU`, `KEKELI_DISQUE_SAUVEGARDES=s3`, `LARAVEL_CLOUD_API_TOKEN` (lecture du coût), et pour GitHub `GITHUB_CLIENT_ID` et `GITHUB_CLIENT_SECRET`.

### Application « kekeli-runner » (le Runner)
- Même dépôt, branche `main`.
- Construction : `cd runner && npm ci --audit false`.
- Démarrage : `node runner/src/server.js`.
- Variables : `KEKELI_RUNNER_SECRET` (le même que le site), `KEKELI_RUNNER_HOTE=0.0.0.0`, `KEKELI_RUNNER_ADRESSE_PUBLIQUE` (l'adresse du Runner en `laravel.cloud`), et les clés : `E2B_API_KEY`, `GEMINI_API_KEY`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` (et, s'il y en a, `OPENROUTER_API_KEY`, `MISTRAL_API_KEY`, `PEXELS_API_KEY`). **Le Runner est le seul endroit qui détient les clés.**

## 6. Application GitHub (gratuite), après la mise en ligne

Pour l'envoi du code des clients vers GitHub, il faut une « OAuth App » de Kekeli, créée une fois sur GitHub (Réglages → Developer settings → OAuth Apps → New OAuth App), avec :
- nom : Kekeli ;
- adresse du site : l'adresse de Kekeli en `laravel.cloud` ;
- adresse de retour : la même adresse suivie de `/github/retour`.

GitHub donne alors un numéro (`GITHUB_CLIENT_ID`) et un secret (`GITHUB_CLIENT_SECRET`), à placer dans les variables de l'application « kekeli ». Tant qu'ils manquent, la page « Mon code » affiche « L'envoi vers GitHub sera disponible très bientôt. »

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
