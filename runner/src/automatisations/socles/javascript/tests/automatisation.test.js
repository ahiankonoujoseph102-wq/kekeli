import { test } from 'node:test'
import assert from 'node:assert/strict'
import { lancer } from '../src/automatisation.js'

const reponse = (donnees, statut = 200) => new Response(JSON.stringify(donnees), { status: statut, headers: { 'content-type': 'application/json' } })
const ENV = { MODELE_API_URL: 'https://modele.exemple/v1', MODELE_API_CLE: 'cle-de-test', MODELE_NOM: 'modele-test', RESEND_API_KEY: 'cle-email-de-test', EMAIL_DESTINATAIRE: 'moi@exemple.com' }

test('prépare le texte avec le modèle puis l’envoie par e-mail', async () => {
  const appels = []
  const appeler = async (url, options) => {
    appels.push({ url, corps: JSON.parse(options.body), entetes: options.headers })
    return url.includes('resend') ? reponse({ id: 'e-1' }) : reponse({ choices: [{ message: { content: 'Ligne 1\nLigne 2' } }] })
  }
  const resultat = await lancer({ env: ENV, appeler })
  assert.equal(resultat.ok, true)
  assert.equal(appels[0].url, 'https://modele.exemple/v1/chat/completions')
  assert.equal(appels[0].entetes.authorization, 'Bearer cle-de-test')
  assert.equal(appels[1].url, 'https://api.resend.com/emails')
  assert.deepEqual(appels[1].corps.to, ['moi@exemple.com'])
  assert.equal(appels[1].corps.text, 'Ligne 1\nLigne 2')
})

test('sans clé d’e-mail, rien n’est envoyé et le journal le dit', async () => {
  const appeler = async () => reponse({ choices: [{ message: { content: 'Texte' } }] })
  const resultat = await lancer({ env: { ...ENV, RESEND_API_KEY: '' }, appeler })
  assert.equal(resultat.ok, true)
})

test('une erreur du modèle arrête l’automatisation avec un message clair', async () => {
  const resultat = await lancer({ env: ENV, appeler: async () => reponse({}, 401) })
  assert.equal(resultat.ok, false)
})
