// Mesure de la vitesse de Kekeli sur un téléphone simulé (Pixel 6, Chrome), comme on la ressent : on touche
// « Envoyer » puis on note, en secondes depuis le toucher, quand le message s'affiche, quand le premier mot de la
// réponse apparaît et quand la réponse est finie. Les temps notés par le site et le Runner (agent_runs.timings) sont
// relus dans la base. Compte et messages inventés ; aucune clé n'est lue ni écrite ici.
//
//   node scripts/mesurer-vitesse.js http://127.0.0.1:8125 docs/captures/vitesse/avant.json
// Variables : BASE_SQLITE (base du site, par défaut /home/user/kekeli-vitesse.sqlite), REPETITIONS (3 par défaut),
// SEULEMENT=salut,question,site, COMPTE (adresse du compte inventé).
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');

const [base, sortie] = process.argv.slice(2);
const sqlite = process.env.BASE_SQLITE || '/home/user/kekeli-vitesse.sqlite';
const repetitions = Number(process.env.REPETITIONS || 3);
const seulement = (process.env.SEULEMENT || '').split(',').filter(Boolean);
const compte = process.env.COMPTE || 'afi-vitesse@exemple.tg';

const SCENARIOS = [
    { nom: 'salut', texte: 'Salut', attendu: 'reponse' },
    { nom: 'question', texte: 'Quel est le meilleur moment pour publier sur Facebook pour un restaurant ?', attendu: 'reponse' },
    { nom: 'site', texte: 'Crée un site vitrine pour mon restaurant Le Palmier à Lomé, avec le menu, les horaires et un bouton WhatsApp.', attendu: 'travail', repetitions: 1 },
];

const secondes = (ms) => Math.round(ms / 100) / 10;
const derniereTache = () => {
    const sortieSql = execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); echo json_encode($db->query("select id, purpose, timings from agent_runs order by created_at desc, id desc limit 2")->fetchAll(PDO::FETCH_ASSOC));', sqlite]).toString();
    return JSON.parse(sortieSql).map((t) => ({ id: t.id, but: t.purpose, temps: t.timings ? JSON.parse(t.timings) : null }));
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

/** Attend une condition dans la page (vérifiée toutes les 50 millisecondes) et rend le temps depuis le toucher. */
async function quand(page, depart, condition, argument, delai = 120000) {
    await page.waitForFunction(condition, argument, { polling: 50, timeout: delai });
    return secondes(Date.now() - depart);
}

async function mesurer(page, scenario) {
    // Une nouvelle discussion à chaque fois : le premier message, comme sur l'accueil.
    await page.goto(`${base}/projets`);
    await page.waitForLoadState('networkidle');
    const champ = page.locator('form textarea').first();
    await champ.fill(scenario.texte);
    const depart = Date.now();
    await page.locator('[data-prompt-send]').first().tap();
    const resultat = { scenario: scenario.nom };
    resultat.affiche = await quand(page, depart, (t) => [...document.querySelectorAll('[data-user-message], [data-message-envoye]')].some((e) => e.textContent.includes(t)), scenario.texte);
    if (scenario.attendu === 'reponse') {
        resultat.premier_mot = await quand(page, depart, () => [...document.querySelectorAll('[data-report-message], [data-reponse-en-cours]')].some((e) => e.textContent.trim().length > 0));
        resultat.fin = await quand(page, depart, () => document.querySelector('[data-report-message]') && !document.querySelector('[data-prompt-stop]') && !document.querySelector('[data-reponse-en-cours]'));
    } else {
        // Une demande de site : le travail commence (état « Kekeli travaille… »), puis la première étape s'affiche.
        resultat.travail_affiche = await quand(page, depart, () => document.querySelector('[data-tab]') || document.querySelector('[data-travail-demarre]'));
        resultat.premiere_etape = await quand(page, depart, () => document.querySelectorAll('[data-step], [data-narration]').length > 0, undefined, 300000).catch(() => null);
        // On arrête le travail : la mesure ne paie pas une construction entière.
        await page.locator('[data-prompt-stop]').first().tap().catch(() => {});
        await page.waitForTimeout(3000);
    }
    resultat.serveur = derniereTache();
    return resultat;
}

(async () => {
    const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
    const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
    const page = await contexte.newPage();
    await connecter(page);
    const resultats = [];
    for (const scenario of SCENARIOS) {
        if (seulement.length && !seulement.includes(scenario.nom)) continue;
        for (let i = 0; i < (scenario.repetitions ?? repetitions); i++) {
            try {
                const r = await mesurer(page, scenario);
                console.log(JSON.stringify({ ...r, serveur: undefined }));
                resultats.push(r);
            } catch (erreur) {
                console.log(`${scenario.nom} : ${erreur.message.split('\n')[0]}`);
                resultats.push({ scenario: scenario.nom, erreur: erreur.message.split('\n')[0] });
            }
        }
    }
    await navigateur.close();
    mkdirSync(dirname(sortie), { recursive: true });
    writeFileSync(sortie, JSON.stringify({ date: new Date().toISOString(), base, resultats }, null, 2));
})();
