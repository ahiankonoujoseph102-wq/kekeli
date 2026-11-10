# Reprise du travail sur Kekeli

Ce fichier sert à reprendre le travail sans rien perdre, par exemple depuis une nouvelle séance ou un autre compte. Il est mis à jour à la fin de chaque chantier. Les règles complètes et toutes les décisions sont dans [`CLAUDE.md`](CLAUDE.md) (il fait foi) ; le plan détaillé est dans [`docs/plan.md`](docs/plan.md).

Dernière mise à jour : 10 novembre 2026.

## Comment reprendre

1. Lire `CLAUDE.md`, puis `docs/plan.md`, puis ce fichier.
2. Vérifier que les clés sont présentes dans l'environnement, **sans jamais afficher leur valeur** (par exemple : `[ -n "$GEMINI_API_KEY" ] && echo présente`).
3. Regarder les pull requests ouvertes et la dernière branche de travail ; comparer avec `main` (`git log origin/main..origin/<branche>`).
4. Installer : `composer install`, `npm ci`, `cd runner && npm ci`, copier `.env.example` en `.env`, `php artisan key:generate`, `npm run build`.
5. Vérifier : `php artisan test`, `vendor/bin/pint --test`, `cd runner && npm test`.

## Règles de travail (rappel court)

- Toujours en **français** correct, **sans abréviations**, y compris dans les commits et les pull requests.
- **Une étape à la fois**, terminée, testée et validée par le porteur du projet avant la suivante.
- Avant chaque envoi : `php artisan test` et `vendor/bin/pint --test` ; chaque nouvelle fonctionnalité arrive avec ses tests.
- **Aucune clé ni aucun secret** dans le code, les commits, les messages ou les réponses ; jamais afficher une clé, même en partie.
- Le porteur du projet est **débutant et travaille depuis son téléphone** : explications simples, gestes précis, captures sur un téléphone simulé (`docs/captures/`).
- Les coûts sont des **estimations** tant qu'ils ne sont pas mesurés.
- Claude ouvre la pull request, surveille la vérification automatique de GitHub, corrige, et **fusionne lui-même quand tout est vert** (jamais quand c'est rouge ou en cours), puis prévient le porteur du projet.
- **Aucune dépense** sans l'accord du porteur du projet : toujours chercher d'abord une solution gratuite.
- Ne jamais toucher à Talkami ni à Lomé IA School sur Laravel Cloud ; ne jamais activer « Stop all compute ».

## Noms des clés (jamais leurs valeurs)

| Nom | Où | À quoi elle sert |
|---|---|---|
| `GEMINI_API_KEY` | Runner (et environnement de Claude pour les essais) | Cerveau de l'agent, offre gratuite de Google AI Studio |
| `E2B_API_KEY` | Runner | Ordinateurs virtuels E2B |
| `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` | Runner | Création d'images gratuite (Workers AI) et publication (Cloudflare Pages) |
| `OPENROUTER_API_KEY`, `MISTRAL_API_KEY` | Runner (facultatives) | Fournisseurs gratuits de secours |
| `PEXELS_API_KEY` | Runner (facultative) | Photos libres Pexels |
| `KEKELI_RUNNER_SECRET` | Site et Runner | Signature des échanges entre le site et le Runner |
| `LARAVEL_CLOUD_API_TOKEN` | Site (lecture seule) | Lecture quotidienne du coût d'hébergement |

Le 10 novembre 2026 : `GEMINI_API_KEY`, `E2B_API_KEY`, `CLOUDFLARE_ACCOUNT_ID` et `CLOUDFLARE_API_TOKEN` sont placées dans l'environnement de Claude ; `OPENROUTER_API_KEY`, `MISTRAL_API_KEY` et `PEXELS_API_KEY` ne le sont pas (facultatives).

## Ce qui est fait (tout est dans `main`)

- Étapes 1 à 7 : terminées et validées (site Laravel, Runner et agent OpenCode dans E2B, aperçu en direct, sécurité, énergie et formules, qualité et images, publication sur Cloudflare Pages).
- Étape 8 : tout est construit et testé (paiements Mobile Money par capture, pays, file d'attente, code du client sur GitHub ou en ZIP, pages juridiques) ; **la mise en ligne attend l'accord du porteur du projet**.
- Galerie de modèles de sites (12 modèles pour l'Afrique) : construite, à valider.
- Mode développeur (ouvrir un dépôt GitHub) : construit et essayé ; envoi réel sur GitHub après la mise en ligne.
- Messages clairs (phrases pendant le travail, compte rendu) : construit, à valider.
- Sites avec base de données en Laravel ou Next.js (appelés « applications web » jusqu'au 10 novembre 2026) : construits et essayés pour de vrai, fusionnés dans `main` le 10 novembre 2026 (pull request numéro 20, vérifications vertes ; captures : `docs/captures/applications.md` ; énergie : `docs/couts.md`).

## Un seul parcours « Nouveau site » (décision du 10 novembre 2026)

Kekeli crée des **sites web**, comme Claude Code, Cursor, Codex, Lovable ou Manus : site vitrine **ou** site professionnel avec base de données, dans un seul parcours « Nouveau site ». Kekeli comprend tout seul, d'après la demande du client, s'il faut une base de données, des comptes, un espace administrateur. Le bouton et la page séparés « Nouvelle application web » sont supprimés ; le mot « application » n'apparaît plus pour le client. La galerie garde tous les modèles, rangés en « Sites vitrines » et « Sites avec base de données ». Tout ce qui a été construit est gardé (Laravel, Next.js, base de données, comptes, espace administrateur, aperçu, guides d'hébergement, ZIP pour cPanel, priorité des clients payants).

État : **construit, testé, essayé pour de vrai avec E2B et Gemini, et fusionné dans `main` le 10 novembre 2026** (pull request numéro 21). Fusion faite avec l'accord du porteur du projet alors que la vérification automatique de GitHub ne démarrait pas (quota gratuit de minutes GitHub Actions du mois épuisé, dépôt privé) ; tous les tests passaient en local (211 tests du site, 135 tests du Runner, style correct). À valider par le porteur du projet.

Ce qui a été fait :
- Page « Nouveau site » (`resources/views/livewire/projects/create.blade.php`, route `projects.create`, adresse `/projets/nouveau-site`) ; détection `app/WebApps/SiteNeeds.php` (gratuite, sans modèle) avec `tests/Unit/SiteNeedsTest.php` ; la description part aussitôt vers Kekeli (sinon elle attend dans la page du projet).
- Page « Nouvelle application web » supprimée ; `/projets/nouvelle-application` renvoie vers « Nouveau site ».
- Site vitrine qui demande des comptes : proposition « Créer un site avec base de données » (`createDatabaseSite` dans `projects/show`).
- Plus de mot « application » pour le client : textes du site, étapes, guides, consignes de l'agent, filet de sécurité `sansMotApplication` dans `runner/src/agent.js` (hors mode développeur), fichier de réglages des sites Laravel renommé `config/site.php`, site de départ « Mon site ».
- Galerie en « Sites vitrines » et « Sites avec base de données ».
- Corrections trouvées pendant les essais : nom tapé transmis à Kekeli, titres du compte rendu jamais affichés comme phrases de travail, étape « Site de départ mis en place » plus répétée après une mise en attente, site vitrine sans aucune page jamais annoncé comme terminé (l'agent corrige une fois).
- Captures : `docs/captures/nouveau-site.md` ; énergie : `docs/couts.md` (partie « Nouveau site ») ; essai : `scripts/essai-nouveau-site.js`.

Reste à faire pour ce chantier : validation par le porteur du projet.

## Contrôle avant le passage en public (10 novembre 2026)

Le porteur du projet veut rendre le dépôt public environ 2 mois, pour que GitHub Actions ait des minutes illimitées, puis le remettre en privé.

- **Clés** : aucune vraie clé dans tout l'historique (toutes les branches, tous les commits, tous les fichiers, y compris les 353 images lues avec un outil de lecture de texte). Les clés de l'environnement (`GEMINI_API_KEY`, `E2B_API_KEY`, `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`) n'apparaissent nulle part. Aucun fichier `.env` n'a jamais été enregistré.
- **Nettoyé dans `main`** : les numéros de paiement, les noms des titulaires et le numéro WhatsApp ne sont plus dans le code ni dans les documents (ils se règlent seulement dans Administration → Paiements) ; captures de l'étape 8 refaites avec des données inventées ; licence « Tous droits réservés — Joseph Ahiankonou » (`LICENSE`, README, `composer.json`).
- **Après la mise en ligne, il faudra saisir les numéros, les titulaires et le numéro WhatsApp dans Administration → Paiements** (ils ne sont plus dans le code).
- **Ce qui reste dans l'historique** (ne s'efface qu'en réécrivant l'historique, ce qui demande l'accord du porteur du projet) : les anciens numéros de paiement et noms des quatre titulaires (anciennes versions de `config/kekeli.php`, `CLAUDE.md`, des pages juridiques, de `tests/Feature/PaymentTest.php`, et six anciennes captures de l'étape 8, depuis le commit `541868d`), ainsi que l'adresse Gmail du porteur du projet comme auteur de 21 commits de fusion.
- **Le dépôt n'est pas public** : ces informations privées restent dans l'historique de `main`, et Claude n'a pas d'outil pour changer la visibilité du dépôt dans cette séance. Décision attendue du porteur du projet : réécrire l'historique (avec son accord) avant de rendre le dépôt public, ou le rendre public tel quel en connaissance de cause.
- Note à compléter le jour du passage en public : « Dépôt public depuis le [date], à remettre en privé vers le [date + 2 mois] ».

## Ce qui reste à faire ensuite

- Validation par le porteur du projet : galerie, messages clairs, sites avec base de données, parcours « Nouveau site ».
- Décision sur l'historique et le passage du dépôt en public (voir la partie « Contrôle avant le passage en public »).
- Mise en ligne sur Laravel Cloud (solution A, guidage une étape à la fois depuis le téléphone, `docs/mise-en-ligne.md`), puis essai réel de l'envoi sur GitHub du mode développeur.
- Avant le lancement public : accès GitHub limité aux dépôts choisis (application GitHub), relecture juridique.
- Plus tard : GitLab et Bitbucket, interface en anglais, clé Claude et pays européens, modèles payants pour les abonnés, achat de kekeli.dev.
