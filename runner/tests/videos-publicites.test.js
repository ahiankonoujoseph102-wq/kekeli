import { test } from 'node:test'
import assert from 'node:assert/strict'
import { decouperVoix, enWav, pausesDeFfmpeg, pausesDesMots, transcrireGroq, voixDuGenre, direAvecGemini } from '../src/videos/voix.js'
import { regarderImage } from '../src/videos/vision.js'
import { chercherMediaLibre } from '../src/videos/medias.js'
import { CONSIGNES_VIDEO, consigneVideo, scenarioPropre } from '../src/videos/scenario.js'
import { voixDUneTraite } from '../src/videos/index.js'
import { REGLES_DECISION } from '../src/competences.js'

const json = (corps, status = 200) => ({ ok: status < 400, status, json: async () => corps, text: async () => JSON.stringify(corps), arrayBuffer: async () => new ArrayBuffer(8), headers: { get: () => null } })

test('voix d’une traite : chaque coupure tombe sur la pause la plus nette près de l’endroit attendu', () => {
  const phrases = ['Vos pagnes s’usent trop vite ?', 'Chez Ama Pagnes, chaque pagne est choisi à la main.', 'Commandez sur WhatsApp.']
  // Des mots avec une longue pause après chaque phrase, et de petites pauses entre les mots.
  const mots = [
    { mot: 'Vos', debut: 0.1, fin: 0.3 }, { mot: 'pagnes', debut: 0.35, fin: 0.7 }, { mot: 's’usent', debut: 0.75, fin: 1.1 }, { mot: 'trop', debut: 1.15, fin: 1.3 }, { mot: 'vite', debut: 1.35, fin: 1.7 },
    { mot: 'Chez', debut: 2.3, fin: 2.5 }, { mot: 'Ama', debut: 2.55, fin: 2.8 }, { mot: 'Pagnes', debut: 2.85, fin: 3.2 }, { mot: 'chaque', debut: 3.4, fin: 3.7 }, { mot: 'pagne', debut: 3.75, fin: 4 }, { mot: 'est', debut: 4.05, fin: 4.15 }, { mot: 'choisi', debut: 4.2, fin: 4.5 }, { mot: 'à', debut: 4.55, fin: 4.6 }, { mot: 'la', debut: 4.65, fin: 4.7 }, { mot: 'main', debut: 4.75, fin: 5.1 },
    { mot: 'Commandez', debut: 5.8, fin: 6.3 }, { mot: 'sur', debut: 6.35, fin: 6.5 }, { mot: 'WhatsApp', debut: 6.55, fin: 7.1 },
  ]
  const parties = decouperVoix(phrases, pausesDesMots(mots), { debut: 0.05, fin: 7.3 })
  assert.equal(parties.length, 3)
  assert.ok(parties[0].fin > 1.7 && parties[0].fin < 2.3, `première coupure ${parties[0].fin}`)
  assert.ok(parties[1].fin > 5.1 && parties[1].fin < 5.8, `deuxième coupure ${parties[1].fin}`)
  assert.equal(parties[2].fin, 7.3)
  assert.equal(parties[1].debut, parties[0].fin)
})

test('voix d’une traite : sans pause trouvée, les phrases se partagent la durée selon leur longueur', () => {
  const parties = decouperVoix(['Une phrase.', 'Une autre phrase un peu plus longue.'], [], { debut: 0, fin: 10 })
  assert.equal(parties.length, 2)
  assert.ok(parties[0].fin > 1 && parties[0].fin < 5)
})

test('les silences de FFmpeg deviennent des pauses', () => {
  const sortie = '[silencedetect @ 0x1] silence_start: 1.71\n[silencedetect @ 0x1] silence_end: 2.29 | silence_duration: 0.58\n[silencedetect @ 0x1] silence_start: 5.1\n[silencedetect @ 0x1] silence_end: 5.8 | silence_duration: 0.7\n'
  assert.deepEqual(pausesDeFfmpeg(sortie), [{ t: 2, longueur: 0.58 }, { t: 5.45, longueur: 0.7 }])
})

test('un son brut de Gemini reçoit son en-tête WAV ; un WAV reste tel quel', () => {
  const brut = Buffer.alloc(48000)
  const wav = enWav(brut)
  assert.equal(wav.toString('ascii', 0, 4), 'RIFF')
  assert.equal(wav.readUInt32LE(24), 24000)
  assert.equal(wav.length, 48044)
  assert.equal(enWav(wav), wav)
})

