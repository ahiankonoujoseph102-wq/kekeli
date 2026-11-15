// Crée (ou reconnecte) un compte de test inventé sur le site en ligne, pour les mesures de vitesse en production :
// l'adresse est une boîte jetable de mail.tm (aucune donnée réelle), le code de connexion est lu dans cette boîte.
// La session du navigateur est gardée dans un fichier hors du dépôt (premier argument), jamais versionnée.
//
//   node scripts/compte-essai-production.js <boite.json> <session.json> [adresse du site]
// boite.json : { "adresse": "…@…", "mdp": "…" } (boîte mail.tm déjà créée).
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const [fichierBoite, fichierSession, base = 'https://kekeli.laravel.cloud'] = process.argv.slice(2);
const boite = JSON.parse(readFileSync(fichierBoite, 'utf8'));
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

/** Lit une réponse JSON de mail.tm, en réessayant après une limite de requêtes (réponse vide). */
async function lireJson(url, options = {}) {
    for (let i = 0; i < 6; i++) {
        const r = await fetch(url, options);
        const texte = await r.text();
        if (r.ok && texte) return JSON.parse(texte);
        await attendre(2000 * (i + 1));
    }
    throw new Error(`mail.tm ne répond pas (${url})`);
}

async function jeton() {
    return (await lireJson('https://api.mail.tm/token', { method: 'POST', headers: { 'content-type': 'application/json' }, body: JSON.stringify({ address: boite.adresse, password: boite.mdp }) })).token;
}

async function codeRecu(t, avant) {
    for (let i = 0; i < 40; i++) {
        const liste = await lireJson('https://api.mail.tm/messages', { headers: { authorization: `Bearer ${t}` } });
        const nouveaux = (liste['hydra:member'] ?? []).filter((m) => !avant.has(m.id));
        for (const m of nouveaux) {
            const detail = await lireJson(`https://api.mail.tm/messages/${m.id}`, { headers: { authorization: `Bearer ${t}` } });
            const code = String(detail.text ?? '').match(/\b(\d{3}\s?\d{3})\b/);
            if (code) return code[1].replace(/\s/g, '');
        }
        await attendre(3000);
    }
    throw new Error('Aucun code reçu');
}

const t = await jeton();
const deja = new Set(((await lireJson('https://api.mail.tm/messages', { headers: { authorization: `Bearer ${t}` } }))['hydra:member'] ?? []).map((m) => m.id));
const navigateur = await chromium.launch();
const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
const page = await contexte.newPage();
await page.goto(`${base}/login`);
await page.locator('[data-login-email]').fill(boite.adresse);
await page.locator('[data-continue-email]').click();
await page.waitForSelector('[data-login-code]', { timeout: 30000 });
const code = await codeRecu(t, deja);
await page.locator('[data-login-code]').fill(code);
await page.locator('[data-verify-code]').click();
await page.waitForTimeout(3000);
if (await page.locator('[data-signup-name]').count()) {
    await page.locator('[data-signup-name]').fill('Essai Vitesse');
    await page.locator('[data-accept-terms]').check();
    await page.locator('[data-create-account]').click();
}
await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 30000 });
console.log('Connecté :', new URL(page.url()).pathname);
await contexte.storageState({ path: fichierSession });
await navigateur.close();
