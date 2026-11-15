// FreeLLMAPI à côté du Runner (docs/plan.md, partie 26.38) : processus voisin sur 127.0.0.1, clés seulement dans le
// Runner, liste fermée de modèles, une demande à la fois par client, vrai fournisseur compté (« X-Routed-Via »).
import { test, afterEach } from 'node:test'
import assert from 'node:assert/strict'
import { EventEmitter } from 'node:events'
import {
  Freellmapi, MODELES_FREELLMAPI, PlacesFreellmapi, adresseSauvegarde, configurationDeclarative, environnementVoisin,
  empreinte, freellmapiDemande, jetonSauvegarde, modeleFreellmapiPermis, plateformesActives, routeServie, secretDerive, utiliserFreellmapi,
} from '../src/freellmapi.js'
import { premierRepondant } from '../src/repondant.js'
import { clesAgent, modeleAutorise, modeleOpencode } from '../src/fournisseurs.js'
import { passerOpenAI, jetonPasserelle } from '../src/passerelle.js'
import { rediger } from '../src/documents/redaction.js'

const ENV = { KEKELI_RUNNER_SECRET: 'secret-de-test', GROQ_API_KEY: 'cle-groq-de-test', OPENAI_API_KEY: 'cle-openai-de-test', GEMINI_API_KEY: 'cle-gemini-de-test', E2B_API_KEY: 'cle-e2b-de-test' }

/** Un FreeLLMAPI simulé, déjà prêt, avec sa clé locale. */
function voisinPret(places = new PlacesFreellmapi(6)) {
  return { pret: () => true, cle: () => 'cle-locale-de-test', adresse: 'http://127.0.0.1:3999', places, etat: () => ({ pret: true }) }
}

function reponseSse(evenements, entetes = {}) {
  const texte = evenements.map((e) => `data: ${JSON.stringify(e)}\n\n`).join('') + 'data: [DONE]\n\n'
  return new Response(texte, { status: 200, headers: { 'content-type': 'text/event-stream', ...entetes } })
}

afterEach(() => utiliserFreellmapi(null))

test('seuls les fournisseurs validés et pourvus d’une clé sont actifs ; FreeLLMAPI ne démarre pas sans eux', () => {
  assert.deepEqual(plateformesActives(ENV), ['groq'])
  assert.deepEqual(plateformesActives({ ...ENV, LONGCAT_API_KEY: 'x', ZAI_API_KEY: 'y' }), ['groq', 'longcat', 'zhipu'])
  // Un revendeur n'est branché que lorsque ses conditions ont été lues et qu'il est nommé.
  assert.deepEqual(plateformesActives({ ...ENV, NARA_API_KEY: 'z' }), ['groq'])
  assert.deepEqual(plateformesActives({ ...ENV, NARA_API_KEY: 'z', FREELLMAPI_PLATEFORMES_VALIDEES: 'nara' }), ['groq', 'nara'])
  assert.equal(freellmapiDemande(ENV), true)
  assert.equal(freellmapiDemande({ ...ENV, FREELLMAPI_ACTIF: 'non' }), false)
  assert.equal(freellmapiDemande({ KEKELI_RUNNER_SECRET: 's' }), false, 'aucune clé : rien à démarrer')
})

