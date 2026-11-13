import { test } from 'node:test'
import assert from 'node:assert/strict'
import { signer, traiter, lireDonnees } from '../point-entree/coeur.js'
import { lireEvenement } from '../src/evenement.js'
import { garderVus, lireVus, nouveaux } from '../src/etat.js'

const ENV = { ENTREE_SECRET: 'secret-de-test-123', GITHUB_DECLENCHEUR: 'jeton-github-de-test', DEPOT: 'moi/mon-automatisation', FORMULAIRE_ORIGINES: 'https://mon-site.pages.dev' }
const MAINTENANT = Date.UTC(2026, 10, 13, 10, 0, 0)

function memoire() {
  const m = new Map()
  return { m, get: async (k) => m.get(k) ?? null, put: async (k, v) => { m.set(k, v) } }
}
function github() {
  const appels = []
  return { appels, appeler: async (url, options) => { appels.push({ url, corps: JSON.parse(options.body), entetes: options.headers }); return new Response(null, { status: 204 }) } }
}
const signe = async (corps, t = MAINTENANT / 1000) => `t=${t},v1=${await signer(`${t}.${corps}`, ENV.ENTREE_SECRET)}`

test('un appel signé lance l’automatisation sur GitHub, une seule fois', async () => {
  const mem = memoire()
  const gh = github()
  const corps = JSON.stringify({ nom: 'Afi', message: 'Bonjour' })
  const requete = async () => new Request('https://point.workers.dev/?type=formulaire', { method: 'POST', body: corps, headers: { 'Content-Type': 'application/json', 'X-Kekeli-Signature': await signe(corps), 'X-Evenement-Id': 'commande-42' } })
  const r = await traiter(await requete(), ENV, { memoire: mem, appeler: gh.appeler, maintenant: MAINTENANT })
  assert.equal(r.status, 202)
  assert.equal(gh.appels[0].url, 'https://api.github.com/repos/moi/mon-automatisation/dispatches')
  assert.equal(gh.appels[0].entetes.Authorization, 'Bearer jeton-github-de-test')
  assert.deepEqual(gh.appels[0].corps.client_payload.donnees, { nom: 'Afi', message: 'Bonjour' })
  assert.equal(gh.appels[0].corps.client_payload.type, 'formulaire')
  // Le même événement renvoyé : rien n'est relancé.
  const deuxieme = await traiter(await requete(), ENV, { memoire: mem, appeler: gh.appeler, maintenant: MAINTENANT })
  assert.deepEqual(await deuxieme.json(), { ok: true, deja: true })
  assert.equal(gh.appels.length, 1)
})

test('une signature fausse ou trop ancienne est refusée', async () => {
  const gh = github()
  const corps = '{"a":1}'
  const fausse = new Request('https://p.dev/', { method: 'POST', body: corps, headers: { 'Content-Type': 'application/json', 'X-Kekeli-Signature': `t=${MAINTENANT / 1000},v1=00ff` } })
  assert.equal((await traiter(fausse, ENV, { memoire: memoire(), appeler: gh.appeler, maintenant: MAINTENANT })).status, 401)
  const ancienne = new Request('https://p.dev/', { method: 'POST', body: corps, headers: { 'Content-Type': 'application/json', 'X-Kekeli-Signature': await signe(corps, MAINTENANT / 1000 - 600) } })
  assert.equal((await traiter(ancienne, ENV, { memoire: memoire(), appeler: gh.appeler, maintenant: MAINTENANT })).status, 401)
  assert.equal(gh.appels.length, 0)
})

test('sans signature, sans secret et hors des sites permis : refusé', async () => {
  const gh = github()
  const r = await traiter(new Request('https://p.dev/', { method: 'POST', body: 'nom=Afi', headers: { 'Content-Type': 'application/x-www-form-urlencoded', Origin: 'https://autre-site.com' } }), ENV, { memoire: memoire(), appeler: gh.appeler, maintenant: MAINTENANT })
  assert.equal(r.status, 401)
  const mauvais = await traiter(new Request('https://p.dev/?jeton=faux', { method: 'POST', body: '{}', headers: { 'Content-Type': 'application/json' } }), ENV, { memoire: memoire(), appeler: gh.appeler, maintenant: MAINTENANT })
  assert.equal(mauvais.status, 401)
  const bon = await traiter(new Request(`https://p.dev/?jeton=${ENV.ENTREE_SECRET}`, { method: 'POST', body: '{"commande":"A12"}', headers: { 'Content-Type': 'application/json' } }), ENV, { memoire: memoire(), appeler: gh.appeler, maintenant: MAINTENANT })
  assert.equal(bon.status, 202)
  assert.equal(gh.appels.length, 1)
})

