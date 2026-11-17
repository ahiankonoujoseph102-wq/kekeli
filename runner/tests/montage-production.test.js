// Étape 6 du chantier des vidéos motion design : le montage branché dans le vrai parcours, les contrôles finaux.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { argumentShell, chiffresAffiches, chiffresManquants, fichiersBibliotheque, formatDeLaDemande, genreDeLaVoix, imageVide, lireNombres, moteurDeLaVideo, motsSansVoix, POIDS_MAX, styleDeLaDemande, suitesDeLaVideo } from '../src/videos/montage/production.js'
import { calerSurLaVoix } from '../src/videos/montage/temps.js'
import { planPropre } from '../src/videos/montage/schema.js'

test('le nouveau montage pour une publicité ; l’ancien moteur pour les vidéos du client, un document ou une ancienne version', () => {
  assert.equal(moteurDeLaVideo({ demande: 'Une pub pour mon pressing' }), 'montage')
  assert.equal(moteurDeLaVideo({ joints: [{ type: 'image' }, { type: 'audio' }] }), 'montage')
  assert.equal(moteurDeLaVideo({ joints: [{ type: 'video' }] }), 'ancien')
  assert.equal(moteurDeLaVideo({ fichier: 12 }), 'ancien')
  assert.equal(moteurDeLaVideo({ precedente: { scenario: {} } }), 'ancien')
  assert.equal(moteurDeLaVideo({ precedente: { plan_montage: { plans: [] } } }), 'montage')
})

test('le style, le format et la voix : choisis par le client, sinon devinés, sinon gardés de la version précédente', () => {
  assert.equal(styleDeLaDemande({ style: 'temoignage', demande: 'boutique' }), 'temoignage')
  assert.equal(styleDeLaDemande({ demande: 'Version plus sobre' }), 'sobre')
  assert.equal(styleDeLaDemande({ demande: 'Change le prix', precedente: { style: 'educatif' } }), 'educatif')
  assert.equal(styleDeLaDemande({ demande: 'Ma cliente Akossiwa m’a dit : « super »' }), 'temoignage')
  assert.equal(styleDeLaDemande({ demande: 'une pub pour ma boutique de pagnes à 12 000 FCFA' }), 'produit')
  assert.equal(styleDeLaDemande({ demande: 'une pub qui donne envie' }), 'dynamique')
  assert.equal(formatDeLaDemande({ format: 'auto', demande: 'Format carré pour Facebook' }), 'carre')
  assert.equal(formatDeLaDemande({ format: 'auto', demande: 'Plus court', precedente: { format: 'paysage' } }), 'paysage')
  assert.equal(formatDeLaDemande({ format: 'auto', demande: 'une pub' }), 'vertical')
  assert.equal(genreDeLaVoix({ voix_genre: 'homme' }), 'homme')
  assert.equal(genreDeLaVoix({}), 'femme')
})

test('les chiffres affichés sont relus à l’image : prix, numéro, compteur ; un nombre absent est signalé', () => {
  const { plan } = planPropre({ marque: { nom: 'Clean Express' }, plans: [{ type: 'accroche', mot: 'Ton', texte: 'Linge propre' }, { type: 'produit', mot: 'La chemise', texte: 'Chemise', prix: '500 FCFA' }, { type: 'numero', mot: 'Écris', texte: 'Écris-nous', groupes: [{ texte: '91', mot: '91' }, { texte: '11', mot: '11' }] }, { type: 'carte', mot: 'Clean' }] })
  const mots = 'Ton linge propre. La chemise à 500 francs. Écris au 91 11. Clean Express.'.split(' ').map((mot, i) => ({ mot, debut: i * 0.4, fin: i * 0.4 + 0.3 }))
  const { plan: cale } = calerSurLaVoix(plan, mots)
  const attendus = chiffresAffiches(cale)
  assert.deepEqual(attendus.map((a) => a.nombres), [['500'], ['91', '11']])
  assert.deepEqual(chiffresManquants(['500'], 'CHEMISE 500 FCFA'), [])
  assert.deepEqual(chiffresManquants(['2500', '91'], '2 500 FCFA'), ['91'])
})

test('les autres contrôles finaux : image vide, poids, mots réguliers sans voix, suites proposées', () => {
  assert.equal(imageVide(0.004), true)
  assert.equal(imageVide(0.2), false)
  assert.equal(POIDS_MAX, 30 * 1024 * 1024)
  const mots = motsSansVoix('Un deux trois quatre cinq')
  assert.equal(mots.length, 5)
  assert.equal(mots[4].debut, 1.6)
  assert.match(suitesDeLaVideo('dynamique'), /^SUITES : .*Version plus sobre/)
  assert.match(suitesDeLaVideo('sobre'), /Version plus dynamique/)
  assert.equal(argumentShell("aevalsrc='0.6*sin(t)'"), "'aevalsrc='\\''0.6*sin(t)'\\'''")
})

test('la bibliothèque de montage entière part dans l’atelier vidéo (styles, éléments, sous-titres)', async () => {
  const noms = (await fichiersBibliotheque()).map((f) => f.nom).sort()
  assert.deepEqual(noms, ['elements.ts', 'montage.ts', 'outils.ts', 'sous-titres.ts', 'style.ts'])
})

