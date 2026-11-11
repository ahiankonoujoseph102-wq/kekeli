# Reprise du travail sur Kekeli

Ce fichier sert à reprendre le travail sans rien perdre, par exemple depuis une nouvelle séance ou un autre compte. Il est mis à jour à la fin de chaque chantier. Les règles complètes et toutes les décisions sont dans [`CLAUDE.md`](CLAUDE.md) (il fait foi) ; le plan détaillé est dans [`docs/plan.md`](docs/plan.md).

Dernière mise à jour : 12 novembre 2026 (nouvelles formules codées).

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
| `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` | Runner | Création d'images gratuite, modèles gratuits de Workers AI (compte à garder sur « Workers Free ») et publication (Cloudflare Pages) |
| `OPENROUTER_API_KEY` | Runner | Fournisseur gratuit de secours (modèles « :free » permis pour des clients seulement, par défaut Gemma 4 ; 50 requêtes par jour) |
| `GROQ_API_KEY` | Runner (facultative) | Groq : gardé dans le code, hors de la liste (offre gratuite trop petite pour l'agent) |
| `MISTRAL_API_KEY` | Inutile | Mistral n'a plus d'offre gratuite pour l'API (code gardé, inactif) |
| `PEXELS_API_KEY` | Runner (facultative) | Photos libres Pexels |
| `KEKELI_RUNNER_SECRET` | Site et Runner | Signature des échanges entre le site et le Runner |
| `LARAVEL_CLOUD_API_TOKEN` | Site (lecture seule) | Lecture quotidienne du coût d'hébergement |

Le 10 novembre 2026 : `GEMINI_API_KEY`, `E2B_API_KEY`, `CLOUDFLARE_ACCOUNT_ID` et `CLOUDFLARE_API_TOKEN` sont placées dans l'environnement de Claude ; `OPENROUTER_API_KEY` et `GROQ_API_KEY` ont été placées ensuite ; `PEXELS_API_KEY` ne l'est pas (facultative), `MISTRAL_API_KEY` est inutile.

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
- **Le porteur du projet a rendu le dépôt public lui-même le 10 novembre 2026**, en connaissance de cause : l'historique ci-dessus reste lisible publiquement. Le passage en public a été vérifié par Claude (le dépôt est visible sans connexion).
- **Dépôt public depuis le 10 novembre 2026, à remettre en privé vers le 10 janvier 2027.**

## Fournisseurs gratuits Groq et OpenRouter (10 novembre 2026)

- Clés présentes le 10 novembre 2026 : `GEMINI_API_KEY`, `E2B_API_KEY`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_API_TOKEN`, `GROQ_API_KEY`, `OPENROUTER_API_KEY` (compte OpenRouter en offre gratuite, aucun crédit acheté, dépense 0 $).
- Conditions lues : les deux autorisent notre usage (intégration dans un produit pour nos clients), interdisent de revendre l'accès ; Groq n'entraîne pas sur les demandes ; chez OpenRouter, l'hébergeur de chaque modèle gratuit peut conserver ou entraîner. Détails : `docs/plan.md`, partie 20.
- Essais réels (restaurant et pressing) : Gemini réussit tout mais avec de longues pauses (pressing : 27 minutes, 8 pauses) ; OpenRouter (Nemotron 3 Ultra gratuit) fait le plus beau site vitrine mais s'arrête à 50 requêtes par jour ; Groq est impossible (8 000 tokens par minute). Tableau : `docs/couts.md` ; captures : `docs/captures/fournisseurs-gratuits.md`.
- Ordre de la liste à ce moment-là : `gemini,openrouter,mistral` (Groq retiré), remplacé le 11 novembre 2026 (partie suivante). Trois verrous empêchent tout modèle payant chez OpenRouter.
- Points à surveiller : un refus passager d'OpenCode après une relance (premier essai du pressing Gemini). L'erreur technique en anglais pendant le redémarrage de l'aperçu est corrigée le 11 novembre 2026.
- Décision possible du porteur du projet (payante, donc pas faite) : 10 $ de crédits OpenRouter, une seule fois, pour passer à 1 000 requêtes gratuites par jour.

## Fournisseurs repris de freellmapi (10 et 11 novembre 2026)

- Dépôt [freellmapi](https://github.com/tashfeenahmed/freellmapi) analysé (licence MIT, mentions dans `MENTIONS.md`) : **pas installé** (usage personnel selon son auteur), il ne contient aucune clé. Conditions officielles de 19 fournisseurs lues : tableau dans `docs/plan.md`, partie 21.
- Ajouts : Cloudflare Workers AI comme fournisseur de modèles (même clé que les images), liste des modèles gratuits et blocage de tout modèle payant chez chaque fournisseur (site et Runner), même modèle 30 minutes par projet (`MemoireModeles`).
- **Ordre de la liste : `gemini,gemini/gemini-3.1-flash-lite,openrouter,openrouter/cohere/north-mini-code:free,workersai,mistral`.** Essais et nombre de sites par jour (estimations) : `docs/couts.md`, dernière partie.
- Ordre corrigé le même jour (partie suivante).
- À faire : mesurer Workers AI un jour où les images n'ont pas pris la réserve gratuite ; décisions du porteur du projet sur les fournisseurs « à décider » (Z.ai et Zhipu, Ollama Cloud, revendeurs).

## Nettoyage du 11 novembre 2026

- **Mistral écarté** : plus d'offre gratuite pour l'API (plan gratuit sans clé API, formule « Experiment » supprimée). Code gardé, inactif. Aucune clé à créer.
- **OpenRouter** : chaque modèle gratuit suit les conditions de son hébergeur (article 5.1 d'OpenRouter). North (Cohere, interdit de « commercially exploit ») et Nemotron (NVIDIA, conditions d'essai « not in production ») retirés ; **Gemma 4 31B** (servi par Google, usage commercial permis) par défaut, qualité encore à mesurer.
- **Ordre de la liste : `gemini,gemini/gemini-3.1-flash-lite,openrouter,workersai`.** Environ 32 à 35 sites vitrines par jour (estimation, `docs/couts.md`).
- **Compte d'essai dans l'aperçu** : vrai défaut (l'agent avait lancé `migrate:fresh`), corrigé : le Runner recrée le compte après chaque demande, et l'agent ne doit plus vider la base. **Erreurs de l'aperçu** : « Votre site redémarre » ou un message clair en français, jamais la page technique en anglais. Détails : `docs/plan.md`, partie 22.
- À faire : mesurer Gemma 4 sur OpenRouter et Workers AI dès que leurs réserves le permettent.

## Analyse « aussi professionnel que Bolt.new, Lovable, Claude Code, Codex, Cursor et Manus » (11 novembre 2026)

- **Analyse seulement, aucun code modifié** : `docs/plan.md`, partie 23 (étude des six outils, Kekeli écran par écran, 10 points « amateur », 13 changements classés, deux parcours GitHub, ce qui dépend du modèle et de l'interface, découpage en pull requests).
- Demandes du porteur du projet : retirer la galerie des modèles de sites ; une simple zone « Décrivez votre site web » ; GitHub « dépôt d'abord » ou « site d'abord » ; parcours professionnel partout. Toujours dire « sites web », jamais « applications ».
- **Accord du porteur du projet (11 novembre 2026)** : ordre accepté, pull requests 2 et 3 réunies, toute la galerie retirée (le socle reste), mise en ligne sur Laravel Cloud après la pull request 5 et avant la pull request 6.
- **Fait (pull requests 2 et 3 réunies)** : premier écran « Que voulez-vous créer aujourd’hui ? » sur l'accueil et dans « Mes projets », texte gardé pendant l'inscription, nom, type de site et technologie devinés sans être montrés, galerie retirée partout (site, Runner, données, scripts, colonnes), plus de « Laravel (PHP) et MySQL », « Sites autorisés » dans le menu « ⋯ », plus de badge « en construction ». Détails : `docs/plan.md`, partie 23.8 ; captures : `docs/captures/premier-ecran.md`.
- **Premier écran validé par le porteur du projet** (pull request numéro 27) ; il garde « Que voulez-vous créer aujourd’hui ? ».
- **Fait (pull request 4, organisation des écrans)** : page du projet en plein écran avec une barre fine (retour, nom, état, « Mettre en ligne », « ⋯ »), trois vues sur le téléphone (Discussion, Aperçu, Code) et côte à côte sur ordinateur ; étapes cochées et dépliables dans la discussion, compte rendu en dernier message ; plus de pages « Comptes rendus » ni « Mes photos » (photos jointes dans la zone de saisie) ; publication et nom de domaine dans la fenêtre « Mettre en ligne » ; énergie en petit, détail dans le menu du compte ; attente en petite ligne grise ; pastille « GitHub » dans la zone ; pages de compte sans impératif ; erreurs douces. Étude et reste à faire : `docs/plan.md`, partie 23.9 ; captures avant et après : `docs/captures/organisation.md`.
- **Fait (nettoyage professionnel, 11 novembre 2026)** : règle « si Claude Code, Lovable ou Bolt.new n'ont pas un élément, ou le montrent autrement, on l'enlève ou on fait comme eux ». Dernier message de Kekeli court et simple, sans jargon (ni espace insécable, ni emoji, ni « code 200 », ni commande, ni nom de fichier, ni ligne en double) ; actions repliées dans « Kekeli a fait X actions » avec des libellés simples ; fichiers modifiés seulement dans l'onglet Code ; plus de « Copier le compte rendu », « Partager sur WhatsApp » ni « Voir les modifications » ; plus d'emoji dans les messages système ; aperçu qui se rallume tout seul avec une animation ; GitHub caché tant qu'il ne fonctionne pas ; tous les écrans du client allégés. Détails écran par écran : `docs/plan.md`, partie 23.10 ; captures avant et après : `docs/captures/nettoyage.md`.

- **Fait (12 novembre 2026, publier et récupérer son site)** : site vitrine publié en un geste, plus « Télécharger le code » et « Relier à GitHub » ; site avec base de données : plus de guide pas à pas ni de fichier ZIP pour cPanel, bouton « Récupérer le code » avec une phrase honnête, « Télécharger le code » et « Relier à GitHub » ; « Relier à GitHub » caché tant que l'accès à GitHub n'est pas réglé ; **questions dans la discussion** : Kekeli reconnaît une question et répond sans toucher au site, avec une seule requête et sans ordinateur (environ 0,3 à 0,4 FCFA) ; conseils d'hébergement honnêtes selon le site et le pays. Détails : `docs/plan.md`, partie 23.12 ; captures : `docs/captures/publier.md` ; énergie : `docs/couts.md`.

- **Fait (12 novembre 2026, nouvelles formules codées)** : Gratuite (30 FCFA par jour), Pro 7 500 FCFA / 13 $ (4 000 FCFA par mois), Expert 20 000 FCFA / 34 $ (11 000), Agence 50 000 FCFA / 85 $ (28 000) ; énergie par mois pour les payantes ; page « Formules » avec des cartes et des équivalences ; abonnés de Découverte et d'Entreprise passés à Pro et à Expert sans perte (migration) ; plafond gratuit 1 000 FCFA, 5 minutes avant la pause pour la gratuite ; cache de Sonnet 5.5 à 0,10 $. Captures : `docs/captures/formules.md` ; détails : `docs/plan.md`, partie 25.7.
- **Proposition de nouvelles formules (12 novembre 2026), acceptée et codée (ligne au-dessus)** : `docs/plan.md`, partie 25. Décisions du porteur du projet : la gratuite reste à 30 FCFA par jour, les payantes commencent à 7 500 FCFA. Proposition de Claude : **Gratuite** (30 FCFA par jour), **Pro** 7 500 FCFA (4 000 FCFA d'énergie par mois, environ 14 sites vitrines complets), **Expert** 20 000 FCFA (11 000, environ 40), **Agence** 50 000 FCFA (28 000, environ 100) ; énergie **par mois** pour les payantes, par jour pour la gratuite ; marge minimale d'environ 37 % avec la clé Claude (énergie comptée au coût réel) ; prix hors zone FCFA : 13 $, 34 $, 85 $ (12 €, 31 €, 76 €) ; plus tard un agrégateur africain et un paiement par carte international ; formule gratuite : plafond total ramené à 1 000 FCFA par jour au lancement et attente de 5 minutes avant la pause. Coût estimé d'un site avec la clé Claude (mélange Haiku 5.5 et Sonnet 5.5, estimation prudente, non mesuré) : environ 100 FCFA pour un site vitrine, 200 FCFA pour un site avec base de données. **Codée le 12 novembre 2026.**

## Ordre de la suite (décidé le 11 novembre 2026, `docs/plan.md`, partie 23.11)

1. Le nettoyage professionnel (fait).
2. **La mise en ligne de Kekeli sur Laravel Cloud** (prochaine étape, `docs/mise-en-ligne.md`).
3. L'application GitHub, puis les **deux façons de travailler avec GitHub, aussi importantes l'une que l'autre et livrées ensemble** : **dépôt d'abord** (le client crée son dépôt sur GitHub, le relie à Kekeli, et Kekeli pousse chaque changement dessus au fur et à mesure) et **site d'abord** (le client crée son site dans Kekeli, le relie à GitHub quand il veut, puis Kekeli pousse chaque changement dessus).
4. Ensuite seulement : les versions et le retour en arrière pour les sites sans GitHub, puis le reste.

## Ce qui reste à faire ensuite

- Validation par le porteur du projet : premier écran, messages clairs, sites avec base de données, parcours « Nouveau site ».
- **Remettre le dépôt en privé vers le 10 janvier 2027** (sur github.com : réglages du dépôt, « Danger Zone », « Change visibility »).
- Mise en ligne sur Laravel Cloud (solution A, guidage une étape à la fois depuis le téléphone, `docs/mise-en-ligne.md`).
- Puis l'application GitHub (accès limité aux dépôts choisis) et les deux façons de travailler avec GitHub (« dépôt d'abord » et « site d'abord »), livrées ensemble ; puis les versions pour les sites sans GitHub.
- Avant le lancement public : relecture juridique.
- Plus tard : GitLab et Bitbucket, interface en anglais, clé Claude et pays européens, modèles payants pour les abonnés, achat de kekeli.dev.
