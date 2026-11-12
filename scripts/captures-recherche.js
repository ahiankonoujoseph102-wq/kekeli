// Captures de la recherche sur Internet avec sources, sur téléphone (Pixel 7) et sur ordinateur, en mode clair et en
// mode sombre : une question avec recherche et ses sources (actions dépliées), une question sans recherche, la limite
// atteinte, et les trois finitions de la navigation (onglets des réglages, extrait de la recherche, question finale).
// Les messages partent une seule fois vers le vrai premier répondant (Gemini, Tavily et Exa gratuits) ; ensuite, on ne
// fait que regarder les pages. Compte et projets inventés.
//
//   php artisan serve --port=8123, php artisan queue:work et le Runner (dans d'autres terminaux)
//   node scripts/captures-recherche.js docs/captures/recherche
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/recherche';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const compte = JSON.parse(
  tinker(`
  $p = App\\Models\\Project::whereHas('user', fn ($q) => $q->where('email', 'like', 'essai-site-openrouter-north-%'))->where('name', 'Restaurant Le Palmier')->latest()->first();
  $p->user->forceFill(['password' => bcrypt('${MOT_DE_PASSE}'), 'country' => 'TG'])->save();
  echo json_encode(['email' => $p->user->email, 'id' => $p->user->id]);
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

/** Pose une question depuis « Nouveau » et attend la réponse ; renvoie l'adresse de la discussion. */
async function demander(page, texte) {
  await page.goto(base + '/projets');
  await page.locator('[data-site-prompt-input]').fill(texte);
  await page.locator('[data-prompt-send]').first().click();
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/, { timeout: 30000 });
  await page.waitForSelector('[data-question-answer]', { timeout: 180000 });
  await attendre(800);
  return page.url();
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  // 1. Les questions, une seule fois.
  const preparation = await navigateur.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fr-FR' });
  let page = await connecter(preparation);
  const avecRecherche = await demander(page, 'quel est le prix du sac de ciment à Lomé en ce moment');
  const sansRecherche = await demander(page, "c'est quoi un nom de domaine");
  const restaurant = await demander(page, "j'ai un restaurant");
  // La limite du jour atteinte pour ce compte : autant de recherches que sa formule en permet par jour.
  tinker(`$u = App\\Models\\User::find(${compte.id}); $n = app(App\\Agent\\WebSearchQuota::class)->perDay($u); $u->projects()->latest('updated_at')->first()->agentRuns()->create([])->forceFill(['web_searches' => $n, 'status' => 'completed'])->save();`);
  const limite = await demander(page, "quel est le taux de change du dollar en franc CFA aujourd'hui");
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

      await page.goto(avecRecherche);
      await attendre(1200);
      await page.locator('[data-sources]').last().scrollIntoViewIfNeeded();
      await page.screenshot({ path: nom('1-question-avec-recherche') });
      await page.locator('[data-narration] summary').last().click().catch(() => {});
      await attendre(400);
      await page.screenshot({ path: nom('2-recherche-dans-les-actions') });

      await page.goto(sansRecherche);
      await attendre(1200);
      await page.screenshot({ path: nom('3-question-sans-recherche') });

      await page.goto(limite);
      await attendre(1200);
      await page.screenshot({ path: nom('4-limite-atteinte') });

      // Les trois finitions.
      await page.goto(base + '/settings/profile');
      await attendre(900);
      await page.screenshot({ path: nom('5-finition-onglets-reglages') });

      await page.goto(base + '/projets');
      await attendre(800);
      if (appareil === 'telephone') await page.locator('[data-nav-toggle]').filter({ visible: true }).first().click();
      await attendre(500);
      await page.locator('[data-nav-search]').filter({ visible: true }).first().click();
      await page.locator('[data-search-input]').fill('domaine');
      await attendre(1300);
      await page.screenshot({ path: nom('6-finition-extrait-recherche') });

      await page.goto(restaurant);
      await attendre(1200);
      await page.screenshot({ path: nom('7-finition-question-finale') });
      await contexte.close();
    }
  }

  console.log(erreurs.length ? `Erreurs de la page : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans la page.');
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
