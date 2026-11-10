// Essai réel de la galerie de modèles : un visiteur choisit un modèle, crée son compte, répond aux questions une à
// une, puis Kekeli personnalise tout le site (E2B + Gemini). Mesures : énergie (FCFA), requêtes, durée, contenu
// d'exemple restant, images cassées, contenu qui dépasse de l'écran du téléphone. Captures sur téléphone simulé.
// Le site (port 8123), la file d'attente et le Runner doivent tourner.
//
//   node scripts/essai-modeles.js MODELE docs/captures/galerie RESULTATS.json
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [slug, dossier, fichier] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

// Réponses d'un client inventé, pour chaque modèle essayé.
const REPONSES = {
  restaurant: { nom: 'Maquis Le Baobab', ville: 'Kara, quartier Kpéwa', whatsapp: '+228 91 23 45 67', offre: 'Riz sauce arachide et poulet – 1 500 FCFA\nAttiéké poisson frit – 2 000 FCFA\nPâte de maïs sauce gombo – 1 000 FCFA\nBrochettes de bœuf (5) – 1 500 FCFA\nJus de gingembre – 300 FCFA', couleurs: 'Vert' },
  'salon-coiffure': { nom: 'Salon Grâce Divine', ville: 'Lomé, quartier Agoè-Nyivé', whatsapp: '+228 92 34 56 78', offre: 'Tresses africaines longues – 12 000 FCFA\nNattes collées – 4 000 FCFA\nVanilles – 8 000 FCFA\nDéfrisage et coiffage – 5 000 FCFA', couleurs: 'Violet' },
};

(async () => {
  const reponses = REPONSES[slug] ?? REPONSES.restaurant;
  await mkdir(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  const capture = (nom, pleine = false) => page.screenshot({ path: `${dossier}/${slug}-${nom}.png`, fullPage: pleine });

  // 1. Le visiteur, sans compte, choisit le modèle puis crée son compte.
  await page.goto(`${base}/modeles/${slug}`);
  await page.locator('[data-use-template]').click();
  await page.waitForURL('**/register');
  const email = `essai-modele-${slug}-${Date.now()}@exemple.tg`;
  await page.fill('#name', 'Client essai');
  await page.fill('#email', email);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.locator('[data-accept-terms]').check();
  await page.click('button[type=submit]');
  await page.waitForURL(`**/modeles/${slug}`);
  // Formule Entreprise pour l'essai : l'énergie d'une formule gratuite ne doit pas couper la mesure.
  tinker(`App\\Models\\User::where("email", "${email}")->update(["plan" => "entreprise", "plan_expires_at" => now()->addDays(30)]);`);
  await page.locator('[data-use-template]').click();
  await page.waitForURL('**/projets/*');
  await attendre(800);
  await capture('01-premiere-question');

  // 2. Les questions, une à la fois.
  for (const question of ['nom', 'ville', 'whatsapp', 'offre']) {
    await page.waitForSelector(`[data-template-question="${question}"]`);
    await page.locator('[data-template-answer]').fill(reponses[question]);
    if (question === 'offre') await capture('02-question-prix');
    await page.locator('[data-template-next]').click();
  }
  await page.waitForSelector('[data-template-question="couleurs"]');
  await page.getByLabel(reponses.couleurs, { exact: true }).check();
  await capture('03-question-couleurs');
  await page.locator('[data-template-next]').click();
  await page.waitForSelector('[data-template-question="photos"]');
  await capture('04-question-photos');

  // 3. Kekeli personnalise tout le site.
  const debut = Date.now();
  await page.locator('[data-finish-questions]').click();
  await page.waitForSelector('[data-run-status="completed"], [data-run-status="failed"]', { timeout: 40 * 60000 });
  const duree = Math.round((Date.now() - debut) / 1000);
  await attendre(2000);
  await page.reload();
  await attendre(1500);
  await capture('05-discussion', true);

  // 4. Mesures : énergie, requêtes, contenu d'exemple restant.
  const mesure = JSON.parse(tinker(`
    $run = App\\Models\\AgentRun::whereHas("project.user", fn ($q) => $q->where("email", "${email}"))->latest()->first();
    $taux = app(App\\Support\\Settings::class)->float("usd_to_fcfa");
    $reste = app(App\\Templates\\TemplateGallery::class)->remainingExamples($run->project->fresh());
    echo json_encode(["statut" => $run->status, "modele" => $run->model, "requetes" => $run->requests, "images" => $run->images_created,
      "modele_usd" => $run->model_cost_usd, "ordinateur_usd" => $run->computer_cost_usd, "images_usd" => $run->image_cost_usd,
      "fcfa" => round(($run->model_cost_usd + $run->computer_cost_usd + $run->image_cost_usd) * $taux, 1),
      "tokens" => ["envoyes" => $run->input_tokens, "cache" => $run->cached_tokens, "recus" => $run->output_tokens + $run->reasoning_tokens],
      "secondes_ordinateur" => $run->computer_seconds,
      "exemples_restants" => array_map(fn ($e) => $e["genre"].": ".$e["valeur"], $reste),
      "etapes" => $run->steps()->pluck("kind")->countBy()->all()]);
  `));

  // 5. Le site personnalisé, ouvert sur le téléphone.
  await page.locator('[data-tab="apercu"]').click();
  await page.waitForSelector('[data-preview="live"] iframe', { timeout: 180000 });
  const adresse = await page.locator('[data-preview="live"] iframe').getAttribute('src');
  const site = await contexte.newPage();
  await site.goto(adresse);
  await attendre(3000);
  const verification = await site.evaluate((numeroExemple) => ({
    titre: document.title,
    depasse: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
    imagesCassees: [...document.images].filter((img) => img.complete && img.naturalWidth === 0).map((img) => img.getAttribute('src')),
    numeroExemple: document.body.innerHTML.replace(/\D/g, ' ').includes('90 12 34 56') || document.body.innerHTML.includes(numeroExemple),
    liensWhatsapp: [...document.querySelectorAll('a[href*="wa.me"]')].map((a) => a.getAttribute('href').split('?')[0]),
    temoignages: document.querySelectorAll('.temoignage, blockquote').length,
  }), '22890123456');
  await site.screenshot({ path: `${dossier}/${slug}-06-site-personnalise.png`, fullPage: true });

  const resultat = { modele: slug, duree, ...mesure, site: verification };
  const anciens = JSON.parse(await readFile(fichier, 'utf8').catch(() => '[]'));
  await writeFile(fichier, JSON.stringify([...anciens.filter((r) => r.modele !== slug), resultat], null, 2));
  console.log(JSON.stringify(resultat, null, 1));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
