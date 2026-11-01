// Envoi des étapes de l'agent au site Kekeli, dans l'ordre, avec une signature.
import { entetesSignes } from './signature.js'

export function creerEnvoyeur({ url, secret, tache, journal = console, essais = 4, attenteMs = 1000 }) {
  let file = Promise.resolve()

  async function poster(evenement) {
    const corps = JSON.stringify({ tache, evenement })
    for (let essai = 1; essai <= essais; essai++) {
      try {
        const reponse = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...entetesSignes(secret, corps) },
          body: corps,
        })
        if (reponse.ok) return
        journal.warn(`Le site Kekeli a répondu ${reponse.status} à l'événement « ${evenement.type} »`)
        if (reponse.status < 500) return
      } catch (erreur) {
        journal.warn(`Envoi au site Kekeli impossible (essai ${essai}) : ${erreur.message}`)
      }
      await new Promise((r) => setTimeout(r, attenteMs * essai))
    }
  }

  return function envoyer(evenement) {
    file = file.then(() => poster(evenement))
    return file
  }
}
