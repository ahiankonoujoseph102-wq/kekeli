// Vérification, depuis le Runner, des images chargées depuis Internet par le site d'un projet :
// chaque adresse doit répondre et donner une vraie image. Une adresse inventée est donc repérée.
const AGENT = 'KekeliBot/1.0 (https://kekeli.dev)'

async function essayer(adresse, methode, telecharger) {
  const reponse = await telecharger(adresse, {
    method: methode,
    redirect: 'follow',
    headers: { 'User-Agent': AGENT, ...(methode === 'GET' ? { Range: 'bytes=0-1023' } : {}) },
    signal: AbortSignal.timeout(10000),
  })
  await reponse.body?.cancel?.().catch?.(() => {})
  return reponse
}

/**
 * Vrai pour une image de Wikimedia en taille d'origine : Wikimedia la refuse souvent aux visiteurs d'autres sites
 * (code 429). Il faut une miniature à une largeur standard, comme celles que donne l'outil de photos.
 */
export function originalWikimedia(adresse) {
  try {
    const url = new URL(adresse)
    return url.hostname === 'upload.wikimedia.org' && !url.pathname.includes('/thumb/')
  } catch {
    return false
  }
}

/** Vrai si l'adresse répond avec une image. Certains sites refusent HEAD : on réessaie alors avec GET. */
export async function imageExiste(adresse, telecharger = fetch) {
  if (originalWikimedia(adresse)) return false
  try {
    let reponse = await essayer(adresse, 'HEAD', telecharger)
    if (reponse.status === 405 || reponse.status === 403 || !reponse.headers.get('content-type')) reponse = await essayer(adresse, 'GET', telecharger)
    return reponse.ok && /^image\//i.test(reponse.headers.get('content-type') ?? '')
  } catch {
    return false
  }
}

/** Adresses d'images qui ne répondent pas (au plus 60 vérifiées, 6 à la fois). */
export async function imagesIntrouvables(adresses, telecharger = fetch) {
  const liste = [...new Set(adresses)].slice(0, 60)
  const introuvables = []
  for (let i = 0; i < liste.length; i += 6) {
    const lot = liste.slice(i, i + 6)
    const resultats = await Promise.all(lot.map((adresse) => imageExiste(adresse, telecharger)))
    lot.forEach((adresse, j) => {
      if (!resultats[j]) introuvables.push(adresse)
    })
  }
  return introuvables
}
