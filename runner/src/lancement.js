// « Lancer maintenant » : l'automatisation du client tourne une fois dans son ordinateur virtuel, avec ses Variables
// secrètes (ou, sans clé de modèle, l'essai gratuit par le relais du Runner : la clé de Kekeli n'entre jamais dans
// l'ordinateur). Le journal lisible (lignes « KEKELI_JOURNAL {…} » écrites par src/journal.js) est renvoyé au site
// étape par étape. L'automatisation tourne ensuite chaque jour sur le dépôt GitHub du client, jamais chez Kekeli.

/** Durée maximale d'un lancement à la main. */
export const DUREE_MAXIMALE_MS = 5 * 60 * 1000

/** Nombre maximal de lignes de journal renvoyées au site. */
export const LIGNES_MAXIMALES = 60

const STATUTS = new Set(['ok', 'erreur', 'attention', 'info'])

/** Une ligne de journal propre (étape, statut, message), ou null si la ligne n'en est pas une. */
export function ligneDeJournal(texte) {
  const trouve = /^KEKELI_JOURNAL (\{.*\})\s*$/.exec(String(texte).trim())
  if (!trouve) return null
  try {
    const l = JSON.parse(trouve[1])
    const etape = String(l.etape ?? '').slice(0, 80).trim()
    if (!etape) return null
    return { etape, statut: STATUTS.has(l.statut) ? l.statut : 'info', message: masquerSecrets(String(l.message ?? '').slice(0, 2000)) }
  } catch {
    return null
  }
}

/** La commande de lancement écrite par l'agent dans le fichier LANCEMENT (une ligne simple), sinon « npm start ». */
export function commandeDeLancement(contenu) {
  const ligne = String(contenu ?? '').split('\n').map((l) => l.trim()).find((l) => l && !l.startsWith('#'))
  // Une seule commande simple : ni enchaînement, ni redirection, ni substitution.
  if (!ligne || ligne.length > 120 || /[;&|`$<>\\]/.test(ligne)) return 'npm start --silent'
  return ligne === 'npm start' ? 'npm start --silent' : ligne
}

/**
 * Rien de secret ne sort dans le journal : les valeurs des Variables secrètes et tout ce qui ressemble à une clé sont
 * masqués.
 */
export function masquerSecrets(texte, valeurs = []) {
  let propre = String(texte)
  for (const valeur of valeurs) {
    if (typeof valeur === 'string' && valeur.length >= 6) propre = propre.split(valeur).join('••••')
  }
  return propre.replace(/\b(sk|re|rk|pk|gsk|xai|AIza)[-_A-Za-z0-9]{12,}/g, '••••').replace(/\bBearer\s+[-._A-Za-z0-9]{8,}/g, 'Bearer ••••')
}

/** Le résumé du lancement, en français simple, pour le site. */
export function resumeDuLancement({ code, lignes, depasse }) {
  if (depasse) return { ok: false, resume: "L'automatisation a dépassé 5 minutes : elle a été arrêtée." }
  const erreur = lignes.find((l) => l.statut === 'erreur')
  if (code === 0 && !erreur) {
    const attention = lignes.find((l) => l.statut === 'attention')
    return { ok: true, resume: attention ? `Lancement terminé, avec un point à régler : ${attention.message}` : 'Lancement réussi.' }
  }
  return { ok: false, resume: erreur ? `Le lancement s'est arrêté à l'étape « ${erreur.etape} » : ${erreur.message}` : "Le lancement s'est arrêté sur une erreur." }
}

/**
 * Lance l'automatisation dans l'ordinateur : `executer(commande, { envs, onStdout, timeoutMs })` est la commande de
 * l'ordinateur (E2B), `envoyer` renvoie chaque ligne au site. Renvoie le résumé.
 */
export async function lancerAutomatisation({ executer, lireLancement, secrets = {}, essai = {}, envoyer = async () => {} }) {
  const envs = { ...(secrets.MODELE_API_CLE ? {} : essai), ...secrets, KEKELI_LANCEMENT: '1', NODE_ENV: 'production' }
  const valeurs = Object.values(secrets)
  const lignes = []
  let reste = ''
  const lire = (morceau) => {
    reste += morceau
    const parties = reste.split('\n')
    reste = parties.pop()
    for (const partie of parties) {
      const ligne = ligneDeJournal(partie)
      if (!ligne || lignes.length >= LIGNES_MAXIMALES) continue
      ligne.message = masquerSecrets(ligne.message, valeurs)
      lignes.push(ligne)
      envoyer({ type: 'journal', ...ligne }).catch(() => {})
    }
  }
  const commande = commandeDeLancement(await lireLancement().catch(() => ''))
  let code = 1
  let depasse = false
  try {
    const sortie = await executer(commande, { envs, onStdout: lire, timeoutMs: DUREE_MAXIMALE_MS })
    code = sortie?.exitCode ?? 0
  } catch (erreur) {
    code = erreur?.exitCode ?? 1
    depasse = /timeout|deadline|dépass/i.test(String(erreur?.message ?? ''))
  }
  lire('\n')
  const resume = resumeDuLancement({ code, lignes, depasse })
  await envoyer({ type: 'lancement', ok: resume.ok, resume: resume.resume, lignes })
  return { ...resume, lignes }
}
