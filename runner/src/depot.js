// Mode développeur : un dépôt GitHub existant du client, ouvert dans l'ordinateur virtuel.
//
// La clé GitHub du client ne quitte jamais le site Kekeli : le site télécharge l'archive du dépôt et la
// transmet au Runner par une adresse signée ; l'agent travaille avec Git dans l'ordinateur virtuel, sur une
// branche à lui ; c'est ensuite le site qui envoie les fichiers modifiés sur GitHub et ouvre la pull request.
// Dans l'ordinateur, aucune clé : « git push » est d'ailleurs une commande qui demande la permission.

export const ETIQUETTE_DEPART = 'kekeli-depart'

/** Sites à autoriser en plus pour installer les outils d'un projet (paquets Debian, Composer, npm, GitHub). */
export const SITES_DEVELOPPEMENT = [
  'deb.debian.org',
  'packages.sury.org',
  'security.debian.org',
  'getcomposer.org',
  'packagist.org',
  'repo.packagist.org',
  'api.github.com',
  'codeload.github.com',
  'github.com',
  'objects.githubusercontent.com',
  'raw.githubusercontent.com',
  'registry.yarnpkg.com',
  'nodejs.org',
]

/** Nom de branche Git sans danger (lettres, chiffres, tirets, barres obliques). */
export function brancheValide(nom) {
  return typeof nom === 'string' && /^[a-z0-9][a-z0-9._/-]{0,99}$/i.test(nom) && !nom.includes('..') && !nom.endsWith('/') && !nom.endsWith('.lock')
}

/** Nom d'un dépôt GitHub (« propriétaire/nom »). */
export function depotValide(nom) {
  return typeof nom === 'string' && /^[A-Za-z0-9_.-]{1,100}\/[A-Za-z0-9_.-]{1,100}$/.test(nom)
}

/**
 * Reconnaît le type de projet à partir des fichiers de la racine (et de composer.json ou package.json),
 * et donne les outils à installer et les commandes utiles. `lire(nom)` renvoie le contenu d'un fichier ou null.
 */
export function detecterProjet(fichiers, lire = () => null) {
  const present = (nom) => fichiers.includes(nom)
  const json = (nom) => {
    try {
      return JSON.parse(lire(nom) ?? 'null')
    } catch {
      return null
    }
  }
  const composer = present('composer.json') ? json('composer.json') ?? {} : null
  const paquet = present('package.json') ? json('package.json') ?? {} : null
  const scripts = paquet?.scripts ?? {}
  const gestionnaire = present('pnpm-lock.yaml') ? 'pnpm' : present('yarn.lock') ? 'yarn' : 'npm'
  const installerNode = gestionnaire === 'npm' ? (present('package-lock.json') ? 'npm ci' : 'npm install') : `npx --yes ${gestionnaire} install`
  const lancerNode = (script) => (gestionnaire === 'npm' ? `npm run ${script}` : `npx --yes ${gestionnaire} ${script}`)

  const resultat = { genre: 'autre', nom: 'Projet', outils: [], installer: [], tester: [], style: [], instructions: [], php: null }
  for (const nom of ['CLAUDE.md', 'AGENTS.md', 'README.md', 'CONTRIBUTING.md']) if (present(nom)) resultat.instructions.push(nom)

  if (composer) {
    const laravel = present('artisan') || Boolean(composer.require?.['laravel/framework'])
    resultat.genre = laravel ? 'laravel' : 'php'
    resultat.nom = laravel ? 'Laravel (PHP)' : 'PHP'
    const version = versionPhp(composer.require?.php)
    if (version) resultat.nom = laravel ? `Laravel (PHP ${version})` : `PHP ${version}`
    resultat.outils.push('php', 'composer')
    resultat.php = versionPhp(composer.require?.php)
    resultat.installer.push('composer install --no-interaction --prefer-dist --no-progress')
    if (laravel) {
      resultat.installer.push('[ -f .env ] || cp .env.example .env', 'php artisan key:generate --force')
      resultat.tester.push('php artisan test')
    } else if (composer.scripts?.test) resultat.tester.push('composer test')
    else if (present('phpunit.xml') || present('phpunit.xml.dist')) resultat.tester.push('vendor/bin/phpunit')
    if (composer['require-dev']?.['laravel/pint']) resultat.style.push('vendor/bin/pint --test')
  }
  if (paquet) {
    if (resultat.genre === 'autre') {
      const dependances = { ...(paquet.dependencies ?? {}), ...(paquet.devDependencies ?? {}) }
      const cadre = dependances.next ? 'Next.js' : dependances.vue ? 'Vue' : dependances.react ? 'React' : dependances.express ? 'Express' : null
      resultat.genre = 'node'
      resultat.nom = cadre ? `Node.js (${cadre})` : 'Node.js'
    }
    resultat.outils.push('node')
    resultat.installer.push(installerNode)
    if (scripts.test && !/no test specified/.test(scripts.test)) resultat.tester.push(gestionnaire === 'npm' ? 'npm test' : `npx --yes ${gestionnaire} test`)
    if (scripts.lint) resultat.style.push(lancerNode('lint'))
    // Projet PHP avec une interface : les tests ont souvent besoin de l'interface construite (par exemple Vite).
    if (scripts.build && composer) resultat.installer.push(lancerNode('build'))
  }
  if (!composer && !paquet) {
    if (present('requirements.txt') || present('pyproject.toml')) {
      resultat.genre = 'python'
      resultat.nom = 'Python'
      resultat.outils.push('python')
      if (present('requirements.txt')) resultat.installer.push('pip install --user -r requirements.txt')
      resultat.tester.push('python3 -m pytest')
    } else if (present('index.html')) {
      resultat.genre = 'site'
      resultat.nom = 'Site simple (HTML, CSS, JavaScript)'
    }
  }
  return resultat
}

