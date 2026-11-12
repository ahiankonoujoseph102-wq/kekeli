// Banc d'essai réel : la même petite application (prise de rendez-vous du salon « Belle Afi ») construite par plusieurs
// modèles, chacun dans son propre ordinateur E2B, exactement comme pour un client (docs/plan.md, partie 27).
//   KEKELI_RUNNER_SECRET=… node runner/scripts/banc-construction.mjs gemini/gemini-3.5-flash-lite openai/gpt-6.1-terra …
// OpenAI passe par le relais de l'ordinateur et la passerelle du Runner : la clé n'entre jamais dans l'ordinateur.
// Après la construction : tests, règles de sécurité, pages privées sans connexion, captures sur un téléphone simulé.
// Résultats : runner/mesures/banc-construction.json ; captures : $BANC/captures/<modèle>/.
// BUDGET_OPENAI (dollars, 2 par défaut) : un modèle OpenAI n'est pas lancé si la dépense des essais en approche.
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { creerGestionnaireOrdinateurs, synchroniserSecrets } from '../src/ordinateurs.js'
import { executerTache } from '../src/agent.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { MisesDeCote } from '../src/limites.js'
import { cleApercu, jetonApercu } from '../src/apercu/serveur.mjs'

const BANC = process.env.BANC ?? '/tmp/claude-0/banc'
const RESULTATS = process.env.RESULTATS ? new URL(process.env.RESULTATS, 'file://') : new URL('../mesures/banc-construction.json', import.meta.url)
const BUDGET = Number(process.env.BUDGET_OPENAI ?? 2)
const secret = process.env.KEKELI_RUNNER_SECRET
if (!secret) throw new Error('KEKELI_RUNNER_SECRET manque.')

// Prix officiels (dollars par million de tokens, pages lues le 12 novembre 2026).
const PRIX = {
  'gemini/gemini-3.5-flash-lite': { entree: 0.3, sortie: 2.5, cache: 0.03 },
  'gemini/gemini-3.6-flash': { entree: 0.75, sortie: 3.75, cache: 0.075 },
  'openai/gpt-6-luna': { entree: 0.1, sortie: 0.5, cache: 0.01 },
  'openai/gpt-6.1-terra': { entree: 1, sortie: 5, cache: 0.05 },
  'openai/gpt-6.1-sol': { entree: 2, sortie: 10, cache: 0.1 },
}

export const DEMANDE = `Crée une application de prise de rendez-vous pour mon salon de coiffure « Belle Afi » à Lomé.

Plan validé par la cliente :
- Pages : accueil (prestations et prix en FCFA), inscription et connexion, « Mes rendez-vous » (liste, prise de rendez-vous, annulation), administration.
- Données : prestations (nom, durée en minutes, prix en FCFA) ; rendez-vous (cliente, prestation, date, heure, état : en attente, confirmé, refusé ou annulé, note facultative).
- Rôles : cliente (voit et annule seulement ses rendez-vous) ; administrateur (voit tous les rendez-vous, les confirme ou les refuse, gère les prestations).
- Règles : pas de rendez-vous dans le passé, pas deux rendez-vous à la même heure, horaires du salon de 8 h à 19 h.
- Tests de chaque fonction, dont un test qui vérifie qu'une cliente ne voit pas les rendez-vous d'une autre.`

const resultats = existsSync(RESULTATS) ? JSON.parse(readFileSync(RESULTATS, 'utf8')) : { essais: [] }
const depenseOpenAI = () => resultats.essais.filter((e) => e.modele.startsWith('openai/')).reduce((t, e) => t + (e.cout_usd ?? 0), 0)
const clesPresentes = fournisseursAvecCle()
await synchroniserSecrets(clesPresentes)
const ordinateurs = creerGestionnaireOrdinateurs({ secretRunner: secret, clesPresentes, journal: { log() {}, warn: (m) => console.log('  attention :', m), error: (m) => console.log('  erreur :', m) } })

