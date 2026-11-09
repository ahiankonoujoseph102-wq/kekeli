// Outil de recherche de photos de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel ;
// l'agent le lance avec Node.js pour trouver de vraies photos sous licence libre, au lieu d'inventer
// des adresses. Aucune dépendance : seulement les modules de Node.js.
//
//   node /home/user/.kekeli/photos.mjs "mots-clés en anglais" [nombre]
//
// Source : Wikimedia Commons (photos sous licence libre ou dans le domaine public). Chaque adresse
// renvoyée a été vérifiée : elle répond et c'est bien une image. Chaque résultat donne la ligne à
// copier dans LICENCES.md (adresse, auteur, licence, page de la photo).
import { pathToFileURL } from 'node:url'

const API = 'https://commons.wikimedia.org/w/api.php'
const AGENT = 'KekeliBot/1.0 (https://kekeli.dev)'
// Wikimedia limite fortement l'affichage des images en taille d'origine sur d'autres sites : on ne garde que
// des miniatures à une largeur standard (liste officielle : https://w.wiki/GHai), ici 1 280 pixels.
export const LARGEUR_MINIATURE = 1280

// Licences permises : utilisables pour un usage commercial, avec attribution si demandé.
export function licenceLibre(nom) {
  const texte = String(nom ?? '').trim().toLowerCase().replace(/\s+/g, ' ')
  if (/\bnc\b|non-?commercial|\bnd\b|no-?deriv|fair use|copyright/.test(texte)) return false
  return /^(cc0|public domain|pd-|cc[ -]by(-sa)? \d(\.\d)?)/.test(texte)
}

const sansBalises = (texte) => String(texte ?? '').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim()

/** Adresse de recherche dans Wikimedia Commons (photos seulement). */
export function adresseRecherche(motsCles, nombre = 12) {
  const parametres = new URLSearchParams({
    action: 'query',
    format: 'json',
    generator: 'search',
    gsrnamespace: '6',
    gsrsearch: `${motsCles} filetype:bitmap -painting -drawing -illustration`,
    gsrlimit: String(Math.min(30, nombre * 3)),
    prop: 'imageinfo',
    iiprop: 'url|extmetadata|mime|size',
    iiurlwidth: String(LARGEUR_MINIATURE),
  })
  return `${API}?${parametres}`
}

/** Transforme la réponse de Wikimedia Commons en photos utilisables (sans vérifier les adresses). */
export function photosDepuisReponse(reponse) {
  const pages = Object.values(reponse?.query?.pages ?? {}).sort((a, b) => (a.index ?? 0) - (b.index ?? 0))
  const photos = []
  for (const page of pages) {
    const info = page.imageinfo?.[0]
    if (!info || !/^image\/(jpeg|png|webp)$/.test(info.mime ?? '')) continue
    // Une image plus petite que la miniature n'a pas de miniature : Wikimedia renverrait l'original.
    if ((info.width ?? 0) <= LARGEUR_MINIATURE) continue
    const meta = info.extmetadata ?? {}
    const licence = sansBalises(meta.LicenseShortName?.value)
    if (!licenceLibre(licence)) continue
    // Adresse sans les paramètres de suivi ajoutés par Wikimedia Commons.
    const adresse = (info.thumburl || '').split('?')[0]
    if (!adresse) continue
    photos.push({
      adresse,
      largeur: info.thumbwidth || info.width,
      hauteur: info.thumbheight || info.height,
      titre: sansBalises(meta.ObjectName?.value) || page.title.replace(/^File:/, ''),
      auteur: sansBalises(meta.Artist?.value) || 'Auteur inconnu',
      licence,
      page: info.descriptionurl,
    })
  }
  return photos
}

/** Vérifie qu'une adresse répond et que c'est bien une image. */
export async function adresseValide(adresse, telecharger = fetch) {
  try {
    const reponse = await telecharger(adresse, { method: 'HEAD', redirect: 'follow', headers: { 'User-Agent': AGENT }, signal: AbortSignal.timeout(10000) })
    return reponse.ok && /^image\//.test(reponse.headers.get('content-type') ?? '')
  } catch {
    return false
  }
}

export function ligneLicence(photo) {
  return `- ${photo.adresse} — « ${photo.titre} » par ${photo.auteur}, licence ${photo.licence}, source : Wikimedia Commons (${photo.page})`
}

export async function chercherPhotos(motsCles, nombre = 4, telecharger = fetch) {
  const reponse = await telecharger(adresseRecherche(motsCles, nombre), { headers: { 'User-Agent': AGENT }, signal: AbortSignal.timeout(20000) })
  if (!reponse.ok) throw new Error(`Wikimedia Commons a répondu ${reponse.status}`)
  const verifiees = []
  for (const photo of photosDepuisReponse(await reponse.json())) {
    if (verifiees.length >= nombre) break
    if (await adresseValide(photo.adresse, telecharger)) verifiees.push(photo)
  }
  return verifiees
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const [motsCles, nombre = '4'] = process.argv.slice(2)
  if (!motsCles) {
    console.log('Utilisation : node photos.mjs "mots-clés en anglais" [nombre]')
    process.exit(1)
  }
  chercherPhotos(motsCles, Math.max(1, Math.min(10, Number(nombre) || 4)))
    .then((photos) => {
      if (!photos.length) {
        console.log(`Aucune photo libre trouvée pour « ${motsCles} ». Essaie d'autres mots-clés en anglais, ou crée une illustration en SVG.`)
        return
      }
      console.log(`${photos.length} photo(s) vérifiée(s) pour « ${motsCles} ». Utilise l'adresse exacte, puis copie la ligne dans LICENCES.md.\n`)
      for (const photo of photos) {
        console.log(`Adresse : ${photo.adresse}`)
        console.log(`Taille : ${photo.largeur} × ${photo.hauteur} · Titre : ${photo.titre}`)
        console.log(`Ligne pour LICENCES.md : ${ligneLicence(photo)}\n`)
      }
    })
    .catch((erreur) => {
      console.log(`Recherche impossible : ${erreur.message}`)
      process.exit(1)
    })
}
