// Kekeli Motion : fabrique le moteur JavaScript à partir des sources TypeScript (runner/src/motion/moteur), sans
// aucune dépendance : les types sont retirés par Node (module.stripTypeScriptTypes), puis chaque module est enveloppé
// dans sa propre fonction (aucun nom ne se mélange) et relié aux autres par ses imports.
//
// Deux sorties, rangées dans le dépôt par « npm run motion:construire » (un test vérifie qu'elles sont à jour) :
//  - construit/kekeli-motion.js : pour le navigateur (aperçu, sites des clients), qui définit window.KekeliMotion ;
//  - construit/kekeli-motion.mjs : pour le Runner (relire une scène, dessiner l'image fixe d'une version).

import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const ICI = path.dirname(fileURLToPath(import.meta.url))
export const DOSSIER_MOTEUR = path.join(ICI, 'moteur')
export const DOSSIER_CONSTRUIT = path.join(ICI, 'construit')

/** Les modules, dans l'ordre où chacun ne dépend que des précédents. */
export const MODULES = ['lib/ink', 'lib/gait', 'lib/motion', 'lib/daynight', 'kekeli/types', 'kekeli/normaliser', 'kekeli/personnage', 'kekeli/decor', 'kekeli/scene', 'kekeli/index']

const ENTETE = `/*! Kekeli Motion. Moteur d'animations en SVG de Kekeli (Lomé IA School).
 * Contient du code repris de line-art-animation (https://github.com/JDhruv14/line-art-animation),
 * licence MIT, Copyright (c) 2026 JDhruv14 : voir LICENCES.md. Fichier fabriqué, ne pas modifier à la main :
 * les sources TypeScript sont dans le dossier src/. */`

/** Retire les types d'un fichier TypeScript (seulement des types « effaçables », comme Node l'exige). */
async function sansTypes(code) {
  const { stripTypeScriptTypes } = await import('node:module')
  const avant = process.emitWarning
  // L'avertissement « fonction expérimentale » ne concerne que la fabrication, jamais le moteur fabriqué.
  process.emitWarning = () => {}
  try {
    return stripTypeScriptTypes(code)
  } finally {
    process.emitWarning = avant
  }
}

/** Transforme un module en corps de fonction : imports reliés, exports rassemblés. */
export function envelopper(nom, code) {
  const exports = new Set()
  let corps = code.replace(/^\s*import\s*\{([^}]*)\}\s*from\s*'([^']+)';?[ \t]*$/gm, (_tout, noms, source) => {
    const cible = path.posix.normalize(path.posix.join(path.posix.dirname(nom), source.replace(/\.ts$/, '')))
    if (!MODULES.includes(cible)) throw new Error(`Import inconnu dans ${nom} : ${source}`)
    const liste = noms.split(',').map((n) => n.trim()).filter(Boolean).map((n) => n.replace(/\s+as\s+/, ': '))
    return liste.length ? `const { ${liste.join(', ')} } = __modules[${JSON.stringify(cible)}];` : ''
  })
  if (/^\s*import\s/m.test(corps)) throw new Error(`Import non reconnu dans ${nom} (un import par ligne, entre accolades)`)
  corps = corps.replace(/^export\s+\{([^}]*)\};?[ \t]*$/gm, (_tout, noms) => {
    for (const n of noms.split(',').map((x) => x.trim()).filter(Boolean)) exports.add(n)
    return ''
  })
  corps = corps.replace(/^export\s+((?:async\s+)?function\*?|const|let|class)\s+([A-Za-z_$][\w$]*)/gm, (_tout, mot, n) => {
    exports.add(n)
    return `${mot} ${n}`
  })
  if (/^export\s/m.test(corps)) throw new Error(`Export non reconnu dans ${nom}`)
  return `__modules[${JSON.stringify(nom)}] = (() => {\n${corps.replace(/\s+$/, '')}\nreturn { ${[...exports].join(', ')} };\n})();`
}

/** Le moteur pour le navigateur et pour le Runner, à partir des sources. */
export async function construireMoteur() {
  const morceaux = []
  for (const nom of MODULES) {
    const source = await readFile(path.join(DOSSIER_MOTEUR, `${nom}.ts`), 'utf8')
    morceaux.push(envelopper(nom, await sansTypes(source)))
  }
  // Les lignes vides laissées par les types retirés ne servent à rien dans le fichier fabriqué.
  const modules = morceaux.join('\n').split('\n').map((l) => l.replace(/\s+$/, '')).filter((l) => l !== '').join('\n')
  const navigateur = `${ENTETE}\n(() => {\n'use strict';\nconst __modules = {};\n${modules}\n})();\n`
  const runner = `${ENTETE}\nconst __modules = {};\n${modules}\nexport const { monter, normaliser, monterTout } = __modules["kekeli/index"];\nexport const { TYPES_ELEMENTS, PERSONNAGES_MAX, ELEMENTS_MAX } = __modules["kekeli/normaliser"];\n`
  return { navigateur, runner }
}
