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
 * Coupe un texte en morceaux de sous-titres lisibles : phrase par phrase, chaque phrase en morceaux de taille égale
 * (7 mots et 42 caractères au plus), pour ne jamais laisser un mot seul à l'écran.
 */
export function morceaux(texte, { mots = 7, caracteres = 42 } = {}) {
  // Un numéro de téléphone ou un montant (« +228 90 12 34 56 », « 12 000 FCFA ») n'est jamais coupé en deux.
  const colle = (t) => t.replace(/(\d) (?=\d)/g, '$1\u00A0').replace(/(\d) (?=F?CFA\b|%)/g, '$1\u00A0')
  const phrases = colle(String(texte).replace(/\s+/g, ' ').trim()).split(/(?<=[.!?…])\s+/).filter(Boolean)
  const sortie = []
  for (const phrase of phrases) {
    const liste = phrase.split(' ')
    const nombre = Math.max(Math.ceil(liste.length / mots), Math.ceil(phrase.length / caracteres))
    const taille = Math.ceil(liste.length / nombre)
    for (let i = 0; i < liste.length; i += taille) sortie.push(liste.slice(i, i + taille).join(' ').replace(/\u00A0/g, ' '))
  }
  return sortie
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
export function sousTitresPhrase(phrase, debut, duree) {
  const liste = morceaux(phrase)
  const total = liste.reduce((t, m) => t + m.length, 0) || 1
  let t = debut
  return liste.map((m) => {
    const d = (duree * m.length) / total
    const ligne = { debut: arrondi(t), fin: arrondi(t + d), texte: m }
    t += d
    return ligne
  })
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
    const texte = [...courant, m].map((x) => x.mot).join(' ')
    if (courant.length && (courant.length >= 7 || texte.length > 42 || m.debut - courant[courant.length - 1].fin > 0.8)) fermer()
    courant.push(m)
    if (/[.!?…]$/.test(m.mot) && courant.length >= 3) fermer()
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
