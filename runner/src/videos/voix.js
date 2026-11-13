// La voix off et les sous-titres d'une vidéo. La voix de Kekeli est créée phrase par phrase (modèle de synthèse
// vocale d'OpenAI, depuis le Runner qui garde la clé) : la durée réelle de chaque phrase donne les sous-titres exacts.
// La voix du client (fichier audio) est transcrite mot à mot pour les sous-titres. La voix créée par intelligence
// artificielle est signalée au client (règles d'utilisation d'OpenAI).

import { valeurCle } from '../fournisseurs.js'

const API_OPENAI = 'https://api.openai.com/v1'
export const MODELES_VOIX = ['gpt-4o-mini-tts', 'gpt-4o-mini-tts-2025-12-15']
export const VOIX = ['marin', 'cedar', 'coral', 'sage', 'ash', 'nova', 'shimmer', 'alloy', 'echo', 'fable', 'onyx', 'ballad', 'verse']
export const MODELES_TRANSCRIPTION = ['whisper-1']

/**
 * Une phrase dite par la voix de Kekeli (WAV). Le ton est donné par des consignes (français naturel, accent neutre).
 * @returns {Promise<Buffer>}
 */
export async function direPhrase(phrase, { modele = 'gpt-4o-mini-tts', voix = 'marin', style = '' } = {}, { appeler = fetch, env = process.env } = {}) {
  if (!MODELES_VOIX.includes(modele) || !VOIX.includes(voix)) throw new Error('voix refusée')
  const consignes = `Parle en français naturel, avec une diction claire et un accent neutre, comme une voix off professionnelle de publicité. Ton : ${String(style || 'chaleureux et dynamique').slice(0, 120)}. Dis les numéros de téléphone chiffre par chiffre ou deux par deux, lentement.`
  const reponse = await appeler(`${API_OPENAI}/audio/speech`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${valeurCle('OPENAI_API_KEY', env)}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ model: modele, voice: voix, input: textePrononce(phrase).slice(0, 1000), instructions: consignes, response_format: 'wav' }),
    signal: AbortSignal.timeout(90000),
  })
  if (!reponse.ok) {
    const erreur = await reponse.text().catch(() => '')
    throw Object.assign(new Error(`voix impossible (${reponse.status} ${erreur.slice(0, 160)})`), { limite: reponse.status === 429 })
  }
  return Buffer.from(await reponse.arrayBuffer())
}

export const MODELES_VOIX_GEMINI = ['gemini-3.8-flash-tts', 'gemini-3.8-flash-lite-tts']
export const VOIX_GEMINI = ['Kore', 'Puck', 'Charon', 'Zephyr', 'Aoede', 'Achird', 'Sulafat', 'Iapetus', 'Erinome', 'Schedar', 'Laomedeia', 'Sadachbia', 'Vindemiatrix', 'Alnilam', 'Gacrux']

/**
 * Une phrase dite par une voix gratuite de Gemini (offre gratuite de Google AI Studio, interface « Interactions »,
 * WAV 24 kHz) ; le ton est donné en langage naturel (« speech_metadata »), la langue est reconnue toute seule.
 * @returns {Promise<Buffer>}
 */
export async function direAvecGemini(phrase, { modele = 'gemini-3.8-flash-tts', voix = 'Kore', style = '' } = {}, { appeler = fetch, env = process.env } = {}) {
  if (!MODELES_VOIX_GEMINI.includes(modele) || !VOIX_GEMINI.includes(voix)) throw new Error('voix refusée')
  const corps = {
    model: modele,
    input: [{ type: 'user_input', content: [{ type: 'text', text: textePrononce(phrase).slice(0, 1000), annotations: [{ type: 'speech_metadata', style: `voix off professionnelle de publicité, français naturel, diction claire, ${String(style || 'chaleureuse et dynamique').slice(0, 120)}` }] }] }],
    response_format: { type: 'audio' },
    generation_config: { speech_config: [{ voice: voix }] },
  }
  const reponse = await appeler('https://generativelanguage.googleapis.com/v1beta/interactions', { method: 'POST', headers: { 'x-goog-api-key': valeurCle('GEMINI_API_KEY', env), 'Content-Type': 'application/json' }, body: JSON.stringify(corps), signal: AbortSignal.timeout(90000) })
  const texte = await reponse.text()
  if (!reponse.ok) throw Object.assign(new Error(`voix impossible (${reponse.status} ${texte.slice(0, 160)})`), { limite: reponse.status === 429 })
  const d = JSON.parse(texte)
  const audio = (d.steps ?? []).flatMap((e) => e.content ?? []).find((c) => typeof c.data === 'string')?.data ?? d.output_audio?.data
  if (!audio) throw new Error('voix vide')
  return Buffer.from(audio, 'base64')
}

