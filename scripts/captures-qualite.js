// Captures « avant / après » de la qualité des réponses, sur téléphone (Pixel 7) et sur ordinateur, en mode clair et
// en mode sombre. Données inventées, préparées par scripts/preparer-captures-qualite.php : aucune requête au modèle.
//
//   php artisan tinker --execute="require 'scripts/preparer-captures-qualite.php';"
//   php artisan serve --port=8123
//   node scripts/captures-qualite.js apres http://127.0.0.1:8123 docs/captures/qualite-reponses
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const etat = process.argv[2] || 'apres';
const base = process.argv[3] || 'http://127.0.0.1:8123';
const dossier = process.argv[4] || 'docs/captures/qualite-reponses';
const racine = process.env.KEKELI_RACINE || process.cwd();
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const ids = JSON.parse(
  execFileSync('php', ['artisan', 'tinker', '--execute', `$u = App\\Models\\User::where('email', 'afi-qualite@exemple.tg')->first(); echo json_encode($u->projects()->pluck('id', 'name'));`], { cwd: racine }).toString().trim(),
);
const projet = (nom) => `${base}/projets/${ids[nom]}`;

/** Fait défiler la discussion pour montrer le début de la réponse de Kekeli. */
const montrerReponse = (page) => page.evaluate(() => {
  const reponses = document.querySelectorAll('[data-run-report]');
  const derniere = reponses[reponses.length - 1];
  const debut = derniere?.closest('[data-run-status]') ?? derniere;
  debut?.scrollIntoView({ block: 'start' });
});

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];

  for (const theme of ['light', 'dark']) {
    for (const [appareil, reglages] of [
      ['telephone', { ...devices['Pixel 7'] }],
      ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }],
    ]) {
      const nom = (n) => `${dossier}/${etat}-${appareil}${theme === 'dark' ? '-sombre' : ''}-${n}.png`;
      const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
      const page = await contexte.newPage();
      page.on('pageerror', (e) => erreurs.push(e.message));
      await page.goto(base + '/login');
      await page.fill('input[type=email]', 'afi-qualite@exemple.tg');
      await page.fill('input[type=password]', 'motdepasse-solide-2026');
      await page.click('button[type=submit]');
      await page.waitForURL('**/projets**');

      // 1. Une réponse riche : titres, gras, tableau, liste numérotée, recommandation.
      await page.goto(projet('Ouvrir une boutique de pagnes'));
      await attendre(900);
      await montrerReponse(page);
      await attendre(300);
      await page.screenshot({ path: nom('1-reponse-riche') });
      // Le tableau sur téléphone : il défile sur le côté au lieu de déborder.
      await page.evaluate(() => document.querySelector('[data-markdown-table], [data-question-answer]')?.scrollIntoView({ block: 'center' }));
      await attendre(300);
      await page.screenshot({ path: nom('2-tableau') });

      // 2. La réflexion, dépliée.
      const reflexion = page.locator('[data-thinking] summary');
      if (await reflexion.count()) {
        await montrerReponse(page);
        await reflexion.first().click();
        await attendre(300);
        await page.screenshot({ path: nom('3-reflexion') });
      }

      // 3. Une réponse simple et directe.
      await page.goto(projet('Remise de 15 %'));
      await attendre(900);
      await page.screenshot({ path: nom('4-reponse-simple') });

      // 4. Un bloc de code avec son bouton « Copier ».
      await page.goto(projet('Lien WhatsApp sur une page'));
      await attendre(900);
      await page.screenshot({ path: nom('5-bloc-de-code') });

      // 5. Pendant la réponse : la ligne de réflexion en cours.
      await page.goto(projet('Plan pour une formation'));
      await attendre(900);
      await page.screenshot({ path: nom('6-en-cours') });

      // 6. La zone de saisie avec le micro, puis le message clair quand le micro est refusé.
      await page.goto(base + '/projets');
      await attendre(900);
      await page.screenshot({ path: nom('7-micro') });
      const micro = page.locator('[data-prompt-mic]').first();
      if (await micro.count()) {
        await page.evaluate(() => {
          // Navigateur de test : la dictée est simulée et le micro refusé, comme lorsqu'un client touche « Bloquer ».
          window.SpeechRecognition = window.webkitSpeechRecognition = class {
            start() { setTimeout(() => this.onerror?.({ error: 'not-allowed' }), 50); }
            stop() {}
            abort() {}
          };
        });
        await micro.click();
        await attendre(500);
        await page.screenshot({ path: nom('8-micro-refuse') });
      }
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log(erreurs.length ? `Erreurs dans les pages : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur dans les pages.');
})();
