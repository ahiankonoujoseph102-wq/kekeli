// Banc d'essai des modèles de Coarena sur 7 tâches réelles de Kekeli, en français (docs/plan.md, partie 26.26).
//   node runner/scripts/banc-coarena.mjs gpt-6-1-sol gemini-4-argon …
// Seule la clé COARENA_API_KEY est lue (jamais affichée). Chaque tâche est vérifiée par le code (pas « à l'œil ») :
// calcul exact, page qui s'ouvre sans erreur dans Chromium, fichier modifié sans rien casser, application lancée pour de
// vrai et interrogée, JSON valide et totaux justes, bons appels d'outils, consignes de forme respectées.
// Coût : lu dans l'interface de Coarena (« utilisé aujourd'hui ») avant et après chaque modèle.
// Garde-fou : arrêt avant de dépasser BUDGET_COARENA dollars pour tout le banc (1 $ par défaut).
// Résultats : runner/mesures/modeles-coarena.json (réponses complètes) ; le tableau est écrit à la main dans
// runner/mesures/modeles-coarena.md après lecture des réponses.
import { readFileSync, writeFileSync, existsSync, mkdtempSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { spawn } from 'node:child_process'
import { createRequire } from 'node:module'
import { consigneSysteme, OUTILS } from '../src/repondant.js'

const require = createRequire(import.meta.url)
const BASE = 'https://coarena.ai/api/v1'
const CLE = process.env.COARENA_API_KEY
const BUDGET = Number(process.env.BUDGET_COARENA ?? 1)
const FICHIER = new URL('../mesures/modeles-coarena.json', import.meta.url)
const SEULES = process.env.TACHES ? process.env.TACHES.split(',') : null
if (!CLE) throw new Error('COARENA_API_KEY absente.')

const entetes = { Authorization: `Bearer ${CLE}`, 'Content-Type': 'application/json' }
const pause = (ms) => new Promise((r) => setTimeout(r, ms))

async function utiliseAujourdhui() {
  const r = await fetch(`${BASE}/limits`, { headers: entetes })
  return (await r.json()).data.today.used_usd
}

async function appeler(modele, corps, delai = 240000) {
  const debut = Date.now()
  try {
    const r = await fetch(`${BASE}/chat/completions`, { method: 'POST', headers: entetes, body: JSON.stringify({ model: modele, ...corps }), signal: AbortSignal.timeout(delai) })
    const d = await r.json()
    const duree = (Date.now() - debut) / 1000
    if (!r.ok || d.error) return { ok: false, duree, erreur: String(d.error?.message ?? r.status).slice(0, 200) }
    const m = d.choices?.[0]?.message ?? {}
    return { ok: true, duree, texte: m.content ?? '', outils: m.tool_calls ?? [], usage: d.usage ?? {}, fin: d.choices?.[0]?.finish_reason }
  } catch (e) {
    return { ok: false, duree: (Date.now() - debut) / 1000, erreur: String(e.message).slice(0, 200) }
  }
}

const SYSTEME_TECHNIQUE = 'Tu es un développeur web expert. Tu réponds en français. Tu donnes uniquement ce qui est demandé.'
const EMOJI = /[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}]/u
const blocCode = (texte, langage) => {
  const blocs = [...String(texte).matchAll(/```([a-zA-Z]*)\n([\s\S]*?)```/g)]
  const choisi = blocs.find((b) => !langage || b[1].toLowerCase() === langage) ?? blocs[0]
  return choisi ? choisi[2] : String(texte)
}
const nombres = (texte) => String(texte).replace(/[  ]/g, ' ').match(/\d{1,3}(?:[ .]\d{3})+|\d+/g)?.map((n) => Number(n.replace(/[ .]/g, ''))) ?? []

