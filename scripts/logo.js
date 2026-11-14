// Tire du logo de Kekeli toutes ses déclinaisons. Deux fichiers sources seulement :
//   public/logo.svg      le symbole (barre, flamme, jambe), couleurs adaptées au mode clair et au mode sombre ;
//   public/logo-nom.svg  le nom « kekeli » en tracés (police Lexend 500, licence OFL, voir MENTIONS.md).
// Fabrique :
//   docs/logo/*.svg      symbole et symbole + nom, en clair, en sombre, en noir, en blanc, et l'icône d'application ;
//   public/logo.png      symbole pour les e-mails et les reçus (192 × 192) ;
//   public/logo-email.png et logo-email-sombre.png  symbole + nom pour l'en-tête des e-mails (deux fois la taille affichée) ;
//   public/icones/*.png  icônes du site installable (192, 512, « maskable » 512, Apple 180) ;
//   public/favicon.ico   16, 32 et 48 pixels (anciens navigateurs).
//   node scripts/logo.js
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const racine = path.join(path.dirname(new URL(import.meta.url).pathname), '..');
const lire = (fichier) => readFileSync(path.join(racine, fichier), 'utf8');

export const COULEURS = {
  encre: '#1C1A17',
  papier: '#FAF8F4',
  nuit: '#171513',
  ambre: '#E3A008',
};

// Les trois formes du symbole, lues dans public/logo.svg (le seul endroit où elles sont dessinées).
const symbole = lire('public/logo.svg');
const forme = (id) => {
  const balise = symbole.match(new RegExp(`<(rect|path)[^>]*id="${id}"[^>]*/>`))?.[0];
  if (!balise) throw new Error(`Forme « ${id} » absente de public/logo.svg`);
  return balise.replace(/\s(id|class)="[^"]*"/g, '');
};
const [vx, vy, vl, vh] = symbole.match(/viewBox="([^"]+)"/)[1].split(/\s+/).map(Number);

const nom = lire('public/logo-nom.svg');
const nomTrace = nom.match(/\sd="([^"]+)"/)[1];
const [, , nomL] = nom.match(/viewBox="([^"]+)"/)[1].split(/\s+/).map(Number);
const nomBase = Number(nom.match(/data-ligne-de-base="([^"]+)"/)[1]);

const formes = (couleurForme, couleurFlamme) =>
  [forme('barre'), forme('flamme'), forme('jambe')]
    .map((b, i) => b.replace(/^<(rect|path)/, `<$1 fill="${i === 1 ? couleurFlamme : couleurForme}"`))
    .join('');

// Symbole + nom : hauteur du « k » à 55 % du symbole, ligne de base à 87 % (proportions de l'image validée).
const ECHELLE_NOM = 0.65;
const ESPACE = 20;
const largeurHorizontale = Math.ceil(vl + ESPACE + nomL * ECHELLE_NOM + 2);
const nomPlace = (couleur) =>
  `<path fill="${couleur}" transform="translate(${vx + vl + ESPACE} ${(vy + 0.87 * vh - nomBase * ECHELLE_NOM).toFixed(2)}) scale(${ECHELLE_NOM})" d="${nomTrace}"/>`;

const svg = (viewBox, contenu, fond) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${viewBox}">${fond ? `<rect x="${viewBox.split(' ')[0]}" y="${viewBox.split(' ')[1]}" width="100%" height="100%" fill="${fond}"/>` : ''}${contenu}</svg>\n`;

const vueSymbole = `${vx} ${vy} ${vl} ${vh}`;
const vueHorizontale = `${vx} ${vy} ${largeurHorizontale} ${vh}`;

// Icône carrée : le symbole centré sur le papier, dans 64 % du carré (88 % de sa boîte dans la zone sûre « maskable »).
const icone = (part, fond = COULEURS.papier) => {
  const cote = vh / part;
  const x = vx + vl / 2 - cote / 2;
  const y = vy + vh / 2 - cote / 2;
  return svg(`${x.toFixed(2)} ${y.toFixed(2)} ${cote.toFixed(2)} ${cote.toFixed(2)}`, formes(COULEURS.encre, COULEURS.ambre), fond);
};

