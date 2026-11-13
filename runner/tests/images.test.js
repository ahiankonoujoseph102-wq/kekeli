import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, readFileSync, writeFileSync, existsSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { CONSIGNES_IMAGE, GENERATEURS, inviteAffiche, structureImage, textesAttendus, texteConforme } from '../src/documents/images.js'
import { produireDocument } from '../src/documents/index.js'
import { CONSIGNES_REPONDANT, outilsDuLieu } from '../src/repondant.js'

const ENV = { OPENAI_API_KEY: 'cle-openai-de-test', CLOUDFLARE_API_TOKEN: 'cle-cloudflare-de-test', CLOUDFLARE_ACCOUNT_ID: 'compte-de-test' }
const MOTEUR = new URL('../src/documents/rendu/kekeli_images.py', import.meta.url).pathname
const AFFICHE = { surtitre: 'Promotion', titre: 'Pagnes Ama : -20 % sur tout le wax', prix: '9 600 FCFA le pagne', prix_barre: '12 000 FCFA', contact: 'Appelez le 90 00 00 00', paiement: 'Paiement par Mixx by Yas ou Flooz' }

test('l’outil « creer_image » dans une discussion, une seule question en cas de doute, les images interdites refusées', () => {
  assert.ok(outilsDuLieu('discussion').includes('creer_image'))
  assert.ok(!outilsDuLieu('document').includes('creer_image'))
  assert.match(CONSIGNES_REPONDANT, /« creer_image »/)
  assert.match(CONSIGNES_REPONDANT, /UNE seule question courte, par exemple « Voulez-vous une affiche/)
  assert.match(CONSIGNES_REPONDANT, /personne réelle identifiable/)
  assert.match(CONSIGNES_IMAGE, /personnage protégé/)
  assert.match(CONSIGNES_IMAGE, /jamais « T-Money »/)
})

test('la structure : formats connus, textes bornés, couleurs valides, majuscules, jamais « T-Money »', () => {
  const s = structureImage({ image: { format: 'poster', usage: 'video', theme: { couleur: 'red' }, textes: { titre: 'grande promo', paiement: 'paiement par T-Money', details: ['livraison gratuite'], inconnu: 'x' } } })
  assert.equal(s.format, 'carre')
  assert.equal(s.usage, 'affiche')
  assert.equal(s.theme.couleur, '#E3A008')
  assert.equal(s.textes.titre, 'Grande promo')
  assert.equal(s.textes.paiement, 'Paiement par Mixx by Yas')
  assert.deepEqual(s.textes.details, ['Livraison gratuite'])
  assert.equal(s.textes.inconnu, undefined)
  for (const format of ['carre', 'vertical', 'paysage', 'a4', 'a3']) assert.equal(structureImage({ image: { format } }).format, format)
})

test('la relecture : chaque texte exact, aucun texte en trop (un slogan inventé), les chiffres stricts', () => {
  const attendus = textesAttendus(structureImage({ image: { textes: AFFICHE } }))
  const lus = ['PROMOTION', 'Pagnes Ama :', '-20% sur tout le wax', '12 000 FCFA', '9 600 FCFA', 'le pagne', 'Appelez le 90 00 00 00', 'Paiement par Mixx by Yas ou Flooz']
  assert.equal(texteConforme(attendus, lus).ok, true)
  assert.equal(texteConforme(attendus, lus.map((l) => l.replace('9 600', '9 800'))).ok, false, 'un prix faux est refusé')
  assert.equal(texteConforme(attendus, lus.map((l) => l.replace('Marché', 'Marche').replace('Mixx', 'Mix'))).ok, false, 'une faute est refusée')
  const enTrop = texteConforme(attendus, [...lus, 'Apprendre. Innover.'])
  assert.equal(enTrop.ok, false)
  assert.deepEqual(enTrop.enTrop, ['Apprendre. Innover.'])
})

