import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execSync } from 'node:child_process'
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, writeFileSync as ecrire } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { appliquerVersion } from '../src/versions.js'

// Un faux ordinateur : les commandes tournent pour de vrai dans un dossier temporaire de cette machine.
function ordinateur() {
  return {
    commands: { run: async (commande) => ({ stdout: execSync(commande, { shell: '/bin/bash' }).toString() }) },
    files: { write: async (chemin, donnees) => ecrire(chemin, Buffer.from(donnees)) },
  }
}

function archive(fichiers) {
  const source = mkdtempSync(path.join(tmpdir(), 'kekeli-version-source-'))
  for (const [chemin, contenu] of Object.entries(fichiers)) {
    mkdirSync(path.dirname(path.join(source, chemin)), { recursive: true })
    writeFileSync(path.join(source, chemin), contenu)
  }
  return execSync(`tar -czf - -C ${source} .`)
}

const lire = (dossier, chemin) => (existsSync(path.join(dossier, chemin)) ? readFileSync(path.join(dossier, chemin), 'utf8') : null)

test('voir une version, revenir, puis restaurer : .env, base d’essai et dépendances restent en place', async () => {
  const racine = mkdtempSync(path.join(tmpdir(), 'kekeli-racine-'))
  const projet = path.join(racine, 'projet')
  mkdirSync(path.join(projet, 'node_modules/paquet'), { recursive: true })
  mkdirSync(path.join(projet, 'database'), { recursive: true })
  writeFileSync(path.join(projet, 'index.html'), 'version 4')
  writeFileSync(path.join(projet, 'contact.html'), 'page ajoutée en version 4')
  writeFileSync(path.join(projet, '.env'), 'APP_KEY=secret-local')
  writeFileSync(path.join(projet, 'database/database.sqlite'), 'base d’essai')
  writeFileSync(path.join(projet, 'node_modules/paquet/index.js'), 'dépendance')
  // Un projet avec Git : les fichiers ignorés (construits) ne sont pas dans les versions et restent en place.
  mkdirSync(path.join(projet, 'public/build'), { recursive: true })
  writeFileSync(path.join(projet, 'public/build/manifest.json'), '{}')
  writeFileSync(path.join(projet, '.gitignore'), '/public/build\n/node_modules\n.env\n')
  execSync(`git init -q ${projet}`)
  const options = { racine: path.join(racine, '.kekeli') }
  const sandbox = ordinateur()

  const inode = (chemin) => execSync(`stat -c %i ${path.join(projet, chemin)}`).toString().trim()
  mkdirSync(path.join(projet, 'public'), { recursive: true })
  writeFileSync(path.join(projet, 'public/index.php'), 'version 4')
  const dossierPublic = inode('public')
  await appliquerVersion(sandbox, projet, 'voir', archive({ 'index.html': 'version 2', '.env.example': 'APP_KEY=', 'public/index.php': 'version 2' }), options)
  assert.equal(inode('public'), dossierPublic, 'le dossier public reste le même (le serveur de l’application continue d’y servir)')
  assert.equal(lire(projet, 'public/index.php'), 'version 2')
  assert.equal(lire(projet, 'index.html'), 'version 2')
  assert.equal(lire(projet, 'contact.html'), null, 'les fichiers ajoutés après la version 2 disparaissent de l’aperçu')
  writeFileSync(path.join(projet, 'vide-plus-tard.txt'), '')
  assert.equal(lire(projet, '.env'), 'APP_KEY=secret-local')
  assert.equal(lire(projet, 'database/database.sqlite'), 'base d’essai')
  assert.equal(lire(projet, 'node_modules/paquet/index.js'), 'dépendance')
  assert.equal(lire(projet, 'public/build/manifest.json'), '{}', 'les fichiers construits restent')
  const date = execSync(`stat -c %Y ${path.join(projet, 'index.html')}`).toString().trim()
  assert.ok(Number(date) > Date.now() / 1000 - 60, 'les fichiers remis prennent la date du moment')

  // Regarder une autre version ne perd jamais les fichiers actuels mis de côté.
  await appliquerVersion(sandbox, projet, 'voir', archive({ 'index.html': 'version 1' }), options)
  await appliquerVersion(sandbox, projet, 'revenir', null, options)
  assert.equal(lire(projet, 'index.html'), 'version 4')
  assert.equal(lire(projet, 'contact.html'), 'page ajoutée en version 4')
  assert.equal(lire(projet, '.env'), 'APP_KEY=secret-local')

  await appliquerVersion(sandbox, projet, 'remettre', archive({ 'index.html': 'version 2' }), options)
  assert.equal(lire(projet, 'index.html'), 'version 2')
  assert.equal(lire(projet, 'contact.html'), null)
  assert.equal(lire(projet, '.env'), 'APP_KEY=secret-local')
  assert.equal(lire(projet, 'database/database.sqlite'), 'base d’essai')
  assert.deepEqual(await appliquerVersion(sandbox, projet, 'revenir', null, options), { action: 'revenir', fait: false }, 'rien à revenir après une restauration')
  await assert.rejects(appliquerVersion(sandbox, projet, 'effacer', null, options), /inconnue/)
})
