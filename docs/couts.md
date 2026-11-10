# Coûts de Kekeli et proposition de formules

Mesures faites le 8 novembre 2026 avec de vrais essais : ordinateur virtuel E2B, agent OpenCode, Gemini 3.5 Flash-Lite (offre gratuite de Google AI Studio). Chaque type de demande a été fait **deux fois** : ce sont des premières moyennes, à confirmer avec plus d'essais.

**Décisions du 9 novembre 2026** (porteur du projet) :
1. **Formule gratuite** : énergie pour environ 1 petite page et 1 modification par jour (30 FCFA), rechargée chaque jour à minuit (heure de Lomé), avec un **plafond total par jour pour l'ensemble des utilisateurs gratuits**, réglable dans l'administration (valeur de départ : **1 500 FCFA**, voir la partie 3).
2. **Formules payantes** : Découverte 2 500 FCFA, Pro 7 500 FCFA, Entreprise 25 000 FCFA par mois, avec l'énergie proposée. **Prix provisoires**, à revoir après le choix du modèle à l'étape 6. **Pas encore publiés** sur la page « Formules ».
3. Recharge de toutes les formules **chaque jour à minuit**, heure de Lomé.
4. Attente avant la pause : **10 minutes** au lieu de 30.

Ces choix sont les valeurs par défaut des réglages (`config/kekeli.php`) et se changent dans **Administration → Réglages**.

Taux utilisé : **1 $ = 590 FCFA** (1 € = 655,957 FCFA, fixe ; 1 € = 1,1165 $ le 6 novembre 2026 selon la Banque centrale européenne). Ce taux se règle dans l'administration.

## 1. Ce que coûte une demande

