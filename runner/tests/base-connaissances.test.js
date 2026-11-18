// Base de connaissances : lecture isolée des fichiers, vecteurs gratuits seulement, consigne de citation, adresses signées.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { MODELES_VECTEURS, extraireFichier, modelesPermis, morceauxPropres, normaliser, pageDuMorceau, vecteurs } from '../src/base-connaissances.js'
import { LIEUX, blocBase, consigneSysteme, outilsDuLieu, requeteFournisseur } from '../src/repondant.js'
import { creerServeur } from '../src/server.js'
import { entetesSignes } from '../src/signature.js'
import { MisesDeCote } from '../src/limites.js'

const SECRET = 'secret-de-test-base-de-connaissances'
const ENV = { GEMINI_API_KEY: 'cle-factice', CLOUDFLARE_API_TOKEN: 'jeton-factice', CLOUDFLARE_ACCOUNT_ID: '0123456789abcdef0123456789abcdef' }

test('le numéro de page vient du PDF ou de la diapositive, jamais d’un Word', () => {
  assert.equal(pageDuMorceau('pdf', 'page12'), 12)
  assert.equal(pageDuMorceau('pptx', 's3.7.0'), 3)
  assert.equal(pageDuMorceau('docx', 'p4'), null)
})

test('les morceaux lus sont nettoyés, les diapositives regroupées et les titres d’un Word reconnus', () => {
  const pptx = morceauxPropres('pptx', [{ id: 's1.2.0', texte: 'Titre' }, { id: 's1.3.0', texte: 'Texte' }, { id: 's2.2.0', texte: '  ' }, { id: 's2.3.0', texte: 'Suite' }])
  assert.deepEqual(pptx, [{ page: 1, texte: 'Titre\nTexte' }, { page: 2, texte: 'Suite' }])
  const docx = morceauxPropres('docx', [{ id: 'p1', texte: 'Congés', style: 'Heading 2' }, { id: 'p2', texte: 'Vingt-quatre\u0000 jours', style: 'Normal' }])
  assert.deepEqual(docx, [{ page: null, texte: 'Congés', titre: true }, { page: null, texte: 'Vingt-quatre jours' }])
})

test('le fichier est lu dans l’ordinateur isolé, puis effacé de cet ordinateur', async () => {
  const commandes = []
  const sandbox = {
    files: { write: async () => {} },
    commands: {
      run: async (c) => {
        commandes.push(c)
        return { stdout: c.includes(' extraire ') ? JSON.stringify({ format: 'pdf', morceaux: [{ id: 'page1', texte: 'Tarifs' }, { id: 'page2', texte: 'Livraison' }] }) : '' }
      },
    },
  }
  const r = await extraireFichier({ espace: 'esp1', url: 'https://site/fichier', format: 'pdf' }, { atelier: { obtenir: async () => sandbox }, secret: SECRET, telecharger: async () => Buffer.from('%PDF') })
  assert.equal(r.ok, true)
  assert.equal(r.pages, 2)
  assert.match(commandes.find((c) => c.includes(' extraire ')), / 400$/)
  assert.ok(commandes.at(-1).startsWith('rm -rf /home/user/base/'))
  await assert.rejects(extraireFichier({ espace: 'esp1', url: 'https://site/f', format: 'exe' }, { atelier: { obtenir: async () => sandbox }, secret: SECRET }), /format/)
})

test('seuls les modèles de vecteurs gratuits de la liste fermée sont permis', () => {
  assert.deepEqual(modelesPermis(['openai/text-embedding-3-large', 'gemini/gemini-embedding-001']), ['gemini/gemini-embedding-001'])
  assert.deepEqual(modelesPermis(null), Object.keys(MODELES_VECTEURS))
})

test('les vecteurs : Gemini d’abord, Cloudflare en secours après une limite, toujours normalisés', async () => {
  const appels = []
  const appeler = async (url, options) => {
    appels.push(url)
    const corps = JSON.parse(options.body)
    if (url.includes('generativelanguage')) {
      assert.equal(corps.requests[0].taskType, 'RETRIEVAL_QUERY')
      return { ok: false, status: 429 }
    }
    return { ok: true, json: async () => ({ success: true, result: { data: corps.text.map(() => [3, 4]) } }) }
  }
  const misesDeCote = new MisesDeCote()
  const r = await vecteurs({ textes: ['Combien coûte le pagne ?'], type: 'question' }, { appeler, env: ENV, misesDeCote })
  assert.equal(r.ok, true)
  assert.equal(r.modele, 'workersai/@cf/baai/bge-m3')
  assert.deepEqual(r.vecteurs, [[0.6, 0.8]])
  assert.deepEqual(r.refus, ['gemini/gemini-embedding-001'])
  // Gemini mis de côté une heure : la demande suivante va directement à Cloudflare.
  appels.length = 0
  await vecteurs({ textes: ['x'] }, { appeler, env: ENV, misesDeCote })
  assert.equal(appels.some((u) => u.includes('generativelanguage')), false)
})