const declinaisons = {
  'kekeli-symbole.svg': svg(vueSymbole, formes(COULEURS.encre, COULEURS.ambre)),
  'kekeli-symbole-sombre.svg': svg(vueSymbole, formes(COULEURS.papier, COULEURS.ambre)),
  'kekeli-symbole-noir.svg': svg(vueSymbole, formes(COULEURS.encre, COULEURS.encre)),
  'kekeli-symbole-blanc.svg': svg(vueSymbole, formes(COULEURS.papier, COULEURS.papier)),
  'kekeli-horizontal.svg': svg(vueHorizontale, formes(COULEURS.encre, COULEURS.ambre) + nomPlace(COULEURS.encre)),
  'kekeli-horizontal-sombre.svg': svg(vueHorizontale, formes(COULEURS.papier, COULEURS.ambre) + nomPlace(COULEURS.papier)),
  'kekeli-horizontal-noir.svg': svg(vueHorizontale, formes(COULEURS.encre, COULEURS.encre) + nomPlace(COULEURS.encre)),
  'kekeli-horizontal-blanc.svg': svg(vueHorizontale, formes(COULEURS.papier, COULEURS.papier) + nomPlace(COULEURS.papier)),
  'kekeli-icone-application.svg': icone(0.64),
};

(async () => {
  mkdirSync(path.join(racine, 'docs/logo'), { recursive: true });
  mkdirSync(path.join(racine, 'public/icones'), { recursive: true });
  for (const [fichier, contenu] of Object.entries(declinaisons)) {
    writeFileSync(path.join(racine, 'docs/logo', fichier), contenu);
  }

  const navigateur = await chromium.launch();
  const page = await navigateur.newPage({ deviceScaleFactor: 1 });
  const png = async (contenu, largeur, hauteur, sortie) => {
    await page.setViewportSize({ width: largeur, height: hauteur });
    await page.setContent(`<html><body style="margin:0;background:transparent">${contenu.replace('<svg', `<svg width="${largeur}" height="${hauteur}" style="display:block"`)}</body></html>`);
    writeFileSync(path.join(racine, sortie), await page.screenshot({ omitBackground: true, clip: { x: 0, y: 0, width: largeur, height: hauteur } }));
  };

  const symboleClair = declinaisons['kekeli-symbole.svg'];
  await png(symboleClair, 192, Math.round((192 * vh) / vl), 'public/logo.png');
  await png(declinaisons['kekeli-horizontal.svg'], Math.round((64 * largeurHorizontale) / vh), 64, 'public/logo-email.png');
  await png(declinaisons['kekeli-horizontal-sombre.svg'], Math.round((64 * largeurHorizontale) / vh), 64, 'public/logo-email-sombre.png');
  await png(icone(0.64), 192, 192, 'public/icones/icone-192.png');
  await png(icone(0.64), 512, 512, 'public/icones/icone-512.png');
  await png(icone(0.5), 512, 512, 'public/icones/icone-maskable-512.png');
  await png(icone(0.64), 180, 180, 'public/icones/apple-touch-icon.png');

  // Favicon : le symbole seul, centré dans un carré transparent, aux trois tailles des anciens navigateurs.
  const favicon = svg(icone(0.92).match(/viewBox="([^"]+)"/)[1], formes(COULEURS.encre, COULEURS.ambre));
  const tailles = [16, 32, 48];
  for (const t of tailles) {
    await png(favicon, t, t, `storage/app/favicon-${t}.png`);
  }
  await navigateur.close();
  execFileSync('convert', [...tailles.map((t) => path.join(racine, `storage/app/favicon-${t}.png`)), path.join(racine, 'public/favicon.ico')]);
  execFileSync('rm', tailles.map((t) => path.join(racine, `storage/app/favicon-${t}.png`)));
  console.log('Déclinaisons du logo créées : docs/logo/, public/logo.png, public/logo-email.png, public/icones/, public/favicon.ico');
})();
