// Base de connaissances (Kekeli Knowledge, 18 novembre 2026) : le client importe ses documents dans un espace, puis
// discute avec Kekeli, qui répond à partir de ces documents et cite le nom et la page de chaque passage.
//
// Le Runner fait seulement ce qui demande une clé ou un ordinateur isolé ; le site garde les documents, les passages,
// les vecteurs et fait la recherche (sens et mots-clés) :
//   1. lire un fichier Word, PDF ou PowerPoint dans un ordinateur E2B isolé, coupé d'Internet (jamais sur le Runner) ;
//   2. transformer des passages en vecteurs (« embeddings ») avec une offre gratuite : Gemini d'abord (Google AI
//      Studio), puis Cloudflare Workers AI en secours. Jamais de modèle payant.
// Idées reprises de lastbook (licence MIT, aucun code copié) : recherche par mots-clés en plus de la recherche par
// sens, qui marche même sans modèle, et citations vérifiées (seuls les numéros des passages envoyés sont gardés).

import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { telechargerFichier } from './sauvegarde.js'
import { numeroCompteCloudflare, valeurCle } from './fournisseurs.js'

const ICI = path.dirname(fileURLToPath(import.meta.url))
const FICHIER_LECTURE = '/home/user/.kekeli/kekeli_documents.py'

/** Les formats lus dans l'ordinateur isolé (les textes simples sont lus par le site lui-même). */
export const FORMATS_BASE = ['pdf', 'docx', 'pptx']

/** Pages lues au plus dans un PDF, et caractères rendus au plus pour un fichier. */
export const PAGES_MAX = 400
export const CARACTERES_MAX = 800000

/** Les modèles de vecteurs permis : seulement des offres gratuites (vérifié ici, quel que soit le réglage). */
export const MODELES_VECTEURS = {
  'gemini/gemini-embedding-001': { fournisseur: 'gemini', dimensions: 768, parLot: 100 },
  'workersai/@cf/baai/bge-m3': { fournisseur: 'workersai', dimensions: 1024, parLot: 50 },
}

/** L'ordre par défaut : Gemini gratuit, puis Cloudflare Workers AI. */
export const ORDRE_VECTEURS = ['gemini/gemini-embedding-001', 'workersai/@cf/baai/bge-m3']

/** Textes acceptés en une demande, et longueur de chaque texte. */
export const TEXTES_MAX = 100
export const LONGUEUR_TEXTE_MAX = 4000

/** Installation légère : seulement les lecteurs de fichiers (aucun LibreOffice), aux versions exactes du moteur de documents. */
export const INSTALLATION_LECTURE = ['pip install -q --disable-pip-version-check python-docx==1.2.0 python-pptx==1.0.2 pypdf==6.21.4']

/**
 * L'atelier de lecture : un ordinateur E2B par espace de connaissances (les fichiers de deux clients ne se croisent
 * jamais), outils installés une fois, puis coupé d'Internet. Il s'efface tout seul après quelques minutes.
 */
export function creerAtelierLecture({ Sandbox, journal = console, dureeVieMs = 5 * 60 * 1000 }) {
  const cache = new Map()
  return {
    async obtenir(espace) {
      const deja = cache.get(espace)
      if (deja && (await deja.isRunning().catch(() => false))) {
        await deja.setTimeout(dureeVieMs).catch(() => {})
        return deja
      }
      const sandbox = await Sandbox.create('base', { timeoutMs: dureeVieMs, metadata: { kekeli_espace: espace, kekeli_role: 'base-de-connaissances' } })
      for (const commande of INSTALLATION_LECTURE) await sandbox.commands.run(commande, { user: 'root', timeoutMs: 300000 })
      await sandbox.files.write(FICHIER_LECTURE, await readFile(path.join(ICI, 'documents', 'rendu', 'kekeli_documents.py'), 'utf8'))
      await sandbox.updateNetwork({ denyOut: ['0.0.0.0/0'] }).catch((erreur) => journal.warn(`Réseau de l'atelier de lecture non coupé : ${erreur.message}`))
      cache.set(espace, sandbox)
      return sandbox
    },
  }
}

