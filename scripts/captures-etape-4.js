// Captures de l'étape 4 (sécurité, sauvegarde et reprise), avec un vrai essai :
// ordinateur E2B, agent OpenCode, Gemini. Le site (port 8123), la file d'attente et le Runner doivent tourner.
//
//   node scripts/captures-etape-4.js PARTIE docs/captures/etape-4 ETAT.json
//
// Parties, dans l'ordre : construction, prive, permission, sites, admin, pause, rallumer, reprise,
// restauration, effacement. ETAT.json garde la connexion et l'adresse du projet entre les parties.
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [partie, dossier, fichierEtat] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const telephone = { ...devices['Pixel 7'], locale: 'fr-FR' };
const artisan = (...args) => execFileSync('php', ['artisan', ...args]).toString().trim();

async function lireEtat() {
  return JSON.parse(await readFile(fichierEtat, 'utf8').catch(() => '{}'));
}

async function ouvrir(etat, options = {}) {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...telephone, ...options, ...(etat?.session ? { storageState: etat.session } : {}) });
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') erreurs.push(m.text()); });
  const capture = (nom, pleine = false) => page.screenshot({ path: `${dossier}/${nom}.png`, fullPage: pleine });
  const onglet = async (nom) => { await page.locator(`[data-tab="${nom}"]`).click(); await attendre(400); };
  return { navigateur, contexte, page, erreurs, capture, onglet };
}

/** Envoie une demande et attend la fin du travail de l'agent ; renvoie l'état final. */
async function demander(page, texte, { pendant } = {}) {
  const avant = await page.locator('[data-run-status]').count();
  await page.getByLabel('Message').fill(texte);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForFunction((n) => document.querySelectorAll('[data-run-status]').length > n, avant);
  const debut = Date.now();
  if (pendant) await pendant();
  await page.waitForFunction(
    (n) => {
      const cartes = document.querySelectorAll('[data-run-status]');
      return cartes.length > n && ['completed', 'failed', 'waiting'].includes(cartes[cartes.length - 1].dataset.runStatus);
    },
    avant,
    { timeout: 15 * 60000 },
  );
  await attendre(1500);
  const statut = await page.locator('[data-run-status]').last().getAttribute('data-run-status');
  console.log(`Demande « ${texte.slice(0, 50)}… » : ${statut} en ${Math.round((Date.now() - debut) / 1000)} secondes.`);
  return statut;
}

