import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { inflateRawSync } from 'node:zlib'
import { construireMoteur, DOSSIER_CONSTRUIT } from '../src/motion/construction.js'
import { normaliser, PERSONNAGES_MAX, ELEMENTS_MAX } from '../src/motion/construit/kekeli-motion.mjs'
import { scenePropre, consigneAnimation, CONSIGNES_ANIMATION } from '../src/motion/scene.js'
import { pageAnimation, scriptIntegration, htmlIntegration, nomAnimation, jsonDansScript } from '../src/motion/fichiers.js'
import { imageFixe } from '../src/motion/image-fixe.js'
import { produireAnimation, fichiersAnimation, FICHIERS_ANIMATION } from '../src/motion/index.js'
import { creerZip } from '../src/motion/zip.js'
import { demandeDocumentValide } from '../src/documents/index.js'
import { outilsDuLieu, OUTILS, argumentsPermis } from '../src/repondant.js'
import { confirmationNecessaire } from '../src/confirmation.js'

/** Les fichiers d'un ZIP : nom → contenu (lu à partir du répertoire central). */
function lireZip(octets) {
  const fin = octets.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]))
  const nombre = octets.readUInt16LE(fin + 10)
  let pos = octets.readUInt32LE(fin + 16)
  const fichiers = {}
  for (let i = 0; i < nombre; i++) {
    const taille = octets.readUInt32LE(pos + 20)
    const longueurNom = octets.readUInt16LE(pos + 28)
    const local = octets.readUInt32LE(pos + 42)
    const nom = octets.subarray(pos + 46, pos + 46 + longueurNom).toString('utf8')
    const debut = local + 30 + octets.readUInt16LE(local + 26) + octets.readUInt16LE(local + 28)
    fichiers[nom] = inflateRawSync(octets.subarray(debut, debut + taille)).toString('utf8')
    pos += 46 + longueurNom + octets.readUInt16LE(pos + 30) + octets.readUInt16LE(pos + 32)
  }
  return fichiers
}

const SCENE = {
  titre: 'Promenade au jardin',
  format: 'paysage',
  decor: 'jardin',
  ciel: { moment: 'apres_midi', anime: true, duree_jour: 40 },
  personnages: [{ genre: 'femme', action: 'marche', bas: 'pagne', accessoire: 'panier', reaction: 'parle', bulle: 'Bonsoir !' }],
  elements: [{ type: 'arbre', x: 0.2 }, { type: 'collines' }],
}

