// Gemini 3 (interface compatible OpenAI) exige qu'on lui renvoie la « signature » de chaque appel d'outil
// (extra_content.google.thought_signature), que LangChain efface. Ce petit relais de fetch la garde à chaque réponse
// et la remet dans la demande suivante. Sans effet pour les autres fournisseurs.

const MAXIMUM = 5000

export function fetchAvecSignatures(appeler = fetch) {
  const signatures = new Map()
  return async (adresse, options = {}) => {
    let corps = options.body
    if (typeof corps === 'string' && corps.includes('"tool_calls"')) {
      try {
        const demande = JSON.parse(corps)
        let change = false
        for (const message of demande.messages ?? []) {
          for (const appel of message?.role === 'assistant' ? message.tool_calls ?? [] : []) {
            if (appel?.id && !appel.extra_content && signatures.has(appel.id)) {
              appel.extra_content = signatures.get(appel.id)
              change = true
            }
          }
        }
        if (change) corps = JSON.stringify(demande)
      } catch {
        // Une demande illisible part telle quelle.
      }
    }
    const reponse = await appeler(adresse, { ...options, body: corps })
    if (reponse.ok && /json/.test(reponse.headers.get('content-type') ?? '')) {
      // Lue avant de rendre la réponse : la signature est gardée avant la demande suivante.
      const d = await reponse.clone().json().catch(() => null)
      for (const choix of d?.choices ?? []) for (const appel of choix?.message?.tool_calls ?? []) if (appel?.id && appel.extra_content) signatures.set(appel.id, appel.extra_content)
      while (signatures.size > MAXIMUM) signatures.delete(signatures.keys().next().value)
    }
    return reponse
  }
}
