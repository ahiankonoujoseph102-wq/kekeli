// Essai de la fluidité (chantier « réponses », 15 novembre 2026), sur un téléphone simulé : écriture régulière, fil qui
// suit la réponse, client qui remonte (jamais ramené en bas, bouton « ↓ »), « Arrêter » qui coupe l'affichage tout de
// suite, et saut de mise en page quand la version du site remplace le texte en train de s'écrire.
//   node scripts/essai-fluidite.js http://127.0.0.1:8131 adresse@exemple.tg <identifiant d'une discussion>
// Le flux de la réponse est remplacé dans le navigateur par une longue réponse inventée, envoyée d'un seul bloc.
import { createRequire } from 'node:module';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, compte, discussion] = process.argv.slice(2);
const LONGUE = ['## Les prix courants', '', '| Modèle | Prix | Durée |', '|---|---|---|', '| Nattes | 3 000 FCFA | 1 h |', '| Tresses mi-dos | 12 000 FCFA | 5 h |', '', ...Array.from({ length: 14 }, (_, i) => `${i + 1}. **Conseil ${i + 1}** : une phrase assez longue pour remplir une ligne entière sur un téléphone, avec des détails utiles.`), '', 'Ma recommandation : demandez toujours le prix avant de commencer.'].join('\n');

const navigateur = await chromium.launch();
const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })).newPage();
await page.goto(`${base}/login`);
await page.locator('[data-password-login]').click();
await page.waitForSelector('[data-password-submit]');
await page.locator('input[type=email]').fill(compte);
await page.locator('input[type=password]').fill('motdepasse-solide-2026');
await page.locator('[data-password-submit]').click();
await page.waitForURL((url) => !url.pathname.startsWith('/login'));

const resultat = {};
const flux = (arret = false) => page.route('**/reponses/*', async (route) => {
    if (route.request().url().endsWith('/chronos')) return route.continue();
    const corps = `data: {"type":"debut"}\n\ndata: ${JSON.stringify({ type: 'texte', delta: LONGUE })}\n\n` + (arret ? '' : 'data: {"type":"fin"}\n\n');
    await route.fulfill({ status: 200, headers: { 'content-type': 'text/event-stream' }, body: corps });
});
const zone = () => page.evaluate(() => {
    const z = [...document.querySelectorAll('[data-reponse-en-cours]')].pop();
    const c = document.querySelector('[data-conversation]');
    return { longueur: z?.textContent.length ?? 0, hauteur: z?.getBoundingClientRect().height ?? 0, distance: c.scrollHeight - c.scrollTop - c.clientHeight, bouton: !!document.querySelector('[data-fil-bas]')?.offsetParent };
});
const envoyer = async (texte) => {
    // La demande précédente se termine vraiment côté site (la file d'attente répond en secours) : on attend la fin.
    await page.waitForFunction(() => { const c = document.querySelector('textarea[data-message-input]'); return c && !c.disabled; }, null, { timeout: 90000 });
    await page.waitForTimeout(500);
    await page.locator('textarea[data-message-input]').fill(texte);
    await page.locator('[data-prompt-send]').click();
};

// 1. Écriture régulière et fil qui suit.
await page.goto(`${base}/projets/${discussion}`);
await page.waitForTimeout(800);
await flux();
await envoyer('Donne-moi les prix des tresses et 14 conseils.');
const etapes = [];
for (let i = 0; i < 12; i++) {
    await page.waitForTimeout(80);
    etapes.push(await zone());
}
resultat.ecriture_reguliere = etapes.map((e) => e.longueur);
resultat.suit_la_reponse = etapes.filter((e) => e.longueur > 0).every((e) => e.distance < 60);
// Hauteur juste avant que la version du site remplace le texte, puis la version finale.
await page.waitForFunction((n) => ([...document.querySelectorAll('[data-reponse-en-cours]')].pop()?.textContent.length ?? 0) >= n - 40, LONGUE.length - 200, { timeout: 15000 }).catch(() => {});
resultat.hauteur_pendant = Math.round((await zone()).hauteur);
await page.unroute('**/reponses/*');

// 2. Le client remonte pendant l'écriture : jamais ramené en bas, bouton « ↓ ».
await page.waitForFunction(() => !(window.Alpine?.store('saisie')?.enCours), null, { timeout: 60000 }).catch(() => {});
await page.waitForTimeout(500);
await flux();
await envoyer('Encore une fois, les mêmes conseils.');
await page.waitForFunction(() => ([...document.querySelectorAll('[data-reponse-en-cours]')].pop()?.textContent.length ?? 0) > 50, null, { timeout: 15000 }).catch(() => {});
// Comme un doigt qui remonte le fil : un défilement vers le haut.
await page.mouse.move(200, 400);
await page.mouse.wheel(0, -4000);
await page.waitForTimeout(700);
const apresRemontee = await zone();
resultat.pas_ramene_en_bas = apresRemontee.distance > 300;
resultat.bouton_visible = apresRemontee.bouton;
await page.screenshot({ path: process.env.CAPTURE_BOUTON || '/tmp/bouton-bas.png' });
await page.locator('[data-fil-bas]').click();
await page.waitForTimeout(900);
const apresBouton = await zone();
resultat.bouton_ramene_en_bas = apresBouton.distance < 60 && !apresBouton.bouton;
await page.unroute('**/reponses/*');

// 3. « Arrêter » coupe l'affichage tout de suite.
await page.waitForFunction(() => !(window.Alpine?.store('saisie')?.enCours), null, { timeout: 60000 }).catch(() => {});
await page.waitForTimeout(500);
await flux(true);
await envoyer('Et une troisième fois ?');
await page.waitForFunction(() => ([...document.querySelectorAll('[data-reponse-en-cours]')].pop()?.textContent.length ?? 0) > 30, null, { timeout: 15000 }).catch(() => {});
await page.locator('[data-arret-instant]:visible, [data-prompt-stop]:visible').first().click();
const auArret = (await zone()).longueur;
await page.waitForTimeout(400);
const ensuite = (await zone()).longueur;
resultat.arret = { au_toucher: auArret, quatre_cents_ms_plus_tard: ensuite, coupe_tout_de_suite: ensuite <= auArret + 5 };

console.log(JSON.stringify(resultat, null, 1));
await navigateur.close();
