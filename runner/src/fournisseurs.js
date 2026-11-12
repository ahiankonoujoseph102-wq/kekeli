// Catalogue des fournisseurs d'IA que le Runner sait utiliser.
//
// L'ordre de la liste et le modèle de chaque fournisseur sont réglés dans le site
// Kekeli (variables KEKELI_FOURNISSEURS et KEKELI_MODELE_*), qui les envoie avec
// chaque tâche. Le Runner, lui, détient les clés : un fournisseur sans clé est ignoré.
//
// Les clés ne vont jamais dans l'ordinateur virtuel : le Runner les range dans les
// « secrets » d'E2B, et le proxy de sortie d'E2B les ajoute lui-même aux requêtes
// HTTPS qui partent vers l'hôte du fournisseur. OpenCode ne reçoit qu'une fausse clé.

export const FAUSSE_CLE = 'injectee-par-e2b'

export const CATALOGUE = {
  gemini: {
    variableCle: 'GEMINI_API_KEY',
    secret: 'kekeli_gemini',
    hote: 'generativelanguage.googleapis.com',
    entete: (valeur) => ({ 'x-goog-api-key': valeur }),
    fournisseurOpencode: 'google',
  },
  // Groq : offre gratuite (modèles gpt-oss), aucun entraînement sur les demandes d'après son contrat.
  groq: {
    variableCle: 'GROQ_API_KEY',
    secret: 'kekeli_groq',
    hote: 'api.groq.com',
    entete: (valeur) => ({ Authorization: `Bearer ${valeur}` }),
    fournisseurOpencode: 'groq',
  },
  // OpenRouter : UNIQUEMENT les modèles gratuits (nom qui finit par « :free »). Voir modeleAutorise().
  openrouter: {
    variableCle: 'OPENROUTER_API_KEY',
    secret: 'kekeli_openrouter',
    hote: 'openrouter.ai',
    entete: (valeur) => ({ Authorization: `Bearer ${valeur}` }),
    fournisseurOpencode: 'openrouter',
  },
  mistral: {
    variableCle: 'MISTRAL_API_KEY',
    secret: 'kekeli_mistral',
    hote: 'api.mistral.ai',
    entete: (valeur) => ({ Authorization: `Bearer ${valeur}` }),
    fournisseurOpencode: 'mistral',
  },
  // Cloudflare Workers AI : 10 000 « neurones » gratuits par jour (remise à zéro à minuit UTC), partagés avec la
  // création d'images ; aucun entraînement sur les demandes. Même clé et même hôte que le service d'images. Le compte
  // doit rester en offre gratuite (Workers Free) : au-delà de la limite, les demandes échouent, sans aucune facture.
  workersai: {
    variableCle: 'CLOUDFLARE_API_TOKEN',
    secret: 'kekeli_cloudflare',
    hote: 'api.cloudflare.com',
    entete: (valeur) => ({ Authorization: `Bearer ${valeur}` }),
    fournisseurOpencode: 'workersai',
    besoinCompteCloudflare: true,
  },
  // OpenAI (clé payante) : JAMAIS dans l'ordinateur virtuel ni dans les secrets d'E2B. L'agent y passe par le relais
  // de l'ordinateur, que le Runner suit (relais.js) : seule la passerelle du Runner ajoute la clé.
  openai: {
    variableCle: 'OPENAI_API_KEY',
    hote: 'api.openai.com',
    entete: (valeur) => ({ Authorization: `Bearer ${valeur}` }),
    fournisseurOpencode: 'openai',
    viaRelais: true,
  },
}

/** Modèles OpenAI permis pour construire (jamais un modèle très cher choisi par erreur de réglage). */
export const MODELES_CONSTRUCTION_OPENAI = ['gpt-6-luna', 'gpt-6.1-terra', 'gpt-6.1-sol']

/**
 * Fournisseurs que l'agent peut utiliser : ceux dont la clé est dans les secrets d'E2B, plus OpenAI quand sa clé est
 * dans le Runner (par le relais, jamais dans l'ordinateur).
 */
export function clesAgent(clesPresentes, env = process.env) {
  return new Set([...clesPresentes, ...(valeurCle('OPENAI_API_KEY', env) ? ['openai'] : [])])
}

// Modèles gratuits autorisés quand un fournisseur a aussi des modèles payants ou réservés à une offre payante
// (idée reprise de freellmapi : un catalogue de modèles gratuits, jamais un modèle choisi au hasard).
// Cloudflare : modèles de texte qui savent utiliser des outils, disponibles sur l'offre gratuite (les modèles
// réservés à l'offre payante, par exemple Kimi K2.6, GLM-5.2, GLM-5.3 et DeepSeek V4, ne sont pas dans la liste).
export const MODELES_GRATUITS = {
  workersai: [
    '@cf/zai-org/glm-4.7-flash',
    '@cf/zai-org/glm-5.3-flash',
    '@cf/qwen/qwen3-30b-a3b-fp8',
    '@cf/openai/gpt-oss-120b',
    '@cf/openai/gpt-oss-20b',
    '@cf/meta/llama-4-scout-17b-16e-instruct',
    '@cf/moonshotai/kimi-k2.5',
  ],
}

