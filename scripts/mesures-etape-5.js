// Mesures de l'étape 5 : requêtes, tokens, temps et coût réels de trois types de demandes,
// avec un vrai ordinateur E2B, l'agent OpenCode et Gemini. Le site (port 8123), la file d'attente
// et le Runner doivent tourner. L'énergie de la formule gratuite est relevée pendant les mesures,
// pour qu'aucune limite ne coupe un essai ; elle revient ensuite à sa valeur par défaut.
//
//   node scripts/mesures-etape-5.js RESULTATS.json [répétitions]
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [fichier, repetitions = '2'] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const PETITE_PAGE = "Crée une page d'accueil simple pour une boutique de pagnes à Lomé, avec une belle photo, les horaires d'ouverture et un bouton pour écrire sur WhatsApp.";
const CINQ_PAGES = "Crée un site professionnel de 5 pages pour un cabinet d'avocats à Lomé : Accueil, À propos, Domaines d'expertise, Équipe et Contact. Un menu commun sur toutes les pages, des photos libres de droits et un formulaire de contact.";
const MODIFICATION = 'Change la couleur principale du site en bleu et ajoute le numéro +228 90 00 00 00 en bas de chaque page.';

async function demander(page, texte) {
  const avant = await page.locator('[data-run-status]').count();
  await page.getByLabel('Message').fill(texte);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForFunction((n) => document.querySelectorAll('[data-run-status]').length > n, avant);
  await page.waitForFunction(
    (n) => {
      const cartes = document.querySelectorAll('[data-run-status]');
      return cartes.length > n && ['completed', 'failed', 'waiting'].includes(cartes[cartes.length - 1].dataset.runStatus);
    },
    avant,
    { timeout: 25 * 60000 },
  );
  await attendre(1500);
}

async function nouveauProjet(page, nom) {
  await page.goto(base + '/projets');
  await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
  await attendre(400);
  await page.getByLabel('Nom du projet').first().fill(nom);
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await attendre(500);
  return page.url();
}

(async () => {
  tinker('$s = app(App\\Support\\Settings::class); $p = $s->get("plans"); $p["gratuite"]["energy_fcfa"] = 1000000; $s->set("plans", $p);');

  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await navigateur.newPage({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const email = `mesures${Date.now()}@exemple.tg`;
  await page.goto(base + '/register');
  await page.fill('#name', 'Mesures Kekeli');
  await page.fill('#email', email);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');

  const essais = [];
  try {
    for (let i = 1; i <= Number(repetitions); i++) {
      const boutique = await nouveauProjet(page, `Boutique de pagnes ${i}`);
      await demander(page, PETITE_PAGE);
      essais.push({ type: 'petite_page', projet: boutique });
      console.log(`Petite page ${i} : faite.`);

      await demander(page, MODIFICATION);
      essais.push({ type: 'modification', projet: boutique });
      console.log(`Modification ${i} : faite.`);

      await nouveauProjet(page, `Cabinet d'avocats ${i}`);
      await demander(page, CINQ_PAGES);
      essais.push({ type: 'cinq_pages', projet: page.url() });
      console.log(`Site de 5 pages ${i} : fait.`);
    }
  } finally {
    await navigateur.close();
    tinker('app(App\\Support\\Settings::class)->reset("plans");');
  }

  // Chiffres réels de chaque demande, dans l'ordre d'envoi.
  const runs = JSON.parse(tinker(`echo App\\Models\\AgentRun::query()->whereHas("project.user", fn ($q) => $q->where("email", "${email}"))->orderBy("created_at")->get(["status", "provider", "model", "requests", "input_tokens", "output_tokens", "reasoning_tokens", "cached_tokens", "model_cost_usd", "computer_seconds", "computer_cost_usd", "started_at", "finished_at"])->toJson();`));
  const resultats = essais.map((essai, index) => ({ ...essai, ...runs[index] }));
  await writeFile(fichier, JSON.stringify({ email, resultats }, null, 2));
  for (const r of resultats) {
    console.log(`${r.type} : ${r.status}, ${r.requests} requêtes, ${r.input_tokens} tokens envoyés, ${r.cached_tokens} en cache, ${r.output_tokens + r.reasoning_tokens} reçus, ${r.computer_seconds} secondes.`);
  }
})().catch((e) => { console.error(e); process.exit(1); });
