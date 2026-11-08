// Captures de l'étape 3 : l'aperçu en direct, sur un téléphone simulé.
//
// Démonstration sans ordinateur E2B : ce script joue le rôle du Runner (événements signés envoyés
// au site) et d'un agent simulé qui écrit les fichiers d'un site pas à pas. L'aperçu est servi par
// le vrai serveur d'aperçu du Runner (runner/src/apercu/serveur.mjs), en HTTPS, sur ce poste.
//
// Utilisation, avec le site lancé sur le port 8123 (avec le même KEKELI_RUNNER_SECRET et
// KEKELI_DOMAINES_APERCU=localhost) et le serveur d'aperçu qui sert DOSSIER_PROJET :
//   KEKELI_RUNNER_SECRET=… node scripts/captures-etape-3.js docs/captures/etape-3 DOSSIER_PROJET https://4100-demo.localhost
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { entetesSignes } from '../runner/src/signature.js';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [dossier, projetDemo, urlApercu] = process.argv.slice(2);
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const secret = process.env.KEKELI_RUNNER_SECRET;
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

const STYLE = `* { box-sizing: border-box; }
body { margin: 0; font-family: system-ui, sans-serif; color: #2b2118; background: #fffaf3; }
header { padding: 56px 24px 40px; text-align: center; color: #fff; background: linear-gradient(135deg, #d97706, #b45309); }
header h1 { margin: 0 0 8px; font-size: 34px; }
header p { margin: 0; font-size: 17px; opacity: .95; }
.bouton { display: inline-block; margin-top: 20px; padding: 12px 22px; border-radius: 999px; background: #fff; color: #b45309; font-weight: 700; text-decoration: none; }
section { padding: 32px 24px; max-width: 720px; margin: 0 auto; }
h2 { color: #b45309; margin-top: 0; }
.plat { display: flex; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px dashed #e7d3b5; }
.plat strong { display: block; }
.plat span { color: #7c6a58; font-size: 14px; }
.prix { font-weight: 700; white-space: nowrap; }
.horaires { background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 16px rgba(180, 83, 9, .12); }
footer { padding: 24px; text-align: center; font-size: 14px; color: #7c6a58; }
`;

const ENTETE = `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Chez Kekeli — Restaurant à Lomé</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
  <h1>Chez Kekeli</h1>
  <p>La cuisine togolaise, au cœur d'Adidogomé</p>
  <a class="bouton" href="#menu">Voir le menu</a>
</header>
`;

const MENU = `<section id="menu">
  <h2>Notre menu</h2>
  <div class="plat"><div><strong>Fufu sauce arachide</strong><span>Poulet bicyclette, sauce maison</span></div><div class="prix">2 500 F CFA</div></div>
  <div class="plat"><div><strong>Akoumé et sauce gboma</strong><span>Épinards, poisson fumé</span></div><div class="prix">2 000 F CFA</div></div>
  <div class="plat"><div><strong>Ablo et poisson braisé</strong><span>Piment frais, oignons</span></div><div class="prix">3 000 F CFA</div></div>
  <div class="plat"><div><strong>Jus de bissap</strong><span>Fait maison, bien frais</span></div><div class="prix">500 F CFA</div></div>
</section>
<section>
  <div class="horaires">
    <h2>Horaires</h2>
    <p>Du lundi au samedi : 11 h 00 – 22 h 00<br>Le dimanche : 12 h 00 – 20 h 00</p>
    <p>Adidogomé, près du Lycée Moderne 2, Lomé</p>
  </div>
</section>
`;

const FIN = `<footer>© 2026 Chez Kekeli — Site de démonstration</footer>
</body>
</html>
`;

let tache = null;
async function evenement(evt) {
  const corps = JSON.stringify({ tache, evenement: evt });
  const reponse = await fetch(base + '/runner/evenements', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...entetesSignes(secret, corps) },
    body: corps,
  });
  if (!reponse.ok) throw new Error(`Le site a répondu ${reponse.status} à « ${evt.type} »`);
}

