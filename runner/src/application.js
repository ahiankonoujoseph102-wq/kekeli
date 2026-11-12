// Applications web dans l'ordinateur virtuel : fichiers de départ, installation, base d'essai, lancement
// (par le serveur d'aperçu), puis vérifications après chaque demande (tests, sécurité, application qui répond).
//
// L'application tourne avec une base d'essai et un administrateur d'essai dont le mot de passe est tiré du
// secret du Runner (différent pour chaque projet). Le fichier .env de l'essai reste dans l'ordinateur :
// il est ignoré par Git, donc jamais sauvegardé ni envoyé sur GitHub.
import { createHmac } from 'node:crypto'
import { estAgent, estAutomatisation, fichiersApplication, PILES } from './applications/index.js'
import { commandesOutils } from './depot.js'
import { jetonEssai, ADRESSE_ESSAI } from './essai-agent.js'
import { MODELE_ESSAI } from './agents/index.js'

export const FICHIER_APPLICATION = '/home/user/.kekeli/application.json'
export const FICHIER_RELANCE = '/home/user/.kekeli/relancer'
const TEMOIN = '/home/user/.kekeli/application-prete.json'
const FICHIER_MOTIFS = '/home/user/.kekeli/motifs-secrets.txt'

/** Formes reconnaissables de clés et de mots de passe écrits dans le code (recherche après chaque demande). */
export const MOTIFS_SECRETS = [
  'sk-[A-Za-z0-9]{20,}',
  'AIza[0-9A-Za-z_-]{30,}',
  'ghp_[A-Za-z0-9]{30,}',
  'xox[bp]-[A-Za-z0-9-]{20,}',
  '-----BEGIN [A-Z ]*PRIVATE KEY',
  '(password|mot_de_passe|secret|api_key)[[:space:]]*[:=][[:space:]]*["\'][^"\' ]{12,}["\']',
]

/** Adresse de l'administrateur d'essai (inventée, seulement dans la base d'essai). */
export const ADMIN_ESSAI = 'admin@essai.kekeli.test'

/** Réglages de lancement de chaque technologie (lus par le serveur d'aperçu). */
export const LANCEMENT = {
  laravel: { commande: 'php artisan serve --host=127.0.0.1 --port=8000', port: 8000, env: { PHP_CLI_SERVER_WORKERS: '4' } },
  nextjs: { commande: 'npx next dev -p 3000 -H 127.0.0.1', port: 3000, env: { NEXT_TELEMETRY_DISABLED: '1' } },
  // Agents IA : le serveur de l'agent (page de discussion, bulle, /api/message).
  langgraph: { commande: 'node serveur.js', port: 3000, env: { HOTE: '127.0.0.1', PORT: '3000' } },
  crewai: { commande: '.venv/bin/python serveur.py', port: 3000, env: { HOTE: '127.0.0.1', PORT: '3000', CREWAI_DISABLE_TELEMETRY: 'true', OTEL_SDK_DISABLED: 'true' } },
}

/** Commandes de test de chaque technologie. */
export const TESTS = { laravel: 'php artisan test', nextjs: 'npm test', langgraph: 'npm test', automatisation: 'npm test', crewai: '.venv/bin/python -m unittest discover -s tests -t .' }

/**
 * Réglages du modèle d'essai d'un agent dans l'aperçu : le relais de l'ordinateur (sans clé), que le Runner passe au
 * modèle gratuit de Kekeli, dans la limite de l'essai gratuit du jour. Ils s'effacent dès que le client a mis SA clé
 * (MODELE_API_CLE) dans ses Variables secrètes : l'agent utilise alors son propre modèle.
 */
export function reglagesEssaiAgent(secretRunner, projetId) {
  return { MODELE_API_URL: ADRESSE_ESSAI, MODELE_API_CLE: jetonEssai(secretRunner, projetId), MODELE_NOM: MODELE_ESSAI }
}

export function applicationValide(application) {
  return Boolean(application && PILES[application.pile] && /^[a-z0-9-]{2,60}$/.test(String(application.modele ?? '')))
}

/** Mot de passe de l'administrateur d'essai, propre à chaque projet. */
export function motDePasseEssai(secretRunner, projetId) {
  return 'essai-' + createHmac('sha256', secretRunner).update(`application-essai:${projetId}`).digest('hex').slice(0, 14)
}

