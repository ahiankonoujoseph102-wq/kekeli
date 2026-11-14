// Essai gratuit d'un agent IA dans l'aperçu, avec le modèle gratuit de Kekeli. L'agent du client, dans l'ordinateur
// E2B, parle au relais de l'ordinateur (adresse ADRESSE_ESSAI, sans aucune clé, avec un jeton propre au projet). Le
// Runner prend la demande, vérifie le jeton, demande au site si l'essai gratuit du jour n'est pas fini (10 messages par
// jour et par client au départ, réglable dans l'administration), puis l'envoie au modèle gratuit de Kekeli AVEC SA
// clé, qui ne quitte jamais le Runner. Le client qui a mis sa propre clé dans ses Variables secrètes ne passe plus ici.
import { createHash, createHmac, timingSafeEqual } from 'node:crypto'
import { CATALOGUE, modeleAutorise, valeurCle } from './fournisseurs.js'
import { entetesSignes } from './signature.js'

export const ADRESSE_ESSAI = 'http://127.0.0.1:4200/essai/v1'

/** Phrase montrée au visiteur de l'aperçu quand l'essai gratuit du jour est fini. */
export const ESSAI_TERMINE = "L'essai gratuit du jour est terminé. Ajoutez votre clé dans les Variables secrètes pour continuer."

/** Champs de la demande transmis au modèle (le reste est ignoré). */
const CHAMPS = ['messages', 'tools', 'tool_choice', 'temperature', 'max_tokens', 'max_completion_tokens', 'response_format', 'stop', 'top_p']

/** Jeton donné à l'agent de l'aperçu à la place d'une clé (calculé avec le secret du Runner, jamais une vraie clé). */
export function jetonEssai(secretRunner, projetId) {
  return 'kekeli-essai-' + createHmac('sha256', secretRunner).update(`essai-agent:${projetId}`).digest('hex')
}

/** Le modèle gratuit de Kekeli pour l'essai (Gemini, offre gratuite ; jamais un modèle payant). */
export function modeleEssai(env = process.env) {
  const modele = String(env.KEKELI_MODELE_ESSAI_AGENT || env.KEKELI_MODELE_GEMINI || 'gemini-3.5-flash-lite')
  return modeleAutorise('gemini', modele) ? modele : 'gemini-3.5-flash-lite'
}

const memeJeton = (a, b) => {
  const x = Buffer.from(String(a ?? ''))
  const y = Buffer.from(String(b ?? ''))
  return x.length === y.length && timingSafeEqual(x, y)
}

const reponseJson = (statut, donnees) => ({ statut, type: 'application/json', corps: JSON.stringify(donnees) })
const erreur = (statut, message, code = 'kekeli') => reponseJson(statut, { error: { message, type: code, code } })

/**
 * Demande au site si ce message d'essai est permis (et le compte). Renvoie { permis, restants }.
 * Sans réponse claire du site, l'essai est refusé : jamais de dépense sans contrôle.
 */
