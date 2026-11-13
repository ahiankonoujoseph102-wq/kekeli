// Montage vidéo et motion design, comme Claude Code : Kekeli écrit le code qui fabrique la vidéo (projet Revideo,
// licence MIT) et le lance dans un ordinateur E2B isolé, sans clé et sans Internet une fois ses outils installés.
// Le Runner (qui garde les clés) écrit le scénario avec le modèle de construction, crée la voix off, cherche et
// télécharge les médias ; l'ordinateur fait le rendu des images (navigateur sans écran), le mixage du son (FFmpeg) et
// les vérifications (durée, saturation, silences, images extraites) ; la vidéo MP4 part ensuite au site.

import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { entetesSignes } from '../signature.js'
import { telechargerFichier } from '../sauvegarde.js'
import { GENERATEURS, generer, SANS_TEXTE } from '../documents/images.js'
import { rediger } from '../documents/redaction.js'
import { dateDuJour } from '../repondant.js'
import { FORMATS_VIDEO, codeProjet, codeVideo, dureeMontage } from './code.js'
import { CONSIGNES_VIDEO, consigneVideo, motsMax, motsVoix, scenarioPropre } from './scenario.js'
import { LICENCES, chercherMediaLibre, chercherMusique, commandeMusiqueKekeli, telechargerMedia } from './medias.js'
import { direVoixOff, fichierSrt, sousTitresMots, sousTitresPhrase, transcrireVoix } from './voix.js'

const ICI = path.dirname(fileURLToPath(import.meta.url))
const GRATUIT = 'cloudflare/flux-1-schnell'

/**
 * L'ordre des sources d'images d'une scène, selon la formule (réglages « images » du site, comme pour les affiches) :
 * formules payantes, le générateur payant, puis les banques libres, puis le générateur gratuit ; Gratuite (ou plafond
 * OpenAI du jour atteint), le générateur gratuit puis les banques libres.
 */
export function ordreImages(images = {}) {
  const generateur = GENERATEURS.includes(images?.generateur) ? images.generateur : GRATUIT
  return generateur.startsWith('openai/') ? [generateur, 'libre', GRATUIT] : [GRATUIT, 'libre']
}

/** La demande faite au générateur pour le fond d'une scène : photo réaliste, sans aucun texte. */
export function inviteImageVideo(description) {
  return `${String(description).replace(/no text[^.]*\.?/gi, '').trim()} Photorealistic, high quality, cinematic light, shallow depth of field, softly blurred background. No shop sign, no billboard, no neon sign, no screen showing text or code, no sign, no poster, no document, no book cover. ${SANS_TEXTE}`
}
export const DOSSIER_OUTILS = '/home/user/.kekeli-video'
export const NAVIGATEUR = '/home/user/.kekeli-chrome'
const VERSION_NAVIGATEUR = '153.0.8010.12'

/** Versions exactes des outils, installées dans chaque atelier vidéo (mises à jour volontairement, après essai). */
export const INSTALLATION_VIDEO = [
  { root: true, commande: 'node -e "process.exit(Number(process.versions.node.split(\'.\')[0]) >= 22 ? 0 : 1)" || (npm install -g n --silent >/dev/null 2>&1 && n 22 >/dev/null 2>&1)' },
  { root: true, commande: 'DEBIAN_FRONTEND=noninteractive apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends ffmpeg imagemagick fonts-inter libnspr4 libnss3 libatk1.0-0 libatk-bridge2.0-0 libdbus-1-3 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libxkbcommon0 libasound2 libatspi2.0-0 >/dev/null 2>&1' },
  { root: false, commande: `mkdir -p ${DOSSIER_OUTILS} && cd ${DOSSIER_OUTILS} && PUPPETEER_SKIP_DOWNLOAD=1 npm install --no-audit --no-fund --loglevel=error >/tmp/kekeli-video-npm.log 2>&1` },
  { root: true, commande: `npx --yes @puppeteer/browsers@2.13.0 install chrome-headless-shell@${VERSION_NAVIGATEUR} --path ${NAVIGATEUR} >/tmp/kekeli-chrome.log 2>&1 && chown -R user:user ${NAVIGATEUR}` },
]

const CHEMIN_NAVIGATEUR = `${NAVIGATEUR}/chrome-headless-shell/linux-${VERSION_NAVIGATEUR}/chrome-headless-shell-linux64/chrome-headless-shell`
const ENV_RENDU = `DISABLE_TELEMETRY=true KEKELI_NAVIGATEUR=${CHEMIN_NAVIGATEUR}`

async function fichierSocle(nom) {
  return readFile(path.join(ICI, 'socle', nom), 'utf8')
}

