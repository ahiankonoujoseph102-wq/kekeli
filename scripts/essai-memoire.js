// Mesure réelle de la mémoire (docs/plan.md, partie 26.21), comme un client sur son téléphone : un compte donne une
// fois ses informations (boutique, numéros, couleurs, logo, avec deux pièges : un mot de passe et une information de
// santé), puis fait 6 demandes dans 6 nouvelles discussions sans les répéter (affiche, devis, vidéo, petite question,
// site, agent). « avant » : mémoire désactivée (Kekeli tel qu'il était) ; « après » : mémoire active ; « kossi » : un
// autre client, pour vérifier qu'aucun souvenir ne passe d'un compte à l'autre. Données inventées.
// Le site, la file d'attente et le Runner doivent tourner (avec le même KEKELI_RUNNER_SECRET).
//
//   php artisan tinker --execute="require 'scripts/preparer-essai-memoire.php';"
//   node scripts/essai-memoire.js            (variable PHASES : « avant,apres,kossi » par défaut)
//   php artisan tinker --execute="require 'scripts/evaluer-essai-memoire.php';"
import { createRequire } from 'node:module'
import { writeFileSync, existsSync, readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'

const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123'
const SORTIE = 'runner/mesures/memoire-essai-projets.json'
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim()

export const INFORMATIONS = {
  ama: "Bonjour ! Je m'appelle Ama Kodjo et je tiens la boutique Ama Pagnes au grand marché de Lomé. Je vends des pagnes wax à 15 000 FCFA les 6 yards et du kente tissé à 35 000 FCFA. Mon WhatsApp : +228 90 11 22 33. Paiement par Mixx by Yas au 70 44 55 66 ou par Flooz au 96 77 88 99. Mes couleurs sont le vert émeraude et l'or. Voici mon logo, garde-le pour mes affiches. Pour info, mon mot de passe Kekeli est Pagne2026! et je suis diabétique.",
  kossi: "Bonjour, je suis Kossi Mensah, j'ai le garage Kossi Motos à Kara, je répare les motos. Mon WhatsApp : +228 91 00 00 01. Paiement par Flooz au 99 00 00 02. Mes couleurs : rouge et noir.",
}
export const DEMANDES = [
  { cle: 'affiche', texte: "Fais-moi une affiche pour annoncer l'arrivée des nouveaux pagnes wax." },
  { cle: 'devis', texte: 'Prépare-moi un devis pour une cliente qui veut 3 pagnes wax livrés à Lomé.' },
  { cle: 'video', texte: 'Fais-moi une petite vidéo publicitaire de 15 secondes pour ma boutique.' },
  { cle: 'question', texte: 'Comment je peux encaisser plus vite les paiements de mes clientes ?' },
  { cle: 'site', texte: 'Crée-moi un site vitrine pour ma boutique.' },
  { cle: 'agent', texte: 'Crée-moi un assistant qui répond aux questions de mes clientes.' },
]
const COMPTES = {
  avant: { email: 'ama-avant@exemple.tg', infos: INFORMATIONS.ama, demandes: DEMANDES },
  apres: { email: 'ama-apres@exemple.tg', infos: INFORMATIONS.ama, demandes: DEMANDES },
  kossi: { email: 'kossi-apres@exemple.tg', infos: INFORMATIONS.kossi, demandes: DEMANDES.filter((d) => ['affiche', 'question'].includes(d.cle)) },
}

async function connecter(contexte, email) {
  const page = await contexte.newPage()
  await page.goto(base + '/login')
  await page.fill('input[type=email]', email)
  await page.fill('input[type=password]', 'motdepasse-solide-2026')
  await page.click('button[type=submit]')
  await page.waitForURL('**/projets**')
  return page
}

/** Une demande depuis « Nouveau », avec une image jointe si besoin ; le plan éventuel est construit tel quel. */
async function envoyer(page, texte, image = null) {
  await page.goto(base + '/projets')
  await page.locator('textarea').first().fill(texte)
  if (image) {
    await page.locator('input[type=file][accept^="image"]').first().setInputFiles(image)
    await attendre(3000)
  }
  await page.locator('[data-prompt-send]').first().click()
  await page.waitForURL('**/projets/**', { timeout: 120000 })
  const projet = page.url().split('/projets/')[1].split('?')[0]
  // Le premier répondant a répondu, lancé un travail ou proposé un plan (alors « Construire ce plan », tel quel).
  for (let i = 0; i < 60; i++) {
    await attendre(4000)
    const question = tinker(`echo optional(App\\Models\\Project::find('${projet}')->agentRuns()->where('purpose', 'question')->latest('created_at')->first())->status;`)
    if (!['completed', 'failed', 'stopped'].includes(question)) continue
    await page.reload()
    await attendre(2500)
    if (await page.locator('[data-build-plan]').count()) {
      await page.locator('[data-build-plan]').first().click()
      await attendre(3000)
    }
    break
  }
  return projet
}

const fini = (projet) => {
  const etats = tinker(`echo App\\Models\\Project::find('${projet}')->agentRuns()->pluck('status')->implode(',');`)
  return etats && !/queued|running|waiting/.test(etats)
}

const resultat = existsSync(SORTIE) ? JSON.parse(readFileSync(SORTIE, 'utf8')) : {}
const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] })
for (const phase of (process.env.PHASES || 'avant,apres,kossi').split(',')) {
  const compte = COMPTES[phase]
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })
  const page = await connecter(contexte, compte.email)
  // « SEULEMENT=affiche » : refait seulement ces demandes (les informations sont déjà données), le reste est gardé.
  const seulement = process.env.SEULEMENT ? process.env.SEULEMENT.split(',') : null
  if (!seulement) {
    resultat[phase] = { email: compte.email, projets: {} }
    resultat[phase].projets.informations = await envoyer(page, compte.infos, phase === 'kossi' ? null : 'scripts/fichiers-essai/logo-ama-pagnes.png')
    while (!fini(resultat[phase].projets.informations)) await attendre(5000)
    console.log(phase, 'informations données', resultat[phase].projets.informations)
  }
  for (const demande of compte.demandes.filter((d) => !seulement || seulement.includes(d.cle))) {
    resultat[phase].projets[demande.cle] = await envoyer(page, demande.texte)
    console.log(phase, demande.cle, resultat[phase].projets[demande.cle])
    writeFileSync(SORTIE, JSON.stringify(resultat, null, 2) + '\n')
  }
  await contexte.close()
}
// Toutes les créations tournent en même temps ; on attend qu'elles soient finies.
const tous = Object.values(resultat).flatMap((p) => Object.values(p.projets))
for (let i = 0; i < 240 && !tous.every(fini); i++) await attendre(15000)
writeFileSync(SORTIE, JSON.stringify(resultat, null, 2) + '\n')
console.log('Fini :', tous.filter(fini).length, 'sur', tous.length)
await navigateur.close()
