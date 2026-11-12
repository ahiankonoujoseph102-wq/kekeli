// Dictée vocale quand le navigateur du client ne sait pas écrire la voix lui-même (par exemple Firefox) : le site
// Kekeli envoie le court enregistrement, et le Runner le fait transcrire par OpenAI (modèle « gpt-transcribe », prix
// officiel lu le 12 novembre 2026 : 0,0045 $ par minute). La clé reste dans le Runner ; l'enregistrement n'est jamais
// gardé. Le site compte le coût dans l'énergie du client et dans le plafond OpenAI du jour.
import { OPENAI } from './repondant.js'
import { valeurCle } from './fournisseurs.js'

/** Modèles de transcription permis (jamais un modèle choisi au hasard par un réglage). */
export const MODELES_TRANSCRIPTION = ['gpt-transcribe', 'gpt-4o-mini-transcribe']

/** Enregistrement accepté : 2 minutes environ, au plus 2 Mo. */
export const AUDIO_MAX_OCTETS = 2 * 1024 * 1024

const TYPES = { 'audio/webm': 'webm', 'audio/ogg': 'ogg', 'audio/mp4': 'mp4', 'audio/mpeg': 'mp3', 'audio/wav': 'wav', 'audio/x-wav': 'wav', 'audio/aac': 'aac' }

/**
 * Transcrit un enregistrement (texte en base 64). Renvoie { ok, texte, secondes } ou { ok: false, erreur }.
 */
export async function transcrire({ audio, type, langue = 'fr', modele = 'gpt-transcribe' }, { appeler = fetch, env = process.env } = {}) {
  const cle = valeurCle(OPENAI.variableCle, env)
  if (!cle) return { ok: false, erreur: 'cle_absente' }
  if (!MODELES_TRANSCRIPTION.includes(modele)) return { ok: false, erreur: 'modele_refuse' }
  const genre = String(type ?? '').split(';')[0].trim().toLowerCase()
  if (!TYPES[genre]) return { ok: false, erreur: 'format_refuse' }
  const octets = Buffer.from(String(audio ?? ''), 'base64')
  if (!octets.length || octets.length > AUDIO_MAX_OCTETS) return { ok: false, erreur: 'taille_refusee' }

  const formulaire = new FormData()
  formulaire.append('file', new Blob([octets], { type: genre }), `dictee.${TYPES[genre]}`)
  formulaire.append('model', modele)
  formulaire.append('language', /^[a-z]{2}$/.test(langue) ? langue : 'fr')
  formulaire.append('response_format', 'json')

  let reponse
  try {
    reponse = await appeler(`https://${OPENAI.hote}/v1/audio/transcriptions`, { method: 'POST', headers: OPENAI.entete(cle), body: formulaire, signal: AbortSignal.timeout(60000) })
  } catch {
    return { ok: false, erreur: 'reseau' }
  }
  if (!reponse.ok) return { ok: false, erreur: reponse.status === 429 ? 'limite' : 'fournisseur' }
  const donnees = await reponse.json().catch(() => ({}))
  const texte = String(donnees.text ?? '').replace(/\s+/g, ' ').trim().slice(0, 4000)
  const secondes = Number(donnees.usage?.seconds ?? donnees.duration ?? 0)
  return { ok: true, texte, secondes: Number.isFinite(secondes) && secondes > 0 ? Math.ceil(secondes) : null, modele }
}
