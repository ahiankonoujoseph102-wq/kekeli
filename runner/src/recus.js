// Reçus d'abonnement : la structure du reçu est écrite par le site (aucun modèle, donc rien d'inventé) ; le moteur de
// documents de Kekeli (python-docx, LibreOffice) la met en page en PDF d'une page dans l'atelier E2B isolé, sans clé
// et sans Internet. Le PDF et l'image de sa page reviennent au site dans la réponse.
import { randomUUID } from 'node:crypto'
import { FICHIER_RENDU } from './documents/index.js'

/** Une structure de reçu acceptable : un document court, des blocs connus, rien d'autre. */
export function structureRecuValide(s) {
  if (!s || typeof s !== 'object' || s.type !== 'document' || typeof s.titre !== 'string' || !Array.isArray(s.blocs)) return false
  if (s.blocs.length > 30) return false
  return s.blocs.every((b) => b && ['titre1', 'titre2', 'paragraphe', 'tableau', 'liste'].includes(b.type))
}

/** @returns {Promise<{ ok: boolean, pdf?: string, apercu?: string, pages?: number }>} */
export async function rendreRecu(structure, { atelier }) {
  const sandbox = await atelier.obtenir('recus-kekeli')
  const dossier = `/home/user/recus/${randomUUID()}`
  await sandbox.commands.run(`mkdir -p ${dossier}`)
  try {
    await sandbox.files.write(`${dossier}/structure.json`, JSON.stringify(structure))
    const fait = await sandbox.commands.run(`python3 ${FICHIER_RENDU} rendre ${dossier}/structure.json ${dossier} pdf`, { timeoutMs: 180000 })
    const rendu = JSON.parse(String(fait.stdout).trim().split('\n').pop())
    const pdf = await sandbox.files.read(`${dossier}/${rendu.fichiers.pdf}`, { format: 'bytes' })
    const apercu = rendu.apercus?.[0] ? await sandbox.files.read(`${dossier}/${rendu.apercus[0]}`, { format: 'bytes' }) : null
    return { ok: true, pdf: Buffer.from(pdf).toString('base64'), apercu: apercu ? Buffer.from(apercu).toString('base64') : null, pages: rendu.pages ?? 1 }
  } finally {
    await sandbox.commands.run(`rm -rf ${dossier}`).catch(() => {})
  }
}
