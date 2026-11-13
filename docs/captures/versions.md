# Versions et retour en arrière — captures

Pull request « Versions et retour en arrière », 13 novembre 2026. Détails : `docs/plan.md`, partie 26.17.

Captures prises pendant l’essai réel (vrai Runner, vrais ordinateurs E2B, vrai agent) sur un téléphone simulé (Pixel 7) et sur un ordinateur, en mode clair et en mode sombre. Pour les refaire : `node scripts/captures-versions.js http://127.0.0.1:8123 docs/captures/versions "Maquis Chez Ama"` (et `GENRE=webapp` pour l’application).

## Le site vitrine

### La liste des versions

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/site-telephone-1-liste-des-versions.png) | ![](versions/site-telephone-sombre-1-liste-des-versions.png) | ![](versions/site-ordinateur-1-liste-des-versions.png) | ![](versions/site-ordinateur-sombre-1-liste-des-versions.png) |

### Une ancienne version dans l’aperçu, avec le bandeau

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/site-telephone-2-apercu-ancienne-version.png) | ![](versions/site-telephone-sombre-2-apercu-ancienne-version.png) | ![](versions/site-ordinateur-2-apercu-ancienne-version.png) | ![](versions/site-ordinateur-sombre-2-apercu-ancienne-version.png) |

### La confirmation de restauration

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/site-telephone-3-confirmation-restauration.png) | ![](versions/site-telephone-sombre-3-confirmation-restauration.png) | ![](versions/site-ordinateur-3-confirmation-restauration.png) | ![](versions/site-ordinateur-sombre-3-confirmation-restauration.png) |

### La version créée par la restauration, dans la discussion

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/site-telephone-4-version-creee-par-la-restauration.png) | ![](versions/site-telephone-sombre-4-version-creee-par-la-restauration.png) | ![](versions/site-ordinateur-4-version-creee-par-la-restauration.png) | ![](versions/site-ordinateur-sombre-4-version-creee-par-la-restauration.png) |

## L’application (Laravel)

### La liste des versions

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/application-telephone-1-liste-des-versions.png) | ![](versions/application-telephone-sombre-1-liste-des-versions.png) | ![](versions/application-ordinateur-1-liste-des-versions.png) | ![](versions/application-ordinateur-sombre-1-liste-des-versions.png) |

### Une ancienne version dans l’aperçu, avec le bandeau

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/application-telephone-2-apercu-ancienne-version.png) | ![](versions/application-telephone-sombre-2-apercu-ancienne-version.png) | ![](versions/application-ordinateur-2-apercu-ancienne-version.png) | ![](versions/application-ordinateur-sombre-2-apercu-ancienne-version.png) |

### La confirmation de restauration

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/application-telephone-3-confirmation-restauration.png) | ![](versions/application-telephone-sombre-3-confirmation-restauration.png) | ![](versions/application-ordinateur-3-confirmation-restauration.png) | ![](versions/application-ordinateur-sombre-3-confirmation-restauration.png) |

### La version créée par la restauration, dans la discussion

| Téléphone | Téléphone, sombre | Ordinateur | Ordinateur, sombre |
|---|---|---|---|
| ![](versions/application-telephone-4-version-creee-par-la-restauration.png) | ![](versions/application-telephone-sombre-4-version-creee-par-la-restauration.png) | ![](versions/application-ordinateur-4-version-creee-par-la-restauration.png) | ![](versions/application-ordinateur-sombre-4-version-creee-par-la-restauration.png) |

## Un travail arrêté au milieu

Le client touche « Arrêter » après que Kekeli a écrit une nouvelle page : le projet revient à sa dernière version qui fonctionnait.

| Téléphone | Téléphone, sombre |
|---|---|
| ![](versions/site-telephone-5-travail-arrete.png) | ![](versions/site-telephone-sombre-5-travail-arrete.png) |

## Essai réel (13 novembre 2026, `scripts/essai-versions.php`)

| Étape | Site vitrine (modèle gratuit) | Application Laravel (GPT-6.1 Terra) |
|---|---|---|
| Création puis 3 modifications | versions 1 à 4, titres « Création du site », « Ajout du texte de la version 2 »… | versions 1 à 4 |
| Aperçu actuel | VERSION-QUATRE | VERSION-QUATRE |
| « Voir » la version 2 | VERSION-DEUX, sans VERSION-QUATRE | VERSION-DEUX, sans VERSION-QUATRE |
| « Revenir à la version actuelle » | VERSION-QUATRE | VERSION-QUATRE |
| Restaurer la version 2 | version 5 « Retour à la version 2 », aperçu VERSION-DEUX | version 7 « Retour à la version 2 », aperçu VERSION-DEUX |
| Annuler la restauration | version 6 « Retour à la version 4 », aperçu VERSION-QUATRE, sauvegarde à jour | version 8 « Retour à la version 6 », aperçu VERSION-QUATRE, sauvegarde à jour |
| Chaque remise dans l’aperçu | environ 5 secondes | environ 5 secondes |

Travail arrêté au milieu (site) : arrêté après l’écriture de `apropos.html` ; le projet est resté sur la version 6 ; `apropos.html` et `galerie.html` répondent « introuvable » dans l’aperçu, la page d’accueil montre VERSION-QUATRE.

Défauts trouvés pendant l’essai de l’application et corrigés : le serveur de l’application servait encore l’ancien dossier `public` effacé (les dossiers restent maintenant en place) ; les fichiers construits ignorés par Git (par exemple `public/build`) restent en place ; les fichiers remis prennent la date du moment. Deuxième défaut corrigé après les captures : la phrase « Ce qui était déjà fait est gardé » ne s’affiche plus quand le projet est revenu à sa dernière version.

Crédit OpenAI dépensé : environ **0,09 $** (construction et 3 modifications de l’application ; le site vitrine a utilisé le modèle gratuit ; voir, restaurer et annuler n’appellent aucun modèle).
