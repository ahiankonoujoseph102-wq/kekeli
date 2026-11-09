// Captures de l'étape 8 sur un téléphone simulé (Pixel 7, navigateur en français).
// Préparer d'abord les données inventées :
//   php artisan tinker --execute="require 'scripts/preparer-captures-etape-8.php';"
// puis, avec le site lancé (php artisan serve --port=8123) :
//   node scripts/captures-etape-8.js docs/captures/etape-8 IDENTIFIANTS.json CAPTURE_DE_PAIEMENT.png
import { createRequire } from 'node:module';
import { readFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier, fichierIds, capturePaiement] = process.argv.slice(2);
const ids = JSON.parse(await readFile(fichierIds, 'utf8'));
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
const erreurs = [];

async function session(email) {
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  page.on('pageerror', (e) => erreurs.push(e.message));
  if (email) {
    await page.goto(base + '/login');
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', 'motdepasse-solide-2026');
    await page.click('button[type=submit]');
    await page.waitForURL('**/projets');
  }
  const capture = async (nom, pleine = true) => {
    await attendre(400);
    await page.screenshot({ path: `${dossier}/${nom}.png`, fullPage: pleine });
  };
  return { contexte, page, capture };
}

// 1. Inscription : tous les pays du monde, conditions à accepter.
{
  const { page, capture, contexte } = await session(null);
  await page.goto(base + '/register');
  await capture('01-inscription');
  await page.goto(base + '/conditions-d-utilisation');
  await capture('02-conditions', false);
  await page.goto(base + '/confidentialite');
  await page.locator('text=Formule gratuite : ce que vous devez savoir').scrollIntoViewIfNeeded();
  await capture('03-confidentialite-offre-gratuite', false);
  await contexte.close();
}

// 2. Paiement par une cliente au Bénin.
{
  const { page, capture, contexte } = await session('fifa-etape8@exemple.bj');
  await page.goto(base + '/formules');
  await capture('04-formules');
  await page.locator('[data-choose-plan="pro"]').click();
  await page.waitForURL('**/formules/pro/payer');
  await capture('05-payer-benin');
  await page.locator('[data-payment-country] select, select').first().selectOption('TG').catch(() => {});
  await attendre(800);
  await capture('06-payer-togo');
  await page.locator('select').first().selectOption('autre');
  await attendre(800);
  await capture('07-autre-pays-whatsapp');
  await page.locator('select').first().selectOption('BJ');
  await attendre(800);
  await page.setInputFiles('[data-screenshot-input]', capturePaiement);
  await page.waitForSelector('[data-screenshot-chosen]');
  await capture('08-capture-choisie');
  await page.locator('[data-send-payment]').click();
  await page.waitForSelector('[data-payment-pending]');
  await capture('09-paiement-envoye');
  await contexte.close();
}

// 3. L'administrateur vérifie et valide.
{
  const { page, capture, contexte } = await session('admin-etape8@exemple.tg');
  await page.goto(base + '/administration/paiements');
  await page.waitForSelector('[data-pending-payment] img');
  await attendre(800);
  await capture('10-administration-paiement-a-verifier');
  await page.locator('[data-payment-country-setting="TG"]').scrollIntoViewIfNeeded();
  await capture('11-administration-numeros', false);
  page.once('dialog', (d) => d.accept());
  await page.locator('[data-validate-payment]').first().click();
  await attendre(1200);
  await page.evaluate(() => window.scrollTo(0, 0));
  await capture('12-administration-paiement-valide');
  await contexte.close();
}

// 4. La cliente voit sa formule active.
{
  const { page, capture, contexte } = await session('fifa-etape8@exemple.bj');
  await page.goto(base + '/formules');
  await capture('13-formule-active');
  await contexte.close();
}

// 5. Rappel avant la fin, nom de domaine (formule payante).
{
  const { page, capture, contexte } = await session('kofi-etape8@exemple.tg');
  await page.goto(base + '/projets');
  await capture('14-rappel-fin-de-formule', false);
  await page.goto(base + '/projets/' + ids.boutique);
  await page.locator('[data-open-code]').click();
  await attendre(800);
  await capture('15-mon-code-formule-payante', false);
  await page.locator('[data-domain-record]').scrollIntoViewIfNeeded();
  await capture('16-nom-de-domaine-cname', false);
  await contexte.close();
}

// 6. « Mon code » en formule gratuite.
{
  const { page, capture, contexte } = await session('ama-etape8@exemple.tg');
  await page.goto(base + '/projets/' + ids.palmier);
  await page.locator('[data-open-code]').click();
  await attendre(800);
  await capture('17-mon-code-formule-gratuite', false);
  await contexte.close();
}

// 7. Pays bientôt disponible, file d'attente.
{
  const { page, capture, contexte } = await session('claire-etape8@exemple.fr');
  await page.goto(base + '/projets/' + ids.boulangerie);
  await page.locator('[data-coming-soon]').scrollIntoViewIfNeeded();
  await capture('18-bientot-disponible', false);
  await contexte.close();
}
{
  const { page, capture, contexte } = await session('yao-etape8@exemple.tg');
  await page.goto(base + '/projets/' + ids.ecole);
  await page.locator('[data-run-waiting-turn]').scrollIntoViewIfNeeded();
  await capture('19-file-d-attente', false);
  await contexte.close();
}

console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
await navigateur.close();
