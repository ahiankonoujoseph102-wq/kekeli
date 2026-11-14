// Captures « Organisation comme les grands outils et design de chaque écran » : chaque écran, sur un téléphone
// (Pixel 6, Chrome) et sur un ordinateur, en clair et en sombre, pour « avant » (la branche principale) et « après ».
// Comptes et données inventés (scripts/preparer-captures-design.php). Images en JPEG pour garder le dépôt léger.
//
//   node scripts/captures-organisation-design.js apres http://127.0.0.1:8123 docs/captures/organisation-design
//   node scripts/captures-organisation-design.js avant http://127.0.0.1:8124 docs/captures/organisation-design
// Variable SEULEMENT=projets,bibliotheque… pour ne refaire que quelques pages. Les pages qui n'existaient pas avant
// (« Projets », « Bibliothèque ») ne sont capturées que pour « après ».
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const [version, base, racine] = process.argv.slice(2);
const dossier = `${racine}/${version}`;
mkdirSync(dossier, { recursive: true });
const seulement = (process.env.SEULEMENT || '').split(',').filter(Boolean);
const siteDir = version === 'avant' ? '/home/user/kekeli-avant' : process.cwd();
const sqlite = '/home/user/kekeli-captures.sqlite';

const PIXEL_6 = {
  viewport: { width: 412, height: 915 },
  deviceScaleFactor: 2,
  isMobile: true,
  hasTouch: true,
  userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',
};
const ORDINATEUR = { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 };

const idProjet = (nom) => execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare("select id from projects where name = ? order by created_at desc limit 1"); $q->execute([$argv[2]]); echo $q->fetchColumn();', sqlite, nom]).toString().trim();

const pause = (p, ms = 500) => p.waitForTimeout(ms);

async function connecter(p, email) {
  await p.goto(`${base}/logout`).catch(() => {});
  await p.context().clearCookies();
  await p.goto(`${base}/login`);
  await p.locator('[data-password-login]').click();
  await p.waitForSelector('[data-password-submit]');
  await p.locator('input[type=email]').fill(email);
  await p.locator('input[type=password]').fill('motdepasse-solide-2026');
  await p.locator('[data-password-submit]').click();
  await p.waitForURL((url) => ! url.pathname.startsWith('/login'), { timeout: 15000 });
}

