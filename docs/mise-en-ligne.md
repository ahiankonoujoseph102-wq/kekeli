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

**Recommandation** : la solution B, pour avoir un vrai plafond qui ne touche jamais vos autres sites. Le coût est d'environ **5 $ par mois au début (2 950 FCFA)**, puis de **8 à 13 $ (4 700 à 7 700 FCFA)** quand la bêta sera active.

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
- Variables (valeurs placées par le porteur du projet, jamais dans un message) : `APP_KEY`, `APP_URL`, `KEKELI_RUNNER_URL`, `KEKELI_RUNNER_SECRET`, `KEKELI_FOURNISSEURS`, `KEKELI_DOMAINES_APERCU`, `KEKELI_DISQUE_SAUVEGARDES=s3`, et pour GitHub `GITHUB_CLIENT_ID` et `GITHUB_CLIENT_SECRET`.

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
