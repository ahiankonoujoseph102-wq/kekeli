// Captures du chantier « Vidéos motion design, étape 5 : les trois questions » (téléphone et ordinateur, clair
// et sombre). Données inventées : scripts/preparer-captures-montage-etape-5.php (compte afi-montage@exemple.tg).
//   node scripts/captures-montage-etape-5.js http://127.0.0.1:8133
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base] = process.argv.slice(2);
const DOSSIER = 'docs/captures/montage-etape-5/ecrans';
const BASE_SQLITE = process.env.BASE_SQLITE || '/home/user/kekeli-montage.sqlite';
const projetDe = (titre) => execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare("select p.id from projects p join users u on u.id = p.user_id where u.email = ? and p.name = ?"); $q->execute(["afi-montage@exemple.tg", $argv[2]]); echo $q->fetchColumn();', BASE_SQLITE, titre]).toString().trim();
mkdirSync(DOSSIER, { recursive: true });

const APPAREILS = {
    telephone: { ...devices['Pixel 7'], locale: 'fr-FR' },
    ordinateur: { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2, locale: 'fr-FR' },
};
const ECRANS = [
    ['1-trois-questions', 'Publicité Clean Express'],
    ['2-questions-restantes', 'Témoignage Belle Afi'],
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
            await page.locator('input[type=email]').fill('afi-montage@exemple.tg');
            await page.locator('input[type=password]').fill('motdepasse-solide-2026');
            await page.locator('[data-password-submit]').click();
            await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
            for (const [nom, titre] of ECRANS) {
                await page.goto(`${base}/projets/${projetDe(titre)}?vue=discussion`);
                await page.waitForSelector('[data-conversation]', { timeout: 20000 });
                await page.waitForTimeout(700);
                await page.evaluate(() => { const c = document.querySelector('[data-conversation]'); c.scrollTop = c.scrollHeight; });
                await page.waitForTimeout(300);
                await page.screenshot({ path: `${DOSSIER}/${nom}-${appareil}-${theme}.png` });
            }
            await contexte.close();
            console.log(appareil, theme, 'fait');
        }
    }
    await navigateur.close();
})();
