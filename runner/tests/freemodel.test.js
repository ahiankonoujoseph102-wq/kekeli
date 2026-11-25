// FreeModel (freemodel.in), le dernier renfort de Kekeli (décision du porteur du projet du 25 novembre 2026).
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { MODELES_FREEMODEL, MODELES_GRATUITS, clesAgent, modeleAutorise, modeleOpencode } from '../src/fournisseurs.js'
import { jetonPasserelle, passerOpenAI } from '../src/passerelle.js'
import { renfortPossible } from '../src/agent.js'
import { entreesRepondant, requeteFournisseur } from '../src/repondant.js'
import { configurationOpencode, relaisUtile } from '../src/ordinateurs.js'
import { estUneLimite } from '../src/limites.js'

const env = { FREEMODEL_API_KEY: 'cle-essai' }

test('FreeModel : seulement la liste fermée, avec le préfixe du relais pour OpenCode', () => {
  assert.ok(MODELES_FREEMODEL.includes('claude-sonnet-5-5'))
  assert.equal(modeleAutorise('freemodel', 'claude-sonnet-5-5'), true)
  assert.equal(modeleAutorise('freemodel', 'claude-opus-5-5'), false, 'hors de la liste fermée')
  assert.equal(modeleOpencode({ fournisseur: 'freemodel', modele: 'glm-5.3-flash' }), 'freemodel-glm-5.3-flash')
  assert.ok(clesAgent(new Set(), env).has('freemodel'))
  assert.equal(clesAgent(new Set(), {}).has('freemodel'), false)
  assert.equal(relaisUtile(env), true)
})

test('la passerelle ajoute la clé de FreeModel ; un modèle hors liste est refusé ; une allocation épuisée devient une limite', async () => {
  const appels = []
  const appeler = async (url, options) => {
    appels.push({ url, options })
    return new Response('{"error":"tokens"}', { status: 402 })
  }
  const secretRunner = 'secret'
  const autorisation = `Bearer ${jetonPasserelle(secretRunner, 'p1')}`
  const refuse = await passerOpenAI({ projet: 'p1', chemin: 'chat/completions', autorisation, corps: JSON.stringify({ model: 'freemodel-claude-opus-5-5' }) }, { secretRunner, appeler, env })
  assert.equal(refuse.statut, 403)
  const epuise = await passerOpenAI({ projet: 'p1', chemin: 'chat/completions', autorisation, corps: JSON.stringify({ model: 'freemodel-claude-sonnet-5-5', messages: [] }) }, { secretRunner, appeler, env })
  assert.equal(epuise.statut, 429)
  assert.equal(appels[0].url, 'https://freemodel.in/v1/chat/completions')
  assert.equal(appels[0].options.headers.Authorization, 'Bearer cle-essai')
  assert.equal(JSON.parse(appels[0].options.body).model, 'claude-sonnet-5-5', 'le préfixe est retiré avant FreeModel')
  await passerOpenAI({ projet: 'p1', chemin: 'responses', autorisation, corps: JSON.stringify({ model: 'freemodel-glm-5.3-flash', input: 'Bonjour' }) }, { secretRunner, appeler, env })
  assert.equal(appels[1].url, 'https://freemodel.in/v1/responses')
  assert.equal(JSON.parse(appels[1].options.body).store, false, 'rien gardé chez FreeModel')
  const sansCle = await passerOpenAI({ projet: 'p1', chemin: 'chat/completions', autorisation, corps: JSON.stringify({ model: 'freemodel-glm-5.3-flash' }) }, { secretRunner, appeler, env: {} })
  assert.equal(sansCle.statut, 503)
  assert.equal(estUneLimite('HTTP 402 Payment Required'), true)
})

test('le premier répondant appelle FreeModel seulement avec sa clé, à son adresse compatible OpenAI', () => {
  const liste = [{ fournisseur: 'freemodel', modele: 'glm-5.3-flash' }, { fournisseur: 'freemodel', modele: 'claude-opus-5-5' }]
  assert.deepEqual(entreesRepondant(liste, new Set(), env).map((e) => e.modele), ['glm-5.3-flash'])
  assert.deepEqual(entreesRepondant(liste, new Set(), {}), [])
  const { url, entetes } = requeteFournisseur({ fournisseur: 'freemodel', modele: 'glm-5.3-flash' }, { lieu: 'discussion', message: 'Bonjour' }, env)
  assert.equal(url, 'https://freemodel.in/v1/chat/completions')
  assert.equal(entetes.Authorization, 'Bearer cle-essai')
})

test('une construction vide est reprise une fois par FreeModel, jamais deux', () => {
  const gemini = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
  const sonnet = { fournisseur: 'freemodel', modele: 'claude-sonnet-5-5' }
  assert.deepEqual(renfortPossible(gemini, [gemini, sonnet]), sonnet)
  assert.equal(renfortPossible(sonnet, [sonnet]), null, 'FreeModel a déjà travaillé')
  assert.equal(renfortPossible(gemini, [gemini]), null, 'aucun modèle de FreeModel disponible')
})

test('OpenCode reçoit les modèles de FreeModel par le relais, avec le jeton du projet et jamais la clé', () => {
  const config = configurationOpencode(new Set(['gemini']), [{ fournisseur: 'freemodel', modele: 'claude-sonnet-5-5' }], { jetonOpenAI: 'kekeli-jeton' })
  assert.ok(config.provider.openai.whitelist.includes('freemodel-claude-sonnet-5-5'))
  assert.equal(config.provider.openai.options.apiKey, 'kekeli-jeton')
  assert.doesNotMatch(JSON.stringify(config), /cle-essai/)
})

test('Cloudflare : les modèles retirés le 20 novembre 2026 ne sont plus permis', () => {
  assert.equal(MODELES_GRATUITS.workersai.includes('@cf/zai-org/glm-5.3-flash'), false)
  assert.equal(MODELES_GRATUITS.workersai.includes('@cf/qwen/qwen3-30b-a3b-fp8'), false)
  assert.equal(modeleAutorise('workersai', '@cf/zai-org/glm-4.7-flash'), true)
})

test('après FreeModel, un lien vers un site que la demande ne cite pas est trouvé ; les sources libres et la demande sont permises', async () => {
  const { liensEtrangers, fichiersAVerifier, citerShell } = await import('../src/agent.js')
  const sortie = 'https://pagnes-ama.tg\nhttp://fonts.googleapis.com\nhttps://pub-inconnue.xyz\nhttps://www.google.com\nhttps://wa.me'
  assert.deepEqual(liensEtrangers(sortie, { message: 'Mon site pagnes-ama.tg', sites: ['fonts.googleapis.com'] }), ['pub-inconnue.xyz'])
  assert.deepEqual(fichiersAVerifier({ fichiers: [{ chemin: 'index.html' }, { chemin: 'vendor/a.php' }, { chemin: 'package-lock.json' }, { chemin: 'images/a.png' }, { chemin: 'vieux.html', etat: 'supprime' }] }), ['index.html'])
  assert.equal(citerShell("a'b"), "'a'\\''b'")
})
