// Les temps d'un plan de montage, calés sur les mots de la voix (comme la fonction « w('mot') » des montages de Lomé IA
// School) : chaque plan commence un peu avant son mot, et finit quand le plan suivant commence. Les éléments d'un plan
// (cases d'une liste, groupes d'un numéro, bulles d'une discussion) apparaissent aussi sur leur mot.

/** Un plan commence un peu avant son mot : l'image arrive avec la parole, jamais après. */
export const AVANCE = 0.1
/** La carte finale reste à l'écran après la dernière parole (musique seule). */
export const TENUE_FIN = 3
/** Rythme du style « Dynamique » : jamais un plan plus long (sauf l'accroche, le numéro et la carte). */
export const RYTHME = { min: 0.6, max: 2.5, accroche: 3, numero: 4.5 }

/** Un mot réduit à ses lettres et à ses chiffres (accents, ponctuation, apostrophes et majuscules ignorés). */
export function normaliser(mot) {
  return String(mot ?? '').toLowerCase().replace(/[’']/g, '').normalize('NFD').replace(/[^\p{L}\p{N}]/gu, '')
}

/** Le nombre de lettres à changer pour passer d'un mot à l'autre (distance de Levenshtein). */
function distance(a, b) {
  let avant = Array.from({ length: b.length + 1 }, (_, j) => j)
  for (let i = 1; i <= a.length; i++) {
    const ligne = [i]
    for (let j = 1; j <= b.length; j++) ligne[j] = Math.min(avant[j] + 1, ligne[j - 1] + 1, avant[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1))
    avant = ligne
  }
  return avant[b.length]
}

export const memeMot = (a, b) => {
  if (a === b) return true
  const nombres = /^\d+$/.test(a) && /^\d+$/.test(b)
  // « 02 » et « 2 » : le même nombre (la transcription enlève parfois le zéro). Un nombre n'est jamais « presque » égal.
  if (nombres) return Number(a) === Number(b)
  if (/\d/.test(a) || /\d/.test(b)) return false
  // La transcription se trompe parfois d'une lettre (« Fini » entendu « Finis », « Agoè » entendu « Agwe ») :
  // une lettre d'écart à partir de 4 lettres, deux à partir de 8.
  const court = Math.min(a.length, b.length)
  if (court < 4) return false
  return distance(a, b) <= (court >= 8 ? 2 : 1)
}

/**
 * Les mots de la voix prêts à chercher : un mot transcrit qui contient plusieurs nombres (« 72-24-02-31 ») est coupé en
 * autant de mots, avec des temps répartis.
 * @param {{ mot: string, debut: number, fin: number }[]} mots
 */
export function motsCherchables(mots = []) {
  const sortie = []
  for (const m of mots) {
    const parties = String(m.mot).split(/[\s\-–./]+/).filter(Boolean)
    if (parties.length > 1 && parties.every((p) => /^\+?\d+[.,!?]?$/.test(p))) {
      const pas = (m.fin - m.debut) / parties.length
      parties.forEach((p, i) => sortie.push({ mot: p, debut: m.debut + pas * i, fin: m.debut + pas * (i + 1) }))
    } else {
      sortie.push(m)
    }
  }
  return sortie.map((m) => ({ ...m, cle: normaliser(m.mot) })).filter((m) => m.cle)
}

/** L'indice du premier mot de « phrase » dans les mots, à partir de « apres » ; -1 si elle n'y est pas. */
export function trouverPhrase(mots, phrase, apres = 0) {
  const cherche = String(phrase ?? '').split(/\s+/).map(normaliser).filter(Boolean)
  if (!cherche.length) return -1
  for (let i = Math.max(0, apres); i <= mots.length - cherche.length; i++) {
    if (cherche.every((c, k) => memeMot(mots[i + k].cle, c))) return i
  }
  return -1
}

const arrondi = (n) => Math.round(n * 1000) / 1000

/**
 * Le plan de montage avec ses temps : « debut » et « duree » de chaque plan (en secondes de vidéo), et le temps de
 * chaque élément calé sur un mot (relatif au début du plan). Les mots introuvables sont rendus dans « erreurs ».
 * @param {object} plan le plan nettoyé (schema.js)
 * @param {{ mot: string, debut: number, fin: number }[]} motsVoix les mots de la voix, avec leurs temps
 * @param {{ decalage?: number }} options le temps où la voix commence dans la vidéo
 */
export function calerSurLaVoix(plan, motsVoix, { decalage = 0 } = {}) {
  const mots = motsCherchables(motsVoix).map((m) => ({ ...m, debut: m.debut + decalage, fin: m.fin + decalage }))
  const erreurs = []
  const finVoix = mots.length ? mots[mots.length - 1].fin : 0
  let apres = 0
  const debuts = []
  const indices = []
  plan.plans.forEach((p, i) => {
    const k = trouverPhrase(mots, p.mot, apres)
    if (k < 0) {
      erreurs.push(`plan ${i + 1} (${p.type}) : « ${p.mot} » introuvable dans la voix`)
      debuts.push(null)
      indices.push(apres)
      return
    }
    debuts.push(i === 0 ? 0 : Math.max(0, mots[k].debut - AVANCE))
    indices.push(k)
    apres = k + 1
  })
  if (erreurs.length) return { plan: null, erreurs, duree: 0 }
  const duree = arrondi(Math.max(finVoix + TENUE_FIN, debuts[debuts.length - 1] + TENUE_FIN))
  const plans = plan.plans.map((p, i) => {
    const debut = debuts[i]
    const fin = i + 1 < debuts.length ? debuts[i + 1] : duree
    const temps = {}
    // Les éléments calés sur un mot, cherchés à partir du mot du plan (jamais avant).
    // Chaque élément est cherché après le précédent (« 91 11 11 11 » : chaque « 11 » est le suivant).
    let suivant = indices[i]
    const caler = (cle, phrase) => {
      if (!phrase) return
      const k = trouverPhrase(mots, phrase, suivant)
      if (k < 0) erreurs.push(`plan ${i + 1} (${p.type}) : « ${phrase} » introuvable dans la voix`)
      else {
        temps[cle] = arrondi(Math.max(0, mots[k].debut - debut))
        suivant = k + 1
      }
    }
    ;(p.elements ?? []).forEach((e, n) => caler(`element${n}`, e.mot))
    ;(p.groupes ?? []).forEach((g, n) => caler(`groupe${n}`, g.mot))
    ;(p.messages ?? []).forEach((x, n) => caler(`message${n}`, x.mot))
    return { ...p, debut: arrondi(debut), duree: arrondi(fin - debut), temps }
  })
  return { plan: erreurs.length ? null : { ...plan, duree, plans }, erreurs, duree }
}

/**
 * Le rythme du style « Dynamique » : jamais plus de 2,5 secondes sur le même plan (l'accroche, le numéro géant et la
 * carte finale peuvent durer plus), jamais un plan trop court pour être lu.
 * @returns {string[]} les problèmes trouvés (vide si le rythme est bon)
 */
export function controlerRythme(planCale, rythme = RYTHME) {
  const problemes = []
  planCale.plans.forEach((p, i) => {
    if (p.type === 'carte') return
    const max = rythme[p.type] ?? rythme.max
    // Une case qui se coche, un groupe de chiffres ou une bulle qui arrive : c'est un changement à l'écran.
    const moments = [0, ...Object.values(p.temps ?? {}), p.duree].sort((x, y) => x - y)
    const attente = Math.max(...moments.slice(1).map((m, k) => m - moments[k]))
    if (attente > max + 0.05) problemes.push(`plan ${i + 1} (${p.type}) : ${attente.toFixed(2)} s sans changement (plus de ${max} s)`)
    if (p.duree < rythme.min - 0.05) problemes.push(`plan ${i + 1} (${p.type}) : ${p.duree.toFixed(2)} s, trop court pour être lu`)
  })
  return problemes
}
