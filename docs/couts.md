# Coûts de Kekeli et proposition de formules

Mesures faites le 8 novembre 2026 avec de vrais essais : ordinateur virtuel E2B, agent OpenCode, Gemini 3.5 Flash-Lite (offre gratuite de Google AI Studio). Chaque type de demande a été fait **deux fois** : ce sont des premières moyennes, à confirmer avec plus d'essais.

**Décisions du 9 novembre 2026** (porteur du projet) :
1. **Formule gratuite** : énergie pour environ 1 petite page et 1 modification par jour (30 FCFA), rechargée chaque jour à minuit (heure de Lomé), avec un **plafond total par jour pour l'ensemble des utilisateurs gratuits**, réglable dans l'administration (valeur de départ : **1 500 FCFA**, voir la partie 3).
2. **Formules payantes** : Découverte 2 500 FCFA, Pro 7 500 FCFA, Entreprise 25 000 FCFA par mois, avec l'énergie proposée. **Prix provisoires**, à revoir après le choix du modèle à l'étape 6. **Pas encore publiés** sur la page « Formules ».
3. Recharge de toutes les formules **chaque jour à minuit**, heure de Lomé.
4. Attente avant la pause : **10 minutes** au lieu de 30.

Ces choix sont les valeurs par défaut des réglages (`config/kekeli.php`) et se changent dans **Administration → Réglages**.

**Décisions du 12 novembre 2026 (remplacent les formules du 9 novembre ; calculs : `docs/plan.md`, partie 25)** :

| Formule | Prix par mois | Hors zone FCFA | Énergie | Équivalence affichée (estimation, mélange Claude prudent) |
|---|---|---|---|---|
| Gratuite | 0 FCFA | 0 $ | 30 FCFA **par jour** | Environ 1 site vitrine tous les 2 jours (mesuré avec Gemini) |
| Pro | 7 500 FCFA | 13 $ | 4 000 FCFA **par mois** | Environ 14 sites vitrines ou 7 sites avec base de données par mois |
| Expert | 20 000 FCFA | 34 $ | 11 000 FCFA par mois | Environ 40 sites vitrines ou 20 sites avec base de données |
| Agence | 50 000 FCFA | 85 $ | 28 000 FCFA par mois | Environ 100 sites vitrines ou 50 sites avec base de données |

