// Essai réel de chaque fonction annoncée sur l'accueil (chantier « Kekeli fait tout correctement », 15 novembre
// 2026) : site web, application, agent IA, automatisation, présentation, image, vidéo, document, deux demandes
// réalistes chacune. Chaque demande part de l'accueil sur un téléphone simulé (Pixel 7, Chrome, en français) ; les
// questions reçoivent « Choisis pour moi », un plan reçoit « Construire ce plan ». Le résultat (état, temps, coût,
// fournisseur qui a vraiment travaillé) est relu dans la base du site. Comptes et demandes inventés ; aucune clé lue.
//
//   node scripts/essai-fonctions.js http://127.0.0.1:8140 docs/captures/fonctions/essais <compte> <scénario,scénario…>
// Variable BASE_SQLITE : base du site (par défaut /home/user/kekeli-fonctions.sqlite).
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, dossier, compte, liste = ''] = process.argv.slice(2);
const sqlite = process.env.BASE_SQLITE || '/home/user/kekeli-fonctions.sqlite';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));

export const SCENARIOS = {
    'site-1': { fonction: 'Site web', texte: 'Crée un site vitrine pour mon restaurant Le Palmier à Lomé (Bè) : riz au poisson 2 500 FCFA, attiéké poulet 3 000 FCFA, ouvert tous les jours de 11 h à 22 h, WhatsApp 90 00 00 00.' },
    'site-2': { fonction: 'Site web', texte: 'Crée un site pour mon pressing Clean Express à Agoè : lavage d’une chemise 500 FCFA, costume 2 500 FCFA, livraison gratuite, WhatsApp 91 11 11 11.' },
    'application-1': { fonction: 'Application', texte: 'Crée-moi une application pour gérer mon école de couture : les élèves, le paiement des frais (Mixx by Yas ou Flooz) et les présences, avec deux rôles : directrice et secrétaire.' },
    'application-2': { fonction: 'Application', texte: 'Je veux une application de prise de commandes pour ma boulangerie : produits avec leur prix, commandes des clients, statut (en préparation, livrée) et un compte administrateur.' },
    'agent-1': { fonction: 'Agent IA', texte: 'Crée un agent IA qui répond aux clientes de mon salon Belle Afi à Adidogomé sur les prix des tresses (à partir de 5 000 FCFA) et qui prend les rendez-vous.' },
    'agent-2': { fonction: 'Agent IA', texte: 'Crée un agent pour ma boutique Kossi Phones à Agoè qui répond sur les prix, la garantie de 6 mois et les horaires (de 8 h à 20 h).' },
    'automatisation-1': { fonction: 'Automatisation', texte: 'Crée une automatisation qui m’envoie chaque lundi à 8 h, par e-mail, le résumé des ventes de la semaine à partir d’un fichier CSV de mon dépôt.' },
    'automatisation-2': { fonction: 'Automatisation', texte: 'Crée une automatisation qui vérifie chaque jour à 18 h le cours du dollar en FCFA et me l’envoie par e-mail.' },
    'presentation-1': { fonction: 'Présentation', texte: 'Fais une présentation de 8 diapositives pour Lomé IA School, une école de formation en intelligence artificielle à Adidogomé : initiation 2 jours 25 000 FCFA, agents IA 5 jours 75 000 FCFA, automatisations 3 jours 45 000 FCFA, pour qui, comment s’inscrire.' },
    'presentation-2': { fonction: 'Présentation', texte: 'Fais une présentation de 6 diapositives pour présenter à une banque mon projet de ferme avicole à Kpalimé : besoin de 5 000 000 FCFA, 1 000 poulets, rentabilité en 18 mois.' },
    'image-1': { fonction: 'Image', texte: 'Fais-moi une affiche pour la promotion de ma boutique Pagnes Ama : -20 % sur tout le wax du 15 au 30 novembre, Grand Marché de Lomé, 90 00 00 00, format statut WhatsApp.' },
    'image-2': { fonction: 'Image', texte: 'Crée une photo de produit d’une bouteille de jus de bissap sans étiquette, sur un fond clair.' },
    'video-1': { fonction: 'Vidéo', texte: 'Fais une vidéo de 15 secondes au format vertical pour annoncer l’ouverture de Kossi Phones à Agoè le 5 décembre, téléphone 90 00 00 00.' },
    'video-2': { fonction: 'Vidéo', texte: 'Crée une vidéo de 20 secondes en format carré pour présenter le plat du jour de Chez Tante Afi : riz, poisson braisé et alloco à 1 500 FCFA.' },
    'document-1': { fonction: 'Document', texte: 'Fais un devis en FCFA de ma boutique « Pagnes Ama » au Grand Marché de Lomé pour l’école Les Étoiles : 40 pagnes wax à 12 000 FCFA, la couture de 40 tenues à 5 000 FCFA et la livraison à 10 000 FCFA. Acompte de 50 % par Mixx by Yas ou Flooz.' },
    'document-2': { fonction: 'Document', texte: 'Écris une lettre de motivation pour un poste de vendeuse chez Kossi Phones à Agoè, au nom d’Ama Mensah, 3 ans d’expérience dans la vente de téléphones.' },
};

const sql = (requete, ...valeurs) => JSON.parse(execFileSync('php', ['-r', '$db = new PDO("sqlite:" . $argv[1]); $q = $db->prepare($argv[2]); $q->execute(array_slice($argv, 3)); echo json_encode($q->fetchAll(PDO::FETCH_ASSOC));', sqlite, requete, ...valeurs.map(String)]).toString());

