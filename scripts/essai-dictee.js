// Essai de la dictée sans mots répétés (chantier « vitesse », partie 4), sur un téléphone Android simulé et sur un
// ordinateur : une fausse reconnaissance vocale rejoue la suite de résultats de chaque navigateur, puis on lit la zone.
//   node scripts/essai-dictee.js http://127.0.0.1:8125 adresse@exemple.tg
import { createRequire } from 'node:module';
const { chromium, devices } = createRequire(import.meta.url)('playwright');

const [base, compte] = process.argv.slice(2);
const SUITES = {
    android: [[['bonjour', false]], [['bonjour je', false]], [['bonjour je veux', true]], [['bonjour je veux', true], ['bonjour je veux un site', false]], [['bonjour je veux', true], ['bonjour je veux un site pour mon restaurant', true]]],
    ordinateur: [[['Bonjour', false]], [['Bonjour je veux', true]], [['Bonjour je veux', true], [' un site', false]], [['Bonjour je veux', true], [' un site pour mon restaurant', true]]],
};

(async () => {
    const navigateur = await chromium.launch();
    let erreurs = 0;
    for (const [nom, suite] of Object.entries(SUITES)) {
        const contexte = await navigateur.newContext(nom === 'android' ? { ...devices['Pixel 7'], locale: 'fr-FR' } : { viewport: { width: 1280, height: 800 }, locale: 'fr-FR' });
        await contexte.addInitScript((suite) => {
            window.SpeechRecognition = window.webkitSpeechRecognition = class {
                start() {
                    let i = 0;
                    const suivant = () => {
                        if (i >= suite.length) return this.onend?.();
                        const results = suite[i++].map(([transcript, isFinal]) => Object.assign([{ transcript }], { isFinal }));
                        this.onresult?.({ results, resultIndex: 0 });
                        setTimeout(suivant, 60);
                    };
                    setTimeout(suivant, 60);
                }
                stop() {}
            };
        }, suite);
        const page = await contexte.newPage();
        await page.goto(`${base}/login`);
        await page.locator('[data-password-login]').click();
        await page.waitForSelector('[data-password-submit]');
        await page.locator('input[type=email]').fill(compte);
        await page.locator('input[type=password]').fill('motdepasse-solide-2026');
        await page.locator('[data-password-submit]').click();
        await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
        await page.goto(`${base}/projets`);
        await page.locator('[data-prompt-mic]').first().click();
        await page.waitForTimeout(800);
        const texte = await page.locator('[data-prompt-mic]').evaluate((b) => b.closest('form').querySelector('textarea').value);
        const attendu = 'bonjour je veux un site pour mon restaurant';
        const bon = texte.toLowerCase() === attendu;
        if (!bon) erreurs++;
        console.log(JSON.stringify({ navigateur: nom, texte, bon }));
        await contexte.close();
    }
    await navigateur.close();
    process.exit(erreurs ? 1 : 0);
})();
