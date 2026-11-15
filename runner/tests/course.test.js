// La course entre fournisseurs (chantier « premier mot », 15 novembre 2026) : le coureur ne part que si le premier
// fournisseur n'a rien envoyé au bout du délai ; le premier contenu gagne ; l'autre demande est annulée ; un seul
// texte s'affiche.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { courir } from '../src/course.js'
import { premierRepondant } from '../src/repondant.js'

const attendre = (ms) => new Promise((r) => setTimeout(r, ms))

/** Une réponse « mot par mot » simulée : ses événements arrivent après « delai », et l'annulation coupe le flux. */
function fluxSimule(evenements, delai, signal, journal, nom) {
  const corps = new ReadableStream({
    async start(controle) {
      signal?.addEventListener('abort', () => { journal.push(`${nom} annulé`); try { controle.error(new Error('annulé')) } catch {} })
      await attendre(delai)
      if (signal?.aborted) return
      for (const e of evenements) {
        if (signal?.aborted) return
        controle.enqueue(new TextEncoder().encode(`data: ${JSON.stringify(e)}\n\n`))
        await attendre(5)
      }
      if (!signal?.aborted) controle.close()
    },
  })
  return new Response(corps, { status: 200, headers: { 'content-type': 'text/event-stream' } })
}

const compatible = (texte) => [{ choices: [{ delta: { content: texte } }] }, { choices: [], usage: { prompt_tokens: 100, completion_tokens: 5 } }]
const responses = (texte) => [{ type: 'response.output_text.delta', delta: texte }, { type: 'response.completed', response: { output: [{ type: 'message', content: [{ type: 'output_text', text: texte }] }], usage: { input_tokens: 100, output_tokens: 5 } } }]

function concurrents(delaiPrincipal, delaiCoureur, journal, { echecPrincipal = false } = {}) {
  const appeler = async (url, options) => {
    journal.push(`${url} appelé`)
    if (url === 'principal') {
      if (echecPrincipal) return new Response('{"error":"panne"}', { status: 500 })
      return fluxSimule(compatible('Bonjour du principal'), delaiPrincipal, options.signal, journal, 'principal')
    }
    return fluxSimule(responses('Bonjour du coureur'), delaiCoureur, options.signal, journal, 'coureur')
  }
  return {
    appeler,
    principal: { entree: { fournisseur: 'openrouter', modele: 'google/gemma-4-31b-it:free' }, url: 'principal', entetes: {}, corps: {} },
    coureur: { entree: { fournisseur: 'openai', modele: 'gpt-6.1-terra' }, url: 'coureur', entetes: {}, corps: {} },
  }
}

test('le premier fournisseur répond vite : aucune course, le coureur ne part jamais', async () => {
  const journal = []
  const vus = []
  const { appeler, principal, coureur } = concurrents(50, 10, journal)
  const r = await courir({ principal, coureur, delaiMs: 300, appeler, surMorceau: (m) => vus.push(m) })
  assert.equal(r.lancee, false)
  assert.equal(r.gagnant, principal)
  assert.ok(!journal.includes('coureur appelé'))
  assert.equal(vus.join(''), 'Bonjour du principal')
})

test('le premier fournisseur est lent : le coureur part, gagne, et le premier est annulé tout de suite', async () => {
  const journal = []
  const vus = []
  const { appeler, principal, coureur } = concurrents(3000, 50, journal)
  const debut = Date.now()
  const r = await courir({ principal, coureur, delaiMs: 200, appeler, surMorceau: (m) => vus.push(m) })
  assert.equal(r.lancee, true)
  assert.equal(r.gagnant, coureur)
  assert.equal(r.perdant, principal)
  assert.ok(journal.includes('principal annulé'), 'la demande perdante est annulée')
  assert.equal(vus.join(''), 'Bonjour du coureur', 'un seul texte, celui du gagnant')
  assert.ok(Date.now() - debut < 1500, 'on n’attend pas la fin du perdant')
  assert.equal(r.donneesFlux.output[0].content[0].text, 'Bonjour du coureur')
})

test('le premier fournisseur commence juste après le départ du coureur : il gagne et le coureur est annulé', async () => {
  const journal = []
  const vus = []
  const { appeler, principal, coureur } = concurrents(300, 2000, journal)
  const r = await courir({ principal, coureur, delaiMs: 200, appeler, surMorceau: (m) => vus.push(m) })
  assert.equal(r.lancee, true)
  assert.equal(r.gagnant, principal)
  assert.ok(journal.includes('coureur annulé'))
  assert.equal(vus.join(''), 'Bonjour du principal')
})

test('le premier fournisseur échoue tout de suite : le coureur part sans attendre le délai', async () => {
  const journal = []
  const { appeler, principal, coureur } = concurrents(0, 30, journal, { echecPrincipal: true })
  const debut = Date.now()
  const r = await courir({ principal, coureur, delaiMs: 5000, appeler })
  assert.equal(r.gagnant, coureur)
  assert.ok(Date.now() - debut < 1500)
})

test('dans le premier répondant : le coureur gagne, la réponse et la course sont rendues, un seul texte', async () => {
  const journal = []
  const vus = []
  const appeler = async (url, options) => {
    const corps = JSON.parse(options.body)
    if (/openrouter|chat\/completions/.test(url)) return fluxSimule(compatible('TITRE : Salutation\nBonjour lent'), 3000, options.signal, journal, 'openrouter')
    assert.equal(corps.stream, true)
    return fluxSimule(responses('TITRE : Salutation\nBonjour !'), 20, options.signal, journal, 'openai')
  }
  const r = await premierRepondant(
    { lieu: 'discussion', message: 'Salut', contexte: { titre: true }, fournisseurs: [{ fournisseur: 'openrouter', modele: 'google/gemma-4-31b-it:free' }], course: { fournisseur: 'openai', modele: 'gpt-6.1-terra', delai_ms: 200 } },
    { clesPresentes: new Set(['openrouter', 'openai']), env: { OPENROUTER_API_KEY: 'cle-de-test', OPENAI_API_KEY: 'cle-de-test' }, appeler, surTexte: (e) => vus.push(e) },
  )
  assert.equal(r.fournisseur, 'openai')
  assert.equal(r.reponse, 'Bonjour !')
  assert.equal(r.course.lancee, true)
  assert.deepEqual(r.course.perdant, { fournisseur: 'openrouter', modele: 'google/gemma-4-31b-it:free' })
  assert.equal(vus.filter((e) => e.type === 'texte').map((e) => e.delta).join(''), 'Bonjour !')
  assert.ok(journal.includes('openrouter annulé'))
})
