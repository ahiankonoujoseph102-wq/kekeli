// Captures des e-mails (rendus par scripts/preparer-captures-emails.php) tels qu'ils s'affichent sur un téléphone,
// en clair et en sombre.
//   node scripts/captures-emails-design.js <dossier des fichiers HTML> <dossier des captures> [préfixe]
import { createRequire } from 'node:module';
import { mkdirSync, readdirSync } from 'node:fs';
import path from 'node:path';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const [source, cible, prefixe = ''] = process.argv.slice(2);
mkdirSync(cible, { recursive: true });

(async () => {
  const navigateur = await chromium.launch();
  for (const theme of ['light', 'dark']) {
    const page = await navigateur.newPage({ viewport: { width: 412, height: 915 }, deviceScaleFactor: 2, colorScheme: theme, locale: 'fr-FR' });
    for (const fichier of readdirSync(source).filter((f) => f.endsWith('.html')).sort()) {
      await page.goto('file://' + path.resolve(source, fichier));
      await page.waitForTimeout(300);
      await page.screenshot({ path: `${cible}/${prefixe}email-${fichier.replace('.html', '')}${theme === 'dark' ? '-sombre' : ''}.jpg`, type: 'jpeg', quality: 75, fullPage: true });
    }
    await page.close();
  }
  await navigateur.close();
})();
