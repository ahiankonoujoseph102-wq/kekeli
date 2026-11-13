// Les médias d'une vidéo, cherchés et téléchargés par le Runner (qui garde les clés), puis copiés dans l'ordinateur
// isolé : 1. les fichiers du client ; 2. les images créées par Kekeli ; 3. les photos et vidéos libres de Pexels et de
// Pixabay (interfaces officielles, clés PEXELS_API_KEY et PIXABAY_API_KEY dans le Runner seulement) ; 4. une musique
// libre CC0 trouvée avec Openverse (Freesound), ou une musique écrite par Kekeli. Chaque média est noté avec sa source
// et sa licence (fichier medias.json de la vidéo). Jamais de téléchargement libre « de tout Internet ».

import { valeurCle } from '../fournisseurs.js'
import { adresseSure } from '../recherche.js'

export const TAILLE_MAX_MEDIA = 80 * 1024 * 1024

export const LICENCES = {
  pexels: { nom: 'Licence Pexels', url: 'https://www.pexels.com/license/', note: 'Utilisation gratuite, commerciale permise, sans obligation de citer l’auteur. Photos et vidéos fournies par Pexels.' },
  pixabay: { nom: 'Licence du contenu Pixabay', url: 'https://pixabay.com/service/license-summary/', note: 'Utilisation gratuite, commerciale permise, sans obligation de citer l’auteur.' },
  cc0: { nom: 'CC0 1.0 (domaine public)', url: 'https://creativecommons.org/publicdomain/zero/1.0/', note: 'Aucune obligation, utilisation commerciale permise.' },
  kekeli: { nom: 'Créé par Kekeli pour le client', url: null, note: 'Image ou musique créée pour cette vidéo ; elle appartient au client.' },
  client: { nom: 'Fichier du client', url: null, note: 'Envoyé par le client, qui en garantit les droits.' },
}

/** Les ambiances de musique et les mots cherchés dans Openverse (CC0 seulement). */
export const RECHERCHES_MUSIQUE = {
  entrainante: ['upbeat music loop', 'happy upbeat music', 'upbeat background music'],
  calme: ['calm ambient music', 'soft piano background music', 'relaxing music loop'],
  corporate: ['corporate background music', 'corporate music loop', 'inspiring background music'],
  africaine: ['african music', 'african drums beat', 'afrobeat loop'],
  festive: ['party music loop', 'festive music', 'upbeat dance music'],
}

/** Télécharge un média (taille bornée, adresses internes refusées). */
export async function telechargerMedia(url, { appeler = fetch, max = TAILLE_MAX_MEDIA } = {}) {
  if (!adresseSure(url)) throw new Error('adresse refusée')
  const reponse = await appeler(url, { signal: AbortSignal.timeout(120000), redirect: 'follow' })
  if (!reponse.ok) throw new Error(`téléchargement impossible (${reponse.status})`)
  const taille = Number(reponse.headers?.get?.('content-length') ?? 0)
  if (taille > max) throw new Error('fichier trop gros')
  const octets = Buffer.from(await reponse.arrayBuffer())
  if (octets.length > max) throw new Error('fichier trop gros')
  return octets
}

/**
 * Une photo ou une vidéo libre (Pexels d'abord, puis Pixabay), quand leurs clés sont placées dans le Runner.
 * @returns {Promise<object|null>} { url, page, auteur, source, licence, type, largeur, hauteur }
 */
export async function chercherMediaLibre(requete, { type = 'image', orientation = 'portrait' } = {}, { appeler = fetch, env = process.env } = {}) {
  const q = String(requete ?? '').trim().slice(0, 80)
  if (!q) return null
  const pexels = valeurCle('PEXELS_API_KEY', env)
  if (pexels) {
    const trouve = await chercherPexels(q, type, orientation, pexels, appeler).catch(() => null)
    if (trouve) return trouve
  }
  const pixabay = valeurCle('PIXABAY_API_KEY', env)
  if (pixabay) {
    const trouve = await chercherPixabay(q, type, orientation, pixabay, appeler).catch(() => null)
    if (trouve) return trouve
  }
  return null
}

async function chercherPexels(q, type, orientation, cle, appeler) {
  const base = type === 'video' ? 'https://api.pexels.com/videos/search' : 'https://api.pexels.com/v1/search'
  const reponse = await appeler(`${base}?${new URLSearchParams({ query: q, orientation, per_page: '8', size: 'medium' })}`, { headers: { Authorization: cle }, signal: AbortSignal.timeout(20000) })
  if (!reponse.ok) return null
  const d = await reponse.json()
  if (type === 'video') {
    for (const v of d.videos ?? []) {
      const fichier = (v.video_files ?? []).filter((f) => f.file_type === 'video/mp4' && f.width && f.height && Math.max(f.width, f.height) <= 1920).sort((a, b) => b.width * b.height - a.width * a.height)[0]
      if (fichier) return { url: fichier.link, page: v.url, auteur: v.user?.name ?? null, source: 'Pexels', licence: 'pexels', type: 'video', largeur: fichier.width, hauteur: fichier.height, duree: v.duration ?? null }
    }
    return null
  }
  const p = (d.photos ?? [])[0]
  return p ? { url: p.src?.large2x ?? p.src?.large ?? p.src?.original, page: p.url, auteur: p.photographer ?? null, source: 'Pexels', licence: 'pexels', type: 'image', largeur: p.width, hauteur: p.height } : null
}

