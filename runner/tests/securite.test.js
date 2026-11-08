import { test } from 'node:test'
import assert from 'node:assert/strict'
import http from 'node:http'
import { mkdtemp, mkdir, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { demandeDeCorrection, verifierProjet } from '../src/licences/verifier.mjs'
import { SITES_LIBRES, SITES_SYSTEME, domaineValide, sitesAutorises } from '../src/reseau.js'
import { envoyer as envoyerSauvegarde, morceaux, telecharger } from '../src/sauvegarde.js'
import { entetesSignes, verifier } from '../src/signature.js'
import { COMMANDES_RISQUEES, codeVerificateur, configurationOpencode } from '../src/ordinateurs.js'
import { descriptionPermission, executerTache, texteAvecHistorique } from '../src/agent.js'
import { MisesDeCote } from '../src/limites.js'
import { creerRegistrePermissions, creerServeur } from '../src/server.js'
import { RIPGREP, archiveRipgrep } from '../src/outils.js'
import { fauxOpencode, finRequete, libre, occupe, outil } from './outils.js'

const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789'
const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
const silencieux = { error() {}, warn() {}, log() {} }

async function projet(fichiers) {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-licences-'))
  for (const [nom, contenu] of Object.entries(fichiers)) {
    await mkdir(path.dirname(path.join(dossier, nom)), { recursive: true })
    await writeFile(path.join(dossier, nom), contenu)
  }
  return dossier
}

// Licences

test('un projet sans ressource externe ni média n’a pas besoin de LICENCES.md', async () => {
  const dossier = await projet({ 'index.html': '<h1>Bonjour</h1><a href="https://wa.me/22890000000">WhatsApp</a>', 'style.css': 'h1 { color: red; }' })
  try {
    assert.deepEqual(await verifierProjet(dossier), { ok: true, fichier: null, ressources: 0, problemes: [] })
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('une photo d’une source libre doit être notée dans LICENCES.md', async () => {
  const photo = 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=1200&q=80'
  const dossier = await projet({ 'index.html': `<img src="${photo}" alt="Salle">` })
  try {
    let resultat = await verifierProjet(dossier)
    assert.equal(resultat.ok, false)
    assert.deepEqual(resultat.problemes.map((p) => p.genre), ['fichier_licences_absent', 'adresse_non_documentee'])

    await writeFile(path.join(dossier, 'LICENCES.md'), '# Licences\n- https://images.unsplash.com/photo-1517248135467-4c7edcad34c4 — Unsplash, Licence Unsplash\n')
    resultat = await verifierProjet(dossier)
    assert.deepEqual(resultat, { ok: true, fichier: 'LICENCES.md', ressources: 1, problemes: [] })
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('une image d’un site qui n’en donne pas le droit est signalée, même notée', async () => {
  const dossier = await projet({
    'index.html': '<img src="https://www.site-dun-concurrent.com/photo.jpg">',
    'style.css': '.fond { background: url("https://autre-site.example/fond.png") }',
    'LICENCES.md': 'https://www.site-dun-concurrent.com/photo.jpg — trouvée sur Internet',
  })
  try {
    const resultat = await verifierProjet(dossier)
    assert.equal(resultat.ok, false)
    assert.deepEqual(resultat.problemes.map((p) => [p.genre, p.fichier]), [
      ['source_non_libre', 'index.html'],
      ['source_non_libre', 'style.css'],
    ])
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('fichiers de médias, polices Google, bibliothèques et cartes intégrées', async () => {
  const dossier = await projet({
    'index.html': [
      '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap">',
      '<script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.400.0/lucide.min.js"></script>',
      '<iframe src="https://www.google.com/maps/embed?pb=xyz"></iframe>',
      '<img src="images/plat.jpg">',
    ].join('\n'),
    'images/plat.jpg': 'octets',
    'sons/ambiance.mp3': 'octets',
  })
  try {
    let resultat = await verifierProjet(dossier)
    assert.deepEqual(resultat.problemes.map((p) => [p.genre, p.cible]).sort(), [
      ['adresse_non_documentee', 'https://cdnjs.cloudflare.com/ajax/libs/lucide/0.400.0/lucide.min.js'],
      ['adresse_non_documentee', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap'],
      ['fichier_licences_absent', 'LICENCES.md'],
      ['fichier_non_documente', 'images/plat.jpg'],
      ['fichier_non_documente', 'sons/ambiance.mp3'],
    ])

    await writeFile(path.join(dossier, 'LICENCES.md'), [
      '- Police Poppins — Google Fonts — SIL Open Font License',
      '- https://cdnjs.cloudflare.com/ajax/libs/lucide/0.400.0/lucide.min.js — Lucide, licence ISC',
      '- plat.jpg — Pexels (https://www.pexels.com/photo/123), Licence Pexels',
      '- ambiance.mp3 — Pixabay, Licence du contenu Pixabay',
    ].join('\n'))
    resultat = await verifierProjet(dossier)
    assert.equal(resultat.ok, true)
    assert.equal(resultat.ressources, 4)
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('la demande de correction explique chaque problème à l’agent', () => {
  const texte = demandeDeCorrection({
    problemes: [
      { genre: 'fichier_licences_absent', fichier: 'LICENCES.md', cible: 'LICENCES.md' },
      { genre: 'source_non_libre', fichier: 'index.html', cible: 'https://exemple.com/a.jpg' },
      { genre: 'fichier_non_documente', fichier: 'logo.png', cible: 'logo.png' },
    ],
  })
  assert.match(texte, /LICENCES\.md n'existe pas/)
  assert.match(texte, /https:\/\/exemple\.com\/a\.jpg.*ne vient pas d'une source libre/)
  assert.match(texte, /« logo\.png » n'est pas noté/)
  assert.match(texte, /Ne copie jamais une image ou un texte/)
})

test('le vérificateur copié dans l’ordinateur virtuel est autonome', async () => {
  const code = await codeVerificateur()
  assert.ok(code.includes('verifierProjet'))
  assert.ok(!/from '\.\.?\//.test(code))
})

// Sites autorisés et permissions

test('les sites autorisés : système, fournisseur avec clé, sources libres, puis ceux de l’utilisateur', () => {
  const sites = sitesAutorises(['api.exemple.com', 'API.EXEMPLE.COM', 'pas un site', 'http://x.com', 'images.unsplash.com'], new Set(['gemini']))
  assert.deepEqual(sites, [...SITES_SYSTEME, 'generativelanguage.googleapis.com', ...SITES_LIBRES, 'api.exemple.com'])
  assert.ok(!sites.includes('openrouter.ai'))
  assert.ok(domaineValide('sous.domaine.exemple.tg'))
  for (const mauvais of ['localhost', '192.168.1.1', '-x.com', 'x..com', '*.exemple.com', 'exemple.com/chemin']) assert.ok(!domaineValide(mauvais), mauvais)
})

test('les commandes risquées demandent la permission, les autres sont permises', () => {
  const bash = configurationOpencode(new Set(['gemini'])).permission.bash
  assert.equal(bash['*'], 'allow')
  for (const motif of ['rm -rf *', 'git push*', 'curl *', 'sudo *']) assert.equal(bash[motif], 'ask', motif)
  assert.equal(Object.keys(bash)[0], '*')
  assert.equal(Object.keys(bash).length, COMMANDES_RISQUEES.length + 1)
  assert.deepEqual(descriptionPermission({ permission: 'bash', patterns: ['rm -rf images'], metadata: { command: 'rm -rf /home/user/projet/images' } }), { outil: 'bash', cible: 'rm -rf images' })
})

test('une permission attend la réponse de l’utilisateur, puis est refusée d’office au bout du délai', async () => {
  const registre = creerRegistrePermissions({ delaiMs: 30 })
  const reponse = registre.attendre('p1', 'per_1')
  assert.equal(registre.decider('p1', 'per_2', 'autoriser'), false)
  assert.equal(registre.decider('p2', 'per_1', 'autoriser'), false)
  assert.equal(registre.decider('p1', 'per_1', 'autoriser'), true)
  assert.equal(await reponse, 'autoriser')
  assert.equal(registre.decider('p1', 'per_1', 'refuser'), false)
  assert.equal(await registre.attendre('p1', 'per_3'), 'expiree')
})

test('pendant une tâche, la demande de permission part vers le site et la réponse revient à l’agent', async () => {
  const demande = { type: 'permission.asked', properties: { id: 'per_1', sessionID: 'ses_test', permission: 'bash', patterns: ['rm -rf images'], metadata: { command: 'rm -rf images' } } }
  // Comme le vrai OpenCode : après la question, rien ne se passe tant que l'utilisateur n'a pas répondu.
  const scenarios = { [GEMINI.modele]: [occupe, demande] }
  const faux = fauxOpencode(scenarios)
  const registre = creerRegistrePermissions()
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Supprime le dossier images', fournisseurs: [GEMINI] },
    {
      ordinateurs: faux.ordinateurs,
      permissions: registre,
      misesDeCote: new MisesDeCote(),
      clesPresentes: new Set(['gemini']),
      journal: silencieux,
      envoyer: async (e) => {
        evenements.push(e)
        if (e.type === 'permission') {
          scenarios[GEMINI.modele] = [occupe, finRequete('r2'), libre]
          registre.decider('p1', e.id, 'refuser')
          setTimeout(() => {
            faux.pousser(finRequete('r1'))
            faux.pousser(libre)
          }, 20)
        }
      },
    },
  )
  for (let i = 0; i < 300 && !evenements.some((e) => e.type === 'permission_reglee'); i++) await new Promise((r) => setTimeout(r, 10))
  assert.deepEqual(evenements.find((e) => e.type === 'permission'), { type: 'permission', id: 'per_1', outil: 'bash', cible: 'rm -rf images' })
  assert.deepEqual(evenements.find((e) => e.type === 'permission_reglee'), { type: 'permission_reglee', id: 'per_1', decision: 'refuser' })
  // L'agent est relancé pour tenir compte du refus et l'expliquer.
  assert.equal(faux.appels.prompts.length, 2)
  assert.match(faux.appels.prompts[1].texte, /L'utilisateur a refusé cette action : « rm -rf images »/)
})

test('une tâche se termine même si OpenCode n’a jamais annoncé « occupé » (première action refusée)', async () => {
  const sid = 'ses_test'
  const outilRefuse = { type: 'message.part.updated', properties: { part: { id: 'o1', sessionID: sid, type: 'tool', tool: 'bash', state: { status: 'error', input: {} } } } }
  const faux = fauxOpencode({ [GEMINI.modele]: [outilRefuse, finRequete('r1'), libre] })
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Lance rm -rf images', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux, dureeMaxMs: 2000 },
  )
  assert.equal(evenements.at(-1).type, 'terminee')
})

test('si la fin de travail se perd, le Runner la retrouve en demandant l’état à OpenCode', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1')] })
  faux.client.session.status = async () => ({ data: {} })
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Bonjour', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux, dureeMaxMs: 3000, delaiVerificationMs: 50 },
  )
  assert.equal(evenements.at(-1).type, 'terminee')
})

// Reprise, licences et sauvegarde pendant une tâche

test('un projet remis en place depuis sa sauvegarde reçoit le contexte des échanges précédents', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  const evenements = []
  const ordinateurs = { obtenir: async () => ({ ...(await faux.ordinateurs.obtenir()), restaure: true }) }
  await executerTache(
    {
      projet: 'p1',
      message: 'Ajoute une page contact',
      fournisseurs: [GEMINI],
      historique: [
        { role: 'user', contenu: 'Crée la page d’accueil de mon restaurant' },
        { role: 'assistant', contenu: 'La page est créée.' },
      ],
    },
    { ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux },
  )
  const texte = faux.appels.prompts[0].texte
  assert.match(texte, /Ce projet existe déjà/)
  assert.match(texte, /Utilisateur : Crée la page d’accueil de mon restaurant/)
  assert.match(texte, /Kekeli : La page est créée\./)
  assert.ok(texte.trim().endsWith('Ajoute une page contact'))
  assert.ok(evenements.some((e) => e.type === 'etape' && e.genre === 'restauration'))
  assert.equal(texteAvecHistorique('Bonjour', []), 'Bonjour')
})

test('un ordinateur réveillé après une pause est signalé, sans contexte ajouté', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  const evenements = []
  const ordinateurs = { obtenir: async () => ({ ...(await faux.ordinateurs.obtenir()), repris: true }) }
  await executerTache(
    { projet: 'p1', message: 'Ajoute une page contact', fournisseurs: [GEMINI], historique: [{ role: 'user', contenu: 'avant' }] },
    { ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux },
  )
  assert.equal(faux.appels.prompts[0].texte, 'Ajoute une page contact')
  assert.ok(evenements.some((e) => e.type === 'etape' && e.genre === 'reprise'))
})

test('après le travail : licences vérifiées, une correction demandée à l’agent, puis sauvegarde', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, outil('write', { filePath: '/home/user/projet/index.html' }, 'p1'), finRequete('r1'), libre] })
  const verifications = [
    { ok: false, fichier: null, problemes: [{ genre: 'fichier_licences_absent', fichier: 'LICENCES.md', cible: 'LICENCES.md' }] },
    { ok: true, fichier: 'LICENCES.md', problemes: [] },
  ]
  const sauvegardes = []
  const ordinateurs = {
    obtenir: faux.ordinateurs.obtenir,
    verifierLicences: async () => verifications.shift(),
    sauvegarder: async (projetId, url) => {
      sauvegardes.push([projetId, url])
      return { taille: 2048, parties: 1 }
    },
  }
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Crée une page', fournisseurs: [GEMINI], sauvegarde: { envoi: 'http://site/runner/projets/p1/sauvegarde' } },
    { ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux },
  )
  assert.equal(faux.appels.prompts.length, 2)
  assert.match(faux.appels.prompts[1].texte, /Vérification des licences/)
  const etapes = evenements.filter((e) => e.type === 'etape').map((e) => [e.genre, e.cible])
  assert.deepEqual(etapes, [
    ['fichier_ecrit', 'index.html'],
    ['licences_correction', '1'],
    ['licences_ok', 'LICENCES.md'],
    ['sauvegarde', '2 Ko'],
  ])
  assert.deepEqual(sauvegardes, [['p1', 'http://site/runner/projets/p1/sauvegarde']])
  assert.equal(evenements.at(-1).type, 'terminee')
})

test('une sauvegarde ratée est signalée sans faire échouer la demande', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  const ordinateurs = { obtenir: faux.ordinateurs.obtenir, sauvegarder: async () => { throw new Error('réseau') } }
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Bonjour', fournisseurs: [GEMINI], sauvegarde: { envoi: 'http://site/s' } },
    { ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: silencieux },
  )
  assert.ok(evenements.some((e) => e.type === 'etape' && e.genre === 'echec_sauvegarde'))
  assert.equal(evenements.at(-1).type, 'terminee')
})

test('l’outil de recherche est refusé si son empreinte ne correspond pas', async () => {
  const faux = (octets) => async (url) => {
    assert.equal(url, RIPGREP.url)
    return new Response(octets)
  }
  await assert.rejects(archiveRipgrep({ telecharger: faux(Buffer.from('archive piégée')) }), /empreinte/)
  const bonne = Buffer.from('archive de test')
  const empreinte = (await import('node:crypto')).createHash('sha256').update(bonne).digest('hex')
  assert.ok((await archiveRipgrep({ telecharger: faux(bonne), attendu: empreinte })).equals(bonne))
})

// Transfert des sauvegardes

test('une sauvegarde est envoyée en morceaux signés, puis téléchargée', async () => {
  assert.deepEqual(morceaux(Buffer.from('abcdefghij'), 4).map((m) => m.toString()), ['abcd', 'efgh', 'ij'])
  assert.equal(morceaux(Buffer.alloc(0)).length, 1)

  const recus = []
  const serveur = http.createServer((req, res) => {
    const parts = []
    req.on('data', (m) => parts.push(m))
    req.on('end', () => {
      const corps = Buffer.concat(parts)
      const signe = verifier(SECRET, req.headers['x-kekeli-horodatage'], req.headers['x-kekeli-signature'], req.method === 'GET' ? '' : corps)
      if (!signe) return res.writeHead(401).end()
      if (req.method === 'GET') return recus.length ? res.writeHead(200).end(Buffer.concat(recus.map((r) => r.corps))) : res.writeHead(404).end()
      recus.push({ url: req.url, corps })
      res.writeHead(200).end('{"ok":true}')
    })
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const base = `http://127.0.0.1:${serveur.address().port}/sauvegarde`
  try {
    assert.equal(await telecharger(base, SECRET), null)
    const octets = Buffer.from(Array.from({ length: 9 * 1024 * 1024 }, (_, i) => i % 251))
    assert.deepEqual(await envoyerSauvegarde(base, SECRET, octets), { taille: octets.length, parties: 3 })
    assert.deepEqual(recus.map((r) => r.url), ['/sauvegarde?partie=1&parties=3', '/sauvegarde?partie=2&parties=3', '/sauvegarde?partie=3&parties=3'])
    assert.ok((await telecharger(base, SECRET)).equals(octets))
    await assert.rejects(envoyerSauvegarde(base, 'un-autre-secret-tout-aussi-long-0123456789', octets, { essais: 1 }))
  } finally {
    serveur.close()
  }
})

test('la signature fonctionne aussi pour des octets', () => {
  const octets = Buffer.from([0, 255, 10, 13, 200])
  const entetes = entetesSignes(SECRET, octets)
  assert.ok(verifier(SECRET, entetes['X-Kekeli-Horodatage'], entetes['X-Kekeli-Signature'], octets))
  assert.ok(!verifier(SECRET, entetes['X-Kekeli-Horodatage'], entetes['X-Kekeli-Signature'], Buffer.from([0, 255, 10, 13, 201])))
})

// Adresses du Runner

test('le serveur transmet les permissions, rallume l’aperçu et efface les projets, sur demande signée', async () => {
  const registre = creerRegistrePermissions()
  const envoyes = []
  const effaces = []
  const ordinateurs = {
    obtenir: async (projet, options) => ({ apercu: { url: 'https://4100-abc.e2b.app' }, expireA: new Date('2026-11-09T10:30:00Z'), options }),
    effacer: async (projet) => {
      effaces.push(projet)
      return 2
    },
  }
  const serveur = creerServeur({
    secret: SECRET,
    clesPresentes: new Set(['gemini']),
    ordinateurs,
    permissions: registre,
    creerEnvoi: ({ url, tache }) => async (e) => envoyes.push({ rappel: url, tache, ...e }),
    journal: silencieux,
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const base = `http://127.0.0.1:${serveur.address().port}`
  const poster = (chemin, donnees, signe = true) => {
    const corps = JSON.stringify(donnees)
    return fetch(base + chemin, { method: 'POST', body: corps, headers: signe ? entetesSignes(SECRET, corps) : {} })
  }
  try {
    const attente = registre.attendre('01JABC', 'per_1')
    assert.equal((await poster('/permissions', { projet: '01JABC', permission: 'per_1', decision: 'autoriser' }, false)).status, 401)
    assert.equal((await poster('/permissions', { projet: '01JABC', permission: 'per_9', decision: 'autoriser' })).status, 404)
    assert.equal((await poster('/permissions', { projet: '01JABC', permission: 'per_1', decision: 'autoriser' })).status, 200)
    assert.equal(await attente, 'autoriser')

    assert.equal((await poster('/apercus', { projet: '../x', rappel: 'http://site/r' })).status, 422)
    assert.equal((await poster('/apercus', { projet: '01JABC', rappel: 'http://site/runner/projets/01JABC/evenements', sites: ['api.exemple.com'] })).status, 202)
    await new Promise((r) => setTimeout(r, 20))
    assert.deepEqual(envoyes, [
      { rappel: 'http://site/runner/projets/01JABC/evenements', tache: null, type: 'apercu', url: 'https://4100-abc.e2b.app', expire_a: '2026-11-09T10:30:00.000Z' },
    ])

    assert.equal((await poster('/projets/effacer', { projet: '01JABC' }, false)).status, 401)
    const reponse = await poster('/projets/effacer', { projet: '01JABC' })
    assert.deepEqual(await reponse.json(), { effaces: 2 })
    assert.deepEqual(effaces, ['01JABC'])
  } finally {
    serveur.close()
  }
})