/** Fichier .env de l'essai, à partir de .env.example (jamais de vraie clé : seulement l'essai dans l'ordinateur). */
export function fichierEnvEssai(pile, exemple, { urlApercu = '', motDePasse }) {
  const valeurs =
    pile === 'laravel'
      ? {
          APP_ENV: 'local',
          APP_DEBUG: 'true',
          APP_URL: urlApercu || 'http://127.0.0.1:8000',
          ADMIN_EMAIL: ADMIN_ESSAI,
          TRUSTED_PROXIES: '*',
          LOG_LEVEL: 'debug',
          DB_CONNECTION: 'sqlite',
          DB_DATABASE: '',
          MAIL_MAILER: 'log',
        }
      : { APP_URL: urlApercu || 'http://127.0.0.1:3000', ADMIN_EMAIL: ADMIN_ESSAI, DATABASE_URL: '' }
  // Le nom affiché vient du code (config/site.php ou lib/config.js), que l'agent personnalise :
  // il n'est pas répété dans le .env de l'essai, sinon il cacherait le nom choisi.
  const lignes = String(exemple ?? '')
    .split('\n')
    .filter((ligne) => !/^(APP_NAME|NEXT_PUBLIC_APP_NAME)=/.test(ligne))
  const vues = new Set()
  const resultat = lignes.map((ligne) => {
    const nom = /^([A-Z_]+)=/.exec(ligne)?.[1]
    if (!nom || !(nom in valeurs)) return ligne
    vues.add(nom)
    return `${nom}=${valeurs[nom]}`
  })
  for (const [nom, valeur] of Object.entries(valeurs)) if (!vues.has(nom)) resultat.push(`${nom}=${valeur}`)
  resultat.push('', '# Administrateur d’essai de Kekeli (base d’essai seulement)', `ESSAI_ADMIN_EMAIL=${ADMIN_ESSAI}`, `ESSAI_ADMIN_MOT_DE_PASSE=${motDePasse}`)
  return resultat.join('\n') + '\n'
}

const octetsPourE2b = (contenu) => (Buffer.isBuffer(contenu) ? contenu.buffer.slice(contenu.byteOffset, contenu.byteOffset + contenu.byteLength) : contenu)

/**
 * Nouveau projet : écrit les fichiers de l'application (le socle) et ouvre un dépôt
 * Git local (pour que la sauvegarde et « Voir les modifications » suivent le .gitignore de l'application).
 */
export async function deposerApplication(sandbox, dossier, application) {
  const fichiers = fichiersApplication(application.pile, application.modele)
  await sandbox.commands.run(`mkdir -p ${dossier}`)
  await sandbox.files.write(Object.entries(fichiers).map(([chemin, contenu]) => ({ path: `${dossier}/${chemin}`, data: octetsPourE2b(contenu) })))
  await sandbox.commands.run(
    `cd ${dossier} && git init -q && git config user.name "Kekeli" && git config user.email "kekeli@users.noreply.github.com" && git add -A && git commit -q -m "Application de départ (Kekeli)"`,
    { timeoutMs: 120000 },
  )
  return Object.keys(fichiers).length
}

/** Une commande dans le dossier du projet : résultat, durée, et la fin de la sortie en cas d'échec. */
async function lancer(sandbox, dossier, commande, { envs = {}, timeoutMs = 600000 } = {}) {
  const debut = Date.now()
  const sortie = await sandbox.commands.run(`cd ${dossier} && ${commande}`, { envs, timeoutMs }).catch((erreur) => erreur.result ?? { exitCode: 1, stderr: erreur.message })
  return { commande, ok: sortie.exitCode === 0, secondes: Math.round((Date.now() - debut) / 1000), sortie: String(sortie.stderr || sortie.stdout || '').trim().split('\n').slice(-6).join('\n'), stdout: String(sortie.stdout ?? '') }
}

/** Attend que l'application réponde (code HTTP), au plus `delaiMs`. */
export async function attendreApplication(sandbox, pile, delaiMs = 120000) {
  const { port } = LANCEMENT[pile]
  const sortie = await sandbox.commands
    .run(`for i in $(seq 1 ${Math.ceil(delaiMs / 2000)}); do c=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 http://127.0.0.1:${port}/); [ "$c" != "000" ] && echo $c && exit 0; sleep 2; done; echo 000`, { timeoutMs: delaiMs + 30000 })
    .catch(() => ({ stdout: '000' }))
  return Number(String(sortie.stdout).trim().split('\n').pop()) || 0
}