test('le processus voisin ne reçoit jamais les autres clés du Runner, écoute sur 127.0.0.1 et ne garde aucune réponse', () => {
  const CLE_SAUVEGARDE = 'ab'.repeat(32)
  const env = environnementVoisin({ ...ENV, KEKELI_SITE_ADRESSE: 'https://kekeli.laravel.cloud/', FREELLMAPI_CLE_SAUVEGARDE: CLE_SAUVEGARDE }, { port: 3101, dossierDonnees: '/tmp/essai' })
  assert.equal(env.HOST, '127.0.0.1')
  assert.equal(env.RESPONSE_CACHE, 'off')
  for (const nom of ['OPENAI_API_KEY', 'GEMINI_API_KEY', 'E2B_API_KEY', 'GROQ_API_KEY', 'KEKELI_RUNNER_SECRET']) assert.equal(env[nom], undefined, nom)
  assert.ok(!JSON.stringify(env).includes('cle-openai-de-test'))
  assert.ok(!JSON.stringify(env).includes('cle-gemini-de-test'))
  assert.ok(!JSON.stringify(env).includes('secret-de-test'))
  // La clé Groq ne passe que par la configuration déclarative, chiffrée ensuite par FreeLLMAPI.
  const config = JSON.parse(env.FREEAPI_CONFIG_JSON)
  assert.deepEqual(config.keys.map((k) => k.platform), ['groq'])
  assert.match(env.ENCRYPTION_KEY, /^[0-9a-f]{64}$/)
  assert.match(env.FREEAPI_DB_BACKUP_URL, /^https:\/\/kekeli\.laravel\.cloud\/runner\/freellmapi\/sauvegarde\?empreinte=[0-9a-f]{12}$/)
  // Un autre secret ou une autre clé de sauvegarde : une autre empreinte, donc jamais une base illisible.
  assert.notEqual(empreinte({ ...ENV, FREELLMAPI_CLE_SAUVEGARDE: CLE_SAUVEGARDE }), empreinte({ ...ENV, KEKELI_RUNNER_SECRET: 'autre', FREELLMAPI_CLE_SAUVEGARDE: CLE_SAUVEGARDE }))
  assert.equal(env.FREEAPI_DB_BACKUP_TOKEN, jetonSauvegarde('secret-de-test'))
  // Sauvegarde chiffrée avec une clé que seul le Runner connaît (jamais tirée du secret partagé avec le site).
  assert.equal(env.FREEAPI_DB_BACKUP_KEY, CLE_SAUVEGARDE)
  assert.notEqual(env.FREEAPI_DB_BACKUP_KEY, env.ENCRYPTION_KEY)
  assert.equal(adresseSauvegarde({}), null)
  assert.equal(adresseSauvegarde({ KEKELI_SITE_ADRESSE: 'https://kekeli.laravel.cloud' }), null, 'sans clé de sauvegarde, pas de sauvegarde')
  assert.equal(environnementVoisin({ ...ENV, KEKELI_SITE_ADRESSE: 'https://k.test', FREELLMAPI_CLE_SAUVEGARDE: CLE_SAUVEGARDE }, { dossierDonnees: '/tmp', sansSauvegarde: true }).FREEAPI_DB_BACKUP_URL, undefined)
  // Z.ai : l'adresse internationale.
  assert.equal(configurationDeclarative({ ...ENV, ZAI_API_KEY: 'y' }).keys.find((k) => k.platform === 'zhipu').baseUrl, 'https://api.z.ai/api/paas/v4')
  // Mêmes secrets tirés à chaque démarrage, différents selon l'usage.
  assert.equal(secretDerive('s', 'admin'), secretDerive('s', 'admin'))
  assert.notEqual(secretDerive('s', 'admin'), secretDerive('s', 'chiffrement'))
})

test('liste fermée : jamais « auto », jamais un modèle payant', () => {
  assert.ok(modeleFreellmapiPermis('gpt-oss-120b'))
  assert.ok(modeleFreellmapiPermis('glm-4.7-flash'))
  assert.equal(modeleFreellmapiPermis('auto'), false)
  assert.equal(modeleFreellmapiPermis('glm-5.3-flash'), false, 'payant chez Z.ai')
  assert.equal(modeleFreellmapiPermis('claude-opus-4-5'), false)
  assert.equal(modeleAutorise('freellmapi', 'fusion'), false)
  assert.ok(MODELES_FREELLMAPI.every((m) => m === m.toLowerCase()))
  assert.equal(modeleOpencode({ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }), 'freellmapi-gpt-oss-120b')
})

test('une demande à la fois par client et un plafond global ; la place est rendue une seule fois', () => {
  const places = new PlacesFreellmapi(2)
  const a = places.prendre('client-a')
  assert.ok(a)
  assert.equal(places.prendre('client-a'), null, 'une seule demande à la fois pour un même client')
  const b = places.prendre('client-b')
  assert.ok(b)
  assert.equal(places.prendre('client-c'), null, 'plafond global atteint')
  a()
  a()
  assert.equal(places.total, 1)
  assert.ok(places.prendre('client-c'))
})

