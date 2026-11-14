# Étude de design : ce qui rend les meilleurs outils professionnels (14 novembre 2026)

Demande du porteur du projet : avant de redessiner Kekeli, étudier Claude (claude.ai), ChatGPT, Lovable, Bolt.new, v0, Manus, Cursor et Perplexity, noter ce qui les rend professionnels, puis choisir pour Kekeli. **Règle de Kekeli : si aucun de ces outils n'a un élément, on ne l'ajoute pas.**

**Méthode et limites, à dire clairement** : observations de leurs pages publiques et de leurs interfaces telles qu'on les connaît, sans compte payant ; aucune de ces entreprises ne publie ses valeurs exactes (sauf Vercel pour Geist, le système de v0). Les valeurs de couleur, de taille et de coins notées plus bas viennent de relevés publiés par des tiers (non officiels, approximatifs) ou de l'observation ; elles servent d'ordre de grandeur, jamais de copie. Kekeli ne reprend ni police propriétaire, ni logo, ni couleur de marque de ces outils.

## 1. Outil par outil

| Outil | Typographie | Couleurs | Coins et ombres | Page d'entrée | Discussion et saisie | Barre latérale | États vides, chargements, transitions | Mode sombre et téléphone |
|---|---|---|---|---|---|---|---|---|
| **Claude** | Un caractère à empattements pour les grands titres, une linéale humaniste pour tout le reste, 16 à 17 pixels pour le texte, interligne d'environ 1,5 | Fond crème chaud (environ `#FAF9F5`), texte presque noir, **un seul accent** (terre cuite) très rare | Cartes de 12 à 16 pixels, saisie très arrondie, ombres presque invisibles (environ 4 % d'opacité), bordures d'un pixel | Une salutation en grand, une zone de saisie, quelques suggestions | Messages du client dans une bulle claire, réponses sans bulle en pleine largeur ; réflexion repliée « Réflexion · 6 s » ; outils en lignes discrètes dépliables ; fichiers et artefacts en cartes | Repliable, « Nouvelle conversation » en haut, « Récents » ensuite, compte en bas | Salutation au lieu d'un écran vide ; réponse qui s'écrit progressivement ; animation du logo pendant la réflexion ; fondus courts | Brun très foncé chaud, jamais noir pur ; sur téléphone, saisie collée en bas, barre en tiroir |
| **ChatGPT** | Linéale neutre, texte de 16 pixels, titres peu nombreux | Presque sans couleur : blanc, gris, noir ; accent seulement pour l'état actif | Saisie en grande pilule (environ 28 pixels), menus arrondis avec une ombre douce | Une question en grand « Par quoi commençons-nous ? » et la saisie au centre | Bulles grises pour le client, réponse sans bulle ; « + » regroupe les outils ; micro et envoi ronds ; « Copier », « Régénérer » sous la réponse | Fine, gris très clair, recherche, « Nouvelle discussion », projets, compte en bas | Point qui pulse pendant l'attente, texte « Réflexion » qui scintille, apparition par fondu | Gris très foncé neutre ; téléphone : tout tient dans une colonne |
| **Lovable** | Linéale géométrique, grand titre très gras sur l'accueil | Dégradé coloré **seulement sur l'accueil**, interface de travail neutre | Saisie arrondie (environ 16 à 20 pixels), ombre portée douce sous la saisie de l'accueil | Grand titre, saisie centrale, exemples à toucher, projets de la communauté | Discussion à gauche, aperçu à droite ; étapes de construction en liste cochée ; messages courts | Projets dans un menu, pas de barre permanente dans l'espace de travail | Squelettes gris pendant le chargement de l'aperçu ; animation du logo pendant la construction | Sombre par défaut dans l'espace de travail |
| **Bolt.new** | Linéale, petits caractères dans l'espace de travail | Fond sombre, une lueur bleue sur l'accueil seulement | Coins de 8 à 12 pixels, bordures fines | Titre « What do you want to build? », saisie, exemples | Discussion à gauche, code et aperçu à droite ; actions repliées (« fichiers créés ») | Barre qui glisse au survol | Liste d'actions qui se coche en direct | Sombre d'abord ; téléphone : vues en onglets |
| **v0** (Vercel, système **Geist**, documenté) | Geist Sans et Geist Mono ; échelle de tailles stricte | Gris à fort contraste, accessibles ; presque pas de couleur | Rayons et ombres en « matériaux » prédéfinis (rien d'improvisé) | Saisie au centre et modèles | Messages sobres, blocs de code soignés, aperçu à côté | Barre latérale fine avec l'historique | Squelettes et indicateurs discrets | Clair et sombre aussi soignés l'un que l'autre |
| **Manus** | Linéale, beaucoup de blanc | Neutre, presque monochrome | Coins moyens, bordures fines | Salutation et saisie | Journal d'étapes de la tâche, fichiers livrés en cartes | Liste des tâches | Étapes qui apparaissent une à une | Sombre neutre |
| **Cursor** | Linéale et chasse fixe pour le code | Monochrome, accent très rare | Petits coins (6 à 8 pixels), presque pas d'ombres | Pas de page d'entrée dans l'éditeur ; site public très sobre | Panneau de l'agent : lignes d'outils compactes, différences en vert et rouge | Arborescence des fichiers | Indicateurs fins, pas d'animation décorative | Sombre par défaut |
| **Perplexity** | Linéale ; réponses très lisibles, titres courts | Neutre, accent turquoise discret | Coins moyens, cartes de sources | Logo, saisie centrale | **Sources numérotées en pastilles**, puis la réponse ; « A cherché sur le Web » | Fine, repliable | Étapes de recherche qui s'enchaînent | Sombre soigné ; téléphone : saisie en bas |

## 2. Ce qu'ils ont en commun (ce qui fait « professionnel »)

1. **Une seule famille de linéales pour l'interface**, une échelle de tailles courte (environ 12, 13, 14, 16, 20, 24 et un grand titre), du texte de 15 à 16 pixels sur téléphone. Claude ajoute un caractère à empattements seulement pour les grands titres.
2. **Des couleurs presque neutres et un seul accent**, utilisé très peu. Pas de rouge vif pour les erreurs ; des tons doux.
3. **Des coins cohérents** : petits pour les champs et les menus (8 à 12 pixels), moyens pour les cartes (12 à 16), très arrondis pour la saisie principale et les boutons (pilule).
4. **Presque pas d'ombres** : bordures d'un pixel ; une ombre douce seulement pour ce qui flotte (menu, fenêtre, saisie de l'accueil).
5. **Beaucoup de vide** et un espacement sur une grille de 4 pixels.
6. **Icônes au trait fin**, d'une seule bibliothèque, à la même taille partout.
7. **La discussion** : bulle claire pour le client, réponse sans bulle en pleine largeur, outils en lignes repliées, réflexion repliée, fichiers en cartes, « Copier » sous la réponse.
8. **États vides utiles** (une salutation, des idées à toucher) au lieu d'un écran blanc ; **chargements** par squelettes gris ou par un texte qui scintille ; **transitions courtes** (150 à 250 millisecondes), fondus et légers glissements, jamais de rebond.
9. **Mode sombre aussi soigné que le clair**, chaud (Claude) ou neutre (ChatGPT), jamais noir pur pour Claude.
10. **Téléphone** : saisie collée en bas, barre latérale en tiroir, une seule couche ouverte à la fois, zones à toucher d'au moins 40 à 44 pixels.

## 3. Choix retenus pour Kekeli, et pourquoi

| Choix | Pourquoi |
|---|---|
| **Garder l'identité « la lumière »** (papier chaud `#FAF8F4`, encre `#1C1A17`, un seul ambre `#E3A008`, sombre brun `#171513`) | Proche de Claude (fond chaud, un accent), déjà validée par le porteur du projet (pull request 34) ; on la rend **systématique** au lieu de la changer |
| **Un seul endroit pour tout le système** : les jetons dans `resources/css/app.css` (bloc `@theme` et variables `--k-…`), repris pour les e-mails et le reçu par `App\Support\Design` (un test vérifie que les deux sont identiques) | Comme Geist chez v0 : rien d'improvisé, un changement de couleur se fait une fois |
| **Échelle de coins** : 6, 8, 10, 14, 18, 24 pixels et pilule ; saisie principale 24, cartes 14, menus 12, champs 10 | Coins cohérents comme chez Claude et ChatGPT |
| **Ombres** : quatre niveaux très doux et chauds, seulement pour ce qui flotte | Comme Claude (environ 4 à 18 % d'opacité) |
| **Gris chauds plus contrastés** : texte secondaire assombri pour atteindre WCAG AA (4,5 pour 1) en clair comme en sombre | Les petits textes gris de Kekeli étaient trop pâles ; ChatGPT et v0 ont un contraste élevé |
| **Couleurs d'état douces** (réussite vert sauge, information bleu ardoise, attention ambre, erreur terre cuite) au lieu des verts, rouges et bleus vifs de Tailwind | Comme les tons doux de Claude ; les erreurs restent aimables |
| **Inter pour l'interface, Instrument Serif seulement pour les grands titres** (polices libres servies par Kekeli) | Même partage que Claude ; on ne prend jamais une police propriétaire |
| **Animations** : apparition des messages (fondu et glissement de 4 pixels, 200 millisecondes), réflexion qui scintille, squelettes pendant le chargement, menus et fenêtres qui s'ouvrent en fondu et léger agrandissement, tout désactivé avec « réduire les animations » ; en CSS seulement | Ce sont les animations de Claude, ChatGPT et Lovable ; aucune bibliothèque, rien de lourd pour un Android modeste |
| **États vides** avec une phrase et une action, **pages d'erreur** (404, 403, 419, 500, maintenance) au même design, avec le contact | Tous ces outils ont des pages d'erreur soignées ; Kekeli avait les pages anglaises de Laravel |
| **Logo dans un seul composant** (`<x-logo>`) et **un seul fichier** (`public/logo.svg`, d'où sont tirés le favicon et l'image des e-mails) | Le soleil est provisoire : on le remplacera en un geste |
| **E-mails et reçu PDF** aux mêmes couleurs et au même logo | Comme les e-mails de Claude et ChatGPT : sobres, la marque en haut, un bouton couleur encre |

## 4. Ce qu'on n'ajoute pas (aucun de ces outils ne l'a, ou on l'a déjà retiré)

- Pas de dégradé coloré dans l'espace de travail (Lovable et Bolt.new le gardent pour l'accueil seulement ; Kekeli n'en a pas besoin).
- Pas d'animation décorative qui tourne en continu, pas de confettis, pas de rebond, pas de bibliothèque d'animation.
- Pas d'emoji dans l'interface, pas de bouton « bientôt disponible ».
- Pas de nouvelle police ni de nouveau logo.

## Sources

- Relevés tiers (non officiels) du design de Claude : [webdesignhot, claude.ai](https://www.webdesignhot.com/design.md/claude-ai/), [kombai, Claude](https://kombai.com/examples/design-systems/claude-kombai).
- Système Geist de Vercel (v0), officiel : [vercel.com/geist](https://vercel.com/geist/introduction).
- Simplification de la zone de saisie de ChatGPT (outils sous « + ») : [BGR](https://bgr.com/tech/chatgpt-got-a-makeover-but-all-of-your-ai-tools-are-still-there) ; barre latérale du bureau : [Digital Trends](https://digitaltrends.com/computing/openai-patches-chatgpt-desktop-after-user-backlash-over-its-recent-redesign).
- Pages publiques de Lovable, Bolt.new, Manus, Cursor et Perplexity (observées ; la page de Lovable refuse la lecture automatique).
- Étude précédente de Kekeli : `docs/plan.md`, parties 23 et 26.1.