/**
 * La voix off d'une phrase, dans l'ordre : les voix gratuites de Gemini (si sa clé est dans le Runner), puis la voix
 * d'OpenAI si elle est permise (plafond du jour). Une voix à sa limite passe à la suivante.
 * @returns {Promise<{ octets: Buffer, fournisseur: string, modele: string }>}
 */
export async function direVoixOff(phrase, { gratuites = [], payante = null, style = '' } = {}, { appeler = fetch, env = process.env, misesDeCote = null, patienceMs = 0, dormir = (ms) => new Promise((r) => setTimeout(r, ms)) } = {}) {
  const erreurs = []
  for (const v of gratuites) {
    if (!valeurCle('GEMINI_API_KEY', env) || misesDeCote?.has?.(`voix:${v.modele}`)) continue
    // L'offre gratuite limite chaque voix à quelques phrases par minute : on attend le délai indiqué (si la patience le
    // permet) plutôt que de changer de voix au milieu d'une vidéo.
    for (let essai = 0; essai < 3; essai++) {
      try {
        return { octets: await direAvecGemini(phrase, { modele: v.modele, voix: v.voix, style }, { appeler, env }), fournisseur: 'gemini', modele: v.modele }
      } catch (erreur) {
        erreurs.push(erreur.message)
        // La limite du jour (« per day », « retry in 13h… ») : inutile d'attendre, la voix est mise de côté.
        const duJour = /per day|retry in \d+h/i.test(erreur.message)
        const attente = erreur.limite && !duJour ? Math.min(65, Number(/retry in (\d+(?:\.\d+)?)s/i.exec(erreur.message)?.[1] ?? 30)) * 1000 + 1000 : Infinity
        if (!erreur.limite || attente > patienceMs) {
          if (erreur.limite) misesDeCote?.add?.(`voix:${v.modele}`)
          break
        }
        patienceMs -= attente
        await dormir(attente)
      }
    }
  }
  if (payante?.permise) {
    return { octets: await direPhrase(phrase, { modele: payante.modele, voix: payante.voix, style }, { appeler, env }), fournisseur: 'openai', modele: payante.modele }
  }
  throw new Error(`aucune voix disponible (${erreurs.join(' ; ').slice(0, 200)})`)
}

/**
 * La transcription mot à mot d'un fichier audio du client (pour ses sous-titres).
 * @returns {Promise<{ texte: string, mots: {mot: string, debut: number, fin: number}[], duree: number }>}
 */
export async function transcrireVoix(octets, nom, { modele = 'whisper-1' } = {}, { appeler = fetch, env = process.env } = {}) {
  if (!MODELES_TRANSCRIPTION.includes(modele)) throw new Error('transcription refusée')
  const formulaire = new FormData()
  formulaire.append('model', modele)
  formulaire.append('response_format', 'verbose_json')
  formulaire.append('timestamp_granularities[]', 'word')
  formulaire.append('file', new Blob([octets], { type: 'audio/wav' }), nom)
  const reponse = await appeler(`${API_OPENAI}/audio/transcriptions`, { method: 'POST', headers: { Authorization: `Bearer ${valeurCle('OPENAI_API_KEY', env)}` }, body: formulaire, signal: AbortSignal.timeout(180000) })
  if (!reponse.ok) throw new Error(`transcription impossible (${reponse.status})`)
  const d = await reponse.json()
  return {
    texte: String(d.text ?? ''),
    mots: (d.words ?? []).map((m) => ({ mot: String(m.word ?? '').trim(), debut: Number(m.start) || 0, fin: Number(m.end) || 0 })).filter((m) => m.mot),
    duree: Number(d.duration) || 0,
  }
}

/**
 * Coupe un texte en sous-titres : une PHRASE ENTIÈRE à la fois (deux lignes à l'écran au plus). Une phrase trop longue
 * est coupée à une virgule ou à un deux-points, jamais au milieu d'un groupe de mots, d'un montant ou d'un numéro.
 */
