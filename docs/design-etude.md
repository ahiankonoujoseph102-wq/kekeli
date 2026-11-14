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

---

# Partie B : comment les grands outils organisent leur barre latérale et leurs pages (14 novembre 2026)

Demande du porteur du projet : étudier la barre latérale et les pages de Claude, ChatGPT, Lovable, Bolt.new, Manus et Cursor, sur téléphone et sur ordinateur, et la façon dont l'utilisateur retrouve ses créations. **Règle de Kekeli : si aucun de ces outils n'a un élément, on ne l'ajoute pas.** Sources officielles d'abord (centres d'aide, documentation) ; les relevés tiers sont signalés.

## 1. Outil par outil

| Outil | Barre latérale (ordinateur), de haut en bas | Sur téléphone | Retrouver ses créations |
|---|---|---|---|
| **Claude** | « Nouvelle discussion », recherche, **Projets**, **Artefacts** (toutes les créations : documents, pages, applications), discussions favorites, **Récents** ; en bas le compte (nom, formule) et ses menus (réglages, aide) | Même liste dans un menu qui glisse depuis la gauche | Section **Artefacts** : tout ce qui a été créé, au même endroit, organisable ; **Projets** : groupes de discussions avec leurs connaissances ; **Récents** : les dernières discussions |
| **ChatGPT** | « Nouvelle discussion », « Rechercher dans les discussions », **Bibliothèque** (images et fichiers créés), **Projets**, puis les discussions (récentes d'abord) ; en bas le compte, les réglages et l'aide | Même menu ☰, la Bibliothèque en grille | **Bibliothèque** : images en grille avec aperçu, fichiers créés ; la recherche couvre discussions, projets, images et fichiers |
| **Lovable** | Espace de travail, « Nouveau », « Rechercher », « Projets », automatisations, connecteurs ; listes **Épinglés**, **Projets** (8 au plus, filtres : récents, créés par moi, partagés, publiés) et **Discussions récentes** ; « Voir tous les projets » ; compte en bas | Projets groupés « À moi » et « Partagés avec moi » | Page **Projets** avec filtres et recherche, dossiers, palette de commandes |
| **Bolt.new** | « Projets » (tableau de bord), compte et espaces de travail | — | Tableau de bord **Projets** : filtres « Créés par vous », « Favoris », « Vus récemment », « Partagés » ; projets récents d'abord |
| **Manus** | « Nouvelle tâche », **Projets** (dossiers avec instructions et fichiers, épinglables), puis les tâches | Projets aussi sur téléphone (mai 2026) | Tâches rangées dans des projets ; fichiers rangés dans chaque projet |
| **Cursor** | Barre « Agents » à gauche : toutes les discussions et agents (au premier plan et en arrière-plan) | — (outil d'ordinateur) | Liste des agents et historique des discussions ; recherche dans les transcriptions |

## 2. Ce qu'ils ont en commun

- **Un bouton principal en haut** pour commencer (« Nouvelle discussion », « Nouveau », « Nouvelle tâche ») : tous.
- **Une recherche** juste en dessous : Claude, ChatGPT, Lovable, Cursor.
- **Les créations rangées par nature**, à part des discussions : les **projets** (Claude, ChatGPT, Lovable, Bolt.new, Manus) et une **galerie des fichiers créés** (Artefacts de Claude, Bibliothèque de ChatGPT).
- **Les discussions récentes** sous ces sections, les plus récentes d'abord : Claude, ChatGPT, Lovable, Cursor.
- **Le compte en bas** (nom, formule, réglages, aide) : tous.
- **Sur téléphone, la même liste dans un menu** qui glisse depuis la gauche : Claude, ChatGPT, Lovable.
- **Une création ouverte garde ses vues** (discussion, aperçu, code) : Lovable, Bolt.new, Claude (panneau de l'artefact).

## 3. Décision pour Kekeli : chaque élément demandé existe chez au moins un grand outil

| Élément demandé | Qui l'a | Décision |
|---|---|---|
| « Nouvelle discussion », bouton principal | Claude, ChatGPT, Lovable, Manus, Cursor | Ajouté (remplace « Nouveau ») |
| « Rechercher » | Claude, ChatGPT, Lovable | Gardé |
| « Projets » : sites, applications, agents, automatisations, avec filtres par type, recherche et tri (récents d'abord) | Lovable (filtres, recherche), Bolt.new (filtres, récents d'abord), Claude | Ajouté (page « Projets ») |
| « Bibliothèque » : documents, présentations, images, vidéos en grille avec aperçu, filtres, téléchargement direct | ChatGPT (Bibliothèque en grille), Claude (Artefacts) | Ajoutée (page « Bibliothèque ») |
| « Récents » : les dernières discussions | Claude, ChatGPT, Lovable | Gardé, **seulement les discussions** (les créations vont dans Projets et Bibliothèque) |
| En bas : compte (nom, formule, énergie), Réglages, « Nous contacter » | Claude, ChatGPT (compte, réglages, aide) | Gardé |
| Onglets « Discussion · Aperçu · Code » (ou « Fichier ») d'une création ouverte | Lovable, Bolt.new, Claude | Gardés |

**Aucun élément de la liste ne manque chez tous les grands outils** : rien n'a eu besoin de l'accord du porteur du projet avant d'être ajouté. Ce que Kekeli n'ajoute pas, faute de l'avoir vu chez eux de façon commune : dossiers créés par le client, épinglage, partage d'équipe (Lovable et Bolt.new les ont pour des équipes ; Kekeli n'a pas encore d'équipes).

## Sources (partie B)

- Claude : [Artefacts dans la barre latérale (centre d'aide)](https://support.claude.com/en/articles/9487310-what-are-artifacts-and-how-do-i-use-them), [Projets (Anthropic)](https://www.anthropic.com/index/projects) ; relevé tiers de la barre (Projets, Récents, favoris) : [kindatechnical](https://kindatechnical.com/claude-ai/the-claude-ai-web-interface-a-complete-tour.html).
- ChatGPT : [retrouver ses discussions, projets et fichiers (centre d'aide)](https://help.openai.com/en/articles/10056348-finding-your-chats-projects-and-files-in-chatgpt), [Bibliothèque (centre d'aide)](https://help.openai.com/sr-rs/articles/20001052-using-library-to-manage-files-in-chatgpt) ; relevé tiers de la grille d'images : [Learn Prompting](https://learnprompting.org/blog/guide-openai-chatgpt-image-library).
- Lovable : [vue d'ensemble du tableau de bord](https://docs.lovable.dev/introduction/dashboard-overview), [rechercher et retrouver un projet](https://docs.lovable.dev/introduction/project-search-and-find).
- Bolt.new : [projets et fichiers](https://support.bolt.new/building/using-bolt/projects-files), [notes de version (filtres du tableau de bord)](https://support.bolt.new/release-notes).
- Manus : [Projets (documentation)](https://manus.im/docs/features/projects), [Projets sur téléphone (AlternativeTo, tiers)](https://alternativeto.net/news/2026/5/manus-adds-projects-feature-to-mobile-for-flexible-task-management/).
- Cursor : [historique des discussions (documentation)](https://docs.cursor.com/agent/chat/history), [notes de version](https://cursor.com/docs/release-notes.md).