test('formulaire public : seulement depuis le site permis, champ piège, 5 envois par 10 minutes', async () => {
  const mem = memoire()
  const gh = github()
  const envoyer = (corps, ip = '1.2.3.4') => traiter(new Request('https://p.dev/', { method: 'POST', body: corps, headers: { 'Content-Type': 'application/x-www-form-urlencoded', Origin: 'https://mon-site.pages.dev', 'CF-Connecting-IP': ip } }), ENV, { memoire: mem, appeler: gh.appeler, maintenant: MAINTENANT })
  const r = await envoyer('nom=Afi&message=Bonjour&site_web=')
  assert.equal(r.status, 202)
  assert.equal(r.headers.get('Access-Control-Allow-Origin'), 'https://mon-site.pages.dev')
  assert.deepEqual(gh.appels[0].corps.client_payload.donnees, { nom: 'Afi', message: 'Bonjour' })
  // Un robot remplit le champ piège : réponse polie, rien n'est lancé.
  assert.equal((await envoyer('nom=Robot&site_web=http://spam')).status, 202)
  assert.equal(gh.appels.length, 1)
  for (let i = 2; i <= 5; i++) await envoyer(`nom=Personne${i}`)
  assert.equal((await envoyer('nom=Sixieme')).status, 429)
  assert.equal((await envoyer('nom=Autre', '5.6.7.8')).status, 202, 'une autre adresse IP n’est pas bloquée')
})

test('GitHub indisponible : erreur claire, et l’événement pourra être renvoyé', async () => {
  const mem = memoire()
  const corps = '{"commande":"A13"}'
  const requete = async () => new Request('https://p.dev/', { method: 'POST', body: corps, headers: { 'Content-Type': 'application/json', 'X-Kekeli-Signature': await signe(corps) } })
  const panne = await traiter(await requete(), ENV, { memoire: mem, appeler: async () => new Response('', { status: 500 }), maintenant: MAINTENANT })
  assert.equal(panne.status, 502)
  const gh = github()
  assert.equal((await traiter(await requete(), ENV, { memoire: mem, appeler: gh.appeler, maintenant: MAINTENANT })).status, 202)
})

test('pas encore réglé, mauvaise méthode, trop gros, données illisibles', async () => {
  const opts = { memoire: memoire(), appeler: github().appeler, maintenant: MAINTENANT }
  assert.equal((await traiter(new Request('https://p.dev/', { method: 'POST', body: '{}' }), { ...ENV, GITHUB_DECLENCHEUR: '' }, opts)).status, 503)
  assert.equal((await traiter(new Request('https://p.dev/'), ENV, opts)).status, 405)
  const gros = 'x'.repeat(40 * 1024)
  assert.equal((await traiter(new Request(`https://p.dev/?jeton=${ENV.ENTREE_SECRET}`, { method: 'POST', body: gros }), ENV, opts)).status, 413)
  assert.equal((await traiter(new Request(`https://p.dev/?jeton=${ENV.ENTREE_SECRET}`, { method: 'POST', body: '[1,', headers: { 'Content-Type': 'application/json' } }), ENV, opts)).status, 400)
  assert.deepEqual(Object.keys(lireDonnees('nom=Afi&<b>=x', '')), ['nom', 'b'])
})

test('l’automatisation lit l’événement de GitHub ou l’événement d’essai de Kekeli', () => {
  const fichiers = { 'evenement-essai.json': '{"type":"formulaire","donnees":{"nom":"Essai"}}', '/tmp/evt.json': '{"client_payload":{"id":"x1","type":"commande","donnees":{"total":"12 000 FCFA"}}}' }
  const lire = (f) => fichiers[f]
  assert.deepEqual(lireEvenement({ env: { KEKELI_EVENEMENT_ESSAI: '1' }, lire }).donnees, { nom: 'Essai' })
  const reel = lireEvenement({ env: { GITHUB_EVENT_NAME: 'repository_dispatch', GITHUB_EVENT_PATH: '/tmp/evt.json' }, lire })
  assert.deepEqual([reel.id, reel.type, reel.essai, reel.donnees.total], ['x1', 'commande', false, '12 000 FCFA'])
  assert.equal(lireEvenement({ env: { GITHUB_EVENT_NAME: 'schedule' }, lire }), null)
})

test('vérification régulière : jamais deux fois le même élément', () => {
  let ecrit = ''
  const vus = lireVus({ lire: () => '["m1","m2"]' })
  const neufs = nouveaux([{ id: 'm1' }, { id: 'm3' }], vus)
  assert.deepEqual(neufs, [{ id: 'm3' }])
  neufs.forEach((e) => vus.add(e.id))
  garderVus(vus, { ecrire: (f, t) => { ecrit = t } })
  assert.deepEqual(JSON.parse(ecrit), ['m1', 'm2', 'm3'])
})
