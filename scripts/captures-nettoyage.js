// Captures de tous les écrans du client, sur téléphone (Pixel 7) et sur ordinateur, AVANT et APRÈS le nettoyage.
// Comptes et projets inventés (scripts/preparer-captures-nettoyage.php).
//
//   php artisan migrate:fresh --force
//   php artisan tinker --execute="require 'scripts/preparer-captures-nettoyage.php';"
//   php artisan serve --port=8123   (dans un autre terminal)
//   node scripts/captures-nettoyage.js docs/captures/nettoyage/avant
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/nettoyage/apres';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const projets = JSON.parse(
  tinker(`
  $p = fn ($courriel) => App\\Models\\User::where('email', $courriel)->first()->projects()->first()->id;
  echo json_encode(['vitrine' => $p('ama-nettoyage@exemple.tg'), 'base' => $p('kofi-nettoyage@exemple.tg'), 'attente' => $p('yao-nettoyage@exemple.tg'), 'publie' => $p('efua-nettoyage@exemple.tg')]);
`),
);
// L'aperçu redevient « en pause » avant chaque série de captures (l'ouverture de l'onglet Aperçu le rallume).
const endormir = () => tinker(`App\\Models\\Project::query()->update(['preview_waking_at' => null]);`);

async function connecter(contexte, email) {
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', MOT_DE_PASSE);
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  return page;
}

async function vue(page, nom) {
  const onglet = page.locator(`[data-tab="${nom}"]:visible, [data-desktop-tab="${nom}"]:visible`).first();
  if (await onglet.count()) {
    await onglet.click().catch(() => {});
    await attendre(1500);
  }
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  for (const [appareil, reglages] of [
    ['telephone', { ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' }],
    ['ordinateur', { viewport: { width: 1440, height: 900 }, locale: 'fr-FR', colorScheme: 'light' }],
  ]) {
    const nom = (n) => `${dossier}/${appareil}-${n}.png`;
    const tel = appareil === 'telephone';
    endormir();

    // Pages ouvertes à tous.
    const visiteur = await navigateur.newContext(reglages);
    const accueil = await visiteur.newPage();
    accueil.on('pageerror', (e) => erreurs.push(e.message));
    await accueil.goto(base + '/');
    await attendre(800);
    await accueil.screenshot({ path: nom('01-accueil'), fullPage: true });
    await accueil.goto(base + '/register');
    await attendre(600);
    await accueil.screenshot({ path: nom('02-inscription'), fullPage: true });
    await accueil.goto(base + '/login');
    await attendre(600);
    await accueil.screenshot({ path: nom('03-connexion'), fullPage: true });
    await visiteur.close();

    // Site vitrine terminé.
    const client = await navigateur.newContext(reglages);
    let page = await connecter(client, 'ama-nettoyage@exemple.tg');
    page.on('pageerror', (e) => erreurs.push(e.message));
    await attendre(800);
    await page.screenshot({ path: nom('04-mes-projets'), fullPage: tel });
    await page.goto(`${base}/projets/${projets.vitrine}`);
    await attendre(1500);
    await page.screenshot({ path: nom('05-projet-discussion') });
    if (tel) {
      await page.screenshot({ path: nom('06-projet-discussion-complete'), fullPage: true });
    }
    // Le détail du travail, déplié.
    await page.locator('[data-run-status] details summary').first().click().catch(() => {});
    await attendre(500);
    await page.screenshot({ path: nom('07-projet-actions-depliees'), fullPage: tel });
    await page.goto(`${base}/projets/${projets.vitrine}`);
    await attendre(1200);
    await vue(page, 'apercu');
    await page.screenshot({ path: nom('08-projet-apercu') });
    await vue(page, 'code');
    await attendre(800);
    await page.screenshot({ path: nom('09-projet-code'), fullPage: tel });
    await page.goto(`${base}/projets/${projets.vitrine}`);
    await attendre(1200);
    await page.locator('[data-publish-open]').first().click().catch(() => {});
    await attendre(800);
    await page.screenshot({ path: nom('10-projet-mettre-en-ligne') });
    await page.keyboard.press('Escape');
    await attendre(400);
    await page.locator('[data-project-more]').first().click().catch(() => {});
    await attendre(600);
    await page.screenshot({ path: nom('11-projet-menu') });
    await page.goto(base + '/projets');
    await attendre(600);
    await page.locator('[data-flux-profile]').last().click({ timeout: 5000 }).catch((e) => console.log('Menu du compte :', e.message.split('\n')[0]));
    await attendre(600);
    await page.screenshot({ path: nom('12-menu-du-compte') });
    await page.goto(base + '/formules');
    await attendre(800);
    await page.screenshot({ path: nom('13-formules'), fullPage: true });
    await page.goto(base + '/formules/pro/payer');
    await attendre(800);
    await page.screenshot({ path: nom('14-paiement'), fullPage: true });
    for (const [n, adresse] of [['15-reglages-profil', '/settings/profile'], ['16-reglages-mot-de-passe', '/settings/password'], ['17-reglages-apparence', '/settings/appearance']]) {
      await page.goto(base + adresse);
      await attendre(700);
      await page.screenshot({ path: nom(n), fullPage: true });
    }
    await client.close();

    // Site avec base de données terminé.
    endormir();
    const clientBase = await navigateur.newContext(reglages);
    page = await connecter(clientBase, 'kofi-nettoyage@exemple.tg');
    await page.goto(`${base}/projets/${projets.base}`);
    await attendre(1500);
    await page.screenshot({ path: nom('18-site-avec-base-de-donnees') });
    await vue(page, 'apercu');
    await page.screenshot({ path: nom('19-site-avec-base-de-donnees-apercu') });
    await page.goto(`${base}/projets/${projets.base}`);
    await attendre(1200);
    await page.locator('[data-publish-open]').first().click().catch(() => {});
    await attendre(800);
    await page.screenshot({ path: nom('20-mettre-en-ligne-base-de-donnees') });
    await page.goto(`${base}/projets/${projets.base}/mise-en-ligne`);
    await attendre(1000);
    await page.screenshot({ path: nom('21-guide-de-mise-en-ligne'), fullPage: true });
    await clientBase.close();

    // Demande en attente, puis site publié.
    const clientAttente = await navigateur.newContext(reglages);
    page = await connecter(clientAttente, 'yao-nettoyage@exemple.tg');
    await page.goto(`${base}/projets/${projets.attente}`);
    await attendre(1500);
    await page.screenshot({ path: nom('22-demande-en-attente') });
    await clientAttente.close();

    const clientPublie = await navigateur.newContext(reglages);
    page = await connecter(clientPublie, 'efua-nettoyage@exemple.tg');
    await page.goto(`${base}/projets/${projets.publie}`);
    await attendre(1500);
    await page.locator('[data-publish-open]').first().click().catch(() => {});
    await attendre(800);
    await page.screenshot({ path: nom('23-site-publie-mettre-en-ligne') });
    await clientPublie.close();
  }

  console.log(erreurs.length ? `Erreurs de la page : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans la page.');
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
