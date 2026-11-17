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
    // Un élément n'est cherché que dans les mots de SON plan : un mot répété plus loin dans la voix (« 500 », « 11 »)
    // ne doit jamais garder le plan à l'écran après le début du suivant. Trouvé trop tard : il prend son temps habituel.
    const limite = i + 1 < indices.length ? indices[i + 1] : mots.length
    const caler = (cle, phrase, facultatif = false) => {
      if (!phrase) return
      const k = trouverPhrase(mots, phrase, suivant)
      if (k < 0 && facultatif) return
      if (k >= limite) return
      if (k < 0) erreurs.push(`plan ${i + 1} (${p.type}) : « ${phrase} » introuvable dans la voix`)
      else {
        temps[cle] = arrondi(Math.max(0, mots[k].debut - debut))
        suivant = k + 1
      }
    }
    ;(p.elements ?? []).forEach((e, n) => caler(`element${n}`, e.mot))
    ;(p.groupes ?? []).forEach((g, n) => caler(`groupe${n}`, g.mot))
    ;(p.messages ?? []).forEach((x, n) => caler(`message${n}`, x.mot))
    // Le prix tombe quand la voix le dit ; sinon, un peu après l'arrivée de la carte (jamais une erreur).
    if (p.mot_prix) caler('prix', p.mot_prix, true)
    return { ...p, debut: arrondi(debut), duree: arrondi(fin - debut), temps }
  })
  return { plan: erreurs.length ? null : { ...plan, duree, plans }, erreurs, duree }
}

/**
 * Un plan trop court pour être lu (moins de 0,6 seconde, quand la voix va vite) est fondu avec son voisin : un plan
 * « texte » simple disparaît au profit du plan d'avant ; sinon, c'est le plan « texte » d'avant qui lui laisse sa place.
 * L'accroche et la carte finale ne sont jamais retirées.
 */
export function retirerPlansTropCourts(planCale, min = RYTHME.min) {
  const plans = planCale.plans.map((p) => ({ ...p, temps: { ...(p.temps ?? {}) } }))
  const simple = (p) => p.type === 'texte' && !p.surtitre && !p.sous_texte && !p.icone
  for (let i = 1; i < plans.length - 1; ) {
    const p = plans[i]
    if (p.duree >= min - 0.05) {
      i++
      continue
    }
    const avant = plans[i - 1]
    if (simple(p)) {
      avant.duree = arrondi(avant.duree + p.duree)
      plans.splice(i, 1)
    } else if (i > 1 && avant.type === 'texte') {
      const decalage = avant.duree
      for (const cle of Object.keys(p.temps)) p.temps[cle] = arrondi(p.temps[cle] + decalage)
      p.debut = avant.debut
      p.duree = arrondi(p.duree + decalage)
      plans.splice(i - 1, 1)
    } else i++
  }
  return { ...planCale, plans }
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

/** La ressemblance de deux mots normalisés (1 : identiques ; 0 : rien de commun). */
function ressemblance(a, b) {
  if (memeMot(a, b)) return 1
  if (!a || !b || /\d/.test(a) || /\d/.test(b)) return 0
  return Math.max(0, 1 - distanceMots(a, b) / Math.max(a.length, b.length))
}

function distanceMots(a, b) {
  let avant = Array.from({ length: b.length + 1 }, (_, j) => j)
  for (let i = 1; i <= a.length; i++) {
    const ligne = [i]
    for (let j = 1; j <= b.length; j++) ligne[j] = Math.min(avant[j] + 1, ligne[j - 1] + 1, avant[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1))
    avant = ligne
  }
  return avant[b.length]
}

/**
 * Le texte connu de la voix (écrit par Kekeli et lu par la voix off) aligné sur sa transcription : chaque mot du texte
 * reçoit le temps du mot entendu en face de lui (alignement de séquences qui tolère les erreurs de la transcription :
 * « Des pagnes » entendu « Des panneaux », « Pagnes Ama » entendu « Panyama ») ; un mot sans vis-à-vis reçoit un temps
 * réparti entre ses voisins. Les mots rendus ont l'orthographe du texte : calage et sous-titres exacts.
 * @param {string} texte le texte lu par la voix
 * @param {{ mot: string, debut: number, fin: number }[]} entendus les mots de la transcription
 */
export function alignerSurLeTexte(texte, entendus) {
  const ecrits = String(texte ?? '').replace(/\s+([?!:;»%])/g, ' $1').split(/[ \t\r\n]+/).filter((m) => normaliser(m))
  const oreille = entendus.filter((m) => normaliser(m.mot))
  const n = ecrits.length
  const m = oreille.length
  if (!n || !m) return []
  const a = ecrits.map(normaliser)
  const b = oreille.map((x) => normaliser(x.mot))
  const TROU = 0.6
  // Programmation dynamique : coût de l'alignement de a[0..i[ avec b[0..j[.
  const cout = Array.from({ length: n + 1 }, () => new Float64Array(m + 1))
  const choix = Array.from({ length: n + 1 }, () => new Uint8Array(m + 1))
  for (let i = 1; i <= n; i++) { cout[i][0] = i * TROU; choix[i][0] = 1 }
  for (let j = 1; j <= m; j++) { cout[0][j] = j * TROU; choix[0][j] = 2 }
  for (let i = 1; i <= n; i++) {
    for (let j = 1; j <= m; j++) {
      const face = cout[i - 1][j - 1] + (1 - ressemblance(a[i - 1], b[j - 1])) * 1.2
      const sansEntendu = cout[i - 1][j] + TROU
      const sansEcrit = cout[i][j - 1] + TROU
      if (face <= sansEntendu && face <= sansEcrit) { cout[i][j] = face; choix[i][j] = 0 }
      else if (sansEntendu <= sansEcrit) { cout[i][j] = sansEntendu; choix[i][j] = 1 }
      else { cout[i][j] = sansEcrit; choix[i][j] = 2 }
    }
  }
  const temps = new Array(n).fill(null)
  for (let i = n, j = m; i > 0 || j > 0; ) {
    const c = i > 0 && j > 0 ? choix[i][j] : i > 0 ? 1 : 2
    if (c === 0) { temps[i - 1] = { debut: oreille[j - 1].debut, fin: oreille[j - 1].fin }; i--; j-- }
    else if (c === 1) i--
    else j--
  }
  // Les mots sans vis-à-vis : temps répartis entre le mot placé avant et le mot placé après.
  for (let i = 0; i < n; ) {
    if (temps[i]) { i++; continue }
    let k = i
    while (k < n && !temps[k]) k++
    const depart = i > 0 ? temps[i - 1].fin : oreille[0].debut
    const arrivee = k < n ? temps[k].debut : oreille[m - 1].fin
    const pas = Math.max(0, arrivee - depart) / (k - i)
    for (let x = i; x < k; x++) temps[x] = { debut: depart + pas * (x - i), fin: depart + pas * (x - i + 1) }
    i = k
  }
  return ecrits.map((mot, i) => ({ mot: mot.replace(/ /g, ' '), debut: arrondi(temps[i].debut), fin: arrondi(temps[i].fin) }))
}
