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