async function chercherPixabay(q, type, orientation, cle, appeler) {
  // La clé passe dans l'adresse : seul le Runner appelle Pixabay, jamais l'ordinateur virtuel.
  const base = type === 'video' ? 'https://pixabay.com/api/videos/' : 'https://pixabay.com/api/'
  const parametres = { key: cle, q, safesearch: 'true', per_page: '10', lang: 'en', ...(type === 'video' ? {} : { image_type: 'photo', orientation: orientation === 'portrait' ? 'vertical' : 'horizontal' }) }
  const reponse = await appeler(`${base}?${new URLSearchParams(parametres)}`, { signal: AbortSignal.timeout(20000) })
  if (!reponse.ok) return null
  const d = await reponse.json()
  const h = (d.hits ?? [])[0]
  if (!h) return null
  if (type === 'video') {
    const f = h.videos?.large?.url ? h.videos.large : h.videos?.medium
    return f?.url ? { url: f.url, page: h.pageURL, auteur: h.user ?? null, source: 'Pixabay', licence: 'pixabay', type: 'video', largeur: f.width, hauteur: f.height, duree: h.duration ?? null } : null
  }
  return { url: h.largeImageURL ?? h.webformatURL, page: h.pageURL, auteur: h.user ?? null, source: 'Pixabay', licence: 'pixabay', type: 'image', largeur: h.imageWidth, hauteur: h.imageHeight }
}

/**
 * Une musique libre CC0 (domaine public) trouvée avec Openverse (sans compte), surtout sur Freesound. Seules les
 * pistes marquées « cc0 » sont gardées, assez longues, et dont le titre parle de musique.
 * @returns {Promise<object|null>} { url, page, titre, auteur, source, licence, duree }
 */
export async function chercherMusique(ambiance, dureeVoulue, { appeler = fetch, deja = [] } = {}) {
  const recherches = RECHERCHES_MUSIQUE[ambiance] ?? RECHERCHES_MUSIQUE.entrainante
  for (const q of recherches) {
    let d
    try {
      const reponse = await appeler(`https://api.openverse.org/v1/audio/?${new URLSearchParams({ q, license: 'cc0', page_size: '20' })}`, { headers: { Accept: 'application/json', 'User-Agent': 'Kekeli/1.0' }, signal: AbortSignal.timeout(20000) })
      if (!reponse.ok) continue
      d = await reponse.json()
    } catch {
      continue
    }
    const pistes = (d.results ?? [])
      .filter((r) => String(r.license).toLowerCase() === 'cc0' && /^https:\/\//.test(String(r.url ?? '')) && !deja.includes(r.url))
      .filter((r) => (r.duration ?? 0) >= Math.min(20000, dureeVoulue * 1000) && (r.duration ?? 0) <= 600000)
      .filter((r) => !/(voice|speech|vocal|talk|scream|noise|fx|effect|sfx|alarm)/i.test(`${r.title} ${(r.tags ?? []).map((t) => t.name).join(' ')}`))
    // Les vraies musiques d'abord (étiquette « music », titre qui parle de musique, de boucle ou de rythme).
    const note = (r) => ((r.tags ?? []).some((t) => t.name === 'music') ? 2 : 0) + (/(music|loop|background|beat|groove|theme|song)/i.test(String(r.title)) ? 1 : 0)
    const r = pistes.map((p, i) => ({ p, n: note(p), i })).sort((a, b) => b.n - a.n || a.i - b.i)[0]?.p
    if (r) return { url: r.url, page: r.foreign_landing_url ?? null, titre: String(r.title ?? '').slice(0, 120), auteur: r.creator ?? null, source: `${r.provider === 'freesound' ? 'Freesound' : r.source ?? 'Openverse'} (trouvée avec Openverse)`, licence: 'cc0', duree: Math.round((r.duration ?? 0) / 1000) }
  }
  return null
}

/**
 * Une musique écrite par Kekeli (code FFmpeg, aucun droit à payer), quand aucune musique libre n'est trouvée :
 * accords doux qui changent toutes les 2 secondes et pulsation légère, selon l'ambiance.
 */
export function commandeMusiqueKekeli(ambiance, duree, sortie) {
  const tempo = { entrainante: 0.5, festive: 0.45, africaine: 0.4, corporate: 0.6, calme: 1.2 }[ambiance] ?? 0.5
  // Quatre accords (la mineur, fa, do, sol), fréquences en hertz.
  const accords = [[220, 261.63, 329.63], [174.61, 220, 261.63], [261.63, 329.63, 392], [196, 246.94, 293.66]]
  const choix = (n) => accords.map((a, i) => `if(eq(mod(floor(t/2),4),${i}),${a[n]},0)`).join('+')
  const nappe = [0, 1, 2].map((n) => `0.07*sin(2*PI*(${choix(n)})*t)`).join('+')
  const pulsation = ambiance === 'calme' ? '0' : `0.22*sin(2*PI*55*t)*exp(-14*mod(t,${tempo}))`
  const expression = `(${nappe})*(0.75+0.25*sin(2*PI*0.25*t))+${pulsation}`
  return `ffmpeg -v error -y -f lavfi -i "aevalsrc='${expression}':s=44100:d=${Math.ceil(duree + 1)}" -af "lowpass=f=4000,afade=t=in:d=1,volume=0.9" -ac 2 -c:a libmp3lame -q:a 4 ${sortie}`
}
