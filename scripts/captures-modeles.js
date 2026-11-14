// Captures « Nouveaux modèles : test, choix automatique, Standard et Pro » (docs/captures/modeles.md) : sur un
// téléphone (Pixel 6, Chrome) et sur un ordinateur, en clair et en sombre. Données inventées
// (scripts/preparer-captures-design.php puis scripts/preparer-captures-modeles.php) ; les deux réponses viennent d'un
// vrai essai (docs/captures/modeles/reponses-standard-pro.json).
//
//   node scripts/captures-modeles.js apres http://127.0.0.1:8123 docs/captures/modeles
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

// Chaque page : [nom, compte, action]. L'action ouvre la page et prépare ce qu'on montre.
const AFI = 'afi-design@exemple.tg';
const PAGES = [
  ['01-administration-modeles', 'admin-design@exemple.tg', async (p) => { await p.goto(base + '/administration/modeles'); }],
  ['02-credits-gratuits-utilises', 'kofi-design@exemple.tg', async (p) => { await p.goto(`${base}/projets/${idProjet('Boutique de Kofi')}`); await pause(p, 600); }],
  ['03-reponse-modeles-economiques', 'kofi-design@exemple.tg', async (p) => { await p.goto(`${base}/projets/${idProjet('Prix des pagnes après remise')}`); await pause(p, 600); }],
  ['04-reponse-modeles-de-qualite', AFI, async (p) => { await p.goto(`${base}/projets/${idProjet('Prix des pagnes avec remise')}`); await pause(p, 600); }],
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
          await p.screenshot({ path: fichier, type: 'jpeg', quality: 72, fullPage: appareil === 'telephone' && /administration|reponse|credits/.test(nom) });
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
