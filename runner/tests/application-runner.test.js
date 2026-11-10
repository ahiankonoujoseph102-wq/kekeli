import { test } from 'node:test'
import assert from 'node:assert/strict'
import { ADMIN_ESSAI, applicationValide, fichierEnvEssai, motDePasseEssai, problemesApplication, resumeTests, texteApplication, texteCorrectionApplication } from '../src/application.js'
import { executerTache } from '../src/agent.js'
import { consignesApplication } from '../src/ordinateurs.js'
import { MisesDeCote } from '../src/limites.js'
import { fauxOpencode, finRequete, libre, occupe } from './outils.js'

const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789'

test('les informations d’une application web sont vérifiées', () => {
  assert.ok(applicationValide({ pile: 'laravel', modele: 'reservations-restaurant' }))
  assert.ok(applicationValide({ pile: 'nextjs', modele: 'socle' }))
  assert.ok(!applicationValide({ pile: 'django', modele: 'socle' }))
  assert.ok(!applicationValide({ pile: 'laravel', modele: '../../etc' }))
  assert.ok(!applicationValide(null))
})

test('le mot de passe d’essai est propre à chaque projet, et le .env d’essai ne contient aucune vraie clé', () => {
  assert.equal(motDePasseEssai(SECRET, 'p1'), motDePasseEssai(SECRET, 'p1'))
  assert.notEqual(motDePasseEssai(SECRET, 'p1'), motDePasseEssai(SECRET, 'p2'))
  assert.match(motDePasseEssai(SECRET, 'p1'), /^essai-[0-9a-f]{14}$/)
  const env = fichierEnvEssai('laravel', 'APP_ENV=production\nAPP_KEY=\nDB_CONNECTION=mysql\nDB_PASSWORD=\nADMIN_EMAIL=\n', { urlApercu: 'https://4100-x.e2b.app', motDePasse: 'essai-123' })
  assert.match(env, /^APP_ENV=local$/m)
  assert.match(env, /^DB_CONNECTION=sqlite$/m)
  assert.match(env, /^DB_PASSWORD=$/m)
  assert.match(env, /^APP_KEY=$/m)
  assert.match(env, /^APP_URL=https:\/\/4100-x\.e2b\.app$/m)
  assert.match(env, new RegExp(`^ADMIN_EMAIL=${ADMIN_ESSAI}$`, 'm'))
  assert.match(env, /^ESSAI_ADMIN_MOT_DE_PASSE=essai-123$/m)
  assert.match(fichierEnvEssai('nextjs', 'DATABASE_URL=\n', { motDePasse: 'x' }), /^DATABASE_URL=$/m)
})

test('le résultat des tests est résumé en français', () => {
  assert.equal(resumeTests('  Tests:    16 passed (70 assertions)\n  Duration: 2s'), '16 réussi(s)')
  assert.equal(resumeTests('  Tests:    1 failed, 15 passed (70 assertions)'), '1 échoué(s), 15 réussi(s)')
  assert.equal(resumeTests('      Tests  13 passed (13)\n   Start at'), '13 réussi(s)')
  assert.equal(resumeTests('      Tests  \u001b[22m \u001b[1m\u001b[32m13 passed\u001b[39m\u001b[22m\u001b[90m (13)'), '13 réussi(s)')
  assert.equal(resumeTests('rien'), null)
})

