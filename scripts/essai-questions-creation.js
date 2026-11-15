// Essai réel : une demande de création, les questions de Kekeli, les réponses touchées, puis la création qui part
// (défaut du 15 novembre 2026 : après les réponses, Kekeli disait « Je ne peux pas générer l'image dans cette
// discussion »). Téléphone simulé (Pixel 7, Chrome, en français), compte et messages inventés, aucune clé lue ici.
//
//   node scripts/essai-questions-creation.js http://127.0.0.1:8140 docs/captures/fonctions/questions-affiche "Fais-moi une affiche…"
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, dossier, demande = 'Fais-moi une affiche sur les moyens de gagner de l’argent sur Internet', compte = 'afi-fonctions@exemple.tg'] = process.argv.slice(2);

(async () => {
    mkdirSync(dossier, { recursive: true });
    const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
    const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })).newPage();
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
    await page.goto(`${base}/projets`);
    await page.locator('form textarea').first().fill(demande);
    const depart = Date.now();
    await page.locator('[data-prompt-send]').first().tap();
    await page.waitForURL(/projets\//, { timeout: 30000 });
    const resultat = { demande };
    const questions = await page.waitForSelector('[data-question]', { timeout: 120000 }).then(() => true).catch(() => false);
    resultat.questions = questions ? await page.locator('[data-question] p').allTextContents() : [];
    await page.screenshot({ path: `${dossier}/1-questions.png` });
    if (questions) {
        // Le premier choix de chaque question, comme un client pressé, puis « Envoyer mes réponses ».
        const n = await page.locator('[data-question]').count();
        for (let i = 0; i < n; i++) await page.locator('[data-question]').nth(i).locator('[data-question-choice]').first().tap();
        await page.locator('[data-send-answers]').tap();
    }
    // La création démarre : une carte de fichier (image, document), un onglet de fichier ou un travail sur un site.
    const fini = await page.waitForSelector('[data-file-card], [data-tab="fichier"], [data-tab="apercu"], [data-step]', { timeout: 300000 }).then(() => true).catch(() => false);
    await page.waitForTimeout(2000);
    resultat.creation_lancee = fini;
    resultat.texte_final = (await page.locator('[data-report-message]').last().textContent().catch(() => '')).trim().slice(0, 400);
    resultat.refus = /ne peux pas|impossible de (créer|générer)/i.test(resultat.texte_final);
    resultat.secondes = Math.round((Date.now() - depart) / 1000);
    await page.screenshot({ path: `${dossier}/2-resultat.png`, fullPage: false });
    console.log(JSON.stringify(resultat, null, 1));
    writeFileSync(`${dossier}/resultat.json`, JSON.stringify(resultat, null, 2));
    await navigateur.close();
})();