test('la consigne d’affiche donne chaque texte exactement, et interdit tout autre texte', () => {
  const invite = inviteAffiche(structureImage({ image: { usage: 'affiche', fond: { description: 'Wax fabrics. No text, no letters, no logo.' }, textes: AFFICHE } }))
  for (const t of Object.values(AFFICHE)) assert.ok(invite.includes(`"${t}"`), t)
  assert.match(invite, /NO OTHER TEXT/)
  assert.deepEqual(GENERATEURS.filter((g) => g.startsWith('openai/')), ['openai/gpt-image-2.6-flare', 'openai/gpt-image-2.6-sunburst'])
})

const pillow = (() => {
  try {
    execFileSync('python3', ['-c', 'import PIL'])
    return true
  } catch {
    return false
  }
})()

test('le texte posé par le code est exactement celui demandé ; PNG pour chaque format, PDF pour l’impression', { skip: !pillow && 'Pillow absent' }, () => {
  const dossier = mkdtempSync(path.join(tmpdir(), 'kekeli-image-'))
  const tailles = { carre: [1080, 1080], vertical: [1080, 1920], paysage: [1200, 630], a4: [1654, 2339], a3: [2339, 3307] }
  for (const [format, taille] of Object.entries(tailles)) {
    writeFileSync(`${dossier}/s.json`, JSON.stringify(structureImage({ image: { format, textes: { ...AFFICHE, details: ['Livraison à Lomé : 1 000 FCFA'] } } })))
    const sortie = JSON.parse(execFileSync('python3', ['-I', MOTEUR, 'composer', `${dossier}/s.json`, `${dossier}/${format}`, '-', '-', '-']).toString())
    assert.deepEqual(sortie.taille, taille, format)
    assert.ok(existsSync(`${dossier}/${format}/image.png`))
    assert.equal(existsSync(`${dossier}/${format}/image.pdf`), ['a4', 'a3'].includes(format), `PDF pour ${format}`)
    const pose = sortie.textes_poses.join(' | ')
    for (const t of [AFFICHE.titre, AFFICHE.prix, AFFICHE.prix_barre, AFFICHE.contact, AFFICHE.paiement, 'Livraison à Lomé : 1 000 FCFA']) assert.ok(pose.includes(t), `${format} : « ${t} » posé exactement`)
  }
})

// Un faux atelier : il enregistre ce qui y est écrit et répond comme le moteur de composition.
function atelier() {
  const ecrits = {}
  const sandbox = {
    files: { write: async (c, v) => { ecrits[c] = v }, read: async () => new Uint8Array([1, 2, 3]) },
    commands: { run: async (c) => (c.includes(' composer ') ? { stdout: JSON.stringify({ fichiers: { png: 'image.png' }, apercus: ['apercu-1.jpg'], pages: 1, textes_poses: [] }) } : { stdout: '' }) },
  }
  return { ecrits, obtenir: async () => sandbox }
}

