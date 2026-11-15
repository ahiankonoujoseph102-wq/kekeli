// Mesure du temps de chaque clic courant sur un téléphone simulé (Pixel 7, Chrome), comme on le ressent (chantier
// « clics instantanés », 15 novembre 2026) : depuis le toucher, quand quelque chose change à l'écran (réponse
// visible) et quand la nouvelle page est complète. Compte et données inventés ; aucune clé n'est lue ni écrite ici.
//
//   node scripts/mesurer-clics.js <adresse du site> <sortie.json>
// Variables : SESSION (fichier de session Playwright déjà connecté, gardé hors du dépôt) ou COMPTE et MOT_DE_PASSE
// (compte local inventé), REPETITIONS (3 par défaut).
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');

const [base, sortie] = process.argv.slice(2);
const repetitions = Number(process.env.REPETITIONS || 3);
const secondes = (ms) => Math.round(ms) / 1000;

async function connecter(page) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(process.env.COMPTE);
    await page.locator('input[type=password]').fill(process.env.MOT_DE_PASSE);
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 30000 });
}

/**
 * Installe l'horloge dans la page. « visible » : le premier signe à l'écran que le toucher est pris en compte (la
 * barre de progression de Livewire, le squelette de la page qui arrive, ou la nouvelle page elle-même) ; « complete » :
 * la nouvelle page affichée (événement « livewire:navigated »).
 */
async function armer(page, attendu = null) {
    await page.evaluate((attendu) => {
        window.__clic = { depart: performance.now(), visible: null, complete: null };
        const visibles = (selecteur) => [...document.querySelectorAll(selecteur)].filter((e) => {
            const r = e.getBoundingClientRect();
            return r.width > 0 && r.height > 0 && r.right > 0 && r.left < innerWidth && getComputedStyle(e).visibility !== 'hidden';
        }).length;
        const signe = () => attendu ? visibles(attendu) > 0 : Boolean(document.querySelector('[data-squelette]') || document.documentElement.dataset.navigation || visibles('#nprogress .bar'));
        const deja = signe();
        const regarder = () => {
            if (window.__clic.visible !== null) return;
            if ((signe() && !deja) || window.__clic.complete !== null) window.__clic.visible = performance.now() - window.__clic.depart;
            else requestAnimationFrame(regarder);
        };
        requestAnimationFrame(regarder);
        document.addEventListener('livewire:navigated', () => {
            window.__clic.complete = performance.now() - window.__clic.depart;
            if (window.__clic.visible === null) window.__clic.visible = window.__clic.complete;
        }, { once: true });
    }, attendu);
}

async function ouvrirMenu(page) {
    const menu = page.locator('[data-nav-toggle]').first();
    if (await menu.isVisible()) {
        await menu.tap();
        await page.waitForTimeout(400);
    }
}

/** Une navigation : toucher un lien, attendre la page complète. */
async function naviguer(page, nom, action, attendre) {
    await armer(page);
    await action();
    await page.waitForFunction(() => window.__clic.complete !== null, null, { timeout: 30000 });
    if (attendre) await page.waitForSelector(attendre, { timeout: 30000 });
    const t = await page.evaluate(() => ({ visible: window.__clic.visible, complete: window.__clic.complete }));
    return { action: nom, visible_s: secondes(t.visible ?? t.complete), complete_s: secondes(t.complete) };
}

/** Une fenêtre ou un menu (sans changer de page) : toucher, attendre qu'il soit affiché. */
async function ouvrir(page, nom, action, attendre) {
    await armer(page, attendre);
    const depart = Date.now();
    await action();
    await page.locator(attendre).last().waitFor({ state: 'visible', timeout: 15000 });
    const visible = await page.evaluate(() => window.__clic.visible);
    return { action: nom, visible_s: secondes(visible ?? Date.now() - depart), complete_s: secondes(Date.now() - depart) };
}

const navigateur = await chromium.launch();
const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', ...(process.env.SESSION ? { storageState: process.env.SESSION } : {}) });
const page = await contexte.newPage();
if (!process.env.SESSION) await connecter(page);

