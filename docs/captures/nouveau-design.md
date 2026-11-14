# Nouveau design de Kekeli, partout : AVANT et APRÈS

Captures du 14 novembre 2026, sur un téléphone simulé (Pixel 6, Chrome, 412 × 915) et sur un ordinateur (1 440 × 900), en clair et en sombre, navigateur en français. **Comptes, projets, paiements et reçus inventés** (`scripts/preparer-captures-design.php`) ; « avant » est le site juste avant ce changement (finitions de la pull request 53 comprises). Scripts : `scripts/captures-design.js` (pages) et `scripts/captures-emails-design.js` (e-mails). Étude préalable : [`docs/design-etude.md`](../design-etude.md) ; détails : `docs/plan.md`, partie 26.23.

Ce qui change partout : un **système de design défini à un seul endroit** (couleurs, typographie, coins, ombres, animations), des **contrastes lisibles** (accessibilité 100 sur 100 au lieu de 95), des **couleurs d'état douces** (plus de rouge, de vert ou de bleu vifs), des **pages d'erreur et de maintenance** en français avec le contact (au lieu des pages anglaises de Laravel), des **états vides** avec une icône et une phrase, des **animations sobres** (apparition des messages, soleil qui tourne pendant le travail, texte qui scintille pendant la réflexion, squelette pendant que l'aperçu se prépare, ouverture des menus et des fenêtres), le **contact de Kekeli** là où l'on cherche à nous joindre, et les **e-mails et le reçu** aux mêmes couleurs, avec le logo.

## Mesures (Lighthouse, téléphone, réseau lent simulé, médiane de 3 essais)

| Page | Performance avant | Performance après | Accessibilité avant | Accessibilité après |
|---|---|---|---|---|
| Accueil | 60 | 77 | 95 | **100** |
| Connexion | 60 | 60 | 95 | **100** |
| Discussion | 80 | 67 | 95 | **100** |
| Formules | 60 | 60 | 95 | **100** |

L'accessibilité perdait 5 points sur chaque page à cause du contraste des petits textes gris : corrigé partout. **La performance ne change pas de façon mesurable** : les écarts (60 à 80) viennent du serveur de développement, qui ne compresse pas les fichiers et répond plus ou moins vite ; un nouvel essai de la discussion donne 58 à 72 pour les deux versions. En ligne, la compression divise par environ 8 le poids du CSS.

| Poids | Avant | Après |
|---|---|---|
| CSS | 342,2 ko (40,0 ko compressé) | 346,1 ko (41,1 ko compressé) |
| JavaScript de Kekeli | 4,6 ko (1,8 ko compressé) | 4,6 ko (1,8 ko compressé) |
| Bibliothèque d'animation | aucune | aucune |

Le CSS gagne environ 1 ko compressé (le système de design et les animations, seulement `opacity` et `transform`) ; aucun JavaScript ajouté. Rien n'a été annulé : chaque ajout améliore l'accessibilité ou remplace un écran qui n'existait pas (pages d'erreur, états vides).

## Reçu PDF

Rendu par le vrai moteur de documents dans l'atelier E2B (22 secondes), données inventées : [télécharger le reçu](nouveau-design/recu-essai-KEK-2026-000001.pdf). Couleur encre du système, contact de Kekeli, chaque numéro sur une seule ligne.

![Reçu](nouveau-design/recu-apres.png)

## Sur le téléphone

### Accueil

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-01-accueil.jpg) | ![Après](nouveau-design/apres/telephone-clair-01-accueil.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-01-accueil.jpg) | ![Après](nouveau-design/apres/telephone-sombre-01-accueil.jpg) |

### Connexion

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-02-connexion.jpg) | ![Après](nouveau-design/apres/telephone-clair-02-connexion.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-02-connexion.jpg) | ![Après](nouveau-design/apres/telephone-sombre-02-connexion.jpg) |

### Connexion : le code reçu par e-mail

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-03-connexion-code.jpg) | ![Après](nouveau-design/apres/telephone-clair-03-connexion-code.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-03-connexion-code.jpg) | ![Après](nouveau-design/apres/telephone-sombre-03-connexion-code.jpg) |

### Connexion avec un mot de passe

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-04-connexion-mot-de-passe.jpg) | ![Après](nouveau-design/apres/telephone-clair-04-connexion-mot-de-passe.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-04-connexion-mot-de-passe.jpg) | ![Après](nouveau-design/apres/telephone-sombre-04-connexion-mot-de-passe.jpg) |

### Conditions d’utilisation

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-05-conditions.jpg) | ![Après](nouveau-design/apres/telephone-clair-05-conditions.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-05-conditions.jpg) | ![Après](nouveau-design/apres/telephone-sombre-05-conditions.jpg) |

