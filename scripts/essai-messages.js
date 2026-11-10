// Essai réel des messages clairs de Kekeli (E2B + Gemini) : phrases au fur et à mesure pendant le travail, compte
// rendu à la fin, « Voir les modifications », « Copier le compte rendu », historique des comptes rendus. Mesure le
// coût en plus des phrases (tokens écrits par l'agent pour parler au client). Le site, la file d'attente et le Runner
// doivent tourner (et le relais GitHub d'essai pour un dépôt).
//
//   node scripts/essai-messages.js site NOM DOSSIER RESULTATS.json "demande 1" ["demande 2"]
//   node scripts/essai-messages.js depot:proprietaire/nom NOM DOSSIER RESULTATS.json "demande"
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [mode, nom, dossier, fichier, ...demandes] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

(async () => {
  await mkdir(dossier, { recursive: true });
  const email = `essai-messages-${nom}-${Date.now()}@exemple.tg`;
  // Compte d'essai inventé, formule Entreprise pour que l'énergie ne coupe pas la mesure.
  tinker(`App\\Models\\User::factory()->create(["name" => "Ama Essai", "email" => "${email}", "password" => bcrypt("motdepasse-solide-2026"), "country" => "TG", "plan" => "entreprise", "plan_expires_at" => now()->addDays(30)]);`);

  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', permissions: ['clipboard-read', 'clipboard-write'] });
  const page = await contexte.newPage();
  const capture = (suffixe, pleine = false) => page.screenshot({ path: `${dossier}/${nom}-${suffixe}.png`, fullPage: pleine });

  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');

  if (mode.startsWith('depot:')) {
    await page.locator('[data-open-repository]').click();
    await page.waitForURL('**/ouvrir-un-depot');
    await page.locator('[data-public-address]').fill(`https://github.com/${mode.slice(6)}`);
    await page.locator('[data-public-repository] button[type=submit]').click();
  } else {
    const id = tinker(`echo App\\Models\\User::where("email", "${email}")->first()->projects()->create(["name" => "Restaurant Chez Ama"])->id;`);
    await page.goto(`${base}/projets/${id}`);
  }
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/);
  const projet = page.url().split('/').pop();

  const mesures = [];
  for (const [numero, demande] of demandes.entries()) {
    const n = numero + 1;
    const avant = await page.locator('[data-run-status]').count();
    const debut = Date.now();
    await page.getByLabel('Message').fill(demande);
    await page.getByRole('button', { name: 'Envoyer' }).click();

    // Pendant le travail : une capture quand Kekeli a déjà dit quelques phrases, une autre avec les détails dépliés.
    let pendant = 0;
    while (Date.now() - debut < 40 * 60000) {
      // Attendre que la nouvelle demande soit affichée, puis lire son état.
      const statut = (await page.locator('[data-run-status]').count()) > avant ? await page.locator('[data-run-status]').last().getAttribute('data-run-status').catch(() => null) : null;
      if (['completed', 'failed', 'stopped'].includes(statut)) break;
      const phrases = await page.locator('[data-run-status="running"] [data-phrase]').count();
      if (pendant === 0 && phrases >= 2) {
        await capture(`${n}-01-pendant`);
        pendant = 1;
      } else if (pendant === 1 && phrases >= 4) {
        await page.locator('[data-run-status="running"] [data-narration] details summary').first().click().catch(() => {});
        await attendre(400);
        await capture(`${n}-02-pendant-details`);
        pendant = 2;
      }
      await attendre(4000);
    }
    const duree = Math.round((Date.now() - debut) / 1000);
    await attendre(2500);
    await page.reload();
    await attendre(1500);
    const dernier = page.locator('[data-run-status]').last();
    await dernier.scrollIntoViewIfNeeded();
    await capture(`${n}-03-fin`, true);
    await page.locator('[data-run-report]').last().screenshot({ path: `${dossier}/${nom}-${n}-04-compte-rendu.png` }).catch(() => {});

    if (await page.locator('[data-show-changes]').last().count()) {
      await page.locator('[data-show-changes]').last().click();
      await page.waitForSelector('[data-changes-modal] details summary', { timeout: 15000 }).catch(() => {});
      await attendre(800);
      await page.locator('[data-changes-modal] details summary').last().click().catch(() => {});
      await attendre(500);
      await capture(`${n}-05-modifications`);
      await page.keyboard.press('Escape');
      await attendre(600);
    }
    await page.locator('[data-copy-report]').last().click();
    await attendre(300);
    await page.locator('[data-run-report]').last().locator('[data-copy-report]').scrollIntoViewIfNeeded();
    await capture(`${n}-06-copie`);
    const copie = await page.evaluate(() => navigator.clipboard.readText()).catch(() => '');

    mesures.push(JSON.parse(tinker(`
      $run = App\\Models\\AgentRun::where("project_id", "${projet}")->orderByDesc("created_at")->first();
      $taux = app(App\\Support\\Settings::class)->float("usd_to_fcfa");
      $phrases = $run->steps()->where("kind", "narration")->pluck("target");
      echo json_encode(["statut" => $run->status, "erreur" => $run->error, "modele" => $run->model, "requetes" => $run->requests,
        "fcfa" => round(($run->model_cost_usd + $run->computer_cost_usd) * $taux, 1), "modele_fcfa" => round($run->model_cost_usd * $taux, 1), "ordinateur_fcfa" => round($run->computer_cost_usd * $taux, 1),
        "tokens" => ["envoyes" => $run->input_tokens, "cache" => $run->cached_tokens, "recus" => $run->output_tokens, "raisonnement" => $run->reasoning_tokens],
        "phrases" => $phrases->all(), "caracteres_phrases" => $phrases->sum(fn ($p) => mb_strlen($p)),
        "etapes_techniques" => $run->steps()->where("kind", "!=", "narration")->count(),
        "fichiers_modifies" => data_get($run->changes, "nombre_fichiers"), "taille_differences" => strlen((string) $run->diff),
        "compte_rendu" => $run->report, "reponse" => App\\Models\\Message::where("agent_run_id", $run->id)->value("content")]);
    `)));
    Object.assign(mesures.at(-1), { demande, duree, copie });
    console.log(JSON.stringify({ n, ...mesures.at(-1), compte_rendu: undefined, reponse: undefined, copie: copie.slice(0, 300) }, null, 1));
  }

  await page.locator('[data-open-reports]').click();
  await page.waitForURL('**/comptes-rendus');
  await attendre(800);
  await capture('07-historique');
  await capture('08-historique-complet', true);

  const resultat = { mode, nom, projet, mesures };
  const anciens = JSON.parse(await readFile(fichier, 'utf8').catch(() => '[]'));
  await writeFile(fichier, JSON.stringify([...anciens.filter((r) => r.nom !== nom), resultat], null, 2));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
