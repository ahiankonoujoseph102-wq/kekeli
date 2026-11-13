// Le code de la vidéo, écrit par Kekeli à partir du scénario : un projet Revideo (licence MIT) complet, scène par
// scène, qui appelle la bibliothèque de montage de Kekeli (socle/src/kekeli.tsx). Le modèle écrit le scénario (JSON),
// jamais le code : chaque valeur est recopiée ici comme une donnée (JSON.stringify), jamais comme du code.

export const FORMATS_VIDEO = {
  vertical: { largeur: 1080, hauteur: 1920, nom: 'vertical (9:16)' },
  carre: { largeur: 1080, hauteur: 1080, nom: 'carré (1:1)' },
  paysage: { largeur: 1920, hauteur: 1080, nom: 'horizontal (16:9)' },
}

export const IMAGES_PAR_SECONDE = 30

const secondes = (n) => `${String(Math.round(n * 10) / 10).replace('.', ',')} seconde${n >= 2 ? 's' : ''}`
const donnee = (valeur) => JSON.stringify(valeur)

/** Les propriétés d'une scène telles que la bibliothèque les attend (rien d'autre n'entre dans le code). */
function sceneDuCode(s) {
  return {
    duree: Math.round(s.duree * 100) / 100,
    ...(s.titre ? { titre: s.titre } : {}),
    ...(s.texte ? { texte: s.texte } : {}),
    ...(s.prix ? { prix: s.prix } : {}),
    media: s.media ? { fichier: s.media.fichier, type: s.media.type, largeur: s.media.largeur, hauteur: s.media.hauteur, ...(s.media.debut ? { debut: s.media.debut } : {}) } : null,
    animation: s.animation,
    disposition: s.disposition,
  }
}

/**
 * Le fichier « src/scenes/video.tsx » : les scènes l'une après l'autre, les sous-titres en même temps.
 * @param {object} montage le scénario nettoyé, avec les durées réelles et les fichiers des médias
 */
export function codeVideo(montage, { date = '' } = {}) {
  const format = FORMATS_VIDEO[montage.format] ?? FORMATS_VIDEO.vertical
  const duree = montage.scenes.reduce((t, s) => t + s.duree, 0) + (montage.fin?.duree ?? 0)
  const lignes = [
    `// Vidéo écrite par Kekeli${date ? ` le ${date}` : ''} : ${montage.scenes.length} scène${montage.scenes.length > 1 ? 's' : ''}${montage.fin ? ' et une fin' : ''}, ${secondes(duree)}, format ${format.nom}, ${format.largeur} × ${format.hauteur}.`,
    '// Moteur : Revideo (licence MIT). Les textes sont posés par le code et ajustés pour ne jamais déborder.',
    "import { makeScene2D } from '@revideo/2d';",
    "import { all } from '@revideo/core';",
    "import { fin, preparer, scene, sousTitres } from '../kekeli';",
    '',
    "export default makeScene2D('video', function* (vue) {",
    `  const k = preparer(vue, ${donnee({ largeur: format.largeur, hauteur: format.hauteur, couleurs: montage.couleurs, sousTitres: montage.sous_titres })});`,
    '  yield* all(',
    '    (function* () {',
  ]
  montage.scenes.forEach((s, i) => {
    lignes.push(`      // Scène ${i + 1} : ${secondes(s.duree)}${s.media ? `, ${s.media.type === 'video' ? 'vidéo' : 'image'} ${s.media.fichier.replace(/^\/medias\//, '')}` : ''}.`)
    lignes.push(`      yield* scene(k, ${donnee(sceneDuCode(s))});`)
  })
  if (montage.fin) {
    const f = montage.fin
    lignes.push(`      // Fin : ${secondes(f.duree)}, l'appel à l'action.`)
    lignes.push(`      yield* fin(k, ${donnee({ duree: f.duree, titre: f.titre, ...(f.texte ? { texte: f.texte } : {}), ...(f.contact ? { contact: f.contact } : {}), ...(f.paiement ? { paiement: f.paiement } : {}), ...(f.logo ? { logo: f.logo } : {}), ...(f.media ? { media: f.media } : {}) })});`)
  }
  lignes.push('    })(),')
  lignes.push(`    sousTitres(k, ${donnee(montage.sous_titres ? montage.lignes : [])}),`)
  lignes.push('  );')
  lignes.push('});')
  return `${lignes.join('\n')}\n`
}

/** Le fichier « src/projet.ts » : la taille et la cadence de la vidéo. */
export function codeProjet(montage) {
  const format = FORMATS_VIDEO[montage.format] ?? FORMATS_VIDEO.vertical
  return [
    "import { makeProject } from '@revideo/core';",
    "import video from './scenes/video?scene';",
    '',
    'export default makeProject({',
    '  scenes: [video],',
    `  settings: { shared: { size: { x: ${format.largeur}, y: ${format.hauteur} }, background: ${donnee(montage.couleurs.fond)} }, rendering: { fps: ${IMAGES_PAR_SECONDE} } },`,
    '});',
    '',
  ].join('\n')
}

/** Durée totale du montage, en secondes. */
export function dureeMontage(montage) {
  return montage.scenes.reduce((t, s) => t + s.duree, 0) + (montage.fin?.duree ?? 0)
}