test('la lecture des nombres passe par la vision gratuite ; un refus pour limite essaie le modèle suivant', async () => {
  const appels = []
  const appeler = async (url) => {
    appels.push(url)
    if (appels.length === 1) return new Response('{}', { status: 429 })
    return new Response(JSON.stringify({ candidates: [{ content: { parts: [{ text: '500 91 11 11 11' }] } }] }), { status: 200 })
  }
  const misesDeCote = new Set()
  const lu = await lireNombres(Buffer.from([0xff, 0xd8, 0xff]), { appeler, env: { GEMINI_API_KEY: 'essai' }, misesDeCote })
  assert.equal(lu.texte, '500 91 11 11 11')
  assert.match(appels[1], /gemini-3\.1-flash-lite/)
  assert.ok(misesDeCote.has('vision:gemini-3.5-flash-lite'))
  assert.equal(await lireNombres(Buffer.from([1]), { appeler, env: {} }), null)
})

test('le prix d’un produit tombe sur le mot dit, même quand le modèle n’a pas donné ce mot', async () => {
  const { motsDesPrix } = await import('../src/videos/montage/plan-modele.js')
  const plan = { texte_voix: 'La chemise est à 500 francs, le costume à 2 500 francs.', plans: [{ type: 'produit', prix: '500 FCFA' }, { type: 'produit', prix: '2 500 FCFA' }, { type: 'produit', prix: '1 000 FCFA', mot_prix: 'mille' }] }
  motsDesPrix(plan)
  assert.deepEqual(plan.plans.map((p) => p.mot_prix), ['500', '2 500', 'mille'])
})

test('voix du client : 90 % des mots dans l’ordre suffisent ; un avis coupé en trois citations devient une seule', async () => {
  const { defautsDuPlan, fidelite } = await import('../src/videos/montage/plan-modele.js')
  assert.equal(fidelite('Bonjour je suis Afi', 'Bonjour je suis Afi'), 1)
  assert.ok(fidelite('Bonjour je suis Afi de Bè', 'Bonjour, je suis Afi de Be') >= 0.9)
  assert.ok(fidelite('Venez vite', 'Bonjour je suis Afi de Bè') < 0.5)
  const transcription = 'Belle Afi à Bè. Mes tresses tiennent un mois. On me reçoit à l’heure. Je ne vais plus ailleurs. Belle Afi.'
  const brut = { marque: { nom: 'Belle Afi' }, plans: [
    { type: 'accroche', voix: 'Belle Afi à Bè.', texte: 'Belle Afi' },
    { type: 'citation', voix: 'Mes tresses tiennent un mois.', texte: 'Mes tresses tiennent un mois.', auteur: 'Akossiwa' },
    { type: 'citation', voix: 'On me reçoit à l’heure.', texte: 'On me reçoit à l’heure.', auteur: 'Akossiwa' },
    { type: 'citation', voix: 'Je ne vais plus ailleurs.', texte: 'Je ne vais plus ailleurs.', auteur: 'Akossiwa' },
    { type: 'carte', voix: 'Belle Afi.' },
  ] }
  const { plan, defauts } = defautsDuPlan(brut, { sources: [`Ma cliente Akossiwa : ${transcription}`], transcription })
  assert.deepEqual(defauts, [])
  assert.deepEqual(plan.plans.map((p) => p.type), ['accroche', 'citation', 'carte'])
  assert.equal(plan.plans[1].texte, 'Mes tresses tiennent un mois. On me reçoit à l’heure. Je ne vais plus ailleurs.')
})

test('le vocabulaire de la transcription : des noms et des mots en liste, jamais une phrase de la demande', async () => {
  const { vocabulaireDe } = await import('../src/videos/montage/production.js')
  const v = vocabulaireDe('Une vidéo témoignage pour mon salon Belle Afi à Bè avec ma propre voix. Ma cliente Akossiwa, au Grand Marché, a dit : « Mes tresses tiennent un mois. »')
  assert.match(v, /Belle Afi/)
  assert.match(v, /Akossiwa/)
  assert.match(v, /Grand Marché/)
  assert.match(v, /tresses/)
  assert.doesNotMatch(v, /propre|voix|vidéo|\bUne\b|\bMes\b/)
})

test('un mot répété plus loin dans la voix ne garde jamais un plan à l’écran après le début du suivant', async () => {
  const { plan } = planPropre({ marque: { nom: 'Clean' }, plans: [{ type: 'accroche', mot: 'Du', texte: 'Linge' }, { type: 'produit', mot: 'La chemise', texte: 'Chemise', prix: '500 FCFA', mot_prix: '500' }, { type: 'pieces', mot: 'Seulement', texte: '500 FCFA' }, { type: 'carte', mot: 'Clean' }] })
  const mots = 'Du linge propre. La chemise lavée et repassée. Seulement 500 francs. Clean.'.split(' ').map((mot, i) => ({ mot, debut: i * 0.4, fin: i * 0.4 + 0.3 }))
  const { plan: cale } = calerSurLaVoix(plan, mots)
  assert.equal(cale.plans[1].temps.prix, undefined, 'le « 500 » du plan suivant n’est pas celui du produit')
  assert.ok(cale.plans[1].debut + cale.plans[1].duree <= cale.plans[2].debut + 1e-9)
})
