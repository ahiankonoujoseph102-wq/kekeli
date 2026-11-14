# E-mails avec Brevo, reçus et connexion comme Claude (14 novembre 2026)

Captures prises sur un téléphone simulé (Pixel 7) et sur un ordinateur, en mode clair et en mode sombre, avec des comptes et des données inventés (`scripts/captures-emails-connexion.js`). Détails : `docs/plan.md`, partie 26.22.

**Envoi réel** : l'environnement de Claude ne laisse sortir que le HTTPS ; le serveur SMTP de Brevo y est injoignable (connexion refusée sur les ports 587, 465, 2525 et 25). Le seul e-mail réel tenté (un reçu d'essai vers l'adresse de l'administrateur) n'est donc pas parti ; l'essai réel est à faire à la mise en ligne (`docs/mise-en-ligne.md`, partie 9). Pour les captures, l'envoi était simulé.

## Le reçu PDF d'essai

Mis en page par le vrai moteur de documents de Kekeli dans l'atelier E2B (23 secondes), données inventées : [télécharger le reçu KEK-2026-000001](emails-connexion/recu-essai-KEK-2026-000001.pdf).

![Reçu KEK-2026-000001](emails-connexion/recu-essai-page.png)

## 1. La page d'entrée, sans Google (identifiants absents : le bouton n'apparaît pas)

| Téléphone | Téléphone (sombre) |
|---|---|
| ![](emails-connexion/1-entree-sans-google-telephone.png) | ![](emails-connexion/1-entree-sans-google-telephone-sombre.png) |

| Ordinateur | Ordinateur (sombre) |
|---|---|
| ![](emails-connexion/1-entree-sans-google-ordinateur.png) | ![](emails-connexion/1-entree-sans-google-ordinateur-sombre.png) |

## 2. La page d'entrée avec Google (site d'essai lancé avec des identifiants Google inventés)

| Téléphone | Téléphone (sombre) |
|---|---|
| ![](emails-connexion/2-entree-avec-google-telephone.png) | ![](emails-connexion/2-entree-avec-google-telephone-sombre.png) |

| Ordinateur | Ordinateur (sombre) |
|---|---|
| ![](emails-connexion/2-entree-avec-google-ordinateur.png) | ![](emails-connexion/2-entree-avec-google-ordinateur-sombre.png) |

## 3. La saisie du code

| Téléphone | Téléphone (sombre) |
|---|---|
| ![](emails-connexion/3-saisie-du-code-telephone.png) | ![](emails-connexion/3-saisie-du-code-telephone-sombre.png) |

| Ordinateur | Ordinateur (sombre) |
|---|---|
| ![](emails-connexion/3-saisie-du-code-ordinateur.png) | ![](emails-connexion/3-saisie-du-code-ordinateur-sombre.png) |

## 4. Nouveau compte : le nom, en une étape

| Téléphone | Téléphone (sombre) |
|---|---|
| ![](emails-connexion/4-nouveau-compte-nom-telephone.png) | ![](emails-connexion/4-nouveau-compte-nom-telephone-sombre.png) |

| Ordinateur | Ordinateur (sombre) |
|---|---|
| ![](emails-connexion/4-nouveau-compte-nom-ordinateur.png) | ![](emails-connexion/4-nouveau-compte-nom-ordinateur-sombre.png) |

## 5. Réglages → Formule → « Mes reçus »

| Téléphone | Téléphone (sombre) |
|---|---|
| ![](emails-connexion/5-mes-recus-telephone.png) | ![](emails-connexion/5-mes-recus-telephone-sombre.png) |

| Ordinateur | Ordinateur (sombre) |
|---|---|
| ![](emails-connexion/5-mes-recus-ordinateur.png) | ![](emails-connexion/5-mes-recus-ordinateur-sombre.png) |

## 6. Le journal des envois (administration)

Les « Échec » sont les tentatives d'envoi réel refusées par le réseau de l'environnement de Claude.

| Téléphone | Téléphone (sombre) |
|---|---|
| ![](emails-connexion/6-journal-des-envois-telephone.png) | ![](emails-connexion/6-journal-des-envois-telephone-sombre.png) |

| Ordinateur | Ordinateur (sombre) |
|---|---|
| ![](emails-connexion/6-journal-des-envois-ordinateur.png) | ![](emails-connexion/6-journal-des-envois-ordinateur-sombre.png) |

## 7. Administration → Réglages : e-mails et reçus

| Téléphone | Téléphone (sombre) |
|---|---|
| ![](emails-connexion/7-reglages-emails-et-recus-telephone.png) | ![](emails-connexion/7-reglages-emails-et-recus-telephone-sombre.png) |

| Ordinateur | Ordinateur (sombre) |
|---|---|
| ![](emails-connexion/7-reglages-emails-et-recus-ordinateur.png) | ![](emails-connexion/7-reglages-emails-et-recus-ordinateur-sombre.png) |

## 8. Chaque e-mail, tel qu'il s'affiche sur un téléphone

### Code de connexion

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-1-code-de-connexion.png) | ![](emails-connexion/email-1-code-de-connexion-sombre.png) |

### Bienvenue

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-2-bienvenue.png) | ![](emails-connexion/email-2-bienvenue-sombre.png) |

### Paiement à vérifier (administrateur)

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-3-paiement-a-verifier.png) | ![](emails-connexion/email-3-paiement-a-verifier-sombre.png) |

### Paiement refusé, avec la raison

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-4-paiement-refuse.png) | ![](emails-connexion/email-4-paiement-refuse-sombre.png) |

### Paiement validé, reçu en pièce jointe

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-5-paiement-valide-recu.png) | ![](emails-connexion/email-5-paiement-valide-recu-sombre.png) |

### Formule qui se termine demain

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-6-formule-finit-demain.png) | ![](emails-connexion/email-6-formule-finit-demain-sombre.png) |

### Formule terminée

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-7-formule-terminee.png) | ![](emails-connexion/email-7-formule-terminee-sombre.png) |

### Mot de passe oublié (comptes avec mot de passe)

| Clair | Sombre |
|---|---|
| ![](emails-connexion/email-8-mot-de-passe.png) | ![](emails-connexion/email-8-mot-de-passe-sombre.png) |

## 9. Finitions de la pull request 52 : avant et après

### Le bloc du logo dans Réglages → Mémoire

| Avant (téléphone) | Après (téléphone) | Après (téléphone, sombre) |
|---|---|---|
| ![](emails-connexion/memoire-logo-avant-telephone.png) | ![](emails-connexion/memoire-logo-apres-telephone.png) | ![](emails-connexion/memoire-logo-apres-telephone-sombre.png) |

| Après (ordinateur) | Après (ordinateur, sombre) |
|---|---|
| ![](emails-connexion/memoire-logo-apres-ordinateur.png) | ![](emails-connexion/memoire-logo-apres-ordinateur-sombre.png) |

### Un mot de passe ou un code annoncé dans un message

| Avant : gardé tel quel | Après : masqué avant tout enregistrement |
|---|---|
| ![](emails-connexion/masquage-avant-telephone.png) | ![](emails-connexion/masquage-apres-telephone.png) |
