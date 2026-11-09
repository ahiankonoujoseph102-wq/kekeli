import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import {
  argumentsImageMagick,
  candidatsOpenverse,
  candidatsPexels,
  candidatsWikimedia,
  choisir,
  commandeCreer,
  consigneVision,
  descriptionCreation,
  lireOptions,
  motsClesAvecPays,
  noterLicence,
} from '../src/outils/images.mjs'
import { charger, chercher, svg } from '../src/outils/icones.mjs'
import { emojisVisibles, explication, verifierPage } from '../src/qualite/verifier.mjs'
import { verifierProjet as verifierLicences } from '../src/licences/verifier.mjs'
import { fournisseursAvecCle, reglagesOutils, reglesReseau, valeurCle } from '../src/fournisseurs.js'
import { lignesJournal, nomPhotoClient } from '../src/ordinateurs.js'
import { sitesAutorises } from '../src/reseau.js'
import { texteAvecContexte, executerTache } from '../src/agent.js'
import { MisesDeCote } from '../src/limites.js'
import { fauxOpencode, libre, occupe } from './outils.js'
import { traduireOutil } from '../src/etapes.js'

const TOGO = { code: 'TG', nom: 'Togo', region: "Afrique de l'Ouest", ville: 'Lomé', anglais: 'Togo, West Africa', monnaie: 'franc CFA (FCFA)', telephone: '+228 90 12 34 56' }

// Sources de photos libres

