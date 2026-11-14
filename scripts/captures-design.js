// Captures « Nouveau design de Kekeli, partout » : chaque page, sur un téléphone (Pixel 6, Chrome) et sur un
// ordinateur, en clair et en sombre, pour « avant » et « après ». Comptes et données inventés
// (scripts/preparer-captures-design.php). Images en JPEG pour garder le dépôt léger.
//
//   node scripts/captures-design.js apres http://127.0.0.1:8123 docs/captures/nouveau-design
//   node scripts/captures-design.js avant http://127.0.0.1:8124 docs/captures/nouveau-design
// Variable SEULEMENT=accueil,connexion… pour ne refaire que quelques pages.
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

// Chaque page : [nom, compte (null : sans compte), action]. L'action ouvre la page et prépare ce qu'on montre.
const PAGES = [
  ['01-accueil', null, async (p) => { await p.goto(base + '/'); }],
  ['02-connexion', null, async (p) => { await p.goto(base + '/login'); }],
  ['03-connexion-code', null, async (p) => {
    await p.goto(base + '/login');
    await p.locator('[data-login-email]').fill(`nouveau-${Date.now()}@exemple.tg`);
    await p.locator('[data-continue-email]').click();
    await p.waitForSelector('[data-login-code]');
  }],
  ['04-connexion-mot-de-passe', null, async (p) => { await p.goto(base + '/login'); await p.locator('[data-password-login]').click(); await p.waitForSelector('[data-password-submit]'); }],
  ['05-conditions', null, async (p) => { await p.goto(base + '/conditions-d-utilisation'); }],
  ['06-confidentialite', null, async (p) => { await p.goto(base + '/confidentialite'); }],
  ['07-page-introuvable', null, async (p) => { await p.goto(base + '/une-page-qui-n-existe-pas'); }],
  ['08-nouveau', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/projets'); }],
  ['09-discussion', 'afi-design@exemple.tg', async (p) => { await p.goto(`${base}/projets/${idProjet('Ouvrir une boutique de pagnes')}`); await p.locator('[data-thinking] summary').first().click().catch(() => {}); }],
  ['10-reflexion-en-cours', 'afi-design@exemple.tg', async (p) => { await p.goto(`${base}/projets/${idProjet('Prix du ciment à Lomé')}`); await pause(p, 1600); }],
  ['11-site-termine', 'afi-design@exemple.tg', async (p) => { await p.goto(`${base}/projets/${idProjet('Restaurant Le Palmier')}`); await p.locator('[data-narration] summary').first().click().catch(() => {}); }],
  ['12-document', 'afi-design@exemple.tg', async (p, ordi) => { await p.goto(`${base}/projets/${idProjet('Devis du salon de coiffure')}${ordi ? '' : '?vue=apercu'}`); await pause(p, 900); }],
  ['13-presentation', 'afi-design@exemple.tg', async (p, ordi) => { await p.goto(`${base}/projets/${idProjet('Présentation de la boutique')}${ordi ? '' : '?vue=apercu'}`); await pause(p, 900); }],
  ['14-affiche', 'afi-design@exemple.tg', async (p) => { await p.goto(`${base}/projets/${idProjet('Affiche de la soirée')}`); await pause(p, 900); }],
  ['15-video', 'afi-design@exemple.tg', async (p, ordi) => { await p.goto(`${base}/projets/${idProjet('Publicité du restaurant')}${ordi ? '' : '?vue=apercu'}`); await pause(p, 900); }],
  ['16-menu-telephone', 'afi-design@exemple.tg', async (p, ordi) => { await p.goto(base + '/projets'); if (! ordi) { await p.locator('[data-nav-toggle]').click(); await pause(p, 400); } }],
  ['17-recherche', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/projets'); await p.evaluate(() => window.Flux?.modal?.('search')?.show?.()); await p.locator('[data-nav-search]').first().click({ force: true }).catch(() => {}); await pause(p, 300); await p.keyboard.type('pagnes'); await pause(p, 900); }],
  ['18-menu-du-compte', 'afi-design@exemple.tg', async (p, ordi) => { await p.goto(base + '/projets'); await p.locator(ordi ? '[data-nav-account]' : '[data-mobile-account]').click(); await pause(p, 400); }],
  ['19-reglages-profil', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/settings/profile'); }],
  ['20-reglages-apparence', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/settings/appearance'); }],
  ['21-reglages-formule-et-recus', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/settings/formule'); }],
  ['22-reglages-memoire', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/settings/memoire'); }],
  ['23-formules', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/formules'); }],
  ['24-paiement', 'afi-design@exemple.tg', async (p) => { await p.goto(base + '/formules/expert/payer'); }],
  ['25-energie-epuisee', 'kofi-design@exemple.tg', async (p) => { await p.goto(`${base}/projets/${idProjet('Boutique de Kofi')}`); }],
  ['26-etats-vides', 'esi-design@exemple.tg', async (p) => { await p.goto(base + '/settings/memoire'); }],
  ['27-admin-tableau-de-bord', 'admin-design@exemple.tg', async (p) => { await p.goto(base + '/administration/fournisseurs'); }],
  ['28-admin-paiements', 'admin-design@exemple.tg', async (p) => { await p.goto(base + '/administration/paiements'); }],
  ['29-admin-emails', 'admin-design@exemple.tg', async (p) => { await p.goto(base + '/administration/emails'); }],
  ['30-admin-reglages', 'admin-design@exemple.tg', async (p) => { await p.goto(base + '/administration/reglages'); }],
  ['31-maintenance', 'maintenance', async (p) => { await p.goto(base + '/'); }],
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
      for (const [nom, email, action] of PAGES) {
        if (seulement.length && ! seulement.some((s) => nom.includes(s))) continue;
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
          await p.screenshot({ path: fichier, type: 'jpeg', quality: 72, fullPage: appareil === 'telephone' && /conditions|confidentialite|formules|admin|reglages/.test(nom) });
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
