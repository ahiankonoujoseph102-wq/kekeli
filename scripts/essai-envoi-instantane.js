// Essai réel de l'envoi instantané (chantier « Kekeli fait tout correctement », 15 novembre 2026), sur un téléphone
// simulé (Pixel 7, Chrome, en français) : au toucher sur « Envoyer », le message doit s'afficher en moins de 100 ms,
// « Kekeli réfléchit… » tout de suite, puis la réponse s'écrire mot par mot (et non d'un coup à la fin), sur
// l'accueil puis dans la discussion. Compte et messages inventés ; aucune clé n'est lue ni écrite ici.
//
//   node scripts/essai-envoi-instantane.js http://127.0.0.1:8140 docs/captures/fonctions/envoi.json [compte]
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

const { chromium, devices } = createRequire(import.meta.url)('playwright');

const [base, sortie, compte = 'afi-fonctions@exemple.tg'] = process.argv.slice(2);

async function connecter(page) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
}

// Observe la page depuis l'intérieur (toutes les 10 ms) : le message affiché, la réflexion visible, le texte de la
// réponse à chaque instant (pour savoir s'il arrive mot par mot), et si la bulle disparaît avant la fin.
async function observer(page, texte) {
    await page.evaluate((t) => {
        window.__essai = { depart: performance.now(), affiche: null, reflexion: null, premierMot: null, fin: null, longueurs: [], disparu: false };
        const e = window.__essai;
        const visible = (el) => el && el.offsetParent !== null && getComputedStyle(el).visibility !== 'hidden';
        const tic = () => {
            const ms = Math.round(performance.now() - e.depart);
            const bulles = [...document.querySelectorAll('[data-message-envoye], [data-user-message]')].filter((b) => visible(b) && b.textContent.includes(t));
            if (bulles.length && e.affiche === null) e.affiche = ms;
            if (e.affiche !== null && !bulles.length && e.fin === null) e.disparu = true;
            const reflexion = [...document.querySelectorAll('[data-reflexion-provisoire], [data-reflexion-ligne], [data-run-working]')].some(visible);
            if (reflexion && e.reflexion === null) e.reflexion = ms;
            const zones = [...document.querySelectorAll('[data-reponse-en-cours]')].filter(visible);
            const longueur = zones.reduce((n, z) => n + z.textContent.trim().length, 0);
            if (longueur > 0 && e.premierMot === null) e.premierMot = ms;
            if (longueur > 0 && longueur !== e.longueurs.at(-1)) e.longueurs.push(longueur);
            if (e.premierMot !== null && !document.querySelector('[data-reponse-en-cours]') && document.querySelector('[data-report-message]') && e.fin === null) e.fin = ms;
            if (e.fin === null && ms < 120000) setTimeout(tic, 10);
        };
        tic();
    }, texte);
}

async function envoyer(page, selecteurChamp, texte) {
    const champ = page.locator(selecteurChamp).first();
    await champ.fill(texte);
    await observer(page, texte);
    await page.locator('[data-prompt-send]').first().tap();
    await page.waitForFunction(() => window.__essai && window.__essai.fin !== null, null, { timeout: 120000, polling: 200 }).catch(() => {});
    return page.evaluate(() => ({ ...window.__essai, morceaux: window.__essai.longueurs.length, longueurs: undefined, depart: undefined }));
}

(async () => {
    const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
    const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
    const page = await contexte.newPage();
    await connecter(page);
    const resultats = [];

    // 1. Accueil (« Nouvelle discussion ») : le message part, la discussion s'ouvre.
    await page.goto(`${base}/projets`);
    await page.waitForLoadState('networkidle');
    const accueil = 'Quels sont 3 conseils pour vendre plus sur WhatsApp ?';
    await page.locator('form textarea').first().fill(accueil);
    const depart = Date.now();
    await page.locator('[data-prompt-send]').first().tap();
    await page.waitForFunction((t) => [...document.querySelectorAll('[data-message-envoye]')].some((b) => b.offsetParent && b.textContent.includes(t)), accueil, { polling: 10, timeout: 10000 });
    const afficheAccueil = Date.now() - depart;
    const reflexionAccueil = await page.locator('[data-reflexion-provisoire]').first().isVisible().catch(() => false);
    await page.waitForURL(/projets\//, { timeout: 20000 });
    await page.waitForSelector('[data-report-message]', { timeout: 120000 });
    resultats.push({ lieu: 'accueil', affiche_ms: afficheAccueil, reflexion_visible: reflexionAccueil });
    await page.waitForTimeout(1500);
    mkdirSync(dirname(sortie), { recursive: true });

    // 2. Dans la discussion : deux messages de suite.
    for (const texte of ['Et pour Facebook ?', 'Donne-moi un exemple de message pour annoncer une promotion.']) {
        const r = await envoyer(page, 'textarea[data-message-input]', texte);
        resultats.push({ lieu: 'discussion', texte, ...r });
        if (texte.startsWith('Et')) await page.screenshot({ path: sortie.replace(/\.json$/, '-discussion.png') });
        await page.waitForTimeout(1500);
    }
    console.log(JSON.stringify(resultats, null, 1));
    writeFileSync(sortie, JSON.stringify({ date: new Date().toISOString(), resultats }, null, 2));
    await navigateur.close();
})();
