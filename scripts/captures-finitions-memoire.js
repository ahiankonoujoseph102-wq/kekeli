// Captures du bloc logo de Réglages → Mémoire (finition de la pull request 52), téléphone et ordinateur, clair et
// sombre. « node scripts/captures-finitions-memoire.js avant » ou « apres » ; compte inventé verif-memoire@exemple.tg.
import { createRequire } from 'node:module'
const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const moment = process.argv[2] || 'apres'
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123'
const b = await chromium.launch({ args: ['--lang=fr-FR'] })
for (const theme of ['light', 'dark']) {
  for (const [appareil, reglages] of [['telephone', { ...devices['Pixel 7'] }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }]]) {
    if (moment === 'avant' && (theme === 'dark' || appareil === 'ordinateur')) continue
    const c = await b.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme })
    const p = await c.newPage()
    await p.goto(base + '/login')
    if (await p.locator('[data-password-login]').count()) await p.locator('[data-password-login]').click()
    await p.fill('input[type=email]', 'verif-memoire@exemple.tg')
    await p.fill('input[type=password]', 'motdepasse-solide-2026')
    await p.click('button[type=submit]')
    await p.waitForURL('**/projets**')
    await p.goto(base + '/settings/memoire')
    await p.locator('[data-memory-logo]').scrollIntoViewIfNeeded()
    await p.waitForTimeout(800)
    await p.locator('[data-memory-logo]').screenshot({ path: `docs/captures/emails-connexion/memoire-logo-${moment}-${appareil}${theme === 'dark' ? '-sombre' : ''}.png` })
    await c.close()
  }
}
await b.close()