async function essayer(cle) {
  const [fournisseur, ...reste] = cle.split('/')
  const modele = reste.join('/')
  const slug = cle.replace(/[^a-z0-9]+/gi, '-').toLowerCase()
  const projet = `banc-${slug}-${Date.now().toString(36)}`
  const evenements = []
  const totaux = { entree: 0, sortie: 0, reflexion: 0, cache: 0, requetes: 0 }
  const debut = Date.now()
  const envoyer = async (ev) => {
    evenements.push(ev)
    if (ev.type === 'requete') {
      totaux.requetes++
      totaux.entree += ev.tokens_entree ?? 0
      totaux.sortie += ev.tokens_sortie ?? 0
      totaux.reflexion += ev.tokens_reflexion ?? 0
      totaux.cache += ev.tokens_cache ?? 0
    }
    if (['narration', 'etape', 'terminee', 'erreur', 'en_attente', 'mis_de_cote', 'fournisseur'].includes(ev.type)) {
      console.log(`  ${Math.round((Date.now() - debut) / 1000)} s · ${ev.type} ${String(ev.texte ?? ev.genre ?? ev.raison ?? ev.modele ?? '').slice(0, 90)} ${String(ev.cible ?? '').slice(0, 60)}`)
    }
    return {}
  }
  const demande = {
    tache: projet,
    projet,
    message: DEMANDE,
    rappel: 'http://banc.invalid/',
    fournisseurs: [{ fournisseur, modele }],
    sites: [],
    duree_vie_minutes: 60,
    application: { pile: 'laravel', modele: 'socle', installable: true },
  }
  try {
    // Comme dans Kekeli : quand le fournisseur demande d'attendre (limite par minute), la demande attend puis reprend.
    const misesDeCote = new MisesDeCote()
    let attentes = 0
    for (let essai = 0; essai < 9; essai++) {
      await executerTache({ ...demande, reprise: essai > 0 }, { ordinateurs, envoyer, misesDeCote, clesPresentes, journal: { log() {}, warn: (m) => console.log('  attention :', m), error: (m) => console.log('  erreur :', m) }, dureeMaxMs: 50 * 60 * 1000 })
      const derniere = evenements.findLast((e) => ['terminee', 'erreur', 'en_attente', 'arretee'].includes(e.type))
      if (derniere?.type !== 'en_attente') break
      attentes++
      const pause = Math.min(10 * 60 * 1000, Math.max(30000, new Date(derniere.reprise_vers).getTime() - Date.now()))
      console.log(`  attente de ${Math.round(pause / 1000)} s (limite du fournisseur), puis reprise`)
      await new Promise((r) => setTimeout(r, pause))
    }
    const fin = evenements.findLast((e) => ['terminee', 'erreur', 'en_attente', 'arretee'].includes(e.type))
    const verification = await ordinateurs.verifierApplication(projet, 'laravel').catch((e) => ({ erreur: e.message }))
    const captures = await capturer(projet, slug, evenements)
    const essai = {
      modele: cle,
      date: new Date().toISOString(),
      minutes: Math.round((Date.now() - debut) / 6000) / 10,
      fin: fin?.type ?? 'inconnue',
      attentes,
      detail: String(fin?.detail ?? fin?.raison ?? '').slice(0, 300),
      ...totaux,
      cout_usd: PRIX[cle] ? Math.round((totaux.entree * PRIX[cle].entree + (totaux.sortie + totaux.reflexion) * PRIX[cle].sortie + totaux.cache * PRIX[cle].cache) / 1e6 * 10000) / 10000 : 0,
      reponse: String(evenements.findLast((e) => e.type === 'terminee')?.reponse ?? '').slice(0, 1500),
      tests: verification.tests ? { ok: verification.tests.ok, resume: verification.tests.resume } : null,
      securite: verification.securite ?? null,
      sante: verification.sante?.code ?? null,
      fichiers: evenements.findLast((e) => e.type === 'modifications')?.nombre_fichiers ?? null,
      captures,
    }
    resultats.essais = resultats.essais.filter((e) => e.modele !== cle)
    resultats.essais.push(essai)
    writeFileSync(RESULTATS, JSON.stringify(resultats, null, 2) + '\n')
    console.log(`${cle} : ${essai.fin} en ${essai.minutes} min, ${totaux.requetes} requêtes, ${essai.cout_usd} $, tests ${essai.tests?.resume ?? '?'}, sécurité ${JSON.stringify(essai.securite)}`)
  } finally {
    await ordinateurs.effacer(projet).catch(() => {})
  }
}

