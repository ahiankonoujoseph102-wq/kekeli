// Captures de l'étape 5 (consommation, énergie qui se recharge, administration, effacement des
// ordinateurs en pause), avec un vrai essai. Le site (port 8123), la file d'attente et le Runner
// doivent tourner.
//
//   node scripts/captures-etape-5.js PARTIE docs/captures/etape-5 ETAT.json
//
// Parties : energie, administration, effacement. Les réglages modifiés pour montrer les messages
// de limite sont remis à leur valeur par défaut à la fin de la partie « energie ».
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [partie, dossier, fichierEtat] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();
const artisan = (...args) => execFileSync('php', ['artisan', ...args]).toString().trim();

async function ouvrir(session = null) {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', ...(session ? { storageState: session } : {}) });
  const page = await contexte.newPage();
  const capture = (nom, pleine = false) => page.screenshot({ path: `${dossier}/${nom}.png`, fullPage: pleine });
  return { navigateur, contexte, page, capture };
}

async function inscrire(page, nom, email) {
  await page.goto(base + '/register');
  await page.fill('#name', nom);
  await page.fill('#email', email);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
}

const parties = {
  async energie() {
    await mkdir(dossier, { recursive: true });
    const { navigateur, contexte, page, capture } = await ouvrir();
    const email = `awa${Date.now()}@exemple.tg`;
    await inscrire(page, 'Awa Test', email);
    await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
    await attendre(400);
    await page.getByLabel('Nom du projet').first().fill('Salon de coiffure');
    await page.getByRole('button', { name: 'Créer le projet' }).click();
    await page.waitForURL('**/projets/*');
    await attendre(500);
    await capture('01-energie-pleine');

    // Une vraie demande : l'énergie baisse.
    await page.getByLabel('Message').fill("Crée une page d'accueil simple pour un salon de coiffure à Lomé, avec les tarifs et les horaires.");
    await page.getByRole('button', { name: 'Envoyer' }).click();
    await page.waitForSelector('[data-run-status="completed"], [data-run-status="failed"]', { timeout: 15 * 60000 });
    await attendre(1500);
    await capture('02-energie-apres-une-demande', true);
    console.log('Énergie après une demande :', await page.locator('[data-energy]').getAttribute('data-energy'), '%');

    // Énergie du jour épuisée (énergie de la formule gratuite abaissée pour la démonstration).
    tinker('$s = app(App\\Support\\Settings::class); $p = $s->get("plans"); $p["gratuite"]["energy_fcfa"] = 0.5; $s->set("plans", $p);');
    await page.reload();
    await attendre(800);
    await capture('03-limite-formule-gratuite', true);

    await page.getByRole('link', { name: 'Voir les formules' }).click();
    await page.waitForURL('**/formules');
    await attendre(600);
    await capture('04-page-formules', true);

    // Formule payante épuisée (formule d'essai « Démonstration », jamais publiée).
    tinker(`$s = app(App\\Support\\Settings::class); $p = $s->get("plans"); $p["demonstration"] = ["name" => "Démonstration", "price_fcfa" => 1000, "energy_fcfa" => 0.5, "window_hours" => 5]; $s->set("plans", $p); App\\Models\\User::where("email", "${email}")->update(["plan" => "demonstration", "energy_window_started_at" => now()->subHour()]);`);
    await page.goBack();
    await page.reload();
    await attendre(800);
    await capture('05-limite-formule-payante', true);

    tinker(`app(App\\Support\\Settings::class)->reset("plans"); App\\Models\\User::where("email", "${email}")->update(["plan" => "gratuite", "energy_window_started_at" => null]);`);
    await writeFile(fichierEtat, JSON.stringify({ email, session: await contexte.storageState() }));
    await navigateur.close();
  },

  async administration() {
    const { navigateur, page, capture } = await ouvrir();
    const email = `admin${Date.now()}@exemple.tg`;
    await inscrire(page, 'Administrateur', email);
    artisan('kekeli:admin', email);
    await page.goto(base + '/administration/consommation');
    await attendre(1000);
    await capture('06-administration-consommation', true);
    await page.goto(base + '/administration/reglages');
    await attendre(1000);
    await capture('07-administration-reglages', true);
    await navigateur.close();
  },

  async effacement() {
    // Le projet « Boutique de pagnes 1 » des mesures : son ordinateur est en pause depuis la fin des mesures.
    const projet = tinker('echo App\\Models\\Project::where("name", "Boutique de pagnes 1")->value("id");');
    tinker(`App\\Models\\Project::whereKey("${projet}")->update(["preview_expires_at" => now()->subDays(8)]);`);
    console.log(artisan('kekeli:effacer-ordinateurs-en-pause'));
    for (let i = 0; i < 60 && !tinker(`echo App\\Models\\Project::find("${projet}")->computer_erased_at;`); i++) await attendre(1000);
    console.log('Ordinateur effacé le :', tinker(`echo App\\Models\\Project::find("${projet}")->computer_erased_at;`));

    const email = tinker(`echo App\\Models\\Project::find("${projet}")->user->email;`);
    const { navigateur, page, capture } = await ouvrir();
    await page.goto(base + '/login');
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', 'motdepasse-solide-2026');
    await page.click('button[type=submit]');
    await page.waitForURL('**/projets');
    await page.goto(base + '/projets/' + projet);
    await attendre(800);
    await page.locator('[data-tab="apercu"]').click();
    await attendre(600);
    await capture('08-ordinateur-efface-projet-sauvegarde');

    const debut = Date.now();
    await page.locator('[data-wake-preview]').click();
    await page.waitForSelector('[data-preview="live"] iframe', { timeout: 5 * 60000 });
    await attendre(4000);
    console.log(`Projet remis en place dans un nouvel ordinateur en ${Math.round((Date.now() - debut) / 1000)} secondes.`);
    await capture('09-projet-remis-en-place');
    await navigateur.close();
  },
};

if (!parties[partie]) {
  console.error(`Partie inconnue : ${partie}. Parties : ${Object.keys(parties).join(', ')}.`);
  process.exit(1);
}
parties[partie]().catch((e) => { console.error(e); process.exit(1); });
