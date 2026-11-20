// Banc du mode équipe (phase 1, docs/multi-agents-etude.md, partie 11) : les mêmes applications construites par
// l'agent unique habituel puis par l'équipe, avec les mêmes modèles gratuits, chacune dans son propre ordinateur E2B,
// exactement comme pour un client. Mesures : critères de la demande respectés (relus par un juge identique pour les deux
// modes à partir de preuves prises dans l'ordinateur : routes, fichiers, tests, pages vraiment ouvertes), tests, sécurité,
// requêtes, tokens, minutes, coût estimé au prix payant équivalent.
//   KEKELI_RUNNER_SECRET=… node runner/scripts/banc-equipe.mjs salon:unique salon:equipe formation:unique …
// Résultats : runner/mesures/banc-equipe.json ; captures : $BANC/captures/<essai>/.
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
import { creerGestionnaireOrdinateurs, synchroniserSecrets } from '../src/ordinateurs.js'
import { executerTache } from '../src/agent.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { MisesDeCote } from '../src/limites.js'
import { cleApercu, jetonApercu } from '../src/apercu/serveur.mjs'
import { rediger } from '../src/documents/redaction.js'

const BANC = process.env.BANC ?? '/tmp/claude-0/banc-equipe'
const RESULTATS = new URL('../mesures/banc-equipe.json', import.meta.url)
const secret = process.env.KEKELI_RUNNER_SECRET
if (!secret) throw new Error('KEKELI_RUNNER_SECRET manque.')

// Les modèles gratuits de Kekeli pour les créations, dans l'ordre (le second prend le relais à la limite du premier).
const MODELES = (process.env.MODELES ?? 'gemini/gemini-3.5-flash-lite,gemini/gemini-3.1-flash-lite').split(',').map((cle) => {
  const [fournisseur, ...reste] = cle.split('/')
  return { fournisseur, modele: reste.join('/') }
})
// Prix payants équivalents (dollars par million de tokens, Gemini 3.5 Flash-Lite, page officielle lue le 12 novembre 2026) :
// une estimation de ce que coûterait la même construction sans l'offre gratuite. E2B : 0,166 $ de l'heure (docs/couts.md).
const PRIX = { entree: 0.3, sortie: 2.5, cache: 0.03, e2b_heure: 0.166 }
const JUGE = [{ fournisseur: 'gemini', modele: process.env.JUGE ?? 'gemini-3.5-flash' }, { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }]