- Énergie **par mois** pour les formules payantes : chaque tranche de 30 jours de l'abonnement, comptée depuis sa fin (un renouvellement payé d'avance donne un nouveau mois plein) ; **par jour** pour la gratuite (minuit, heure de Lomé).
- Équivalences calculées sur la page « Formules » avec les réglages `site_estimate_fcfa` (275 FCFA : un site vitrine complet, création et environ 5 retouches), `webapp_estimate_fcfa` (550 FCFA) et `free_site_estimate_fcfa` (55 FCFA).
- **Marge minimale** avec la clé Claude (toute l'énergie utilisée, attente avant la pause et 3 % de frais de paiement compris) : 37 % (Pro), 37 % (Expert), 38 % (Agence) ; **marge probable** (30 % utilisé) : environ 79 %. TVA non comptée. Ce sont des **estimations**.
- Formule gratuite : **plafond total de 1 000 FCFA par jour** (au lieu de 1 500) et **5 minutes d'attente avant la pause** (au lieu de 10) : sa dépense réelle maximale passe d'environ 22 500 à 37 500 FCFA par mois à environ 12 000 à 20 000 FCFA par mois (estimation : ordinateur seulement, environ 20 FCFA par séance au lieu de 25).
- Prix de lecture du cache de Claude Sonnet 5.5 corrigé : **0,10 $** par million de tokens (au lieu de 0,20 $).
- Découverte et Entreprise sont supprimées ; leurs abonnés passent à Pro et à Expert sans rien perdre (migration `2026_11_12_000001_move_to_new_plans`).

**Décisions du 15 novembre 2026 : une formule gratuite généreuse et rapide, comme Lovable** (`docs/plan.md`, partie 26.32 ; captures : `docs/captures/formule-gratuite.md`) :

| Changement | Avant | Après |
|---|---|---|
| Énergie de la formule Gratuite | 30 FCFA par jour | **100 FCFA par jour** (minuit, heure de Lomé) ; recherches : toujours 3 par jour |
| Discussion simple (aucune création, recherche ni connecteur) | comptée dans l'énergie | **0 FCFA pour le client**, au plus 100 (Gratuite), 300 (Pro), 600 (Expert), 1 000 (Agence) par jour |
| Modèle des réponses | Terra, sauf « économie » sous 15 % d'énergie (Luna, puis GLM, puis Gemini 10 s) | **toujours** GPT-6.1 Terra puis GPT-6 Luna ; Gemini en tout dernier secours |
| Premier modèle d'un site vitrine et de ses modifications | GPT-6.1 Sol | **GPT-6.1 Terra** (choix du porteur du projet), Sol ensuite |
| Plafond global des comptes gratuits | 1 000 FCFA par jour | **inchangé**, discussions simples comprises |
| Plafond OpenAI | 1 $ par jour | **inchangé** |

**Coût d'une discussion simple (estimation)** : avec GPT-6.1 Terra, environ **0,3 à 1,8 FCFA** au prix de l'offre payante (consigne du premier répondant d'environ 3 000 tokens, en grande partie lue dans le cache, et une réponse courte), soit **environ 1 FCFA** en moyenne ; la comparaison réelle du 12 novembre 2026 donnait 1,5 FCFA en moyenne pour des réponses plus riches. Avec GPT-6 Luna : environ 0,14 FCFA. La mémoire ajoute environ 0,1 FCFA. **Dépense réelle de Kekeli** : 0 $ tant que Coarena répond dans ses 5 $ gratuits par jour et par compte (11 comptes ; environ 20 000 réponses de Terra par jour) ; en secours, GPT-6 Luna d'OpenAI pour la Gratuite (environ 0,00024 $ la réponse), dans le plafond de 1 $ par jour ; puis les offres gratuites (0 $).

**Site vitrine gratuit** : estimation de **75 FCFA** (`free_site_estimate_fcfa`) avec GPT-6.1 Terra, à partir de la construction réelle d'une application par Terra (66 FCFA de modèle, `docs/plan.md`, partie 27) et d'environ 5 minutes d'ordinateur (environ 8 FCFA). Il tient dans les 100 FCFA d'une journée. Avec GPT-6.1 Sol, il aurait coûté environ 170 à 280 FCFA (estimation : une application avec Sol a coûté 276 FCFA). **Non mesuré sur un vrai site vitrine** : aucune clé dans l'environnement de travail de Claude le 15 novembre 2026 ; à mesurer en ligne dans Administration → Consommation. La carte de la formule n'affiche « De quoi créer un site vitrine complet chaque jour » que si cette estimation tient dans l'énergie du jour.

**Dépense maximale (estimation)** :
- Comptes gratuits ensemble : au plus **1 000 FCFA d'énergie par jour** (inchangé, discussions simples comprises), soit 30 000 FCFA par mois en coût compté. Ce qui est vraiment payé dans ces 1 000 FCFA : l'ordinateur E2B des créations (au plus environ 1 000 FCFA, soit 1,7 $, par jour si tout était de l'ordinateur), le reste étant des modèles offerts (Coarena, offres gratuites).
- OpenAI, pour tout Kekeli : au plus **1 $ par jour** (inchangé), soit 590 FCFA par jour, environ **17 700 FCFA (30 $) par mois**.
- Total réellement payé au pire, pour la formule gratuite et OpenAI : environ **2,7 $ par jour (1 600 FCFA)**, soit environ **81 $ (48 000 FCFA) par mois** : **le même maximum qu'avant**, puisque les deux plafonds ne changent pas. En pratique, bien moins (à mesurer en ligne).
- Discussions simples des formules payantes : elles ne sortent pas de leur énergie ; au plus 1 000 par jour et par compte Agence (environ 1 000 FCFA de coût compté au prix de Terra), payées en réalité par les crédits gratuits de Coarena, puis par OpenAI dans son plafond.

**Deux points à décider par le porteur du projet** :
1. **Plafond global de 1 000 FCFA** : à environ 1 FCFA la discussion et 75 FCFA le site, il permet environ **13 sites vitrines gratuits par jour pour tous les comptes ensemble**, moins s'il y a beaucoup de discussions (environ 1 000 discussions simples rempliraient le plafond seules). Quand il est atteint, les comptes gratuits attendent minuit. À relever avec les premiers clients (Administration → Réglages, `free_daily_cap_fcfa`).
2. **Écart entre Gratuite et Pro** : la Gratuite donne maintenant jusqu'à 3 000 FCFA d'énergie par mois (100 par jour), Pro 4 000 FCFA par mois : **1,33 fois** seulement. La carte de Pro n'affiche donc plus « 4 fois plus d'énergie que la formule Gratuite » (ce ne serait plus vrai) mais « L'énergie du mois entier, à utiliser quand vous voulez » (Pro peut tout utiliser en un jour, la Gratuite est limitée à 100 FCFA par jour). Propositions : garder ainsi ; ou donner plus d'énergie à Pro (par exemple 6 000 FCFA, « 2 fois plus », marge minimale estimée autour de 6 %, déconseillé sans mesure) ; ou baisser la Gratuite. Aucun changement fait.

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

## 3. Formules (décidées le 9 novembre 2026, remplacées le 12 novembre 2026 : voir le début de ce document)

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

~~Pas de bonus de bienvenue (décision du 9 novembre 2026). Quand l'énergie est épuisée au milieu d'une demande, le travail s'arrête aussitôt.~~ **Remplacé le 15 novembre 2026** : bonus de bienvenue de 500 FCFA, et une création commencée n'est jamais coupée (dépassement de 150 FCFA au plus, retiré de l'énergie suivante) ; voir la dernière partie de ce fichier.

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

## Fournisseurs gratuits comparés (essais réels du 10 novembre 2026)

Mêmes demandes que la partie « Nouveau site » (restaurant à Lomé ; pressing avec comptes, commandes et espace du gérant, en Laravel), chaque fournisseur seul dans la liste. Énergie = coût du modèle au prix payant équivalent (estimation) + ordinateur. Pour un modèle gratuit d'OpenRouter, Kekeli ne compte que l'ordinateur (le modèle ne coûte rien). Captures : `docs/captures/fournisseurs-gratuits.md`.

| Essai | Fournisseur et modèle | Réussite | Qualité | Durée | Pauses (limites) | Requêtes | Tests | Énergie |
|---|---|---|---|---|---|---|---|---|
| Restaurant (site vitrine) | Gemini 3.5 Flash-Lite | Oui | Correcte : pas d'image en haut, menu qui déborde sur téléphone | 4 minutes 30 | 1 | 27 | — | 59,8 FCFA |
| Restaurant (site vitrine) | Groq `openai/gpt-oss-120b` | **Non** : demande trop grande (8 000 tokens par minute) | — | 0 | — | 0 | — | 0 FCFA |
| Restaurant (site vitrine) | OpenRouter `nvidia/nemotron-3-ultra-550b-a55b:free` | Oui | **La meilleure** : photo, icônes, bouton WhatsApp, menu adapté | 9 minutes | 0 | 27 | — | 14,9 FCFA (ordinateur seulement) |
| Pressing (Laravel) | Gemini 3.5 Flash-Lite | Oui au deuxième essai (le premier s'est arrêté au bout d'une minute sur un refus passager d'OpenCode) | Bonne : comptes, commandes, validation, 13 fichiers | 27 minutes | **8** | 63 | 17 réussis, sécurité vérifiée | 167,1 FCFA |
| Pressing (Laravel) | Groq `openai/gpt-oss-120b` | **Non** (même raison) | — | 0 | — | 0 | — | 0 FCFA |
| Pressing (Laravel) | OpenRouter Nemotron gratuit | **Arrêté** par le quota du jour (50 requêtes) après 28 requêtes, reprise après minuit | Bon début (9 fichiers : commandes, contrôleurs, migration, politique d'accès) | — | 3 puis attente jusqu'à minuit | 28 | — | 0 FCFA au moment de l'arrêt |

**Ce que cela veut dire :**
- **Groq gratuit ne peut pas faire tourner l'agent** : 8 000 tokens par minute alors qu'une seule demande en pèse environ 9 000. Il est gardé dans le code (utile avec une offre payante plus tard), mais **retiré de la liste**.
- **OpenRouter gratuit donne le meilleur résultat**, mais **50 requêtes par jour pour tout Kekeli**, soit environ un site vitrine et demi par jour. Avec 10 $ de crédits achetés une seule fois, ce serait 1 000 requêtes par jour : **dépense non faite**, à décider par le porteur du projet.
- **Gemini 3.5 Flash-Lite reste le fournisseur principal** (environ 500 requêtes par jour), mais sa limite par minute provoque de longues pauses sur les sites avec base de données (8 pauses, 27 minutes).
- **Ordre choisi : Gemini, puis OpenRouter.** Quand Gemini est limité, Kekeli passe à OpenRouter au lieu d'attendre ; les pauses de 17 à 27 minutes seront donc réduites **tant qu'il reste des requêtes OpenRouter dans la journée** (environ un site avec base de données), puis reviendront. Estimation : non mesurée en conditions réelles le 10 novembre, car le quota du jour d'OpenRouter était épuisé par les essais.
- Cloudflare Workers AI restait réservé aux images ce jour-là ; il est entré dans la liste (en dernier recours) le 11 novembre 2026, voir la partie suivante.

## Fournisseurs repris de freellmapi : mesures et nombre de sites par jour (nuit du 10 au 11 novembre 2026)

Même description de restaurant à Lomé que plus haut ; pressing en Laravel avec la chaîne Gemini. Énergie = coût du modèle au prix payant équivalent (estimation) + ordinateur ; pour un modèle gratuit d'OpenRouter, seulement l'ordinateur. Captures : `docs/captures/fournisseurs-gratuits.md`.

| Essai | Fournisseur et modèle | Réussite | Qualité | Durée | Requêtes | Énergie |
|---|---|---|---|---|---|---|
| Restaurant (site vitrine) | Gemini 3.5 Flash-Lite | Oui | Correcte | 2 minutes 12 | 19 | 44,7 FCFA |
| Restaurant (site vitrine) | Gemini 3.1 Flash-Lite | Oui | **Faible** : menu vide dans l'aperçu | 12 minutes 02 | 40 | 58,7 FCFA |
| Restaurant (site vitrine) | OpenRouter `cohere/north-mini-code:free` | Oui | **Bonne** : photo libre, icônes Lucide, bouton WhatsApp, compte rendu complet | 6 minutes 29 | 48 (6 refus passagers par minute) | 13,8 FCFA (ordinateur seulement) |
| Restaurant (site vitrine) | Cloudflare Workers AI `@cf/zai-org/glm-4.7-flash` | **Non mesuré** : réserve du jour (10 000 neurones) déjà prise par les images ; demande mise en attente, **aucune facture** | — | — | 0 | 0 FCFA |
| Restaurant (site vitrine) | Groq `openai/gpt-oss-120b` | Non : 8 788 tokens pour une limite de 8 000 par minute | — | — | 0 | 0,3 FCFA (ordinateur) |
| Pressing (Laravel) | Chaîne Gemini 3.5 puis 3.1 | Oui : 17 tests réussis, sécurité vérifiée, site qui répond | Bonne | **5 minutes 45** (27 minutes avec Gemini 3.5 seul) | 38 | 73,6 FCFA |

**Ce que cela veut dire :**
- Mettre Gemini 3.1 juste après Gemini 3.5 supprime l'essentiel des pauses : quand Gemini 3.5 atteint sa limite par minute, Kekeli passe à Gemini 3.1 au lieu d'attendre (pressing : 5 minutes 45 au lieu de 27 minutes).
- OpenRouter a donné la meilleure qualité avec Nemotron et North, mais **ces deux versions gratuites ne sont pas permises pour des clients** (conditions de NVIDIA et de Cohere, voir la partie suivante) ; et 50 requêtes par jour pour tous ses modèles gratuits ensemble : environ un site vitrine par jour.
- Workers AI partage sa réserve avec la création d'images : il ne servira au code que les jours où il reste des neurones. À mesurer un jour sans images.

### Combien de sites par jour gratuitement (estimations)

Calcul : requêtes gratuites par jour de chaque fournisseur divisées par les requêtes mesurées d'un site (site vitrine : 19 à 48 ; site avec base de données : 38 à 63). Les limites par jour de Google (environ 500 requêtes par modèle) sont **à vérifier dans AI Studio**. Ce sont des **estimations**.

| | Liste | Sites vitrines par jour | Sites avec base de données par jour |
|---|---|---|---|
| **Avant** (10 novembre 2026) | Gemini 3.5, OpenRouter | Environ 20 à 22 | Environ 8, avec de longues pauses |
| **Après** (11 novembre 2026) | Gemini 3.5, Gemini 3.1, OpenRouter (deux modèles), Workers AI | Environ 32 à 35 | Environ 14, presque sans pauses |

- Détail de l'estimation « après » : Gemini 3.5 environ 20 sites vitrines, Gemini 3.1 environ 12, OpenRouter environ 1, Workers AI de 0 à 2 (environ 0,05 $ de neurones par site vitrine pour une réserve de 0,11 $ par jour, moins les images).
- Mistral n'est plus compté : il n'a plus d'offre gratuite pour l'API (11 novembre 2026).
- Le **plafond d'énergie de la formule gratuite** (1 500 FCFA par jour pour tous les utilisateurs gratuits, réglable) correspond à environ 30 sites vitrines : il reste cohérent avec la nouvelle liste.

## Liste corrigée et nombre de sites par jour (11 novembre 2026, après le nettoyage)

Changements : **Mistral retiré** (plus d'offre gratuite pour l'API) ; chez OpenRouter, **Nemotron et North retirés** (versions gratuites non permises pour des clients) et **Gemma 4 31B** (servi par Google) à la place. Raisons et phrases des conditions : `docs/plan.md`, partie 22. Ordre : `gemini,gemini/gemini-3.1-flash-lite,openrouter,workersai`.

| Fournisseur | Part estimée, sites vitrines par jour | Mesure |
|---|---|---|
| Gemini 3.5 Flash-Lite | Environ 20 | Mesuré (19 à 27 requêtes par site vitrine) |
| Gemini 3.1 Flash-Lite | Environ 12 | Mesuré (40 requêtes par site vitrine) |
| OpenRouter, Gemma 4 31B gratuit | De 0 à 1 | **Non mesuré** : limité par Google au premier essai (réserve partagée) |
| Cloudflare Workers AI | De 0 à 2 | **Non mesuré** : réserve partagée avec les images |
| **Total** | **Environ 32 à 35 sites vitrines, ou environ 14 sites avec base de données** | Estimation |

Le total ne change presque pas : Mistral n'était compté pour rien (pas de clé), et OpenRouter ne pesait qu'environ un site par jour. **Avant les fournisseurs de freellmapi** : environ 20 à 22 sites vitrines ou 8 sites avec base de données. Ce sont des **estimations** (les limites par jour de Google sont à vérifier dans AI Studio).


## Une question dans la discussion : énergie mesurée (essai réel du 12 novembre 2026)

Depuis le 12 novembre 2026, une question (« Où héberger mon site ? », « Combien ça coûte ? ») est répondue **sans ordinateur virtuel** et sans toucher au site : le Runner envoie une seule requête au modèle (adresse `POST /question`, `runner/src/questions.js`), avec le type de site, le pays et la liste des fichiers. Essai réel avec Gemini 3.5 Flash-Lite (offre gratuite), sur les sites inventés « Pressing Éclat » (Laravel) et « Restaurant Le Palmier » (site vitrine) :

| Question | Requêtes | Tokens envoyés | Tokens reçus | Durée | Énergie (prix payant de Gemini) |
|---|---|---|---|---|---|
| « Où héberger mon site et combien ça coûte ? » (site avec base de données) | 1 | 784 | 181 | 2,5 secondes | environ 0,0007 $, soit **0,4 FCFA** |
| « Où héberger mon site ? » (site vitrine) | 1 | 769 | 144 | 2,8 secondes | environ 0,0006 $, soit **0,35 FCFA** |
| « Faut-il changer la couleur du bouton ? » (doute) | 1 | 767 | 83 | 2,4 secondes | environ 0,0005 $, soit **0,3 FCFA** |

- **Aucune seconde d'ordinateur** (0 FCFA d'ordinateur), contre plusieurs minutes pour une modification.
- Une question coûte donc **environ 1 % de l'énergie gratuite du jour** (30 FCFA) : environ 75 à 100 questions par jour, contre environ 1 petite page et 1 modification. Une modification ou une page coûte plusieurs dizaines de fois plus (dizaines de requêtes et temps d'ordinateur).
- Côté fournisseurs gratuits, une question prend **une seule requête** sur la réserve du jour (par exemple environ 500 requêtes par jour pour Gemini Flash-Lite, à vérifier dans AI Studio).
- Ces montants sont des **estimations** au prix payant de Gemini 3.5 Flash-Lite (0,30 $ par million de tokens envoyés, 2,50 $ par million de tokens reçus) ; avec l'offre gratuite, la dépense réelle est nulle.

## Réponses aux questions avec OpenAI (12 novembre 2026)

Comparaison réelle sur 12 questions (détails et choix : `docs/plan.md`, partie 26.15 ; réponses : `runner/mesures/comparaison-modeles.json`). Coût par réponse au prix officiel, 1 $ = 590 FCFA, mesuré avec les tokens réels (cache compris) :

| Modèle | Coût moyen par réponse | Coût le plus haut | Formule |
|---|---|---|---|
| GPT-6 Luna | 0,14 FCFA | 0,5 FCFA | Gratuite |
| GPT-6.1 Terra | 1,5 FCFA | 6,7 FCFA (réponse riche avec recherche) | Pro, Expert, Agence |
| Gemini 3.6 Flash (prix payant, non utilisé) | 3,6 FCFA | 5,3 FCFA | Aucune |
| Gemini 3.5 Flash-Lite (prix payant, gratuit pour Kekeli) | 2,3 FCFA | 4,0 FCFA | Secours |

- Formule Gratuite (30 FCFA par jour) : environ 200 réponses avec GPT-6 Luna, bien au-delà d'un usage normal.
- Formule Pro (4 000 FCFA par mois) : environ 2 700 réponses avec GPT-6.1 Terra.
- Plafond OpenAI de 1 $ par jour pour tout Kekeli : environ 400 réponses de GPT-6.1 Terra ou 4 000 de GPT-6 Luna ; au-delà, les fournisseurs gratuits répondent.
- Dictée vocale transcrite par OpenAI (seulement quand le navigateur ne sait pas le faire) : 0,0045 $ par minute, soit environ 2,7 FCFA la minute.
- Ce sont des mesures sur 12 questions : les coûts réels seront suivis dans Administration → Consommation.

## FreeLLMAPI (15 novembre 2026, à la place de Coarena)

Détail : `docs/freellmapi.md` et `docs/captures/freellmapi.md`.
- **Dépense réelle : 0 $.** FreeLLMAPI tourne à côté du Runner (aucune application ni serveur en plus) ; ses fournisseurs (Groq aujourd'hui, LongCat et Z.ai quand leurs clés seront placées) sont des offres gratuites, qui refusent au-delà de leur quota sans rien facturer. L'abonnement « Premium » de FreeLLMAPI (19 $ par an) n'est pas pris.
- **Énergie comptée au prix de l'offre payante de chaque famille (estimation)** : gpt-oss-120b 0,15 $ et 0,60 $ par million de tokens (entrée et sortie), gpt-oss-20b 0,075 $ et 0,30 $, Qwen 3.8 27B 0,29 $ et 0,59 $, GLM-4.7-Flash 0,07 $ et 0,40 $, autre famille 0,30 $ et 1,20 $. Mesuré : **0,0005 à 0,0016 $ par réponse**, soit environ 0,3 à 1 FCFA.
- **Capacité avec Groq seul (mesurée)** : 1 à 2 réponses par minute et par modèle (8 000 tokens par minute), 3 modèles ; environ 50 réponses par jour et par modèle (200 000 tokens par jour). LongCat (environ 500 000 tokens par jour, estimation à confirmer) et Z.ai ajouteront la capacité des constructions.
- **Garde-fous** : part de chaque client par jour (Gratuite 60, Pro 200, Expert 400, Agence 800) et plafond global de 2 000 demandes par jour, réglables dans Administration → Modèles. Le plafond OpenAI (10 $ par jour) et le plafond des comptes gratuits (3 000 FCFA par jour) ne changent pas.

## Modèles de Coarena (banc d'essai réel du 14 novembre 2026, historique : Coarena supprimé le 15 novembre 2026)

Détail : `runner/mesures/modeles-coarena.md` et `docs/coarena.md`. **Dépense du banc : 0,73 $ de crédits Coarena** (usage gratuit, dans la limite de 5 $ par jour et par compte) et **0 $ chez OpenAI**.

| Modèle | Coût des 7 tâches (crédits Coarena) | Durée totale | Qualité (sur 10) |
|---|---|---|---|
| GPT-6.1 Sol | 0,148 $ | 110 s | 10 |
| GPT-6.1 Terra | 0,060 $ | 105 s | 9,5 |
| GPT-6 Luna | 0,006 $ | 77 s | 9 |
| Kimi K3.1 Pro | 0,243 $ | 111 s | 9 |
| Gemini 3.8 Flash | 0,088 $ | 89 s | 8,5 |
| GLM 5.3 Flash | 0,005 $ | 84 s | 8 |

- **Ce que Kekeli paie réellement** : 0 $ tant que l'usage reste dans les 5 $ gratuits par jour de chaque compte (11 comptes, soit au plus 55 $ d'usage par jour, si Coarena maintient ses conditions). Au-delà, ou si Coarena retire l'accès, OpenAI (plafond du jour, 1 $ au départ) puis les offres gratuites prennent le relais.
- **Ce que l'énergie du client compte** : le prix de l'offre payante de chaque modèle (`model_prices`, préfixe `coarena/`), comme pour les offres gratuites. Les prix des modèles OpenAI sont les prix officiels ; ceux de GLM et de Kimi sont **estimés** d'après la dépense mesurée par Coarena. La marge reste donc garantie le jour où un modèle devient payant.
- **Une seule expérience pour tous** (décision du 14 novembre 2026) : la formule Gratuite reçoit les mêmes modèles de qualité ; son crédit (30 FCFA par jour, plafond global de 1 000 FCFA par jour pour tous les comptes gratuits) **n'est pas changé** : un site avec GPT-6.1 Sol coûte plus d'énergie qu'avec un modèle gratuit, donc il est atteint plus vite. Le montant du crédit gratuit et des formules reste à décider par le porteur du projet, avec ces coûts mesurés.

## Multiplicateurs d'énergie des formules (14 novembre 2026, pull request « Accueil et abonnements »)

Calculés à partir des réglages actuels (`config/kekeli.php`, Administration → Réglages), sans rien inventer. La formule gratuite donne 30 FCFA d'énergie par jour, soit **900 FCFA par mois** de 30 jours.

| Formule | Énergie par mois | Par rapport à | Multiplicateur réel | Affiché sur la carte |
|---|---|---|---|---|
| Pro | 4 000 FCFA | Gratuite (900 FCFA) | **4,44** | « 4 fois plus d'énergie que la formule Gratuite » |
| Expert | 11 000 FCFA | Pro (4 000 FCFA) | **2,75** | « Près de 3 fois plus d'énergie que Pro » |
| Agence | 28 000 FCFA | Pro (4 000 FCFA) | **7** | « 7 fois plus d'énergie que Pro » |

Règle d'affichage (`PlanPerks::ratioText`) : le chiffre est relu dans les réglages à chaque affichage ; s'il n'est pas entier, on affiche le nombre entier juste en dessous (« 4 » pour 4,44, jamais plus que la vérité), ou « près de » le nombre entier juste au-dessus quand il est à moins d'une demie (« près de 3 » pour 2,75). Si le porteur du projet change une énergie, les cartes changent seules.

**Le texte demandé « 10 fois plus d'énergie que la formule Gratuite » n'est pas vrai aujourd'hui** : il n'est pas affiché.

### Valeurs rondes proposées (à décider par le porteur du projet ; rien n'est changé)

Coûts **estimés** avec la méthode de `docs/plan.md`, partie 25.3 (énergie comptée au coût réel, attente des ordinateurs et 3 % de frais de paiement : le coût maximal vaut environ 1,18 fois l'énergie pour Pro, 1,14 pour Expert, 1,11 pour Agence). Taux : 1 $ = 590 FCFA.

| Proposition | Énergie par mois | Affiché | Coût maximal estimé (tout utilisé) | Marge minimale estimée |
|---|---|---|---|---|
| **A. Ne rien changer (conseillé)** | Pro 4 000, Expert 11 000, Agence 28 000 | 4 fois, près de 3 fois, 7 fois | Pro 4 705 FCFA (8,0 $), Expert 12 560 FCFA (21,3 $), Agence 30 940 FCFA (52,4 $) | 37 %, 37 %, 38 % |
| B. Expert à 12 000 FCFA | Expert 12 000 | « 3 fois plus d'énergie que Pro » (exact) | 13 700 FCFA (23,2 $) | environ 31 % |
| C. Pro « 10 fois » la Gratuite en augmentant Pro | Pro 9 000 | « 10 fois » | environ 10 600 FCFA (18 $) pour un prix de 7 500 FCFA | **perte d'environ 41 %** : déconseillé |
| D. Pro « 10 fois » la Gratuite en baissant la Gratuite | Gratuite 13 FCFA par jour (390 par mois) | « 10 fois » | inchangé pour Pro | marges inchangées, mais la formule gratuite ne permet plus qu'environ 1 site vitrine tous les 4 à 5 jours : déconseillé |

**Report de l'énergie au mois suivant** (Pro, Expert, Agence, au plus un mois d'énergie) : un mois peut coûter jusqu'au double, mais seulement après un mois payé et non utilisé ; sur deux mois, le coût maximal ne dépasse jamais deux mois d'énergie, donc **la marge minimale sur deux mois reste la même** (estimation). Le report n'est donné que si le mois précédent a été payé pour la même formule (vérifié par les paiements validés).

**Priorité et projets en même temps** : aucun coût de plus (même énergie, comptée au coût réel) ; la priorité change seulement l'ordre d'attente.

## Recherche sur Internet comme Claude : requêtes et plafond du mois (15 novembre 2026)

Mesure réelle (banc d'essai de 12 questions, `docs/captures/recherche.md`) : une question qui cherche envoie maintenant **2 à 3 requêtes** aux fournisseurs en moyenne (26 requêtes pour 10 questions qui ont cherché ; 5 au plus par question) : le nom exact entre guillemets, les mots du modèle, parfois une variante en anglais ou une reformulation, et la lecture des meilleures pages (extraction de Tavily, 1 crédit pour 5 pages). Avant : 1 requête.

- **Une question du client = une seule « recherche du jour »** (Gratuite 3, Pro 20, Expert 40, Agence 80, inchangé), quel que soit le nombre de requêtes internes (`agent_runs.web_searches`).
- **Les requêtes réelles sont comptées à part** (`agent_runs.web_search_requests`) pour le plafond du mois de tous les clients ensemble (`web_search_monthly_cap`, Administration → Réglages).
- **Offres gratuites** (pages officielles lues le 15 novembre 2026) : Tavily, 1 000 crédits par mois (recherche « basic » : 1 crédit, texte des pages compris ; extraction : 1 crédit pour 5 pages) ; Exa, 10 $ offerts chaque mois (recherche 7 $ pour 1 000, texte 1 $ pour 1 000 pages, soit environ 0,015 $ par requête de 8 pages : **environ 600 à 660 requêtes**). Aucune carte, aucun paiement à l'usage (vérifié : 0 chez Tavily).
- **Réglage proposé : 1 500 requêtes par mois** (valeur par défaut de la configuration ; l'ancienne valeur était 900 recherches). Avec 2,6 requêtes par question, cela fait **environ 570 questions avec recherche par mois pour tous les clients**, en restant sous 1 000 + 600 environ. Si la valeur 900 a été enregistrée dans Administration → Réglages, elle y reste (environ 340 questions par mois) : **à relever à 1 500 par le porteur du projet** s'il est d'accord. Au-delà du plafond, Kekeli répond sans chercher et sans le dire.
- **Énergie** : chaque requête compte 0,008 $ (prix d'un crédit Tavily payant, estimation), soit **environ 12 FCFA pour une question qui cherche** (2,6 × 0,008 × 590), plus le modèle (environ 1 à 2 FCFA), contre environ 5,9 FCFA avant. Le coût réel reste 0 FCFA avec les offres gratuites. **À décider par le porteur du projet** : garder 0,008 $ par requête (énergie honnête au prix payant) ou compter 0,004 $ (prix d'un crédit de la formule payante « Project » de Tavily, environ 30 $ pour 4 000 crédits, à vérifier sur leur site).
- **Crédits utilisés pour ce chantier** (estimation) : environ 135 requêtes Tavily (banc avant, deux bancs intermédiaires, banc après, essais et captures), sur 1 000 par mois ; Exa n'a pas servi (Tavily a toujours répondu).

## Créer comme Lovable et Claude : coûts réels d'un site vitrine, bonus et dépense maximale (15 novembre 2026)

**Mesure réelle de 5 sites vitrines** (comptes de formule Gratuite inventés, GPT-6.1 Terra chez Coarena, ordinateur E2B ; détail : `docs/captures/creation.md`) :

| Site | Coût compté (modèle + ordinateur) | Durée |
|---|---|---|
| Formations en IA (questions, « Choisis pour moi ») | 61 FCFA (52,4 + 8,6) | 339 s |
| Restaurant (questions, réponses écrites) | 48 FCFA (40,9 + 7,3) | 304 s |
| Boutique de pagnes (demande détaillée) | 52 FCFA | 274 s |
| Salon de coiffure (questions, « Choisis pour moi ») | 52 FCFA | 230 s |
| Pressing (demande détaillée) | 52 FCFA | 246 s |

- **53 FCFA en moyenne** au prix officiel des modèles (estimation de l'énergie comptée) : environ 85 % de modèle, 15 % d'ordinateur. Les questions coûtent environ 1 FCFA (discussion simple, sans énergie).
- **Le bonus de bienvenue (500 FCFA) permet environ 8 à 10 sites vitrines** ; l'énergie du jour (100 FCFA) permet un site complet et quelques retouches. L'estimation de 75 FCFA est remplacée par **60 FCFA** (`free_site_estimate_fcfa`).
- Le site du porteur du projet s'était arrêté en ligne : son coût dépassait l'énergie restante du jour. Depuis cette pull request, il va au bout (dépassement de 150 FCFA au plus, retiré du lendemain).
- **Dépense réelle de Kekeli pour un site** : surtout l'ordinateur (environ 7 à 9 FCFA, soit environ 0,013 $) tant que Coarena répond dans ses 5 $ gratuits par jour et par compte ; sinon le modèle d'OpenAI, dans son plafond de 1 $ par jour.
- **Démarrage anticipé** : un ordinateur allumé pendant les questions et jamais utilisé coûte au plus 5 minutes d'ordinateur (environ 8 FCFA, 0,014 $), payées par Kekeli (hors énergie du client).

**Dépense maximale (estimation)** :
- Comptes gratuits ensemble : **3 000 FCFA d'énergie comptée par jour** (au lieu de 1 000), bonus compris, plus au plus 150 FCFA de dépassement pour finir les créations en cours (garde-fou) et la dernière requête de chaque création qui tourne (10 au plus en même temps) : **environ 3 150 à 4 500 FCFA par jour au pire**, soit environ **95 000 à 135 000 FCFA par mois** en coût compté (le porteur du projet avait accepté environ 90 000 FCFA par mois pour le plafond seul).
- Ce qui est **vraiment payé** dans ce montant : l'ordinateur E2B (environ 15 % d'un site, soit environ 500 à 700 FCFA par jour au pire, environ 0,9 à 1,2 $), le reste étant des modèles offerts (Coarena, offres gratuites) ; si tout passait par OpenAI, son plafond de **1 $ par jour** (inchangé) s'applique.
- **Total réellement payé au pire** pour la formule gratuite et OpenAI : environ **2,2 $ par jour, soit environ 66 $ (39 000 FCFA) par mois** (estimation), plus les ordinateurs allumés à l'avance et jamais utilisés (environ 0,014 $ chacun). Si l'ordinateur pesait plus que mesuré, le maximum théorique (tout en ordinateur) serait d'environ 5,3 $ par jour pour les gratuits, plus 1 $ pour OpenAI.
- Bonus des comptes déjà créés : donné une seule fois, il n'augmente pas le plafond du jour (il y est compté).

## Répondre comme Claude et ChatGPT : coût d'une réponse (15 novembre 2026)

Mesure réelle sur 20 vraies questions (`runner/scripts/banc-reponses.mjs`, détails dans `docs/captures/reponses.md`), avec le modèle des réponses de la production, GPT-6.1 Terra (Coarena, puis OpenAI en secours). Coût **estimé au prix payant d'OpenAI** (1 $ et 5 $ par million de tokens, 600 FCFA pour 1 $) ; tant que les crédits gratuits du jour de Coarena suffisent, la dépense réelle est nulle.

| | Avant | Après |
|---|---|---|
| Coût moyen d'une réponse (sans le tout premier message) | environ 1,85 FCFA | environ 1,80 FCFA |
| Tout premier message (consigne pas encore en cache) | environ 5,7 FCFA | environ 5,9 FCFA (même cas) |
| Longueur moyenne | 153 mots | 170 mots |

Gemini 3.8 Flash, mesuré sur les mêmes questions : environ 6 FCFA par réponse à son prix officiel (0,75 $ et 3,75 $ par million de tokens ; ses consignes ne sont pas gardées en cache chez Coarena), réponses de 291 mots en moyenne : non retenu. Le plafond de dépense OpenAI du jour (1 $) ne change pas.

## OpenAI d'abord, Coarena en dernier : coût réel d'un message et d'un site vitrine (15 novembre 2026)

Décision du porteur du projet (Coarena trop lent) : OpenAI en premier (GPT-6 Luna pour les discussions, GPT-6.1 Terra et Sol pour les créations), puis Gemini gratuit, OpenRouter, Workers AI, Coarena en dernier. **Plafond OpenAI du jour : 10 $** (au lieu de 1 $), alertes à 80 % et 100 %. Mesures réelles (site local, vrais modèles ; `docs/captures/premier-mot.md`).

| Mesure | Coût réel |
|---|---|
| Message « Salut » (GPT-6 Luna, moyenne de 10) | 0,000093 $ (environ 0,06 FCFA) |
| Question courte (moyenne de 10) | 0,000224 $ (environ 0,13 FCFA) |
| Question longue avec tableau (moyenne de 10) | 0,000431 $ (environ 0,26 FCFA) |
| Site vitrine (moyenne de 2 sites réels) | environ 61 FCFA (0,10 $) : GPT-6.1 Terra environ 0,09 $, décision de GPT-6 Luna 0,001 $, ordinateur E2B environ 0,01 $ |

**Estimations par jour et par mois** (moyenne d'environ 0,00025 $ par message et 0,10 $ par site vitrine ; 600 FCFA pour 1 $ ; 30 jours ; les recherches sur Internet restent dans les crédits gratuits de Tavily et d'Exa) :

| Volume | Par jour | Par mois | Dont OpenAI par jour (plafond : 10 $) |
|---|---|---|---|
| 100 messages | environ 0,03 $ (15 FCFA) | environ 0,8 $ (450 FCFA) | 0,03 $ |
| 1 000 messages | environ 0,25 $ (150 FCFA) | environ 7,5 $ (4 500 FCFA) | 0,25 $ |
| 10 sites vitrines | environ 1,0 $ (610 FCFA) | environ 31 $ (18 300 FCFA) | environ 0,9 $ |
| 50 sites vitrines | environ 5,1 $ (3 050 FCFA) | environ 153 $ (91 500 FCFA) | environ 4,8 $ |

Avec 10 $ par jour, OpenAI couvre environ 100 sites vitrines par jour (ou des dizaines de milliers de messages) ; au-delà, Gemini puis les suivants prennent le relais. **Ces montants sont des estimations** tirées de mesures sur peu d'essais ; ils seront relus en ligne dans Administration → Consommation.

**Énergie des clients** : le coût réel de chaque message et de chaque site reste décompté de l'énergie (sauf les discussions simples, gratuites pour le client). Un site vitrine à environ 61 FCFA tient dans les 100 FCFA par jour de la formule Gratuite.

## GPT-6.1 Terra pour les discussions : coût d'une réponse et plafond OpenAI (15 novembre 2026)

Mesure réelle sur le banc de 30 questions riches (`runner/mesures/banc-reponses-b30-*.json`, prix officiels d'OpenAI) ; ce sont des estimations tirées de ces mesures.

| Modèle des discussions | Note (sur 10) | Tokens (entrée, sortie) | Coût d'une réponse |
|---|---|---|---|
| GPT-6 Luna (avant) | 7,80 | environ 1 600, 330 | 0,0004 $ (0,24 FCFA) |
| **GPT-6.1 Terra, sans réflexion (choisi)** | **9,03** | environ 1 600, 520 | **0,0041 $ (2,46 FCFA)** |
| GPT-6.1 Terra, réflexion « low » | 8,95 | | 0,0033 $ (2,0 FCFA) |
| GPT-6.1 Terra, réflexion « medium » | | | 0,0034 $ (2,05 FCFA) |

- **Plafond OpenAI de 10 $ par jour** : environ **2 400 réponses riches par jour** (contre environ 25 000 avec GPT-6 Luna). Les constructions (environ 0,10 $ par site vitrine) partagent ce plafond. Au-delà, Kekeli passe à Gemini gratuit sans rien dire au client.
- **Par jour et par mois** (réponses riches seulement) : 100 messages, environ 0,41 $ par jour (12 $ par mois) ; 1 000 messages, environ 4,1 $ par jour (123 $ par mois). Une salutation ou une question courte coûte beaucoup moins.
- Les discussions simples ne retirent toujours rien à l'énergie du client (décision du 15 novembre 2026) : ce coût est payé par Kekeli. **À surveiller avec les premiers clients**, dans Administration → Consommation.
- **Rester toujours éveillé sur Laravel Cloud** (si les mesures en ligne le demandent, rien n'est changé sans accord) : site environ 6,5 $ par mois, Runner environ 6,5 $, base environ 19,7 $ (prix officiels notés le 9 novembre 2026, 730 heures par mois).

## Affiches et images : coût de chaque générateur (15 novembre 2026)

Banc de 10 affiches et 5 images (`runner/scripts/banc-images.mjs`, `docs/captures/fonctions/images/banc.json`) ; prix officiels lus le 15 novembre 2026 (OpenAI : 5 $, 8 $ et 30 $ par million de tokens de texte, d'image donnée et d'image produite ; Cloudflare : 0,011 $ pour 1 000 neurones au-delà de 10 000 gratuits par jour). Ce sont des **estimations** tirées de ces essais (1 $ = 590 FCFA).

| Générateur | Note moyenne | Coût d'une image | Temps | Limite gratuite par jour |
|---|---|---|---|---|
| **GPT Image 2.6 « flare », texte dessiné (choisi, toutes les formules)** | **8,8** (images sans texte : 9) | 0,026 à 0,045 $ (15 à 26 FCFA), plus la relecture (environ 0,005 $) | 6 à 9 s | payant ; compté dans le plafond OpenAI de 10 $ par jour (environ 250 affiches si rien d'autre ne l'utilise) |
| FLUX.1 schnell, texte posé par le code (secours) | 7 | 0 (environ 58 neurones) | 2 à 5 s | environ 170 images |
| FLUX.2 dev | 6 à 7 | 0 (environ 3 000 neurones) | 11 à 17 s | environ 3 images : inutilisable en production |
| FLUX.2 klein 9B | 4,5 (texte dessiné) | 0 (environ 1 364 neurones) | 2 s | environ 7 images |
| Leonardo Phoenix et Lucid | 2 (dessiné), 6 (code) | 0 (environ 2 400 à 2 800 neurones) | 2 à 3 s | environ 4 images |
| Gemini (Nano Banana, 5 modèles) | non essayé | 0,034 à 0,134 $ | | **aucune offre gratuite** pour les images |
| OpenRouter | | | | **aucun modèle d'images gratuit** |

- **Règle de coût** : aucun modèle gratuit n'atteint 8 sur 10, et une affiche de qualité ChatGPT coûte 15 à 26 FCFA (moins de 50 FCFA) : OpenAI passe en premier, sans arrêt avant fusion.
- **Formule Gratuite** (100 FCFA par jour, plus 500 FCFA de bienvenue) : environ 4 affiches par jour, contre une vingtaine avant avec le générateur gratuit. Le prix réel reste décompté de l'énergie.
- **Banc** : environ 0,95 $ dépensés chez OpenAI ; la réserve gratuite de Cloudflare du 15 novembre 2026 a été épuisée par la présélection (33 images).

## Documents : coût mesuré (15 novembre 2026)

Rédaction de 5 documents réels (`runner/scripts/banc-documents.mjs`) : GPT-6.1 Sol 0,015 à 0,018 $ par document (9 à 11 FCFA), Gemini gratuit 0 $ (utilisé quand le crédit OpenAI était épuisé) ; mise en page dans l'atelier : 2 conversions LibreOffice au lieu d'une pour un document avec sommaire (quelques secondes de plus, coût de l'ordinateur négligeable). Estimations.

## Le meilleur du gratuit : capacité par jour (15 novembre 2026)

Mesures réelles : `docs/captures/gratuit.md`. Aucune dépense ; ce sont des **estimations** de capacité tant que de vrais clients ne sont pas mesurés.

| Tâche | Capacité gratuite par jour (estimation) | Limite qui décide |
|---|---|---|
| Discussions | environ 600 à 650 réponses | 500 de Gemini 3.1 Flash-Lite, environ 30 par modèle chez Groq, 50 chez OpenRouter, environ 25 chez Workers AI |
| Documents | environ 100 à 250 | part des 500 demandes de Gemini 3.5 Flash-Lite, puis Z.ai (une à la fois) |
| Sites vitrines | environ 25 (19 demandes chacun) | 500 demandes de Gemini 3.5 Flash-Lite |
| Applications | environ 7 (58 à 70 demandes chacune) | idem |
| Images et affiches | environ 150 | 10 000 neurones de Cloudflare (environ 58 par image) |

Soit **environ 60 à 80 clients gratuits par jour** (8 discussions, 1 document et une création de temps en temps chacun). Au-delà : attente (« Kekeli est très sollicité ») ou OpenAI en dernier s'il a du crédit. Coût réel pour Kekeli : 0.
