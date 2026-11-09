// Essai réel de l'étape 7 : publication en un clic sur Cloudflare Pages, sur téléphone simulé.
// Le site (port 8123), la file d'attente et le Runner doivent tourner, avec la clé Cloudflare dans le Runner.
//
//   node scripts/essai-etape-7.js docs/captures/etape-7 IDENTIFIANT_DU_PROJET
//
// Le projet doit déjà avoir été construit par Kekeli (une sauvegarde existe).
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier, projet] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();

(async () => {
  await mkdir(dossier, { recursive: true });
  const email = tinker(`echo App\\Models\\Project::find("${projet}")->user->email;`);
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[name=email]', email);
  await page.fill('input[name=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  await page.goto(`${base}/projets/${projet}`);
  await attendre(1500);
  await page.screenshot({ path: `${dossier}/01-bouton-publier.png` });

  const debut = Date.now();
  await page.locator('[data-publish]').click();
  await page.waitForSelector('[data-publication="publishing"]', { timeout: 20000 });
  await page.screenshot({ path: `${dossier}/02-publication-en-cours.png` });
  await page.waitForSelector('[data-publication="published"], [data-publication="failed"]', { timeout: 5 * 60000 });
  const duree = Math.round((Date.now() - debut) / 1000);
  await attendre(800);
  await page.screenshot({ path: `${dossier}/03-site-en-ligne.png` });
  const etat = await page.locator('[data-publication]').getAttribute('data-publication');
  const adresse = etat === 'published' ? await page.locator('[data-published-url]').getAttribute('href') : null;

  // Le site en ligne, ouvert comme un visiteur (sans compte Kekeli), page par page.
  const pages = [];
  if (adresse) {
    const visiteur = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })).newPage();
    let reponse = null;
    for (let essai = 0; essai < 20 && !(reponse && reponse.ok()); essai++) {
      reponse = await visiteur.goto(adresse).catch(() => null);
      if (!(reponse && reponse.ok())) await attendre(3000);
    }
    await attendre(2500);
    const liens = await visiteur.evaluate(() => [...new Set([...document.querySelectorAll('a[href]')]
      .map((a) => a.getAttribute('href'))
      .filter((h) => h && /\.html?$/i.test(h) && !/^https?:|^\/\//.test(h) && !/^(\.\/)?index\.html$/.test(h)))]);
    for (const [i, chemin] of ['', ...liens].slice(0, 5).entries()) {
      const r = await visiteur.goto(new URL(chemin, adresse).toString());
      await attendre(2000);
      const mesure = await visiteur.evaluate(() => ({
        titre: document.title,
        depasse: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        imagesCassees: [...document.images].filter((img) => img.complete && img.naturalWidth === 0).map((img) => img.getAttribute('src')),
      }));
      pages.push({ chemin: chemin || 'index.html', statut: r?.status(), ...mesure });
      await visiteur.screenshot({ path: `${dossier}/${String(i + 4).padStart(2, '0')}-en-ligne-${(chemin || 'index.html').replace(/[^a-z0-9]+/gi, '-').replace(/-html$/, '')}.png`, fullPage: true });
    }
  }
  console.log(JSON.stringify({ etat, adresse, duree, pages, erreur: tinker(`echo App\\Models\\Project::find("${projet}")->publish_error;`) }, null, 1));
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