export const APPLICATIONS = {
  salon: {
    nom: 'Belle Afi',
    demande: `Crée une application de prise de rendez-vous pour mon salon de coiffure « Belle Afi » à Lomé.

Plan validé par la cliente :
- Pages : accueil (prestations et prix en FCFA), inscription et connexion, « Mes rendez-vous » (liste, prise de rendez-vous, annulation), administration.
- Données : prestations (nom, durée en minutes, prix en FCFA) ; rendez-vous (cliente, prestation, date, heure, état : en attente, confirmé, refusé ou annulé, note facultative).
- Rôles : cliente (voit et annule seulement ses rendez-vous) ; administrateur (voit tous les rendez-vous, les confirme ou les refuse, gère les prestations).
- Règles : pas de rendez-vous dans le passé, pas deux rendez-vous à la même heure, horaires du salon de 8 h à 19 h.
- Tests de chaque fonction, dont un test qui vérifie qu'une cliente ne voit pas les rendez-vous d'une autre.`,
    criteres: [
      "L'accueil montre les prestations avec leur prix en FCFA (pas l'écran de départ).",
      'Une table et un modèle pour les prestations (nom, durée, prix) et pour les rendez-vous (cliente, prestation, date, heure, état).',
      'Une page « Mes rendez-vous » réservée aux clientes connectées, qui liste leurs rendez-vous.',
      'Une cliente peut prendre un rendez-vous (formulaire et route qui enregistre).',
      'Une cliente peut annuler son rendez-vous, et seulement le sien.',
      "L'administrateur voit tous les rendez-vous et peut les confirmer ou les refuser.",
      "L'administrateur peut gérer les prestations (ajouter, modifier ou supprimer).",
      'Pas de rendez-vous dans le passé ni hors des horaires de 8 h à 19 h (règle vérifiée côté serveur).',
      'Pas deux rendez-vous à la même heure (règle vérifiée côté serveur).',
      "Un test vérifie qu'une cliente ne voit pas les rendez-vous d'une autre.",
    ],
  },
  formation: {
    nom: 'Savoir Plus',
    demande: `Crée une plateforme professionnelle de formation en ligne « Savoir Plus » à Lomé avec inscription, connexion, espace membre, catalogue de formations, paiement et tableau de bord administrateur.

Plan validé par le client :
- Pages : accueil, catalogue des formations (liste et page de détail avec le prix en FCFA), inscription et connexion, espace membre « Mes formations » (formations achetées et leurs chapitres), paiement, tableau de bord administrateur.
- Données : formations (titre, description, prix en FCFA, publiée ou non) ; chapitres d'une formation (titre, ordre, contenu texte) ; inscriptions (membre, formation, état : en attente de paiement ou payée) ; paiements (membre, formation, montant, état, référence).
- Rôles : membre (achète une formation, voit seulement ses formations payées et leurs chapitres) ; administrateur (gère les formations et les chapitres, voit les membres, les inscriptions et les paiements, et le chiffre total des ventes).
- Paiement : le client branchera plus tard son service de paiement Mobile Money avec ses clés ; en attendant, un mode de paiement d'essai, clairement affiché, qui valide l'inscription.
- Tests de chaque fonction, dont un test qui vérifie qu'un membre ne voit pas les chapitres d'une formation qu'il n'a pas payée.`,
    criteres: [
      "L'accueil présente la plateforme « Savoir Plus » (pas l'écran de départ).",
      'Un catalogue public liste les formations publiées avec leur prix en FCFA, et une page de détail par formation.',
      'Des tables et modèles pour les formations, les chapitres, les inscriptions et les paiements.',
      'Un membre connecté peut acheter une formation (inscription puis paiement).',
      "Un mode de paiement d'essai, affiché comme tel, valide l'inscription sans vraie clé.",
      'Un espace membre « Mes formations » réservé aux membres connectés, avec leurs formations payées et leurs chapitres.',
      "Un membre ne peut pas lire les chapitres d'une formation qu'il n'a pas payée (vérifié côté serveur).",
      "L'administrateur gère les formations et leurs chapitres (ajouter, modifier, publier).",
      "Le tableau de bord administrateur montre les membres, les inscriptions, les paiements et le total des ventes en FCFA.",
      "Un test vérifie qu'un membre ne voit pas les chapitres d'une formation non payée.",
    ],
  },
  boutique: {
    nom: 'Ama Pagnes',
    demande: `Crée une application de commandes pour ma boutique de pagnes « Ama Pagnes » au marché d'Adawlato à Lomé.

Plan validé par la cliente :
- Pages : accueil, catalogue des pagnes (photo facultative, prix en FCFA, stock), page d'un produit, panier, commande, « Mes commandes », inscription et connexion, administration.
- Données : produits (nom, description, prix en FCFA, stock) ; commandes (cliente, date, état : nouvelle, préparée, livrée ou annulée, total en FCFA, adresse de livraison dans Lomé) ; lignes de commande (produit, quantité, prix).
- Rôles : cliente (passe commande, voit seulement ses commandes) ; administrateur (gère les produits et les stocks, voit toutes les commandes et change leur état).
- Règles : impossible de commander plus que le stock ; le stock diminue à la commande ; livraison 1 000 FCFA ajoutée au total.
- Tests de chaque fonction, dont un test qui vérifie qu'une cliente ne voit pas les commandes d'une autre.`,
    criteres: [
      "L'accueil présente « Ama Pagnes » (pas l'écran de départ).",
      'Un catalogue public des produits avec prix en FCFA et stock, et une page par produit.',
      'Des tables et modèles pour les produits, les commandes et les lignes de commande.',
      'Un panier et une commande par une cliente connectée, avec une adresse de livraison.',
      'Le total de la commande ajoute la livraison de 1 000 FCFA.',
      'Impossible de commander plus que le stock (vérifié côté serveur), et le stock diminue à la commande.',
      'Une page « Mes commandes » réservée aux clientes connectées, avec seulement leurs commandes.',
      "L'administrateur gère les produits et les stocks.",
      "L'administrateur voit toutes les commandes et change leur état.",
      "Un test vérifie qu'une cliente ne voit pas les commandes d'une autre.",
    ],
  },
}