const reponseOpenAI = (objet, usage = { input_tokens: 800, output_tokens: 300 }) => new Response(JSON.stringify({ output: [{ type: 'message', content: [{ type: 'output_text', text: JSON.stringify(objet) }] }], usage }), { status: 200 })
const IMAGE = { message: 'Votre affiche est prête.', titre_court: 'Affiche pagnes', image: { usage: 'affiche', format: 'vertical', fond: { description: 'Wax fabrics on a stall.' }, textes: AFFICHE } }
const demande = (images, extra = {}) => ({ tache: 't1', projet: 'p1', envoi: 'https://site.test/envoi?signature=x', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6.1-sol' }], images, document: { type: 'image', action: 'creer', demande: 'Une affiche pour ma boutique de pagnes', ...extra } })

async function produire(d, appeler, envoyer = async () => null) {
  const evenements = []
  const a = atelier()
  const r = await produireDocument(d, { atelier: a, envoyer: async (e) => { evenements.push(e); return envoyer(e) }, secret: 's', clesPresentes: new Set(), appeler, env: ENV, journal: { log() {}, warn() {} } })
  return { r, evenements, ecrits: a.ecrits }
}

test('formule gratuite : le fond par Cloudflare, le texte posé par le code, l’image comptée, aucune clé dans l’atelier', async () => {
  const appels = []
  const appeler = async (url) => {
    appels.push(url)
    if (url.includes('/responses')) return reponseOpenAI(IMAGE)
    if (url.includes('cloudflare')) return new Response(JSON.stringify({ result: { image: Buffer.from('fond').toString('base64') } }), { status: 200 })
    return new Response('{}', { status: 200 })
  }
  const { r, evenements, ecrits } = await produire(demande({ generateur: 'cloudflare/flux-1-schnell', secours: 'cloudflare/flux-1-schnell' }), appeler)
  assert.equal(r.methode, 'code')
  assert.ok(appels.some((u) => u.includes('flux-1-schnell')))
  assert.ok(!appels.some((u) => u.includes('/images/')), 'aucun appel au générateur payant')
  assert.deepEqual(evenements.find((e) => e.type === 'images'), { type: 'images', nombre: 1 })
  const doc = evenements.find((e) => e.type === 'document')
  assert.equal(doc.structure.methode, 'code')
  assert.equal(doc.fichiers.fond, 'fond.jpg')
  const structure = JSON.parse(Object.entries(ecrits).find(([c]) => c.endsWith('structure.json'))[1])
  assert.equal(structure.textes.prix, '9 600 FCFA le pagne', 'le texte part tel quel au moteur de composition')
  assert.ok(!JSON.stringify(Object.values(ecrits).map(String)).includes('cle-'), 'aucune clé dans l’atelier')
  const envois = appels.filter((u) => u.startsWith('https://site.test')).map((u) => new URL(u).searchParams.get('nom'))
  assert.deepEqual(envois, ['image.png', 'fond.jpg', 'apercu-1.jpg'])
})

test('formule payante : l’affiche dessinée par le générateur, relue texte par texte, puis gardée', async () => {
  const appels = []
  const appeler = async (url, options) => {
    appels.push(url)
    if (url.endsWith('/images/generations')) return new Response(JSON.stringify({ data: [{ b64_json: Buffer.from('affiche').toString('base64') }], usage: { input_tokens: 200, output_tokens: 1400 } }), { status: 200 })
    if (url.endsWith('/responses') && String(options.body).includes('input_image')) return reponseOpenAI({ textes: ['PROMOTION', 'Pagnes Ama : -20 % sur tout le wax', '12 000 FCFA', '9 600 FCFA le pagne', 'Appelez le 90 00 00 00', 'Paiement par Mixx by Yas ou Flooz'] })
    if (url.endsWith('/responses')) return reponseOpenAI(IMAGE)
    return new Response('{}', { status: 200 })
  }
  const { r, evenements } = await produire(demande({ generateur: 'openai/gpt-image-2.6-flare', verification: { fournisseur: 'openai', modele: 'gpt-6.1-terra' }, secours: 'cloudflare/flux-1-schnell' }), appeler)
  assert.equal(r.methode, 'modele')
  assert.equal(appels.filter((u) => u.endsWith('/images/generations')).length, 1)
  const cout = evenements.find((e) => e.type === 'image_payante')
  assert.deepEqual(cout, { type: 'image_payante', fournisseur: 'openai', modele: 'gpt-image-2.6-flare', tokens_texte: 200, tokens_image_entree: 0, tokens_sortie: 1400 })
  assert.equal(evenements.filter((e) => e.type === 'requete').length, 2, 'la rédaction et la relecture sont comptées')
  assert.equal(evenements.find((e) => e.type === 'document').structure.methode, 'modele')
  assert.ok(!JSON.stringify(evenements.filter((e) => e.type === 'narration')).match(/openai|cloudflare|gpt|flux/i), 'fournisseurs cachés au client')
})

test('un texte faux ou en trop deux fois : Kekeli repasse au fond seul avec le texte posé par le code', async () => {
  let generations = 0
  const appeler = async (url, options) => {
    if (url.endsWith('/images/generations')) {
      generations++
      return new Response(JSON.stringify({ data: [{ b64_json: 'YQ==' }], usage: { output_tokens: 1000 } }), { status: 200 })
    }
    if (url.endsWith('/responses') && String(options.body).includes('input_image')) return reponseOpenAI({ textes: ['Pagnes Ama : -20 % sur tout le wax', '9 800 FCFA le pagne', 'Apprendre. Innover.'] })
    if (url.endsWith('/responses')) return reponseOpenAI(IMAGE)
    return new Response('{}', { status: 200 })
  }
  const { r } = await produire(demande({ generateur: 'openai/gpt-image-2.6-flare', verification: { fournisseur: 'openai', modele: 'gpt-6.1-terra' }, secours: 'cloudflare/flux-1-schnell' }), appeler)
  assert.equal(r.methode, 'code')
  assert.equal(generations, 3, 'deux affiches refusées, puis le fond seul')
})

test('le logo joint est posé tel quel par le code, jamais redessiné par le générateur', async () => {
  const vraiFetch = globalThis.fetch
  globalThis.fetch = async () => new Response(new Uint8Array([9, 9, 9]), { status: 200 })
  try {
    const appels = []
    const appeler = async (url) => {
      appels.push(url)
      if (url.endsWith('/images/generations')) return new Response(JSON.stringify({ data: [{ b64_json: 'YQ==' }], usage: { output_tokens: 1000 } }), { status: 200 })
      if (url.endsWith('/responses')) return reponseOpenAI({ ...IMAGE, image: { ...IMAGE.image, avec_logo: true } })
      return new Response('{}', { status: 200 })
    }
    const { r, ecrits } = await produire(demande({ generateur: 'openai/gpt-image-2.6-flare', verification: { fournisseur: 'openai', modele: 'gpt-6.1-terra' } }, { joints: [{ url: 'https://site.test/photo-signee' }] }), appeler)
    assert.equal(r.methode, 'code')
    assert.ok(Object.keys(ecrits).some((c) => c.endsWith('/logo.png')))
    assert.ok(!appels.some((u) => u.endsWith('/images/edits')))
  } finally {
    globalThis.fetch = vraiFetch
  }
})

test('une image interdite : refus poli, aucune image créée, aucun fichier', async () => {
  const appels = []
  const appeler = async (url) => {
    appels.push(url)
    return reponseOpenAI({ refus: 'Je ne peux pas créer l’image d’une personne réelle. Je peux faire une affiche avec une personne imaginaire.' })
  }
  const { r, evenements } = await produire(demande({ generateur: 'openai/gpt-image-2.6-flare' }), appeler)
  assert.equal(r.refus, true)
  assert.ok(!appels.some((u) => u.includes('/images/') || u.includes('cloudflare')))
  assert.equal(evenements.find((e) => e.type === 'document'), undefined)
  assert.match(evenements.find((e) => e.type === 'terminee').reponse, /personne imaginaire/)
})

test('l’énergie épuisée après l’image payante arrête le travail avant la composition', async () => {
  const appeler = async (url) => {
    if (url.endsWith('/images/generations')) return new Response(JSON.stringify({ data: [{ b64_json: 'YQ==' }], usage: { output_tokens: 1000 } }), { status: 200 })
    return reponseOpenAI(IMAGE)
  }
  const { r, evenements } = await produire(demande({ generateur: 'openai/gpt-image-2.6-flare', verification: { fournisseur: 'openai', modele: 'gpt-6.1-terra' } }), appeler, async (e) => (e.type === 'image_payante' ? { arreter: true } : null))
  assert.equal(r.arrete, true)
  assert.equal(evenements.find((e) => e.type === 'document'), undefined)
})

test('un générateur inconnu n’est jamais appelé', async () => {
  const appels = []
  const appeler = async (url) => {
    appels.push(url)
    if (url.includes('cloudflare')) return new Response(JSON.stringify({ result: { image: 'YQ==' } }), { status: 200 })
    return url.endsWith('/responses') ? reponseOpenAI(IMAGE) : new Response('{}', { status: 200 })
  }
  await produire(demande({ generateur: 'openai/dall-e-ultra', secours: 'autre/inconnu' }), appeler)
  assert.ok(!appels.some((u) => u.includes('/images/')))
  assert.ok(appels.some((u) => u.includes('flux-1-schnell')))
})
