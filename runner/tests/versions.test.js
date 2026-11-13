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
  const options = { racine: path.join(racine, '.kekeli') }
  const sandbox = ordinateur()

  await appliquerVersion(sandbox, projet, 'voir', archive({ 'index.html': 'version 2', '.env.example': 'APP_KEY=' }), options)
  assert.equal(lire(projet, 'index.html'), 'version 2')
  assert.equal(lire(projet, 'contact.html'), null, 'les fichiers ajoutés après la version 2 disparaissent de l’aperçu')
  assert.equal(lire(projet, '.env'), 'APP_KEY=secret-local')
  assert.equal(lire(projet, 'database/database.sqlite'), 'base d’essai')
  assert.equal(lire(projet, 'node_modules/paquet/index.js'), 'dépendance')

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
