// Recherche sur Internet pour le premier répondant (outil « rechercher_web »), comme Claude ou ChatGPT : le modèle
// reste le même, il demande une recherche quand la réponse dépend d'informations actuelles ou vérifiables.
//
// Offres GRATUITES seulement, jamais de carte : Tavily en principal (recherche « basic », 1 crédit), Exa en secours.
// Un fournisseur qui répond « paiement nécessaire » (402) ou « limite atteinte » (429) est laissé de côté, le suivant
// prend le relais. Les clés restent dans le Runner (jamais dans le navigateur, jamais dans un ordinateur virtuel).
//
// Sécurité : on ne cherche jamais d'adresse interne, et l'on ne garde que des résultats aux adresses publiques en
// http ou https. Le contenu des pages est une donnée, jamais une consigne : il est raccourci, puis remis au modèle
// avec une consigne qui le dit.

export const FOURNISSEURS_RECHERCHE = {
  tavily: { variableCle: 'TAVILY_API_KEY', hote: 'api.tavily.com' },
  exa: { variableCle: 'EXA_API_KEY', hote: 'api.exa.ai' },
}

export const ORDRE_RECHERCHE = ['tavily', 'exa']

export const RESULTATS_MAX = 5
/** Résultats demandés au fournisseur : plus que ceux gardés, pour trier par pertinence (chez Tavily, le prix ne dépend pas du nombre de résultats ni du contenu des pages ; chez Exa, chaque page lue compte, on en demande moins). */
export const RESULTATS_DEMANDES = 10
export const RESULTATS_DEMANDES_EXA = 8
export const EXTRAIT_MAX = 1200
/** Texte d'une page gardé pour juger sa pertinence et en tirer les passages utiles. */
export const CONTENU_MAX = 12000
/** Passages d'une page remis au modèle (les meilleures pages seulement). */
export const PASSAGES_MAX = 2500
export const PAGES_LUES = 3
/** Requêtes envoyées aux fournisseurs pour UNE question du client, au plus (une seule « recherche du jour »). */
export const REQUETES_MAX_PAR_QUESTION = 5

/**
 * Réseaux sociaux, vidéos et forums : gardés seulement en dernier recours, quand il n'y a pas assez de sources plus
 * fiables (sites officiels, presse, entreprises), comme le font Perplexity ou ChatGPT.
 */
export const SITES_DERNIER_RECOURS = ['facebook.com', 'fb.com', 'instagram.com', 'tiktok.com', 'x.com', 'twitter.com', 'youtube.com', 'youtu.be', 'linkedin.com', 'pinterest.com', 'snapchat.com', 'threads.net', 'threads.com', 'reddit.com', 'quora.com', 'dailymotion.com', 'vimeo.com', 't.me', 'telegram.org', 'whatsapp.com']

/** Sources fiables au-dessous desquelles on garde aussi les réseaux sociaux et les vidéos. */
export const FIABLES_MIN = 2

/** Une adresse de réseau social, de vidéo ou de forum (le site lui-même ou l'un de ses sous-domaines). */
export function estDernierRecours(adresse) {
  let hote
  try {
    hote = new URL(String(adresse)).hostname.toLowerCase().replace(/^www\./, '')
  } catch {
    return false
  }
  return SITES_DERNIER_RECOURS.some((site) => hote === site || hote.endsWith(`.${site}`))
}

/**
 * Les sources fiables d'abord. Les réseaux sociaux et les vidéos ne restent que s'il y a moins de FIABLES_MIN sources
 * fiables, et toujours après elles.
 */
export function classerResultats(resultats) {
  const fiables = resultats.filter((r) => !estDernierRecours(r.url))
  if (fiables.length >= FIABLES_MIN) return fiables
  return [...fiables, ...resultats.filter((r) => estDernierRecours(r.url))]
}

const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']

/** Date de publication d'une page, en clair (« juin 2025 »), quand le fournisseur la donne ; sinon rien. */
export function dateEnClair(valeur, maintenant = new Date()) {
  if (!valeur) return null
  const date = new Date(String(valeur))
  if (Number.isNaN(date.getTime()) || date.getUTCFullYear() < 1995 || date.getTime() > maintenant.getTime() + 86400000) return null
  return `${MOIS[date.getUTCMonth()]} ${date.getUTCFullYear()}`
}

