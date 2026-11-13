# Mon automatisation

Automatisation faite avec Kekeli. Son code vous appartient. Elle tourne sur **votre** dépôt GitHub, avec GitHub Actions, jamais chez Kekeli.

## Ce qu'elle fait
- Chaque jour à 7 h (heure de Lomé), elle prépare un court texte avec votre modèle d'intelligence artificielle, puis l'envoie par e-mail.
- Le fichier `.github/workflows/automatisation.yml` dit quand elle se lance ; `src/automatisation.js` dit ce qu'elle fait.

## Variables à remplir
Sur GitHub : **Settings → Secrets and variables → Actions**. Kekeli y met lui-même vos Variables secrètes, chiffrées.
- `MODELE_API_URL`, `MODELE_API_CLE`, `MODELE_NOM` : votre modèle (voir « Choisir votre fournisseur ») ;
- `RESEND_API_KEY` : votre clé Resend (resend.com, offre gratuite : 100 e-mails par jour, 3 000 par mois) ;
- `EMAIL_DESTINATAIRE` : l'adresse qui reçoit l'e-mail ;
- `EMAIL_EXPEDITEUR` : facultatif. Sans domaine vérifié chez Resend, l'adresse d'essai `onboarding@resend.dev` n'écrit qu'à l'adresse de votre compte Resend ; pour écrire à d'autres personnes, vérifiez votre nom de domaine chez Resend.

Ne mettez jamais ces valeurs dans le code ni dans un fichier envoyé sur GitHub.

## Les règles de GitHub Actions (documentation officielle de GitHub)
- **Dépôt public** : les machines standard de GitHub sont gratuites. **Dépôt privé** (compte GitHub Free) : un crédit mensuel de calcul est compris (12 $ par mois, soit environ 2 000 minutes sur une machine Linux standard, estimation) ; sans moyen de paiement, les lancements s'arrêtent une fois ce crédit utilisé. Cette automatisation dure en général moins d'une minute.
- **Heure** : par défaut, les tâches planifiées suivent l'heure UTC ; ce fichier précise le fuseau `Africa/Lome`. L'intervalle le plus court est de 5 minutes.
- **Retards possibles** : un lancement planifié peut être retardé quand GitHub est très chargé, surtout au début de chaque heure ; aux moments les plus chargés, certains lancements peuvent même être abandonnés.
- **Branche** : les tâches planifiées ne tournent que sur la branche principale du dépôt.
- **Dépôt public sans activité** : GitHub désactive les tâches planifiées après 60 jours sans activité dans le dépôt ; il suffit de les réactiver dans l'onglet « Actions ».
- Sources : docs.github.com, « Events that trigger workflows » (schedule), « Workflow syntax » (timezone) et « GitHub Actions billing ».

## Brancher un événement (si votre automatisation se lance après un événement)
Une automatisation peut se lancer **toute seule quand quelque chose arrive** (un formulaire rempli, une commande reçue). L'événement arrive à votre **point d'entrée**, un petit service gratuit sur **votre** compte Cloudflare : il vérifie l'appel (signature, secret ou site permis), refuse les appels répétés, puis lance l'automatisation sur GitHub, en général en moins d'une minute.

1. **Créez un compte Cloudflare** (gratuit) sur dash.cloudflare.com, puis ouvrez une fois « Workers & Pages » pour choisir votre adresse en `workers.dev`.
2. **Créez une clé Cloudflare** : « My Profile » → « API Tokens » → « Create Token » → modèle **« Edit Cloudflare Workers »**. Copiez-la.
3. **Créez un jeton GitHub** qui a le droit de lancer l'automatisation : github.com → « Settings » → « Developer settings » → « Fine-grained tokens » → « Generate new token » ; dépôt : seulement celui-ci ; autorisation **« Contents » en lecture et écriture** (c'est celle que demande GitHub pour cet appel).
4. **Dans Kekeli**, menu « ⋯ » → **Variables secrètes** : ajoutez `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID` (visible sur la page d'accueil de Cloudflare) et `GITHUB_DECLENCHEUR`. Kekeli crée lui-même `ENTREE_SECRET` (le secret du point d'entrée), sans jamais l'afficher.
5. Le point d'entrée se met en ligne tout seul (onglet **Actions** → « Point d'entrée »). Son **adresse** (`https://…workers.dev`) est écrite à la fin de ce lancement.
6. **Branchez l'événement** sur cette adresse :
   - le formulaire d'un site fait avec Kekeli : Kekeli le branche lui-même, avec votre accord ;
   - un autre site : ajoutez son adresse dans la Variable secrète `FORMULAIRE_ORIGINES` (par exemple `https://mon-site.pages.dev`), puis faites envoyer le formulaire à l'adresse du point d'entrée (méthode POST) ;
   - un service qui sait appeler une adresse (boutique, outil de formulaire) : donnez-lui `https://…workers.dev/?jeton=VOTRE_ENTREE_SECRET`, ou faites-lui signer ses appels (en-tête `X-Kekeli-Signature`, voir `point-entree/worker.js`).

Limites gratuites (documentation officielle de Cloudflare) : 100 000 appels par jour pour le point d'entrée ; sa mémoire (Workers KV) accepte 1 000 écritures par jour, soit environ 500 événements par jour. Chaque événement lance GitHub Actions : voir les règles ci-dessus (crédit mensuel pour un dépôt privé).

## Lancer à la main
- Sur GitHub : onglet **Actions** → « Automatisation » → **Run workflow**.
- Dans Kekeli : **Lancer maintenant**, avec un journal lisible.
- Sur un ordinateur (Node.js 22) :
```bash
cp .env.example .env   # puis remplissez les lignes
node --env-file=.env src/automatisation.js
```

## Tests
```bash
npm test
```
