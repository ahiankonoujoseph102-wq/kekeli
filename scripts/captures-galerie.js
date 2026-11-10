// Captures de la galerie de modèles sur un téléphone simulé (Pixel 7, navigateur en français).
// Le site doit tourner (php artisan serve --port=8123).
//   node scripts/captures-galerie.js docs/captures/galerie
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();
const modeles = ['restaurant', 'boutique-pagnes', 'salon-coiffure', 'atelier-couture', 'ecole-formation', 'eglise', 'ong-association', 'btp', 'cabinet-conseil', 'cv-portfolio', 'hotel-residence', 'mon-activite'];

const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
const page = await contexte.newPage();
const erreurs = [];
page.on('pageerror', (e) => erreurs.push(e.message));

// La galerie et un modèle, sans compte.
await page.goto(`${base}/modeles`);
await attendre(1500);
await page.screenshot({ path: `${dossier}/01-galerie.png`, fullPage: true });
await page.goto(`${base}/modeles/hotel-residence`);
await attendre(2500);
await page.screenshot({ path: `${dossier}/02-modele-hotel.png`, fullPage: true });

// Chaque modèle, ouvert directement sur le téléphone : haut de la page et vérification de l'affichage.
const resultats = {};
for (const slug of modeles) {
  await page.goto(`${base}/modeles/${slug}/apercu/index.html`);
  await attendre(1200);
  resultats[slug] = await page.evaluate(() => ({
    depasse: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    imagesCassees: [...document.images].filter((i) => i.complete && i.naturalWidth === 0).length,
  }));
  await page.screenshot({ path: `${dossier}/modele-${slug}.png` });
}

// Un projet créé à partir d'un modèle mais pas encore personnalisé : la publication est refusée.
const email = `galerie-${Date.now()}@exemple.tg`;
tinker(`$u = App\\Models\\User::factory()->create(["email" => "${email}", "password" => bcrypt("motdepasse-solide-2026"), "country" => "TG"]); $p = app(App\\Templates\\TemplateGallery::class)->createProject($u, "eglise"); $p->forceFill(["template_step" => null])->save();`);
await page.goto(`${base}/login`);
await page.fill('input[type=email]', email);
await page.fill('input[type=password]', 'motdepasse-solide-2026');
await page.click('button[type=submit]');
await page.waitForURL('**/projets');
await page.locator('a[href*="/projets/"]').first().click();
await page.waitForSelector('[data-remaining-examples]');
await page.locator('[data-publish]').click();
await attendre(1500);
await page.screenshot({ path: `${dossier}/03-publication-refusee.png`, fullPage: true });

console.log(JSON.stringify(resultats), 'Erreurs :', erreurs.length ? erreurs : 'aucune');
await navigateur.close();
