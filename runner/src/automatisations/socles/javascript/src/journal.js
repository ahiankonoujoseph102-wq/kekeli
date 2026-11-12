// Journal lisible de l'automatisation : une ligne par étape, en français.
// Dans Kekeli (« Lancer maintenant »), chaque ligne est aussi donnée en JSON pour être affichée proprement.

/** Écrit une étape : statut « ok », « info », « attention » ou « erreur ». */
export function journal(etape, statut, message = '') {
  const ligne = { etape: String(etape), statut, message: String(message), a: new Date().toISOString() }
  if (process.env.KEKELI_LANCEMENT === '1') console.log('KEKELI_JOURNAL ' + JSON.stringify(ligne))
  else console.log(`[${statut}] ${ligne.etape}${ligne.message ? ' : ' + ligne.message : ''}`)
  return ligne
}