export function morceaux(texte, { caracteres = 84 } = {}) {
  const phrases = String(texte).replace(/\s+/g, ' ').trim().split(/(?<=[.!?…])\s+/).filter(Boolean)
  const sortie = []
  const couper = (morceau) => {
    if (morceau.length <= caracteres) return [morceau]
    // Les coupures possibles : après une virgule, un point-virgule ou un deux-points, jamais dans un nombre (« 12 000 »).
    const coupures = [...morceau.matchAll(/[,;:](?!\d)\s/g)].map((m) => m.index + 1)
    if (!coupures.length) return [morceau]
    const milieu = morceau.length / 2
    const i = coupures.reduce((a, b) => (Math.abs(b - milieu) < Math.abs(a - milieu) ? b : a))
    return [...couper(morceau.slice(0, i).trim()), ...couper(morceau.slice(i).trim())]
  }
  for (const phrase of phrases) sortie.push(...couper(phrase))
  return sortie
}

const motsDe = (t) => String(t).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9% ]/g, ' ').split(/\s+/).filter((m) => m.length > 1)

/** Un sous-titre qui répète le texte déjà affiché dans la scène (titre ou texte) n'est pas montré. */
export function repeteLEcran(sousTitre, affiches = []) {
  const mots = motsDe(sousTitre)
  if (!mots.length) return true
  // Tout ce qui est écrit à l'écran pendant la scène, ensemble (titre, texte, prix, contact).
  const ecran = new Set(affiches.filter(Boolean).flatMap(motsDe))
  if (!ecran.size) return false
  return mots.filter((m) => ecran.has(m)).length / mots.length >= 0.8
}

/** Le texte lu par la voix : les montants et les sigles dits comme on les dit en Afrique de l'Ouest. */
export function textePrononce(phrase) {
  return String(phrase)
    .replace(/\bF\s?CFA\b/g, 'francs CFA')
    .replace(/(\d)\s(?=\d{3}\b)/g, '$1')
    .replace(/\+228/g, 'plus deux cent vingt-huit,')
}

/**
 * Les sous-titres d'une phrase de la voix de Kekeli : ses morceaux répartis sur sa durée réelle, selon leur longueur.
 * @returns {{debut: number, fin: number, texte: string}[]}
 */
export function sousTitresPhrase(phrase, debut, duree, affiches = []) {
  const liste = morceaux(phrase)
  const total = liste.reduce((t, m) => t + m.length, 0) || 1
  let t = debut
  return liste.map((m) => {
    const d = (duree * m.length) / total
    const ligne = { debut: arrondi(t), fin: arrondi(t + d), texte: m }
    t += d
    return ligne
  }).filter((l) => !repeteLEcran(l.texte, affiches))
}

/** Les sous-titres de la voix du client, à partir des mots transcrits (avec leur temps exact). */
export function sousTitresMots(mots, decalage = 0) {
  const lignes = []
  let courant = []
  const fermer = () => {
    if (!courant.length) return
    lignes.push({ debut: arrondi(courant[0].debut + decalage), fin: arrondi(courant[courant.length - 1].fin + decalage), texte: courant.map((m) => m.mot).join(' ') })
    courant = []
  }
  for (const m of mots) {
    // Une phrase entière par sous-titre ; une longue pause ou une phrase très longue coupent aussi (après une virgule).
    const longueur = [...courant, m].map((x) => x.mot).join(' ').length
    if (courant.length && (m.debut - courant[courant.length - 1].fin > 0.8 || (longueur > 84 && /,$/.test(courant[courant.length - 1].mot)))) fermer()
    courant.push(m)
    if (/[.!?…]$/.test(m.mot)) fermer()
  }
  fermer()
  return lignes
}

const arrondi = (n) => Math.round(n * 100) / 100

const temps = (s) => {
  const ms = Math.max(0, Math.round(s * 1000))
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  const sec = Math.floor((ms % 60000) / 1000)
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')},${String(ms % 1000).padStart(3, '0')}`
}

/** Le fichier de sous-titres (SRT), livré avec la vidéo pour les réseaux sociaux qui l'acceptent. */
export function fichierSrt(lignes) {
  return lignes.map((l, i) => `${i + 1}\n${temps(l.debut)} --> ${temps(l.fin)}\n${l.texte}\n`).join('\n')
}
