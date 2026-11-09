// Essai réel de l'étape 6 : le client envoie ses photos depuis son téléphone, puis demande son site.
// Le site (port 8123), la file d'attente et le Runner doivent tourner.
//
//   node scripts/essai-photos-client.js docs/captures/etape-6 photo-1.jpg photo-2.jpg
//
// Captures : la fenêtre « Mes photos » avant et après l'envoi, la discussion, puis la page d'accueil.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier, ...photos] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const DEMANDE = "Crée la page d'accueil du restaurant « Le Palmier » à Lomé, quartier Bè, spécialiste du poisson braisé. Utilise mes photos.";

(async () => {
  await mkdir(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  const email = `essai-photos-${Date.now()}@exemple.tg`;
  await page.goto(base + '/register');
  await page.fill('#name', 'Essai photos du client');
  await page.fill('#email', email);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.screenshot({ path: `${dossier}/photos-01-inscription-pays.png`, fullPage: true });
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  tinker(`App\\Models\\User::where("email", "${email}")->update(["plan" => "entreprise"]);`);

  await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
  await attendre(400);
  await page.getByLabel('Nom du projet').first().fill('Restaurant Le Palmier');
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await attendre(500);

  await page.locator('[data-open-photos]').click();
  await attendre(600);
  await page.locator('[data-photo-input]').setInputFiles(photos);
  await page.waitForFunction(() => !document.querySelector('[wire\\:loading][wire\\:target="uploads"]')?.offsetParent, null, { timeout: 60000 });
  await attendre(1500);
  await page.getByRole('button', { name: 'Ajouter ces photos' }).click();
  await page.waitForSelector('[data-client-photos] img', { timeout: 30000 });
  await attendre(1500);
  await page.screenshot({ path: `${dossier}/photos-02-mes-photos.png` });
  await page.keyboard.press('Escape');
  await attendre(500);

  const debut = Date.now();
  await page.getByLabel('Message').fill(DEMANDE);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForSelector('[data-run-status="completed"], [data-run-status="failed"]', { timeout: 30 * 60000 });
  const duree = Math.round((Date.now() - debut) / 1000);
  await attendre(1500);
  await page.locator('[data-run-status] details summary').last().click();
  await attendre(400);
  await page.screenshot({ path: `${dossier}/photos-03-discussion.png`, fullPage: true });

  await page.locator('[data-tab="apercu"]').click();
  await page.waitForSelector('[data-preview="live"] iframe', { timeout: 120000 });
  const adresse = await page.locator('[data-preview="live"] iframe').getAttribute('src');
  const site = await contexte.newPage();
  await site.goto(adresse);
  await attendre(3000);
  const images = await site.evaluate(() => [...document.images].map((img) => img.getAttribute('src')));
  await site.screenshot({ path: `${dossier}/photos-04-accueil.png`, fullPage: true });
  console.log(JSON.stringify({ duree, images }, null, 1));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
