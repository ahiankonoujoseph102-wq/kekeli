// Essai réel « quand le formulaire de contact du site est rempli, envoyer un résumé par e-mail » :
// 1. Kekeli construit pour de vrai un petit site vitrine avec un formulaire, puis l'automatisation (plan avec son
//    déclencheur, « Construire ce plan »), dans des ordinateurs E2B ;
// 2. « Lancer maintenant » simule l'événement avec les données d'essai ;
// 3. « Brancher un formulaire » : avec l'accord du client, Kekeli modifie pour de vrai le formulaire du site ;
// 4. un visiteur remplit le formulaire dans l'aperçu du site : l'envoi part vers le VRAI point d'entrée écrit par
//    Kekeli, qui tourne dans le moteur de Cloudflare (wrangler dev, sur cet ordinateur) avec sa vraie vérification ;
//    le point d'entrée lance l'automatisation par un faux GitHub (le dépôt du client n'existe pas encore), qui la fait
//    tourner exactement comme GitHub Actions (Node 22, « npm start », événement « repository_dispatch »).
// Ce qui se fera à la mise en ligne, avec les vrais comptes du client : docs/mise-en-ligne.md.
// Site lancé avec KEKELI_GITHUB_API=http://127.0.0.1:8790 ; file d'attente et Runner lancés.
//   node scripts/essai-evenement.js docs/captures/evenements-equipes
import { createRequire } from 'node:module'
import { mkdirSync, mkdtempSync, writeFileSync, readFileSync, rmSync } from 'node:fs'
import { execFileSync, spawn } from 'node:child_process'
import http from 'node:http'
import path from 'node:path'
import { tmpdir } from 'node:os'

const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const dossier = process.argv[2] || 'docs/captures/evenements-equipes'
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123'
const WRANGLER = process.env.WRANGLER || 'npx wrangler@4'
const EMAIL = 'kofi-evenement@exemple.tg'
const DEPOT = 'kofi-mensah/kofi-couture-contacts'
const ENTREE = 'https://kofi-couture-contacts.kofi-mensah.workers.dev'
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim()
const SITE = 'Un petit site vitrine pour mon atelier Kofi Couture à Bè (Lomé) : retouches, tenues sur mesure en pagne. Avec un formulaire de contact : nom, téléphone et message. Téléphone : +228 90 11 22 33.'
const AUTOMATISATION = 'Quand un client remplit le formulaire de contact de mon site Kofi Couture, envoie-moi un résumé du message par e-mail (nom, téléphone, demande), sans modèle d’intelligence artificielle.'
const journal = process.env.AUTO ? JSON.parse(readFileSync('runner/mesures/essai-evenement.json', 'utf8')) : { etapes: [] }
const noter = (etape, details = {}) => { journal.etapes.push({ etape, a: new Date().toISOString(), ...details }); console.log(etape, JSON.stringify(details).slice(0, 400)); writeFileSync('runner/mesures/essai-evenement.json', JSON.stringify(journal, null, 2)) }

if (!process.env.AUTO) tinker(`
  $u = App\\Models\\User::where('email', '${EMAIL}')->first() ?? new App\\Models\\User(['email' => '${EMAIL}']);
  $u->forceFill(['name' => 'Kofi Mensah', 'password' => Hash::make('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'pro', 'plan_expires_at' => now()->addDays(20), 'appearance' => 'system', 'github_token' => 'ghu_jeton_invente_pour_l_essai'])->save();
  $u->projects()->get()->each->delete();`)

async function connecter(contexte) {
  const page = await contexte.newPage()
  await page.goto(base + '/login')
  await page.fill('input[type=email]', EMAIL)
  await page.fill('input[type=password]', 'motdepasse-solide-2026')
  await page.click('button[type=submit]')
  await page.waitForURL('**/projets**')
  return page
}
// Le dernier vrai travail de Kekeli (pas la réponse du premier répondant ni « Construire ce plan »).
const dernierTravail = (projet) => tinker(`echo optional(App\\Models\\Project::find('${projet}')->agentRuns()->whereNull('purpose')->latest('created_at')->first())->status;`)
async function attendreFin(projet, minutes = 40) {
  const debut = Date.now()
  await attendre(8000)
  while (Date.now() - debut < minutes * 60000) {
    const s = dernierTravail(projet)
    if (['completed', 'failed', 'stopped'].includes(s)) return s
    await attendre(10000)
  }
  return 'trop long'
}

