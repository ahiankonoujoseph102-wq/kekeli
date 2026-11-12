// Création d'applications comme Claude Code (12 novembre 2026) : outils du premier répondant, relais et passerelle
// OpenAI (la clé ne va jamais dans l'ordinateur), Variables secrètes, application installable avec LISEZ-MOI.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { OUTILS, argumentsPermis, outilsDuLieu, premierRepondant, requeteFournisseur, CONSIGNES_REPONDANT } from '../src/repondant.js'
import { fichiersApplication } from '../src/applications/index.js'
import { consignesApplication, configurationOpencode } from '../src/ordinateurs.js'
import { passerOpenAI, jetonPasserelle, lireCheminPasserelle } from '../src/passerelle.js'
import { suivreRelais, jetonRelais } from '../src/relais.js'
import { clesAgent, entreesUtilisables, fournisseursAvecCle, reglesReseau } from '../src/fournisseurs.js'
import { variablesPermises } from '../src/apercu/serveur.mjs'
import { creerServeur } from '../src/server.js'

const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test', OPENAI_API_KEY: 'cle-openai-de-test' }
const reponseJson = (statut, donnees) => new Response(JSON.stringify(donnees), { status: statut, headers: { 'content-type': 'application/json' } })

test('site ou application : deux outils distincts dans une discussion, une seule question en cas de doute', () => {
  assert.deepEqual(outilsDuLieu('discussion'), ['creer_site', 'creer_application', 'creer_agent'])
  assert.deepEqual(outilsDuLieu('site'), ['modifier_site'])
  assert.deepEqual(outilsDuLieu('site', { lecture: true }), ['modifier_site', 'lire_fichier'])
  assert.match(CONSIGNES_REPONDANT, /Si tu hésites vraiment entre les deux, pose UNE question courte/)
  assert.match(CONSIGNES_REPONDANT, /« Construire ce plan »/)
  assert.match(CONSIGNES_REPONDANT, /« en Laravel », « en Next.js », « avec Supabase »/)
  assert.match(CONSIGNES_REPONDANT, /Kekeli n'héberge aucune donnée/)
  assert.match(CONSIGNES_REPONDANT, /Variables secrètes/)
  const r = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6.1-terra' }, { lieu: 'discussion', message: 'une application' }, ENV)
  const outil = r.corps.tools.find((t) => t.name === 'creer_application')
  assert.deepEqual(outil.parameters.required, ['plan', 'pile', 'confirme', 'nom'])
})

test('les arguments d’un outil sont filtrés : seulement ceux déclarés, en texte, raccourcis', () => {
  const args = argumentsPermis('creer_application', { plan: 'x'.repeat(5000), pile: 'laravel', confirme: 'non', pirate: 'rm -rf /', autre: 3 })
  assert.deepEqual(Object.keys(args), ['plan', 'pile', 'confirme'])
  assert.equal(args.plan.length, 4000)
  assert.deepEqual(argumentsPermis('creer_site', { plan: 'x' }), {})
})

test('creer_application renvoie son plan au site ; une question sur le code lit le fichier sans rien modifier', async () => {
  const appel = (nom, args) => reponseJson(200, { output: [{ type: 'function_call', name: nom, call_id: 'c1', arguments: JSON.stringify(args) }], usage: {} })
  const plan = await premierRepondant({ lieu: 'discussion', message: 'une application de rendez-vous', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }] }, { clesPresentes: new Set(), appeler: async () => appel('creer_application', { plan: '## Pages\n- Accueil', pile: 'auto', confirme: 'non' }), env: ENV })
  assert.equal(plan.decision, 'outil')
  assert.deepEqual(plan.arguments, { plan: '## Pages\n- Accueil', pile: 'auto', confirme: 'non' })

  let tour = 0
  const lus = []
  const reponse = await premierRepondant(
    { lieu: 'site', message: 'où est la page de connexion ?', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }], lecture: 'https://kekeli.test/runner/projets/p/fichier?signature=abc' },
    {
      clesPresentes: new Set(),
      env: ENV,
      appeler: async () => (++tour === 1 ? appel('lire_fichier', { chemin: 'routes/web.php' }) : reponseJson(200, { output: [{ type: 'message', content: [{ type: 'output_text', text: 'Elle est déclarée dans `routes/web.php`.' }] }], usage: {} })),
      lire: async (adresse, chemin) => {
        lus.push(chemin)
        return { chemin, contenu: "Route::get('/connexion', …)" }
      },
    },
  )
  assert.equal(reponse.decision, 'repondre')
  assert.deepEqual(lus, ['routes/web.php'])
  // Sans adresse de lecture, l'outil n'est jamais proposé.
  const r = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6-luna' }, { lieu: 'site', message: 'x', contexte: {} }, ENV)
  assert.deepEqual(r.corps.tools.map((t) => t.name), ['modifier_site'])
})

