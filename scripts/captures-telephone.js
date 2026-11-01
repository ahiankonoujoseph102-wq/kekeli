// Parcourt Kekeli comme un utilisateur sur téléphone et prend des captures.
//
// Utilisation, avec le site lancé par « php artisan serve --port=8123 » :
//   node scripts/captures-telephone.js docs/captures/etape-1
const { chromium, devices } = require('playwright');
const dossier = process.argv[2];
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';

(async () => {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const page = await navigateur.newPage({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const erreurs = [];
  page.on('pageerror', e => erreurs.push(e.message));
  page.on('console', m => { if (m.type() === 'error') erreurs.push(m.text()); });

  await page.goto(base + '/');
  await page.screenshot({ path: `${dossier}/01-accueil.png`, fullPage: true });

  await page.goto(base + '/register');
  await page.fill('#name', 'Joseph Test');
  await page.fill('#email', `test${Date.now()}@exemple.tg`);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.screenshot({ path: `${dossier}/02-inscription.png`, fullPage: true });
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${dossier}/03-projets-vide.png`, fullPage: true });

  await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
  await page.waitForTimeout(400);
  await page.screenshot({ path: `${dossier}/04-nouveau-projet.png` });
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForTimeout(600);
  await page.screenshot({ path: `${dossier}/05-erreur-nom.png` });

  await page.getByLabel('Nom du projet').first().fill('Site de mon restaurant');
  await page.getByLabel('Description (facultative)').fill('Un site moderne avec le menu et les horaires.');
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${dossier}/06-page-projet.png`, fullPage: true });

  await page.getByRole('link', { name: '← Mes projets' }).click();
  await page.waitForURL('**/projets');
  await page.waitForTimeout(500);
  await page.getByRole('button', { name: 'Actions sur le projet' }).click();
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${dossier}/07-menu-projet.png` });
  await page.getByRole('menuitem', { name: 'Renommer' }).click();
  await page.waitForTimeout(600);
  await page.screenshot({ path: `${dossier}/08-renommer.png` });
  await page.getByLabel('Nom du projet').last().fill('Restaurant Chez Kekeli');
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${dossier}/09-renomme.png`, fullPage: true });

  await page.getByRole('button', { name: 'Actions sur le projet' }).click();
  await page.waitForTimeout(300);
  await page.getByRole('menuitem', { name: 'Supprimer' }).click();
  await page.waitForTimeout(600);
  await page.screenshot({ path: `${dossier}/10-supprimer.png` });
  await page.locator('[data-modal="delete-project"] button', { hasText: 'Supprimer' }).click().catch(async () => {
    await page.getByRole('button', { name: 'Supprimer', exact: true }).last().click();
  });
  await page.waitForTimeout(800);
  await page.screenshot({ path: `${dossier}/11-apres-suppression.png`, fullPage: true });

  await page.goto(base + '/settings/profile');
  await page.screenshot({ path: `${dossier}/12-reglages.png`, fullPage: true });

  console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
  await navigateur.close();
})().catch(e => { console.error(e); process.exit(1); });
