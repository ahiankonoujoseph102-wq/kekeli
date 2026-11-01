import { test } from 'node:test'
import assert from 'node:assert/strict'
import { entetesSignes, signer, verifier } from '../src/signature.js'
import { FAUSSE_CLE, entreesUtilisables, fournisseursAvecCle, reglesReseau } from '../src/fournisseurs.js'
import { MisesDeCote, attenteDemandeeMs, estUneLimite } from '../src/limites.js'
import { traduireOutil, tokensDeRequete } from '../src/etapes.js'
import { executerTache, TEXTE_REPRISE } from '../src/agent.js'
import { configurationOpencode, motDePasseProjet } from '../src/ordinateurs.js'
import { creerServeur } from '../src/server.js'
import { essai, fauxOpencode, finRequete, libre, occupe, outil } from './outils.js'

const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789'
const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
const MISTRAL = { fournisseur: 'mistral', modele: 'devstral-latest' }

test('la signature est acceptée puis refusée si le corps, le secret ou l’heure changent', () => {
  const maintenant = Date.UTC(2026, 10, 1, 12)
  const entetes = entetesSignes(SECRET, '{"a":1}', maintenant)
  const h = entetes['X-Kekeli-Horodatage']
  const s = entetes['X-Kekeli-Signature']
  assert.equal(s, signer(SECRET, h, '{"a":1}'))
  assert.ok(verifier(SECRET, h, s, '{"a":1}', maintenant))
  assert.ok(!verifier(SECRET, h, s, '{"a":2}', maintenant))
  assert.ok(!verifier('autre-secret', h, s, '{"a":1}', maintenant))
  assert.ok(!verifier(SECRET, h, s, '{"a":1}', maintenant + 10 * 60 * 1000))
  assert.ok(!verifier(SECRET, undefined, s, '{"a":1}', maintenant))
})

test('seuls les fournisseurs connus et dont la clé est présente sont gardés, dans l’ordre', () => {
  const cles = fournisseursAvecCle({ GEMINI_API_KEY: 'x', MISTRAL_API_KEY: 'y' })
  assert.deepEqual([...cles].sort(), ['gemini', 'mistral'])
  const liste = [{ fournisseur: 'openrouter', modele: 'm' }, MISTRAL, { fournisseur: 'inconnu', modele: 'm' }, GEMINI, GEMINI, { fournisseur: 'gemini' }]
  assert.deepEqual(entreesUtilisables(liste, cles), [MISTRAL, GEMINI])
})

test('la clé n’est jamais placée dans l’ordinateur virtuel : seulement une référence au secret E2B', () => {
  const cles = new Set(['gemini', 'openrouter'])
  const regles = reglesReseau(cles, (nom) => `{{secret:${nom}}}`)
  assert.deepEqual(regles['generativelanguage.googleapis.com'][0].transform.headers, { 'x-goog-api-key': '{{secret:kekeli_gemini}}' })
  assert.deepEqual(regles['openrouter.ai'][0].transform.headers, { Authorization: 'Bearer {{secret:kekeli_openrouter}}' })
  assert.equal(regles['api.mistral.ai'], undefined)
  const config = JSON.stringify(configurationOpencode(cles))
  assert.ok(config.includes(FAUSSE_CLE))
  assert.equal(motDePasseProjet(SECRET, 'p1'), motDePasseProjet(SECRET, 'p1'))
  assert.notEqual(motDePasseProjet(SECRET, 'p1'), motDePasseProjet(SECRET, 'p2'))
})

test('reconnaît une limite atteinte et lit la durée demandée', () => {
  assert.ok(estUneLimite('You exceeded your current quota'))
  assert.ok(estUneLimite('Rate limit exceeded: free-models-per-day'))
  assert.ok(!estUneLimite('Internal server error'))
  assert.equal(attenteDemandeeMs('Please retry in 13347s.'), 13347000)
  assert.equal(attenteDemandeeMs('Please retry in 41.5s.'), 41500)
  assert.equal(attenteDemandeeMs('rien'), null)
  assert.equal(attenteDemandeeMs('Please retry in 3h2m10.5s.'), (3 * 3600 + 2 * 60 + 10.5) * 1000)
  assert.equal(attenteDemandeeMs('retry after 500ms'), 500)
})

