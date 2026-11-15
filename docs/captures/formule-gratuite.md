# Une formule gratuite généreuse et rapide, comme Lovable (15 novembre 2026)

Décision du porteur du projet du 15 novembre 2026 (détails : `docs/plan.md`, partie 26.32 ; coûts : `docs/couts.md`). Comptes et messages **inventés** (`scripts/preparer-captures-formule-gratuite.php`), captures prises avec `scripts/captures-formule-gratuite.js` sur un téléphone simulé (Pixel 7, navigateur en français) et sur un ordinateur, en clair et en sombre.

## Ce qui change pour le client

1. **Une discussion simple ne consomme plus d'énergie** : salutations, questions, conseils, explications, et la question de confirmation avant une création. Dès qu'une création, une modification, une recherche sur Internet ou un connecteur est utilisé, l'énergie est comptée comme avant.
2. **Garde-fou** : 100 discussions simples par jour en Gratuite, 300 en Pro, 600 en Expert, 1 000 en Agence (heure de Lomé), réglables dans Administration → Réglages. Au-delà : « Vous avez utilisé toutes vos discussions du jour. Elles reviennent à 00 h 00. Vos créations restent possibles avec votre énergie. », avec « Voir les formules » pour la Gratuite ; la discussion compte alors de nouveau dans l'énergie.
3. **Réponses rapides pour tous** : les réponses de toutes les formules prennent toujours GPT-6.1 Terra puis GPT-6 Luna, même quand il reste peu d'énergie ; le modèle gratuit de Google (10,2 secondes avant son premier mot) passe en tout dernier secours.
4. **Formule Gratuite : 100 FCFA d'énergie par jour** (30 avant), toujours rechargée à minuit, heure de Lomé ; 3 recherches sur Internet par jour, comme avant.
5. **Sites vitrines construits d'abord par GPT-6.1 Terra** pour toutes les formules (choix du porteur du projet du 15 novembre 2026), pour qu'un site complet tienne dans une journée gratuite.
6. **Menu du compte** : la barre d'énergie ne bouge pas après une discussion simple ; une ligne discrète « Discussions du jour : N restantes » sous « Recherches du jour ».

## Menu du compte après trois discussions simples (Kossi, formule Gratuite)

Avant : les trois réponses ont pris 9 % de l'énergie. Après : 100 %, et « Discussions du jour : 97 restantes ».

| | Avant | Après |
|---|---|---|
| Téléphone, clair | ![](formule-gratuite/avant/menu-telephone-clair.png) | ![](formule-gratuite/apres/menu-telephone-clair.png) |
| Téléphone, sombre | ![](formule-gratuite/avant/menu-telephone-sombre.png) | ![](formule-gratuite/apres/menu-telephone-sombre.png) |
| Ordinateur, clair | ![](formule-gratuite/avant/menu-ordinateur-clair.png) | ![](formule-gratuite/apres/menu-ordinateur-clair.png) |
| Ordinateur, sombre | ![](formule-gratuite/avant/menu-ordinateur-sombre.png) | ![](formule-gratuite/apres/menu-ordinateur-sombre.png) |

## Page des formules

Après : « De quoi créer un site vitrine complet chaque jour » et « Jusqu'à 100 discussions par jour, sans toucher à votre énergie » pour la Gratuite ; pour Pro, « L'énergie du mois entier, à utiliser quand vous voulez » remplace « 4 fois plus d'énergie que la formule Gratuite », qui ne serait plus vrai (voir la décision à prendre dans `docs/couts.md`).

| | Avant | Après |
|---|---|---|
| Téléphone, clair | ![](formule-gratuite/avant/formules-telephone-clair.png) | ![](formule-gratuite/apres/formules-telephone-clair.png) |
| Téléphone, sombre | ![](formule-gratuite/avant/formules-telephone-sombre.png) | ![](formule-gratuite/apres/formules-telephone-sombre.png) |
| Ordinateur, clair | ![](formule-gratuite/avant/formules-ordinateur-clair.png) | ![](formule-gratuite/apres/formules-ordinateur-clair.png) |
| Ordinateur, sombre | ![](formule-gratuite/avant/formules-ordinateur-sombre.png) | ![](formule-gratuite/apres/formules-ordinateur-sombre.png) |

