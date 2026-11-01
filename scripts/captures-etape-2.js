// Captures de l'étape 2 : demander « crée un fichier bonjour.txt », voir l'agent travailler
// en direct, puis la page d'administration des fournisseurs.
//
// Utilisation, avec le site (port 8123), la file d'attente et le Runner lancés :
//   node scripts/captures-etape-2.js docs/captures/etape-2
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2];
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const prefixe = process.env.CAPTURES_PREFIXE || '';

(async () => {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await navigateur.newPage({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const erreurs = [];
  page.on('pageerror', e => erreurs.push(e.message));
  page.on('console', m => { if (m.type() === 'error') erreurs.push(m.text()); });
  const capture = async (nom, pleine = true) => page.screenshot({ path: `${dossier}/${prefixe}${nom}.png`, fullPage: pleine });

  const email = `joseph${Date.now()}@exemple.tg`;
  await page.goto(base + '/register');
  await page.fill('#name', 'Joseph Test');
  await page.fill('#email', email);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  execFileSync('php', ['artisan', 'kekeli:admin', email]);

  await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
  await page.waitForTimeout(400);
  await page.getByLabel('Nom du projet').first().fill('Mon premier essai');
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await page.waitForTimeout(500);
  await capture('01-projet-vide');

  await page.getByLabel('Message').fill(process.env.DEMANDE || 'Crée un fichier bonjour.txt contenant « Bonjour depuis Kekeli », puis affiche son contenu avec une commande.');
  await capture('02-demande-ecrite');
  await page.getByRole('button', { name: 'Envoyer' }).click();

  // Pendant le travail de l'agent.
  await page.waitForSelector('[data-run-status]');
  await page.waitForTimeout(1500);
  await capture('03-kekeli-se-prepare');
  await page.waitForSelector('[data-run-status="running"] li', { timeout: 180000 }).catch(() => {});
  await capture('04-kekeli-travaille');

  // Fin du travail.
  await page.waitForSelector('[data-run-status="completed"], [data-run-status="failed"], [data-run-status="waiting"]', { timeout: 600000 });
  await page.waitForTimeout(800);
  await capture('05-resultat');
  const actions = page.locator('details summary').first();
  if (await actions.count()) {
    await actions.click();
    await page.waitForTimeout(300);
    await capture('06-actions-de-kekeli');
  }

  await page.goto(base + '/administration/fournisseurs');
  await page.waitForTimeout(800);
  await capture('07-administration-fournisseurs');

  await page.goto(base + '/projets');
  await page.locator('header [data-flux-sidebar-toggle]').first().click();
  await page.waitForTimeout(500);
  await capture('08-menu-administration', false);

  console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
  await navigateur.close();
})().catch(e => { console.error(e); process.exit(1); });