test('l’en-tête « X-Routed-Via » donne le vrai fournisseur ; un en-tête bizarre est ignoré', () => {
  assert.deepEqual(routeServie(new Headers({ 'x-routed-via': 'groq/openai/gpt-oss-120b' })), { plateforme: 'groq', modele: 'groq/openai/gpt-oss-120b' })
  assert.equal(routeServie(new Headers({ 'x-routed-via': 'cache' })), null)
  assert.equal(routeServie(new Headers({ 'x-routed-via': '<script>' })), null)
  assert.equal(routeServie(new Headers()), null)
})

test('le premier répondant parle à FreeLLMAPI mot par mot, avec outils, et compte le vrai fournisseur', async () => {
  utiliserFreellmapi(voisinPret())
  let requete = null
  const appeler = async (url, options) => {
    requete = { url, entetes: options.headers, corps: JSON.parse(options.body) }
    return reponseSse([
      { choices: [{ delta: { content: 'Lomé est ' } }] },
      { choices: [{ delta: { content: 'la capitale du Togo.\nTITRE : Capitale du Togo' } }] },
      { choices: [], usage: { prompt_tokens: 3400, completion_tokens: 20 } },
    ], { 'x-routed-via': 'groq/openai/gpt-oss-120b' })
  }
  const morceaux = []
  const r = await premierRepondant(
    { lieu: 'discussion', message: 'Quelle est la capitale du Togo ?', contexte: { titre: true, client: 'client-1', session: 'projet-1' }, fournisseurs: [{ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }] },
    { clesPresentes: new Set(), appeler, env: ENV, surTexte: (e) => e.type === 'texte' && morceaux.push(e.delta) },
  )
  assert.equal(requete.url, 'http://127.0.0.1:3999/v1/chat/completions')
  assert.equal(requete.entetes.Authorization, 'Bearer cle-locale-de-test')
  assert.equal(requete.entetes['x-session-id'], 'projet-1')
  assert.equal(requete.corps.model, 'gpt-oss-120b')
  assert.equal(requete.corps.stream, true)
  assert.ok(Array.isArray(requete.corps.tools))
  assert.ok(!JSON.stringify(requete).includes('cle-groq-de-test'), 'la clé Groq ne passe jamais par le Runner vers FreeLLMAPI à chaque demande')
  assert.equal(r.ok, true)
  assert.equal(r.fournisseur, 'freellmapi')
  assert.equal(r.modele, 'groq/openai/gpt-oss-120b')
  assert.equal(r.famille, 'gpt-oss-120b')
  assert.equal(r.tokens_entree, 3400)
  assert.equal(r.reponse, 'Lomé est la capitale du Togo.')
  assert.ok(morceaux.join('').startsWith('Lomé est'))

  // Une demande de création : les outils sont donnés à FreeLLMAPI (format OpenAI), qui les fait suivre.
  await premierRepondant(
    { lieu: 'discussion', message: 'Crée-moi un site pour mon restaurant à Lomé', contexte: { client: 'client-1' }, fournisseurs: [{ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }] },
    { clesPresentes: new Set(), appeler, env: ENV },
  )
  assert.ok(requete.corps.tools.some((o) => o.function?.name === 'creer_site'))
})

test('FreeLLMAPI épuisé (429) : le fournisseur suivant répond, et le refus est compté', async () => {
  utiliserFreellmapi(voisinPret())
  const appeler = async (url) => {
    if (url.startsWith('http://127.0.0.1:3999')) return new Response(JSON.stringify({ error: { message: 'All providers rate limited' } }), { status: 429 })
    return new Response(JSON.stringify({ candidates: [{ content: { parts: [{ text: 'Bonjour.' }] } }], usageMetadata: { promptTokenCount: 10, candidatesTokenCount: 2 } }), { status: 200 })
  }
  const r = await premierRepondant(
    { lieu: 'discussion', message: 'Bonjour', contexte: { client: 'c' }, fournisseurs: [{ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }, { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }] },
    { clesPresentes: new Set(['gemini']), appeler, env: ENV },
  )
  assert.equal(r.fournisseur, 'gemini')
  assert.deepEqual(r.refus, [{ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }])
})

