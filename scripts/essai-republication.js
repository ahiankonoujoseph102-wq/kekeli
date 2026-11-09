// Essai réel de l'étape 7 : le client demande une correction, puis touche « Publier à nouveau ».
//
//   node scripts/essai-republication.js docs/captures/etape-7 IDENTIFIANT_DU_PROJET "demande"
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier, projet, demande] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

(async () => {
  const email = tinker(`echo App\\Models\\Project::find("${projet}")->user->email;`);
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  await page.goto(`${base}/projets/${projet}`);
  await attendre(1500);

  const avant = await page.locator('[data-run-status]').count();
  await page.getByLabel('Message').fill(demande);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForFunction((n) => document.querySelectorAll('[data-run-status]').length > n, avant, { timeout: 60000 });
  await page.waitForFunction(() => { const s = [...document.querySelectorAll('[data-run-status]')].at(-1)?.dataset.runStatus; return ['completed', 'failed', 'stopped'].includes(s) }, null, { timeout: 30 * 60000 });
  await attendre(1500);
  await page.screenshot({ path: `${dossier}/08-correction-demandee.png`, fullPage: true });

  await page.locator('[data-publish]').click();
  // D'abord « publication en cours », puis la fin (sinon l'ancien état « publié » serait pris pour le nouveau).
  await page.waitForSelector('[data-publication="publishing"]', { timeout: 20000 });
  await page.waitForSelector('[data-publication="published"], [data-publication="failed"]', { timeout: 5 * 60000 });
  await attendre(800);
  await page.screenshot({ path: `${dossier}/09-publie-a-nouveau.png` });
  const adresse = await page.locator('[data-published-url]').getAttribute('href');

  const visiteur = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })).newPage();
  let reponse = null;
  for (let i = 0; i < 30 && !(reponse && reponse.status() === 200); i++) {
    reponse = await visiteur.goto(new URL('menu.html', adresse).toString()).catch(() => null);
    if (!(reponse && reponse.status() === 200)) await attendre(10000);
  }
  await attendre(2500);
  const chevauchements = await visiteur.evaluate(() => [...document.querySelectorAll('.prix')].filter((l) => {
    const [a, z] = [l.firstElementChild, l.lastElementChild];
    if (!a || !z || a === z) return false;
    const r = document.createRange();
    r.selectNodeContents(a);
    return [...r.getClientRects()].some((c) => c.right > z.getBoundingClientRect().left + 1);
  }).length);
  await visiteur.screenshot({ path: `${dossier}/10-menu-corrige-en-ligne.png`, fullPage: true });
  console.log(JSON.stringify({ adresse, statut: reponse?.status(), chevauchements }, null, 1));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
