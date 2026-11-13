// L'agent, avec LangGraph : un graphe à deux étapes (le modèle répond ou demande un outil ; l'outil s'exécute, puis
// le modèle reprend), et une mémoire de chaque conversation (MemorySaver, identifiant de conversation).
import { MemorySaver, MessagesAnnotation, StateGraph, START, END } from '@langchain/langgraph'
import { ToolNode, toolsCondition } from '@langchain/langgraph/prebuilt'
import { ChatOpenAI } from '@langchain/openai'
import { HumanMessage, SystemMessage } from '@langchain/core/messages'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { chargerDocuments, passagesUtiles } from './documents.js'
import { consigneSysteme, lireRole } from './consignes.js'
import { outilsDisponibles } from './outils.js'
import { fetchAvecSignatures } from './signatures.js'

const RACINE = path.dirname(path.dirname(fileURLToPath(import.meta.url)))

/** Messages gardés en mémoire pour une conversation (les plus anciens sont oubliés). */
export const MEMOIRE_MAX_MESSAGES = 20

/** Le modèle de l'entreprise (interface compatible OpenAI), avec sa clé gardée côté serveur. */
export function modeleDepuisReglages(reglages) {
  return new ChatOpenAI({
    model: reglages.nomModele,
    apiKey: reglages.cleModele,
    // fetchAvecSignatures : les outils fonctionnent aussi avec Gemini 3 (voir agent/signatures.js).
    configuration: { baseURL: reglages.adresseModele, fetch: fetchAvecSignatures() },
    temperature: 0.3,
    maxRetries: 1,
    timeout: 60000,
  })
}

/**
 * Crée l'agent. `modele` : un modèle de discussion LangChain (le vrai, ou un faux dans les tests).
 * Renvoie { repondre(conversation, message) }.
 */
export function creerAgent({ modele, reglages, outils = outilsDisponibles(reglages), dossierDocuments = path.join(RACINE, 'documents'), fichierRole = path.join(RACINE, 'agent', 'role.md') }) {
  const passages = chargerDocuments(dossierDocuments)
  const role = lireRole(fichierRole)
  const modeleAvecOutils = outils.length ? modele.bindTools(outils) : modele

  async function appelerModele(etat, config) {
    const question = config?.configurable?.question ?? ''
    const systeme = new SystemMessage(consigneSysteme(role, passagesUtiles(passages, question)))
    const recents = etat.messages.slice(-MEMOIRE_MAX_MESSAGES)
    const reponse = await modeleAvecOutils.invoke([systeme, ...recents])
    return { messages: [reponse] }
  }

  const graphe = new StateGraph(MessagesAnnotation).addNode('modele', appelerModele).addEdge(START, 'modele')
  if (outils.length) {
    graphe.addNode('outils', new ToolNode(outils)).addConditionalEdges('modele', toolsCondition, { tools: 'outils', [END]: END }).addEdge('outils', 'modele')
  } else {
    graphe.addEdge('modele', END)
  }
  const application = graphe.compile({ checkpointer: new MemorySaver() })

  return {
    async repondre(conversation, message) {
      const resultat = await application.invoke(
        { messages: [new HumanMessage(message)] },
        { configurable: { thread_id: conversation, question: message }, recursionLimit: 8 },
      )
      // La dernière réponse du modèle (jamais un message du visiteur ni le résultat brut d'un outil).
      const derniere = [...resultat.messages].reverse().find((m) => m._getType?.() === 'ai' || m.getType?.() === 'ai')
      if (!derniere) return ''
      return typeof derniere.content === 'string' ? derniere.content : derniere.content.map((p) => p.text ?? '').join('')
    },
  }
}
