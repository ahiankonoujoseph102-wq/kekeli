// Vérificateur de qualité de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel et lancé
// avec Node.js après chaque demande. Aucune dépendance : seulement les modules de Node.js.
//
// Il contrôle ce qu'un site professionnel doit avoir sur chaque page, et donne la liste des images
// chargées depuis Internet, que le Runner vérifie ensuite (adresse qui répond, vraie image).
//
// Sortie : un objet JSON { ok, pages, images, problemes } sur la sortie standard.
import { promises as fs } from 'node:fs'
import path from 'node:path'
import { pathToFileURL } from 'node:url'

const IGNORES = new Set(['node_modules', 'dist', 'build'])
const TEXTES_PROVISOIRES = /lorem ipsum|dolor sit amet|example\.com|votre texte ici|texte à remplacer|à compléter|\bTODO\b|\bXXX\b/i

async function pagesHtml(racine) {
  const pages = []
  async function dossier(courant) {
    let entrees
    try {
      entrees = await fs.readdir(courant, { withFileTypes: true })
    } catch {
      return
    }
    for (const entree of entrees) {
      if (entree.name.startsWith('.') || IGNORES.has(entree.name)) continue
      const complet = path.join(courant, entree.name)
      if (entree.isDirectory()) await dossier(complet)
      else if (/\.html?$/i.test(entree.name) && pages.length < 200) pages.push(complet)
    }
  }
  await dossier(racine)
  return pages
}

