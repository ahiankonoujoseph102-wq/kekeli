// Captures de « Une seule zone pour tout » (premier répondant), sur téléphone (Pixel 7) et sur ordinateur, en mode
// clair et en mode sombre : une question depuis l'accueil (elle ouvre une discussion), la discussion dans « Mes
// projets », une question dans un projet de site, et la question courte posée en cas de doute.
// Les trois messages partent une seule fois, vers le vrai premier répondant (Gemini gratuit) ; ensuite, on ne fait que
// regarder les pages. Comptes et projets inventés.
//
//   php artisan serve --port=8123, php artisan queue:work et le Runner (dans d'autres terminaux)
//   node scripts/captures-une-seule-zone.js docs/captures/une-seule-zone
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/une-seule-zone';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

// Le compte du site « Restaurant Le Palmier » (essai réel du 10 novembre 2026).
const compte = JSON.parse(
  tinker(`
  $p = App\\Models\\Project::whereHas('user', fn ($q) => $q->where('email', 'like', 'essai-site-openrouter-north-%'))->where('name', 'Restaurant Le Palmier')->latest()->first();
  $p->user->forceFill(['password' => bcrypt('${MOT_DE_PASSE}'), 'country' => 'TG'])->save();
  echo json_encode(['email' => $p->user->email, 'site' => $p->id]);
`),
);

async function connecter(contexte) {
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', compte.email);
  await page.fill('input[type=password]', MOT_DE_PASSE);
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  return page;
}

/** Envoie un message dans la page ouverte et attend la réponse du premier répondant. */
async function poser(page, champ, texte) {
  const avant = await page.locator('[data-question-answer]').count();
  await page.locator(champ).fill(texte);
  await page.locator('[data-prompt-send]').first().click();
  await page.waitForFunction((n) => document.querySelectorAll('[data-question-answer]').length > n, avant, { timeout: 120000 });
  await attendre(800);
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  // 1. Les trois messages, une seule fois (ordinateur, mode clair).
  const preparation = await navigateur.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
  let page = await connecter(preparation);
  page.on('pageerror', (e) => erreurs.push(e.message));
  await page.goto(base + '/');
  await page.locator('[data-site-prompt-input]').fill('combien coûte un nom de domaine pour mon restaurant');
  await page.locator('[data-prompt-send]').click();
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/, { timeout: 30000 });
  const discussion = page.url();
  await page.waitForSelector('[data-question-answer]', { timeout: 120000 });
  await page.goto(`${base}/projets/${compte.site}`);
  await attendre(1200);
  await poser(page, '[data-message-input]', 'Où héberger mon site ?');
  await poser(page, '[data-message-input]', 'la page contact');
  await preparation.close();

  // 2. Les captures.
  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [
      ['telephone', { ...devices['Pixel 7'] }],
      ['ordinateur', { viewport: { width: 1440, height: 900 } }],
    ]) {
      const nom = (n) => `${dossier}/${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      page = await connecter(contexte);
      page.on('pageerror', (e) => erreurs.push(e.message));

      await page.goto(base + '/');
      await page.locator('[data-site-prompt-input]').fill('combien coûte un nom de domaine pour mon restaurant');
      await attendre(600);
      await page.screenshot({ path: nom('1-accueil-question') });

      await page.goto(discussion);
      await attendre(1200);
      await page.screenshot({ path: nom('2-discussion-reponse') });

      await page.goto(base + '/projets');
      await attendre(1000);
      await page.locator('[data-project-icon="chat"]').first().scrollIntoViewIfNeeded();
      await attendre(300);
      await page.screenshot({ path: nom('3-mes-projets-discussion') });

      await page.goto(`${base}/projets/${compte.site}`);
      await attendre(1500);
      const reponses = page.locator('[data-question-answer]');
      await reponses.nth((await reponses.count()) - 2).scrollIntoViewIfNeeded();
      await attendre(400);
      await page.screenshot({ path: nom('4-site-question') });
      await page.evaluate(() => document.querySelector('[data-conversation]')?.scrollTo(0, 1e6));
      await attendre(400);
      await page.screenshot({ path: nom('5-site-doute') });
      await contexte.close();
    }
  }

  console.log(erreurs.length ? `Erreurs de la page : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans la page.');
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
