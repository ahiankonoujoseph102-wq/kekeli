# Kekeli — l'agent IA qui code pour vous

Kekeli veut dire « la lumière » en éwé. C'est un agent de programmation en ligne : on décrit son projet en mots simples, l'agent le construit, le teste et montre le résultat en direct.

Projet de Lomé IA School, Lomé (Togo).

- Consignes, cahier des charges et décisions : [`CLAUDE.md`](CLAUDE.md)
- Plan détaillé par étapes : [`docs/plan.md`](docs/plan.md)

## Technique

- Laravel 12, Livewire 4 avec Volt, Flux (version gratuite), Tailwind CSS 4
- PostgreSQL en production (Laravel Cloud), SQLite pour les tests rapides
- Interface en français (`lang/fr.json` et `lang/fr/`)

## Commandes

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan test          # tous les tests
vendor/bin/pint --test    # vérification du style du code
```

## Mise en ligne sur Laravel Cloud

- Commande de construction : `composer install --no-dev --optimize-autoloader && npm ci && npm run build`
- Commande de déploiement : `php artisan migrate --force`
- Base de données : PostgreSQL serverless attachée à l'environnement (Laravel Cloud remplit lui-même les variables `DB_*`).

Aucune clé ni aucun secret ne doit être écrit dans ce dépôt : tout passe par les variables d'environnement de Laravel Cloud.
