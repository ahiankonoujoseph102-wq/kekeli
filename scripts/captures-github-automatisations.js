// Captures de « GitHub et automatisations, comme Claude Code » (après l'essai réel de scripts/essai-automatisation.js
// et scripts/preparer-captures-github.php) : le plan de l'automatisation, « Lancer maintenant » et son journal, la
// pastille GitHub, le choix pull request ou branche principale, le choix guidé du fournisseur de la clé, et GitHub dans
// Réglages → Connecteurs. Sur téléphone et sur ordinateur, en mode clair et en mode sombre.
//   node scripts/captures-github-automatisations.js http://127.0.0.1:8123 docs/captures/github-automatisations
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/github-automatisations';
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
      await page.fill('input[type=email]', 'kofi-automatisation@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');
      const adresse = `${base}/projets/${projet}`;

      // 1. Le plan de l'automatisation (dans l'historique), avec la pastille GitHub en haut.
      await page.goto(adresse);
      await attendre(1500);
      await page.evaluate(() => {
        const plan = [...document.querySelectorAll('[data-question-answer]')].find((e) => e.textContent.includes('Déclencheur'))
        plan?.scrollIntoView({ block: 'start' })
      });
      await attendre(400);
      await page.screenshot({ path: nom('1-plan') });

      // 2. Le message final de la construction.
      await page.evaluate(() => {
        const r = document.querySelectorAll('[data-run-report]');
        r[r.length - 1]?.scrollIntoView({ block: 'center' });
      });
      await attendre(300);
      await page.screenshot({ path: nom('2-message-final') });

      // 3. « Lancer maintenant » et le journal du dernier lancement.
      await page.goto(adresse + (appareil === 'telephone' ? '?vue=apercu' : ''));
      await attendre(1500);
      await page.screenshot({ path: nom('3-lancer-maintenant') });

      // 4. La vue Code : le dépôt GitHub, la pull request et le choix de l'envoi.
      await page.goto(adresse + '?vue=code');
      await attendre(2500);
      await page.locator('[data-github-pill]').first().scrollIntoViewIfNeeded().catch(() => {});
      await attendre(300);
      await page.screenshot({ path: nom('4-github') });

      // 5. Variables secrètes : le choix guidé du fournisseur de la clé du client.
      await page.goto(adresse);
      await attendre(1500);
      await page.locator('[data-project-more]').first().click();
      await page.locator('[data-open-secrets]').first().click();
      await attendre(700);
      await page.locator('[data-model-form]').first().scrollIntoViewIfNeeded().catch(() => {});
      await page.screenshot({ path: nom('5-choix-du-fournisseur') });

      // 6. Réglages → Connecteurs : GitHub relié, choix des dépôts.
      await page.goto(base + '/settings/connecteurs');
      await attendre(1000);
      await page.screenshot({ path: nom('6-connecteurs') });
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log(erreurs.length ? `Erreurs dans les pages : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans les pages.');
})();
