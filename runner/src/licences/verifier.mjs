// Vérificateur de licences de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel et lancé
// avec Node.js après chaque demande. Aucune dépendance : seulement les modules de Node.js.
//
// Règle : tout ce que Kekeli ajoute dans un projet (photos, illustrations, icônes, polices, vidéos,
// sons, bibliothèques de code) doit venir d'une source libre de droits ou sous licence libre, avec
// la source et la licence notées dans le fichier LICENCES.md, à la racine du projet.
//
// Sortie : un objet JSON { ok, fichier, ressources, problemes } sur la sortie standard.
import { promises as fs } from 'node:fs'
import path from 'node:path'
import { pathToFileURL } from 'node:url'

export const FICHIER_LICENCES = 'LICENCES.md'

// Sources acceptées pour les ressources chargées depuis Internet, avec leur licence.
export const SOURCES_LIBRES = {
  'images.unsplash.com': 'Licence Unsplash',
  'unsplash.com': 'Licence Unsplash',
  'images.pexels.com': 'Licence Pexels',
  'videos.pexels.com': 'Licence Pexels',
  'cdn.pixabay.com': 'Licence du contenu Pixabay',
  'pixabay.com': 'Licence du contenu Pixabay',
  'upload.wikimedia.org': 'Licence libre indiquée sur Wikimedia Commons',
  'thumb.wikimedia.org': 'Licence libre indiquée sur Wikimedia Commons',
  'fonts.googleapis.com': 'SIL Open Font License (Google Fonts)',
  'fonts.gstatic.com': 'SIL Open Font License (Google Fonts)',
  'cdn.jsdelivr.net': 'Licence libre de la bibliothèque',
  'unpkg.com': 'Licence libre de la bibliothèque',
  'cdnjs.cloudflare.com': 'Licence libre de la bibliothèque',
  'cdn.tailwindcss.com': 'Licence MIT (Tailwind CSS)',
  'code.jquery.com': 'Licence MIT (jQuery)',
}

const MEDIAS = new Set([
  '.png', '.jpg', '.jpeg', '.gif', '.webp', '.avif', '.svg', '.ico', '.bmp', '.tif', '.tiff',
  '.woff', '.woff2', '.ttf', '.otf', '.eot',
  '.mp4', '.webm', '.mov', '.ogv',
  '.mp3', '.wav', '.ogg', '.oga', '.m4a', '.flac',
])
const TEXTES = new Set(['.html', '.htm', '.css', '.js', '.mjs', '.jsx', '.ts', '.tsx', '.vue', '.svelte', '.json'])
const IGNORES = new Set(['node_modules', 'dist', 'build'])
const FICHIERS_MAXIMUM = 3000