test('met de côté jusqu’à la remise à zéro, attend pour une limite par minute, puis double la pause', () => {
  let maintenant = 0
  const m = new MisesDeCote(() => maintenant)
  assert.equal(m.analyserEssai('g', 'quota exceeded. Please retry in 30s.', 1), null)
  const jusqua = m.analyserEssai('g', 'quota exceeded. Please retry in 13347s.', 1)
  assert.equal(jusqua.getTime(), 13347000)
  assert.ok(!m.estDisponible('g'))
  maintenant = 13347001
  assert.ok(m.estDisponible('g'))
  assert.equal(m.analyserEssai('g', '429 Too Many Requests', 3).getTime(), maintenant + 4 * 60 * 1000)
  m.reussite('g')
  assert.equal(m.analyserEssai('g', '429 Too Many Requests', 3).getTime(), maintenant + 2 * 60 * 1000)
  assert.equal(m.analyserEssai('h', 'Internal server error', 2), null)
  assert.equal(m.analyserEssai('h', 'Internal server error', 4).getTime(), maintenant + 2 * 60 * 1000)
  assert.equal(m.prochaineDisponibilite(['g', 'h']).getTime(), maintenant + 2 * 60 * 1000)

  // Quota du jour sans durée : jusqu'à la remise à zéro envoyée par le site.
  const remise = new Date(maintenant + 5 * 3600 * 1000).toISOString()
  assert.equal(m.analyserEssai('i', 'Generate content requests per day limit exceeded on Free Tier.', 1, { remiseAZero: remise }).toISOString(), remise)
  // Durée donnée par OpenCode (champ « next ») quand le message n'en donne pas.
  assert.equal(m.analyserEssai('j', 'Rate limit exceeded', 1, { prochainEssai: maintenant + 3600 * 1000 }).getTime(), maintenant + 3600 * 1000)
})

test('traduit les actions de l’agent en étapes et lit les tokens', () => {
  assert.deepEqual(traduireOutil(outil('write', { filePath: '/home/user/projet/bonjour.txt' }, 'a').properties.part), { genre: 'fichier_ecrit', cible: 'bonjour.txt' })
  assert.deepEqual(traduireOutil(outil('bash', { command: 'cat /home/user/projet/bonjour.txt' }, 'b').properties.part), { genre: 'commande', cible: 'cat bonjour.txt' })
  assert.equal(traduireOutil(outil('todowrite', {}, 'c').properties.part), null)
  assert.equal(traduireOutil({ type: 'tool', tool: 'write', state: { status: 'running' } }), null)
  assert.deepEqual(traduireOutil({ type: 'tool', tool: 'bash', state: { status: 'error', input: {} } }), { genre: 'echec', cible: 'bash' })
  assert.deepEqual(tokensDeRequete(finRequete('x').properties.part), { tokens_entree: 1000, tokens_sortie: 50, tokens_reflexion: 5, tokens_cache: 10 })
})

function preparer(scenarios, fournisseurs = [GEMINI, MISTRAL]) {
  const faux = fauxOpencode(scenarios)
  const evenements = []
  const misesDeCote = new MisesDeCote()
  const executer = () =>
    executerTache(
      { tache: 't1', projet: 'p1', message: 'Crée un fichier bonjour.txt', fournisseurs },
      { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote, clesPresentes: new Set(['gemini', 'mistral']), journal: { error() {}, warn() {} } },
    )
  return { faux, evenements, misesDeCote, executer }
}

test('une tâche normale : étapes, requêtes comptées et réponse finale', async () => {
  const { faux, evenements, executer } = preparer({
    'gemini-3.5-flash-lite': [occupe, outil('write', { filePath: '/home/user/projet/bonjour.txt' }, 'p1'), finRequete('r1'), outil('write', { filePath: '/home/user/projet/bonjour.txt' }, 'p1'), finRequete('r1'), finRequete('r2', 2000, 10), libre],
  })
  await executer()
  assert.deepEqual(evenements.map((e) => e.type), ['demarrage', 'fournisseur', 'etape', 'requete', 'requete', 'terminee'])
  assert.equal(evenements[3].fournisseur, 'gemini')
  assert.equal(evenements[4].tokens_entree, 2000)
  assert.equal(evenements.at(-1).reponse, 'Le fichier bonjour.txt est créé.')
  assert.equal(faux.appels.prompts[0].fournisseur, 'google')
})

