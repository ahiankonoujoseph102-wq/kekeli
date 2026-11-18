// Bien comprendre la demande, comme Claude et ChatGPT (chantier « comprendre, répondre et réagir », 15 novembre 2026).
// Une QUESTION (« est-ce possible ? », « comment ? », « pourquoi ? », « combien ? », « quelle différence ? ») reçoit une
// réponse, jamais une fiche de création ni un plan d'agent : pour un tel message, les outils de création ne sont même
// pas proposés au modèle (décision sans modèle, donc gratuite, et la requête est plus légère). Une création ne commence
// que si le client la demande clairement (« crée-moi », « je veux un… », « peux-tu me faire… ») ou confirme une
// proposition. À la fin de sa réponse, Kekeli peut proposer de créer la chose dont il a parlé, avec un bouton.

/** Les mots qui montrent une question de connaissance, de conseil ou d'explication. */
const QUESTION = /^(?:et\s+)?(?:est[- ]ce\s+(?:que\s+)?(?:c'est\s+|ce\s+serait\s+)?(?:possible|rentable|utile|difficile|facile|vrai|normal|légal|legal|cher)|c'est\s+(?:possible|quoi|rentable|difficile|facile|vrai|cher)|ce\s+serait\s+possible|est[- ](?:il|elle)\s+(?:possible|rentable|utile|vrai)|peut[- ]on|on\s+peut|est[- ]ce\s+qu'on\s+peut|est[- ]ce\s+qu'(?:un|une|il|elle|on)|comment|pourquoi|combien|quelle?s?\s|quels?\s|qu'est[- ]ce|que\s+(?:faut|dois|devrais|pensez|penses)|à\s+quoi|a\s+quoi|explique|expliquez|dis[- ]moi|dites[- ]moi|aide[- ]moi\s+à\s+comprendre|où\s|ou\s+trouver|quand\s|qui\s)/i

/** Les mots d'une vraie demande faite à Kekeli : il crée quelque chose POUR le client. */
const DEMANDE = /\b(?:je\s+(?:veux|voudrais|souhaite|souhaiterais|désire|cherche\s+à\s+avoir)|j'(?:aimerais|ai\s+besoin\s+d)|il\s+me\s+(?:faut|faudrait)|(?:peux|pourrais|pouvez|pourriez)[- ](?:tu|vous)\s+(?:me|m'|nous|lui)|(?:tu|vous)\s+(?:peux|pouvez|pourrais|pourriez)\s+(?:me|m'|nous)|est[- ]ce\s+que\s+(?:tu|vous)\s+(?:peux|pouvez|pourrais|pourriez)\s+(?:me|m'|nous)|(?:crée|crées|créez|créer|fais|faites|construis|construisez|génère|générez|réalise|réalisez|prépare|préparez|écris|écrivez|rédige|rédigez|dessine|dessinez|monte|montez|conçois|concevez)[- ](?:moi|nous|lui|leur)\b|^(?:crée|créez|fais|faites|construis|construisez|génère|générez|réalise|réalisez|prépare|préparez|écris|écrivez|rédige|rédigez|dessine|dessinez|monte|montez|conçois|concevez)\b)/i

const normaliser = (texte) => String(texte ?? '').trim().replace(/[’]/g, "'").replace(/\s+/g, ' ')

/**
 * Le message est une question (de connaissance, de conseil, d'explication) et non une demande de création faite à
 * Kekeli. Chaque phrase compte : « C'est possible de créer un agent IA ? Et comment gagner de l'argent avec l'IA » est
 * une question ; « Peux-tu me créer un site pour mon restaurant ? » est une demande.
 */
export function estQuestion(message) {
  const texte = normaliser(message)
  if (!texte || DEMANDE.test(texte)) return false
  const phrases = texte.split(/(?<=[.!?…])\s+/).map((p) => p.trim()).filter(Boolean)
  return phrases.length > 0 && phrases.some((p) => QUESTION.test(p) || /\?\s*$/.test(p)) && phrases.every((p) => !DEMANDE.test(p))
}

/** Les outils que Kekeli peut proposer de créer à la fin d'une réponse (avec un bouton). */
export const PROPOSABLES = ['creer_site', 'creer_application', 'creer_agent', 'creer_automatisation', 'creer_presentation', 'creer_document', 'creer_image', 'creer_video', 'creer_animation']

/** La consigne ajoutée quand le message est une question : répondre, puis, si c'est utile, proposer en une phrase. */
export const CONSIGNE_QUESTION = `Ce message est une QUESTION : réponds-y vraiment, de façon complète et concrète. N'écris jamais de plan, de fiche ni de liste « Rôle, Ce qu'il sait, Ce qu'il peut faire » à la place de la réponse, et ne crée rien.
Depuis cette discussion, Kekeli sait créer des sites web, des applications, des agents IA, des automatisations, des présentations, des documents (Word et PDF), des images et des affiches, des vidéos et des animations interactives (Kekeli Motion) : ne dis jamais que tu ne peux pas en créer ici. Si le client en veut un, réponds puis propose-le comme ci-dessous.
Proposition finale (facultative, comme Claude) : seulement si le client semble avoir un projet concret que Kekeli sait créer (un site, une application, un agent IA, une automatisation, une présentation, un document, une image ou une vidéo), termine ta réponse par UNE phrase courte qui le propose (par exemple « Voulez-vous que je crée cet agent pour votre boutique ? »), puis, sur une dernière ligne à part, « PROPOSITION : » suivi de l'outil exact (« creer_site », « creer_application », « creer_agent », « creer_automatisation », « creer_presentation », « creer_document », « creer_image », « creer_video » ou « creer_animation »). Kekeli enlève cette ligne et montre un bouton. Jamais de proposition pour une question de pure connaissance, une salutation ou un calcul.`

/** La ligne « PROPOSITION : outil » à la fin d'une réponse : enlevée du texte, l'outil est gardé s'il est permis. */
export function propositionEtTexte(texte) {
  const brut = String(texte ?? '')
  // La dernière mention « PROPOSITION : … », sur sa propre ligne ou collée à la fin d'une phrase : tout ce qui la suit
  // est enlevé (un outil inconnu n'est jamais gardé).
  const m = [...brut.matchAll(/[*_]*\s*PROPOSITION\s*:\s*[*_]*\s*[«"]?\s*([A-Za-z_]*)[^\n]*$/gm)].at(-1)
  if (!m) return { texte: brut.trimEnd(), outil: null }
  const outil = PROPOSABLES.includes(m[1].toLowerCase()) ? m[1].toLowerCase() : null
  return { texte: brut.slice(0, m.index).trimEnd(), outil }
}
