// Captures « Recherches et réponses aussi bonnes que Claude et ChatGPT » (téléphone et ordinateur, clair et sombre),
// discussions de scripts/preparer-captures-recherche-claude.php (réponses réelles du banc d'essai) :
//   JOSEPH_AVANT=… JOSEPH_APRES=… (sorties du script de préparation) node scripts/captures-recherche-claude.js http://127.0.0.1:8128 docs/captures/recherche/claude
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

async function connecter(page) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill('afi-recherche@exemple.tg');
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
}

/** Une discussion, la réponse en haut de l'écran ; « deplier » ouvre « A cherché sur le Web ». */
const discussion = (variable, deplier = false) => async (p) => {
    await p.goto(`${base}/projets/${process.env[variable]}`);
    await p.waitForSelector('[data-question-answer]');
    if (deplier) {
        const ligne = p.locator('[data-web-search] summary, [data-web-search] button').first();
        if (await ligne.count()) await ligne.click();
    }
    await p.waitForTimeout(500);
};

const ETATS = [
    ['1-joseph-avant', discussion('JOSEPH_AVANT')],
    ['2-joseph-apres', discussion('JOSEPH_APRES')],
    ['3-joseph-apres-recherches', discussion('JOSEPH_APRES', true)],
    ['4-noubissie-avant', discussion('NOUBISSIE_AVANT')],
    ['5-noubissie-apres', discussion('NOUBISSIE_APRES')],
    ['6-togocom', discussion('TOGOCOM')],
    ['7-fondateur', discussion('FONDATEUR')],
    ['8-agents-ia', discussion('AGENTS')],
];

(async () => {
    const navigateur = await chromium.launch();
    for (const [etat, faire] of ETATS) {
        if (seulement && !seulement.some((s) => etat.includes(s))) continue;
        for (const [appareil, reglages] of Object.entries(APPAREILS)) {
            for (const theme of ['clair', 'sombre']) {
                const contexte = await navigateur.newContext({ ...reglages, colorScheme: theme === 'sombre' ? 'dark' : 'light' });
                const page = await contexte.newPage();
                await connecter(page);
                await faire(page);
                await page.screenshot({ path: `${dossier}/${etat}-${appareil}-${theme}.png`, fullPage: appareil === 'telephone' });
                await contexte.close();
                console.log(`${etat} ${appareil} ${theme}`);
            }
        }
    }
    await navigateur.close();
})();
