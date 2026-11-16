// Les sous-titres « mot par mot » des vidéos motion design (comme les montages de Lomé IA School) : des blocs courts
// (4 mots au plus), chaque mot à son instant exact dans la voix, les mots-clés en couleur. Le texte vient de la
// transcription (ce qui est vraiment dit), corrigé par le texte lu par la voix ou par les mots du plan (nom de la
// marque, mots-clés) : « Agoè » entendu « Agwe » est écrit « Agoè ». Rien n'est inventé : un mot entendu reste écrit.
import { memeMot, normaliser } from './temps.js'

export const SOUS_TITRES = {
  /** Au plus 4 mots par bloc. */
  motsMax: 4,
  /** Au plus 24 caractères par bloc (une seule ligne lisible sur un téléphone). */
  caracteresMax: 24,
  /** Une pause plus longue coupe le bloc. */
  pause: 0.35,
  /** Un bloc reste au moins une demi-seconde à l'écran (s'il a la place). */
  tenueMin: 0.5,
  /** Un bloc reste encore un quart de seconde après son dernier mot (s'il a la place). */
  apres: 0.25,
}

const arrondi = (n) => Math.round(n * 1000) / 1000
const finDePhrase = /[.!?…]$/
const virgule = /[,;:]$/

/** Les mots d'un texte, avec leur ponctuation collée (« sales ? » reste un seul mot). */
function motsDuTexte(texte) {
  return String(texte ?? '')
    .replace(/\s+([?!:;»%])/g, ' $1')
    .replace(/«\s+/g, '« ')
    .split(/[ \t\r\n]+/)
    .filter((m) => normaliser(m))
}

/**
 * Les noms propres du plan (nom de la marque, slogan, mots avec une majuscule au milieu d'un texte, comme « à Agoè ») :
 * ils donnent l'orthographe des mots que la transcription écrit mal. Jamais un mot ordinaire (« livre » ne devient
 * jamais « livré » parce que le plan l'écrit ainsi) : ce que la voix dit reste écrit.
 */