test('les voix 3.1 et 2.5 de Gemini reçoivent le texte seul (elles refusent les consignes de ton)', async () => {
  const corps = []
  const appeler = async (url, options) => {
    corps.push(JSON.parse(options.body))
    return { ok: true, status: 200, text: async () => JSON.stringify({ steps: [{ content: [{ data: Buffer.alloc(10).toString('base64') }] }] }) }
  }
  await direAvecGemini('Bonjour.', { modele: 'gemini-3.1-flash-tts-preview', voix: 'Charon' }, { appeler, env: { GEMINI_API_KEY: 'cle' } })
  await direAvecGemini('Bonjour.', { modele: 'gemini-3.8-flash-tts', voix: 'Kore' }, { appeler, env: { GEMINI_API_KEY: 'cle' } })
  assert.equal(corps[0].input[0].content[0].annotations, undefined)
  assert.equal(corps[1].input[0].content[0].annotations[0].type, 'speech_metadata')
})

test('la voix voulue par le client change le timbre des voix gratuites, jamais leur modèle', () => {
  const liste = [{ modele: 'gemini-3.8-flash-tts', voix: 'Kore' }, { modele: 'gemini-3.8-flash-lite-tts', voix: 'Kore' }]
  assert.deepEqual(voixDuGenre(liste, 'homme').map((v) => v.voix), ['Charon', 'Charon'])
  assert.deepEqual(voixDuGenre(liste, 'femme', { femme: 'Aoede' }).map((v) => v.voix), ['Aoede', 'Aoede'])
  // Une voix inconnue dans les réglages n'est jamais envoyée.
  assert.deepEqual(voixDuGenre(liste, 'homme', { homme: 'Inconnue' }).map((v) => v.voix), ['Kore', 'Kore'])
})

test('la retranscription gratuite de Groq donne le temps de chaque mot ; sans clé, elle est refusée', async () => {
  let envoye
  const appeler = async (url, options) => {
    envoye = { url, options }
    return json({ text: ' Bonjour à tous. ', duration: 1.5, words: [{ word: 'Bonjour', start: 0.1, end: 0.5 }, { word: 'à', start: 0.55, end: 0.6 }, { word: 'tous', start: 0.65, end: 1 }] })
  }
  const r = await transcrireGroq(Buffer.from('son'), 'voix.wav', {}, { appeler, env: { GROQ_API_KEY: 'cle' } })
  assert.match(envoye.url, /api\.groq\.com\/openai\/v1\/audio\/transcriptions/)
  assert.equal(envoye.options.body.get('model'), 'whisper-large-v3-turbo')
  assert.equal(envoye.options.body.get('language'), 'fr')
  assert.equal(r.texte, 'Bonjour à tous.')
  assert.equal(r.mots.length, 3)
  await assert.rejects(() => transcrireGroq(Buffer.from('son'), 'voix.wav', {}, { appeler, env: {} }), /indisponible/)
})

test('le regard de Kekeli : une image sans rapport est refusée, une bonne image acceptée, aucune clé : aucun avis', async () => {
  const reponses = [{ convient: false, note: 3, raison: 'Une personne sans rapport.' }, { convient: true, note: 8, raison: 'Des pagnes sur un étal.' }, { convient: true, note: 4, raison: 'Vague.' }]
  const appeler = async () => json({ candidates: [{ content: { parts: [{ text: JSON.stringify(reponses.shift()) }] } }] })
  const env = { GEMINI_API_KEY: 'cle' }
  assert.equal((await regarderImage(Buffer.from([0xff, 0xd8]), { phrase: 'Des pagnes choisis à la main' }, { appeler, env })).convient, false)
  assert.equal((await regarderImage(Buffer.from([0xff, 0xd8]), { phrase: 'Des pagnes choisis à la main' }, { appeler, env })).convient, true)
  // Une note trop basse n'est jamais acceptée, même si le modèle dit « convient ».
  assert.equal((await regarderImage(Buffer.from([0xff, 0xd8]), { phrase: 'x' }, { appeler, env })).convient, false)
  assert.equal(await regarderImage(Buffer.from([0xff]), { phrase: 'x' }, { appeler, env: {} }), null)
})

test('le regard passe au modèle suivant quand le premier est à sa limite du jour', async () => {
  const vus = []
  const appeler = async (url) => {
    vus.push(url)
    return url.includes('3.5') ? json({}, 429) : json({ candidates: [{ content: { parts: [{ text: '{"convient":true,"note":9,"raison":"ok"}' }] } }] })
  }
  const misesDeCote = new Set()
  const avis = await regarderImage(Buffer.from([0xff]), { phrase: 'x' }, { appeler, env: { GEMINI_API_KEY: 'cle' }, misesDeCote })
  assert.equal(avis.modele, 'gemini-3.1-flash-lite')
  assert.ok(misesDeCote.has('vision:gemini-3.5-flash-lite'))
})

