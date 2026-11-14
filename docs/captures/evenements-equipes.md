# Automatisations par événement et équipes d'agents (13 et 14 novembre 2026)

Règle du porteur du projet : mesurer avant et après quand c'est possible, et annuler ce qui n'améliore rien. **Kekeli écrit le code ; tout tourne chez l'utilisateur** (son GitHub, son compte Cloudflare, ses clés), jamais chez Kekeli. Détails et sources : `docs/plan.md`, partie 26.20. Les coûts sont des **estimations**.

## 1. Images libres des vidéos en formule Gratuite

| | Avant | Après |
|---|---|---|
| Scènes avec une vraie image (5 sujets, 15 scènes, recherche Pixabay seule) | 11 sur 15 | **15 sur 15** |
| Fond animé | 4 scènes | aucune |

Sujets : boutique de pagnes, école primaire, maquis, garage, formation en couture. Images trouvées après : tissus colorés, salle de classe, poisson grillé, atelier de moto, machine à coudre, mètre de couturière… Mesure : `runner/mesures/pixabay-mesure.json`.

## 2. Automatisation déclenchée par le formulaire d'un site (essai réel)

« Quand un client remplit le formulaire de contact de mon site Kofi Couture, envoie-moi un résumé par e-mail » : site et automatisation construits pour de vrai par Kekeli, formulaire branché par Kekeli, envoi depuis l'aperçu du site, point d'entrée écrit par Kekeli lancé dans le vrai moteur de Cloudflare, automatisation lancée comme le fait GitHub Actions. Résultats : `runner/mesures/essai-evenement.json`.

| Contrôle | Résultat |
|---|---|
| Déclencheur reconnu dans le code | « événement », secret du point d'entrée créé, événement d'essai prêt |
| « Lancer maintenant » (événement simulé) | nom, téléphone et demande reçus ; résumé préparé ; e-mail non envoyé (pas de clé Resend pendant l'essai) |
| Formulaire du site rempli par un visiteur | « Merci, votre message est bien envoyé. » ; point d'entrée : accepté ; automatisation lancée une fois, avec les trois champs |
| Le même envoi rejoué | accepté poliment, **rien de relancé** |
| Un appel venu d'un autre site | **refusé** (erreur 401) |
| Défaut trouvé et corrigé | le site envoyait « message », l'automatisation lisait « demande » : Kekeli donne maintenant au site les noms exacts des champs |

Ce qui se fera à la mise en ligne, avec les vrais comptes de l'utilisateur : `docs/mise-en-ligne.md`, partie 8.

### Le plan, avec son déclencheur expliqué simplement
| Téléphone | Téléphone, sombre |
|---|---|
| ![](evenements-equipes/telephone-plan-automatisation.png) | ![](evenements-equipes/telephone-sombre-plan-automatisation.png) |

| Ordinateur | Ordinateur, sombre |
|---|---|
| ![](evenements-equipes/ordinateur-plan-automatisation.png) | ![](evenements-equipes/ordinateur-sombre-plan-automatisation.png) |

### Le LISEZ-MOI de branchement, écrit par Kekeli
| Téléphone | Ordinateur |
|---|---|
| ![](evenements-equipes/telephone-lisez-moi-branchement.png) | ![](evenements-equipes/ordinateur-lisez-moi-branchement.png) |

### « Brancher un formulaire », avec l'accord du client
| Téléphone | Ordinateur |
|---|---|
| ![](evenements-equipes/telephone-brancher-formulaire.png) | ![](evenements-equipes/ordinateur-brancher-formulaire.png) |

### Le formulaire du site, après l'envoi
![](evenements-equipes/telephone-formulaire-envoye.png)

### Le journal des déclenchements
Chaque ligne « Échec » vient de la dernière étape (l'e-mail), faute de clé Resend pendant l'essai ; le point d'entrée et le lancement ont fonctionné. La ligne la plus ancienne date d'avant la correction des noms de champs.

| Téléphone | Téléphone, sombre |
|---|---|
| ![](evenements-equipes/telephone-journal-declenchements.png) | ![](evenements-equipes/telephone-sombre-journal-declenchements.png) |

| Ordinateur | Ordinateur, sombre |
|---|---|
| ![](evenements-equipes/ordinateur-journal-declenchements.png) | ![](evenements-equipes/ordinateur-sombre-journal-declenchements.png) |

## 3. Équipe d'agents contre agent seul (essai réel)

La même demande (boutique Ama Pagnes : commande, stock, facture, réponse au client), construite pour de vrai par Kekeli deux fois ; 10 questions d'essai avec des pièges, posées à chacun avec le modèle gratuit de l'essai. Réponses complètes : `runner/mesures/equipe-vs-seul.json`.

| | Agent seul | Équipe (4 agents et un coordinateur) |
|---|---|---|
| Réponses justes | 8 sur 10 | **9 sur 10** |
| Appels au modèle par question (estimation) | 1 ou 2 | 2 ou 3 |

**Équipe gardée**, avec honnêteté : l'écart n'est que d'une question, et le coordinateur a confié toutes ces questions à l'agent clientèle. Deux défauts trouvés et corrigés pendant l'essai : les outils échouaient avec Gemini 3 (signature des appels d'outil), et « ouverts le dimanche » ne retrouvait pas les horaires dans les documents.

Pendant l'essai, la limite de l'essai gratuit a été relevée (d'où « 250 messages restants » sur la capture), puis remise à 10.

### Le plan d'une équipe
| Téléphone | Téléphone, sombre |
|---|---|
| ![](evenements-equipes/telephone-plan-equipe.png) | ![](evenements-equipes/telephone-sombre-plan-equipe.png) |

| Ordinateur | Ordinateur, sombre |
|---|---|
| ![](evenements-equipes/ordinateur-plan-equipe.png) | ![](evenements-equipes/ordinateur-sombre-plan-equipe.png) |

### « Tester l'agent » : l'agent qui a répondu, sur une ligne repliable
| Téléphone | Téléphone, sombre |
|---|---|
| ![](evenements-equipes/telephone-tester-equipe.png) | ![](evenements-equipes/telephone-sombre-tester-equipe.png) |

| Ordinateur | Ordinateur, sombre |
|---|---|
| ![](evenements-equipes/ordinateur-tester-equipe.png) | ![](evenements-equipes/ordinateur-sombre-tester-equipe.png) |
