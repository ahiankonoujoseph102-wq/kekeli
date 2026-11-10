// Sauvegarde des fichiers d'un projet dans le site Kekeli, et remise en place dans un nouvel ordinateur.
//
// Après chaque demande, le Runner compresse le dossier du projet dans l'ordinateur virtuel et l'envoie
// au site par morceaux signés. Si l'ordinateur d'un projet ne peut pas être réveillé, un nouvel
// ordinateur est créé et cette sauvegarde y est remise : un projet ne repart jamais d'un dossier vide.
import { entetesSignes } from './signature.js'

export const TAILLE_MORCEAU = 4 * 1024 * 1024
export const TAILLE_MAXIMALE = 50 * 1024 * 1024
const ARCHIVE = '/tmp/kekeli-sauvegarde.tar.gz'

/** Découpe des octets en morceaux d'au plus « taille » octets. */
export function morceaux(octets, taille = TAILLE_MORCEAU) {
  const liste = []
  for (let debut = 0; debut < octets.length; debut += taille) liste.push(octets.subarray(debut, debut + taille))
  return liste.length ? liste : [octets.subarray(0, 0)]
}

/** Compresse le dossier du projet dans l'ordinateur et renvoie les octets de l'archive. */
export async function archiver(sandbox, dossier) {
  // Un dépôt Git (mode développeur) : seulement les fichiers suivis et les nouveaux fichiers non ignorés
  // (jamais vendor, node_modules, .env ni les fichiers construits, que le .gitignore du projet écarte).
  await sandbox.commands.run(
    `if [ -d ${dossier}/.git ]; then cd ${dossier} && git ls-files -z -co --exclude-standard | tar --null -T - --ignore-failed-read -czf ${ARCHIVE}; ` +
      `else tar -czf ${ARCHIVE} --exclude=./node_modules --exclude=./.git --exclude=./.cache -C ${dossier} .; fi`,
    { timeoutMs: 120000 },
  )
  const taille = Number((await sandbox.commands.run(`stat -c %s ${ARCHIVE}`)).stdout.trim())
  if (!Number.isFinite(taille) || taille > TAILLE_MAXIMALE) {
    throw new Error(`Sauvegarde trop grande (${Math.round(taille / 1048576)} Mo, au plus ${TAILLE_MAXIMALE / 1048576} Mo).`)
  }
  const octets = await sandbox.files.read(ARCHIVE, { format: 'bytes' })
  await sandbox.commands.run(`rm -f ${ARCHIVE}`)
  return Buffer.from(octets)
}

/** Envoie l'archive au site Kekeli, morceau par morceau. */
export async function envoyer(url, secret, octets, { essais = 3 } = {}) {
  const liste = morceaux(octets)
  for (let i = 0; i < liste.length; i++) {
    const adresse = `${url}${url.includes('?') ? '&' : '?'}partie=${i + 1}&parties=${liste.length}`
    for (let essai = 1; ; essai++) {
      try {
        const reponse = await fetch(adresse, {
          method: 'POST',
          headers: { 'Content-Type': 'application/gzip', Accept: 'application/json', ...entetesSignes(secret, liste[i]) },
          body: liste[i],
        })
        if (reponse.ok) break
        throw new Error(`le site a répondu ${reponse.status}`)
      } catch (erreur) {
        if (essai >= essais) throw new Error(`Envoi de la sauvegarde impossible : ${erreur.message}`)
        await new Promise((r) => setTimeout(r, 1000 * essai))
      }
    }
  }
  return { taille: octets.length, parties: liste.length }
}

/** Télécharge la dernière sauvegarde du projet ; renvoie null s'il n'y en a pas. */
export async function telecharger(url, secret) {
  const reponse = await fetch(url, { headers: { Accept: 'application/gzip', ...entetesSignes(secret, '') } })
  if (reponse.status === 404) return null
  if (!reponse.ok) throw new Error(`Téléchargement de la sauvegarde impossible (code ${reponse.status}).`)
  return Buffer.from(await reponse.arrayBuffer())
}

/** Télécharge un fichier du site Kekeli (par exemple une photo du client), avec une requête signée. */
export async function telechargerFichier(url, secret) {
  const reponse = await fetch(url, { headers: entetesSignes(secret, ''), signal: AbortSignal.timeout(60000) })
  if (!reponse.ok) throw new Error(`Téléchargement impossible (code ${reponse.status}).`)
  return Buffer.from(await reponse.arrayBuffer())
}

/** Remet une sauvegarde dans le dossier du projet d'un ordinateur. */
export async function remettre(sandbox, dossier, octets) {
  await sandbox.files.write(ARCHIVE, octets.buffer.slice(octets.byteOffset, octets.byteOffset + octets.byteLength))
  await sandbox.commands.run(`mkdir -p ${dossier} && tar -xzf ${ARCHIVE} -C ${dossier} && rm -f ${ARCHIVE}`, { timeoutMs: 120000 })
}
