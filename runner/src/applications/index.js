// Sites avec base de données de Kekeli (« applications » dans le code) : le socle, en Laravel ou en Next.js.
// Les fichiers fixes de chaque pile sont dans socles/<pile>/ ; le reste est fabriqué à partir de la
// description de l'application (modeles.json) par laravel.js ou nextjs.js.
import { readFileSync, readdirSync, statSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { fichiersLaravel } from './laravel.js'
import { fichiersNextjs } from './nextjs.js'

const ICI = path.dirname(fileURLToPath(import.meta.url))

export const PILES = {
  laravel: { nom: 'Laravel (PHP) et MySQL', fabrique: fichiersLaravel },
  nextjs: { nom: 'Next.js (Node) et PostgreSQL', fabrique: fichiersNextjs },
}

/** Toutes les descriptions de sites avec base de données (aujourd’hui : le socle seul). */
export function modelesApplications() {
  return JSON.parse(readFileSync(path.join(ICI, 'modeles.json'), 'utf8')).modeles
}

export function modeleApplication(slug) {
  const modele = modelesApplications().find((m) => m.slug === slug)
  if (!modele) throw new Error(`Modèle d'application inconnu : ${slug}.`)
  return modele
}

/** Fichiers fixes d'une pile (chemin relatif → contenu en octets). */
function fichiersFixes(pile) {
  const racine = path.join(ICI, 'socles', pile)
  const fichiers = {}
  const parcourir = (dossier) => {
    for (const nom of readdirSync(dossier)) {
      const complet = path.join(dossier, nom)
      if (statSync(complet).isDirectory()) parcourir(complet)
      else fichiers[path.relative(racine, complet).split(path.sep).join('/')] = readFileSync(complet)
    }
  }
  parcourir(racine)
  return fichiers
}

/**
 * Tous les fichiers d'une application : fichiers fixes de la pile, puis fichiers fabriqués à partir
 * de la description (qui remplacent les fixes de même nom). `modele` : un slug ou une description.
 */
export function fichiersApplication(pile, modele) {
  if (!PILES[pile]) throw new Error(`Technologie inconnue : ${pile}.`)
  const description = typeof modele === 'string' ? modeleApplication(modele) : modele
  const fichiers = { ...fichiersFixes(pile), ...PILES[pile].fabrique(description) }
  // Le fichier des versions exactes (Node) porte le nom de l'application.
  if (fichiers['package-lock.json']) {
    const verrou = JSON.parse(fichiers['package-lock.json'].toString())
    verrou.name = description.slug
    if (verrou.packages?.['']) verrou.packages[''].name = description.slug
    fichiers['package-lock.json'] = JSON.stringify(verrou, null, 2) + '\n'
  }
  return fichiers
}
