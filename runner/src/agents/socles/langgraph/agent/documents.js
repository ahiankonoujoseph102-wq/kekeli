// Documents de l'entreprise (dossier documents/ : questions fréquentes, catalogue, tarifs…). Ils sont découpés en
// passages ; pour chaque question, les passages les plus proches sont donnés au modèle. Simple, gratuit, sans base
// de vecteurs : suffisant pour quelques dizaines de pages.
import { readdirSync, readFileSync } from 'node:fs'
import path from 'node:path'

const MOTS_VIDES = new Set('le la les un une des de du et ou a à au aux en dans pour par sur avec est sont ce cette ces que qui quoi quel quelle quels quelles comment combien je tu il elle nous vous ils elles mon ma mes ton ta tes son sa ses votre vos notre nos y ne pas plus'.split(' '))

/** Mots utiles d'un texte, en minuscules et sans accents. */
export function mots(texte) {
  return String(texte)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .split(/[^a-z0-9]+/)
    .filter((mot) => mot.length > 1 && !MOTS_VIDES.has(mot))
}

/** Lit les documents et les découpe en passages (un titre et son texte, ou des paragraphes). */
export function chargerDocuments(dossier) {
  let fichiers = []
  try {
    fichiers = readdirSync(dossier).filter((nom) => /\.(md|txt)$/i.test(nom)).sort()
  } catch {
    return []
  }
  const passages = []
  for (const nom of fichiers) {
    const texte = readFileSync(path.join(dossier, nom), 'utf8')
    for (const bloc of texte.split(/\n(?=#{1,3} )|\n{2,}/)) {
      const propre = bloc.trim()
      if (propre) passages.push({ source: nom, texte: propre.slice(0, 2000), mots: new Set(mots(propre)) })
    }
  }
  return passages
}

/** Les passages les plus proches de la question (au plus `nombre`). */
export function passagesUtiles(passages, question, nombre = 4) {
  const motsQuestion = mots(question)
  return passages
    .map((passage) => ({ passage, score: motsQuestion.filter((mot) => passage.mots.has(mot)).length }))
    .filter(({ score }) => score > 0)
    .sort((a, b) => b.score - a.score)
    .slice(0, nombre)
    .map(({ passage }) => passage)
}