// 1. Réponse avec raisonnement : un calcul en plusieurs étapes, avec une réponse exacte attendue (115 000 FCFA).
const raisonnement = {
  id: 'raisonnement',
  nom: 'Réponse avec raisonnement',
  corps: () => ({
    max_tokens: 4000,
    messages: [{ role: 'system', content: consigneSysteme('discussion', { pays: 'Togo', monnaie: 'franc CFA (FCFA)' }) }, { role: 'user', content: 'Je veux ouvrir une boutique de pagnes à Lomé. J\'ai 1 500 000 FCFA. Le loyer est de 75 000 FCFA par mois et le propriétaire demande 3 mois d\'avance. Le premier stock coûte 900 000 FCFA, l\'aménagement 200 000 FCFA et les papiers 60 000 FCFA. Combien me reste-t-il pour tenir les premiers mois, et est-ce suffisant ? Conseille-moi.' }],
  }),
  verifier: (r) => {
    const n = nombres(r.texte)
    const juste = n.includes(115000)
    const fcfa = /FCFA/.test(r.texte)
    const conseil = r.texte.length > 400
    const propre = !EMOJI.test(r.texte)
    return { note: (juste ? 6 : 0) + (fcfa ? 1 : 0) + (conseil ? 2 : 0) + (propre ? 1 : 0), details: { calcul_juste: juste, fcfa, conseil, sans_emoji: propre } }
  },
}

