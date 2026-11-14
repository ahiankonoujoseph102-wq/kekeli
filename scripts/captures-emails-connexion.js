// Captures « E-mails avec Brevo, reçus et connexion comme Claude » : page d'entrée (sans Google, et avec Google sur
// un second site lancé avec des identifiants Google inventés), saisie du code, étape du nom, « Mes reçus », journal
// des envois, chaque e-mail tel qu'il s'affiche sur un téléphone, et l'avant et l'après du masquage d'un mot de passe.
// Téléphone (Pixel 7) et ordinateur, clair et sombre. Données inventées.
// Le site (port 8123, envoi simulé MAIL_MAILER=log), sa file et le Runner tournent ; le second site (port 8124) aussi :
//   GOOGLE_CLIENT_ID=identifiant-invente GOOGLE_CLIENT_SECRET=secret-invente php artisan serve --port=8124
//   php artisan tinker --execute="require 'scripts/essai-recu-reel.php';"
//   php artisan tinker --execute="require 'scripts/preparer-captures-emails.php';"
//   node scripts/captures-emails-connexion.js
import { createRequire } from 'node:module'
import { mkdirSync, readFileSync, readdirSync } from 'node:fs'
import { execFileSync } from 'node:child_process'

const require = createRequire(import.meta.url)
const { chromium, devices } = require('playwright')
const base = 'http://127.0.0.1:8123'
const avecGoogle = 'http://127.0.0.1:8124'
const dossier = 'docs/captures/emails-connexion'
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code]).toString().trim()
mkdirSync(dossier, { recursive: true })

/** Les codes déjà envoyés (envoi simulé : chaque e-mail est écrit dans le journal de Laravel). */
const codes = () => [...readFileSync('storage/logs/laravel.log', 'utf8').matchAll(/Votre code Kekeli : (\d{6})/g)].map((m) => m[1])

/** Envoie l'adresse, puis attend le nouvel e-mail (il passe par la file d'attente) et rend son code. */
async function demanderCode(p, adresse) {
  const avant = codes().length
  await p.fill('input[type=email]', adresse)
  await p.click('[data-continue-email]')
  await p.waitForSelector('[data-login-code]')
  for (let i = 0; i < 60 && codes().length <= avant; i++) await attendre(1000)
  return codes().at(-1)
}

const APPAREILS = [
  ['telephone', { ...devices['Pixel 7'] }],
  ['ordinateur', { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 }],
]
const navigateur = await chromium.launch({ args: ['--lang=fr-FR'] })
const erreurs = []

