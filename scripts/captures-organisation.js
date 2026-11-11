// Captures de tous les écrans du client, sur téléphone (Pixel 7) et sur ordinateur, pour comparer l'organisation des
// écrans AVANT et APRÈS la pull request 4. Comptes et projets inventés (essais réels du 10 et du 11 novembre 2026).
//
//   php artisan serve --port=8123   (dans un autre terminal)
//   node scripts/captures-organisation.js docs/captures/organisation/avant
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/organisation/apres';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const MOT_DE_PASSE = 'motdepasse-solide-2026';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

// Projets d'essai : un site vitrine terminé, un site avec base de données terminé, une demande en attente, un site publié.
const projets = JSON.parse(
  tinker(`
  $trouver = fn ($courriel, $nom) => App\\Models\\Project::whereHas('user', fn ($q) => $q->where('email', 'like', $courriel))->where('name', $nom)->latest()->first();
  $vitrine = $trouver('essai-site-openrouter-north-%', 'Restaurant Le Palmier');
  $base = $trouver('essai-site-gemini-chaine-pressing-%', 'Pressing Éclat');
  $attente = $trouver('essai-site-workersai-glm-%', 'Restaurant Le Palmier');
  $publie = $trouver('ama-etape8@exemple.tg', 'Restaurant Le Palmier');
  foreach ([$vitrine, $base, $attente, $publie] as $p) { $p->user->forceFill(['password' => bcrypt('${MOT_DE_PASSE}'), 'country' => 'TG'])->save(); }
  echo json_encode(collect(compact('vitrine', 'base', 'attente', 'publie'))->map(fn ($p) => ['id' => $p->id, 'email' => $p->user->email]));
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

async function vue(page, nom) {
  // Avant : onglets gérés par Alpine ; après : onglets avec l'attribut data-tab aussi.
  const onglet = page.locator(`[data-tab="${nom}"]:visible, [data-desktop-tab="${nom}"]:visible`).first();
  if (await onglet.count()) {
    await onglet.click().catch(() => {});
    await attendre(1200);
    return true;
  }
  return false;
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

    // Pages ouvertes à tous.
    const visiteur = await navigateur.newContext(reglages);
    const accueil = await visiteur.newPage();
    accueil.on('pageerror', (e) => erreurs.push(e.message));
    await accueil.goto(base + '/');
    await attendre(800);
    await accueil.screenshot({ path: nom('01-accueil'), fullPage: true });
    if (appareil === 'telephone') {
      await accueil.goto(base + '/register');
      await attendre(600);
      await accueil.screenshot({ path: nom('02-inscription'), fullPage: true });
      await accueil.goto(base + '/login');
      await attendre(600);
      await accueil.screenshot({ path: nom('03-connexion'), fullPage: true });
      await accueil.fill('input[type=email]', 'personne@exemple.tg');
      await accueil.fill('input[type=password]', 'mauvais-mot-de-passe');
      await accueil.click('button[type=submit]');
      await attendre(1500);
      await accueil.screenshot({ path: nom('04-connexion-erreur'), fullPage: true });
    }
    await visiteur.close();

    // Site vitrine terminé : discussion, aperçu, code, menu, comptes rendus.
    const client = await navigateur.newContext(reglages);
    let page = await connecter(client, projets.vitrine.email);
    page.on('pageerror', (e) => erreurs.push(e.message));
    await attendre(800);
    await page.screenshot({ path: nom('05-mes-projets'), fullPage: appareil === 'telephone' });
    await page.goto(`${base}/projets/${projets.vitrine.id}`);
    await attendre(1500);
    await page.screenshot({ path: nom('06-projet-discussion') });
    if (appareil === 'telephone') {
      await page.screenshot({ path: nom('07-projet-discussion-complete'), fullPage: true });
    }
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await attendre(500);
    await page.screenshot({ path: nom('08-projet-fin-de-discussion') });
    if (await vue(page, 'apercu')) await page.screenshot({ path: nom('09-projet-apercu') });
    if (await vue(page, 'code')) {
      await page.screenshot({ path: nom('10-projet-code') });
    } else {
      await page.goto(`${base}/projets/${projets.vitrine.id}`);
      await attendre(1200);
      await page.locator('[data-open-code]').first().click().catch(() => {});
      await attendre(800);
      await page.screenshot({ path: nom('10-projet-code') });
    }
    await page.goto(`${base}/projets/${projets.vitrine.id}`);
    await attendre(1200);
    await page.locator('[data-publish-open]').first().click().catch(() => {});
    await attendre(800);
    await page.screenshot({ path: nom('11-projet-mettre-en-ligne') });
    await page.keyboard.press('Escape');
    await page.locator('[data-project-more]').first().click().catch(() => {});
    await attendre(600);
    await page.screenshot({ path: nom('12-projet-menu') });
    if (appareil === 'telephone') {
      const comptesRendus = await page.goto(`${base}/projets/${projets.vitrine.id}/comptes-rendus`);
      await attendre(1000);
      await page.screenshot({ path: nom('13-comptes-rendus'), fullPage: true });
      void comptesRendus;
      await page.goto(base + '/formules');
      await attendre(800);
      await page.screenshot({ path: nom('14-formules'), fullPage: true });
      await page.goto(base + '/formules/pro/payer');
      await attendre(800);
      await page.screenshot({ path: nom('15-paiement'), fullPage: true });
      await page.goto(base + '/settings/profile');
      await attendre(800);
      await page.screenshot({ path: nom('16-compte'), fullPage: true });
      await page.goto(base + '/projets');
      await attendre(600);
      await page.locator('[data-flux-profile]').last().click({ timeout: 5000 }).catch((e) => console.log('Menu du compte :', e.message.split('\n')[0]));
      await attendre(600);
      await page.screenshot({ path: nom('17-menu-du-compte') });
    }
    await client.close();

    // Site avec base de données terminé.
    const clientBase = await navigateur.newContext(reglages);
    page = await connecter(clientBase, projets.base.email);
    await page.goto(`${base}/projets/${projets.base.id}`);
    await attendre(1500);
    await page.screenshot({ path: nom('18-site-avec-base-de-donnees') });
    await page.locator('[data-publish-open], [data-publish], a[href$="/mise-en-ligne"]').first().click().catch(() => {});
    await attendre(1200);
    await page.screenshot({ path: nom('19-mise-en-ligne-base-de-donnees'), fullPage: appareil === 'telephone' });
    await clientBase.close();

    // Demande en attente (fournisseurs épuisés), puis site publié.
    const clientAttente = await navigateur.newContext(reglages);
    page = await connecter(clientAttente, projets.attente.email);
    await page.goto(`${base}/projets/${projets.attente.id}`);
    await attendre(1500);
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await attendre(400);
    await page.screenshot({ path: nom('20-demande-en-attente') });
    await clientAttente.close();

    const clientPublie = await navigateur.newContext(reglages);
    page = await connecter(clientPublie, projets.publie.email);
    await page.goto(`${base}/projets/${projets.publie.id}`);
    await attendre(1500);
    await page.screenshot({ path: nom('21-site-publie') });
    await clientPublie.close();
  }

  console.log(erreurs.length ? `Erreurs de la page : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans la page.');
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
