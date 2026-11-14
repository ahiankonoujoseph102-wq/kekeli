// Captures de la mémoire, sur téléphone (Pixel 7) et sur ordinateur, en mode clair et en mode sombre, avec le compte
// de la mesure réelle (scripts/essai-memoire.js, compte « ama-apres ») : la ligne « Mémoire mise à jour » dépliée, la
// page Réglages → Mémoire (avec le logo), et l'affiche faite sans redonner les informations. Site lancé.
//
//   node scripts/captures-memoire.js http://127.0.0.1:8123 docs/captures/memoire
import { createRequire } from 'node:module'
import { mkdirSync, readFileSync } from 'node:fs'

const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const base = process.argv[2] || 'http://127.0.0.1:8123'
const dossier = process.argv[3] || 'docs/captures/memoire'
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
const { apres } = JSON.parse(readFileSync('runner/mesures/memoire-essai-projets.json', 'utf8'))

mkdirSync(dossier, { recursive: true })
const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] })
const erreurs = []
for (const theme of ['light', 'dark']) {
  for (const [appareil, reglages] of [
    ['telephone', { ...devices['Pixel 7'] }],
    ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }],
  ]) {
    const nom = (n) => `${dossier}/${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`
    const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme })
    const page = await contexte.newPage()
    page.on('pageerror', (e) => erreurs.push(e.message))
    await page.goto(base + '/login')
    await page.fill('input[type=email]', apres.email)
    await page.fill('input[type=password]', 'motdepasse-solide-2026')
    await page.click('button[type=submit]')
    await page.waitForURL('**/projets**')

    // 1. La ligne « Mémoire mise à jour », dépliée, sous le message qui donne les informations.
    await page.goto(`${base}/projets/${apres.projets.informations}`)
    await page.waitForSelector('[data-memory-updated]')
    await page.locator('[data-memory-updated] summary').first().click()
    await page.locator('[data-memory-updated]').first().scrollIntoViewIfNeeded()
    await attendre(600)
    await page.screenshot({ path: nom('1-memoire-mise-a-jour') })

    // 2. Réglages → Mémoire : les souvenirs, le logo, « Tout effacer » et l'interrupteur.
    await page.goto(`${base}/settings/memoire`)
    await page.waitForSelector('[data-memory-settings]')
    await attendre(600)
    await page.screenshot({ path: nom('2-reglages-memoire'), fullPage: appareil === 'telephone' })

    // 3. L'affiche faite sans redonner les informations (aperçu du projet d'image).
    await page.goto(`${base}/projets/${apres.projets.affiche}?vue=apercu`)
    await attendre(3500)
    await page.screenshot({ path: nom('3-affiche-sans-redonner') })
    if (appareil === 'telephone') {
      await page.goto(`${base}/projets/${apres.projets.affiche}`)
      await attendre(2500)
      await page.screenshot({ path: nom('4-affiche-discussion') })
    }
    await contexte.close()
  }
}
await navigateur.close()
console.log(erreurs.length ? `Erreurs de la page : ${erreurs.join(' | ')}` : 'Aucune erreur de la page.')
