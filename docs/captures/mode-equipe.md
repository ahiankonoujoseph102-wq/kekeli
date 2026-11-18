# Mode équipe, phase 1 (MVP) : résultats et captures (18 novembre 2026)

Étude : `docs/multi-agents-etude.md`. Détails : `docs/plan.md`, partie 26.46. Mesures brutes : `runner/mesures/banc-equipe.json`.

## Les écrans (téléphone simulé, compte administrateur d'essai, données inventées)

Le plan d'une application : « Construire ce plan » (agent unique, chemin normal) et, **pour un administrateur seulement**, « Construire en mode équipe (essai) ». Un client ne voit jamais ce second bouton.

![Le plan et les deux boutons](mode-equipe/1-plan-deux-boutons.png)

Pendant une construction en mode équipe : les missions cochées une à une, avec leur rôle, leur durée et leurs requêtes.

![Les missions de l'équipe](mode-equipe/2-missions-en-cours.png)

## Le banc : agent unique contre équipe

Méthode : 3 applications réelles, chacune construite une fois par l'agent unique habituel et une fois par l'équipe, dans un vrai ordinateur E2B, avec les mêmes modèles gratuits (Gemini 3.5 Flash-Lite puis Gemini 3.1 Flash-Lite), par le même code que pour un client (`runner/scripts/banc-equipe.mjs`). Chaque demande a 10 critères concrets, notés par un même juge (Gemini gratuit) à partir de preuves prises dans l'ordinateur : routes, fichiers, extraits de règles, tests, et le texte des pages vraiment ouvertes.

| Application | Mode | Critères (juge) | Requêtes | Minutes | Tests | Coût équivalent payant (modèle + E2B) |
|---|---|---|---|---|---|---|
| Salon Belle Afi | Agent unique | **10/10** | 72 | 9,6 | 21 réussis | 0,21 $ + 0,03 $ |
| Salon Belle Afi | Équipe | 9/10 | 77 | 21,4 | 22 réussis | 0,22 $ + 0,06 $ |
| Plateforme de formation Savoir Plus | Agent unique | 4/10 | 53 | 7,1 | 20 réussis | 0,17 $ + 0,02 $ |
| Plateforme de formation Savoir Plus | Équipe | 5/10 | 103 | 13,7 | 22 réussis | 0,32 $ + 0,04 $ |
| Boutique Ama Pagnes | Agent unique | 2/10 | 35 | 3,4 | 3 en échec, 14 réussis | 0,11 $ + 0,01 $ |
| Boutique Ama Pagnes | Équipe | 9/10 | 126 | 11,9 | 24 réussis | 0,28 $ + 0,03 $ |
| **Total** | **Agent unique** | **16/30** | **160** | **20,1** | | **0,49 $ + 0,06 $** |
| **Total** | **Équipe** | **23/30** | **306** | **47,0** | | **0,81 $ + 0,13 $** |

Les coûts sont des **estimations** au prix payant de Gemini 3.5 Flash-Lite (les essais eux-mêmes ont coûté 0 $ : offres gratuites). Les minutes de l'équipe comprennent beaucoup d'attentes dues à la limite de 15 requêtes par minute des offres gratuites. Un premier essai de l'équipe sur le salon, avec la version qui n'imposait pas la mission de la page d'accueil, avait donné 8/10 (81 requêtes, 8,7 minutes).

## Ce que montrent les captures (relecture faite par Claude, plus sévère que le juge)

Le juge a été **trop indulgent avec l'équipe** sur deux points visibles : la page d'accueil de la boutique est restée celle du point de départ, et l'administration garde les « demandes » du point de départ.

**Salon** : l'agent unique est meilleur (accueil avec le tableau des prestations et leurs prix ; administration qui gère les rendez-vous et les prestations). L'accueil de l'équipe montre « Nos prestations » sans rien dessous, et son administration est encore celle du point de départ.

![Salon : accueil et administration](mode-equipe/comparaison-salon.png)

**Formation** : proche. L'agent unique est plus propre ; l'équipe a une administration plus riche (chiffre d'affaires, inscriptions) mais garde les « demandes » du point de départ. Aucune des deux n'a mis de formations d'essai dans le catalogue.

![Formation : accueil et administration](mode-equipe/comparaison-formation.png)

**Boutique** : l'équipe couvre bien plus de fonctions (panier, commandes, stock, gestion des pagnes et des commandes) mais **a laissé l'accueil du point de départ** (« Envoyez votre demande en ligne »). L'agent unique a un vrai accueil avec les pagnes, mais s'est arrêté avant le panier, les commandes et l'administration.

![Boutique : accueil et administration](mode-equipe/comparaison-boutique.png)

## Conclusion honnête

- **Fonctions couvertes** : l'équipe en couvre plus (23 critères contre 16 selon le juge), surtout parce que l'agent unique s'arrête parfois trop tôt avec les modèles gratuits (boutique, formation).
- **Finition visible** : l'agent unique est souvent plus propre (accueil réel, moins de restes du point de départ) ; l'équipe oublie des finitions entre les missions.
- **Coût** : l'équipe demande environ **1,9 fois plus de requêtes** et **1,7 fois plus de coût de modèle** ; elle est plus lente avec les limites par minute des offres gratuites.
- **Taille de l'échantillon** : un seul essai par application et par mode ; les modèles gratuits sont irréguliers. Ces chiffres sont des indications, pas une preuve.
- La règle de décision de l'étude (« nettement mieux pour au plus 2,5 fois le coût ») **n'est pas clairement remplie** une fois les captures relues : le mode équipe reste **désactivé pour les clients**.

## Défauts trouvés et corrigés pendant le banc

1. Une limite par minute arrêtait toute l'équipe (et la reprise repartait avec l'agent unique) : la mission attend maintenant et reprend seule dans sa session.
2. Le planificateur oubliait la page d'accueil : le code confie maintenant toujours l'accueil d'une application neuve à une mission « pages ».
3. La vérification de l'accueil du point de départ regardait un seul fichier : en mode équipe, Kekeli lit maintenant la page vraiment affichée (la boutique de l'équipe aurait ainsi reçu une relance pour remplacer son accueil). Correction ajoutée après les essais : **pas encore mesurée**.
4. Le juge pouvait être occupé : il réessaie, et les preuves sont gardées pour relire un essai.
