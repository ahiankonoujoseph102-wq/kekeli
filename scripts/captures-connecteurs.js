// Captures « Connecteurs (MCP) », sur téléphone (Pixel 7) et sur ordinateur, en mode clair et en mode sombre :
// catalogue, connexion (DeepWiki, sans compte), menu « + » → « Connecteurs », demande d'accord, ligne « A utilisé »,
// journal des actions. Données préparées par scripts/preparer-captures-connecteurs.php.
//
//   php artisan tinker --execute="require 'scripts/preparer-captures-connecteurs.php';"
//   php artisan serve --port=8123   (et le Runner, pour la page de consentement de Notion)
//   node scripts/captures-connecteurs.js http://127.0.0.1:8123 docs/captures/connecteurs/ecrans
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/connecteurs/ecrans';
const racine = process.env.KEKELI_RACINE || process.cwd();
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const ids = JSON.parse(
  execFileSync('php', ['artisan', 'tinker', '--execute', `$u = App\\Models\\User::where('email', 'kossi-connecteurs@exemple.tg')->first(); echo json_encode($u->projects()->pluck('id', 'name'));`], { cwd: racine }).toString().trim(),
);
const projet = (nom) => `${base}/projets/${ids[nom]}`;
const montrer = (page, selecteur, bloc = 'center') => page.evaluate(([s, b]) => document.querySelector(s)?.scrollIntoView({ block: b }), [selecteur, bloc]);

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [
      ['telephone', { ...devices['Pixel 7'] }],
      ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }],
    ]) {
      const nom = (n) => `${dossier}/${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'kossi-connecteurs@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');

      // 1. Le catalogue (Réglages → Connecteurs).
      await page.goto(base + '/settings/connecteurs');
      await attendre(700);
      await montrer(page, '[data-connectors]', 'start');
      await page.screenshot({ path: nom('1-catalogue'), fullPage: appareil === 'telephone' });

      // 2. La connexion : DeepWiki se connecte sans compte, Kekeli le confirme.
      await page.click('[data-connector-connect="deepwiki"]');
      await page.waitForSelector('[data-connector-status]');
      await attendre(400);
      await montrer(page, '[data-connector="deepwiki"]');
      await page.screenshot({ path: nom('2-connexion') });

      // 3. Le menu « + » → « Connecteurs » dans une discussion.
      await page.goto(projet('Menu de la semaine'));
      await attendre(900);
      await page.click('[data-prompt-plus]');
      await attendre(300);
      await attendre(500);
      await page.screenshot({ path: nom('3-menu-plus-connecteurs') });
      await page.keyboard.press('Escape');
      await page.keyboard.press('Escape');
      await attendre(300);

      // 4. La demande d'accord avant une action qui modifie, et 5. la ligne « A utilisé Notion » dépliée.
      await page.goto(projet('Menu de la semaine'));
      await attendre(900);
      await montrer(page, '[data-connector-approval]');
      await attendre(300);
      await page.screenshot({ path: nom('4-demande-accord') });
      await page.goto(projet('Menu de la semaine'));
      await attendre(900);
      await montrer(page, '[data-connectors-used]', 'start');
      await page.locator('[data-connectors-used] summary').click();
      await attendre(300);
      await montrer(page, '[data-connectors-used]', 'start');
      await page.screenshot({ path: nom('5-a-utilise-notion') });

      // 6. Le journal des actions.
      await page.goto(base + '/settings/connecteurs');
      await attendre(700);
      await montrer(page, '[data-connector-journal]', appareil === 'telephone' ? 'start' : 'center');
      await page.screenshot({ path: nom('6-journal') });

      // Remise en état pour la capture suivante : DeepWiki déconnecté.
      await page.click('[data-connector-disconnect="deepwiki"]');
      await page.waitForSelector('[data-connector-status]');
      await contexte.close();
    }
  }

  // La vraie page de consentement de Notion, ouverte par « Connecter » (inscription automatique de Kekeli et PKCE
  // faites par le Runner). Une seule capture : la page appartient à Notion.
  if (process.env.CAPTURE_NOTION === '1') {
    const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', ignoreHTTPSErrors: true, proxy: process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined });
    const page = await contexte.newPage();
    await page.goto(base + '/login');
    await page.fill('input[type=email]', 'kossi-connecteurs@exemple.tg');
    await page.fill('input[type=password]', 'motdepasse-solide-2026');
    await page.click('button[type=submit]');
    await page.waitForURL('**/projets**');
    await page.goto(base + '/connecteurs/notion/connecter').catch((e) => erreurs.push(`Notion : ${e.message}`));
    await attendre(5000);
    console.log('Page de consentement :', new URL(page.url()).host);
    await page.screenshot({ path: `${dossier}/telephone-2b-consentement-notion.png` });
    await contexte.close();
  }

  await navigateur.close();
  if (erreurs.length) console.log('Erreurs de la page :', erreurs);
  console.log('Captures prêtes dans', dossier);
})();
