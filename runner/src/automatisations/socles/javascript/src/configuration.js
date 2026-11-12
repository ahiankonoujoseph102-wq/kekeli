// Réglages lus dans l'environnement (fichier .env sur un ordinateur, secrets du dépôt sur GitHub).
// Jamais de clé dans le code.

export function configuration(env = process.env) {
  return {
    modele: {
      url: String(env.MODELE_API_URL ?? '').replace(/\/+$/, ''),
      cle: String(env.MODELE_API_CLE ?? ''),
      nom: String(env.MODELE_NOM ?? ''),
    },
    email: {
      cle: String(env.RESEND_API_KEY ?? ''),
      destinataire: String(env.EMAIL_DESTINATAIRE ?? ''),
      expediteur: String(env.EMAIL_EXPEDITEUR ?? '') || 'Automatisation <onboarding@resend.dev>',
    },
  }
}
