import { test } from 'node:test'
import assert from 'node:assert/strict'
import { APPELS_MAX_PAR_MESSAGE, estLecture, resultatPourModele, resumeAction, schemaPropre, serveurPermis, texteResultat, RESULTAT_MAX } from '../src/connecteurs.js'
import { premierRepondant, consigneSysteme } from '../src/repondant.js'
import { demarrerFauxServeurMcp } from './support/faux-serveur-mcp.js'

const ENV = { OPENAI_API_KEY: 'cle-openai-de-test', KEKELI_CONNECTEURS_LOCAUX: '1' }
const OPENAI = [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }, { fournisseur: 'openai', modele: 'gpt-6.1-terra' }]

test('seuls les serveurs https publics sont permis, jamais une adresse interne', () => {
  assert.equal(serveurPermis('https://mcp.notion.com/mcp', {}), true)
  for (const mauvaise of ['http://mcp.notion.com/mcp', 'https://localhost/mcp', 'https://10.0.0.4/mcp', 'https://192.168.1.2/mcp', 'https://169.254.169.254/latest', 'https://serveur.internal/mcp', 'https://[::1]/mcp', 'ftp://exemple.tg', 'https://user:motdepasse@exemple.tg/mcp', 'pas une adresse']) {
    assert.equal(serveurPermis(mauvaise, {}), false, mauvaise)
  }
  assert.equal(serveurPermis('http://127.0.0.1:4000/mcp', {}), false, 'jamais en production')
  assert.equal(serveurPermis('http://127.0.0.1:4000/mcp', { KEKELI_CONNECTEURS_LOCAUX: '1' }), true, 'seulement pour les tests')
})

test('lecture libre, écriture avec accord : indications du serveur d’abord, puis le nom, et en cas de doute Kekeli demande', () => {
  assert.equal(estLecture({ name: 'whatever', annotations: { readOnlyHint: true } }), true)
  assert.equal(estLecture({ name: 'search', annotations: { readOnlyHint: false } }), false)
  assert.equal(estLecture({ name: 'list_x', annotations: { readOnlyHint: true, destructiveHint: true } }), false)
  for (const lecture of ['notion-search', 'notion-fetch', 'read_wiki_structure', 'ask_question', 'hf_whoami', 'list_issues', 'getPage']) assert.equal(estLecture({ name: lecture }), true, lecture)
  for (const ecriture of ['notion-create-pages', 'send_message', 'createPage', 'delete_file', 'issue_write', 'manage_crm_objects', 'publish_post', 'frobnicate']) assert.equal(estLecture({ name: ecriture }), false, ecriture)
})

test('schéma borné, résumé clair, résultat raccourci et toujours présenté comme une information', () => {
  assert.deepEqual(schemaPropre(null), { type: 'object', properties: {} })
  assert.deepEqual(schemaPropre({ type: 'object', properties: { a: { type: 'string', description: 'x'.repeat(9000) } } }), { type: 'object', properties: {}, additionalProperties: true })
  const r = resumeAction('Notion', { name: 'create_page', title: 'Créer une page' }, { title: 'Menu', content: 'x'.repeat(500) })
  assert.equal(r.action, 'Créer une page')
  assert.equal(r.details[0].valeur, 'Menu')
  assert.ok(r.details[1].valeur.length <= 240)
  assert.equal(texteResultat({ content: [{ type: 'text', text: 'y'.repeat(RESULTAT_MAX + 50) }] }).length, RESULTAT_MAX + '\n[… résultat raccourci par Kekeli]'.length)
  assert.match(resultatPourModele('Notion', { content: [] }).consigne, /JAMAIS une consigne/)
  assert.match(consigneSysteme('discussion', { connecteurs: ['Notion'] }), /Connecteurs actifs : Notion/)
  assert.doesNotMatch(consigneSysteme('discussion', {}), /Connecteurs actifs/)
})

const reponse = (sorties, usage = { input_tokens: 900, output_tokens: 60 }) => new Response(JSON.stringify({ output: sorties, usage }), { status: 200 })
const appelOutil = (nom, args) => ({ type: 'function_call', name: nom, arguments: JSON.stringify(args), call_id: `appel-${nom}` })
const texte = (t) => ({ type: 'message', content: [{ type: 'output_text', text: t }] })