/** Une requête de recherche acceptable : courte, sans adresse interne ni fichier local. */
export function requeteSure(requete) {
  const q = String(requete ?? '').trim()
  if (q.length < 2 || q.length > 300) return false
  return !/(localhost|127\.0\.0\.1|0\.0\.0\.0|\b10\.\d+\.\d+\.\d+|\b192\.168\.|\b172\.(1[6-9]|2\d|3[01])\.|\b169\.254\.|file:\/\/|\[::1\]|\.internal\b|\.local\b)/i.test(q)
}

/** Une adresse de résultat acceptable : http ou https, nom de domaine public (jamais une adresse interne ou une IP privée). */
export function adresseSure(adresse) {
  let url
  try {
    url = new URL(String(adresse))
  } catch {
    return false
  }
  if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return false
  const hote = url.hostname.toLowerCase().replace(/^\[|\]$/g, '')
  if (!hote.includes('.') || hote === 'localhost' || hote.endsWith('.localhost') || hote.endsWith('.local') || hote.endsWith('.internal')) return false
  if (/^\d+\.\d+\.\d+\.\d+$/.test(hote)) {
    const [a, b] = hote.split('.').map(Number)
    if (a === 10 || a === 127 || a === 0 || (a === 169 && b === 254) || (a === 172 && b >= 16 && b <= 31) || (a === 192 && b === 168) || a >= 224) return false
  }
  if (hote.includes(':')) return false
  return true
}

const nettoyer = (texte, max) => String(texte ?? '')
  // eslint-disable-next-line no-control-regex
  .replace(/[\u0000-\u0008\u000B-\u001F\u007F]/g, ' ')
  .replace(/\s+/g, ' ')
  .trim()
  .slice(0, max)

/** Adresse et corps de la requête, selon le fournisseur de recherche. */
export function requeteRecherche(fournisseur, requete, cle, { exact = false } = {}) {
  if (fournisseur === 'tavily') {
    // Le texte des pages (« include_raw_content ») est compris dans le crédit de la recherche « basic ».
    return {
      url: 'https://api.tavily.com/search',
      entetes: { 'content-type': 'application/json', authorization: `Bearer ${cle}` },
      corps: { query: requete, search_depth: 'basic', max_results: RESULTATS_DEMANDES, include_answer: false, include_raw_content: 'text', include_images: false, ...(exact ? { exact_match: true } : {}) },
    }
  }
  return {
    url: 'https://api.exa.ai/search',
    entetes: { 'content-type': 'application/json', 'x-api-key': cle },
    corps: { query: requete, numResults: RESULTATS_DEMANDES_EXA, type: 'auto', contents: { text: { maxCharacters: CONTENU_MAX } } },
  }
}

/** Résultats d'une réponse, nettoyés : titre, adresse publique, extrait raccourci, date si elle est connue ; les sources fiables d'abord. */
export function lireResultats(fournisseur, donnees, { tous = false } = {}) {
  const bruts = Array.isArray(donnees?.results) ? donnees.results : []
  const resultats = bruts
    .map((r) => {
      const contenu = nettoyerPage(fournisseur === 'tavily' ? r.raw_content : r.text, CONTENU_MAX)
      return {
        titre: nettoyer(r.title, 150) || null,
        url: String(r.url ?? ''),
        extrait: nettoyer(fournisseur === 'tavily' ? r.content : (r.text ?? r.summary), EXTRAIT_MAX),
        date: dateEnClair(fournisseur === 'tavily' ? r.published_date : r.publishedDate),
        ...(contenu ? { contenu } : {}),
        ...(typeof r.score === 'number' ? { score: r.score } : {}),
      }
    })
    .filter((r) => adresseSure(r.url) && r.extrait)
    .map((r) => ({ ...r, titre: r.titre ?? new URL(r.url).hostname }))
  return classerResultats(resultats).slice(0, tous ? RESULTATS_DEMANDES : RESULTATS_MAX)
}