// Le faux GitHub : il reçoit « repository_dispatch » et fait tourner l'automatisation comme GitHub Actions.
const lancements = []
let dossierAutomatisation = null
const fauxGithub = http.createServer(async (req, res) => {
  let corps = ''
  for await (const m of req) corps += m
  if (req.method === 'POST' && req.url === `/repos/${DEPOT}/dispatches`) {
    const recu = { a: new Date().toISOString(), autorisation: Boolean(req.headers.authorization), corps: JSON.parse(corps || '{}') }
    res.writeHead(204).end()
    const evenement = path.join(dossierAutomatisation, 'evenement-github.json')
    writeFileSync(evenement, JSON.stringify({ action: recu.corps.event_type, client_payload: recu.corps.client_payload }))
    const sortie = await new Promise((resolve) => {
      const p = spawn('npm', ['start', '--silent'], { cwd: dossierAutomatisation, env: { PATH: process.env.PATH, HOME: process.env.HOME, GITHUB_EVENT_NAME: 'repository_dispatch', GITHUB_EVENT_PATH: evenement, EMAIL_DESTINATAIRE: 'kofi@exemple.tg' } })
      let texte = ''
      p.stdout.on('data', (d) => { texte += d })
      p.stderr.on('data', (d) => { texte += d })
      p.on('close', (code) => resolve({ code, texte }))
    })
    lancements.push({ ...recu, ...sortie, conclusion: sortie.code === 0 ? 'success' : 'failure' })
    noter('Automatisation lancée par le point d’entrée (comme GitHub Actions)', { donnees: recu.corps.client_payload?.donnees, mode: recu.corps.client_payload?.mode, code: sortie.code, journal: sortie.texte })
    return
  }
  if (req.method === 'GET' && req.url.startsWith(`/repos/${DEPOT}/actions/runs`)) {
    res.writeHead(200, { 'content-type': 'application/json' })
    return res.end(JSON.stringify({ workflow_runs: lancements.map((l, i) => ({ name: 'Automatisation', event: 'repository_dispatch', status: 'completed', conclusion: l.conclusion, created_at: l.a, html_url: `https://github.com/${DEPOT}/actions/runs/${1000 + i}` })).reverse() }))
  }
  res.writeHead(404).end('{}')
})
await new Promise((r) => fauxGithub.listen(8790, '127.0.0.1', r))

mkdirSync(dossier, { recursive: true })
const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] })
const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })
const page = await connecter(contexte)