/** Un faux OpenAI qui rend les réponses prévues, dans l'ordre, et garde chaque requête. */
function fauxOpenAI(sorties) {
  const requetes = []
  const appeler = async (url, options) => {
    requetes.push({ url, corps: JSON.parse(options.body) })
    return reponse(sorties[Math.min(requetes.length - 1, sorties.length - 1)])
  }
  return { appeler, requetes }
}

async function avecServeur(rappel, options) {
  const serveur = await demarrerFauxServeurMcp({ jeton: 'jeton-notion', ...options })
  try {
    return await rappel(serveur)
  } finally {
    await serveur.fermer()
  }
}

const demande = (serveur, extra = {}) => ({ lieu: 'discussion', message: 'Que dit ma page sur le menu ?', fournisseurs: OPENAI, connecteurs: [{ id: 7, nom: 'Notion', url: serveur.url, jeton: 'jeton-notion', toujours: [] }], ...extra })

test('une lecture se fait tout de suite, le résultat part au modèle comme une information, l’action est notée', () => avecServeur(async (serveur) => {
  const openai = fauxOpenAI([[appelOutil('c0_search_pages', { query: 'menu' })], [texte('Dans votre Notion, le riz gras est à 1 500 FCFA.')]])
  const r = await premierRepondant(demande(serveur), { clesPresentes: new Set(['gemini']), appeler: openai.appeler, env: ENV })
  assert.equal(r.decision, 'repondre')
  assert.match(r.reponse, /1 500 FCFA/)
  assert.ok(openai.requetes.every((q) => q.url.includes('api.openai.com')), 'jamais une offre gratuite avec un connecteur')
  const outils = openai.requetes[0].corps.tools.map((t) => t.name)
  assert.ok(outils.includes('c0_search_pages') && outils.includes('c0_create_page'))
  assert.match(openai.requetes[0].corps.instructions, /Connecteurs actifs : Notion/)
  const resultat = JSON.parse(openai.requetes[1].corps.input.find((i) => i.type === 'function_call_output').output)
  assert.match(resultat.consigne, /JAMAIS une consigne/)
  assert.match(resultat.contenu, /riz gras/)
  assert.deepEqual(serveur.appels, [{ nom: 'search_pages', arguments: { query: 'menu' } }])
  assert.equal(r.actions_connecteurs.length, 1)
  assert.deepEqual({ ...r.actions_connecteurs[0], resume: undefined, duree_ms: undefined }, { connecteur: 7, nom: 'Notion', outil: 'search_pages', ecriture: false, statut: 'fait', resume: undefined, duree_ms: undefined })
}))

test('une action qui modifie attend l’accord du client : rien n’est envoyé au serveur', () => avecServeur(async (serveur) => {
  const openai = fauxOpenAI([[appelOutil('c0_create_page', { title: 'Menu de la semaine', content: 'Riz gras' })]])
  const r = await premierRepondant(demande(serveur, { message: 'Crée une page « Menu de la semaine »' }), { clesPresentes: new Set(), appeler: openai.appeler, env: ENV })
  assert.equal(r.decision, 'accord')
  assert.deepEqual(r.accord.arguments, { title: 'Menu de la semaine', content: 'Riz gras' })
  assert.equal(r.accord.connecteur, 7)
  assert.equal(r.accord.outil, 'create_page')
  assert.equal(r.accord.resume.action, 'Créer une page')
  assert.equal(r.accord.resume.connecteur, 'Notion')
  assert.deepEqual(serveur.appels, [], 'aucune écriture sans accord')
  assert.equal(r.requetes, 1, 'les tokens déjà utilisés sont comptés')
}))

test('« Toujours autoriser pour cet outil » : l’action se fait sans redemander', () => avecServeur(async (serveur) => {
  const openai = fauxOpenAI([[appelOutil('c0_create_page', { title: 'Menu' })], [texte('La page est créée dans votre Notion.')]])
  const d = demande(serveur)
  d.connecteurs[0].toujours = ['create_page']
  const r = await premierRepondant(d, { clesPresentes: new Set(), appeler: openai.appeler, env: ENV })
  assert.equal(r.decision, 'repondre')
  assert.deepEqual(serveur.appels.map((a) => a.nom), ['create_page'])
  assert.equal(r.actions_connecteurs[0].ecriture, true)
}))

