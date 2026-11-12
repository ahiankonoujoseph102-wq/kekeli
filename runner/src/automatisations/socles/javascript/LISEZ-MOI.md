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
