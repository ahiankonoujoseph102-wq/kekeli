// Captures de la page « Formules » et du paiement, AVANT et APRÈS les nouvelles formules, sur téléphone et sur ordinateur.
//   node scripts/captures-formules.js docs/captures/formules/avant   (ancienne version sur KEKELI_URL)
//   node scripts/captures-formules.js docs/captures/formules/apres
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/formules/apres';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

async function connecter(contexte, email) {
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  return page;
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  for (const [appareil, reglages] of [
    ['telephone', { ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' }],
    ['ordinateur', { viewport: { width: 1440, height: 900 }, locale: 'fr-FR', colorScheme: 'light' }],
  ]) {
    const nom = (n) => `${dossier}/${appareil}-${n}.png`;
    for (const [n, email, extra] of [
      ['01-formules-client-gratuit-togo', 'ama-nettoyage@exemple.tg'],
      ['02-formules-abonne-pro', 'esi-formules@exemple.tg', 'menu'],
      ['03-formules-client-ghana', 'kwame-formules@exemple.gh', 'paiement'],
    ]) {
      const contexte = await navigateur.newContext(reglages);
      const page = await connecter(contexte, email);
      await page.goto(base + '/formules');
      await attendre(900);
      await page.screenshot({ path: nom(n), fullPage: true });
      if (extra === 'paiement') {
        await page.goto(base + '/formules/pro/payer');
        await attendre(900);
        await page.screenshot({ path: nom('04-paiement-client-ghana'), fullPage: true });
      }
      if (extra === 'menu' && appareil === 'ordinateur') {
        await page.locator('[data-flux-profile]').first().click().catch(() => {});
        await attendre(700);
        await page.screenshot({ path: nom('05-menu-du-compte-abonne-pro') });
      }
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log('Captures prises.');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