test('seules les photos sans attribution obligatoire sont gardées, quelle que soit la source', () => {
  const openverse = candidatsOpenverse({
    results: [
      { license: 'cc0', url: 'https://live.staticflickr.com/1/a_b.jpg', thumbnail: 'https://api.openverse.org/v1/images/1/thumb/', width: 1024, title: 'Marché', foreign_landing_url: 'https://www.flickr.com/1' },
      { license: 'by', url: 'https://live.staticflickr.com/2/b_b.jpg', width: 1024 },
      { license: 'pdm', url: 'https://autre-site.example/c.jpg', width: 1024 },
      { license: 'pdm', url: 'https://live.staticflickr.com/3/c_b.jpg', width: 400 },
    ],
  })
  assert.deepEqual(openverse.map((c) => [c.adresse, c.licence]), [['https://live.staticflickr.com/1/a_b.jpg', 'CC0']])

  const pexels = candidatsPexels({ photos: [{ alt: 'Poisson braisé', url: 'https://www.pexels.com/photo/1', width: 4000, height: 3000, src: { large2x: 'https://images.pexels.com/1-l.jpg', medium: 'https://images.pexels.com/1-m.jpg' } }] })
  assert.deepEqual(pexels.map((c) => [c.adresse, c.apercu, c.licence]), [['https://images.pexels.com/1-l.jpg', 'https://images.pexels.com/1-m.jpg', 'Licence Pexels']])

  const wikimedia = candidatsWikimedia({
    query: {
      pages: {
        1: { index: 1, title: 'File:Marche.jpg', imageinfo: [{ mime: 'image/jpeg', width: 3000, height: 2000, thumburl: 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/Marche.jpg/1280px-Marche.jpg', descriptionurl: 'https://commons.wikimedia.org/wiki/File:Marche.jpg', extmetadata: { LicenseShortName: { value: 'CC0' } } }] },
        2: { index: 2, title: 'File:Attribution.jpg', imageinfo: [{ mime: 'image/jpeg', width: 3000, height: 2000, thumburl: 'https://upload.wikimedia.org/x/1280px-A.jpg', extmetadata: { LicenseShortName: { value: 'CC BY 4.0' } } }] },
      },
    },
  })
  assert.equal(wikimedia.length, 1)
  assert.match(wikimedia[0].apercu, /\/500px-Marche\.jpg$/)
})

test('les mots-clés sont complétés par le pays du client', () => {
  assert.deepEqual(motsClesAvecPays('grilled fish', TOGO), ['grilled fish togo', 'grilled fish'])
  assert.deepEqual(motsClesAvecPays('togo market', TOGO), ['togo market'])
  assert.deepEqual(motsClesAvecPays('grilled fish', null), ['grilled fish'])
  assert.match(consigneVision({ motsCles: 'grilled fish', role: 'accueil', pays: TOGO }), /au Togo/)
})

test('le regard de Kekeli refuse filigranes, noms effacés et personnes reconnaissables, puis garde la meilleure', () => {
  const candidats = ['a', 'b', 'c', 'd', 'e'].map((nom) => ({ adresse: `https://x/${nom}.jpg` }))
  const avis = [
    { numero: 1, pertinence: 10, qualite: 10, adaptee_au_pays: 10, filigrane: true },
    { numero: 2, pertinence: 10, qualite: 10, adaptee_au_pays: 10, nom_auteur_efface: true },
    { numero: 3, pertinence: 10, qualite: 10, adaptee_au_pays: 10, personne_reconnaissable: true },
    { numero: 4, pertinence: 7, qualite: 7, adaptee_au_pays: 9, zones_a_flouter: [{ genre: 'marque', boite: [0, 0, 100, 100] }] },
    { numero: 5, pertinence: 8, qualite: 6, adaptee_au_pays: 2 },
  ]
  assert.equal(choisir(candidats, avis).candidat.adresse, 'https://x/d.jpg')
  assert.equal(choisir(candidats, avis.slice(0, 3)), null)
  assert.equal(choisir(candidats, [{ numero: 4, pertinence: 4, qualite: 9 }]), null)
  // Une photo d'un autre pays ou d'une autre culture est refusée, même belle et pertinente.
  assert.equal(choisir(candidats, [{ numero: 4, pertinence: 9, qualite: 9, adaptee_au_pays: 2 }]), null)
})

test('les marques, textes et visages repérés sont floutés par ImageMagick, et l\'image est allégée', async () => {
  const args = argumentsImageMagick('in.jpg', 'out.jpg', { largeur: 3200, hauteur: 2000, zones: [{ genre: 'marque', boite: [100, 200, 300, 400] }, { genre: 'visage', boite: [500, 500, 400, 600] }] })
  assert.deepEqual(args.slice(0, 4), ['in.jpg', '-auto-orient', '-resize', '1600x'])
  // Une seule zone valide (la seconde a une boîte à l'envers) ; marge de 1,5 % autour.
  assert.equal(args.filter((a) => a === '-composite').length, 1)
  assert.ok(args.includes('368x230+296+85'), args.join(' '))
  assert.deepEqual(args.slice(-3), ['-quality', '82', 'out.jpg'])
  assert.ok(args.includes('-strip'))

  // Essai réel avec ImageMagick : une image est bien créée, à la bonne taille.
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-flou-'))
  try {
    execFileSync('convert', ['-size', '2000x1000', 'xc:orange', path.join(dossier, 'in.jpg')])
    execFileSync('convert', argumentsImageMagick(path.join(dossier, 'in.jpg'), path.join(dossier, 'out.jpg'), { largeur: 2000, hauteur: 1000, zones: [{ genre: 'texte', boite: [100, 100, 200, 300] }] }))
    assert.equal(execFileSync('identify', ['-format', '%w %h', path.join(dossier, 'out.jpg')]).toString(), '1600 800')
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('chaque image est notée une seule fois dans LICENCES.md', async () => {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-licences-'))
  try {
    noterLicence('images/accueil.jpg', 'première version', dossier)
    noterLicence('images/menu.jpg', 'photo du menu', dossier)
    noterLicence('images/accueil.jpg', 'seconde version', dossier)
    const texte = await readFile(path.join(dossier, 'LICENCES.md'), 'utf8')
    assert.equal(texte, '# Licences des ressources du projet\n\n- images/menu.jpg — photo du menu\n- images/accueil.jpg — seconde version\n')
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

// Création d'images

test('sans service de création gratuit configuré, l\'outil le dit au lieu de payer ou d\'inventer', async () => {
  const resultat = await commandeCreer('grilled fish', { fichier: 'images/x.jpg' }, { regler: { services: [] }, telecharger: () => assert.fail('aucun appel') })
  assert.equal(resultat.ok, false)
  assert.match(resultat.message, /pas disponible/)
})

test('au-delà du nombre d\'images permises aujourd\'hui, aucune image n\'est créée', async () => {
  const regler = { services: ['cloudflare'], cloudflare_compte: '0123456789abcdef0123456789abcdef', images_max: 2 }
  const telecharger = () => assert.fail('aucun appel au service de création')
  for (const dejaCreees of [2, 3]) {
    const resultat = await commandeCreer('grilled fish', { fichier: 'images/x.jpg' }, { regler, telecharger, dejaCreees })
    assert.equal(resultat.ok, false)
    assert.match(resultat.message, /Plus de création d'image possible aujourd'hui/)
  }
  const sansReglage = await commandeCreer('grilled fish', { fichier: 'images/x.jpg' }, { regler: { ...regler, images_max: undefined }, telecharger, dejaCreees: 0 })
  assert.equal(sansReglage.ok, false)
  assert.equal(reglagesOutils({ images_restantes: 12 }, new Set(), {}).images_max, 12)
  assert.equal(reglagesOutils({}, new Set(), {}).images_max, 0)
})

test('la description envoyée au service de création est adaptée au pays, sans texte ni marque', () => {
  const texte = descriptionCreation('A family eating grilled fish at a restaurant terrace', TOGO)
  assert.match(texte, /Togo, West Africa/)
  assert.match(texte, /No text, no letters, no logo, no brand, no watermark/)
  assert.match(texte, /no famous or real identifiable person/)
  assert.deepEqual(lireOptions(['grilled', 'fish', '--fichier', 'images/a.jpg', '--format', 'carre']), { texte: 'grilled fish', options: { fichier: 'images/a.jpg', format: 'carre' } })
})

// Icônes

test('la bibliothèque d\'icônes Lucide est complète et donne du SVG prêt à coller', () => {
  const bibliotheque = charger(path.join(import.meta.dirname, '../src/modeles/icones'))
  assert.ok(Object.keys(bibliotheque.icones).length > 1500)
  assert.ok(chercher('phone', bibliotheque).includes('phone'))
  assert.ok(chercher('call', bibliotheque).includes('phone'), 'recherche par mot-clé')
  const code = svg('map-pin', bibliotheque)
  assert.match(code, /^<svg class="icone" [^>]*aria-hidden="true">.*<\/svg>$/)
  assert.equal(svg('icone-inconnue', bibliotheque), null)
})

test('les émojis utilisés comme icônes sont signalés, pas les symboles simples', async () => {
  assert.deepEqual(emojisVisibles('<h2>🍲 Spécialités</h2><a>☀️ Réserver</a><p>© 2026</p><img alt="🔥">'), ['🍲', '☀️'])
  const html = '<!doctype html><html lang="fr"><head><meta name="viewport" content="width=device-width, initial-scale=1"><title>Le Palmier</title><meta name="description" content="Le Palmier, restaurant de poisson braisé à Lomé, quartier Bè."></head><body><h1>🌴 Le Palmier</h1></body></html>'
  const problemes = await verifierPage('index.html', html, async () => true)
  assert.deepEqual(problemes.map((p) => [p.genre, p.cible]), [['emoji', '🌴']])
  assert.match(explication(problemes[0]), /icones\.mjs svg/)
})

test('les photos du client n\'ont pas besoin d\'être notées dans LICENCES.md', async () => {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-client-'))
  try {
    await import('node:fs/promises').then((fs) => fs.mkdir(path.join(dossier, 'images/client'), { recursive: true }))
    await writeFile(path.join(dossier, 'images/client/plat.jpg'), 'x')
    await writeFile(path.join(dossier, 'images/autre.jpg'), 'x')
    await writeFile(path.join(dossier, 'LICENCES.md'), '# Licences\n')
    const resultat = await verifierLicences(dossier)
    assert.deepEqual(resultat.problemes.map((p) => p.cible), ['images/autre.jpg'])
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

// Services d'images, pays et journal

test('les clés des services d\'images restent dans le Runner et sont ajoutées par le réseau', () => {
  const cles = fournisseursAvecCle({ GEMINI_API_KEY: 'a', PEXELS_API_KEY: 'b', CLOUDFLARE_API_TOKEN: 'c' })
  // Sans numéro de compte, Cloudflare n'est pas utilisable.
  assert.deepEqual([...cles], ['gemini', 'pexels'])
  const COMPTE = '0123456789abcdef0123456789abcdef'
  const avecCompte = fournisseursAvecCle({ GEMINI_API_KEY: 'a', CLOUDFLARE_API_TOKEN: 'c', CLOUDFLARE_ACCOUNT_ID: `[https://dash.cloudflare.com/${COMPTE}/home]` })
  assert.deepEqual([...avecCompte], ['gemini', 'cloudflare'])
  const regles = reglesReseau(new Set(['pexels', 'cloudflare']), (nom) => `{{${nom}}}`)
  assert.deepEqual(regles['api.pexels.com'], [{ transform: { headers: { Authorization: '{{kekeli_pexels}}' } } }])
  assert.deepEqual(regles['api.cloudflare.com'], [{ transform: { headers: { Authorization: 'Bearer {{kekeli_cloudflare}}' } } }])
  assert.ok(sitesAutorises([], new Set(['cloudflare'])).includes('api.cloudflare.com'))
  assert.ok(sitesAutorises([], new Set()).includes('live.staticflickr.com'))

  // Le numéro de compte est extrait même quand la variable contient l'adresse d'une page du tableau de bord.
  const reglages = reglagesOutils({ pays: TOGO, modele_vision: 'gemini-3.1-flash-lite' }, avecCompte, { CLOUDFLARE_ACCOUNT_ID: `[https://dash.cloudflare.com/${COMPTE}/home]` })
  assert.equal(reglages.cloudflare_compte, COMPTE)
  assert.deepEqual(reglages.services, ['cloudflare'])
  // Les crochets, guillemets et espaces ajoutés par un copier-coller sont retirés de la clé.
  for (const collee of ['[abc-123_XYZ]', ' "abc-123_XYZ" ', '«abc-123_XYZ»', 'abc-123_XYZ']) assert.equal(valeurCle('CLE', { CLE: collee }), 'abc-123_XYZ')
  assert.equal(reglagesOutils({}, new Set(), {}).vision, null)
})

test('le pays du client et ses photos sont indiqués à l\'agent avant la demande', () => {
  const texte = texteAvecContexte('Crée mon site', { pays: TOGO, photos_client: [{ nom: 'a.jpg' }, { nom: 'b.jpg' }] })
  assert.match(texte, /^\[Informations de Kekeli\] Pays du client : Togo \(Afrique de l'Ouest\), ville principale : Lomé\. Monnaie : franc CFA \(FCFA\)\. Format des numéros de téléphone : \+228 90 12 34 56\. Le client a envoyé 2 photo\(s\)/)
  assert.ok(texte.endsWith('\n\nCrée mon site'))
  assert.equal(texteAvecContexte('Crée mon site', {}), 'Crée mon site')
  assert.equal(nomPhotoClient('Mon Plat Préféré.JPG'), 'mon-plat-prefere.jpg')
  assert.equal(nomPhotoClient('../../etc/passwd'), null)
  assert.equal(nomPhotoClient('document.pdf'), null)
})

test('le journal des outils est lu, et les images créées sont comptées pour l\'énergie', async () => {
  assert.deepEqual(lignesJournal('{"type":"image_creee"}\nillisible\n{"pas":"de type"}\n'), [{ type: 'image_creee' }])
  const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, libre] })
  const preparations = []
  faux.ordinateurs.preparerTache = async (projet, options) => preparations.push(options)
  faux.ordinateurs.lireJournal = async () => [
    { type: 'requete', fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite', tokens_entree: 3000, tokens_sortie: 200, a: '2026-11-09' },
    { type: 'image_creee', service: 'cloudflare' },
    { type: 'image_creee', service: 'cloudflare' },
  ]
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Un site', fournisseurs: [GEMINI], pays: TOGO, photos_client: [{ nom: 'plat.jpg', url: 'https://site/photo' }] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.equal(preparations[0].reglages.pays.nom, 'Togo')
  assert.deepEqual(preparations[0].photosClient, [{ nom: 'plat.jpg', url: 'https://site/photo' }])
  assert.match(faux.appels.prompts[0].texte, /^\[Informations de Kekeli\] Pays du client : Togo/)
  assert.deepEqual(evenements.find((e) => e.type === 'requete' && e.outil), { type: 'requete', fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite', tokens_entree: 3000, tokens_sortie: 200, outil: true })
  assert.deepEqual(evenements.find((e) => e.type === 'images'), { type: 'images', nombre: 2 })
})

test('les outils d\'images et d\'icônes sont montrés clairement dans la discussion', () => {
  const bash = (command) => traduireOutil({ type: 'tool', tool: 'bash', state: { status: 'completed', input: { command } } })
  assert.deepEqual(bash('node /home/user/.kekeli/images.mjs chercher "grilled fish" --fichier images/a.jpg'), { genre: 'photos', cible: 'grilled fish' })
  assert.deepEqual(bash('node /home/user/.kekeli/images.mjs creer "restaurant terrace" --fichier images/b.jpg'), { genre: 'image_creee', cible: 'restaurant terrace' })
  assert.deepEqual(bash('node /home/user/.kekeli/images.mjs client'), { genre: 'photos_client', cible: '' })
  assert.deepEqual(bash('node /home/user/.kekeli/icones.mjs svg phone clock'), { genre: 'icones', cible: 'phone clock' })
})
