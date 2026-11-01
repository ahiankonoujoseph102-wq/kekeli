# Runner de Kekeli

Petit service Node.js qui reçoit les demandes signées du site Kekeli, crée un ordinateur virtuel E2B par projet, y pilote l'agent **OpenCode** (version fixée : 1.19.4) et renvoie chaque étape au site.

C'est **le seul endroit qui détient les clés**. Elles ne vont jamais dans l'ordinateur virtuel : au démarrage, le Runner les range dans les « secrets » d'E2B, et le proxy de sortie d'E2B les ajoute lui-même aux requêtes HTTPS vers le fournisseur du modèle. OpenCode ne reçoit qu'une fausse clé.

## Variables d'environnement

| Variable | Rôle |
|---|---|
| `KEKELI_RUNNER_SECRET` | Secret partagé avec le site (au moins 32 caractères), pour signer les messages |
| `E2B_API_KEY` | Ordinateurs virtuels E2B |
| `GEMINI_API_KEY`, `OPENROUTER_API_KEY`, `MISTRAL_API_KEY` | Clés des fournisseurs d'IA ; un fournisseur sans clé est ignoré |
| `PORT` | Port d'écoute (3100 par défaut) |
| `KEKELI_RUNNER_HOTE` | Adresse d'écoute (`127.0.0.1` par défaut ; `0.0.0.0` une fois hébergé) |

L'ordre des fournisseurs et le modèle de chacun se règlent dans le site Kekeli (`KEKELI_FOURNISSEURS`, `KEKELI_MODELE_*`), qui les envoie avec chaque demande.

## Commandes

```bash
npm ci
npm test    # tests (sans réseau : E2B et OpenCode sont simulés)
npm start   # lance le Runner
```

## Fonctionnement

- `POST /taches` (signé) : réponse immédiate, puis les événements partent vers l'adresse de rappel du site : `demarrage`, `fournisseur`, `etape`, `requete` (une requête au modèle, avec ses tokens), `refus` (limite atteinte), `mis_de_cote`, `terminee`, `en_attente`, `erreur`.
- `GET /etat` (signé) : fournisseurs dont la clé est présente et fournisseurs mis de côté.
- `GET /sante` : le Runner répond-il ?
- Basculement : quand OpenCode signale un nouvel essai après une limite atteinte, le Runner décide (fichier `src/limites.js`) s'il faut patienter (limite par minute) ou mettre le fournisseur de côté jusqu'à sa remise à zéro et reprendre la même conversation avec le suivant.