// Modèles Gemini payants (aucune offre gratuite) : refusés même si la clé les permettrait un jour.
const GEMINI_PAYANTS = /(^|-)(pro|argon|ultra)(-|$)/i

// Services d'images utilisés par les outils de Kekeli dans l'ordinateur virtuel. Comme pour les modèles,
// la clé reste dans le Runner : le proxy d'E2B l'ajoute lui-même aux requêtes vers l'hôte du service.
// Ces services ne servent jamais de « cerveau » à l'agent.
export const SERVICES = {
  // Photos libres de Pexels (licence sans attribution obligatoire) : clé gratuite.
  pexels: {
    variableCle: 'PEXELS_API_KEY',
    secret: 'kekeli_pexels',
    hote: 'api.pexels.com',
    entete: (valeur) => ({ Authorization: valeur }),
  },
  // Création d'images avec Cloudflare Workers AI (10 000 « neurones » gratuits par jour).
  // Le numéro de compte n'est pas un secret : il est transmis à l'outil dans ses réglages.
  cloudflare: {
    variableCle: 'CLOUDFLARE_API_TOKEN',
    secret: 'kekeli_cloudflare',
    hote: 'api.cloudflare.com',
    entete: (valeur) => ({ Authorization: `Bearer ${valeur}` }),
  },
}

/**
 * Valeur d'une clé lue dans l'environnement, débarrassée de ce qu'un copier-coller depuis un téléphone
 * ajoute parfois autour : espaces, crochets, guillemets.
 */
