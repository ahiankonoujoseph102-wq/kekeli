// Captures « Une formule gratuite généreuse et rapide » (téléphone et ordinateur, clair et sombre), comptes inventés de
// scripts/preparer-captures-formule-gratuite.php :
//   node scripts/captures-formule-gratuite.js http://127.0.0.1:8126 docs/captures/formule-gratuite/apres
//   SEULEMENT=menu,formules node scripts/captures-formule-gratuite.js http://127.0.0.1:8127 docs/captures/formule-gratuite/avant
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

async function ouvrirMenuDuCompte(page, appareil) {
    if (appareil === 'telephone') {
        await page.locator('[data-mobile-account]:visible').first().click();
    } else {
        await page.locator('[data-nav-account]:visible').first().click();
    }
    await page.locator('[data-account-energy]:visible').first().waitFor({ state: 'visible' });
    await pause(page, 300);
}

const ETATS = [
    // 1. Le menu du compte après trois discussions simples : l'énergie ne bouge pas, « Discussions du jour ».
    ['menu', 'kossi-gratuit@exemple.tg', async (p, appareil) => { await p.goto(`${base}/projets`); await pause(p, 500); await ouvrirMenuDuCompte(p, appareil); }],
    // 2. La page des formules.
    ['formules', 'kossi-gratuit@exemple.tg', async (p) => { await p.goto(`${base}/formules`); await pause(p, 500); }, true],
    // 3. Toutes les discussions du jour utilisées : la phrase courte et « Voir les formules ».
    ['limite', 'ama-gratuit@exemple.tg', async (p) => {
        // Identifiant de la discussion « Questions sur mon salon » (variable DISCUSSION_LIMITE).
        await p.goto(`${base}/projets/${process.env.DISCUSSION_LIMITE}`);
        await p.locator('[data-chat-limit]').first().scrollIntoViewIfNeeded();
        await pause(p, 500);
    }],
    // 4. Administration → Réglages : le nombre de discussions simples par formule.
    ['administration', 'admin-gratuit@exemple.tg', async (p) => {
        await p.goto(`${base}/administration/reglages`);
        await p.locator('[data-plan-chats]').first().scrollIntoViewIfNeeded();
        await p.evaluate(() => window.scrollBy(0, -120));
        await pause(p, 400);
    }],
];

(async () => {
    const navigateur = await chromium.launch();
    for (const [etat, compte, faire, pleinePage] of ETATS) {
        if (seulement && !seulement.includes(etat)) continue;
        for (const [appareil, reglages] of Object.entries(APPAREILS)) {
            for (const theme of ['clair', 'sombre']) {
                const contexte = await navigateur.newContext({ ...reglages, colorScheme: theme === 'sombre' ? 'dark' : 'light' });
                const page = await contexte.newPage();
                await connecter(page, compte);
                await faire(page, appareil);
                await page.screenshot({ path: `${dossier}/${etat}-${appareil}-${theme}.png`, fullPage: Boolean(pleinePage) });
                await contexte.close();
                console.log(`${etat} ${appareil} ${theme}`);
            }
        }
    }
    await navigateur.close();
})();
