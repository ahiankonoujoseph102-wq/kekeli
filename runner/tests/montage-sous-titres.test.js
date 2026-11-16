import { test } from 'node:test'
import assert from 'node:assert/strict'
import { blocsSousTitres, clesDuPlan, corrigerOrthographe, lexiqueDuPlan, lignesSrt, sousTitresDuMontage } from '../src/videos/montage/sous-titres.js'
import { codeMontage } from '../src/videos/montage/code.js'
import { planPropre } from '../src/videos/montage/schema.js'
import { transcrireMotParMot } from '../src/videos/voix.js'

const motsDe = (texte, pas = 0.3) => texte.split(' ').map((mot, i) => ({ mot, debut: i * pas, fin: i * pas + 0.25 }))
const lire = (blocs) => blocs.map((b) => b.mots.map((m) => m.texte).join(' ').replace(/ /g, ' '))

const PLAN = planPropre({
  texte_voix: 'Fini la corvée ! Plus de 500 clients à Agoè. Écris-nous au 91 11 11 11.',
  marque: { nom: 'Clean Express' },
  plans: [
    { type: 'accroche', mot: 'Fini', texte: 'Fini la corvée', cles: ['corvée'] },
    { type: 'texte', mot: 'Plus', texte: 'à Agoè', cles: ['Agoè'] },
    { type: 'carte', mot: 'Écris-nous' },
  ],
}).plan

test('les blocs : 4 mots au plus, coupés à la ponctuation et aux meilleures places', () => {
  const blocs = blocsSousTitres(motsDe('Tes chemises sont encore sales ? Fini la corvée ! Clean Express lave, repasse et livre ton linge en 24 heures.'))
  assert.deepEqual(lire(blocs), ['Tes chemises', 'sont encore sales ?', 'Fini la corvée !', 'Clean Express lave,', 'repasse et livre', 'ton linge', 'en 24 heures.'])
  for (const b of blocs) {
    assert.ok(b.mots.length <= 4)
    assert.ok(b.mots.map((m) => m.texte).join(' ').length <= 24)
  }
  // Un numéro n'est jamais coupé au milieu quand il tient dans un bloc.
  assert.deepEqual(lire(blocsSousTitres(motsDe('Écris-nous au 91 11 11 11.'))), ['Écris-nous au', '91 11 11 11.'])
  // Une pause de la voix coupe le bloc.
  const pause = blocsSousTitres([{ mot: 'Bonjour', debut: 0, fin: 0.4 }, { mot: 'Agoè', debut: 1.2, fin: 1.6 }])
  assert.equal(pause.length, 2)
})

test('les temps : chaque mot garde le sien, un bloc tient au moins une demi-seconde sans chevaucher le suivant', () => {
  const blocs = blocsSousTitres(motsDe('Fini la corvée ! Bonjour.'), { decalage: 0.25 })
  assert.equal(blocs[0].mots[0].debut, 0.25)
  assert.equal(blocs[0].mots[2].debut, 0.85)
  assert.ok(blocs[0].fin <= blocs[1].debut)
  assert.ok(blocs[1].fin - blocs[1].debut >= 0.5)
})