// Adresses de ressources : attributs src, srcset, poster, data, url() et @import des feuilles de style,
// liens vers une feuille de style, une icône ou une police. Les simples liens (<a href>) ne sont pas des ressources.
const MOTIFS = [
  /\b(?:src|poster|data)\s*=\s*["']?(https?:\/\/[^"'\s>]+)/gi,
  /\bsrcset\s*=\s*["']([^"']+)["']/gi,
  /url\(\s*["']?(https?:\/\/[^"')\s]+)/gi,
  /@import\s+(?:url\()?\s*["'](https?:\/\/[^"']+)/gi,
  /<link\b[^>]*\bhref\s*=\s*["'](https?:\/\/[^"']+)["'][^>]*>/gi,
  /\bimport\s+(?:[^'"]+\s+from\s+)?["'](https?:\/\/[^"']+)["']/gi,
  /\b(?:fetch|Image|Audio)\(\s*["'](https?:\/\/[^"']+)["']/gi,
]

/** Adresse sans paramètres ni ancre, pour la comparer à ce qui est noté dans LICENCES.md. */
export function adresseSimple(adresse) {
  try {
    const url = new URL(adresse)
    return `${url.protocol}//${url.host}${url.pathname}`
  } catch {
    return null
  }
}

/** Ressources externes trouvées dans un texte (HTML, CSS ou JavaScript). */
export function ressourcesExternes(texte) {
  // Une carte ou une vidéo intégrée dans un cadre (Google Maps, YouTube…) n'est pas une ressource
  // copiée dans le projet : son intégration est permise par le service lui-même.
  texte = texte.replace(/<iframe\b[^>]*>/gi, '')
  const trouvees = new Set()
  for (const motif of MOTIFS) {
    for (const correspondance of texte.matchAll(motif)) {
      let valeur = correspondance[1]
      if (/^<link/i.test(correspondance[0]) && !/rel\s*=\s*["'][^"']*(stylesheet|icon|preload|manifest)/i.test(correspondance[0])) continue
      const candidates = motif.source.startsWith('\\bsrcset') ? valeur.split(',').map((morceau) => morceau.trim().split(/\s+/)[0]) : [valeur]
      for (const candidate of candidates) {
        if (/^https?:\/\//i.test(candidate)) trouvees.add(candidate.replace(/&amp;/g, '&'))
      }
    }
  }
  return [...trouvees]
}

function estDocumentee(adresse, licences) {
  const hote = new URL(adresse).hostname
  if (hote.startsWith('fonts.g')) return /google fonts/i.test(licences)
  const simple = adresseSimple(adresse)
  return Boolean(simple) && licences.includes(simple)
}

async function parcourir(racine) {
  const fichiers = []
  async function dossier(courant) {
    let entrees
    try {
      entrees = await fs.readdir(courant, { withFileTypes: true })
    } catch {
      return
    }
    for (const entree of entrees) {
      if (fichiers.length >= FICHIERS_MAXIMUM) return
      if (entree.name.startsWith('.') || IGNORES.has(entree.name)) continue
      const complet = path.join(courant, entree.name)
      if (entree.isDirectory()) await dossier(complet)
      else if (entree.isFile()) fichiers.push(complet)
    }
  }
  await dossier(racine)
  return fichiers
}

/** Vérifie un dossier de projet et renvoie la liste des problèmes de licence. */
export async function verifierProjet(racine) {
  const fichiers = await parcourir(racine)
  const licences = await fs.readFile(path.join(racine, FICHIER_LICENCES), 'utf8').catch(() => null)
  const texteLicences = licences ?? ''
  const problemes = []
  let ressources = 0

  for (const fichier of fichiers) {
    const relatif = path.relative(racine, fichier)
    const extension = path.extname(fichier).toLowerCase()

    if (MEDIAS.has(extension)) {
      ressources++
      // Les photos envoyées par le client lui appartiennent : rien à noter pour elles.
      if (relatif.split(path.sep).join('/').startsWith('images/client/')) continue
      if (!texteLicences.includes(path.basename(fichier))) {
        problemes.push({ genre: 'fichier_non_documente', fichier: relatif, cible: relatif })
      }
      continue
    }

    if (!TEXTES.has(extension)) continue
    const texte = await fs.readFile(fichier, 'utf8').catch(() => '')
    for (const adresse of ressourcesExternes(texte)) {
      let hote
      try {
        hote = new URL(adresse).hostname.toLowerCase()
      } catch {
        continue
      }
      ressources++
      if (!SOURCES_LIBRES[hote]) {
        problemes.push({ genre: 'source_non_libre', fichier: relatif, cible: adresse })
      } else if (!estDocumentee(adresse, texteLicences)) {
        problemes.push({ genre: 'adresse_non_documentee', fichier: relatif, cible: adresse })
      }
    }
  }

  if (ressources > 0 && licences === null) {
    problemes.unshift({ genre: 'fichier_licences_absent', fichier: FICHIER_LICENCES, cible: FICHIER_LICENCES })
  }

  return { ok: problemes.length === 0, fichier: licences === null ? null : FICHIER_LICENCES, ressources, problemes: problemes.slice(0, 30) }
}

/** Texte envoyé à l'agent pour qu'il corrige lui-même les problèmes trouvés. */
export function demandeDeCorrection(resultat) {
  const lignes = resultat.problemes.map((p) => {
    switch (p.genre) {
      case 'fichier_licences_absent':
        return `- Le fichier ${FICHIER_LICENCES} n'existe pas à la racine du projet.`
      case 'fichier_non_documente':
        return `- Le fichier « ${p.cible} » n'est pas noté dans ${FICHIER_LICENCES} (source, auteur, licence).`
      case 'source_non_libre':
        return `- « ${p.cible} » (dans ${p.fichier}) ne vient pas d'une source libre de droits reconnue.`
      default:
        return `- « ${p.cible} » (dans ${p.fichier}) n'est pas noté dans ${FICHIER_LICENCES}.`
    }
  })
  return [
    'Vérification des licences : il reste des problèmes à corriger avant de terminer.',
    ...lignes,
    '',
    `Remplace chaque ressource qui ne vient pas d'une source libre (Unsplash, Pexels, Pixabay, Wikimedia Commons, Google Fonts, cdnjs, jsDelivr, unpkg) ou crée la toi-même (par exemple une icône en SVG). Puis note chaque ressource dans ${FICHIER_LICENCES} : l'adresse exacte, l'auteur s'il est connu et la licence. Ne copie jamais une image ou un texte d'un site qui n'en donne pas le droit.`,
  ].join('\n')
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  verifierProjet(path.resolve(process.argv[2] ?? '/home/user/projet'))
    .then((resultat) => process.stdout.write(JSON.stringify(resultat)))
    .catch((erreur) => {
      process.stdout.write(JSON.stringify({ ok: false, erreur: erreur.message, problemes: [] }))
      process.exitCode = 1
    })
}
