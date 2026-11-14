// Jamais de création sans confirmation quand la demande est floue, répétée ou brouillée (chantier « vitesse »,
// partie 5, 15 novembre 2026). Un message dicté qui se répète (« crée un site crée un site pour mon… »), un message
// illisible ou une demande trop courte (« fais un site ») ne lance plus le travail : Kekeli pose une seule question
// courte, avec le bouton « Oui, créer le site » (ou l'image, le document, la présentation, la vidéo). Une demande
// claire et détaillée part tout de suite, comme avant. Tout se décide sans requête au modèle, donc gratuitement.
//
// Seulement pour les outils qui lancent le travail tout de suite : les applications, les agents et les automatisations
// passent déjà par un plan et « Construire ce plan ». Une demande qui suit une question de Kekeli (le client répond
// « oui ») n'est jamais retenue : c'est déjà une confirmation.

export const OUTILS_CONFIRMES = ['creer_site', 'creer_image', 'creer_document', 'creer_presentation', 'creer_video']

const QUOI = {
  creer_site: { question: 'Voulez-vous que je crée le site maintenant ?', precision: 'l’activité, le nom et ce que le site doit montrer' },
  creer_image: { question: 'Voulez-vous que je crée l’image maintenant ?', precision: 'ce que l’image doit montrer et où elle sera utilisée' },
  creer_document: { question: 'Voulez-vous que je crée le document maintenant ?', precision: 'le type de document et ce qu’il doit contenir' },
  creer_presentation: { question: 'Voulez-vous que je crée la présentation maintenant ?', precision: 'le sujet et le public de la présentation' },
  creer_video: { question: 'Voulez-vous que je crée la vidéo maintenant ?', precision: 'ce que la vidéo doit montrer et où elle sera publiée' },
}

// Les mots qui ne disent rien du contenu : la demande elle-même (« crée un site »), les petits mots, la politesse.
const VIDES = new Set(`
  je j tu il elle nous vous on me moi te m t s se le la les l un une des de du d au aux a à en et ou pour par avec sans
  sur dans mon ma mes ton ta tes son sa ses notre nos votre vos ce cet cette ces c qu que qui quoi est suis veux voudrais
  voulez veut aimerais souhaite besoin faut peux pouvez peut stp svp merci bonjour bonsoir salut kekeli s'il plait plaît
  crée cree créer creer créez fais fait faire faites fasse construis construire construit réalise réaliser prépare
  préparer génère générer genere generer dessine dessiner écris écrire monte monter nouveau nouvelle petit petite beau belle
  site web internet page vitrine image photo affiche visuel illustration document fichier word pdf présentation presentation
  diapositives powerpoint vidéo video clip moi-même maintenant vite rapidement simple joli professionnel pro
`.split(/\s+/).filter(Boolean))

const ACCORDS = /^(oui|ouais|ok|okay|d'accord|daccord|vas-y|vas y|go|allez|c'est bon|parfait|exactement|oui[ ,].*)$/i

