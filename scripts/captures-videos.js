// Captures « Montage vidéo et motion design », sur téléphone (Pixel 7) et sur ordinateur, en mode clair et en mode
// sombre : le plan d'une vidéo longue, la progression (« Rendu… 60 % » et « Arrêter »), le lecteur, les versions, la
// carte du fichier. Vraie vidéo de l'essai réel (scripts/essai-videos.php), écrans préparés par
// scripts/preparer-captures-videos.php. Site lancé.
//
//   node scripts/captures-videos.js http://127.0.0.1:8123 docs/captures/videos/ecrans '{"plan":"…","en_cours":"…","video":"…"}'
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2] || 'http://127.0.0.1:8123';
const dossier = process.argv[3] || 'docs/captures/videos/ecrans';
const projets = JSON.parse(process.argv[4]);
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR', '--autoplay-policy=no-user-gesture-required'] });
  const erreurs = [];
  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [['telephone', { ...devices['Pixel 7'] }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }]]) {
      const nom = (n) => `${dossier}/${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'essai-videos@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');

      // 1. Le plan d'une vidéo de plus de 30 secondes, avec « Construire ce plan ».
      await page.goto(`${base}/projets/${projets.plan}`);
      await page.waitForSelector('[data-build-plan]');
      await page.locator('[data-build-plan]').first().scrollIntoViewIfNeeded();
      await attendre(800);
      await page.screenshot({ path: nom('1-plan') });

      // 2. La progression : les étapes et « Rendu… 60 % », le bouton « Arrêter ».
      await page.goto(`${base}/projets/${projets.en_cours}?vue=discussion`);
      await attendre(1500);
      // La page se met à jour toute seule pendant le travail : la liste est dépliée juste avant la capture.
      await page.evaluate(() => document.querySelectorAll('[data-narration]').forEach((d) => { d.open = true; }));
      await attendre(250);
      await page.screenshot({ path: nom('2-progression') });

      // 3. La carte du fichier dans la discussion.
      await page.goto(`${base}/projets/${projets.video}?vue=discussion`);
      await page.waitForSelector('[data-file-card]');
      await page.locator('[data-file-card]').last().scrollIntoViewIfNeeded();
      await attendre(1200);
      await page.screenshot({ path: nom('3-carte-du-fichier') });

      // 4. Le lecteur, avec une image de la vidéo, et les médias avec leur licence dépliés.
      await page.goto(`${base}/projets/${projets.video}?vue=apercu`);
      await page.waitForSelector('[data-video-player]');
      await page.evaluate(async () => {
        const v = document.querySelector('[data-video-player]');
        v.muted = true;
        await new Promise((r) => { v.addEventListener('loadeddata', r, { once: true }); v.load(); setTimeout(r, 4000); });
        v.currentTime = 6;
        await new Promise((r) => { v.addEventListener('seeked', r, { once: true }); setTimeout(r, 3000); });
      });
      await page.locator('[data-video-media] summary').click().catch(() => {});
      await attendre(800);
      await page.screenshot({ path: nom('4-lecteur') });

      // 5. Les versions (la version 2 : prix plus gros, sans sous-titres).
      await page.locator('[data-versions-open]:visible').first().click();
      await page.waitForSelector('[data-versions]');
      await attendre(600);
      await page.screenshot({ path: nom('5-versions') });
      await contexte.close();
    }
  }
  await navigateur.close();
  if (erreurs.length) console.log('Erreurs de la page :', erreurs);
  console.log('Captures prêtes dans', dossier);
})();
