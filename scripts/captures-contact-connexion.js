// Captures « Contact sobre, connexion comme Claude et finitions » : téléphone (Pixel 6) et ordinateur, clair et
// sombre, comptes inventés (scripts/preparer-captures-design.php). Site lancé avec un identifiant Google simulé.
//   SEULEMENT=accueil,erreur node scripts/captures-contact-connexion.js http://127.0.0.1:8123 docs/captures/contact-connexion
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const [base, dossier] = process.argv.slice(2);
mkdirSync(dossier, { recursive: true });
const seulement = (process.env.SEULEMENT || '').split(',').filter(Boolean);
const PIXEL_6 = { viewport: { width: 412, height: 915 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36' };
const ORDINATEUR = { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 };

async function connecter(p) {
  await p.context().clearCookies();
  await p.goto(`${base}/login`);
  await p.locator('[data-password-login]').click();
  await p.locator('input[type=email]').fill('afi-design@exemple.tg');
  await p.locator('input[type=password]').fill('motdepasse-solide-2026');
  await p.locator('[data-password-submit]').click();
  await p.waitForURL((u) => ! u.pathname.startsWith('/login'));
}

const PAGES = [
  ['accueil', false, async (p) => { await p.goto(base + '/'); }],
  ['hors-connexion', false, async (p) => { await p.goto(base + '/hors-connexion'); }],
  ['erreur-404', false, async (p) => { await p.goto(base + '/une-page-qui-n-existe-pas'); }],
  ['maintenance', 'maintenance', async (p) => { await p.goto(base + '/'); }],
  ['connexion', false, async (p) => { await p.goto(base + '/login'); }],
  ['creer-un-compte', false, async (p) => { await p.goto(base + '/register'); }],
  ['nous-contacter', true, async (p, ordi) => {
    await p.goto(base + '/projets');
    await p.locator(ordi ? '[data-nav-account]' : '[data-mobile-account]').click();
    await p.waitForTimeout(300);
    await p.locator('[data-account-contact]:visible').click();
    await p.waitForSelector('dialog[data-modal="contact"][open]'); await p.waitForTimeout(300);
  }],
];

(async () => {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  for (const [appareil, options] of [['telephone', PIXEL_6], ['ordinateur', ORDINATEUR]]) {
    for (const theme of ['light', 'dark']) {
      const contexte = await navigateur.newContext({ ...options, locale: 'fr-FR', colorScheme: theme, reducedMotion: 'reduce' });
      const p = await contexte.newPage();
      for (const [nom, compte, action] of PAGES) {
        if (seulement.length && ! seulement.some((s) => nom.includes(s))) continue;
        if (compte === 'maintenance') execFileSync('php', ['artisan', 'down', '--retry=60'], { stdio: 'ignore' });
        try {
          if (compte === true) await connecter(p); else await contexte.clearCookies();
          await action(p, appareil === 'ordinateur');
          await p.waitForLoadState('networkidle').catch(() => {});
          await p.waitForTimeout(500);
          await p.screenshot({ path: `${dossier}/${appareil}-${theme === 'dark' ? 'sombre' : 'clair'}-${nom}.jpg`, type: 'jpeg', quality: 72 });
        } catch (e) {
          console.log(nom, appareil, theme, e.message.split('\n')[0]);
        } finally {
          if (compte === 'maintenance') execFileSync('php', ['artisan', 'up'], { stdio: 'ignore' });
        }
      }
      await contexte.close();
    }
  }
  await navigateur.close();
})();
