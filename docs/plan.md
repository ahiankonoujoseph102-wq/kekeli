# Plan de Kekeli

Plan validé le 1er novembre 2026, avec trois corrections du porteur du projet (paiements, publication, coûts). Les prix cités ont été relevés dans les documentations officielles à cette date ; ils peuvent changer.

## 1. Architecture

```
 Téléphone ou ordinateur de l'utilisateur
            │  (site web Kekeli)
            ▼
 ┌──────────────────────────────────────┐
 │ 1. SITE KEKELI — Laravel             │  Laravel Cloud
 │ comptes, conversations, quotas,      │  + PostgreSQL serverless
 │ paiements, administration            │
 └──────────────┬───────────────────────┘
                │ messages signés (HTTPS)
                ▼
 ┌──────────────────────────────────────┐
 │ 2. RUNNER — petit service Node.js    │  hébergé à part
 │ - crée et efface les ordinateurs     │
 │ - PASSERELLE : seul endroit qui      │
 │   détient la clé Anthropic, compte   │
 │   les tokens de chaque utilisateur   │
 └──────────────┬───────────────────────┘
                │
                ▼
 ┌──────────────────────────────────────┐
 │ 3. ORDINATEUR VIRTUEL (un par projet)│  E2B
 │ Claude Agent SDK + fichiers du site  │
 │ + serveur d'aperçu → lien en direct  │
 └──────────────────────────────────────┘
```

1. L'utilisateur écrit sa demande. Laravel l'enregistre, vérifie le quota et l'envoie au Runner avec une signature.
2. Le Runner démarre ou réveille l'ordinateur virtuel et y lance l'agent.
3. Chaque étape de l'agent remonte au Runner, puis à Laravel, qui l'affiche en direct sur le téléphone.
4. L'aperçu du site s'affiche grâce au lien fourni par l'ordinateur virtuel.

## 2. Ordinateur virtuel : E2B

Choix : **E2B** (Daytona en plan de secours).
- Liste des sites autorisés modifiable pendant que l'ordinateur tourne.
- Lien d'aperçu immédiat pour chaque port.
- Formule gratuite avec 100 $ de crédit unique ; une session dure au plus une heure (on met en pause puis on reprend). Formule Pro à 150 $ par mois seulement quand il y aura des clients payants.
- Prix à l'usage : environ 0,17 $ de l'heure pour 2 processeurs et 4 Go de mémoire.

## 3. Modèles

| Rôle | Modèle | Entrée | Sortie |
|---|---|---|---|
| Coder par défaut | Claude Sonnet 5.5 | 2 $ par million de tokens | 10 $ par million de tokens |
| Gros projets et formules supérieures | Claude Opus 5.5 | 4 $ | 20 $ |
| Petites tâches | Claude Haiku 5.5 | 0,10 $ | 0,50 $ |

**Correction numéro 3 : les coûts ci-dessous sont des estimations. Ils seront mesurés pour de vrai à l'étape 5, avant de fixer les prix.**

