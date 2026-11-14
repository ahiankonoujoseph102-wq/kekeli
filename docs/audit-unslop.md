# Audit « unslop » de Kekeli (14 novembre 2026)

**Demande du porteur du projet** : analyser toute l'application avec le skill unslop (Damir Sokolovsky, licence CC BY 4.0, `MENTIONS.md`), relever tout ce qui donne un aspect générique, artificiel ou « fait par une IA », puis proposer une direction visuelle et un plan en passes successives. **Rien n'est encore modifié dans l'application** : ce document attend la validation du porteur du projet.

- Méthode : celle du skill (`.claude/skills/unslop/SKILL.md`) : regarder l'écran rendu, passer en revue chaque signe de `references/signs.md`, décider de l'ampleur, écrire la direction, puis corriger en passes, dans l'ordre. Ni la génération d'images payante ni la vidéo « avant/après » ne sont utilisées.
- Écrans analysés : 45 écrans, chacun sur téléphone (Pixel 6) et sur ordinateur, en clair et en sombre, soit 180 captures (`docs/captures/audit-unslop.md`), plus les e-mails et le reçu PDF. Données inventées, aucune requête à un modèle.
- Une capture annotée (cadre rouge) par problème : `docs/captures/audit-unslop/problemes/`.

## 1. Verdict : « légère trace », pas « interface fabriquée par une IA »

Le skill distingue trois cas : beaucoup de signes (tout refaire), quelques signes sur un écran déjà dessiné (« polish mode »), aucun signe. **Kekeli est dans le deuxième cas.** Le système de design de la partie 26.23 a déjà retiré la plupart des signes classiques ; la recherche dans le code le confirme :

| Signe classique d'une interface faite par une IA | Dans Kekeli |
|---|---|
| Dégradés de couleur sur les surfaces, texte en dégradé | Aucun (0 occurrence de `gradient` dans les vues) |
| Émojis comme icônes | Aucun ; une seule bibliothèque d'icônes au trait |
| `transition-all`, agrandissement au survol | Aucun |
| Animations qui tournent sans raison | Seulement sur des choses vivantes (point « Kekeli travaille », micro qui écoute), toutes coupées par « réduire les animations » (règle globale de `resources/css/app.css`) |
| Valeurs d'espacement au hasard (`mt-[13px]`) | 2 seulement, justifiées |
| Titres très gras (800 ou 900) | Aucun ; `font-bold` une seule fois |
| Couleurs vives, plusieurs accents | Gris chauds, un seul ambre, états doux |

Conséquence, comme le demande le skill pour ce cas : **on garde la palette, les polices, la mise en page et le logo**, on corrige seulement ce que l'audit a trouvé, et les idées plus grandes restent des propositions à valider.

## 2. Problèmes, classés par gravité

Chaque ligne : ce qu'on voit, où, la capture, et la correction proposée (avec la passe qui la fait).

### Graves (à corriger en premier)

