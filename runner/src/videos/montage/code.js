// Le code Revideo (licence MIT) d'une vidéo « motion design » : il appelle seulement la bibliothèque fixe de Kekeli
// (socle/src/montage/) avec le plan de montage calé sur la voix. Chaque valeur est recopiée comme une donnée
// (JSON.stringify), jamais comme du code : le modèle ne peut rien exécuter.
import { FORMATS_VIDEO, IMAGES_PAR_SECONDE } from '../code.js'
import { iconesDuPlan } from './icones.js'

/** Le fichier « src/scenes/video.tsx » d'une vidéo motion design. */
export function codeMontage(planCale, { date = '' } = {}) {
  const format = FORMATS_VIDEO[planCale.format] ?? FORMATS_VIDEO.vertical
  const donnees = {
    largeur: format.largeur,
    hauteur: format.hauteur,
    style: planCale.style,
    couleurs: planCale.couleurs ?? {},
    marque: planCale.marque ?? {},
    duree: planCale.duree,
    plans: planCale.plans,
    icones: iconesDuPlan(planCale),
  }
  return [
    `// Vidéo écrite par Kekeli${date ? ` le ${date}` : ''} : ${planCale.plans.length} plans, ${String(planCale.duree).replace('.', ',')} secondes, style « ${planCale.style} », ${format.largeur} × ${format.hauteur}.`,
    '// Moteur : Revideo (licence MIT). Tout est dessiné par la bibliothèque de Kekeli à partir de ces données.',
    "import { makeScene2D } from '@revideo/2d';",
    "import { monter } from '../montage/montage';",
    '',
    "export default makeScene2D('video', function* (vue) {",
    `  yield* monter(vue, ${JSON.stringify(donnees)});`,
    '});',
    '',
  ].join('\n')
}

/** Le fichier « src/projet.ts » : la taille et la cadence de la vidéo. */
export function codeProjetMontage(planCale) {
  const format = FORMATS_VIDEO[planCale.format] ?? FORMATS_VIDEO.vertical
  return [
    "import { makeProject } from '@revideo/core';",
    "import video from './scenes/video?scene';",
    '',
    'export default makeProject({',
    '  scenes: [video],',
    `  settings: { shared: { size: { x: ${format.largeur}, y: ${format.hauteur} }, background: '#000000' }, rendering: { fps: ${IMAGES_PAR_SECONDE} } },`,
    '});',
    '',
  ].join('\n')
}