// 2. Petit site complet : ouvert dans Chromium (format téléphone), sans erreur, sans débordement, menu qui marche.
const site = {
  id: 'site',
  nom: 'Création d\'un petit site (HTML, CSS, JS)',
  corps: () => ({
    max_tokens: 12000,
    messages: [{ role: 'system', content: SYSTEME_TECHNIQUE }, { role: 'user', content: 'Crée le site vitrine complet du salon de coiffure « Belle Afi » à Kpalimé (Togo), en UN SEUL fichier index.html (CSS dans <style>, JavaScript dans <script>), en français, adapté au téléphone. Sections : accueil, services avec 4 prix en FCFA, horaires, contact avec un bouton WhatsApp vers le +228 90 00 00 00. Un menu qui s\'ouvre et se ferme avec un bouton sur téléphone (id="bouton-menu"). Aucune image externe, aucun emoji, aucun texte « lorem ipsum ». Réponds seulement avec le fichier dans un bloc ```html.' }],
  }),
  verifier: async (r) => {
    const html = blocCode(r.texte, 'html')
    const points = {
      doctype: /<!doctype html>/i.test(html),
      langue: /<html[^>]*lang=["']fr/i.test(html),
      viewport: /name=["']viewport/i.test(html),
      style_script: /<style[\s>]/i.test(html) && /<script[\s>]/i.test(html),
      whatsapp: /wa\.me\/22890000000/.test(html.replace(/\s/g, '')),
      prix: (html.match(/FCFA/g) ?? []).length >= 4,
      sans_lorem_emoji: !/lorem/i.test(html) && !EMOJI.test(html),
    }
    const { chromium } = require('playwright')
    const navigateur = await chromium.launch()
    const page = await navigateur.newPage({ viewport: { width: 412, height: 915 }, isMobile: true, hasTouch: true })
    const erreurs = []
    page.on('pageerror', (e) => erreurs.push(e.message))
    await page.setContent(html, { waitUntil: 'load' }).catch((e) => erreurs.push(e.message))
    points.sans_erreur_js = erreurs.length === 0
    points.sans_debordement = await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1).catch(() => false)
    let menu = false
    try {
      const avant = await page.evaluate(() => document.body.innerHTML.length + '|' + [...document.querySelectorAll('nav, [class*=menu], [id*=menu]')].map((e) => getComputedStyle(e).display + e.className + e.getAttribute('aria-expanded')).join())
      await page.locator('#bouton-menu').click({ timeout: 2000 })
      const apres = await page.evaluate(() => document.body.innerHTML.length + '|' + [...document.querySelectorAll('nav, [class*=menu], [id*=menu]')].map((e) => getComputedStyle(e).display + e.className + e.getAttribute('aria-expanded')).join())
      menu = avant !== apres
    } catch {}
    points.menu = menu
    await navigateur.close()
    const n = Object.values(points).filter(Boolean).length
    return { note: Math.round((n / Object.keys(points).length) * 10), details: points }
  },
}

// 3. Modification d'un site existant : changer un prix et ajouter une section, sans rien perdre du reste.
const ORIGINAL = `<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Le Palmier</title>
<style>body{font-family:sans-serif;margin:0}header,section,footer{padding:16px}.plat{display:flex;justify-content:space-between}</style></head>
<body>
<header><h1>Restaurant Le Palmier</h1><p>Cuisine togolaise à Lomé, quartier Bè.</p></header>
<section id="menu"><h2>Notre menu</h2>
<div class="plat"><span>Poulet braisé</span><span>3 500 FCFA</span></div>
<div class="plat"><span>Akoumé et sauce gombo</span><span>2 000 FCFA</span></div>
<div class="plat"><span>Poisson grillé</span><span>4 500 FCFA</span></div>
<div class="plat"><span>Ablo et sauce tomate</span><span>1 500 FCFA</span></div>
</section>
<section id="horaires"><h2>Horaires</h2><p>Du lundi au samedi, de 11 h à 22 h.</p></section>
<footer><p>© 2026 Restaurant Le Palmier</p></footer>
<script>document.querySelectorAll('.plat').forEach(function(p){p.addEventListener('click',function(){p.classList.toggle('choisi')})})</script>
</body>
</html>`
const modification = {
  id: 'modification',
  nom: 'Modification d\'un site sans rien casser',
  corps: () => ({
    max_tokens: 8000,
    messages: [{ role: 'system', content: SYSTEME_TECHNIQUE }, { role: 'user', content: `Voici le fichier index.html d'un restaurant :\n\n\`\`\`html\n${ORIGINAL}\n\`\`\`\n\nFais exactement deux changements : 1) le poulet braisé passe à 4 000 FCFA ; 2) ajoute une section « Contact » (id="contact") juste avant le pied de page, avec un lien WhatsApp vers le +228 91 11 11 11. Ne change rien d'autre. Réponds seulement avec le fichier complet dans un bloc \`\`\`html.` }],
  }),
  verifier: (r) => {
    const html = blocCode(r.texte, 'html')
    const sans = html.replace(/\s+/g, ' ')
    const points = {
      nouveau_prix: /Poulet braisé<\/span>\s*<span>4[\s  ]?000 FCFA/.test(html),
      ancien_prix_parti: !/3[\s  ]?500/.test(html),
      autres_plats: ['Akoumé et sauce gombo</span><span>2 000 FCFA', 'Poisson grillé</span><span>4 500 FCFA', 'Ablo et sauce tomate</span><span>1 500 FCFA'].every((t) => sans.includes(t)),
      horaires: sans.includes('Du lundi au samedi, de 11 h à 22 h.'),
      contact_avant_pied: /id=["']contact["'][\s\S]*<footer/.test(html),
      whatsapp: /wa\.me\/22891111111/.test(html.replace(/\s/g, '')),
      script_garde: html.includes("p.classList.toggle('choisi')"),
      pied_garde: sans.includes('© 2026 Restaurant Le Palmier'),
    }
    const n = Object.values(points).filter(Boolean).length
    return { note: Math.round((n / Object.keys(points).length) * 10), details: points }
  },
}

// 4. Petite application avec base de données : lancée pour de vrai (Node 22, node:sqlite) et interrogée.
const application = {
  id: 'application',
  nom: 'Création d\'une petite application avec base de données',
  corps: () => ({
    max_tokens: 10000,
    messages: [{ role: 'system', content: SYSTEME_TECHNIQUE }, { role: 'user', content: 'Écris server.js, une petite application de rendez-vous pour un salon, en Node.js 22 SANS aucune dépendance : serveur avec node:http et base SQLite avec node:sqlite (DatabaseSync), fichier de base indiqué par la variable BASE (sinon « rdv.db »), port indiqué par la variable PORT. Table rendez_vous (id, nom, telephone, date, service). Routes : POST /rendez-vous (corps JSON avec nom, telephone, date, service ; tous obligatoires ; réponse 201 avec le rendez-vous créé et son id, ou 400 avec { "erreur": "…" } en français si un champ manque) ; GET /rendez-vous (liste JSON, du plus ancien au plus récent) ; toute autre adresse : 404. Requêtes SQL préparées obligatoires. Réponds seulement avec le fichier dans un bloc ```javascript.' }],
  }),
  verifier: async (r) => {
    const code = blocCode(r.texte, 'javascript') || blocCode(r.texte, 'js')
    const dossier = mkdtempSync(join(tmpdir(), 'banc-app-'))
    writeFileSync(join(dossier, 'server.js'), code)
    const port = 4700 + Math.floor(Math.random() * 200)
    const enfant = spawn(process.execPath, ['server.js'], { cwd: dossier, env: { ...process.env, PORT: String(port), BASE: join(dossier, 'essai.db'), COARENA_API_KEY: '' }, stdio: 'ignore' })
    const url = `http://127.0.0.1:${port}`
    const points = { demarre: false, creation_201: false, liste: false, validation_400: false, injection_sans_effet: false, inconnue_404: false, requetes_preparees: /prepare\s*\(/.test(code) && !/\$\{[^}]*\}[^`]*`\s*\)/.test(code.match(/exec\s*\(`[\s\S]*?`\)/g)?.join('') ?? '') }
    try {
      for (let i = 0; i < 30 && !points.demarre; i++) {
        await pause(200)
        points.demarre = await fetch(`${url}/rendez-vous`).then(() => true, () => false)
      }
      if (points.demarre) {
        const poster = (corps) => fetch(`${url}/rendez-vous`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(corps) })
        const a = await poster({ nom: 'Afi Mensah', telephone: '+228 90 00 00 00', date: '2026-12-01 10:00', service: 'Tresses' })
        const corpsA = await a.json().catch(() => ({}))
        points.creation_201 = a.status === 201 && JSON.stringify(corpsA).includes('Afi Mensah')
        const b = await poster({ nom: 'Kofi' })
        const corpsB = await b.json().catch(() => ({}))
        points.validation_400 = b.status === 400 && typeof corpsB.erreur === 'string'
        await poster({ nom: "x'); DROP TABLE rendez_vous;--", telephone: '1', date: '2026-12-02', service: 'Coupe' })
        const liste = await fetch(`${url}/rendez-vous`).then((x) => x.json()).catch(() => null)
        const tableau = Array.isArray(liste) ? liste : (liste?.rendez_vous ?? liste?.rendezVous ?? liste?.data)
        points.liste = Array.isArray(tableau) && tableau.length >= 1 && tableau[0].nom === 'Afi Mensah'
        points.injection_sans_effet = Array.isArray(tableau) && tableau.length === 2
        points.inconnue_404 = (await fetch(`${url}/nimporte`)).status === 404
      }
    } finally {
      enfant.kill()
      rmSync(dossier, { recursive: true, force: true })
    }
    const n = Object.values(points).filter(Boolean).length
    return { note: Math.round((n / Object.keys(points).length) * 10), details: points }
  },
}

// 5. Document structuré : un devis en JSON (comme la rédaction des documents de Kekeli), totaux vérifiés.
const document = {
  id: 'document',
  nom: 'Rédaction d\'un document structuré',
  corps: () => ({
    max_tokens: 4000,
    messages: [{ role: 'system', content: 'Tu rédiges des documents professionnels en français pour Kekeli. Tu réponds UNIQUEMENT avec un objet JSON valide, sans texte autour.' }, { role: 'user', content: 'Prépare un devis de la couturière « Atelier Ama » (Lomé) pour l\'école Les Étoiles : 40 tenues scolaires à 6 500 FCFA, 40 cravates à 1 200 FCFA, livraison 10 000 FCFA. Format : {"titre": "…", "sections": [{"titre": "…", "paragraphes": ["…"], "tableau": {"colonnes": ["Désignation", "Quantité", "Prix unitaire", "Montant"], "lignes": [["…", 40, 6500, 260000]]}}], "total": nombre}. Montants en nombres entiers (FCFA). Ajoute une section « Conditions de paiement » avec un acompte de 50 %.' }],
  }),
  verifier: (r) => {
    let d = null
    try {
      d = JSON.parse(blocCode(r.texte, 'json').trim())
    } catch {}
    const lignes = d?.sections?.flatMap((s) => s.tableau?.lignes ?? []) ?? []
    const montants = lignes.map((l) => Number(l[3]))
    const total = 40 * 6500 + 40 * 1200 + 10000
    const points = {
      json_valide: Boolean(d),
      schema: Boolean(d?.titre) && Array.isArray(d?.sections),
      lignes_justes: lignes.length >= 3 && lignes.every((l) => Number(l[1]) * Number(l[2]) === Number(l[3])),
      total_juste: Number(d?.total) === total || montants.reduce((a, b) => a + b, 0) === total,
      conditions: JSON.stringify(d ?? {}).includes('50'),
      francais: /[éèàç]/.test(JSON.stringify(d ?? {})),
    }
    const n = Object.values(points).filter(Boolean).length
    return { note: Math.round((n / Object.keys(points).length) * 10), details: points }
  },
}

// 6. Outils de Kekeli : la vraie consigne et les vrais outils du premier répondant, trois messages, bonne décision.
const declaration = (nom) => {
  const o = OUTILS[nom]
  const proprietes = Object.fromEntries(Object.entries(o.parametres ?? {}).map(([c, desc]) => [c, { type: 'string', description: desc }]))
  return { type: 'function', function: { name: nom, description: o.description, parameters: { type: 'object', properties: proprietes, required: Object.keys(proprietes) } } }
}
const CAS_OUTILS = [
  { message: 'Crée-moi un site pour mon restaurant Le Palmier à Lomé, avec le menu et un bouton WhatsApp.', attendu: 'creer_site' },
  { message: 'C\'est quoi la différence entre un nom de domaine et un hébergement ?', attendu: null },
  { message: 'Je veux une application pour gérer les rendez-vous de mon salon, avec des comptes pour mes clientes et pour mes coiffeuses.', attendu: 'creer_application' },
]
const outils = {
  id: 'outils',
  nom: 'Utilisation des outils de Kekeli',
  plusieurs: true,
  executer: async (modele) => {
    const tours = []
    for (const cas of CAS_OUTILS) {
      const r = await appeler(modele, {
        max_tokens: 3000,
        messages: [{ role: 'system', content: consigneSysteme('discussion', { pays: 'Togo', monnaie: 'franc CFA (FCFA)' }) }, { role: 'user', content: cas.message }],
        tools: ['creer_site', 'creer_application', 'creer_agent', 'creer_document'].map(declaration),
        tool_choice: 'auto',
      })
      tours.push({ ...cas, ...r })
    }
    return tours
  },
  verifier: (tours) => {
    const details = tours.map((t) => {
      const appel = t.outils?.[0]?.function
      let argsOk = true
      if (appel) {
        try { JSON.parse(appel.arguments || '{}') } catch { argsOk = false }
      }
      const bon = t.ok && ((t.attendu === null && !appel && t.texte.length > 50) || appel?.name === t.attendu) && argsOk
      return { attendu: t.attendu ?? 'réponse écrite', obtenu: appel?.name ?? (t.ok ? 'réponse écrite' : 'erreur'), arguments_json_valides: argsOk, bon }
    })
    const n = details.filter((d) => d.bon).length
    return { note: Math.round((n / details.length) * 10), details }
  },
}

// 7. Français et respect des consignes de forme.
const consignes = {
  id: 'consignes',
  nom: 'Français et respect des consignes',
  corps: () => ({
    max_tokens: 2000,
    messages: [{ role: 'user', content: 'Donne exactement 3 conseils pour vendre plus de poulets braisés sur WhatsApp à Lomé. Format obligatoire : une liste numérotée de 3 lignes (« 1. », « 2. », « 3. »), chaque ligne de moins de 20 mots, en français, aucun emoji, aucun titre, aucune phrase avant ou après la liste, et un prix en FCFA dans au moins une ligne.' }],
  }),
  verifier: (r) => {
    const lignes = r.texte.trim().split('\n').map((l) => l.trim()).filter(Boolean)
    const points = {
      trois_lignes: lignes.length === 3,
      numerotees: lignes.every((l, i) => l.startsWith(`${i + 1}.`)),
      courtes: lignes.every((l) => l.split(/\s+/).length < 21),
      sans_emoji: !EMOJI.test(r.texte),
      fcfa: /FCFA/.test(r.texte),
      francais: /[éèàç]/.test(r.texte) || / le | la | les | des /.test(r.texte),
    }
    const n = Object.values(points).filter(Boolean).length
    return { note: Math.round((n / Object.keys(points).length) * 10), details: points }
  },
}

export const TACHES = [raisonnement, site, modification, application, document, outils, consignes]

const resultats = existsSync(FICHIER) ? JSON.parse(readFileSync(FICHIER, 'utf8')) : { debut_utilise_usd: null, essais: [] }
const modeles = process.argv.slice(2)
const depart = await utiliseAujourdhui()
resultats.debut_utilise_usd ??= depart
let depense = resultats.essais.reduce((t, e) => t + (e.cout_usd ?? 0), 0)

for (const modele of modeles) {
  for (const tache of TACHES) {
    if (SEULES && !SEULES.includes(tache.id)) continue
    if (depense > BUDGET) {
      console.log(`Budget de ${BUDGET} $ atteint (${depense.toFixed(4)} $) : arrêt.`)
      writeFileSync(FICHIER, JSON.stringify(resultats, null, 2))
      process.exit(0)
    }
    const avant = await utiliseAujourdhui()
    let essai
    if (tache.plusieurs) {
      const tours = await tache.executer(modele)
      const v = tache.verifier(tours)
      essai = { modele, tache: tache.id, ok: tours.every((t) => t.ok), duree: tours.reduce((a, t) => a + t.duree, 0), erreurs: tours.filter((t) => !t.ok).map((t) => t.erreur), note: v.note, details: v.details, tokens_sortie: tours.reduce((a, t) => a + (t.usage?.completion_tokens ?? 0), 0), reponse: tours.map((t) => ({ message: t.message, texte: t.texte, outils: t.outils })) }
    } else {
      const r = await appeler(modele, tache.corps())
      const v = r.ok ? await tache.verifier(r) : { note: 0, details: {} }
      essai = { modele, tache: tache.id, ok: r.ok, duree: r.duree, erreurs: r.ok ? [] : [r.erreur], note: v.note, details: v.details, tokens_sortie: r.usage?.completion_tokens ?? 0, fin: r.fin, reponse: r.texte }
    }
    await pause(1500)
    essai.cout_usd = Math.max(0, (await utiliseAujourdhui()) - avant)
    depense += essai.cout_usd
    resultats.essais = resultats.essais.filter((e) => !(e.modele === modele && e.tache === tache.id))
    resultats.essais.push(essai)
    writeFileSync(FICHIER, JSON.stringify(resultats, null, 2))
    console.log(`${modele} · ${tache.id} : ${essai.ok ? 'réussi' : 'échec'} · note ${essai.note}/10 · ${essai.duree.toFixed(1)} s · ${essai.cout_usd.toFixed(4)} $${essai.erreurs.length ? ' · ' + essai.erreurs[0] : ''}`)
  }
}
console.log(`Dépense totale du banc : ${depense.toFixed(4)} $`)
