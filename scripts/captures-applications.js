// Captures de la création d'applications (après l'essai réel de scripts/essai-application.js) : le plan, la
// construction avec les actions repliées, l'application dans l'aperçu et les Variables secrètes, sur téléphone et sur
// ordinateur, en mode clair et en mode sombre.
//   node scripts/captures-applications.js http://127.0.0.1:8123 docs/captures/applications
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/applications';
const { projet } = JSON.parse(readFileSync(`${dossier}/essai.json`, 'utf8'));
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];
  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [
      ['telephone', { ...devices['Pixel 7'] }],
      ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }],
    ]) {
      const nom = (n) => `${dossier}/${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'afi-application@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');
      const adresse = `${base}/projets/${projet}`;

      // 1. Le plan proposé, avec « Construire ce plan » (dans l'historique de la discussion).
      await page.goto(adresse);
      await attendre(1500);
      await page.evaluate(() => document.querySelector('[data-plan-actions], [data-question-answer]')?.closest('[data-run-status]')?.scrollIntoView({ block: 'start' }));
      await attendre(400);
      await page.screenshot({ path: nom('1-plan') });

      // 2. La construction : les actions dépliées, avec les fichiers et les commandes, puis le message final.
      const actions = page.locator('[data-narration] summary').last();
      if (await actions.count()) {
        await actions.click();
        await attendre(400);
        await actions.scrollIntoViewIfNeeded();
      }
      await page.screenshot({ path: nom('2-construction') });
      await page.evaluate(() => {
        const r = document.querySelectorAll('[data-run-report]');
        r[r.length - 1]?.scrollIntoView({ block: 'center' });
      });
      await attendre(300);
      await page.screenshot({ path: nom('3-message-final') });

      // 3. L'application dans l'aperçu (sur ordinateur, à côté de la discussion).
      await page.goto(adresse + (appareil === 'telephone' ? '?vue=apercu' : ''));
      await attendre(20000);
      await page.screenshot({ path: nom('4-apercu') });

      // 4. Variables secrètes : la valeur n'est jamais réaffichée.
      await page.goto(adresse);
      await attendre(1500);
      await page.locator('[data-project-more]').first().click();
      await page.locator('[data-open-secrets]').first().click();
      await attendre(700);
      await page.screenshot({ path: nom('5-variables-secretes') });
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log(erreurs.length ? `Erreurs dans les pages : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans les pages.');
})();
