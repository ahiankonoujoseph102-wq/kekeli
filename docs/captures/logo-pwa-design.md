# Logo de Kekeli, site installable et design visible : AVANT et APRÈS

Captures du 14 novembre 2026, sur un téléphone simulé (Pixel 6, Chrome, 412 × 915) et sur un ordinateur (1 440 × 900), en clair et en sombre, navigateur en français. **Comptes, projets, paiements et reçus inventés** (`scripts/preparer-captures-design.php`) ; « avant » est le site de `main` juste avant ce changement (pull request 54). Scripts : `scripts/captures-design.js` (pages), `scripts/captures-logo-pwa.js` (logo, favicon, site installable, page hors connexion), `scripts/rendre-email-essai.php` (e-mail). Détails : `docs/plan.md`, partie 26.24.

Ce qui change : le **nouveau logo « K à la flamme »** partout à la place du soleil, un **accueil plus marquant** (le symbole, une aube ambrée, une lueur douce autour de la zone de saisie), la **connexion** avec le logo bien mis en valeur, l'ambre utilisé avec retenue ailleurs (élément actif, flèche d'envoi, réflexion), un **reçu PDF** avec le logo en tête, des **e-mails** avec le logo en haut, et un **site installable** sur le téléphone avec une page « hors connexion ».

## Le logo

Redessiné en SVG d'après l'image validée ([image de référence](../logo/reference-logo-valide.png), [planche des déclinaisons](../logo/reference-declinaisons.png)) : barre parfaitement droite, flamme et jambe lisses, trois espaces égaux. Le nom est en police Lexend (licence libre OFL), transformé en tracés. Tous les fichiers : [`docs/logo/`](../logo/).

![Toutes les versions du logo](logo-pwa-design/logo-toutes-les-versions.png)

### Le favicon en petit

À sa vraie taille dans un onglet clair et un onglet sombre, puis agrandi sans lissage pour voir chaque pixel : le K et la flamme restent reconnaissables à 16 et à 32 pixels.

![Favicon](logo-pwa-design/favicon-petites-tailles.png)

## Le site installable

Le contrôle d'installation de Chrome ne trouve **aucune erreur** (manifeste lu, service worker actif). Le service worker ne garde que la page « hors connexion » et le logo : **aucune page réelle et aucune donnée d'un compte** ne sont gardées.

L'invite de Chrome et l'écran d'accueil ci-dessous sont des **simulations** (nom et icône lus dans le vrai manifeste) : un navigateur sans écran ne peut pas les photographier. L'essai réel se fera sur votre téléphone à la mise en ligne.

| Invite « Installer l'application » (simulation) | Écran d'accueil (simulation) |
|---|---|
| ![Invite](logo-pwa-design/installation-invite-simulation.jpg) | ![Écran d'accueil](logo-pwa-design/installation-ecran-d-accueil-simulation.jpg) |

### Le site installé, en plein écran (simulation)

| Clair | Sombre |
|---|---|
| ![Clair](logo-pwa-design/site-installe-clair-simulation.jpg) | ![Sombre](logo-pwa-design/site-installe-sombre-simulation.jpg) |

### La page « hors connexion » (vraie capture, réseau coupé)

| Clair | Sombre |
|---|---|
| ![Clair](logo-pwa-design/hors-connexion-clair.jpg) | ![Sombre](logo-pwa-design/hors-connexion-sombre.jpg) |

## Mesures

| Page | Performance avant | Performance après | Accessibilité avant | Accessibilité après | Bonnes pratiques avant | Bonnes pratiques après |
|---|---|---|---|---|---|---|
| Accueil | 60 | 59 | 100 | 100 | 100 | 100 |
| Connexion | 60 | 59 | 100 | 100 | 100 | 100 |
| Formules | 60 | 59 | 100 | 100 | 100 | 100 |
| Conditions d'utilisation | 84 | 83 | 100 | 100 | 100 | 100 |

Lighthouse 12.6.1, téléphone avec réseau lent simulé, médiane de 3 essais par page. **Pas d'écart mesurable** : un point de moins, dans le bruit de la mesure ; l'élément le plus long à s'afficher est le même (le grand titre sur l'accueil), et le temps vient du serveur de développement, qui ne compresse pas les fichiers (le CSS de 348 ko passe à 41 ko compressé en ligne). Accessibilité et bonnes pratiques restent à 100.

| Poids | Avant | Après |
|---|---|---|
| CSS | 346,1 ko (40,5 ko compressé) | 348,1 ko (41,0 ko compressé) |
| JavaScript de Kekeli | 4,6 ko (1,8 ko compressé) | 4,8 ko (1,9 ko compressé) |
| Service worker et manifeste | aucun | 1,5 ko et 0,8 ko |
| Logo dans la page | soleil, 0,4 ko | symbole 0,7 ko, nom 2,5 ko |