const resultats = existsSync(RESULTATS) ? JSON.parse(readFileSync(RESULTATS, 'utf8')) : { essais: [] }
const clesPresentes = fournisseursAvecCle()
await synchroniserSecrets(clesPresentes)
const journal = { log() {}, warn: (m) => console.log('  attention :', m), error: (m) => console.log('  erreur :', m) }
const ordinateurs = creerGestionnaireOrdinateurs({ secretRunner: secret, clesPresentes, journal })

async function essayer(appli, mode) {
  const app = APPLICATIONS[appli]
  const nom = `${appli}-${mode}`
  const projet = `banc-equipe-${nom}-${Date.now().toString(36)}`
  const evenements = []
  const totaux = { requetes: 0, entree: 0, sortie: 0, reflexion: 0, cache: 0 }
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
    if (ev.type === 'etape' && /verification|tests_|securite_|application_|relance|garde_fou/.test(ev.genre ?? '')) {
      console.log(`  ${Math.round((Date.now() - debut) / 1000)} s · etape ${ev.genre} ${String(ev.cible ?? '').slice(0, 120)}`)
    }
    if (['narration', 'terminee', 'erreur', 'en_attente', 'mis_de_cote'].includes(ev.type) || (ev.type === 'equipe' && ev.statut && ev.statut !== 'en_cours')) {
      console.log(`  ${Math.round((Date.now() - debut) / 1000)} s · ${ev.type} ${String(ev.texte ?? ev.raison ?? ev.code ?? ev.modele ?? '').slice(0, 90)} ${ev.statut ?? ''} ${ev.type === 'mis_de_cote' ? String(ev.message ?? '').slice(0, 120) : ''}`)
    }
    return {}
  }
  const demande = {
    tache: projet,
    projet,
    message: app.demande,
    compte_rendu: true,
    rappel: 'http://banc.invalid/',
    fournisseurs: MODELES,
    sites: [],
    duree_vie_minutes: 90,
    application: { pile: 'laravel', modele: 'socle', installable: true, nom: app.nom },
    equipe: mode === 'equipe',
  }
  try {
    const misesDeCote = new MisesDeCote()
    let attentes = 0
    for (let essai = 0; essai < 9; essai++) {
      await executerTache({ ...demande, reprise: essai > 0 }, { ordinateurs, envoyer, misesDeCote, clesPresentes, journal, dureeMaxMs: (mode === 'equipe' ? 45 : 20) * 60 * 1000 })
      const derniere = evenements.findLast((e) => ['terminee', 'erreur', 'en_attente', 'arretee'].includes(e.type))
      if (derniere?.type !== 'en_attente') break
      attentes++
      const pause = Math.min(10 * 60 * 1000, Math.max(30000, new Date(derniere.reprise_vers).getTime() - Date.now()))
      console.log(`  attente de ${Math.round(pause / 1000)} s (limite du fournisseur), puis reprise`)
      await new Promise((r) => setTimeout(r, pause))
    }
    const minutes = Math.round((Date.now() - debut) / 6000) / 10
    const fin = evenements.findLast((e) => ['terminee', 'erreur', 'en_attente', 'arretee'].includes(e.type))
    const verification = await ordinateurs.verifierApplication(projet, 'laravel').catch((e) => ({ erreur: e.message }))
    const preuves = await rassembler(projet, nom, evenements, verification)
    // Les preuves sont gardées : un essai peut être relu par le juge plus tard sans reconstruire l'application.
    mkdirSync(path.join(BANC, 'preuves'), { recursive: true })
    writeFileSync(path.join(BANC, 'preuves', `${nom}.json`), JSON.stringify(preuves, null, 2))
    const jugement = await juger(app, preuves)
    const missions = evenements.filter((e) => e.type === 'equipe' && e.etat === 'mission' && e.statut !== 'en_cours').map((e) => ({ code: e.code, statut: e.statut, secondes: e.secondes, requetes: e.requetes, probleme: e.probleme }))
    const essai = {
      application: appli,
      mode,
      date: new Date().toISOString(),
      modeles: MODELES.map((m) => `${m.fournisseur}/${m.modele}`),
      fin: fin?.type ?? 'inconnue',
      detail: String(fin?.detail ?? fin?.raison ?? '').slice(0, 300),
      attentes,
      minutes,
      ...totaux,
      // Les tokens lus dans le cache sont comptés à part par OpenCode (jamais inclus dans les tokens d'entrée).
      cout_modele_usd: Math.round((totaux.entree * PRIX.entree + (totaux.sortie + totaux.reflexion) * PRIX.sortie + totaux.cache * PRIX.cache) / 1e6 * 10000) / 10000,
      cout_e2b_usd: Math.round((minutes / 60) * PRIX.e2b_heure * 10000) / 10000,
      secours: evenements.some((e) => e.type === 'equipe' && e.etat === 'secours'),
      missions,
      tests: verification?.tests ? { ok: verification.tests.ok, resume: verification.tests.resume } : null,
      securite: (verification?.securite ?? []).map((p) => p.detail).slice(0, 5),
      sante: verification?.sante?.code ?? null,
      pages: preuves.pages.map((p) => `${p.chemin} (${p.titre})`),
      protection: preuves.protection,
      criteres: jugement,
      score: jugement ? jugement.filter((c) => c.ok).length : null,
      sur: app.criteres.length,
    }
    resultats.essais = resultats.essais.filter((e) => !(e.application === appli && e.mode === mode))
    resultats.essais.push(essai)
    writeFileSync(RESULTATS, JSON.stringify(resultats, null, 2) + '\n')
    console.log(`${nom} : ${essai.fin} en ${minutes} min, ${totaux.requetes} requêtes, ${essai.cout_modele_usd} $ (équivalent payant), tests ${essai.tests?.resume ?? '?'}, critères ${essai.score}/${essai.sur}`)
  } finally {
    await ordinateurs.effacer(projet).catch(() => {})
  }
}