async function connecter(page) {
    await page.goto(`${base}/login`);
    await page.locator('[data-password-login]').click();
    await page.locator('input[type=email]').fill(compte);
    await page.locator('input[type=password]').fill('motdepasse-solide-2026');
    await page.locator('[data-password-submit]').click();
    await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 30000 });
}

async function essayer(page, nom) {
    const s = SCENARIOS[nom];
    await page.goto(`${base}/projets`);
    await page.locator('form textarea').first().fill(s.texte);
    const depart = Date.now();
    await page.locator('[data-prompt-send]').first().tap();
    await page.waitForURL(/projets\/[0-9a-z]{20,}/, { timeout: 60000 });
    const projet = page.url().split('/projets/')[1].split(/[?#]/)[0];
    const actions = [];
    // Jusqu'à 25 minutes : questions, plan et travail, en relisant la base toutes les 5 secondes.
    for (let i = 0; i < 300; i++) {
        await attendre(5000);
        const runs = sql('select id, purpose, status, report from agent_runs where project_id = ? order by created_at', projet);
        const actif = runs.some((r) => ['queued', 'running', 'waiting'].includes(r.status));
        if (actif) continue;
        await page.reload({ waitUntil: 'domcontentloaded' }).catch(() => {});
        await attendre(1500);
        if (!actions.includes('questions') && await page.locator('[data-choose-for-me]').count()) {
            actions.push('questions');
            await page.locator('[data-choose-for-me]').last().tap();
            continue;
        }
        if (!actions.includes('plan') && await page.locator('[data-build-plan]').count()) {
            actions.push('plan');
            await page.locator('[data-build-plan]').last().tap();
            continue;
        }
        // Une question courte ou une proposition (« Oui, créer… ») : acceptée une fois.
        if (!actions.includes('oui') && await page.locator('[data-accept-offer], [data-accept-site-offer], [data-suggestion]').count()) {
            actions.push('oui');
            await page.locator('[data-accept-offer], [data-accept-site-offer], [data-suggestion]').last().tap();
            continue;
        }
        break;
    }
    const runs = sql('select purpose, status, provider, model, error, stop_reason, requests, model_cost_usd, computer_cost_usd, image_cost_usd, voice_cost_usd, web_search_cost_usd, started_at, finished_at, created_at, report from agent_runs where project_id = ? order by created_at', projet);
    const travail = runs.filter((r) => r.purpose !== 'question').at(-1) ?? runs.at(-1);
    const cout = runs.reduce((n, r) => n + ['model_cost_usd', 'computer_cost_usd', 'image_cost_usd', 'voice_cost_usd', 'web_search_cost_usd'].reduce((m, c) => m + Number(r[c] || 0), 0), 0);
    const fournisseurs = sql('select provider, model, sum(requests) as requetes from provider_usages where updated_at >= ? group by provider, model', new Date(depart - 1000).toISOString().slice(0, 19).replace('T', ' '));
    const rapport = (() => {
        try {
            return JSON.parse(travail?.report || '{}');
        } catch {
            return {};
        }
    })();
    await page.waitForTimeout(1000);
    await page.screenshot({ path: `${dossier}/${nom}-discussion.png` });
    // L'aperçu, le fichier ou la vidéo, quand ils existent.
    for (const vue of ['apercu', 'fichier']) {
        const onglet = page.locator(`[data-tab="${vue}"]`);
        if (await onglet.count()) {
            await onglet.first().tap().catch(() => {});
            await page.waitForTimeout(6000);
            await page.screenshot({ path: `${dossier}/${nom}-${vue}.png` });
            break;
        }
    }
    return {
        scenario: nom,
        fonction: s.fonction,
        projet,
        actions,
        etat: travail?.status ?? 'aucun travail',
        but: travail?.purpose ?? null,
        erreur: travail?.error ?? null,
        arret: travail?.stop_reason ?? null,
        secondes: Math.round((Date.now() - depart) / 1000),
        cout_usd: Math.round(cout * 10000) / 10000,
        cout_fcfa: Math.round(cout * 590 * 10) / 10,
        fournisseur: [travail?.provider, travail?.model].filter(Boolean).join('/') || null,
        fournisseurs_du_moment: fournisseurs,
        message_final: String(rapport.done ?? '').slice(0, 600),
    };
}

(async () => {
    mkdirSync(dossier, { recursive: true });
    const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
    const page = await (await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })).newPage();
    await connecter(page);
    const fichier = `${dossier}/resultats.json`;
    for (const nom of liste.split(',').filter(Boolean)) {
        let r;
        try {
            r = await essayer(page, nom);
        } catch (erreur) {
            r = { scenario: nom, fonction: SCENARIOS[nom]?.fonction, etat: 'erreur du script', erreur: erreur.message.split('\n')[0] };
        }
        console.log(JSON.stringify({ ...r, message_final: undefined, fournisseurs_du_moment: undefined }));
        // Plusieurs essais tournent en même temps : le fichier est relu juste avant d'écrire.
        const tous = existsSync(fichier) ? JSON.parse(readFileSync(fichier, 'utf8')) : [];
        writeFileSync(fichier, JSON.stringify([...tous.filter((x) => x.scenario !== nom), r], null, 2));
    }
    await navigateur.close();
})();