test("l'orthographe vient du texte lu ou du plan, jamais inventée", () => {
  // Voix de Kekeli : le texte lu corrige « Finis » et « Agwe » et remet la ponctuation.
  const entendus = [{ mot: 'Finis', debut: 0, fin: 0.3 }, { mot: 'la', debut: 0.3, fin: 0.4 }, { mot: 'corvée', debut: 0.4, fin: 0.8 }, { mot: 'Plus', debut: 1, fin: 1.2 }, { mot: 'de', debut: 1.2, fin: 1.3 }, { mot: '500', debut: 1.3, fin: 1.6 }, { mot: 'clients', debut: 1.6, fin: 1.9 }, { mot: 'à', debut: 1.9, fin: 2 }, { mot: 'Agwe.', debut: 2, fin: 2.3 }]
  const corriges = corrigerOrthographe(entendus, { texte: PLAN.texte_voix })
  assert.deepEqual(corriges.map((m) => m.mot), ['Fini', 'la', 'corvée !', 'Plus', 'de', '500', 'clients', 'à', 'Agoè.'])
  // Voix du client (sans texte lu) : seuls les mots du plan corrigent, la ponctuation entendue reste.
  const client = corrigerOrthographe([{ mot: 'Agwe.', debut: 0, fin: 0.3 }, { mot: 'Bonjour', debut: 0.4, fin: 0.7 }], { lexique: lexiqueDuPlan(PLAN) })
  assert.deepEqual(client.map((m) => m.mot), ['Agoè.', 'Bonjour'])
  // Un mot ordinaire du plan ne corrige jamais la voix : « livre » reste « livre » même si le plan écrit « livré ».
  const lexique = lexiqueDuPlan({ marque: { nom: 'Clean Express' }, plans: [{ texte: 'Livré chez toi', cles: ['livré'] }, { texte: 'Lavage', elements: [{ texte: 'Repassage' }] }] })
  assert.deepEqual(lexique.map(([, mot]) => mot), ['Clean', 'Express'])
  assert.deepEqual(corrigerOrthographe([{ mot: 'livre', debut: 0, fin: 1 }, { mot: 'repassage,', debut: 1, fin: 2 }], { lexique }).map((m) => m.mot), ['livre', 'repassage,'])
  // Un nombre ou un mot court n'est jamais remplacé par un mot « presque pareil ».
  assert.deepEqual(corrigerOrthographe([{ mot: '12', debut: 0, fin: 1 }, { mot: 'le', debut: 1, fin: 2 }], { lexique: [['11', '11'], ['la', 'la']] }).map((m) => m.mot), ['12', 'le'])
})

test('les mots-clés du plan, la marque et les nombres sont en couleur', () => {
  const cles = clesDuPlan(PLAN)
  assert.ok(cles.has('corvee') && cles.has('agoe') && cles.has('clean') && cles.has('express'))
  const blocs = sousTitresDuMontage(PLAN, motsDe('Plus de 500 clients à Agoè.'), { texte: PLAN.texte_voix })
  const mots = blocs.flatMap((b) => b.mots)
  assert.deepEqual(mots.filter((m) => m.cle).map((m) => m.texte), ['500', 'Agoè.'])
  // Le SRT reprend les blocs.
  assert.equal(lignesSrt(blocs)[0].texte, 'Plus de 500 clients')
})

test('le code de la vidéo reçoit les sous-titres et la mention de la voix comme des données', () => {
  const blocs = sousTitresDuMontage(PLAN, motsDe('Fini la corvée !'))
  const code = codeMontage({ ...PLAN, duree: 5, plans: PLAN.plans.map((p, i) => ({ ...p, debut: i, duree: 1, temps: {} })), sous_titres: blocs, voix_ia: true })
  assert.match(code, /"sousTitres":\[\{"debut":0/)
  assert.match(code, /"voixIA":true/)
  assert.doesNotMatch(code, /eval|Function\(/)
})

test('la transcription : gratuite (Groq) d’abord, OpenAI seulement en dernier secours', async () => {
  const appels = []
  const reponse = (ok, corps) => ({ ok, status: ok ? 200 : 429, json: async () => corps, text: async () => JSON.stringify(corps) })
  const mots = { text: 'Bonjour', words: [{ word: 'Bonjour', start: 0, end: 0.4 }], duration: 0.5 }
  const env = { GROQ_API_KEY: 'faux-groq', OPENAI_API_KEY: 'faux-openai' }
  const gratuit = await transcrireMotParMot(Buffer.from('son'), 'voix.wav', { env, appeler: async (url) => { appels.push(url); return reponse(true, mots) } })
  assert.equal(gratuit.service, 'groq')
  assert.equal(appels.length, 1)
  assert.match(appels[0], /groq/)
  appels.length = 0
  const secours = await transcrireMotParMot(Buffer.from('son'), 'voix.wav', { env, appeler: async (url) => { appels.push(url); return url.includes('groq') ? reponse(false, {}) : reponse(true, mots) } })
  assert.equal(secours.service, 'openai')
  assert.deepEqual(appels.map((u) => (u.includes('groq') ? 'groq' : 'openai')), ['groq', 'openai'])
  // Sans clé OpenAI, l'échec de la transcription gratuite est rendu tel quel.
  await assert.rejects(transcrireMotParMot(Buffer.from('son'), 'voix.wav', { env: { GROQ_API_KEY: 'faux' }, appeler: async () => reponse(false, {}) }))
})