/** Captures sur un téléphone simulé : accueil, administrateur d'essai connecté, pages du menu ; pages privées sans connexion. */
async function capturer(projet, slug, evenements) {
  const acces = evenements.findLast((e) => e.type === 'application')
  const apercu = evenements.findLast((e) => e.type === 'apercu')?.url
  if (!apercu || !acces) return { erreur: 'pas d’aperçu' }
  const require = createRequire(import.meta.url)
  const { chromium, devices } = require('playwright')
  const dossier = path.join(BANC, 'captures', slug)
  mkdirSync(dossier, { recursive: true })
  const navigateur = await chromium.launch()
  const resultat = { pages: [], protection: [] }
  try {
    const jeton = jetonApercu(cleApercu(secret, projet), projet, Math.floor(Date.now() / 1000) + 1800)
    const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })
    const page = await contexte.newPage()
    const prendre = async (nom) => {
      await page.waitForTimeout(800)
      await page.screenshot({ path: path.join(dossier, `${nom}.png`), fullPage: true })
      resultat.pages.push({ nom, chemin: new URL(page.url()).pathname, titre: await page.title() })
    }
    await page.goto(`${apercu}/k/${jeton}/`, { waitUntil: 'networkidle' }).catch(() => {})
    await prendre('1-accueil')
    resultat.manifeste = (await page.evaluate(() => document.querySelector('link[rel=manifest]')?.getAttribute('href') ?? null)) ?? null
    await page.goto(`${apercu}/connexion`).catch(() => {})
    await page.fill('input[type=email]', acces.identifiant).catch(() => {})
    await page.fill('input[type=password]', acces.mot_de_passe).catch(() => {})
    await page.click('button[type=submit]').catch(() => {})
    await page.waitForLoadState('networkidle').catch(() => {})
    await prendre('2-administrateur-connecte')
    const liens = [...new Set(await page.$$eval('header a, nav a', (as) => as.map((a) => a.href)))].filter((h) => new URL(h).pathname !== '/').slice(0, 5)
    for (const [i, lien] of liens.entries()) {
      await page.goto(lien).catch(() => {})
      await prendre(`${3 + i}${new URL(lien).pathname.replaceAll('/', '-')}`)
    }
    // Pages privées ouvertes sans être connecté (avec seulement l'accès à l'aperçu) : elles doivent renvoyer vers la connexion.
    const anonyme = await (await navigateur.newContext()).newPage()
    await anonyme.goto(`${apercu}/k/${jeton}/`).catch(() => {})
    for (const lien of liens) {
      await anonyme.goto(lien).catch(() => {})
      resultat.protection.push({ page: new URL(lien).pathname, arrivee: new URL(anonyme.url()).pathname })
    }
  } catch (e) {
    resultat.erreur = e.message
  } finally {
    await navigateur.close()
  }
  return resultat
}

for (const cle of process.argv.slice(2)) {
  if (cle.startsWith('openai/') && depenseOpenAI() >= BUDGET * 0.75) {
    console.log(`Budget OpenAI presque atteint (${depenseOpenAI().toFixed(3)} $) : ${cle} n'est pas essayé.`)
    continue
  }
  console.log(`=== ${cle}`)
  await essayer(cle).catch((e) => console.log(`${cle} : erreur ${e.message}`))
}
console.log(`Dépense OpenAI estimée des essais : ${depenseOpenAI().toFixed(4)} $`)
process.exit(0)