/** Les preuves prises dans l'ordinateur : routes, fichiers, tests, et les pages vraiment ouvertes sur un téléphone simulé. */
async function rassembler(projet, nom, evenements, verification) {
  const lancer = async (commande) => (await ordinateurs.executerDansProjet(projet, `${commande} ; true`, { timeoutMs: 120000 }).catch((e) => ({ stdout: '', stderr: e.message }))).stdout ?? ''
  const routes = (await lancer('php artisan route:list --except-vendor 2>/dev/null | head -120')).replace(/\s+\n/g, '\n').slice(0, 9000)
  const fichiers = (await lancer('ls -1 app/Models app/Http/Controllers app/Http/Controllers/* resources/views resources/views/* database/migrations tests/Feature 2>/dev/null | head -250')).slice(0, 6000)
  const regles = (await lancer("grep -rhoiE \"(after_or_equal|after:|before:|before_or_equal|unique|exists|max:|min:|stock|19:00|08:00|1000|1 000|abort\\(403|authorize|Gate::|->can\\(|policy)[^\\n]{0,80}\" app 2>/dev/null | sort | uniq -c | sort -rn | head -60")).slice(0, 5000)
  const visite = await visiter(projet, nom, evenements)
  return { routes, fichiers, regles, tests: verification?.tests ?? null, securite: verification?.securite ?? [], ...visite }
}

