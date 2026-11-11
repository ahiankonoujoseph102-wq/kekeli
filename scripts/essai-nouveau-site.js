// Essai réel du premier écran « Que voulez-vous créer aujourd'hui ? » (E2B + Gemini) sur un téléphone simulé : le
// client écrit son idée dans la zone unique, Kekeli devine seul le nom, le type de site et la technologie, construit
// le site et le montre dans l'aperçu.
// Pour un site vitrine : la demande de comptes ensuite fait apparaître la proposition d'un site avec base de données.
// Pour un site avec base de données : connexion de l'administrateur d'essai dans l'aperçu, puis guide de mise en ligne.
// Le site, la file d'attente et le Runner doivent tourner.
//
//   node scripts/essai-nouveau-site.js NOM DOSSIER RESULTATS.json "(ignoré : Kekeli devine le nom)" "description du site"
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [nom, dossier, fichier, nomSite, description] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

async function attendreFin(page, capture) {
  const debut = Date.now();
  let pendant = false;
  while (Date.now() - debut < 40 * 60000) {
    const cartes = page.locator('[data-run-status]');
    if ((await cartes.count()) > 0) {
      const statut = await cartes.last().getAttribute('data-run-status').catch(() => null);
      if (['completed', 'failed', 'stopped'].includes(statut)) break;
      if (!pendant && (await cartes.last().locator('[data-phrase]').count()) >= 3) {
        await capture('04-pendant');
        pendant = true;
      }
    }
    await attendre(4000);
  }
  return Math.round((Date.now() - debut) / 1000);
}

function mesurer(projet) {
  return JSON.parse(
    tinker(`
    $projet = App\\Models\\Project::find("${projet}");
    $run = $projet->agentRuns()->orderByDesc("created_at")->first();
    $taux = app(App\\Support\\Settings::class)->float("usd_to_fcfa");
    echo json_encode(["type" => $projet->kind, "technologie" => $projet->app_stack, "statut" => $run->status, "erreur" => $run->error, "requetes" => $run->requests,
      "fcfa" => round(($run->model_cost_usd + $run->computer_cost_usd) * $taux, 1),
      "phrases" => $run->steps()->where("kind", "narration")->pluck("target")->all(),
      "etapes" => $run->steps()->where("kind", "!=", "narration")->get(["kind", "target"])->map(fn ($s) => $s->kind . " : " . mb_substr((string) $s->target, 0, 140))->all(),
      "compte_rendu" => $run->report]);
  `),
  );
}

(async () => {
  await mkdir(dossier, { recursive: true });
  const email = `essai-site-${nom}-${Date.now()}@exemple.tg`;
  // Compte d'essai inventé, formule Entreprise pour que l'énergie ne coupe pas la mesure.
  tinker(`App\\Models\\User::factory()->create(["name" => "Ama Essai", "email" => "${email}", "password" => bcrypt("motdepasse-solide-2026"), "country" => "TG", "plan" => "entreprise", "plan_expires_at" => now()->addDays(30)]);`);

  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  const capture = (suffixe, pleine = false) => page.screenshot({ path: `${dossier}/${nom}-${suffixe}.png`, fullPage: pleine });

  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  await capture('01-mes-projets');

  // 1. « Que voulez-vous créer aujourd'hui ? » : une seule zone de saisie, puis Kekeli commence.
  await page.locator('[data-site-prompt-input]').fill(description);
  await capture('02-zone', true);
  await page.locator('[data-site-prompt-send]').click();
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/, { timeout: 60000 });
  const projet = page.url().split('/').pop();
  await attendre(1500);
  await capture('03-depart');

  const duree = await attendreFin(page, capture);
  await attendre(2500);
  await page.reload();
  await attendre(1500);
  await capture('05-fin', true);
  await page.locator('[data-run-report]').last().screenshot({ path: `${dossier}/${nom}-06-compte-rendu.png` }).catch(() => {});
  const mesure = { duree, ...mesurer(projet) };
  console.log(JSON.stringify({ ...mesure, compte_rendu: undefined }, null, 1));

  // 2. L'aperçu.
  let connexion = null;
  let suggestion = null;
  if (mesure.type === 'webapp') {
    await page.locator('[data-webapp-card] button:has-text("Afficher")').click().catch(() => {});
    await attendre(300);
    await page.locator('[data-webapp-card]').screenshot({ path: `${dossier}/${nom}-07-carte.png` }).catch(() => {});
    const identifiant = (await page.locator('[data-test-admin] code').first().textContent().catch(() => '')).trim();
    const motDePasse = (await page.locator('[data-test-password]').textContent().catch(() => '')).trim();
    await page.locator('[data-tab="apercu"]').click();
    await attendre(6000);
    await capture('08-apercu');
    const cadre = page.frameLocator('[data-preview="live"] iframe');
    try {
      await cadre.locator('a[href$="/connexion"]').first().click({ timeout: 20000 });
      await cadre.locator('input[type=email], #email').first().fill(identifiant, { timeout: 30000 });
      await cadre.locator('input[type=password], #password').first().fill(motDePasse);
      await cadre.locator('form.carte button[type=submit]').click();
      await cadre.locator('a[href$="/admin"]').first().waitFor({ timeout: 30000 });
      await cadre.locator('a[href$="/admin"]').first().click();
      await cadre.locator('h1:has-text("Administration")').waitFor({ timeout: 30000 });
      await attendre(800);
      await capture('09-apercu-administration');
      connexion = 'réussie dans le cadre';
    } catch (erreur) {
      connexion = `échec dans le cadre : ${erreur.message.split('\n')[0]}`;
      await capture('09-apercu-erreur');
    }
    await page.goto(`${base}/projets/${projet}/mise-en-ligne`);
    await attendre(1500);
    await capture('10-mise-en-ligne', true);
  } else {
    await page.locator('[data-tab="apercu"]').click();
    await attendre(5000);
    await capture('07-apercu');
    await page.locator('[data-tab="discussion"]').click();
    // 3. Le client demande ensuite des comptes : Kekeli propose un site avec base de données.
    await page.getByLabel('Message').fill('Ajoute des comptes clients avec connexion et un espace administrateur pour gérer les réservations.');
    await page.getByRole('button', { name: 'Envoyer' }).click();
    await page.locator('[data-database-suggestion]').waitFor({ timeout: 15000 }).then(() => (suggestion = 'proposée')).catch(() => (suggestion = 'absente'));
    await attendre(500);
    await page.locator('[data-database-suggestion]').scrollIntoViewIfNeeded().catch(() => {});
    await capture('08-proposition-base-de-donnees');
  }

  const resultat = { nom, projet, description, connexion, suggestion, mesure };
  const anciens = JSON.parse(await readFile(fichier, 'utf8').catch(() => '[]'));
  await writeFile(fichier, JSON.stringify([...anciens.filter((r) => r.nom !== nom), resultat], null, 2));
  console.log(JSON.stringify({ connexion, suggestion }, null, 1));
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
