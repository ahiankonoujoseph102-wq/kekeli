// Essai réel de la base de connaissances (18 novembre 2026), comme un client sur son téléphone : site local, file
// d'attente, Runner local, vrais documents inventés (PDF de 4 pages, Word, PowerPoint), lecture dans un vrai ordinateur
// E2B isolé, vrais vecteurs gratuits, vrai modèle gratuit. Captures dans docs/captures/base-de-connaissances/, réponses
// dans runner/mesures/base-connaissances-essai.json.
//   php artisan tinker --execute="require 'scripts/preparer-essai-base.php';"
//   node scripts/essai-base-connaissances.js http://127.0.0.1:8123 <dossier des documents d'essai>
import { createRequire } from 'node:module'
import { mkdirSync, writeFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'
import path from 'node:path'

const { chromium, devices } = createRequire(import.meta.url)('playwright')
const [base = 'http://127.0.0.1:8123', documents = 'docs/captures/base-de-connaissances/documents'] = process.argv.slice(2)
const dossier = 'docs/captures/base-de-connaissances'
mkdirSync(dossier, { recursive: true })
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim()
const resultats = { questions: [], isolation: {}, suppression: {} }

async function connecter(navigateur, compte) {
  const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })
  const page = await contexte.newPage()
  await page.goto(`${base}/login`)
  await page.locator('[data-password-login]').click()
  await page.waitForSelector('[data-password-submit]')
  await page.locator('input[type=email]').fill(compte)
  await page.locator('input[type=password]').fill('motdepasse-solide-2026')
  await page.locator('[data-password-submit]').click()
  await page.waitForURL((u) => !u.pathname.startsWith('/login'), { timeout: 20000 })
  return page
}

const capture = async (page, nom, pleine = false) => {
  await page.waitForTimeout(600)
  await page.screenshot({ path: `${dossier}/${nom}.png`, fullPage: pleine })
}

/** Attend la réponse n (1, 2, 3…) de la conversation, puis la rend avec ses sources. */
async function reponse(page, n) {
  await page.waitForFunction((n) => document.querySelectorAll('[data-question-answer]').length >= n && document.querySelector('[data-regenerate]'), n, { timeout: 180000 })
  await page.waitForTimeout(800)
  const bloc = page.locator('[data-question-answer]').nth(n - 1)
  const texte = (await bloc.innerText()).trim()
  const rapport = bloc.locator('xpath=ancestor::*[.//*[@data-question-answer]][1]')
  const sources = await page.locator('[data-knowledge-sources]').last().innerText().catch(() => '')
  return { texte, sources: sources.trim(), pastilles: await rapport.locator('[data-citation-document]').count() }
}

