// Outils déposés par le Runner dans chaque ordinateur virtuel.
//
// OpenCode télécharge l'outil de recherche « ripgrep » depuis GitHub au premier usage. Comme
// l'ordinateur n'a accès qu'aux sites autorisés (GitHub n'en fait pas partie), le Runner le
// télécharge lui-même une fois, vérifie son empreinte, puis le dépose dans chaque ordinateur.
import { createHash } from 'node:crypto'

export const RIPGREP = {
  version: '15.1.0',
  url: 'https://github.com/BurntSushi/ripgrep/releases/download/15.1.0/ripgrep-15.1.0-x86_64-unknown-linux-musl.tar.gz',
  // Empreinte publiée par l'auteur de ripgrep à côté de l'archive.
  sha256: '1c9297be4a084eea7ecaedf93eb03d058d6faae29bbc57ecdaf5063921491599',
  dossier: 'ripgrep-15.1.0-x86_64-unknown-linux-musl',
}

const DESTINATION = '/home/user/.cache/opencode/bin/rg'
let archiveEnCache = null

/** Archive de ripgrep, téléchargée une seule fois et refusée si son empreinte ne correspond pas. */
export async function archiveRipgrep({ telecharger = fetch, attendu = RIPGREP.sha256 } = {}) {
  if (archiveEnCache) return archiveEnCache
  const reponse = await telecharger(RIPGREP.url)
  if (!reponse.ok) throw new Error(`téléchargement de ripgrep impossible (code ${reponse.status})`)
  const octets = Buffer.from(await reponse.arrayBuffer())
  const empreinte = createHash('sha256').update(octets).digest('hex')
  if (empreinte !== attendu) throw new Error("l'empreinte de ripgrep ne correspond pas : archive refusée")
  archiveEnCache = octets
  return octets
}

/** Dépose ripgrep dans l'ordinateur s'il n'y est pas encore. */
export async function installerRipgrep(sandbox) {
  const present = (await sandbox.commands.run(`test -x ${DESTINATION} && echo oui || echo non`)).stdout.trim()
  if (present === 'oui') return false
  const octets = await archiveRipgrep()
  await sandbox.files.write('/tmp/kekeli-rg.tgz', octets.buffer.slice(octets.byteOffset, octets.byteOffset + octets.byteLength))
  await sandbox.commands.run(
    `mkdir -p $(dirname ${DESTINATION}) && tar -xzf /tmp/kekeli-rg.tgz -C /tmp && cp /tmp/${RIPGREP.dossier}/rg ${DESTINATION} && chmod +x ${DESTINATION} && rm -rf /tmp/kekeli-rg.tgz /tmp/${RIPGREP.dossier}`,
  )
  return true
}
