// Captures du premier écran « Que voulez-vous créer aujourd'hui ? » sur un téléphone simulé (Pixel 7, en français) :
// accueil sans compte, idée touchée, inscription avec le texte gardé, site créé, « Mes projets », menu « ⋯ ».
// Données inventées. Le Runner n'a pas besoin de tourner : la demande attend simplement son tour.
//
//   php artisan serve --port=8123   (dans un autre terminal)
//   node scripts/captures-premier-ecran.js docs/captures/premier-ecran
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');

const dossier = process.argv[2] || 'docs/captures/premier-ecran';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' });
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(e.message));
  const capture = (nom, pleine = false) => page.screenshot({ path: `${dossier}/${nom}.png`, fullPage: pleine });

  // 1. L'accueil, sans compte : une question, une zone de saisie.
  await page.goto(base + '/');
  await attendre(800);
  await capture('01-accueil');
  await capture('02-accueil-complet', true);

  // 2. Une idée touchée remplit la zone (le texte reste modifiable).
  await page.getByRole('button', { name: 'mon restaurant' }).click();
  await attendre(800);
  await page.locator('[data-site-prompt-input]').fill('Un site web pour mon restaurant Le Palmier à Lomé, quartier Bè, avec le menu, les prix en FCFA et un bouton WhatsApp pour réserver une table.');
  await capture('03-accueil-idee');

  // 3. Envoi sans compte : l'inscription, avec le texte gardé.
  await page.locator('[data-site-prompt-send]').click();
  await page.waitForURL('**/register');
  await attendre(800);
  await capture('04-inscription-idee-gardee', true);

  await page.fill('#name', 'Ama Essai');
  await page.fill('#email', `essai-premier-ecran-${Date.now()}@exemple.tg`);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.locator('[data-flux-checkbox], input[type=checkbox]').first().click();
  await page.locator('button[type=submit]').click();

  // 4. Kekeli crée le site (nom deviné) et commence tout de suite.
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/, { timeout: 30000 });
  await attendre(1500);
  await capture('05-site-cree');

  // 5. « Mes projets » : la même question en haut, puis la liste des sites.
  await page.goto(base + '/projets');
  await attendre(1000);
  await capture('06-mes-projets');

  // 6. Un deuxième site, avec une photo jointe depuis le téléphone (bouton photo de la zone).
  await page.locator('[data-site-prompt-input]').fill('Un pressing à Lomé : les clients créent un compte, déposent leurs commandes de linge et suivent leur état. Le gérant valide chaque commande.');
  await page.locator('[data-site-prompt-attach] input[type=file]').setInputFiles({ name: 'devanture.png', mimeType: 'image/png', buffer: readFileSync('docs/captures/fournisseurs-gratuits/nettoyage-apercu-redemarre.png') });
  await attendre(2500);
  await capture('07-mes-projets-photo');
  await page.locator('[data-site-prompt-send]').click();
  await page.waitForURL(/\/projets\/[0-9a-z]{26}$/, { timeout: 30000 });
  await attendre(1500);
  await capture('08-site-avec-base-de-donnees');

  // 7. Le menu « ⋯ » : l'accès à Internet de Kekeli, rangé à l'écart.
  await page.locator('[data-project-more]').click();
  await attendre(600);
  await capture('09-menu-plus');

  await page.goto(base + '/projets');
  await attendre(1000);
  await capture('10-mes-projets-liste', true);

  // 8. Message en français quand le texte est trop court.
  await page.locator('[data-site-prompt-input]').fill('Un site');
  await page.locator('[data-site-prompt-send]').click();
  await attendre(1200);
  await capture('11-texte-trop-court');

  console.log(erreurs.length ? `Erreurs de la page : ${erreurs.join(' | ')}` : 'Aucune erreur dans la page.');
  await navigateur.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
