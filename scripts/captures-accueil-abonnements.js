// Captures « Accueil et abonnements comme les grands outils » (docs/captures/accueil-abonnements.md) : l'accueil et
// ses suggestions, la page des formules, la carte des crédits utilisés, Réglages → Formule (énergie reportée) et le
// paiement, sur un téléphone (Pixel 6, Chrome) et un ordinateur, en clair et en sombre. Comptes et données inventés
// (scripts/preparer-captures-design.php puis scripts/preparer-captures-abonnements.php). Aucune requête à un modèle.
//
//   node scripts/captures-accueil-abonnements.js http://127.0.0.1:8123 docs/captures/accueil-abonnements/apres
// Variable SEULEMENT=accueil,discussion… pour ne refaire que quelques écrans ; APPAREILS=telephone pour un seul format.
import { createRequire } from 'node:module';
import { mkdirSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const [base, dossier] = process.argv.slice(2);
mkdirSync(dossier, { recursive: true });
const seulement = (process.env.SEULEMENT || '').split(',').filter(Boolean);
const appareils = (process.env.APPAREILS || 'telephone,ordinateur').split(',');
const sqlite = process.env.KEKELI_CAPTURES_SQLITE || '/home/user/kekeli-captures.sqlite';

const PIXEL_6 = {
  viewport: { width: 412, height: 915 },
  deviceScaleFactor: 2,
  isMobile: true,
  hasTouch: true,
  userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',
};
const ORDINATEUR = { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 };

const idProjet = (nom) => execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare("select id from projects where name = ? order by created_at desc limit 1"); $q->execute([$argv[2]]); echo $q->fetchColumn();', sqlite, nom]).toString().trim();
const projet = (nom, vue = '') => `${base}/projets/${idProjet(nom)}${vue}`;

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

const AFI = 'afi-design@exemple.tg';
const KOFI = 'kofi-design@exemple.tg';

// Chaque écran : [nom, compte (null : sans compte), action, page entière sur téléphone].
const PAGES = [
  ['01-accueil', null, async (p) => { await p.goto(base + '/'); }],
  ['02-accueil-suggestion-touchee', null, async (p) => { await p.goto(base + '/'); const idee = p.locator('[data-idea="0"]'); if (await idee.count()) { await idee.click(); } else { await p.locator('[data-site-prompt-ideas] button').first().click(); } await pause(p, 300); }],
  ['03-nouvelle-discussion', AFI, async (p) => { await p.goto(base + '/projets'); }],
  ['04-formules', AFI, async (p) => { await p.goto(base + '/formules'); }, true],
  ['05-credits-epuises', KOFI, async (p) => { await p.goto(projet('Boutique de Kofi')); await pause(p, 600); }, true],
  ['06-reglages-formule', AFI, async (p) => { await p.goto(base + '/settings/formule'); }, true],
  ['07-paiement', AFI, async (p) => { await p.goto(base + '/formules/expert/payer'); }, true],
];

(async () => {
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const erreurs = [];
  for (const [appareil, options] of [['telephone', PIXEL_6], ['ordinateur', ORDINATEUR]]) {
    if (! appareils.includes(appareil)) continue;
    for (const theme of ['light', 'dark']) {
      const contexte = await navigateur.newContext({ ...options, locale: 'fr-FR', colorScheme: theme, reducedMotion: 'reduce' });
      const p = await contexte.newPage();
      let ecran = '';
      p.on('pageerror', (e) => erreurs.push(`${ecran} ${appareil} ${theme} : ${e.message}`));
      let compte;
      for (const [nom, email, action, entiere] of PAGES) {
        if (seulement.length && ! seulement.some((s) => nom.includes(s))) continue;
        ecran = nom;
        if (email !== compte) {
          if (email) await connecter(p, email);
          else { await p.goto(`${base}/`); await contexte.clearCookies(); }
          compte = email;
        }
        try {
          await action(p, appareil === 'ordinateur');
          await p.waitForLoadState('networkidle').catch(() => {});
          await pause(p, 400);
          const fichier = `${dossier}/${appareil}-${theme === 'dark' ? 'sombre' : 'clair'}-${nom}.jpg`;
          await p.screenshot({ path: fichier, type: 'jpeg', quality: 62, fullPage: appareil === 'telephone' && Boolean(entiere) });
        } catch (e) {
          erreurs.push(`${nom} ${appareil} ${theme} : ${e.message.split('\n')[0]}`);
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
