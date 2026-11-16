// Les images jointes par le client (captures d'écran, photos) lues par le premier répondant, comme Claude et ChatGPT
// (16 novembre 2026). Avant, le modèle ne recevait que « Le client a joint 1 photo » : il répondait qu'il ne pouvait pas
// la lire. Les images partent maintenant au modèle, dans le format de chaque fournisseur, et seulement aux modèles qui
// savent les lire, dans l'ordre habituel (fournisseurs gratuits d'abord, OpenAI en dernier).

/** Au plus 4 images par message, chacune d'au plus 5 Mo une fois encodée (Gemini accepte 20 Mo par demande). */
export const IMAGES_MAX = 4
const TAILLE_MAX_BASE64 = 7_000_000
const TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif']

/** Les images reçues du site, nettoyées : type d'image connu, données en base64, nombre et taille bornés. */
export function imagesPropres(images) {
  return (Array.isArray(images) ? images : [])
    .filter((i) => i && TYPES.includes(String(i.type).toLowerCase()) && typeof i.donnees === 'string' && i.donnees.length > 0 && i.donnees.length <= TAILLE_MAX_BASE64 && /^[A-Za-z0-9+/=\s]+$/.test(i.donnees.slice(0, 200)))
    .slice(0, IMAGES_MAX)
    .map((i) => ({ type: String(i.type).toLowerCase(), donnees: i.donnees.replace(/\s+/g, '') }))
}

/**
 * Le modèle sait-il lire une image ? Gemini et Gemma (Google AI Studio), OpenAI, et chez OpenRouter et Workers AI les
 * familles connues pour la vision. Les modèles de FreeLLMAPI (gpt-oss, Qwen texte, GLM-4.5-Flash) ne lisent que du texte.
 */
export function voitLesImages(entree) {
  const modele = String(entree?.modele ?? '').toLowerCase()
  switch (entree?.fournisseur) {
    case 'gemini':
    case 'openai':
      return true
    case 'openrouter':
      return /gemma-[34]|llama-4|vision|-vl\b|qwen.*vl|pixtral|gemini/.test(modele)
    case 'workersai':
      return /llama-4-scout|llama-3\.2-11b-vision|gemma-3|vision|llava/.test(modele)
    default:
      return false
  }
}

const adresseDonnees = (i) => `data:${i.type};base64,${i.donnees}`

/** Le contenu du dernier tour du client, avec ses images, au format du fournisseur. */
export function contenuAvecImages(fournisseur, texte, images) {
  if (!images.length) return null
  if (fournisseur === 'openai') return [{ type: 'input_text', text: texte }, ...images.map((i) => ({ type: 'input_image', image_url: adresseDonnees(i) }))]
  if (fournisseur === 'gemini') return [...images.map((i) => ({ inlineData: { mimeType: i.type, data: i.donnees } })), { text: texte }]
  return [{ type: 'text', text: texte }, ...images.map((i) => ({ type: 'image_url', image_url: { url: adresseDonnees(i) } }))]
}

/** La consigne quand le message porte des images : les regarder vraiment, jamais dire qu'on ne peut pas les lire. */
export function consigneImages(nombre) {
  return `Le client a joint ${nombre} image(s) à ce message (capture d'écran ou photo) : elles sont dans le message, tu les vois. Regarde-les attentivement et appuie ta réponse sur ce qu'elles montrent (texte lisible, erreur affichée, chiffres, interface, produit). Ne dis jamais que tu ne peux pas voir, lire ou ouvrir une image.`
}
