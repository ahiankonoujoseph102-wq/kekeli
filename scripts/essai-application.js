// Essai réel de la création d'une application (E2B, modèle de construction de la formule Pro) sur un téléphone simulé,
// comme un client : la demande dans « Que voulez-vous créer aujourd'hui ? », le plan proposé, « Construire ce plan »,
// la construction avec ses actions repliées, l'application dans l'aperçu, puis une Variable secrète.
// Le site, la file d'attente et le Runner doivent tourner (avec le même KEKELI_RUNNER_SECRET).
//
//   php artisan tinker --execute="require 'scripts/preparer-essai-application.php';"
//   node scripts/essai-application.js docs/captures/applications
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/applications';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const DEMANDE = "Je veux une application pour gérer les rendez-vous de mon salon de coiffure Belle Afi à Lomé : mes clientes créent un compte, prennent rendez-vous et l'annulent si besoin ; moi, je confirme ou je refuse dans l'administration.";

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })).newPage();
  const capture = (nom) => page.screenshot({ path: `${dossier}/essai-telephone-${nom}.png` });
  const debut = Date.now();
  const temps = () => `${Math.round((Date.now() - debut) / 1000)} s`;

  await page.goto(base + '/login');
  await page.fill('input[type=email]', 'afi-application@exemple.tg');
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');

  // 1. La demande, puis le plan proposé par Kekeli.
  await page.locator('textarea').first().fill(DEMANDE);
  await page.locator('[data-prompt-send]').first().click();
  await page.waitForURL('**/projets/**');
  await page.locator('[data-build-plan]').waitFor({ timeout: 180000 });
  console.log('Plan proposé', temps());
  await attendre(800);
  await capture('1-plan');

  // 2. « Construire ce plan » : la construction dans l'ordinateur E2B.
  await page.locator('[data-build-plan]').click();
  let pendant = false;
  let statut = null;
  while (Date.now() - debut < 50 * 60000) {
    await attendre(5000);
    const cartes = page.locator('[data-run-status]');
    statut = (await cartes.count()) ? await cartes.last().getAttribute('data-run-status') : null;
    if (!pendant && statut === 'running' && (await page.locator('[data-run-working]').count())) {
      await capture('2-construction-en-cours');
      pendant = true;
    }
    if (['completed', 'failed', 'stopped'].includes(statut)) break;
  }
  console.log('Construction terminée', statut, temps());
  await attendre(1500);
  const actions = page.locator('[data-narration] summary').last();
  if (await actions.count()) await actions.click();
  await attendre(500);
  await capture('3-construction-terminee');

  // 3. L'application dans l'aperçu (la vue « Aperçu » du téléphone).
  await page.goto(page.url().split('?')[0] + '?vue=apercu');
  await attendre(15000);
  await capture('4-apercu');

  // 4. Variables secrètes : une variable ajoutée, jamais réaffichée.
  await page.goto(page.url().split('?')[0]);
  await attendre(1500);
  await page.locator('[data-project-more]').click();
  await page.locator('[data-open-secrets]').click();
  await attendre(600);
  await page.locator('[data-secret-form] input').first().fill('DATABASE_URL');
  await page.locator('[data-secret-form] input[type=password]').fill('postgresql://postgres:mot-de-passe-invente@db.exemple.supabase.co:5432/postgres');
  await page.locator('[data-secret-form] button[type=submit]').click();
  await attendre(1500);
  await capture('5-variables-secretes');

  const projet = page.url().split('/projets/')[1].split('?')[0];
  writeFileSync(`${dossier}/essai.json`, JSON.stringify({ projet, statut, secondes: Math.round((Date.now() - debut) / 1000) }, null, 2));
  console.log('Projet', projet, temps());
  await navigateur.close();
})();
