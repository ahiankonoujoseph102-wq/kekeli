// Essai réel de la création d'un agent IA, comme un client sur un téléphone simulé : la demande dans « Que voulez-vous
// créer aujourd'hui ? », le plan proposé, « Construire ce plan », la construction dans un vrai ordinateur E2B, puis de
// vraies questions dans le panneau « Tester l'agent » avec l'essai gratuit (modèle gratuit de Kekeli par le relais du
// Runner), jusqu'à l'essai gratuit terminé. Captures sur téléphone et sur ordinateur, en clair et en sombre.
// Le site, la file d'attente et le Runner doivent tourner (avec le même KEKELI_RUNNER_SECRET).
//
//   php artisan tinker --execute="require 'scripts/preparer-essai-agent.php';"
//   node scripts/essai-agent.js docs/captures/agents
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const require = createRequire(import.meta.url);
const { chromium, devices } = require('playwright');
const dossier = process.argv[2] || 'docs/captures/agents';
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123';
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim();
const DEMANDE = "Je veux une assistante pour mon Salon de coiffure Belle Afi à Lomé. Elle répond aux questions des clientes sur nos prestations et nos prix à partir de ma FAQ, et elle propose de prendre rendez-vous. FAQ : tresses simples 5 000 FCFA ; tresses africaines longues 15 000 FCFA ; coupe femme 3 000 FCFA ; brushing 4 000 FCFA ; soin des cheveux 6 000 FCFA. Ouvert du lundi au samedi, de 9 h à 19 h, à Adidogomé (Lomé). Pour un rendez-vous, elle note le nom, la prestation, le jour et l'heure, et dit que le salon confirmera par téléphone.";
const QUESTIONS = ['Bonjour, combien coûtent les tresses africaines longues ?', 'Je voudrais un rendez-vous samedi à 10 h pour un brushing, je m\'appelle Ama.'];

async function connecter(contexte) {
  const page = await contexte.newPage();
  await page.goto(base + '/login');
  await page.fill('input[type=email]', 'afi-agent@exemple.tg');
  await page.fill('input[type=password]', 'motdepasse-solide-2026');
  await page.click('button[type=submit]');
  await page.waitForURL('**/projets**');
  return page;
}

/** Pose une question à l'agent dans le panneau « Tester l'agent » et attend sa réponse (ou la note d'erreur). */
async function demanderAgent(page, question) {
  const cadre = page.frameLocator('[data-panel="apercu"] iframe');
  const avant = await cadre.locator('.message.agent, .note').count();
  await cadre.locator('#texte').fill(question);
  await cadre.locator('#envoyer').click();
  for (let i = 0; i < 90; i++) {
    await attendre(1000);
    const notes = await cadre.locator('.note').allInnerTexts();
    const n = await cadre.locator('.message.agent, .note').count();
    if (n > avant && !notes.includes('…')) break;
  }
  const textes = await cadre.locator('.message.agent, .note').allInnerTexts();
  return textes.at(-1);
}

