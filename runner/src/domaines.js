// Nom de domaine du client pour son site publié sur Cloudflare Pages (formules payantes).
//
// Le client achète son nom de domaine où il veut. Kekeli demande à Cloudflare de rattacher l'adresse
// (par exemple www.monrestaurant.com) au projet Cloudflare Pages du site, puis le client ajoute chez son
// vendeur de nom de domaine un enregistrement « CNAME » : www → nom-du-projet.pages.dev. Cloudflare vérifie
// l'enregistrement et crée le certificat HTTPS, gratuitement (100 domaines par projet dans l'offre gratuite).
const API = 'https://api.cloudflare.com/client/v4'

export const ACTIONS = new Set(['ajouter', 'verifier', 'retirer'])

/** Adresse acceptée : au moins un point, des lettres, des chiffres et des tirets (par exemple www.monsite.com). */
export const DOMAINE = /^(?=.{4,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/

/** Message d'explication de Cloudflare, s'il y en a un (enregistrement absent, mauvaise valeur…). */
function explication(resultat) {
  return resultat?.verification_data?.error_message || resultat?.validation_data?.error_message || null
}

function reponse(resultat, nom) {
  return {
    domaine: resultat?.name ?? null,
    statut: resultat?.status ?? 'inconnu',
    cible: `${nom}.pages.dev`,
    explication: explication(resultat),
  }
}

/**
 * Ajoute, vérifie ou retire le nom de domaine d'un projet Cloudflare Pages.
 * `jeton` : clé Cloudflare (droit « Cloudflare Pages : modifier ») ; `compte` : numéro du compte.
 */
export async function gererDomaine({ action, nom, domaine, compte, jeton, telecharger = fetch }) {
  const appeler = async (chemin, methode = 'GET', corps) => {
    const r = await telecharger(`${API}/accounts/${compte}/pages/projects/${nom}/domains${chemin}`, {
      method: methode,
      headers: { Authorization: `Bearer ${jeton}`, ...(corps ? { 'Content-Type': 'application/json' } : {}) },
      body: corps ? JSON.stringify(corps) : undefined,
      signal: AbortSignal.timeout(60000),
    })
    const donnees = await r.json().catch(() => ({}))
    if (!r.ok || donnees.success === false) {
      const erreur = new Error(`Cloudflare a refusé la demande (${(donnees.errors ?? []).map((e) => e.message).join(' ; ') || `code ${r.status}`}).`)
      erreur.statut = r.status
      erreur.codes = (donnees.errors ?? []).map((e) => e.code)
      throw erreur
    }
    return donnees.result
  }
  const adresse = `/${encodeURIComponent(domaine)}`

  if (action === 'ajouter') {
    try {
      return reponse(await appeler('', 'POST', { name: domaine }), nom)
    } catch (erreur) {
      // Déjà rattaché à ce projet (deuxième essai) : on renvoie simplement son état.
      if (erreur.statut !== 409 && !/already/i.test(erreur.message)) throw erreur
      return reponse(await appeler(adresse), nom)
    }
  }

  if (action === 'verifier') {
    const resultat = await appeler(adresse)
    if (['pending', 'error', 'deactivated'].includes(resultat?.status)) {
      // Demande à Cloudflare de vérifier de nouveau l'enregistrement chez le vendeur du nom de domaine.
      return reponse(await appeler(adresse, 'PATCH', {}), nom)
    }
    return reponse(resultat, nom)
  }

  if (action === 'retirer') {
    try {
      await appeler(adresse, 'DELETE')
    } catch (erreur) {
      if (erreur.statut !== 404) throw erreur
    }
    return { domaine, statut: 'retire', cible: `${nom}.pages.dev`, explication: null }
  }

  throw new Error('Action inconnue.')
}
