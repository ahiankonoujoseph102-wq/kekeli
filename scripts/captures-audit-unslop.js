// Captures de l'audit « unslop » (docs/audit-unslop.md) : chaque écran de Kekeli, sur un téléphone (Pixel 6, Chrome)
// et sur un ordinateur, en clair et en sombre. Comptes et données inventés (scripts/preparer-captures-design.php puis
// scripts/preparer-captures-modeles.php). Aucune requête à un modèle. Images en JPEG pour garder le dépôt léger.
//
//   node scripts/captures-audit-unslop.js http://127.0.0.1:8123 docs/captures/audit-unslop/ecrans
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
const ESI = 'esi-design@exemple.tg';
const ADMIN = 'admin-design@exemple.tg';
const ouvrirRecherche = async (p) => { await p.goto(base + '/projets'); await p.evaluate(() => window.Flux?.modal?.('search')?.show?.()); await pause(p, 600); };

// Chaque écran : [nom, compte (null : sans compte), action, page entière sur téléphone].
const PAGES = [
  ['01-accueil', null, async (p) => { await p.goto(base + '/'); }, true],
  ['02-connexion', null, async (p) => { await p.goto(base + '/login'); }],
  ['03-connexion-mot-de-passe', null, async (p) => { await p.goto(base + '/login'); await p.locator('[data-password-login]').click(); await p.waitForSelector('[data-password-submit]'); }],
  ['04-conditions', null, async (p) => { await p.goto(base + '/conditions-d-utilisation'); }],
  ['05-page-introuvable', null, async (p) => { await p.goto(base + '/une-page-qui-n-existe-pas'); }],
  ['06-hors-connexion', null, async (p) => { await p.goto(base + '/hors-connexion'); }],
  ['07-nouvelle-discussion', AFI, async (p) => { await p.goto(base + '/projets'); }],
  ['08-menu', AFI, async (p, ordi) => { await p.goto(base + '/projets'); if (! ordi) { await p.locator('[data-nav-toggle]').click(); await pause(p, 400); } }],
  ['09-menu-du-compte', AFI, async (p, ordi) => { await p.goto(base + '/projets'); await p.locator(ordi ? '[data-nav-account]' : '[data-mobile-account]').click(); await pause(p, 400); }],
  ['10-recherche', AFI, async (p) => { await ouvrirRecherche(p); }],
  ['11-discussion', AFI, async (p) => { await p.goto(projet('Ouvrir une boutique de pagnes')); await p.locator('[data-thinking] summary').first().click().catch(() => {}); }, true],
  ['12-reflexion-en-cours', AFI, async (p) => { await p.goto(projet('Prix du ciment à Lomé')); await pause(p, 1600); }],
  ['13-reponse-avec-calcul', AFI, async (p) => { await p.goto(projet('Prix des pagnes avec remise')); }, true],
  ['14-site-discussion', AFI, async (p) => { await p.goto(projet('Restaurant Le Palmier')); await p.locator('[data-narration] summary').first().click().catch(() => {}); }, true],
  ['15-site-apercu', AFI, async (p, ordi) => { await p.goto(projet('Restaurant Le Palmier', ordi ? '' : '?vue=apercu')); await pause(p, 900); }],
  ['16-site-code', AFI, async (p) => { await p.goto(projet('Restaurant Le Palmier', '?vue=code')); await pause(p, 600); }],
  ['17-site-menu-projet', AFI, async (p) => { await p.goto(projet('Restaurant Le Palmier')); await p.locator('[data-project-menu], [aria-label="Plus d’actions"], [aria-label="Plus d\'actions"]').first().click().catch(() => {}); await pause(p, 400); }],
  ['18-application', AFI, async (p) => { await p.goto(projet('Rendez-vous du salon')); }, true],
  ['19-agent', AFI, async (p) => { await p.goto(projet('Assistante de la boutique')); }, true],
  ['20-agent-tester', AFI, async (p, ordi) => { await p.goto(projet('Assistante de la boutique', ordi ? '' : '?vue=apercu')); await pause(p, 700); }],
  ['21-automatisation', AFI, async (p) => { await p.goto(projet('Rapport des ventes du matin')); }, true],
  ['22-document', AFI, async (p, ordi) => { await p.goto(projet('Devis du salon de coiffure', ordi ? '' : '?vue=apercu')); await pause(p, 900); }],
  ['23-document-discussion', AFI, async (p) => { await p.goto(projet('Devis du salon de coiffure', '?vue=discussion')); await pause(p, 600); }, true],
  ['24-presentation', AFI, async (p, ordi) => { await p.goto(projet('Présentation de la boutique', ordi ? '' : '?vue=apercu')); await pause(p, 900); }],
  ['25-affiche', AFI, async (p, ordi) => { await p.goto(projet('Affiche de la soirée', ordi ? '' : '?vue=apercu')); await pause(p, 900); }],
  ['26-video', AFI, async (p, ordi) => { await p.goto(projet('Publicité du restaurant', ordi ? '' : '?vue=apercu')); await pause(p, 900); }],
  ['27-projets', AFI, async (p) => { await p.goto(base + '/mes-projets'); }, true],
  ['28-bibliotheque', AFI, async (p) => { await p.goto(base + '/bibliotheque'); await pause(p, 900); }, true],
  ['29-projets-vide', ESI, async (p) => { await p.goto(base + '/mes-projets'); }],
  ['30-bibliotheque-vide', ESI, async (p) => { await p.goto(base + '/bibliotheque'); }],
  ['31-memoire-vide', ESI, async (p) => { await p.goto(base + '/settings/memoire'); }],
  ['32-reglages-profil', AFI, async (p) => { await p.goto(base + '/settings/profile'); }],
  ['33-reglages-apparence', AFI, async (p) => { await p.goto(base + '/settings/appearance'); }],
  ['34-reglages-formule-et-recus', AFI, async (p) => { await p.goto(base + '/settings/formule'); }, true],
  ['35-reglages-memoire', AFI, async (p) => { await p.goto(base + '/settings/memoire'); }, true],
  ['36-formules', AFI, async (p) => { await p.goto(base + '/formules'); }, true],
  ['37-paiement', AFI, async (p) => { await p.goto(base + '/formules/expert/payer'); }, true],
  ['38-credits-epuises', KOFI, async (p) => { await p.goto(projet('Boutique de Kofi')); await pause(p, 600); }, true],
  ['39-admin-fournisseurs', ADMIN, async (p) => { await p.goto(base + '/administration/fournisseurs'); }, true],
  ['40-admin-modeles', ADMIN, async (p) => { await p.goto(base + '/administration/modeles'); }],
  ['41-admin-projets', ADMIN, async (p) => { await p.goto(base + '/administration/projets'); }],
  ['42-admin-consommation', ADMIN, async (p) => { await p.goto(base + '/administration/consommation'); }, true],
  ['43-admin-paiements', ADMIN, async (p) => { await p.goto(base + '/administration/paiements'); }, true],
  ['44-admin-emails', ADMIN, async (p) => { await p.goto(base + '/administration/emails'); }],
  ['45-admin-reglages', ADMIN, async (p) => { await p.goto(base + '/administration/reglages'); }],
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
