# Messages clairs de Kekeli : captures sur un écran de téléphone simulé

Captures prises le 10 novembre 2026 sur un téléphone simulé (Pixel 7, navigateur en français), pendant des **essais réels** avec un ordinateur E2B et Gemini (offre gratuite) :

- **Partie 1** : un site d'une page pour un restaurant inventé, « Chez Ama » à Lomé (création, puis une modification) ;
- **Partie 2** : le mode développeur, sur le dépôt public `jonschlinkert/is-number`, ouvert en lecture seule (rien n'a été envoyé sur GitHub).

Les noms, numéros et prix sont inventés.

## Partie 1 : un site (essai réel)

### 1. Pendant le travail : une phrase courte à chaque étape
Kekeli dit ce qu'il fait et pourquoi, en français simple. La phrase en cours tourne ; les phrases terminées sont cochées.
![Pendant le travail](messages/site-1-01-pendant.png)

### 2. Les détails techniques restent en petit, dépliables
En touchant « 1 détail », on voit les commandes et les fichiers.
![Détails dépliés](messages/site-1-02-pendant-details.png)

### 3. À la fin : le compte rendu, toujours dans le même ordre
Ce qui a été fait ; les fichiers modifiés ; les tests et vérifications ; ce qui n'a pas marché ou reste à faire ; ce que le client doit vérifier lui-même.
![Compte rendu](messages/site-1-04-compte-rendu.png)

La page entière, avec les phrases du travail au-dessus du compte rendu :
![Fin de la demande](messages/site-1-03-fin.png)

### 4. « Voir les modifications »
Chaque fichier ajouté, modifié ou supprimé ; en vert, ce qui a été ajouté ; en rouge, ce qui a été retiré.
![Voir les modifications](messages/site-1-05-modifications.png)

### 5. « Copier le compte rendu » et « Partager sur WhatsApp »
![Copier et partager](messages/site-1-06-copie.png)

### 6. Une modification : « ajoute une section Livraison et passe la couleur en vert »
Pendant le travail :
![Pendant la modification](messages/site-2-01-pendant.png)
![Détails de la modification](messages/site-2-02-pendant-details.png)

Le compte rendu :
![Compte rendu de la modification](messages/site-2-04-compte-rendu.png)

Les modifications : l'ancienne couleur en rouge, le nouveau vert et la nouvelle section en vert.
![Modifications de la couleur et de la section](messages/site-2-05-modifications.png)

![Fin de la modification](messages/site-2-03-fin.png)
![Copier le compte rendu de la modification](messages/site-2-06-copie.png)

### 7. L'historique de tous les comptes rendus du projet
Bouton « Comptes rendus » du projet, du plus récent au plus ancien.
![Historique](messages/site-07-historique.png)
![Historique complet](messages/site-08-historique-complet.png)

## Partie 2 : le mode développeur (essai réel)

### 1. Pendant le travail
Kekeli ouvre le dépôt, reconnaît un projet Node.js et installe les outils (« Kekeli se prépare »), puis explique chaque étape : il lit la documentation, lance les tests existants, ajoute l'option, ajoute ses tests, relance les tests et enregistre son travail.
![Pendant le travail](messages/depot-1-01-pendant.png)
![Détails dépliés](messages/depot-1-02-pendant-details.png)

### 2. Le compte rendu
Les tests lancés (« npm test ») et le résultat dit par Kekeli : les 113 tests ont réussi.
![Compte rendu](messages/depot-1-04-compte-rendu.png)
![Fin de la demande](messages/depot-1-03-fin.png)

### 3. « Voir les modifications »
![Voir les modifications](messages/depot-1-05-modifications.png)

### 4. Copier, partager et historique
![Copier et partager](messages/depot-1-06-copie.png)
![Historique](messages/depot-07-historique.png)
![Historique complet](messages/depot-08-historique-complet.png)

## Coût en plus
Environ 0,3 à 0,5 FCFA par demande, soit 1 à 2 % de l'énergie d'une demande (estimation mesurée sur ces essais). Détails dans [`docs/couts.md`](../couts.md), partie « Messages clairs ».