const motsDe = (texte) => String(texte ?? '').toLocaleLowerCase('fr').replace(/[’]/g, "'").match(/[\p{L}\p{N}'-]+/gu) ?? []

/** Le message se répète : une suite d'au moins 3 mots revient, ou un même mot revient 3 fois de suite. */
export function estRepete(texte) {
  const m = motsDe(texte)
  for (let i = 2; i < m.length; i++) if (m[i] === m[i - 1] && m[i] === m[i - 2]) return true
  const vus = new Set()
  for (let i = 0; i + 3 <= m.length; i++) {
    const suite = m.slice(i, i + 3).join(' ')
    if (vus.has(suite)) return true
    vus.add(suite)
  }
  return false
}

/** Le message est illisible : trop de « mots » sans voyelle ou faits d'une seule lettre répétée. */
export function estBrouille(texte) {
  const m = motsDe(texte).filter((x) => x.length > 1 && !/^\d+$/.test(x))
  if (m.length < 2) return false
  const etranges = m.filter((x) => !/[aeiouyàâäéèêëîïôöùûü]/.test(x) || /^(.)\1+$/.test(x) || x.length > 25)
  return etranges.length / m.length > 0.3
}

/** Ce qui décrit vraiment le contenu demandé (activité, nom, lieu, détails), sans la demande elle-même. */
export function motsUtiles(texte) {
  return motsDe(texte).filter((x) => x.length > 1 && !VIDES.has(x) && !VIDES.has(x.replace(/^[a-z]'/, '')))
}

/** Le texte compris, sans la répétition (la suite de mots déjà dite une fois n'est gardée qu'une fois). */
export function sansRepetition(texte) {
  const mots = String(texte ?? '').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean)
  const cle = (x) => x.toLocaleLowerCase('fr').replace(/[.,;:!?…«»"]/g, '')
  const garde = []
  for (let i = 0; i < mots.length; i++) {
    if (garde.length && cle(garde.at(-1)) === cle(mots[i])) continue
    // La suite qui commence ici reprend le début déjà gardé : on saute la partie répétée.
    let saut = 0
    for (let k = Math.min(garde.length, mots.length - i); k >= 3; k--) {
      const debut = garde.slice(0, k).map(cle).join(' ')
      if (mots.slice(i, i + k).map(cle).join(' ') === debut) { saut = k; break }
    }
    if (saut) {
      // Le morceau répété peut continuer plus loin que le début gardé : la suite remplace alors la fin gardée.
      i += saut - 1
      continue
    }
    garde.push(mots[i])
  }
  return garde.join(' ')
}

/**
 * Faut-il demander une confirmation avant de lancer l'outil ? Rend null (le travail part) ou la question à poser.
 *
 * @param {{ outil: string, arguments?: object, message: string, historique?: Array<{role: string, contenu: string}>, contexte?: object }} d
 */
export function confirmationNecessaire({ outil, arguments: args = {}, message, historique = [], contexte = {} }) {
  if (!OUTILS_CONFIRMES.includes(outil)) return null
  // Un plan est déjà proposé (gros document, longue vidéo) : le client le validera avec « Construire ce plan ».
  if (typeof args?.plan === 'string' && args.plan.trim()) return null
  // Un fichier joint à corriger, résumer ou traduire : la demande porte sur le fichier, elle est claire.
  if (typeof args?.fichier === 'string' && args.fichier.trim()) return null
  if (String(args?.confirme ?? '').trim().toLowerCase() === 'oui') return null
  const precedents = (Array.isArray(historique) ? historique : []).filter((m) => m && typeof m.contenu === 'string')
  const derniereReponse = [...precedents].reverse().find((m) => m.role === 'assistant')
  // Le client répond à une question de Kekeli : c'est déjà une confirmation.
  if (derniereReponse && /\?\s*$/.test(derniereReponse.contenu.trim())) return null
  const texte = String(message ?? '').trim()
  if (ACCORDS.test(texte) && derniereReponse) return null

  const quoi = QUOI[outil]
  const clientAvant = precedents.filter((m) => m.role !== 'assistant').slice(-3).map((m) => m.contenu)
  const dernierClient = clientAvant.at(-1)
  const meme = (a, b) => motsDe(a).join(' ') === motsDe(b).join(' ')

  if (estBrouille(texte)) {
    return { raison: 'brouille', question: `Je n’ai pas bien compris votre message. ${quoi.question} Pour un meilleur résultat, dites-moi en une phrase ${quoi.precision}.` }
  }
  if (estRepete(texte) || (dernierClient && meme(dernierClient, texte))) {
    const compris = sansRepetition(texte)
    return { raison: 'repete', question: `J’ai compris : « ${compris} ». ${quoi.question}` }
  }
  // Trop court : aucun mot qui décrive le contenu (ni activité, ni nom, ni sujet), même avec les derniers messages
  // du client. Des photos ou des fichiers joints disent déjà ce qu'il faut montrer.
  const joints = Number(contexte?.photos) > 0 || (Array.isArray(contexte?.fichiers_joints) && contexte.fichiers_joints.length > 0)
  if (!joints && motsUtiles([...clientAvant, texte].join(' ')).length === 0) {
    return { raison: 'flou', question: `${quoi.question} Pour un meilleur résultat, dites-moi en une phrase ${quoi.precision}.` }
  }
  return null
}