Le coût d'une demande a trois parties :
1. **le modèle d'IA** : on paie les tokens envoyés, reçus et lus depuis le cache ;
2. **l'ordinateur qui travaille** : E2B facture à la seconde un ordinateur en marche ;
3. **l'ordinateur qui attend** : après la dernière demande, l'ordinateur reste allumé (30 minutes aujourd'hui) pour que l'aperçu reste visible, puis se met en pause.

### Mesures réelles (moyenne de 2 essais)

| Demande | Requêtes au modèle | Tokens envoyés | Tokens lus depuis le cache | Tokens reçus | Durée |
|---|---|---|---|---|---|
| Une petite page (boutique de pagnes) | 8,5 | 31 500 | 86 300 | 7 000 | 41 secondes |
| Un site de 5 pages (cabinet d'avocats) | 11,5 | 46 600 | 200 200 | 20 300 | 44 et 234 secondes (*) |
| Une modification simple (couleur et numéro) | 3,5 | 14 900 | 64 300 | 5 000 | 14 secondes |

(*) Le second site de 5 pages a été interrompu par la limite par minute de Gemini gratuit pendant la correction des licences : il a été mis en attente puis **repris tout seul**, d'où sa durée.

### Coût par demande selon le modèle (modèle + ordinateur au travail)

Prix officiels relevés le 8 novembre 2026 (prix payants ; pendant la construction, Kekeli utilise l'offre gratuite de Gemini). Pour les modèles autres que Gemini 3.5 Flash-Lite, c'est une **estimation** : un autre modèle n'utiliserait pas exactement le même nombre de tokens.

| Modèle (prix par million de tokens : entrée / sortie) | Petite page | Site de 5 pages | Modification |
|---|---|---|---|
| Claude Haiku 5.5 (0,10 $ / 0,50 $) | 5,5 FCFA | 13,7 FCFA | 3,1 FCFA |
| Gemini 3.1 Flash-Lite (0,25 $ / 1,50 $) | 13,2 FCFA | 31,6 FCFA | 8,0 FCFA |
| **Gemini 3.5 Flash-Lite (0,30 $ / 2,50 $)** — mesuré | **18,5 FCFA** | **45,6 FCFA** | **11,6 FCFA** |
| Gemini 3.8 Flash (0,75 $ / 3,75 $ ; le double en 2027) | 34,4 FCFA | 78,3 FCFA | 21,0 FCFA |
| Claude Sonnet 5.5 (2 $ / 10 $) | 89,8 FCFA | 202,4 FCFA | 55,3 FCFA |
| Gemini 4 Argon (4 $ / 20 $ dès le 1er décembre 2026) | 168,3 FCFA | 377,4 FCFA | 102,6 FCFA |
| Claude Opus 5.5 (4 $ / 20 $) | 168,3 FCFA | 377,4 FCFA | 102,6 FCFA |

La qualité de chaque modèle sera jugée à l'étape 6 : le moins cher n'est pas forcément assez bon.

### L'ordinateur

- Taille mesurée : **2 processeurs et 4 Go de mémoire**, soit **0,166 $ de l'heure (environ 98 FCFA)** quand il tourne (prix officiels d'E2B : 0,000014 $ par processeur et par seconde, 0,0000045 $ par Go et par seconde).
- Au travail : 0,4 à 3,8 FCFA par demande, presque rien.
- **En attente avant la pause : c'est le poste le plus cher.** 30 minutes d'attente = **49 FCFA**, plus que le coût du modèle pour une petite page. Avec 10 minutes : **16 FCFA**. Avec 5 minutes : 8 FCFA.
- **Décidé le 9 novembre 2026** : le réglage « Minutes sans demande avant la pause » passe de 30 à **10 minutes**. L'aperçu se rallume en moins d'une minute avec le bouton « Rallumer l'aperçu ».

## 2. Ordinateurs en pause : prix du stockage

- **Documentation officielle d'E2B** (pages « Billing & limits », « How do I calculate the price of a sandbox? » et page des prix, consultées le 8 novembre 2026) : un ordinateur en pause **n'est pas facturé** (« Compute is not billed for paused or killed sandboxes ») et le disque est **inclus** (10 Go gratuits en formule Hobby, 20 Go en formule Pro). **Aucun prix de stockage n'est indiqué** pour les ordinateurs en pause.
- **La facture** : je ne peux pas la consulter (il faut se connecter à votre compte E2B). Geste à faire sur le téléphone : ouvrir `console.e2b.dev`, se connecter, toucher l'onglet **« Usage »** (consommation), puis **« Billing »** (facturation), et regarder si une ligne « storage » ou « paused » apparaît.

| Projets en pause pendant un mois | Selon les prix officiels d'aujourd'hui | Hypothèse prudente (*) |
|---|---|---|
| 10 | **0 FCFA** | environ 850 FCFA (1,4 $) |
| 100 | **0 FCFA** | environ 8 500 FCFA (14 $) |
| 1 000 | **0 FCFA** | environ 85 000 FCFA (144 $) |

(*) Hypothèse **si E2B se mettait un jour à facturer le disque** comme un disque de cloud ordinaire (environ 0,08 $ par Go et par mois) pour 1,8 Go par ordinateur (place mesurée sur le disque d'un ordinateur Kekeli ; les fichiers du projet eux-mêmes ne pèsent que quelques dizaines de kilo-octets). C'est une estimation, pas un prix annoncé.

**La nouvelle règle limite ce risque** : un ordinateur en pause depuis plus de **7 jours** (réglable dans l'administration) est effacé chaque nuit. Le projet reste sauvegardé dans Kekeli et revient dans un nouvel ordinateur à la demande suivante (vérifié pour de vrai : remis en place en 14 secondes, sans perte). Le nombre d'ordinateurs en pause reste donc proche du nombre de projets utilisés dans la semaine.

## 3. Formules (décidées le 9 novembre 2026, prix provisoires)

### Le principe : « l'énergie qui se recharge »

- Chaque formule donne une **énergie par jour**, exprimée dans le site en **FCFA de coût réel** (modèle + ordinateur au travail). L'utilisateur, lui, voit un pourcentage et des messages simples.
- L'énergie se recharge **chaque jour à minuit, heure de Lomé**. Je propose la recharge quotidienne pour toutes les formules : c'est simple à comprendre, et le coût maximal d'un mois est connu d'avance (30 fois l'énergie du jour). Des recharges toutes les 5 heures (comme Claude Code) restent possibles dans les réglages, mais il faudrait alors ajouter un plafond par semaine pour garder la marge sous contrôle.
- **Avantage clé** : comme l'énergie est comptée en coût réel, **la marge est garantie quel que soit le modèle choisi**. Si l'on passe à un modèle plus cher, l'utilisateur fait moins de demandes avec la même énergie ; la marge ne bouge pas.

### Les formules

Équivalences calculées avec Gemini 3.5 Flash-Lite payant.

| Formule | Prix par mois | Énergie par jour | Ce que ça permet par jour (environ) |
|---|---|---|---|
| **Gratuite** | 0 FCFA | 30 FCFA | 1 petite page et 1 modification |
| **Découverte** | 2 500 FCFA | 40 FCFA | 2 petites pages, ou 1 site de 5 pages, ou 3 modifications |
| **Pro** | 7 500 FCFA | 130 FCFA | 7 petites pages, ou 3 sites de 5 pages, ou 11 modifications |
| **Entreprise** | 25 000 FCFA | 450 FCFA | 24 petites pages, ou 10 sites de 5 pages, ou 39 modifications |

### Les marges

Hypothèses : modèle Gemini 3.5 Flash-Lite payant, attente de **10 minutes** avant la pause (1, 2 et 3 séances de travail par jour selon la formule), 1 % de frais Mobile Money (estimation). **TVA non comptée : à vérifier avec un comptable.**

| Formule | Prix | Coût maximal (tout utilisé, chaque jour) | Marge minimale | Coût probable (30 % utilisé) | Marge probable |
|---|---|---|---|---|---|
| Découverte | 2 500 FCFA | 1 714 FCFA | **31 %** | 514 FCFA | **79 %** |
| Pro | 7 500 FCFA | 4 952 FCFA | **34 %** | 1 486 FCFA | **80 %** |
| Entreprise | 25 000 FCFA | 15 216 FCFA | **39 %** | 4 565 FCFA | **82 %** |

**La formule gratuite** utilise les offres gratuites des fournisseurs d'IA : le modèle ne coûte rien. Seul l'ordinateur coûte : au plus environ 530 FCFA par mois pour un utilisateur gratuit très actif (tous les jours), environ 110 FCFA pour un utilisateur qui vient un jour sur cinq. C'est un coût de publicité à surveiller dans la page « Consommation ».

**Avec l'attente actuelle de 30 minutes au lieu de 10, les marges minimales tombent à −8 % (Découverte, donc une perte si la formule est utilisée à fond), 8 % (Pro) et 27 % (Entreprise)** : d'où la recommandation de passer à 10 minutes avant de publier les formules.

### Plafond total de la formule gratuite

Les utilisateurs gratuits partagent les offres gratuites des fournisseurs d'IA (par exemple environ 500 requêtes par jour par modèle Flash-Lite chez Google, à vérifier dans AI Studio) et leurs ordinateurs coûtent vraiment. Un plafond total par jour protège les deux.

**Valeur de départ proposée : 1 500 FCFA d'énergie par jour** pour l'ensemble des utilisateurs gratuits, soit environ 80 petites pages par jour, environ 700 requêtes au modèle (proche de ce que donnent les deux modèles Flash-Lite gratuits ensemble) et, au plus, environ 1 000 à 1 400 FCFA par jour de coût réel d'ordinateurs (30 000 à 42 000 FCFA par mois si le plafond est atteint tous les jours). Quand il est atteint, les utilisateurs gratuits voient : « ☀️ Kekeli se recharge en ce moment. Il sera de nouveau prêt à 00:00. Vous ne voulez pas attendre ? Passez à une formule payante et continuez tout de suite. » et l'administrateur reçoit une alerte (une seule par jour). Le plafond se change dans **Administration → Réglages** ; la page **Consommation** montre chaque jour l'énergie utilisée par rapport au plafond.

### Si l'étape 6 montre qu'il faut un modèle plus fort

Avec Claude Sonnet 5.5, la même énergie donne environ **5 fois moins de demandes** (la marge reste la même). Exemple : la formule Pro ne permettrait plus qu'environ 1 petite page par jour. Il faudrait alors augmenter l'énergie, donc les prix, ou utiliser un modèle économique pour les petites tâches et un modèle puissant pour les tâches difficiles, comme prévu dans le plan. **Claude Haiku 5.5** est le moins cher de tous (environ 3 fois moins que Gemini 3.5 Flash-Lite) : à essayer en priorité à la fin du projet, quand la clé Claude sera achetée.

**Mesure de l'étape 6 (9 novembre 2026)** : un site professionnel de restaurant de 4 pages, avec la recherche de photos libres, les vérifications automatiques et une correction par l'agent, a coûté environ **41 FCFA** avec Gemini 3.5 Flash-Lite (modèle 35 FCFA, ordinateur 6 FCFA ; estimation au prix payant équivalent, l'essai ayant utilisé l'offre gratuite), en 17 requêtes. C'est du même ordre que le site de 5 pages mesuré à l'étape 5 (45,6 FCFA) : les vérifications n'augmentent pas beaucoup le coût. Les modèles plus forts n'ont pas pu être mesurés avec les offres gratuites (environ 20 requêtes par jour) ; aucun budget payant pendant la construction (règle du 9 novembre 2026) : les modèles payants seront mesurés à la fin du projet.

**Mesures de la fin de l'étape 6 (9 novembre 2026, toutes des estimations au prix payant équivalent, les essais ayant utilisé l'offre gratuite)** :
- site de 4 pages avec photos choisies et regardées par Kekeli, icônes et vérifications : **environ 75 FCFA** avec Gemini 3.5 Flash-Lite (22 requêtes ; modèle 71 FCFA, ordinateur 4 FCFA) et environ 32 FCFA avec Gemini 3.1 Flash-Lite (pages plus pauvres) ;
- page d'accueil avec les photos du client : **environ 21 FCFA** (9 requêtes, 44 secondes) ;
- **une image créée** (Cloudflare Workers AI, FLUX.1 schnell) : gratuite dans la limite de 10 000 « neurones » par jour ; au-delà, environ 0,0006 $ (0,35 FCFA) d'après la grille officielle. Kekeli compte **0,001 $ (0,59 FCFA) par image** dans l'énergie du client, réglable dans l'administration ;
- une photo regardée par Kekeli : une requête au modèle de vision (Gemini 3.1 Flash-Lite), comptée comme les autres.

**Création d'images (essai réel du 9 novembre 2026)** : gratuite tant que Kekeli reste sous les 10 000 « neurones » par jour de Cloudflare. Une image de 1 024 × 1 024 en 4 étapes coûte environ 58 neurones d'après la grille officielle (4 morceaux de 512 × 512 à 4,8 et 4 étapes à 9,6), soit environ 170 images par jour (estimation). Le maximum réglable de Kekeli est fixé à 150 par jour pour garder une marge. Au-delà de l'offre gratuite, ce serait environ 0,0006 $ par image ; l'énergie du client compte 0,001 $ (0,59 FCFA) par image.

**Pas de bonus de bienvenue** (décision du 9 novembre 2026). **Quand l'énergie est épuisée au milieu d'une demande**, le travail s'arrête aussitôt : l'utilisateur ne consomme jamais plus que son énergie, à une requête près (la requête en cours est comptée en entier).

**Publication (étape 7, essai réel du 9 novembre 2026)** : gratuite avec Cloudflare Pages (pas de coût d'ordinateur : on publie la sauvegarde). Limite gratuite : **100 projets par compte**, soit 100 sites clients publiés ; au-delà, il faudra une autre solution, à décider avant la bêta. Une correction suivie d'une nouvelle publication a coûté environ 13 FCFA d'énergie (7 requêtes ; estimation au prix payant équivalent).

**Images sans texte** : une image créée qui contient du texte est recréée (deux fois au plus) : dans le pire des cas, une image demandée en coûte trois dans le maximum du jour et dans l'énergie (environ 1,8 FCFA au lieu de 0,6 ; estimation), plus une requête de vision par image.

**À revoir avec le porteur du projet** : avec ces mesures, l'énergie gratuite d'un jour (30 FCFA) suffit pour une petite page, pas pour un site complet (environ 75 FCFA).

### Coûts fixes à couvrir (estimations)

| Poste | Coût estimé par mois |
|---|---|
| Laravel Cloud (site et base de données) | 5 à 20 $ (3 000 à 12 000 FCFA) |
| Hébergement du Runner | environ 5 $ (3 000 FCFA) |
| Nom de domaine kekeli.dev | environ 1 $ (600 FCFA) |
| E2B formule Pro (au-delà de 20 ordinateurs en marche en même temps) | 150 $ (88 500 FCFA), seulement plus tard |

Au départ, environ **7 000 à 16 000 FCFA par mois** : couverts par environ 2 à 3 abonnements Pro, ou 4 à 8 abonnements Découverte (avec une utilisation moyenne).

## 4. Ce qui reste à décider

1. Les **prix définitifs**, après le choix du ou des modèles payants (étape 6, puis étape 8).
2. La **publication** des formules payantes (case « Publier les formules » dans les réglages), seulement avec l'accord du porteur du projet.

## Galerie de modèles : énergie mesurée (essai réel du 10 novembre 2026)

Personnaliser un modèle de la galerie (nom, ville, numéro WhatsApp, prix, couleurs ; site d'une page avec 5 images) a coûté, en vrai, avec E2B et Gemini 3.1 Flash-Lite (offre gratuite ; estimation au prix payant équivalent) :

| Essai | Requêtes | Durée | Modèle | Ordinateur | **Énergie** |
|---|---|---|---|---|---|
| Restaurant → « Maquis Le Baobab » | 10 | 58 secondes | 26,6 FCFA | 1,4 FCFA | **28 FCFA** |
| Salon de coiffure → « Salon Grâce Divine » | 9 | 2 minutes 45 | 37,7 FCFA | 4,3 FCFA | **42 FCFA** |

**Comparaison avec un site créé à partir de rien** : environ 41 FCFA (site de 4 pages, 17 requêtes, mesure de l'étape 6) et 45,6 FCFA (site de 5 pages, étape 5). Un modèle personnalisé coûte donc **à peu près autant d'énergie, ou un peu moins** (28 à 42 FCFA), avec **moitié moins de requêtes** (9 à 10 au lieu de 17). Le gain principal n'est pas l'énergie, mais la qualité et la rapidité : un site professionnel complet, avec ses images déjà prêtes (aucune image à chercher ni à créer pendant la demande), en 1 à 3 minutes. La plus grosse partie du coût vient de la lecture du modèle par l'agent (environ 50 000 tokens envoyés). Ces chiffres sont des estimations faites sur deux essais ; ils varient d'un essai à l'autre.

Avec l'énergie gratuite d'un jour (30 FCFA), un client peut donc personnaliser environ un modèle par jour.

**Fabrication des modèles** : gratuite. Les 60 images ont été créées une seule fois par l'offre gratuite de Cloudflare (85 créations en comptant celles qui ont été refaites à cause du texte), et regardées par l'offre gratuite de Gemini. Elles font partie du projet du client dès qu'il choisit le modèle : elles ne coûtent rien de plus à chaque utilisation.

## Mode développeur : énergie mesurée (essais réels du 10 novembre 2026)

Le dépôt est ouvert, les outils installés, le travail fait par l'agent et les tests lancés, avec E2B et Gemini 3.1 Flash-Lite (offre gratuite ; estimation au prix payant équivalent) :

| Essai | Requêtes | Durée | Modèle | Ordinateur | **Énergie** |
|---|---|---|---|---|---|
| `jonschlinkert/is-number` (Node) : une option et ses tests | 16 | 1 minute 10 | 30,9 FCFA | 1,8 FCFA | **33 FCFA** |
| `laravel/laravel` (Laravel, PHP 8.3) : une page et son test | 14 | 1 minute 51 | 31,9 FCFA | 2,9 FCFA | **35 FCFA** |

L'installation des outils (environ 1 minute pour Laravel) n'a lieu qu'une fois par ordinateur. Le coût vient surtout de la lecture du projet par l'agent (85 000 à 120 000 tokens envoyés, plus le cache) : **sur un gros projet, le coût d'une demande peut être plusieurs fois plus élevé**. Une correction automatique après un échec de la vérification de GitHub coûte une demande de plus (2 au plus par envoi, réglable). Ces chiffres viennent de deux essais : ce sont des estimations.

## Messages clairs : coût en plus (essais réels du 10 novembre 2026)

Pendant le travail, Kekeli écrit une phrase courte avant chaque groupe d'actions ; à la fin, il écrit un compte rendu avec cinq titres ; le site montre aussi les modifications. Ce qui coûte en plus, mesuré sur trois demandes réelles avec E2B et Gemini 3.5 Flash-Lite (offre gratuite ; estimation au prix payant équivalent : 0,30 $ par million de tokens envoyés, 0,03 $ en cache, 2,50 $ écrits ; 1 $ = 590 FCFA) :

| Demande | Requêtes | Phrases écrites | **Énergie totale** | Phrases | Consignes en plus | Photo et comparaison | **Coût en plus** |
|---|---|---|---|---|---|---|---|
| Site d'une page « Chez Ama » (création) | 12 | 10 phrases, 600 caractères | 28,3 FCFA | 0,25 FCFA | 0,12 FCFA | 0,05 FCFA | **environ 0,4 FCFA** |
| Ajout de la section « Livraison » et couleur verte | 7 | 6 phrases, 388 caractères | 17,8 FCFA | 0,16 FCFA | 0,09 FCFA | 0,05 FCFA | **environ 0,3 FCFA** |
| Mode développeur, `jonschlinkert/is-number` (option et tests) | 13 | 12 phrases, 853 caractères | 33,1 FCFA | 0,36 FCFA | 0,12 FCFA | 0,05 FCFA | **environ 0,5 FCFA** |

Comment c'est calculé :
- **Phrases** : les caractères écrits par l'agent pour parler au client, divisés par 3,5 (taille moyenne d'un token en français), au prix des tokens écrits.
- **Consignes en plus** : environ 1 100 caractères (environ 320 tokens) ajoutés aux consignes de chaque requête ; la première requête les envoie, les suivantes les lisent en cache.
- **Photo et comparaison** : environ 2 secondes de l'ordinateur virtuel avant et après la demande.
- **Compte rendu, « Voir les modifications », « Copier » et historique** : **0 FCFA**. Le compte rendu est construit par le site Kekeli à partir de ce qu'il sait déjà ; il ne demande **aucune requête de plus** au modèle. Les cinq titres du compte rendu final ajoutent seulement quelques mots à la réponse que l'agent écrivait déjà.

**Résultat : les messages clairs coûtent environ 0,3 à 0,5 FCFA par demande, soit 1 à 2 % de l'énergie d'une demande.** Pour comparer : l'essai du mode développeur sur le même dépôt coûtait 33 FCFA avant ce chantier, et 33,1 FCFA après. Un client gratuit (30 FCFA d'énergie par jour) ne perd donc presque rien.

**Relance après une erreur passagère du modèle** : quand Gemini répond par une erreur passagère, Kekeli relance le travail (deux fois au plus, puis passe au modèle suivant). Chaque relance coûte une requête de plus (environ 2 FCFA) seulement quand l'erreur arrive ; sans elle, la demande échouait et tout le travail déjà fait était perdu.

Ces chiffres viennent de trois demandes : ce sont des estimations ; ils varient d'une demande à l'autre.

## Applications web : énergie mesurée (essais réels du 10 novembre 2026)

Essais de bout en bout sur un téléphone simulé, avec E2B et Gemini Flash-Lite (offre gratuite ; estimation au prix payant équivalent). L'énergie compte le modèle et l'ordinateur pendant la demande ; l'installation de l'application (environ 30 secondes) est comprise dans la première demande.

| Essai | Technologie | Requêtes | Durée | Modèle | Ordinateur | **Énergie** |
|---|---|---|---|---|---|---|
| Modèle « Réservations d'un restaurant » personnalisé (Restaurant Le Palmier, Lomé) | Laravel | 24 | 1 minute 24 | 27,2 FCFA | 2,2 FCFA | **29,3 FCFA** |
| Demande suivante : choix « Occasion » dans le formulaire, l'administration et un nouveau test | Laravel | 28 | 4 minutes 33 | 45,5 FCFA | 7,3 FCFA | **52,7 FCFA** |
| Application sur mesure, à partir de zéro (banque alimentaire de Kara : familles, quartiers, validation, date de distribution) | Next.js | 41 | 7 minutes 9 | 69,0 FCFA | 11,5 FCFA | **80,6 FCFA** |
| Demande suivante : filtre par quartier dans l'administration et son test | Next.js | 13 | 1 minute | 23,5 FCFA | 1,6 FCFA | **25,1 FCFA** |

Un premier essai du même modèle Laravel (avant une correction de l'aperçu) avait coûté 24,9 FCFA pour la personnalisation et 31,2 FCFA pour le choix « Occasion » : **les chiffres varient d'un essai à l'autre**, ce sont des estimations.

**Ce que cela veut dire pour les clients :**
- **Un modèle de la galerie personnalisé** : environ 25 à 30 FCFA, soit à peu près l'énergie gratuite d'un jour (30 FCFA).
- **Une application sur mesure à partir de zéro** : environ 80 FCFA dans cet essai (moins que l'estimation de 100 à 300 FCFA du plan, grâce à l'application de départ déjà prête et testée). Avec la formule gratuite, cela demande environ **3 jours d'énergie** : le travail s'arrête quand l'énergie du jour est épuisée et reprend le lendemain, là où il s'était arrêté. Une application plus grande coûtera davantage.
- **Une modification ensuite** : de 25 à 55 FCFA selon sa taille.
- **Fichier ZIP pour l'hébergement mutualisé** : environ 16 secondes d'ordinateur (moins de 1 FCFA, pris en charge par Kekeli, pas compté dans l'énergie du client).

**Quota gratuit de Gemini** : une application sur mesure a demandé 41 requêtes, soit environ 8 % des 500 requêtes du jour d'un modèle Flash-Lite. C'est pour cela que les clients payants passent d'abord quand le quota gratuit du jour devient bas.

**Qualité avec Gemini gratuit** : l'agent a bien construit l'application demandée (11 fichiers, tests verts, sécurité vérifiée), mais il oublie parfois des détails : par exemple, les textes de la page d'accueil (« Nouvelle demande ») n'avaient pas été adaptés au métier du client. Les consignes ont été précisées ; un modèle plus puissant (payant, à la fin du projet) fera mieux ces finitions.


## Nouveau site : énergie mesurée (essais réels du 10 novembre 2026, après la décision « un seul parcours »)

Essais de bout en bout depuis la page « Nouveau site », sur un téléphone simulé, avec E2B et Gemini Flash-Lite (offre gratuite ; estimation au prix payant équivalent). Seule la clé Gemini était placée : la limite par minute de l'offre gratuite a mis les demandes avec base de données plusieurs fois en attente (reprise automatique), ce qui allonge la durée et le temps d'ordinateur.

| Essai | Ce que Kekeli a compris | Requêtes | Durée | Modèle | Ordinateur | **Énergie** |
|---|---|---|---|---|---|---|
| Restaurant à Lomé : menu, prix, horaires, plan, bouton WhatsApp | Site vitrine | 23 | 5 minutes | 60,7 FCFA | 8,3 FCFA | **69,0 FCFA** |
| Salon de coiffure à Lomé : prestations, prix, horaires, bouton WhatsApp (avec une correction : page oubliée par l'agent) | Site vitrine | 19 | 3 minutes | — | — | **37,8 FCFA** |
| Pressing à Lomé : comptes des clients, commandes de linge, validation par le gérant | Site avec base de données (Laravel) | 63 | 17 minutes 30 | 115,0 FCFA | 28,5 FCFA | **143,5 FCFA** |
| Association à Kara (Next.js, Vercel) : comptes des bénévoles, inscriptions aux activités, validation | Site avec base de données (Next.js) | 51 | 19 minutes | 104,5 FCFA | 31,0 FCFA | **135,5 FCFA** |

**Ce que cela veut dire :**
- Ces sites, décrits avec des mots simples, coûtent plus que l'essai précédent (80 FCFA) parce que Kekeli a remplacé le formulaire de départ par tout un métier (nouvelles tables, nouvelles pages, nouveaux tests) et parce que les mises en attente gardent l'ordinateur allumé. **Ce sont des estimations** qui varient d'un essai à l'autre.
- Avec la formule gratuite (30 FCFA par jour), un site vitrine complet demande environ 1 à 3 jours d'énergie, un site avec base de données environ 5 jours : le travail s'arrête quand l'énergie du jour est épuisée et reprend le lendemain. Les modèles de la galerie restent beaucoup moins chers (environ 25 à 30 FCFA).
- **À revoir avec le porteur du projet** avant le lancement : l'énergie gratuite d'un jour, et le temps d'attente des demandes. Placer les clés gratuites d'OpenRouter et de Mistral éviterait la plupart des mises en attente (Kekeli passerait au fournisseur suivant au lieu d'attendre).
- La détection « site vitrine ou site avec base de données » ne coûte rien : elle se fait sur le site, sans requête au modèle.
