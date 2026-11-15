// Réserve de Cloudflare pour les images des clients (15 novembre 2026) : le 15 novembre, des essais d'images avaient vidé
// les 10 000 neurones gratuits du jour. Les scripts d'essai comptent désormais leurs appels à Cloudflare Workers AI par
// jour UTC (le jour de Cloudflare) et s'arrêtent à ESSAIS_CLOUDFLARE_MAX appels (20 par défaut, environ 1 200 neurones
// pour FLUX.1 schnell) : le reste de l'offre du jour reste aux clients.
import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import os from 'node:os'
import path from 'node:path'

export const ESSAIS_CLOUDFLARE_MAX = Number(process.env.ESSAIS_CLOUDFLARE_MAX ?? 20)

function fichier() {
  return path.join(os.tmpdir(), `kekeli-essais-cloudflare-${new Date().toISOString().slice(0, 10)}.json`)
}

export function appelsCloudflareDuJour() {
  try {
    return existsSync(fichier()) ? Number(JSON.parse(readFileSync(fichier(), 'utf8')).appels) || 0 : 0
  } catch {
    return 0
  }
}

/** Compte un appel d'essai à Cloudflare ; refuse (erreur) quand la part des essais du jour est utilisée. */
export function prendreAppelCloudflare() {
  const appels = appelsCloudflareDuJour()
  if (appels >= ESSAIS_CLOUDFLARE_MAX) {
    throw new Error(`Réserve de Cloudflare : ${appels} appels d'essai aujourd'hui (limite ${ESSAIS_CLOUDFLARE_MAX}), le reste est gardé pour les images des clients.`)
  }
  writeFileSync(fichier(), JSON.stringify({ appels: appels + 1 }))
}
