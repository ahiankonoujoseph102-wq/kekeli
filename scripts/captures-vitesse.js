// Captures du chantier « vitesse » (téléphone et ordinateur, clair et sombre), compte et messages inventés.
//   node scripts/captures-vitesse.js http://127.0.0.1:8125 adresse@exemple.tg
// Les états très courts (message affiché avant la réponse du site, réponse en train de s'écrire, réveil, échec) sont
// tenus à l'écran en retardant ou en remplaçant les réponses du site dans le navigateur seulement.
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, compte] = process.argv.slice(2);
const DOSSIER = 'docs/captures/vitesse';
mkdirSync(DOSSIER, { recursive: true });

const APPAREILS = {
    telephone: { ...devices['Pixel 7'], locale: 'fr-FR' },
    ordinateur: { viewport: { width: 1280, height: 800 }, deviceScaleFactor: 2, locale: 'fr-FR' },
};

async function connecter(page) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.waitForSelector('[data-password-submit]');
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
}

/** Une nouvelle discussion déjà ouverte (sa première réponse est faite), pour envoyer le message suivant. */
async function ouvrirDiscussion(page, premier) {
    await page.goto(`${base}/projets`);
    await page.locator('form textarea').first().fill(premier);
    await page.locator('[data-prompt-send]').first().click();
    await page.waitForURL(/\/projets\/[0-9a-z]{26}/, { timeout: 30000 });
    await page.waitForSelector('[data-report-message]', { timeout: 60000 });
    await page.waitForTimeout(500);
}

const ecrire = async (page, texte) => {
    const champ = page.locator('textarea[data-message-input]');
    // Une réponse encore en cours (par exemple celle que la file d'attente termine en secours) : on attend la fin.
    await champ.waitFor({ state: 'visible', timeout: 90000 });
    await page.waitForFunction(() => { const c = document.querySelector('textarea[data-message-input]'); return c && !c.disabled; }, null, { timeout: 90000 });
    await champ.fill(texte, { timeout: 90000 });
};

(async () => {
    const navigateur = await chromium.launch();
    for (const [appareil, reglages] of Object.entries(APPAREILS)) {
        for (const theme of ['clair', 'sombre']) {
            const contexte = await navigateur.newContext({ ...reglages, colorScheme: theme === 'sombre' ? 'dark' : 'light' });
            const page = await contexte.newPage();
            const nom = (etat) => `${DOSSIER}/${etat}-${appareil}-${theme}.png`;
            await connecter(page);

            // 1. Envoi instantané : la réponse du site est retenue, le message et « Arrêter » sont déjà là.
            await ouvrirDiscussion(page, 'Bonjour Kekeli');
            let retenir = true;
            await page.route('**/livewire*/**', async (route) => {
                if (retenir && route.request().method() === 'POST' && (route.request().postData() || '').includes('"send"')) {
                    await new Promise((r) => setTimeout(r, 4500));
                }
                await route.continue().catch(() => {});
            });
            await ecrire(page, 'Combien coûte un nom de domaine en .tg ?');
            await page.locator('[data-prompt-send]').click();
            await page.waitForTimeout(300);
            await page.screenshot({ path: nom('1-envoi-instantane') });
            // 2. Le site tarde (réveil après une pause) : une ligne courte, jamais un écran figé.
            await page.waitForTimeout(3200);
            await page.screenshot({ path: nom('2-reveil') });
            retenir = false;
            await page.waitForTimeout(1500);
            await page.unroute('**/livewire*/**', { behavior: 'ignoreErrors' }).catch(() => {});

            // 3. La réponse s'écrit mot par mot : le flux est remplacé par un faux, arrêté au milieu.
            await page.waitForSelector('[data-report-message]', { timeout: 60000 });
            await page.route('**/reponses/*', async (route) => {
                if (route.request().url().endsWith('/chronos')) return route.continue();
                const morceaux = ['debut', 'Un nom de domaine en **.tg** coûte en général ', 'entre 10 000 et 20 000 FCFA par an, ', 'selon le bureau d’enregistrement.\n\n- Vérifiez '];
                const corps = morceaux.map((m) => `data: ${JSON.stringify(m === 'debut' ? { type: 'debut' } : { type: 'texte', delta: m })}\n\n`).join('');
                await route.fulfill({ status: 200, headers: { 'content-type': 'text/event-stream' }, body: corps + ': attente\n\n'.repeat(1) });
            });
            await ecrire(page, 'Et en .com ?');
            await page.locator('[data-prompt-send]').click();
            await page.waitForFunction(() => document.querySelector('[data-reponse-en-cours]:not(.hidden)')?.textContent.includes('bureau'), null, { timeout: 30000 }).catch(() => {});
            await page.waitForTimeout(200);
            await page.screenshot({ path: nom('3-mot-par-mot') });
            await page.unroute('**/reponses/*');

            // 4. Le message n'est pas parti (réseau coupé) : il reste, avec « Réessayer ».
            await page.goto(page.url());
            await page.waitForTimeout(800);
            await ecrire(page, 'Merci, et pour un site vitrine ?');
            await page.route('**/livewire*/**', (route) => (route.request().method() === 'POST' ? route.abort() : route.continue()));
            await page.locator('[data-prompt-send]').click();
            await page.waitForSelector('[data-envoi-echoue] [data-reessayer]:not(.hidden)', { timeout: 20000 }).catch(() => {});
            await page.screenshot({ path: nom('4-reessayer') });
            await page.unroute('**/livewire*/**');

            // 5. Une demande dictée qui se répète : une question courte et « Oui, créer le site », rien n'est créé.
            await page.goto(`${base}/projets`);
            await page.locator('form textarea').first().fill('crée un site crée un site pour mon restaurant crée un site pour mon restaurant à Lomé');
            await page.locator('[data-prompt-send]').first().click();
            await page.waitForSelector('[data-accept-offer]', { timeout: 60000 }).catch(() => {});
            await page.waitForTimeout(400);
            await page.screenshot({ path: nom('5-confirmation') });

            // 6. Administration → Consommation : la vitesse des réponses.
            await page.goto(`${base}/administration/consommation`);
            await page.locator('[data-speeds]').scrollIntoViewIfNeeded();
            await page.waitForTimeout(300);
            await page.locator('[data-speeds]').screenshot({ path: nom('6-administration') });
            await contexte.close();
            console.log(appareil, theme, 'fait');
        }
    }
    await navigateur.close();
})();
