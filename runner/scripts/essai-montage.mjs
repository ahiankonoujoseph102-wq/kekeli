// Essai d'une vidéo « motion design » de Kekeli, en local (étape 1 du chantier des vidéos publicitaires).
// Un plan de montage écrit à la main (JSON) est nettoyé, calé sur la voix (mots horodatés par Whisper chez Groq,
// gratuit), puis rendu par la bibliothèque Revideo (licence MIT) ; la voix est ajoutée telle quelle (le son travaillé
// vient à l'étape 3). Sans voix (« --galerie »), chaque plan dure 2 secondes : pour regarder tous les composants.
// Aucune clé n'est affichée.
//
//   cd runner && node scripts/essai-montage.mjs <plan.json> <dossier de sortie> [voix | --galerie] [--voix-client]
// La voix peut être dans n'importe quel format (WAV, MP3, M4A, OGG de WhatsApp) : elle est convertie par FFmpeg.
// « --voix-client » : la voix est celle du client (pas de mention « voix créée par intelligence artificielle », les
// sous-titres suivent ce qu'il dit vraiment, corrigés seulement par les mots du plan).
// Variables : REVIDEO (dossier où Revideo 0.11.0 est installé), NAVIGATEUR (Chrome sans interface).
import { execFileSync } from 'node:child_process'
import { cpSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { planPropre } from '../src/videos/montage/schema.js'
import { calerSurLaVoix, controlerRythme } from '../src/videos/montage/temps.js'
import { codeMontage, codeProjetMontage } from '../src/videos/montage/code.js'
import { fichierSrt, transcrireMotParMot } from '../src/videos/voix.js'
import { lignesSrt, sousTitresDuMontage } from '../src/videos/montage/sous-titres.js'

const [fichierPlan, sortie, voixOuGalerie, option] = process.argv.slice(2)
const voixClient = option === '--voix-client'
const revideo = process.env.REVIDEO
const navigateur = process.env.NAVIGATEUR
if (!fichierPlan || !sortie || !revideo || !navigateur) {
  console.error('Usage : REVIDEO=… NAVIGATEUR=… node scripts/essai-montage.mjs <plan.json> <sortie> [voix | --galerie] [--voix-client]')
  process.exit(1)
}
mkdirSync(sortie, { recursive: true })
const { plan, erreurs } = planPropre(JSON.parse(readFileSync(fichierPlan, 'utf8')))
if (!plan) {
  console.error('Plan refusé :', erreurs.join(' ; '))
  process.exit(1)
}

const DEBUT_VOIX = 0.25
let cale
let mots = []
let voix = null
if (voixOuGalerie === '--galerie' || !voixOuGalerie) {
  // Chaque plan dure 2 secondes, les éléments arrivent toutes les 0,35 seconde.
  let t = 0
  cale = { ...plan, duree: plan.plans.length * 2 + 1, plans: plan.plans.map((p) => ({ ...p, debut: (t += 2) - 2, duree: p.type === 'carte' ? 3 : 2, temps: {} })) }
} else {
  // N'importe quel format : converti en WAV mono 24 kHz (la transcription et le mixage lisent ce fichier).
  const wav = path.join(sortie, 'voix.wav')
  if (path.resolve(voixOuGalerie) !== path.resolve(wav)) execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', voixOuGalerie, '-ac', '1', '-ar', '24000', wav])
  voix = wav
  const r = await transcrireMotParMot(readFileSync(wav), 'voix.wav')
  console.log(`Transcription : ${r.service === 'groq' ? 'gratuite (Groq)' : 'OpenAI (secours)'}, ${r.mots.length} mots`)
  mots = r.mots
  writeFileSync(path.join(sortie, 'mots.json'), JSON.stringify(mots, null, 1))
  const c = calerSurLaVoix(plan, mots, { decalage: DEBUT_VOIX })
  if (!c.plan) {
    console.error('Calage impossible :', c.erreurs.join(' ; '))
    process.exit(1)
  }
  cale = c.plan
  // Les sous-titres mot par mot, avec l'orthographe du texte lu (voix de Kekeli) ou des mots du plan (voix du client).
  cale.sous_titres = sousTitresDuMontage(plan, mots, { decalage: DEBUT_VOIX, texte: voixClient ? '' : plan.texte_voix })
  cale.voix_ia = !voixClient
  writeFileSync(path.join(sortie, 'sous-titres.srt'), fichierSrt(lignesSrt(cale.sous_titres)))
  console.log(`Sous-titres : ${cale.sous_titres.length} blocs ; ${cale.sous_titres.map((b) => b.mots.map((m) => m.texte).join(' ')).join(' | ')}`)
  const rythme = controlerRythme(cale)
  console.log(rythme.length ? `Rythme : ${rythme.join(' ; ')}` : 'Rythme : bon (aucun plan de plus de 2,5 secondes)')
}
writeFileSync(path.join(sortie, 'plan-cale.json'), JSON.stringify(cale, null, 1))

// Le projet Revideo : la bibliothèque de Kekeli copiée, le code de la vidéo écrit à partir du plan.
const socle = new URL('../src/videos/socle/', import.meta.url).pathname
rmSync(path.join(revideo, 'src'), { recursive: true, force: true })
mkdirSync(path.join(revideo, 'src', 'scenes'), { recursive: true })
cpSync(path.join(socle, 'src', 'montage'), path.join(revideo, 'src', 'montage'), { recursive: true })
cpSync(path.join(socle, 'rendre.mjs'), path.join(revideo, 'rendre.mjs'))
writeFileSync(path.join(revideo, 'src', 'scenes', 'video.tsx'), codeMontage(cale))
writeFileSync(path.join(revideo, 'src', 'projet.ts'), codeProjetMontage(cale))
rmSync(path.join(revideo, 'sortie'), { recursive: true, force: true })

const debut = Date.now()
const journal = execFileSync('node', ['rendre.mjs'], { cwd: revideo, env: { ...process.env, DISABLE_TELEMETRY: 'true', KEKELI_NAVIGATEUR: navigateur }, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 })
const secondes = Math.round((Date.now() - debut) / 100) / 10
const alertes = journal.split('\n').filter((l) => l.startsWith('ALERTE_'))
console.log(`Rendu en ${secondes} s${alertes.length ? ` ; alertes : ${alertes.join(' | ')}` : ' ; aucune alerte de texte'}`)
// La voix est prolongée par du silence (« apad ») : la vidéo garde toute sa durée, carte finale comprise.
const images = path.join(revideo, 'sortie', 'images.mp4')
const video = path.join(sortie, 'video.mp4')
if (mots.length) {
  execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', images, '-itsoffset', String(DEBUT_VOIX), '-i', voix, '-map', '0:v', '-map', '1:a', '-af', 'apad', '-c:v', 'libx264', '-crf', '20', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '192k', '-shortest', video])
} else {
  execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', images, '-c:v', 'libx264', '-crf', '20', '-pix_fmt', 'yuv420p', video])
}
// La planche : une image toutes les 0,5 seconde, plus l'image 0 (la miniature).
execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', video, '-frames:v', '1', path.join(sortie, 'miniature.png')])
execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', video, '-vf', 'fps=2,scale=270:-1', path.join(sortie, 'image-%03d.png')])
execFileSync('bash', ['-c', `montage ${JSON.stringify(sortie)}/image-*.png -tile 10x -geometry +3+3 -background '#222' ${JSON.stringify(path.join(sortie, 'planche.jpg'))} && rm ${JSON.stringify(sortie)}/image-*.png`])
console.log(`Vidéo : ${video} (${cale.duree} s)`)
