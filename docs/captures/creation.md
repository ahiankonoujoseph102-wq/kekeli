# Créer comme Lovable et Claude (15 novembre 2026)

Mesures réelles et captures du 15 novembre 2026, sur un téléphone simulé (Pixel 7, navigateur en français) et sur un ordinateur (1 280 × 800), en mode clair et en mode sombre. Les sites ont été créés **pour de vrai** (site local, Runner avec un ordinateur E2B, modèle de construction de la production : GPT-6.1 Terra chez Coarena) avec des **comptes de formule Gratuite inventés**, un nouveau compte par site (bonus de bienvenue de 500 FCFA). Scripts : `scripts/mesurer-creation.js` (mesure), `scripts/relire-mesures-creation.php` (temps relus en base), `scripts/preparer-captures-creation.php` puis `scripts/captures-creation.js` (captures). Détails : `docs/plan.md`, partie 26.33 bis.

## Les 5 sites vitrines réels

Temps en secondes depuis l'envoi de la demande (questions comprises, réponses données tout de suite) ; « première page » : la première page écrite dans le projet (l'aperçu montre le site). Coût réel au prix officiel des modèles (estimation de l'énergie comptée ; Coarena offre 5 $ par jour et par compte, le vrai coût payé est donc surtout l'ordinateur).

| Site | Demande | Questions | Début du travail | Première page | Fin | Coût (modèle + ordinateur) |
|---|---|---|---|---|---|---|
| Formations en IA | « pouvez-vous me créer un site web pour vendre mes formations en intelligence artificielle » | oui, « Choisis pour moi » | 22 s | 74 s | 339 s | **61 FCFA** (52,4 + 8,6) |
| Restaurant | « fais-moi un site pour mon restaurant » | oui, réponses écrites | 35 s | 77 s | 304 s | **48 FCFA** (40,9 + 7,3) |
| Boutique de pagnes | demande détaillée (prix, Mixx by Yas, Flooz, WhatsApp, couleurs) | non | 11 s | 112 s | 274 s | **52 FCFA** |
| Salon de coiffure | « je veux un site pour mon salon de coiffure » | oui, « Choisis pour moi » | 20 s | **34 s** | 230 s | **52 FCFA** |
| Pressing | demande détaillée (prix, horaires, livraison, WhatsApp, couleurs) | non | 7 s | 37 s | 246 s | **52 FCFA** |

- **Chaque site tient très largement dans le bonus de 500 FCFA** (environ 8 à 10 sites), et même dans les 100 FCFA d'une journée : **53 FCFA en moyenne** (48 à 61). L'estimation de 75 FCFA est remplacée par **60 FCFA** (`free_site_estimate_fcfa`) ; la carte Gratuite affiche « De quoi créer un site vitrine complet chaque jour » et « Un bonus de bienvenue de 500 FCFA d'énergie, une seule fois ».
- Chaque site a un vrai compte rendu, le bon nom (après une correction : « Restaurant Quel » et « Salon de coiffure Choisis » venaient des réponses ; le nom donné par le modèle passe maintenant en premier) et 2 ou 3 boutons de suite.

