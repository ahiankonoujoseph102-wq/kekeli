import { test } from 'node:test'
import assert from 'node:assert/strict'
import { CONSIGNES_QUESTION, lireReponse, repondreQuestion, requeteFournisseur, sansPropositionDeModification, texteQuestion } from '../src/questions.js'
import { creerServeur } from '../src/server.js'
import { entetesSignes } from '../src/signature.js'

const SECRET = 'secret-de-test-questions'
const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test', OPENROUTER_API_KEY: 'cle-openrouter-de-test' }
const LISTE = [
  { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' },
  { fournisseur: 'openrouter', modele: 'google/gemma-4-31b-it:free' },
]
const reponseJson = (statut, donnees) => new Response(JSON.stringify(donnees), { status: statut, headers: { 'content-type': 'application/json' } })

test('les consignes d’une question : répondre sans modifier le site, conseils d’hébergement honnêtes, sans lien', () => {
  assert.match(CONSIGNES_QUESTION, /Tu NE modifies PAS le site/)
  assert.match(CONSIGNES_QUESTION, /2 ou 3 choix honnêtes/)
  assert.match(CONSIGNES_QUESTION, /prix à vérifier sur le site de l'hébergeur/)
  assert.match(CONSIGNES_QUESTION, /Aucun lien, aucune publicité/)
  assert.match(CONSIGNES_QUESTION, /Voulez-vous que je modifie votre site/)
  assert.doesNotMatch(CONSIGNES_QUESTION, /https?:\/\//)
  // Le mot « application » n'apparaît que dans la règle qui l'interdit.
  assert.equal((CONSIGNES_QUESTION.match(/application/g) ?? []).length, 1)
})

test('le texte envoyé au modèle donne le type de site, le pays et la question', () => {
  const texte = texteQuestion('Où héberger mon site ?', { nom: 'Pressing Éclat', type: 'laravel', pays: 'Togo', monnaie: 'franc CFA (FCFA)', doute: true }, [{ role: 'user', contenu: 'Un site pour mon pressing' }])
  assert.match(texte, /Laravel \(PHP et MySQL\)/)
  assert.match(texte, /Pays du client : Togo, monnaie : franc CFA/)
  assert.match(texte, /demande poliment si tu dois modifier le site/)
  assert.match(texte, /Client : Un site pour mon pressing/)
  assert.ok(texte.endsWith('Où héberger mon site ?'))
})

test('Gemini et les fournisseurs au format OpenAI : adresse, clé dans l’en-tête et tokens lus', () => {
  const g = requeteFournisseur(LISTE[0], 'Bonjour', ENV)
  assert.equal(g.url, 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent')
  assert.equal(g.entetes['x-goog-api-key'], 'cle-gemini-de-test')
  assert.ok(!g.url.includes('cle-gemini-de-test'), 'la clé ne passe jamais dans l’adresse')
  const o = requeteFournisseur(LISTE[1], 'Bonjour', ENV)
  assert.equal(o.url, 'https://openrouter.ai/api/v1/chat/completions')
  assert.equal(o.corps.model, 'google/gemma-4-31b-it:free')
  assert.deepEqual(lireReponse('gemini', { candidates: [{ content: { parts: [{ text: 'Réponse' }] } }], usageMetadata: { promptTokenCount: 900, candidatesTokenCount: 120, thoughtsTokenCount: 30 } }), {
    texte: 'Réponse', tokens_entree: 900, tokens_sortie: 120, tokens_reflexion: 30, tokens_cache: 0,
  })
})

test('une question est répondue sans ordinateur ; sur une limite atteinte, le fournisseur suivant répond', async () => {
  const appels = []
  const appeler = async (url) => {
    appels.push(url)
    if (url.includes('generativelanguage')) return reponseJson(429, { error: { status: 'RESOURCE_EXHAUSTED' } })
    return reponseJson(200, { choices: [{ message: { content: 'Trois choix possibles…' } }], usage: { prompt_tokens: 1000, completion_tokens: 150 } })
  }
  const resultat = await repondreQuestion({ question: 'Où héberger mon site ?', fournisseurs: LISTE }, { clesPresentes: new Set(['gemini', 'openrouter']), appeler, env: ENV })
  assert.equal(resultat.ok, true)
  assert.equal(resultat.reponse, 'Trois choix possibles…')
  assert.equal(resultat.fournisseur, 'openrouter')
  assert.equal(resultat.tokens_entree, 1000)
  assert.deepEqual(resultat.refus, [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }])
  assert.equal(appels.length, 2)

  // Jamais de modèle payant, même demandé : il est ignoré.
  const payant = await repondreQuestion({ question: 'Combien ?', fournisseurs: [{ fournisseur: 'openrouter', modele: 'openai/gpt-5' }] }, { clesPresentes: new Set(['openrouter']), appeler, env: ENV })
  assert.equal(payant.ok, false)
})

test('le serveur répond à une question seulement sur demande signée, sans toucher aux ordinateurs', async () => {
  const recues = []
  const ordinateurs = new Proxy({}, { get: () => { throw new Error('aucun ordinateur pour une question') } })
  const serveur = creerServeur({
    secret: SECRET,
    clesPresentes: new Set(['gemini']),
    ordinateurs,
    repondre: async (demande) => {
      recues.push(demande)
      return { ok: true, reponse: 'Voici trois choix.', fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite', tokens_entree: 900, tokens_sortie: 100, refus: [] }
    },
    journal: { error() {}, warn() {}, log() {} },
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const poster = (donnees, signe = true) => {
    const corps = JSON.stringify(donnees)
    return fetch(`http://127.0.0.1:${serveur.address().port}/question`, { method: 'POST', body: corps, headers: signe ? entetesSignes(SECRET, corps) : {} })
  }
  try {
    assert.equal((await poster({ projet: '01JABC', question: 'Où ?' }, false)).status, 401)
    assert.equal((await poster({ projet: '01JABC', question: '' })).status, 422)
    const reponse = await poster({ projet: '01JABC', question: 'Où héberger mon site ?', contexte: { type: 'vitrine' }, fournisseurs: LISTE })
    assert.equal(reponse.status, 200)
    assert.equal((await reponse.json()).reponse, 'Voici trois choix.')
    assert.equal(recues[0].question, 'Où héberger mon site ?')
  } finally {
    serveur.close()
  }
})

test('une vraie question ne se termine jamais par une proposition de modifier le site', () => {
  assert.equal(sansPropositionDeModification('Trois choix possibles.\n\nVoulez-vous que je modifie votre site dans ce sens ?'), 'Trois choix possibles.')
  assert.equal(sansPropositionDeModification('Netlify est gratuit. Souhaitez-vous que je change quelque chose ?'), 'Netlify est gratuit.')
  assert.equal(sansPropositionDeModification('Quel est votre budget ?'), 'Quel est votre budget ?')
})