| Numéro | Problème | Où | Capture | Correction proposée |
|---|---|---|---|---|
| G1 | **Erreur JavaScript « __ is not defined »** : le bouton « Copier » de la bulle à intégrer d'un agent a `:aria-label="__('Copy')"` sur un simple bouton HTML ; Alpine l'exécute comme du JavaScript et plante. Le bouton n'a donc pas de nom pour les lecteurs d'écran. C'est un défaut, pas seulement du design | Fenêtre « Mettre en ligne » d'un agent (`resources/views/projects/partials/publish.blade.php`, ligne 103), donc page de tout agent | (erreur de la console, relevée sur les 4 formats) | `aria-label="{{ __('Copy') }}"` ; un test qui vérifie qu'aucune vue n'écrit `:attribut="__(` sur une balise HTML simple. Passe 0 |
| G2 | **Lueur ambrée autour de la zone de saisie et aube derrière le symbole** : une ombre colorée de 56 à 72 pixels, plus vive pendant la frappe. C'est le premier signe de la liste du skill (« colored glows ») ; elle fait « effet » plutôt que « lumière » | Accueil, « Nouvelle discussion » (téléphone et ordinateur, surtout en sombre) | `problemes/01-lueur-ambree-saisie.jpg` | Garder l'idée validée le 14 novembre 2026 (partie 26.24), mais en sobre : un filet ambré net d'un pixel autour de la saisie quand on écrit, une aube deux fois plus discrète, plus d'ombre colorée large. **Décision du porteur du projet nécessaire** (voir partie 5). Passe 2 |
| G3 | **Des pilules partout** : filtres, idées à toucher, onglets des réglages, boutons, pastilles, menus. Le skill : « Every element a full pill » ; tout se ressemble, rien ne se distingue | Projets, Bibliothèque, Réglages, Accueil, Formules | `problemes/10-pilules-partout.jpg` | Rayon selon la taille (déjà défini dans `@theme`) : les **boutons d'action** restent en pilule (identité validée), les **filtres** deviennent des onglets soulignés sur une ligne (comme les onglets « Discussion · Aperçu · Code » déjà en place), les idées à toucher gardent la pilule (comme ChatGPT et Claude). Passe 7 |
| G4 | **Onglets des réglages en grille de 6 pilules sur téléphone** (2 colonnes, 3 lignes) : lourd, ressemble à un clavier, l'onglet actif est un gros bloc noir | Réglages (5 écrans) | `problemes/03-onglets-reglages-en-pilules.jpg` | Une seule ligne d'onglets qui défile, soulignée, avec le fondu sur le bord déjà construit pour les filtres (`kekeli-defile`) ; sur ordinateur, la même ligne. **Choix à confirmer** (voir partie 5). Passe 6 |
| G5 | **Largeurs et alignements de page différents d'un écran à l'autre sur ordinateur** : 7 largeurs (`max-w-xl` à `max-w-6xl`) ; le titre « Réglages » commence à 464 pixels, « Formules » à 288, « Consommation » à 400 ; l'administration « Projets » est une colonne étroite collée à gauche | Ordinateur : Réglages, Formules, Paiement, toutes les pages d'administration | `problemes/05-largeurs-de-page-ordinateur-a.jpg`, `…-b.jpg` | Trois largeurs seulement : lecture (discussion, réglages, paiement : 768 pixels), grille (Projets, Bibliothèque, Formules : 1 152 pixels), administration (1 152 pixels) ; même marge gauche et même en-tête de page partout (titre, phrase, action). Passe 6 |
| G6 | **L'ambre est répété** : sur un même écran, trait ambré et cadre de l'élément actif de la barre latérale, barre d'énergie ambrée en bas de la barre, flamme ambrée, carte « Votre formule » à bordure ambrée et pastille ambrée. Le skill et `CLAUDE.md` : une seule touche d'ambre | Barre latérale (toutes les pages connectées), menu du compte, Formules, Administration | `problemes/11-ambre-repete.jpg`, `problemes/12-element-actif-encadre.jpg` | Élément actif : simple fond gris chaud, sans cadre ni trait ambré (comme Claude et ChatGPT) ; l'ambre reste à la flamme du logo, à l'énergie et au bouton d'envoi. Carte de la formule actuelle : bordure encre, pastille grise. Passe 2 |

### Moyens

