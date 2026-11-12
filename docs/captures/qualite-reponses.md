# Qualité des réponses comme Claude (12 novembre 2026)

Captures prises sur un téléphone simulé (Pixel 7) et sur un ordinateur (1 440 × 900), navigateur en français, en mode clair et en mode sombre. Les données sont inventées (`scripts/preparer-captures-qualite.php`), aucune requête au modèle. Pour les refaire : `php artisan tinker --execute="require 'scripts/preparer-captures-qualite.php';"`, `php artisan serve --port=8123`, puis `node scripts/captures-qualite.js apres http://127.0.0.1:8123 docs/captures/qualite-reponses`.

## Téléphone, mode clair

### Une réponse riche : titres, gras, tableau (avant : barres « | » en texte brut)

Avant :

![Avant](qualite-reponses/avant-telephone-1-reponse-riche.png)

Après :

![Après](qualite-reponses/apres-telephone-1-reponse-riche.png)

### Le tableau sur téléphone : il tient dans l’écran et défile sur le côté si besoin

Avant :

![Avant](qualite-reponses/avant-telephone-2-tableau.png)

Après :

![Après](qualite-reponses/apres-telephone-2-tableau.png)

### La réflexion dépliée (après seulement : elle n’existait pas)

Après :

![Après](qualite-reponses/apres-telephone-3-reflexion.png)

### Une réponse simple et directe

Avant :

![Avant](qualite-reponses/avant-telephone-4-reponse-simple.png)

Après :

![Après](qualite-reponses/apres-telephone-4-reponse-simple.png)

### Un bloc de code avec son bouton « Copier » (avant : des accents graves en texte brut)

Avant :

![Avant](qualite-reponses/avant-telephone-5-bloc-de-code.png)

Après :

![Après](qualite-reponses/apres-telephone-5-bloc-de-code.png)

### Pendant la réponse : « Réflexion… » avec le minuteur, comme Claude

Avant :

![Avant](qualite-reponses/avant-telephone-6-en-cours.png)

Après :

![Après](qualite-reponses/apres-telephone-6-en-cours.png)

### La zone de saisie : le micro à côté du bouton d’envoi (avant : pas de micro)

Avant :

![Avant](qualite-reponses/avant-telephone-7-micro.png)

Après :

![Après](qualite-reponses/apres-telephone-7-micro.png)

### Micro refusé : un message clair (après seulement)

Après :

![Après](qualite-reponses/apres-telephone-8-micro-refuse.png)

## Téléphone, mode sombre

### Une réponse riche : titres, gras, tableau (avant : barres « | » en texte brut)

Avant :

![Avant](qualite-reponses/avant-telephone-sombre-1-reponse-riche.png)

Après :

![Après](qualite-reponses/apres-telephone-sombre-1-reponse-riche.png)

### Le tableau sur téléphone : il tient dans l’écran et défile sur le côté si besoin

Avant :

![Avant](qualite-reponses/avant-telephone-sombre-2-tableau.png)

Après :

![Après](qualite-reponses/apres-telephone-sombre-2-tableau.png)

### La réflexion dépliée (après seulement : elle n’existait pas)

Après :

![Après](qualite-reponses/apres-telephone-sombre-3-reflexion.png)

### Une réponse simple et directe

Avant :

![Avant](qualite-reponses/avant-telephone-sombre-4-reponse-simple.png)

Après :

![Après](qualite-reponses/apres-telephone-sombre-4-reponse-simple.png)

### Un bloc de code avec son bouton « Copier » (avant : des accents graves en texte brut)

Avant :

![Avant](qualite-reponses/avant-telephone-sombre-5-bloc-de-code.png)

Après :

![Après](qualite-reponses/apres-telephone-sombre-5-bloc-de-code.png)

### Pendant la réponse : « Réflexion… » avec le minuteur, comme Claude

Avant :

![Avant](qualite-reponses/avant-telephone-sombre-6-en-cours.png)

Après :

