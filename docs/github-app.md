# Créer la GitHub App de Kekeli (depuis un téléphone)

Kekeli travaille sur GitHub comme Claude Code : chaque client installe **l'application GitHub de Kekeli** sur son compte et choisit les dépôts que Kekeli peut voir. Cette application se crée **une seule fois**, dans le compte GitHub du porteur du projet. C'est gratuit.

**Règle de sécurité** : aucune valeur (identifiant, secret) ne s'écrit dans une discussion avec Claude, ni dans le dépôt. Elles vont seulement dans les variables de l'environnement « kekeli ». Avant toute capture d'écran, cacher le secret.

## 1. Ouvrir la page de création
1. Sur le téléphone, ouvrir **github.com** dans le navigateur (pas l'application GitHub) et se connecter.
2. Toucher la photo du profil (en haut à droite), puis **Settings**.
3. Descendre tout en bas du menu et toucher **Developer settings**.
4. Toucher **GitHub Apps**, puis **New GitHub App**. GitHub peut redemander le mot de passe.

## 2. Remplir le formulaire
| Champ | Valeur |
|---|---|
| **GitHub App name** | `Kekeli` (s'il est déjà pris : `Kekeli Lome`) |
| **Homepage URL** | l'adresse de votre profil GitHub, par exemple `https://github.com/<votre-nom>` (à remplacer par l'adresse du site Kekeli après la mise en ligne) |
| **Callback URL** | `http://127.0.0.1:8123/github/retour` (à remplacer après la mise en ligne par `https://<adresse du site>/github/retour`) |
| **Expire user authorization tokens** | coché (jeton de 8 heures renouvelé par Kekeli) |
| **Request user authorization (OAuth) during installation** | coché |
| **Enable Device Flow** | coché (sert à l'essai réel tant que le site n'est pas en ligne) |
| **Setup URL** | laisser vide |
| **Webhook → Active** | **décoché** (Kekeli n'utilise pas de webhook) |

### Autorisations (« Permissions ») : le minimum
Dans **Repository permissions** :
| Autorisation | Accès | Pourquoi |
|---|---|---|
| Actions | Read and write | lancer l'automatisation sur GitHub Actions |
| Administration | Read and write | créer le dépôt privé (« projet d'abord ») |
| Checks | Read-only | lire la vérification automatique |
| Contents | Read and write | lire et envoyer les fichiers |
| Metadata | Read-only | obligatoire (choisi tout seul) |
| Pull requests | Read and write | ouvrir la pull request |
| Secrets | Read and write | ranger les clés de l'automatisation, chiffrées |
| Workflows | Read and write | envoyer le fichier GitHub Actions planifié |

Rien dans **Organization permissions** ni dans **Account permissions**.

**Where can this GitHub App be installed?** : **Any account** (pour que les clients puissent l'installer).

Toucher **Create GitHub App**.

## 3. Où trouver chaque valeur (sans jamais l'envoyer à Claude)
Sur la page de l'application qui s'affiche (**General**) :
- **Client ID** : en haut, ligne « Client ID ».
- **Client secret** : toucher **Generate a new client secret**, puis copier la valeur affichée (elle n'est montrée qu'une fois).
- **Nom court** : dans « Public link », la fin de l'adresse `https://github.com/apps/<nom-court>` (par exemple `kekeli`).
- La **clé privée** et l'**App ID** ne sont pas nécessaires : Kekeli agit avec l'accord de chaque client.

## 4. Les placer dans l'environnement « kekeli »
Sur claude.ai, dans les réglages de l'environnement « kekeli », ajouter ces trois variables :
| Nom | Valeur |
|---|---|
| `GITHUB_APP_SLUG` | le nom court |
| `GITHUB_CLIENT_ID` | le Client ID |
| `GITHUB_CLIENT_SECRET` | le Client secret |

## 5. Installer l'application sur son propre compte (pour l'essai)
1. Ouvrir `https://github.com/apps/<nom-court>` et toucher **Install**.
2. Choisir le compte, puis **Only select repositories** (on peut n'en choisir aucun : Kekeli créera son dépôt d'essai et l'ajoutera seul) ou **All repositories**.
3. Toucher **Install**. La page qui suit peut afficher une erreur (le site n'est pas encore en ligne) : c'est normal, l'installation est faite.

Écrire ensuite « c'est fait » à Claude. Il lancera la connexion par code : il donnera un code court à taper sur **github.com/login/device**, puis fera l'essai réel (dépôt privé, pull request, automatisation lancée depuis GitHub Actions).

Sources : [créer une GitHub App](https://docs.github.com/en/apps/creating-github-apps/registering-a-github-app/registering-a-github-app), [jetons de l'utilisateur et flux appareil](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/generating-a-user-access-token-for-a-github-app), [autorisations nécessaires](https://docs.github.com/en/rest/authentication/permissions-required-for-github-apps).
