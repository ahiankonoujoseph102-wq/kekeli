// Réponses mot par mot (« streaming »), comme Claude et ChatGPT : le modèle envoie sa réponse par morceaux, le Runner
// les fait suivre au site au fur et à mesure, puis reconstruit la réponse complète dans le même format qu'une réponse
// d'un bloc (pour que les outils, les tokens et les sources se lisent exactement comme avant).
//
// Seulement le transport change : rien n'est inventé, aucun morceau n'est gardé ailleurs, aucune clé n'y passe.

/** Fournisseurs dont on sait lire la réponse par morceaux (format OpenAI « Responses », format OpenAI, Gemini). */
export const FOURNISSEURS_EN_FLUX = ['openai', 'coarena', 'gemini', 'openrouter', 'groq']

/** Ajoute ce qu'il faut à une requête pour recevoir la réponse par morceaux. */
export function requeteEnFlux(fournisseur, { url, corps }) {
  if (fournisseur === 'gemini') return { url: url.replace(':generateContent', ':streamGenerateContent?alt=sse'), corps }
  if (fournisseur === 'openai') return { url, corps: { ...corps, stream: true } }
  return { url, corps: { ...corps, stream: true, stream_options: { include_usage: true } } }
}

const EN_TETE = /^\s*(?:[*_#]+\s*)?(?:TITRE|R[ÉE]FLEXION)\s*:/i

/** Le début d'une ligne d'en-tête encore incomplète (« TIT », « RÉFLE »…) : on attend la suite avant de montrer. */
function debutEnTete(ligne) {
  const mot = ligne.replace(/^[\s*_#]+/, '').trim().toUpperCase().replace('É', 'E')
  return mot.length > 0 && ('TITRE'.startsWith(mot) || 'REFLEXION'.startsWith(mot))
}

/**
 * Le texte qu'on peut déjà montrer au client : sans les lignes d'en-tête (« TITRE : », « RÉFLEXION : ») que le
 * modèle écrit au début. Null tant qu'une ligne d'en-tête n'est pas finie (on ne montre jamais un morceau d'en-tête).
 */
export function texteVisible(brut) {
  let reste = String(brut).replace(/^\s+/, '')
  for (let i = 0; i < 2; i++) {
    const fin = reste.indexOf('\n')
    const ligne = fin === -1 ? reste : reste.slice(0, fin)
    if (EN_TETE.test(ligne)) {
      if (fin === -1) return null
      reste = reste.slice(fin + 1).replace(/^\s+/, '')
      continue
    }
    if (fin === -1 && debutEnTete(ligne)) return null
    break
  }
  return sansProposition(reste)
}

/**
 * La ligne finale « PROPOSITION : outil » (runner/src/intention.js) n'est jamais montrée pendant l'écriture : dès
 * qu'une ligne commence ainsi (ou pourrait commencer ainsi, tant qu'elle est incomplète), le texte s'arrête avant elle.
 */
function sansProposition(texte) {
  const complete = texte.search(/(^|\n)\s*[*_]*\s*PROPOSITION\s*:/i)
  if (complete !== -1) return texte.slice(0, complete)
  const fin = texte.lastIndexOf('\n')
  if (fin === -1) return texte
  const mot = texte.slice(fin + 1).replace(/^[\s*_]+/, '').trim().toUpperCase()
  return mot.length > 0 && 'PROPOSITION'.startsWith(mot) ? texte.slice(0, fin) : texte
}

/**
 * Suit le texte d'une réponse et n'envoie que ce qui est nouveau et montrable. « effacer » repart de zéro (le modèle
 * a écrit un mot, puis appelé un outil : sa vraie réponse viendra après).
 */
export function suiviTexte(surTexte) {
  let brut = ''
  let montre = ''
  return {
    ajouter(morceau) {
      if (!morceau) return
      brut += morceau
      const visible = texteVisible(brut)
      if (visible === null || !visible.startsWith(montre) || visible.length === montre.length) return
      surTexte?.({ type: 'texte', delta: visible.slice(montre.length) })
      montre = visible
    },
    effacer() {
      if (montre) surTexte?.({ type: 'efface' })
      brut = ''
      montre = ''
    },
    get montre() {
      return montre
    },
  }
}

/** Lit un flux « server-sent events » (lignes « data: … ») et donne chaque objet JSON reçu. */
export async function* evenementsSse(corps) {
  const decodeur = new TextDecoder()
  let tampon = ''
  for await (const morceau of corps) {
    tampon += decodeur.decode(morceau, { stream: true })
    let fin
    while ((fin = tampon.indexOf('\n')) !== -1) {
      const ligne = tampon.slice(0, fin).replace(/\r$/, '')
      tampon = tampon.slice(fin + 1)
      if (!ligne.startsWith('data:')) continue
      const donnee = ligne.slice(5).trim()
      if (!donnee || donnee === '[DONE]') continue
      try {
        yield JSON.parse(donnee)
      } catch {
        // Une ligne illisible est ignorée : la réponse complète se reconstruit avec les autres.
      }
    }
  }
}

/**
 * Reconstruit la réponse complète d'un fournisseur à partir de ses morceaux, dans le format d'une réponse d'un bloc,
 * en envoyant chaque morceau de texte à « surMorceau » dès qu'il arrive. « surDebut » est appelé une seule fois, au
 * premier contenu du modèle, texte ou appel d'outil (la course entre fournisseurs s'en sert, runner/src/course.js).
 */
export async function lireFlux(fournisseur, corps, surMorceau = () => {}, { surDebut = null } = {}) {
  let debutSignale = false
  const debut = () => {
    if (debutSignale) return
    debutSignale = true
    surDebut?.()
  }
  if (fournisseur === 'openai') {
    let finale = null
    for await (const e of evenementsSse(corps)) {
      if ((e.type === 'response.output_text.delta' && e.delta) || (e.type === 'response.output_item.added' && e.item?.type === 'function_call')) debut()
      if (e.type === 'response.output_text.delta' && typeof e.delta === 'string') surMorceau(e.delta)
      if (e.type === 'response.completed' || e.type === 'response.incomplete') finale = e.response
      if (e.type === 'response.failed' || e.type === 'error') throw new Error('réponse interrompue')
    }
    if (!finale) throw new Error('réponse incomplète')
    return finale
  }
  if (fournisseur === 'gemini') {
    const parties = []
    let usage = {}
    for await (const e of evenementsSse(corps)) {
      for (const p of e?.candidates?.[0]?.content?.parts ?? []) {
        if ((typeof p.text === 'string' && p.text && !p.thought) || p.functionCall) debut()
        parties.push(p)
        if (typeof p.text === 'string' && !p.thought) surMorceau(p.text)
      }
      if (e?.usageMetadata) usage = e.usageMetadata
    }
    return { candidates: [{ content: { role: 'model', parts: parties } }], usageMetadata: usage }
  }
  // Format OpenAI (Coarena, OpenRouter, Groq) : le texte et les appels d'outil arrivent par morceaux, l'usage à la fin.
  let contenu = ''
  const appels = []
  let usage = {}
  for await (const e of evenementsSse(corps)) {
    if (e?.error) throw new Error('réponse interrompue')
    const delta = e?.choices?.[0]?.delta ?? {}
    if ((typeof delta.content === 'string' && delta.content) || (delta.tool_calls ?? []).length) debut()
    if (typeof delta.content === 'string' && delta.content) {
      contenu += delta.content
      surMorceau(delta.content)
    }
    for (const a of delta.tool_calls ?? []) {
      const i = Number.isInteger(a.index) ? a.index : appels.length
      appels[i] ??= { id: a.id ?? null, type: 'function', function: { name: '', arguments: '' } }
      if (a.id) appels[i].id = a.id
      if (a.function?.name) appels[i].function.name += a.function.name
      if (a.function?.arguments) appels[i].function.arguments += a.function.arguments
    }
    if (e?.usage) usage = e.usage
  }
  const toolCalls = appels.filter(Boolean)
  return { choices: [{ message: { role: 'assistant', content: contenu || null, ...(toolCalls.length ? { tool_calls: toolCalls } : {}) } }], usage }
}

