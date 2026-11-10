// Essai réel du mode développeur : un client ouvre un dépôt GitHub public (lecture seule) sur son téléphone,
// demande une vraie modification, et Kekeli (E2B + Gemini) reconnaît le projet, installe les outils, modifie les
// fichiers, lance les tests et enregistre son travail sur sa branche. Mesures : durée, requêtes, énergie, étapes,
// fichiers modifiés. Captures sur téléphone simulé. Le site, la file d'attente et le Runner doivent tourner.
//
//   node scripts/essai-mode-developpeur.js proprietaire/nom NOM docs/captures/mode-developpeur RESULTATS.json "demande"
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [depot, nom, dossier, fichier, demande] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

(async () => {
  await mkdir(dossier, { recursive: true });
  const email = `essai-dev-${nom}-${Date.now()}@exemple.tg`;
  // Compte d'essai inventé, formule Entreprise pour que l'énergie ne coupe pas la mesure.
  tinker(`App\\Models\\User::factory()->create(["name" => "Développeuse essai", "email" => "${email}", "password" => bcrypt("motdepasse-solide-2026"), "country" => "TG", "plan" => "entreprise", "plan_expires_at" => now()->addDays(30)]);`);

  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  const capture = (suffixe, pleine = false) => page.screenshot({ path: `${dossier}/${nom}-${suffixe}.png`, fullPage: pleine });

  await page.goto(base + '/login');
  await page.fill('input[type=email]', email);
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  await page.locator('[data-open-repository]').click();
  await page.waitForURL('**/ouvrir-un-depot');
  await page.locator('[data-public-address]').fill(`https://github.com/${depot}`);
  await attendre(300);
  await capture('01-ouvrir-un-depot', true);
  await page.locator('[data-public-repository] button[type=submit]').click();
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/);
  await attendre(800);
  await capture('02-depot-ouvert', true);

  const debut = Date.now();
  await page.getByLabel('Message').fill(demande);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForSelector('[data-run-status="completed"], [data-run-status="failed"]', { timeout: 40 * 60000 });
  const duree = Math.round((Date.now() - debut) / 1000);
  await attendre(2000);
  await page.reload();
  await attendre(1500);
  await page.locator('[data-run-status] details summary').last().click().catch(() => {});
  await attendre(500);
  await capture('03-travail-de-kekeli', true);

  const mesure = JSON.parse(tinker(`
    $run = App\\Models\\AgentRun::whereHas("project.user", fn ($q) => $q->where("email", "${email}"))->latest()->first();
    $taux = app(App\\Support\\Settings::class)->float("usd_to_fcfa");
    echo json_encode(["statut" => $run->status, "erreur" => $run->error, "modele" => $run->model, "requetes" => $run->requests,
      "fcfa" => round(($run->model_cost_usd + $run->computer_cost_usd) * $taux, 1), "modele_fcfa" => round($run->model_cost_usd * $taux, 1), "ordinateur_fcfa" => round($run->computer_cost_usd * $taux, 1),
      "secondes_ordinateur" => $run->computer_seconds,
      "tokens" => ["envoyes" => $run->input_tokens, "cache" => $run->cached_tokens, "recus" => $run->output_tokens + $run->reasoning_tokens],
      "etapes" => $run->steps()->get(["kind", "target"])->map(fn ($s) => $s->kind . " : " . $s->target)->all(),
      "depot" => $run->project->repository_state,
      "reponse" => App\\Models\\Message::where("project_id", $run->project_id)->where("role", "assistant")->latest("created_at")->value("content")]);
  `));

  const resultat = { depot, nom, duree, ...mesure };
  const anciens = JSON.parse(await readFile(fichier, 'utf8').catch(() => '[]'));
  await writeFile(fichier, JSON.stringify([...anciens.filter((r) => r.nom !== nom), resultat], null, 2));
  console.log(JSON.stringify({ ...resultat, etapes: resultat.etapes.slice(0, 60) }, null, 1));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
