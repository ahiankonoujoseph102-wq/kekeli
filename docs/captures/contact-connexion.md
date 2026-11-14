# Contact sobre, connexion comme Claude et finitions

Captures du 14 novembre 2026 sur un téléphone simulé (Pixel 6, Chrome, 412 × 915) et sur un ordinateur (1 440 × 900), en clair et en sombre, navigateur en français. Comptes et données inventés (`scripts/preparer-captures-design.php`) ; site lancé avec un identifiant Google **simulé** (jamais un vrai). Script : `scripts/captures-contact-connexion.js`.

## Partie 1 : un seul numéro sur l'accueil

Sur l'accueil, les pages d'erreur et la page hors connexion, le contact montre seulement l'e-mail et le numéro principal (champ « Téléphone » de l'émetteur). Les trois numéros restent sur les reçus, les e-mails, le paiement, les pages légales et « Nous contacter ».

| | Téléphone | Ordinateur |
|---|---|---|
| Accueil, clair | ![](contact-connexion/telephone-clair-accueil.jpg) | ![](contact-connexion/ordinateur-clair-accueil.jpg) |
| Accueil, sombre | ![](contact-connexion/telephone-sombre-accueil.jpg) | ![](contact-connexion/ordinateur-sombre-accueil.jpg) |
| Hors connexion, clair | ![](contact-connexion/telephone-clair-hors-connexion.jpg) | ![](contact-connexion/ordinateur-clair-hors-connexion.jpg) |
| Hors connexion, sombre | ![](contact-connexion/telephone-sombre-hors-connexion.jpg) | ![](contact-connexion/ordinateur-sombre-hors-connexion.jpg) |

## Partie 2 : pages d'erreur sobres, comme Claude et ChatGPT

Le logo, un titre, une phrase courte, un seul bouton « Retour à l'accueil », et le contact réduit en une ligne discrète en bas. « Erreur 404 » en majuscules est retiré (ni Claude ni ChatGPT ne l'affichent). La page hors connexion suit la même règle (son bouton reste « Réessayer », puisque l'accueil n'est pas joignable sans réseau).

| | Téléphone | Ordinateur |
|---|---|---|
| Page introuvable, clair | ![](contact-connexion/telephone-clair-erreur-404.jpg) | ![](contact-connexion/ordinateur-clair-erreur-404.jpg) |
| Page introuvable, sombre | ![](contact-connexion/telephone-sombre-erreur-404.jpg) | ![](contact-connexion/ordinateur-sombre-erreur-404.jpg) |
| Maintenance, clair | ![](contact-connexion/telephone-clair-maintenance.jpg) | ![](contact-connexion/ordinateur-clair-maintenance.jpg) |
| Maintenance, sombre | ![](contact-connexion/telephone-sombre-maintenance.jpg) | ![](contact-connexion/ordinateur-sombre-maintenance.jpg) |

## Partie 3 : connexion comme Claude

« Continuer avec Google » en premier, puis « ou », puis l'e-mail et « Continuer avec l'e-mail ». Le bouton Google n'apparaît que lorsque `GOOGLE_CLIENT_ID` est réglé ; ici, un identifiant **simulé** pour la capture. « Créer un compte » mène à la même page, dans le même ordre (vérifié par un test).

| | Téléphone | Ordinateur |
|---|---|---|
| Connexion, clair | ![](contact-connexion/telephone-clair-connexion.jpg) | ![](contact-connexion/ordinateur-clair-connexion.jpg) |
| Connexion, sombre | ![](contact-connexion/telephone-sombre-connexion.jpg) | ![](contact-connexion/ordinateur-sombre-connexion.jpg) |
| Créer un compte, clair | ![](contact-connexion/telephone-clair-creer-un-compte.jpg) | ![](contact-connexion/ordinateur-clair-creer-un-compte.jpg) |
| Créer un compte, sombre | ![](contact-connexion/telephone-sombre-creer-un-compte.jpg) | ![](contact-connexion/ordinateur-sombre-creer-un-compte.jpg) |

## Partie 4 : « Nous contacter »

Dans le menu du compte, « Nous contacter » ouvre une petite fenêtre : l'e-mail (un toucher ouvre la messagerie), les trois numéros (un toucher lance l'appel) et « Écrire sur WhatsApp » vers le numéro principal.

| | Téléphone | Ordinateur |
|---|---|---|
| Clair | ![](contact-connexion/telephone-clair-nous-contacter.jpg) | ![](contact-connexion/ordinateur-clair-nous-contacter.jpg) |
| Sombre | ![](contact-connexion/telephone-sombre-nous-contacter.jpg) | ![](contact-connexion/ordinateur-sombre-nous-contacter.jpg) |

## Partie 5 : vérifications

- **Ancien soleil** : plus aucun reste dans le site (vues, logo, favicon, icônes du site installable, e-mails, reçu) ; vérifié par une recherche dans tout le code et par une planche de toutes les images du logo. Seule reste l'icône « soleil » du mode « Clair » dans Réglages → Apparence, comme chez Claude et ChatGPT : ce n'est pas le logo.
- **Nom** : « Kekeli » partout ; « kekeli » en minuscules seulement dans le dessin du logo. Le texte lu par les lecteurs d'écran pour le logo (site et e-mails) disait « kekeli » : il dit maintenant « Kekeli ». Un test vérifie les traductions, l'accueil, la connexion et les e-mails. Aucun changement visible : pas de nouvelle capture.