## Toutes les discussions du jour utilisées (Ama, formule Gratuite)

| Téléphone, clair | Téléphone, sombre |
|---|---|
| ![](formule-gratuite/apres/limite-telephone-clair.png) | ![](formule-gratuite/apres/limite-telephone-sombre.png) |

| Ordinateur, clair | Ordinateur, sombre |
|---|---|
| ![](formule-gratuite/apres/limite-ordinateur-clair.png) | ![](formule-gratuite/apres/limite-ordinateur-sombre.png) |

## Administration → Réglages : « Discussions simples par jour »

| Téléphone, clair | Téléphone, sombre |
|---|---|
| ![](formule-gratuite/apres/administration-telephone-clair.png) | ![](formule-gratuite/apres/administration-telephone-sombre.png) |

| Ordinateur, clair | Ordinateur, sombre |
|---|---|
| ![](formule-gratuite/apres/administration-ordinateur-clair.png) | ![](formule-gratuite/apres/administration-ordinateur-sombre.png) |

## Mesures

### Premier mot pour un compte de formule Gratuite

**La mesure réelle (« Salut » et une question courte, 3 essais chacun) n'a pas pu être faite dans l'environnement de travail de Claude le 15 novembre 2026 : aucune clé (Coarena, OpenAI, Gemini) n'y était placée.** À faire en ligne après le déploiement : écrire « Salut » puis « Quelle est la capitale du Togo ? » trois fois chacun avec un compte gratuit, puis lire Administration → Consommation, « Vitesse des réponses » ; ou, avec les clés, `node runner/scripts/mesurer-premier-mot.mjs`.

Ce qui est vérifié (par les tests et sur les deux versions du code, avec les temps mesurés le 15 novembre 2026, `runner/mesures/premier-mot.json`) : la liste des modèles envoyée au Runner pour un compte gratuit à qui il reste moins de 15 % d'énergie.

| | Avant | Après |
|---|---|---|
| Premier modèle | GPT-6 Luna chez Coarena (2,3 s au premier mot) | GPT-6.1 Terra chez Coarena (1,2 s) |
| Puis | GLM 5.3 Flash (échoue chez Coarena), GPT-6 Luna d'OpenAI (1,3 s), **Gemini 3.5 Flash-Lite (10,2 s)**, Gemini 3.1 Flash-Lite, OpenRouter, Workers AI | GPT-6 Luna chez Coarena, GPT-6 Luna d'OpenAI, OpenRouter, Workers AI, puis **Gemini en tout dernier** |
| Compte gratuit avec assez d'énergie | GPT-6.1 Terra, GPT-6 Luna, OpenAI, Gemini, puis les autres | GPT-6.1 Terra, GPT-6 Luna, OpenAI, les autres, Gemini en dernier |

Plafond OpenAI du jour : inchangé (1 $), vérifié par un test (OpenAI n'est plus proposé une fois le plafond atteint).

### Poids et Lighthouse (téléphone, médiane de 3)

Script : `scripts/lighthouse-vitesse.js`, même machine, même base inventée.

| Mesure | Avant | Après |
|---|---|---|
| CSS (compressé) | 42,0 Ko | 42,0 Ko (même fichier) |
| JavaScript (compressé) | 4,2 Ko | 4,2 Ko (même fichier) |
| Accueil, performance | 59 | 64 |
| Discussion, performance | 58 | 64 |
| Accessibilité | 100 | 100 |

Les écarts de performance restent dans la variation habituelle de la mesure (le code envoyé au navigateur est identique).