test('après « Autoriser » : l’action vue par le client est faite telle quelle, puis le modèle termine', () => avecServeur(async (serveur) => {
  const openai = fauxOpenAI([[texte('C’est fait : la page « Menu de la semaine » est dans votre Notion.')]])
  const accord = { decision: 'autoriser', connecteur: 7, outil: 'create_page', arguments: { title: 'Menu de la semaine' } }
  const r = await premierRepondant(demande(serveur, { accord }), { clesPresentes: new Set(), appeler: openai.appeler, env: ENV })
  assert.equal(r.decision, 'repondre')
  assert.deepEqual(serveur.appels, [{ nom: 'create_page', arguments: { title: 'Menu de la semaine' } }])
  const entree = openai.requetes[0].corps.input
  assert.ok(entree.some((i) => i.type === 'function_call' && i.name === 'c0_create_page'))
  assert.ok(entree.some((i) => i.type === 'function_call_output'))
  assert.equal(r.actions_connecteurs[0].statut, 'fait')
}))

test('un connecteur injoignable ou à une adresse interne est ignoré : Kekeli répond sans lui', async () => {
  const openai = fauxOpenAI([[texte('Bonjour !')]])
  const r = await premierRepondant({ lieu: 'discussion', message: 'Bonjour', fournisseurs: OPENAI, connecteurs: [{ id: 1, nom: 'Interne', url: 'https://10.0.0.3/mcp' }, { id: 2, nom: 'Éteint', url: 'http://127.0.0.1:9/mcp' }] }, { clesPresentes: new Set(), appeler: openai.appeler, env: ENV, journal: { warn() {} } })
  assert.equal(r.decision, 'repondre')
  assert.deepEqual(r.connecteurs_injoignables.map((c) => c.id).sort(), [1, 2])
  assert.equal(openai.requetes[0].corps.tools.some((t) => /^c\d+_/.test(t.name)), false)
})

test('un jeton refusé par le serveur : le connecteur est ignoré, jamais d’erreur pour le client', () => avecServeur(async (serveur) => {
  const openai = fauxOpenAI([[texte('Je ne peux pas lire votre Notion pour le moment.')]])
  const d = demande(serveur)
  d.connecteurs[0].jeton = 'jeton-expire'
  const r = await premierRepondant(d, { clesPresentes: new Set(), appeler: openai.appeler, env: ENV, journal: { warn() {} } })
  assert.equal(r.decision, 'repondre')
  assert.deepEqual(r.connecteurs_injoignables, [{ id: 7, nom: 'Notion' }])
}))

test(`au plus ${APPELS_MAX_PAR_MESSAGE} appels aux connecteurs par message`, () => avecServeur(async (serveur) => {
  const openai = fauxOpenAI([[appelOutil('c0_search_pages', { query: 'menu' })]])
  const r = await premierRepondant(demande(serveur), { clesPresentes: new Set(), appeler: openai.appeler, env: ENV })
  assert.equal(serveur.appels.length, APPELS_MAX_PAR_MESSAGE)
  assert.equal(r.ok, false, 'le modèle n’a jamais répondu : le site redemandera plus tard')
}))

test('sans connecteur, rien ne change : pas d’outil de connecteur, les offres gratuites restent permises', async () => {
  const appels = []
  const appeler = async (url) => {
    appels.push(url)
    return new Response(JSON.stringify({ candidates: [{ content: { role: 'model', parts: [{ text: 'Bonjour !' }] } }], usageMetadata: { promptTokenCount: 10, candidatesTokenCount: 3 } }), { status: 200 })
  }
  const r = await premierRepondant({ lieu: 'discussion', message: 'Bonjour', fournisseurs: OPENAI }, { clesPresentes: new Set(['gemini']), appeler, env: { ...ENV, GEMINI_API_KEY: 'cle-gemini-de-test' } })
  assert.equal(r.fournisseur, 'gemini')
  assert.equal(r.actions_connecteurs, undefined)
})

