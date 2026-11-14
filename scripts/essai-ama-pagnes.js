// Essai réel après les finitions de la pull request 51 : la demande « Boutique Ama Pagnes » envoyée à Kekeli comme un
// client (plan proposé par Kekeli, puis « Construire ce plan »), les 10 mêmes questions d'essai que la mesure
// « équipe contre agent seul », et la vérification qu'aucun « ** » n'est visible dans la page de l'agent.
// Le site, la file d'attente et le Runner doivent tourner (avec le même KEKELI_RUNNER_SECRET).
//
//   php artisan tinker --execute="require 'scripts/preparer-essai-equipe.php';"
//   node scripts/essai-ama-pagnes.js docs/captures/memoire
import { createRequire } from 'node:module'
import { mkdirSync, writeFileSync, existsSync, readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'
import { DEMANDE, QUESTIONS, connecter, demander, cadreAgent } from './essai-equipe.js'

const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const dossier = process.argv[2] || 'docs/captures/memoire'
const base = process.env.KEKELI_URL || 'http://127.0.0.1:8123'
const SORTIE = 'runner/mesures/ama-pagnes-apres.json'
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim()
const statut = (projet) => tinker(`echo optional(App\\Models\\Project::find('${projet}')->agentRuns()->whereNull('purpose')->latest('created_at')->first())->status;`)

/** Une réponse juste : tout ce qu'elle doit contenir, rien de ce qu'elle ne doit jamais contenir. */
export const juste = (question, reponse) => question.doit.every((r) => r.test(reponse)) && !question.jamais.some((r) => r.test(reponse))

mkdirSync(dossier, { recursive: true })
const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] })
const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })
const page = await connecter(contexte)
const debut = Date.now()
const temps = () => `${Math.round((Date.now() - debut) / 1000)} s`
const resultat = existsSync(SORTIE) && process.env.PROJET ? JSON.parse(readFileSync(SORTIE, 'utf8')) : { demande: DEMANDE }

if (!process.env.PROJET) {
  await page.locator('textarea').first().fill(DEMANDE)
  await page.locator('[data-prompt-send]').first().click()
  await page.waitForURL('**/projets/**')
  await page.locator('[data-build-plan]').waitFor({ timeout: 300000 })
  await attendre(800)
  resultat.projet = page.url().split('/projets/')[1].split('?')[0]
  resultat.plan = tinker(`echo data_get(app(App\\Agent\\Tools\\CreateAgent::class)->lastOffer(App\\Models\\Project::find('${resultat.projet}')), 'report.plan');`)
  resultat.equipe = /(^|\n)\s*[-*#]*\s*\**\s*Équipe\b|coordinateur/iu.test(resultat.plan)
  console.log('Plan proposé', temps(), resultat.equipe ? '(équipe)' : '(un seul agent)', '\n' + resultat.plan)
  await page.locator('[data-build-plan]').click()
  while (Date.now() - debut < 60 * 60000) {
    await attendre(15000)
    const s = statut(resultat.projet)
    console.log(temps(), s)
    if (['completed', 'failed', 'stopped'].includes(s)) break
  }
  resultat.construction = JSON.parse(tinker(`$r = App\\Models\\Project::find('${resultat.projet}')->agentRuns()->whereNull('purpose')->latest('created_at')->first(); echo json_encode(['statut' => $r->status, 'modele' => $r->provider.'/'.$r->model, 'minutes' => round($r->started_at?->diffInSeconds($r->finished_at) / 60, 1), 'tests' => data_get($r->report, 'tests'), 'cout_modele_usd' => (float) $r->model_cost_usd]);`).split('\n').pop() || '{}')
  writeFileSync(SORTIE, JSON.stringify(resultat, null, 2) + '\n')
} else {
  resultat.projet = process.env.PROJET
}

// Les 10 questions, une conversation neuve chacune, avec le modèle gratuit de l'essai.
const projet = resultat.projet
tinker(`$p = App\\Models\\Project::find('${projet}'); $p->forceFill(['preview_url' => null, 'preview_expires_at' => null])->save(); App\\Jobs\\WakePreview::dispatch($p);`)
for (let i = 0; i < 60 && !tinker(`echo App\\Models\\Project::find('${projet}')->preview_url;`); i++) await attendre(10000)
const cadre = await cadreAgent(page, projet)
resultat.reponses = []
for (const question of QUESTIONS) {
  let r = { statut: 0, reponse: 'aperçu introuvable' }
  for (let essai = 0; cadre && essai < 4; essai++) {
    r = await demander(cadre, question.q).catch((err) => ({ statut: 0, reponse: String(err.message) }))
    if (r.statut !== 429) break
    await attendre(65000)
  }
  await attendre(20000)
  resultat.reponses.push({ question: question.q, ...r, juste_automatique: juste(question, r.reponse), asterisques_brutes: /\*\*/.test(r.reponse) })
  console.log(juste(question, r.reponse) ? 'oui' : 'NON', '|', question.q, '\n  →', r.statut, r.reponse.replace(/\s+/g, ' ').slice(0, 300))
  writeFileSync(SORTIE, JSON.stringify(resultat, null, 2) + '\n')
}
resultat.justes_automatique = resultat.reponses.filter((r) => r.juste_automatique).length

// La page de l'agent (téléphone et ordinateur, clair et sombre) : une facture détaillée, sans aucun « ** » visible.
resultat.asterisques_visibles = 0
for (const [nom, reglages] of [['telephone', { ...devices['Pixel 7'], colorScheme: 'light' }], ['telephone-sombre', { ...devices['Pixel 7'], colorScheme: 'dark' }], ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'light' }], ['ordinateur-sombre', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: 'dark' }]]) {
  const autre = await navigateur.newContext({ ...reglages, locale: 'fr-FR' })
  const p = await connecter(autre)
  await p.goto(`${base}/projets/${projet}?vue=apercu`)
  await p.locator('[data-panel="apercu"] iframe').waitFor({ timeout: 180000 }).catch(() => {})
  await attendre(5000)
  const cadrePage = p.frameLocator('[data-panel="apercu"] iframe')
  await cadrePage.locator('#texte').fill('Je veux 2 Kente et 1 Wax Vlisco, livrés à Lomé. Faites-moi la facture.')
  await cadrePage.locator('#envoyer').click()
  await cadrePage.locator('.message.agent').nth(1).waitFor({ timeout: 180000 }).catch(() => {})
  await attendre(1500)
  const textes = await cadrePage.locator('.message.agent').allInnerTexts().catch(() => [])
  resultat.asterisques_visibles += textes.filter((t) => t.includes('*')).length
  resultat[`texte_${nom}`] = textes.at(-1) ?? ''
  await p.screenshot({ path: `${dossier}/${nom}-agent-sans-asterisques.png` })
  await autre.close()
  await attendre(15000)
}
writeFileSync(SORTIE, JSON.stringify(resultat, null, 2) + '\n')
console.log(`Réponses justes (vérification automatique) : ${resultat.justes_automatique} sur 10 ; bulles avec un astérisque visible : ${resultat.asterisques_visibles}`, temps())
await navigateur.close()