const parties = {
  /** Reprend l'essai avec le dernier compte créé (même mot de passe), sans refaire de demande. */
  async connexion() {
    const email = artisan('tinker', '--execute', 'echo App\\Models\\User::latest("id")->value("email");')
    const { navigateur, contexte, page, capture, onglet } = await ouvrir(null)
    await page.goto(base + '/login')
    await page.fill('input[name=email]', email)
    await page.fill('input[name=password]', 'motdepasse-solide-2026')
    await page.click('button[type=submit]')
    await page.waitForURL('**/projets')
    await page.locator('a[href*="/projets/"]').first().click()
    await page.waitForURL('**/projets/*')
    await attendre(800)
    await page.locator('[data-run-status] details summary').last().click()
    await attendre(400)
    await capture('01-licences-et-sauvegarde', true)
    await onglet('apercu')
    await attendre(4000)
    await capture('02-apercu-prive-du-proprietaire')
    const cadre = page.frames().find((f) => /e2b\.(app|dev)/.test(f.url()))
    await writeFile(fichierEtat, JSON.stringify({ email, url: page.url(), session: await contexte.storageState(), apercu: cadre?.url() ?? null }))
    console.log('Aperçu ouvert avec un jeton :', cadre ? 'oui' : 'non')
    await navigateur.close()
  },

  async construction() {
    await mkdir(dossier, { recursive: true });
    const { navigateur, contexte, page, erreurs, capture, onglet } = await ouvrir(null);
    const email = `joseph${Date.now()}@exemple.tg`;
    await page.goto(base + '/register');
    await page.fill('#name', 'Joseph Test');
    await page.fill('#email', email);
    await page.fill('#password', 'motdepasse-solide-2026');
    await page.fill('#password_confirmation', 'motdepasse-solide-2026');
    await page.click('button[type=submit]');
    await page.waitForURL('**/projets');
    await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
    await attendre(400);
    await page.getByLabel('Nom du projet').first().fill('Restaurant à Lomé');
    await page.getByRole('button', { name: 'Créer le projet' }).click();
    await page.waitForURL('**/projets/*');
    await attendre(500);

    await demander(page, "Crée une page d'accueil pour un restaurant à Lomé, avec une belle photo, le menu et les horaires.");
    await page.locator('[data-run-status] details summary').last().click();
    await attendre(400);
    await capture('01-licences-et-sauvegarde', true);
    await onglet('apercu');
    await attendre(3000);
    await capture('02-apercu-prive-du-proprietaire');

    const cadre = page.frames().find((f) => /e2b\.(app|dev)/.test(f.url()));
    await writeFile(fichierEtat, JSON.stringify({ email, url: page.url(), session: await contexte.storageState(), apercu: cadre?.url() ?? null }));
    console.log('Adresse de l’aperçu avec jeton :', cadre ? 'oui (' + new URL(cadre.url()).pathname.slice(0, 4) + '…)' : 'non');
    console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
    await navigateur.close();
  },

  async prive() {
    const etat = await lireEtat();
    const { navigateur, page, capture } = await ouvrir(null);
    const sansJeton = new URL(etat.apercu).origin + '/';
    const reponse = await page.goto(sansJeton);
    console.log(`Aperçu sans jeton : réponse ${reponse.status()}.`);
    await capture('03-apercu-sans-jeton');
    await navigateur.close();
  },

  async permission() {
    const etat = await lireEtat();
    const { navigateur, page, capture } = await ouvrir(etat);
    await page.goto(etat.url);
    await attendre(800);
    await demander(page, "Lance exactement la commande « rm -rf images » dans le dossier du projet pour supprimer le dossier images, puis dis-moi ce qui s'est passé.", {
      pendant: async () => {
        await page.waitForSelector('[data-permission="pending"]', { timeout: 5 * 60000 });
        await attendre(800);
        await capture('04-permission-demandee', true);
        await page.locator('[data-refuse]').last().click();
        await attendre(2000);
        await capture('05-permission-refusee', true);
      },
    });
    await capture('06-reponse-apres-refus', true);
    await navigateur.close();
  },

  async sites() {
    const etat = await lireEtat();
    const { navigateur, page, capture } = await ouvrir(etat);
    await page.goto(etat.url);
    await attendre(800);
    await page.locator('[data-open-sites]').click();
    await attendre(600);
    await page.getByLabel('Site à autoriser').fill('https://api.exemple-meteo.com/v1');
    await page.getByRole('button', { name: 'Ajouter' }).click();
    await attendre(1000);
    await page.locator('[data-sites-history] summary').click();
    await attendre(400);
    await capture('07-sites-autorises');
    await navigateur.close();
  },

  async admin() {
    const etat = await lireEtat();
    const { navigateur, page, capture } = await ouvrir(null);
    const email = `admin${Date.now()}@exemple.tg`;
    await page.goto(base + '/register');
    await page.fill('#name', 'Administrateur');
    await page.fill('#email', email);
    await page.fill('#password', 'motdepasse-solide-2026');
    await page.fill('#password_confirmation', 'motdepasse-solide-2026');
    await page.click('button[type=submit]');
    await page.waitForURL('**/projets');
    artisan('kekeli:admin', email);
    await page.goto(base + '/administration/projets');
    await attendre(800);
    await capture('08-administration-projets');
    await page.goto(etat.url);
    await attendre(800);
    await page.locator('[data-tab="apercu"]').click();
    await attendre(3000);
    await capture('09-administrateur-voit-l-apercu');
    await navigateur.close();
  },

  async pause() {
    // La page reste ouverte jusqu'à la pause : elle annonce la pause, puis passe en pause toute seule.
    const etat = await lireEtat();
    const { navigateur, page, capture, onglet, erreurs } = await ouvrir(etat);
    await page.goto(etat.url);
    await attendre(800);
    await onglet('apercu');
    await page.waitForSelector('[data-preview-pausing-soon]:visible, [data-preview="pausing"]', { timeout: 40 * 60000 });
    await attendre(1000);
    await capture('10-kekeli-se-met-en-pause');
    console.log(`« Kekeli se met en pause… » affiché à ${new Date().toISOString()}.`);
    await page.waitForSelector('[data-preview="asleep"]', { timeout: 10 * 60000 });
    await attendre(800);
    await capture('11-apercu-en-pause');
    console.log(`Aperçu en pause à ${new Date().toISOString()}.`);
    console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
    await navigateur.close();
  },

  async rallumer() {
    const etat = await lireEtat();
    const { navigateur, page, capture, onglet } = await ouvrir(etat);
    await page.goto(etat.url);
    await attendre(800);
    await onglet('apercu');
    const debut = Date.now();
    await page.locator('[data-wake-preview]').click();
    await page.waitForSelector('[data-preview="waking"]', { timeout: 10000 });
    await capture('12-kekeli-rallume-l-apercu');
    await page.waitForSelector('[data-preview="live"] iframe', { timeout: 5 * 60000 });
    await attendre(3000);
    console.log(`Aperçu rallumé en ${Math.round((Date.now() - debut) / 1000)} secondes.`);
    await capture('13-apercu-rallume');
    await navigateur.close();
  },

  async reprise() {
    const etat = await lireEtat();
    const { navigateur, page, capture } = await ouvrir(etat);
    await page.goto(etat.url);
    await attendre(800);
    await demander(page, 'Ajoute une section « Contact » avec un numéro WhatsApp et l’adresse du restaurant.');
    await page.locator('[data-run-status] details summary').last().click();
    await attendre(400);
    await capture('14-reprise-apres-pause', true);
    await navigateur.close();
  },

  async restauration() {
    const etat = await lireEtat();
    const { navigateur, page, capture, onglet } = await ouvrir(etat);
    await page.goto(etat.url);
    await attendre(800);
    await demander(page, 'Change la couleur principale du site en vert, sans rien enlever.');
    await page.locator('[data-run-status] details summary').last().click();
    await attendre(400);
    await capture('15-remise-en-place-depuis-la-sauvegarde', true);
    await onglet('apercu');
    await attendre(3000);
    await capture('16-apercu-apres-remise-en-place');
    await navigateur.close();
  },

  async effacement() {
    const etat = await lireEtat();
    const { navigateur, page, capture } = await ouvrir(etat);
    await page.goto(base + '/projets');
    await attendre(800);
    await page.getByRole('button', { name: 'Actions sur le projet' }).first().click();
    await attendre(300);
    await page.getByRole('menuitem', { name: 'Supprimer' }).click();
    await attendre(600);
    await capture('17-supprimer-le-projet');
    await page.locator('[data-modal="delete-project"] button', { hasText: 'Supprimer' }).click().catch(async () => {
      await page.getByRole('button', { name: 'Supprimer', exact: true }).last().click();
    });
    await attendre(1500);
    await capture('18-projet-supprime');
    await navigateur.close();
  },
};

if (!parties[partie]) {
  console.error(`Partie inconnue : ${partie}. Parties : ${Object.keys(parties).join(', ')}.`);
  process.exit(1);
}
parties[partie]().catch((e) => { console.error(e); process.exit(1); });