async function visiter(projet, nom, evenements) {
  const acces = evenements.findLast((e) => e.type === 'application')
  const apercu = evenements.findLast((e) => e.type === 'apercu')?.url
  const resultat = { pages: [], protection: [], textes: [] }
  if (!apercu) return resultat
  const require = createRequire(import.meta.url)
  const { chromium, devices } = require('playwright')
  const dossier = path.join(BANC, 'captures', nom)
  mkdirSync(dossier, { recursive: true })
  const navigateur = await chromium.launch()
  try {
    const jeton = jetonApercu(cleApercu(secret, projet), projet, Math.floor(Date.now() / 1000) + 3600)
    const contexte = await navigateur.newContext({ ...devices['Pixel 7'], locale: 'fr-FR' })
    const page = await contexte.newPage()
    const prendre = async (n) => {
      await page.waitForTimeout(800)
      await page.screenshot({ path: path.join(dossier, `${n}.png`), fullPage: true }).catch(() => {})
      resultat.pages.push({ chemin: new URL(page.url()).pathname, titre: await page.title().catch(() => '') })
      resultat.textes.push(`${new URL(page.url()).pathname} : ${(await page.evaluate(() => document.body?.innerText ?? '').catch(() => '')).replace(/\s+/g, ' ').slice(0, 700)}`)
    }
    await page.goto(`${apercu}/k/${jeton}/`, { waitUntil: 'networkidle' }).catch(() => {})
    await prendre('1-accueil')
    const publics = [...new Set(await page.$$eval('a', (as) => as.map((a) => a.href)).catch(() => []))].filter((h) => h.startsWith(apercu) && !/connexion|login|inscription|register|logout|deconnexion/.test(h)).slice(0, 3)
    for (const [i, lien] of publics.entries()) {
      await page.goto(lien).catch(() => {})
      await prendre(`2-public-${i + 1}`)
    }
    if (acces) {
      await page.goto(`${apercu}/connexion`).catch(() => {})
      await page.fill('input[type=email]', acces.identifiant).catch(() => {})
      await page.fill('input[type=password]', acces.mot_de_passe).catch(() => {})
      await page.click('button[type=submit]').catch(() => {})
      await page.waitForLoadState('networkidle').catch(() => {})
      await prendre('3-administrateur-connecte')
      const liens = [...new Set(await page.$$eval('header a, nav a, aside a, main a', (as) => as.map((a) => a.href)).catch(() => []))].filter((h) => h.startsWith(apercu) && new URL(h).pathname !== '/' && !/logout|deconnexion/.test(h)).slice(0, 8)
      for (const [i, lien] of liens.entries()) {
        await page.goto(lien).catch(() => {})
        await prendre(`4-${i + 1}${new URL(lien).pathname.replaceAll('/', '-')}`)
      }
      // Pages privées ouvertes sans être connecté (avec seulement l'accès à l'aperçu) : elles doivent renvoyer vers la connexion.
      const anonyme = await (await navigateur.newContext()).newPage()
      await anonyme.goto(`${apercu}/k/${jeton}/`).catch(() => {})
      for (const lien of liens) {
        await anonyme.goto(lien).catch(() => {})
        resultat.protection.push({ page: new URL(lien).pathname, arrivee: new URL(anonyme.url()).pathname })
      }
    }
  } catch (e) {
    resultat.erreur = e.message
  } finally {
    await navigateur.close()
  }
  return resultat
}

