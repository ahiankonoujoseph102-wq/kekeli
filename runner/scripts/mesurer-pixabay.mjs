// Mesure des images libres des vidéos en formule Gratuite (quand le générateur gratuit est épuisé) : sur 5 sujets
// variés, le nombre de scènes qui reçoivent une vraie image (photo ou vidéo libre) avant et après l'assouplissement.
//   node runner/scripts/mesurer-pixabay.mjs scenarios   (écrit les 5 scénarios avec le modèle gratuit, une fois)
//   node runner/scripts/mesurer-pixabay.mjs mesurer     (compare l'ancienne et la nouvelle recherche)
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { rediger } from '../src/documents/redaction.js'
import { CONSIGNES_VIDEO, consigneVideo, scenarioPropre } from '../src/videos/scenario.js'
import * as apres from '../src/videos/medias.js'

const FICHIER = new URL('../mesures/pixabay-scenarios.json', import.meta.url)
const SUJETS = [
  'Publicité TikTok de 20 secondes pour « Ama Pagnes », boutique de pagnes wax au grand marché de Lomé. Commandes au +228 90 12 34 56.',
  'Vidéo de 20 secondes pour la rentrée de l’école primaire « Les Petits Génies » à Adidogomé : inscriptions ouvertes.',
  'Publicité de 20 secondes pour « Chez Tantie Rose », maquis qui sert du poisson braisé et de l’akoumé à Bè.',
  'Vidéo de 20 secondes pour « Garage Koffi », réparation de motos et de voitures à Agoè.',
  'Publicité de 20 secondes pour une formation en couture et stylisme de 3 mois à Kpalimé.',
]

if (process.argv[2] === 'scenarios') {
  const sortie = []
  for (const demande of SUJETS) {
    let sc = { scenes: [] }
    // Une réponse vide est redemandée (deux fois au plus).
    for (let essai = 0; essai < 3 && !sc.scenes.length; essai++) {
    const r = await rediger({ systeme: CONSIGNES_VIDEO, consigne: consigneVideo({ demande, dureeMax: 30, pays: 'Togo', monnaie: 'FCFA' }), fournisseurs: [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }] }, { clesPresentes: new Set(['gemini']) })
    sc = scenarioPropre(r.objet?.video ?? {}, { dureeMax: 30 })
    }
    console.log(demande.slice(0, 40), sc.scenes.length, 'scènes')
    sortie.push({ demande, scenes: sc.scenes.map((s) => s.media) })
  }
  writeFileSync(FICHIER, JSON.stringify(sortie, null, 2))
  process.exit(0)
}

if (!existsSync(FICHIER)) throw new Error('lancez d’abord « scenarios »')
const avant = await import('../mesures/medias-avant.mjs')
const scenarios = JSON.parse(readFileSync(FICHIER, 'utf8'))
const resultat = { avant: { scenes: 0, images: 0, videos: 0 }, apres: { scenes: 0, images: 0, videos: 0 }, sujets: [] }
for (const sc of scenarios) {
  const detail = { demande: sc.demande.slice(0, 60), avant: [], apres: [] }
  for (const [nom, module] of [['avant', avant], ['apres', apres]]) {
    const pris = []
    for (const m of sc.scenes) {
      if (m.source === 'client' || m.source === 'aucun') continue
      resultat[nom].scenes++
      const voulue = m.recherche || m.description
      let t = await module.chercherMediaLibre(voulue, { type: 'image', orientation: 'portrait', exclure: pris, larges: m.larges ?? [] }).catch(() => null)
      // Avant : des photos seulement ; après : une vidéo libre si aucune photo.
      if (!t && nom === 'apres') t = await module.chercherMediaLibre(voulue, { type: 'video', orientation: 'portrait', exclure: pris, larges: m.larges ?? [] }).catch(() => null)
      if (t) {
        pris.push(t.page)
        resultat[nom][t.type === 'video' ? 'videos' : 'images']++
      }
      detail[nom].push(t ? `${t.type} ${t.page}` : `rien (${voulue})`)
    }
  }
  resultat.sujets.push(detail)
  console.log(detail.demande, '→ avant', detail.avant.filter((x) => !x.startsWith('rien')).length, '/', detail.avant.length, ', après', detail.apres.filter((x) => !x.startsWith('rien')).length, '/', detail.apres.length)
}
writeFileSync(new URL('../mesures/pixabay-mesure.json', import.meta.url), JSON.stringify(resultat, null, 2))
console.log(JSON.stringify({ avant: resultat.avant, apres: resultat.apres }))