| Tâche | Sonnet 5.5 | Opus 5.5 | Ordinateur E2B |
|---|---|---|---|
| Simple (site vitrine d'une page) | environ 0,50 à 1,50 $ | environ 1 à 3 $ | environ 0,05 $ |
| Grosse (site de huit pages, plusieurs corrections) | environ 5 à 15 $ | environ 10 à 30 $ | environ 0,30 à 1 $ |

## 4. Sécurité

1. Un ordinateur virtuel isolé par projet, effacé à la fin ; les fichiers du projet sont sauvegardés à part.
2. Aucune clé dans l'ordinateur virtuel : l'agent passe par la passerelle. Les clés sont uniquement dans les variables d'environnement protégées.
3. Internet limité à une liste de sites autorisés, modifiable par l'utilisateur, chaque ajout étant enregistré.
4. Boutons « Autoriser » et « Refuser » avant toute action risquée.
5. Nombre maximal d'étapes, budget maximal par tâche, quotas par utilisateur.
6. Les sites des clients ne sont jamais publiés sur kekeli.dev (voir la correction numéro 2).
7. Conditions d'utilisation : piratage, spam et hameçonnage interdits ; suspension possible.
8. Le contenu lu sur Internet est traité comme une information, jamais comme un ordre.

## 5. Étapes

| Numéro | Étape | Ce que l'on teste sur le téléphone |
|---|---|---|
| 1 | Fondations : site Laravel en français, PostgreSQL, comptes, page « Mes projets » | Créer un compte, se connecter, créer, renommer et supprimer un projet |
| 2 | Runner, passerelle et premier agent dans un ordinateur E2B | Demander « crée un fichier bonjour.txt » et voir l'agent travailler |
| 3 | Aperçu en direct (onglets « Discussion » et « Aperçu ») | Voir une page simple apparaître pendant sa construction |
| 4 | Sécurité : permissions, sites autorisés, effacement, sauvegarde et reprise | Refuser une action, ajouter un site, reprendre un projet le lendemain |
| 5 | Consommation et quotas, **mesure réelle des coûts**, administration | Atteindre la limite gratuite et voir le compteur |
| 6 | Qualité « site professionnel » : modèles soignés, images libres de droits, vérifications automatiques | Demander un vrai site de restaurant et juger le résultat |
| 7 | Publication en un clic sur Cloudflare, **à une adresse gratuite `pages.dev`** | Appuyer sur « Publier » et ouvrir le site en ligne |
| 8 | **Paiement Mobile Money (Mixx by Yas, Flooz) par capture d'écran validée manuellement par l'administrateur**, conditions d'utilisation, achat de kekeli.dev, **mise en ligne sur Laravel Cloud**, version bêta | Envoyer une capture de paiement, la valider dans l'administration, voir la limite augmenter |

Tant que le site n'est pas hébergé, le résultat de chaque étape est montré par des captures prises sur un écran de téléphone simulé (`docs/captures/`). La mise en ligne sur Laravel Cloud devient **indispensable à l'étape 8** (vrais utilisateurs et paiements) ; elle est **conseillée dès l'étape 6**, pour essayer soi-même l'agent sur son téléphone avant la bêta.

Après l'étape 8 : lancement de la création de sites web. Ensuite : applications, agents IA, automatisations, puis mode développeur (GitHub).

## 6. Comptes à créer et coûts de départ

| Compte | Usage | Coût au départ |
|---|---|---|
| GitHub | Le code | 0 $ (déjà fait) |
| Anthropic Console | Les modèles Claude (crédits prépayés) | 20 à 50 $ de crédits pour les tests |
| Laravel Cloud | Le site Kekeli et PostgreSQL | Premier mois gratuit, puis 5 $ par mois (5 $ d'usage inclus) |
| E2B | Les ordinateurs virtuels | 0 $ (100 $ de crédit offert) |
| Hébergement du Runner (par exemple Railway) | Le Runner | Environ 5 $ par mois, à vérifier |
| Cloudflare | Publication des sites des clients (`pages.dev`) et gestion de kekeli.dev | 0 $ |
| Nom de domaine kekeli.dev | Le site Kekeli | Environ 12 à 15 $ par an, à vérifier |
| Pexels, Unsplash, Pixabay | Images libres de droits | 0 $ |

**Correction numéro 1 : pas d'agrégateur de paiement au départ**, donc pas de commission ni de dossier d'entreprise à fournir pour commencer. **Correction numéro 2 : pas de deuxième nom de domaine.**

Total pendant la construction : environ 30 à 60 $ par mois, surtout pour les essais des modèles. Une carte bancaire internationale (Visa ou Mastercard acceptant les dollars) est nécessaire.

## 7. Risques et limites

1. Le prix est le premier risque : une grosse tâche peut coûter plus de 10 $ en modèles. Les limites et les prix seront fixés après les mesures de l'étape 5.
2. La validation manuelle des captures d'écran prend du temps à l'administrateur et expose aux fausses captures : vérifier chaque paiement dans l'historique Mixx by Yas ou Flooz avant de valider. Un agrégateur viendra quand le volume le justifiera.
3. Dépendance à plusieurs entreprises (Anthropic, E2B, Laravel Cloud, Cloudflare) dont les prix et les règles peuvent changer. Les utilisateurs de Kekeli ne peuvent pas se connecter avec leur propre compte Claude : Kekeli paie l'usage avec sa clé API.
4. À vérifier : disponibilité de l'API d'Anthropic depuis le Togo et paiement des fournisseurs avec la carte disponible.
5. Abus : modération nécessaire, suspension des comptes et des sites.
6. Les grosses tâches durent plusieurs minutes : le téléphone doit pouvoir être fermé puis rouvert sans rien perdre.
7. Même moteur que Claude Code, mais l'expérience dépend de tout ce qui est construit autour : plusieurs mois de travail.
8. Les adresses `pages.dev` sont gratuites mais portent le nom de Cloudflare ; un nom de domaine personnalisé pour les clients pourra venir plus tard.
9. Une politique de confidentialité conforme à la loi togolaise sur la protection des données personnelles sera nécessaire avant le lancement.