test('la clé OpenAI n’entre jamais dans l’ordinateur : pas de secret E2B, seulement le jeton du projet dans OpenCode', () => {
  const cles = fournisseursAvecCle({ ...ENV })
  assert.ok(!cles.has('openai'))
  assert.ok(!('api.openai.com' in reglesReseau(cles, (n) => n)))
  assert.ok(clesAgent(cles, ENV).has('openai'))
  assert.ok(!clesAgent(cles, { GEMINI_API_KEY: 'x' }).has('openai'))
  assert.deepEqual(entreesUtilisables([{ fournisseur: 'openai', modele: 'gpt-6-astra' }, { fournisseur: 'openai', modele: 'gpt-6.1-terra' }], clesAgent(cles, ENV)).map((e) => e.modele), ['gpt-6.1-terra'])
  const config = configurationOpencode(cles, [], { jetonOpenAI: jetonPasserelle('secret-runner', 'projet-1') })
  assert.equal(config.provider.openai.options.baseURL, 'http://127.0.0.1:4200/v1')
  assert.ok(!JSON.stringify(config).includes('cle-openai-de-test'))
  assert.deepEqual(config.provider.openai.whitelist, ['gpt-6-luna', 'gpt-6.1-terra', 'gpt-6.1-sol'])
})

test('la passerelle vérifie le jeton du projet et le modèle, puis ajoute la clé (rien n’est gardé chez OpenAI)', async () => {
  let recu = null
  const appeler = async (url, options) => {
    recu = { url, options }
    return reponseJson(200, { ok: true })
  }
  const jeton = jetonPasserelle('secret-runner', 'projet-1')
  const ok = await passerOpenAI({ projet: 'projet-1', chemin: 'responses', autorisation: `Bearer ${jeton}`, corps: JSON.stringify({ model: 'gpt-6.1-terra', input: 'x' }) }, { secretRunner: 'secret-runner', appeler, env: ENV })
  assert.equal(ok.status, 200)
  assert.equal(recu.url, 'https://api.openai.com/v1/responses')
  assert.equal(recu.options.headers.Authorization, 'Bearer cle-openai-de-test')
  assert.equal(JSON.parse(recu.options.body).store, false)
  assert.deepEqual(await passerOpenAI({ projet: 'projet-2', chemin: 'responses', autorisation: `Bearer ${jeton}`, corps: '{}' }, { secretRunner: 'secret-runner', appeler, env: ENV }), { statut: 401, erreur: 'jeton invalide' })
  assert.deepEqual(await passerOpenAI({ projet: 'projet-1', chemin: 'responses', autorisation: `Bearer ${jeton}`, corps: JSON.stringify({ model: 'gpt-6-astra' }) }, { secretRunner: 'secret-runner', appeler, env: ENV }), { statut: 403, erreur: 'modèle refusé' })
  assert.deepEqual(await passerOpenAI({ projet: 'projet-1', chemin: 'files', autorisation: `Bearer ${jeton}`, corps: '{}' }, { secretRunner: 'secret-runner', appeler, env: ENV }), { statut: 404, erreur: 'adresse refusée' })
  assert.deepEqual(lireCheminPasserelle('/passerelle/projet-1/openai/v1/responses'), { projet: 'projet-1', chemin: 'responses' })
})

test('le relais : le Runner prend la demande d’OpenCode, l’envoie à OpenAI avec sa clé, puis dépose la réponse', async () => {
  const jeton = jetonPasserelle('secret-runner', 'projet-1')
  const deposees = []
  let pris = false
  let actif = true
  const appeler = async (url, options = {}) => {
    if (url.endsWith('/relais/attente')) {
      assert.equal(options.headers['x-kekeli-relais'], jetonRelais('secret-runner', 'projet-1'))
      if (pris) {
        actif = false
        return new Response(null, { status: 204 })
      }
      pris = true
      return reponseJson(200, { id: '00000000-0000-0000-0000-000000000001', chemin: 'responses', autorisation: `Bearer ${jeton}`, corps: JSON.stringify({ model: 'gpt-6.1-terra', input: 'x' }) })
    }
    if (url === 'https://api.openai.com/v1/responses') {
      assert.equal(options.headers.Authorization, 'Bearer cle-openai-de-test')
      return new Response('data: {"type":"response.completed"}\n\n', { status: 200, headers: { 'content-type': 'text/event-stream' } })
    }
    deposees.push({ url, corps: JSON.parse(options.body) })
    return reponseJson(200, { ok: true })
  }
  await suivreRelais({ url: 'https://4200-boite.e2b.app', projet: 'projet-1', secretRunner: 'secret-runner', continuer: () => actif, appeler, env: ENV })
  await new Promise((r) => setTimeout(r, 20))
  assert.equal(deposees.length, 1)
  assert.match(deposees[0].url, /\/relais\/reponse\/00000000-0000-0000-0000-000000000001$/)
  assert.deepEqual([deposees[0].corps.statut, deposees[0].corps.type], [200, 'text/event-stream'])
  assert.ok(!JSON.stringify(deposees).includes('cle-openai-de-test'))
})