test('un client qui a déjà une demande chez FreeLLMAPI passe tout de suite au fournisseur suivant ; la place est rendue', async () => {
  const places = new PlacesFreellmapi(6)
  utiliserFreellmapi(voisinPret(places))
  const deja = places.prendre('client-occupe')
  const urls = []
  const appeler = async (url) => {
    urls.push(url)
    return new Response(JSON.stringify({ candidates: [{ content: { parts: [{ text: 'Bonjour.' }] } }] }), { status: 200 })
  }
  const r = await premierRepondant(
    { lieu: 'discussion', message: 'Bonjour', contexte: { client: 'client-occupe' }, fournisseurs: [{ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }, { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }] },
    { clesPresentes: new Set(['gemini']), appeler, env: ENV },
  )
  assert.equal(r.fournisseur, 'gemini')
  assert.ok(urls.every((u) => !u.startsWith('http://127.0.0.1:3999')))
  deja()
  // Après une demande servie par FreeLLMAPI, la place est rendue.
  const appelerVoisin = async () => new Response(JSON.stringify({ choices: [{ message: { content: 'Bonjour.' } }], usage: { prompt_tokens: 5, completion_tokens: 2 } }), { status: 200 })
  await premierRepondant({ lieu: 'discussion', message: 'Bonjour', contexte: { client: 'client-occupe' }, fournisseurs: [{ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }] }, { clesPresentes: new Set(), appeler: appelerVoisin, env: ENV })
  assert.equal(places.total, 0)
})

test('FreeLLMAPI absent ou modèle hors liste : l’entrée est ignorée, sans appel', async () => {
  utiliserFreellmapi({ ...voisinPret(), pret: () => false })
  const urls = []
  const appeler = async (url) => {
    urls.push(url)
    return new Response(JSON.stringify({ candidates: [{ content: { parts: [{ text: 'Bonjour.' }] } }] }), { status: 200 })
  }
  await premierRepondant({ lieu: 'discussion', message: 'Bonjour', contexte: {}, fournisseurs: [{ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }, { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }] }, { clesPresentes: new Set(['gemini']), appeler, env: ENV })
  utiliserFreellmapi(voisinPret())
  await premierRepondant({ lieu: 'discussion', message: 'Bonjour', contexte: {}, fournisseurs: [{ fournisseur: 'freellmapi', modele: 'auto' }, { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }] }, { clesPresentes: new Set(['gemini']), appeler, env: ENV })
  assert.ok(urls.every((u) => !u.startsWith('http://127.0.0.1')))
})

test('la rédaction d’un document passe par FreeLLMAPI et compte le vrai modèle', async () => {
  utiliserFreellmapi(voisinPret())
  const appeler = async (url, options) => {
    assert.equal(url, 'http://127.0.0.1:3999/v1/chat/completions')
    assert.equal(JSON.parse(options.body).response_format.type, 'json_object')
    return new Response(JSON.stringify({ choices: [{ message: { content: '{"titre":"Devis"}' } }], usage: { prompt_tokens: 100, completion_tokens: 10 } }), { status: 200, headers: { 'x-routed-via': 'zhipu/glm-4.7-flash' } })
  }
  const r = await rediger({ systeme: 's', consigne: 'c', fournisseurs: [{ fournisseur: 'freellmapi', modele: 'glm-4.7-flash' }] }, { clesPresentes: new Set(), appeler, env: ENV })
  assert.equal(r.ok, true)
  assert.deepEqual(r.objet, { titre: 'Devis' })
  assert.equal(r.consommation.fournisseur, 'freellmapi')
  assert.equal(r.consommation.modele, 'zhipu/glm-4.7-flash')
})