/** L'atelier vidéo : un ordinateur E2B (4 Go de mémoire) par projet, outils installés une fois, puis coupé d'Internet. */
export function creerAtelierVideo({ Sandbox, journal = console, dureeVieMs = 20 * 60 * 1000 }) {
  const cache = new Map()
  return {
    async obtenir(projet) {
      const deja = cache.get(projet)
      if (deja && (await deja.isRunning().catch(() => false))) {
        await deja.setTimeout(dureeVieMs).catch(() => {})
        return deja
      }
      const debut = Date.now()
      const sandbox = await Sandbox.create('opencode', { timeoutMs: dureeVieMs, metadata: { kekeli_projet: projet, kekeli_role: 'videos' } })
      await sandbox.files.write(`${DOSSIER_OUTILS}/package.json`, await fichierSocle('package.json'))
      for (const etape of INSTALLATION_VIDEO) {
        const fait = await sandbox.commands.run(etape.commande, { user: etape.root ? 'root' : 'user', timeoutMs: 900000 }).catch((e) => e)
        if (fait?.exitCode && fait.exitCode !== 0) throw Object.assign(new Error(`installation de l'atelier vidéo impossible (${String(fait.stderr ?? fait.message ?? '').slice(0, 200)})`), { raison: 'atelier' })
      }
      await sandbox.files.write(`${DOSSIER_OUTILS}/socle/kekeli.tsx`, await fichierSocle('src/kekeli.tsx'))
      await sandbox.files.write(`${DOSSIER_OUTILS}/socle/rendre.mjs`, await fichierSocle('rendre.mjs'))
      // Plus d'Internet : l'ordinateur ne fait que monter la vidéo avec les fichiers que le Runner lui donne.
      await sandbox.updateNetwork({ denyOut: ['0.0.0.0/0'] }).catch((erreur) => journal.warn(`Réseau de l'atelier vidéo non coupé : ${erreur.message}`))
      journal.log(`Atelier vidéo prêt pour le projet ${projet} en ${Math.round((Date.now() - debut) / 1000)} secondes.`)
      cache.set(projet, sandbox)
      return sandbox
    },
  }
}

/** Les mesures d'un fichier (taille, durée, son), lues par FFprobe dans l'ordinateur. */
async function mesurer(sandbox, chemin) {
  const fait = await sandbox.commands.run(`ffprobe -v error -print_format json -show_streams -show_format ${chemin}`, { timeoutMs: 60000 }).catch(() => null)
  try {
    const d = JSON.parse(String(fait?.stdout ?? ''))
    const image = (d.streams ?? []).find((s) => s.codec_type === 'video')
    return {
      largeur: image?.width ?? 0,
      hauteur: image?.height ?? 0,
      duree: Number(d.format?.duration) || 0,
      son: (d.streams ?? []).some((s) => s.codec_type === 'audio'),
    }
  } catch {
    return { largeur: 0, hauteur: 0, duree: 0, son: false }
  }
}

const ecrire = async (sandbox, chemin, octets) => sandbox.files.write(chemin, octets.buffer.slice(octets.byteOffset, octets.byteOffset + octets.byteLength))
const ms = (s) => Math.max(0, Math.round(s * 1000))

/** Envoie un fichier au site en morceaux de 8 Mo (une vidéo peut dépasser la taille d'une seule requête). */
export async function envoyerEnMorceaux(url, secret, nom, octets, { appeler = fetch, taille = 8 * 1024 * 1024 } = {}) {
  const corps = Buffer.from(octets)
  const parties = Math.max(1, Math.ceil(corps.length / taille))
  for (let i = 0; i < parties; i++) {
    const morceau = corps.subarray(i * taille, Math.min(corps.length, (i + 1) * taille))
    const adresse = `${url}${url.includes('?') ? '&' : '?'}nom=${encodeURIComponent(nom)}${parties > 1 ? `&partie=${i}&parties=${parties}` : ''}`
    for (let essai = 1; ; essai++) {
      try {
        const reponse = await appeler(adresse, { method: 'POST', headers: { 'Content-Type': 'application/octet-stream', Accept: 'application/json', ...entetesSignes(secret, morceau) }, body: morceau })
        if (reponse.ok) break
        throw new Error(`le site a répondu ${reponse.status}`)
      } catch (erreur) {
        if (essai >= 3) throw new Error(`Envoi du fichier ${nom} impossible : ${erreur.message}`)
        await new Promise((r) => setTimeout(r, 1000 * essai))
      }
    }
  }
}

/** Le filtre FFmpeg du son : voix, extraits des vidéos du client, musique baissée sous la voix, volume final sans saturation. */
export function filtreSon({ voix = [], extraits = [], musique = null, duree }) {
  const parties = []
  const sorties = []
  let index = 1
  const voixNoms = []
  for (const v of voix) {
    parties.push(`[${index}:a]aresample=48000,aformat=channel_layouts=stereo,adelay=${ms(v.debut)}:all=1[v${index}]`)
    voixNoms.push(`[v${index}]`)
    index++
  }
  const extraitsNoms = []
  for (const e of extraits) {
    parties.push(`[${index}:a]aresample=48000,aformat=channel_layouts=stereo,atrim=${e.debutSource}:${e.debutSource + e.duree},asetpts=PTS-STARTPTS,afade=t=in:d=0.15,afade=t=out:st=${Math.max(0, e.duree - 0.25)}:d=0.25,volume=${voix.length ? 0.35 : 0.9},adelay=${ms(e.debut)}:all=1[e${index}]`)
    extraitsNoms.push(`[e${index}]`)
    index++
  }
  if (voixNoms.length) {
    parties.push(`${voixNoms.join('')}amix=inputs=${voixNoms.length}:normalize=0,apad=whole_dur=${duree}${musique ? ',asplit=2[voix][cle]' : '[voix]'}`)
    sorties.push('[voix]')
  }
  if (musique) {
    parties.push(`[${index}:a]aresample=48000,aformat=channel_layouts=stereo,atrim=0:${duree},asetpts=PTS-STARTPTS,volume=${voixNoms.length ? 0.32 : 0.55},afade=t=in:d=0.8,afade=t=out:st=${Math.max(0, duree - 2)}:d=2[mus]`)
    if (voixNoms.length) {
      // La musique baisse toute seule quand la voix parle (compression par la voix).
      parties.push('[mus][cle]sidechaincompress=threshold=0.015:ratio=10:attack=20:release=400:makeup=1[musb]')
      sorties.push('[musb]')
    } else {
      sorties.push('[mus]')
    }
    index++
  }
  sorties.push(...extraitsNoms)
  if (!sorties.length) return null
  parties.push(`${sorties.join('')}amix=inputs=${sorties.length}:normalize=0,atrim=0:${duree},loudnorm=I=-15:TP=-1.5:LRA=11,aresample=48000,aformat=sample_fmts=fltp:channel_layouts=stereo,alimiter=limit=0.8:level=false[son]`)
  return parties.join(';')
}

