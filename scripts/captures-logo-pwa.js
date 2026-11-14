// Captures « Logo de Kekeli, site installable et design visible » (docs/captures/logo-pwa-design.md) :
//   - le logo dans toutes ses versions (docs/logo/*.svg) et le favicon à sa vraie taille ;
//   - l'icône sur l'écran d'accueil et le site installé, ouvert sans barre d'adresse (SIMULATIONS dessinées ici : un
//     navigateur sans écran ne peut pas photographier l'invite de Chrome) ;
//   - la vraie page « hors connexion », servie par le service worker quand le réseau est coupé.
//   node scripts/captures-logo-pwa.js http://127.0.0.1:8123 docs/captures/logo-pwa-design
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const [base, dossier] = process.argv.slice(2);
const racine = process.cwd();
mkdirSync(dossier, { recursive: true });

const svg = (fichier) => readFileSync(path.join(racine, 'docs/logo', fichier), 'utf8').replace('<svg', '<svg style="width:100%;height:auto;display:block"');
const donnees = (fichier, type) => `data:${type};base64,${readFileSync(path.join(racine, fichier)).toString('base64')}`;
const police = 'font-family:system-ui,-apple-system,Roboto,sans-serif';

const PIXEL_6 = { viewport: { width: 412, height: 915 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: 'fr-FR' };

(async () => {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });

  // 1. Le logo dans toutes ses versions.
  const planche = await navigateur.newPage({ viewport: { width: 1200, height: 800 }, deviceScaleFactor: 2 });
  const case_ = (fichier, fond, largeur, legende, encre = '#6b655c') => `<figure style="margin:0;background:${fond};border-radius:18px;padding:28px 20px 14px;display:flex;flex-direction:column;align-items:center;gap:14px;border:1px solid #e7e2d9"><div style="width:${largeur}px">${svg(fichier)}</div><figcaption style="${police};font-size:13px;color:${encre}">${legende}</figcaption></figure>`;
  await planche.setContent(`<body style="margin:0;padding:20px;background:#fff;display:grid;grid-template-columns:repeat(3,1fr);gap:14px">
    ${case_('kekeli-symbole.svg', '#FAF8F4', 110, 'Symbole, fond clair')}
    ${case_('kekeli-symbole-sombre.svg', '#171513', 110, 'Symbole, fond sombre', '#a8a196')}
    ${case_('kekeli-icone-application.svg', '#e7e2d9', 150, 'Icône d’application')}
    ${case_('kekeli-horizontal.svg', '#FAF8F4', 300, 'Symbole et nom, fond clair')}
    ${case_('kekeli-horizontal-sombre.svg', '#171513', 300, 'Symbole et nom, fond sombre', '#a8a196')}
    ${case_('kekeli-symbole-noir.svg', '#ffffff', 90, 'Une couleur : noir')}
    ${case_('kekeli-horizontal-noir.svg', '#ffffff', 300, 'Une couleur : noir')}
    ${case_('kekeli-horizontal-blanc.svg', '#000000', 300, 'Une couleur : blanc', '#a8a196')}
    ${case_('kekeli-symbole-blanc.svg', '#000000', 90, 'Une couleur : blanc', '#a8a196')}
  </body>`);
  await planche.screenshot({ path: `${dossier}/logo-toutes-les-versions.png`, fullPage: true });

  // 2. Le favicon à sa vraie taille (16 et 32 pixels) dans un onglet, puis agrandi sans lissage pour voir chaque pixel.
  const ico = donnees('public/favicon.ico', 'image/x-icon');
  const onglet = (fond, texte) => `<div style="display:flex;align-items:center;gap:8px;background:${fond};color:${texte};border-radius:10px 10px 0 0;padding:8px 14px;width:220px;${police};font-size:12px"><img src="${ico}" width="16" height="16">Kekeli</div>`;
  const favicon = await navigateur.newPage({ viewport: { width: 760, height: 220 }, deviceScaleFactor: 1 });
  await favicon.setContent(`<body style="margin:0;padding:20px;background:#fff;${police};font-size:13px;color:#3f3b35">
    <div style="display:flex;gap:16px;margin-bottom:18px">${onglet('#e7e2d9', '#1c1a17')}${onglet('#35302b', '#f3efe8')}</div>
    <div style="display:flex;align-items:flex-end;gap:28px">
      <div>16 × 16 réel<br><img src="${ico}" width="16" height="16"></div>
      <div>32 × 32 réel<br><img src="${ico}" width="32" height="32"></div>
      <div>16 × 16 agrandi 6 fois<br><img src="${ico}" width="96" height="96" style="image-rendering:pixelated"></div>
      <div>32 × 32 agrandi 3 fois<br><img src="${ico}" width="96" height="96" style="image-rendering:pixelated;image-resolution:32dpi"></div>
    </div></body>`);
  await favicon.screenshot({ path: `${dossier}/favicon-petites-tailles.png` });

  // 3. Simulation : l'invite « Ajouter à l'écran d'accueil » telle que Chrome la remplit avec le manifeste (nom, icône,
  // adresse), puis l'icône sur l'écran d'accueil (icône « maskable » découpée en cercle, comme Android).
  const maskable = donnees('public/icones/icone-maskable-512.png', 'image/png');
  const telephone = await navigateur.newContext(PIXEL_6);
  const accueil = await telephone.newPage();
  await accueil.goto(base + '/');
  await accueil.waitForTimeout(800);
  const fondAccueil = (await accueil.screenshot({ type: 'jpeg', quality: 80 })).toString('base64');
  const invite = await telephone.newPage();
  await invite.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><body style="margin:0;height:915px;background:url(data:image/jpeg;base64,${fondAccueil}) 0 0/412px 915px;${police}">
    <div style="position:absolute;inset:0;background:rgb(0 0 0 / .45)"></div>
    <div style="position:absolute;left:24px;right:24px;top:300px;background:#fff;border-radius:28px;padding:24px;color:#1c1a17">
      <div style="font-size:22px;margin-bottom:18px">Installer l’application</div>
      <div style="display:flex;align-items:center;gap:14px"><img src="${maskable}" width="48" height="48" style="border-radius:50%"><div><div style="font-size:16px">Kekeli</div><div style="font-size:13px;color:#6b655c">127.0.0.1:8123</div></div></div>
      <div style="display:flex;justify-content:flex-end;gap:24px;margin-top:28px;font-size:15px;font-weight:600;color:#9a5b06"><span>Annuler</span><span>Installer</span></div>
    </div>
    <div style="position:absolute;left:0;right:0;bottom:8px;text-align:center;color:#fff;font-size:12px">Simulation de l’invite de Chrome (nom et icône lus dans le manifeste)</div></body>`);
  await invite.screenshot({ path: `${dossier}/installation-invite-simulation.jpg`, type: 'jpeg', quality: 80 });

  const ecran = await telephone.newPage();
  const appli = (nom, couleur) => `<div style="display:flex;flex-direction:column;align-items:center;gap:6px"><div style="width:58px;height:58px;border-radius:50%;background:${couleur}"></div><span style="font-size:12px">${nom}</span></div>`;
  await ecran.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><body style="margin:0;height:915px;background:linear-gradient(160deg,#3b4a5a,#1f2a33);${police};color:#fff">
    <div style="padding:14px 20px;font-size:13px;display:flex;justify-content:space-between"><span>9:41</span><span>100 %</span></div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:28px 8px;padding:420px 18px 0">
      ${appli('Téléphone', '#4f7a5a')}${appli('Messages', '#5a6f8f')}${appli('Appareil photo', '#6b655c')}
      <div style="display:flex;flex-direction:column;align-items:center;gap:6px"><img src="${maskable}" width="58" height="58" style="border-radius:50%"><span style="font-size:12px">Kekeli</span></div>
    </div>
    <div style="position:absolute;left:0;right:0;bottom:8px;text-align:center;font-size:12px;opacity:.8">Simulation de l’écran d’accueil Android (icône « maskable » du manifeste)</div></body>`);
  await ecran.screenshot({ path: `${dossier}/installation-ecran-d-accueil-simulation.jpg`, type: 'jpeg', quality: 80 });

  // 4. Simulation : le site installé s'ouvre sans barre d'adresse (barre d'état du téléphone aux couleurs du thème),
  // à côté du même site dans Chrome avec sa barre d'adresse.
  for (const theme of ['light', 'dark']) {
    const contexte = await navigateur.newContext({ ...PIXEL_6, viewport: { width: 412, height: 835 }, colorScheme: theme });
    const page = await contexte.newPage();
    await page.goto(base + '/');
    await page.waitForTimeout(900);
    const site = (await page.screenshot({ type: 'jpeg', quality: 80 })).toString('base64');
    const fond = theme === 'dark' ? '#171513' : '#FAF8F4';
    const encre = theme === 'dark' ? '#f3efe8' : '#1c1a17';
    const cadre = await navigateur.newPage({ viewport: { width: 880, height: 960 }, deviceScaleFactor: 1 });
    const telephoneDessine = (barre, titre) => `<figure style="margin:0;display:flex;flex-direction:column;align-items:center;gap:10px"><div style="width:412px;border-radius:28px;overflow:hidden;border:6px solid #2a2622;background:${fond}">
      <div style="height:28px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:${fond};color:${encre};font-size:12px">9:41<span>100 %</span></div>
      ${barre}<img src="data:image/jpeg;base64,${site}" style="display:block;width:412px;height:${barre ? 779 : 835}px;object-fit:cover;object-position:top"></div><figcaption style="font-size:13px;color:#3f3b35">${titre}</figcaption></figure>`;
    await cadre.setContent(`<body style="margin:0;padding:20px;background:#fff;${police};display:flex;gap:16px">
      ${telephoneDessine(`<div style="height:56px;background:${theme === 'dark' ? '#2a2622' : '#ececec'};display:flex;align-items:center;padding:0 12px"><div style="flex:1;height:36px;border-radius:18px;background:${theme === 'dark' ? '#3f3b35' : '#fff'};color:${encre};font-size:14px;display:flex;align-items:center;padding:0 14px">127.0.0.1:8123</div></div>`, 'Dans Chrome : barre d’adresse')}
      ${telephoneDessine('', 'Installé : plein écran, sans barre d’adresse (simulation)')}
    </body>`);
    await cadre.screenshot({ path: `${dossier}/site-installe-${theme === 'dark' ? 'sombre' : 'clair'}-simulation.jpg`, type: 'jpeg', quality: 80 });
    await cadre.close();
    await contexte.close();
  }

  // 5. La vraie page « hors connexion » : le service worker s'installe, puis le réseau est coupé et une page est ouverte.
  for (const theme of ['light', 'dark']) {
    const contexte = await navigateur.newContext({ ...PIXEL_6, colorScheme: theme });
    const page = await contexte.newPage();
    await page.goto(base + '/');
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.waitForTimeout(500);
    await contexte.setOffline(true);
    await page.goto(base + '/projets').catch(() => {});
    await page.waitForSelector('[data-offline-page]', { timeout: 10000 });
    await page.screenshot({ path: `${dossier}/hors-connexion-${theme === 'dark' ? 'sombre' : 'clair'}.jpg`, type: 'jpeg', quality: 80 });
    await contexte.close();
  }

  await navigateur.close();
  console.log(readdirSync(dossier).length, 'fichiers dans', dossier);
})();
