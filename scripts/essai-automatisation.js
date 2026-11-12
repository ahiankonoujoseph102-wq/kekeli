// Essai réel de la création d'une automatisation, comme un client sur un téléphone simulé : la demande dans « Que
// voulez-vous créer aujourd'hui ? », le plan proposé, « Construire ce plan », la construction dans un vrai ordinateur
// E2B (modèle de construction de la formule Pro), une adresse d'e-mail inventée dans les Variables secrètes, puis
// « Lancer maintenant » avec son journal lisible. Sans clé d'e-mail, le journal dit ce qu'il reste à régler.
// Le site, la file d'attente et le Runner doivent tourner (avec le même KEKELI_RUNNER_SECRET).
//
//   php artisan tinker --execute="require 'scripts/preparer-essai-automatisation.php';"
//   node scripts/essai-automatisation.js docs/captures/github-automatisations
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/github-automatisations';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const DEMANDE = "Je tiens une boutique de pagnes à Lomé. Chaque matin à 7 h (heure de Lomé), je veux recevoir par e-mail un court résumé avec trois conseils du jour pour un commerçant : vente, relation client et gestion. Utilise le service d'e-mail gratuit Resend.";

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })).newPage();
  const capture = (nom) => page.screenshot({ path: `${dossier}/essai-telephone-${nom}.png` });
  const debut = Date.now();
  const temps = () => `${Math.round((Date.now() - debut) / 1000)} s`;

  await page.goto(base + '/login');
  await page.fill('input[type=email]', 'kofi-automatisation@exemple.tg');
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');

  // 1. La demande, puis le plan proposé.
  await page.locator('textarea').first().fill(DEMANDE);
  await page.locator('[data-prompt-send]').first().click();
  await page.waitForURL('**/projets/**');
  await page.locator('[data-build-plan]').waitFor({ timeout: 180000 });
  console.log('Plan proposé', temps());
  await attendre(800);
  await capture('1-plan');

  // 2. « Construire ce plan ».
  await page.locator('[data-build-plan]').click();
  let statut = null;
  let pendant = false;
  while (Date.now() - debut < 40 * 60000) {
    await attendre(5000);
    const cartes = page.locator('[data-run-status]');
    statut = (await cartes.count()) ? await cartes.last().getAttribute('data-run-status') : null;
    if (!pendant && statut === 'running') {
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

  // 3. Une adresse d'e-mail inventée dans les Variables secrètes (aucune vraie clé).
  await page.locator('[data-project-more]').click();
  await page.locator('[data-open-secrets]').click();
  await attendre(600);
  await page.locator('[data-secret-form] input').first().fill('EMAIL_DESTINATAIRE');
  await page.locator('[data-secret-form] input[type=password]').fill('kofi@exemple.tg');
  await page.locator('[data-secret-form] button[type=submit]').click();
  await attendre(1500);
  await capture('4-variables-secretes');
  await page.keyboard.press('Escape');

  // 4. « Lancer maintenant » et son journal.
  await page.goto(page.url().split('?')[0] + '?vue=apercu');
  await page.locator('[data-launch-now]').waitFor({ timeout: 30000 });
  await page.locator('[data-launch-now]').click();
  let lancement = null;
  for (let i = 0; i < 120; i++) {
    await attendre(5000);
    lancement = await page.locator('[data-last-launch]').getAttribute('data-last-launch').catch(() => null);
    if (lancement && lancement !== 'running') break;
  }
  console.log('Lancement', lancement, temps());
  await attendre(1000);
  await capture('5-lancer-maintenant');
  const journal = await page.locator('[data-launch-panel]').innerText();
  console.log(journal);

  const projet = page.url().split('/projets/')[1].split('?')[0];
  writeFileSync(`${dossier}/essai.json`, JSON.stringify({ projet, statut, lancement, secondes: Math.round((Date.now() - debut) / 1000) }, null, 2));
  console.log('Projet', projet, temps());
  await navigateur.close();
})();