export function lexiqueDuPlan(plan) {
  const propres = []
  const m = plan?.marque ?? {}
  propres.push(...motsDuTexte(m.nom))
  for (const texte of [m.slogan, ...(plan?.plans ?? []).flatMap((p) => [p.texte, p.surtitre, p.sous_texte, p.titre, ...(p.cles ?? []), ...(p.elements ?? []).map((e) => e.texte)])]) {
    // Une majuscule au milieu d'un texte : un nom propre (le premier mot a sa majuscule de début de phrase).
    motsDuTexte(texte).forEach((mot, i) => {
      if (i > 0 && /^[«"“(]*\p{Lu}/u.test(mot)) propres.push(mot)
    })
  }
  const lexique = new Map()
  for (const mot of propres) {
    const propre = mot.replace(/^[«"“(]+|[»"”).,;:!?…\u00a0]+$/g, '')
    const cle = normaliser(propre)
    if (cle.length >= 4 && !/\d/.test(cle) && !lexique.has(cle)) lexique.set(cle, propre)
  }
  return [...lexique.entries()]
}

/** Les mots-clés du plan (normalisés), mis en couleur dans les sous-titres ; les nombres le sont toujours. */
export function clesDuPlan(plan) {
  const cles = new Set()
  for (const p of plan?.plans ?? []) for (const k of p.cles ?? []) for (const mot of motsDuTexte(k)) cles.add(normaliser(mot))
  for (const mot of motsDuTexte(plan?.marque?.nom)) cles.add(normaliser(mot))
  cles.delete('')
  return cles
}

/** La ponctuation de fin d'un mot transcrit (« sales ? », « effort. »), à garder quand l'orthographe est corrigée. */
const ponctuationDe = (mot) => (String(mot).match(/[\s ]*[.,;:!?…]+$/) ?? [''])[0]

/**
 * Les mots de la voix avec la bonne orthographe : chaque mot transcrit est cherché dans le texte lu (à partir du mot
 * précédent, 4 mots d'avance au plus), sinon dans le lexique du plan. Le temps reste celui de la voix.
 * @param {{ mot: string, debut: number, fin: number }[]} mots
 * @param {{ texte?: string, lexique?: [string, string][] }} reference
 */
export function corrigerOrthographe(mots, { texte = '', lexique = [] } = {}) {
  const lus = motsDuTexte(texte)
  let r = 0
  return mots.map((m) => {
    const cle = normaliser(m.mot)
    if (!cle) return { ...m }
    for (let k = r; k < Math.min(lus.length, r + 5); k++) {
      if (memeMot(normaliser(lus[k]), cle)) {
        r = k + 1
        return { ...m, mot: lus[k] }
      }
    }
    const trouve = cle.length >= 4 && !/\d/.test(cle) ? lexique.find(([l]) => memeMot(l, cle)) : null
    return trouve ? { ...m, mot: trouve[1] + ponctuationDe(m.mot) } : { ...m }
  })
}

/** Les petits mots qui ne terminent jamais un bloc (« ton | linge » se lit mal) : articles, prépositions, liaisons. */
const MOTS_LIES = new Set(['le', 'la', 'les', 'l', 'un', 'une', 'des', 'du', 'de', 'd', 'au', 'aux', 'a', 'en', 'et', 'ou', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'nos', 'votre', 'vos', 'leur', 'leurs', 'ce', 'cet', 'cette', 'ces', 'pour', 'avec', 'sans', 'sur', 'dans', 'par', 'chez', 'qui', 'que', 'tu', 'je', 'il', 'elle', 'on', 'nous', 'vous', 'ils', 'plus', 'tres', 'tout', 'tous'])
const estNombre = (mot) => /^\+?\d/.test(normaliser(mot)) || /^\+\d/.test(String(mot))

/**
 * Coupe une phrase (une suite de mots sans ponctuation forte ni pause) en blocs : au plus 4 mots et 24 caractères,
 * en choisissant les coupures qui se lisent le mieux (jamais après un petit mot, jamais au milieu d'un numéro, pas de
 * mot seul si on peut l'éviter), avec le moins de blocs possible.
 */
function couperPhrase(mots, reglages) {
  const n = mots.length
  const cout = new Array(n + 1).fill(Infinity)
  const avant = new Array(n + 1).fill(-1)
  cout[0] = 0
  for (let fin = 1; fin <= n; fin++) {
    for (let debut = Math.max(0, fin - reglages.motsMax); debut < fin; debut++) {
      const bloc = mots.slice(debut, fin)
      if (bloc.map((m) => m.texte).join(' ').length > reglages.caracteresMax && bloc.length > 1) continue
      let c = cout[debut] + 1
      if (fin < n && MOTS_LIES.has(normaliser(mots[fin - 1].texte))) c += 3
      if (fin < n && estNombre(mots[fin - 1].texte) && estNombre(mots[fin].texte)) c += 4
      if (bloc.length === 1 && n > 1) c += 1.5
      // Couper juste avant un petit mot (« 500 clients | à Agoè ») se lit le mieux.
      if (fin < n && MOTS_LIES.has(normaliser(mots[fin].texte))) c -= 0.3
      if (c < cout[fin]) {
        cout[fin] = c
        avant[fin] = debut
      }
    }
  }
  const blocs = []
  for (let fin = n; fin > 0; fin = avant[fin]) blocs.unshift(mots.slice(avant[fin], fin))
  return blocs
}

/**
 * Les blocs de sous-titres : 4 mots et 24 caractères au plus, coupés à la fin d'une phrase, après une virgule, à une
 * pause, puis aux meilleures coupures ; chaque mot garde son temps (en secondes de vidéo) et sait s'il est un mot-clé.
 * @param {{ mot: string, debut: number, fin: number }[]} mots les mots de la voix (orthographe déjà corrigée)
 * @param {{ decalage?: number, cles?: Set<string>, reglages?: typeof SOUS_TITRES }} options
 * @returns {{ debut: number, fin: number, mots: { texte: string, debut: number, fin: number, cle: boolean }[] }[]}
 */
export function blocsSousTitres(mots, { decalage = 0, cles = new Set(), reglages = SOUS_TITRES } = {}) {
  // 1. Les phrases : coupées à la ponctuation forte, à une virgule et aux pauses de la voix.
  const phrases = []
  let courante = []
  for (const m of mots) {
    const texte = String(m.mot).trim().replace(/\s+([?!:;»%])/g, '\u00a0$1')
    if (!normaliser(texte)) {
      // Une ponctuation transcrite seule (« ? ») se colle au mot d'avant et peut finir la phrase.
      const precedent = courante[courante.length - 1] ?? phrases[phrases.length - 1]?.at(-1)
      if (precedent && /^[.!?…,;:]+$/.test(texte)) {
        precedent.texte += /^[?!:;]/.test(texte) ? `\u00a0${texte}` : texte
        if (courante.length && (finDePhrase.test(texte) || virgule.test(texte))) {
          phrases.push(courante)
          courante = []
        }
      }
      continue
    }
    const dernier = courante[courante.length - 1]
    if (dernier && m.debut - dernier.finVoix > reglages.pause) {
      phrases.push(courante)
      courante = []
    }
    const cle = normaliser(texte)
    courante.push({ texte, debut: arrondi(m.debut + decalage), fin: arrondi(m.fin + decalage), finVoix: m.fin, cle: cles.has(cle) || /^\d/.test(cle) })
    if (finDePhrase.test(texte) || virgule.test(texte)) {
      phrases.push(courante)
      courante = []
    }
  }
  if (courante.length) phrases.push(courante)
  // 2. Chaque phrase en blocs lisibles.
  const blocs = phrases.flatMap((p) => couperPhrase(p, reglages))
  return blocs.map((b, i) => {
    const debut = b[0].debut
    const suivant = blocs[i + 1]?.[0].debut ?? Infinity
    const fin = Math.min(suivant, Math.max(b[b.length - 1].fin + reglages.apres, debut + reglages.tenueMin))
    return { debut, fin: arrondi(fin), mots: b.map(({ finVoix, ...x }) => x) }
  })
}

/**
 * Les sous-titres d'une vidéo motion design, de la transcription aux blocs : orthographe corrigée par le texte lu
 * (voix de Kekeli) ou par le plan seul (voix du client), mots-clés du plan en couleur.
 */
export function sousTitresDuMontage(plan, motsVoix, { decalage = 0, texte = '' } = {}) {
  const corriges = corrigerOrthographe(motsVoix, { texte, lexique: lexiqueDuPlan(plan) })
  return blocsSousTitres(corriges, { decalage, cles: clesDuPlan(plan) })
}

/** Le fichier SRT des blocs (livré avec la vidéo pour les plateformes qui affichent leurs propres sous-titres). */
export function lignesSrt(blocs) {
  return blocs.map((b) => ({ debut: b.debut, fin: b.fin, texte: b.mots.map((m) => m.texte).join(' ').replace(/ /g, ' ') }))
}
