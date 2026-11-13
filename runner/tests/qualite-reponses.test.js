// Qualité des réponses comme Claude (12 novembre 2026) : consigne de style, OpenAI réservé au premier répondant,
// réflexion selon la question, résumé « RÉFLEXION : », secours gratuit, dictée vocale.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { CONSIGNES_REPONDANT, MODELES_OPENAI, consigneSysteme, entreesRepondant, enTeteEtTexte, lireReponse, niveauReflexion, nomsActuels, premierRepondant, requeteFournisseur } from '../src/repondant.js'
import { fournisseursAvecCle, reglesReseau, fournisseursOpencode } from '../src/fournisseurs.js'
import { transcrire } from '../src/transcription.js'
import { creerServeur } from '../src/server.js'
import { entetesSignes } from '../src/signature.js'

const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test', OPENAI_API_KEY: 'cle-openai-de-test' }
const CLES = new Set(['gemini'])
const reponseJson = (statut, donnees) => new Response(JSON.stringify(donnees), { status: statut, headers: { 'content-type': 'application/json' } })
const openaiTexte = (texte, reflexion = 0) => reponseJson(200, { output: [...(reflexion ? [{ type: 'reasoning', summary: [] }] : []), { type: 'message', content: [{ type: 'output_text', text: texte }] }], usage: { input_tokens: 3200, input_tokens_details: { cached_tokens: 3000 }, output_tokens: 300 + reflexion, output_tokens_details: { reasoning_tokens: reflexion } } })
const geminiTexte = (texte) => reponseJson(200, { candidates: [{ content: { parts: [{ text: texte }] } }], usageMetadata: { promptTokenCount: 1200, candidatesTokenCount: 80 } })