### Politique de confidentialité

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-06-confidentialite.jpg) | ![Après](nouveau-design/apres/telephone-clair-06-confidentialite.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-06-confidentialite.jpg) | ![Après](nouveau-design/apres/telephone-sombre-06-confidentialite.jpg) |

### Page introuvable (erreur 404)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-07-page-introuvable.jpg) | ![Après](nouveau-design/apres/telephone-clair-07-page-introuvable.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-07-page-introuvable.jpg) | ![Après](nouveau-design/apres/telephone-sombre-07-page-introuvable.jpg) |

### « Nouveau » (la grande question)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-08-nouveau.jpg) | ![Après](nouveau-design/apres/telephone-clair-08-nouveau.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-08-nouveau.jpg) | ![Après](nouveau-design/apres/telephone-sombre-08-nouveau.jpg) |

### Discussion : réponse, réflexion repliée, tableau, code

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-09-discussion.jpg) | ![Après](nouveau-design/apres/telephone-clair-09-discussion.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-09-discussion.jpg) | ![Après](nouveau-design/apres/telephone-sombre-09-discussion.jpg) |

### Kekeli réfléchit (soleil qui tourne, texte qui scintille)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-10-reflexion-en-cours.jpg) | ![Après](nouveau-design/apres/telephone-clair-10-reflexion-en-cours.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-10-reflexion-en-cours.jpg) | ![Après](nouveau-design/apres/telephone-sombre-10-reflexion-en-cours.jpg) |

### Site terminé : actions repliées et dernier message

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-11-site-termine.jpg) | ![Après](nouveau-design/apres/telephone-clair-11-site-termine.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-11-site-termine.jpg) | ![Après](nouveau-design/apres/telephone-sombre-11-site-termine.jpg) |

### Document (aperçu des pages)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-12-document.jpg) | ![Après](nouveau-design/apres/telephone-clair-12-document.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-12-document.jpg) | ![Après](nouveau-design/apres/telephone-sombre-12-document.jpg) |

### Présentation

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-13-presentation.jpg) | ![Après](nouveau-design/apres/telephone-clair-13-presentation.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-13-presentation.jpg) | ![Après](nouveau-design/apres/telephone-sombre-13-presentation.jpg) |

### Affiche

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-14-affiche.jpg) | ![Après](nouveau-design/apres/telephone-clair-14-affiche.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-14-affiche.jpg) | ![Après](nouveau-design/apres/telephone-sombre-14-affiche.jpg) |

### Vidéo

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-15-video.jpg) | ![Après](nouveau-design/apres/telephone-clair-15-video.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-15-video.jpg) | ![Après](nouveau-design/apres/telephone-sombre-15-video.jpg) |

### Barre latérale (menu sur téléphone)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-16-menu-telephone.jpg) | ![Après](nouveau-design/apres/telephone-clair-16-menu-telephone.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-16-menu-telephone.jpg) | ![Après](nouveau-design/apres/telephone-sombre-16-menu-telephone.jpg) |

### Rechercher

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-17-recherche.jpg) | ![Après](nouveau-design/apres/telephone-clair-17-recherche.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-17-recherche.jpg) | ![Après](nouveau-design/apres/telephone-sombre-17-recherche.jpg) |

### Menu du compte (énergie, « Nous contacter »)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-18-menu-du-compte.jpg) | ![Après](nouveau-design/apres/telephone-clair-18-menu-du-compte.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-18-menu-du-compte.jpg) | ![Après](nouveau-design/apres/telephone-sombre-18-menu-du-compte.jpg) |

### Réglages : Profil

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-19-reglages-profil.jpg) | ![Après](nouveau-design/apres/telephone-clair-19-reglages-profil.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-19-reglages-profil.jpg) | ![Après](nouveau-design/apres/telephone-sombre-19-reglages-profil.jpg) |

### Réglages : Apparence

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-20-reglages-apparence.jpg) | ![Après](nouveau-design/apres/telephone-clair-20-reglages-apparence.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-20-reglages-apparence.jpg) | ![Après](nouveau-design/apres/telephone-sombre-20-reglages-apparence.jpg) |

### Réglages : Formule et « Mes reçus »

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-21-reglages-formule-et-recus.jpg) | ![Après](nouveau-design/apres/telephone-clair-21-reglages-formule-et-recus.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-21-reglages-formule-et-recus.jpg) | ![Après](nouveau-design/apres/telephone-sombre-21-reglages-formule-et-recus.jpg) |

### Réglages : Mémoire

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-22-reglages-memoire.jpg) | ![Après](nouveau-design/apres/telephone-clair-22-reglages-memoire.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-22-reglages-memoire.jpg) | ![Après](nouveau-design/apres/telephone-sombre-22-reglages-memoire.jpg) |