/** Le numéro de page (PDF) ou de diapositive (PowerPoint) d'un morceau lu ; null pour un Word (pas de pages fixes). */
export function pageDuMorceau(format, id) {
  const texte = String(id ?? '')
  if (format === 'pdf') return Number(/^page(\d+)$/.exec(texte)?.[1]) || null
  if (format === 'pptx') return Number(/^s(\d+)\./.exec(texte)?.[1]) || null
  return null
}

/**
 * Les morceaux lus, regroupés par page (ou par diapositive) et nettoyés ; un Word rend ses paragraphes dans l'ordre,
 * avec ses titres reconnus. Jamais plus de CARACTERES_MAX caractères.
 */
export function morceauxPropres(format, morceaux) {
  const sortie = []
  let total = 0
  for (const m of Array.isArray(morceaux) ? morceaux : []) {
    const texte = String(m?.texte ?? '').replace(/\u0000/g, '').replace(/[ \t]+/g, ' ').trim()
    if (!texte) continue
    const page = pageDuMorceau(format, m?.id)
    const titre = format === 'docx' && /^(Heading|Titre|Title)/i.test(String(m?.style ?? ''))
    const reste = CARACTERES_MAX - total
    if (reste <= 0) break
    const morceau = texte.slice(0, reste)
    total += morceau.length
    const precedent = sortie.at(-1)
    // Une même page de PowerPoint : ses textes vont ensemble.
    if (format === 'pptx' && precedent && precedent.page === page) precedent.texte += `\n${morceau}`
    else sortie.push({ page, texte: morceau, ...(titre ? { titre: true } : {}) })
  }
  return sortie
}

/**
 * Lit un fichier du client : téléchargé par le Runner (adresse signée du site), lu seulement dans l'atelier isolé.
 *
 * @returns {Promise<{ ok: true, morceaux: Array<{ page: number|null, texte: string }>, pages: number }>}
 */
export async function extraireFichier({ espace, url, format }, { atelier, secret, telecharger = telechargerFichier }) {
  if (!FORMATS_BASE.includes(format)) throw Object.assign(new Error('format non pris en charge'), { raison: 'format' })
  const octets = await telecharger(url, secret)
  const sandbox = await atelier.obtenir(espace)
  const dossier = `/home/user/base/${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
  const source = `${dossier}/source.${format}`
  try {
    await sandbox.commands.run(`mkdir -p ${dossier}`)
    await sandbox.files.write(source, octets.buffer.slice(octets.byteOffset, octets.byteOffset + octets.byteLength))
    const lu = await sandbox.commands.run(`python3 ${FICHIER_LECTURE} extraire ${source} ${PAGES_MAX}`, { timeoutMs: 180000 })
    let donnees = null
    try {
      donnees = JSON.parse(String(lu.stdout).trim().split('\n').pop())
    } catch {
      donnees = null
    }
    if (!donnees) throw Object.assign(new Error('fichier illisible'), { raison: 'illisible' })
    const morceaux = morceauxPropres(format, donnees.morceaux)
    const pages = morceaux.reduce((max, m) => Math.max(max, m.page ?? 0), 0)
    return { ok: true, morceaux, pages }
  } catch (erreur) {
    // Un fichier abîmé ou protégé par un mot de passe : la commande échoue.
    if (!erreur.raison) erreur.raison = 'illisible'
    throw erreur
  } finally {
    // Le fichier ne reste jamais dans l'ordinateur.
    await sandbox.commands.run(`rm -rf ${dossier}`).catch(() => {})
  }
}

/** Les modèles de vecteurs demandés par le site, filtrés : seulement ceux de la liste fermée, dans l'ordre reçu. */
export function modelesPermis(demandes) {
  const liste = (Array.isArray(demandes) && demandes.length ? demandes : ORDRE_VECTEURS).map(String).filter((m) => MODELES_VECTEURS[m])
  return [...new Set(liste)]
}

/** La clé d'un modèle de vecteurs est-elle présente ? (jamais la valeur, seulement sa présence) */
export function modeleDisponible(modele, env = process.env) {
  const d = MODELES_VECTEURS[modele]
  if (!d) return false
  if (d.fournisseur === 'gemini') return Boolean(valeurCle('GEMINI_API_KEY', env))
  return Boolean(valeurCle('CLOUDFLARE_API_TOKEN', env)) && Boolean(numeroCompteCloudflare(env))
}

async function vecteursGemini(modele, textes, type, { appeler, env }) {
  const nom = modele.split('/').slice(1).join('/')
  const reponse = await appeler(`https://generativelanguage.googleapis.com/v1beta/models/${nom}:batchEmbedContents`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'x-goog-api-key': valeurCle('GEMINI_API_KEY', env) },
    body: JSON.stringify({
      requests: textes.map((t) => ({ model: `models/${nom}`, content: { parts: [{ text: t }] }, taskType: type === 'question' ? 'RETRIEVAL_QUERY' : 'RETRIEVAL_DOCUMENT', outputDimensionality: MODELES_VECTEURS[modele].dimensions })),
    }),
    signal: AbortSignal.timeout(60000),
  })
  if (!reponse.ok) return { ok: false, limite: reponse.status === 429, statut: reponse.status }
  const donnees = await reponse.json()
  const vecteurs = (donnees.embeddings ?? []).map((e) => e.values)
  return { ok: vecteurs.length === textes.length, vecteurs }
}

