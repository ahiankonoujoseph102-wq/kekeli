# Applications web : captures sur un écran de téléphone simulé

Captures prises le 10 novembre 2026 sur un téléphone simulé (Pixel 7, navigateur en français), pendant des **essais réels** avec un ordinateur E2B et Gemini (offre gratuite). Les noms, numéros et données sont inventés. Le navigateur d'essai affiche « mm/dd/yyyy » dans les dates vides : c'est le navigateur, l'application affiche les dates en français.

- **Partie 1** : Laravel, à partir du modèle « Réservations d'un restaurant », pour un restaurant inventé, « Restaurant Le Palmier » à Lomé ; puis une modification.
- **Partie 2** : Next.js, une application **sur mesure** à partir de zéro (banque alimentaire de Kara) ; puis une modification.
- **Partie 3** : la galerie des modèles d'applications web.

## Partie 1 : Laravel, à partir d'un modèle (essai réel)

### 1. « Nouvelle application web » : les modèles d'abord, puis la technologie
![Nouvelle application web](applications/laravel-modele-01-nouvelle-application.png)

### 2. Kekeli travaille, phrase par phrase
L'application de départ est posée, les outils installés, la base d'essai créée ; puis Kekeli personnalise.
![Pendant le travail](applications/laravel-modele-1-02-pendant.png)

### 3. Le compte rendu : tests, sécurité, application qui répond
![Compte rendu](applications/laravel-modele-1-04-compte-rendu.png)
![Fin de la demande](applications/laravel-modele-1-03-fin.png)

### 4. La carte de l'application : technologie, compte administrateur d'essai, « Mettre en ligne »
![Carte de l'application](applications/laravel-modele-05-carte-application.png)

### 5. L'application tourne dans l'aperçu
Accueil, puis connexion avec le compte administrateur d'essai, **dans le cadre de la page**, puis l'espace administrateur.
![Aperçu : accueil](applications/laravel-modele-06-apercu-accueil.png)
![Aperçu : connexion](applications/laravel-modele-07-apercu-connexion.png)
![Aperçu : administration](applications/laravel-modele-08-apercu-administration.png)

La même application ouverte dans un nouvel onglet (bouton en haut à droite de l'aperçu, utile sur iPhone) :
![Nouvel onglet](applications/laravel-modele-09-nouvel-onglet.png)

### 6. Une modification : un choix « Occasion » dans le formulaire, l'administration et un nouveau test
![Pendant la modification](applications/laravel-modele-2-02-pendant.png)
![Compte rendu de la modification](applications/laravel-modele-2-04-compte-rendu.png)

### 7. « Mettre en ligne » : guide pour l'hébergement mutualisé avec cPanel
Étapes à cocher, clé APP_KEY créée sur la page, réglages à remplir, fichiers préparés, sécurité.
![Guide cPanel](applications/laravel-modele-10-guide.png)

Le fichier ZIP prêt pour cPanel ou Hostinger : préparé par Kekeli en 16 secondes (8 Mo, application et bibliothèques, sans aucun mot de passe).
![Fichier ZIP](applications/laravel-modele-11-fichier-zip.png)

## Partie 2 : Next.js, une application sur mesure (essai réel)

### 1. « Sur mesure » : la description de l'application et le choix de Next.js
![Nouvelle application sur mesure](applications/nextjs-zero-01-nouvelle-application.png)

### 2. Kekeli construit l'application
![Pendant le travail](applications/nextjs-zero-1-02-pendant.png)

### 3. Le compte rendu (11 fichiers, 13 tests réussis, sécurité vérifiée)
![Compte rendu](applications/nextjs-zero-1-04-compte-rendu.png)
![Fin de la demande](applications/nextjs-zero-1-03-fin.png)

### 4. L'application dans l'aperçu, connexion de l'administrateur dans le cadre
Dans cet essai, le nom affiché est resté « Mon application » : le nom du modèle, gardé dans le fichier de réglages de l'essai, cachait celui choisi par Kekeli. **C'est corrigé** (le nom n'est plus répété dans ce fichier ; un test le vérifie), et les consignes demandent maintenant d'adapter aussi les textes de la page d'accueil.
![Carte de l'application](applications/nextjs-zero-05-carte-application.png)
![Aperçu : accueil](applications/nextjs-zero-06-apercu-accueil.png)
![Aperçu : connexion](applications/nextjs-zero-07-apercu-connexion.png)
![Aperçu : administration](applications/nextjs-zero-08-apercu-administration.png)
![Nouvel onglet](applications/nextjs-zero-09-nouvel-onglet.png)

### 5. Une modification : filtre par quartier dans l'administration et son test
![Pendant la modification](applications/nextjs-zero-2-02-pendant.png)
![Compte rendu de la modification](applications/nextjs-zero-2-04-compte-rendu.png)

### 6. « Mettre en ligne » : guide Vercel (base PostgreSQL Neon)
![Guide Vercel](applications/nextjs-zero-10-guide.png)

## Partie 3 : la galerie

La page « Modèles » (ouverte à tous) montre aussi les modèles d'applications web ; chaque carte ouvre « Nouvelle application web » avec le modèle déjà choisi.
![Galerie des applications web](applications/galerie-applications-web.png)

## Énergie
Modèle personnalisé : environ 25 à 30 FCFA ; application sur mesure : environ 80 FCFA ; modification : 25 à 55 FCFA (estimations mesurées sur ces essais). Détails dans [`docs/couts.md`](../couts.md), partie « Applications web ».
