// Publication d'un projet sur Cloudflare Pages (adresse gratuite en pages.dev), en un clic depuis Kekeli.
//
// Le site publié est la dernière sauvegarde du projet, envoyée par le site Kekeli après chaque demande :
// aucun ordinateur virtuel n'est rallumé pour publier. Le Runner, seul à détenir la clé Cloudflare,
// envoie les fichiers par l'interface « Direct Upload » de Cloudflare Pages (la même que l'outil officiel
// wrangler) : jeton d'envoi, fichiers manquants, envoi, puis déploiement avec la liste des fichiers.
import { execFile } from 'node:child_process'
import { mkdtemp, readdir, readFile, rm, stat, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { promisify } from 'node:util'
import { hash as blake3 } from 'blake3-wasm'

const API = 'https://api.cloudflare.com/client/v4'
// Limites de l'offre gratuite de Cloudflare Pages (page officielle des limites, 5 septembre 2026).
export const FICHIERS_MAXIMUM = 20000
export const TAILLE_MAXIMALE = 25 * 1024 * 1024
const LOT_MAXIMAL = 40 * 1024 * 1024
const IGNORES = new Set(['node_modules', '.git', '.kekeli'])

const TYPES = {
  html: 'text/html', htm: 'text/html', css: 'text/css', js: 'application/javascript', mjs: 'application/javascript',
  json: 'application/json', txt: 'text/plain', md: 'text/markdown', xml: 'application/xml', svg: 'image/svg+xml',
  png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', gif: 'image/gif', webp: 'image/webp', avif: 'image/avif', ico: 'image/x-icon',
  woff: 'font/woff', woff2: 'font/woff2', ttf: 'font/ttf', otf: 'font/otf',
  mp4: 'video/mp4', webm: 'video/webm', mp3: 'audio/mpeg', ogg: 'audio/ogg', wav: 'audio/wav', pdf: 'application/pdf',
}

/** Empreinte d'un fichier, calculée exactement comme l'outil officiel de Cloudflare (blake3, 32 caractères). */
export function empreinte(chemin, octets) {
  const extension = path.extname(chemin).slice(1)
  return blake3(Buffer.from(octets).toString('base64') + extension).toString('hex').slice(0, 32)
}

export function typeDeContenu(chemin) {
  return TYPES[path.extname(chemin).slice(1).toLowerCase()] ?? 'application/octet-stream'
}

/** Fichiers à publier, lus dans un dossier : chemins avec « / », sans fichiers cachés ni dossiers techniques. */
export async function fichiersDuDossier(dossier) {
  const fichiers = []
  async function parcourir(relatif) {
    for (const entree of await readdir(path.join(dossier, relatif), { withFileTypes: true })) {
      if (entree.name.startsWith('.') || IGNORES.has(entree.name)) continue
      const chemin = relatif ? `${relatif}/${entree.name}` : entree.name
      if (entree.isDirectory()) await parcourir(chemin)
      else if (entree.isFile()) fichiers.push(chemin)
      if (fichiers.length > FICHIERS_MAXIMUM) throw new Error(`Le site contient plus de ${FICHIERS_MAXIMUM} fichiers : trop pour une publication gratuite.`)
    }
  }
  await parcourir('')
  const resultat = []
  for (const chemin of fichiers) {
    const taille = (await stat(path.join(dossier, chemin))).size
    if (taille > TAILLE_MAXIMALE) throw new Error(`Le fichier ${chemin} dépasse 25 Mo : trop gros pour une publication gratuite.`)
    resultat.push({ chemin, octets: await readFile(path.join(dossier, chemin)) })
  }
  return resultat
}

/** Ouvre une sauvegarde (archive .tar.gz) dans un dossier temporaire et renvoie ses fichiers. */
export async function fichiersDeLaSauvegarde(octets) {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-publication-'))
  try {
    await writeFile(path.join(dossier, 'sauvegarde.tar.gz'), octets)
    await promisify(execFile)('mkdir', ['-p', path.join(dossier, 'site')])
    await promisify(execFile)('tar', ['-xzf', path.join(dossier, 'sauvegarde.tar.gz'), '-C', path.join(dossier, 'site'), '--no-same-owner', '--no-same-permissions'])
    return await fichiersDuDossier(path.join(dossier, 'site'))
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
}

/** Lots d'envoi d'au plus 40 Mo, en commençant par les plus gros fichiers. */
export function lots(fichiers) {
  const tries = [...fichiers].sort((a, b) => b.octets.length - a.octets.length)
  const resultat = []
  for (const fichier of tries) {
    const lot = resultat.find((l) => l.taille + fichier.octets.length <= LOT_MAXIMAL)
    if (lot) {
      lot.fichiers.push(fichier)
      lot.taille += fichier.octets.length
    } else resultat.push({ fichiers: [fichier], taille: fichier.octets.length })
  }
  return resultat.map((l) => l.fichiers)
}

/**
 * Publie les fichiers sur Cloudflare Pages et renvoie l'adresse du site en ligne.
 * `jeton` : clé Cloudflare (droit « Cloudflare Pages : modifier ») ; `compte` : numéro du compte.
 */
export async function publier({ fichiers, nom, compte, jeton, telecharger = fetch }) {
  if (!fichiers.some((f) => f.chemin === 'index.html')) throw new Error("Le projet n'a pas de page d'accueil (index.html) : rien à publier.")

  const appeler = async (adresse, { methode = 'GET', corps, cle = jeton, json = true } = {}) => {
    const reponse = await telecharger(adresse.startsWith('http') ? adresse : `${API}${adresse}`, {
      method: methode,
      headers: { Authorization: `Bearer ${cle}`, ...(json && corps !== undefined ? { 'Content-Type': 'application/json' } : {}) },
      body: corps === undefined ? undefined : json ? JSON.stringify(corps) : corps,
      signal: AbortSignal.timeout(120000),
    })
    const donnees = await reponse.json().catch(() => ({}))
    if (!reponse.ok || donnees.success === false) {
      const message = (donnees.errors ?? []).map((e) => e.message).join(' ; ') || `code ${reponse.status}`
      const erreur = new Error(`Cloudflare a refusé la publication (${message}).`)
      erreur.statut = reponse.status
      throw erreur
    }
    return donnees.result
  }

  // 1. Le projet chez Cloudflare (créé à la première publication).
  let projet
  try {
    projet = await appeler(`/accounts/${compte}/pages/projects/${nom}`)
  } catch (erreur) {
    if (erreur.statut !== 404) throw erreur
    projet = await appeler(`/accounts/${compte}/pages/projects`, { methode: 'POST', corps: { name: nom, production_branch: 'main' } })
  }

  // 2. Les fichiers : seuls ceux que Cloudflare n'a pas déjà sont envoyés.
  const manifeste = {}
  const parEmpreinte = new Map()
  for (const fichier of fichiers) {
    const cle = empreinte(fichier.chemin, fichier.octets)
    manifeste[`/${fichier.chemin}`] = cle
    parEmpreinte.set(cle, fichier)
  }
  const { jwt } = await appeler(`/accounts/${compte}/pages/projects/${nom}/upload-token`)
  const manquantes = new Set(await appeler('/pages/assets/check-missing', { methode: 'POST', corps: { hashes: [...parEmpreinte.keys()] }, cle: jwt }))
  for (const lot of lots([...parEmpreinte].filter(([cle]) => manquantes.has(cle)).map(([, fichier]) => fichier))) {
    await appeler('/pages/assets/upload', {
      methode: 'POST',
      cle: jwt,
      corps: lot.map((f) => ({ key: empreinte(f.chemin, f.octets), value: Buffer.from(f.octets).toString('base64'), metadata: { contentType: typeDeContenu(f.chemin) }, base64: true })),
    })
  }
  await appeler('/pages/assets/upsert-hashes', { methode: 'POST', corps: { hashes: [...parEmpreinte.keys()] }, cle: jwt }).catch(() => null)

  // 3. Le déploiement, sur la branche principale : c'est l'adresse publique du site.
  const formulaire = new FormData()
  formulaire.append('manifest', JSON.stringify(manifeste))
  formulaire.append('branch', 'main')
  const deploiement = await appeler(`/accounts/${compte}/pages/projects/${nom}/deployments`, { methode: 'POST', corps: formulaire, json: false })

  return {
    adresse: `https://${projet.subdomain ?? `${nom}.pages.dev`}`,
    deploiement: deploiement?.url ?? null,
    fichiers: fichiers.length,
    envoyes: manquantes.size,
  }
}
