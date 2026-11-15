// Captures « FreeLLMAPI à la place de Coarena » (docs/captures/freellmapi.md) : Administration → Modèles sur un
// téléphone simulé et sur un ordinateur, avec le site, le Runner et FreeLLMAPI lancés en local (compte inventé) :
//   node scripts/captures-freellmapi.js http://127.0.0.1:8131 docs/captures/freellmapi
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, dossier] = process.argv.slice(2);
mkdirSync(dossier, { recursive: true });

const APPAREILS = {
    telephone: { ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' },
    ordinateur: { viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2, locale: 'fr-FR', colorScheme: 'light' },
};

(async () => {
    const navigateur = await chromium.launch();
    for (const [appareil, reglages] of Object.entries(APPAREILS)) {
        const contexte = await navigateur.newContext(reglages);
        const page = await contexte.newPage();
        await page.goto(`${base}/login`);
        await page.locator('[data-password-login]').click();
        await page.waitForSelector('[data-password-submit]');
        await page.locator('input[type=email]').fill('admin-freellmapi@exemple.tg');
        await page.locator('input[type=password]').fill('motdepasse-solide-2026');
        await page.locator('[data-password-submit]').click();
        await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
        await page.goto(`${base}/administration/modeles`);
        await page.locator('[data-freellmapi]').waitFor();
        await page.waitForTimeout(600);
        await page.screenshot({ path: `${dossier}/administration-${appareil}.png`, fullPage: true });
        await contexte.close();
    }
    await navigateur.close();
})();
