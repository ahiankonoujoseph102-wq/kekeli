// Captures « avant / après » des finitions de la pull request 41 (applications), sur téléphone (Pixel 7) et sur
// ordinateur, en clair et en sombre. Données inventées : scripts/preparer-captures-finitions-applications.php.
//   node scripts/captures-finitions-applications.js apres http://127.0.0.1:8123 docs/captures/agents/finitions
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const etat = process.argv[2] || 'apres';
const base = process.argv[3] || 'http://127.0.0.1:8123';
const dossier = process.argv[4] || 'docs/captures/agents/finitions';
const racine = process.env.KEKELI_RACINE || process.cwd();
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const id = (email) => execFileSync('php', ['artisan', 'tinker', '--execute', `echo App\\Models\\User::where('email', '${email}')->first()->projects()->value('id');`], { cwd: racine }).toString().trim();

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];
  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [['telephone', { ...devices['Pixel 7'] }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }]]) {
      const nom = (n) => `${dossier}/${etat}-${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      for (const [email, captures] of [['afi-finitions-applications@exemple.tg', 'application'], ['kofi-finitions-applications@exemple.tg', 'energie']]) {
        const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
        const page = await contexte.newPage();
        page.on('pageerror', (e) => erreurs.push(e.message));
        await page.goto(base + '/login');
        await page.fill('input[type=email]', email);
        await page.fill('input[type=password]', 'motdepasse-solide-2026');
        await page.click('button[type=submit]');
        await page.waitForURL('**/projets**');
        await page.goto(`${base}/projets/${id(email)}`);
        await attendre(1000);
        if (captures === 'application') {
          await page.locator('[data-narration] summary').first().click();
          await attendre(400);
          await page.screenshot({ path: nom('1-actions-et-titre') });
        } else {
          await page.screenshot({ path: nom('2-energie-epuisee') });
        }
        await contexte.close();
      }
    }
  }
  await navigateur.close();
  console.log(erreurs.length ? `Erreurs dans les pages : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans les pages.');
})();