/** Le texte d'une page : caractères de contrôle enlevés, lignes gardées (pour en tirer des passages), raccourci. */
const nettoyerPage = (texte, max) => String(texte ?? '')
  // eslint-disable-next-line no-control-regex
  .replace(/[\u0000-\u0008\u000B-\u001F\u007F]/g, ' ')
  .replace(/[ \t]+/g, ' ')
  .replace(/\n\s*\n+/g, '\n')
  .trim()
  .slice(0, max)

/**
 * Cherche sur Internet avec le premier fournisseur disponible. Renvoie les résultats, le fournisseur qui a répondu et
 * les fournisseurs laissés de côté (limite atteinte, paiement demandé, panne).
 */
export async function chercherSurInternet(requete, { env = process.env, appeler = fetch, ordre = ORDRE_RECHERCHE, misesDeCote = null, exact = false, tous = false } = {}) {
  if (!requeteSure(requete)) return { ok: false, raison: 'requete', refus: [] }
  const refus = []
  for (const fournisseur of ordre) {
    const f = FOURNISSEURS_RECHERCHE[fournisseur]
    const cle = f && env[f.variableCle]
    if (!cle) continue
    if (misesDeCote && !misesDeCote.estDisponible(`recherche:${fournisseur}`)) continue
    const { url, entetes, corps } = requeteRecherche(fournisseur, requete, cle, { exact })
    let reponse
    try {
      reponse = await appeler(url, { method: 'POST', headers: entetes, body: JSON.stringify(corps), signal: AbortSignal.timeout(20000) })
    } catch {
      refus.push({ fournisseur, raison: 'injoignable' })
      continue
    }
    if (reponse.status === 402 || reponse.status === 429 || reponse.status === 432 || reponse.status === 433) {
      // Limite de l'offre gratuite atteinte, ou paiement demandé : ce fournisseur est ignoré, jamais payé.
      refus.push({ fournisseur, raison: reponse.status === 429 ? 'limite' : 'paiement' })
      misesDeCote?.mettreDeCote?.(`recherche:${fournisseur}`, 6 * 3600 * 1000)
      continue
    }
    if (!reponse.ok) {
      refus.push({ fournisseur, raison: `erreur ${reponse.status}` })
      continue
    }
    let donnees = null
    try {
      donnees = await reponse.json()
    } catch {
      refus.push({ fournisseur, raison: 'réponse illisible' })
      continue
    }
    return { ok: true, fournisseur, resultats: lireResultats(fournisseur, donnees, { tous }), refus }
  }
  return { ok: false, raison: 'indisponible', refus }
}

// ---------------------------------------------------------------------------------------------------------------
// Recherche approfondie, comme Claude : plusieurs requêtes pour une même question (le nom exact entre guillemets,
// la question, une variante en anglais), plus de résultats bruts, tri par pertinence (une page qui ne parle pas du
// sujet est écartée), lecture du texte des meilleures pages, et une reformulation quand rien n'est pertinent.
// Toute la question compte pour UNE seule « recherche du jour » du client ; les requêtes envoyées aux fournisseurs
// sont comptées à part (offres gratuites : Tavily environ 1 000 crédits par mois, Exa 10 $ par mois).
// ---------------------------------------------------------------------------------------------------------------

/** Mots trop courants pour dire de quoi parle une page. */
const MOTS_VIDES = new Set(('le la les un une des de du d l au aux et ou en dans sur pour par avec sans ce cet cette ces qui que quoi quel quelle quels quelles est sont etait ete etre a ont son sa ses leur leurs mon ma mes ton ta tes notre votre vos il elle ils elles je tu nous vous on me moi te toi se lui y ne pas plus tres bien aussi comme dont '
  + 'the of and or to in on for with by is are was who what which how when where from at as an be about this that these those it its into '
  + 'dr docteur doctor mr mme mlle monsieur madame m pr professeur prof sieur me maitre saint st '
  + 'qu est ce fais faire dis moi recherche recherches internet web cherche chercher trouve trouver information informations actuel actuelle actuellement aujourd hui maintenant moment toujours encore annee '
  + 'combien prix cout coute tarif latest current today now still year '
  + 'janvier fevrier mars avril mai juin juillet aout septembre octobre novembre decembre january february march april may june july august september october november december').split(/\s+/))