test('les consignes d’une application web rappellent la sécurité, les tests et le compte rendu', () => {
  for (const pile of ['laravel', 'nextjs']) {
    const consignes = consignesApplication(pile)
    assert.match(consignes, /user_id/)
    assert.match(consignes, /Jamais de clé, de mot de passe ni de secret dans le code/)
    assert.match(consignes, /ne lance jamais de serveur toi-même/)
    assert.match(consignes, /## Ce que j'ai fait/)
    assert.match(consignes, pile === 'laravel' ? /@csrf/ : /use server/)
  }
})

test('une application web : installation, identifiants d’essai, puis correction quand les tests échouent', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] }, { reponse: "## Ce que j'ai fait\nJ'ai ajouté le menu." })
  const verifications = [
    { tests: { ok: false, commande: 'php artisan test', resume: '1 échoué(s), 15 réussi(s)', sortie: 'FAILED ReservationTest' }, securite: [], sante: { ok: true, code: 200 } },
    { tests: { ok: true, commande: 'php artisan test', resume: '17 réussi(s)', sortie: '' }, securite: [], sante: { ok: true, code: 200 } },
  ]
  const demandes = []
  faux.ordinateurs.obtenir = async (projet, options) => {
    demandes.push(options)
    return { client: faux.client, baseUrl: 'http://faux', entetes: {}, applicationDeposee: true }
  }
  faux.ordinateurs.preparerApplication = async (projet, pile) => ({
    etapes: [{ commande: 'composer install', ok: true, secondes: 30 }],
    identifiant: ADMIN_ESSAI,
    mot_de_passe: motDePasseEssai(SECRET, projet),
  })
  faux.ordinateurs.verifierApplication = async () => verifications.shift()
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Ajoute un menu du jour', fournisseurs: [GEMINI], application: { pile: 'laravel', modele: 'reservations-restaurant' } },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.deepEqual(demandes[0].application, { pile: 'laravel', modele: 'reservations-restaurant' })
  assert.ok(evenements.some((e) => e.genre === 'application_deposee'))
  assert.ok(evenements.some((e) => e.genre === 'installation' && e.cible.startsWith('composer install')))
  assert.deepEqual(evenements.find((e) => e.type === 'application'), { type: 'application', pile: 'laravel', identifiant: ADMIN_ESSAI, mot_de_passe: motDePasseEssai(SECRET, 'p1') })
  // La demande donnée à l'agent commence par les informations de l'application.
  assert.match(faux.appels.prompts[0].texte, /Application web Laravel/)
  assert.match(faux.appels.prompts[0].texte, /Ajoute un menu du jour/)
  // Les tests échouent : l'agent reçoit la sortie des tests pour corriger.
  assert.match(faux.appels.prompts[1].texte, /Les tests échouent \(php artisan test\)/)
  assert.ok(evenements.some((e) => e.genre === 'verification_correction'))
  assert.deepEqual(
    evenements.filter((e) => ['tests_ok', 'tests_echec', 'securite_ok', 'securite_probleme', 'application_ok', 'application_erreur'].includes(e.genre)).map((e) => [e.genre, e.cible]),
    [
      ['tests_ok', 'php artisan test : 17 réussi(s)'],
      ['securite_ok', ''],
      ['application_ok', 'code 200'],
    ],
  )
  assert.equal(evenements.at(-1).type, 'terminee')
})

test('une application web mal décrite est refusée avant de démarrer un ordinateur', async () => {
  const faux = fauxOpencode({})
  let obtenu = false
  faux.ordinateurs.obtenir = async () => {
    obtenu = true
  }
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'x', fournisseurs: [GEMINI], application: { pile: 'laravel', modele: '../secret' } },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.equal(obtenu, false)
  assert.equal(evenements.at(-1).type, 'erreur')
})

test('la consigne de correction rassemble tests, sécurité et application qui ne répond pas', () => {
  const v = { tests: { ok: false, commande: 'npm test', sortie: '1 failed' }, securite: [{ genre: 'csrf', detail: 'Formulaire sans @csrf : a.blade.php' }], sante: { ok: false, code: 500, journal: 'Erreur' } }
  assert.equal(problemesApplication(v), 3)
  const texte = texteCorrectionApplication(v)
  assert.match(texte, /Les tests échouent \(npm test\)/)
  assert.match(texte, /Formulaire sans @csrf/)
  assert.match(texte, /code 500/)
  assert.match(texteApplication({ pile: 'nextjs', modele: 'socle' }, { etapes: [{ commande: 'npm ci', ok: false, sortie: 'réseau' }] }), /« npm ci » a échoué/)
})

test('le serveur fabrique le fichier ZIP d’hébergement seulement sur demande signée', async () => {
  const { creerServeur } = await import('../src/server.js')
  const { entetesSignes } = await import('../src/signature.js')
  const paquets = []
  const serveur = creerServeur({
    secret: SECRET,
    clesPresentes: new Set(['gemini']),
    ordinateurs: {
      paquet: async (projet, options) => {
        paquets.push({ projet, ...options })
        return { taille: 1234 }
      },
    },
    journal: { error() {}, warn() {}, log() {} },
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const poster = (donnees, signe = true) => {
    const corps = JSON.stringify(donnees)
    return fetch(`http://127.0.0.1:${serveur.address().port}/paquet`, { method: 'POST', body: corps, headers: signe ? entetesSignes(SECRET, corps) : {} })
  }
  const demande = { projet: '01JABC', envoi: 'http://site/runner/projets/01JABC/paquet', sauvegarde: 'http://site/runner/projets/01JABC/sauvegarde' }
  try {
    assert.equal((await poster(demande, false)).status, 401)
    assert.equal((await poster({ ...demande, envoi: 'ftp://ailleurs' })).status, 422)
    const reponse = await poster(demande)
    assert.equal(reponse.status, 200)
    assert.deepEqual(await reponse.json(), { ok: true, taille: 1234 })
    assert.equal(paquets[0].envoi, demande.envoi)
    assert.equal(paquets[0].sauvegarde, demande.sauvegarde)
  } finally {
    serveur.close()
  }
})
