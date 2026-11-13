// Captures « avant / après » des 6 finitions des documents (pull request « Images et affiches ») : la carte du fichier
// dans la discussion, « Récents » avec une icône par type, le titre sans « Brouillon », un seul « Télécharger », la
// ligne d'actions d'un fichier, et la majuscule en début de cellule (image d'une diapositive).
//   node scripts/captures-finitions-documents.js http://127.0.0.1:8123 docs/captures/images/finitions avant
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/images/finitions';
const moment = process.argv[4] || 'avant';
const essai = JSON.parse(readFileSync('docs/captures/documents/essai.json', 'utf8'));
const projet = (nom) => essai.find((e) => e.nom === nom)?.projet;
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [['telephone', { ...devices['Pixel 7'] }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }]]) {
      const nom = (n) => `${dossier}/${moment}-${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'esi-documents@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');

      // 1 et 5. La discussion d'un document : la ligne d'actions et la carte du fichier sous la réponse.
      await page.goto(`${base}/projets/${projet('devis')}`);
      await attendre(2000);
      await page.screenshot({ path: nom('1-discussion') });

      // 2. « Récents » : une icône par type et les filtres.
      if (appareil === 'telephone') {
        await page.locator('[data-nav-toggle]').first().click();
        await attendre(800);
      }
      await page.screenshot({ path: nom('2-recents') });

      // 3 et 4. La barre du projet (statut sous le titre) et les boutons « Télécharger ».
      await page.goto(`${base}/projets/${projet('presentation')}${appareil === 'telephone' ? '?vue=apercu' : ''}`);
      await attendre(2500);
      await page.screenshot({ path: nom('3-barre-et-telecharger') });
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log('Captures prises.');
})();
