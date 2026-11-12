// L'automatisation : ce qu'elle fait à chaque lancement (planifié par GitHub Actions, ou « Lancer maintenant » dans Kekeli).
// Exemple de départ : préparer un court texte avec le modèle, puis l'envoyer par e-mail. Kekeli l'adapte au plan validé.
import { pathToFileURL } from 'node:url'
import { configuration } from './configuration.js'
import { journal } from './journal.js'
import { demanderAuModele } from './modele.js'
import { envoyerEmail } from './email.js'

export const SUJET = 'Votre message du jour'

export async function lancer({ env = process.env, appeler = fetch, maintenant = new Date() } = {}) {
  const reglages = configuration(env)
  journal('Démarrage', 'info', `Lancement du ${maintenant.toLocaleString('fr-FR', { timeZone: 'Africa/Lome' })} (heure de Lomé)`)

  let texte
  try {
    texte = await demanderAuModele(
      [
        { role: 'system', content: 'Tu écris en français simple, sans émoji. Réponds seulement par le texte demandé.' },
        { role: 'user', content: 'Écris un court message utile pour aujourd’hui, en 3 lignes au plus.' },
      ],
      { modele: reglages.modele, appeler },
    )
    journal('Préparer le contenu', 'ok', `${texte.split('\n').length} ligne(s) préparée(s)`)
  } catch (erreur) {
    journal('Préparer le contenu', 'erreur', erreur.message)
    return { ok: false }
  }

  try {
    const envoi = await envoyerEmail({ sujet: SUJET, texte }, { email: reglages.email, appeler })
    if (envoi.envoye) journal('Envoyer l’e-mail', 'ok', `E-mail envoyé à ${reglages.email.destinataire}`)
    else journal('Envoyer l’e-mail', 'attention', envoi.raison === 'cle_absente' ? 'Clé RESEND_API_KEY absente : e-mail non envoyé. Voici le texte qui serait parti :\n' + texte : 'Destinataire absent : remplissez EMAIL_DESTINATAIRE.')
  } catch (erreur) {
    journal('Envoyer l’e-mail', 'erreur', erreur.message)
    return { ok: false }
  }

  journal('Fin', 'ok', 'Automatisation terminée')
  return { ok: true, texte }
}

// Lancée directement (npm start, GitHub Actions) : code de sortie 1 en cas d'erreur, pour que GitHub l'affiche.
if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const resultat = await lancer()
  process.exitCode = resultat.ok ? 0 : 1
}
