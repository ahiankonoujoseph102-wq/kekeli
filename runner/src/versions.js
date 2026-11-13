// Versions et retour en arrière, comme Lovable et Bolt.new : le site garde chaque version dans une archive nettoyée
// (jamais .env, base d'essai ni clé) ; le Runner remet ces fichiers dans le dossier du projet de l'ordinateur.
// - « voir » : les fichiers actuels sont mis de côté (une seule fois), puis ceux de la version ; l'aperçu la montre.
// - « revenir » : les fichiers mis de côté reviennent tels quels.
// - « remettre » : restauration, ou retour à la dernière version qui fonctionnait après un travail échoué ou arrêté.
// Ce qui n'est jamais dans une version reste en place : .env, bases d'essai, dépendances (node_modules, vendor), .git.

export const ACTIONS_VERSION = ['voir', 'revenir', 'remettre']
const DOSSIERS_GARDES = ['node_modules', 'vendor', '.git', '.venv', 'venv', '.next', '.cache', '__pycache__']
const RACINE = '/home/user/.kekeli'

/** Les commandes qui remplacent les fichiers du projet par ceux d'une archive, en gardant ce qui n'est jamais versionné. */
export function commandesRemplacer(dossier, archive, racine = RACINE) {
  const garde = `${racine}/garde`
  const elagage = DOSSIERS_GARDES.map((d) => `-path ./${d}`).join(' -o ')
  const gardes = DOSSIERS_GARDES.map((d) => `! -name ${d}`).join(' ')
  return [
    `rm -rf ${garde} && mkdir -p ${garde} ${dossier}`,
    // Les fichiers à garder (secrets et bases d'essai), copiés à part avec leur chemin.
    `cd ${dossier} && find . \\( ${elagage} \\) -prune -o -type f \\( \\( -name '.env' -o -name '.env.*' \\) ! -name '.env.example' -o -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.db' -o -path '*pglite*' \\) -print0 | while IFS= read -r -d '' f; do mkdir -p "${garde}/$(dirname "$f")" && cp -p "$f" "${garde}/$f"; done`,
    `cd ${dossier} && find . -mindepth 1 -maxdepth 1 ${gardes} -exec rm -rf {} +`,
    `tar -xzf ${archive} -C ${dossier}`,
    `cp -a ${garde}/. ${dossier}/ && rm -rf ${garde}`,
  ].join(' && ')
}

/**
 * Remet une version dans l'ordinateur. « octets » : l'archive de la version (pour « voir » et « remettre »).
 * Renvoie ce qui a été fait.
 */
export async function appliquerVersion(sandbox, dossier, action, octets = null, { racine = RACINE } = {}) {
  if (!ACTIONS_VERSION.includes(action)) throw new Error('action de version inconnue')
  const deCote = `${racine}/version-actuelle.tar.gz`
  const archive = `${racine}/version.tar.gz`
  const exclus = DOSSIERS_GARDES.map((d) => `--exclude=./${d}`).join(' ')
  // Entre apostrophes : « $f » doit être lu par ce bash-là, pas par le shell qui le lance.
  const lancer = (commande) => sandbox.commands.run(`bash -c '${commande.replace(/'/g, "'\\''")}'`, { timeoutMs: 180000 })
  await lancer(`mkdir -p ${racine}`)
  if (action === 'revenir') {
    const existe = await lancer(`[ -f ${deCote} ] && echo oui || echo non`)
    if (!String(existe.stdout).includes('oui')) return { action, fait: false }
    await lancer(`${commandesRemplacer(dossier, deCote, racine)} && rm -f ${deCote}`)
    return { action, fait: true }
  }
  if (!octets) throw new Error('archive de la version absente')
  await sandbox.files.write(archive, octets.buffer.slice(octets.byteOffset, octets.byteOffset + octets.byteLength))
  // « voir » : les fichiers actuels (avec .env et la base d'essai) sont mis de côté une seule fois.
  if (action === 'voir') await lancer(`[ -f ${deCote} ] || tar -czf ${deCote} ${exclus} -C ${dossier} .`)
  await lancer(`${commandesRemplacer(dossier, archive, racine)} && rm -f ${archive}${action === 'remettre' ? ` ${deCote}` : ''}`)
  return { action, fait: true }
}
