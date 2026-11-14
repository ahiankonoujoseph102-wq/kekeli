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
