import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execSync } from 'node:child_process'
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { brancheValide, commandesOutils, depotValide, detecterProjet, etatDepot, initialiserGit, lireEtatFichiers, ouvrirDepot, texteDepot } from '../src/depot.js'
import { archiver } from '../src/sauvegarde.js'
import { executerTache } from '../src/agent.js'
import { CONSIGNES_DEVELOPPEUR, configurationOpencode } from '../src/ordinateurs.js'
import { MisesDeCote } from '../src/limites.js'
import { fauxOpencode, finRequete, libre, occupe } from './outils.js'

const silencieux = { error() {}, warn() {}, log() {} }

/** Faux ordinateur : les commandes tournent pour de vrai sur cette machine, dans un dossier temporaire. */
function ordinateurLocal() {
  return {
    commands: {
      run: async (commande) => {
        try {
          return { exitCode: 0, stdout: execSync(commande, { shell: '/bin/bash', encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }), stderr: '' }
        } catch (erreur) {
          const e = new Error(erreur.message)
          e.result = { exitCode: erreur.status ?? 1, stdout: String(erreur.stdout ?? ''), stderr: String(erreur.stderr ?? '') }
          throw e
        }
      },
    },
    files: {
      write: async (chemin, contenu) => {
        await mkdir(path.dirname(chemin), { recursive: true })
        await writeFile(chemin, typeof contenu === 'string' ? contenu : Buffer.from(contenu))
      },
      read: async (chemin, options) => (options?.format === 'bytes' ? readFile(chemin) : readFile(chemin, 'utf8')),
    },
  }
}

/** Archive comme celles de GitHub : un dossier racine « propriétaire-dépôt-commit ». */
async function archiveGithub(fichiers) {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-archive-'))
  const racine = path.join(dossier, 'client-boutique-abc123')
  for (const [nom, contenu] of Object.entries(fichiers)) {
    await mkdir(path.dirname(path.join(racine, nom)), { recursive: true })
    await writeFile(path.join(racine, nom), contenu)
  }
  execSync(`tar -czf ${dossier}/a.tar.gz -C ${dossier} client-boutique-abc123`)
  const octets = await readFile(`${dossier}/a.tar.gz`)
  await rm(dossier, { recursive: true, force: true })
  return octets
}

test('les noms de dépôt et de branche sont vérifiés', () => {
  assert.ok(depotValide('client/boutique'))
  assert.ok(!depotValide('client/boutique; rm -rf /'))
  assert.ok(!depotValide('https://github.com/client/boutique'))
  assert.ok(brancheValide('kekeli/ajout-page-contact-20261110'))
  for (const mauvais of ['main; rm -rf ~', '../x', 'a b', 'x.lock', 'x/']) assert.ok(!brancheValide(mauvais), mauvais)
})

test('Kekeli reconnaît un projet Laravel, Node, PHP, Python ou un site simple', () => {
  const laravel = detecterProjet(['artisan', 'composer.json', 'package.json', 'package-lock.json', 'CLAUDE.md', 'README.md'], (nom) => ({
    'composer.json': JSON.stringify({ require: { 'laravel/framework': '^12.0' }, 'require-dev': { 'laravel/pint': '^1.0' } }),
    'package.json': JSON.stringify({ scripts: { build: 'vite build', dev: 'vite' } }),
  })[nom])
  assert.equal(laravel.genre, 'laravel')
  assert.deepEqual(laravel.outils, ['php', 'composer', 'node'])
  assert.deepEqual(laravel.tester, ['php artisan test'])
  assert.deepEqual(laravel.style, ['vendor/bin/pint --test'])
  assert.ok(laravel.installer.includes('npm ci'))
  assert.ok(laravel.installer.includes('npm run build'), 'interface construite pour les tests')
  assert.deepEqual(laravel.instructions, ['CLAUDE.md', 'README.md'])

  const node = detecterProjet(['package.json', 'yarn.lock'], () => JSON.stringify({ scripts: { test: 'jest', lint: 'eslint .' }, dependencies: { express: '^5' } }))
  assert.equal(node.genre, 'node')
  assert.equal(node.nom, 'Node.js (Express)')
  assert.deepEqual(node.installer, ['npx --yes yarn install'])
  assert.deepEqual(node.tester, ['npx --yes yarn test'])

  assert.deepEqual(detecterProjet(['package.json'], () => JSON.stringify({ scripts: { test: 'echo "Error: no test specified" && exit 1' } })).tester, [])
  assert.deepEqual(detecterProjet(['composer.json', 'phpunit.xml'], () => '{}').tester, ['vendor/bin/phpunit'])
  assert.equal(detecterProjet(['requirements.txt']).genre, 'python')
  assert.equal(detecterProjet(['index.html', 'style.css']).genre, 'site')
  assert.equal(detecterProjet(['README.md']).genre, 'autre')
})