**Avant** (même demande que « Formations en IA », code de `main`) : début 10 s, première page 48 s, fin 175 s, **32,5 FCFA** (un premier essai identique : environ 60 FCFA) ; mais aucune question, un site nommé « Mon site web », 4 requêtes au modèle seulement et un dernier message réduit à une phrase de travail (« La création d'image est indisponible ; je réalise un visuel original directement dans la page. »), sans compte rendu ni suite. Les sites d'après sont plus complets (12 à 17 requêtes) : ils prennent plus de temps (230 à 339 s au lieu de 175 s) et coûtent un peu plus.

**Vitesse** :
- Démarrage anticipé de l'ordinateur pendant les questions : la première page arrive **14 s après le début du travail** pour le salon de coiffure (38 s avant). Sans questions, rien ne change (30 à 101 s selon que l'agent cherche d'abord les images ou non).
- Une consigne « première page d'accueil courte d'abord » a été **essayée puis annulée** : même demande du pressing, première page 34 s après le début (30 s sans elle) et fin à 294 s (246 s sans elle) : aucun gain.
- **À vérifier en ligne** : dans cet environnement de travail, la création d'images échoue dans l'ordinateur (le service gratuit répond bien hors de l'ordinateur) ; l'agent perd environ 40 s puis dessine une illustration. Les images libres (Pixabay, Openverse) ont parfois été trouvées.

**Lighthouse téléphone** (médiane de 3) : accueil 59 (59 avant), discussion d'un vrai site 57 (56 à 66 lors des mesures précédentes, écart de la mesure) ; **accessibilité 100** partout. **Poids** : CSS 352,26 ko (42,0 ko compressé ; 352,05 ko avant), JavaScript 11,78 ko (4,3 ko compressé ; 11,39 ko avant). **Plafond OpenAI inchangé** (1 $ par jour).

## 1. Les questions avant une création, avec leurs choix

Demande vague : au plus 3 questions courtes, des choix à toucher (un choix touché devient foncé), « Envoyer mes réponses » et « Choisis pour moi ». Ces questions ne coûtent pas d'énergie. Mesure réelle : **12 bonnes décisions sur 12** (questions pour les demandes vagues, création directe pour les demandes détaillées), `runner/mesures/questions-apres.json`.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](creation/1-questions-telephone-clair.png) | ![Téléphone, sombre](creation/1-questions-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](creation/1-questions-ordinateur-clair.png) | ![Ordinateur, sombre](creation/1-questions-ordinateur-sombre.png) |

## 2. Les étapes visibles pendant le travail

Comme Claude Code : la ligne d'état dit ce que fait Kekeli, et les étapes restent dépliées une par une (données inventées).

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](creation/2-etapes-telephone-clair.png) | ![Téléphone, sombre](creation/2-etapes-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](creation/2-etapes-ordinateur-clair.png) | ![Ordinateur, sombre](creation/2-etapes-ordinateur-sombre.png) |

## 3. Le compte rendu final et les boutons de suite (vrai site du pressing)

Ce qui est prêt, ce que trouvent les clients, « J'ai choisi… », puis 2 ou 3 boutons de suite proposés par Kekeli ; les étapes sont repliées (« Kekeli a fait 21 actions »).

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](creation/3-compte-rendu-telephone-clair.png) | ![Téléphone, sombre](creation/3-compte-rendu-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](creation/3-compte-rendu-ordinateur-clair.png) | ![Ordinateur, sombre](creation/3-compte-rendu-ordinateur-sombre.png) |

## 4. L'aperçu (vrai site du pressing)

Sur téléphone, l'onglet « Aperçu » s'ouvre tout seul à la fin d'une création.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](creation/4-apercu-telephone-clair.png) | ![Téléphone, sombre](creation/4-apercu-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](creation/4-apercu-ordinateur-clair.png) | ![Ordinateur, sombre](creation/4-apercu-ordinateur-sombre.png) |

## 5. Le bonus de bienvenue dans le menu du compte

Une ligne discrète, du même style que « Recherches du jour », tant qu'il reste du bonus.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](creation/5-bonus-telephone-clair.png) | ![Téléphone, sombre](creation/5-bonus-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](creation/5-bonus-ordinateur-clair.png) | ![Ordinateur, sombre](creation/5-bonus-ordinateur-sombre.png) |

## 6. Une création qui dépasse l'énergie et se termine proprement

Le travail n'est jamais coupé net : au-delà du dépassement permis (150 FCFA), Kekeli finit l'étape en cours, enregistre et écrit son compte rendu ; la demande suivante attend, avec la carte habituelle et « Voir les formules » (données inventées).

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](creation/6-depassement-telephone-clair.png) | ![Téléphone, sombre](creation/6-depassement-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](creation/6-depassement-ordinateur-clair.png) | ![Ordinateur, sombre](creation/6-depassement-ordinateur-sombre.png) |