Rien n'a été annulé : la lueur et l'aube ne coûtent que 0,5 ko de CSS compressé et aucun flou recalculé (seulement des ombres et un fondu d'opacité).

## Reçu PDF

Rendu par le vrai moteur de documents de Kekeli (en local : la clé E2B n'est pas dans l'environnement de Claude), données inventées : [télécharger le reçu](logo-pwa-design/recu-essai-KEK-2026-000001.pdf). Toujours une seule page.

| Avant | Après |
|---|---|
| ![Avant](logo-pwa-design/recu-avant.png) | ![Après](logo-pwa-design/recu-apres.jpg) |

## E-mail (reçu de paiement)

| Avant | Après, clair | Après, sombre |
|---|---|---|
| ![Avant](logo-pwa-design/emails/avant-email-recu.jpg) | ![Après](logo-pwa-design/emails/apres-email-recu.jpg) | ![Après, sombre](logo-pwa-design/emails/apres-email-recu-sombre.jpg) |

## Sur le téléphone

### Accueil

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-01-accueil.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-01-accueil.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-01-accueil.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-01-accueil.jpg) |

### Connexion

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-02-connexion.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-02-connexion.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-02-connexion.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-02-connexion.jpg) |

### Connexion : le code reçu par e-mail

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-03-connexion-code.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-03-connexion-code.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-03-connexion-code.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-03-connexion-code.jpg) |

### Connexion avec un mot de passe

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-04-connexion-mot-de-passe.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-04-connexion-mot-de-passe.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-04-connexion-mot-de-passe.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-04-connexion-mot-de-passe.jpg) |

### « Nouveau » (accueil connecté)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-08-nouveau.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-08-nouveau.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-08-nouveau.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-08-nouveau.jpg) |

### Discussion

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-09-discussion.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-09-discussion.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-09-discussion.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-09-discussion.jpg) |

### Réflexion en cours

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-10-reflexion-en-cours.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-10-reflexion-en-cours.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-10-reflexion-en-cours.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-10-reflexion-en-cours.jpg) |

### Site terminé

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-11-site-termine.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-11-site-termine.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-11-site-termine.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-11-site-termine.jpg) |

### Barre latérale (menu du téléphone)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-16-menu-telephone.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-16-menu-telephone.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-16-menu-telephone.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-16-menu-telephone.jpg) |

### Réglages → Formule et reçus

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-21-reglages-formule-et-recus.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-21-reglages-formule-et-recus.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-21-reglages-formule-et-recus.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-21-reglages-formule-et-recus.jpg) |

### Formules

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-23-formules.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-23-formules.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-23-formules.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-23-formules.jpg) |

### Politique de confidentialité (mise à jour)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-06-confidentialite.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-06-confidentialite.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-06-confidentialite.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-06-confidentialite.jpg) |

### Page introuvable

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/telephone-clair-07-page-introuvable.jpg) | ![Après](logo-pwa-design/apres/telephone-clair-07-page-introuvable.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/telephone-sombre-07-page-introuvable.jpg) | ![Après](logo-pwa-design/apres/telephone-sombre-07-page-introuvable.jpg) |

## Sur l’ordinateur

### Accueil

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-01-accueil.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-01-accueil.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-01-accueil.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-01-accueil.jpg) |

### Connexion

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-02-connexion.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-02-connexion.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-02-connexion.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-02-connexion.jpg) |

### Connexion : le code reçu par e-mail

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-03-connexion-code.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-03-connexion-code.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-03-connexion-code.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-03-connexion-code.jpg) |

### Connexion avec un mot de passe

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-04-connexion-mot-de-passe.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-04-connexion-mot-de-passe.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-04-connexion-mot-de-passe.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-04-connexion-mot-de-passe.jpg) |

### « Nouveau » (accueil connecté)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-08-nouveau.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-08-nouveau.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-08-nouveau.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-08-nouveau.jpg) |

### Discussion

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-09-discussion.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-09-discussion.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-09-discussion.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-09-discussion.jpg) |

### Réflexion en cours

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-10-reflexion-en-cours.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-10-reflexion-en-cours.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-10-reflexion-en-cours.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-10-reflexion-en-cours.jpg) |

### Site terminé

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-11-site-termine.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-11-site-termine.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-11-site-termine.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-11-site-termine.jpg) |

### Barre latérale (menu du téléphone)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-16-menu-telephone.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-16-menu-telephone.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-16-menu-telephone.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-16-menu-telephone.jpg) |

### Réglages → Formule et reçus

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-21-reglages-formule-et-recus.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-21-reglages-formule-et-recus.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-21-reglages-formule-et-recus.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-21-reglages-formule-et-recus.jpg) |

### Formules

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-23-formules.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-23-formules.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-23-formules.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-23-formules.jpg) |

### Politique de confidentialité (mise à jour)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-06-confidentialite.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-06-confidentialite.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-06-confidentialite.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-06-confidentialite.jpg) |

### Page introuvable

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](logo-pwa-design/avant/ordinateur-clair-07-page-introuvable.jpg) | ![Après](logo-pwa-design/apres/ordinateur-clair-07-page-introuvable.jpg) |
| Sombre | ![Avant](logo-pwa-design/avant/ordinateur-sombre-07-page-introuvable.jpg) | ![Après](logo-pwa-design/apres/ordinateur-sombre-07-page-introuvable.jpg) |
