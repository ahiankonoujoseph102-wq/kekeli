import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, mkdir, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { adresseRecherche, chercherPhotos, licenceLibre, ligneLicence, photosDepuisReponse } from '../src/outils/photos.mjs'
import { explication, imagesExternes, verifierProjet } from '../src/qualite/verifier.mjs'
import { imageExiste, imagesIntrouvables, originalWikimedia } from '../src/qualite/images.js'
import { codeOutil, CONSIGNES_AGENT } from '../src/ordinateurs.js'
import { executerTache, texteDeCorrection } from '../src/agent.js'
import { MisesDeCote } from '../src/limites.js'
import { SITES_LIBRES } from '../src/reseau.js'
import { SOURCES_LIBRES } from '../src/licences/verifier.mjs'
import { traduireOutil } from '../src/etapes.js'
import { fauxOpencode, finRequete, libre, occupe } from './outils.js'

const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
const silencieux = { error() {}, warn() {}, log() {} }

// Recherche de photos

const reponseCommons = {
  query: {
    pages: {
      1: { index: 2, title: 'File:Plat.jpg', imageinfo: [{ mime: 'image/jpeg', width: 3000, height: 2000, thumburl: 'https://thumb.wikimedia.org/plat.jpg?utm_source=commons', thumbwidth: 1600, thumbheight: 1067, descriptionurl: 'https://commons.wikimedia.org/wiki/File:Plat.jpg', extmetadata: { LicenseShortName: { value: 'CC BY-SA 4.0' }, Artist: { value: '<a href="x">Ama</a>' }, ObjectName: { value: 'Plat de fufu' } } }] },
      2: { index: 1, title: 'File:Interdit.jpg', imageinfo: [{ mime: 'image/jpeg', width: 3000, thumburl: 'https://thumb.wikimedia.org/interdit.jpg', extmetadata: { LicenseShortName: { value: 'CC BY-NC 2.0' } } }] },
      3: { index: 3, title: 'File:Petit.jpg', imageinfo: [{ mime: 'image/jpeg', width: 300, thumburl: 'https://thumb.wikimedia.org/petit.jpg', extmetadata: { LicenseShortName: { value: 'CC0' } } }] },
      4: { index: 4, title: 'File:Carte.svg', imageinfo: [{ mime: 'image/svg+xml', width: 3000, url: 'https://upload.wikimedia.org/carte.svg', extmetadata: { LicenseShortName: { value: 'CC0' } } }] },
      5: { index: 5, title: 'File:Marche.jpg', imageinfo: [{ mime: 'image/jpeg', width: 2000, height: 1500, thumburl: 'https://thumb.wikimedia.org/marche.jpg', descriptionurl: 'https://commons.wikimedia.org/wiki/File:Marche.jpg', extmetadata: { LicenseShortName: { value: 'Public domain' } } }] },
    },
  },
}

test('seules les photos sous licence libre, assez grandes, sont gardées', () => {
  for (const libre of ['CC0', 'Public domain', 'CC BY 2.0', 'CC BY-SA 4.0', 'PD-US']) assert.ok(licenceLibre(libre), libre)
  for (const interdite of ['CC BY-NC 2.0', 'CC BY-ND 4.0', 'CC BY-NC-SA 3.0', 'GFDL', 'Copyrighted', '', 'Fair use']) assert.ok(!licenceLibre(interdite), interdite)

  const photos = photosDepuisReponse(reponseCommons)
  assert.deepEqual(photos.map((p) => p.adresse), ['https://thumb.wikimedia.org/plat.jpg', 'https://thumb.wikimedia.org/marche.jpg'])
  assert.equal(photos[0].auteur, 'Ama')
  assert.equal(photos[0].titre, 'Plat de fufu')
  assert.equal(photos[1].auteur, 'Auteur inconnu')
  assert.equal(ligneLicence(photos[0]), '- https://thumb.wikimedia.org/plat.jpg — « Plat de fufu » par Ama, licence CC BY-SA 4.0, source : Wikimedia Commons (https://commons.wikimedia.org/wiki/File:Plat.jpg)')
  assert.match(adresseRecherche('restaurant food'), /^https:\/\/commons\.wikimedia\.org\/w\/api\.php\?.*gsrsearch=restaurant\+food\+filetype%3Abitmap/)
})