test('les Variables secrètes : noms en capitales seulement, jamais les réglages réservés de l’aperçu', () => {
  assert.deepEqual(variablesPermises({ DATABASE_URL: 'postgresql://…', mauvais: 'x', APP_KEY: 'x', PATH: '/x', NOMBRE: 3 }), { DATABASE_URL: 'postgresql://…' })
})

test('l’application est installable et livrée avec un LISEZ-MOI en français ; les consignes de sécurité sont données à l’agent', () => {
  for (const pile of ['laravel', 'nextjs']) {
    const f = fichiersApplication(pile, 'socle')
    const manifeste = JSON.parse(f['public/manifest.webmanifest'])
    assert.equal(manifeste.display, 'standalone')
    assert.deepEqual(manifeste.icons.map((i) => i.sizes), ['192x192', '512x512'])
    assert.ok(f['public/icones/icone-512.png'].length > 100)
    assert.match(f['public/service-worker.js'], /Rien n'est gardé hors ligne/)
    assert.match(f['LISEZ-MOI.md'], /## Variables à remplir/)
    const consignes = consignesApplication(pile, { installable: true })
    assert.match(consignes, /Mots de passe toujours chiffrés/)
    assert.match(consignes, /Chaque page privée est protégée côté serveur/)
    assert.match(consignes, /droits .* vérifiés côté serveur/)
    assert.match(consignes, /Aucune clé ni aucun secret dans le code envoyé au navigateur/)
    assert.match(consignes, /Kekeli n'héberge aucune donnée du client/)
    assert.match(consignes, /N'impose aucun tutoriel/)
    assert.match(consignes, /votre application/)
  }
  assert.match(fichiersApplication('laravel', 'socle')['resources/views/layouts/app.blade.php'], /rel="manifest"/)
  assert.match(fichiersApplication('nextjs', 'socle')['app/layout.js'], /manifest: '\/manifest.webmanifest'/)
  assert.match(consignesApplication('nextjs', { installable: true, service: 'supabase' }), /Supabase/)
  // Un site avec base de données (pas une application) garde son vocabulaire.
  assert.match(consignesApplication('laravel'), /n'emploie jamais le mot « application »/)
})

test('la base de test de l’aperçu est locale : SQLite pour Laravel, aucun compte extérieur', async () => {
  const { fichierEnvEssai } = await import('../src/application.js')
  const env = fichierEnvEssai('laravel', 'DB_CONNECTION=mysql\nDB_DATABASE=prod\n', { motDePasse: 'essai' })
  assert.match(env, /DB_CONNECTION=sqlite/)
  assert.doesNotMatch(env, /DB_DATABASE=prod/)
})

test('l’adresse /passerelle du Runner transmet la réponse d’OpenAI (jeton du projet)', async () => {
  const serveur = creerServeur({ secret: 'secret-runner', clesPresentes: new Set(), ordinateurs: {}, journal: { error() {}, log() {}, warn() {} } })
  await new Promise((r) => serveur.listen(0, r))
  try {
    const r = await fetch(`http://127.0.0.1:${serveur.address().port}/passerelle/projet-1/openai/v1/responses`, { method: 'POST', body: '{}', headers: { authorization: 'Bearer faux' } })
    assert.ok([401, 503].includes(r.status))
  } finally {
    serveur.close()
  }
})

test('l’aperçu privé laisse passer seulement les fichiers d’installation (aucune donnée) sans son cookie', async () => {
  const { FICHIERS_INSTALLATION } = await import('../src/apercu/serveur.mjs')
  for (const chemin of ['/manifest.webmanifest', '/service-worker.js', '/icones/icone-192.png', '/icones/icone-512.png']) assert.ok(FICHIERS_INSTALLATION.test(chemin), chemin)
  for (const chemin of ['/', '/admin', '/mes-rendez-vous', '/icones/../.env', '/service-worker.js.map', '/manifest.webmanifest/x']) assert.ok(!FICHIERS_INSTALLATION.test(chemin), chemin)
})

test('finitions : « dossier du projet », commandes internes de Kekeli cachées, phrases d’une construction neuve', async () => {
  const { traduireOutil, commandeDuProjet } = await import('../src/etapes.js')
  const outil = (tool, input) => traduireOutil({ type: 'tool', tool, state: { status: 'completed', input } })
  assert.deepEqual(outil('read', { filePath: '/home/user/projet' }), { genre: 'fichier_lu', cible: 'dossier du projet' })
  assert.deepEqual(outil('list', { path: '/home/user/projet/' }), { genre: 'recherche', cible: 'dossier du projet' })
  assert.equal(outil('bash', { command: 'touch /home/user/.kekeli/relancer' }), null)
  assert.deepEqual(outil('bash', { command: 'php artisan migrate && touch /home/user/.kekeli/relancer' }), { genre: 'commande', cible: 'php artisan migrate' })
  assert.equal(commandeDuProjet('touch /home/user/.kekeli/x; npm test'), 'npm test')
  const consignes = consignesApplication('laravel', { installable: true })
  assert.match(consignes, /jamais « votre application actuelle », « les comptes existants » ni « sans toucher à… »/)
})
