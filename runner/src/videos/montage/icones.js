// Les icônes des vidéos : la bibliothèque Lucide (licence ISC) déjà rangée dans Kekeli (runner/src/modeles/icones),
// convertie en formes simples (chemins, cercles, rectangles, lignes) que la bibliothèque de montage dessine sans
// Internet. Jamais le logo d'une marque : Lucide n'a que des icônes génériques.
import { readFileSync } from 'node:fs'

let lucide = null
const toutes = () => (lucide ??= JSON.parse(readFileSync(new URL('../../modeles/icones/lucide.json', import.meta.url), 'utf8')))

const attributs = (balise) => Object.fromEntries([...balise.matchAll(/([\w-]+)="([^"]*)"/g)].map((m) => [m[1], m[2]]))
const n = (v) => Number.parseFloat(v) || 0

/** Les formes d'une icône Lucide (dessinée dans un carré de 24), ou null si elle n'existe pas. */
export function formesIcone(nom) {
  const svg = toutes()[String(nom ?? '')]
  if (!svg) return null
  const formes = []
  for (const [, type, reste] of svg.matchAll(/<(\w+)\s([^>]*)\/?>/g)) {
    const a = attributs(reste)
    if (type === 'path' && a.d) formes.push({ forme: 'chemin', d: a.d })
    else if (type === 'circle') formes.push({ forme: 'cercle', x: n(a.cx), y: n(a.cy), r: n(a.r) })
    else if (type === 'ellipse') formes.push({ forme: 'ellipse', x: n(a.cx), y: n(a.cy), rx: n(a.rx), ry: n(a.ry) })
    else if (type === 'rect') formes.push({ forme: 'rectangle', x: n(a.x), y: n(a.y), l: n(a.width), h: n(a.height), r: n(a.rx) })
    else if (type === 'line') formes.push({ forme: 'ligne', points: [[n(a.x1), n(a.y1)], [n(a.x2), n(a.y2)]] })
    else if (type === 'polyline' || type === 'polygon') {
      const v = String(a.points ?? '').trim().split(/[\s,]+/).map(n)
      const points = []
      for (let i = 0; i + 1 < v.length; i += 2) points.push([v[i], v[i + 1]])
      formes.push({ forme: 'ligne', points, fermee: type === 'polygon' })
    }
  }
  return formes.length ? formes : null
}

/** Toutes les icônes nommées dans un plan de montage (champ « icone » des plans, des éléments et des articles). */
export function iconesDuPlan(plan) {
  // Les icônes que la bibliothèque dessine toujours (avant et après, boutons de contact, étoiles).
  const noms = new Set(['x', 'check', 'message-circle', 'sparkles'])
  for (const p of plan.plans ?? []) {
    if (p.icone) noms.add(p.icone)
    for (const e of [...(p.elements ?? []), ...(p.articles ?? [])]) if (e.icone) noms.add(e.icone)
  }
  const sortie = {}
  for (const nom of noms) {
    const f = formesIcone(nom)
    if (f) sortie[nom] = f
  }
  return sortie
}
