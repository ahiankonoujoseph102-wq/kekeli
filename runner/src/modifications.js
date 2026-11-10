// « Voir les modifications » : ce qui a changé pendant une demande, pour tous les projets (sites, modèles, dépôts).
//
// Kekeli prend une photo des fichiers du projet avant la demande, dans un dépôt Git à lui, rangé à part
// (/home/user/.kekeli/historique.git) : il ne touche jamais au dépôt Git du client. Après la demande, il compare.
// Les dossiers techniques (node_modules, vendor) et les fichiers ignorés par le projet ne sont jamais comparés.

export const HISTORIQUE = '/home/user/.kekeli/historique.git'

/** Taille maximale des différences envoyées au site (au-delà, elles sont coupées). */
export const DIFF_MAXIMUM = 120000

const git = (dossier) => `git --git-dir=${HISTORIQUE} --work-tree=${dossier} -c user.name=Kekeli -c user.email=kekeli@users.noreply.github.com -c core.quotepath=off`

/** Photo des fichiers avant la demande (étiquette « avant »). */
export async function photographier(sandbox, dossier) {
  await sandbox.commands.run(
    [
      `[ -d ${HISTORIQUE} ] || (git init -q --bare ${HISTORIQUE} && printf 'node_modules/\\nvendor/\\n.git/\\n.cache/\\n' > ${HISTORIQUE}/info/exclude)`,
      `${git(dossier)} add -A`,
      `${git(dossier)} commit -q --allow-empty -m avant`,
      `${git(dossier)} tag -f avant >/dev/null`,
    ].join(' && '),
    { timeoutMs: 120000 },
  )
}

/** Lit « git diff --numstat » et « --name-status » : un fichier par ligne, avec son état et ses lignes. */
export function lireFichiers(numstat, nameStatus) {
  const etats = new Map()
  for (const ligne of String(nameStatus ?? '').split('\n').filter(Boolean)) {
    const [code, ...chemins] = ligne.split('\t')
    const chemin = chemins[chemins.length - 1]
    etats.set(chemin, code.startsWith('A') ? 'ajoute' : code.startsWith('D') ? 'supprime' : code.startsWith('R') ? 'renomme' : 'modifie')
  }
  return String(numstat ?? '')
    .split('\n')
    .filter(Boolean)
    .map((ligne) => {
      const [ajouts, suppressions, ...reste] = ligne.split('\t')
      const chemin = reste[reste.length - 1].replace(/^.*=> /, '').replace(/[{}]/g, '')
      const binaire = ajouts === '-'
      return { chemin, etat: etats.get(chemin) ?? 'modifie', ajouts: binaire ? 0 : Number(ajouts), suppressions: binaire ? 0 : Number(suppressions), binaire }
    })
}

/**
 * Ce qui a changé depuis la photo « avant » : fichiers (état, lignes ajoutées et retirées) et différences
 * (coupées au-delà de DIFF_MAXIMUM caractères). Renvoie null si aucune photo n'a été prise.
 */
export async function modifications(sandbox, dossier) {
  const lancer = (commande) => sandbox.commands.run(commande, { timeoutMs: 120000 }).then((r) => r.stdout).catch((erreur) => erreur.result?.stdout ?? null)
  const g = git(dossier)
  const pret = await lancer(`[ -d ${HISTORIQUE} ] && ${g} rev-parse -q --verify avant >/dev/null && echo oui`)
  if (!String(pret ?? '').includes('oui')) return null
  await lancer(`${g} add -A && ${g} commit -q --allow-empty -m apres`)
  const fichiers = lireFichiers(await lancer(`${g} diff --numstat avant HEAD`), await lancer(`${g} diff --name-status avant HEAD`))
  const diff = String((await lancer(`${g} diff --no-color -U2 avant HEAD | head -c ${DIFF_MAXIMUM + 1}`)) ?? '')
  return {
    fichiers: fichiers.slice(0, 300),
    nombre_fichiers: fichiers.length,
    diff: diff.slice(0, DIFF_MAXIMUM),
    tronque: diff.length > DIFF_MAXIMUM,
  }
}