test('l’agent passe par la passerelle du Runner : jeton du projet vérifié, liste fermée, clé locale ajoutée ici', async () => {
  utiliserFreellmapi(voisinPret())
  assert.ok(clesAgent(new Set(), ENV).has('freellmapi'))
  const autorisation = `Bearer ${jetonPasserelle('secret-de-test', 'projet1')}`
  let envoye = null
  const appeler = async (url, options) => {
    envoye = { url, options }
    return new Response('{}', { status: 200 })
  }
  const ok = await passerOpenAI({ projet: 'projet1', chemin: 'chat/completions', autorisation, corps: JSON.stringify({ model: 'freellmapi-gpt-oss-120b', messages: [] }) }, { secretRunner: 'secret-de-test', appeler, env: ENV })
  assert.equal(ok.status, 200)
  assert.equal(envoye.url, 'http://127.0.0.1:3999/v1/chat/completions')
  assert.equal(envoye.options.headers.Authorization, 'Bearer cle-locale-de-test')
  assert.equal(JSON.parse(envoye.options.body).model, 'gpt-oss-120b')
  const refuse = await passerOpenAI({ projet: 'projet1', chemin: 'chat/completions', autorisation, corps: JSON.stringify({ model: 'freellmapi-auto', messages: [] }) }, { secretRunner: 'secret-de-test', appeler, env: ENV })
  assert.equal(refuse.statut, 403)
  const mauvaisJeton = await passerOpenAI({ projet: 'projet1', chemin: 'chat/completions', autorisation: 'Bearer faux', corps: JSON.stringify({ model: 'freellmapi-gpt-oss-120b' }) }, { secretRunner: 'secret-de-test', appeler, env: ENV })
  assert.equal(mauvaisJeton.statut, 401)
})

test('le processus voisin : démarrage, clé locale lue par le compte d’administration, état sans aucune clé', async () => {
  const lances = []
  const lancer = (commande, args, options) => {
    const p = new EventEmitter()
    p.stderr = new EventEmitter()
    p.kill = () => {}
    lances.push({ commande, args, options })
    return p
  }
  const appeler = async (url, options) => {
    if (url.endsWith('/api/auth/login')) {
      assert.equal(JSON.parse(options.body).password, secretDerive('secret-de-test', 'admin'))
      return new Response(JSON.stringify({ token: 'jeton-admin' }), { status: 200 })
    }
    if (url.endsWith('/api/settings/api-key')) return new Response(JSON.stringify({ apiKey: 'freellmapi-cle-locale-de-test-0123456789' }), { status: 200 })
    if (url.endsWith('/api/keys')) return new Response(JSON.stringify([{ platform: 'groq', status: 'healthy', enabled: true, maskedKey: 'gsk_...ABCD', monthlyUsage: { requests: 12, tokens: 3400 }, cooldowns: [] }]), { status: 200 })
    if (url.endsWith('/api/free-tier')) return new Response(JSON.stringify({ pools: [{ platform: 'groq', modelCount: 9, bestLabel: '~30M', quota: { limit: 8000, remaining: 7000, metric: 'tokens' } }] }), { status: 200 })
    return new Response('{}', { status: 404 })
  }
  const dossier = new URL('./support/faux-freellmapi', import.meta.url).pathname
  const f = new Freellmapi({ env: ENV, dossier, lancer, appeler, journal: { warn() {} } })
  assert.equal(await f.demarrer(), true)
  assert.equal(lances[0].options.env.HOST, '127.0.0.1')
  assert.equal(f.cle(), 'freellmapi-cle-locale-de-test-0123456789')
  const etat = f.etat()
  assert.deepEqual(etat.plateformes, ['groq'])
  assert.ok(!JSON.stringify(etat).includes('freellmapi-cle-locale'))
  const sante = await f.sante()
  assert.deepEqual(sante.fournisseurs, [{ nom: 'groq', etat: 'healthy', actif: true, requetes_mois: 12, tokens_mois: 3400, pauses: 0 }])
  assert.equal(sante.quotas[0].restant, 7000)
  assert.ok(!JSON.stringify(sante).includes('ABCD'), 'jamais le morceau de clé masquée')
  f.arreter()
})

test('FreeLLMAPI non installé : le Runner continue sans lui', async () => {
  const f = new Freellmapi({ env: ENV, dossier: '/dossier/qui/n-existe/pas', lancer: () => assert.fail('ne doit pas être lancé') })
  assert.equal(await f.demarrer(), false)
  assert.equal(f.pret(), false)
  assert.match(f.etat().erreur, /non installé/)
})
