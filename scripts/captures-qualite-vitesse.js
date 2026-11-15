// Captures du chantier « comprendre, répondre et réagir comme Claude et ChatGPT » (15 novembre 2026), sur un
// téléphone simulé (Pixel 7) et un ordinateur, en clair et en sombre, avec un compte et des données inventés :
// la question exacte du porteur du projet reçoit une réponse (et le bouton de proposition), le squelette de
// « Nouvelle discussion » s'affiche pendant que la page arrive. Aucune clé n'est lue ni écrite ici.
//
//   COMPTE=… MOT_DE_PASSE=… node scripts/captures-qualite-vitesse.js http://127.0.0.1:8131
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const base = process.argv[2];
const dossier = 'docs/captures/qualite-vitesse';
mkdirSync(dossier, { recursive: true });

const QUESTION = "C'est possible de créer un agent IA aujourd'hui avec l'IA ? Et comment gagner de l'argent avec l'IA";

async function connecter(page) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(process.env.COMPTE);
    await page.locator('input[type=password]').fill(process.env.MOT_DE_PASSE);
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 30000 });
}

const navigateur = await chromium.launch();

// 1. La question du porteur du projet, posée sur un téléphone (clair) : la réponse, puis le bouton de proposition.
const telephone = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', deviceScaleFactor: 2 });
const page = await telephone.newPage();
await connecter(page);
await page.goto(`${base}/projets`);
await page.locator('form textarea').first().fill(QUESTION);
await page.locator('[data-prompt-send]').first().tap();
await page.waitForURL((url) => /\/projets\/[0-9A-Z]{26}/i.test(url.pathname), { timeout: 30000 });
await page.waitForSelector('[data-suggestion], [data-report-message]', { timeout: 120000 });
await page.waitForTimeout(2500);
const adresse = page.url();
await page.screenshot({ path: `${dossier}/telephone-clair-question-haut.png` });
await page.evaluate(() => document.querySelectorAll('*').forEach((e) => { if (e.scrollHeight > e.clientHeight + 20) e.scrollTop = e.scrollHeight; }));
await page.waitForTimeout(800);
await page.screenshot({ path: `${dossier}/telephone-clair-question-fin.png` });

// 2. Le squelette de « Nouvelle discussion » (la page suivante est volontairement retardée de 3 secondes).
await page.route((url) => url.pathname === '/projets', async (route) => {
    await new Promise((fin) => setTimeout(fin, 3000));
    await route.continue();
});
await page.locator('[data-nav-toggle]').first().tap();
await page.waitForTimeout(500);
await page.locator('[data-nav-new]').first().tap();
await page.waitForTimeout(250);
await page.screenshot({ path: `${dossier}/telephone-clair-squelette-nouvelle-discussion.png` });
await page.unroute((url) => url.pathname === '/projets');
await page.waitForTimeout(3500);

// 3. La même discussion en sombre, sur le téléphone.
const sombre = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', deviceScaleFactor: 2, colorScheme: 'dark', storageState: await telephone.storageState() });
const pageSombre = await sombre.newPage();
await pageSombre.goto(adresse);
await pageSombre.waitForTimeout(1500);
await pageSombre.evaluate(() => document.querySelectorAll('*').forEach((e) => { if (e.scrollHeight > e.clientHeight + 20) e.scrollTop = e.scrollHeight; }));
await pageSombre.waitForTimeout(800);
await pageSombre.screenshot({ path: `${dossier}/telephone-sombre-question-fin.png` });

// 4. Sur un ordinateur, en clair et en sombre.
for (const theme of ['light', 'dark']) {
    const ordinateur = await navigateur.newContext({ viewport: { width: 1366, height: 860 }, locale: 'fr-FR', deviceScaleFactor: 2, colorScheme: theme, storageState: await telephone.storageState() });
    const p = await ordinateur.newPage();
    await p.goto(adresse);
    await p.waitForTimeout(1500);
    await p.evaluate(() => document.querySelectorAll('*').forEach((e) => { if (e.scrollHeight > e.clientHeight + 20) e.scrollTop = e.scrollHeight; }));
    await p.waitForTimeout(800);
    await p.screenshot({ path: `${dossier}/ordinateur-${theme === 'light' ? 'clair' : 'sombre'}-question.png` });
    // Le squelette d'une page qui tarde (« Projets »).
    if (theme === 'light') {
        await p.route((url) => url.pathname === '/mes-projets', async (route) => {
            await new Promise((fin) => setTimeout(fin, 3000));
            await route.continue();
        });
        await p.locator('[data-nav-projects]').first().click();
        await p.waitForTimeout(450);
        await p.screenshot({ path: `${dossier}/ordinateur-clair-squelette-page.png` });
        await p.waitForTimeout(3500);
    }
    await ordinateur.close();
}

console.log('Captures prêtes :', adresse);
await navigateur.close();