/**
 * Une version d'une vidéo, du scénario au fichier MP4 envoyé au site. « envoyer » transmet les événements (étapes,
 * consommation, progression du rendu, fin) ; sa réponse peut demander l'arrêt (« Arrêter », énergie épuisée).
 */
export async function produireVideo(demande, { atelierVideo, envoyer, secret, clesPresentes, misesDeCote = null, appeler = fetch, env = process.env, journal = console, extrait = null }) {
  const doc = demande.document
  const reglages = demande.video ?? {}
  const dureeMax = Math.max(5, Math.min(600, Number(reglages.duree_max) || 30))
  const arret = (r) => Boolean(r?.arreter)
  const dire = (texte) => envoyer({ type: 'narration', texte })
  const compter = async (r) => {
    if (!r?.consommation) return null
    await envoyer({ type: 'fournisseur', fournisseur: r.consommation.fournisseur, modele: r.consommation.modele })
    return envoyer({ type: 'requete', ...r.consommation })
  }
  const debutTravail = Date.now()
  // Arrêt demandé (« Arrêter », énergie épuisée, plafond du jour) : le site clôt le travail, rien n'est envoyé de plus.
  const arreter = async () => {
    await envoyer({ type: 'arretee' }).catch(() => {})
    return { arrete: true }
  }

  await envoyer({ type: 'demarrage' })
  await dire('J’écris le script de la vidéo.')
  const sandbox = await atelierVideo.obtenir(demande.projet)
  const dossier = `/home/user/videos/${String(demande.tache).replace(/[^A-Za-z0-9_-]/g, '')}`
  await sandbox.commands.run(`rm -rf ${dossier} && mkdir -p ${dossier}/src/scenes ${dossier}/public/medias ${dossier}/sons ${dossier}/sortie && ln -s ${DOSSIER_OUTILS}/node_modules ${dossier}/node_modules && cp ${DOSSIER_OUTILS}/package.json ${dossier}/ && cp ${DOSSIER_OUTILS}/socle/kekeli.tsx ${dossier}/src/ && cp ${DOSSIER_OUTILS}/socle/rendre.mjs ${dossier}/`, { timeoutMs: 60000 })

  // 1. Les fichiers du client (photos, vidéos, son), lus par le Runner avec des adresses signées, préparés dans l'ordinateur.
  const fichiers = []
  const joints = (Array.isArray(doc.joints) ? doc.joints : []).filter((j) => /^https?:\/\//.test(String(j?.url)) && ['image', 'video', 'audio'].includes(j.type)).slice(0, 12)
  if (joints.length) await dire('Je prépare vos photos et vos vidéos.')
  for (const [i, j] of joints.entries()) {
    const numero = i + 1
    try {
      const octets = await telechargerFichier(j.url, secret)
      const brut = `${dossier}/sons/brut-${numero}`
      await ecrire(sandbox, brut, octets)
      // Un enregistrement de la voix (« Enregistrer ma voix ») peut arriver dans un fichier vidéo sans image : c'est un son.
      const type = j.type === 'video' && !(await mesurer(sandbox, brut)).largeur ? 'audio' : j.type
      if (type === 'image') {
        // Photo du téléphone : remise droite (orientation), taille raisonnable.
        await sandbox.commands.run(`convert ${brut} -auto-orient -resize '2160x2160>' -quality 90 ${dossier}/public/medias/client-${numero}.jpg`, { timeoutMs: 120000 })
        const m = await mesurer(sandbox, `${dossier}/public/medias/client-${numero}.jpg`)
        fichiers.push({ numero, type: 'image', fichier: `/medias/client-${numero}.jpg`, largeur: m.largeur, hauteur: m.hauteur, nom: j.nom ?? null })
      } else if (type === 'video') {
        // Vidéo du téléphone (souvent HEVC ou tournée) : convertie en H.264 lisible par le moteur, le son à part.
        await sandbox.commands.run(`ffmpeg -v error -y -i ${brut} -vf "scale='if(gt(iw,ih),min(1920,iw),-2)':'if(gt(iw,ih),-2,min(1920,ih))',fps=30,format=yuv420p" -c:v libx264 -preset veryfast -crf 20 -an ${dossier}/public/medias/client-${numero}.mp4`, { timeoutMs: 600000 })
        const m = await mesurer(sandbox, `${dossier}/public/medias/client-${numero}.mp4`)
        const avecSon = (await mesurer(sandbox, brut)).son
        if (avecSon) await sandbox.commands.run(`ffmpeg -v error -y -i ${brut} -vn -ac 2 -ar 48000 ${dossier}/sons/client-${numero}.wav`, { timeoutMs: 300000 })
        fichiers.push({ numero, type: 'video', fichier: `/medias/client-${numero}.mp4`, largeur: m.largeur, hauteur: m.hauteur, duree: m.duree, son: avecSon, nom: j.nom ?? null })
      } else {
        await sandbox.commands.run(`ffmpeg -v error -y -i ${brut} -vn -ac 1 -ar 48000 ${dossier}/sons/client-${numero}.wav`, { timeoutMs: 300000 })
        const m = await mesurer(sandbox, `${dossier}/sons/client-${numero}.wav`)
        fichiers.push({ numero, type: 'audio', fichier: `sons/client-${numero}.wav`, duree: m.duree, nom: j.nom ?? null })
      }
    } catch (erreur) {
      journal.warn?.(`Fichier ${numero} du client illisible : ${erreur.message}`)
    }
  }

  // 2. Le scénario, écrit par le modèle de construction (depuis le Runner, qui garde les clés).
  const precedente = doc.precedente ?? null
  // Les voix gratuites de Gemini d'abord (clé dans le Runner), la voix d'OpenAI en secours (si le plafond du jour le permet).
  const voixGratuites = (Array.isArray(reglages.voix_gratuites) ? reglages.voix_gratuites : []).filter((v) => v && typeof v.modele === 'string')
  const voixDeCote = new Set()
  let voixChoisie = null
  const voixPermise = Boolean(reglages.voix?.permise) || (voixGratuites.length > 0 && [...(clesPresentes ?? [])].includes('gemini'))
  const consigne = consigneVideo({ demande: doc.demande, dureeVoulue: Number(doc.duree_voulue) || 0, plan: doc.plan, precedente: precedente?.scenario ?? null, fichiers, dureeMax, format: doc.format, extrait, pays: doc.pays, monnaie: doc.monnaie, date: dateDuJour(), voixPermise })
  // La durée visée : celle demandée par le client, jamais plus que la durée maximale de sa formule.
  const cible = Math.min(dureeMax, Number(doc.duree_voulue) > 0 ? Number(doc.duree_voulue) + 1 : dureeMax)
  const r = await rediger({ systeme: CONSIGNES_VIDEO, consigne, fournisseurs: demande.fournisseurs, maxTokens: 8000 }, { clesPresentes, misesDeCote, appeler, env })
  if (arret(await compter(r))) return arreter()
  if (!r.ok) throw Object.assign(new Error('scénario impossible'), { raison: r.erreur })
  const refus = String(r.objet.refus ?? '').trim().slice(0, 600)
  if (refus) {
    await envoyer({ type: 'terminee', reponse: refus })
    return { ok: true, refus: true }
  }
  let scenario = scenarioPropre(r.objet.video ?? r.objet, { dureeMax, format: doc.format, fichiers })
  // Comme un monteur qui relit son texte : une voix off trop longue pour la durée visée est raccourcie une fois.
  const budget = motsMax(cible)
  const mots = motsVoix(scenario)
  if (voixPermise && scenario.voix_off && scenario.voix_source !== 'client' && mots > budget * 1.15) {
    const court = await rediger({ systeme: CONSIGNES_VIDEO, consigne: `${consigne}\n\nTon scénario (ci-dessous) a ${mots} mots de voix off : c'est trop pour environ ${Math.round(cible)} secondes. Réécris-le avec ${budget} mots de voix off AU PLUS en tout : phrases plus courtes, même sens, mêmes médias, rien d'inventé. Rends la réponse complète au même format.\n${JSON.stringify(r.objet)}`, fournisseurs: demande.fournisseurs, maxTokens: 8000 }, { clesPresentes, misesDeCote, appeler, env })
    if (arret(await compter(court))) return arreter()
    if (court.ok && !String(court.objet.refus ?? '').trim()) {
      const raccourci = scenarioPropre(court.objet.video ?? court.objet, { dureeMax, format: doc.format, fichiers })
      if (raccourci.scenes.length && motsVoix(raccourci) < mots) scenario = raccourci
    }
  }
  if (!scenario.scenes.length) throw Object.assign(new Error('scénario vide'), { raison: 'scenario' })
  const message = String(r.objet.message ?? '').trim().slice(0, 600)
  const titre = String(r.objet.titre_court ?? '').trim().slice(0, 120)
  const format = FORMATS_VIDEO[scenario.format]

  const clipsVoix = new Map()

  // 3. La voix off : phrase par phrase (durées réelles), ou la voix du client transcrite mot à mot.
  const sonsVoix = []
  let lignes = []
  const elements = [...scenario.scenes, ...(scenario.fin ? [scenario.fin] : [])]
  if (scenario.voix_off && scenario.voix_source === 'client') {
    const audio = fichiers.find((f) => f.type === 'audio')
    if (audio) {
      await dire('Je place votre voix et j’écris les sous-titres.')
      // Les scènes suivent la voix du client : leur durée est ajustée à la sienne.
      const total = elements.reduce((t, s) => t + s.duree, 0)
      const facteur = audio.duree > 0 ? Math.min(dureeMax, audio.duree + 0.6) / total : 1
      for (const s of elements) s.duree = Math.max(1, Math.round(s.duree * facteur * 100) / 100)
      sonsVoix.push({ chemin: `${dossier}/${audio.fichier}`, debut: 0, duree: audio.duree })
      if (reglages.transcription?.permise && scenario.sous_titres) {
        try {
          const octets = await sandbox.files.read(`${dossier}/${audio.fichier}`, { format: 'bytes' })
          const t = await transcrireVoix(Buffer.from(octets), 'voix.wav', { modele: reglages.transcription.modele }, { appeler, env })
          lignes = sousTitresMots(t.mots)
          if (arret(await envoyer({ type: 'voix', fournisseur: 'openai', modele: reglages.transcription.modele, secondes: Math.round(audio.duree), usage: 'transcription' }))) return arreter()
        } catch (erreur) {
          journal.warn?.(`Sous-titres de la voix du client impossibles : ${erreur.message}`)
        }
      }
    }
  } else if (scenario.voix_off && voixPermise) {
    await dire('J’enregistre la voix off.')
    for (const [i, s] of elements.entries()) {
      if (!s.voix) continue
      try {
        // Une seule voix pour toute la vidéo (même timbre du début à la fin) : celle de la première phrase.
        const voixPossibles = {
          gratuites: voixChoisie ? voixGratuites.filter((v) => v.modele === voixChoisie) : voixGratuites,
          payante: voixChoisie && voixChoisie !== reglages.voix?.modele ? null : reglages.voix,
          style: scenario.style_voix,
        }
        // Première phrase : la première voix libre, sans attendre ; ensuite (ou si toutes sont à leur limite), on attend.
        const dit = await direVoixOff(s.voix, voixPossibles, { appeler, env, misesDeCote: voixDeCote, patienceMs: voixChoisie ? 3 * 60 * 1000 : 0 })
          .catch(() => (voixDeCote.clear(), direVoixOff(s.voix, voixPossibles, { appeler, env, misesDeCote: voixDeCote, patienceMs: 3 * 60 * 1000 })))
        voixChoisie ??= dit.modele
        const chemin = `${dossier}/sons/voix-${i + 1}.wav`
        await ecrire(sandbox, chemin, dit.octets)
        const duree = (await mesurer(sandbox, chemin)).duree
        if (arret(await envoyer({ type: 'voix', fournisseur: dit.fournisseur, modele: dit.modele, secondes: Math.max(1, Math.round(duree)), caracteres: s.voix.length, usage: 'voix' }))) return arreter()
        clipsVoix.set(i, { chemin, duree })
      } catch (erreur) {
        journal.warn?.(`Voix de la scène ${i + 1} impossible : ${erreur.message}`)
      }
    }
    // La voix dépasse la durée visée : elle est un peu accélérée (15 % au plus, la voix reste naturelle).
    const voixTotale = [...clipsVoix.values()].reduce((t, c) => t + c.duree, 0)
    const sansVoix = elements.filter((s, i) => !clipsVoix.has(i)).reduce((t, s) => t + Math.min(s.duree, 1.5), 0)
    const place = cible - sansVoix - clipsVoix.size * 0.45
    const tempo = voixTotale > 0 && place > 0 ? Math.min(1.15, voixTotale / place) : 1
    if (tempo > 1.02) {
      for (const c of clipsVoix.values()) {
        await sandbox.commands.run(`ffmpeg -v error -y -i ${c.chemin} -filter:a atempo=${tempo.toFixed(3)} ${c.chemin}.rapide.wav && mv ${c.chemin}.rapide.wav ${c.chemin}`, { timeoutMs: 60000 })
        c.duree = (await mesurer(sandbox, c.chemin)).duree || c.duree / tempo
      }
    }
    // Chaque scène dure au moins le temps de sa phrase (un léger souffle avant et après).
    for (const [i, s] of elements.entries()) {
      const c = clipsVoix.get(i)
      if (c) s.duree = Math.max(s.duree, Math.round((c.duree + 0.45) * 100) / 100)
    }
  }
  // La vidéo dépasse la durée visée : les scènes sans voix raccourcissent, puis le temps libre des scènes avec voix.
  let duree = elements.reduce((t, s) => t + s.duree, 0)
  const raccourcir = (liste) => {
    for (const { s, min } of liste) {
      if (duree <= cible) break
      const gain = Math.min(s.duree - min, duree - cible)
      if (gain > 0) {
        s.duree = Math.round((s.duree - gain) * 100) / 100
        duree -= gain
      }
    }
  }
  const avecIndex = elements.map((s, i) => ({ s, i })).reverse()
  raccourcir(avecIndex.filter(({ i }) => !clipsVoix.has(i)).map(({ s }) => ({ s, min: 1.2 })))
  raccourcir(avecIndex.filter(({ i }) => clipsVoix.has(i)).map(({ s, i }) => ({ s, min: Math.max(1.2, clipsVoix.get(i).duree + 0.45) })))
  // Les voix de Kekeli sont posées à leur place, avec leurs sous-titres exacts.
  if (scenario.voix_source !== 'client') {
    let t = 0
    for (const [i, s] of elements.entries()) {
      const c = clipsVoix.get(i)
      if (c) {
        sonsVoix.push({ chemin: c.chemin, debut: t + 0.2, duree: c.duree })
        lignes.push(...sousTitresPhrase(s.voix, t + 0.2, c.duree, [s.titre, s.texte, s.prix, s.contact, s.paiement]))
      }
      t += s.duree
    }
  }
  if (!scenario.sous_titres) lignes = []

  // 4. Les médias de chaque scène : fichiers du client, médias libres (Pexels, Pixabay), images créées, fond animé.
  if (arret(await dire('Je prépare les images.'))) return arreter()
  const medias = []
  const orientation = scenario.format === 'paysage' ? 'landscape' : scenario.format === 'carre' ? 'square' : 'portrait'
  let imagesRestantes = Math.max(0, Number(reglages.images_max ?? 6))
  const precedentes = Object.fromEntries((Array.isArray(precedente?.medias) ? precedente.medias : []).filter((m) => m.scene).map((m) => [m.scene, m]))
  for (const [i, s] of scenario.scenes.entries()) {
    const numero = i + 1
    s.media.resolu = null
    if (s.media.source === 'client') {
      const f = fichiers.find((x) => x.numero === s.media.numero)
      if (f) {
        s.media.resolu = { fichier: f.fichier, type: f.type, largeur: f.largeur, hauteur: f.hauteur, ...(f.type === 'video' ? { debut: s.media.debut ?? 0 } : {}) }
        medias.push({ scene: numero, fichier: f.fichier, type: f.type, source: 'Fichier du client', licence: LICENCES.client.nom, nom: f.nom })
      }
      continue
    }
    // Une modification garde l'image de la version précédente quand la scène demande la même chose.
    const avant = precedentes[numero]
    if (avant?.url_version && avant.demande === (s.media.recherche || s.media.description) && doc.medias_precedents?.[avant.fichier_version]) {
      try {
        const octets = await telechargerFichier(doc.medias_precedents[avant.fichier_version], secret)
        const extension = avant.fichier_version.split('.').pop()
        await ecrire(sandbox, `${dossier}/public/medias/scene-${numero}.${extension}`, octets)
        const m = await mesurer(sandbox, `${dossier}/public/medias/scene-${numero}.${extension}`)
        s.media.resolu = { fichier: `/medias/scene-${numero}.${extension}`, type: avant.type, largeur: m.largeur, hauteur: m.hauteur }
        medias.push({ ...avant, scene: numero })
        continue
      } catch {
        // Sinon, un nouveau média.
      }
    }
    // Une image pour chaque scène, dans l'ordre de la formule : formules payantes, le générateur des affiches payantes
    // puis les banques libres puis le générateur gratuit ; Gratuite, le générateur gratuit puis les banques libres.
    // Le fond animé ne sert qu'en tout dernier recours.
    for (const essai of ordreImages(reglages.images)) {
      if (s.media.resolu) break
      if (essai === 'libre') {
        const voulue = s.media.recherche || s.media.description
        // Des photos seulement (les vidéos des banques commencent parfois par des images noires), jamais deux fois la même.
        const trouve = voulue ? await chercherMediaLibre(s.media.recherche || voulue, { type: 'image', orientation, exclure: medias.map((m) => m.page).filter(Boolean) }, { appeler, env }).catch(() => null) : null
        if (!trouve) continue
        try {
          const octets = await telechargerMedia(trouve.url, { appeler })
          const extension = trouve.type === 'video' ? 'mp4' : 'jpg'
          const brut = `${dossier}/sons/libre-${numero}`
          await ecrire(sandbox, brut, octets)
          if (trouve.type === 'video') await sandbox.commands.run(`ffmpeg -v error -y -i ${brut} -t 20 -vf "scale='if(gt(iw,ih),min(1920,iw),-2)':'if(gt(iw,ih),-2,min(1920,ih))',fps=30,format=yuv420p" -c:v libx264 -preset veryfast -crf 21 -an ${dossier}/public/medias/scene-${numero}.mp4`, { timeoutMs: 300000 })
          else await sandbox.commands.run(`convert ${brut} -auto-orient -resize '2160x2160>' -quality 90 ${dossier}/public/medias/scene-${numero}.jpg`, { timeoutMs: 120000 })
          const m = await mesurer(sandbox, `${dossier}/public/medias/scene-${numero}.${extension}`)
          if (!m.largeur) continue
          s.media.resolu = { fichier: `/medias/scene-${numero}.${extension}`, type: trouve.type, largeur: m.largeur, hauteur: m.hauteur }
          medias.push({ scene: numero, fichier: `/medias/scene-${numero}.${extension}`, type: trouve.type, source: trouve.source, licence: LICENCES[trouve.licence].nom, licence_url: LICENCES[trouve.licence].url, page: trouve.page, auteur: trouve.auteur, demande: voulue })
        } catch (erreur) {
          journal.warn?.(`Média libre de la scène ${numero} impossible : ${erreur.message}`)
        }
        continue
      }
      // Une image créée (le texte est toujours posé par le code, jamais dans l'image).
      if (imagesRestantes <= 0 || !(s.media.description || s.media.recherche)) continue
      try {
        const payant = essai.startsWith('openai/')
        const fait = await generer(essai, inviteImageVideo(s.media.description || s.media.recherche), scenario.format, { appeler, env, qualite: 'low' })
        imagesRestantes--
        if (arret(await envoyer(payant ? fait.cout : { type: 'images', nombre: 1 }))) return arreter()
        const extension = fait.extension === 'png' ? 'png' : 'jpg'
        await ecrire(sandbox, `${dossier}/sons/creee-${numero}.${extension}`, fait.octets)
        // Une image payante arrive en PNG : convertie en JPEG (plus légère, même rendu dans la vidéo).
        await sandbox.commands.run(`convert ${dossier}/sons/creee-${numero}.${extension} -quality 90 ${dossier}/public/medias/scene-${numero}.jpg`, { timeoutMs: 120000 })
        const m = await mesurer(sandbox, `${dossier}/public/medias/scene-${numero}.jpg`)
        s.media.resolu = { fichier: `/medias/scene-${numero}.jpg`, type: 'image', largeur: m.largeur, hauteur: m.hauteur }
        medias.push({ scene: numero, fichier: `/medias/scene-${numero}.jpg`, type: 'image', source: 'Image créée par Kekeli', licence: LICENCES.kekeli.nom, demande: s.media.description || s.media.recherche, fichier_version: `media-${numero}.jpg` })
      } catch (erreur) {
        journal.warn?.(`Image de la scène ${numero} impossible (${essai}) : ${erreur.message}`)
      }
    }
  }
  const logo = scenario.fin?.logo ? fichiers.find((f) => f.numero === scenario.fin.logo) : null

  // 5. La musique : libre CC0 (Openverse, Freesound) ou écrite par Kekeli.
  let musique = null
  if (scenario.musique.ambiance !== 'aucune') {
    await dire('Je choisis la musique.')
    const trouvee = await chercherMusique(scenario.musique.ambiance, duree, { appeler }).catch(() => null)
    if (trouvee) {
      try {
        const octets = await telechargerMedia(trouvee.url, { appeler, max: 20 * 1024 * 1024 })
        await ecrire(sandbox, `${dossier}/sons/musique-brute`, octets)
        const m = await mesurer(sandbox, `${dossier}/sons/musique-brute`)
        if (m.duree >= 5) {
          // Une musique plus courte que la vidéo est jouée en boucle.
          await sandbox.commands.run(`ffmpeg -v error -y -stream_loop -1 -i ${dossier}/sons/musique-brute -t ${Math.ceil(duree + 1)} -ac 2 -ar 48000 ${dossier}/sons/musique.wav`, { timeoutMs: 120000 })
          musique = { chemin: `${dossier}/sons/musique.wav`, ...trouvee }
          medias.push({ scene: null, fichier: 'musique', type: 'musique', titre: trouvee.titre, source: trouvee.source, licence: LICENCES.cc0.nom, licence_url: LICENCES.cc0.url, page: trouvee.page, auteur: trouvee.auteur })
        }
      } catch (erreur) {
        journal.warn?.(`Musique libre impossible : ${erreur.message}`)
      }
    }
    if (!musique) {
      await sandbox.commands.run(commandeMusiqueKekeli(scenario.musique.ambiance, duree, `${dossier}/sons/musique.mp3`), { timeoutMs: 120000 })
      musique = { chemin: `${dossier}/sons/musique.mp3` }
      medias.push({ scene: null, fichier: 'musique', type: 'musique', titre: 'Musique écrite par Kekeli', source: 'Kekeli', licence: LICENCES.kekeli.nom })
    }
  }

  // 6. Le code de la vidéo, écrit par Kekeli scène par scène, puis le rendu des images dans l'ordinateur isolé.
  const montage = {
    format: scenario.format,
    couleurs: scenario.couleurs,
    sous_titres: lignes.length > 0,
    scenes: scenario.scenes.map((s) => ({ ...s, media: s.media.resolu })),
    fin: scenario.fin ? { ...scenario.fin, logo: logo ? { fichier: logo.fichier, type: 'image', largeur: logo.largeur, hauteur: logo.hauteur } : null } : null,
    lignes,
  }
  duree = dureeMontage(montage)
  await sandbox.files.write(`${dossier}/src/scenes/video.tsx`, codeVideo(montage, { date: dateDuJour() }))
  await sandbox.files.write(`${dossier}/src/projet.ts`, codeProjet(montage))
  if (arret(await dire('Je monte la vidéo.'))) return arreter()

  let alertes = []
  let rendue = false
  for (let essai = 1; essai <= 3 && !rendue; essai++) {
    await sandbox.commands.run(`rm -rf ${dossier}/sortie/images.mp4`)
    let arrete = false
    let derniere = 0
    const sortie = []
    const commande = await sandbox.commands.run(`cd ${dossier} && ${ENV_RENDU} node rendre.mjs`, {
      background: true,
      timeoutMs: 1800000,
      onStdout: (ligne) => {
        sortie.push(String(ligne))
        const lue = /PROGRESSION (\d\.\d+)/.exec(String(ligne))
        if (lue) {
          const pourcentage = Math.min(99, Math.round(Number(lue[1]) * 100))
          if (pourcentage >= derniere + 10) {
            derniere = pourcentage
            envoyer({ type: 'rendu', pourcentage }).then((reponse) => {
              if (arret(reponse) && !arrete) {
                arrete = true
                commande.kill().catch(() => {})
              }
            }).catch(() => {})
          }
        }
      },
    })
    await commande.wait().catch(() => null)
    if (arrete) return arreter()
    const texte = sortie.join('\n')
    alertes = [...new Set([...texte.matchAll(/ALERTE_TEXTE ([^\n"]+)/g)].map((m) => m[1].trim()))]
    rendue = (await mesurer(sandbox, `${dossier}/sortie/images.mp4`)).duree > 0
    if (!rendue) journal.warn?.(`Rendu ${essai} de la vidéo impossible : ${texte.slice(-300)}`)
  }
  if (!rendue) throw Object.assign(new Error('rendu impossible'), { raison: 'rendu' })
  if (arret(await envoyer({ type: 'rendu', pourcentage: 100 }))) return arreter()

  // 7. Le son (voix, extraits du client, musique baissée sous la voix, volume final), puis la vidéo finale.
  await dire('Je mixe le son et je vérifie la vidéo.')
  const extraits = []
  if (scenario.son_origine !== false) {
    let t = 0
    for (const s of scenario.scenes) {
      const f = s.media.source === 'client' ? fichiers.find((x) => x.numero === s.media.numero && x.type === 'video' && x.son) : null
      if (f && (scenario.voix_source !== 'client')) extraits.push({ chemin: `${dossier}/sons/client-${f.numero}.wav`, debut: t, debutSource: s.media.debut ?? 0, duree: s.duree })
      t += s.duree
    }
  }
  // Le son d'origine des vidéos du client est gardé seulement pour un montage sans voix off (sinon la musique et la voix le remplacent).
  const garderOrigine = scenario.genre === 'montage' && !sonsVoix.length
  const filtre = filtreSon({ voix: sonsVoix, extraits: garderOrigine ? extraits : [], musique, duree })
  const entrees = [`-i ${dossier}/sortie/images.mp4`, ...sonsVoix.map((v) => `-i ${v.chemin}`), ...(garderOrigine ? extraits.map((e) => `-i ${e.chemin}`) : []), ...(musique ? [`-i ${musique.chemin}`] : [])]
  const son = filtre ? `-filter_complex "${filtre}" -map 0:v -map "[son]"` : `-f lavfi -t ${duree} -i anullsrc=r=48000:cl=stereo -map 0:v -map ${entrees.length}:a`
  const mixage = `ffmpeg -v error -y ${entrees.join(' ')} ${son} -t ${duree.toFixed(2)} -c:v libx264 -preset veryfast -crf 21 -maxrate 8M -bufsize 16M -pix_fmt yuv420p -r 30 -c:a aac -b:a 160k -ar 48000 -movflags +faststart ${dossier}/sortie/video.mp4`
  const mixe = await sandbox.commands.run(mixage, { timeoutMs: 900000 }).catch((e) => e)
  const finale = await mesurer(sandbox, `${dossier}/sortie/video.mp4`)
  if (!finale.duree) throw Object.assign(new Error(`mixage impossible (${String(mixe?.stderr ?? mixe?.message ?? '').slice(0, 200)})`), { raison: 'rendu' })

  // 8. Les vérifications : durée, saturation (crête sous 0 dB), silences anormaux, images extraites à plusieurs moments.
  const analyse = await sandbox.commands.run(`ffmpeg -v info -i ${dossier}/sortie/video.mp4 -af "volumedetect,silencedetect=n=-45dB:d=1.5" -vn -f null - 2>&1 | grep -E "max_volume|mean_volume|silence_(start|end)"`, { timeoutMs: 300000 }).catch((e) => e)
  const texteAnalyse = String(analyse?.stdout ?? '')
  const crete = Number(/max_volume: (-?[\d.]+) dB/.exec(texteAnalyse)?.[1] ?? -99)
  const moyenne = Number(/mean_volume: (-?[\d.]+) dB/.exec(texteAnalyse)?.[1] ?? -99)
  const silences = [...texteAnalyse.matchAll(/silence_start: (-?[\d.]+)/g)].map((m) => Math.round(Number(m[1]) * 10) / 10)
  const apercus = []
  for (const [i, part] of [0.12, 0.5, 0.88].entries()) {
    const nom = `apercu-${i + 1}.jpg`
    await sandbox.commands.run(`ffmpeg -v error -y -ss ${(finale.duree * part).toFixed(2)} -i ${dossier}/sortie/video.mp4 -frames:v 1 -vf "scale='if(gt(iw,ih),960,-2)':'if(gt(iw,ih),-2,960)'" -q:v 4 ${dossier}/sortie/${nom}`, { timeoutMs: 60000 })
    apercus.push(nom)
  }
  const verifications = {
    duree: Math.round(finale.duree * 10) / 10,
    duree_max: dureeMax,
    crete_db: crete,
    volume_moyen_db: moyenne,
    saturation: crete > -0.3,
    silences: (musique || sonsVoix.length) ? silences.filter((s) => s > 0.5 && s < finale.duree - 2.5) : [],
    textes_trop_longs: alertes,
    largeur: finale.largeur,
    hauteur: finale.hauteur,
  }

  // 9. Les fichiers partent au site : la vidéo (en morceaux), les sous-titres, la liste des médias, les images créées.
  const fichiersVersion = { mp4: 'video.mp4' }
  if (lignes.length) {
    await sandbox.files.write(`${dossier}/sortie/sous-titres.srt`, fichierSrt(lignes))
    fichiersVersion.srt = 'sous-titres.srt'
  }
  const listeMedias = medias.map(({ demande: _d, ...m }) => m)
  await sandbox.files.write(`${dossier}/sortie/medias.json`, JSON.stringify({ video: titre, medias: listeMedias, voix: sonsVoix.length && scenario.voix_source !== 'client' ? 'Voix off créée par intelligence artificielle.' : null, moteur: 'Revideo (licence MIT) et FFmpeg' }, null, 2))
  fichiersVersion.medias = 'medias.json'
  for (const m of medias.filter((x) => x.fichier_version)) {
    await sandbox.commands.run(`cp ${dossier}/public${m.fichier} ${dossier}/sortie/${m.fichier_version}`)
    fichiersVersion[m.fichier_version.replace(/\.\w+$/, '')] = m.fichier_version
  }
  await dire('J’envoie la vidéo.')
  for (const nom of [...Object.values(fichiersVersion), ...apercus]) {
    const octets = await sandbox.files.read(`${dossier}/sortie/${nom}`, { format: 'bytes' })
    await envoyerEnMorceaux(demande.envoi, secret, nom, octets, { appeler })
  }

  const gardee = {
    type: 'video',
    titre,
    format: scenario.format,
    largeur: format.largeur,
    hauteur: format.hauteur,
    duree: verifications.duree,
    scenario: { ...scenario, scenes: scenario.scenes.map(({ media, ...s }) => ({ ...s, media: { source: media.source, ...(media.numero ? { numero: media.numero } : {}), ...(media.debut ? { debut: media.debut } : {}), ...(media.recherche ? { recherche: media.recherche } : {}), ...(media.description ? { description: media.description } : {}) } })) },
    medias: medias.map((m) => ({ ...m, ...(m.fichier_version ? { url_version: true } : {}) })),
    voix_ia: sonsVoix.length > 0 && scenario.voix_source !== 'client',
    sous_titres: lignes.length > 0,
    verifications,
    rendu_secondes: Math.round((Date.now() - debutTravail) / 1000),
  }
  await envoyer({ type: 'document', titre, message, fichiers: fichiersVersion, apercus, pages: Math.round(verifications.duree), avertissements: alertes, structure: gardee, recherches: 0 })
  await envoyer({ type: 'terminee', reponse: message })
  journal.log(`Vidéo du projet ${demande.projet} : ${verifications.duree} secondes, rendue en ${gardee.rendu_secondes} secondes.`)
  return { ok: true, fichiers: fichiersVersion, apercus, verifications, structure: gardee }
}