for (const theme of ['light', 'dark']) {
  for (const [appareil, reglages] of APPAREILS) {
    const nom = (n) => `${dossier}/${n}-${appareil}${theme === 'dark' ? '-sombre' : ''}.png`
    const nouveau = async () => {
      const c = await navigateur.newContext({ ...reglages, locale: 'fr-FR', colorScheme: theme })
      const p = await c.newPage()
      p.on('pageerror', (e) => erreurs.push(e.message))
      return [c, p]
    }

    // 1. La page d'entrée, sans Google (identifiants absents) puis avec Google (second site).
    let [c, p] = await nouveau()
    await p.goto(base + '/login')
    await attendre(600)
    await p.screenshot({ path: nom('1-entree-sans-google') })
    await p.goto(avecGoogle + '/login')
    await attendre(600)
    await p.screenshot({ path: nom('2-entree-avec-google') })
    await c.close()

    // 2. Un nouveau compte : l'e-mail, le code, puis le nom en une étape.
    ;[c, p] = await nouveau()
    await p.goto(base + '/login')
    const adresse = `nouveau-${appareil}-${theme}@exemple.tg`
    const code = await demanderCode(p, adresse)
    await p.screenshot({ path: nom('3-saisie-du-code') })
    await p.fill('input[autocomplete=one-time-code]', code)
    await p.click('[data-verify-code]')
    await p.waitForSelector('[data-signup-name]')
    await attendre(600)
    await p.screenshot({ path: nom('4-nouveau-compte-nom') })
    await c.close()

    // 3. Le client d'essai se connecte par code et retrouve son reçu dans Réglages → Formule → « Mes reçus ».
    ;[c, p] = await nouveau()
    await p.goto(base + '/login')
    await p.fill('input[autocomplete=one-time-code]', await demanderCode(p, 'afi.mensah@exemple.tg'))
    await p.click('[data-verify-code]')
    await p.waitForURL('**/projets**')
    await p.goto(base + '/settings/formule')
    await p.locator('[data-my-receipts]').scrollIntoViewIfNeeded()
    await attendre(800)
    await p.screenshot({ path: nom('5-mes-recus'), fullPage: appareil === 'telephone' })
    await c.close()

    // 4. Le journal des envois (administrateur d'essai, avec son mot de passe).
    ;[c, p] = await nouveau()
    await p.goto(base + '/login?etape=mot-de-passe')
    await p.fill('input[type=email]', 'admin-essai@exemple.tg')
    await p.fill('input[type=password]', 'motdepasse-admin-2026')
    await p.click('[data-password-submit]')
    await p.waitForURL('**/projets**')
    await p.goto(base + '/administration/emails')
    await attendre(800)
    await p.screenshot({ path: nom('6-journal-des-envois') })
    await p.goto(base + '/administration/reglages')
    await p.locator('[data-settings-emails]').scrollIntoViewIfNeeded()
    await attendre(600)
    await p.locator('[data-settings-emails]').screenshot({ path: nom('7-reglages-emails-et-recus') })
    await c.close()

    // 5. Chaque e-mail tel qu'il s'affiche sur un téléphone (clair et sombre).
    if (appareil === 'telephone') {
      ;[c, p] = await nouveau()
      for (const fichier of readdirSync('storage/app/captures-emails').filter((f) => f.endsWith('.html')).sort()) {
        await p.goto('file://' + process.cwd() + '/storage/app/captures-emails/' + fichier)
        await attendre(300)
        await p.screenshot({ path: `${dossier}/email-${fichier.replace('.html', '')}${theme === 'dark' ? '-sombre' : ''}.png`, fullPage: true })
      }
      await c.close()
    }
  }
}

// 6. Avant et après la finition : un mot de passe annoncé dans un message (téléphone, clair).
const projet = tinker(`
  $u = App\\Models\\User::where('email', 'afi.mensah@exemple.tg')->first();
  $p = $u->projects()->make(['name' => 'Avant : message enregistré tel quel']);
  $p->forceFill(['kind' => App\\Models\\Project::KIND_CHAT])->save();
  $p->messages()->create(['role' => 'user', 'content' => 'Pour info, mon mot de passe Kekeli est Pagne2026! et le code SMS reçu est 482913.']);
  echo $p->id;`).split('\n').pop()
const c = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR', colorScheme: 'light' })
const p = await c.newPage()
await p.goto(base + '/login')
await p.fill('input[autocomplete=one-time-code]', await demanderCode(p, 'afi.mensah@exemple.tg'))
await p.click('[data-verify-code]')
await p.waitForURL('**/projets**')
await p.goto(`${base}/projets/${projet}`)
await attendre(1000)
await p.screenshot({ path: `${dossier}/masquage-avant-telephone.png` })
await p.goto(base + '/projets')
await p.locator('textarea').first().fill('Pour info, mon mot de passe Kekeli est Pagne2026! et le code SMS reçu est 482913.')
await p.locator('[data-prompt-send]').first().click()
await p.waitForURL('**/projets/**')
await attendre(8000)
await p.screenshot({ path: `${dossier}/masquage-apres-telephone.png` })
await c.close()

await navigateur.close()
console.log(erreurs.length ? `Erreurs de la page : ${[...new Set(erreurs)].join(' | ')}` : 'Aucune erreur de la page.')