// Chaque page : [nom, compte, action, nouvelle (seulement « après »)]. L'action ouvre la page et prépare ce qu'on montre.
const AFI = 'afi-design@exemple.tg';
const PAGES = [
  ['01-menu', AFI, async (p, ordi) => { await p.goto(base + '/projets'); if (! ordi) { await p.locator('[data-nav-toggle]').click(); await pause(p, 400); } }],
  ['02-nouvelle-discussion', AFI, async (p) => { await p.goto(base + '/projets'); }],
  ['03-projets', AFI, async (p) => { await p.goto(base + '/mes-projets'); }, true],
  ['04-projets-filtre-sites', AFI, async (p) => { await p.goto(base + '/mes-projets?type=site'); }, true],
  ['05-bibliotheque', AFI, async (p) => { await p.goto(base + '/bibliotheque'); await pause(p, 900); }, true],
  ['06-bibliotheque-filtre-images', AFI, async (p) => { await p.goto(base + '/bibliotheque?type=image'); await pause(p, 900); }, true],
  ['07-recherche', AFI, async (p) => { await p.goto(base + '/projets'); await p.evaluate(() => window.Flux?.modal?.('search')?.show?.()); await pause(p, 700); }],
  ['08-recherche-resultats', AFI, async (p) => { await p.goto(base + '/projets'); await p.evaluate(() => window.Flux?.modal?.('search')?.show?.()); await pause(p, 400); await p.locator('[data-search-input] input, input[data-search-input]').first().fill('pagnes'); await pause(p, 1200); }],
  ['09-discussion', AFI, async (p) => { await p.goto(`${base}/projets/${idProjet('Ouvrir une boutique de pagnes')}`); await p.locator('[data-thinking] summary').first().click().catch(() => {}); }],
  ['10-site', AFI, async (p) => { await p.goto(`${base}/projets/${idProjet('Restaurant Le Palmier')}`); await p.locator('[data-narration] summary').first().click().catch(() => {}); }],
  ['11-document', AFI, async (p, ordi) => { await p.goto(`${base}/projets/${idProjet('Devis du salon de coiffure')}${ordi ? '' : '?vue=apercu'}`); await pause(p, 900); }],
  ['12-video', AFI, async (p, ordi) => { await p.goto(`${base}/projets/${idProjet('Publicité du restaurant')}${ordi ? '' : '?vue=apercu'}`); await pause(p, 900); }],
  ['13-menu-du-compte', AFI, async (p, ordi) => { await p.goto(base + '/projets'); await p.locator(ordi ? '[data-nav-account]' : '[data-mobile-account]').click(); await pause(p, 400); }],
  ['14-reglages-formule-et-recus', AFI, async (p) => { await p.goto(base + '/settings/formule'); }],
  ['15-formules', AFI, async (p) => { await p.goto(base + '/formules'); }],
  ['16-paiement', AFI, async (p) => { await p.goto(base + '/formules/expert/payer'); }],
  ['17-projets-vide', 'esi-design@exemple.tg', async (p) => { await p.goto(base + '/mes-projets'); }, true],
  ['18-bibliotheque-vide', 'esi-design@exemple.tg', async (p) => { await p.goto(base + '/bibliotheque'); }, true],
  ['19-admin-paiements', 'admin-design@exemple.tg', async (p) => { await p.goto(base + '/administration/paiements'); }],
];

(async () => {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];
  for (const [appareil, options] of [['telephone', PIXEL_6], ['ordinateur', ORDINATEUR]]) {
    for (const theme of ['light', 'dark']) {
      const contexte = await navigateur.newContext({ ...options, locale: 'fr-FR', colorScheme: theme, reducedMotion: 'reduce' });
      const p = await contexte.newPage();
      p.on('pageerror', (e) => erreurs.push(`${appareil} ${theme} : ${e.message}`));
      let compte;
      for (const [nom, email, action, nouvelle] of PAGES) {
        if (seulement.length && ! seulement.some((s) => nom.includes(s))) continue;
        if (nouvelle && version === 'avant') continue;
        if (email === 'maintenance') {
          execFileSync('php', ['artisan', 'down', '--retry=60'], { cwd: siteDir, stdio: 'ignore' });
        } else if (email !== compte) {
          if (email) await connecter(p, email);
          else { await p.goto(`${base}/`); await contexte.clearCookies(); }
          compte = email;
        }
        try {
          await action(p, appareil === 'ordinateur');
          await p.waitForLoadState('networkidle').catch(() => {});
          await pause(p, 500);
          const fichier = `${dossier}/${appareil}-${theme === 'dark' ? 'sombre' : 'clair'}-${nom}.jpg`;
          await p.screenshot({ path: fichier, type: 'jpeg', quality: 72, fullPage: appareil === 'telephone' && /projets|bibliotheque|formules|admin|reglages/.test(nom) });
        } catch (e) {
          erreurs.push(`${nom} ${appareil} ${theme} : ${e.message.split('\n')[0]}`);
        } finally {
          if (email === 'maintenance') { execFileSync('php', ['artisan', 'up'], { cwd: siteDir, stdio: 'ignore' }); compte = undefined; }
        }
        await p.keyboard.press('Escape').catch(() => {});
      }
      await contexte.close();
    }
  }
  await navigateur.close();
  console.log(readdirSync(dossier).length, 'captures dans', dossier);
  if (erreurs.length) console.log('Erreurs :\n' + erreurs.join('\n'));
})();
