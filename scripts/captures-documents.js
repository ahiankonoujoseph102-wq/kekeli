// Captures des documents et des présentations (après la mesure réelle de scripts/essai-documents.js) : l'aperçu d'un
// document, d'une présentation, la liste des versions et le menu « Télécharger », sur téléphone et sur ordinateur, en
// mode clair et en mode sombre.
//   node scripts/captures-documents.js http://127.0.0.1:8123 docs/captures/documents
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/documents';
const essai = JSON.parse(readFileSync(`${dossier}/essai.json`, 'utf8'));
const projet = (nom) => essai.find((e) => e.nom === nom)?.projet;
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
      await page.fill('input[type=email]', 'esi-documents@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');
      const vue = appareil === 'telephone' ? '?vue=apercu' : '';

      // 1. Aperçu d'un document (le rapport, avec son tableau).
      await page.goto(`${base}/projets/${projet('rapport')}${vue}`);
      await attendre(2500);
      await page.screenshot({ path: nom('1-apercu-document') });

      // 2. Aperçu d'une présentation.
      await page.goto(`${base}/projets/${projet('presentation')}${vue}`);
      await attendre(2500);
      await page.screenshot({ path: nom('2-apercu-presentation') });

      // 3. Les versions de la lettre (la modification en a fait une 2e).
      await page.goto(`${base}/projets/${projet('lettre')}${vue}`);
      await attendre(2000);
      await page.locator('[data-versions-open]').first().click().catch(() => {});
      await attendre(600);
      await page.screenshot({ path: nom('3-versions') });
      await page.keyboard.press('Escape');

      // 4. « Télécharger » : le choix du format.
      await attendre(300);
      await page.locator('[data-panel="apercu"] [data-download-open]').first().click().catch(() => {});
      await attendre(600);
      await page.screenshot({ path: nom('4-telecharger') });

      // 5. La discussion : la demande, le travail replié et le message de Kekeli.
      if (appareil === 'telephone') {
        await page.goto(`${base}/projets/${projet('devis')}`);
        await attendre(2000);
        await page.screenshot({ path: nom('5-discussion') });
      }
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log(erreurs.length ? `Erreurs dans les pages : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans les pages.');
})();
