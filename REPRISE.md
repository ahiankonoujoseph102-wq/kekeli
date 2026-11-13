# Reprise du travail sur Kekeli

Ce fichier sert à reprendre le travail sans rien perdre, par exemple depuis une nouvelle séance ou un autre compte. Il est mis à jour à la fin de chaque chantier. Les règles complètes et toutes les décisions sont dans [`CLAUDE.md`](CLAUDE.md) (il fait foi) ; le plan détaillé est dans [`docs/plan.md`](docs/plan.md).

Dernière mise à jour : 13 novembre 2026 (pull request « Versions et retour en arrière »).

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
| `TAVILY_API_KEY` | Runner | Recherche sur Internet, en principal (offre gratuite, environ 1 000 recherches par mois) |
| `EXA_API_KEY` | Runner | Recherche sur Internet, en secours (offre gratuite) |
| `OPENAI_API_KEY` | Runner seulement (jamais dans l'ordinateur virtuel) | Réponses aux questions (GPT-6.1 Terra et GPT-6 Luna), dictée vocale et construction (GPT-6.1 Sol et Terra, par le relais), clé payante avec plafond de 1 $ par jour |
| `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID`, `GITHUB_CLIENT_SECRET` | Site seulement | GitHub App de Kekeli (nom court, identifiant public et secret) ; à créer par le porteur du projet avec `docs/github-app.md` ; absentes le 12 novembre 2026 |
| `KEKELI_RUNNER_SECRET` | Site et Runner | Signature des échanges entre le site et le Runner |
| `LARAVEL_CLOUD_API_TOKEN` | Site (lecture seule) | Lecture quotidienne du coût d'hébergement |

Le 10 novembre 2026 : `GEMINI_API_KEY`, `E2B_API_KEY`, `CLOUDFLARE_ACCOUNT_ID` et `CLOUDFLARE_API_TOKEN` sont placées dans l'environnement de Claude ; `OPENROUTER_API_KEY` et `GROQ_API_KEY` ont été placées ensuite ; `PEXELS_API_KEY` ne l'est pas (facultative), `MISTRAL_API_KEY` est inutile. Le 12 novembre 2026 : `TAVILY_API_KEY` et `EXA_API_KEY` sont présentes.

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

## Kekeli, agent général (étude du 12 novembre 2026, `docs/plan.md`, partie 26)

- Objectif : un agent général (questions avec recherche, sites web, applications, automatisations, agents IA, documents, présentations, affiches, prompts, prospects, connecteurs), avec un design au niveau des meilleurs outils.
- Trois niveaux de travail : répondre (une requête), chercher (boucle dans le Runner, sans ordinateur), fabriquer (OpenCode dans E2B avec une boîte à outils par capacité). Un aiguilleur d'intention étend `MessageIntent`.
- **Ordre des pull requests** : 1. nouveau design ; 2. une seule zone pour tout ; 3. documents ; 4. présentations ; 5. affiches et images ; 6. recherche avec sources ; 7. mise en ligne Laravel Cloud ; 8. application GitHub (deux parcours) ; 9. automatisations ; 10. versions, puis applications et logiciels ; 11. prospects ; 12. agents IA ; 13. connecteurs.
- **Arrêts obligatoires, accord du porteur du projet à demander** : compte gratuit de recherche sur Internet (pull request 6) ; coûts de la mise en ligne (7) ; projet Google Cloud et compte Meta (13) ; tout modèle payant.
- Formules : aucun changement pour le moment ; à revoir avec la clé Claude.
- **Nouveau design fait** (captures : `docs/captures/design.md`). **Le porteur du projet veut le valider avant la suite** : ne pas commencer la pull request 2 « Une seule zone pour tout » avant son accord.
- **Design validé le 12 novembre 2026 (pull request 34)**, puis **finitions** : message final de Kekeli en paragraphes et listes (affichage sûr, `RunReport::text` et `RunReport::blocks`), consignes du message final sans jargon pour un commerçant (`consignesMessageFinal` et `JARGON_INTERDIT` dans `runner/src/ordinateurs.js`), bloc « Compte administrateur d'essai » au style du design. Finitions validées.
- **Pull request 2 « Une seule zone pour tout » construite** (`docs/plan.md`, partie 26.11 ; captures : `docs/captures/une-seule-zone.md`) : premier répondant (le modèle décide avec un outil par lieu), discussions, `MessageIntent` supprimé ; mesure réelle 33 sur 33. Validée (pull request 36).
- **Pull request « Navigation professionnelle » construite** (`docs/plan.md`, partie 26.12 ; captures : `docs/captures/navigation.md`) : barre commune, recherche, Récents filtrés, menu « + », apparence Système / Clair / Sombre gardée pour le compte, réglages par onglets, nombres en chiffres (mesure 35 sur 36). Validée (pull request 37).
- **Pull request « Recherche sur Internet avec sources » construite** (`docs/plan.md`, partie 26.13 ; captures : `docs/captures/recherche.md`) : outil `rechercher_web` (Tavily puis Exa gratuits), sources, quotas par formule et par mois, coût dans l'énergie, trois finitions de la navigation ; mesure 12 sur 13 avec recherche, 36 sur 36 sans ; 26 crédits Tavily consommés. **Ne pas commencer la suite (documents) : le porteur du projet veut d'abord valider.**
- Essai local du premier répondant sans ordinateur : un petit Runner qui crée le serveur sans gestionnaire d'ordinateurs (le vrai Runner contacte E2B au démarrage) ; la mesure se refait avec `node runner/scripts/mesurer-repondant.mjs`.
- Règle de travail demandée le 12 novembre 2026 : un commit et un push après chaque étape importante, pour ne jamais perdre de travail.

## Finitions professionnelles de la discussion et de la recherche (12 novembre 2026)

- **Construit et testé** (`docs/plan.md`, partie 26.14 ; captures avant et après : `docs/captures/finitions-pro.md`) : dates et sources fiables dans les réponses avec recherche, pastilles de renvoi cliquables, « A cherché sur Internet · N sources », mots « toujours », « encore », « cette année » qui déclenchent une recherche, limite de recherche jamais mentionnée (recherches du jour dans le menu du compte), vrais titres donnés par le modèle, « Copier » et « Régénérer », bouton « Oui, créer le site », bouton « Arrêter » (il n'existait pas), menu refermé par « Rechercher » sur téléphone, corrections de la revue générale.
- **Mesure** : 16 sur 16 avec recherche, 36 sur 36 sans, 15 recherches Tavily. Elle a surtout tourné sur Gemini 3.1 Flash-Lite (quota du jour de Gemini 3.5 Flash-Lite épuisé) : **à refaire sur Gemini 3.5 Flash-Lite** (`node runner/scripts/mesurer-repondant.mjs`, puis `--recherche` avec `RECHERCHES_BUDGET`).
- Pour refaire les captures : `KEKELI_CAPTURES=apres php artisan tinker --execute="require 'scripts/preparer-captures-finitions-pro.php';"`, `php artisan serve --port=8123`, puis `node scripts/captures-finitions-pro.js apres http://127.0.0.1:8123 docs/captures/finitions-pro`.
- **Ne pas commencer la suite (documents) : le porteur du projet veut d'abord valider.**

## Qualité des réponses comme Claude (12 novembre 2026)

- **Construit et testé** (`docs/plan.md`, partie 26.15 ; captures avant et après : `docs/captures/qualite-reponses.md`) : consigne de style « comme Claude », Markdown affiché de façon sûre (tableaux défilants, code avec « Copier »), modèle de réponse par formule (GPT-6.1 Terra pour les payants, GPT-6 Luna pour la Gratuite, fournisseurs gratuits en secours), plafond de dépense OpenAI par jour (1 $), réflexion visible « Réflexion · 6 s », dictée vocale (navigateur d'abord, sinon gpt-transcribe), mots alignés sur Claude (« A cherché sur le Web »).
- `OPENAI_API_KEY` présente le 12 novembre 2026 ; essais OpenAI : environ 0,06 $ (estimation, plafond 0,50 $).
- Comparaison à refaire au besoin : `node runner/scripts/comparer-modeles.mjs openai/gpt-6.1-terra gemini/gemini-3.6-flash` (variable `QUESTIONS` pour quelques questions, `BUDGET_OPENAI` pour le plafond).
- Pour refaire les captures : `php artisan tinker --execute="require 'scripts/preparer-captures-qualite.php';"`, `php artisan serve --port=8123`, puis `node scripts/captures-qualite.js apres http://127.0.0.1:8123 docs/captures/qualite-reponses`.
- **Ne pas commencer la suite (documents) avant la validation du porteur du projet.**

## Création d'applications comme Claude Code (12 novembre 2026)

- **Pull request 40 validée.** **Nouvel ordre du plan** (`docs/plan.md`, partie 26.9) : 1. applications, 2. agents IA, 3. automatisations, 4. documents, présentations et images, puis le reste (mise en ligne, application GitHub, connecteurs…).
- **Construit et testé, essai réel de bout en bout** (`docs/plan.md`, partie 27 ; captures : `docs/captures/applications.md`) : outil « creer_application » et mode plan (« Construire ce plan »), technologie imposée respectée (Laravel par défaut), applications installables avec LISEZ-MOI, actions détaillées comme Claude Code, questions sur le code (« lire_fichier »), Variables secrètes chiffrées, clé collée masquée, consignes de sécurité.
- **Modèle de construction** (banc d'essai réel dans E2B, même application de salon) : GPT-6.1 Sol pour Pro, Expert, Agence (36 tests, 7,7 minutes, environ 276 FCFA) ; GPT-6.1 Terra pour la Gratuite (24 tests, 5 minutes, environ 66 FCFA) ; Gemini 3.5 Flash-Lite inachevé (limite par minute), Gemini 3.6 Flash bloqué par sa limite du jour. Crédit OpenAI des essais : environ 0,71 $ (plafond 2 $).
- **OpenAI jamais dans l'ordinateur E2B** : relais sans clé dans l'ordinateur, suivi par le Runner qui ajoute la clé (fonctionne sans adresse publique du Runner).
- **À décider par le porteur du projet** : relever le plafond OpenAI du jour (1 $ couvre environ 2 applications avec Sol) ; énergie de la formule Gratuite (30 FCFA par jour, une application Terra en demande environ 66).
- Pour refaire l'essai réel : site, `php artisan queue:work` et Runner (`cd runner && npm start`) avec le même `KEKELI_RUNNER_SECRET`, puis `php artisan tinker --execute="require 'scripts/preparer-essai-application.php';"`, `node scripts/essai-application.js docs/captures/applications`, `node scripts/captures-applications.js`.
- **Ne pas commencer les agents IA avant la validation du porteur du projet.**

## Agents IA, comme Claude Code (12 novembre 2026)

- **Pull request 41 (applications) validée.** Cette pull request commence par un commit séparé de finitions (« dossier du projet », commandes internes cachées, construction neuve, icône « Récupérer le code » sur téléphone, titre dès le plan, restes du point de départ retirés) et la règle d'énergie comme Claude Code (une ligne sobre « Énergie épuisée · Recharge à 00 h 00 », arrêt d'une construction au plafond OpenAI du jour au lieu de passer à Gemini).
- **Construit et testé** (`docs/plan.md`, partie 28 ; captures : `docs/captures/agents.md`) : outil « creer_agent » et mode plan, LangGraph en JavaScript par défaut ou CrewAI, technologie imposée respectée, documents, outils, mémoire, page de discussion et bulle, LISEZ-MOI, sécurité vérifiée, panneau « Tester l'agent » avec essai gratuit par le relais du Runner (clé de Kekeli jamais dans l'ordinateur E2B).
- Licences vérifiées : LangGraph et CrewAI sous licence MIT ; Dify (Apache 2.0 modifiée) seulement comme inspiration, aucun code repris.
- Pour refaire l'essai réel : `APP_URL=http://127.0.0.1:8123` dans `.env` (sinon le Runner ne joint pas le site), site, `php artisan queue:work` et Runner (`cd runner && npm start`) avec le même `KEKELI_RUNNER_SECRET`, puis `php artisan tinker --execute="require 'scripts/preparer-essai-agent.php';"` et `node scripts/essai-agent.js docs/captures/agents`.
- **À signaler** : une dizaine d'ordinateurs E2B en pause datant du 10 novembre 2026 (essais d'anciennes séances) existent encore sur le compte E2B ; ils n'ont pas été effacés sans l'accord du porteur du projet.
- **Pull request 42 (agents IA) validée.**

## GitHub et automatisations, comme Claude Code (12 novembre 2026)

- **Construit et testé** (`docs/plan.md`, partie 29 ; captures : `docs/captures/github-automatisations.md`) : finitions (bulle de « Tester l'agent », une seule ligne d'état, fournisseurs de Kekeli cachés au client, choix guidé du fournisseur de la clé du client, phrase de la politique de confidentialité à faire relire par un juriste) ; GitHub App (dépôts choisis par le client, dépôt d'abord, projet d'abord avec dépôt privé, envoi après chaque travail en pull request ou sur la branche principale, rien de secret envoyé, secrets du dépôt chiffrés, pastille GitHub, Réglages → Connecteurs) ; automatisations (outil « creer_automatisation », mode plan, JavaScript et GitHub Actions planifié, « Lancer maintenant » avec journal lisible).
- **Essai réel de l'automatisation fait** (E2B, GPT-6.1 Sol) : 2 minutes 31, 17 tests, environ 66 FCFA ; crédit OpenAI de la pull request environ 0,11 $.
- **Pull request 43 fusionnée le 12 novembre 2026** avec l'accord du porteur du projet. GitHub n'a été testé qu'avec un GitHub simulé : la GitHub App « Kekeli-Lome » existe et ses trois variables sont dans l'environnement, mais le réseau de l'environnement de Claude bloque la connexion à GitHub (voir « Mise en ligne : à faire avec la GitHub App Kekeli-Lome »). Ancienne consigne, gardée pour l'essai depuis le site en ligne : Quand le porteur du projet écrit « c'est fait » (GitHub App créée avec `docs/github-app.md`, variables placées, application installée) : lancer `php artisan kekeli:github-appareil kofi-automatisation@exemple.tg` (ou un autre compte d'essai) et lui donner le code court à taper sur github.com/login/device ; puis « Créer un dépôt privé » sur le projet d'essai, vérifier la pull request, placer les Variables secrètes, et lancer l'automatisation depuis GitHub Actions (`GithubClient::dispatchWorkflow`, fichier `automatisation.yml`) ; fusionner quand la vérification de GitHub est verte, puis s'arrêter.
- Pour refaire l'essai réel de l'automatisation : `php artisan tinker --execute="require 'scripts/preparer-essai-automatisation.php';"`, puis `node scripts/essai-automatisation.js docs/captures/github-automatisations` ; captures : `scripts/preparer-captures-github.php` puis `scripts/captures-github-automatisations.js` (site lancé avec des valeurs inventées de `GITHUB_APP_SLUG`, `GITHUB_CLIENT_ID` et `GITHUB_CLIENT_SECRET`).

