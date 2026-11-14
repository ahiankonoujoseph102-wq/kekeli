# Mon agent

Agent IA fait avec Kekeli, en JavaScript avec LangGraph. Son code vous appartient : vous l'hébergez où vous voulez,
avec votre propre clé de modèle (vous payez directement votre fournisseur, Kekeli ne paie pas les messages).

## Ce qu'il sait faire
- Répondre aux questions de vos clients selon son rôle (`agent/role.md`).
- S'appuyer sur vos documents (`documents/` : questions fréquentes, catalogue, tarifs, horaires).
- Se souvenir de la conversation de chaque visiteur.
- Utiliser des outils quand vous les avez demandés (`agent/outils.js`).
- Se montrer dans une page de discussion et dans une bulle à placer sur votre site.
- Travailler en ÉQUIPE quand il y a plusieurs rôles (`agent/equipe.js`) : un coordinateur fait passer chaque demande d'un agent à l'autre (par exemple commande, stock, facture, réponse au client). Pour voir sous chaque réponse quel agent a agi pendant vos essais, mettez `AFFICHER_PARCOURS=1` dans `.env` (laissez-le vide pour vos clients).

## Ce qu'il faut
- Node.js 22.
- Une clé chez un fournisseur de modèles compatible avec l'interface d'OpenAI (OpenAI, Gemini, Mistral…).

## Installer
```bash
npm install
cp .env.example .env
```

## Variables à remplir
Ouvrez le fichier `.env` et remplissez au moins :
- `MODELE_API_URL` : l'adresse de l'interface de votre fournisseur (exemples dans `.env.example`) ;
- `MODELE_API_CLE` : votre clé chez ce fournisseur ;
- `MODELE_NOM` : le nom du modèle chez ce fournisseur.

Ne mettez jamais ces valeurs dans le code ni sur GitHub : seulement dans le fichier `.env` de votre serveur ou dans
les réglages de votre hébergeur. La clé reste sur le serveur : le navigateur de vos visiteurs ne la voit jamais.

## Lancer
```bash
npm start
```
Puis ouvrez `http://localhost:3000` : c'est la page de discussion de votre agent.

## Tests
```bash
npm test
```

## Mettre la bulle sur votre site
Une fois l'agent en ligne (par exemple à l'adresse `https://assistant.mon-site.tg`), collez cette ligne juste avant
`</body>` dans les pages de votre site :

```html
<script src="https://assistant.mon-site.tg/bulle.js" defer></script>
```

Pour que seul votre site puisse afficher la bulle, mettez son adresse dans `ORIGINES_AUTORISEES` (fichier `.env`).

## Changer ce que dit l'agent
- Son rôle et ses règles : `agent/role.md`.
- Ses connaissances : les fichiers `.md` ou `.txt` du dossier `documents/`.

## Sécurité
- La clé du modèle n'est jamais envoyée au navigateur.
- Les messages des visiteurs et le contenu des documents sont traités comme des informations, jamais comme des ordres.
- Chaque visiteur est limité à `LIMITE_MESSAGES_MINUTE` messages par minute.
- L'agent refuse poliment ce qui sort de son rôle.

## Licences
LangGraph et LangChain (licence MIT).
