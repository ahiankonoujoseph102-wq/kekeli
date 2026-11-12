// Captures de la navigation, AVANT et APRÈS la pull request « Navigation professionnelle » : barre latérale, menu ☰
// ouvert, recherche, Récents filtré, menu « + » ouvert, réglages ; sur téléphone (Pixel 7) et sur ordinateur, en mode
// clair et en mode sombre. Un élément qui n'existe pas encore (avant) est simplement sauté. Compte et projets inventés.
//
//   php artisan serve --port=8123   (dans un autre terminal)
//   node scripts/captures-navigation.js docs/captures/navigation/apres
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/navigation/apres';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const compte = JSON.parse(
  tinker(`
  $p = App\\Models\\Project::whereHas('user', fn ($q) => $q->where('email', 'like', 'essai-site-openrouter-north-%'))->where('name', 'Restaurant Le Palmier')->latest()->first();
  $p->user->forceFill(['password' => bcrypt('${MOT_DE_PASSE}'), 'country' => 'TG'])->save();
  echo json_encode(['email' => $p->user->email, 'site' => $p->id]);
`),
);

async function cliquerSiPresent(page, selecteur) {
  const element = page.locator(selecteur).filter({ visible: true }).first();
  if (!(await element.count())) return false;
  const ok = await element.click({ timeout: 4000 }).then(() => true, () => false);
  await attendre(700);
  return ok;
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [
      ['telephone', { ...devices['Pixel 7'] }],
      ['ordinateur', { viewport: { width: 1440, height: 900 } }],
    ]) {
      const nom = (n) => `${dossier}/${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(base + '/login');
      await page.fill('input[type=email]', compte.email);
      await page.fill('input[type=password]', MOT_DE_PASSE);
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');
      await attendre(1000);

      // 1. L'accueil connecté : la barre latérale (ordinateur) ou la barre du haut (téléphone).
      await page.goto(base + '/');
      await attendre(1000);
      await page.screenshot({ path: nom('1-accueil-connecte') });

      // 2. Le menu ☰ ouvert (téléphone).
      if (appareil === 'telephone' && (await cliquerSiPresent(page, '[data-nav-toggle]'))) {
        await page.screenshot({ path: nom('2-menu-ouvert') });
        await page.keyboard.press('Escape');
        await page.goto(base + '/projets');
        await attendre(800);
      }

      // 3. La recherche (sur un téléphone, depuis le menu ☰).
      if (appareil === 'telephone') await cliquerSiPresent(page, '[data-nav-toggle]');
      if (await cliquerSiPresent(page, '[data-nav-search]')) {
        await page.locator('[data-search-input]').fill('domaine');
        await attendre(1200);
        await page.screenshot({ path: nom('3-recherche') });
        await page.keyboard.press('Escape');
        await attendre(400);
      }

      // 4. Récents filtré sur les discussions (avant : la liste « Mes projets »).
      await page.goto(base + '/projets');
      await attendre(900);
      if (appareil === 'telephone') await cliquerSiPresent(page, '[data-nav-toggle]');
      if (await cliquerSiPresent(page, '[data-recents-filter="chat"]')) {
        await page.screenshot({ path: nom('4-recents-discussions') });
      } else {
        await page.screenshot({ path: nom('4-mes-projets'), fullPage: true });
      }

      // 5. Le menu « + » de la zone de saisie (avant : les boutons séparés).
      await page.goto(base + '/projets');
      await attendre(900);
      if (await cliquerSiPresent(page, '[data-prompt-plus]')) {
        await page.screenshot({ path: nom('5-menu-plus') });
      } else {
        await page.screenshot({ path: nom('5-zone-de-saisie') });
      }

      // 6. La page d'un projet.
      await page.goto(`${base}/projets/${compte.site}`);
      await attendre(1500);
      await page.screenshot({ path: nom('6-projet') });

      // 7. Les réglages.
      await page.goto(base + '/settings/profile');
      await attendre(900);
      await page.screenshot({ path: nom('7-reglages-profil') });
      await page.goto(base + '/settings/appearance');
      await attendre(900);
      await page.screenshot({ path: nom('8-reglages-apparence') });
      await contexte.close();
    }
  }

  console.log(erreurs.length ? `Erreurs de la page : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans la page.');
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