/** Sans accents ni majuscules, pour comparer. */
export const sansAccents = (texte) => String(texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Les mots qui disent de quoi parle un texte : sans accents, sans mots vides, 3 lettres au moins, jamais un nombre seul. */
export function motsUtiles(texte) {
  return [...new Set(sansAccents(texte).split(/[^a-z0-9]+/).filter((m) => m.length >= 3 && !MOTS_VIDES.has(m) && !/^\d+$/.test(m)))]
}

/**
 * Un mot du sujet est présent dans la page, avec un peu de tolérance pour l'orthographe (« Noubissi » trouve
 * « Noubissie », « Ahiankonou » trouve « Ahiankonou ») : même début, au plus 2 lettres de différence.
 */
export function motPresent(mot, motsPage) {
  if (motsPage.has(mot)) return true
  for (const m of motsPage) {
    if (m.length >= 4 && mot.length >= 4 && Math.abs(m.length - mot.length) <= 2 && (m.startsWith(mot) || mot.startsWith(m))) return true
  }
  return false
}

/**
 * Une page est pertinente quand elle parle vraiment du sujet : avec un sujet précis (le nom d'une personne, d'une
 * entreprise, d'un produit), TOUS ses mots sont dans la page ; sinon, au moins la moitié des mots utiles de la
 * requête. Le titre, l'extrait et le texte de la page comptent.
 */
export function estPertinent(resultat, { sujet = '', requete = '' } = {}) {
  const motsPage = new Set(sansAccents(`${resultat.titre ?? ''} ${resultat.extrait ?? ''} ${resultat.contenu ?? ''} ${decodeURIComponent(String(resultat.url ?? '').replace(/%(?![0-9a-f]{2})/gi, ''))}`).split(/[^a-z0-9]+/).filter(Boolean))
  const duSujet = motsUtiles(sujet)
  if (duSujet.length) return duSujet.every((m) => motPresent(m, motsPage))
  const deLaRequete = motsUtiles(requete)
  if (!deLaRequete.length) return true
  return deLaRequete.filter((m) => motPresent(m, motsPage)).length >= Math.ceil(deLaRequete.length / 2)
}

/** Les passages d'une page qui parlent du sujet (les lignes qui en contiennent un mot), puis son début, raccourcis. */
export function passagesUtiles(resultat, { sujet = '', requete = '' } = {}, max = PASSAGES_MAX) {
  const contenu = String(resultat.contenu ?? '')
  if (!contenu) return ''
  const mots = motsUtiles(sujet).length ? motsUtiles(sujet) : motsUtiles(requete)
  const lignes = contenu.split(/\n|(?<=[.!?])\s+(?=[A-ZÀ-Ý])/).map((l) => l.trim()).filter((l) => l.length >= 20)
  const choisies = []
  let taille = 0
  for (const ligne of lignes) {
    const motsLigne = new Set(sansAccents(ligne).split(/[^a-z0-9]+/).filter(Boolean))
    if (!mots.some((m) => motPresent(m, motsLigne))) continue
    if (taille + ligne.length > max) break
    choisies.push(ligne)
    taille += ligne.length + 1
  }
  for (const ligne of lignes) {
    if (taille >= max * 0.6) break
    if (choisies.includes(ligne) || taille + ligne.length > max) continue
    choisies.push(ligne)
    taille += ligne.length + 1
  }
  return choisies.join('\n')
}

/** L'adresse d'une page sans ce qui ne change pas son contenu (« www. », barre finale, suivi publicitaire). */
const cleAdresse = (adresse) => {
  try {
    const u = new URL(adresse)
    for (const p of [...u.searchParams.keys()]) if (/^utm_|^fbclid$|^gclid$/i.test(p)) u.searchParams.delete(p)
    return `${u.hostname.replace(/^www\./, '')}${u.pathname.replace(/\/+$/, '')}${u.search}`.toLowerCase()
  } catch {
    return String(adresse)
  }
}

/** Les requêtes à envoyer pour une question : la requête du modèle, le nom exact entre guillemets, la variante en anglais. */
export function requetesDeLaQuestion({ requete = '', sujet = '', requete_anglais: anglais = '' } = {}) {
  const liste = []
  const ajouter = (q, exact = false) => {
    const t = String(q ?? '').trim().slice(0, 300)
    if (!requeteSure(t) || liste.some((x) => sansAccents(x.q) === sansAccents(t))) return
    liste.push({ q: t, exact })
  }
  const s = String(sujet ?? '').replace(/["«»“”]/g, '').trim()
  // Le nom exact d'abord : c'est lui qui trouve le site officiel, la page « À propos » ou la fiche Wikipédia.
  if (s && motsUtiles(s).length) ajouter(`"${s}"`, true)
  ajouter(requete)
  ajouter(anglais)
  return liste.slice(0, 3)
}

/** Une reformulation quand rien de pertinent n'a été trouvé : le sujet seul sans guillemets, ou les mots utiles. */
export function reformulation({ requete = '', sujet = '' } = {}, dejaFaites = []) {
  // Le nom exact entre guillemets et le nom seul sont deux recherches différentes (la seconde est plus large).
  const deja = new Set(dejaFaites.map((q) => sansAccents(q).trim()))
  const essais = [String(sujet ?? '').replace(/["«»“”]/g, '').trim(), motsUtiles(`${sujet} ${requete}`).slice(0, 6).join(' ')]
  return essais.find((q) => q && requeteSure(q) && !deja.has(sansAccents(q))) ?? null
}

/**
 * La recherche d'une question : les requêtes partent en même temps, les résultats sont réunis (une page trouvée par
 * plusieurs requêtes passe devant), les pages sans rapport avec le sujet sont écartées, et les meilleures pages sont
 * lues (leurs passages utiles). Si rien n'est pertinent, une reformulation est envoyée avant de répondre.
 * « budget » : les requêtes encore permises pour cette question (partagé entre les appels du modèle).
 * Renvoie les résultats pertinents (5 au plus), les requêtes faites, le nombre de requêtes envoyées aux
 * fournisseurs et les refus rencontrés.
 */
export async function rechercheApprofondie(demande, options = {}) {
  const d = typeof demande === 'string' ? { requete: demande } : (demande ?? {})
  const budget = options.budget ?? { restantes: REQUETES_MAX_PAR_QUESTION }
  const chercher = options.chercherUne ?? chercherSurInternet
  const refus = []
  const faites = []
  let envoyees = 0
  let fournisseur = null
  const reunis = new Map()
  const lancer = async (liste) => {
    const permises = liste.slice(0, Math.max(0, budget.restantes))
    budget.restantes -= permises.length
    const reponses = await Promise.all(permises.map(({ q, exact }) => chercher(q, { ...options, exact, tous: true }).then((r) => ({ q, r }))))
    for (const { q, r } of reponses) {
      refus.push(...(r.refus ?? []))
      if (r.raison === 'requete') continue
      envoyees += 1 + (r.refus?.length ?? 0)
      faites.push(q)
      if (!r.ok) continue
      fournisseur ??= r.fournisseur
      for (const [rang, res] of (r.resultats ?? []).entries()) {
        const cle = cleAdresse(res.url)
        const deja = reunis.get(cle)
        if (deja) {
          deja.trouvee++
          deja.rang = Math.min(deja.rang, rang)
          if (!deja.contenu && res.contenu) deja.contenu = res.contenu
        } else {
          reunis.set(cle, { ...res, trouvee: 1, rang })
        }
      }
    }
  }
  await lancer(requetesDeLaQuestion(d))
  const pertinents = () => [...reunis.values()].filter((r) => (typeof r.score !== 'number' || r.score >= 0.05) && estPertinent(r, d))
  let gardes = pertinents()
  if (!gardes.length && budget.restantes > 0) {
    const autre = reformulation(d, faites)
    if (autre) {
      await lancer([{ q: autre, exact: false }])
      gardes = pertinents()
    }
  }
  if (!faites.length) return { ok: false, raison: refus.length ? 'indisponible' : 'requete', refus, requetes: [], envoyees }
  // Le site du sujet lui-même d'abord (son nom dans l'adresse : talkami.net pour Talkami), puis les pages trouvées par
  // plusieurs requêtes et les mieux classées, puis les sources fiables avant les réseaux sociaux.
  const motsSujet = motsUtiles(d.sujet)
  const officiel = (r) => (motsSujet.length && motsSujet.some((m) => m.length >= 4 && sansAccents(new URL(r.url).hostname).includes(m)) && !estDernierRecours(r.url) ? 1 : 0)
  gardes.sort((a, b) => officiel(b) - officiel(a) || b.trouvee - a.trouvee || a.rang - b.rang || (b.score ?? 0) - (a.score ?? 0))
  const classes = classerResultats(gardes).slice(0, RESULTATS_MAX)
  // Les meilleures pages dont le fournisseur n'a pas donné le texte sont lues (extraction de Tavily, 1 crédit pour
  // 5 pages), si le budget de la question le permet.
  const aLire = classes.slice(0, PAGES_LUES).filter((r) => String(r.contenu ?? '').length < 800 && !estDernierRecours(r.url))
  if (aLire.length && budget.restantes > 0 && (options.lirePages ?? lirePages) !== null) {
    budget.restantes--
    const lu = await (options.lirePages ?? lirePages)(aLire.map((r) => r.url), options)
    if (lu.envoyee) envoyees++
    for (const r of aLire) {
      const texte = lu.pages?.get(r.url)
      if (texte && estPertinent({ ...r, contenu: texte }, d)) r.contenu = texte
    }
  }
  const resultats = classes.map((r, i) => {
    const { trouvee, rang, score, contenu, ...reste } = r
    const passages = i < PAGES_LUES ? passagesUtiles(r, d) : ''
    return { ...reste, ...(passages ? { passages } : {}) }
  })
  return { ok: Boolean(fournisseur), fournisseur, resultats, requetes: faites, envoyees, refus, ecartes: reunis.size - gardes.length }
}

/**
 * Lit le texte de quelques pages avec l'extraction de Tavily (offre gratuite : 1 crédit pour 5 pages lues, rien pour
 * une page qui échoue). Seulement des adresses publiques ; le texte est raccourci. Renvoie les textes par adresse et
 * si une requête a été envoyée.
 */
export async function lirePages(adresses, { env = process.env, appeler = fetch, misesDeCote = null } = {}) {
  const urls = adresses.filter(adresseSure).slice(0, 5)
  const cle = env[FOURNISSEURS_RECHERCHE.tavily.variableCle]
  if (!urls.length || !cle || (misesDeCote && !misesDeCote.estDisponible('recherche:tavily'))) return { pages: new Map(), envoyee: false }
  let reponse
  try {
    reponse = await appeler('https://api.tavily.com/extract', {
      method: 'POST',
      headers: { 'content-type': 'application/json', authorization: `Bearer ${cle}` },
      body: JSON.stringify({ urls, extract_depth: 'basic', format: 'text', include_images: false }),
      signal: AbortSignal.timeout(20000),
    })
  } catch {
    return { pages: new Map(), envoyee: true }
  }
  if (reponse.status === 402 || reponse.status === 429 || reponse.status === 432 || reponse.status === 433) {
    misesDeCote?.mettreDeCote?.('recherche:tavily', 6 * 3600 * 1000)
    return { pages: new Map(), envoyee: true }
  }
  if (!reponse.ok) return { pages: new Map(), envoyee: true }
  const donnees = await reponse.json().catch(() => null)
  const pages = new Map()
  for (const r of Array.isArray(donnees?.results) ? donnees.results : []) {
    if (urls.includes(r.url) && r.raw_content) pages.set(r.url, nettoyerPage(r.raw_content, CONTENU_MAX))
  }
  return { pages, envoyee: true }
}