const resultats = [];
for (let essai = 1; essai <= repetitions; essai++) {
    // L'accueil : un chargement complet de la page (adresse tapée ou application ouverte).
    const debut = Date.now();
    await page.goto(`${base}/projets`, { waitUntil: 'domcontentloaded' });
    const premierAffichage = Date.now() - debut;
    await page.waitForLoadState('load');
    resultats.push({ essai, action: 'Ouvrir l’accueil', visible_s: secondes(premierAffichage), complete_s: secondes(Date.now() - debut) });
    await page.waitForTimeout(800);

    await ouvrirMenu(page);
    resultats.push({ essai, ...(await naviguer(page, 'Projets', () => page.locator('[data-nav-projects]').first().tap(), '[data-flux-main]')) });
    await page.waitForTimeout(800);

    await ouvrirMenu(page);
    resultats.push({ essai, ...(await naviguer(page, 'Bibliothèque', () => page.locator('[data-nav-library]').first().tap(), '[data-flux-main]')) });
    await page.waitForTimeout(800);

    await ouvrirMenu(page);
    resultats.push({ essai, ...(await naviguer(page, 'Nouvelle discussion', () => page.locator('[data-nav-new]').first().tap(), '[data-site-prompt]')) });
    await page.waitForTimeout(800);

    await ouvrirMenu(page);
    const recent = page.locator('[data-navigation] a[href*="/projets/"]').first();
    if (await recent.count()) {
        resultats.push({ essai, ...(await naviguer(page, 'Ouvrir une discussion', () => recent.tap(), null)) });
        await page.waitForTimeout(800);
    }

    await ouvrirMenu(page);
    await page.locator('[data-nav-projects]').first().tap();
    await page.waitForURL('**/mes-projets');
    await page.waitForTimeout(800);
    const projet = page.locator('[data-flux-main] a[href*="/projets/"]').first();
    if (await projet.count()) {
        resultats.push({ essai, ...(await naviguer(page, 'Ouvrir un projet', () => projet.tap(), null)) });
        await page.waitForTimeout(800);
    }

    await ouvrirMenu(page);
    resultats.push({ essai, ...(await ouvrir(page, 'Rechercher', () => page.locator('[data-nav-search]').first().tap(), '[data-search-input]')) });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);

    resultats.push({ essai, ...(await ouvrir(page, 'Menu du compte', () => page.locator('[data-mobile-account]').first().tap(), '[data-account-energy]')) });
    // Le menu finit de s'ouvrir (son animation) avant le toucher, comme pour une vraie personne.
    await page.waitForTimeout(400);
    resultats.push({ essai, ...(await naviguer(page, 'Formules', () => page.locator('[data-account-energy]').last().tap(), '[data-flux-main]')) });
    await page.waitForTimeout(800);

    await page.locator('[data-mobile-account]').first().tap();
    await page.waitForTimeout(400);
    resultats.push({ essai, ...(await naviguer(page, 'Réglages', () => page.locator('a[href*="/settings/profile"]').last().tap(), '[data-flux-main]')) });
    await page.waitForTimeout(800);
    console.log(`essai ${essai} fini`);
}

const parAction = {};
for (const r of resultats) (parAction[r.action] ??= []).push(r);
const mediane = (liste) => {
    const tri = [...liste].sort((a, b) => a - b);
    return tri[Math.floor(tri.length / 2)];
};
const resume = Object.fromEntries(Object.entries(parAction).map(([action, liste]) => [action, { visible_s: mediane(liste.map((r) => r.visible_s)), complete_s: mediane(liste.map((r) => r.complete_s)), pire_s: Math.max(...liste.map((r) => r.complete_s)) }]));
console.table(resume);
mkdirSync(dirname(sortie), { recursive: true });
writeFileSync(sortie, JSON.stringify({ date: new Date().toISOString(), base, repetitions, resume, resultats }, null, 2) + '\n');
await navigateur.close();
