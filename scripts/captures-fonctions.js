// Captures du chantier « Kekeli fait tout correctement » (15 novembre 2026) : la discussion de l'affiche créée après
// les questions, et l'envoi instantané (message et « Kekeli réfléchit… » dès le toucher), sur téléphone et sur
// ordinateur, en clair et en sombre. Compte et données inventés.
//
//   node scripts/captures-fonctions.js http://127.0.0.1:8140 docs/captures/fonctions/ecrans <projet de l'affiche>
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, dossier, projet] = process.argv.slice(2);

const APPAREILS = {
    telephone: { ...devices['Pixel 7'] },
    ordinateur: { viewport: { width: 1366, height: 860 }, deviceScaleFactor: 1 },
};

(async () => {
    mkdirSync(dossier, { recursive: true });
    const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
    for (const [appareil, reglages] of Object.entries(APPAREILS)) {
        for (const theme of ['light', 'dark']) {
            const contexte = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme });
            const page = await contexte.newPage();
            await page.goto(`${base}/login`);
            await page.locator('[data-password-login]').click();
            await page.locator('input[type=email]').fill('afi-fonctions@exemple.tg');
            await page.locator('input[type=password]').fill('motdepasse-solide-2026');
            await page.locator('[data-password-submit]').click();
            await page.waitForURL((url) => !url.pathname.startsWith('/login'));
            const nom = `${appareil}-${theme === 'light' ? 'clair' : 'sombre'}`;

            // 1. La discussion de l'affiche (questions, réponses, carte du fichier) et l'affiche elle-même.
            await page.goto(`${base}/projets/${projet}`);
            await page.waitForTimeout(2500);
            await page.screenshot({ path: `${dossier}/affiche-discussion-${nom}.png` });
            if (appareil === 'telephone') {
                await page.locator('[data-tab="apercu"]').first().tap().catch(() => {});
                await page.waitForTimeout(2500);
                await page.screenshot({ path: `${dossier}/affiche-image-${nom}.png` });
            }

            // 2. L'envoi instantané : capture prise 150 ms après le toucher sur « Envoyer ».
            await page.goto(`${base}/projets`);
            await page.waitForLoadState('networkidle');
            await page.locator('form textarea').first().fill('Comment fixer le prix de mes pagnes ?');
            await page.locator('[data-prompt-send]').first().click();
            await page.waitForTimeout(150);
            await page.screenshot({ path: `${dossier}/envoi-accueil-${nom}.png` });
            await page.waitForURL(/projets\//, { timeout: 20000 });
            await page.waitForSelector('[data-report-message]', { timeout: 120000 });
            await page.waitForTimeout(1500);
            await page.locator('textarea[data-message-input]').fill('Et pour les tenues sur mesure ?');
            await page.locator('[data-prompt-send]').first().click();
            await page.waitForTimeout(150);
            await page.screenshot({ path: `${dossier}/envoi-discussion-${nom}.png` });
            await page.waitForSelector('[data-reponse-en-cours]:not(.hidden)', { timeout: 60000 }).catch(() => {});
            await page.waitForTimeout(700);
            await page.screenshot({ path: `${dossier}/reponse-en-cours-${nom}.png` });
            await page.waitForTimeout(8000);
            await contexte.close();
        }
    }
    await navigateur.close();
})();
