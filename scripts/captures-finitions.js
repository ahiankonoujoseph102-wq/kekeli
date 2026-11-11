// Captures des finitions du design : le message final de Kekeli (paragraphes et listes) et le bloc « Compte
// administrateur d'essai » au-dessus de l'aperçu, sur téléphone (Pixel 7) et sur ordinateur, en mode clair et sombre.
// Comptes et projets inventés (essais réels du 10 et du 11 novembre 2026).
//
//   php artisan serve --port=8123   (dans un autre terminal)
//   node scripts/captures-finitions.js docs/captures/design/finitions/avant
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/design/finitions/apres';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const projets = JSON.parse(
  tinker(`
  $trouver = fn ($courriel, $nom) => App\\Models\\Project::whereHas('user', fn ($q) => $q->where('email', 'like', $courriel))->where('name', $nom)->latest()->first();
  $vitrine = $trouver('essai-site-openrouter-north-%', 'Restaurant Le Palmier');
  $base = $trouver('essai-site-gemini-chaine-pressing-%', 'Pressing Éclat');
  foreach ([$vitrine, $base] as $p) { $p->user->forceFill(['password' => bcrypt('${MOT_DE_PASSE}'), 'country' => 'TG'])->save(); }
  echo json_encode(collect(compact('vitrine', 'base'))->map(fn ($p) => ['id' => $p->id, 'email' => $p->user->email]));
`),
);

async function connecter(contexte, email) {
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', MOT_DE_PASSE);
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  return page;
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
      const options = { ...reglages, locale: 'fr-FR', colorScheme: theme };

      // 1. Le message final de Kekeli dans la discussion.
      const client = await navigateur.newContext(options);
      let page = await connecter(client, projets.vitrine.email);
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(`${base}/projets/${projets.vitrine.id}`);
      await attendre(1500);
      await page.screenshot({ path: nom('1-message-final') });
      await client.close();

      // 2. Le compte administrateur d'essai, fermé puis ouvert, au-dessus de l'aperçu.
      const clientBase = await navigateur.newContext(options);
      page = await connecter(clientBase, projets.base.email);
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(`${base}/projets/${projets.base.id}?vue=apercu`);
      await attendre(2500);
      await page.screenshot({ path: nom('2-compte-essai-ferme') });
      await page.locator('[data-test-admin] summary').first().click();
      await attendre(500);
      // Le mot de passe d'essai n'est jamais affiché sur une capture, même s'il est inventé.
      await page.screenshot({ path: nom('3-compte-essai-ouvert') });
      await clientBase.close();
    }
  }

  console.log(erreurs.length ? `Erreurs de la page : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans la page.');
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