(async () => {
  mkdirSync(dossier, { recursive: true });
  const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] });
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' });
  const page = await connecter(contexte);
  const capture = (nom) => page.screenshot({ path: `${dossier}/telephone-${nom}.png` });
  const debut = Date.now();
  const temps = () => `${Math.round((Date.now() - debut) / 1000)} s`;
  const journal = { questions: [] };

  // 1. La demande, puis le plan.
  await page.locator('textarea').first().fill(DEMANDE);
  await page.locator('[data-prompt-send]').first().click();
  await page.waitForURL('**/projets/**');
  await page.locator('[data-build-plan]').waitFor({ timeout: 240000 });
  console.log('Plan proposé', temps());
  await attendre(800);
  await capture('1-plan');
  const projet = page.url().split('/projets/')[1].split('?')[0];

  // 2. « Construire ce plan ».
  await page.locator('[data-build-plan]').click();
  let pendant = false;
  let statut = null;
  while (Date.now() - debut < 50 * 60000) {
    await attendre(5000);
    const cartes = page.locator('[data-run-status]');
    statut = (await cartes.count()) ? await cartes.last().getAttribute('data-run-status') : null;
    if (!pendant && statut === 'running' && (await page.locator('[data-run-working]').count())) {
      await attendre(20000);
      await capture('2-construction-en-cours');
      pendant = true;
    }
    if (['completed', 'failed', 'stopped'].includes(statut)) break;
  }
  journal.statut = statut;
  journal.construction_secondes = Math.round((Date.now() - debut) / 1000);
  console.log('Construction terminée', statut, temps());
  await attendre(1500);
  const actions = page.locator('[data-narration] summary').last();
  if (await actions.count()) await actions.click();
  await attendre(500);
  await capture('3-construction-terminee');

  // 3. « Tester l'agent » : vraies questions avec l'essai gratuit.
  await page.goto(`${base}/projets/${projet}?vue=apercu`);
  await page.locator('[data-panel="apercu"] iframe').waitFor({ timeout: 180000 });
  await attendre(4000);
  for (const question of QUESTIONS) {
    const reponse = await demanderAgent(page, question);
    journal.questions.push({ question, reponse });
    console.log('Q :', question, '\nR :', reponse);
  }
  await page.reload();
  await page.locator('[data-panel="apercu"] iframe').waitFor({ timeout: 120000 });
  await attendre(4000);
  // La conversation est retrouvée par la page de l'agent (même appareil) : la réponse reste visible.
  await capture('4-tester-agent');

  // 4. Essai gratuit terminé : la limite du jour ramenée aux messages déjà utilisés, puis une question de plus.
  tinker(`$u = App\\Models\\User::where('email', 'afi-agent@exemple.tg')->first(); app(App\\Support\\Settings::class)->set('agent_trial_messages_per_day', app(App\\Agent\\AgentTrial::class)->usedToday($u));`);
  journal.essai_termine = await demanderAgent(page, 'Et le soin des cheveux, combien ?');
  console.log('Essai terminé :', journal.essai_termine);
  await page.reload();
  await page.locator('[data-panel="apercu"] iframe').waitFor({ timeout: 120000 });
  await attendre(4000);
  await capture('5-essai-termine');

  // 5. Les mêmes écrans sur ordinateur et en sombre.
  for (const [nom, reglages] of [['telephone-sombre', { ...devices['Pixel 7'], colorScheme: 'dark' }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'light' }], ['ordinateur-sombre', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'dark' }]]) {
    const autre = await navigateur.newContext({ ...reglages, locale: 'fr-FR' });
    const p = await connecter(autre);
    await p.goto(`${base}/projets/${projet}`);
    await attendre(1500);
    // Le plan : on remonte la discussion jusqu'à lui.
    await p.locator('[data-plan-actions], [data-question-answer]').first().scrollIntoViewIfNeeded().catch(() => {});
    await p.screenshot({ path: `${dossier}/${nom}-1-plan.png` });
    const a = p.locator('[data-narration] summary').last();
    if (await a.count()) {
      await a.click();
      await attendre(400);
      await a.scrollIntoViewIfNeeded();
    }
    await p.screenshot({ path: `${dossier}/${nom}-3-construction-terminee.png` });
    await p.goto(`${base}/projets/${projet}?vue=apercu`);
    await p.locator('[data-panel="apercu"] iframe').waitFor({ timeout: 120000 }).catch(() => {});
    await attendre(5000);
    await p.screenshot({ path: `${dossier}/${nom}-5-essai-termine.png` });
    await autre.close();
  }

  // Une réponse de l'agent sur ordinateur et en sombre : la limite remise, une question de plus.
  tinker(`app(App\\Support\\Settings::class)->set('agent_trial_messages_per_day', 10);`);
  for (const [nom, reglages] of [['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'light' }], ['telephone-sombre', { ...devices['Pixel 7'], colorScheme: 'dark' }], ['ordinateur-sombre', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'dark' }]]) {
    const autre = await navigateur.newContext({ ...reglages, locale: 'fr-FR' });
    const p = await connecter(autre);
    await p.goto(`${base}/projets/${projet}?vue=apercu`);
    await p.locator('[data-panel="apercu"] iframe').waitFor({ timeout: 120000 });
    await attendre(4000);
    const r = await demanderAgent(p, 'Quels sont vos horaires ?');
    journal.questions.push({ question: `Quels sont vos horaires ? (${nom})`, reponse: r });
    await p.screenshot({ path: `${dossier}/${nom}-4-tester-agent.png` });
    await autre.close();
  }

  journal.projet = projet;
  journal.secondes = Math.round((Date.now() - debut) / 1000);
  writeFileSync(`${dossier}/essai.json`, JSON.stringify(journal, null, 2) + '\n');
  console.log('Fini', temps());
  await navigateur.close();
})();
