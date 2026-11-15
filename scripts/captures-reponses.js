// Captures du chantier « Répondre comme Claude et ChatGPT » (téléphone et ordinateur, clair et sombre), avant et après.
//   node scripts/captures-reponses.js http://127.0.0.1:8131 avant
// Données inventées : scripts/preparer-captures-reponses.php (compte afi-reponses@exemple.tg).
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, phase = 'apres'] = process.argv.slice(2);
const DOSSIER = `docs/captures/reponses/${phase}`;
const BASE_SQLITE = process.env.BASE_SQLITE || '/home/user/kekeli-reponses.sqlite';
/** L'identifiant d'une discussion du compte d'essai, lu dans la base locale. */
const discussionDe = (titre) => execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare("select p.id from projects p join users u on u.id = p.user_id where u.email = ? and p.name = ?"); $q->execute(["afi-reponses@exemple.tg", $argv[2]]); echo $q->fetchColumn();', BASE_SQLITE, titre]).toString().trim();
mkdirSync(DOSSIER, { recursive: true });

const APPAREILS = {
    telephone: { ...devices['Pixel 7'], locale: 'fr-FR' },
    ordinateur: { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2, locale: 'fr-FR' },
};
const DISCUSSIONS = [
    ['1-courte', 'Capitale du Burkina Faso', 'fin'],
    ['2-longue', 'Prix d’une tresse à Lomé', 'debut'],
    ['3-code', 'Bouton WhatsApp sur une page', 'fin'],
    ['4-remonter', 'Trouver ses premiers clients', 'milieu'],
];

(async () => {
    const navigateur = await chromium.launch();
    for (const [appareil, reglages] of Object.entries(APPAREILS)) {
        for (const theme of ['clair', 'sombre']) {
            const contexte = await navigateur.newContext({ ...reglages, colorScheme: theme === 'sombre' ? 'dark' : 'light' });
            const page = await contexte.newPage();
            await page.goto(`${base}/login`);
            await page.locator('[data-password-login]').click();
            await page.waitForSelector('[data-password-submit]');
            await page.locator('input[type=email]').fill('afi-reponses@exemple.tg');
            await page.locator('input[type=password]').fill('motdepasse-solide-2026');
            await page.locator('[data-password-submit]').click();
            await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
            for (const [nom, titre, position] of DISCUSSIONS) {
                await page.goto(`${base}/projets/${discussionDe(titre)}`);
                await page.waitForSelector('[data-conversation]', { timeout: 20000 });
                await page.waitForTimeout(600);
                await page.evaluate((p) => {
                    const c = document.querySelector('[data-conversation]');
                    if (p === 'debut') c.scrollTop = 0;
                    if (p === 'fin') c.scrollTop = c.scrollHeight;
                    if (p === 'milieu') c.scrollTop = c.scrollHeight * 0.35;
                    c.dispatchEvent(new Event('scroll'));
                }, position);
                await page.waitForTimeout(400);
                await page.screenshot({ path: `${DOSSIER}/${nom}-${appareil}-${theme}.png` });
            }
            await contexte.close();
            console.log(phase, appareil, theme, 'fait');
        }
    }
    await navigateur.close();
})();