async function vecteursWorkersAi(modele, textes, { appeler, env }) {
  const nom = modele.split('/').slice(1).join('/')
  const reponse = await appeler(`https://api.cloudflare.com/client/v4/accounts/${numeroCompteCloudflare(env)}/ai/run/${nom}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${valeurCle('CLOUDFLARE_API_TOKEN', env)}` },
    body: JSON.stringify({ text: textes }),
    signal: AbortSignal.timeout(60000),
  })
  if (!reponse.ok) return { ok: false, limite: reponse.status === 429, statut: reponse.status }
  const donnees = await reponse.json()
  const vecteurs = donnees?.result?.data ?? []
  return { ok: donnees?.success === true && vecteurs.length === textes.length, vecteurs }
}

/** Un vecteur ramené à la longueur 1 (le site compare ensuite par simple produit scalaire). */
export function normaliser(vecteur) {
  const v = (Array.isArray(vecteur) ? vecteur : []).map(Number)
  const longueur = Math.sqrt(v.reduce((s, x) => s + x * x, 0)) || 1
  return v.map((x) => Math.round((x / longueur) * 1e6) / 1e6)
}

/**
 * Les vecteurs de quelques textes, avec le premier modèle disponible de la liste (gratuits seulement). Pour une
 * question, le site demande le modèle des documents de l'espace : les vecteurs de deux modèles ne se comparent pas.
 *
 * @returns {Promise<{ ok: boolean, modele?: string, vecteurs?: number[][], requetes?: number, refus?: string[] }>}
 */
export async function vecteurs({ textes, type = 'document', modeles }, { appeler = fetch, env = process.env, misesDeCote = null } = {}) {
  const propres = (Array.isArray(textes) ? textes : []).slice(0, TEXTES_MAX).map((t) => String(t ?? '').slice(0, LONGUEUR_TEXTE_MAX).trim() || '.')
  if (!propres.length) return { ok: false, erreur: 'aucun texte' }
  const refus = []
  for (const modele of modelesPermis(modeles)) {
    if (!modeleDisponible(modele, env)) continue
    if (misesDeCote && !misesDeCote.estDisponible(`vecteurs/${modele}`)) continue
    const d = MODELES_VECTEURS[modele]
    const tous = []
    let requetes = 0
    let echec = null
    for (let i = 0; i < propres.length; i += d.parLot) {
      const lot = propres.slice(i, i + d.parLot)
      const r = await (d.fournisseur === 'gemini' ? vecteursGemini(modele, lot, type, { appeler, env }) : vecteursWorkersAi(modele, lot, { appeler, env })).catch(() => ({ ok: false }))
      requetes++
      if (!r.ok) {
        echec = r
        break
      }
      tous.push(...r.vecteurs.map(normaliser))
    }
    if (!echec) return { ok: true, modele, dimensions: tous[0]?.length ?? 0, vecteurs: tous, requetes, refus }
    refus.push(modele)
    // Une limite atteinte : ce modèle est mis de côté une heure, le suivant prend le relais.
    if (echec.limite && misesDeCote?.mettreDeCote) misesDeCote.mettreDeCote(`vecteurs/${modele}`, 3600000)
  }
  return { ok: false, erreur: 'aucun_fournisseur', refus }
}
