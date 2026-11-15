import { test } from 'node:test'
import assert from 'node:assert/strict'
import { hoteEcoute } from '../src/server.js'

test('en local, le Runner n’écoute que sur la machine elle-même', () => {
  assert.equal(hoteEcoute({}), '127.0.0.1')
  assert.equal(hoteEcoute({ NODE_ENV: 'development', PORT: '3100' }), '127.0.0.1')
})

test('en production (Laravel Cloud), il écoute sur toutes les interfaces, derrière le proxy de l’hébergeur', () => {
  assert.equal(hoteEcoute({ NODE_ENV: 'production', PORT: '8080' }), undefined)
})

test('KEKELI_RUNNER_HOTE garde toujours le dernier mot', () => {
  assert.equal(hoteEcoute({ NODE_ENV: 'production', KEKELI_RUNNER_HOTE: '0.0.0.0' }), '0.0.0.0')
  assert.equal(hoteEcoute({ KEKELI_RUNNER_HOTE: '::' }), '::')
})

test('Node fait confiance au proxy d’E2B dans l’ordinateur : sinon la création d’images et le regard de Gemini échouent', async () => {
  const { VARIABLES_CERTIFICATS } = await import('../src/ordinateurs.js')
  assert.equal(VARIABLES_CERTIFICATS.NODE_EXTRA_CA_CERTS, '/etc/ssl/certs/ca-certificates.crt')
  const { readFile } = await import('node:fs/promises')
  const code = await readFile(new URL('../src/ordinateurs.js', import.meta.url), 'utf8')
  assert.match(code, /Sandbox\.create\('opencode', \{[\s\S]{0,200}envs: VARIABLES_CERTIFICATS/)
  assert.match(code, /envs: \{ \.\.\.VARIABLES_CERTIFICATS, OPENCODE_SERVER_PASSWORD/)
})

test('une construction neuve sans aucun fichier écrit est reconnue comme vide (jamais « Mon site » livré comme fini)', async () => {
  const { travailVide } = await import('../src/agent.js')
  assert.equal(travailVide({ fichiers: [] }), true)
  assert.equal(travailVide({ fichiers: [{ chemin: 'LICENCES.md', etat: 'modifie' }, { chemin: 'database/database.sqlite', etat: 'modifie' }] }), true)
  assert.equal(travailVide({ fichiers: [{ chemin: 'index.html', etat: 'ajoute' }] }), false)
  assert.equal(travailVide({ fichiers: [{ chemin: 'resources/views/welcome.blade.php', etat: 'modifie' }] }), false)
  assert.equal(travailVide(null), false, 'sans comparaison possible, jamais bloqué')
})

test('une application neuve dont l’accueil est resté celui du point de départ est reconnue (une relance, puis arrêt clair)', async () => {
  const { accueilDuDepart, TEXTE_ACCUEIL } = await import('../src/agent.js')
  const laravel = { pile: 'laravel' }
  assert.equal(accueilDuDepart({ fichiers: [{ chemin: 'app/Models/RendezVous.php', etat: 'ajoute' }, { chemin: 'routes/web.php', etat: 'modifie' }] }, laravel), true)
  assert.equal(accueilDuDepart({ fichiers: [{ chemin: 'resources/views/accueil.blade.php', etat: 'modifie' }] }, laravel), false)
  assert.equal(accueilDuDepart({ fichiers: [{ chemin: 'app/page.js', etat: 'modifie' }] }, { pile: 'nextjs' }), false)
  assert.equal(accueilDuDepart({ fichiers: [] }, { pile: 'langgraph' }), false, 'un agent n’a pas d’accueil de départ à remplacer')
  assert.equal(accueilDuDepart(null, laravel), false)
  assert.match(TEXTE_ACCUEIL, /point de départ/)
})