/**
 * Plus petite version de PHP acceptée par le projet (« ^8.3 » → « 8.3 », « ^8.2|^8.3 » → « 8.2 ») ;
 * null si le projet ne dit rien.
 */
export function versionPhp(contrainte) {
  const versions = [...String(contrainte ?? '').matchAll(/(\d+)\.(\d+)/g)].map((m) => [Number(m[1]), Number(m[2])])
  if (!versions.length) return null
  versions.sort((a, b) => a[0] - b[0] || a[1] - b[1])
  return `${versions[0][0]}.${versions[0][1]}`
}

/** PHP fourni par Debian 12 dans l'ordinateur virtuel ; une version plus récente vient du dépôt libre packages.sury.org. */
const PHP_DEBIAN = '8.2'
const EXTENSIONS_PHP = ['cli', 'xml', 'mbstring', 'curl', 'sqlite3', 'zip', 'intl', 'bcmath', 'gd']

/** Commandes système pour installer les outils manquants (lancées par le Runner, jamais par l'agent). */
export function commandesOutils(outils, php = null) {
  const commandes = []
  if (outils.includes('php')) {
    const apt = 'sudo DEBIAN_FRONTEND=noninteractive apt-get'
    const plusRecent = php && /^\d+\.\d+$/.test(php) && Number(php.replace('.', '')) > Number(PHP_DEBIAN.replace('.', '')) && php.split('.').map(Number).every((n) => n < 20)
    if (plusRecent) {
      const paquets = EXTENSIONS_PHP.map((e) => `php${php}-${e}`).join(' ')
      commandes.push(
        `php -v 2>/dev/null | grep -q "^PHP ${php}" || (${apt} update -qq && ${apt} install -y -qq lsb-release ca-certificates curl >/tmp/kekeli-apt.log 2>&1 && ` +
          'sudo curl -sSLo /usr/share/keyrings/sury-php.gpg https://packages.sury.org/php/apt.gpg && ' +
          'echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/sury-php.list >/dev/null && ' +
          `${apt} update -qq && ${apt} install -y -qq ${paquets} unzip >>/tmp/kekeli-apt.log 2>&1)`,
      )
    } else {
      commandes.push(`command -v php >/dev/null || (${apt} update -qq && ${apt} install -y -qq ${EXTENSIONS_PHP.map((e) => `php-${e}`).join(' ')} unzip >/tmp/kekeli-apt.log 2>&1)`)
    }
  }
  if (outils.includes('node')) {
    // Node 20.9 dans l'ordinateur : trop ancien pour les outils récents (par exemple Vite demande Node 20.19 ou plus).
    commandes.push('node -e "process.exit(Number(process.versions.node.split(\'.\')[0]) >= 22 ? 0 : 1)" || (sudo npm install -g n --silent >/tmp/kekeli-node.log 2>&1 && sudo n 22 >>/tmp/kekeli-node.log 2>&1)')
  }
  if (outils.includes('composer')) {
    commandes.push('command -v composer >/dev/null || (curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php && sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet)')
  }
  return commandes
}