/**
 * Prépare l'application une fois par ordinateur : outils, dépendances, .env d'essai, base d'essai, lancement.
 * Renvoie les étapes faites (vide si l'application était déjà prête), l'identifiant et le mot de passe d'essai.
 */
export async function preparerApplication(sandbox, dossier, { pile, projetId, secretRunner, urlApercu = '' }) {
  const motDePasse = motDePasseEssai(secretRunner, projetId)
  const identifiants = { identifiant: ADMIN_ESSAI, mot_de_passe: motDePasse }
  const deja = await sandbox.files.read(TEMOIN).catch(() => null)
  if (deja) return estAgent(pile) ? { etapes: [], agent: true } : estAutomatisation(pile) ? { etapes: [], automatisation: true } : { etapes: [], ...identifiants }

  const etapes = []
  // Automatisation : seulement Node 22 et les dépendances ; ni serveur, ni aperçu, ni base d'essai.
  if (estAutomatisation(pile)) {
    for (const commande of commandesOutils(['node'])) etapes.push(await lancer(sandbox, dossier, commande))
    etapes.push(await lancer(sandbox, dossier, 'npm ci --no-audit --no-fund || npm install --no-audit --no-fund'))
    await sandbox.files.write(TEMOIN, JSON.stringify({ pile, a: new Date().toISOString() }))
    return { etapes: etapes.map(({ stdout, ...etape }) => etape), automatisation: true }
  }
  // Agent IA : outils, dépendances, puis lancement avec le modèle d'essai (aucune base, aucun compte d'essai).
  if (estAgent(pile)) {
    if (pile === 'langgraph') {
      for (const commande of commandesOutils(['node'])) etapes.push(await lancer(sandbox, dossier, commande))
      etapes.push(await lancer(sandbox, dossier, 'npm ci --no-audit --no-fund || npm install --no-audit --no-fund'))
    } else {
      etapes.push(await lancer(sandbox, dossier, 'python3 -m venv .venv && .venv/bin/pip install -q --disable-pip-version-check -r requirements.txt', { timeoutMs: 900000 }))
    }
    await sandbox.files.write(FICHIER_APPLICATION, JSON.stringify({ ...LANCEMENT[pile], envParDefaut: reglagesEssaiAgent(secretRunner, projetId) }))
    const code = await attendreApplication(sandbox, pile)
    etapes.push({ commande: `${LANCEMENT[pile].commande} (agent lancé, réponse ${code || 'aucune'})`, ok: code > 0 && code < 500, secondes: 0, sortie: '' })
    await sandbox.files.write(TEMOIN, JSON.stringify({ pile, a: new Date().toISOString() }))
    return { etapes: etapes.map(({ stdout, ...etape }) => etape), agent: true }
  }
  for (const commande of commandesOutils(pile === 'laravel' ? ['php', 'composer'] : ['node'], '8.3')) etapes.push(await lancer(sandbox, dossier, commande))
  etapes.push(
    await lancer(sandbox, dossier, pile === 'laravel' ? 'composer install --no-interaction --prefer-dist --no-progress' : 'npm ci --no-audit --no-fund || npm install --no-audit --no-fund'),
  )

  // .env de l'essai, seulement s'il n'existe pas encore (les réglages ajoutés par l'agent sont gardés).
  const existe = await sandbox.files.exists(`${dossier}/.env`).catch(() => false)
  if (!existe) {
    const exemple = await sandbox.files.read(`${dossier}/.env.example`).catch(() => '')
    await sandbox.files.write(`${dossier}/.env`, fichierEnvEssai(pile, exemple, { urlApercu, motDePasse }))
  }
  if (pile === 'laravel') {
    if (!existe) etapes.push(await lancer(sandbox, dossier, 'php artisan key:generate --force'))
    etapes.push(await lancer(sandbox, dossier, 'touch database/database.sqlite && php artisan migrate --force --seed'))
  } else {
    etapes.push(await lancer(sandbox, dossier, 'node scripts/donnees-essai.mjs', { envs: { ESSAI_ADMIN_EMAIL: ADMIN_ESSAI, ESSAI_ADMIN_MOT_DE_PASSE: motDePasse } }))
  }

  // Le serveur d'aperçu lance l'application dès que ce fichier existe.
  await sandbox.files.write(FICHIER_APPLICATION, JSON.stringify(LANCEMENT[pile]))
  const code = await attendreApplication(sandbox, pile)
  etapes.push({ commande: `${LANCEMENT[pile].commande} (site lancé, réponse ${code || 'aucune'})`, ok: code > 0 && code < 500, secondes: 0, sortie: '' })
  await sandbox.files.write(TEMOIN, JSON.stringify({ pile, a: new Date().toISOString() }))
  return { etapes: etapes.map(({ stdout, ...etape }) => etape), ...identifiants }
}

