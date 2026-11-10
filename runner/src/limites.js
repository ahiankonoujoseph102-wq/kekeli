// Décide quand un fournisseur doit être mis de côté (limite atteinte) et pour combien de temps.
//
// Quand un fournisseur répond « limite atteinte » (code 429), OpenCode réessaie tout seul
// et signale chaque nouvel essai par un statut « retry » qui contient le message d'erreur.
//  - Si le fournisseur demande d'attendre longtemps (plus de deux minutes, par exemple le
//    quota du jour épuisé chez Gemini, qui indique « Please retry in 3h2m10s »), il est mis de
//    côté pour toute cette durée, c'est-à-dire jusqu'à sa remise à zéro, et on passe au suivant.
//    Si le message parle du quota du jour sans donner de durée, on attend l'heure de remise à
//    zéro envoyée par le site Kekeli.
//  - Si l'attente demandée est courte (limite par minute), on laisse OpenCode patienter.
//  - Après trois refus de suite, le fournisseur est mis de côté pour une pause qui double à
//    chaque fois (2, 4, 8 minutes, et ainsi de suite, au plus six heures).
//  - Une panne qui n'est pas une limite (erreur du serveur du fournisseur) fait basculer au
//    bout de quatre essais, avec une pause courte.

export const ATTENTE_LONGUE_MS = 2 * 60 * 1000
export const PAUSE_DE_BASE_MS = 2 * 60 * 1000
export const PAUSE_MAXIMALE_MS = 6 * 60 * 60 * 1000

const MOTIFS_LIMITE = /\b429\b|quota|rate.?limit|resource.?exhausted|too many requests|limit exceeded/i

export function estUneLimite(message) {
  return MOTIFS_LIMITE.test(String(message ?? '')) && !MOTIFS_TROP_GRANDE.test(String(message ?? ''))
}

// Demande trop grande pour ce modèle chez ce fournisseur (par exemple Groq gratuit : 8 000 tokens par minute,
// alors qu'une demande de l'agent en pèse environ 9 000). Attendre ne sert à rien : on passe au suivant.
const MOTIFS_TROP_GRANDE = /request too large|request entity too large|\b413\b|maximum context length|context length exceeded|context_length_exceeded|reduce (?:your|the) (?:message|prompt)/i

export function estTropGrande(message) {
  return MOTIFS_TROP_GRANDE.test(String(message ?? ''))
}

// Erreurs passagères du fournisseur : on relance le même travail au lieu d'échouer (par exemple Gemini qui refuse
// une conversation terminée par un tour du modèle, ou un serveur surchargé).
const MOTIFS_PASSAGERS = /ending with a model turn|\b(500|502|503|504)\b|overloaded|unavailable|internal error|try again later|deadline exceeded/i

export function estPassagere(message) {
  return !estUneLimite(message) && MOTIFS_PASSAGERS.test(String(message ?? ''))
}

/**
 * Durée d'attente demandée par le fournisseur, en millisecondes, si elle est indiquée.
 * Formats reconnus : « retry in 41.5s », « retry in 3h2m10.49s », « retry after 500ms ».
 */
export function attenteDemandeeMs(message) {
  const texte = String(message ?? '')
  const correspondance = texte.match(/retry (?:in|after) ((?:[\d.]+\s*(?:h|ms|m|s|seconds?)\s*)+)/i)
  if (!correspondance) return null
  const unites = { h: 3600000, m: 60000, s: 1000, ms: 1, second: 1000, seconds: 1000 }
  let total = 0
  for (const [, valeur, unite] of correspondance[1].matchAll(/([\d.]+)\s*(ms|h|m|seconds?|s)/gi)) {
    total += Number(valeur) * unites[unite.toLowerCase()]
  }
  return Number.isFinite(total) && total > 0 ? total : null
}

/** Le message parle-t-il du quota du jour ? */
export function estUnQuotaDuJour(message) {
  return /per[\s_-]?day|daily|PerDay/i.test(String(message ?? ''))
}

export class MisesDeCote {
  constructor(horloge = () => Date.now()) {
    this.horloge = horloge
    this.etats = new Map()
  }

  estDisponible(cle) {
    const etat = this.etats.get(cle)
    return !etat || etat.jusqua <= this.horloge()
  }

  jusqua(cle) {
    const etat = this.etats.get(cle)
    return etat && etat.jusqua > this.horloge() ? new Date(etat.jusqua) : null
  }

  /** Enregistre une réponse réussie : le compteur de refus de suite repart à zéro. */
  reussite(cle) {
    const etat = this.etats.get(cle)
    if (etat) etat.refusDeSuite = 0
  }

  /**
   * Analyse un nouvel essai annoncé par OpenCode. Renvoie null s'il faut laisser patienter,
   * sinon la date jusqu'à laquelle le fournisseur est mis de côté (il faut alors basculer).
   */
  analyserEssai(cle, message, tentative, { remiseAZero = null, prochainEssai = null } = {}) {
    const limite = estUneLimite(message)
    const maintenant = this.horloge()
    let demande = attenteDemandeeMs(message)
    if (demande === null && Number.isFinite(prochainEssai)) demande = Math.max(0, prochainEssai - maintenant)
    const remise = remiseAZero ? new Date(remiseAZero).getTime() : NaN
    let dureeMs = null

    if (limite && demande !== null && demande > ATTENTE_LONGUE_MS) {
      dureeMs = Math.min(demande, 24 * 60 * 60 * 1000)
    } else if (limite && estUnQuotaDuJour(message) && demande === null) {
      dureeMs = Number.isFinite(remise) && remise > maintenant ? remise - maintenant : PAUSE_MAXIMALE_MS
    } else if (limite && tentative >= 3) {
      const refus = (this.etats.get(cle)?.refusDeSuite ?? 0) + 1
      dureeMs = Math.min(PAUSE_DE_BASE_MS * 2 ** (refus - 1), PAUSE_MAXIMALE_MS)
    } else if (!limite && tentative >= 4) {
      dureeMs = PAUSE_DE_BASE_MS
    }

    if (dureeMs === null) return null
    return this.mettreDeCote(cle, dureeMs, limite)
  }

  mettreDeCote(cle, dureeMs, limite = true) {
    const precedent = this.etats.get(cle)
    const jusqua = this.horloge() + dureeMs
    this.etats.set(cle, { jusqua, refusDeSuite: (precedent?.refusDeSuite ?? 0) + (limite ? 1 : 0) })
    return new Date(jusqua)
  }

  /** Première date à laquelle l'une des entrées redevient disponible. */
  prochaineDisponibilite(cles) {
    const dates = cles.map((cle) => this.etats.get(cle)?.jusqua ?? this.horloge())
    return new Date(Math.min(...dates))
  }

  etat() {
    const resultat = {}
    for (const [cle, etat] of this.etats) {
      if (etat.jusqua > this.horloge()) resultat[cle] = new Date(etat.jusqua).toISOString()
    }
    return resultat
  }
}