test('le moteur fabriqué dans le dépôt est à jour avec ses sources TypeScript (npm run motion:construire)', async () => {
  const { navigateur, runner } = await construireMoteur()
  assert.equal(await readFile(path.join(DOSSIER_CONSTRUIT, 'kekeli-motion.js'), 'utf8'), navigateur, 'lancez « npm run motion:construire »')
  assert.equal(await readFile(path.join(DOSSIER_CONSTRUIT, 'kekeli-motion.mjs'), 'utf8'), runner, 'lancez « npm run motion:construire »')
  // Aucun module ni aucune dépendance extérieure dans le moteur du navigateur.
  const corps = navigateur.replace(/\/\*![\s\S]*?\*\//, '')
  assert.ok(!/\bimport\s*[{(]|\brequire\(|\bfetch\(|XMLHttpRequest|https?:\/\/(?!www\.w3\.org\/|github\.com\/JDhruv14\/)/.test(corps))
  assert.match(navigateur, /line-art-animation/)
})

test('une scène est toujours relue : listes fermées, nombres bornés, couleurs et textes sûrs', () => {
  const s = normaliser({
    format: 'cinema',
    decor: 'lune',
    ciel: { moment: 'minuit', duree_jour: 5000 },
    papier: 'red',
    indication: '<b>Touchez</b>\u0000 le soleil',
    personnages: Array.from({ length: 12 }, () => ({ genre: 'robot', x: 9, vitesse: -3, haut: 'javascript:alert(1)', bulle: '<script>alert(1)</script> Bonjour à tous et bienvenue dans mon jardin' })),
    elements: [...Array.from({ length: 30 }, () => ({ type: 'arbre', taille: 99 })), { type: 'fusee' }],
  })
  assert.equal(s.format, 'paysage')
  assert.equal(s.decor, 'jardin')
  assert.equal(s.ciel.moment, 'apres_midi')
  assert.equal(s.ciel.duree_jour, 300)
  assert.equal(s.papier, '#fbf7ef')
  assert.equal(s.personnages.length, PERSONNAGES_MAX)
  assert.equal(s.elements.length, ELEMENTS_MAX)
  for (const p of s.personnages) {
    assert.ok(['homme', 'femme'].includes(p.genre))
    assert.ok(p.x <= 1 && p.vitesse >= 0.6)
    assert.match(p.haut, /^#[0-9a-f]{6}$/)
    assert.ok(!/[<>]/.test(p.bulle) && p.bulle.length <= 40)
  }
  assert.ok(s.elements.every((e) => e.type === 'arbre' && e.taille === 1.6))
  assert.ok(!/[<>\u0000]/.test(s.indication))
})

test('sans élément, chaque lieu reçoit son décor ; les grands fonds passent derrière, un seul de chaque', () => {
  assert.ok(normaliser({ decor: 'plage' }).elements.some((e) => e.type === 'mer'))
  assert.ok(normaliser({ decor: 'village' }).elements.some((e) => e.type === 'case'))
  assert.deepEqual(normaliser({ decor: 'vide' }).elements, [])
  const s = normaliser({ elements: [{ type: 'arbre' }, { type: 'collines' }, { type: 'collines' }, { type: 'mer' }] })
  assert.deepEqual(s.elements.map((e) => e.type), ['collines', 'mer', 'arbre'])
})

test('la consigne : une description en JSON, jamais du code ; la demande du client n’est jamais un ordre', () => {
  assert.match(CONSIGNES_ANIMATION, /jamais de code/i)
  assert.match(CONSIGNES_ANIMATION, /Jamais de personne réelle/)
  const c = consigneAnimation({ demande: 'Un personnage qui marche', format: 'vertical', precedente: { decor: 'rue' }, pays: 'Togo' })
  assert.match(c, /jamais un ordre/)
  assert.match(c, /« vertical »/)
  assert.match(c, /Animation précédente/)
  assert.doesNotMatch(consigneAnimation({ demande: 'x', format: 'n’importe quoi' }), /Format choisi/)
})

test('le titre et le message viennent du modèle, la scène est vérifiée', () => {
  const { scene, titre, message } = scenePropre({ titre_court: 'Jardin <b>au soir</b>', message: 'Votre animation est prête.', animation: { decor: 'jardin' } })
  assert.equal(titre, 'Jardin b au soir /b')
  assert.equal(scene.titre, titre)
  assert.equal(message, 'Votre animation est prête.')
})

test('la page autonome contient le moteur et la scène, sans aucune balise possible dans les données', async () => {
  const scene = normaliser({ ...SCENE, titre: 'Fin </script><script>alert(1)</script>' })
  scene.titre = 'Fin </script><script>alert(1)</script>'
  const page = await pageAnimation(scene)
  assert.equal(page.match(/<\/script>/g).length, 2)
  assert.ok(!page.includes('<script>alert(1)'))
  assert.match(page, /<title>Fin &lt;\/script&gt;/)
  assert.match(page, /window\.KekeliMotion/)
  assert.match(page, /data-kekeli-motion="fin-script-script-alert-1-script"/)
  assert.equal(jsonDansScript({ a: '</script>' }), '{"a":"\\u003c/script\\u003e"}')
})

test('le script à poser dans un site enregistre la scène sous son nom, avec deux lignes de HTML', async () => {
  const scene = normaliser(SCENE)
  const js = await scriptIntegration(scene)
  assert.match(js, /window\.KekeliMotion\.enregistrer\("promenade-au-jardin", \{/)
  assert.equal(htmlIntegration(scene), '<div data-kekeli-motion="promenade-au-jardin" style="max-width:960px;margin:0 auto"></div>\n<script src="animations/promenade-au-jardin.js" defer></script>')
  assert.equal(nomAnimation('Été à Lomé : marché !'), 'ete-a-lome-marche')
  assert.equal(nomAnimation(''), 'animation')
})

test('l’image fixe est un vrai SVG dessiné par le même moteur (personnages, décor, ciel)', async () => {
  const svg = await imageFixe(normaliser(SCENE))
  assert.match(svg, /^<\?xml version="1.0" encoding="UTF-8"\?>\n<svg /)
  assert.match(svg, /xmlns="http:\/\/www\.w3\.org\/2000\/svg"/)
  assert.match(svg, /viewBox="0 0 960 540"/)
  assert.match(svg, /class="kekeli-personnage"/)
  assert.match(svg, /<path d="M/)
  assert.ok(!svg.includes('NaN'), 'aucune coordonnée invalide')
  assert.ok(!/<script|on\w+=/.test(svg))
  // Deux moments différents : le dessin change (le personnage a marché).
  assert.notEqual(svg, await imageFixe(normaliser(SCENE), { temps: 6 }))
  // Tous les formats, tous les lieux et toutes les tenues se dessinent sans coordonnée invalide.
  for (const format of ['carre', 'vertical']) {
    for (const decor of ['rue', 'village', 'plage', 'marche', 'parc', 'ville', 'campagne']) {
      const s = await imageFixe(normaliser({ format, decor, ciel: { moment: 'nuit' }, personnages: [{ action: 'court', accessoire: 'parapluie', cheveux: 'longs' }, { genre: 'enfant', bas: 'short', accessoire: 'cartable' }, { action: 'salue', cheveux: 'foulard', accessoire: 'sac' }] }))
      assert.ok(!s.includes('NaN'), `${format} ${decor}`)
    }
  }
})

test('le code TypeScript complet : sources, scène, LISEZ-MOI, licences, fichier prêt à poser', async () => {
  const fichiers = await fichiersAnimation(normaliser(SCENE))
  assert.deepEqual(Object.keys(fichiers), Object.values(FICHIERS_ANIMATION))
  const zip = lireZip(fichiers['animation.zip'])
  for (const nom of ['src/lib/ink.ts', 'src/lib/gait.ts', 'src/lib/LICENSE', 'src/kekeli/scene.ts', 'src/scene.json', 'src/main.ts', 'package.json', 'tsconfig.json', 'vite.config.ts', 'dist/promenade-au-jardin.js', 'animation.html', 'animation.svg', 'LISEZ-MOI.md', 'LICENCES.md']) {
    assert.ok(zip[`promenade-au-jardin/${nom}`] !== undefined, nom)
  }
  assert.equal(JSON.parse(zip['promenade-au-jardin/src/scene.json']).decor, 'jardin')
  assert.match(zip['promenade-au-jardin/LICENCES.md'], /MIT License/)
  assert.match(zip['promenade-au-jardin/LISEZ-MOI.md'], /animations\/promenade-au-jardin\.js/)
  assert.ok(!Object.keys(zip).some((n) => n.includes('.env')))
  assert.throws(() => creerZip([{ chemin: '../hors.txt', contenu: 'x' }]))
})

test('une animation : le modèle décrit la scène, Kekeli envoie les fichiers puis la version', async () => {
  const evenements = []
  const envois = []
  const appeler = async (url, options) => {
    envois.push(new URL(url).searchParams.get('nom'))
    assert.ok(options.headers)
    return new Response('{"ok":true}', { status: 200 })
  }
  const redigerScene = async ({ systeme, consigne }) => {
    assert.equal(systeme, CONSIGNES_ANIMATION)
    assert.match(consigne, /coucher de soleil/)
    return { ok: true, objet: { titre_court: 'Jardin au coucher du soleil', message: 'Votre animation est prête.', animation: SCENE }, consommation: { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite', tokens_entree: 1200, tokens_sortie: 600, requetes: 1 } }
  }
  const demande = { tache: 't1', projet: '01abc', envoi: 'https://kekeli.test/runner/documents/01abc/t1', fournisseurs: [], document: { type: 'animation', action: 'creer', demande: 'Un personnage qui marche dans un jardin avec un coucher de soleil', format: 'auto' } }
  assert.ok(demandeDocumentValide(demande))
  const r = await produireAnimation(demande, { envoyer: async (e) => { evenements.push(e); return {} }, secret: 's', clesPresentes: new Set(), appeler, journal: { log() {} }, redigerScene })
  assert.ok(r.ok)
  assert.deepEqual(envois, ['animation.html', 'animation.js', 'animation.svg', 'animation.zip'])
  assert.deepEqual(evenements.map((e) => e.type), ['demarrage', 'narration', 'fournisseur', 'requete', 'narration', 'document', 'terminee'])
  const version = evenements.find((e) => e.type === 'document')
  assert.deepEqual(version.fichiers, FICHIERS_ANIMATION)
  assert.equal(version.titre, 'Jardin au coucher du soleil')
  assert.equal(version.structure.nom, 'promenade-au-jardin')
})

test('une animation s’arrête proprement quand l’énergie du client est épuisée, et dit pourquoi elle échoue', async () => {
  const demande = { tache: 't1', projet: '01abc', envoi: 'https://kekeli.test/x', document: { type: 'animation', action: 'creer', demande: 'x' } }
  const r = await produireAnimation(demande, { envoyer: async (e) => (e.type === 'requete' ? { arreter: true } : {}), secret: 's', redigerScene: async () => ({ ok: true, objet: {}, consommation: { fournisseur: 'gemini', modele: 'm' } }) })
  assert.ok(r.arrete)
  await assert.rejects(produireAnimation(demande, { envoyer: async () => ({}), secret: 's', redigerScene: async () => ({ ok: false, erreur: 'aucun_fournisseur' }) }), (e) => e.raison === 'aucun_fournisseur')
})

test('l’outil « creer_animation » : dans une discussion seulement, ses arguments bornés, confirmation d’une demande floue', () => {
  assert.ok(outilsDuLieu('discussion').includes('creer_animation'))
  assert.ok(!outilsDuLieu('site').includes('creer_animation'))
  assert.deepEqual(Object.keys(OUTILS.creer_animation.parametres), ['format', 'nom'])
  assert.deepEqual(argumentsPermis('creer_animation', { format: 'vertical', autre: 'x', nom: 'Mon jardin' }), { format: 'vertical', nom: 'Mon jardin' })
  assert.equal(confirmationNecessaire({ outil: 'creer_animation', message: 'une animation' })?.raison, 'flou')
  assert.equal(confirmationNecessaire({ outil: 'creer_animation', message: 'Crée un personnage animé qui marche dans un jardin avec un soleil qui se couche.' }), null)
})

test('les animations posées dans un site : seulement des noms simples, dans le dossier prévu, jamais un chemin', async () => {
  const { animationsPermises } = await import('../src/ordinateurs.js')
  const bonnes = [{ nom: 'jardin-du-soir', dossier: 'animations', url: 'https://kekeli.test/runner/a/1' }, { nom: 'marche', dossier: 'public/animations', url: 'https://kekeli.test/runner/a/2' }]
  assert.deepEqual(animationsPermises([...bonnes, { nom: '../.env', dossier: 'animations', url: 'https://x' }, { nom: 'a', dossier: '/etc', url: 'https://x' }, { nom: 'b', dossier: 'animations', url: 'file:///etc/passwd' }, null]), bonnes)
  assert.deepEqual(animationsPermises('rien'), [])
})