export async function autoriserEssai(adresse, projet, { secretRunner, suite = false, appeler = fetch }) {
  if (!/^https?:\/\//.test(String(adresse ?? ''))) return { permis: false }
  const corps = JSON.stringify({ projet, ...(suite ? { suite: true } : {}) })
  try {
    const r = await appeler(adresse, { method: 'POST', headers: { 'content-type': 'application/json', accept: 'application/json', ...entetesSignes(secretRunner, corps) }, body: corps, signal: AbortSignal.timeout(15000) })
    if (!r.ok) return { permis: false }
    const d = await r.json()
    return { permis: d?.permis === true, restants: Number(d?.restants) || 0 }
  } catch {
    return { permis: false }
  }
}

/** Durée pendant laquelle les appels au modèle pour un même message du visiteur comptent pour un seul message. */
export const DUREE_TOUR_MS = 5 * 60 * 1000

/** Appels au modèle permis pour un seul message du visiteur (une équipe d'agents en fait plusieurs). */
export const APPELS_PAR_MESSAGE = 12

/** Le message du visiteur d'une demande au modèle : le dernier message « user » (son empreinte). */
export function cleMessage(messages) {
  const dernier = [...(Array.isArray(messages) ? messages : [])].reverse().find((m) => m?.role === 'user')
  const texte = typeof dernier?.content === 'string' ? dernier.content : (Array.isArray(dernier?.content) ? dernier.content.map((p) => p?.text ?? '').join('') : '')
  return texte ? createHash('sha256').update(texte).digest('hex') : null
}

/**
 * Gemini 3 exige qu'on lui renvoie la « signature » de chaque appel d'outil (extra_content.google.thought_signature),
 * que les bibliothèques d'agents (LangChain) effacent : le relais la garde à la réponse et la remet à la demande
 * suivante. Sans elle, Gemini refuse la suite de la conversation (erreur 400).
 */
const SIGNATURES = new Map()

export function garderSignatures(projet, reponse, signatures = SIGNATURES) {
  for (const choix of reponse?.choices ?? []) {
    for (const appel of choix?.message?.tool_calls ?? []) {
      if (appel?.id && appel.extra_content) signatures.set(`${projet}:${appel.id}`, appel.extra_content)
    }
  }
  while (signatures.size > 20000) signatures.delete(signatures.keys().next().value)
}

export function remettreSignatures(projet, messages, signatures = SIGNATURES) {
  for (const message of Array.isArray(messages) ? messages : []) {
    if (message?.role !== 'assistant' || !Array.isArray(message.tool_calls)) continue
    for (const appel of message.tool_calls) {
      const gardee = appel?.id ? signatures.get(`${projet}:${appel.id}`) : null
      if (gardee && !appel.extra_content) appel.extra_content = gardee
    }
  }
}

/** Les messages en cours de chaque projet : une équipe d'agents (coordinateur, membres) compte pour un seul message. */
const TOURS = new Map()

/**
 * Traite une demande d'essai venue du relais : jeton, quota du jour (site), puis modèle gratuit de Kekeli.
 * Renvoie { statut, type, corps } (la réponse que le relais rend à l'agent).
 */
export async function passerEssai({ projet, chemin, autorisation, corps }, { secretRunner, autoriser, appeler = fetch, env = process.env, tours = TOURS, maintenant = Date.now() }) {
  if (!memeJeton(String(autorisation ?? '').replace(/^Bearer\s+/i, ''), jetonEssai(secretRunner, projet))) return erreur(401, 'Jeton invalide.')
  if (chemin !== 'chat/completions') return erreur(404, 'Adresse refusée.')
  const cle = valeurCle(CATALOGUE.gemini.variableCle, env)
  if (!cle) return erreur(503, "L'essai gratuit n'est pas disponible pour le moment. Ajoutez votre clé dans les Variables secrètes pour continuer.")
  let demande
  try {
    demande = JSON.parse(corps)
  } catch {
    return erreur(422, 'Demande illisible.')
  }
  // Les appels suivants pour le même message du visiteur (équipe d'agents, outils) ne comptent pas un message de plus.
  const message = cleMessage(demande?.messages)
  const tour = tours.get(projet)
  const suite = Boolean(message && tour && tour.cle === message && maintenant - tour.a < DUREE_TOUR_MS && tour.appels < APPELS_PAR_MESSAGE)
  const { permis } = await autoriser(projet, { suite })
  if (!permis) return erreur(429, ESSAI_TERMINE, 'essai_termine')
  if (suite) tour.appels++
  else if (message) tours.set(projet, { cle: message, a: maintenant, appels: 1 })
  if (tours.size > 5000) tours.delete(tours.keys().next().value)
  const propre = Object.fromEntries(CHAMPS.filter((champ) => demande?.[champ] !== undefined).map((champ) => [champ, demande[champ]]))
  if (!Array.isArray(propre.messages) || !propre.messages.length) return erreur(422, 'Demande sans message.')
  // Une réponse d'essai reste courte (coût et quota de l'offre gratuite).
  propre.max_tokens = Math.min(Number(propre.max_tokens ?? propre.max_completion_tokens) || 800, 800)
  delete propre.max_completion_tokens
  remettreSignatures(projet, propre.messages)
  const r = await appeler(`https://${CATALOGUE.gemini.hote}/v1beta/openai/chat/completions`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', authorization: `Bearer ${cle}` },
    body: JSON.stringify({ ...propre, model: modeleEssai(env), stream: false }),
    signal: AbortSignal.timeout(90000),
  }).catch(() => null)
  if (!r) return erreur(502, "Le modèle d'essai ne répond pas. Réessayez dans un instant.")
  if (r.status === 429) return erreur(429, "Le modèle d'essai reçoit beaucoup de messages. Réessayez dans une minute.")
  const texte = await r.text()
  if (r.ok) {
    try {
      garderSignatures(projet, JSON.parse(texte))
    } catch {
      // Une réponse illisible est rendue telle quelle.
    }
  }
  return { statut: r.status, type: r.headers.get('content-type') ?? 'application/json', corps: texte }
}