test('la consigne demande des réponses comme Claude : directes, à la bonne longueur, en Markdown utile, avec une recommandation', () => {
  assert.match(CONSIGNES_REPONDANT, /Commence directement par la réponse/)
  assert.match(CONSIGNES_REPONDANT, /Adapte la longueur à la question/)
  assert.match(CONSIGNES_REPONDANT, /tableau pour comparer ou chiffrer/)
  assert.match(CONSIGNES_REPONDANT, /bloc de code \(```\)/)
  assert.match(CONSIGNES_REPONDANT, /Ma recommandation/)
  assert.match(CONSIGNES_REPONDANT, /montants sont en FCFA/)
  assert.match(CONSIGNES_REPONDANT, /jamais de lien, jamais d'émoji/)
  assert.doesNotMatch(CONSIGNES_REPONDANT, /120 mots|Pas d'émoji, pas de titre, pas de tableau/)
  assert.match(CONSIGNES_REPONDANT, /« Mixx by Yas » et « Flooz »/)
})

test('une question simple va vite ; une question riche laisse le modèle réfléchir et résumer sa réflexion', () => {
  assert.equal(niveauReflexion('Quelle est la capitale du Burkina Faso ?'), 'simple')
  assert.equal(niveauReflexion('combien font 18 % de TVA sur 250 000 FCFA'), 'simple')
  assert.equal(niveauReflexion('donne moi des conseils pour attirer des clients'), 'riche')
  assert.equal(niveauReflexion('Compare une page Facebook et un site web'), 'riche')
  assert.equal(niveauReflexion('Comment fixer le prix de mes gâteaux'), 'riche')

  const simple = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6-luna' }, { lieu: 'discussion', message: 'Bonjour' }, ENV)
  assert.equal(simple.corps.reasoning.effort, 'none')
  assert.doesNotMatch(simple.corps.instructions, /RÉFLEXION :/)
  const riche = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6.1-terra' }, { lieu: 'discussion', message: 'conseils', contexte: { niveau: 'riche' } }, ENV)
  assert.equal(riche.corps.reasoning.effort, 'medium')
  assert.match(riche.corps.instructions, /RÉFLEXION :/)
  // GPT-6.1 Sol ne sait pas répondre sans réflexion : « low » au plus court.
  assert.equal(requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6.1-sol' }, { lieu: 'discussion', message: 'Bonjour' }, ENV).corps.reasoning.effort, 'low')
  // Gemini 3 : niveau de réflexion « low » ou « medium » ; jamais pour Gemma.
  const g = requeteFournisseur({ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }, { lieu: 'discussion', message: 'conseils', contexte: { niveau: 'riche' } }, ENV)
  assert.equal(g.corps.generationConfig.thinkingConfig.thinkingLevel, 'medium')
  assert.equal(requeteFournisseur({ fournisseur: 'gemini', modele: 'gemma-4-26b-a4b-it' }, { lieu: 'discussion', message: 'Bonjour' }, ENV).corps.generationConfig.thinkingConfig, undefined)
})

test('OpenAI : interface « Responses », rien de gardé chez OpenAI, une recherche à la fois, la clé seulement dans l’en-tête', () => {
  const r = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6.1-terra' }, { lieu: 'discussion', message: 'Bonjour', contexte: { recherche: true } }, ENV)
  assert.equal(r.url, 'https://api.openai.com/v1/responses')
  assert.equal(r.entetes.Authorization, 'Bearer cle-openai-de-test')
  assert.equal(r.corps.store, false)
  assert.equal(r.corps.parallel_tool_calls, false)
  assert.deepEqual(r.corps.tools.map((t) => t.name), ['creer_site', 'creer_application', 'creer_agent', 'creer_automatisation', 'creer_document', 'creer_presentation', 'creer_image', 'creer_video', 'rechercher_web'])
  assert.equal(r.corps.input.at(-1).content, 'Bonjour')
  assert.ok(!JSON.stringify(r.corps).includes('cle-openai-de-test'))
})

test('OpenAI : le cache et la réflexion sont séparés pour compter le prix exact', () => {
  const lu = lireReponse('openai', { output: [{ type: 'reasoning' }, { type: 'message', content: [{ type: 'output_text', text: 'Bonjour' }] }], usage: { input_tokens: 3200, input_tokens_details: { cached_tokens: 3000 }, output_tokens: 500, output_tokens_details: { reasoning_tokens: 200 } } })
  assert.deepEqual([lu.texte, lu.tokens_entree, lu.tokens_cache, lu.tokens_sortie, lu.tokens_reflexion], ['Bonjour', 200, 3000, 300, 200])
  const appel = lireReponse('openai', { output: [{ type: 'function_call', name: 'rechercher_web', call_id: 'appel-1', arguments: '{"requete":"prix"}' }], usage: {} })
  assert.deepEqual([appel.outil, appel.idAppel, appel.arguments.requete], ['rechercher_web', 'appel-1', 'prix'])
})

test('seuls les modèles OpenAI permis, et seulement avec la clé ; jamais dans l’ordinateur virtuel ni pour OpenCode', () => {
  assert.deepEqual(MODELES_OPENAI, ['gpt-6-luna', 'gpt-6.1-terra', 'gpt-6.1-sol'])
  const liste = [{ fournisseur: 'openai', modele: 'gpt-6-astra' }, { fournisseur: 'openai', modele: 'gpt-6.1-terra' }, { fournisseur: 'gemini', modele: 'gemini-4-argon' }, { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }]
  assert.deepEqual(entreesRepondant(liste, CLES, ENV).map((e) => e.modele), ['gpt-6.1-terra', 'gemini-3.5-flash-lite'])
  assert.deepEqual(entreesRepondant(liste, CLES, { GEMINI_API_KEY: 'x' }).map((e) => e.modele), ['gemini-3.5-flash-lite'])
  const cles = fournisseursAvecCle({ ...ENV })
  assert.ok(!cles.has('openai'))
  assert.ok(!('api.openai.com' in reglesReseau(cles, (n) => n)))
  assert.ok(!('openai' in fournisseursOpencode(cles, [])))
})

test('OpenAI d’abord ; s’il refuse (limite, plafond, panne), Gemini gratuit répond sans rien dire au client', async () => {
  const appels = []
  const appeler = async (url) => {
    appels.push(url)
    return url.includes('openai') ? reponseJson(429, { error: { message: 'Rate limit reached' } }) : geminiTexte('Bonjour, comment puis-je vous aider ?')
  }
  const r = await premierRepondant({ lieu: 'discussion', message: 'Bonjour', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }, { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }] }, { clesPresentes: CLES, appeler, env: ENV })
  assert.equal(r.ok, true)
  assert.equal(r.fournisseur, 'gemini')
  assert.deepEqual(r.refus, [{ fournisseur: 'openai', modele: 'gpt-6-luna' }])
  assert.doesNotMatch(r.reponse, /OpenAI|limite|quota/i)
})

test('la réflexion n’est montrée que si le modèle a vraiment réfléchi ; la ligne « RÉFLEXION : » sort du texte', async () => {
  const texte = 'TITRE : Prix des pagnes\nRÉFLEXION : Je calcule le prix après la remise.\nAffichez **12 000 FCFA**.'
  const reflechi = await premierRepondant({ lieu: 'discussion', message: 'à quel prix afficher', contexte: { titre: true }, fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6.1-terra' }] }, { clesPresentes: CLES, appeler: async () => openaiTexte(texte, 150), env: ENV })
  assert.equal(reflechi.reponse, 'Affichez **12 000 FCFA**.')
  assert.equal(reflechi.titre, 'Prix des pagnes')
  assert.equal(reflechi.reflexion.resume, 'Je calcule le prix après la remise.')
  assert.ok(reflechi.reflexion.secondes >= 1)
  const direct = await premierRepondant({ lieu: 'discussion', message: 'Bonjour', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }] }, { clesPresentes: CLES, appeler: async () => openaiTexte('RÉFLEXION : rien.\nBonjour !', 0), env: ENV })
  assert.equal(direct.reflexion, null)
  assert.equal(direct.reponse, 'Bonjour !')
  assert.deepEqual(enTeteEtTexte('RÉFLEXION : A.\nTITRE : Deux mots\nTexte'), { titre: 'Deux mots', reflexion: 'A.', texte: 'Texte' })
})

test('l’ancien nom « T-Money » devient « Mixx by Yas »', () => {
  assert.equal(nomsActuels('Payez par T-Money, TMoney ou Flooz.'), 'Payez par Mixx by Yas, Mixx by Yas ou Flooz.')
})

test('après une recherche, OpenAI reçoit le résultat lié à son appel, puis répond avec des sources', async () => {
  const corps = []
  let tour = 0
  const appeler = async (url, options) => {
    corps.push(JSON.parse(options.body))
    tour++
    return tour === 1
      ? reponseJson(200, { output: [{ type: 'reasoning', encrypted_content: 'chiffre' }, { type: 'function_call', name: 'rechercher_web', call_id: 'appel-7', arguments: '{"requete":"prix essence Togo"}' }], usage: { input_tokens: 100, output_tokens: 20 } })
      : openaiTexte('Le litre coûte **817 FCFA** [1].')
  }
  const chercher = async () => ({ ok: true, fournisseur: 'tavily', resultats: [{ titre: 'Prix', url: 'https://www.republicoftogo.com/essence', extrait: '817 FCFA', date: 'septembre 2026' }, { titre: 'Autre', url: 'https://exemple.tg/a', extrait: 'x' }] })
  const r = await premierRepondant({ lieu: 'discussion', message: 'prix de l’essence au Togo en ce moment', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6.1-terra' }], recherche: { permise: true, restantes: 2 } }, { clesPresentes: CLES, appeler, env: ENV, chercher })
  assert.equal(r.ok, true)
  assert.equal(r.sources[0].url, 'https://www.republicoftogo.com/essence')
  const suite = corps[1].input
  assert.equal(suite.at(-1).type, 'function_call_output')
  assert.equal(suite.at(-1).call_id, 'appel-7')
  assert.ok(suite.some((e) => e.type === 'reasoning' && e.encrypted_content === 'chiffre'))
})

test('la dictée vocale : OpenAI « gpt-transcribe », formats et taille vérifiés, jamais sans clé', async () => {
  let recu = null
  const appeler = async (url, options) => {
    recu = { url, options }
    return reponseJson(200, { text: ' je veux un site  pour mon restaurant ', usage: { type: 'duration', seconds: 7.2 } })
  }
  const audio = Buffer.from('enregistrement').toString('base64')
  const r = await transcrire({ audio, type: 'audio/webm;codecs=opus', langue: 'fr' }, { appeler, env: ENV })
  assert.deepEqual(r, { ok: true, texte: 'je veux un site pour mon restaurant', secondes: 8, modele: 'gpt-transcribe' })
  assert.equal(recu.url, 'https://api.openai.com/v1/audio/transcriptions')
  assert.equal(recu.options.headers.Authorization, 'Bearer cle-openai-de-test')
  assert.equal(recu.options.body.get('model'), 'gpt-transcribe')
  assert.equal(recu.options.body.get('language'), 'fr')
  assert.deepEqual(await transcrire({ audio, type: 'text/html' }, { appeler, env: ENV }), { ok: false, erreur: 'format_refuse' })
  assert.deepEqual(await transcrire({ audio, type: 'audio/webm', modele: 'gpt-6-astra' }, { appeler, env: ENV }), { ok: false, erreur: 'modele_refuse' })
  assert.deepEqual(await transcrire({ audio, type: 'audio/webm' }, { appeler, env: {} }), { ok: false, erreur: 'cle_absente' })
})

test('l’adresse /transcrire du Runner est signée', async () => {
  const secret = 'secret-de-test-transcription'
  const serveur = creerServeur({ secret, clesPresentes: CLES, ordinateurs: {}, transcrireDictee: async (d) => ({ ok: true, texte: `lu : ${d.type}`, secondes: 3 }), journal: { error() {}, log() {} } })
  await new Promise((resolve) => serveur.listen(0, resolve))
  const adresse = `http://127.0.0.1:${serveur.address().port}/transcrire`
  try {
    const corps = JSON.stringify({ audio: 'YWJj', type: 'audio/webm', langue: 'fr' })
    assert.equal((await fetch(adresse, { method: 'POST', body: corps })).status, 401)
    const ok = await fetch(adresse, { method: 'POST', body: corps, headers: { 'content-type': 'application/json', ...entetesSignes(secret, corps) } })
    assert.equal(ok.status, 200)
    assert.deepEqual(await ok.json(), { ok: true, texte: 'lu : audio/webm', secondes: 3 })
  } finally {
    serveur.close()
  }
})