const attribut = (balise, nom) => {
  const trouve = new RegExp(`\\b${nom}\\s*=\\s*("([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i').exec(balise)
  return trouve ? (trouve[2] ?? trouve[3] ?? trouve[4] ?? '') : null
}

/** Images chargées depuis Internet dans un texte HTML ou CSS. */
export function imagesExternes(texte) {
  const trouvees = new Set()
  for (const balise of texte.match(/<(img|source)\b[^>]*>/gi) ?? []) {
    const src = attribut(balise, 'src')
    if (src && /^https?:\/\//i.test(src)) trouvees.add(src.replace(/&amp;/g, '&'))
    for (const morceau of (attribut(balise, 'srcset') ?? '').split(',')) {
      const adresse = morceau.trim().split(/\s+/)[0]
      if (/^https?:\/\//i.test(adresse)) trouvees.add(adresse.replace(/&amp;/g, '&'))
    }
  }
  for (const [, adresse] of texte.matchAll(/url\(\s*["']?(https?:\/\/[^"')\s]+)/gi)) trouvees.add(adresse)
  for (const balise of texte.match(/<video\b[^>]*>/gi) ?? []) {
    const affiche = attribut(balise, 'poster')
    if (affiche && /^https?:\/\//i.test(affiche)) trouvees.add(affiche)
  }
  return [...trouvees]
}

/** Contrôles d'une page HTML. `existe` dit si un fichier du projet existe. */
export async function verifierPage(relatif, html, existe) {
  const problemes = []
  const ajouter = (genre, cible = '') => problemes.push({ genre, fichier: relatif, cible })

  const balisesHtml = /<html\b[^>]*>/i.exec(html)?.[0] ?? ''
  if (!attribut(balisesHtml, 'lang')) ajouter('langue_absente')
  if (!/<meta\b[^>]*name\s*=\s*["']viewport["'][^>]*>/i.test(html)) ajouter('telephone_absent')
  const titre = /<title\b[^>]*>([\s\S]*?)<\/title>/i.exec(html)?.[1]?.trim()
  if (!titre) ajouter('titre_absent')
  // Description : on lit les attributs un par un (une apostrophe dans le texte ne doit pas gêner).
  const description = (html.match(/<meta\b[^>]*>/gi) ?? []).find((balise) => (attribut(balise, 'name') ?? '').toLowerCase() === 'description')
  if (!description || (attribut(description, 'content') ?? '').trim().length < 20) ajouter('description_absente')

  for (const balise of html.match(/<img\b[^>]*>/gi) ?? []) {
    const alt = attribut(balise, 'alt')
    if (alt === null) ajouter('image_sans_texte', attribut(balise, 'src') ?? '')
  }

  // Liens et fichiers du projet qui n'existent pas.
  const dossier = path.dirname(relatif)
  const references = [
    ...[...html.matchAll(/<a\b[^>]*\bhref\s*=\s*["']([^"'#?]+)/gi)].map((m) => m[1]),
    ...[...html.matchAll(/<(?:img|script|source)\b[^>]*\bsrc\s*=\s*["']([^"'#?]+)/gi)].map((m) => m[1]),
    ...[...html.matchAll(/<link\b[^>]*\bhref\s*=\s*["']([^"'#?]+)/gi)].map((m) => m[1]),
  ]
  for (const reference of new Set(references)) {
    if (/^(https?:|mailto:|tel:|data:|javascript:|\/\/|#)/i.test(reference) || reference.trim() === '') continue
    const cible = path.normalize(reference.startsWith('/') ? reference.slice(1) : path.join(dossier, reference))
    if (cible.startsWith('..')) continue
    if (!(await existe(cible)) && !(await existe(path.join(cible, 'index.html')))) ajouter('lien_casse', reference)
  }

  const texteVisible = html.replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, ' ')
  const provisoire = TEXTES_PROVISOIRES.exec(texteVisible)
  if (provisoire) ajouter('texte_provisoire', provisoire[0])

  return problemes
}

export async function verifierProjet(racine) {
  const pages = await pagesHtml(racine)
  const existe = (relatif) => fs.stat(path.join(racine, relatif)).then((info) => info.isFile(), () => false)
  const problemes = []
  const images = new Set()

  for (const page of pages) {
    const relatif = path.relative(racine, page)
    const html = await fs.readFile(page, 'utf8').catch(() => '')
    problemes.push(...(await verifierPage(relatif, html, existe)))
    for (const image of imagesExternes(html)) images.add(image)
  }
  for (const feuille of (await fs.readdir(racine, { recursive: true }).catch(() => [])).filter((f) => /\.css$/i.test(f) && !f.split(path.sep).some((m) => m.startsWith('.') || IGNORES.has(m)))) {
    for (const image of imagesExternes(await fs.readFile(path.join(racine, feuille), 'utf8').catch(() => ''))) images.add(image)
  }
  if (pages.length > 0 && !(await existe('index.html'))) problemes.unshift({ genre: 'accueil_absent', fichier: 'index.html', cible: 'index.html' })

  return { ok: problemes.length === 0, pages: pages.length, images: [...images].slice(0, 60), problemes: problemes.slice(0, 40) }
}

/** Phrases expliquant chaque problème à l'agent. */
export function explication(probleme) {
  const ou = probleme.fichier
  switch (probleme.genre) {
    case 'accueil_absent':
      return '- Il n\'y a pas de page d\'accueil index.html à la racine du projet.'
    case 'langue_absente':
      return `- ${ou} : ajoute la langue de la page (<html lang="fr">).`
    case 'telephone_absent':
      return `- ${ou} : ajoute <meta name="viewport" content="width=device-width, initial-scale=1"> pour un bon affichage sur téléphone.`
    case 'titre_absent':
      return `- ${ou} : ajoute un titre de page (<title>).`
    case 'description_absente':
      return `- ${ou} : ajoute une description pour les moteurs de recherche (<meta name="description" content="…">, au moins une phrase).`
    case 'image_sans_texte':
      return `- ${ou} : l'image « ${probleme.cible} » n'a pas de texte de remplacement (attribut alt).`
    case 'lien_casse':
      return `- ${ou} : le lien ou le fichier « ${probleme.cible} » n'existe pas dans le projet.`
    case 'texte_provisoire':
      return `- ${ou} : il reste un texte provisoire (« ${probleme.cible} ») : remplace-le par un vrai contenu.`
    case 'image_introuvable':
      return `- ${ou} : l'image « ${probleme.cible} » ne répond pas ou n'est pas une image (adresse inventée ou cassée, ou image de Wikimedia en taille d'origine) : remplace-la par une adresse donnée par l'outil de photos.`
    default:
      return `- ${ou} : ${probleme.cible}`
  }
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  verifierProjet(path.resolve(process.argv[2] ?? '/home/user/projet'))
    .then((resultat) => process.stdout.write(JSON.stringify(resultat)))
    .catch((erreur) => {
      process.stdout.write(JSON.stringify({ ok: false, erreur: erreur.message, images: [], problemes: [] }))
      process.exitCode = 1
    })
}
