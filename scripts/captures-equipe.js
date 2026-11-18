// Captures du mode équipe (phase 1) sur un téléphone simulé : le plan d'une application avec le second bouton réservé
// à l'administrateur, puis les missions de l'équipe pendant une construction.
//   php artisan tinker --execute="require 'scripts/preparer-captures-equipe.php';"
//   node scripts/captures-equipe.js <discussion> <application> docs/captures/mode-equipe
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [discussion, application, dossier = 'docs/captures/mode-equipe'] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })).newPage();
  await page.goto(`${base}/login`);
  await page.locator('[data-password-login]').click();
  await page.locator('input[type=email]').fill('equipe-admin@exemple.tg');
  await page.locator('input[type=password]').fill('motdepasse-solide-2026');
  await page.locator('[data-password-submit]').click();
  await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 30000 });

  await page.goto(`${base}/projets/${discussion}`);
  await page.waitForTimeout(2000);
  await page.locator('[data-plan-actions]').scrollIntoViewIfNeeded();
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${dossier}/1-plan-deux-boutons.png` });

  await page.goto(`${base}/projets/${application}`);
  await page.waitForTimeout(2500);
  await page.locator('[data-team-missions]').scrollIntoViewIfNeeded();
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${dossier}/2-missions-en-cours.png` });
  await navigateur.close();
})();
