// Lighthouse sur téléphone (médiane de 3) pour le chantier « vitesse » : l'accueil et une discussion ouverte, avec le
// même compte inventé, pour comparer deux versions du site sur la même machine.
//   node scripts/lighthouse-vitesse.js http://127.0.0.1:8125 adresse@exemple.tg <identifiant d'une discussion>
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { readFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const { chromium } = createRequire(import.meta.url)('playwright');
const [base, compte, discussion] = process.argv.slice(2);
const dossier = mkdtempSync(join(tmpdir(), 'lighthouse-'));

async function cookieDeSession() {
    const navigateur = await chromium.launch();
    const page = await navigateur.newPage();
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
    const cookies = (await page.context().cookies()).map((c) => `${c.name}=${c.value}`).join('; ');
    await navigateur.close();
    return cookies;
}

function note(adresse, cookie, n) {
    const sortie = join(dossier, `r${n}.json`);
    const options = ['--yes', 'lighthouse@12.6.0', adresse, '--quiet', '--output=json', `--output-path=${sortie}`, '--only-categories=performance,accessibility', '--chrome-flags=--headless --no-sandbox'];
    if (cookie) options.push(`--extra-headers=${JSON.stringify({ Cookie: cookie })}`);
    execFileSync('npx', options, { env: { ...process.env, CHROME_PATH: '/opt/pw-browsers/chromium' }, stdio: 'ignore' });
    const r = JSON.parse(readFileSync(sortie));
    return { performance: Math.round(r.categories.performance.score * 100), accessibilite: Math.round(r.categories.accessibility.score * 100) };
}

const mediane = (v) => [...v].sort((a, b) => a - b)[1];
const cookie = await cookieDeSession();
for (const [nom, adresse, avecCookie] of [['Accueil', `${base}/`, false], ['Discussion', `${base}/projets/${discussion}`, true]]) {
    const essais = [0, 1, 2].map((i) => note(adresse, avecCookie ? cookie : null, `${nom}-${i}`));
    console.log(JSON.stringify({ page: nom, performance: mediane(essais.map((e) => e.performance)), accessibilite: mediane(essais.map((e) => e.accessibilite)) }));
}
