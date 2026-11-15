# Recherches et réponses aussi bonnes que Claude et ChatGPT (15 novembre 2026)

Mesures et captures du 15 novembre 2026. Les réponses montrées sont les **vraies réponses** du banc d'essai (recherches réelles avec Tavily, offre gratuite, et le modèle des réponses de la production), rangées dans un compte inventé pour les photographier (captures 2, 3 et 5 : les questions 1 et 3 reposées le même jour pour garder la liste de toutes les requêtes, `runner/mesures/banc-recherche-captures.json` ; `scripts/preparer-captures-recherche-claude.php`, puis `scripts/captures-recherche-claude.js`). Téléphone simulé (Pixel 7, navigateur en français) et ordinateur (1 280 × 800), en mode clair et en mode sombre. Les tests sur Joseph Ahiankonou, Talkami et Lomé IA School sont autorisés par le porteur du projet. Détails : `docs/plan.md`, partie 26.33.

## Le banc d'essai : avant et après

`node runner/scripts/banc-recherche.mjs <étiquette>` pose 12 questions réelles (`runner/mesures/questions-recherche.json`) et note, pour chacune, les sources montrées et celles qui parlent vraiment du sujet, la présence du bon site, la longueur de la réponse, les faits utiles présents, les requêtes envoyées aux fournisseurs de recherche et le temps total. Résultats : `runner/mesures/banc-recherche-avant.json` et `banc-recherche-apres.json`.

| | Avant | Après |
|---|---|---|
| Sources montrées | 21 | 17 |
| Sources sans rapport avec le sujet | **2** (« Saint-Joseph College of Lomé », « Talkio ») | **0** |
| Bon site trouvé (8 questions qui en attendent un) | 5 sur 8 | 5 sur 8 (Talkami et Lomé IA School sont maintenant répondus avec leurs fiches, sans recherche ; Togocom : togocom.tg absent des résultats) |
| Réponses utiles (les faits attendus sont présents) | 10 sur 12 | **12 sur 12** |
| Longueur moyenne | 51 mots | **73 mots** (personnes et entreprises : 81 à 106 mots) |
| Questions répondues sans chercher alors qu'il fallait chercher | 2 (Lomé IA School, Togocom) | 0 (Togocom cherché ; Lomé IA School et Talkami : fiches) |
| Requêtes envoyées aux fournisseurs | 12 | 26 (environ 2,6 par question qui cherche) |
| Temps moyen | 8,4 s | 8,8 s |

Détail par question (sources pertinentes sur sources montrées, mots, requêtes, secondes) :

| Question | Avant | Après |
|---|---|---|
| 1. Qui est Joseph Ahiankonou (« fais des recherches ») | 1 sur 2, talkami.net absent, 62 mots, 2 requêtes, 9,6 s | 2 sur 2, talkami.net et lomeiaschool.laravel.cloud, 81 mots, 3 requêtes, 8,5 s |
| 2. Qu'est-ce que Talkami ? | 1 sur 2 (Talkio sans rapport), 62 mots | fiche de Talkami, 92 mots, aucune requête |
| 3. Qui est Dr Claudel noubissi (nom mal écrit) | 3 sur 3, 84 mots, 1 requête | 2 sur 2, 106 mots (études, SOS Médecins Cameroun, StartUp Academy, livres), 3 requêtes |
| 4. Qu'est-ce que Lomé IA School ? | aucune recherche, 30 mots | fiche de Lomé IA School, 98 mots |
| 5. Qui est Akinwumi Adesina ? | 2 sur 2, 47 mots | 2 sur 2, 90 mots |
| 6. Que fait l'entreprise Togocom ? | aucune recherche, 27 mots | 3 sur 3, 93 mots (Yas Togo, Mixx by Yas, groupe AXIAN) |
| 7. Actualités de la BCEAO ce mois-ci | 2 sur 2, 104 mots | 1 sur 1, 95 mots |
| 8. Prix du sac de ciment à Lomé | 2 sur 2, 54 mots | 1 sur 1, 65 mots |
| 9. Taux du dollar en franc CFA | 2 sur 2, 44 mots, sans le taux | 1 sur 1, 36 mots, avec le taux |
| 10. Who is the current president of Ghana? | 2 sur 2, 29 mots | 2 sur 2, 31 mots |
| 11. Canva est-il toujours gratuit cette année ? | 2 sur 2, 42 mots | 2 sur 2, 65 mots |
| 12. Premier ministre du Sénégal | 2 sur 2, 29 mots | 1 sur 1, 25 mots |

Une question courte (un nom, un taux) garde une réponse courte, comme chez Claude ; une personne ou une entreprise reçoit une présentation complète.