test('l’outil de photos ne renvoie que des adresses qui répondent vraiment', async () => {
  const appels = []
  const telecharger = async (adresse, options = {}) => {
    appels.push([options.method ?? 'GET', adresse])
    if (adresse.startsWith('https://commons.wikimedia.org')) return new Response(JSON.stringify(reponseCommons), { headers: { 'content-type': 'application/json' } })
    if (adresse.endsWith('plat.jpg')) return new Response(null, { status: 404 })
    return new Response(null, { status: 200, headers: { 'content-type': 'image/jpeg' } })
  }
  const photos = await chercherPhotos('restaurant', 4, telecharger)
  assert.deepEqual(photos.map((p) => p.adresse), ['https://thumb.wikimedia.org/marche.jpg'])
  assert.ok(appels.some(([methode, adresse]) => methode === 'HEAD' && adresse.endsWith('plat.jpg')))
})

// Vérificateur de qualité

async function projet(fichiers) {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-qualite-'))
  for (const [nom, contenu] of Object.entries(fichiers)) {
    await mkdir(path.dirname(path.join(dossier, nom)), { recursive: true })
    await writeFile(path.join(dossier, nom), contenu)
  }
  return dossier
}

const PAGE_PROPRE = (corps) => `<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Chez Ama</title><meta name="description" content="Restaurant togolais à Lomé, ouvert tous les jours."><link rel="stylesheet" href="css/style.css"></head><body>${corps}</body></html>`