| Numéro | Problème | Où | Capture | Correction proposée |
|---|---|---|---|---|
| M1 | **Fusée sur « Mettre en ligne »** (icône `rocket-launch`) : le cliché des produits faits par une IA (le skill cite 🚀) | En-tête de chaque site, fenêtre de publication | `problemes/02-fusee-mettre-en-ligne.jpg` | Icône « globe » ou « flèche vers le haut dans un nuage » de la même bibliothèque, ou pas d'icône (Lovable et Bolt.new écrivent « Publish » sans fusée). Passe 3 |
| M2 | **Libellé en majuscules espacées « RÉCENTS »** (classe `kekeli-surtitre`, `uppercase`, `letter-spacing: 0.04em`) | Fenêtre « Rechercher » | `problemes/04-libelle-majuscules-espacees.jpg` | « Récents », en minuscules, gris, comme dans la barre latérale. Passe 5 |
| M3 | **Formats de nombres et de dates mélangés** : « 04/12/2026 » à côté de « 14 novembre 2026 », « 14/11/2026 16:15 », « 0.000014 » avec un point, « 118,0 FCFA », « 14 / décembre » coupé en fin de ligne | Menu du compte, Réglages → Formule, Formules, administration, e-mail du reçu | `problemes/06-formats-nombres-et-dates.jpg` | Une seule règle : dates en toutes lettres (« 4 décembre 2026 ») pour le client, « 14 nov. » interdit (pas d'abréviation), heure « 16 h 15 » ; nombres à la française (virgule, espace fine insécable), chiffres tabulaires dans les colonnes ; une aide commune (`App\Support\Formats`). Passe 4 |
| M4 | **Pastille d'état « ● Discussion »** sous le titre d'une discussion : un point gris qui ne dit rien (une discussion n'a pas d'état). Pour un site, « ● Brouillon » reste utile | En-tête de toutes les discussions | `problemes/07-pastille-etat-sans-sens.jpg` | Rien sous le titre d'une discussion ; le point seulement quand il signale un vrai état (brouillon, en ligne, Kekeli travaille). Passe 8 |
| M5 | **« Copier » et « Régénérer » trop petits et trop pâles** : 32 pixels, sous les 44 conseillés pour un doigt | Sous chaque réponse, sur téléphone | `problemes/14-copier-regenerer-trop-petits.jpg` | Zone à toucher de 40 pixels sur téléphone (l'icône garde sa taille), gris secondaire au lieu du gris pâle. Passe 8 |
| M6 | **Gras partout dans les réponses** : chaque nombre, chaque étiquette et la recommandation en gras ; plus rien ne ressort | Réponses de Kekeli (discussion) | `problemes/13-gras-partout-dans-les-reponses.jpg` | Consigne du premier répondant : le gras seulement pour la réponse et la recommandation (comme Claude). **Touche à la consigne du modèle** : mesurer avec `node runner/scripts/mesurer-repondant.mjs` (33 phrases) avant de garder, offre gratuite seulement. Passe 4 |
| M7 | **Bouton « Refuser » rempli de terre cuite en pleine largeur** sur une carte à bordure ambrée : deux couleurs fortes sur le même écran | Administration → Paiements | `problemes/08-bouton-refuser-terre-cuite.jpg` | « Refuser » en bouton secondaire (contour, texte terre cuite) ; carte en attente avec une bordure normale et la pastille « À vérifier ». Passe 2 |
| M8 | **Reçu PDF qui ressemble à un tableur** : bandeau noir « Détail · Valeur » (en-tête sans contenu), numéro répété trois fois, police différente de celle du site | Reçu PDF | `problemes/18-recu-pdf-tableur.jpg` | Lignes « étiquette à gauche, valeur à droite » avec des filets fins (comme l'e-mail du reçu, déjà réussi), numéro une seule fois dans le titre, total mis en avant. Les reçus déjà émis ne changent pas (ils sont figés). Passe 7 |
| M9 | **Compteur de réflexion en secondes sans fin** : « Réflexion… 513 s » | Discussion, pendant que Kekeli réfléchit | `problemes/16-reflexion-compteur-secondes.jpg` | Au-delà de 60 secondes : « 8 min 33 s ». Passe 4 |

### Mineurs

| Numéro | Problème | Où | Capture | Correction proposée |
|---|---|---|---|---|
| m1 | Titres avec parenthèses : « Paiements (administration) », « E-mails (administration) » | Administration | `problemes/09-titres-administration-parentheses.jpg` | « Paiements », « E-mails » (la barre latérale dit déjà « Administration »). Passe 4 |
| m2 | Points d'exclamation dans l'interface : « Merci ! Votre paiement est validé. » (e-mail), « Oups ! », « Merci de votre patience ! » | E-mail du reçu, page d'erreur de Laravel, page « bientôt disponible » | `problemes/19-email-point-exclamation.jpg` | Phrases calmes : « Merci, votre paiement est validé. » Passe 4 |
| m3 | Bande vide sous les idées à toucher, puis le pied de page tout en bas | Accueil sur téléphone | `problemes/15-bande-vide-accueil.jpg` | Centrer le bloc (symbole, question, saisie, idées) dans la hauteur restante. Passe 6 |
| m4 | Cartes avec trois marges intérieures différentes (12, 16 et 20 pixels) pour le même genre de contenu | Projets, Formules, Réglages, administration | (captures des écrans) | 16 pixels sur téléphone, 20 sur ordinateur, pour toutes les cartes. Passe 1 |
| m5 | Durée « 1 seconde » sous la vidéo d'exemple | Captures seulement : la vidéo inventée de `scripts/preparer-captures-design.php` n'a pas de durée (une vraie vidéo envoie la sienne) | `ecrans/telephone-clair-26-video.jpg` | Corriger le script de préparation des captures, pas l'application. Passe 0 |

### Examinés et gardés (règle de Kekeli : si un grand outil l'a, on peut le garder)

- **« Bienvenue sur Kekeli » à la connexion** (`problemes/17-connexion-salutation.jpg`) : le skill déconseille les titres de salutation dans une application, mais c'est la page d'entrée, comme chez Claude et ChatGPT ; gardé.
- **Instrument Serif pour les grands titres seulement** : décision du 12 novembre 2026, conforme au skill (un caractère à empattements pour les grands titres seulement).
- **Boutons d'action en pilule couleur encre** : identité validée ; le skill accepte la pilule pour les boutons quand elle est cohérente.
- **Zone de saisie très arrondie** (24 pixels) : comme Claude et ChatGPT.
- **Tableaux dans les réponses, cartes de fichier, réflexion repliée, actions repliées** : comme Claude, Manus et Bolt.new.

## 3. Direction proposée pour Kekeli

Les cinq lignes demandées par le skill, tirées du produit et de l'identité déjà validée (nom Kekeli, ambre `#E3A008`, encre `#1C1A17`, papier chaud `#FAF8F4`, logo « K à la flamme » inchangé) :

- **Produit** : Kekeli fabrique, à partir d'une simple phrase écrite sur un téléphone, les sites, applications, agents, automatisations, documents, affiches et vidéos des commerçants, des écoles et des entreprises d'Afrique de l'Ouest.
- **Caractère** : chaleureux, posé, précis. (Les mots « moderne », « épuré » ou « premium » sont interdits par le skill : ils décrivent tous les modèles.)
- **Monde** : le papier et l'encre d'un cahier de commandes, la lumière chaude d'une fin d'après-midi à Lomé. Le papier donne la teinte des gris (déjà en place), l'encre donne le texte et les boutons, la lumière donne l'ambre.
- **Moments** (les seuls endroits riches en couleur) : (1) le symbole et la grande question de l'accueil ; (2) ce que Kekeli livre : l'aperçu du site, la page du document, l'affiche, la vidéo. La couleur vient des créations du client ; l'interface autour reste papier et encre.
- **Signature** : la flamme ambrée est l'énergie de Kekeli et rien d'autre : la flamme du logo (qui vacille pendant le travail), la fine barre d'énergie, le bouton d'envoi. Ailleurs, pas d'ambre.

Test du skill (« si on remplace le nom par celui d'un autre produit, l'écran convient-il encore ? ») : aujourd'hui, la barre latérale et les pages Projets et Réglages conviendraient à n'importe quel outil ; après le plan, la signature (flamme et énergie seulement) et les textes propres à Kekeli (FCFA, Mixx by Yas et Flooz, heure de Lomé, « énergie ») les rendent propres à Kekeli, sans rien ajouter qu'aucun grand outil n'a.

## 4. Plan d'amélioration en passes

Règles communes à toutes les passes :
- **Téléphone d'abord, ordinateur ensuite**, clair et sombre vérifiés à chaque passe.
- **Aucune fonction supprimée**, aucune intégration touchée (OpenAI, Coarena, E2B, Runner, Brevo, Google, GitHub, connecteurs), aucune architecture changée, rien de réel remplacé par du fictif.
- **Rien d'ajouté qu'aucun grand outil (Claude, ChatGPT, Lovable, Bolt.new, Manus, Cursor) n'a.**
- Tout passe par le système de design (`resources/css/app.css`, `App\Support\Design`) : jamais de couleur en dur.
- **Une passe = un commit**, poussé aussitôt (règle numéro 10) ; après chaque passe : `php artisan test`, `vendor/bin/pint --test`, `cd runner && npm test`, captures des écrans touchés (`scripts/captures-audit-unslop.js` avec `SEULEMENT=…`), poids du CSS et du JavaScript ; Lighthouse téléphone (médiane de 3) après les passes 2, 6 et 8, et à la fin. **Ce qui n'améliore rien est annulé et signalé.**
- Animations : seulement `opacity` et `transform`, 150 à 250 millisecondes, `--ease-kekeli`, coupées par « réduire les animations ».

| Passe | Ce qu'elle change | Écrans touchés | Tests |
|---|---|---|---|
| **0. Défauts** | G1 (bouton « Copier » de la bulle : erreur JavaScript et nom accessible) ; m5 (durée de la vidéo d'exemple dans le script des captures) | Page de tout agent ; captures | Test : aucune vue n'écrit `:attribut="__(` sur une balise HTML simple ; le bouton a son `aria-label` ; plus aucune erreur dans la console sur les 45 écrans |
| **1. Espacements** | Grille de 4 pixels vérifiée ; marges intérieures des cartes unifiées (16 et 20 pixels, m4) ; même espace entre le titre d'une page, sa phrase et son contenu ; rangées de la barre latérale à hauteur égale (36 pixels, 4 entre elles, 24 avant « Récents » et 8 après) | Toutes les pages connectées, administration | Test de design : plus de `kekeli-carte` avec `p-3` ou `p-5` hors des exceptions listées |
| **2. Couleurs** | G6 : élément actif sans cadre ni trait ambré, carte de la formule actuelle sans ambre ; G2 : lueur adoucie selon la décision du porteur du projet ; M7 : « Refuser » en bouton secondaire ; vérification qu'il ne reste qu'une touche d'ambre par écran (hors créations du client) ; contrastes AA vérifiés en clair et en sombre | Barre latérale (toutes les pages), menu du compte, Formules, Réglages → Formule, accueil, Administration → Paiements | Test de design (l'ambre n'apparaît que dans les classes permises) ; test des contrastes existant |
| **3. Icônes** | M1 : plus de fusée ; une seule taille par endroit (16 pixels dans les listes et menus, 20 dans les en-têtes), trait identique ; icônes à la couleur de leur texte (aucune icône colorée à part l'état « fait », vert sauge) | En-tête des sites, fenêtre de publication, menus « ⋯ », listes | Test : `rocket` absent des vues |
| **4. Textes** | M3 : dates, heures et nombres à un seul format (`App\Support\Formats`), espaces insécables ; M9 : compteur « 8 min 33 s » ; m1 : titres sans parenthèses ; m2 : plus de point d'exclamation dans l'interface et les e-mails ; M6 (seulement si la mesure le garde) : moins de gras dans les réponses ; titres et boutons sans point final | Menu du compte, Réglages, Formules, Paiement, administration, e-mails, discussion, `lang/fr.json` | Tests des formats (dates, heures, nombres) ; test : aucun « ! » dans `lang/fr.json` ni dans `App\Mail\Emails` ; mesure du premier répondant 33 sur 33 avant et après pour M6 |
| **5. Typographie** | M2 : plus de majuscules espacées (`kekeli-surtitre` en casse normale) ; poids 400 et 500, 600 seulement pour les rares mises en avant (19 `font-semibold` revus) ; chiffres tabulaires dans les tableaux, les compteurs et les prix | Recherche, Formules, administration (Consommation, E-mails), tableaux des réponses | Test de design : `uppercase` absent des classes `kekeli-…` |
| **6. Mise en page** | G5 : trois largeurs de page et un en-tête de page commun ; G4 : onglets des réglages sur une ligne (selon la décision) ; m3 : bloc de l'accueil centré dans la hauteur | Réglages (5 pages), Formules, Paiement, administration (7 pages), Projets, Bibliothèque, accueil | Tests d'organisation existants ; nouveau test : chaque page connectée utilise l'en-tête commun |
| **7. Détails** | G3 : filtres en onglets soulignés, pilules gardées pour les boutons et les idées ; rayons emboîtés (rayon intérieur = rayon extérieur moins la marge) ; M8 : reçu PDF en lignes « étiquette · valeur » (nouveaux reçus seulement) ; états du survol et du focus visibles au clavier partout | Projets, Bibliothèque, cartes, reçu PDF (`runner/src/documents/rendu/kekeli_documents.py`, `runner/src/recus.js`) | `runner/tests/recus.test.js` (toujours une seule page) ; test de design |
| **8. Animations et finitions** | M4 : pastille d'état seulement quand elle signale un vrai état ; M5 : zones à toucher de 40 pixels sous les réponses ; une seule apparition au chargement (fondu et montée de 4 pixels), menus qui s'ouvrent depuis leur bouton ; vérification de « réduire les animations » | Discussion, en-têtes des projets, menus | Tests existants des réponses (« Copier », « Régénérer ») ; test : la règle globale de réduction des animations est présente |

Le skill prévoit aussi une passe « Visuels » : **elle n'est pas nécessaire** (les moments de Kekeli sont les créations du client et le symbole ; aucune image décorative à créer, aucune génération d'images payante).

**Ordre et livraison** : une pull request pour l'ensemble des passes, un commit par passe ; à la fin, captures avant et après de tous les écrans (`docs/captures/audit-unslop-apres.md`), mesures, mise à jour de `CLAUDE.md` (partie « Système de design ») et de `docs/plan.md`.

## 5. Décisions à prendre par le porteur du projet avant les modifications

1. **La lueur et l'aube de l'accueil (G2)**, validées le 14 novembre 2026 :
   - A. les garder telles quelles ;
   - B. **(recommandé)** les adoucir : aube deux fois plus discrète, filet ambré net d'un pixel autour de la saisie quand on écrit, plus d'ombre colorée large.
2. **Les onglets des réglages sur téléphone (G4)** :
   - A. **(recommandé)** une seule ligne qui défile, soulignée, avec le fondu sur le bord (comme les filtres) ;
   - B. une liste de sections, chacune ouvrant sa page (comme les réglages de ChatGPT sur téléphone).
3. **Moins de gras dans les réponses (M6)** : accord pour changer la consigne du premier répondant et refaire la mesure des 33 phrases (offre gratuite, aucune dépense).
4. **Le reçu PDF (M8)** : accord pour changer la mise en page des **nouveaux** reçus (les reçus déjà émis ne changent jamais).

## 6. Mesures de départ (avant toute modification)

Lighthouse téléphone (médiane de 3 essais, site local, données inventées) :

| Page | Performance | Accessibilité | Bonnes pratiques |
|---|---|---|---|
| Accueil | 59 | 100 | 100 |
| Connexion | 59 | 100 | 100 |
| Nouvelle discussion | 58 | 100 | 100 |
| Discussion | 58 | 100 | 100 |
| Site | 55 | 100 | 100 |
| Projets | 54 | 100 | 100 |
| Bibliothèque | 56 | 100 | 100 |
| Formules | 56 | 100 | 100 |
| Réglages (mémoire) | 54 | 100 | 100 |

La performance d'environ 55 à 60 vient surtout du site local (serveur de développement de PHP, sans cache) et du téléphone lent simulé par Lighthouse : elle sert à comparer avant et après sur la même machine, pas comme note absolue.

Poids de l'interface construite (`npm run build`) : CSS 350,4 ko (41,2 ko compressé) ; JavaScript 4,8 ko (1,9 ko compressé).

---

Audit fait avec unslop de Damir Sokolovsky ([@heydamir](https://x.com/heydamir)), https://github.com/heydamir/unslop, licence CC BY 4.0.