// 1. Le site d'essai, construit par Kekeli ; 2. l'automatisation : plan avec son déclencheur, « Construire ce plan ».
// (SITE et AUTO : reprendre avec des projets déjà demandés.)
let site = process.env.SITE
let automatisation = process.env.AUTO
if (!site) {
  await page.locator('textarea').first().fill(SITE)
  await page.locator('[data-prompt-send]').first().click()
  await page.waitForURL('**/projets/**')
  site = page.url().split('/projets/')[1].split('?')[0]
  noter('Site demandé', { site })
}
if (!automatisation) {
  await page.goto(base + '/projets')
  await page.locator('textarea').first().fill(AUTOMATISATION)
  await page.locator('[data-prompt-send]').first().click()
  await page.waitForURL('**/projets/**')
  await page.locator('[data-build-plan]').waitFor({ timeout: 300000 })
  automatisation = page.url().split('/projets/')[1].split('?')[0]
  await attendre(800)
  const plan = tinker(`echo data_get(app(App\\Agent\\Tools\\CreateAutomation::class)->lastOffer(App\\Models\\Project::find('${automatisation}')), 'report.plan');`)
  noter('Plan proposé', { plan })
  await page.locator('[data-build-plan]').click()
}
const declenchementSeul = process.env.ETAPE === 'declenchement'
if (!declenchementSeul) {
noter('Site construit', { statut: await attendreFin(site) })
noter('Automatisation construite', { statut: await attendreFin(automatisation) })
const declencheur = JSON.parse(tinker(`$p = App\\Models\\Project::find('${automatisation}'); $p->secrets()->updateOrCreate(['name' => 'EMAIL_DESTINATAIRE'], ['value' => 'kofi@exemple.tg']); $p->forceFill(['repository' => '${DEPOT}'])->save(); echo json_encode(['declencheur' => $p->automation_trigger, 'secret' => $p->secrets()->where('name', 'ENTREE_SECRET')->exists()]);`))
noter('Déclencheur lu dans le code', declencheur)

// 3. « Lancer maintenant » : l'événement simulé avec les données d'essai.
await page.goto(`${base}/projets/${automatisation}?vue=apercu`)
await page.locator('[data-launch-now]').click()
await page.locator('[data-last-launch="ok"], [data-last-launch="error"]').waitFor({ timeout: 600000 })
noter('Lancer maintenant', { resume: await page.locator('[data-launch-summary]').innerText().catch(() => '') })

// 4. « Brancher un formulaire » : Kekeli modifie le formulaire du site, avec l'accord du client.
await page.locator('[data-wire-open]').click()
await page.locator('[data-wire-form] select').first().waitFor({ timeout: 10000 })
await page.locator('[data-wire-form] select').first().selectOption(site)
await page.locator('[data-wire-form] input').last().fill(ENTREE)
await page.screenshot({ path: `${dossier}/telephone-brancher-formulaire.png` })
await page.locator('[data-wire-submit]').click()
await attendre(3000)
noter('Formulaire branché par Kekeli', { statut: await attendreFin(site) })
}

// 5. Le vrai point d'entrée, dans le moteur de Cloudflare, avec le code écrit par Kekeli.
dossierAutomatisation = mkdtempSync(path.join(tmpdir(), 'automatisation-'))
const fichiers = JSON.parse(tinker(`echo json_encode(app(App\\Agent\\ProjectFiles::class)->read(App\\Models\\Project::find('${automatisation}'), true));`))
for (const [nom, contenu] of Object.entries(fichiers)) {
  mkdirSync(path.dirname(path.join(dossierAutomatisation, nom)), { recursive: true })
  writeFileSync(path.join(dossierAutomatisation, nom), contenu)
}
const secret = tinker(`echo App\\Models\\Project::find('${automatisation}')->secrets()->where('name', 'ENTREE_SECRET')->value('value');`)
const apercu = tinker(`echo App\\Models\\Project::find('${site}')->preview_url;`)
const origine = new URL(apercu).origin
const entree = path.join(dossierAutomatisation, 'point-entree')
writeFileSync(path.join(entree, 'wrangler.toml'), readFileSync(path.join(entree, 'wrangler.toml'), 'utf8').replace('ID_MEMOIRE', 'essai-local'))
writeFileSync(path.join(entree, '.dev.vars'), `ENTREE_SECRET=${secret}\nGITHUB_DECLENCHEUR=jeton-github-invente\n`)
const wrangler = spawn('bash', ['-c', `${WRANGLER} dev --port 8787 --var DEPOT:${DEPOT} --var GITHUB_API:http://127.0.0.1:8790 --var FORMULAIRE_ORIGINES:${origine}`], { cwd: entree, env: process.env })
let sortieWrangler = ''
wrangler.stdout.on('data', (d) => { sortieWrangler += d })
wrangler.stderr.on('data', (d) => { sortieWrangler += d })
for (let i = 0; i < 60 && !/Ready on/.test(sortieWrangler); i++) await attendre(1000)
noter('Point d’entrée lancé (moteur de Cloudflare)', { pret: /Ready on/.test(sortieWrangler), origine_permise: origine })

// Les envois du navigateur vers l'adresse du point d'entrée vont au point d'entrée local (même requête, même origine).
const hote = new URL(ENTREE).hostname
await contexte.route((url) => url.hostname === hote, async (route) => {
  const r = route.request()
  const reponse = await fetch('http://127.0.0.1:8787/' + (new URL(r.url()).search || ''), { method: r.method(), headers: r.headers(), body: r.method() === 'POST' ? r.postDataBuffer() : undefined })
  noter('Envoi du formulaire reçu par le point d’entrée', { methode: r.method(), statut: reponse.status })
  await route.fulfill({ status: reponse.status, headers: Object.fromEntries(reponse.headers), body: Buffer.from(await reponse.arrayBuffer()) })
})

