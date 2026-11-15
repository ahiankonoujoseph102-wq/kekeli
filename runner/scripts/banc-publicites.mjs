// Banc des publicités vidéo (15 novembre 2026) : les 5 vraies demandes de runner/mesures/publicites-5.json, faites
// l'une après l'autre avec le Runner seul (vrai modèle gratuit, vraies voix gratuites, vrais ordinateurs E2B), avec
// essai-video.mjs. Pour chaque vidéo : le MP4, les sous-titres, une planche d'images (une image toutes les 2 secondes)
// et le bilan (temps, voix, images, coût). Les notes sont données ensuite, en regardant les planches et les sous-titres.
//   node runner/scripts/banc-publicites.mjs <dossier de sortie> [nom…]
// Variables : VOIX_GRATUITES (JSON) pour changer les voix ; SANS_GENRE=1 pour ne pas donner la voix voulue.
import { spawnSync, execFileSync } from 'node:child_process'
import { mkdirSync, readFileSync, writeFileSync, existsSync, readdirSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const ICI = path.dirname(fileURLToPath(import.meta.url))
const sortie = path.resolve(process.argv[2] ?? 'banc-publicites')
const seulement = process.argv.slice(3)
const demandes = JSON.parse(readFileSync(path.join(ICI, '../mesures/publicites-5.json'), 'utf8')).filter((d) => !seulement.length || seulement.includes(d.nom))
mkdirSync(sortie, { recursive: true })

const bilan = []
for (const d of demandes) {
  const dossier = path.join(sortie, d.nom)
  mkdirSync(dossier, { recursive: true })
  const spec = { demande: d.demande, duree_voulue: d.duree_voulue, format: 'auto', duree_max: 30, ...(process.env.SANS_GENRE ? {} : { voix_genre: d.voix_genre }), ...(process.env.VOIX_GRATUITES ? { voix_gratuites: JSON.parse(process.env.VOIX_GRATUITES) } : {}) }
  writeFileSync(path.join(dossier, 'demande.json'), JSON.stringify(spec, null, 2))
  const debut = Date.now()
  const r = spawnSync('node', [path.join(ICI, 'essai-video.mjs'), dossier, path.join(dossier, 'demande.json')], { encoding: 'utf8', timeout: 20 * 60 * 1000, env: process.env })
  writeFileSync(path.join(dossier, 'journal.txt'), `${r.stdout}\n${r.stderr}`)
  const secondes = Math.round((Date.now() - debut) / 1000)
  const mp4 = readdirSync(dossier).find((f) => f.endsWith('.mp4'))
  const ligne = { nom: d.nom, secondes, video: Boolean(mp4) }
  if (mp4) {
    const fichier = path.join(dossier, mp4)
    // Une image toutes les 2 secondes, en planche (pour regarder la cohérence des images avec ce qui est dit).
    execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', fichier, '-vf', 'fps=1/2,scale=270:-2,tile=8x2:padding=6:color=white', '-frames:v', '1', path.join(dossier, 'planche.jpg')])
    const duree = Number(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', fichier], { encoding: 'utf8' }))
    ligne.duree = Math.round(duree * 10) / 10
    const evenements = existsSync(path.join(dossier, 'evenements.json')) ? JSON.parse(readFileSync(path.join(dossier, 'evenements.json'), 'utf8')) : []
    const voix = evenements.filter((e) => e.type === 'voix' && e.usage === 'voix')
    ligne.voix = voix.length ? { requetes: voix.length, modeles: [...new Set(voix.map((e) => e.modele))], secondes: voix.reduce((t, e) => t + e.secondes, 0) } : null
    ligne.images_creees = evenements.filter((e) => e.type === 'images').reduce((t, e) => t + e.nombre, 0)
    ligne.requetes_modele = evenements.filter((e) => e.type === 'requete').reduce((t, e) => t + (e.requetes ?? 1), 0)
    const b = existsSync(path.join(dossier, 'bilan.json')) ? JSON.parse(readFileSync(path.join(dossier, 'bilan.json'), 'utf8')) : {}
    const medias = b.structure?.medias ?? []
    ligne.medias = medias.map((m) => m.source).reduce((o, s) => ({ ...o, [s]: (o[s] ?? 0) + 1 }), {})
    ligne.openai_usd = b.cout?.openai_usd ?? 0
    const srt = readdirSync(dossier).find((f) => f.endsWith('.srt'))
    ligne.sous_titres = srt ? readFileSync(path.join(dossier, srt), 'utf8').split('\n').filter((l) => l && !/^\d+$/.test(l) && !l.includes('-->')) : []
  } else {
    ligne.erreur = r.stdout.split('\n').filter((l) => /ÉCHEC|Erreur|Réponse/.test(l)).join(' ').slice(0, 300)
  }
  bilan.push(ligne)
  console.log(JSON.stringify(ligne))
  writeFileSync(path.join(sortie, 'bilan.json'), JSON.stringify(bilan, null, 2))
}
