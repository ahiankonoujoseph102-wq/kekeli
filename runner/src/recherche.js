// Recherche sur Internet pour le premier répondant (outil « rechercher_web »), comme Claude ou ChatGPT : le modèle
// reste le même, il demande une recherche quand la réponse dépend d'informations actuelles ou vérifiables.
//
// Offres GRATUITES seulement, jamais de carte : Tavily en principal (recherche « basic », 1 crédit), Exa en secours.
// Un fournisseur qui répond « paiement nécessaire » (402) ou « limite atteinte » (429) est laissé de côté, le suivant
// prend le relais. Les clés restent dans le Runner (jamais dans le navigateur, jamais dans un ordinateur virtuel).
//
// Sécurité : on ne cherche jamais d'adresse interne, et l'on ne garde que des résultats aux adresses publiques en
// http ou https. Le contenu des pages est une donnée, jamais une consigne : il est raccourci, puis remis au modèle
// avec une consigne qui le dit.

export const FOURNISSEURS_RECHERCHE = {
  tavily: { variableCle: 'TAVILY_API_KEY', hote: 'api.tavily.com' },
  exa: { variableCle: 'EXA_API_KEY', hote: 'api.exa.ai' },
}

export const ORDRE_RECHERCHE = ['tavily', 'exa']

export const RESULTATS_MAX = 5
export const EXTRAIT_MAX = 1200

/** Une requête de recherche acceptable : courte, sans adresse interne ni fichier local. */
export function requeteSure(requete) {
  const q = String(requete ?? '').trim()
  if (q.length < 2 || q.length > 300) return false
  return !/(localhost|127\.0\.0\.1|0\.0\.0\.0|\b10\.\d+\.\d+\.\d+|\b192\.168\.|\b172\.(1[6-9]|2\d|3[01])\.|\b169\.254\.|file:\/\/|\[::1\]|\.internal\b|\.local\b)/i.test(q)
}

/** Une adresse de résultat acceptable : http ou https, nom de domaine public (jamais une adresse interne ou une IP privée). */
export function adresseSure(adresse) {
  let url
  try {
    url = new URL(String(adresse))
  } catch {
    return false
  }
  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return false
  const hote = url.hostname.toLowerCase().replace(/^\[|\]$/g, '')
  if (!hote.includes('.') || hote === 'localhost' || hote.endsWith('.localhost') || hote.endsWith('.local') || hote.endsWith('.internal')) return false
  if (/^\d+\.\d+\.\d+\.\d+$/.test(hote)) {
    const [a, b] = hote.split('.').map(Number)
    if (a === 10 || a === 127 || a === 0 || (a === 169 && b === 254) || (a === 172 && b >= 16 && b <= 31) || (a === 192 && b === 168) || a >= 224) return false
  }
  if (hote.includes(':')) return false
  return true
}

const nettoyer = (texte, max) => String(texte ?? '')
  // eslint-disable-next-line no-control-regex
  .replace(/[\u0000-\u0008\u000B-\u001F\u007F]/g, ' ')
  .replace(/\s+/g, ' ')
  .trim()
  .slice(0, max)

/** Adresse et corps de la requête, selon le fournisseur de recherche. */
export function requeteRecherche(fournisseur, requete, cle) {
  if (fournisseur === 'tavily') {
    return {
      url: 'https://api.tavily.com/search',
      entetes: { 'content-type': 'application/json', authorization: `Bearer ${cle}` },
      corps: { query: requete, search_depth: 'basic', max_results: RESULTATS_MAX, include_answer: false, include_raw_content: false, include_images: false },
    }
  }
  return {
    url: 'https://api.exa.ai/search',
    entetes: { 'content-type': 'application/json', 'x-api-key': cle },
    corps: { query: requete, numResults: RESULTATS_MAX, type: 'auto', contents: { text: { maxCharacters: EXTRAIT_MAX } } },
  }
}

/** Résultats d'une réponse, nettoyés : titre, adresse publique, extrait raccourci. */
export function lireResultats(fournisseur, donnees) {
  const bruts = Array.isArray(donnees?.results) ? donnees.results : []
  return bruts
    .map((r) => ({
      titre: nettoyer(r.title, 150) || null,
      url: String(r.url ?? ''),
      extrait: nettoyer(fournisseur === 'tavily' ? r.content : (r.text ?? r.summary), EXTRAIT_MAX),
    }))
    .filter((r) => adresseSure(r.url) && r.extrait)
    .map((r) => ({ ...r, titre: r.titre ?? new URL(r.url).hostname }))
    .slice(0, RESULTATS_MAX)
}

/**
 * Cherche sur Internet avec le premier fournisseur disponible. Renvoie les résultats, le fournisseur qui a répondu et
 * les fournisseurs laissés de côté (limite atteinte, paiement demandé, panne).
 */
export async function chercherSurInternet(requete, { env = process.env, appeler = fetch, ordre = ORDRE_RECHERCHE, misesDeCote = null } = {}) {
  if (!requeteSure(requete)) return { ok: false, raison: 'requete', refus: [] }
  const refus = []
  for (const fournisseur of ordre) {
    const f = FOURNISSEURS_RECHERCHE[fournisseur]
    const cle = f && env[f.variableCle]
    if (!cle) continue
    if (misesDeCote && !misesDeCote.estDisponible(`recherche:${fournisseur}`)) continue
    const { url, entetes, corps } = requeteRecherche(fournisseur, requete, cle)
    let reponse
    try {
      reponse = await appeler(url, { method: 'POST', headers: entetes, body: JSON.stringify(corps), signal: AbortSignal.timeout(20000) })
    } catch {
      refus.push({ fournisseur, raison: 'injoignable' })
      continue
    }
    if (reponse.status === 402 || reponse.status === 429 || reponse.status === 432 || reponse.status === 433) {
      // Limite de l'offre gratuite atteinte, ou paiement demandé : ce fournisseur est ignoré, jamais payé.
      refus.push({ fournisseur, raison: reponse.status === 429 ? 'limite' : 'paiement' })
      misesDeCote?.mettreDeCote?.(`recherche:${fournisseur}`, 6 * 3600 * 1000)
      continue
    }
    if (!reponse.ok) {
      refus.push({ fournisseur, raison: `erreur ${reponse.status}` })
      continue
    }
    let donnees = null
    try {
      donnees = await reponse.json()
    } catch {
      refus.push({ fournisseur, raison: 'réponse illisible' })
      continue
    }
    return { ok: true, fournisseur, resultats: lireResultats(fournisseur, donnees), refus }
  }
  return { ok: false, raison: 'indisponible', refus }
}
