# Runner de Kekeli

Petit service Node.js qui reçoit les demandes signées du site Kekeli, crée un ordinateur virtuel E2B par projet, y pilote l'agent **OpenCode** (version fixée : 1.19.4) et renvoie chaque étape au site.

C'est **le seul endroit qui détient les clés**. Elles ne vont jamais dans l'ordinateur virtuel : au démarrage, le Runner les range dans les « secrets » d'E2B, et le proxy de sortie d'E2B les ajoute lui-même aux requêtes HTTPS vers le fournisseur du modèle. OpenCode ne reçoit qu'une fausse clé.

## Variables d'environnement

| Variable | Rôle |
|---|---|
| `KEKELI_RUNNER_SECRET` | Secret partagé avec le site (au moins 32 caractères), pour signer les messages |
| `E2B_API_KEY` | Ordinateurs virtuels E2B |
| `GEMINI_API_KEY`, `OPENROUTER_API_KEY`, `GROQ_API_KEY`, `MISTRAL_API_KEY` | Clés des fournisseurs d’IA ; un fournisseur sans clé est ignoré. Chez OpenRouter, seuls les modèles gratuits (« :free ») sont acceptés |
| `PORT` | Port d'écoute (3100 par défaut) |
| `KEKELI_RUNNER_HOTE` | Adresse d'écoute (`127.0.0.1` par défaut ; `0.0.0.0` une fois hébergé) |
| `KEKELI_RUNNER_ADRESSE_PUBLIQUE` | Adresse publique du Runner une fois hébergé (par exemple sur Laravel Cloud) : pendant une demande, le Runner l'appelle chaque minute pour ne pas s'endormir en plein travail |
| `KEKELI_RUNNER_EVEIL_MAX_MINUTES` | Durée maximale pendant laquelle le Runner se garde éveillé d'affilée (60 minutes par défaut) : ensuite, il se laisse endormir même si une demande reste bloquée |

L'ordre des fournisseurs et le modèle de chacun se règlent dans le site Kekeli (`KEKELI_FOURNISSEURS`, `KEKELI_MODELE_*`), qui les envoie avec chaque demande.

## Commandes

```bash
npm ci
npm test    # tests (sans réseau : E2B et OpenCode sont simulés)
npm start   # lance le Runner
```

## Fonctionnement

- `POST /taches` (signé) : réponse immédiate, puis les événements partent vers l'adresse de rappel du site : `demarrage`, `apercu` (lien de l'aperçu en direct et heure de mise en veille), `fournisseur`, `etape`, `requete` (une requête au modèle, avec ses tokens), `refus` (limite atteinte), `mis_de_cote`, `terminee`, `en_attente`, `erreur`.
- `POST /permissions` (signé) : réponse de l'utilisateur (« autoriser » ou « refuser ») à une demande de permission de l'agent.
- `POST /apercus` (signé) : réveille l'ordinateur d'un projet pour rallumer l'aperçu, sans demande à l'agent.
- `POST /projets/effacer` (signé) : efface pour de bon les ordinateurs (en marche ou en pause) d'un projet supprimé.
- `GET /etat` (signé) : fournisseurs dont la clé est présente et fournisseurs mis de côté.
- `GET /sante` : le Runner répond-il ?
- Basculement : quand OpenCode signale un nouvel essai après une limite atteinte, le Runner décide (fichier `src/limites.js`) s'il faut patienter (limite par minute) ou mettre le fournisseur de côté jusqu'à sa remise à zéro et reprendre la même conversation avec le suivant.

## Aperçu en direct

Chaque ordinateur virtuel fait tourner, en plus d'OpenCode, un petit serveur d'aperçu (`src/apercu/serveur.mjs`, sans dépendance, port 4100). Il montre les fichiers du dossier du projet comme un vrai site et ajoute aux pages HTML un petit script qui recharge la page dès qu'un fichier change. Il refuse les fichiers cachés (`.env`, `.git`…) et tout chemin qui sort du dossier du projet. Tant qu'il n'y a pas de page d'accueil (`index.html`), il affiche « Votre site apparaîtra ici ».

Le lien d'aperçu (`https://4100-…e2b.app`) est envoyé au site avec l'événement `apercu`, qui donne aussi l'heure à laquelle l'ordinateur sera effacé (30 minutes après la dernière demande).

## Sécurité, sauvegarde et reprise (étape 4)

- **Pause au lieu d'effacement** : au bout de 30 minutes sans demande, l'ordinateur est mis en pause par E2B (`lifecycle.onTimeout` : pause, disque seulement). À la demande suivante, il est réveillé avec tous les fichiers et la conversation de l'agent.
- **Sauvegarde à part** : après chaque demande, le dossier du projet est compressé et envoyé au site par morceaux signés de 4 Mo (`src/sauvegarde.js`). Si l'ordinateur d'un projet ne peut pas être réveillé, un nouvel ordinateur est créé, la sauvegarde y est remise, et l'agent reçoit les derniers échanges pour le contexte.
- **Aperçu privé** : le serveur d'aperçu n'accepte que les adresses `/k/<jeton>/…`, avec un jeton fabriqué par le site (clé tirée de `KEKELI_RUNNER_SECRET`, même calcul des deux côtés) et valable peu de temps.
- **Sites autorisés** (`src/reseau.js`) : l'ordinateur ne joint que les services indispensables, les sources libres de droits et les sites ajoutés par l'utilisateur. L'outil de recherche d'OpenCode (ripgrep) est déposé par le Runner, après vérification de son empreinte (`src/outils.js`), car GitHub n'est pas autorisé.
- **Permissions** : les commandes risquées (effacer, envoyer des données, droits du système) demandent l'accord de l'utilisateur ; sans réponse en 10 minutes, l'action est refusée. Après un refus, l'agent est relancé pour en tenir compte et l'expliquer.
- **Licences** (`src/licences/verifier.mjs`) : après chaque demande, un vérificateur contrôle que chaque ressource vient d'une source libre et figure dans `LICENCES.md` ; sinon l'agent reçoit une demande de correction (une fois), puis le résultat est affiché à l'utilisateur.
