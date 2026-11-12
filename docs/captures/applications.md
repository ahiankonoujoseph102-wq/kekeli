# Applications : captures

## Création d'applications comme Claude Code (12 novembre 2026)

Essai **réel** de bout en bout : un compte d'essai inventé (formule Pro, Togo) demande, depuis « Que voulez-vous créer aujourd’hui ? », une application de rendez-vous pour un salon inventé, « Belle Afi ». Kekeli propose un plan ; le client touche « Construire ce plan » ; GPT-6.1 Terra construit l'application dans un ordinateur E2B en 5 minutes 24 (la clé OpenAI reste dans le Runner : relais) ; l'application tourne dans l'aperçu avec sa base d'essai locale. Captures : téléphone simulé (Pixel 7) et ordinateur (1 440 × 900), clair et sombre. Scripts : `scripts/essai-application.js` puis `scripts/captures-applications.js`.

### L'essai sur téléphone, dans l'ordre

![La demande et le plan](applications/essai-telephone-1-plan.png)

![Construction en cours](applications/essai-telephone-2-construction-en-cours.png)

![Construction terminée](applications/essai-telephone-3-construction-terminee.png)

![L'application dans l'aperçu](applications/essai-telephone-4-apercu.png)

![Variables secrètes](applications/essai-telephone-5-variables-secretes.png)

### Téléphone, mode clair

**Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)**

![Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)](applications/telephone-1-plan.png)

**La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests**

![La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests](applications/telephone-2-construction.png)

**Le message final, court et clair**

![Le message final, court et clair](applications/telephone-3-message-final.png)

**L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai**

![L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai](applications/telephone-4-apercu.png)

**Les Variables secrètes : la valeur n’est jamais réaffichée**

![Les Variables secrètes : la valeur n’est jamais réaffichée](applications/telephone-5-variables-secretes.png)

### Téléphone, mode sombre

**Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)**

![Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)](applications/telephone-sombre-1-plan.png)

**La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests**

![La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests](applications/telephone-sombre-2-construction.png)

**Le message final, court et clair**

![Le message final, court et clair](applications/telephone-sombre-3-message-final.png)

**L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai**

![L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai](applications/telephone-sombre-4-apercu.png)

**Les Variables secrètes : la valeur n’est jamais réaffichée**

![Les Variables secrètes : la valeur n’est jamais réaffichée](applications/telephone-sombre-5-variables-secretes.png)

### Ordinateur, mode clair

**Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)**

![Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)](applications/ordinateur-1-plan.png)

**La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests**

![La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests](applications/ordinateur-2-construction.png)

**Le message final, court et clair**

![Le message final, court et clair](applications/ordinateur-3-message-final.png)

**L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai**

![L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai](applications/ordinateur-4-apercu.png)

**Les Variables secrètes : la valeur n’est jamais réaffichée**

![Les Variables secrètes : la valeur n’est jamais réaffichée](applications/ordinateur-5-variables-secretes.png)

### Ordinateur, mode sombre

**Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)**

![Le plan proposé par Kekeli, avec « Construire ce plan » (mode plan, comme Claude Code)](applications/ordinateur-sombre-1-plan.png)

**La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests**

![La construction : les actions dépliées montrent les fichiers lus et modifiés, les commandes et les tests](applications/ordinateur-sombre-2-construction.png)

**Le message final, court et clair**

![Le message final, court et clair](applications/ordinateur-sombre-3-message-final.png)

**L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai**

![L’application dans l’aperçu, avec sa base d’essai locale et le compte administrateur d’essai](applications/ordinateur-sombre-4-apercu.png)

**Les Variables secrètes : la valeur n’est jamais réaffichée**

![Les Variables secrètes : la valeur n’est jamais réaffichée](applications/ordinateur-sombre-5-variables-secretes.png)

### Banc d'essai : la même application construite par chaque modèle (téléphone simulé)

Détails et choix : `docs/plan.md`, partie 27 ; résultats : `runner/mesures/banc-construction.json`.

**GPT-6.1 Sol (formules payantes) : terminé en 7,7 minutes, 36 tests réussis**

![openai-gpt-6-1-sol 1-accueil.png](applications/banc/openai-gpt-6-1-sol/1-accueil.png)
![openai-gpt-6-1-sol 2-administrateur-connecte.png](applications/banc/openai-gpt-6-1-sol/2-administrateur-connecte.png)
![openai-gpt-6-1-sol 3-mes-rendez-vous.png](applications/banc/openai-gpt-6-1-sol/3-mes-rendez-vous.png)
![openai-gpt-6-1-sol 4-admin.png](applications/banc/openai-gpt-6-1-sol/4-admin.png)

**GPT-6.1 Terra (formule Gratuite) : terminé en 5 minutes, 24 tests réussis**

![openai-gpt-6-1-terra 1-accueil.png](applications/banc/openai-gpt-6-1-terra/1-accueil.png)
![openai-gpt-6-1-terra 2-administrateur-connecte.png](applications/banc/openai-gpt-6-1-terra/2-administrateur-connecte.png)
![openai-gpt-6-1-terra 3-espace.png](applications/banc/openai-gpt-6-1-terra/3-espace.png)
![openai-gpt-6-1-terra 3-mes-rendez-vous.png](applications/banc/openai-gpt-6-1-terra/3-mes-rendez-vous.png)
![openai-gpt-6-1-terra 4-admin.png](applications/banc/openai-gpt-6-1-terra/4-admin.png)

**Gemini 3.5 Flash-Lite (secours gratuit) : inachevé après 26 minutes et 9 reprises**

![gemini-gemini-3-5-flash-lite 1-accueil.png](applications/banc/gemini-gemini-3-5-flash-lite/1-accueil.png)
![gemini-gemini-3-5-flash-lite 2-administrateur-connecte.png](applications/banc/gemini-gemini-3-5-flash-lite/2-administrateur-connecte.png)
![gemini-gemini-3-5-flash-lite 3-espace.png](applications/banc/gemini-gemini-3-5-flash-lite/3-espace.png)
![gemini-gemini-3-5-flash-lite 4-admin.png](applications/banc/gemini-gemini-3-5-flash-lite/4-admin.png)

Gemini 3.6 Flash n'a rien produit : sa limite gratuite du jour (environ 20 requêtes) a été atteinte pendant la lecture du socle.

---

## Sites avec base de données (10 novembre 2026, captures plus anciennes)


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
