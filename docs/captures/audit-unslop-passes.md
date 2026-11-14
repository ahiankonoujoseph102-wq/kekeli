# Audit unslop : les passes, une à une

Décisions du porteur du projet (14 novembre 2026) : **1B** (lueur adoucie), **2A** (onglets des réglages sur une seule ligne qui défile), **3 oui** (moins de gras dans les réponses, annulé si la mesure sur 33 phrases est moins bonne), **4 oui** (nouveaux reçus plus sobres). Plan : [`docs/audit-unslop.md`](../audit-unslop.md). Captures prises sur un téléphone simulé (Pixel 6) et sur un ordinateur, en clair et en sombre, avec des données inventées (`scripts/captures-audit-unslop.js`).

Poids de départ de l'interface : CSS 350,4 ko (41,2 ko compressé), JavaScript 4,8 ko (1,9 ko compressé).

## Passe 0 : défauts

- **Bouton « Copier » de la bulle d'un agent** : il donnait `:aria-label="__('Copy')"` à Alpine, qui l'exécutait comme du JavaScript (« __ is not defined ») ; le bouton n'avait pas de nom pour les lecteurs d'écran. Il a maintenant `aria-label` et `title` « Copier », puis « Copié » après la copie. Nouveau test : aucune balise HTML simple ne donne une traduction à Alpine (`tests/Feature/DesignTest.php`) ; le test des agents vérifie le nom du bouton.
- **Durée de la vidéo d'exemple** des captures : « 26 secondes » au lieu de « 1 seconde » (seulement le script `scripts/preparer-captures-design.php` ; une vraie vidéo envoie déjà sa durée).
- Console du navigateur : plus aucune erreur sur la page d'un agent. Poids du CSS et du JavaScript inchangés (aucun style touché).

| Fenêtre « Le code de votre agent », téléphone clair | Téléphone sombre | Ordinateur clair | Vidéo, téléphone clair |
|---|---|---|---|
| ![](audit-unslop/passes/passe-0/telephone-clair-46-agent-recuperer-le-code.jpg) | ![](audit-unslop/passes/passe-0/telephone-sombre-46-agent-recuperer-le-code.jpg) | ![](audit-unslop/passes/passe-0/ordinateur-clair-46-agent-recuperer-le-code.jpg) | ![](audit-unslop/passes/passe-0/telephone-clair-26-video.jpg) |

## Passe 1 : espacements

- **Une seule marge intérieure par genre de carte** : 16 pixels sur téléphone et 20 sur ordinateur pour une carte seule (Formules, Réglages → Formule, Paiement, Administration → Modèles), 16 pixels pour les cartes d'une liste (tuiles de Consommation, Projets, Paiements et Réglages de l'administration, Variables secrètes). Avant : 12, 16 et 20 pixels mélangés. La carte « Votre formule » prend la classe commune `kekeli-carte` (elle avait son propre coin et son propre fond).
- **« Récents » mieux séparé** dans la barre latérale : 12 pixels au-dessus, 8 en dessous (avant : collé au groupe du dessus).
- Nouveau test : `test_cards_share_one_padding` (`tests/Feature/DesignTest.php`).
- Poids : CSS 350,4 → 351,8 ko (41,2 → 41,4 ko compressé), à cause des classes de marge pour l'ordinateur ; JavaScript inchangé.

| Consommation, avant | Après | Formules, avant | Après | Menu, avant | Après |
|---|---|---|---|---|---|
| ![](audit-unslop/ecrans/telephone-clair-42-admin-consommation.jpg) | ![](audit-unslop/passes/passe-1/telephone-clair-42-admin-consommation.jpg) | ![](audit-unslop/ecrans/telephone-clair-36-formules.jpg) | ![](audit-unslop/passes/passe-1/telephone-clair-36-formules.jpg) | ![](audit-unslop/ecrans/telephone-clair-08-menu.jpg) | ![](audit-unslop/passes/passe-1/telephone-clair-08-menu.jpg) |

