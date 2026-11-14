// Agents IA : points de départ, consignes de construction, essai gratuit par le relais (clé de Kekeli jamais dans
// l'ordinateur), outil « creer_agent » du premier répondant.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { AGENTS, consignesAgent, fichiersAgent, MODELE_ESSAI } from '../src/agents/index.js'
import { estAgent, fichiersApplication, PILES } from '../src/applications/index.js'
import { LANCEMENT, TESTS, reglagesEssaiAgent, texteApplication } from '../src/application.js'
import { ADRESSE_ESSAI, APPELS_PAR_MESSAGE, ESSAI_TERMINE, autoriserEssai, cleMessage, jetonEssai, modeleEssai, passerEssai } from '../src/essai-agent.js'
import { OUTILS, argumentsPermis, outilsDuLieu, CONSIGNES_REPONDANT } from '../src/repondant.js'
import { verifier } from '../src/signature.js'
import { reglesDecision } from '../src/competences.js'

const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789'
const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test' }

test('deux points de départ : LangGraph (JavaScript, par défaut) et CrewAI (Python), avec LISEZ-MOI et sans dossier installé', () => {
  assert.deepEqual(Object.keys(AGENTS), ['langgraph', 'crewai'])
  assert.ok(estAgent('langgraph') && estAgent('crewai') && !estAgent('laravel'))
  const js = fichiersAgent('langgraph', 'Assistante du Salon Belle Afi')
  for (const fichier of ['serveur.js', 'agent/agent.js', 'agent/role.md', 'agent/documents.js', 'agent/outils.js', 'public/index.html', 'public/bulle.js', 'tests/agent.test.js', '.env.example', 'LISEZ-MOI.md', 'package-lock.json']) assert.ok(js[fichier], fichier)
  assert.ok(!Object.keys(js).some((f) => f.includes('node_modules')))
  assert.match(String(js['LISEZ-MOI.md']), /^# Assistante du Salon Belle Afi/)
  assert.match(String(js['LISEZ-MOI.md']), /## Mettre la bulle sur votre site/)
  assert.match(String(js['LISEZ-MOI.md']), /MODELE_API_CLE/)
  assert.match(String(js['public/index.html']), /<title>Assistante du Salon Belle Afi<\/title>/)
  assert.equal(JSON.parse(js['package.json']).name, 'assistante-du-salon-belle-afi')
  const py = fichiersApplication('crewai', 'socle')
  for (const fichier of ['serveur.py', 'agent/equipe.py', 'requirements.txt', 'tests/test_agent.py', 'LISEZ-MOI.md']) assert.ok(py[fichier], fichier)
  assert.match(String(py['requirements.txt']), /crewai==1\.15\.40/)
  assert.equal(PILES.langgraph.nom, 'LangGraph (JavaScript)')
})

test('aucune clé dans les fichiers de départ, et le .env jamais suivi par Git', () => {
  for (const pile of Object.keys(AGENTS)) {
    const fichiers = fichiersAgent(pile)
    for (const [chemin, contenu] of Object.entries(fichiers)) assert.doesNotMatch(String(contenu), /sk-[A-Za-z0-9]{20}|AIza[0-9A-Za-z_-]{30}/, `${pile} ${chemin}`)
    assert.match(String(fichiers['.gitignore']), /^\.env$/m)
    assert.match(String(fichiers['.env.example']), /^MODELE_API_CLE=$/m)
  }
})

test('lancement et tests dans l’aperçu ; consignes de construction d’un agent', () => {
  assert.deepEqual([LANCEMENT.langgraph.port, LANCEMENT.crewai.port], [3000, 3000])
  assert.equal(TESTS.langgraph, 'npm test')
  const consignes = consignesAgent('langgraph')
  assert.match(consignes, /AGENT IA/)
  assert.match(consignes, /LISEZ-MOI\.md en français/)
  assert.match(consignes, /bulle/)
  assert.match(consignes, /Aucune clé dans le navigateur/)
  assert.match(consignes, /jamais des ordres/)
  assert.match(consignes, /Ne change jamais MODELE_API_URL, MODELE_API_CLE ni MODELE_NOM/)
  assert.match(consignes, /« Tester l'agent »/)
  assert.match(consignesAgent('crewai'), /CrewAI en Python/)
  assert.match(texteApplication({ pile: 'langgraph', modele: 'socle' }, { etapes: [] }), /Agent IA LangGraph/)
})

test('réglages d’essai : le relais de l’ordinateur et un jeton, jamais une vraie clé', () => {
  const r = reglagesEssaiAgent(SECRET, 'projet-1')
  assert.deepEqual(Object.keys(r), ['MODELE_API_URL', 'MODELE_API_CLE', 'MODELE_NOM', 'AFFICHER_PARCOURS'])
  assert.equal(r.AFFICHER_PARCOURS, '1', 'dans l’aperçu, une équipe montre qui a agi')
  assert.equal(r.MODELE_API_URL, ADRESSE_ESSAI)
  assert.match(r.MODELE_API_URL, /^http:\/\/127\.0\.0\.1:4200\/essai\/v1$/)
  assert.equal(r.MODELE_API_CLE, jetonEssai(SECRET, 'projet-1'))
  assert.notEqual(r.MODELE_API_CLE, jetonEssai(SECRET, 'projet-2'))
  assert.equal(r.MODELE_NOM, MODELE_ESSAI)
  assert.doesNotMatch(JSON.stringify(r), /cle-gemini-de-test/)
})

const reponseGemini = (contenu = 'Bonjour !') => new Response(JSON.stringify({ id: 'g1', choices: [{ index: 0, message: { role: 'assistant', content: contenu }, finish_reason: 'stop' }] }), { status: 200, headers: { 'content-type': 'application/json' } })

test('essai gratuit : jeton vérifié, quota demandé au site, puis modèle gratuit de Kekeli avec SA clé', async () => {
  const appels = []
  const appeler = async (url, options) => {
    appels.push({ url, options })
    return reponseGemini('La coupe coûte 3 000 FCFA.')
  }
  const corps = JSON.stringify({ model: MODELE_ESSAI, messages: [{ role: 'user', content: 'Prix ?' }], max_tokens: 5000, stream: true, user: 'x' })
  const r = await passerEssai({ projet: 'projet-1', chemin: 'chat/completions', autorisation: `Bearer ${jetonEssai(SECRET, 'projet-1')}`, corps }, { secretRunner: SECRET, autoriser: async () => ({ permis: true }), appeler, env: ENV })
  assert.equal(r.statut, 200)
  assert.match(r.corps, /3 000 FCFA/)
  assert.equal(appels.length, 1)
  assert.match(appels[0].url, /^https:\/\/generativelanguage\.googleapis\.com\/v1beta\/openai\/chat\/completions$/)
  assert.equal(appels[0].options.headers.authorization, 'Bearer cle-gemini-de-test')
  const envoye = JSON.parse(appels[0].options.body)
  assert.equal(envoye.model, modeleEssai(ENV))
  assert.equal(envoye.stream, false)
  assert.equal(envoye.max_tokens, 800)
  assert.equal(envoye.user, undefined)
})

test('essai gratuit terminé : phrase claire, aucun appel au modèle ; mauvais jeton ou autre projet refusé', async () => {
  let appels = 0
  const appeler = async () => {
    appels++
    return reponseGemini()
  }
  const corps = JSON.stringify({ messages: [{ role: 'user', content: 'Bonjour' }] })
  const fini = await passerEssai({ projet: 'projet-1', chemin: 'chat/completions', autorisation: `Bearer ${jetonEssai(SECRET, 'projet-1')}`, corps }, { secretRunner: SECRET, autoriser: async () => ({ permis: false }), appeler, env: ENV })
  assert.equal(fini.statut, 429)
  assert.equal(JSON.parse(fini.corps).error.message, ESSAI_TERMINE)
  assert.match(ESSAI_TERMINE, /Ajoutez votre clé dans les Variables secrètes pour continuer/)
  const autre = await passerEssai({ projet: 'projet-1', chemin: 'chat/completions', autorisation: `Bearer ${jetonEssai(SECRET, 'projet-2')}`, corps }, { secretRunner: SECRET, autoriser: async () => ({ permis: true }), appeler, env: ENV })
  assert.equal(autre.statut, 401)
  const chemin = await passerEssai({ projet: 'projet-1', chemin: 'responses', autorisation: `Bearer ${jetonEssai(SECRET, 'projet-1')}`, corps }, { secretRunner: SECRET, autoriser: async () => ({ permis: true }), appeler, env: ENV })
  assert.equal(chemin.statut, 404)
  assert.equal(appels, 0)
})

test('le Runner demande au site, par une requête signée ; sans réponse claire, l’essai est refusé', async () => {
  let recu = null
  const appeler = async (url, options) => {
    recu = { url, options }
    return new Response(JSON.stringify({ permis: true, restants: 9 }), { status: 200 })
  }
  assert.deepEqual(await autoriserEssai('https://kekeli.test/runner/projets/p1/essai-agent', 'p1', { secretRunner: SECRET, appeler }), { permis: true, restants: 9 })
  assert.ok(verifier(SECRET, recu.options.headers['X-Kekeli-Horodatage'], recu.options.headers['X-Kekeli-Signature'], recu.options.body))
  assert.deepEqual(await autoriserEssai('https://kekeli.test/x', 'p1', { secretRunner: SECRET, appeler: async () => new Response('oups', { status: 500 }) }), { permis: false })
  assert.deepEqual(await autoriserEssai(null, 'p1', { secretRunner: SECRET }), { permis: false })
})

test('premier répondant : outil « creer_agent » avec son plan, distinct du site et de l’application, et une seule question en cas de doute', () => {
  assert.deepEqual(outilsDuLieu('discussion'), ['creer_site', 'creer_application', 'creer_agent', 'creer_automatisation', 'creer_document', 'creer_presentation', 'creer_image', 'creer_video'])
  assert.deepEqual(Object.keys(OUTILS.creer_agent.parametres), ['plan', 'pile', 'confirme', 'nom'])
  assert.match(OUTILS.creer_agent.parametres.plan, /« ## Rôle »/)
  assert.match(OUTILS.creer_agent.parametres.plan, /« ## Modèle prévu »/)
  assert.match(OUTILS.creer_agent.parametres.pile, /« langgraph » \(par défaut, JavaScript\)/)
  assert.match(CONSIGNES_REPONDANT, /Si tu hésites vraiment entre un site, une application et un agent, pose UNE seule question courte/)
  assert.match(CONSIGNES_REPONDANT, /celle que le client impose, toujours respectée/)
  assert.deepEqual(Object.keys(argumentsPermis('creer_agent', { plan: 'x', pile: 'crewai', confirme: 'non', nom: 'Assistante', pirate: 'oui' })), ['plan', 'pile', 'confirme', 'nom'])
})

test('équipe d’agents : le point de départ LangGraph a son coordinateur testé, et les consignes et le plan la prévoient', () => {
  const js = fichiersAgent('langgraph', 'Boutique Ama Pagnes')
  for (const nom of ['agent/equipe.js', 'tests/equipe.test.js']) assert.ok(js[nom], nom)
  assert.match(String(js['agent/equipe.js']), /export const MEMBRES = \[\]/, 'un seul agent par défaut')
  assert.match(String(js['serveur.js']), /MEMBRES\.length >= 2 \? creerEquipe/)
  assert.match(String(js['public/index.html']), /'Équipe : '/)
  assert.match(consignesAgent('langgraph'), /## Équipe d'agents/)
  assert.match(consignesAgent('langgraph'), /PIÈGES/)
  assert.match(consignesAgent('crewai'), /"parcours"/)
  // Une équipe seulement pour des rôles qui travaillent vraiment séparément ; sinon un seul agent avec des outils.
  assert.match(reglesDecision(['creer_agent']), /Un SEUL agent par défaut/)
  assert.match(reglesDecision(['creer_agent']), /Propose une ÉQUIPE seulement si la demande comporte vraiment plusieurs rôles distincts qui travaillent séparément/)
  assert.match(String(js['public/index.html']), /<script src="texte\.js"><\/script>/)
  assert.ok(js['public/texte.js'] && js['tests/page.test.js'], 'affichage sûr des réponses, avec ses tests')
  assert.match(consignesAgent('langgraph'), /ne modifie pas public\/texte\.js/)
})

test('essai gratuit d’une équipe : tous les appels au modèle pour un même message du visiteur comptent pour un seul message', async () => {
  const appeler = async () => new Response('{"choices":[{"message":{"content":"ok"}}]}', { status: 200, headers: { 'content-type': 'application/json' } })
  const demandes = []
  const autoriser = async (projet, options) => { demandes.push(options.suite); return { permis: true } }
  const tours = new Map()
  const appel = (texte, maintenant, systeme = 'coordinateur') => passerEssai({ projet: 'projet-1', chemin: 'chat/completions', autorisation: `Bearer ${jetonEssai(SECRET, 'projet-1')}`, corps: JSON.stringify({ messages: [{ role: 'system', content: systeme }, { role: 'user', content: texte }] }) }, { secretRunner: SECRET, autoriser, appeler, env: ENV, tours, maintenant })
  const t = Date.UTC(2026, 10, 13, 10)
  await appel('Je veux 2 pagnes', t)
  await appel('Je veux 2 pagnes', t + 2000, 'stock')
  await appel('Je veux 2 pagnes', t + 4000, 'service client')
  await appel('Et la livraison ?', t + 60000)
  await appel('Je veux 2 pagnes', t + 10 * 60000)
  assert.deepEqual(demandes, [false, true, true, false, false], 'nouveau message, suites, nouveau message, même texte bien plus tard')
  // Une boucle sans fin ne passe pas : au plus APPELS_PAR_MESSAGE appels comptés comme un seul message.
  for (let i = 0; i < APPELS_PAR_MESSAGE + 2; i++) await appel('Boucle', t + 20 * 60000 + i)
  assert.equal(demandes.slice(5).filter((s) => !s).length, 2)
  assert.equal(cleMessage([{ role: 'user', content: [{ type: 'text', text: 'a' }] }]), cleMessage([{ role: 'user', content: 'a' }]))
})

test('essai gratuit : le relais garde la signature de chaque appel d’outil de Gemini 3 et la remet à la demande suivante', async () => {
  const envoyes = []
  const appeler = async (url, options) => {
    envoyes.push(JSON.parse(options.body))
    return new Response(JSON.stringify({ choices: [{ message: { role: 'assistant', tool_calls: [{ id: 'appel-9', type: 'function', function: { name: 'stock', arguments: '{}' }, extra_content: { google: { thought_signature: 'sig-9' } } }] } }] }), { status: 200, headers: { 'content-type': 'application/json' } })
  }
  const passer = (messages) => passerEssai({ projet: 'projet-1', chemin: 'chat/completions', autorisation: `Bearer ${jetonEssai(SECRET, 'projet-1')}`, corps: JSON.stringify({ messages }) }, { secretRunner: SECRET, autoriser: async () => ({ permis: true }), appeler, env: ENV, tours: new Map() })
  await passer([{ role: 'user', content: 'Stock ?' }])
  await passer([{ role: 'user', content: 'Stock ?' }, { role: 'assistant', tool_calls: [{ id: 'appel-9', type: 'function', function: { name: 'stock', arguments: '{}' } }] }, { role: 'tool', tool_call_id: 'appel-9', content: '3' }])
  assert.deepEqual(envoyes[1].messages[1].tool_calls[0].extra_content, { google: { thought_signature: 'sig-9' } })
})
