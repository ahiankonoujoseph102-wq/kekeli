// Captures « publier et récupérer son site » : site avec base de données, site vitrine, site publié, question dans
// la discussion, sur téléphone (Pixel 7) et sur ordinateur. Comptes et projets inventés.
//
//   node scripts/captures-publier.js docs/captures/publier/avant   (ancienne version sur KEKELI_URL)
//   node scripts/captures-publier.js docs/captures/publier/apres
//   KEKELI_URL=http://127.0.0.1:8125 node scripts/captures-publier.js docs/captures/publier/apres github
//     (même chose, avec l'accès à GitHub réglé : « Relier à GitHub » apparaît)
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/publier/apres';
const avecGithub = process.argv[3] === 'github';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();
const projets = JSON.parse(tinker(`
  $p = fn ($courriel) => App\\Models\\User::where('email', $courriel)->first()->projects()->first()->id;
  echo json_encode(['vitrine' => $p('ama-nettoyage@exemple.tg'), 'base' => $p('kofi-nettoyage@exemple.tg'), 'publie' => $p('efua-nettoyage@exemple.tg')]);
`));

async function connecter(contexte, email) {
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', MOT_DE_PASSE);
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  return page;
}

async function fenetre(page) {
  await page.locator('[data-publish-open]').first().click().catch(() => {});
  await attendre(900);
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const appareils = [
    ['telephone', { ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' }],
    ['ordinateur', { viewport: { width: 1440, height: 900 }, locale: 'fr-FR', colorScheme: 'light' }],
  ];
  for (const [appareil, reglages] of appareils) {
    const nom = (n) => `${dossier}/${appareil}-${n}${avecGithub ? '-avec-github' : ''}.png`;

    const kofi = await navigateur.newContext(reglages);
    let page = await connecter(kofi, 'kofi-nettoyage@exemple.tg');
    await page.goto(`${base}/projets/${projets.base}`);
    await attendre(1500);
    if (!avecGithub) await page.screenshot({ path: nom('01-site-avec-base-de-donnees') });
    await fenetre(page);
    await page.screenshot({ path: nom('02-site-avec-base-de-donnees-fenetre') });
    if (!avecGithub) {
      await page.goto(`${base}/projets/${projets.base}/mise-en-ligne`);
      await attendre(1200);
      await page.screenshot({ path: nom('03-ancienne-adresse-du-guide'), fullPage: appareil === 'telephone' });
      await page.goto(`${base}/projets/${projets.base}?vue=code`);
      await attendre(1500);
      await page.screenshot({ path: nom('04-site-avec-base-de-donnees-code') });
    }
    await kofi.close();

    const ama = await navigateur.newContext(reglages);
    page = await connecter(ama, 'ama-nettoyage@exemple.tg');
    await page.goto(`${base}/projets/${projets.vitrine}`);
    await attendre(1500);
    await fenetre(page);
    await page.screenshot({ path: nom('05-site-vitrine-fenetre') });
    await ama.close();

    if (!avecGithub) {
      const efua = await navigateur.newContext(reglages);
      page = await connecter(efua, 'efua-nettoyage@exemple.tg');
      await page.goto(`${base}/projets/${projets.publie}`);
      await attendre(1500);
      await fenetre(page);
      await page.screenshot({ path: nom('06-site-publie-fenetre') });
      await efua.close();
    }
  }
  await navigateur.close();
  console.log('Captures prises.');
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
