// Montage vidéo et motion design : scénario nettoyé, code écrit par Kekeli (données, jamais du code du modèle),
// sous-titres, son, médias libres avec leur licence, musique CC0, envoi en morceaux, refus, arrêt.
import test from 'node:test'
import assert from 'node:assert/strict'
import { codeProjet, codeVideo, dureeMontage, FORMATS_VIDEO } from '../src/videos/code.js'
import { consigneVideo, CONSIGNES_VIDEO, scenarioPropre } from '../src/videos/scenario.js'
import { chercherMediaLibre, chercherMusique, commandeMusiqueKekeli, LICENCES } from '../src/videos/medias.js'
import { direVoixOff, fichierSrt, morceaux, repeteLEcran, sousTitresMots, sousTitresPhrase, textePrononce } from '../src/videos/voix.js'
import { envoyerEnMorceaux, filtreSon, produireVideo } from '../src/videos/index.js'
import { OUTILS, outilsDuLieu } from '../src/repondant.js'

const fichiers = [
  { numero: 1, type: 'video', duree: 6, largeur: 1080, hauteur: 1920 },
  { numero: 2, type: 'image', largeur: 1200, hauteur: 800 },
  { numero: 3, type: 'audio', duree: 20 },
]

test('l’outil « creer_video » dans une discussion, un plan au-delà de 30 secondes, les contenus interdits refusés', () => {
  assert.ok(outilsDuLieu('discussion').includes('creer_video'))
  assert.ok(!outilsDuLieu('site').includes('creer_video'))
  assert.deepEqual(Object.keys(OUTILS.creer_video.parametres), ['duree', 'format', 'plan', 'fichier', 'confirme', 'nom'])
  assert.match(OUTILS.creer_video.parametres.plan, /plus de 30 secondes/)
  assert.match(CONSIGNES_VIDEO, /personne réelle identifiable/)
  assert.match(CONSIGNES_VIDEO, /Mixx by Yas/)
  assert.match(CONSIGNES_VIDEO, /2 secondes au plus/, 'l’accroche des deux premières secondes')
})

test('le scénario est nettoyé : formats, durées, fichiers du client, durée maximale de la formule', () => {
  const brut = {
    format: 'cinema',
    couleurs: { fond: 'rouge', accent: '#e3a008' },
    scenes: [
      { duree: 40, titre: 'x'.repeat(300), voix: 'Bonjour', media: { source: 'client', numero: 1, debut: 4 }, animation: 'explosion', disposition: 'partout' },
      { duree: 3, titre: 'Promo', media: { source: 'client', numero: 9 } },
      { duree: 3, titre: 'Libre', media: { source: 'libre', recherche: 'african fabric market' } },
    ],
    fin: { duree: 3, titre: 'Commandez sur WhatsApp', contact: '+228 90 12 34 56', paiement: 'Mixx by Yas · Flooz', logo: 2 },
  }
  const s = scenarioPropre(brut, { dureeMax: 30, format: 'auto', fichiers })
  assert.equal(s.format, 'vertical', 'format inconnu : vertical')
  assert.deepEqual(s.couleurs, { fond: '#1C1A17', texte: '#FFFFFF', accent: '#E3A008' })
  assert.equal(s.scenes[0].duree, 2, 'un extrait ne dure jamais plus que ce qui reste de la vidéo (6 − 4)')
  assert.equal(s.scenes[0].titre.length, 90)
  assert.equal(s.scenes[0].animation, 'apparition')
  assert.equal(s.scenes[0].disposition, 'centre')
  assert.equal(s.scenes[1].media.source, 'aucun', 'un fichier du client qui n’existe pas devient un fond animé')
  assert.equal(s.scenes[2].media.recherche, 'african fabric market')
  assert.equal(s.fin.logo, 2)
  assert.ok(s.voix_off)

  const long = scenarioPropre({ scenes: Array.from({ length: 10 }, () => ({ duree: 10, titre: 'A' })), fin: { duree: 5, titre: 'Fin' } }, { dureeMax: 30 })
  assert.ok(dureeMontage({ ...long, scenes: long.scenes, fin: long.fin }) <= 30.01, 'ramené à 30 secondes')
  assert.equal(scenarioPropre({ format: 'paysage', scenes: [{ titre: 'A' }] }, { format: 'carre' }).format, 'carre', 'le format choisi par le client l’emporte')
  const doublons = scenarioPropre({ scenes: [{ titre: '12 000 FCFA', prix: '12 000 FCFA' }, { titre: 'Promo', texte: 'Le pagne à 12 000 FCFA', prix: '12 000 FCFA' }], fin: { titre: 'Commandez', texte: 'Au +228 90 12 34 56', contact: '+228 90 12 34 56' } })
  assert.deepEqual([doublons.scenes[0].prix, doublons.scenes[1].prix, doublons.fin.texte], ['', '', ''], 'rien d’écrit deux fois')
  assert.equal(scenarioPropre({ voix_source: 'client', scenes: [{ titre: 'A' }] }, { fichiers: [] }).voix_source, 'kekeli', 'pas de fichier audio : la voix de Kekeli')
})

