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
}

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
      .filter(([nom, f]) => Boolean(valeurCle(f.variableCle, env)) && (nom !== 'cloudflare' || Boolean(numeroCompteCloudflare(env))))
      .map(([nom]) => nom),
  )
}

/**
 * Aucune dépense possible : chez OpenRouter, seuls les modèles gratuits (« :free ») sont autorisés,
 * jamais « openrouter/free » (qui choisit un modèle au hasard) ni un modèle payant, même par erreur de réglage.
 */
export function modeleAutorise(fournisseur, modele) {
  if (typeof modele !== 'string' || !modele.trim()) return false
  if (fournisseur === 'openrouter') return /^[a-z0-9][\w.\-]*\/[\w.\-]+:free$/i.test(modele) && !/^openrouter\//i.test(modele)
  return true
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
export function fournisseursOpencode(clesPresentes, entrees = []) {
  const config = {}
  for (const nom of clesPresentes) {
    const f = CATALOGUE[nom]
    if (!f) continue
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
