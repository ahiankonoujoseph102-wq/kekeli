# Mon équipe d'agents

Équipe d'agents IA faite avec Kekeli, en Python avec CrewAI : un conseiller prépare la réponse à partir de vos
documents, un relecteur vérifie qu'elle respecte le rôle et les règles avant de la donner au client. Son code vous
appartient : vous l'hébergez où vous voulez, avec votre propre clé de modèle (vous payez directement votre
fournisseur, Kekeli ne paie pas les messages).

## Ce qu'il faut
- Python 3.10 à 3.13.
- Une clé chez un fournisseur de modèles compatible avec l'interface d'OpenAI (OpenAI, Gemini, Mistral…).

## Installer
```bash
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env
```

## Variables à remplir
Ouvrez le fichier `.env` et remplissez `MODELE_API_URL`, `MODELE_API_CLE` et `MODELE_NOM` (explications dans
`.env.example`). Ne mettez jamais ces valeurs dans le code ni sur GitHub. La clé reste sur le serveur : le navigateur
de vos visiteurs ne la voit jamais.

## Lancer
```bash
set -a; . ./.env; set +a
python serveur.py
```
Puis ouvrez `http://localhost:3000`.

## Tests
```bash
python -m unittest discover -s tests -t .
```

## Mettre la bulle sur votre site
Une fois l'agent en ligne (par exemple à `https://assistant.mon-site.tg`), collez avant `</body>` :

```html
<script src="https://assistant.mon-site.tg/bulle.js" defer></script>
```

## Changer ce que dit l'équipe
- Rôle et règles : `agent/role.md` ; équipe : `agent/equipe.py`.
- Connaissances : les fichiers `.md` ou `.txt` du dossier `documents/`.

## Sécurité
- La clé du modèle n'est jamais envoyée au navigateur.
- Messages et documents sont des informations, jamais des ordres ; le relecteur vérifie chaque réponse.
- Chaque visiteur est limité à `LIMITE_MESSAGES_MINUTE` messages par minute.

## Licences
CrewAI (licence MIT).