## Ordre de la suite (décidé le 11 novembre 2026, `docs/plan.md`, partie 23.11)

1. Le nettoyage professionnel (fait).
2. **La mise en ligne de Kekeli sur Laravel Cloud** (prochaine étape, `docs/mise-en-ligne.md`).
3. L'application GitHub, puis les **deux façons de travailler avec GitHub, aussi importantes l'une que l'autre et livrées ensemble** : **dépôt d'abord** (le client crée son dépôt sur GitHub, le relie à Kekeli, et Kekeli pousse chaque changement dessus au fur et à mesure) et **site d'abord** (le client crée son site dans Kekeli, le relie à GitHub quand il veut, puis Kekeli pousse chaque changement dessus).
4. Ensuite seulement : les versions et le retour en arrière pour les sites sans GitHub, puis le reste.

## Documents et présentations (12 novembre 2026)

- **Pull requests 43 et 44 validées.** Décision : on continue les fonctions avant la mise en ligne ; ordre : 1. documents et présentations, 2. images et affiches.
- **Construit, testé et mesuré pour de vrai** (`docs/plan.md`, partie 30 ; captures et fichiers produits : `docs/captures/documents.md`) : vrais fichiers Word, PowerPoint et PDF, aperçu des pages, versions, « Télécharger », fichiers joints corrigés dans leur format, plan pour les gros documents ; rédaction par le modèle de construction en JSON depuis le Runner, mise en page dans un ordinateur E2B isolé sans clé ni Internet.
- Mesure : CV, lettre, devis, rapport de 6 pages, présentation de 8 diapositives, correction d'un Word joint et une modification ; environ 11 FCFA par document et 9 FCFA par présentation (estimation) ; crédit OpenAI de la pull request environ 0,21 $.
- Pour refaire la mesure : `php artisan tinker --execute="require 'scripts/preparer-essai-documents.php';"`, puis `node scripts/essai-documents.js docs/captures/documents` (ou `SEULEMENT=rapport,presentation …`), puis `node scripts/captures-documents.js http://127.0.0.1:8123 docs/captures/documents`. Après un changement du site, relancer `php artisan queue:work` (il garde l'ancien code en mémoire).
- **Pull request 45 validée.**

## Images et affiches (13 novembre 2026)

- Commit séparé de six finitions des documents (carte du fichier, « Récents » avec une icône par type, plus de « Brouillon », un seul « Télécharger », ligne « A créé le document · Word, PDF », majuscules) ; captures avant et après dans `docs/captures/images.md`.
- **Construit, testé et mesuré pour de vrai** (`docs/plan.md`, partie 31) : outil « creer_image », PNG et PDF, formats selon l'usage, versions, logo ou photo joints posés tels quels, refus des images interdites. Formules payantes : GPT Image 2.6 « flare » dessine l'affiche, puis chaque texte est relu ; Gratuite : FLUX.1 schnell (Cloudflare) pour le fond, texte posé par le code.
- Coût mesuré (estimation) : 30 à 41 FCFA par affiche en payant, 3 à 4 FCFA en Gratuite ; crédit OpenAI de la pull request environ 0,89 $ (plafond 1 $).
- Pour refaire l'essai : `php artisan tinker --execute="require 'scripts/preparer-essai-images.php';"`, puis `COMPTE=pro node scripts/essai-images.js docs/captures/images/essai` (et `COMPTE=gratuite`), puis `node scripts/captures-images.js http://127.0.0.1:8123 docs/captures/images/ecrans`. Essai comparatif : `cd runner && node scripts/comparer-images.mjs ../docs/captures/images/comparaison` (coûte environ 0,40 $ chez OpenAI).

## Connecteurs (MCP) (13 novembre 2026)

- **Pull request 46 (images et affiches) validée.**
- **Finition (commit séparé)** : une modification du texte d'une affiche garde l'image et Kekeli repose lui-même le texte ; essai réel Gratuite et Pro, captures avant et après dans `docs/captures/connecteurs.md`.
- **Étude** (`docs/plan.md`, partie 26.16, sources officielles et métadonnées des serveurs vérifiées) : catégorie (1) sans compte ni dépense (Notion, Airtable, Jira et Confluence, Linear, Miro, Sentry ; Hugging Face et DeepWiki sans connexion) ; (2) application développeur gratuite à créer par le porteur du projet (HubSpot, Asana, Dropbox, GitHub ; gestes en 26.16.2) ; (3) payants ou validation longue (Google Workspace, Microsoft 365, Slack, Canva, Figma, Square, Stripe, Box).
- **Construit et testé (catégorie 1 seulement)** : Réglages → Connecteurs, connecteur personnalisé, OAuth par le Runner (kit officiel `@modelcontextprotocol/client` 2.4.0), jetons chiffrés, « + » → « Connecteurs », lecture libre et accord avant toute modification, « Toujours autoriser », journal des actions, Gratuite limitée à 2 connecteurs actifs, jamais d'offre gratuite de modèle avec un connecteur.
- **Essai réel** avec DeepWiki et Hugging Face (sans compte) et un faux serveur local pour l'accord : tout fonctionne, consigne cachée ignorée. Crédit OpenAI de la pull request : environ 0,11 $ (plafond 1 $).
- **À faire à la mise en ligne** : l'essai OAuth réel avec Notion et les autres (`docs/mise-en-ligne.md`, partie 7). Depuis l'environnement de Claude, l'inscription de Kekeli chez Notion a été refusée par la protection de l'environnement.
- Pour refaire l'essai : `node runner/scripts/essai-connecteurs.mjs gpt-6.1-terra docs/captures/connecteurs/essai` ; captures : `php artisan tinker --execute="require 'scripts/preparer-captures-connecteurs.php';"`, site et Runner lancés avec le même `KEKELI_RUNNER_SECRET` (32 caractères au moins), puis `node scripts/captures-connecteurs.js http://127.0.0.1:8123 docs/captures/connecteurs/ecrans`.

## Versions et retour en arrière (13 novembre 2026)

- **Pull request 47 (connecteurs) validée.** **Nouvel ordre** (`docs/plan.md`, partie 26.9) : 1. versions (cette pull request), 2. **étude des coûts de mise en ligne : ne pas la commencer sans l'accord du porteur du projet**, 3. mise en ligne, 4. prospects après le lancement.
- **Construit, testé et essayé pour de vrai** (`docs/plan.md`, partie 26.17 ; captures : `docs/captures/versions.md`) : une version par travail réussi (titre donné par le premier répondant), « Versions », voir une ancienne version dans l'aperçu avec un bandeau, restaurer (nouvelle version, rien d'effacé), retour à la dernière version qui fonctionnait après un travail échoué ou arrêté, GitHub en nouveau commit sans envoi forcé, limite de 30 versions en Gratuite et 200 en payant.
- **Méthode** : une archive figée et nettoyée par version, rangée par le site (jamais `.env`, base d'essai, dépendances ni clé) ; le Runner remet les fichiers dans l'ordinateur en gardant `.env`, la base d'essai, les dépendances et les fichiers ignorés par Git.
- Essai réel : site vitrine et application Laravel, tout juste après correction de deux défauts trouvés avec l'application. Crédit OpenAI de la pull request : environ 0,09 $.
- Pour refaire l'essai : site, `php artisan queue:work` et Runner avec le même `KEKELI_RUNNER_SECRET` (32 caractères au moins), puis `ESSAI=site php artisan tinker --execute="require 'scripts/essai-versions.php';"` (et `ESSAI=application`, `ESSAI=application-suite`, `ESSAI=arret`) ; captures : `node scripts/captures-versions.js http://127.0.0.1:8123 docs/captures/versions "Maquis Chez Ama"` (et `GENRE=webapp`).

## Mise en ligne : à faire avec la GitHub App Kekeli-Lome

- Remplacer l'adresse de redirection (« Callback URL ») `http://127.0.0.1:8123/github/retour` par l'adresse réelle du site : `https://<adresse du site>/github/retour`.
- Passer l'installation de « Uniquement sur ce compte » à « N'importe quel compte » (« Any account »), pour que les clients puissent installer l'application.
- Faire l'essai réel complet : relier le compte, projet d'abord avec un dépôt privé, pousser les changements (pull request), automatisation lancée depuis GitHub Actions.
- Vérifier si la création d'un dépôt fonctionne quand l'installation est limitée à certains dépôts (aujourd'hui, l'installation de « Kekeli-Lome » ne voit que `ahiankonoujoseph102-wq/lang`).

Détails : `docs/plan.md`, partie 29.6.

## Ce qui reste à faire ensuite

- Validation par le porteur du projet : premier écran, messages clairs, sites avec base de données, parcours « Nouveau site ».
- **Remettre le dépôt en privé vers le 10 janvier 2027** (sur github.com : réglages du dépôt, « Danger Zone », « Change visibility »).
- Mise en ligne sur Laravel Cloud (solution A, guidage une étape à la fois depuis le téléphone, `docs/mise-en-ligne.md`).
- Validation des versions par le porteur du projet ; puis, **seulement avec son accord**, l'étude des coûts de mise en ligne ; essais réels de la GitHub App et des connecteurs OAuth à la mise en ligne.
- Avant le lancement public : relecture juridique.
- Plus tard : GitLab et Bitbucket, interface en anglais, clé Claude et pays européens, modèles payants pour les abonnés, achat de kekeli.dev.