**Le fondateur et ses sites** (`node runner/scripts/essai-fondateur.mjs`, `runner/mesures/fondateur.json`) : **5 sur 5** : « qui est ton fondateur » (27 mots, les deux liens), « je veux apprendre l'anglais » (conseil, puis Talkami et son lien), « je veux apprendre à créer des agents IA » (parcours, puis « Création d'agents IA » à 150 000 FCFA), « combien coûte Talkami » (2 000, 7 500 et 14 000 FCFA), « fais-moi une affiche pour ma boutique » (l'affiche est lancée, aucune recommandation). Aucun fournisseur d'IA nommé.

**Lighthouse téléphone** (médiane de 3, discussion avec recherche) : accessibilité **96 avant, 100 après** (le numéro et le site de chaque source étaient d'un gris trop clair, corrigé en `zinc-500`) ; performance 56 à 66 selon le passage (écart de la mesure ; accueil 59). **Poids** : CSS 352,0 ko (42,0 ko compressé), JavaScript 11,4 ko (4,2 ko compressé), inchangés. **Plafond OpenAI du jour inchangé** (1 $).

## 1. « Qui est Joseph Ahiankonou », avant

Une source sans rapport (« Saint-Joseph College of Lomé ») ajoutée pour arriver à deux sources, et une phrase des informations internes de Kekeli présentée à côté des sources.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/1-joseph-avant-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/1-joseph-avant-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/1-joseph-avant-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/1-joseph-avant-ordinateur-sombre.png) |

## 2. « Qui est Joseph Ahiankonou », après

Kekeli cherche le nom exact entre guillemets, ses mots et une variante en anglais, écarte les pages qui ne parlent pas de Joseph Ahiankonou et lit la page de Talkami. Ce qu'il sait déjà (Kekeli, Lomé IA School) n'a aucun numéro ; seules les phrases tirées de la page ont [1], et seule la page citée est montrée dans « Sources ».

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/2-joseph-apres-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/2-joseph-apres-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/2-joseph-apres-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/2-joseph-apres-ordinateur-sombre.png) |

## 3. Les recherches faites, dépliées

« A cherché sur le Web · 1 source » se déplie : les trois requêtes envoyées et les sites consultés, comme chez Claude.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/3-joseph-apres-recherches-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/3-joseph-apres-recherches-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/3-joseph-apres-recherches-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/3-joseph-apres-recherches-ordinateur-sombre.png) |

## 4. « Qui est Dr Claudel noubissi », avant

Nom mal écrit : la réponse avait 2 phrases.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/4-noubissie-avant-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/4-noubissie-avant-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/4-noubissie-avant-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/4-noubissie-avant-ordinateur-sombre.png) |

## 5. « Qui est Dr Claudel noubissi », après

L'orthographe est corrigée (« Noubissie »), Wikipédia et livres.propulsion.cm sont lus : l'essentiel d'abord, puis les études, SOS Médecins Cameroun, StartUp Academy et les livres, chaque phrase avec sa source.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/5-noubissie-apres-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/5-noubissie-apres-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/5-noubissie-apres-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/5-noubissie-apres-ordinateur-sombre.png) |

## 6. « Que fait l'entreprise Togocom ? »

Avant, Kekeli répondait de mémoire en 27 mots. Maintenant, il cherche et répond par une liste des activités, avec trois sources.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/6-togocom-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/6-togocom-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/6-togocom-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/6-togocom-ordinateur-sombre.png) |

## 7. « Qui est ton fondateur ? »

Une réponse courte, avec les deux sites.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/7-fondateur-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/7-fondateur-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/7-fondateur-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/7-fondateur-ordinateur-sombre.png) |

## 8. « Je veux apprendre à créer des agents IA »

La réponse d'abord, puis une seule phrase de recommandation : la formation de Lomé IA School la plus adaptée, avec son prix exact.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/claude/8-agents-ia-telephone-clair.png) | ![Téléphone, sombre](recherche/claude/8-agents-ia-telephone-sombre.png) |
| Ordinateur | ![Ordinateur](recherche/claude/8-agents-ia-ordinateur-clair.png) | ![Ordinateur, sombre](recherche/claude/8-agents-ia-ordinateur-sombre.png) |

---

# Recherche sur Internet avec sources

Captures du 12 novembre 2026 sur un téléphone simulé (Pixel 7, navigateur en français) et sur un ordinateur (1 440 × 900), en mode clair et en mode sombre. Les questions sont parties pour de vrai vers le premier répondant (Gemini gratuit) et la recherche (Tavily gratuit), sans ordinateur virtuel. Compte et projets inventés. Script : `scripts/captures-recherche.js`. Détails : `docs/plan.md`, partie 26.13.