test('un site propre passe la vérification de qualité', async () => {
  const dossier = await projet({
    'index.html': PAGE_PROPRE('<nav><a href="menu.html">Menu</a> <a href="#contact">Contact</a> <a href="https://wa.me/22890000000">WhatsApp</a> <a href="tel:+22890000000">Appeler</a></nav><img src="https://thumb.wikimedia.org/plat.jpg" alt="Plat de fufu">'),
    'menu.html': PAGE_PROPRE('<a href="index.html">Accueil</a><img src="images/plat.jpg" alt="Plat">'),
    'images/plat.jpg': 'octets',
    'css/style.css': '.accueil { background: url("https://thumb.wikimedia.org/fond.jpg") }',
  })
  try {
    const resultat = await verifierProjet(dossier)
    assert.equal(resultat.ok, true, JSON.stringify(resultat.problemes))
    assert.equal(resultat.pages, 2)
    assert.deepEqual(resultat.images.sort(), ['https://thumb.wikimedia.org/fond.jpg', 'https://thumb.wikimedia.org/plat.jpg'])
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('les manques d’un site sont trouvés et expliqués à l’agent', async () => {
  const dossier = await projet({
    'accueil.html': '<html><head></head><body><p>Lorem ipsum dolor sit amet</p><img src="images/absente.jpg"><a href="contact.html">Contact</a></body></html>',
  })
  try {
    const resultat = await verifierProjet(dossier)
    assert.equal(resultat.ok, false)
    assert.deepEqual(resultat.problemes.map((p) => p.genre), [
      'accueil_absent', 'langue_absente', 'telephone_absent', 'titre_absent', 'description_absente', 'image_sans_texte', 'lien_casse', 'lien_casse', 'texte_provisoire',
    ])
    const lignes = resultat.problemes.map(explication)
    assert.match(lignes[0], /pas de page d'accueil index\.html/)
    assert.match(lignes.find((l) => l.includes('contact.html')), /n'existe pas dans le projet/)
    assert.match(explication({ genre: 'image_introuvable', fichier: 'site', cible: 'https://x.jpg' }), /adresse inventée ou cassée/)
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('les images externes sont trouvées dans les balises, les jeux de tailles et les feuilles de style', () => {
  const images = imagesExternes('<img src="https://a.org/1.jpg" srcset="https://a.org/2.jpg 1x, https://a.org/3.jpg 2x"><picture><source srcset="https://a.org/4.webp"></picture><video poster="https://a.org/5.jpg"></video><div style="background:url(https://a.org/6.jpg)"></div><img src="local.jpg"><a href="https://a.org/page">lien</a>')
  assert.deepEqual(images.sort(), ['1', '2', '3', '4', '5', '6'].map((n) => `https://a.org/${n}.${n === '4' ? 'webp' : 'jpg'}`).sort())
})

// Vérification des images depuis le Runner

test('une adresse d’image inventée ou qui n’est pas une image est repérée', async () => {
  const telecharger = async (adresse, { method }) => {
    if (adresse.includes('inventee')) return new Response(null, { status: 404 })
    if (adresse.includes('page')) return new Response(null, { status: 200, headers: { 'content-type': 'text/html' } })
    if (adresse.includes('sans-head')) return method === 'HEAD' ? new Response(null, { status: 405 }) : new Response('x', { status: 206, headers: { 'content-type': 'image/png' } })
    if (adresse.includes('panne')) throw new Error('réseau')
    return new Response(null, { status: 200, headers: { 'content-type': 'image/jpeg' } })
  }
  assert.equal(await imageExiste('https://a.org/bonne.jpg', telecharger), true)
  assert.equal(await imageExiste('https://a.org/sans-head.png', telecharger), true)
  assert.deepEqual(await imagesIntrouvables(['https://a.org/bonne.jpg', 'https://a.org/inventee.jpg', 'https://a.org/page', 'https://a.org/panne.jpg', 'https://a.org/bonne.jpg'], telecharger), [
    'https://a.org/inventee.jpg', 'https://a.org/page', 'https://a.org/panne.jpg',
  ])
})

// Correction groupée pendant une tâche

test('les problèmes de qualité et les images cassées sont corrigés en une seule demande, puis revérifiés', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  const verificationsQualite = [
    { ok: false, pages: 1, images: ['https://images.unsplash.com/photo-inventee'], problemes: [{ genre: 'description_absente', fichier: 'index.html', cible: '' }] },
    { ok: true, pages: 1, images: ['https://thumb.wikimedia.org/plat.jpg'], problemes: [] },
  ]
  const ordinateurs = {
    obtenir: faux.ordinateurs.obtenir,
    verifierLicences: async () => ({ ok: true, fichier: 'LICENCES.md', problemes: [] }),
    verifierQualite: async () => verificationsQualite.shift(),
  }
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Crée une page', fournisseurs: [GEMINI] },
    {
      ordinateurs,
      envoyer: async (e) => evenements.push(e),
      misesDeCote: new MisesDeCote(),
      clesPresentes: new Set(['gemini']),
      journal: silencieux,
      verifierImages: async (adresses) => adresses.filter((a) => a.includes('inventee')),
    },
  )
  assert.equal(faux.appels.prompts.length, 2)
  const correction = faux.appels.prompts[1].texte
  assert.match(correction, /Vérification de la qualité/)
  assert.match(correction, /description pour les moteurs de recherche/)
  assert.match(correction, /photo-inventee.*ne répond pas/)
  assert.match(correction, /photos\.mjs/)
  assert.doesNotMatch(correction, /Vérification des licences/)
  assert.deepEqual(evenements.filter((e) => e.type === 'etape').map((e) => [e.genre, e.cible]), [
    ['verification_correction', '2'],
    ['licences_ok', 'LICENCES.md'],
    ['qualite_ok', '1 page(s), 1 image(s) vérifiée(s)'],
  ])
})

test('la correction regroupe licences et qualité', () => {
  const texte = texteDeCorrection({
    licences: { ok: false, problemes: [{ genre: 'fichier_licences_absent', fichier: 'LICENCES.md', cible: 'LICENCES.md' }] },
    qualite: [{ genre: 'titre_absent', fichier: 'index.html', cible: '' }],
  })
  assert.match(texte, /Vérification des licences[\s\S]*Vérification de la qualité[\s\S]*titre de page/)
})

// Outils déposés dans l'ordinateur

test('les outils de Kekeli sont autonomes et les miniatures de Wikimedia sont autorisées', async () => {
  for (const outil of ['outils/photos.mjs', 'qualite/verifier.mjs']) {
    const code = await codeOutil(outil)
    assert.ok(!/from '\.\.?\//.test(code), outil)
  }
  assert.match(await codeOutil('modeles/base.css'), /--couleur-principale/)
  assert.ok(SITES_LIBRES.includes('thumb.wikimedia.org'))
  assert.ok(SOURCES_LIBRES['thumb.wikimedia.org'])
  assert.match(CONSIGNES_AGENT, /n'invente jamais une adresse d'image/)
  assert.match(CONSIGNES_AGENT, /node \/home\/user\/\.kekeli\/photos\.mjs/)
})

test('la recherche de photos est montrée avec ses mots-clés', () => {
  const partie = { type: 'tool', tool: 'bash', state: { status: 'completed', input: { command: 'node /home/user/.kekeli/photos.mjs "restaurant interior" 4' } } }
  assert.deepEqual(traduireOutil(partie), { genre: 'photos', cible: 'restaurant interior' })
})

test('une description qui contient une apostrophe, ou placée avant le nom, est bien reconnue', async () => {
  const existe = async () => true
  const page = (meta) => `<html lang="fr"><head><meta name="viewport" content="width=device-width"><title>Le Palmier</title>${meta}</head><body></body></html>`
  for (const meta of [
    `<meta name="description" content="Découvrez l'histoire du restaurant Le Palmier à Lomé.">`,
    `<meta content="Découvrez l'histoire du restaurant Le Palmier à Lomé." name="description">`,
    `<meta name='description' content='Le restaurant « Le Palmier », cuisine togolaise à Lomé.'>`,
  ]) {
    const { verifierPage } = await import('../src/qualite/verifier.mjs')
    assert.deepEqual((await verifierPage('apropos.html', page(meta), existe)).map((p) => p.genre), [], meta)
  }
  const { verifierPage } = await import('../src/qualite/verifier.mjs')
  assert.deepEqual((await verifierPage('a.html', page('<meta name="description" content="Trop court">'), existe)).map((p) => p.genre), ['description_absente'])
})

test('une image de Wikimedia en taille d’origine est refusée : seules les miniatures standard sont acceptées', async () => {
  const original = 'https://upload.wikimedia.org/wikipedia/commons/d/dd/Plat.jpg'
  const miniature = 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dd/Plat.jpg/1280px-Plat.jpg'
  assert.equal(originalWikimedia(original), true)
  assert.equal(originalWikimedia(miniature), false)
  assert.equal(originalWikimedia('pas une adresse'), false)
  const telecharger = async () => new Response(null, { status: 200, headers: { 'content-type': 'image/jpeg' } })
  assert.deepEqual(await imagesIntrouvables([original, miniature], telecharger), [original])
  // L'outil de photos ne propose jamais l'original quand l'image est trop petite pour avoir une miniature.
  const photos = photosDepuisReponse({ query: { pages: { 1: { index: 1, title: 'File:Moyen.jpg', imageinfo: [{ mime: 'image/jpeg', width: 1200, url: original, thumburl: original, extmetadata: { LicenseShortName: { value: 'CC0' } } }] } } } })
  assert.deepEqual(photos, [])
})
