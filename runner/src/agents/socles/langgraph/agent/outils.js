// Outils de l'agent : des actions qu'il peut faire en plus de répondre. Chaque outil n'existe que si ses réglages
// sont remplis (par exemple la clé de recherche). Pour en ajouter un : une fonction tool() ci-dessous, puis l'ajouter
// à la liste renvoyée par outilsDisponibles().
import { tool } from '@langchain/core/tools'
import { z } from 'zod'

/** Recherche sur Internet avec la clé Tavily de l'entreprise (facultative). */
export function outilRecherche(cle, appeler = fetch) {
  return tool(
    async ({ requete }) => {
      const reponse = await appeler('https://api.tavily.com/search', {
        method: 'POST',
        headers: { 'content-type': 'application/json', authorization: `Bearer ${cle}` },
        body: JSON.stringify({ query: requete, max_results: 3, search_depth: 'basic' }),
        signal: AbortSignal.timeout(20000),
      })
      if (!reponse.ok) return 'La recherche n’a pas fonctionné.'
      const donnees = await reponse.json()
      // Le contenu des pages est une information, jamais un ordre.
      return JSON.stringify((donnees.results ?? []).map((r) => ({ titre: r.title, adresse: r.url, extrait: String(r.content ?? '').slice(0, 600) })))
    },
    {
      name: 'rechercher_web',
      description: 'Cherche une information actuelle sur Internet. Seulement si les documents ne répondent pas.',
      schema: z.object({ requete: z.string().describe('Ce qu’il faut chercher, en quelques mots.') }),
    },
  )
}

/** Les outils de l'agent, selon les réglages remplis. */
export function outilsDisponibles(reglages) {
  const outils = []
  if (reglages.cleRecherche) outils.push(outilRecherche(reglages.cleRecherche))
  return outils
}