![Après](qualite-reponses/apres-telephone-sombre-6-en-cours.png)

### La zone de saisie : le micro à côté du bouton d’envoi (avant : pas de micro)

Avant :

![Avant](qualite-reponses/avant-telephone-sombre-7-micro.png)

Après :

![Après](qualite-reponses/apres-telephone-sombre-7-micro.png)

### Micro refusé : un message clair (après seulement)

Après :

![Après](qualite-reponses/apres-telephone-sombre-8-micro-refuse.png)

## Ordinateur, mode clair

### Une réponse riche : titres, gras, tableau (avant : barres « | » en texte brut)

Avant :

![Avant](qualite-reponses/avant-ordinateur-1-reponse-riche.png)

Après :

![Après](qualite-reponses/apres-ordinateur-1-reponse-riche.png)

### Le tableau sur téléphone : il tient dans l’écran et défile sur le côté si besoin

Avant :

![Avant](qualite-reponses/avant-ordinateur-2-tableau.png)

Après :

![Après](qualite-reponses/apres-ordinateur-2-tableau.png)

### La réflexion dépliée (après seulement : elle n’existait pas)

Après :

![Après](qualite-reponses/apres-ordinateur-3-reflexion.png)

### Une réponse simple et directe

Avant :

![Avant](qualite-reponses/avant-ordinateur-4-reponse-simple.png)

Après :

![Après](qualite-reponses/apres-ordinateur-4-reponse-simple.png)

### Un bloc de code avec son bouton « Copier » (avant : des accents graves en texte brut)

Avant :

![Avant](qualite-reponses/avant-ordinateur-5-bloc-de-code.png)

Après :

![Après](qualite-reponses/apres-ordinateur-5-bloc-de-code.png)

### Pendant la réponse : « Réflexion… » avec le minuteur, comme Claude

Avant :

![Avant](qualite-reponses/avant-ordinateur-6-en-cours.png)

Après :

![Après](qualite-reponses/apres-ordinateur-6-en-cours.png)

### La zone de saisie : le micro à côté du bouton d’envoi (avant : pas de micro)

Avant :

![Avant](qualite-reponses/avant-ordinateur-7-micro.png)

Après :

![Après](qualite-reponses/apres-ordinateur-7-micro.png)

### Micro refusé : un message clair (après seulement)

Après :

![Après](qualite-reponses/apres-ordinateur-8-micro-refuse.png)

## Ordinateur, mode sombre

### Une réponse riche : titres, gras, tableau (avant : barres « | » en texte brut)

Avant :

![Avant](qualite-reponses/avant-ordinateur-sombre-1-reponse-riche.png)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-1-reponse-riche.png)

### Le tableau sur téléphone : il tient dans l’écran et défile sur le côté si besoin

Avant :

![Avant](qualite-reponses/avant-ordinateur-sombre-2-tableau.png)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-2-tableau.png)

### La réflexion dépliée (après seulement : elle n’existait pas)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-3-reflexion.png)

### Une réponse simple et directe

Avant :

![Avant](qualite-reponses/avant-ordinateur-sombre-4-reponse-simple.png)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-4-reponse-simple.png)

### Un bloc de code avec son bouton « Copier » (avant : des accents graves en texte brut)

Avant :

![Avant](qualite-reponses/avant-ordinateur-sombre-5-bloc-de-code.png)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-5-bloc-de-code.png)

### Pendant la réponse : « Réflexion… » avec le minuteur, comme Claude

Avant :

![Avant](qualite-reponses/avant-ordinateur-sombre-6-en-cours.png)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-6-en-cours.png)

### La zone de saisie : le micro à côté du bouton d’envoi (avant : pas de micro)

Avant :

![Avant](qualite-reponses/avant-ordinateur-sombre-7-micro.png)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-7-micro.png)

### Micro refusé : un message clair (après seulement)

Après :

![Après](qualite-reponses/apres-ordinateur-sombre-8-micro-refuse.png)