### Formules (avec le contact)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-23-formules.jpg) | ![Après](nouveau-design/apres/telephone-clair-23-formules.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-23-formules.jpg) | ![Après](nouveau-design/apres/telephone-sombre-23-formules.jpg) |

### Paiement (avec le contact)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-24-paiement.jpg) | ![Après](nouveau-design/apres/telephone-clair-24-paiement.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-24-paiement.jpg) | ![Après](nouveau-design/apres/telephone-sombre-24-paiement.jpg) |

### Énergie épuisée

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-25-energie-epuisee.jpg) | ![Après](nouveau-design/apres/telephone-clair-25-energie-epuisee.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-25-energie-epuisee.jpg) | ![Après](nouveau-design/apres/telephone-sombre-25-energie-epuisee.jpg) |

### État vide (mémoire d’un nouveau compte)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-26-etats-vides.jpg) | ![Après](nouveau-design/apres/telephone-clair-26-etats-vides.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-26-etats-vides.jpg) | ![Après](nouveau-design/apres/telephone-sombre-26-etats-vides.jpg) |

### Administration : fournisseurs (tableau de bord)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-27-admin-tableau-de-bord.jpg) | ![Après](nouveau-design/apres/telephone-clair-27-admin-tableau-de-bord.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-27-admin-tableau-de-bord.jpg) | ![Après](nouveau-design/apres/telephone-sombre-27-admin-tableau-de-bord.jpg) |

### Administration : paiements

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-28-admin-paiements.jpg) | ![Après](nouveau-design/apres/telephone-clair-28-admin-paiements.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-28-admin-paiements.jpg) | ![Après](nouveau-design/apres/telephone-sombre-28-admin-paiements.jpg) |

### Administration : e-mails

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-29-admin-emails.jpg) | ![Après](nouveau-design/apres/telephone-clair-29-admin-emails.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-29-admin-emails.jpg) | ![Après](nouveau-design/apres/telephone-sombre-29-admin-emails.jpg) |

### Administration : réglages

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-30-admin-reglages.jpg) | ![Après](nouveau-design/apres/telephone-clair-30-admin-reglages.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-30-admin-reglages.jpg) | ![Après](nouveau-design/apres/telephone-sombre-30-admin-reglages.jpg) |

### Maintenance (erreur 503)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/telephone-clair-31-maintenance.jpg) | ![Après](nouveau-design/apres/telephone-clair-31-maintenance.jpg) |
| Sombre | ![Avant](nouveau-design/avant/telephone-sombre-31-maintenance.jpg) | ![Après](nouveau-design/apres/telephone-sombre-31-maintenance.jpg) |

## Sur l’ordinateur

### Accueil

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-01-accueil.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-01-accueil.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-01-accueil.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-01-accueil.jpg) |

### Connexion

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-02-connexion.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-02-connexion.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-02-connexion.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-02-connexion.jpg) |

### Connexion : le code reçu par e-mail

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-03-connexion-code.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-03-connexion-code.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-03-connexion-code.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-03-connexion-code.jpg) |

### Connexion avec un mot de passe

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-04-connexion-mot-de-passe.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-04-connexion-mot-de-passe.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-04-connexion-mot-de-passe.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-04-connexion-mot-de-passe.jpg) |

### Conditions d’utilisation

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-05-conditions.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-05-conditions.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-05-conditions.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-05-conditions.jpg) |

### Politique de confidentialité

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-06-confidentialite.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-06-confidentialite.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-06-confidentialite.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-06-confidentialite.jpg) |

### Page introuvable (erreur 404)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-07-page-introuvable.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-07-page-introuvable.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-07-page-introuvable.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-07-page-introuvable.jpg) |

### « Nouveau » (la grande question)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-08-nouveau.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-08-nouveau.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-08-nouveau.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-08-nouveau.jpg) |

### Discussion : réponse, réflexion repliée, tableau, code

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-09-discussion.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-09-discussion.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-09-discussion.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-09-discussion.jpg) |

### Kekeli réfléchit (soleil qui tourne, texte qui scintille)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-10-reflexion-en-cours.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-10-reflexion-en-cours.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-10-reflexion-en-cours.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-10-reflexion-en-cours.jpg) |

### Site terminé : actions repliées et dernier message

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-11-site-termine.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-11-site-termine.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-11-site-termine.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-11-site-termine.jpg) |

### Document (aperçu des pages)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-12-document.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-12-document.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-12-document.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-12-document.jpg) |

### Présentation

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-13-presentation.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-13-presentation.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-13-presentation.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-13-presentation.jpg) |

### Affiche

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-14-affiche.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-14-affiche.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-14-affiche.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-14-affiche.jpg) |

### Vidéo

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-15-video.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-15-video.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-15-video.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-15-video.jpg) |

### Barre latérale (menu sur téléphone)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-16-menu-telephone.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-16-menu-telephone.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-16-menu-telephone.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-16-menu-telephone.jpg) |

