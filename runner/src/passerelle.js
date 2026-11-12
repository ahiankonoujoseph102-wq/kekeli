// Passerelle OpenAI du Runner, pour l'agent qui construit les applications : la clé OpenAI n'entre JAMAIS dans
// l'ordinateur virtuel. OpenCode, dans l'ordinateur, reçoit seulement l'adresse de cette passerelle et un jeton propre
// au projet (calculé avec le secret du Runner) ; le Runner vérifie le jeton et le modèle, ajoute la vraie clé, puis
// transmet la demande à OpenAI et renvoie la réponse telle quelle (flux compris).
import { createHmac, timingSafeEqual } from 'node:crypto'
import { MODELES_CONSTRUCTION_OPENAI, valeurCle } from './fournisseurs.js'
import { OPENAI } from './repondant.js'

export { MODELES_CONSTRUCTION_OPENAI }

/** Seules ces adresses d'OpenAI passent (réponses du modèle) ; tout le reste est refusé. */
const CHEMINS = new Set(['responses', 'chat/completions'])

/** Taille maximale d'une demande de l'agent (conversation et fichiers lus). */
export const TAILLE_MAXIMALE_PASSERELLE = 8 * 1024 * 1024

const PROJET = /^[A-Za-z0-9_-]{1,64}$/

/** Jeton de la passerelle pour un projet (donné à OpenCode à la place de la clé). */
export function jetonPasserelle(secretRunner, projetId) {
  return 'kekeli-' + createHmac('sha256', secretRunner).update(`passerelle-openai:${projetId}`).digest('hex')
}

/** Adresse de base donnée à OpenCode, ou null si le Runner n'a pas d'adresse publique (pas d'OpenAI alors). */
export function adressePasserelle(projetId, env = process.env) {
  const publique = String(env.KEKELI_RUNNER_ADRESSE_PUBLIQUE ?? '').trim().replace(/\/+$/, '')
  return /^https?:\/\//.test(publique) && PROJET.test(projetId) ? `${publique}/passerelle/${projetId}/openai/v1` : null
}

/** Le chemin demandé est-il une demande à la passerelle ? Renvoie { projet, chemin } ou null. */
export function lireCheminPasserelle(pathname) {
  const m = /^\/passerelle\/([A-Za-z0-9_-]{1,64})\/openai\/v1\/(.+)$/.exec(pathname)
  return m ? { projet: m[1], chemin: m[2] } : null
}

function memeJeton(a, b) {
  const x = Buffer.from(String(a ?? ''))
  const y = Buffer.from(String(b ?? ''))
  return x.length === y.length && timingSafeEqual(x, y)
}

/**
 * Traite une demande à la passerelle. `corps` est le texte déjà lu. Renvoie une Response (fetch) à transmettre, ou
 * { statut, erreur } quand la demande est refusée.
 */
export async function passerOpenAI({ projet, chemin, autorisation, corps }, { secretRunner, appeler = fetch, env = process.env }) {
  const cle = valeurCle(OPENAI.variableCle, env)
  if (!cle) return { statut: 503, erreur: 'openai_absent' }
  if (!memeJeton(String(autorisation ?? '').replace(/^Bearer\s+/i, ''), jetonPasserelle(secretRunner, projet))) return { statut: 401, erreur: 'jeton invalide' }
  if (!CHEMINS.has(chemin)) return { statut: 404, erreur: 'adresse refusée' }
  let demande
  try {
    demande = JSON.parse(corps)
  } catch {
    return { statut: 422, erreur: 'corps illisible' }
  }
  if (!MODELES_CONSTRUCTION_OPENAI.includes(demande?.model)) return { statut: 403, erreur: 'modèle refusé' }
  // Rien n'est gardé chez OpenAI (sauf pour les réponses enchaînées, que l'agent n'utilise pas).
  if (chemin === 'responses' && demande.store === undefined) demande.store = false
  return appeler(`https://${OPENAI.hote}/v1/${chemin}`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', ...OPENAI.entete(cle) },
    body: JSON.stringify(demande),
    signal: AbortSignal.timeout(10 * 60 * 1000),
  })
}