(async () => {
  if (!secret) throw new Error('KEKELI_RUNNER_SECRET manque.');
  await rm(projetDemo, { recursive: true, force: true });
  await mkdir(projetDemo, { recursive: true });
  await mkdir(dossier, { recursive: true });

  const navigateur = await chromium.launch({ args: ['--lang=fr-FR', '--no-proxy-server'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', ignoreHTTPSErrors: true });
  const page = await contexte.newPage();
  const erreurs = [];
  page.on('pageerror', (e) => erreurs.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !m.text().includes('style.css')) erreurs.push(m.text()); });
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
  await page.getByLabel('Nom du projet').first().fill('Restaurant Chez Kekeli');
  await page.getByRole('button', { name: 'Créer le projet' }).click();
  await page.waitForURL('**/projets/*');
  await attendre(600);
  await capture('01-onglet-discussion');

  await onglet('apercu');
  await capture('02-onglet-apercu-vide');

  await onglet('discussion');
  await page.getByLabel('Message').fill("Crée la page d'accueil de mon restaurant Chez Kekeli à Lomé, avec le menu et les horaires.");
  await page.getByRole('button', { name: 'Envoyer' }).click();
  await page.waitForSelector('[data-run-status]');
  tache = execFileSync('php', ['artisan', 'tinker', '--execute', 'echo App\\Models\\AgentRun::latest()->value("id");']).toString().trim();

  await evenement({ type: 'demarrage' });
  await attendre(2500);
  await onglet('apercu');
  await capture('03-kekeli-prepare-apercu');

  await evenement({ type: 'apercu', url: urlApercu, expire_a: new Date(Date.now() + 30 * 60000).toISOString() });
  await page.waitForSelector('[data-preview="live"] iframe', { timeout: 10000 });
  await attendre(2000);
  await capture('04-apercu-attend-la-page');

  // L'agent simulé écrit la page d'accueil : l'aperçu se recharge tout seul.
  await writeFile(path.join(projetDemo, 'index.html'), ENTETE + FIN);
  await evenement({ type: 'etape', genre: 'fichier_ecrit', cible: 'index.html' });
  await attendre(2500);
  await capture('05-premiere-version');

  await writeFile(path.join(projetDemo, 'style.css'), STYLE);
  await evenement({ type: 'etape', genre: 'fichier_ecrit', cible: 'style.css' });
  await attendre(2500);
  await capture('06-avec-les-couleurs');

  await writeFile(path.join(projetDemo, 'index.html'), ENTETE + MENU + FIN);
  await evenement({ type: 'etape', genre: 'fichier_modifie', cible: 'index.html' });
  await attendre(2500);
  await page.frameLocator('iframe').locator('#menu').scrollIntoViewIfNeeded();
  await attendre(400);
  await capture('07-menu-et-horaires');

  await onglet('discussion');
  await attendre(500);
  await capture('08-discussion-pendant-le-travail');

  await evenement({ type: 'apercu', url: urlApercu, expire_a: new Date(Date.now() + 30 * 60000).toISOString() });
  await evenement({
    type: 'terminee',
    reponse: "J'ai créé la page d'accueil de Chez Kekeli : un en-tête aux couleurs chaudes, le menu avec quatre plats et leurs prix, les horaires et l'adresse à Adidogomé. Le style est dans le fichier style.css. Vous pouvez la voir dans l'onglet « Aperçu ».",
  });
  await page.waitForSelector('[data-run-status="completed"]', { timeout: 15000 });
  await attendre(800);
  await capture('09-resultat', true);

  await page.locator('[data-show-preview]').click();
  await attendre(1200);
  await capture('10-voir-apercu');

  // Sur un ordinateur : la discussion et l'aperçu côte à côte.
  const bureau = await navigateur.newContext({ viewport: { width: 1280, height: 800 }, locale: 'fr-FR', ignoreHTTPSErrors: true, storageState: await contexte.storageState() });
  const pageBureau = await bureau.newPage();
  await pageBureau.goto(page.url());
  await attendre(2000);
  await pageBureau.screenshot({ path: `${dossier}/11-ordinateur-cote-a-cote.png` });
  await bureau.close();

  // Trente minutes après la dernière demande, l'ordinateur s'éteint : l'aperçu passe en pause.
  execFileSync('php', ['artisan', 'tinker', '--execute', `App\\Models\\Project::query()->update(["preview_expires_at" => now()->subMinute()]);`]);
  await page.reload();
  await attendre(800);
  await onglet('apercu');
  await capture('12-apercu-en-pause');

  console.log('Erreurs du navigateur :', erreurs.length ? erreurs : 'aucune');
  await navigateur.close();
})().catch((e) => { console.error(e); process.exit(1); });
