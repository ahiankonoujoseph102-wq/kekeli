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

/** Noms des fournisseurs du catalogue dont la clé est présente dans l'environnement. */
export function fournisseursAvecCle(env = process.env) {
  return new Set(Object.entries(CATALOGUE).filter(([, f]) => Boolean(env[f.variableCle])).map(([nom]) => nom))
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
    const f = CATALOGUE[nom]
    if (f) regles[f.hote] = [{ transform: { headers: f.entete(remplir(f.secret)) } }]
  }
  return regles
}

/** Configuration des fournisseurs pour OpenCode : uniquement de fausses clés. */
export function fournisseursOpencode(clesPresentes) {
  const config = {}
  for (const nom of clesPresentes) {
    const f = CATALOGUE[nom]
    if (f) config[f.fournisseurOpencode] = { options: { apiKey: FAUSSE_CLE } }
  }
  return config
}