export function valeurCle(nom, env = process.env) {
  return String(env[nom] ?? '').trim().replace(/^[\s\[\]«»"'`<>()]+|[\s\[\]«»"'`<>()]+$/g, '')
}

/** Numéro de compte Cloudflare (32 caractères), même si la variable contient l'adresse d'une page du tableau de bord. */
export function numeroCompteCloudflare(env = process.env) {
  return /[0-9a-f]{32}/.exec(String(env.CLOUDFLARE_ACCOUNT_ID ?? ''))?.[0] ?? null
}

/** Fournisseur de modèles ou service d'images, par son nom. */
export const definition = (nom) => CATALOGUE[nom] ?? SERVICES[nom]

/**
 * Noms des fournisseurs et des services dont la clé est présente dans l'environnement
 * (avec les autres réglages nécessaires, par exemple le numéro de compte Cloudflare).
 */
export function fournisseursAvecCle(env = process.env) {
  return new Set(
    Object.entries({ ...CATALOGUE, ...SERVICES })
      .filter(([nom, f]) => !f.viaRelais && Boolean(valeurCle(f.variableCle, env)) && ((nom !== 'cloudflare' && !f.besoinCompteCloudflare) || Boolean(numeroCompteCloudflare(env))))
      .map(([nom]) => nom),
  )
}

/**
 * Aucune dépense possible, même par erreur de réglage : chez OpenRouter, seuls les modèles gratuits (« :free »),
 * jamais « openrouter/free » (qui choisit un modèle au hasard) ; chez Cloudflare, seulement la liste des modèles
 * gratuits ; chez Google, jamais un modèle Gemini payant (Pro, Argon, Ultra).
 */
export function modeleAutorise(fournisseur, modele) {
  if (typeof modele !== 'string' || !modele.trim()) return false
  if (fournisseur === 'openrouter') return /^[a-z0-9][\w.\-]*\/[\w.\-]+:free$/i.test(modele) && !/^openrouter\//i.test(modele)
  if (MODELES_GRATUITS[fournisseur]) return MODELES_GRATUITS[fournisseur].includes(modele)
  if (fournisseur === 'gemini') return !GEMINI_PAYANTS.test(modele)
  if (fournisseur === 'openai') return MODELES_CONSTRUCTION_OPENAI.includes(modele)
  return true
}

// Même modèle pour un même projet pendant 30 minutes (idée reprise de freellmapi, « sticky sessions ») : la
// conversation reste cohérente d'une demande à l'autre, au lieu de changer de modèle à chaque message.
export const DUREE_MEME_MODELE_MS = 30 * 60 * 1000

export class MemoireModeles {
  constructor(horloge = () => Date.now()) {
    this.horloge = horloge
    this.projets = new Map()
  }

  /** Le modèle qui vient de travailler pour ce projet. */
  retenir(projet, entree) {
    if (projet && entree) this.projets.set(projet, { cle: cleEntree(entree), a: this.horloge() })
  }

  /** La liste des entrées disponibles, avec en tête celle qui a travaillé pour ce projet il y a moins de 30 minutes. */
  ordonner(projet, disponibles) {
    const souvenir = this.projets.get(projet)
    if (!souvenir || this.horloge() - souvenir.a > DUREE_MEME_MODELE_MS) return disponibles
    const preferee = disponibles.find((e) => cleEntree(e) === souvenir.cle)
    return preferee ? [preferee, ...disponibles.filter((e) => e !== preferee)] : disponibles
  }
}

/** Identifiant d'une entrée de la liste, par exemple « gemini/gemini-3.5-flash-lite ». */
export function cleEntree(entree) {
  return `${entree.fournisseur}/${entree.modele}`
}

/**
 * Garde, dans l'ordre reçu, les entrées utilisables : fournisseur connu, clé présente,
 * modèle indiqué. Les doublons sont retirés.
 */
export function entreesUtilisables(liste, clesPresentes) {
  const vues = new Set()
  return (Array.isArray(liste) ? liste : []).filter((entree) => {
    if (!entree || typeof entree.fournisseur !== 'string' || typeof entree.modele !== 'string' || !entree.modele) return false
    if (!CATALOGUE[entree.fournisseur] || !clesPresentes.has(entree.fournisseur)) return false
    if (!modeleAutorise(entree.fournisseur, entree.modele)) return false
    const cle = cleEntree(entree)
    if (vues.has(cle)) return false
    vues.add(cle)
    return true
  })
}

/** Règles réseau E2B : la clé de chaque fournisseur est ajoutée par le proxy d'E2B. */
export function reglesReseau(clesPresentes, remplir) {
  const regles = {}
  for (const nom of clesPresentes) {
    const f = definition(nom)
    if (f) regles[f.hote] = [{ transform: { headers: f.entete(remplir(f.secret)) } }]
  }
  return regles
}

/**
 * Configuration des fournisseurs pour OpenCode : uniquement de fausses clés. Chez OpenRouter, OpenCode ne connaît
 * que les modèles gratuits de la liste (liste blanche) : il ne peut pas en choisir un payant de lui-même.
 */
export function fournisseursOpencode(clesPresentes, entrees = [], env = process.env) {
  const config = {}
  for (const nom of clesPresentes) {
    const f = CATALOGUE[nom]
    if (!f) continue
    if (nom === 'workersai') {
      // Cloudflare n'est pas un fournisseur connu d'OpenCode : il passe par son adresse compatible OpenAI, avec
      // seulement les modèles gratuits de la liste de la demande (outils activés pour l'agent).
      const modeles = [...new Set((entrees ?? []).filter((e) => e?.fournisseur === 'workersai' && modeleAutorise('workersai', e.modele)).map((e) => e.modele))]
      config.workersai = {
        npm: '@ai-sdk/openai-compatible',
        name: 'Cloudflare Workers AI',
        options: { apiKey: FAUSSE_CLE, baseURL: `https://${f.hote}/client/v4/accounts/${numeroCompteCloudflare(env)}/ai/v1` },
        models: Object.fromEntries(modeles.map((m) => [m, { name: m, tool_call: true, limit: { context: 128000, output: 8192 } }])),
      }
      continue
    }
    config[f.fournisseurOpencode] = { options: { apiKey: FAUSSE_CLE } }
    if (nom === 'openrouter') {
      const gratuits = [...new Set((entrees ?? []).filter((e) => e?.fournisseur === 'openrouter' && modeleAutorise('openrouter', e.modele)).map((e) => e.modele))]
      config[f.fournisseurOpencode].whitelist = gratuits.length ? gratuits : ['aucun-modele-autorise:free']
    }
  }
  return config
}

/** Réglages lus par les outils de l'agent : pays du client, modèle de vision, services d'images disponibles. */
export function reglagesOutils(demande, clesPresentes, env = process.env) {
  const services = ['pexels', 'cloudflare'].filter((nom) => clesPresentes.has(nom))
  return {
    pays: demande.pays ?? null,
    vision: clesPresentes.has('gemini') ? demande.modele_vision || 'gemini-3.1-flash-lite' : null,
    services,
    cloudflare_compte: services.includes('cloudflare') ? numeroCompteCloudflare(env) : null,
    // Images encore permises aujourd'hui pour tout Kekeli (réglage de l'administration) ; 0 si le site ne le dit pas.
    images_max: Number.isInteger(demande.images_restantes) ? Math.max(0, demande.images_restantes) : 0,
  }
}