test('la consigne donne la durée maximale, les fichiers numérotés, le plan et la version précédente', () => {
  const c = consigneVideo({ demande: 'une pub', dureeVoulue: 20, plan: '## Scènes', fichiers, dureeMax: 30, format: 'carre', precedente: { format: 'vertical' }, voixPermise: false })
  assert.match(c, /Durée maximale de la vidéo pour la formule du client : 30 secondes/)
  assert.match(c, /Durée visée : environ 20 secondes/)
  assert.match(c, /1\. vidéo de 6 secondes/)
  assert.match(c, /3\. fichier audio de 20 secondes/)
  assert.match(c, /Format choisi par le client : « carre »/)
  assert.match(c, /voix off de Kekeli n’est pas disponible/)
  assert.match(c, /Fais SEULEMENT le changement demandé/)
})

test('le code de la vidéo recopie le scénario comme des données, jamais comme du code', () => {
  const piege = "'); process.exit(1); //"
  const montage = {
    format: 'paysage',
    couleurs: { fond: '#102030', texte: '#FFFFFF', accent: '#E3A008' },
    sous_titres: true,
    scenes: [{ duree: 3, titre: piege, texte: '', prix: '12 000 FCFA', media: { fichier: '/medias/scene-1.jpg', type: 'image', largeur: 1024, hauteur: 768 }, animation: 'zoom', disposition: 'haut' }],
    fin: { duree: 2, titre: 'Abonnez-vous', paiement: 'Mixx by Yas · Flooz' },
    lignes: [{ debut: 0.2, fin: 2, texte: 'Bonjour' }],
  }
  const code = codeVideo(montage, { date: '13 novembre 2026' })
  assert.ok(code.includes(JSON.stringify(piege)), 'le texte reste une chaîne JSON')
  assert.ok(!code.includes(`titre: '${piege}`))
  assert.match(code, /Scène 1 : 3 secondes, image scene-1\.jpg/)
  assert.match(code, /format horizontal \(16:9\), 1920 × 1080/)
  assert.match(code, /yield\* fin\(k, /)
  assert.match(code, /sousTitres\(k, \[\{"debut":0\.2/)
  assert.match(codeProjet(montage), /size: \{ x: 1920, y: 1080 \}/)
  assert.match(codeProjet(montage), /fps: 30/)
  assert.deepEqual(Object.keys(FORMATS_VIDEO), ['vertical', 'carre', 'paysage'])
  assert.equal(dureeMontage(montage), 5)
})

test('les sous-titres suivent la voix, en morceaux lisibles, et le fichier SRT est juste', () => {
  // Une phrase entière par sous-titre ; une phrase très longue est coupée à une virgule, jamais dans un montant.
  assert.deepEqual(morceaux('Le pagne complet à 12 000 FCFA. Retrouvez Ama Pagnes au Grand Marché de Lomé, cette semaine seulement !'), ['Le pagne complet à 12 000 FCFA.', 'Retrouvez Ama Pagnes au Grand Marché de Lomé, cette semaine seulement !'])
  assert.deepEqual(morceaux('-20 % sur les pagnes wax cette semaine.'), ['-20 % sur les pagnes wax cette semaine.'])
  assert.deepEqual(morceaux('Commandez sur WhatsApp au +228 90 12 34 56. Paiement Mixx by Yas et Flooz.'), ['Commandez sur WhatsApp au +228 90 12 34 56.', 'Paiement Mixx by Yas et Flooz.'])
  assert.deepEqual(morceaux('Le pagne complet est à seulement 12 000 FCFA au lieu de 15 000 FCFA, livré partout à Lomé, avec paiement Mixx by Yas ou Flooz.'), ['Le pagne complet est à seulement 12 000 FCFA au lieu de 15 000 FCFA,', 'livré partout à Lomé, avec paiement Mixx by Yas ou Flooz.'])
  // Un sous-titre qui répète le texte déjà à l'écran n'est pas montré.
  assert.deepEqual(sousTitresPhrase('Livraison partout à Lomé.', 0, 2, ['Livraison partout à Lomé']), [])
  assert.equal(repeteLEcran('Le pagne complet à 12 000 FCFA.', ['Pagne complet', '12 000 FCFA au lieu de 15 000 FCFA']), true)
  assert.equal(repeteLEcran('Découvrez les pagnes wax chez Ama Pagnes.', ['Ama Pagnes', 'Grand Marché de Lomé']), false)
  const lignes = sousTitresPhrase('Bienvenue chez Ama Pagnes. Livraison partout à Lomé.', 1, 4)
  assert.equal(lignes[0].debut, 1)
  assert.equal(lignes.at(-1).fin, 5)
  const mots = [{ mot: 'Bonjour', debut: 0, fin: 0.4 }, { mot: 'à', debut: 0.5, fin: 0.6 }, { mot: 'tous.', debut: 0.6, fin: 1 }, { mot: 'Voici', debut: 3, fin: 3.4 }]
  assert.deepEqual(sousTitresMots(mots).map((l) => l.texte), ['Bonjour à tous.', 'Voici'])
  assert.equal(fichierSrt([{ debut: 0.25, fin: 2.1, texte: 'Le wax à prix réduit !' }]), '1\n00:00:00,250 --> 00:00:02,100\nLe wax à prix réduit !\n')
  assert.equal(textePrononce('Le pagne à 12 000 FCFA'), 'Le pagne à 12000 francs CFA', 'le montant est dit, les sous-titres gardent les chiffres')
})

test('le son : voix, musique baissée sous la voix, volume final sans saturation', () => {
  const f = filtreSon({ voix: [{ debut: 0.25 }, { debut: 3 }], musique: { chemin: 'm.wav' }, duree: 20 })
  assert.match(f, /adelay=250:all=1/)
  assert.match(f, /sidechaincompress/)
  assert.match(f, /loudnorm=I=-15:TP=-1\.5/)
  assert.match(f, /alimiter=limit=0\.8:level=false\[son\]$/)
  assert.doesNotMatch(filtreSon({ musique: { chemin: 'm.wav' }, duree: 10 }), /sidechaincompress/, 'sans voix, la musique seule')
  assert.equal(filtreSon({ duree: 10 }), null, 'ni voix ni musique : une piste silencieuse')
  assert.match(commandeMusiqueKekeli('entrainante', 20, 'm.mp3'), /aevalsrc/)
})

test('médias libres : Pexels puis Pixabay (clés seulement dans le Runner), rien sans clé', async () => {
  const appels = []
  const appeler = async (url, options = {}) => {
    appels.push({ url, entetes: options.headers ?? {} })
    if (url.startsWith('https://api.pexels.com/')) return new Response('{"photos":[]}', { status: 200 })
    return new Response(JSON.stringify({ hits: [{ largeImageURL: 'https://pixabay.com/get/abc.jpg', pageURL: 'https://pixabay.com/photos/abc', user: 'Ama', imageWidth: 1200, imageHeight: 800 }] }), { status: 200 })
  }
  assert.equal(await chercherMediaLibre('african market', {}, { appeler, env: {} }), null, 'sans clé : aucun appel')
  assert.equal(appels.length, 0)
  const trouve = await chercherMediaLibre('african market', { type: 'image', orientation: 'portrait' }, { appeler, env: { PEXELS_API_KEY: 'cle-pexels', PIXABAY_API_KEY: 'cle-pixabay' } })
  assert.equal(appels[0].entetes.Authorization, 'cle-pexels', 'Pexels : la clé dans l’en-tête')
  assert.match(appels[1].url, /^https:\/\/pixabay\.com\/api\/\?key=cle-pixabay/)
  assert.deepEqual([trouve.source, trouve.licence, trouve.page], ['Pixabay', 'pixabay', 'https://pixabay.com/photos/abc'])
  assert.match(LICENCES.pixabay.note, /sans obligation de citer/)
})

test('musique libre : seulement des pistes CC0, assez longues, qui sont de la musique', async () => {
  const resultats = [
    { title: 'Voice memo', license: 'cc0', url: 'https://cdn.freesound.org/a.mp3', duration: 40000, tags: [{ name: 'voice' }] },
    { title: 'Nice loop', license: 'by', url: 'https://cdn.freesound.org/b.mp3', duration: 40000, tags: [] },
    { title: 'Short', license: 'cc0', url: 'https://cdn.freesound.org/c.mp3', duration: 3000, tags: [] },
    { title: 'Upbeat loop', license: 'cc0', url: 'https://cdn.freesound.org/d.mp3', duration: 58000, tags: [{ name: 'music' }], provider: 'freesound', creator: 'x', foreign_landing_url: 'https://freesound.org/s/1' },
  ]
  let adresse = ''
  const appeler = async (url) => {
    adresse = url
    return new Response(JSON.stringify({ results: resultats }), { status: 200 })
  }
  const m = await chercherMusique('entrainante', 25, { appeler })
  assert.match(adresse, /license=cc0/)
  assert.deepEqual([m.titre, m.licence, m.source], ['Upbeat loop', 'cc0', 'Freesound (trouvée avec Openverse)'])
})

test('une vidéo est envoyée au site en morceaux signés', async () => {
  const recus = []
  const appeler = async (url, options) => {
    recus.push({ url, taille: options.body.length, signe: Boolean(options.headers['X-Kekeli-Signature'] ?? Object.keys(options.headers).find((h) => /signature/i.test(h))) })
    return new Response('{"ok":true}', { status: 200 })
  }
  await envoyerEnMorceaux('https://site.test/envoi', 'secret', 'video.mp4', Buffer.alloc(25), { appeler, taille: 10 })
  assert.deepEqual(recus.map((r) => r.url), ['https://site.test/envoi?nom=video.mp4&partie=0&parties=3', 'https://site.test/envoi?nom=video.mp4&partie=1&parties=3', 'https://site.test/envoi?nom=video.mp4&partie=2&parties=3'])
  assert.deepEqual(recus.map((r) => r.taille), [10, 10, 5])
  assert.ok(recus.every((r) => r.signe))
})

/** Un faux atelier : il enregistre les commandes et répond comme FFprobe. */
function fauxAtelier() {
  const commandes = []
  const sandbox = {
    commands: { run: async (c) => { commandes.push(c); return { exitCode: 0, stdout: '{"streams":[],"format":{"duration":"0"}}', stderr: '' } } },
    files: { write: async () => {}, read: async () => new Uint8Array() },
  }
  return { commandes, atelierVideo: { obtenir: async () => sandbox } }
}

const demande = (reglages = {}) => ({
  tache: 'tache-1', projet: '01jessai', envoi: 'https://site.test/envoi',
  fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6.1-terra' }],
  video: { duree_max: 30, images_max: 2, voix: { permise: true, modele: 'gpt-4o-mini-tts', voix: 'marin' }, transcription: { permise: true, modele: 'whisper-1' }, ...reglages },
  document: { type: 'video', action: 'creer', demande: 'une vidéo', format: 'auto', joints: [] },
})
const reponseModele = (objet) => new Response(JSON.stringify({ output: [{ type: 'message', content: [{ type: 'output_text', text: JSON.stringify(objet) }] }], usage: { input_tokens: 900, output_tokens: 400 } }), { status: 200 })

test('une vidéo interdite : refus poli, aucun média, aucun rendu, aucun fichier', async () => {
  const { commandes, atelierVideo } = fauxAtelier()
  const evenements = []
  const appels = []
  const r = await produireVideo(demande(), {
    atelierVideo,
    envoyer: async (e) => { evenements.push(e); return null },
    secret: 's',
    clesPresentes: new Set(),
    env: { OPENAI_API_KEY: 'cle' },
    appeler: async (url) => { appels.push(url); return reponseModele({ refus: 'Je ne peux pas imiter une personne réelle. Je peux faire la vidéo avec une voix et un visage imaginaires.', video: {} }) },
  })
  assert.equal(r.refus, true)
  assert.ok(appels.every((u) => u.endsWith('/responses')), 'seul le scénario est demandé')
  assert.ok(!commandes.some((c) => c.includes('node rendre.mjs')))
  assert.equal(evenements.find((e) => e.type === 'document'), undefined)
  assert.match(evenements.find((e) => e.type === 'terminee').reponse, /imaginaires/)
})

test('l’énergie épuisée après le scénario arrête tout : le site est prévenu, rien n’est rendu', async () => {
  const { commandes, atelierVideo } = fauxAtelier()
  const evenements = []
  const r = await produireVideo(demande(), {
    atelierVideo,
    envoyer: async (e) => { evenements.push(e); return e.type === 'requete' ? { arreter: true } : null },
    secret: 's',
    clesPresentes: new Set(),
    env: { OPENAI_API_KEY: 'cle' },
    appeler: async () => reponseModele({ message: 'ok', video: { scenes: [{ titre: 'A', voix: 'Bonjour' }] } }),
  })
  assert.equal(r.arrete, true)
  assert.ok(evenements.some((e) => e.type === 'arretee'))
  assert.ok(!commandes.some((c) => c.includes('node rendre.mjs')))
  assert.ok(!evenements.some((e) => e.type === 'voix'), 'aucune voix payée après l’arrêt')
})

test('voix off : les voix gratuites de Gemini d’abord, OpenAI seulement en secours, une voix à sa limite mise de côté', async () => {
  const appels = []
  const wav = Buffer.from('RIFF')
  const appeler = async (url, options) => {
    const corps = JSON.parse(options.body)
    appels.push(`${url.includes('googleapis') ? 'gemini' : 'openai'}:${corps.model}`)
    if (corps.model === 'gemini-3.8-flash-tts') return new Response('{"error":"quota"}', { status: 429 })
    if (url.includes('googleapis')) return new Response(JSON.stringify({ steps: [{ content: [{ type: 'audio', data: wav.toString('base64') }] }] }), { status: 200 })
    return new Response(wav, { status: 200 })
  }
  const gratuites = [{ modele: 'gemini-3.8-flash-tts', voix: 'Kore' }, { modele: 'gemini-3.8-flash-lite-tts', voix: 'Kore' }]
  const deCote = new Set()
  const r = await direVoixOff('Bonjour à tous.', { gratuites, payante: { permise: true, modele: 'gpt-4o-mini-tts', voix: 'marin' } }, { appeler, env: { GEMINI_API_KEY: 'g', OPENAI_API_KEY: 'o' }, misesDeCote: deCote })
  assert.deepEqual([r.fournisseur, r.modele], ['gemini', 'gemini-3.8-flash-lite-tts'])
  assert.deepEqual(appels, ['gemini:gemini-3.8-flash-tts', 'gemini:gemini-3.8-flash-lite-tts'])
  assert.ok(deCote.has('voix:gemini-3.8-flash-tts'), 'la voix à sa limite est mise de côté pour la suite de la vidéo')
  const sansGemini = await direVoixOff('Bonjour.', { gratuites, payante: { permise: true, modele: 'gpt-4o-mini-tts', voix: 'marin' } }, { appeler, env: { OPENAI_API_KEY: 'o' } })
  assert.equal(sansGemini.fournisseur, 'openai', 'sans clé Gemini : la voix d’OpenAI')
  await assert.rejects(direVoixOff('Bonjour.', { gratuites: [], payante: { permise: false } }, { appeler, env: {} }), /aucune voix/)
})

test('images des scènes : générateur payant des affiches pour les formules payantes, gratuit puis banques libres pour la Gratuite', async () => {
  const { ordreImages } = await import('../src/videos/index.js')
  assert.deepEqual(ordreImages({ generateur: 'openai/gpt-image-2.6-flare' }), ['openai/gpt-image-2.6-flare', 'libre', 'cloudflare/flux-1-schnell'])
  assert.deepEqual(ordreImages({ generateur: 'cloudflare/flux-1-schnell' }), ['cloudflare/flux-1-schnell', 'libre'])
  assert.deepEqual(ordreImages({ generateur: 'openai/inconnu' }), ['cloudflare/flux-1-schnell', 'libre'], 'un générateur inconnu n’est jamais appelé')
  // Le fond animé est le dernier recours : une scène qui décrit une image en reçoit une.
  const s = scenarioPropre({ scenes: [{ titre: 'A', media: { source: 'aucun', description: 'African market', recherche: 'african market' } }, { titre: 'B', media: { source: 'aucun' } }] })
  assert.deepEqual(s.scenes.map((x) => x.media.source), ['creee', 'aucun'])
})