;(async () => {
  const navigateur = await chromium.launch()
  const ama = await connecter(navigateur, 'ama-base@exemple.tg')

  // 1. La base vide, puis un espace « Entreprise » créé d'un toucher.
  await ama.goto(`${base}/connaissances`)
  await capture(ama, '01-base-vide')
  await ama.locator('[data-knowledge-suggestion]', { hasText: 'Entreprise' }).tap()
  await ama.waitForURL(/connaissances\/[0-9A-Z]+/i)
  await ama.waitForLoadState('networkidle')
  await capture(ama, '02-espace-vide')

  // 2. Importation de trois vrais documents ; la liste se met à jour seule.
  const debut = Date.now()
  await ama.locator('[data-knowledge-file]').setInputFiles(['catalogue-ama-pagnes.pdf', 'guide-employe.docx', 'presentation.pptx'].map((f) => path.join(documents, f)))
  await ama.waitForSelector('[data-knowledge-document]', { timeout: 60000 })
  await ama.waitForTimeout(1500)
  await capture(ama, '03-traitement')
  await ama.waitForFunction(() => [...document.querySelectorAll('[data-knowledge-document]')].length === 3 && [...document.querySelectorAll('[data-knowledge-document]')].every((d) => ['ready', 'failed'].includes(d.dataset.knowledgeDocument)), null, { timeout: 300000 })
  resultats.importation_secondes = Math.round((Date.now() - debut) / 1000)
  resultats.etats = await ama.locator('[data-knowledge-document]').evaluateAll((l) => l.map((d) => d.dataset.knowledgeDocument))
  await capture(ama, '04-documents-prets', true)

  // 3. Une question depuis l'espace, puis des questions de suivi dans la conversation.
  const questions = [
    'Combien coûte la livraison vers Kara, et comment je peux payer ?',
    'Et pour Lomé ?',
    'Combien de jours de congé a un employé, et à qui faut-il demander ?',
    'Quel est le numéro de compte bancaire de la boutique ?',
  ]
  await ama.locator('[data-knowledge-question]').fill(questions[0])
  await ama.locator('[data-knowledge-ask-button]').tap()
  await ama.waitForURL(/projets\//, { timeout: 30000 })
  for (const [index, question] of questions.entries()) {
    if (index > 0) {
      await ama.locator('textarea[data-message-input]').fill(question)
      await ama.locator('[data-prompt-send]').first().tap()
    }
    const debutQuestion = Date.now()
    const r = await reponse(ama, index + 1)
    resultats.questions.push({ question, secondes: Math.round((Date.now() - debutQuestion) / 100) / 10, ...r })
    console.log(`\n— ${question}\n${r.texte}\n[${r.sources.replace(/\n/g, ' | ')}]`)
    if (index === 0) {
      await capture(ama, '05-reponse-avec-sources')
      const source = ama.locator('[data-knowledge-source] summary').first()
      if (await source.count()) {
        await source.tap()
        await ama.waitForTimeout(300)
        await ama.locator('[data-knowledge-sources]').last().scrollIntoViewIfNeeded()
        await capture(ama, '06-source-depliee')
      }
    }
  }
  await capture(ama, '07-conversation-suivi')
  await ama.locator('[data-question-answer]').last().scrollIntoViewIfNeeded()
  await capture(ama, '08-information-absente')
  const conversation = ama.url()

  // 4. Choix des documents de la conversation.
  await ama.locator('[data-knowledge-documents-used]').tap()
  await ama.waitForTimeout(400)
  await capture(ama, '09-choix-des-documents')
  await ama.keyboard.press('Escape')

  // 5. Isolation : un autre client n'atteint ni l'espace, ni la conversation d'Ama.
  const espace = tinker("echo App\\Models\\KnowledgeSpace::whereHas('user', fn ($q) => $q->where('email', 'ama-base@exemple.tg'))->value('id');")
  const kossi = await connecter(navigateur, 'kossi-base@exemple.tg')
  resultats.isolation.espace = (await kossi.goto(`${base}/connaissances/${espace}`)).status()
  resultats.isolation.conversation = (await kossi.goto(conversation)).status()
  await kossi.goto(`${base}/connaissances`)
  resultats.isolation.liste_vide = (await kossi.locator('[data-knowledge-empty]').count()) === 1
  await capture(kossi, '10-autre-client')

  // 6. Suppression d'un document : le fichier, ses passages et ses vecteurs disparaissent.
  const compte = (nom) => Number(tinker(`echo App\\Models\\KnowledgeChunk::whereHas('document', fn ($q) => $q->where('name', '${nom}'))->count();`))
  resultats.suppression.passages_avant = compte('presentation.pptx')
  await ama.goto(`${base}/connaissances/${espace}`)
  await ama.waitForLoadState('networkidle')
  const ligne = ama.locator('[data-knowledge-document]', { hasText: 'presentation.pptx' })
  await ligne.locator('[data-knowledge-document-actions]').tap()
  await ama.locator('[data-knowledge-delete]').filter({ visible: true }).first().tap()
  await ama.locator('[data-knowledge-delete-confirm]').filter({ visible: true }).first().tap()
  await ama.waitForTimeout(1500)
  resultats.suppression.passages_apres = compte('presentation.pptx')
  resultats.suppression.documents_restants = await ama.locator('[data-knowledge-document]').count()
  await capture(ama, '11-apres-suppression', true)
  await ama.goto(`${base}/connaissances`)
  await capture(ama, '12-liste-des-espaces')

  writeFileSync('runner/mesures/base-connaissances-essai.json', JSON.stringify(resultats, null, 2) + '\n')
  console.log('\n', JSON.stringify({ ...resultats, questions: resultats.questions.map((q) => ({ question: q.question, secondes: q.secondes, pastilles: q.pastilles })) }, null, 2))
  await navigateur.close()
})()
