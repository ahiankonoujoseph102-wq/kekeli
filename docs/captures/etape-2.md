# Étape 2 — captures sur un écran de téléphone simulé

Captures prises le 1er novembre 2026 sur un téléphone simulé (Pixel 7, navigateur en français). **Tout est réel** : l'agent OpenCode a vraiment travaillé dans un ordinateur virtuel E2B, avec Gemini 3.5 Flash-Lite (offre gratuite de Google AI Studio) comme cerveau. La clé Gemini n'a jamais été placée dans l'ordinateur virtuel : c'est E2B qui l'ajoute aux requêtes en sortie.

## Partie 1 — Demander « crée un fichier bonjour.txt » et voir l'agent travailler

### 1. Un projet vide, prêt à recevoir une demande
![Projet vide](etape-2/01-projet-vide.png)

### 2. La demande est écrite
![Demande écrite](etape-2/02-demande-ecrite.png)

### 3. Kekeli se prépare (création de l'ordinateur virtuel, environ 7 secondes)
![Kekeli se prépare](etape-2/03-kekeli-se-prepare.png)

### 4. Kekeli travaille : chaque action apparaît en direct
![Kekeli travaille](etape-2/04-kekeli-travaille.png)

### 5. Résultat : la réponse de l'agent, en français
![Résultat](etape-2/05-resultat.png)

### 6. Le détail des actions de Kekeli (fichier écrit, commande lancée)
![Actions de Kekeli](etape-2/06-actions-de-kekeli.png)

## Partie 2 — Administration des fournisseurs (réservée à l'administrateur)

### 7. Page « Fournisseurs d'IA » : ordre de la liste, requêtes du jour, tokens, remise à zéro (heure de Lomé)
![Administration des fournisseurs](etape-2/07-administration-fournisseurs.png)

### 8. Le lien « Fournisseurs d'IA » dans le menu, visible seulement pour l'administrateur
![Menu de l'administrateur](etape-2/08-menu-administration.png)

## Partie 3 — Basculement automatique, avec un vrai refus de Google

Pour cet essai, la liste commençait par un modèle Gemini que l'offre gratuite refuse (« limite atteinte », code 429), suivi de Gemini 3.5 Flash-Lite.

### 9. L'utilisateur ne voit rien : sa demande est faite normalement
![Résultat après la bascule](etape-2/09-bascule-resultat.png)

### 10. Dans l'administration : le premier modèle est mis de côté jusqu'à sa remise à zéro (07:00, heure de Lomé), le second a fait le travail
![Bascule dans l'administration](etape-2/10-bascule-administration.png)

## Partie 4 — Tous les fournisseurs épuisés

Pour cet essai, la liste ne contenait que le modèle refusé.

### 11. L'utilisateur voit seulement « Kekeli est très sollicité », sans savoir qu'il s'agit d'offres gratuites ; sa demande reprendra toute seule
![Kekeli très sollicité](etape-2/11-tres-sollicite.png)

### 12. L'administrateur reçoit une alerte
![Alerte de l'administrateur](etape-2/12-alerte-administrateur.png)

## Partie 5 — Alerte à partir de 80 % de la limite du jour

Le compteur a été porté artificiellement à 421 requêtes sur la base de test, pour montrer l'alerte.

### 13. Alerte rouge : il sera bientôt temps de passer à une clé payante
![Alerte à 80 %](etape-2/13-alerte-80-pourcent.png)