const CONSIGNE_JUGE = `Tu es le juge d'un banc d'essai. Une application Laravel a été construite pour un client. Pour CHAQUE critère, dis s'il est respecté, UNIQUEMENT d'après les preuves données (routes, fichiers, extraits de règles, résultat des tests, pages réellement ouvertes et leur texte, protection des pages privées). Sois strict : sans preuve claire, le critère n'est pas respecté. Réponds par un seul objet JSON, sans texte autour : {"criteres": [{"numero": 1, "ok": true, "preuve": "une phrase courte"}]}`

async function juger(app, preuves) {
  const consigne = [
    `Critères :\n${app.criteres.map((c, i) => `${i + 1}. ${c}`).join('\n')}`,
    `Routes (php artisan route:list) :\n${preuves.routes || '(vide)'}`,
    `Fichiers :\n${preuves.fichiers || '(vide)'}`,
    `Extraits de règles dans le code :\n${preuves.regles || '(vide)'}`,
    `Tests : ${preuves.tests ? `${preuves.tests.ok ? 'réussis' : 'en échec'} (${preuves.tests.resume ?? '?'})` : 'non lancés'}`,
    `Problèmes de sécurité trouvés par Kekeli : ${preuves.securite.length ? preuves.securite.map((p) => p.detail).join(' ; ') : 'aucun'}`,
    `Pages ouvertes (texte visible) :\n${preuves.textes.join('\n').slice(0, 9000) || '(aucune)'}`,
    `Pages privées ouvertes sans connexion → page d'arrivée :\n${preuves.protection.map((p) => `${p.page} → ${p.arrivee}`).join('\n') || '(aucune)'}`,
  ].join('\n\n')
  // Les modèles gratuits du juge peuvent être occupés (limite par minute) : jusqu'à 4 essais, une minute d'écart.
  let r = null
  for (let essai = 0; essai < 4 && !r?.ok; essai++) {
    if (essai) await new Promise((ok) => setTimeout(ok, 60000))
    r = await rediger({ systeme: CONSIGNE_JUGE, consigne, fournisseurs: JUGE, maxTokens: 3000 }, { clesPresentes, misesDeCote: new MisesDeCote() }).catch(() => null)
  }
  if (!r?.ok) return null
  const liste = Array.isArray(r.objet?.criteres) ? r.objet.criteres : []
  return app.criteres.map((critere, i) => {
    const c = liste.find((x) => Number(x?.numero) === i + 1)
    return { critere, ok: c?.ok === true, preuve: String(c?.preuve ?? '').slice(0, 200) }
  })
}

// « rejuger » : relire un essai déjà fait à partir de ses preuves gardées (par exemple quand le juge était occupé).
if (process.argv[2] === 'rejuger') {
  for (const essai of process.argv.slice(3)) {
    const [appli, mode] = essai.split(':')
    const preuves = JSON.parse(readFileSync(path.join(BANC, 'preuves', `${appli}-${mode}.json`), 'utf8'))
    const jugement = await juger(APPLICATIONS[appli], preuves)
    const e = resultats.essais.find((x) => x.application === appli && x.mode === mode)
    if (e && jugement) Object.assign(e, { criteres: jugement, score: jugement.filter((c) => c.ok).length })
    writeFileSync(RESULTATS, JSON.stringify(resultats, null, 2) + '\n')
    console.log(`${essai} : critères ${e?.score}/${APPLICATIONS[appli].criteres.length}`)
  }
  process.exit(0)
}

for (const essai of process.argv.slice(2)) {
  const [appli, mode] = essai.split(':')
  if (!APPLICATIONS[appli] || !['unique', 'equipe'].includes(mode)) {
    console.log(`Essai inconnu : ${essai} (application:unique ou application:equipe)`)
    continue
  }
  console.log(`=== ${appli} · ${mode === 'equipe' ? 'mode équipe' : 'agent unique'}`)
  await essayer(appli, mode).catch((e) => console.log(`${essai} : erreur ${e.message}`))
}
process.exit(0)
