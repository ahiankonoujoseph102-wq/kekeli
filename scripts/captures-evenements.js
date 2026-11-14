// Captures du journal des déclenchements d'une automatisation lancée par un événement (après scripts/essai-evenement.js) :
// un faux GitHub rend les lancements de l'essai réel (runner/mesures/essai-evenement.json), comme l'interface
// officielle de GitHub. Site lancé avec KEKELI_GITHUB_API=http://127.0.0.1:8790.
//   node scripts/captures-evenements.js <projet de l'automatisation> docs/captures/evenements-equipes
import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
import http from 'node:http'
import { execFileSync } from 'node:child_process'

const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const [projet, dossier = 'docs/captures/evenements-equipes'] = process.argv.slice(2)
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123'
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
const essai = JSON.parse(readFileSync('runner/mesures/essai-evenement.json', 'utf8'))
const lancements = essai.etapes.filter((e) => e.etape.startsWith('Automatisation lancée'))
const runs = lancements.map((l, i) => ({ name: 'Automatisation', event: 'repository_dispatch', status: 'completed', conclusion: l.code === 0 ? 'success' : 'failure', created_at: l.a, html_url: `https://github.com/kofi-mensah/kofi-couture-contacts/actions/runs/${1000 + i}` })).reverse()
const github = http.createServer((req, res) => {
  res.writeHead(200, { 'content-type': 'application/json' })
  res.end(JSON.stringify(req.url.includes('/actions/runs') ? { workflow_runs: runs } : {}))
}).listen(8790, '127.0.0.1')

const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] })
for (const [nom, reglages] of [['telephone', { ...devices['Pixel 7'], colorScheme: 'light' }], ['telephone-sombre', { ...devices['Pixel 7'], colorScheme: 'dark' }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'light' }], ['ordinateur-sombre', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'dark' }]]) {
  const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR' })
  const page = await contexte.newPage()
  await page.goto(base + '/login')
  await page.fill('input[type=email]', 'kofi-evenement@exemple.tg')
  await page.fill('input[type=password]', 'motdepasse-solide-2026')
  await page.click('button[type=submit]')
  await page.waitForURL('**/projets**')
  // Le plan, à partir de son déclencheur.
  await page.goto(`${base}/projets/${projet}`)
  await attendre(2000)
  await page.locator('h2:has-text("Déclencheur")').first().evaluate((h) => h.scrollIntoView({ block: 'start' }))
  await attendre(500)
  await page.screenshot({ path: `${dossier}/${nom}-plan-automatisation.png` })
  await page.goto(`${base}/projets/${projet}?vue=apercu`)
  await attendre(2500)
  // La fenêtre « Brancher un formulaire », une fois ouverte (sans rien envoyer).
  if (nom === 'telephone' || nom === 'ordinateur') {
    await page.locator('[data-wire-open]').click()
    await page.locator('[data-wire-form] select').first().selectOption({ index: 1 }).catch(() => {})
    await page.locator('[data-wire-form] input').last().fill('https://kofi-couture-contacts.kofi-mensah.workers.dev')
    await attendre(1200)
    await page.screenshot({ path: `${dossier}/${nom}-brancher-formulaire.png` })
    await page.keyboard.press('Escape')
    await attendre(800)
  }
  await page.goto(`${base}/projets/${projet}?vue=apercu`)
  await attendre(2500)
  await page.locator('[data-trigger-log]').scrollIntoViewIfNeeded()
  await attendre(500)
  await page.screenshot({ path: `${dossier}/${nom}-journal-declenchements.png` })
  await contexte.close()
}
// Le LISEZ-MOI écrit par Kekeli, section « Brancher un événement », mis en forme comme sur GitHub.
const lisezMoi = String(essai.lisez_moi ?? '')
const debut = lisezMoi.indexOf('## Brancher un événement')
const section = debut >= 0 ? lisezMoi.slice(debut).split(/\n(?=## )/)[0] : lisezMoi
const html = execFileSync('php', ['artisan', 'tinker', '--execute', `echo Illuminate\\Support\\Str::markdown(base64_decode('${Buffer.from(section).toString('base64')}'), ['html_input' => 'escape']);`]).toString()
for (const [nom, reglages] of [['telephone', { ...devices['Pixel 7'], colorScheme: 'light' }], ['ordinateur', { viewport: { width: 1000, height: 900 }, deviceScaleFactor: 2, colorScheme: 'light' }]]) {
  const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR' })
  const page = await contexte.newPage()
  await page.setContent(`<!doctype html><meta charset="utf-8"><style>body{font:16px/1.6 -apple-system,Segoe UI,Helvetica,Arial,sans-serif;color:#1f2328;margin:0;padding:16px;background:#fff}.fichier{border:1px solid #d0d7de;border-radius:6px;overflow:hidden}.entete{background:#f6f8fa;border-bottom:1px solid #d0d7de;padding:8px 16px;font-weight:600;font-size:14px}.corps{padding:8px 20px}h2{border-bottom:1px solid #d0d7de;padding-bottom:6px}pre{background:#f6f8fa;padding:12px;border-radius:6px;overflow-x:auto;font-size:12px;white-space:pre-wrap;word-break:break-word}code{background:#eff1f3;padding:1px 4px;border-radius:4px;font-size:85%;word-break:break-word}pre code{background:none;padding:0}</style><div class="fichier"><div class="entete">LISEZ-MOI.md</div><div class="corps">${html}</div></div>`)
  await page.screenshot({ path: `${dossier}/${nom}-lisez-moi-branchement.png`, fullPage: true })
  await contexte.close()
}
await navigateur.close()
github.close()
