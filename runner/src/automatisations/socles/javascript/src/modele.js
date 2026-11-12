// Appel au modèle d'intelligence artificielle du client (interface compatible OpenAI : OpenAI, Gemini, Mistral…).

export async function demanderAuModele(messages, { modele, appeler = fetch }) {
  if (!modele.url || !modele.cle || !modele.nom) throw new Error('Le modèle n’est pas réglé : remplissez MODELE_API_URL, MODELE_API_CLE et MODELE_NOM.')
  const reponse = await appeler(`${modele.url}/chat/completions`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', authorization: `Bearer ${modele.cle}` },
    body: JSON.stringify({ model: modele.nom, messages }),
    signal: AbortSignal.timeout(60000),
  })
  if (!reponse.ok) throw new Error(`Le modèle a refusé la demande (code ${reponse.status}).`)
  const donnees = await reponse.json()
  const texte = String(donnees?.choices?.[0]?.message?.content ?? '').trim()
  if (!texte) throw new Error('Le modèle n’a rien répondu.')
  return texte
}
