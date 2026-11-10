// Essai réel du mode application web avec E2B, sans le site : ordinateur, application posée et installée,
// puis ouverture par l'aperçu privé (cookie), comme le téléphone du client.
//   KEKELI_RUNNER_SECRET=… node scripts/essai-application.mjs laravel reservations-restaurant
import { creerGestionnaireOrdinateurs, synchroniserSecrets } from '../src/ordinateurs.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { cleApercu, jetonApercu } from '../src/apercu/serveur.mjs'

const [pile, modele] = process.argv.slice(2)
const secret = process.env.KEKELI_RUNNER_SECRET
const projet = 'essai-application-' + pile + '-' + Date.now()
const clesPresentes = fournisseursAvecCle()
await synchroniserSecrets(clesPresentes)
const ordinateurs = creerGestionnaireOrdinateurs({ secretRunner: secret, clesPresentes })
const debut = Date.now()
const temps = () => Math.round((Date.now() - debut) / 1000) + ' s'
try {
  const ordinateur = await ordinateurs.obtenir(projet, { application: { pile, modele }, dureeVieMinutes: 15 })
  console.log('Ordinateur prêt', temps(), 'aperçu :', Boolean(ordinateur.apercu?.url))
  const preparation = await ordinateurs.preparerApplication(projet, pile)
  for (const e of preparation.etapes) console.log(e.ok ? 'OK ' : 'ÉCHEC', e.secondes + ' s', e.commande.slice(0, 110), e.ok ? '' : '\n   ' + e.sortie.replace(/\n/g, '\n   '))
  console.log('Préparée', temps())
  const jeton = jetonApercu(cleApercu(secret, projet), projet, Math.floor(Date.now() / 1000) + 1800)
  const entree = await fetch(`${ordinateur.apercu.url}/k/${jeton}/`, { redirect: 'manual' })
  const cookie = entree.headers.get('set-cookie').split(';')[0]
  for (const chemin of ['/', '/connexion', '/inscription']) {
    const r = await fetch(ordinateur.apercu.url + chemin, { headers: { cookie } })
    const t = await r.text()
    console.log(chemin, r.status, (t.match(/<h1[^>]*>([^<]*)/) ?? [])[1] ?? t.slice(0, 120).replace(/\s+/g, ' '))
  }
  const verification = await ordinateurs.verifierApplication(projet, pile)
  console.log('Vérification', temps(), JSON.stringify({ tests: [verification.tests.ok, verification.tests.resume], securite: verification.securite, sante: verification.sante.code }))
} finally {
  await ordinateurs.effacer(projet).catch(() => {})
  console.log('Ordinateur effacé', temps())
}
