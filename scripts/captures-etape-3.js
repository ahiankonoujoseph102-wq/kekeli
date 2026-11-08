// Captures de l'étape 3 : l'aperçu en direct, avec un vrai essai (ordinateur E2B, agent OpenCode, Gemini).
//
// Utilisation, avec le site (port 8123), la file d'attente et le Runner lancés :
//   node scripts/captures-etape-3.js construction docs/captures/etape-3 ETAT.json
//   (plus de 30 minutes plus tard, quand l'ordinateur s'est éteint)
//   node scripts/captures-etape-3.js pause docs/captures/etape-3 ETAT.json
//
// ETAT.json garde la connexion et l'adresse du projet entre les deux parties.
import { createRequire } from 'node:module';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [partie, dossier, fichierEtat] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const demande = process.env.DEMANDE || "Crée une page d'accueil pour un restaurant à Lomé, avec le menu et les horaires.";
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const telephone = { ...devices['Pixel 7'], locale: 'fr-FR' };

async function demarrer(options = {}) {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...telephone, ...options });
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') erreurs.push(m.text()); });
  return { navigateur, contexte, page, erreurs };
}

const cadreApercu = (page) => page.frames().find((f) => /e2b\.(app|dev)/.test(f.url()));

async function construction() {
  await mkdir(dossier, { recursive: true });
  const { navigateur, contexte, page, erreurs } = await demarrer();
  const capture = (nom, pleine = false) => page.screenshot({ path: `${dossier}/${nom}.png`, fullPage: pleine });
  const onglet = async (nom) => { await page.locator(`[data-tab="${nom}"]`).click(); await attendre(400); };

  await page.goto(base + '/register');
  await page.fill('#name', 'Joseph Test');
  await page.fill('#email', `joseph${Date.now()}@exemple.tg`);
  await page.fill('#password', 'motdepasse-solide-2026');
  await page.fill('#password_confirmation', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets');

  await page.getByRole('button', { name: 'Nouveau projet' }).first().click();
  await attendre(400);
  await page.getByLabel('Nom du projet').first().fill('Restaurant à Lomé');
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await attendre(600);
  await capture('01-onglet-discussion');
  await onglet('apercu');
  await capture('02-onglet-apercu-vide');

  await onglet('discussion');
  await page.getByLabel('Message').fill(demande);
  await page.getByRole('button', { name: 'Envoyer' }).click();
  const debut = Date.now();
  await page.waitForSelector('[data-run-status]');
  await attendre(1500);
  await capture('03-demande-envoyee');

  await onglet('apercu');
  await page.waitForSelector('[data-preview="starting"]', { timeout: 30000 }).catch(() => {});
  await capture('04-kekeli-prepare-apercu');

  await page.waitForSelector('[data-preview="live"] iframe', { timeout: 180000 });
  console.log(`Aperçu en ligne après ${Math.round((Date.now() - debut) / 1000)} secondes.`);

  // Pendant la construction : une capture à chaque fois que le contenu de l'aperçu change.
  let rechargements = 0;
  page.on('framenavigated', (f) => { if (f !== page.mainFrame() && /e2b\.(app|dev)/.test(f.url())) rechargements++; });
  let derniere = null;
  let numero = 0;
  const finie = () => page.locator('[data-run-status="completed"], [data-run-status="failed"], [data-run-status="waiting"]').count();
  const limite = Date.now() + 15 * 60000;
  while (Date.now() < limite) {
    const cadre = cadreApercu(page);
    const contenu = cadre ? await cadre.evaluate(() => document.documentElement.outerHTML).catch(() => null) : null;
    const empreinte = contenu ? createHash('sha1').update(contenu).digest('hex') : null;
    if (empreinte && empreinte !== derniere) {
      derniere = empreinte;
      numero++;
      await attendre(600);
      await capture(`05-construction-${numero}`);
      console.log(`Aperçu ${numero} capturé après ${Math.round((Date.now() - debut) / 1000)} secondes.`);
    }
    if (await finie()) break;
    await attendre(1500);
  }
  const statut = await page.locator('[data-run-status]').first().getAttribute('data-run-status');
  console.log(`Demande terminée avec l'état « ${statut} » en ${Math.round((Date.now() - debut) / 1000)} secondes ; rechargements automatiques de l'aperçu : ${rechargements}.`);

  await attendre(2000);
  await capture('06-apercu-final');
  const cadre = cadreApercu(page);
  if (cadre) {
    await cadre.evaluate(() => window.scrollTo(0, document.body.scrollHeight / 2)).catch(() => {});
    await attendre(500);
    await capture('07-apercu-final-suite');
  }

  await onglet('discussion');
  await attendre(500);
  await capture('08-resultat', true);
  const actions = page.locator('details summary').first();
  if (await actions.count()) {
    await actions.click();
    await attendre(400);
    await capture('09-actions-de-kekeli', true);
  }

  await page.locator('[data-show-preview]').click();
  await attendre(1500);
  await capture('10-voir-apercu');
  console.log('Bouton « Voir l’aperçu » :', await page.locator('[data-panel="apercu"]').isVisible() ? 'ouvre bien l’aperçu' : 'ne fonctionne pas');

  const bureau = await navigateur.newContext({ viewport: { width: 1280, height: 800 }, locale: 'fr-FR', storageState: await contexte.storageState() });
  const pageBureau = await bureau.newPage();
  await pageBureau.goto(page.url());
  await attendre(4000);
  await pageBureau.screenshot({ path: `${dossier}/11-ordinateur-cote-a-cote.png` });
  await bureau.close();

  await writeFile(fichierEtat, JSON.stringify({ url: page.url(), session: await contexte.storageState(), apercu: cadreApercu(page)?.url() ?? null }));
  console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
  await navigateur.close();
}

async function pause() {
  // La page est ouverte avant l'extinction de l'ordinateur, puis laissée telle quelle :
  // l'aperçu doit passer « en pause » tout seul, sans recharger la page.
  const etat = JSON.parse(await readFile(fichierEtat, 'utf8'));
  const { navigateur, page, erreurs } = await demarrer({ storageState: etat.session });
  await page.goto(etat.url);
  await attendre(800);
  await page.locator('[data-tab="apercu"]').click();
  await page.waitForSelector('[data-panel="apercu"] [data-preview="asleep"]', { timeout: 15 * 60000 });
  await attendre(600);
  await page.screenshot({ path: `${dossier}/12-apercu-en-pause.png` });
  console.log(`Aperçu passé en pause tout seul à ${new Date().toISOString()}.`);
  if (etat.apercu) {
    const reponse = await fetch(etat.apercu).catch((e) => ({ status: e.message }));
    console.log(`Adresse de l'ancien aperçu : réponse ${reponse.status}.`);
  }
  console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
  await navigateur.close();
}

(partie === 'pause' ? pause() : construction()).catch((e) => { console.error(e); process.exit(1); });
