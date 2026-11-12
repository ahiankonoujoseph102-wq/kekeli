// Captures « avant / après » des finitions professionnelles de la discussion et de la recherche, sur téléphone
// (Pixel 7) et sur ordinateur, en mode clair et en mode sombre. Les données sont inventées et préparées par
// scripts/preparer-captures-finitions-pro.php : aucune requête au modèle ni à la recherche.
//
//   KEKELI_CAPTURES=apres php artisan tinker --execute="require 'scripts/preparer-captures-finitions-pro.php';"
//   php artisan serve --port=8123
//   node scripts/captures-finitions-pro.js apres http://127.0.0.1:8123 docs/captures/finitions-pro
// Pour « avant » : la même chose dans une copie de la version précédente (KEKELI_CAPTURES=avant, autre port).
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const etat = process.argv[2] || 'apres';
const base = process.argv[3] || 'http://127.0.0.1:8123';
const dossier = process.argv[4] || 'docs/captures/finitions-pro';
const racine = process.env.KEKELI_RACINE || process.cwd();
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const ids = JSON.parse(
  execFileSync('php', ['artisan', 'tinker', '--execute', `$u = App\\Models\\User::where('email', 'afi-finitions@exemple.tg')->first(); echo json_encode($u->projects()->pluck('id', 'name'));`], { cwd: racine }).toString().trim(),
);
const projet = (debut) => `${base}/projets/${Object.entries(ids).find(([nom]) => nom.startsWith(debut))[1]}`;

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [
      ['telephone', { ...devices['Pixel 7'] }],
      ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }],
    ]) {
      const nom = (n) => `${dossier}/${etat}-${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'afi-finitions@exemple.tg');
      await page.fill('input[type=password]', MOT_DE_PASSE);
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');
      const telephone = appareil === 'telephone';

      // 1. Réponse avec recherche : dates, pastilles, « A cherché sur Internet · N sources » déplié, Copier et Régénérer.
      await page.goto(projet(etat === 'avant' ? 'Quel est le prix' : 'Prix du ciment'));
      await attendre(900);
      await page.locator('[data-web-search] summary, [data-narration] summary').first().click();
      await attendre(300);
      await page.screenshot({ path: nom('1-recherche') });

      // 2. Limite de recherche atteinte : la réponse, puis le menu du compte (recherches restantes).
      await page.goto(projet(etat === 'avant' ? 'Quel est le taux' : 'Taux du dollar'));
      await attendre(900);
      await page.screenshot({ path: nom('2-limite-atteinte') });
      if (telephone) {
        await page.locator('[data-nav-toggle]').first().click();
        await attendre(500);
      }
      await page.locator('[data-nav-account]').click();
      await attendre(500);
      await page.screenshot({ path: nom('3-menu-du-compte') });
      await page.keyboard.press('Escape');

      // 3. Proposition de créer le site : bouton « Oui, créer le site ».
      await page.goto(projet(etat === 'avant' ? "J'ai un restaurant" : 'Restaurant Le Palmier'));
      await attendre(900);
      await page.screenshot({ path: nom('4-oui-creer-le-site') });

      // 4. Pendant que Kekeli répond : bouton « Arrêter ».
      await page.goto(projet(etat === 'avant' ? 'Donne moi des conseils' : 'Conseils pour attirer'));
      await attendre(900);
      await page.screenshot({ path: nom('5-arreter') });

      // 5. Titres des discussions dans « Récents » ; sur téléphone, « Rechercher » referme le menu.
      await page.goto(base + '/projets');
      await attendre(700);
      if (telephone) {
        await page.locator('[data-nav-toggle]').first().click();
        await attendre(600);
      }
      await page.screenshot({ path: nom('6-titres') });
      if (telephone) {
        await page.locator('[data-nav-search]').click();
        await attendre(900);
        await page.screenshot({ path: nom('7-rechercher') });
      }
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log(erreurs.length ? `Erreurs dans les pages : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans les pages.');
})();
