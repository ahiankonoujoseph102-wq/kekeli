# Un seul parcours « Nouveau site » : captures sur un écran de téléphone simulé

Captures prises le 10 novembre 2026 sur un téléphone simulé (Pixel 7, navigateur en français), pendant des **essais réels** avec un ordinateur E2B et Gemini (offre gratuite). Les noms, numéros et données sont inventés. Le navigateur d'essai affiche « mm/dd/yyyy » dans les dates vides : c'est le navigateur, le site affiche les dates en français.

Décision du porteur du projet : Kekeli crée des **sites web**, comme Claude Code, Cursor, Codex, Lovable ou Manus. Site vitrine **ou** site avec base de données, dans un seul parcours « Nouveau site » ; Kekeli comprend tout seul ce qu'il faut. Le mot « application » n'apparaît plus pour le client.

- **Partie 1** : un site vitrine (restaurant à Lomé), puis la proposition d'un site avec base de données quand le client demande des comptes.
- **Partie 2** : un site avec base de données en Laravel (pressing à Lomé), décrit avec des mots simples.
- **Partie 3** : un site avec base de données en Next.js (association de bénévoles à Kara).
- **Partie 4** : la galerie rangée en « Sites vitrines » et « Sites avec base de données ».

## Partie 1 : un site vitrine

### 1. « Mes projets » : un seul bouton « Nouveau site »
Plus de bouton « Nouvelle application web ».
![Mes projets](nouveau-site/vitrine-01-mes-projets.png)

### 2. « Nouveau site » : le client décrit son site, Kekeli dit ce qu'il a compris
« Kekeli a compris : un site vitrine ». Le client peut corriger le type de site s'il le souhaite.
![Nouveau site, site vitrine](nouveau-site/vitrine-02-nouveau-site.png)

### 3. Kekeli commence tout de suite, phrase par phrase
![Départ](nouveau-site/vitrine-03-depart.png)
![Pendant le travail](nouveau-site/vitrine-04-pendant.png)

### 4. Le compte rendu et l'aperçu
![Fin de la demande](nouveau-site/vitrine-05-fin.png)
![Compte rendu](nouveau-site/vitrine-06-compte-rendu.png)
![Aperçu](nouveau-site/vitrine-07-apercu.png)

Dans cet essai, Kekeli avait appelé le restaurant « Le Bè-Gastronome » au lieu de « Restaurant Le Palmier » : le nom tapé n'était pas encore transmis à l'agent. **C'est corrigé** (le nom est donné à Kekeli dès la première demande ; un test le vérifie, et un second essai réel l'a confirmé).

Second essai réel, après les corrections (salon de coiffure à Lomé) : le nom tapé, « Salon Belle Afi », est respecté. Au premier essai de ce salon, Gemini s'était arrêté avant d'écrire la page ; **Kekeli refuse maintenant d'annoncer un site sans aucune page** : il le signale à l'agent, qui a écrit la page tout seul.
![Aperçu du salon](nouveau-site/vitrine2-07-apercu.png)
![Compte rendu du salon](nouveau-site/vitrine2-06-compte-rendu.png)

### 5. Le client demande ensuite des comptes : Kekeli propose un site avec base de données
Un site vitrine ne peut pas avoir de comptes. Kekeli l'explique et propose « Créer un site avec base de données » (nouveau projet à partir de la demande ; le site vitrine reste tel quel) ou « Envoyer quand même à ce site ».
![Proposition d'un site avec base de données](nouveau-site/vitrine-08-proposition-base-de-donnees.png)

## Partie 2 : un site avec base de données en Laravel (pressing à Lomé)

### 1. La description, et ce que Kekeli a compris
« Kekeli a compris : un site avec base de données, pour les comptes des utilisateurs, un espace administrateur, les informations enregistrées ». La technologie reste facultative (repliée) : Kekeli a choisi Laravel.
![Nouveau site, base de données](nouveau-site/base-02-nouveau-site.png)

### 2. Kekeli travaille : le site de départ est posé, puis personnalisé
La carte du projet dit « Site avec base de données · Laravel (PHP) et MySQL », avec le compte administrateur d'essai.
![Départ](nouveau-site/base-03-depart.png)
![Pendant le travail](nouveau-site/base-04-pendant.png)

### 3. Le compte rendu : tests réussis, sécurité vérifiée, le site répond
![Fin de la demande](nouveau-site/base-05-fin.png)
![Compte rendu](nouveau-site/base-06-compte-rendu.png)

### 4. Le site tourne dans l'aperçu : connexion de l'administrateur dans le cadre de la page
![Carte du site](nouveau-site/base-07-carte.png)
![Aperçu](nouveau-site/base-08-apercu.png)
![Administration dans l'aperçu](nouveau-site/base-09-apercu-administration.png)

### 5. « Mettre en ligne » : le guide pas à pas
![Mise en ligne](nouveau-site/base-10-mise-en-ligne.png)

## Partie 3 : un site avec base de données en Next.js (bénévoles de Kara)

La description parlait de Next.js et de Vercel : Kekeli a choisi Next.js tout seul.
![Nouveau site](nouveau-site/nextjs-02-nouveau-site.png)
![Pendant le travail](nouveau-site/nextjs-04-pendant.png)
![Compte rendu](nouveau-site/nextjs-06-compte-rendu.png)
![Aperçu](nouveau-site/nextjs-08-apercu.png)
![Administration dans l'aperçu](nouveau-site/nextjs-09-apercu-administration.png)

## Partie 4 : la galerie

Deux rubriques sur la même page : « Sites vitrines » (12 modèles) et « Sites avec base de données » (5 modèles). Un modèle avec base de données ouvre « Nouveau site » avec le modèle déjà choisi.
![Sites vitrines](nouveau-site/galerie-01-sites-vitrines.png)
![Sites avec base de données](nouveau-site/galerie-02-sites-avec-base-de-donnees.png)

## Énergie
Site vitrine : environ 38 à 69 FCFA ; site avec base de données décrit avec des mots simples : environ 135 à 145 FCFA (estimations mesurées sur ces essais). Détails dans [`docs/couts.md`](../couts.md), partie « Nouveau site ».
