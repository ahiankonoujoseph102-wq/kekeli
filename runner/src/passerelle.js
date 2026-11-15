// Passerelle OpenAI du Runner, pour l'agent qui construit les applications : la clé OpenAI n'entre JAMAIS dans
// l'ordinateur virtuel. OpenCode, dans l'ordinateur, reçoit seulement l'adresse de cette passerelle et un jeton propre
// au projet (calculé avec le secret du Runner) ; le Runner vérifie le jeton et le modèle, ajoute la vraie clé, puis
// transmet la demande à OpenAI et renvoie la réponse telle quelle (flux compris).
import { createHmac, timingSafeEqual } from 'node:crypto'
import { MODELES_CONSTRUCTION_OPENAI, valeurCle } from './fournisseurs.js'
import { OPENAI } from './repondant.js'
import { MODELES_COARENA, baseCoarena, classerErreur, registrePour } from './coarena.js'
import { freellmapi, modeleFreellmapiPermis } from './freellmapi.js'

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
  if (!memeJeton(String(autorisation ?? '').replace(/^Bearer\s+/i, ''), jetonPasserelle(secretRunner, projet))) return { statut: 401, erreur: 'jeton invalide' }
  if (!CHEMINS.has(chemin)) return { statut: 404, erreur: 'adresse refusée' }
  let demande
  try {
    demande = JSON.parse(corps)
  } catch {
    return { statut: 422, erreur: 'corps illisible' }
  }
  // Coarena : modèle « coarena-… » de la liste fermée, clé choisie par le registre (jamais dans l'ordinateur).
  if (typeof demande?.model === 'string' && demande.model.startsWith('coarena-')) return passerCoarena(demande, chemin, { appeler, env })
  // FreeLLMAPI : modèle « freellmapi-… » de la liste fermée, vers le processus voisin du Runner (clé locale ajoutée ici).
  if (typeof demande?.model === 'string' && demande.model.startsWith('freellmapi-')) return passerFreellmapi(demande, chemin, { appeler })
  if (!cle) return { statut: 503, erreur: 'openai_absent' }
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

/**
 * Demande de l'agent pour un modèle de FreeLLMAPI. Seulement « chat/completions » (FreeLLMAPI ne sert pas l'interface
 * « Responses » d'OpenAI) ; « tout est épuisé » revient à l'agent comme une limite atteinte (429), et la liste des
 * fournisseurs passe au suivant.
 */
async function passerFreellmapi(demande, chemin, { appeler }) {
  const modele = demande.model.slice('freellmapi-'.length)
  if (!modeleFreellmapiPermis(modele)) return { statut: 403, erreur: 'modèle refusé' }
  if (chemin !== 'chat/completions') return { statut: 404, erreur: 'adresse refusée' }
  const voisin = freellmapi()
  if (!voisin.pret()) return { statut: 503, erreur: 'freellmapi_absent' }
  try {
    return await appeler(`${voisin.adresse}/v1/chat/completions`, {
      method: 'POST',
      headers: { 'content-type': 'application/json', Authorization: `Bearer ${voisin.cle()}` },
      body: JSON.stringify({ ...demande, model: modele }),
      signal: AbortSignal.timeout(10 * 60 * 1000),
    })
  } catch {
    return { statut: 502, erreur: 'freellmapi : injoignable' }
  }
}

/**
 * Demande de l'agent pour un modèle de Coarena. Un quota épuisé ou un modèle indisponible sont rendus à l'agent comme
 * une limite atteinte (429) : la liste des fournisseurs passe au suivant, comme pour les autres.
 */
async function passerCoarena(demande, chemin, { appeler, env }) {
  const modele = demande.model.slice('coarena-'.length)
  if (!MODELES_COARENA.includes(modele)) return { statut: 403, erreur: 'modèle refusé' }
  const registre = registrePour(env)
  // Quota du compte épuisé, clé hors service ou trop sollicitée : la demande repart avec la clé suivante (un autre
  // compte si le basculement est activé) ; une panne passagère a droit à un seul nouvel essai.
  let nouvelEssaiFait = false
  for (let essai = 0; essai < 12; essai++) {
    const nom = registre.cleActive()
    if (!nom) return { statut: 429, erreur: 'coarena indisponible (quota du jour épuisé)' }
    const debut = Date.now()
    let r
    try {
      r = await appeler(`${baseCoarena(env)}/${chemin}`, {
        method: 'POST',
        headers: { 'content-type': 'application/json', Authorization: `Bearer ${registre.valeur(nom)}` },
        body: JSON.stringify({ ...demande, model: modele }),
        signal: AbortSignal.timeout(10 * 60 * 1000),
      })
    } catch (erreur) {
      registre.signaler(nom, classerErreur(0, erreur.message))
      if (!nouvelEssaiFait) {
        nouvelEssaiFait = true
        continue
      }
      return { statut: 502, erreur: 'coarena : fournisseur' }
    }
    if (r.ok) {
      registre.compter(nom, { ok: true, dureeMs: Date.now() - debut })
      return r
    }
    const texte = await r.text()
    const classe = classerErreur(r.status, texte)
    registre.signaler(nom, classe)
    if ((classe === 'quota' || classe === 'cle' || classe === 'debit') && registre.cleActive() && registre.cleActive() !== nom) continue
    if (classe === 'fournisseur' && !nouvelEssaiFait) {
      nouvelEssaiFait = true
      await new Promise((resolve) => setTimeout(resolve, 1500))
      continue
    }
    // Pour l'agent, un modèle saturé ou indisponible, ou plus aucun compte, est une limite atteinte : le modèle suivant
    // de la liste prend le relais.
    return { statut: ['quota', 'modele', 'debit', 'sature'].includes(classe) ? 429 : r.status, erreur: `coarena : ${classe}` }
  }
  return { statut: 429, erreur: 'coarena : trop d\'essais' }
}
