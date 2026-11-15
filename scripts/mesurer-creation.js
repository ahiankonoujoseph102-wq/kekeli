// Mesure réelle d'une création (site vitrine) comme un client la vit : on écrit la demande sur l'accueil d'un téléphone
// simulé, puis on note, en secondes depuis l'envoi, quand le travail commence, quand la première page existe dans
// l'aperçu (l'aperçu répond 200), et quand le travail est fini ; le coût réel (modèle, ordinateur, images) est relu
// dans la base. Le site, la file d'attente et le Runner (avec E2B) doivent tourner. Comptes inventés.
//
//   node scripts/mesurer-creation.js <adresse du site> <sortie.json> "<demande>" [nom court]
// Variables : COMPTE (adresse du compte, créé s'il manque), REPONSES (réponses aux questions de Kekeli, séparées par
// « | », ou « choisir » pour toucher « Choisis pour moi »).
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname } from 'node:path';

const { chromium, devices } = createRequire(import.meta.url)('playwright');
const [base, sortie, demande, nom = 'site'] = process.argv.slice(2);
const compte = process.env.COMPTE || 'kossi-creation@exemple.tg';
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { maxBuffer: 1 << 26 }).toString().trim();
const attendre = (ms) => new Promise((r) => setTimeout(r, ms));
const secondes = (ms) => Math.round(ms / 100) / 10;

tinker(`
  $u = App\\Models\\User::firstOrNew(['email' => '${compte}']);
  $u->forceFill(['name' => 'Kossi Mensah', 'password' => bcrypt('motdepasse-solide-2026'), 'email_verified_at' => now(), 'country' => 'TG', 'terms_accepted_at' => now(), 'plan' => 'gratuite', 'appearance' => 'light'])->save();
`);

const etat = (avant) => JSON.parse(tinker(`
  $u = App\\Models\\User::where('email', '${compte}')->first();
  $p = $u->projects()->latest('created_at')->first();
  $run = $p ? $p->agentRuns()->where('created_at', '>', '${avant}')->where(fn ($w) => $w->whereNull('purpose')->orWhere('purpose', '!=', 'question'))->latest('created_at')->first() : null;
  $q = $p ? $p->agentRuns()->where('created_at', '>', '${avant}')->latest('created_at')->first() : null;
  $taux = app(App\\Support\\Settings::class)->float('usd_to_fcfa');
  echo json_encode([
    'projet' => $p?->id,
    'derniere' => $q ? ['statut' => $q->status, 'question' => $q->purpose === 'question', 'rapport' => $q->report] : null,
    'statut' => $run?->status, 'raison' => $run?->stop_reason, 'debut' => $run?->started_at?->toIso8601String(),
    'apercu' => $p ? App\\Agent\\PreviewToken::url($p) : null,
    'fcfa' => $run ? round(($run->model_cost_usd + $run->computer_cost_usd + $run->image_cost_usd + $run->web_search_cost_usd) * $taux, 1) : 0,
    'modele_fcfa' => $run ? round($run->model_cost_usd * $taux, 1) : 0,
    'ordinateur_fcfa' => $run ? round($run->computer_cost_usd * $taux, 1) : 0,
    'images_fcfa' => $run ? round($run->image_cost_usd * $taux, 1) : 0,
    'requetes' => $run?->requests, 'modeles' => $run ? App\\Models\\ProviderUsage::query()->where('updated_at', '>=', $run->created_at)->pluck('model')->unique()->values() : [],
    'actions' => $run ? $run->steps()->count() : 0,
    'rapport' => $run?->report,
  ]);
`));

(async () => {
  const navigateur = await chromium.launch();
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' });
  const page = await contexte.newPage();
  await page.goto(`${base}/login`);
  await page.locator('[data-password-login]').click();
  await page.locator('input[type=email]').fill(compte);
  await page.locator('input[type=password]').fill('motdepasse-solide-2026');
  await page.locator('[data-password-submit]').click();
  await page.waitForURL((u) => !u.pathname.startsWith('/login'));
  await page.goto(`${base}/projets`);
  const avant = new Date(Date.now() - 1000).toISOString().replace('T', ' ').slice(0, 19);
  await page.locator('[data-site-prompt-input]').fill(demande);
  const t0 = Date.now();
  await page.locator('[data-prompt-send]').first().click();
  await page.waitForURL(/\/projets\/[0-9a-z]{26}/, { timeout: 60000 });

  const mesures = { demande, debut_travail_s: null, premier_apercu_s: null, fin_s: null, questions: null };
  let reponses = (process.env.REPONSES || '').split('|').filter(Boolean);
  let e = etat(avant);
  while (Date.now() - t0 < 40 * 60000) {
    e = etat(avant);
    // Kekeli pose des questions : réponses de l'essai, ou « Choisis pour moi ».
    if (!e.statut && e.derniere?.question && e.derniere.statut === 'completed' && e.derniere.rapport?.questions && mesures.questions === null) {
      mesures.questions = { secondes: secondes(Date.now() - t0), questions: e.derniere.rapport.questions };
      if (reponses[0] === 'choisir' || !reponses.length) {
        await page.locator('[data-choose-for-me]').last().click();
      } else {
        await page.locator('[data-prompt-input], textarea').last().fill(reponses.join('. '));
        await page.locator('[data-prompt-send]').last().click();
      }
      reponses = [];
    }
    if (e.statut && !mesures.debut_travail_s) mesures.debut_travail_s = secondes(Date.now() - t0);
    if (e.apercu && !mesures.premier_apercu_s) {
      const r = await fetch(e.apercu).catch(() => null);
      if (r?.status === 200) mesures.premier_apercu_s = secondes(Date.now() - t0);
    }
    if (['completed', 'failed', 'stopped'].includes(e.statut)) break;
    await attendre(2000);
  }
  mesures.fin_s = secondes(Date.now() - t0);
  e = etat(avant);
  await attendre(1500);
  mkdirSync(dirname(sortie), { recursive: true });
  await page.screenshot({ path: sortie.replace(/\.json$/, '.png') });
  const resultat = { nom, ...mesures, statut: e.statut, raison: e.raison, fcfa: e.fcfa, modele_fcfa: e.modele_fcfa, ordinateur_fcfa: e.ordinateur_fcfa, images_fcfa: e.images_fcfa, requetes: e.requetes, actions: e.actions, projet: e.projet, compte_rendu: e.rapport?.done ?? null };
  const liste = existsSync(sortie) ? JSON.parse(readFileSync(sortie)) : [];
  writeFileSync(sortie, JSON.stringify([...liste.filter((x) => x.nom !== nom), resultat], null, 2) + '\n');
  console.log(JSON.stringify({ ...resultat, compte_rendu: undefined }));
  await navigateur.close();
})();
