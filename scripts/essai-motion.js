// Essai réel de Kekeli Motion, comme un client sur un téléphone simulé : la phrase part de « Que voulez-vous créer
// aujourd'hui ? », Kekeli crée l'animation, on la touche (soleil, personnage), puis on la met dans un site.
// Le site, la file d'attente et le Runner tournent ; le compte d'essai est inventé.
//
//   node scripts/essai-motion.js docs/captures/motion
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/motion';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const compte = process.env.COMPTE || 'motion@exemple.tg';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const DEMANDE = process.env.DEMANDE || 'Crée un personnage animé qui marche dans un jardin avec un soleil qui se couche.';

async function attendreFin(page, minimum = 1) {
  for (let i = 0; i < 90; i++) {
    await attendre(3000);
    const cartes = page.locator('[data-run-status]');
    const n = await cartes.count();
    const statut = n ? await cartes.last().getAttribute('data-run-status') : null;
    if (n >= minimum && ['completed', 'failed', 'stopped'].includes(statut)) return statut;
  }
  return 'trop long';
}

/** Un travail sur un site : fini quand plus aucune demande n'est active (le bouton d'envoi revient). */
async function attendreSite(page) {
  for (let i = 0; i < 200; i++) {
    await attendre(5000);
    // Kekeli pose ses questions avant un site : « Choisis pour moi », comme un client pressé.
    const choisir = page.locator('[data-choose-for-me]');
    if (await choisir.count()) await choisir.last().click().catch(() => {});
    const actif = await page.locator('[data-run-status="running"], [data-run-status="waiting"], [data-run-status="queued"]').count();
    if (!actif && i > 3) return;
  }
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })).newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(e.message));
  await page.goto(`${base}/login`);
  await page.locator('[data-password-login]').click();
  await page.locator('input[type=email]').fill(compte);
  await page.locator('input[type=password]').fill('motdepasse-solide-2026');
  await page.locator('[data-password-submit]').click();
  await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 30000 });

  // 1. La demande, depuis la grande question (PROJET=… pour reprendre une animation déjà créée).
  const debut = Date.now();
  let projet = process.env.PROJET || '';
  let statut = 'reprise';
  if (!projet) {
  await page.goto(`${base}/projets`);
  await page.locator('textarea').first().fill(DEMANDE);
  await page.screenshot({ path: `${dossier}/1-demande.png` });
  await page.locator('[data-prompt-send]').first().click();
  await page.waitForURL('**/projets/**', { timeout: 60000 });
  // Le travail est fini quand la carte du fichier apparaît dans la discussion.
  await page.locator('[data-file-card], [data-run-status="failed"]').first().waitFor({ timeout: 300000 }).catch(() => {});
  statut = (await page.locator('[data-file-card]').count()) ? 'completed' : 'échec';
  projet = page.url().split('/projets/')[1].split('?')[0];
  await attendre(1500);
  await page.screenshot({ path: `${dossier}/2-discussion.png` });
  }
  const secondes = Math.round((Date.now() - debut) / 1000);
  console.log('animation', statut, secondes, 's', projet);

  // 2. L'aperçu vivant : on le regarde, on touche le soleil (la nuit tombe), puis un personnage.
  await page.goto(`${base}/projets/${projet}?vue=apercu`);
  await attendre(4000);
  await page.screenshot({ path: `${dossier}/3-apercu.png` });
  const cadre = page.frameLocator('[data-animation-frame]');
  const soleil = cadre.locator('[aria-label="Soleil"]');
  if (await soleil.count()) await soleil.first().dispatchEvent('pointerdown');
  await attendre(3500);
  const personnages = cadre.locator('.kekeli-personnage');
  if (await personnages.count()) await personnages.first().dispatchEvent('pointerdown');
  await attendre(600);
  await page.screenshot({ path: `${dossier}/4-apercu-nuit.png` });

  // 3. Le menu « Télécharger » : page web, image SVG, code TypeScript.
  const telecharger = page.locator('[data-download-open]:visible').first();
  if (await telecharger.count()) {
    await telecharger.click();
    await attendre(500);
    await page.screenshot({ path: `${dossier}/5-telecharger.png` });
    await page.keyboard.press('Escape');
  }

  // 4. La bibliothèque.
  await page.goto(`${base}/bibliotheque`);
  await attendre(2500);
  await page.screenshot({ path: `${dossier}/6-bibliotheque.png` });

  // 5. Mettre l'animation dans un site créé par Kekeli (SITE=… pour un site déjà créé), puis regarder le site.
  if (process.env.INTEGRER) {
    let site = process.env.SITE || '';
    if (!site) {
      await page.goto(`${base}/projets`);
      await page.locator('textarea').first().fill('Crée le site vitrine de mon restaurant « Chez Tante Afi » à Lomé, quartier Bè : plat du jour riz, poisson braisé et alloco à 1 500 FCFA, ouvert de 11 h à 22 h, téléphone 90 00 00 00, couleurs orange et vert.');
      await page.locator('[data-prompt-send]').first().click();
      await page.waitForURL('**/projets/**', { timeout: 60000 });
      site = page.url().split('/projets/')[1].split('?')[0];
      console.log('site en construction', site);
      await attendreSite(page);
    }
    await page.goto(`${base}/projets/${projet}?vue=apercu`);
    await attendre(2500);
    await page.locator('[data-animation-integrate-open]').click();
    await attendre(800);
    await page.screenshot({ path: `${dossier}/7-mettre-dans-un-site.png` });
    await page.locator('[data-animation-integrate-form] select').selectOption(site);
    await attendre(500);
    await page.locator('[data-animation-integrate-submit]').click();
    await page.waitForURL(`**/projets/${site}**`, { timeout: 60000 });
    const debutSite = Date.now();
    await attendre(3000);
    await page.screenshot({ path: `${dossier}/8-site-au-travail.png` });
    await attendreSite(page);
    console.log('intégration', Math.round((Date.now() - debutSite) / 1000), 's');
    await page.screenshot({ path: `${dossier}/9-site-compte-rendu.png` });
    await page.goto(`${base}/projets/${site}?vue=apercu`);
    await attendre(12000);
    await page.screenshot({ path: `${dossier}/10-site-apercu.png` });
  }

  writeFileSync(`${dossier}/essai.json`, JSON.stringify({ demande: DEMANDE, statut, secondes, projet, erreurs }, null, 2));
  console.log('erreurs de page', erreurs);
  await navigateur.close();
})();
