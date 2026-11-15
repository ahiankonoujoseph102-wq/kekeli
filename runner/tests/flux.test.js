// Réponses mot par mot (chantier « vitesse », 15 novembre 2026) : le Runner fait suivre chaque morceau de texte du
// modèle, sans jamais montrer les lignes d'en-tête, puis rend exactement le même résultat qu'une réponse d'un bloc.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { lireFlux, requeteEnFlux, suiviTexte, texteVisible } from '../src/flux.js'
import { premierRepondant } from '../src/repondant.js'

const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test', OPENAI_API_KEY: 'cle-openai-de-test' }
const CLES = new Set(['gemini'])

/** Une réponse « server-sent events » comme celle des fournisseurs, envoyée en plusieurs morceaux. */
function reponseSse(evenements) {
  const texte = evenements.map((e) => `data: ${JSON.stringify(e)}\n\n`).join('') + 'data: [DONE]\n\n'
  const octets = new TextEncoder().encode(texte)
  const corps = new ReadableStream({
    start(controle) {
      for (let i = 0; i < octets.length; i += 7) controle.enqueue(octets.slice(i, i + 7))
      controle.close()
    },
  })
  return new Response(corps, { status: 200, headers: { 'content-type': 'text/event-stream' } })
}

test('les lignes « TITRE : » et « RÉFLEXION : » ne sont jamais montrées, même à moitié écrites', () => {
  assert.equal(texteVisible('TIT'), null)
  assert.equal(texteVisible('TITRE : Prix du'), null)
  assert.equal(texteVisible('TITRE : Prix du ciment\nLe sac'), 'Le sac')
  assert.equal(texteVisible('TITRE : Prix\nRÉFLE'), null)
  assert.equal(texteVisible('TITRE : Prix\nRÉFLEXION : je compare.\nBonjour'), 'Bonjour')
  assert.equal(texteVisible('Bonjour'), 'Bonjour')
  assert.equal(texteVisible('Re'), null, 'le début de « RÉFLEXION » attend la suite')
  assert.equal(texteVisible('Ravi de vous aider'), 'Ravi de vous aider')

  const vus = []
  const suivi = suiviTexte((e) => vus.push(e))
  for (const m of ['TIT', 'RE : Salutations\n', 'Bon', 'jour ', '!']) suivi.ajouter(m)
  assert.deepEqual(vus.map((e) => e.delta).join(''), 'Bonjour !')
  suivi.effacer()
  assert.equal(vus.at(-1).type, 'efface')
})

test('chaque format de fournisseur est reconstruit comme une réponse d’un bloc', async () => {
  const morceaux = []
  const openai = await lireFlux('openai', reponseSse([{ type: 'response.output_text.delta', delta: 'Bon' }, { type: 'response.output_text.delta', delta: 'jour' }, { type: 'response.completed', response: { output: [{ type: 'message', content: [{ type: 'output_text', text: 'Bonjour' }] }], usage: { input_tokens: 10, output_tokens: 2 } } }]).body, (m) => morceaux.push(m))
  assert.deepEqual(morceaux, ['Bon', 'jour'])
  assert.equal(openai.output[0].content[0].text, 'Bonjour')

  const compatible = await lireFlux('freellmapi', reponseSse([
    { choices: [{ delta: { content: 'Je ' } }] },
    { choices: [{ delta: { tool_calls: [{ index: 0, id: 'appel-1', function: { name: 'creer_', arguments: '{"no' } }] } }] },
    { choices: [{ delta: { tool_calls: [{ index: 0, function: { name: 'site', arguments: 'm":"x"}' } }] } }] },
    { choices: [], usage: { prompt_tokens: 50, completion_tokens: 5 } },
  ]).body)
  assert.equal(compatible.choices[0].message.content, 'Je ')
  assert.deepEqual(compatible.choices[0].message.tool_calls[0], { id: 'appel-1', type: 'function', function: { name: 'creer_site', arguments: '{"nom":"x"}' } })
  assert.equal(compatible.usage.prompt_tokens, 50)

  const gemini = await lireFlux('gemini', reponseSse([
    { candidates: [{ content: { parts: [{ text: 'pensée', thought: true }] } }] },
    { candidates: [{ content: { parts: [{ text: 'Bon' }] } }] },
    { candidates: [{ content: { parts: [{ text: 'jour' }] } }], usageMetadata: { promptTokenCount: 9, candidatesTokenCount: 2 } },
  ]).body, (m) => morceaux.push(m))
  assert.equal(gemini.candidates[0].content.parts.length, 3)
  assert.equal(gemini.usageMetadata.candidatesTokenCount, 2)
  assert.ok(!morceaux.includes('pensée'), 'la réflexion de Gemini n’est jamais montrée')

  assert.match(requeteEnFlux('gemini', { url: 'https://x/v1beta/models/m:generateContent', corps: {} }).url, /:streamGenerateContent\?alt=sse$/)
  assert.equal(requeteEnFlux('openai', { url: 'u', corps: {} }).corps.stream, true)
  assert.deepEqual(requeteEnFlux('freellmapi', { url: 'u', corps: {} }).corps.stream_options, { include_usage: true })
})

test('le premier répondant envoie les mots au fur et à mesure et rend le même résultat qu’avant', async () => {
  const vus = []
  const appeler = async (url, options) => {
    const corps = JSON.parse(options.body)
    assert.equal(corps.stream, true)
    return reponseSse([
      { type: 'response.output_text.delta', delta: 'TITRE : Premier bonjour à Kekeli\n' },
      { type: 'response.output_text.delta', delta: 'Bonjour, ' },
      { type: 'response.output_text.delta', delta: 'que voulez-vous créer ?' },
      { type: 'response.completed', response: { output: [{ type: 'message', content: [{ type: 'output_text', text: 'TITRE : Premier bonjour à Kekeli\nBonjour, que voulez-vous créer ?' }] }], usage: { input_tokens: 100, output_tokens: 12 } } },
    ])
  }
  const chronos = {}
  const r = await premierRepondant(
    { lieu: 'discussion', message: 'Salut', contexte: { titre: true }, fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }] },
    { clesPresentes: CLES, appeler, env: ENV, surTexte: (e) => vus.push(e), chrono: (nom) => { chronos[nom] ??= true } },
  )
  assert.equal(vus.filter((e) => e.type === 'texte').map((e) => e.delta).join(''), 'Bonjour, que voulez-vous créer ?')
  assert.equal(r.reponse, 'Bonjour, que voulez-vous créer ?')
  assert.equal(r.titre, 'Premier bonjour à Kekeli')
  assert.equal(r.tokens_sortie, 12)
  assert.ok(chronos.appel_modele && chronos.premier_mot)

  // Sans flux demandé (la file d'attente, en secours), la requête reste d'un bloc, exactement comme avant.
  const bloc = await premierRepondant({ lieu: 'discussion', message: 'Salut', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }] }, {
    clesPresentes: CLES,
    env: ENV,
    appeler: async (url, options) => {
      assert.equal(JSON.parse(options.body).stream, undefined)
      return new Response(JSON.stringify({ output: [{ type: 'message', content: [{ type: 'output_text', text: 'Bonjour !' }] }], usage: {} }), { status: 200, headers: { 'content-type': 'application/json' } })
    },
  })
  assert.equal(bloc.reponse, 'Bonjour !')
})