/** Script PHP qui recrée l'administrateur d'essai sans passer par DatabaseSeeder (que l'agent a pu modifier). */
export const SCRIPT_ADMIN_LARAVEL = `<?php
// Kekeli : administrateur d'essai, seulement dans la base d'essai de l'ordinateur (jamais en production).
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
if ($app->isProduction()) { exit(0); }
$utilisateur = App\\Models\\User::updateOrCreate(['email' => getenv('ESSAI_ADMIN_EMAIL')], ['name' => 'Administrateur (essai)', 'password' => getenv('ESSAI_ADMIN_MOT_DE_PASSE')]);
$utilisateur->forceFill(['is_admin' => true])->save();
echo "compte d'essai prêt\\n";
`

const FICHIER_SCRIPT_ADMIN = '/home/user/.kekeli/admin-essai.php'

/**
 * Recrée l'administrateur d'essai (même adresse, même mot de passe) s'il a disparu ou changé, sans doublon :
 * tables remises en place si besoin, puis compte créé ou mis à jour.
 */
export async function retablirAdminEssai(sandbox, dossier, pile, motDePasse) {
  const envs = { ESSAI_ADMIN_EMAIL: ADMIN_ESSAI, ESSAI_ADMIN_MOT_DE_PASSE: motDePasse }
  if (pile === 'laravel') {
    await sandbox.files.write(FICHIER_SCRIPT_ADMIN, SCRIPT_ADMIN_LARAVEL)
    return lancer(sandbox, dossier, `php artisan migrate --force && php ${FICHIER_SCRIPT_ADMIN}`, { envs })
  }
  return lancer(sandbox, dossier, 'node scripts/donnees-essai.mjs', { envs })
}