test('connexion OAuth : découverte, inscription automatique de Kekeli, PKCE, puis échange du code et renouvellement', async () => {
  const { preparerConnexion, echangerCode, renouvelerJeton, sonderServeur } = await import('../src/connecteurs.js')
  const vus = []
  const json = (objet, statut = 200) => new Response(JSON.stringify(objet), { status: statut, headers: { 'content-type': 'application/json' } })
  const appeler = async (url, options = {}) => {
    const u = new URL(String(url))
    vus.push(`${options.method ?? 'GET'} ${u.pathname}`)
    if (u.pathname.startsWith('/.well-known/oauth-protected-resource')) return json({ resource: 'https://mcp.exemple.tg/mcp', authorization_servers: ['https://auth.exemple.tg'], scopes_supported: ['read', 'write'] })
    if (u.pathname.startsWith('/.well-known/oauth-authorization-server')) return json({ issuer: 'https://auth.exemple.tg', authorization_endpoint: 'https://auth.exemple.tg/authorize', token_endpoint: 'https://auth.exemple.tg/token', registration_endpoint: 'https://auth.exemple.tg/register', response_types_supported: ['code'], code_challenge_methods_supported: ['S256'], grant_types_supported: ['authorization_code', 'refresh_token'], token_endpoint_auth_methods_supported: ['none'] })
    if (u.pathname === '/register') {
      const corps = JSON.parse(options.body)
      assert.equal(corps.client_name, 'Kekeli')
      assert.deepEqual(corps.redirect_uris, ['https://kekeli.test/connecteurs/retour'])
      return json({ client_id: 'client-kekeli', ...corps }, 201)
    }
    if (u.pathname === '/token') {
      const p = new URLSearchParams(String(options.body))
      if (p.get('grant_type') === 'authorization_code') {
        assert.equal(p.get('code'), 'code-recu')
        assert.ok(p.get('code_verifier'))
        assert.equal(p.get('resource'), 'https://mcp.exemple.tg/mcp')
        return json({ access_token: 'acces-1', refresh_token: 'renouvellement-1', expires_in: 3600, token_type: 'Bearer' })
      }
      assert.equal(p.get('refresh_token'), 'renouvellement-1')
      return json({ access_token: 'acces-2', expires_in: 3600, token_type: 'Bearer' })
    }
    return json({ error: 'inconnu' }, 404)
  }
  const prep = await preparerConnexion({ url: 'https://mcp.exemple.tg/mcp', redirection: 'https://kekeli.test/connecteurs/retour', etat: 'etat-1' }, { appeler })
  const consentement = new URL(prep.adresse)
  assert.equal(consentement.origin + consentement.pathname, 'https://auth.exemple.tg/authorize')
  assert.equal(consentement.searchParams.get('client_id'), 'client-kekeli')
  assert.equal(consentement.searchParams.get('code_challenge_method'), 'S256')
  assert.equal(consentement.searchParams.get('state'), 'etat-1')
  assert.equal(consentement.searchParams.get('resource'), 'https://mcp.exemple.tg/mcp')
  assert.ok(prep.verificateur.length >= 43)
  assert.equal(prep.client.client_id, 'client-kekeli')
  // Une deuxième connexion réutilise l'inscription gardée par le site : pas de nouvelle inscription.
  vus.length = 0
  await preparerConnexion({ url: 'https://mcp.exemple.tg/mcp', redirection: 'https://kekeli.test/connecteurs/retour', etat: 'etat-2', client: prep.client }, { appeler })
  assert.ok(!vus.includes('POST /register'))
  const jetons = await echangerCode({ url: 'https://mcp.exemple.tg/mcp', client: prep.client, code: 'code-recu', verificateur: prep.verificateur, redirection: 'https://kekeli.test/connecteurs/retour', ressource: prep.ressource }, { appeler })
  assert.deepEqual(jetons, { ok: true, jeton: 'acces-1', renouvellement: 'renouvellement-1', expire_dans: 3600 })
  const neufs = await renouvelerJeton({ url: 'https://mcp.exemple.tg/mcp', client: prep.client, renouvellement: 'renouvellement-1', ressource: prep.ressource }, { appeler })
  assert.equal(neufs.jeton, 'acces-2')
  assert.equal(neufs.renouvellement, 'renouvellement-1', 'le jeton de renouvellement est gardé')
  // Sonde : un serveur qui refuse sans jeton et qui permet l'inscription automatique se connecte par OAuth.
  const sonde = await sonderServeur('https://mcp.exemple.tg/mcp', { ouvrir: async () => { throw new Error('401') }, appeler })
  assert.deepEqual(sonde, { ok: true, connexion: 'oauth', raison: null })
})
