// Tire du seul fichier du logo (public/logo.svg) les images qui ne peuvent pas être en SVG :
// public/logo.png (e-mails, 96 × 96) et public/favicon.ico (32 × 32, anciens navigateurs).
//   node scripts/logo.js
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const __dirname = path.dirname(new URL(import.meta.url).pathname);

(async () => {
  const racine = path.join(__dirname, '..');
  const svg = readFileSync(path.join(racine, 'public/logo.svg'), 'utf8');
  const navigateur = await chromium.launch();
  const page = await navigateur.newPage({ viewport: { width: 96, height: 96 }, deviceScaleFactor: 1 });
  await page.setContent(`<html><body style="margin:0;background:transparent">${svg.replace('<svg', '<svg width="96" height="96"')}</body></html>`);
  const png = await page.screenshot({ omitBackground: true, clip: { x: 0, y: 0, width: 96, height: 96 } });
  writeFileSync(path.join(racine, 'public/logo.png'), png);
  await navigateur.close();
  execFileSync('convert', [path.join(racine, 'public/logo.png'), '-resize', '32x32', path.join(racine, 'public/favicon.ico')]);
  console.log('public/logo.png et public/favicon.ico créés à partir de public/logo.svg');
})();
