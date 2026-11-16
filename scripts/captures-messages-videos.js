// Captures du chantier « messages et vidéos » (16 novembre 2026) : « Copier » et « Modifier » sous les messages du client,
// « Régénérer » avec « ‹ 1 / 2 › », sur téléphone et sur ordinateur, en clair et en sombre. Essai réel de « Modifier » :
// la discussion repart du message modifié, la nouvelle réponse s'écrit mot par mot.
//   node scripts/captures-messages-videos.js http://127.0.0.1:8131 adresse@exemple.tg
import { createRequire } from 'node:module';
const { chromium, devices } = createRequire(import.meta.url)('playwright');

const [base = 'http://127.0.0.1:8131', compte = 'afi-reponses@exemple.tg'] = process.argv.slice(2);
const dossier = 'docs/captures/messages-videos';

async function connecter(page) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 });
}

(async () => {
    const navigateur = await chromium.launch();
    const resultats = {};
    // 1. Essai réel de « Modifier » sur téléphone (clair).
    const tel = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' });
    const page = await tel.newPage();
    await connecter(page);
    await page.goto(`${base}/projets`);
    await page.waitForLoadState('networkidle');
    await page.locator('form textarea').first().fill('Combien coûte un nom de domaine en .tg ?');
    await page.locator('[data-prompt-send]').first().tap();
    await page.waitForURL(/projets\//, { timeout: 30000 });
    await page.waitForSelector('[data-edit-message]', { timeout: 120000 });
    await page.waitForTimeout(800);
    const adresse = page.url();
    await page.screenshot({ path: `${dossier}/messages-telephone-clair.png` });
    await page.locator('[data-edit-message]').first().tap();
    await page.waitForSelector('[data-editing-message]');
    const texteDansLaZone = await page.locator('textarea[data-message-input]').inputValue();
    await page.locator('textarea[data-message-input]').fill('Combien coûte un nom de domaine en .com ?');
    await page.screenshot({ path: `${dossier}/modifier-telephone-clair.png` });
    const debut = Date.now();
    await page.locator('[data-prompt-send]').last().tap();
    let premierMot = null;
    for (let i = 0; i < 240; i++) {
        await page.waitForTimeout(250);
        const ecrit = await page.evaluate(() => [...document.querySelectorAll('[data-reponse-en-cours]')].map((e) => e.innerText).join(''));
        if (ecrit.trim() && premierMot === null) premierMot = Date.now() - debut;
        if (premierMot !== null && !(await page.locator('[data-reponse-en-cours]').count())) break;
    }
    await page.waitForTimeout(1000);
    const messages = await page.locator('[data-user-message] .whitespace-pre-line').allInnerTexts();
    resultats.modifier = { texte_dans_la_zone: texteDansLaZone, messages_du_client: messages, premier_mot_ms: premierMot, duree_ms: Date.now() - debut };
    await page.screenshot({ path: `${dossier}/apres-modification-telephone-clair.png` });

    // 2. Mêmes écrans en sombre (téléphone) et sur ordinateur (clair et sombre).
    for (const [nom, options] of [['telephone-sombre', { ...devices['Pixel 7'], colorScheme: 'dark' }], ['ordinateur-clair', { viewport: { width: 1366, height: 860 }, colorScheme: 'light' }], ['ordinateur-sombre', { viewport: { width: 1366, height: 860 }, colorScheme: 'dark' }]]) {
        const c = await navigateur.newContext({ ...options, locale: 'fr-FR' });
        const p = await c.newPage();
        await connecter(p);
        await p.goto(adresse);
        await p.waitForTimeout(1500);
        await p.screenshot({ path: `${dossier}/messages-${nom}.png` });
        await c.close();
    }
    console.log(JSON.stringify(resultats, null, 1));
    await navigateur.close();
})();
