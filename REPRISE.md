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

## Chantier en cours : un seul parcours « Nouveau site » (décision du 10 novembre 2026)

Kekeli crée des **sites web**, comme Claude Code, Cursor, Codex, Lovable ou Manus : site vitrine **ou** site professionnel avec base de données, dans un seul parcours « Nouveau site ». Kekeli comprend tout seul, d'après la demande du client, s'il faut une base de données, des comptes, un espace administrateur. Le bouton et la page séparés « Nouvelle application web » sont supprimés ; le mot « application » n'apparaît plus pour le client. La galerie garde tous les modèles, rangés en « Sites vitrines » et « Sites avec base de données ». Tout ce qui a été construit est gardé (Laravel, Next.js, base de données, comptes, espace administrateur, aperçu, guides d'hébergement, ZIP pour cPanel, priorité des clients payants).

État : en cours (voir la fin de ce fichier pour le détail, mis à jour à la fin du chantier).

## Ce qui reste à faire ensuite

- Validation par le porteur du projet : galerie, messages clairs, sites avec base de données, parcours « Nouveau site ».
- Mise en ligne sur Laravel Cloud (solution A, guidage une étape à la fois depuis le téléphone, `docs/mise-en-ligne.md`), puis essai réel de l'envoi sur GitHub du mode développeur.
- Avant le lancement public : accès GitHub limité aux dépôts choisis (application GitHub), relecture juridique.
- Plus tard : GitLab et Bitbucket, interface en anglais, clé Claude et pays européens, modèles payants pour les abonnés, achat de kekeli.dev.
