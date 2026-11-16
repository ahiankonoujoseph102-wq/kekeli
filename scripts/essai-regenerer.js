// Essai réel de « Régénérer » (16 novembre 2026) : site local, Runner local, vrai modèle gratuit. Une question, puis
// « Régénérer » : la nouvelle réponse doit s'écrire mot par mot, remplacer l'ancienne, avec « ‹ 1 / 2 › ».
//   node scripts/essai-regenerer.js http://127.0.0.1:8131 adresse@exemple.tg
import { createRequire } from 'node:module';
const { chromium, devices } = createRequire(import.meta.url)('playwright');

const [base = 'http://127.0.0.1:8131', compte = 'afi-reponses@exemple.tg'] = process.argv.slice(2);
const dossier = 'docs/captures/messages-videos';

(async () => {
    const navigateur = await chromium.launch();
    const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' });
    const page = await contexte.newPage();
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 });

    // Une nouvelle discussion depuis la grande question.
    await page.goto(`${base}/projets`);
    await page.waitForLoadState('networkidle');
    await page.locator('form textarea').first().fill('Donne-moi une idée de slogan court pour ma boutique de pagnes à Lomé.');
    await page.locator('[data-prompt-send]').first().tap();
    await page.waitForURL(/projets\//, { timeout: 30000 });
    await page.waitForSelector('[data-regenerate]', { timeout: 120000 });
    const premiere = (await page.locator('[data-question-answer]').last().innerText()).trim();

    // « Régénérer » : on suit le texte qui s'écrit.
    const debut = Date.now();
    await page.locator('[data-regenerate]').click();
    const tailles = [];
    let premierMot = null;
    for (let i = 0; i < 240; i++) {
        await page.waitForTimeout(250);
        const encours = await page.evaluate(() => [...document.querySelectorAll('[data-reponse-en-cours]')].map((e) => e.innerText).join(''));
        if (encours.trim() && premierMot === null) premierMot = Date.now() - debut;
        tailles.push(encours.length);
        if (await page.locator('[data-answer-variants]').count()) break;
    }
    const duree = Date.now() - debut; // jusqu'à la réponse finale affichée avec « ‹ 1 / 2 › »
    await page.waitForTimeout(800);
    const seconde = (await page.locator('[data-question-answer]').last().innerText()).trim();
    const variantes = await page.locator('[data-answer-variants]').getAttribute('data-answer-variants').catch(() => null);
    await page.screenshot({ path: `${dossier}/regenerer-telephone-clair.png`, fullPage: false });

    // « ‹ » : retour à la réponse précédente.
    await page.locator('[data-previous-answer]').click();
    await page.waitForTimeout(1500);
    const retour = (await page.locator('[data-question-answer]').last().innerText()).trim();

    const motParMot = new Set(tailles.filter((t) => t > 0)).size > 2;
    console.log(JSON.stringify({ premiere: premiere.slice(0, 120), seconde: seconde.slice(0, 120), differente: premiere !== seconde, variantes, premier_mot_ms: premierMot, duree_ms: duree, mot_par_mot: motParMot, retour_a_la_premiere: retour === premiere }, null, 1));
    await navigateur.close();
})();
