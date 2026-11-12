// Suivi du relais OpenAI d'un ordinateur virtuel par le Runner (voir relais/relais-openai.mjs) : le Runner prend les
// demandes d'OpenCode en attente, les envoie à OpenAI avec SA clé (passerelle.js : jeton du projet et modèle vérifiés),
// puis dépose la réponse dans le relais. La clé OpenAI ne quitte jamais le Runner.
import { createHmac } from 'node:crypto'
import { passerOpenAI } from './passerelle.js'

export const PORT_RELAIS = 4200
export const FICHIER_RELAIS = '/home/user/.kekeli/relais-openai.mjs'

/** Jeton avec lequel le Runner se présente au relais d'un projet. */
export function jetonRelais(secretRunner, projetId) {
  return createHmac('sha256', secretRunner).update(`relais-openai:${projetId}`).digest('hex')
}

/**
 * Suit le relais tant que `continuer()` est vrai. `url` : adresse publique du port du relais. Renvoie le nombre de
 * demandes transmises.
 */
export async function suivreRelais({ url, projet, secretRunner, continuer, appeler = fetch, env = process.env, journal = console }) {
  const entetes = { 'x-kekeli-relais': jetonRelais(secretRunner, projet) }
  let transmises = 0
  let echecs = 0
  while (continuer()) {
    let demande = null
    try {
      const r = await appeler(`${url}/relais/attente`, { headers: entetes, signal: AbortSignal.timeout(40000) })
      if (r.status === 200) demande = await r.json()
      else if (r.status !== 204) throw new Error(`relais : ${r.status}`)
      echecs = 0
    } catch (erreur) {
      if (++echecs > 3) journal.warn?.(`Relais OpenAI du projet ${projet} : ${erreur.message}`)
      await new Promise((r) => setTimeout(r, Math.min(10000, 1000 * echecs)))
      continue
    }
    if (!demande) continue
    // Chaque demande est traitée à part : l'agent peut en envoyer plusieurs à la suite.
    transmises++
    ;(async () => {
      let reponse
      try {
        const r = await passerOpenAI({ projet, chemin: demande.chemin, autorisation: demande.autorisation, corps: demande.corps }, { secretRunner, appeler, env })
        reponse = r instanceof Response ? { statut: r.status, type: r.headers.get('content-type') ?? 'application/json', corps: await r.text() } : { statut: r.statut, type: 'application/json', corps: JSON.stringify({ error: { message: r.erreur } }) }
      } catch (erreur) {
        reponse = { statut: 502, type: 'application/json', corps: JSON.stringify({ error: { message: `OpenAI injoignable : ${erreur.message}` } }) }
      }
      await appeler(`${url}/relais/reponse/${demande.id}`, { method: 'POST', headers: { ...entetes, 'content-type': 'application/json' }, body: JSON.stringify(reponse) }).catch(() => {})
    })()
  }
  return transmises
}