/** Résumé d'une sortie de tests : « 16 réussis », « 2 échoués »… */
export function resumeTests(sortie) {
  // Sans les codes de couleur du terminal.
  const texte = String(sortie ?? '').replace(/\x1b\[[0-9;]*m/g, '')
  const laravel = /Tests:\s+(.*?)\s*\(/.exec(texte)?.[1]
  if (laravel) return laravel.replace(/passed/g, 'réussi(s)').replace(/failed/g, 'échoué(s)').replace(/skipped/g, 'ignoré(s)')
  const vitest = /Tests\s+(.*?)\s*\(/.exec(texte)?.[1] ?? /Tests\s+(.*)$/m.exec(texte)?.[1]
  if (vitest) return vitest.trim().replace(/passed/g, 'réussi(s)').replace(/failed/g, 'échoué(s)').replace(/skipped/g, 'ignoré(s)')
  return null
}

/**
 * Vérifications après chaque demande : tests de l'application, règles de sécurité, application qui répond.
 * Renvoie { tests, securite, sante } ; chaque problème est expliqué pour que l'agent le corrige.
 */
export async function verifierApplication(sandbox, dossier, pile, { motDePasse = null } = {}) {
  // L'agent a pu vider la base d'essai (par exemple « php artisan migrate:fresh ») : le compte d'essai donné
  // au client est recréé avant les vérifications, sinon la connexion dans l'aperçu échouerait.
  const compteEssai = motDePasse && !estAgent(pile) && !estAutomatisation(pile) ? await retablirAdminEssai(sandbox, dossier, pile, motDePasse) : null
  const tests = await lancer(sandbox, dossier, `${TESTS[pile]} 2>&1`, { timeoutMs: 600000 })
  const sortieTests = tests.stdout.split('\n').slice(-60).join('\n')

  const securite = []
  // Aucune clé ni aucun mot de passe dans les fichiers suivis par Git.
  await sandbox.files.write(FICHIER_MOTIFS, MOTIFS_SECRETS.join('\n') + '\n')
  const secrets = await lancer(sandbox, dossier, `git add -A >/dev/null 2>&1; git grep -nIE -f ${FICHIER_MOTIFS} -- . ':!*.lock' ':!package-lock.json' ':!tests/*' ':!*.test.js' | head -5`)
  if (secrets.stdout.trim()) securite.push({ genre: 'secret', detail: secrets.stdout.trim().split('\n').slice(0, 3).join(' · ') })
  const envSuivi = await lancer(sandbox, dossier, 'git ls-files --error-unmatch .env >/dev/null 2>&1 && echo suivi || true')
  if (envSuivi.stdout.includes('suivi')) securite.push({ genre: 'env', detail: 'Le fichier .env est suivi par Git : il ne doit jamais l’être (ajouter .env au .gitignore et le retirer de Git).' })
  if (pile === 'laravel') {
    const formulaires = await lancer(sandbox, dossier, `grep -rlEi '<form[^>]*method="post"' resources/views 2>/dev/null | xargs -r grep -L '@csrf' | head -5`)
    if (formulaires.stdout.trim()) securite.push({ genre: 'csrf', detail: `Formulaire sans @csrf : ${formulaires.stdout.trim().split('\n').join(', ')}` })
    const motsDePasse = await lancer(sandbox, dossier, `grep -q "'password' => 'hashed'" app/Models/User.php && echo ok || true`)
    if (!motsDePasse.stdout.includes('ok')) securite.push({ genre: 'mot_de_passe', detail: 'Le mot de passe des comptes doit rester chiffré (cast « hashed » dans app/Models/User.php).' })
  } else if (estAutomatisation(pile)) {
    // Automatisation : un fichier GitHub Actions planifié, et les clés seulement par les secrets du dépôt.
    const planifiee = await lancer(sandbox, dossier, `ls .github/workflows/*.yml .github/workflows/*.yaml 2>/dev/null | head -1 | xargs -r grep -lE '^\\s*(schedule|workflow_dispatch|push|issues|pull_request):|cron:' || true`)
    if (!planifiee.stdout.trim()) securite.push({ genre: 'workflow', detail: 'Le fichier GitHub Actions (.github/workflows/) manque ou n’a plus de déclencheur : remets « schedule » (avec cron et timezone) et « workflow_dispatch ».' })
    const enClair = await lancer(sandbox, dossier, `grep -rnE '^\\s+[A-Z][A-Z0-9_]+:\\s*[^$ ][^ ]{11,}\\s*$' .github/workflows 2>/dev/null | grep -viE 'node-version|timezone|cron|runs-on|uses:' | head -3`)
    if (enClair.stdout.trim()) securite.push({ genre: 'secret', detail: `Une valeur semble écrite en clair dans le fichier GitHub Actions : ${enClair.stdout.trim().split('\n')[0].slice(0, 160)}. Les valeurs doivent venir de \${{ secrets.NOM }}.` })
  } else if (estAgent(pile)) {
    // Agent IA : aucune clé dans ce qui part au navigateur, règles de sécurité et limite par minute gardées.
    const navigateur = await lancer(sandbox, dossier, `grep -rlEi 'MODELE_API_CLE|TAVILY_API_KEY|authorization|api_key' public 2>/dev/null | head -5`)
    if (navigateur.stdout.trim()) securite.push({ genre: 'navigateur', detail: `Une clé ou un réglage secret apparaît dans un fichier envoyé au navigateur : ${navigateur.stdout.trim().split('\n').join(', ')}. La clé doit rester sur le serveur.` })
    const regles = await lancer(sandbox, dossier, `grep -rlq 'jamais des ordres' agent && echo ok || true`)
    if (!regles.stdout.includes('ok')) securite.push({ genre: 'regles', detail: 'Les règles de sécurité de l’agent ont disparu : messages et documents doivent rester des informations, jamais des ordres (REGLES_SECURITE).' })
    const limite = await lancer(sandbox, dossier, `grep -rlqiE 'messagesParMinute|messages_par_minute' . --include=*.js --include=*.py --exclude-dir=node_modules --exclude-dir=.venv && echo ok || true`)
    if (!limite.stdout.includes('ok')) securite.push({ genre: 'limite', detail: 'La limite de messages par minute a disparu du serveur de l’agent : remets-la.' })
  } else {
    const actions = await lancer(sandbox, dossier, `for f in $(ls app/actions/*.js 2>/dev/null); do head -c 20 "$f" | grep -q "use server" || echo "$f"; done`)
    if (actions.stdout.trim()) securite.push({ genre: 'action', detail: `Action de formulaire sans « 'use server' » : ${actions.stdout.trim().split('\n').join(', ')}` })
  }

  // Une automatisation n'a pas de serveur : seulement les tests et les règles ci-dessus.
  if (estAutomatisation(pile)) {
    return { compteEssai, tests: { ok: tests.ok, commande: TESTS[pile], resume: resumeTests(sortieTests), sortie: sortieTests.slice(-3000) }, securite, sante: { ok: true, code: 0, journal: '' } }
  }

  // L'application répond-elle ? (une erreur 500 sur l'accueil est un problème à corriger)
  const code = await attendreApplication(sandbox, pile, 60000)
  let journal = ''
  if (!code || code >= 500) {
    const fin = await lancer(sandbox, dossier, pile === 'laravel' ? 'tail -n 25 storage/logs/laravel.log 2>/dev/null; tail -n 15 /tmp/application.log 2>/dev/null' : 'tail -n 30 /tmp/application.log 2>/dev/null')
    journal = fin.stdout.trim().slice(-3000)
  }

  return {
    compteEssai,
    tests: { ok: tests.ok, commande: TESTS[pile], resume: resumeTests(sortieTests), sortie: sortieTests.slice(-3000) },
    securite,
    sante: { ok: code > 0 && code < 500, code, journal },
  }
}

/** Nombre de problèmes à faire corriger par l'agent. */
export function problemesApplication(verification) {
  return (verification.tests.ok ? 0 : 1) + verification.securite.length + (verification.sante.ok ? 0 : 1)
}

/** Consigne donnée à l'agent pour corriger les problèmes trouvés par Kekeli. */
export function texteCorrectionApplication(verification) {
  const parties = ['Kekeli a vérifié le site et a trouvé des problèmes. Corrige-les avant de terminer, puis relance les tests.']
  if (!verification.tests.ok) parties.push(`Les tests échouent (${verification.tests.commande}). Fin de la sortie :\n${verification.tests.sortie}`)
  for (const probleme of verification.securite) parties.push(`Sécurité : ${probleme.detail}`)
  if (!verification.sante.ok) parties.push(`Le site ne répond pas correctement (code ${verification.sante.code || 'aucune réponse'}). Journal :\n${verification.sante.journal || '(vide)'}`)
  return parties.join('\n\n')
}

/** Informations données à l'agent au début de chaque demande sur une application web. */
export function texteApplication(application, preparation) {
  const pile = PILES[application.pile]
  if (estAutomatisation(application.pile)) {
    return [
      `[Informations de Kekeli] Automatisation ${pile.nom}, point de départ déjà en place dans le dossier du projet (étapes, journal, modèle, e-mail, fichier GitHub Actions planifié, tests).`,
      `Commande de test : ${TESTS[application.pile]}. Elle tournera sur le dépôt GitHub du client ; Kekeli la lance aussi avec « Lancer maintenant ».`,
    ].join(' ')
  }
  if (estAgent(application.pile)) {
    const lignes = [
      `[Informations de Kekeli] Agent IA ${pile.nom}, point de départ déjà en place dans le dossier du projet (rôle, documents, outils, mémoire, page de discussion, bulle, tests).`,
      `Il tourne déjà dans l’aperçu du client (port ${LANCEMENT[application.pile].port}) avec un modèle d’essai fourni par Kekeli : ne lance pas de serveur toi-même. Pour le relancer : touch ${FICHIER_RELANCE}.`,
      `Commande de test : ${TESTS[application.pile]}.`,
    ]
    for (const etape of preparation?.etapes ?? []) {
      if (!etape.ok) lignes.push(`Attention : « ${etape.commande} » a échoué (${String(etape.sortie ?? '').split('\n').slice(-3).join(' ').slice(0, 400)}). Si c'est utile, trouve la cause et corrige-la.`)
    }
    return lignes.join(' ')
  }
  const { port } = LANCEMENT[application.pile]
  const lignes = [
    `[Informations de Kekeli] Site avec base de données ${pile.nom}, déjà en place dans le dossier du projet (comptes, administration, tests).`,
    `Il tourne déjà dans l’aperçu du client (port ${port}) avec une base de données d’essai : ne lance pas de serveur toi-même. Pour le relancer après un changement de réglages : touch ${FICHIER_RELANCE}.`,
    `Compte administrateur d’essai : ${ADMIN_ESSAI} (le client connaît le mot de passe).`,
    `Commande de test : ${TESTS[application.pile]}.`,
  ]
  for (const etape of preparation?.etapes ?? []) {
    if (!etape.ok) lignes.push(`Attention : « ${etape.commande} » a échoué (${String(etape.sortie ?? '').split('\n').slice(-3).join(' ').slice(0, 400)}). Si c'est utile, trouve la cause et corrige-la.`)
  }
  return lignes.join(' ')
}

