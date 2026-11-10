import { test } from 'node:test'
import assert from 'node:assert/strict'
import { CATALOGUE, entreesUtilisables, fournisseursAvecCle, fournisseursOpencode, modeleAutorise, reglesReseau } from '../src/fournisseurs.js'
import { configurationOpencode } from '../src/ordinateurs.js'
import { sitesAutorises } from '../src/reseau.js'

test('Groq est un fournisseur : clé lue dans GROQ_API_KEY, ajoutée par le proxy, jamais dans l’ordinateur', () => {
  assert.equal(CATALOGUE.groq.variableCle, 'GROQ_API_KEY')
  assert.equal(CATALOGUE.groq.hote, 'api.groq.com')
  assert.ok(fournisseursAvecCle({ GROQ_API_KEY: 'gsk_faux' }).has('groq'))
  const regles = reglesReseau(new Set(['groq']), (nom) => `{{${nom}}}`)
  assert.deepEqual(regles['api.groq.com'], [{ transform: { headers: { Authorization: 'Bearer {{kekeli_groq}}' } } }])
  assert.deepEqual(fournisseursOpencode(new Set(['groq'])).groq.options.apiKey, 'injectee-par-e2b')
  assert.ok(sitesAutorises([], new Set(['groq'])).includes('api.groq.com'))
})

test('OpenRouter : seuls les modèles gratuits sont autorisés, jamais un modèle payant ni le routeur au hasard', () => {
  assert.equal(modeleAutorise('openrouter', 'nvidia/nemotron-3-ultra-550b-a55b:free'), true)
  assert.equal(modeleAutorise('openrouter', 'anthropic/claude-sonnet-5.5'), false)
  assert.equal(modeleAutorise('openrouter', 'openai/gpt-oss-120b'), false)
  assert.equal(modeleAutorise('openrouter', 'openrouter/free'), false)
  assert.equal(modeleAutorise('openrouter', 'openrouter/auto:free'), false)
  assert.equal(modeleAutorise('openrouter', 'nvidia/nemotron:free:extended'), false)
  assert.equal(modeleAutorise('groq', 'openai/gpt-oss-120b'), true)

  const cles = new Set(['gemini', 'groq', 'openrouter'])
  const entrees = entreesUtilisables([
    { fournisseur: 'openrouter', modele: 'anthropic/claude-sonnet-5.5' },
    { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' },
    { fournisseur: 'groq', modele: 'openai/gpt-oss-120b' },
    { fournisseur: 'openrouter', modele: 'nvidia/nemotron-3-ultra-550b-a55b:free' },
  ], cles)
  assert.deepEqual(entrees.map((e) => `${e.fournisseur}/${e.modele}`), ['gemini/gemini-3.5-flash-lite', 'groq/openai/gpt-oss-120b', 'openrouter/nvidia/nemotron-3-ultra-550b-a55b:free'])
})

test('OpenCode ne connaît que les modèles OpenRouter gratuits de la liste, et son petit modèle est fixé', () => {
  const cles = new Set(['gemini', 'openrouter'])
  const entrees = [
    { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' },
    { fournisseur: 'openrouter', modele: 'nvidia/nemotron-3-ultra-550b-a55b:free' },
    { fournisseur: 'openrouter', modele: 'anthropic/claude-sonnet-5.5' },
  ]
  const config = configurationOpencode(cles, entrees)
  assert.deepEqual(config.provider.openrouter.whitelist, ['nvidia/nemotron-3-ultra-550b-a55b:free'])
  assert.equal(config.small_model, 'google/gemini-3.5-flash-lite')
  // Sans modèle gratuit dans la liste, OpenCode ne peut utiliser aucun modèle d'OpenRouter.
  assert.deepEqual(configurationOpencode(cles, []).provider.openrouter.whitelist, ['aucun-modele-autorise:free'])
  assert.equal(configurationOpencode(cles, []).small_model, undefined)
})

test('une demande trop grande pour Groq gratuit passe au fournisseur suivant au lieu d’échouer', async () => {
  const { executerTache } = await import('../src/agent.js')
  const { MisesDeCote, estTropGrande, estUneLimite } = await import('../src/limites.js')
  const { fauxOpencode, libre, occupe, finRequete } = await import('./outils.js')
  const message = 'Request too large for model `openai/gpt-oss-120b` in organization `org_exemple` service tier `on_demand` on tokens per minute (TPM): Limit 8000, Requested 8833, please reduce your message size and try again.'
  assert.equal(estTropGrande(message), true)
  assert.equal(estUneLimite(message), false)

  const GROQ = { fournisseur: 'groq', modele: 'openai/gpt-oss-120b' }
  const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
  const faux = fauxOpencode({ [GROQ.modele]: [occupe, libre], [GEMINI.modele]: [occupe, finRequete('r2'), libre] })
  faux.client.session.messages = async () => (faux.appels.prompts.length === 1
    ? { data: [{ info: { role: 'assistant', error: { name: 'APIError', data: { message } } }, parts: [] }] }
    : { data: [{ info: { role: 'assistant' }, parts: [{ type: 'text', text: 'Le site est prêt.' }] }] })
  const evenements = []
  const misesDeCote = new MisesDeCote()
  await executerTache(
    { projet: 'p1', message: 'Un site', fournisseurs: [GROQ, GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote, clesPresentes: new Set(['groq', 'gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.deepEqual(faux.appels.prompts.map((p) => p.modele), ['openai/gpt-oss-120b', 'gemini-3.5-flash-lite'])
  assert.ok(evenements.some((e) => e.type === 'mis_de_cote' && e.fournisseur === 'groq'))
  assert.ok(!evenements.some((e) => e.type === 'refus'))
  assert.deepEqual(evenements.at(-1), { type: 'terminee', reponse: 'Le site est prêt.' })
  assert.ok(!misesDeCote.estDisponible('groq/openai/gpt-oss-120b'))
})

test('le quota du jour d’OpenRouter gratuit (« free-models-per-day ») met le fournisseur de côté jusqu’à sa remise à zéro', async () => {
  const { MisesDeCote, estUnQuotaDuJour, estUneLimite } = await import('../src/limites.js')
  const message = 'Rate limit exceeded: free-models-per-day. Add 10 credits to unlock 1000 free model requests per day'
  assert.equal(estUneLimite(message), true)
  assert.equal(estUnQuotaDuJour(message), true)
  const maintenant = Date.parse('2026-11-10T19:40:00Z')
  const misesDeCote = new MisesDeCote(() => maintenant)
  const jusqua = misesDeCote.analyserEssai('openrouter/nvidia/nemotron-3-ultra-550b-a55b:free', message, 1, { remiseAZero: '2026-11-11T00:00:00Z' })
  assert.equal(jusqua.toISOString(), '2026-11-11T00:00:00.000Z')
})
