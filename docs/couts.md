# Coûts de Kekeli et proposition de formules

Mesures faites le 8 novembre 2026 avec de vrais essais : ordinateur virtuel E2B, agent OpenCode, Gemini 3.5 Flash-Lite (offre gratuite de Google AI Studio). Chaque type de demande a été fait **deux fois** : ce sont des premières moyennes, à confirmer avec plus d'essais.

**Rien n'est fixé.** Les formules ci-dessous sont des **propositions** qui attendent l'accord du porteur du projet. Dans le site, elles ne sont pas publiées : la page « Formules » montre seulement la formule gratuite et « Les formules payantes sont en préparation ».

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
- **Recommandation** : passer le réglage « Minutes sans demande avant la pause » de 30 à **10 minutes**. L'aperçu se rallume en moins d'une minute avec le bouton « Rallumer l'aperçu ». Ce réglage se change dans l'administration ; je ne l'ai pas changé sans votre accord.

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

## 3. Proposition de formules (à valider)

### Le principe : « l'énergie qui se recharge »

- Chaque formule donne une **énergie par jour**, exprimée dans le site en **FCFA de coût réel** (modèle + ordinateur au travail). L'utilisateur, lui, voit un pourcentage et des messages simples.
- L'énergie se recharge **chaque jour à minuit, heure de Lomé**. Je propose la recharge quotidienne pour toutes les formules : c'est simple à comprendre, et le coût maximal d'un mois est connu d'avance (30 fois l'énergie du jour). Des recharges toutes les 5 heures (comme Claude Code) restent possibles dans les réglages, mais il faudrait alors ajouter un plafond par semaine pour garder la marge sous contrôle.
- **Avantage clé** : comme l'énergie est comptée en coût réel, **la marge est garantie quel que soit le modèle choisi**. Si l'on passe à un modèle plus cher, l'utilisateur fait moins de demandes avec la même énergie ; la marge ne bouge pas.

### Les formules proposées

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

### Si l'étape 6 montre qu'il faut un modèle plus fort

Avec Claude Sonnet 5.5, la même énergie donne environ **5 fois moins de demandes** (la marge reste la même). Exemple : la formule Pro ne permettrait plus qu'environ 1 petite page par jour. Il faudrait alors augmenter l'énergie, donc les prix, ou utiliser un modèle économique pour les petites tâches et un modèle puissant pour les tâches difficiles, comme prévu dans le plan. **Claude Haiku 5.5** est le moins cher de tous (environ 3 fois moins que Gemini 3.5 Flash-Lite) : à essayer en priorité à l'étape 6.

### Coûts fixes à couvrir (estimations)

| Poste | Coût estimé par mois |
|---|---|
| Laravel Cloud (site et base de données) | 5 à 20 $ (3 000 à 12 000 FCFA) |
| Hébergement du Runner | environ 5 $ (3 000 FCFA) |
| Nom de domaine kekeli.dev | environ 1 $ (600 FCFA) |
| E2B formule Pro (au-delà de 20 ordinateurs en marche en même temps) | 150 $ (88 500 FCFA), seulement plus tard |

Au départ, environ **7 000 à 16 000 FCFA par mois** : couverts par environ 2 à 3 abonnements Pro, ou 4 à 8 abonnements Découverte (avec une utilisation moyenne).

## 4. Ce qu'il faut décider

1. Les **prix** et l'**énergie** de chaque formule (ou d'autres chiffres).
2. La **recharge** : chaque jour à minuit (proposé), ou toutes les 5 heures avec un plafond par semaine.
3. Le passage de l'attente avant la pause de **30 à 10 minutes**.

Une fois validés, il suffit de les entrer dans **Administration → Réglages** et de cocher « Publier les formules ».
