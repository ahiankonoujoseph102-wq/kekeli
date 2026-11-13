// Le cœur du point d'entrée (vérification, mémoire, lancement sur GitHub), séparé de worker.js : le fichier principal
// d'un Worker ne peut exporter que son point d'entrée, et les tests utilisent ces fonctions.

export const TAILLE_MAX = 32 * 1024
export const ECART_MAX_SECONDES = 300
export const ENVOIS_FORMULAIRE = 5
export const FENETRE_FORMULAIRE_SECONDES = 600
const DUREE_DEDOUBLONNAGE = 24 * 3600

/** Le cœur du point d'entrée (séparé pour les tests). */
export async function traiter(requete, env, { memoire = null, appeler = fetch, maintenant = Date.now() } = {}) {
  const origine = requete.headers.get('Origin') ?? ''
  const origines = String(env.FORMULAIRE_ORIGINES ?? '').split(',').map((o) => o.trim().replace(/\/+$/, '')).filter(Boolean)
  const cors = origines.includes(origine) ? { 'Access-Control-Allow-Origin': origine, 'Access-Control-Allow-Methods': 'POST', 'Access-Control-Allow-Headers': 'Content-Type', Vary: 'Origin' } : {}
  if (requete.method === 'OPTIONS') return new Response(null, { status: origines.includes(origine) ? 204 : 403, headers: cors })
  if (requete.method !== 'POST') return json({ ok: false, erreur: 'Envoyez l’événement avec POST.' }, 405, cors)
  if (!env.ENTREE_SECRET || !env.GITHUB_DECLENCHEUR || !env.DEPOT || !memoire) return json({ ok: false, erreur: 'Point d’entrée pas encore réglé.' }, 503, cors)

  const longueur = Number(requete.headers.get('Content-Length') ?? 0)
  if (longueur > TAILLE_MAX) return json({ ok: false, erreur: 'Événement trop gros.' }, 413, cors)
  const corps = await requete.text()
  if (corps.length > TAILLE_MAX) return json({ ok: false, erreur: 'Événement trop gros.' }, 413, cors)

  // 1, 2 ou 3 : la vérification qui convient, sinon refus.
  const adresse = new URL(requete.url)
  let mode = null
  const signature = requete.headers.get('X-Kekeli-Signature')
  if (signature) {
    if (!(await signatureValide(signature, corps, env.ENTREE_SECRET, maintenant))) return json({ ok: false, erreur: 'Signature invalide ou trop ancienne.' }, 401, cors)
    mode = 'signature'
  } else {
    const jeton = adresse.searchParams.get('jeton') ?? (requete.headers.get('Authorization') ?? '').replace(/^Bearer\s+/i, '')
    if (jeton) {
      if (!egaux(jeton, env.ENTREE_SECRET)) return json({ ok: false, erreur: 'Secret invalide.' }, 401, cors)
      mode = 'jeton'
    } else if (origines.includes(origine)) {
      mode = 'formulaire'
    } else {
      return json({ ok: false, erreur: 'Appel non autorisé.' }, 401, cors)
    }
  }

  const donnees = lireDonnees(corps, requete.headers.get('Content-Type') ?? '')
  if (donnees === null) return json({ ok: false, erreur: 'Données illisibles.' }, 400, cors)

  if (mode === 'formulaire') {
    // Un robot remplit le champ piège, invisible pour une personne : on répond poliment sans rien lancer.
    if (String(donnees.site_web ?? '').trim()) return reponseFormulaire(requete, env, cors)
    const ip = requete.headers.get('CF-Connecting-IP') ?? 'inconnue'
    const compte = await lire(memoire, `limite/${await empreinte(ip)}`)
    const envois = Number(compte ?? 0)
    if (envois >= ENVOIS_FORMULAIRE) return json({ ok: false, erreur: 'Trop d’envois. Réessayez dans quelques minutes.' }, 429, cors)
    await ecrire(memoire, `limite/${await empreinte(ip)}`, String(envois + 1), FENETRE_FORMULAIRE_SECONDES)
  }
  delete donnees.site_web

  // Jamais deux fois le même événement : son identifiant (donné par l'expéditeur, sinon l'empreinte de son contenu).
  const id = String(requete.headers.get('X-Evenement-Id') ?? donnees.id_evenement ?? (await empreinte(corps))).slice(0, 100)
  if (await lire(memoire, `vu/${await empreinte(id)}`)) return mode === 'formulaire' ? reponseFormulaire(requete, env, cors) : json({ ok: true, deja: true }, 200, cors)

  const type = String(adresse.searchParams.get('type') ?? env.TYPE_EVENEMENT ?? 'evenement').replace(/[^a-z0-9_-]/gi, '').slice(0, 40) || 'evenement'
  const evenement = { id, type, mode, recu_le: new Date(maintenant).toISOString(), donnees }
  // GITHUB_API ne sert qu'à un essai local (un faux GitHub) ; sinon, l'interface officielle de GitHub.
  const api = /^https:\/\/|^http:\/\/127\.0\.0\.1[:/]/.test(String(env.GITHUB_API ?? '')) ? String(env.GITHUB_API).replace(/\/+$/, '') : 'https://api.github.com'
  const reponse = await appeler(`${api}/repos/${env.DEPOT}/dispatches`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${env.GITHUB_DECLENCHEUR}`, Accept: 'application/vnd.github+json', 'X-GitHub-Api-Version': '2022-11-28', 'User-Agent': 'point-entree-automatisation', 'Content-Type': 'application/json' },
    body: JSON.stringify({ event_type: 'evenement', client_payload: evenement }),
  }).catch(() => null)
  // Journal des déclenchements (onglet « Logs » du Worker sur Cloudflare) : jamais le contenu, seulement le résumé.
  console.log(JSON.stringify({ declenchement: id, type, mode, lance: reponse?.status === 204, a: evenement.recu_le }))
  if (reponse?.status !== 204) return json({ ok: false, erreur: 'L’automatisation n’a pas pu être lancée. Réessayez dans un instant.' }, 502, cors)

  await ecrire(memoire, `vu/${await empreinte(id)}`, '1', DUREE_DEDOUBLONNAGE)
  return mode === 'formulaire' ? reponseFormulaire(requete, env, cors) : json({ ok: true, id }, 202, cors)
}

/** Vérifie « t=<secondes>,v1=<hexadécimal> » : HMAC-SHA256 de "<t>.<corps>" avec le secret, pas plus vieux que 5 minutes. */
export async function signatureValide(entete, corps, secret, maintenant = Date.now()) {
  const parties = Object.fromEntries(String(entete).split(',').map((p) => p.trim().split('=')).filter((p) => p.length === 2))
  const t = Number(parties.t)
  if (!Number.isFinite(t) || !parties.v1 || Math.abs(maintenant / 1000 - t) > ECART_MAX_SECONDES) return false
  return egaux(parties.v1, await signer(`${t}.${corps}`, secret))
}

/** HMAC-SHA256 en hexadécimal (pour signer un appel depuis votre serveur). */
export async function signer(texte, secret) {
  const cle = await crypto.subtle.importKey('raw', new TextEncoder().encode(secret), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
  return hex(await crypto.subtle.sign('HMAC', cle, new TextEncoder().encode(texte)))
}

/** Comparaison en temps constant (la durée ne révèle rien du secret). */
export function egaux(a, b) {
  const x = new TextEncoder().encode(String(a))
  const y = new TextEncoder().encode(String(b))
  let difference = x.length ^ y.length
  for (let i = 0; i < Math.max(x.length, y.length); i++) difference |= (x[i] ?? 0) ^ (y[i] ?? 0)
  return difference === 0
}

/** Les données de l'événement : JSON, ou champs d'un formulaire. Des textes courts, 50 champs au plus. */
export function lireDonnees(corps, type) {
  let brut
  try {
    if (/json/i.test(type)) brut = corps ? JSON.parse(corps) : {}
    else brut = Object.fromEntries(new URLSearchParams(corps))
  } catch {
    return null
  }
  if (!brut || typeof brut !== 'object' || Array.isArray(brut)) return null
  const propres = {}
  for (const [cle, valeur] of Object.entries(brut).slice(0, 50)) {
    const nom = String(cle).replace(/[^\p{L}\p{N}_ .-]/gu, '').slice(0, 60)
    if (nom) propres[nom] = typeof valeur === 'string' ? valeur.slice(0, 4000) : valeur
  }
  return propres
}

function reponseFormulaire(requete, env, cors) {
  // Un formulaire envoyé sans JavaScript revient sur la page de remerciement (ou la page d'origine).
  if (/text\/html/.test(requete.headers.get('Accept') ?? '') && !/json/.test(requete.headers.get('Content-Type') ?? '')) {
    const retour = env.PAGE_MERCI || requete.headers.get('Referer') || '/'
    return new Response(null, { status: 303, headers: { Location: retour, ...cors } })
  }
  return json({ ok: true }, 202, cors)
}

const json = (objet, statut, entetes = {}) => new Response(JSON.stringify(objet), { status: statut, headers: { 'Content-Type': 'application/json; charset=utf-8', ...entetes } })
const hex = (octets) => [...new Uint8Array(octets)].map((o) => o.toString(16).padStart(2, '0')).join('')
const empreinte = async (texte) => hex(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(String(texte))))
// La mémoire du point d'entrée (Workers KV) : des empreintes, jamais le contenu d'un événement.
const lire = (memoire, nom) => memoire.get(nom)
const ecrire = (memoire, nom, valeur, secondes) => memoire.put(nom, valeur, { expirationTtl: Math.max(60, secondes) })
