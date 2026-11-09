// Essai réel de l'étape 6 : un vrai site de restaurant demandé à Kekeli, puis jugé sur téléphone simulé.
// Le site (port 8123), la file d'attente et le Runner doivent tourner ; le modèle utilisé est celui de
// KEKELI_FOURNISSEURS au moment de l'essai.
//
//   node scripts/essai-etape-6.js NOM docs/captures/etape-6 RESULTATS.json
//
// Captures : la discussion, puis chaque page du site produit, en entier, sur téléphone.
// Mesures : requêtes et durée, résultat des vérifications de Kekeli, images cassées et contenu qui
// dépasse de l'écran du téléphone, page par page.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [nom, dossier, fichier] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

const DEMANDE = process.env.DEMANDE || "Crée le site professionnel du restaurant « Le Palmier » à Lomé, quartier Bè : une page d'accueil avec une belle photo, une page Menu avec les plats et leurs prix en FCFA, une page À propos et une page Contact avec les horaires, l'adresse et un bouton WhatsApp.";

(async () => {
  await mkdir(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  const email = `essai-${nom}-${Date.now()}@exemple.tg`;
  await page.goto(base + '/register');
  await page.fill('#name', `Essai ${nom}`);
  await page.fill('#email', email);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  // Formule Entreprise pour l'essai : l'énergie d'une formule gratuite ne doit pas couper un grand site.
  tinker(`App\\Models\\User::where("email", "${email}")->update(["plan" => "entreprise"]);`);

  await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
  await attendre(400);
  await page.getByLabel('Nom du projet').first().fill('Restaurant Le Palmier');
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await attendre(500);

  const debut = Date.now();
  await page.getByLabel('Message').fill(DEMANDE);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForSelector('[data-run-status="completed"], [data-run-status="failed"]', { timeout: 30 * 60000 });
  const duree = Math.round((Date.now() - debut) / 1000);
  await attendre(1500);
  await page.locator('[data-run-status] details summary').last().click();
  await attendre(400);
  await page.screenshot({ path: `${dossier}/${nom}-01-discussion.png`, fullPage: true });

  // L'aperçu privé, ouvert directement sur le téléphone pour voir chaque page en entier.
  await page.locator('[data-tab="apercu"]').click();
  await page.waitForSelector('[data-preview="live"] iframe', { timeout: 120000 });
  const adresse = await page.locator('[data-preview="live"] iframe').getAttribute('src');
  const site = await contexte.newPage();
  await site.goto(adresse);
  await attendre(2500);
  const liens = await site.evaluate(() => [...new Set([...document.querySelectorAll('a[href]')]
    .map((a) => a.getAttribute('href'))
    .filter((h) => h && /\.html?$/i.test(h) && !/^https?:|^\/\//.test(h)))]);

  const pages = [];
  for (const [i, chemin] of ['', ...liens.filter((l) => !/^(\.\/)?index\.html$/.test(l))].slice(0, 6).entries()) {
    await site.goto(new URL(chemin, adresse).toString());
    await attendre(2500);
    const mesure = await site.evaluate(() => ({
      titre: document.title,
      depasse: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
      images: document.images.length,
      imagesCassees: [...document.images].filter((img) => img.complete && img.naturalWidth === 0).map((img) => img.getAttribute('src')),
      imagesDuProjet: [...document.images].filter((img) => !/^https?:/.test(img.getAttribute('src') ?? '')).length,
      icones: document.querySelectorAll('svg').length,
      emojis: [...document.body.innerText.matchAll(/\p{Extended_Pictographic}/gu)].map((m) => m[0]),
    }));
    pages.push({ chemin: chemin || 'index.html', ...mesure });
    await site.screenshot({ path: `${dossier}/${nom}-${String(i + 2).padStart(2, '0')}-${(chemin || 'index.html').replace(/[^a-z0-9]+/gi, '-').replace(/-html$/, '')}.png`, fullPage: true });
  }

  const run = JSON.parse(tinker(`echo App\\Models\\AgentRun::with("steps")->whereHas("project.user", fn ($q) => $q->where("email", "${email}"))->latest()->first()->toJson();`));
  const resultat = {
    nom,
    modele: run.model,
    statut: run.status,
    duree,
    requetes: run.requests,
    imagesCreees: run.images_created,
    tokens: { envoyes: run.input_tokens, cache: run.cached_tokens, recus: run.output_tokens + run.reasoning_tokens },
    etapes: run.steps.map((s) => [s.kind, s.target]),
    pages,
  };
  const anciens = JSON.parse(await readFile(fichier, 'utf8').catch(() => '[]'));
  await writeFile(fichier, JSON.stringify([...anciens.filter((r) => r.nom !== nom), resultat], null, 2));
  console.log(JSON.stringify({ ...resultat, etapes: resultat.etapes.filter(([k]) => /qualite|licences|correction|photos|echec/.test(k)) }, null, 1));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
