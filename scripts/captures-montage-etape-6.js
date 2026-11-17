// Captures du chantier « Vidéos motion design, étape 6 » (téléphone, clair et sombre ; ordinateur, clair) : la vidéo
// dans le lecteur avec son style, et le message de fin avec ses boutons de suite. Données :
// scripts/preparer-captures-montage-etape-6.php (compte afi-montage@exemple.tg, vraies vidéos de l'essai réel).
//   BASE_SQLITE=… node scripts/captures-montage-etape-6.js http://127.0.0.1:8133
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base] = process.argv.slice(2);
const DOSSIER = 'docs/captures/montage-etape-6/ecrans';
const BASE_SQLITE = process.env.BASE_SQLITE || '/home/user/kekeli-montage.sqlite';
const projetDe = (titre) => execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare("select p.id from projects p join users u on u.id = p.user_id where u.email = ? and p.name = ?"); $q->execute(["afi-montage@exemple.tg", $argv[2]]); echo $q->fetchColumn();', BASE_SQLITE, titre]).toString().trim();
mkdirSync(DOSSIER, { recursive: true });

const APPAREILS = {
    telephone: { ...devices['Pixel 7'], locale: 'fr-FR' },
    ordinateur: { viewport: { width: 1280, height: 860 }, deviceScaleFactor: 2, locale: 'fr-FR' },
};
const ECRANS = [
    ['1-produit-discussion', 'Clean Express', 'discussion', null],
    ['2-produit-video', 'Clean Express', 'apercu', 5.6],
    ['3-temoignage-video', 'Belle Afi', 'apercu', 10.5],
    ['4-temoignage-discussion', 'Belle Afi', 'discussion', null],
];

(async () => {
    const navigateur = await chromium.launch();
    for (const [appareil, reglages] of Object.entries(APPAREILS)) {
        for (const theme of appareil === 'telephone' ? ['clair', 'sombre'] : ['clair']) {
            const contexte = await navigateur.newContext({ ...reglages, colorScheme: theme === 'sombre' ? 'dark' : 'light' });
            const page = await contexte.newPage();
            await page.goto(`${base}/login`);
            await page.locator('[data-password-login]').click();
            await page.waitForSelector('[data-password-submit]');
            await page.locator('input[type=email]').fill('afi-montage@exemple.tg');
            await page.locator('input[type=password]').fill('motdepasse-solide-2026');
            await page.locator('[data-password-submit]').click();
            await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
            for (const [nom, titre, vue, instant] of ECRANS) {
                await page.goto(`${base}/projets/${projetDe(titre)}?vue=${vue}`);
                await page.waitForSelector('[data-conversation]', { timeout: 20000, state: 'attached' });
                await page.waitForTimeout(800);
                if (instant !== null) {
                    // Le lecteur montre une image de la vidéo (pas l'écran noir du début).
                    await page.evaluate(async (t) => {
                        const v = document.querySelector('[data-video-player]');
                        if (!v) return;
                        v.muted = true;
                        await new Promise((ok) => { if (v.readyState >= 1) ok(); else v.addEventListener('loadedmetadata', ok, { once: true }); setTimeout(ok, 4000); });
                        v.currentTime = t;
                        await new Promise((ok) => { v.addEventListener('seeked', ok, { once: true }); setTimeout(ok, 4000); });
                    }, instant);
                    await page.waitForTimeout(500);
                } else {
                    await page.evaluate(() => { const c = document.querySelector('[data-conversation]'); c.scrollTop = c.scrollHeight; });
                    await page.waitForTimeout(300);
                }
                await page.screenshot({ path: `${DOSSIER}/${nom}-${appareil}-${theme}.png` });
            }
            await contexte.close();
            console.log(appareil, theme, 'fait');
        }
    }
    await navigateur.close();
})();
