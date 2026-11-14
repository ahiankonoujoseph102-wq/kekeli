// L'événement qui a lancé l'automatisation, quand elle n'est pas lancée à heure fixe :
// - sur GitHub, l'événement « repository_dispatch » envoyé par le point d'entrée (fichier GITHUB_EVENT_PATH) ;
// - dans Kekeli, « Lancer maintenant » avec l'événement d'essai (fichier evenement-essai.json, données inventées).
// Renvoie null pour un lancement à heure fixe ou à la main.
import { readFileSync } from 'node:fs'

export function lireEvenement({ env = process.env, lire = (chemin) => readFileSync(chemin, 'utf8') } = {}) {
  try {
    if (env.KEKELI_EVENEMENT_ESSAI === '1') return { ...propre(JSON.parse(lire('evenement-essai.json'))), essai: true }
    if (env.GITHUB_EVENT_NAME === 'repository_dispatch' && env.GITHUB_EVENT_PATH) return { ...propre(JSON.parse(lire(env.GITHUB_EVENT_PATH)).client_payload ?? {}), essai: false }
  } catch {
    return null
  }
  return null
}

function propre(e) {
  return {
    id: String(e.id ?? ''),
    type: String(e.type ?? 'evenement'),
    recu_le: String(e.recu_le ?? new Date().toISOString()),
    donnees: e.donnees && typeof e.donnees === 'object' && !Array.isArray(e.donnees) ? e.donnees : {},
  }
}