// 6. Un visiteur remplit le formulaire du site, dans l'aperçu ouvert dans sa propre page (adresse avec son jeton).
await page.goto(`${base}/projets/${site}?vue=apercu`)
await page.locator('[data-panel="apercu"] iframe').waitFor({ timeout: 300000 })
const adresseApercu = await page.locator('[data-panel="apercu"] iframe').getAttribute('src')
const visiteur = await contexte.newPage()
await visiteur.goto(adresseApercu)
await attendre(5000)
const cadre = visiteur.mainFrame()
await cadre.locator('#formulaire-contact').scrollIntoViewIfNeeded()
await cadre.locator('form [name="nom"]').fill('Afi Lawson')
await cadre.locator('form [name="telephone"]').fill('+228 91 23 45 67')
await cadre.locator('form textarea').first().fill('Bonjour, je voudrais une robe en pagne pour un mariage le 20 décembre. Est-ce possible ?')
await cadre.locator('form [type="submit"]').first().click()
for (let i = 0; i < 90 && !lancements.length; i++) await attendre(1000)
await attendre(3000)
await cadre.locator('#formulaire-etat').scrollIntoViewIfNeeded().catch(() => {})
noter('Message affiché au visiteur', { texte: await cadre.locator('#formulaire-etat').innerText().catch(() => '') })
await visiteur.screenshot({ path: `${dossier}/telephone-formulaire-envoye.png` })
// Le même envoi rejoué : jamais deux fois le même événement.
const rejoue = await cadre.evaluate(async (adresse) => {
  const corps = new URLSearchParams({ nom: 'Afi Lawson', telephone: '+228 91 23 45 67', demande: 'Bonjour, je voudrais une robe en pagne pour un mariage le 20 décembre. Est-ce possible ?', site_web: '' })
  const r = await fetch(adresse, { method: 'POST', body: corps })
  return r.status
}, ENTREE).catch((e) => String(e))
await attendre(4000)
// Un appel sans autorisation, d'une autre origine : refusé.
const refuse = await fetch('http://127.0.0.1:8787/', { method: 'POST', headers: { Origin: 'https://site-pirate.example', 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'nom=Robot' }).then((r) => r.status)
noter('Contrôles', { lancements: lancements.length, rejoue, refuse, journal_point_entree: sortieWrangler.split('\n').filter((l) => l.includes('declenchement')).slice(-5) })

// 7. Le journal des déclenchements dans Kekeli (lu dans « GitHub », ici le faux GitHub).
for (const [nom, reglages] of [['telephone', { ...devices['Pixel 7'], colorScheme: 'light' }], ['telephone-sombre', { ...devices['Pixel 7'], colorScheme: 'dark' }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'light' }], ['ordinateur-sombre', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'dark' }]]) {
  const autre = await navigateur.newContext({ ...reglages, locale: 'fr-FR' })
  const p = await connecter(autre)
  await p.goto(`${base}/projets/${automatisation}`)
  await attendre(1500)
  await p.locator('[data-plan-actions], [data-question-answer]').first().scrollIntoViewIfNeeded().catch(() => {})
  await p.screenshot({ path: `${dossier}/${nom}-plan-automatisation.png` })
  await p.goto(`${base}/projets/${automatisation}?vue=apercu`)
  await attendre(2500)
  await p.locator('[data-trial-event] summary').click().catch(() => {})
  await p.locator('[data-launch-panel]').screenshot({ path: `${dossier}/${nom}-journal-declenchements.png` }).catch(() => p.screenshot({ path: `${dossier}/${nom}-journal-declenchements.png` }))
  await autre.close()
}
journal.lisez_moi = fichiers['LISEZ-MOI.md']
journal.fichiers = Object.keys(fichiers)
writeFileSync('runner/mesures/essai-evenement.json', JSON.stringify(journal, null, 2))
wrangler.kill()
rmSync(path.join(entree, '.dev.vars'), { force: true })
await navigateur.close()
fauxGithub.close()
console.log('Fini')