test('une photo libre n’est gardée que si le regard de Kekeli la valide (trois candidates au plus)', async () => {
  const hits = [1, 2, 3, 4].map((n) => ({ pageURL: `https://pixabay.com/photo-${n}`, tags: 'african fabric market', webformatURL: `https://cdn.pixabay.com/${n}.jpg`, largeImageURL: `https://cdn.pixabay.com/${n}-grand.jpg`, user: 'auteur', imageWidth: 1000, imageHeight: 1500 }))
  const appeler = async () => json({ hits })
  const regardes = []
  const trouve = await chercherMediaLibre('african fabric market', { verifier: async ({ apercu }) => (regardes.push(apercu), apercu.endsWith('2.jpg')) }, { appeler, env: { PIXABAY_API_KEY: 'cle' } })
  assert.equal(trouve.page, 'https://pixabay.com/photo-2')
  assert.equal(regardes.length, 2)
  const rien = await chercherMediaLibre('african fabric market', { verifier: async () => false }, { appeler, env: { PIXABAY_API_KEY: 'cle' } })
  assert.equal(rien, null)
})

test('le scénario d’une publicité garde le rôle de chaque scène et la voix voulue', () => {
  const s = scenarioPropre({ genre: 'publicite', voix_genre: 'homme', scenes: [{ role: 'accroche', duree: 3, titre: 'Vos pagnes s’usent ?', voix: 'Vos pagnes s’usent trop vite ?' }, { role: 'inconnu', duree: 3, titre: 'Ama Pagnes' }] }, { dureeMax: 30 })
  assert.deepEqual(s.scenes.map((x) => x.role), ['accroche', 'autre'])
  assert.equal(s.voix_genre, 'homme')
  assert.equal(scenarioPropre({ scenes: [{ duree: 2, titre: 'a' }] }).voix_genre, 'femme')
  assert.match(consigneVideo({ demande: 'pub', voixGenre: 'homme' }), /voix d'homme/)
  for (const mot of ['accroche', 'probleme', 'solution', 'preuve', 'action', 'EXACTEMENT ce que dit la phrase', 'jamais une personne sans rapport']) assert.ok(CONSIGNES_VIDEO.includes(mot), mot)
})

test('la voix off d’une traite : une seule requête, puis une partie par scène coupée aux pauses', async () => {
  const commandes = []
  const fichiers = new Map()
  const sandbox = {
    files: { write: async (chemin, octets) => fichiers.set(chemin, octets) },
    commands: { run: async (c) => {
      commandes.push(c)
      if (c.startsWith('ffprobe')) return { stdout: JSON.stringify({ format: { duration: c.includes('complete') ? '7.4' : '2.1' }, streams: [{ codec_type: 'audio' }] }) }
      return { stdout: '' }
    } },
  }
  const appels = []
  const appeler = async (url, options) => {
    appels.push(url)
    if (url.includes('interactions')) return { ok: true, status: 200, text: async () => JSON.stringify({ steps: [{ content: [{ data: Buffer.alloc(100).toString('base64') }] }] }) }
    return json({ text: 'x', duration: 7.4, words: [{ word: 'Vos', start: 0.1, end: 1.6 }, { word: 'Chez', start: 2.3, end: 5 }, { word: 'Commandez', start: 5.8, end: 7.1 }] })
  }
  const elements = [{ voix: 'Vos pagnes s’usent trop vite ?' }, { voix: 'Chez Ama Pagnes, chaque pagne est choisi à la main.' }, { titre: 'sans voix' }, { voix: 'Commandez sur WhatsApp.' }]
  const r = await voixDUneTraite({ elements, voixGratuites: [{ modele: 'gemini-3.8-flash-tts', voix: 'Kore' }], style: '', sandbox, dossier: '/d', appeler, env: { GEMINI_API_KEY: 'g', GROQ_API_KEY: 'q' }, journal: { warn() {} } })
  assert.equal(appels.filter((u) => u.includes('interactions')).length, 1)
  assert.deepEqual([...r.clips.keys()], [0, 1, 3])
  assert.equal(r.paiements.length, 1)
  assert.equal(r.modele, 'gemini-3.8-flash-tts')
  assert.equal(commandes.filter((c) => c.includes('-ss ')).length, 3)
})

test('une seule phrase de voix : pas de voix d’une traite (la méthode habituelle suffit)', async () => {
  assert.equal(await voixDUneTraite({ elements: [{ voix: 'Une seule phrase.' }], voixGratuites: [{ modele: 'gemini-3.8-flash-tts', voix: 'Kore' }] }), null)
})

test('le premier répondant demande la voix d’une publicité et explique une action impossible avec une autre proposition', () => {
  const texte = REGLES_DECISION.map((c) => c.texte).join('\n')
  for (const mot of ['« Voix de femme », « Voix d’homme », « Ma propre voix »', 'Enregistrer ma voix', 'Je ne peux pas appeler votre client', 'propose tout de suite ce que tu peux faire à la place']) assert.ok(texte.includes(mot), mot)
})
