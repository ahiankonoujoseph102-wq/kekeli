# Kekeli — l'agent IA qui code pour vous

Kekeli veut dire « la lumière » en éwé. C'est un agent de programmation en ligne : on décrit son projet en mots simples, l'agent le construit, le teste et montre le résultat en direct.

Projet de Lomé IA School, Lomé (Togo).

**Tous droits réservés — Joseph Ahiankonou.** Le code est visible, mais il est interdit de le copier, de le réutiliser ou de le vendre sans autorisation écrite. Voir [`LICENSE`](LICENSE).

- Consignes, cahier des charges et décisions : [`CLAUDE.md`](CLAUDE.md)
- Plan détaillé par étapes : [`docs/plan.md`](docs/plan.md)

## Technique

- Laravel 12, Livewire 4 avec Volt, Flux (version gratuite), Tailwind CSS 4
- PostgreSQL en production (Laravel Cloud), SQLite pour les tests rapides
- Interface en français (`lang/fr.json` et `lang/fr/`)
- Runner (Node.js 22) dans [`runner/`](runner/README.md) : ordinateurs virtuels E2B et agent OpenCode

## Commandes

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan test          # tous les tests
vendor/bin/pint --test    # vérification du style du code
(cd runner && npm ci && npm test)   # tests du Runner
```

## Faire tourner l'agent en local

Il faut trois programmes lancés en même temps, avec `KEKELI_RUNNER_SECRET` identique dans le site (`.env`) et dans le Runner :

```bash
php artisan serve --port=8123
php artisan queue:work
(cd runner && npm start)
```

Donner l'accès à l'administration à un compte : `php artisan kekeli:admin adresse@exemple.tg`.

## Mise en ligne sur Laravel Cloud

- Commande de construction : `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
- Commande de déploiement : `php artisan migrate --force`
- Base de données : PostgreSQL serverless attachée à l'environnement (Laravel Cloud remplit lui-même les variables `DB_*`).

Aucune clé ni aucun secret ne doit être écrit dans ce dépôt : tout passe par les variables d'environnement de Laravel Cloud.