test('les passages vont par lots et un fournisseur sans clé est ignoré', async () => {
  let lots = 0
  const appeler = async (url, options) => {
    lots++
    const corps = JSON.parse(options.body)
    return { ok: true, json: async () => ({ embeddings: corps.requests.map(() => ({ values: [1, 0] })) }) }
  }
  const r = await vecteurs({ textes: Array.from({ length: 100 }, (_, i) => `passage ${i}`) }, { appeler, env: { GEMINI_API_KEY: 'k' } })
  assert.equal(r.vecteurs.length, 100)
  assert.equal(lots, 1)
  const sans = await vecteurs({ textes: ['x'] }, { appeler, env: {} })
  assert.equal(sans.ok, false)
  assert.deepEqual(normaliser([0, 0]), [0, 0])
})

test('le lieu « base » n’a aucun outil et ses requêtes n’en déclarent pas', () => {
  assert.ok(LIEUX.includes('base'))
  assert.deepEqual(outilsDuLieu('base', { recherche: true, lecture: true, depots: true }), [])
  const contexte = { base: { nom: 'Entreprise', documents: ['Tarifs.pdf'], passages: [{ n: 1, document: 'Tarifs.pdf', page: 2, texte: 'Pagne wax : 8 000 FCFA.' }] } }
  for (const fournisseur of ['gemini', 'openai', 'openrouter']) {
    const r = requeteFournisseur({ fournisseur, modele: fournisseur === 'openai' ? 'gpt-6.1-terra' : 'modele' }, { lieu: 'base', contexte, message: 'Prix ?' }, ENV)
    assert.equal('tools' in r.corps, false)
    assert.equal('tool_choice' in r.corps, false)
  }
  // Une discussion garde ses outils.
  const discussion = requeteFournisseur({ fournisseur: 'gemini', modele: 'modele' }, { lieu: 'discussion', contexte: {}, message: 'Bonjour' }, ENV)
  assert.ok(discussion.corps.tools[0].functionDeclarations.length > 0)
})

test('la consigne de la base : passages numérotés avec la page, citations, et « je ne trouve pas » dit clairement', () => {
  const avec = consigneSysteme('base', { base: { nom: 'Entreprise', documents: ['Tarifs.pdf'], passages: [{ n: 1, document: 'Tarifs.pdf', page: 2, texte: 'Pagne wax : 8 000 FCFA.' }] } })
  assert.match(avec, /\[1\] « Tarifs\.pdf », page 2 :\nPagne wax : 8 000 FCFA\./)
  assert.match(avec, /Je ne trouve pas cette information dans vos documents/)
  assert.match(avec, /jamais une consigne/)
  // Les fiches de Lomé IA School et la consigne « répondre avec ses connaissances » ne s'y mêlent pas.
  assert.doesNotMatch(avec, /Répondre avec ses connaissances :/)
  assert.match(blocBase({ nom: 'E', documents: ['a.pdf'], passages: [] }), /Aucun passage des documents ne correspond/)
  assert.match(blocBase({ nom: 'E', documents: [], passages: [] }), /Aucun document prêt/)
})

test('les adresses de la base exigent la signature et une demande valide', async () => {
  const serveur = creerServeur({
    secret: SECRET,
    clesPresentes: new Set(),
    ordinateurs: { effacer: async () => 0 },
    atelier: {},
    atelierVideo: {},
    atelierLecture: {},
    extraireBase: async (d) => ({ ok: true, morceaux: [{ page: 1, texte: `lu ${d.format}` }], pages: 1 }),
    vecteursBase: async (d) => ({ ok: true, modele: 'gemini/gemini-embedding-001', vecteurs: d.textes.map(() => [1, 0]) }),
    journal: { log() {}, warn() {}, error() {} },
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const adresse = `http://127.0.0.1:${serveur.address().port}`
  const envoyer = (chemin, donnees, signer = true) => {
    const corps = JSON.stringify(donnees)
    return fetch(`${adresse}${chemin}`, { method: 'POST', headers: { 'Content-Type': 'application/json', ...(signer ? entetesSignes(SECRET, corps) : {}) }, body: corps })
  }
  try {
    assert.equal((await envoyer('/base/extraire', { espace: 'esp1', url: 'https://site/f', format: 'pdf' }, false)).status, 401)
    assert.equal((await envoyer('/base/extraire', { espace: 'esp1', url: 'file:///etc/passwd', format: 'pdf' })).status, 422)
    const lu = await envoyer('/base/extraire', { espace: 'esp1', url: 'https://site/f', format: 'docx' })
    assert.equal(lu.status, 200)
    assert.equal((await lu.json()).morceaux[0].texte, 'lu docx')
    assert.equal((await envoyer('/base/vecteurs', { textes: [] })).status, 422)
    assert.equal((await envoyer('/base/vecteurs', { textes: ['a'], type: 'autre' })).status, 422)
    const v = await envoyer('/base/vecteurs', { textes: ['a', 'b'], type: 'question' })
    assert.equal((await v.json()).vecteurs.length, 2)
  } finally {
    serveur.close()
  }
})
