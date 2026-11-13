// Captures des images et des affiches (pull request « Images et affiches ») : la discussion avec la carte du fichier,
// l'aperçu de l'image avec ses versions et « Télécharger », et « Récents » avec l'icône des images ; sur un téléphone
// et un ordinateur, en clair et en sombre. Après scripts/essai-images.js (compte Pro).
//   node scripts/captures-images.js http://127.0.0.1:8123 docs/captures/images/ecrans
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/images/ecrans';
const essai = JSON.parse(readFileSync('docs/captures/images/essai/essai-pro.json', 'utf8'));
const projet = (nom) => essai.find((e) => e.nom === nom)?.projet;
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [['telephone', { ...devices['Pixel 7'] }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }]]) {
      const nom = (n) => `${dossier}/${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'images-pro@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');

      // 1. La discussion : la demande, la ligne « A créé l'image · PNG », la carte du fichier.
      await page.goto(`${base}/projets/${projet('banniere')}${appareil === 'telephone' ? '?vue=discussion' : ''}`);
      await attendre(2500);
      await page.screenshot({ path: nom('1-discussion') });

      // 2. L'aperçu d'une affiche à imprimer (A4) avec « Télécharger » (PNG et PDF).
      await page.goto(`${base}/projets/${projet('ecole')}?vue=apercu`);
      await attendre(2500);
      await page.screenshot({ path: nom('2-apercu') });

      // 3. « Récents » : l'icône des images.
      if (appareil === 'telephone') {
        await page.locator('[data-nav-toggle]').first().click();
        await attendre(800);
      }
      await page.screenshot({ path: nom('3-recents') });
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log('Captures prises.');
})();