## Une question avec recherche, et ses sources

« quel est le prix du sac de ciment à Lomé en ce moment » : Kekeli cherche sur Internet, répond court avec ses propres mots et des nombres en chiffres, cite [1], et ajoute « Sources » : liens cliquables, numérotés comme dans le texte, avec le nom du site.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/telephone-1-question-avec-recherche.png) | ![Téléphone, sombre](recherche/telephone-sombre-1-question-avec-recherche.png) |
| Ordinateur | ![Ordinateur](recherche/ordinateur-1-question-avec-recherche.png) | ![Ordinateur, sombre](recherche/ordinateur-sombre-1-question-avec-recherche.png) |

## La recherche, rangée dans les actions repliées

« Kekeli a fait 1 action » se déplie : « Recherche sur Internet ». Pendant la recherche, la ligne discrète « Recherche sur Internet… » remplace « Kekeli réfléchit… ».

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/telephone-2-recherche-dans-les-actions.png) | ![Téléphone, sombre](recherche/telephone-sombre-2-recherche-dans-les-actions.png) |
| Ordinateur | ![Ordinateur](recherche/ordinateur-2-recherche-dans-les-actions.png) | ![Ordinateur, sombre](recherche/ordinateur-sombre-2-recherche-dans-les-actions.png) |

## Une question sans recherche

« c'est quoi un nom de domaine » : une explication stable, Kekeli répond directement, sans chercher et sans sources.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/telephone-3-question-sans-recherche.png) | ![Téléphone, sombre](recherche/telephone-sombre-3-question-sans-recherche.png) |
| Ordinateur | ![Ordinateur](recherche/ordinateur-3-question-sans-recherche.png) | ![Ordinateur, sombre](recherche/ordinateur-sombre-3-question-sans-recherche.png) |

## La limite atteinte

Plus de recherche permise aujourd'hui pour ce compte : Kekeli répond avec ce qu'il sait et dit poliment qu'il n'a pas pu vérifier sur Internet.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/telephone-4-limite-atteinte.png) | ![Téléphone, sombre](recherche/telephone-sombre-4-limite-atteinte.png) |
| Ordinateur | ![Ordinateur](recherche/ordinateur-4-limite-atteinte.png) | ![Ordinateur, sombre](recherche/ordinateur-sombre-4-limite-atteinte.png) |

# Les trois finitions de la navigation

## 1. Les onglets des réglages sur téléphone

Avant : l'onglet « Formule » était coupé au bord de l'écran. Après : deux colonnes de pastilles, toutes visibles.

| Avant (téléphone) | Après (téléphone) |
|---|---|
| ![Avant](navigation/apres/telephone-7-reglages-profil.png) | ![Après](recherche/telephone-5-finition-onglets-reglages.png) |

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/telephone-5-finition-onglets-reglages.png) | ![Téléphone, sombre](recherche/telephone-sombre-5-finition-onglets-reglages.png) |
| Ordinateur | ![Ordinateur](recherche/ordinateur-5-finition-onglets-reglages.png) | ![Ordinateur, sombre](recherche/ordinateur-sombre-5-finition-onglets-reglages.png) |

## 2. L'extrait de la recherche

Avant : « …t gratuits. - Si vous… » (début au milieu d'un mot, marque de liste). Après : début sur un mot entier, sans marque de liste.

| Avant (téléphone) | Après (téléphone) |
|---|---|
| ![Avant](navigation/apres/telephone-3-recherche.png) | ![Après](recherche/telephone-6-finition-extrait-recherche.png) |

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/telephone-6-finition-extrait-recherche.png) | ![Téléphone, sombre](recherche/telephone-sombre-6-finition-extrait-recherche.png) |
| Ordinateur | ![Ordinateur](recherche/ordinateur-6-finition-extrait-recherche.png) | ![Ordinateur, sombre](recherche/ordinateur-sombre-6-finition-extrait-recherche.png) |

## 3. « j'ai un restaurant »

Après : une seule question courte, à la toute fin (« Voulez-vous que je crée le site de votre restaurant ? ») ; plus de proposition glissée au milieu de la réponse, ni après des conseils ou un texte écrit pour le client.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](recherche/telephone-7-finition-question-finale.png) | ![Téléphone, sombre](recherche/telephone-sombre-7-finition-question-finale.png) |
| Ordinateur | ![Ordinateur](recherche/ordinateur-7-finition-question-finale.png) | ![Ordinateur, sombre](recherche/ordinateur-sombre-7-finition-question-finale.png) |
