// Essai réel : l'énergie d'un utilisateur de la formule gratuite s'épuise au milieu d'une grosse demande.
// Le travail s'arrête, le projet est sauvegardé, le message d'arrêt s'affiche ; puis l'utilisateur passe à une
// formule payante (validée par l'administrateur) et touche « Continuer ».
// Le site (port 8123), la file d'attente et le Runner doivent tourner.
//
//   node scripts/essai-arret-energie.js docs/captures/etape-6-ajouts
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const DEMANDE = "Crée le site professionnel du restaurant « Le Palmier » à Lomé, quartier Bè : une page d'accueil avec une belle photo, une page Menu avec les plats et leurs prix en FCFA, une page À propos et une page Contact avec les horaires, l'adresse et un bouton WhatsApp.";

(async () => {
  await mkdir(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  const email = `essai-energie-${Date.now()}@exemple.tg`;
  await page.goto(base + '/register');
  await page.fill('#name', 'Essai énergie');
  await page.fill('#email', email);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');

  await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
  await attendre(400);
  await page.getByLabel('Nom du projet').first().fill('Restaurant Le Palmier');
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await attendre(500);

  // Formule gratuite (30 FCFA d'énergie) : un site complet en demande plus.
  await page.getByLabel('Message').fill(DEMANDE);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForSelector('[data-run-status="stopped"], [data-run-status="completed"], [data-run-status="failed"]', { timeout: 30 * 60000 });
  await attendre(1500);
  const statut = await page.locator('[data-run-status]').last().getAttribute('data-run-status');
  await page.screenshot({ path: `${dossier}/energie-01-travail-arrete.png`, fullPage: true });
  const avant = JSON.parse(tinker(`echo App\\Models\\AgentRun::whereHas("project.user", fn ($q) => $q->where("email", "${email}"))->latest()->first()->toJson();`));

  // L'utilisateur paie une formule ; l'administrateur valide (ici : changement de formule).
  tinker(`App\\Models\\User::where("email", "${email}")->update(["plan" => "pro"]);`);
  await page.reload();
  await attendre(1500);
  await page.screenshot({ path: `${dossier}/energie-02-bouton-continuer.png`, fullPage: true });

  await page.locator('[data-continue]').click();
  // D'abord la reprise (la même demande repart), puis la fin du travail.
  await page.waitForSelector('[data-run-status="queued"], [data-run-status="running"]', { timeout: 60000 });
  await page.waitForSelector('[data-run-status="completed"], [data-run-status="failed"], [data-run-status="stopped"]', { timeout: 30 * 60000 });
  await attendre(1500);
  await page.locator('[data-run-status] details summary').last().click().catch(() => {});
  await attendre(400);
  await page.screenshot({ path: `${dossier}/energie-03-travail-termine.png`, fullPage: true });
  const apres = JSON.parse(tinker(`echo App\\Models\\AgentRun::whereHas("project.user", fn ($q) => $q->where("email", "${email}"))->latest()->first()->toJson();`));

  await page.locator('[data-tab="apercu"]').click();
  await page.waitForSelector('[data-preview="live"] iframe', { timeout: 120000 });
  const adresse = await page.locator('[data-preview="live"] iframe').getAttribute('src');
  const site = await contexte.newPage();
  await site.goto(adresse);
  await attendre(3000);
  await site.screenshot({ path: `${dossier}/energie-04-site-termine.png`, fullPage: true });

  console.log(JSON.stringify({
    premierStatut: statut,
    arret: { statut: avant.status, raison: avant.stop_reason, requetes: avant.requests, coutModele: avant.model_cost_usd, secondes: avant.computer_seconds },
    fin: { statut: apres.status, requetes: apres.requests, coutModele: apres.model_cost_usd, secondes: apres.computer_seconds },
    projet: avant.project_id,
  }, null, 1));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
