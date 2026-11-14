// Captures de la page de connexion (finition : le symbole et le titre seulement, comme Claude), téléphone (Pixel 6) et
// ordinateur, clair et sombre. « node scripts/captures-connexion-finition.js avant|apres ».
import { createRequire } from 'node:module'
const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const moment = process.argv[2] || 'apres'
const b = await chromium.launch({ args: ['--lang=fr-FR'] })
for (const theme of ['light', 'dark']) {
  for (const [appareil, r] of [['telephone', { ...devices['Pixel 6'] }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }]]) {
    const c = await b.newContext({ ...r, locale: 'fr-FR', colorScheme: theme })
    const p = await c.newPage()
    await p.goto('http://127.0.0.1:8123/login')
    await p.waitForTimeout(900)
    await p.screenshot({ path: `docs/captures/organisation-design/connexion-${moment}-${appareil}${theme === 'dark' ? '-sombre' : ''}.png` })
    await c.close()
  }
}
await b.close()