Toutes les captures de la passe (téléphone et ordinateur, clair et sombre) : `docs/captures/audit-unslop/passes/passe-1/`.

## Passe 2 : couleurs

- **L'ambre est réservé à l'énergie** : la flamme du logo, la barre d'énergie (barre latérale, menu du compte, Réglages → Formule, carte des crédits gratuits) et la flèche du bouton d'envoi. Un test le garantit (`test_amber_is_kept_for_the_energy_and_the_send_button`).
- **Lueur de l'accueil adoucie (décision 1B)** : plus d'ombre colorée large autour de la zone de saisie ni sous le symbole ; une ombre neutre et, quand on écrit, un filet ambré net d'un pixel ; l'aube est deux fois plus discrète.
- **Barre latérale** : l'élément actif est une simple surface plus foncée, sans cadre, sans trait ni icône ambrés (comme Claude et ChatGPT).
- **États lisibles sans ambre** : « En ligne », « Valide » et « Retenu » en vert sauge (nouvelle pastille `kekeli-pastille-succes`) ; avis (rappel de formule, idée gardée, permission, pays, suggestion de base de données, bientôt disponible, avis de la confidentialité) sur une surface douce à l'encre (`kekeli-avis`) ; paiement refusé en terre cuite ; formule actuelle entourée d'encre ; « Dans l'aperçu » et le bandeau d'une ancienne version en gris ; fichier modifié en bleu ardoise dans l'onglet Code ; e-mail reporté en bleu ardoise ; chargements en gris ; « Kekeli réfléchit… » scintille en encre ; sélection du texte en gris. La dictée qui écoute : bouton en encre, point d'enregistrement terre cuite.
- **« Refuser »** (Administration → Paiements) : bouton secondaire au texte terre cuite, au lieu d'un gros bouton rempli à côté d'une carte ambrée.
- Poids : CSS 351,8 → 351,0 ko (41,4 → 41,3 ko compressé) ; JavaScript inchangé.
- Lighthouse téléphone (médiane de 3, départ → après la passe 2) : Accueil 59 → 60, Connexion 59 → 59, Nouvelle discussion 58 → 58, Discussion 58 → 73 (écart probablement dû à la mesure : rien n'a changé dans le poids de cette page), Site 55 → 55, Projets 54 → 54, Bibliothèque 56 → 57, Formules 56 → 56, Réglages 54 → 58 ; accessibilité et bonnes pratiques 100 partout. Rien à annuler.

| Nouvelle discussion sombre, avant | Après | Menu | Projets (« En ligne ») | Formules | Paiements de l'administration |
|---|---|---|---|---|---|
| ![](audit-unslop/ecrans/telephone-sombre-07-nouvelle-discussion.jpg) | ![](audit-unslop/passes/passe-2/telephone-sombre-07-nouvelle-discussion.jpg) | ![](audit-unslop/passes/passe-2/telephone-clair-08-menu.jpg) | ![](audit-unslop/passes/passe-2/telephone-clair-27-projets.jpg) | ![](audit-unslop/passes/passe-2/telephone-clair-36-formules.jpg) | ![](audit-unslop/passes/passe-2/telephone-sombre-43-admin-paiements.jpg) |

Toutes les captures de la passe, tous les écrans (téléphone et ordinateur, clair et sombre) : `docs/captures/audit-unslop/passes/passe-2/`.

## Passe 3 : icônes

- **Plus de fusée** sur « Mettre en ligne » (en-tête d'un site et fenêtre de publication) : un globe, de la même bibliothèque et à la même taille. Lovable et Bolt.new n'ont pas de fusée.
- **Onglets des réglages sans icône** : comme Claude et ChatGPT ; le mot suffit.
- Les coches vertes « fait » (copie réussie, étape terminée) restent : elles portent un sens. Aucun émoji, une seule bibliothèque d'icônes, déjà vérifié par l'audit.
- Nouveau test : `test_no_rocket_and_no_useless_icons_in_the_settings_tabs`. Poids : CSS 351,0 → 350,9 ko (41,3 ko compressé) ; JavaScript inchangé.

| Site, téléphone | Réglages, téléphone sombre | Site, ordinateur |
|---|---|---|
| ![](audit-unslop/passes/passe-3/telephone-clair-14-site-discussion.jpg) | ![](audit-unslop/passes/passe-3/telephone-sombre-32-reglages-profil.jpg) | ![](audit-unslop/passes/passe-3/ordinateur-clair-14-site-discussion.jpg) |

Toutes les captures de la passe : `docs/captures/audit-unslop/passes/passe-3/`.

## Passe 4 : textes

- **Dates et heures à un seul format**, à l'heure de Lomé, sans abréviation (`App\Support\Formats`, test `tests/Unit/FormatsTest.php`) : « 4 décembre 2026 » au lieu de « 04/12/2026 », « 16 h 15 » au lieu de « 16:15 », « 14 novembre, 16 h 15 » dans les listes serrées de l'administration ; espaces insécables, pour que « 14 décembre » ne se coupe plus en fin de ligne. Écrans : menu du compte, Réglages → Formule, Formules, rappel de fin de formule, « En ligne depuis », Accès à Internet de Kekeli, attente quand Kekeli est très sollicité, toutes les pages d'administration, journal des connecteurs.
- **Durées lisibles** : « Réflexion… 8 min 33 s » au lieu de « 513 s », et « Réflexion · 6 s » avec une espace insécable.
- **Titres sans parenthèses** : « Paiements », « E-mails » (la barre latérale dit déjà « Administration »).
- **Plus de point d'exclamation** : « Merci, votre paiement est validé. » (e-mail du reçu), « Un problème est survenu » (au lieu de « Oups ! »), « Merci de votre patience. ».
- **Moins de gras dans les réponses (décision 3)** : la consigne du premier répondant (`runner/src/competences.js`) garde le gras pour l'essentiel seulement (le chiffre clé, la conclusion ou la recommandation, un mot à ne pas manquer, deux ou trois fois au plus), jamais pour des étiquettes ni pour chaque nombre. **Mesure réelle sur les 36 phrases** (offre gratuite de Google, aucune dépense ; `runner/mesures/gras-reponses-avant.json` et `…-apres.json`) : bonnes décisions 34 sur 36 avant et après (les deux erreurs changent d'un essai à l'autre, sans lien avec le gras) ; nombres écrits en chiffres 3 sur 3 avant et après ; passages en gras 77 → 46 (3,5 → 2,1 par réponse, au plus 13 → 8) ; réponses relues une à une : la recommandation et les chiffres restent, aussi claires qu'avant. **Gardé.**
- Champs numériques de l'administration (« 0.000014 ») : laissés avec un point, car un champ numérique du navigateur n'accepte que le point.
- Poids : CSS 350,9 ko (41,3 ko compressé) et JavaScript inchangés.

| Réflexion en cours | Réglages → Formule | Projets de l'administration | Paiements de l'administration, sombre |
|---|---|---|---|
| ![](audit-unslop/passes/passe-4/telephone-clair-12-reflexion-en-cours.jpg) | ![](audit-unslop/passes/passe-4/telephone-clair-34-reglages-formule-et-recus.jpg) | ![](audit-unslop/passes/passe-4/telephone-clair-41-admin-projets.jpg) | ![](audit-unslop/passes/passe-4/telephone-sombre-43-admin-paiements.jpg) |

Toutes les captures de la passe : `docs/captures/audit-unslop/passes/passe-4/`.

## Passe 5 : typographie

- **Plus de majuscules espacées** : « Récents » de la recherche en casse normale (classe `kekeli-surtitre` : poids 500, sans majuscules ni espacement).
- **Poids 400 et 500** pour les titres, les noms et les chiffres (titre d'un projet, nom dans le menu, formules et leurs prix, chiffres de la consommation et des e-mails, titre d'un état vide, en-têtes de tableau) ; 600 reste seulement pour les rares mises en avant des réponses (gras et titres d'une réponse).
- **Chiffres tabulaires** : prix des formules, chiffres de l'administration et tous les tableaux (`kekeli-tableau`), pour que les colonnes de nombres s'alignent.
- Nouveau test : `test_no_uppercase_tracked_labels_and_light_weights`. Poids : CSS 350,9 → 351,4 ko (41,3 ko compressé) ; JavaScript inchangé.

| Rechercher (« Récents ») | Formules | Consommation, sombre | Site |
|---|---|---|---|
| ![](audit-unslop/passes/passe-5/telephone-clair-10-recherche.jpg) | ![](audit-unslop/passes/passe-5/telephone-clair-36-formules.jpg) | ![](audit-unslop/passes/passe-5/telephone-sombre-42-admin-consommation.jpg) | ![](audit-unslop/passes/passe-5/telephone-clair-14-site-discussion.jpg) |

Toutes les captures de la passe : `docs/captures/audit-unslop/passes/passe-5/`.

## Passe 6 : mise en page

- **Trois largeurs de page seulement**, toujours centrées (test `test_pages_use_three_widths`) : lecture, 768 pixels (nouvelle discussion, réglages, paiement, ouvrir un dépôt GitHub) ; administration, 896 pixels (les sept pages) ; grilles, 1 024 pixels (Projets, Bibliothèque, Formules). Avant : sept largeurs, de 576 à 1 152 pixels. Les pages d'un même genre commencent maintenant au même endroit sur ordinateur, avec le même en-tête (titre, phrase, action).
- **Onglets des réglages sur une seule ligne (décision 2A)** : onglets soulignés, comme Claude et ChatGPT ; sur téléphone, la ligne défile sur le côté avec le léger fondu des filtres, et l'onglet actif est ramené en vue. Le contenu des réglages s'aligne sur le titre (plus de colonne étroite).
- **Accueil sur téléphone** : la question, la saisie et les idées sont centrées dans la hauteur (moins de vide sous les idées).
- Poids : CSS 351,4 → 351,5 ko (41,3 ko compressé) ; JavaScript inchangé.
- Lighthouse téléphone (médiane de 3, départ → après la passe 6) : Accueil 59 → 59, Connexion 59 → 59, Nouvelle discussion 58 → 58, Discussion 58 → 73, Site 55 → 58, Projets 54 → 56, Bibliothèque 56 → 57, Formules 56 → 58, Réglages 54 → 57 ; accessibilité et bonnes pratiques 100 partout. Rien à annuler.

| Accueil | Réglages → Mémoire | Réglages → Formule, sombre | Paiement |
|---|---|---|---|
| ![](audit-unslop/passes/passe-6/telephone-clair-01-accueil.jpg) | ![](audit-unslop/passes/passe-6/telephone-clair-35-reglages-memoire.jpg) | ![](audit-unslop/passes/passe-6/telephone-sombre-34-reglages-formule-et-recus.jpg) | ![](audit-unslop/passes/passe-6/telephone-clair-37-paiement.jpg) |

| Réglages, ordinateur | Formules, ordinateur | Projets de l'administration, ordinateur |
|---|---|---|
| ![](audit-unslop/passes/passe-6/ordinateur-clair-35-reglages-memoire.jpg) | ![](audit-unslop/passes/passe-6/ordinateur-clair-36-formules.jpg) | ![](audit-unslop/passes/passe-6/ordinateur-clair-41-admin-projets.jpg) |

Toutes les captures de la passe, tous les écrans : `docs/captures/audit-unslop/passes/passe-6/`.