test('PHP et Composer ne sont installés que s’ils manquent', () => {
  const commandes = commandesOutils(['php', 'composer'])
  assert.equal(commandes.length, 2)
  assert.match(commandes[0], /^command -v php >\/dev\/null \|\| /)
  assert.match(commandes[1], /getcomposer\.org/)
  assert.deepEqual(commandesOutils(['node']), [])
})

test('l’agent sait sur quel dépôt et quelle branche il travaille, et quelles commandes lancer', () => {
  const texte = texteDepot({ depot: 'client/boutique', branche_base: 'main', branche_travail: 'kekeli/contact' }, detecterProjet(['artisan', 'composer.json', 'CLAUDE.md'], () => '{}'))
  assert.match(texte, /client\/boutique/)
  assert.match(texte, /« kekeli\/contact », créée à partir de « main »/)
  assert.match(texte, /php artisan test/)
  assert.match(texte, /Lis d'abord CLAUDE\.md/)
  assert.match(texteDepot({ depot: 'a/b', branche_base: 'main', branche_travail: 'k', lecture_seule: true }, null), /lecture seule/)
})

test('les consignes du mode développeur interdisent le push et demandent de lancer les tests', () => {
  assert.match(CONSIGNES_DEVELOPPEUR, /ne fais jamais « git push »/)
  assert.match(CONSIGNES_DEVELOPPEUR, /CLAUDE\.md/)
  assert.match(CONSIGNES_DEVELOPPEUR, /Lance les tests du projet/)
  assert.match(CONSIGNES_DEVELOPPEUR, /Ne désactive jamais un test/)
  // « git push » reste de toute façon une commande qui demande la permission du client.
  assert.equal(configurationOpencode(new Set()).permission.bash['git push*'], 'ask')
})

test('les fichiers modifiés sont lus depuis Git', () => {
  assert.deepEqual(lireEtatFichiers('M\tapp/Http/Kernel.php\nA\ttests/Feature/ContactTest.php\nD\told.txt\nR100\ta.txt\tb.txt\n'), [
    { etat: 'modifie', chemin: 'app/Http/Kernel.php' },
    { etat: 'ajoute', chemin: 'tests/Feature/ContactTest.php' },
    { etat: 'supprime', chemin: 'old.txt' },
    { etat: 'renomme', chemin: 'b.txt' },
  ])
})

test('vrai Git : dépôt ouvert, branche de travail, commits, fichiers modifiés et sauvegarde sans les fichiers ignorés', async () => {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-depot-'))
  const projet = path.join(dossier, 'projet')
  const sandbox = ordinateurLocal()
  try {
    const octets = await archiveGithub({ 'README.md': '# Boutique\n', '.gitignore': 'vendor/\n.env\n', 'src/prix.js': 'export const prix = 1\n', '.github/workflows/tests.yml': 'name: tests\n' })
    await ouvrirDepot(sandbox, projet, octets, { brancheBase: 'main', brancheTravail: 'kekeli/prix' })

    const branche = execSync(`cd ${projet} && git rev-parse --abbrev-ref HEAD`, { encoding: 'utf8' }).trim()
    assert.equal(branche, 'kekeli/prix')
    assert.equal(execSync(`cd ${projet} && cat src/prix.js`, { encoding: 'utf8' }), 'export const prix = 1\n')

    // L'agent travaille : une modification, un nouveau fichier, un commit à lui, puis un changement non enregistré.
    await writeFile(path.join(projet, 'src/prix.js'), 'export const prix = 2\n')
    await mkdir(path.join(projet, 'tests'))
    await writeFile(path.join(projet, 'tests/prix.test.js'), 'test\n')
    execSync(`cd ${projet} && git add -A && git -c user.name=a -c user.email=a@b commit -q -m "Nouveau prix"`)
    await writeFile(path.join(projet, 'README.md'), '# Boutique de Kofi\n')
    // Fichiers ignorés par le projet : jamais dans la sauvegarde.
    await mkdir(path.join(projet, 'vendor'))
    await writeFile(path.join(projet, 'vendor/gros.php'), 'x'.repeat(1000))
    await writeFile(path.join(projet, '.env'), 'SECRET=jamais\n')

    const etat = await etatDepot(sandbox, projet)
    assert.equal(etat.branche, 'kekeli/prix')
    assert.deepEqual(etat.commits, ['Travail de Kekeli', 'Nouveau prix'])
    assert.deepEqual(etat.fichiers.map((f) => `${f.etat} ${f.chemin}`).sort(), ['ajoute tests/prix.test.js', 'modifie README.md', 'modifie src/prix.js'])

    const sauvegarde = await archiver(sandbox, projet)
    const liste = await mkdtemp(path.join(tmpdir(), 'kekeli-liste-'))
    await writeFile(path.join(liste, 's.tar.gz'), sauvegarde)
    const contenu = execSync(`tar -tzf ${liste}/s.tar.gz`, { encoding: 'utf8' }).split('\n').filter(Boolean).sort()
    await rm(liste, { recursive: true, force: true })
    assert.deepEqual(contenu, ['.github/workflows/tests.yml', '.gitignore', 'README.md', 'src/prix.js', 'tests/prix.test.js'])

    // Remis depuis la sauvegarde (sans dossier .git) : le dépôt Git est refait, sur la même branche.
    await rm(path.join(projet, '.git'), { recursive: true, force: true })
    await initialiserGit(sandbox, projet, { brancheBase: 'main', brancheTravail: 'kekeli/prix', message: 'État remis' })
    assert.equal(execSync(`cd ${projet} && git rev-parse --abbrev-ref HEAD`, { encoding: 'utf8' }).trim(), 'kekeli/prix')
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('mode développeur : dépôt ouvert, outils installés, pas de vérification de site, état Git envoyé au site', async () => {
  const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  let options = null
  const verifications = []
  const ordinateurs = {
    ...faux.ordinateurs,
    obtenir: async (projet, recues) => {
      options = recues
      return { ...(await faux.ordinateurs.obtenir()), depotOuvert: true }
    },
    preparerDepot: async () => ({ detection: detecterProjet(['artisan', 'composer.json'], () => '{}'), etapes: [{ commande: 'composer install', ok: true, secondes: 15 }] }),
    etatDepot: async () => ({ branche: 'kekeli/contact', commits: ['Ajoute la page de contact'], fichiers: [{ etat: 'ajoute', chemin: 'resources/views/contact.blade.php' }], nombre_fichiers: 1 }),
    verifierLicences: async () => verifications.push('licences'),
    verifierQualite: async () => verifications.push('qualite'),
    corrigerTypographie: async () => verifications.push('typographie'),
    preparerTache: async () => verifications.push('photos'),
  }
  const evenements = []
  const depot = { depot: 'client/boutique', branche_base: 'main', branche_travail: 'kekeli/contact', archive: 'https://kekeli.test/runner/projets/p1/depot' }
  await executerTache(
    { projet: 'p1', message: 'Ajoute une page de contact', fournisseurs: [GEMINI], depot },
    { ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux },
  )
  assert.deepEqual(options.depot, depot)
  assert.deepEqual(verifications, [], 'aucune vérification de site web ni dossier de photos dans un dépôt')
  const genres = evenements.filter((e) => e.type === 'etape').map((e) => e.genre)
  assert.ok(genres.includes('depot_ouvert') && genres.includes('detection') && genres.includes('installation') && genres.includes('depot_commits'))
  assert.equal(evenements.find((e) => e.type === 'depot').branche, 'kekeli/contact')
  assert.equal(evenements.at(-1).type, 'terminee')
  assert.match(faux.appels.prompts[0].texte, /Mode développeur : dépôt GitHub client\/boutique/)
  assert.match(faux.appels.prompts[0].texte, /php artisan test/)

  // Des informations de dépôt douteuses sont refusées avant tout travail.
  const refus = []
  await executerTache(
    { projet: 'p1', message: 'x', fournisseurs: [GEMINI], depot: { ...depot, branche_travail: 'x; rm -rf ~' } },
    { ordinateurs, envoyer: async (e) => refus.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux },
  )
  assert.equal(refus.at(-1).type, 'erreur')
})
