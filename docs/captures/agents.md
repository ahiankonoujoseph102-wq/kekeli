# Agents IA

Captures du 12 novembre 2026, sur un téléphone simulé (Pixel 7, navigateur en français) et sur un ordinateur (1 440 × 900), en mode clair et en mode sombre. Compte inventé (formule Pro, Togo). L'agent d'essai, « l'assistante du Salon Belle Afi », a été **construit pour de vrai** dans un ordinateur E2B, puis **testé pour de vrai** dans le panneau « Tester l'agent » avec l'essai gratuit (modèle gratuit de Kekeli, par le relais du Runner). Scripts : `scripts/preparer-essai-agent.php` et `scripts/essai-agent.js`. Détails : `docs/plan.md`, partie 28.

## La demande et le plan

Le client décrit son assistante dans « Que voulez-vous créer aujourd’hui ? ». Kekeli comprend que c'est un agent (pas un site, pas une application), donne son nom à la discussion et propose un plan court : rôle, ce qu'elle sait, ce qu'elle peut faire, où elle sera utilisée, modèle prévu, technologie. Rien n'est construit avant « Construire ce plan ».

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](agents/telephone-1-plan.png) | ![Téléphone, sombre](agents/telephone-sombre-1-plan.png) |
| Ordinateur | ![Ordinateur](agents/ordinateur-1-plan.png) | ![Ordinateur, sombre](agents/ordinateur-sombre-1-plan.png) |

## La construction

Kekeli écrit le code de l'agent dans un ordinateur E2B (LangGraph en JavaScript) : rôle, FAQ, outil de rendez-vous, page de discussion et bulle, tests, LISEZ-MOI. Les actions repliées montrent chaque fichier et chaque commande du projet, comme Claude Code.

![Pendant la construction, sur téléphone](agents/telephone-2-construction-en-cours.png)

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](agents/telephone-3-construction-terminee.png) | ![Téléphone, sombre](agents/telephone-sombre-3-construction-terminee.png) |
| Ordinateur | ![Ordinateur](agents/ordinateur-3-construction-terminee.png) | ![Ordinateur, sombre](agents/ordinateur-sombre-3-construction-terminee.png) |

## « Tester l'agent », avec une vraie réponse

L'agent répond pour de vrai dans l'aperçu, avec l'essai gratuit du jour (la ligne du haut dit combien de messages restent, avec « Ajouter votre clé »).

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](agents/telephone-4-tester-agent.png) | ![Téléphone, sombre](agents/telephone-sombre-4-tester-agent.png) |
| Ordinateur | ![Ordinateur](agents/ordinateur-4-tester-agent.png) | ![Ordinateur, sombre](agents/ordinateur-sombre-4-tester-agent.png) |

## L'essai gratuit du jour est terminé

L'agent dit lui-même « L'essai gratuit du jour est terminé. Ajoutez votre clé dans les Variables secrètes pour continuer. », et la ligne du panneau ouvre les Variables secrètes.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](agents/telephone-5-essai-termine.png) | ![Téléphone, sombre](agents/telephone-sombre-5-essai-termine.png) |
| Ordinateur | ![Ordinateur](agents/ordinateur-5-essai-termine.png) | ![Ordinateur, sombre](agents/ordinateur-sombre-5-essai-termine.png) |

## La ligne « Énergie épuisée »

Comme Claude Code : tout s'arrête, une seule ligne sobre avec l'heure de recharge (heure de Lomé) et un lien discret « Voir les formules ». Le travail fait est sauvegardé ; « Continuer » reprend après la recharge.

| | Mode clair | Mode sombre |
|---|---|---|
| Téléphone | ![Téléphone](agents/finitions/apres-telephone-2-energie-epuisee.png) | ![Téléphone, sombre](agents/finitions/apres-telephone-sombre-2-energie-epuisee.png) |
| Ordinateur | ![Ordinateur](agents/finitions/apres-ordinateur-2-energie-epuisee.png) | ![Ordinateur, sombre](agents/finitions/apres-ordinateur-sombre-2-energie-epuisee.png) |

## Les 6 finitions des applications, avant et après

Données inventées (`scripts/preparer-captures-finitions-applications.php`), même écran avant (version de la pull request 41) et après.

1. « Lu · . » devient « Lu · dossier du projet ».
2. Les commandes internes de Kekeli (`touch /home/user/.kekeli/relancer`) ne sont plus montrées ; seules les commandes du projet restent (8 actions deviennent 7).
3. La phrase « Je découvre votre application actuelle… sans toucher aux comptes existants » ne sort plus pour une application neuve (la consigne de construction le dit maintenant à l'agent).
4. Sur téléphone, « Récupérer le code » devient une icône (avec son libellé accessible) : le titre n'est plus coupé.
5. Le titre de la discussion est le nom deviné (« Salon de coiffure Belle Afi ») dès que le plan est proposé, plus le début coupé du message.
6. Les restes du point de départ (« Anciennes demandes ») : la consigne de construction demande maintenant de les retirer. C'est une consigne donnée à l'agent : elle se voit dans l'application construite, pas dans l'écran de Kekeli (avant : voir `applications/banc/openai-gpt-6-1-terra/3-espace.png`).

| | Avant | Après |
|---|---|---|
| Téléphone | ![Avant](agents/finitions/avant-telephone-1-actions-et-titre.png) | ![Après](agents/finitions/apres-telephone-1-actions-et-titre.png) |
| Téléphone, sombre | ![Avant](agents/finitions/avant-telephone-sombre-1-actions-et-titre.png) | ![Après](agents/finitions/apres-telephone-sombre-1-actions-et-titre.png) |
| Ordinateur | ![Avant](agents/finitions/avant-ordinateur-1-actions-et-titre.png) | ![Après](agents/finitions/apres-ordinateur-1-actions-et-titre.png) |
| Ordinateur, sombre | ![Avant](agents/finitions/avant-ordinateur-sombre-1-actions-et-titre.png) | ![Après](agents/finitions/apres-ordinateur-sombre-1-actions-et-titre.png) |

Énergie épuisée, avant et après :

| | Avant | Après |
|---|---|---|
| Téléphone | ![Avant](agents/finitions/avant-telephone-2-energie-epuisee.png) | ![Après](agents/finitions/apres-telephone-2-energie-epuisee.png) |
| Téléphone, sombre | ![Avant](agents/finitions/avant-telephone-sombre-2-energie-epuisee.png) | ![Après](agents/finitions/apres-telephone-sombre-2-energie-epuisee.png) |
| Ordinateur | ![Avant](agents/finitions/avant-ordinateur-2-energie-epuisee.png) | ![Après](agents/finitions/apres-ordinateur-2-energie-epuisee.png) |
| Ordinateur, sombre | ![Avant](agents/finitions/avant-ordinateur-sombre-2-energie-epuisee.png) | ![Après](agents/finitions/apres-ordinateur-sombre-2-energie-epuisee.png) |