/** Informations données à l'agent au début de chaque demande sur un dépôt (et les installations qui ont échoué). */
export function texteDepot(depot, detection, echecs = []) {
  const lignes = [
    `[Informations de Kekeli] Mode développeur : dépôt GitHub ${depot.depot}, ouvert dans le dossier du projet. Tu travailles sur la branche Git « ${depot.branche_travail} », créée à partir de « ${depot.branche_base} ».`,
  ]
  if (detection) {
    lignes.push(`Type de projet reconnu : ${detection.nom}.`)
    if (detection.installer.length) lignes.push(`Kekeli a déjà installé les outils et les dépendances (${detection.installer.join(' ; ')}).`)
    if (detection.tester.length) lignes.push(`Commande(s) de test : ${detection.tester.join(' ; ')}.`)
    if (detection.style.length) lignes.push(`Vérification du style : ${detection.style.join(' ; ')}.`)
    if (detection.instructions.length) lignes.push(`Lis d'abord ${detection.instructions.join(', ')} et respecte les règles du projet.`)
  }
  for (const echec of echecs) {
    lignes.push(`Attention : l'installation « ${echec.commande} » a échoué (${String(echec.sortie ?? '').split('\n').slice(-3).join(' ').slice(0, 400)}). Si c'est utile pour la demande, trouve la cause et corrige-la.`)
  }
  if (depot.lecture_seule) lignes.push("Ce dépôt est ouvert en lecture seule : tes modifications restent dans l'ordinateur et ne seront pas envoyées sur GitHub.")
  return lignes.join(' ')
}

/** Ligne de « git status --porcelain » ou de « git diff --name-status » → { etat, chemin }. */
export function lireEtatFichiers(texte) {
  return String(texte ?? '')
    .split('\n')
    .map((ligne) => ligne.trim())
    .filter(Boolean)
    .map((ligne) => {
      const [etat, ...reste] = ligne.split(/\s+/)
      const chemin = reste[reste.length - 1]
      return { etat: etat.startsWith('A') ? 'ajoute' : etat.startsWith('D') ? 'supprime' : etat.startsWith('R') ? 'renomme' : 'modifie', chemin }
    })
}

