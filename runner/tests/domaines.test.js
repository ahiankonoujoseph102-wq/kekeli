import { test } from 'node:test'
import assert from 'node:assert/strict'
import { DOMAINE, gererDomaine } from '../src/domaines.js'
import { creerServeur } from '../src/server.js'
import { entetesSignes } from '../src/signature.js'

const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789'
const silencieux = { log() {}, warn() {}, error() {} }

/** Faux Cloudflare : garde les appels et répond dans l'ordre donné. */
function fauxCloudflare(reponses) {
  const appels = []
  const telecharger = async (adresse, options) => {
    appels.push({ adresse, methode: options.method, corps: options.body ? JSON.parse(options.body) : undefined, cle: options.headers.Authorization })
    const [statut, donnees] = reponses.shift()
    return new Response(JSON.stringify(donnees), { status: statut })
  }
  return { appels, telecharger }
}

test('seules des adresses de nom de domaine valides sont acceptées', () => {
  for (const bonne of ['www.monrestaurant.com', 'boutique.mon-site.tg', 'monsite.ci']) assert.ok(DOMAINE.test(bonne), bonne)
  for (const mauvaise of ['monsite', 'https://www.monsite.com', 'www.mon site.com', '-a.com', 'a..com', 'WWW.MONSITE.COM']) assert.ok(!DOMAINE.test(mauvaise), mauvaise)
})

test('ajouter le nom de domaine, vérifier, puis retirer', async () => {
  const cf = fauxCloudflare([
    [200, { success: true, result: { name: 'www.monsite.com', status: 'initializing' } }],
    [200, { success: true, result: { name: 'www.monsite.com', status: 'pending', verification_data: { error_message: 'CNAME record not set' } } }],
    [200, { success: true, result: { name: 'www.monsite.com', status: 'pending' } }],
    [200, { success: true, result: null }],
  ])
  const commun = { nom: 'le-palmier-abc123', domaine: 'www.monsite.com', compte: 'c'.repeat(32), jeton: 'jeton', telecharger: cf.telecharger }

  assert.deepEqual(await gererDomaine({ ...commun, action: 'ajouter' }), { domaine: 'www.monsite.com', statut: 'initializing', cible: 'le-palmier-abc123.pages.dev', explication: null })
  assert.equal(cf.appels[0].methode, 'POST')
  assert.match(cf.appels[0].adresse, /\/accounts\/c{32}\/pages\/projects\/le-palmier-abc123\/domains$/)
  assert.deepEqual(cf.appels[0].corps, { name: 'www.monsite.com' })
  assert.equal(cf.appels[0].cle, 'Bearer jeton')

  // En attente : Cloudflare est relancé pour vérifier de nouveau l'enregistrement CNAME.
  const verification = await gererDomaine({ ...commun, action: 'verifier' })
  assert.equal(verification.statut, 'pending')
  assert.deepEqual(cf.appels.slice(1, 3).map((a) => a.methode), ['GET', 'PATCH'])

  assert.equal((await gererDomaine({ ...commun, action: 'retirer' })).statut, 'retire')
  assert.equal(cf.appels[3].methode, 'DELETE')
})

test('un nom de domaine déjà rattaché renvoie simplement son état', async () => {
  const cf = fauxCloudflare([
    [409, { success: false, errors: [{ code: 8000018, message: 'You have already added this custom domain.' }] }],
    [200, { success: true, result: { name: 'www.monsite.com', status: 'active' } }],
  ])
  const resultat = await gererDomaine({ action: 'ajouter', nom: 'site-abc123', domaine: 'www.monsite.com', compte: 'c', jeton: 'j', telecharger: cf.telecharger })
  assert.equal(resultat.statut, 'active')
})

test('l’adresse /domaine est signée et vérifie la demande', async () => {
  const demandes = []
  const demarrer = async (clesPresentes) => {
    const serveur = creerServeur({
      secret: SECRET,
      clesPresentes,
      ordinateurs: {},
      domaineProjet: async (d) => {
        demandes.push(d)
        return { domaine: d.domaine, statut: 'pending', cible: `${d.nom}.pages.dev`, explication: null }
      },
      journal: silencieux,
    })
    await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
    return serveur
  }
  const poster = (serveur, donnees, signe = true) => {
    const corps = JSON.stringify(donnees)
    return fetch(`http://127.0.0.1:${serveur.address().port}/domaine`, { method: 'POST', body: corps, headers: signe ? entetesSignes(SECRET, corps) : {} })
  }
  const demande = { action: 'ajouter', nom: 'le-palmier-abc123', domaine: 'www.monsite.com' }

  const sansCloudflare = await demarrer(new Set(['gemini']))
  try {
    assert.equal((await poster(sansCloudflare, demande)).status, 503)
  } finally {
    sansCloudflare.close()
  }

  const serveur = await demarrer(new Set(['cloudflare']))
  try {
    assert.equal((await poster(serveur, demande, false)).status, 401)
    assert.equal((await poster(serveur, { ...demande, domaine: 'pas un domaine' })).status, 422)
    assert.equal((await poster(serveur, { ...demande, action: 'tout-effacer' })).status, 422)
    const reponse = await poster(serveur, demande)
    assert.equal(reponse.status, 200)
    assert.deepEqual(await reponse.json(), { ok: true, domaine: 'www.monsite.com', statut: 'pending', cible: 'le-palmier-abc123.pages.dev', explication: null })
    assert.deepEqual(demandes, [demande])
  } finally {
    serveur.close()
  }
})

test('le Runner s’appelle lui-même chaque minute seulement pendant qu’une demande travaille', async () => {
  const { garderEveille } = await import('../src/server.js')
  const appels = []
  let taches = 0
  const serveur = { tachesEnCours: () => taches }
  assert.equal(garderEveille(serveur, undefined), null)
  const minuteur = garderEveille(serveur, 'https://runner-kekeli.laravel.cloud/', { intervalleMs: 20, telecharger: async (a) => appels.push(a) })
  try {
    await new Promise((r) => setTimeout(r, 70))
    assert.equal(appels.length, 0)
    taches = 1
    await new Promise((r) => setTimeout(r, 70))
    assert.ok(appels.length >= 1)
    assert.equal(appels[0], 'https://runner-kekeli.laravel.cloud/sante')
  } finally {
    clearInterval(minuteur)
  }
})

test('le Runner ne se garde jamais éveillé plus longtemps que le maximum, même si une demande reste bloquée', async () => {
  const { garderEveille } = await import('../src/server.js')
  const appels = []
  let horloge = 0
  const minuteur = garderEveille({ tachesEnCours: () => 1 }, 'https://runner.laravel.cloud', {
    intervalleMs: 10,
    maximumMinutes: 1,
    maintenant: () => horloge,
    telecharger: async (a) => appels.push(a),
    journal: { warn() {} },
  })
  try {
    await new Promise((r) => setTimeout(r, 35))
    const avant = appels.length
    assert.ok(avant >= 1)
    horloge = 2 * 60000
    await new Promise((r) => setTimeout(r, 35))
    assert.equal(appels.length, avant)
  } finally {
    clearInterval(minuteur)
  }
})