### Rechercher

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-17-recherche.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-17-recherche.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-17-recherche.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-17-recherche.jpg) |

### Menu du compte (énergie, « Nous contacter »)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-18-menu-du-compte.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-18-menu-du-compte.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-18-menu-du-compte.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-18-menu-du-compte.jpg) |

### Réglages : Profil

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-19-reglages-profil.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-19-reglages-profil.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-19-reglages-profil.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-19-reglages-profil.jpg) |

### Réglages : Apparence

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-20-reglages-apparence.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-20-reglages-apparence.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-20-reglages-apparence.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-20-reglages-apparence.jpg) |

### Réglages : Formule et « Mes reçus »

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-21-reglages-formule-et-recus.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-21-reglages-formule-et-recus.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-21-reglages-formule-et-recus.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-21-reglages-formule-et-recus.jpg) |

### Réglages : Mémoire

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-22-reglages-memoire.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-22-reglages-memoire.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-22-reglages-memoire.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-22-reglages-memoire.jpg) |

### Formules (avec le contact)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-23-formules.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-23-formules.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-23-formules.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-23-formules.jpg) |

### Paiement (avec le contact)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-24-paiement.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-24-paiement.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-24-paiement.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-24-paiement.jpg) |

### Énergie épuisée

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-25-energie-epuisee.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-25-energie-epuisee.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-25-energie-epuisee.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-25-energie-epuisee.jpg) |

### État vide (mémoire d’un nouveau compte)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-26-etats-vides.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-26-etats-vides.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-26-etats-vides.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-26-etats-vides.jpg) |

### Administration : fournisseurs (tableau de bord)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-27-admin-tableau-de-bord.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-27-admin-tableau-de-bord.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-27-admin-tableau-de-bord.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-27-admin-tableau-de-bord.jpg) |

### Administration : paiements

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-28-admin-paiements.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-28-admin-paiements.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-28-admin-paiements.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-28-admin-paiements.jpg) |

### Administration : e-mails

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-29-admin-emails.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-29-admin-emails.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-29-admin-emails.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-29-admin-emails.jpg) |

### Administration : réglages

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-30-admin-reglages.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-30-admin-reglages.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-30-admin-reglages.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-30-admin-reglages.jpg) |

### Maintenance (erreur 503)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/avant/ordinateur-clair-31-maintenance.jpg) | ![Après](nouveau-design/apres/ordinateur-clair-31-maintenance.jpg) |
| Sombre | ![Avant](nouveau-design/avant/ordinateur-sombre-31-maintenance.jpg) | ![Après](nouveau-design/apres/ordinateur-sombre-31-maintenance.jpg) |

## E-mails (téléphone)

### Code de connexion

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-1-code-de-connexion.jpg) | ![Après](nouveau-design/emails-apres/email-1-code-de-connexion.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-1-code-de-connexion-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-1-code-de-connexion-sombre.jpg) |

### Bienvenue

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-2-bienvenue.jpg) | ![Après](nouveau-design/emails-apres/email-2-bienvenue.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-2-bienvenue-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-2-bienvenue-sombre.jpg) |

### Paiement à vérifier (administrateur)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-3-paiement-a-verifier.jpg) | ![Après](nouveau-design/emails-apres/email-3-paiement-a-verifier.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-3-paiement-a-verifier-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-3-paiement-a-verifier-sombre.jpg) |

### Paiement refusé (avec le contact)

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-4-paiement-refuse.jpg) | ![Après](nouveau-design/emails-apres/email-4-paiement-refuse.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-4-paiement-refuse-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-4-paiement-refuse-sombre.jpg) |

### Paiement validé et reçu

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-5-paiement-valide-recu.jpg) | ![Après](nouveau-design/emails-apres/email-5-paiement-valide-recu.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-5-paiement-valide-recu-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-5-paiement-valide-recu-sombre.jpg) |

### Formule finie demain

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-6-formule-finit-demain.jpg) | ![Après](nouveau-design/emails-apres/email-6-formule-finit-demain.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-6-formule-finit-demain-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-6-formule-finit-demain-sombre.jpg) |

### Formule terminée

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-7-formule-terminee.jpg) | ![Après](nouveau-design/emails-apres/email-7-formule-terminee.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-7-formule-terminee-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-7-formule-terminee-sombre.jpg) |

### Mot de passe oublié

| | Avant | Après |
|---|---|---|
| Clair | ![Avant](nouveau-design/emails-avant/email-8-mot-de-passe.jpg) | ![Après](nouveau-design/emails-apres/email-8-mot-de-passe.jpg) |
| Sombre | ![Avant](nouveau-design/emails-avant/email-8-mot-de-passe-sombre.jpg) | ![Après](nouveau-design/emails-apres/email-8-mot-de-passe-sombre.jpg) |
