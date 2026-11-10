// Captures des étapes GitHub du mode développeur (pull request, vérification automatique, fusion) sur un téléphone
// simulé, avec des données INVENTÉES : l'envoi réel vers GitHub sera essayé juste après la mise en ligne.
//   node scripts/captures-mode-developpeur.js docs/captures/mode-developpeur
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();
const email = `captures-dev-${Date.now()}@exemple.tg`;

const id = tinker(`
  $u = App\\Models\\User::factory()->create(["name" => "Ama Mensah", "email" => "${email}", "password" => bcrypt("motdepasse-solide-2026"), "country" => "TG", "github_login" => "ama-exemple", "github_token" => "jeton-invente"]);
  $p = $u->projects()->create(["name" => "boutique-en-ligne", "description" => "Dépôt GitHub ama-exemple/boutique-en-ligne"]);
  $p->forceFill(["kind" => "repository", "repository" => "ama-exemple/boutique-en-ligne", "repository_base_branch" => "main", "repository_base_sha" => str_repeat("a", 40), "repository_access" => "write", "work_branch" => "kekeli/20261110-101500", "backup_at" => now(),
    "repository_state" => ["detection" => ["nom" => "Laravel (PHP 8.3)", "tester" => ["php artisan test"], "instructions" => ["CLAUDE.md"]], "branche" => "kekeli/20261110-101500", "commits" => ["Ajoute la page de contact avec son test"],
    "fichiers" => [["etat" => "modifie", "chemin" => "routes/web.php"], ["etat" => "ajoute", "chemin" => "resources/views/contact.blade.php"], ["etat" => "ajoute", "chemin" => "tests/Feature/ContactTest.php"]], "nombre_fichiers" => 3]])->save();
  $m = $p->messages()->create(["role" => "user", "content" => "Ajoute une page de contact avec un formulaire, et son test."]);
  $p->agentRuns()->create(["message_id" => $m->id, "status" => "completed"]);
  $p->messages()->create(["role" => "assistant", "content" => "J'ai ajouté la page de contact (routes/web.php, resources/views/contact.blade.php) et son test (tests/Feature/ContactTest.php). Les 26 tests passent, et le style est vérifié avec Pint. Le travail est enregistré dans un commit sur la branche kekeli/20261110-101500."]);
  echo $p->id;`).split('\n').pop();

const etat = (attributs, message = null) => tinker(`$p = App\\Models\\Project::find("${id}"); $p->forceFill(${attributs})->save();${message ? ` $p->messages()->create(["role" => "assistant", "content" => ${JSON.stringify(message)}]);` : ''}`);

const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })).newPage();
await page.goto(base + '/login');
await page.fill('input[type=email]', email);
await page.fill('input[type=password]', 'motdepasse-solide-2026');
await page.click('button[type=submit]');
await page.waitForURL('**/projets');
const capture = async (nom) => {
  await page.goto(`${base}/projets/${id}`);
  await attendre(1200);
  await page.locator('[data-changed-files] summary').click().catch(() => {});
  await attendre(300);
  await page.screenshot({ path: `${dossier}/github-${nom}.png`, fullPage: true });
};

await capture('01-pret-a-envoyer');
etat(`["push_status" => "pushed", "pushed_at" => now(), "pushed_sha" => str_repeat("b", 40), "pr_number" => 12, "pr_url" => "https://github.com/ama-exemple/boutique-en-ligne/pull/12", "pr_status" => "open", "ci_status" => "pending"]`,
  'Votre travail est sur GitHub : pull request https://github.com/ama-exemple/boutique-en-ligne/pull/12 (branche kekeli/20261110-101500, 3 fichier(s) modifié(s)). Kekeli surveille maintenant la vérification automatique de GitHub et vous donnera le résultat ici.');
await capture('02-pull-request-verification-en-cours');
etat(`["ci_status" => "failure", "ci_summary" => "tests : 1 test a échoué (ContactTest)", "ci_fix_attempts" => 1]`);
tinker(`$p = App\\Models\\Project::find("${id}"); $m = $p->messages()->create(["role" => "user", "content" => "La vérification automatique de GitHub a échoué (tests) : corrige-la."]); $p->agentRuns()->create(["message_id" => $m->id, "status" => "completed", "purpose" => "ci_fix"]); $p->messages()->create(["role" => "assistant", "content" => "Le test ContactTest échouait sur GitHub car la base de données de test n'avait pas la table des messages. J'ai ajouté la migration manquante, relancé les 26 tests (tous verts) et enregistré la correction dans un commit."]);`);
await capture('03-verification-rouge-corrigee');
etat(`["ci_status" => "success", "ci_summary" => null, "ci_fix_attempts" => 0, "pushed_sha" => str_repeat("c", 40)]`,
  '✅ La vérification automatique de GitHub est verte (2 vérification(s)). La pull request https://github.com/ama-exemple/boutique-en-ligne/pull/12 est prête. Kekeli ne la fusionne que si vous le demandez.');
await capture('04-verification-verte');
etat(`["pr_status" => "merged"]`, "La pull request https://github.com/ama-exemple/boutique-en-ligne/pull/12 est fusionnée dans main, comme vous l'avez demandé.");
await capture('05-fusionnee-a-la-demande');
await navigateur.close();
console.log('Captures prises.');