const echapper = (texte) => `'${String(texte).replace(/'/g, `'\\''`)}'`

/**
 * Met le dépôt en place dans un ordinateur neuf : archive du dépôt (téléchargée depuis le site), dépôt Git,
 * étiquette de départ et branche de travail. `octets` : archive .tar.gz de GitHub (un dossier racine).
 */
export async function ouvrirDepot(sandbox, dossier, octets, { brancheBase, brancheTravail }) {
  if (!brancheValide(brancheBase) || !brancheValide(brancheTravail)) throw new Error('Nom de branche invalide.')
  const archive = '/tmp/kekeli-depot.tar.gz'
  await sandbox.files.write(archive, octets.buffer.slice(octets.byteOffset, octets.byteOffset + octets.byteLength))
  await sandbox.commands.run(`mkdir -p ${dossier} && tar -xzf ${archive} -C ${dossier} --strip-components=1 && rm -f ${archive}`, { timeoutMs: 120000 })
  await initialiserGit(sandbox, dossier, { brancheBase, brancheTravail, message: `État de départ (GitHub, branche ${brancheBase})` })
}

/**
 * Dépôt Git local : tout le dossier enregistré dans un premier commit, étiqueté, puis la branche de travail.
 * Sert aussi quand un projet est remis en place depuis sa sauvegarde (qui ne contient pas le dossier .git).
 */
export async function initialiserGit(sandbox, dossier, { brancheBase, brancheTravail, message }) {
  if (!brancheValide(brancheBase) || !brancheValide(brancheTravail)) throw new Error('Nom de branche invalide.')
  await sandbox.commands.run(
    [
      `cd ${dossier}`,
      'git init -q',
      'git config user.name "Kekeli"',
      'git config user.email "kekeli@users.noreply.github.com"',
      `git checkout -q -B ${echapper(brancheBase)}`,
      'git add -A',
      `git commit -q --allow-empty -m ${echapper(message)}`,
      `git tag -f ${ETIQUETTE_DEPART}`,
      `git checkout -q -B ${echapper(brancheTravail)}`,
    ].join(' && '),
    { timeoutMs: 120000 },
  )
}

/** Installe les outils et les dépendances du projet ; renvoie la détection et le résultat de chaque commande. */
export async function preparerOutils(sandbox, dossier) {
  const liste = await sandbox.commands.run(`ls -1A ${dossier}`)
  const fichiers = liste.stdout.split('\n').filter(Boolean)
  const lire = (nom) => (fichiers.includes(nom) ? sandbox.files.read(`${dossier}/${nom}`).catch(() => null) : null)
  const contenus = {}
  for (const nom of ['composer.json', 'package.json']) contenus[nom] = await lire(nom)
  const detection = detecterProjet(fichiers, (nom) => contenus[nom] ?? null)
  const etapes = []
  for (const commande of [...commandesOutils(detection.outils, detection.php), ...detection.installer]) {
    const debut = Date.now()
    const sortie = await sandbox.commands.run(`cd ${dossier} && ${commande}`, { timeoutMs: 600000 }).catch((erreur) => erreur.result ?? { exitCode: 1, stderr: erreur.message })
    etapes.push({ commande, ok: sortie.exitCode === 0, secondes: Math.round((Date.now() - debut) / 1000), sortie: String(sortie.stderr || sortie.stdout || '').trim().split('\n').slice(-5).join('\n') })
  }
  // Fichiers créés par l'installation et que le dépôt ne suit pas (par exemple composer.lock dans un squelette) :
  // ignorés seulement dans cet ordinateur, pour qu'ils ne se mêlent jamais au travail envoyé au client.
  await sandbox.commands.run(`cd ${dossier} && [ -d .git ] && git status --porcelain --untracked-files=normal | sed -n 's/^?? //p' >> .git/info/exclude`).catch(() => {})
  return { detection, etapes }
}

/**
 * État du travail après une demande : les modifications non enregistrées sont enregistrées dans un commit,
 * puis Kekeli renvoie la branche, les commits et les fichiers modifiés depuis l'état de départ.
 */
export async function etatDepot(sandbox, dossier) {
  const lancer = (commande) => sandbox.commands.run(`cd ${dossier} && ${commande}`, { timeoutMs: 60000 }).then((r) => r.stdout).catch((erreur) => erreur.result?.stdout ?? '')
  await lancer('git add -A && (git diff --cached --quiet || git commit -q -m "Travail de Kekeli")')
  const branche = (await lancer('git rev-parse --abbrev-ref HEAD')).trim()
  const commits = (await lancer(`git log --format=%s ${ETIQUETTE_DEPART}..HEAD`)).split('\n').map((l) => l.trim()).filter(Boolean)
  const fichiers = lireEtatFichiers(await lancer(`git diff --name-status ${ETIQUETTE_DEPART}..HEAD`))
  return { branche, commits: commits.slice(0, 50), fichiers: fichiers.slice(0, 300), nombre_fichiers: fichiers.length }
}