test('limite du jour atteinte : bascule automatique vers le fournisseur suivant, même tâche', async () => {
  const { faux, evenements, misesDeCote, executer } = preparer({
    'gemini-3.5-flash-lite': [occupe, finRequete('r1'), essai(1, 'You exceeded your current quota. Please retry in 13347s.')],
    'devstral-latest': [occupe, finRequete('r2'), libre],
  })
  await executer()
  assert.deepEqual(evenements.map((e) => e.type), ['demarrage', 'fournisseur', 'requete', 'refus', 'mis_de_cote', 'fournisseur', 'requete', 'terminee'])
  assert.equal(evenements[5].fournisseur, 'mistral')
  assert.equal(evenements[6].fournisseur, 'mistral')
  assert.equal(faux.appels.abandons, 1)
  assert.equal(faux.appels.prompts[1].texte, TEXTE_REPRISE)
  assert.ok(!misesDeCote.estDisponible('gemini/gemini-3.5-flash-lite'))
})

test('tous les fournisseurs épuisés : la demande est mise en attente avec une heure de reprise', async () => {
  const { evenements, executer } = preparer({
    'gemini-3.5-flash-lite': [occupe, essai(1, 'quota. Please retry in 3600s.')],
    'devstral-latest': [occupe, essai(1, 'quota. Please retry in 600s.')],
  })
  const avant = Date.now()
  await executer()
  const attente = evenements.at(-1)
  assert.equal(attente.type, 'en_attente')
  const reprise = new Date(attente.reprise_vers).getTime()
  assert.ok(reprise >= avant + 600000 && reprise < avant + 3600000)

  // Une nouvelle tâche pendant que tout est épuisé est mise en attente sans lancer l'agent.
  evenements.length = 0
  await executer()
  assert.deepEqual(evenements.map((e) => e.type), ['en_attente'])
})

test('aucun fournisseur utilisable : erreur claire, sans lancer l’agent', async () => {
  const { evenements, executer } = preparer({}, [{ fournisseur: 'openrouter', modele: 'x' }])
  await executer()
  assert.deepEqual(evenements.map((e) => [e.type, e.raison]), [['erreur', 'aucun_fournisseur']])
})

test('une erreur de l’agent est signalée', async () => {
  const { evenements, executer } = preparer({
    'gemini-3.5-flash-lite': [occupe, { type: 'session.error', properties: { sessionID: 'ses_test', error: { name: 'APIError', data: { message: 'Modèle introuvable' } } } }],
  })
  await executer()
  assert.deepEqual(evenements.at(-1), { type: 'erreur', raison: 'agent', detail: 'Modèle introuvable' })
})

test('le serveur refuse les demandes non signées et accepte les demandes signées', async () => {
  const recues = []
  const serveur = creerServeur({
    secret: SECRET,
    clesPresentes: new Set(['gemini']),
    ordinateurs: {},
    executer: async (demande) => recues.push(demande),
    creerEnvoi: () => async () => {},
    journal: { error() {} },
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const base = `http://127.0.0.1:${serveur.address().port}`
  try {
    assert.equal((await fetch(base + '/sante')).status, 200)
    assert.equal((await fetch(base + '/etat')).status, 401)
    const etat = await fetch(base + '/etat', { headers: entetesSignes(SECRET, '') })
    assert.deepEqual(await etat.json(), { cles_presentes: ['gemini'], mis_de_cote: {} })

    const demande = JSON.stringify({ tache: 't', projet: '01JABC', message: 'bonjour', rappel: 'http://site/runner/evenements', fournisseurs: [GEMINI] })
    assert.equal((await fetch(base + '/taches', { method: 'POST', body: demande })).status, 401)
    const fausse = { 'X-Kekeli-Horodatage': String(Math.floor(Date.now() / 1000)), 'X-Kekeli-Signature': 'sha256=00' }
    assert.equal((await fetch(base + '/taches', { method: 'POST', body: demande, headers: fausse })).status, 401)
    const bonne = await fetch(base + '/taches', { method: 'POST', body: demande, headers: entetesSignes(SECRET, demande) })
    assert.equal(bonne.status, 202)
    const incomplete = JSON.stringify({ tache: 't' })
    assert.equal((await fetch(base + '/taches', { method: 'POST', body: incomplete, headers: entetesSignes(SECRET, incomplete) })).status, 422)
    await new Promise((r) => setTimeout(r, 20))
    assert.equal(recues.length, 1)
    assert.equal(recues[0].projet, '01JABC')
  } finally {
    serveur.close()
  }
})
