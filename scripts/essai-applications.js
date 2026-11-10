// Essai réel des applications web (E2B + Gemini) sur un téléphone simulé : création depuis la page
// « Nouvelle application web » (modèle de la galerie ou sur mesure), travail de Kekeli, application qui tourne
// dans l'aperçu (connexion de l'administrateur d'essai dans le cadre de la page), guide de mise en ligne et
// fichier ZIP pour l'hébergement mutualisé. Mesures : durée, requêtes, énergie, étapes, compte rendu.
// Le site, la file d'attente et le Runner doivent tourner.
//
//   node scripts/essai-applications.js laravel modele:reservations-restaurant NOM DOSSIER RESULTATS.json "Nom" ["demande suivante"]
//   node scripts/essai-applications.js nextjs zero NOM DOSSIER RESULTATS.json "Nom" "description de l'application" ["demande suivante"]
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [pile, depart, nom, dossier, fichier, nomApplication, ...textes] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();
const modele = depart.startsWith('modele:') ? depart.slice(7) : null;
const description = modele ? null : textes.shift();
const suite = textes.shift() ?? null;

async function attendreFin(page, avant, numero, capture) {
  const debut = Date.now();
  let pendant = false;
  while (Date.now() - debut < 40 * 60000) {
    const cartes = page.locator('[data-run-status]');
    if ((await cartes.count()) > avant) {
      const statut = await cartes.last().getAttribute('data-run-status').catch(() => null);
      if (['completed', 'failed', 'stopped'].includes(statut)) break;
      if (!pendant && (await cartes.last().locator('[data-phrase]').count()) >= 3) {
        await capture(`${numero}-02-pendant`);
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
    $run = App\\Models\\AgentRun::where("project_id", "${projet}")->orderByDesc("created_at")->first();
    $taux = app(App\\Support\\Settings::class)->float("usd_to_fcfa");
    echo json_encode(["statut" => $run->status, "erreur" => $run->error, "modele" => $run->model, "requetes" => $run->requests,
      "fcfa" => round(($run->model_cost_usd + $run->computer_cost_usd) * $taux, 1), "modele_fcfa" => round($run->model_cost_usd * $taux, 1), "ordinateur_fcfa" => round($run->computer_cost_usd * $taux, 1),
      "secondes_ordinateur" => $run->computer_seconds,
      "tokens" => ["envoyes" => $run->input_tokens, "cache" => $run->cached_tokens, "recus" => $run->output_tokens, "raisonnement" => $run->reasoning_tokens],
      "phrases" => $run->steps()->where("kind", "narration")->pluck("target")->all(),
      "etapes" => $run->steps()->where("kind", "!=", "narration")->get(["kind", "target"])->map(fn ($s) => $s->kind . " : " . mb_substr((string) $s->target, 0, 140))->all(),
      "fichiers_modifies" => data_get($run->changes, "nombre_fichiers"),
      "compte_rendu" => $run->report]);
  `),
  );
}

(async () => {
  await mkdir(dossier, { recursive: true });
  const email = `essai-app-${nom}-${Date.now()}@exemple.tg`;
  // Compte d'essai inventé, formule Entreprise pour que l'énergie ne coupe pas la mesure.
  tinker(`App\\Models\\User::factory()->create(["name" => "Ama Essai", "email" => "${email}", "password" => bcrypt("motdepasse-solide-2026"), "country" => "TG", "plan" => "entreprise", "plan_expires_at" => now()->addDays(30)]);`);

  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', acceptDownloads: true });
  const page = await contexte.newPage();
  const capture = (suffixe, pleine = false) => page.screenshot({ path: `${dossier}/${nom}-${suffixe}.png`, fullPage: pleine });

  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');

  // 1. Nouvelle application web : les modèles d'abord, puis le formulaire.
  await page.locator('[data-new-webapp]').click();
  await page.waitForURL(/nouvelle-application/);
  await attendre(800);
  if (modele) {
    await page.locator(`[data-webapp-template="${modele}"]`).click();
    await attendre(800);
  } else {
    await page.locator('[data-webapp-from-scratch]').click();
    await attendre(800);
    await page.locator('[data-webapp-description] textarea, textarea[wire\\:model="description"]').first().fill(description);
  }
  await page.locator('[data-webapp-name] input, input[wire\\:model="name"]').first().fill(nomApplication);
  if (modele) await page.locator('input[wire\\:model="city"]').fill('Lomé');
  await page.locator('[data-webapp-stack]').getByText(pile === 'laravel' ? 'Laravel (PHP) et MySQL' : 'Next.js (Node) et PostgreSQL', { exact: true }).click();
  await attendre(400);
  await capture('01-nouvelle-application', true);
  await page.locator('[data-webapp-create]').click();
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/, { timeout: 60000 });
  const projet = page.url().split('/').pop();

  const mesures = [];
  const duree = await attendreFin(page, 0, 1, capture);
  await attendre(2500);
  await page.reload();
  await attendre(1500);
  await capture('1-03-fin', true);
  await page.locator('[data-run-report]').last().screenshot({ path: `${dossier}/${nom}-1-04-compte-rendu.png` }).catch(() => {});
  mesures.push({ demande: modele ? `modèle ${modele}` : description, duree, ...mesurer(projet) });
  console.log(JSON.stringify({ ...mesures.at(-1), compte_rendu: undefined }, null, 1));

  // 2. L'application tourne dans l'aperçu : connexion de l'administrateur d'essai dans le cadre de la page.
  await page.locator('[data-webapp-card] button:has-text("Afficher")').click().catch(() => {});
  await attendre(300);
  await page.locator('[data-webapp-card]').screenshot({ path: `${dossier}/${nom}-05-carte-application.png` }).catch(() => {});
  const identifiant = (await page.locator('[data-test-admin] code').first().textContent().catch(() => '')).trim();
  const motDePasse = (await page.locator('[data-test-password]').textContent().catch(() => '')).trim();
  await page.locator('[data-tab="apercu"]').click();
  await attendre(6000);
  await capture('06-apercu-accueil');
  const cadre = page.frameLocator('[data-preview="live"] iframe');
  let connexion = 'non essayée';
  try {
    await cadre.locator('a[href$="/connexion"]').first().click({ timeout: 20000 });
    await cadre.locator('input[type=email], #email').first().fill(identifiant, { timeout: 30000 });
    await cadre.locator('input[type=password], #password').first().fill(motDePasse);
    await attendre(300);
    await capture('07-apercu-connexion');
    await cadre.locator('form.carte button[type=submit]').click();
    await cadre.locator('a[href$="/admin"]').first().waitFor({ timeout: 30000 });
    await cadre.locator('a[href$="/admin"]').first().click();
    await cadre.locator('h1:has-text("Administration")').waitFor({ timeout: 30000 });
    await attendre(800);
    await capture('08-apercu-administration');
    connexion = 'réussie dans le cadre';
  } catch (erreur) {
    connexion = `échec dans le cadre : ${erreur.message.split('\n')[0]}`;
    await capture('08-apercu-erreur');
  }

  // 3. La même application ouverte dans un nouvel onglet (indispensable sur iPhone).
  const lien = await page.locator('[data-preview="live"] a[target="_blank"]').first().getAttribute('href').catch(() => null);
  if (lien) {
    const onglet = await contexte.newPage();
    await onglet.goto(lien);
    await attendre(2500);
    await onglet.screenshot({ path: `${dossier}/${nom}-09-nouvel-onglet.png` });
    await onglet.close();
  }

  // 4. Une demande de plus (par exemple ajouter un champ ou une page).
  if (suite) {
    await page.locator('[data-tab="discussion"]').click();
    const avant = await page.locator('[data-run-status]').count();
    await page.getByLabel('Message').fill(suite);
    await page.getByRole('button', { name: 'Envoyer' }).click();
    const duree2 = await attendreFin(page, avant, 2, capture);
    await attendre(2500);
    await page.reload();
    await attendre(1500);
    await page.locator('[data-run-report]').last().screenshot({ path: `${dossier}/${nom}-2-04-compte-rendu.png` }).catch(() => {});
    mesures.push({ demande: suite, duree: duree2, ...mesurer(projet) });
    console.log(JSON.stringify({ ...mesures.at(-1), compte_rendu: undefined }, null, 1));
  }

  // 5. Guide de mise en ligne (et fichier ZIP pour l'hébergement mutualisé avec Laravel).
  const hebergeur = pile === 'laravel' ? 'cpanel' : 'vercel';
  await page.goto(`${base}/projets/${projet}/mise-en-ligne?hebergeur=${hebergeur}`);
  await attendre(1500);
  await capture('10-guide', true);
  let paquet = null;
  if (pile === 'laravel') {
    await page.locator('[data-package-build]').click();
    const debut = Date.now();
    while (Date.now() - debut < 12 * 60000) {
      await attendre(5000);
      const etat = await page.locator('[data-package]').getAttribute('data-package').catch(() => null);
      if (etat === 'ready' || etat === 'failed') break;
    }
    await page.reload();
    await attendre(1000);
    await page.locator('[data-package]').screenshot({ path: `${dossier}/${nom}-11-fichier-zip.png` }).catch(() => {});
    const etat = await page.locator('[data-package]').getAttribute('data-package');
    paquet = { etat, secondes: Math.round((Date.now() - debut) / 1000) };
    if (etat === 'ready') {
      const [telechargement] = await Promise.all([page.waitForEvent('download'), page.locator('[data-package-download]').click()]);
      const chemin = `${process.env.KEKELI_TELECHARGEMENTS || tmpdir()}/${nom}-hebergement.zip`;
      await telechargement.saveAs(chemin);
      paquet.fichier = chemin;
      paquet.liste = execFileSync('python3', ['-c', `import zipfile,sys;z=zipfile.ZipFile(sys.argv[1]);n=z.namelist();print(len(n));print("\\n".join(x for x in n if x.count("/")<1)[:2000])`, chemin]).toString();
    }
  }

  const resultat = { pile, depart, nom, projet, connexion, paquet, mesures };
  const anciens = JSON.parse(await readFile(fichier, 'utf8').catch(() => '[]'));
  await writeFile(fichier, JSON.stringify([...anciens.filter((r) => r.nom !== nom), resultat], null, 2));
  console.log(JSON.stringify({ connexion, paquet }, null, 1));
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
