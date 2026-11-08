// Signature des messages échangés entre le site Kekeli (Laravel) et le Runner.
//
// Chaque message porte deux en-têtes :
//   X-Kekeli-Horodatage : l'heure d'envoi, en secondes depuis 1970 ;
//   X-Kekeli-Signature  : « sha256= » suivi du HMAC-SHA256 de « horodatage.corps »
//                          calculé avec le secret partagé KEKELI_RUNNER_SECRET.
// Un message trop ancien (plus de cinq minutes) est refusé, pour empêcher qu'on le rejoue.
import { createHmac, timingSafeEqual } from 'node:crypto'

export const TOLERANCE_SECONDES = 300

/** Le corps peut être un texte ou des octets (par exemple un morceau de sauvegarde). */
export function signer(secret, horodatage, corps) {
  return 'sha256=' + createHmac('sha256', secret).update(`${horodatage}.`).update(corps ?? '').digest('hex')
}

export function entetesSignes(secret, corps, maintenant = Date.now()) {
  const horodatage = String(Math.floor(maintenant / 1000))
  return {
    'X-Kekeli-Horodatage': horodatage,
    'X-Kekeli-Signature': signer(secret, horodatage, corps),
  }
}

export function verifier(secret, horodatage, signature, corps, maintenant = Date.now()) {
  if (!secret || !horodatage || !signature || !/^\d+$/.test(String(horodatage))) return false
  if (Math.abs(Math.floor(maintenant / 1000) - Number(horodatage)) > TOLERANCE_SECONDES) return false
  const attendue = Buffer.from(signer(secret, horodatage, corps))
  const recue = Buffer.from(String(signature))
  return attendue.length === recue.length && timingSafeEqual(attendue, recue)
}
