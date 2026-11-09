# Mise en ligne sur Laravel Cloud (étape 8)

Rien n'est encore créé sur Laravel Cloud. **Rien de payant ne sera créé sans l'accord écrit du porteur du projet** (règle numéro 8). Prix relevés le 9 novembre 2026 sur les pages officielles de Laravel Cloud ([prix](https://laravel.com/cloud/docs/pricing), [ordinateurs](https://laravel.com/cloud/docs/compute), [files d'attente](https://laravel.com/cloud/docs/queues)). **Ce sont des estimations** tant qu'une vraie facture n'a pas été vue.

## 1. Ce qui sera créé, et combien cela coûte

| Élément | Réglage | Prix officiel | Estimation par mois |
|---|---|---|---|
| Abonnement « Starter » | Une organisation | 5 $ par mois, **31 premiers jours gratuits**, avec 5 $ de crédit d'utilisation chaque mois | 5 $ (environ 2 950 FCFA), 0 $ le premier mois |
| Application « kekeli » (le site) | Ordinateur « Flex 512 Mo », qui s'endort sans visite | 0,00893 $ par heure allumée, 28 jours facturés au plus | 0,50 $ à 2 $ s'il dort la nuit ; 6 $ au plus s'il reste allumé |
| Application « kekeli-runner » (le Runner) | Ordinateur « Flex 512 Mo », qui s'endort sans demande | 0,00893 $ par heure allumée, 28 jours au plus | 0,50 $ à 2 $ s'il dort ; 6 $ au plus s'il reste allumé |
| Base de données PostgreSQL « serverless » | 0,25 unité, s'endort sans requête | environ 0,106 $ par heure pour 1 unité, soit environ 0,027 $ par heure pour 0,25 ; environ 0,50 $ par Go et par mois | 1 $ à 3 $ |
| Espace de fichiers (« bucket ») | Sauvegardes des projets, photos, captures de paiement | 0,02 $ par Go et par mois ; sorties gratuites | moins de 0,10 $ |
| Adresse | `….laravel.cloud`, gratuite | gratuit | 0 $ |

**Total estimé** : environ **5 à 6 $ par mois (3 000 à 3 600 FCFA)** avec la version qui s'endort, car le crédit de 5 $ couvre presque toute l'utilisation ; **environ 15 $ par mois (9 000 FCFA)** si le Runner et le site doivent rester allumés. **Premier mois gratuit.**

Garde-fou proposé : une **limite de dépense de 10 $ par mois** (Organisation → Réglages → Facturation → Limite de dépense), avec les alertes à 50 %, 80 % et 90 %.

Rappels : l'ordinateur virtuel E2B reste sur son crédit gratuit unique de 100 $ (offre « Hobby ») ; Gemini et Cloudflare restent gratuits ; le nom de domaine kekeli.dev n'est pas acheté (adresse gratuite en `laravel.cloud` pour le moment).

## 2. Version qui s'endort : comment Kekeli reste éveillé quand il le faut

- **Le site** se réveille à chaque visite (moins d'une demi-seconde). Pendant qu'une demande travaille, le Runner envoie sans arrêt les étapes de l'agent au site : il reste donc éveillé. Le planificateur de Laravel Cloud réveille le site pour ses tâches (chaque heure à 7 minutes, et chaque nuit à 3 h 17).
- **Le Runner** ne reçoit rien de l'extérieur pendant qu'il travaille. Pour ne pas s'endormir en plein travail, il appelle sa propre adresse publique chaque minute tant qu'une demande est en cours (variable `KEKELI_RUNNER_ADRESSE_PUBLIQUE`), puis il se laisse endormir.
- **Les ordinateurs E2B** ont leur propre minuterie chez E2B : un Runner endormi ne laisse jamais un ordinateur allumé.
- **À vérifier pendant l'essai réel** : qu'une demande complète va jusqu'au bout sans coupure, que la publication fonctionne, et que le réveil après une nuit fonctionne. Si ce n'est pas fiable, on passe le Runner en version toujours allumée (environ 6 $ par mois de plus, estimation) et on prévient le porteur du projet.

## 3. Réglages des deux applications

### Application « kekeli » (le site)
- Dépôt : `kekeli`, branche `main`.
- Construction : `composer install --no-dev --optimize-autoloader` puis `npm ci && npm run build`.
- Déploiement : `php artisan migrate --force`.
- Processus en arrière-plan : `php artisan queue:work --tries=3 --timeout=300` (les longues publications ne tiennent pas dans les files gérées, limitées à 90 secondes par travail).
- Planificateur : activé.
- Base de données : PostgreSQL serverless, 0,25 unité.
- Espace de fichiers : un « bucket » privé rattaché à l'application, puis `KEKELI_DISQUE_SAUVEGARDES=s3`. Les fichiers `.html` y sont effacés automatiquement par Laravel Cloud : Kekeli n'y range que des archives `.tar.gz`, des photos et des captures, jamais de page HTML.
- Variables (valeurs placées par le porteur du projet, jamais dans un message) : `APP_KEY`, `APP_URL`, `KEKELI_RUNNER_URL`, `KEKELI_RUNNER_SECRET`, `KEKELI_FOURNISSEURS`, `KEKELI_DOMAINES_APERCU`, `KEKELI_DISQUE_SAUVEGARDES=s3`, et pour GitHub `GITHUB_CLIENT_ID` et `GITHUB_CLIENT_SECRET`.

### Application « kekeli-runner » (le Runner)
- Même dépôt, branche `main`.
- Construction : `cd runner && npm ci --audit false`.
- Démarrage : `node runner/src/server.js`.
- Variables : `KEKELI_RUNNER_SECRET` (le même que le site), `KEKELI_RUNNER_HOTE=0.0.0.0`, `KEKELI_RUNNER_ADRESSE_PUBLIQUE` (l'adresse du Runner en `laravel.cloud`), et les clés : `E2B_API_KEY`, `GEMINI_API_KEY`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` (et, s'il y en a, `OPENROUTER_API_KEY`, `MISTRAL_API_KEY`, `PEXELS_API_KEY`). **Le Runner est le seul endroit qui détient les clés.**

## 4. Application GitHub (gratuite), après la mise en ligne

Pour l'envoi du code des clients vers GitHub, il faut une « OAuth App » de Kekeli, créée une fois sur GitHub (Réglages → Developer settings → OAuth Apps → New OAuth App), avec :
- nom : Kekeli ;
- adresse du site : l'adresse de Kekeli en `laravel.cloud` ;
- adresse de retour : la même adresse suivie de `/github/retour`.

GitHub donne alors un numéro (`GITHUB_CLIENT_ID`) et un secret (`GITHUB_CLIENT_SECRET`), à placer dans les variables de l'application « kekeli ». Tant qu'ils manquent, la page « Mon code » affiche « L'envoi vers GitHub sera disponible très bientôt. »
