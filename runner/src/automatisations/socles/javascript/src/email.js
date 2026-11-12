// Envoi d'un e-mail avec Resend (https://resend.com, offre gratuite : 100 e-mails par jour, 3 000 par mois).

export async function envoyerEmail({ sujet, texte }, { email, appeler = fetch }) {
  if (!email.cle) return { envoye: false, raison: 'cle_absente' }
  if (!email.destinataire) return { envoye: false, raison: 'destinataire_absent' }
  const reponse = await appeler('https://api.resend.com/emails', {
    method: 'POST',
    headers: { 'content-type': 'application/json', authorization: `Bearer ${email.cle}` },
    body: JSON.stringify({ from: email.expediteur, to: [email.destinataire], subject: sujet, text: texte }),
    signal: AbortSignal.timeout(30000),
  })
  if (!reponse.ok) {
    const detail = await reponse.json().catch(() => ({}))
    throw new Error(`Resend a refusé l’envoi (code ${reponse.status}${detail?.message ? ' : ' + detail.message : ''}).`)
  }
  return { envoye: true, id: (await reponse.json().catch(() => ({}))).id ?? null }
}
