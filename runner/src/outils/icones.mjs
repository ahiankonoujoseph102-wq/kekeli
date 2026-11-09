// Outil d'icônes de Kekeli : la bibliothèque Lucide (licence ISC, usage commercial permis), copiée dans
// chaque ordinateur virtuel. L'agent l'utilise au lieu des émojis, pour des icônes cohérentes sur tout le site.
//
//   node /home/user/.kekeli/icones.mjs chercher "phone map clock"   → noms d'icônes qui correspondent
//   node /home/user/.kekeli/icones.mjs svg phone map-pin            → code SVG prêt à coller dans la page
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const DOSSIER = join(dirname(fileURLToPath(import.meta.url)), 'icones')

let bibliotheque = null
export function charger(dossier = DOSSIER) {
  bibliotheque ??= {
    icones: JSON.parse(readFileSync(join(dossier, 'lucide.json'), 'utf8')),
    motsCles: JSON.parse(readFileSync(join(dossier, 'mots-cles.json'), 'utf8')),
  }
  return bibliotheque
}

/** Noms des icônes dont le nom ou un mot-clé contient l'un des mots cherchés (les meilleures d'abord). */
export function chercher(texte, { icones, motsCles } = charger(), limite = 30) {
  const mots = String(texte ?? '').toLowerCase().split(/[\s,]+/).filter(Boolean)
  const scores = []
  for (const nom of Object.keys(icones)) {
    let score = 0
    for (const mot of mots) {
      if (nom === mot) score += 5
      else if (nom.split('-').includes(mot)) score += 3
      else if (nom.includes(mot)) score += 1
      if ((motsCles[nom] ?? []).some((cle) => cle.toLowerCase() === mot)) score += 2
    }
    if (score) scores.push([score, nom])
  }
  return scores.sort((a, b) => b[0] - a[0] || a[1].localeCompare(b[1])).slice(0, limite).map(([, nom]) => nom)
}

/** Code SVG d'une icône, à la taille du texte et de la couleur du texte (classe « icone » du modèle de Kekeli). */
export function svg(nom, { icones } = charger()) {
  const contenu = icones[nom]
  if (!contenu) return null
  return `<svg class="icone" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${contenu}</svg>`
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const [action, ...reste] = process.argv.slice(2)
  if (action === 'chercher' && reste.length) {
    console.log('Icônes de la bibliothèque Lucide (licence ISC), pour chaque mot cherché :')
    for (const mot of reste.join(' ').split(/[\s,]+/).filter(Boolean)) {
      const noms = chercher(mot, charger(), 8)
      console.log(`- ${mot} : ${noms.length ? noms.join(', ') : 'aucune icône, essaie un autre mot en anglais'}`)
    }
  } else if (action === 'svg' && reste.length) {
    for (const nom of reste) {
      const code = svg(nom)
      console.log(code ? `<!-- ${nom} -->\n${code}\n` : `Icône inconnue : « ${nom} ». Cherche son nom avec : node icones.mjs chercher "mot en anglais"`)
    }
  } else {
    console.log('Utilisation :\n  node icones.mjs chercher "mots en anglais"\n  node icones.mjs svg nom-1 nom-2')
    process.exit(1)
  }
}
