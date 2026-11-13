// Captures « Versions et retour en arrière », sur téléphone (Pixel 7) et sur ordinateur, en mode clair et en mode
// sombre, avec le vrai projet de l'essai réel (scripts/essai-versions.php) et son aperçu en direct : liste des
// versions, aperçu d'une ancienne version avec le bandeau, confirmation de restauration, version créée par la
// restauration. Site, file de travail et Runner lancés.
//
//   node scripts/captures-versions.js http://127.0.0.1:8123 docs/captures/versions "Maquis Chez Ama"
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/versions';
const nomProjet = process.argv[4] || 'Maquis Chez Ama';
const racine = process.env.KEKELI_RACINE || process.cwd();
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const id = execFileSync('php', ['artisan', 'tinker', '--execute', `echo App\\Models\\User::where('email', 'essai-versions@exemple.tg')->first()->projects()->where('name', ${JSON.stringify(nomProjet)})->latest('created_at')->value('id');`], { cwd: racine }).toString().trim();
const prefixe = nomProjet === 'Maquis Chez Ama' ? 'site' : 'application';

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [
      ['telephone', { ...devices['Pixel 7'] }],
      ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }],
    ]) {
      const nom = (n) => `${dossier}/${prefixe}-${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'essai-versions@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');
      await page.goto(`${base}/projets/${id}`);
      await attendre(1500);

      // 1. La version créée par la restauration, dans la discussion.
      await page.evaluate(() => {
        const lignes = [...document.querySelectorAll('[data-message], p, div')].filter((e) => /Retour à la version/.test(e.textContent ?? '') && e.children.length < 4);
        lignes[lignes.length - 1]?.scrollIntoView({ block: 'center' });
      });
      await attendre(400);
      await page.screenshot({ path: nom('4-version-creee-par-la-restauration') });

      // 2. La liste des versions.
      await page.locator('[data-versions-button]:visible').first().click();
      await page.waitForSelector('[data-versions-list]');
      await attendre(500);
      await page.screenshot({ path: nom('1-liste-des-versions') });

      // 3. Une ancienne version dans l'aperçu, avec le bandeau.
      await page.click('[data-version-view="2"]');
      await page.waitForSelector('[data-viewing-version="2"]');
      await page.waitForSelector('[data-version-busy]', { state: 'detached', timeout: 180000 }).catch(() => {});
      await attendre(5000);
      if (appareil === 'telephone') await page.click('[data-tab="apercu"], [data-mobile-tab="apercu"]').catch(() => {});
      await attendre(2500);
      await page.screenshot({ path: nom('2-apercu-ancienne-version') });

      // 4. La confirmation de restauration (sans confirmer : le projet ne change pas).
      await page.click('[data-banner-restore]');
      await page.waitForSelector('[data-restore-confirm]');
      await attendre(400);
      await page.screenshot({ path: nom('3-confirmation-restauration') });
      await page.keyboard.press('Escape');
      await attendre(300);

      // Remise en état : retour à la version actuelle.
      await page.click('[data-back-to-current]');
      await page.waitForSelector('[data-viewing-version]', { state: 'detached' });
      await page.waitForSelector('[data-version-busy]', { state: 'detached', timeout: 180000 }).catch(() => {});
      await contexte.close();
    }
  }

  await navigateur.close();
  if (erreurs.length) console.log('Erreurs de la page :', erreurs);
  console.log('Captures prêtes dans', dossier);
})();
