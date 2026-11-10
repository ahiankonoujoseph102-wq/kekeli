// Fabrique les images des modèles de la galerie, avec les mêmes règles que Kekeli : image créée par l'offre gratuite
// de Cloudflare Workers AI (FLUX.1 schnell), adaptée à l'Afrique de l'Ouest, puis regardée par Gemini (offre
// gratuite) : s'il y a du texte, des lettres ou une marque, elle est recréée deux fois au plus, puis floutée.
// Seules les images manquantes sont fabriquées. Clés lues dans l'environnement, jamais affichées.
//
//   node scripts/modeles/images.mjs [modèle…]
import { execFileSync } from 'node:child_process'
import { existsSync, readFileSync, renameSync, unlinkSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { CONSIGNE_TEXTE, descriptionCreation } from '../../runner/src/outils/images.mjs'
import { numeroCompteCloudflare, valeurCle } from '../../runner/src/fournisseurs.js'
import { MODELES } from './donnees.mjs'

const racine = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const compte = numeroCompteCloudflare(process.env)
const jetonCloudflare = valeurCle('CLOUDFLARE_API_TOKEN', process.env)
const cleGemini = valeurCle('GEMINI_API_KEY', process.env)
const vision = process.env.KEKELI_MODELE_VISION || 'gemini-3.1-flash-lite'
const PAYS = { anglais: 'Togo, West Africa' }
const PRECISIONS = ['', ' Show the scene from a closer angle, with blank plain surfaces and no signboard.', ' Simple composition, everything unlabeled, walls and objects completely free of any writing.']

async function creer(prompt) {
  const r = await fetch(`https://api.cloudflare.com/client/v4/accounts/${compte}/ai/run/@cf/black-forest-labs/flux-1-schnell`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${jetonCloudflare}` },
    body: JSON.stringify({ prompt, steps: 4 }),
    signal: AbortSignal.timeout(120000),
  })
  const d = await r.json()
  if (!d?.result?.image) throw new Error(`Cloudflare : ${(d.errors ?? []).map((x) => x.message).join(' ; ') || r.status}`)
  return Buffer.from(d.result.image, 'base64')
}

async function texteVisible(fichier) {
  const r = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${vision}:generateContent`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'x-goog-api-key': cleGemini },
    body: JSON.stringify({
      contents: [{ parts: [{ text: CONSIGNE_TEXTE }, { inline_data: { mime_type: 'image/jpeg', data: readFileSync(fichier).toString('base64') } }] }],
      generationConfig: { responseMimeType: 'application/json', temperature: 0 },
    }),
    signal: AbortSignal.timeout(60000),
  })
  const d = await r.json()
  const brut = d.candidates?.[0]?.content?.parts?.map((p) => p.text ?? '').join('') ?? '{}'
  const avis = JSON.parse(brut.replace(/^```(json)?|```$/g, '').trim())
  return { texte: Boolean(avis.texte), zones: Array.isArray(avis.zones) ? avis.zones : [] }
}

function enregistrer(octets, cible) {
  const brut = `${cible}.brut`
  writeFileSync(brut, octets)
  // Format paysage 16/9, 1 280 pixels de large, JPEG léger.
  execFileSync('convert', [brut, '-auto-orient', '-resize', '1280x720^', '-gravity', 'center', '-extent', '1280x720', '-strip', '-quality', '78', cible])
  unlinkSync(brut)
}

function flouter(cible, zones) {
  const largeur = 1280
  const hauteur = 720
  const args = [cible]
  for (const { boite } of zones) {
    if (!Array.isArray(boite) || boite.length !== 4) continue
    const [y1, x1, y2, x2] = boite.map((v) => Math.max(0, Math.min(1000, Number(v))))
    const l = Math.max(8, Math.round(((x2 - x1) / 1000) * largeur))
    const h = Math.max(8, Math.round(((y2 - y1) / 1000) * hauteur))
    const x = Math.round((x1 / 1000) * largeur)
    const y = Math.round((y1 / 1000) * hauteur)
    args.push('(', '-clone', '0', '-crop', `${l}x${h}+${x}+${y}`, '-blur', '0x14', ')', '-geometry', `+${x}+${y}`, '-composite')
  }
  const temporaire = `${cible}.flou.jpg`
  execFileSync('convert', [...args, '-quality', '78', temporaire])
  renameSync(temporaire, cible)
}

async function fabriquer(m, image) {
  const cible = path.join(racine, 'resources/modeles-sites', m.slug, 'site/images', image.fichier)
  if (existsSync(cible)) return 'déjà là'
  let avis = null
  let essais = 0
  for (const precision of PRECISIONS) {
    essais++
    enregistrer(await creer(descriptionCreation(image.prompt, PAYS) + precision), cible)
    avis = await texteVisible(cible).catch(() => null)
    if (avis && !avis.texte) break
  }
  if (avis?.texte && avis.zones.length) flouter(cible, avis.zones)
  return `${essais} création(s)${avis?.texte ? `, ${avis.zones.length} zone(s) floutée(s)` : avis ? ', aucun texte' : ', non vérifiée'}`
}

const choisis = process.argv.slice(2)
let total = 0
for (const m of MODELES.filter((x) => !choisis.length || choisis.includes(x.slug))) {
  for (const image of [m.accueil.image, m.apropos.image, ...m.galerie.images]) {
    try {
      const resultat = await fabriquer(m, image)
      if (resultat !== 'déjà là') total++
      console.log(`${m.slug}/${image.fichier} : ${resultat}`)
    } catch (erreur) {
      console.log(`${m.slug}/${image.fichier} : ÉCHEC ${erreur.message}`)
    }
  }
}
console.log(`Terminé : ${total} image(s) fabriquée(s).`)
