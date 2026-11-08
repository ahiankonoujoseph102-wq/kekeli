// Faux agent OpenCode pour les tests : chaque modèle suit un scénario d'événements.
export function fauxOpencode(scenarios, { reponse = 'Le fichier bonjour.txt est créé.' } = {}) {
  const session = { id: 'ses_test', time: { updated: 1 } }
  const file = []
  let reveil = null
  const appels = { prompts: [], abandons: 0 }
  const pousser = (evenement) => {
    file.push(evenement)
    reveil?.()
  }

  const client = {
    session: {
      list: async () => ({ data: [] }),
      create: async () => ({ data: session }),
      promptAsync: async ({ body }) => {
        appels.prompts.push({ modele: body.model.modelID, fournisseur: body.model.providerID, texte: body.parts[0].text })
        const scenario = scenarios[body.model.modelID] ?? []
        queueMicrotask(() => scenario.forEach(pousser))
        return { data: {} }
      },
      abort: async () => {
        appels.abandons++
        pousser({ type: 'session.status', properties: { sessionID: session.id, status: { type: 'idle' } } })
        return { data: true }
      },
      messages: async () => ({ data: [{ info: { role: 'assistant' }, parts: [{ type: 'text', text: reponse }] }] }),
    },
    event: {
      subscribe: async () => ({
        stream: (async function* () {
          while (true) {
            while (file.length) yield file.shift()
            await new Promise((r) => (reveil = r))
          }
        })(),
      }),
    },
  }
  return { client, appels, pousser, ordinateurs: { obtenir: async () => ({ client, baseUrl: 'http://faux', entetes: {} }) } }
}

const sid = 'ses_test'
export const occupe = { type: 'session.status', properties: { sessionID: sid, status: { type: 'busy' } } }
export const libre = { type: 'session.status', properties: { sessionID: sid, status: { type: 'idle' } } }
export const outil = (tool, input, id) => ({
  type: 'message.part.updated',
  properties: { part: { id, sessionID: sid, type: 'tool', tool, state: { status: 'completed', input } } },
})
export const finRequete = (id, input = 1000, output = 50) => ({
  type: 'message.part.updated',
  properties: { part: { id, sessionID: sid, type: 'step-finish', tokens: { input, output, reasoning: 5, cache: { read: 10 } } } },
})
export const essai = (attempt, message) => ({ type: 'session.status', properties: { sessionID: sid, status: { type: 'retry', attempt, message } } })
