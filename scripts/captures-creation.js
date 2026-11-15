// Captures « Créer comme Lovable et Claude » (téléphone et ordinateur, clair et sombre). États inventés de
// scripts/preparer-captures-creation.php (QUESTIONS, ETAPES, DEPASSE), et un vrai site créé par la mesure
// (scripts/mesurer-creation.js) : SITE (identifiant du projet) et COMPTE_SITE (son compte).
//   QUESTIONS=… ETAPES=… DEPASSE=… SITE=… COMPTE_SITE=… node scripts/captures-creation.js http://127.0.0.1:8130 docs/captures/creation
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, dossier] = process.argv.slice(2);
const seulement = process.env.SEULEMENT ? process.env.SEULEMENT.split(',') : null;
mkdirSync(dossier, { recursive: true });

const APPAREILS = {
    telephone: { ...devices['Pixel 7'], locale: 'fr-FR' },
    ordinateur: { viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2, locale: 'fr-FR' },
};
const pause = (p, ms) => p.waitForTimeout(ms);

async function connecter(page, compte) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
}

/** La discussion d'un projet, sans l'ouverture automatique de l'aperçu (déjà vue). */
const discussion = (variable, fin = true) => async (p) => {
    await p.addInitScript(() => {
        const lire = Storage.prototype.getItem;
        Storage.prototype.getItem = function (cle) { return String(cle).startsWith('kekeli-apercu-') ? '1' : lire.call(this, cle); };
    });
    await p.goto(`${base}/projets/${process.env[variable]}?vue=discussion`);
    await pause(p, 800);
    if (fin) await p.evaluate(() => document.querySelectorAll('[data-run-status]').forEach((e) => e.scrollIntoView({ block: 'end' })));
    await pause(p, 300);
};

const ETATS = [
    // 1. Les questions avant une création, avec leurs choix ; un choix déjà touché.
    ['1-questions', 'afi-creation@exemple.tg', async (p) => {
        await discussion('QUESTIONS')(p);
        await p.locator('[data-question-choice]').nth(1).click();
        await pause(p, 300);
    }],
    // 2. Les étapes visibles pendant le travail.
    ['2-etapes', 'afi-creation@exemple.tg', discussion('ETAPES')],
    // 3. Le compte rendu final d'un vrai site, avec les boutons de suite.
    ['3-compte-rendu', process.env.COMPTE_SITE, discussion('SITE')],
    // 4. L'aperçu du vrai site.
    ['4-apercu', process.env.COMPTE_SITE, async (p) => {
        await p.goto(`${base}/projets/${process.env.SITE}?vue=apercu`);
        await pause(p, 6000);
    }],
    // 5. Le bonus de bienvenue dans le menu du compte.
    ['5-bonus', 'afi-creation@exemple.tg', async (p, appareil) => {
        await p.goto(`${base}/projets`);
        await pause(p, 500);
        await p.locator(appareil === 'telephone' ? '[data-mobile-account]:visible' : '[data-nav-account]:visible').first().click();
        await p.locator('[data-account-bonus]:visible').first().waitFor({ state: 'visible' });
        await pause(p, 300);
    }],
    // 6. Une création qui a dépassé l'énergie : finie proprement, avec son compte rendu.
    ['6-depassement', 'kossi-depasse@exemple.tg', discussion('DEPASSE')],
];

(async () => {
    const navigateur = await chromium.launch();
    for (const [etat, compte, faire] of ETATS) {
        if (seulement && !seulement.some((s) => etat.includes(s))) continue;
        for (const [appareil, reglages] of Object.entries(APPAREILS)) {
            for (const theme of ['clair', 'sombre']) {
                const contexte = await navigateur.newContext({ ...reglages, colorScheme: theme === 'sombre' ? 'dark' : 'light' });
                const page = await contexte.newPage();
                await connecter(page, compte);
                await faire(page, appareil);
                await page.screenshot({ path: `${dossier}/${etat}-${appareil}-${theme}.png` });
                await contexte.close();
                console.log(`${etat} ${appareil} ${theme}`);
            }
        }
    }
    await navigateur.close();
})();
