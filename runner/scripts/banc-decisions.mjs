// Banc des 30 décisions du premier répondant (chantier « comprendre, répondre et réagir comme Claude et ChatGPT »,
// 15 novembre 2026) : chaque demande de runner/mesures/decisions-30.json part au vrai premier répondant, comme dans une
// discussion du site (Togo, FCFA, recherche permise), avec l'ordre des modèles de la production. La recherche sur
// Internet n'est pas faite (la décision de chercher suffit) : aucune requête Tavily ni Exa. Les clés restent dans
// l'environnement, jamais affichées.
//
//   node runner/scripts/banc-decisions.mjs <etiquette> [numéros…]
//   MODELES=openai/gpt-6-luna,openai/gpt-6.1-terra : l'ordre des modèles (par défaut celui de la production).
import { readFileSync, writeFileSync } from 'node:fs'
import { freellmapi } from '../src/freellmapi.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { premierRepondant, OUTILS_DE_CREATION } from '../src/repondant.js'

const etiquette = process.argv[2] || 'essai'
const choisis = process.argv.slice(3).map(Number).filter(Boolean)
const { demandes } = JSON.parse(readFileSync(new URL('../mesures/decisions-30.json', import.meta.url)))
const fournisseurs = (process.env.MODELES || 'openai/gpt-6.1-terra,openai/gpt-6-luna').split(',').map((m) => ({ fournisseur: m.split('/')[0], modele: m.split('/').slice(1).join('/') }))

/** La décision lue dans le résultat : créer, chercher, préciser ou répondre. */
export function decisionDe(r, cherche) {
  if (r.decision === 'outil' && OUTILS_DE_CREATION.includes(r.outil)) return 'creer'
  if (r.questions || (r.proposition && r.proposition.raison && r.proposition.raison !== 'proposition')) return 'preciser'
  if (cherche) return 'chercher'
  const texte = String(r.reponse ?? '').trim()
  // Une question courte qui remplace la réponse ; une longue réponse terminée par une proposition reste une réponse.
  if (texte.length < 400 && /\?\s*$/.test(texte)) return 'preciser'
  return 'repondre'
}

// FreeLLMAPI (Groq, Z.ai) tourne à côté, comme dans le Runner, seulement pendant le banc.
const avecFreellmapi = fournisseurs.some((f) => f.fournisseur === 'freellmapi') && (await freellmapi().demarrer())
const resultats = []
for (const d of demandes) {
  // PAUSE_S : attente entre deux demandes, pour un fournisseur limité par minute (Groq : 8 000 tokens par minute).
  if (resultats.length && Number(process.env.PAUSE_S)) await new Promise((r) => setTimeout(r, Number(process.env.PAUSE_S) * 1000))
  if (choisis.length && !choisis.includes(d.numero)) continue
  let cherche = false
  const debut = Date.now()
  const r = await premierRepondant(
    { lieu: 'discussion', message: d.texte, contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)', photos: 0, titre: true }, fournisseurs, recherche: { permise: true, restantes: 2 } },
    { clesPresentes: fournisseursAvecCle(process.env), env: process.env, chercher: async () => { cherche = true; return { ok: false, refus: [], envoyees: 0 } } },
  ).catch((erreur) => ({ ok: false, erreur: erreur.message }))
  const obtenu = r.ok === false ? 'échec' : decisionDe(r, cherche)
  const bon = d.attendu.includes(obtenu)
  resultats.push({ numero: d.numero, genre: d.genre, texte: d.texte, attendu: d.attendu, obtenu, bon, outil: r.outil ?? r.questions?.outil ?? r.proposition?.outil ?? null, proposition: r.proposition ?? null, modele: r.modele ?? null, duree_ms: Date.now() - debut, reponse: r.reponse ?? null })
  console.log(`${bon ? 'bon ' : 'FAUX'} ${String(d.numero).padStart(2)} [${d.genre}] ${d.texte.slice(0, 70)} → ${obtenu}${r.outil ? ` (${r.outil})` : ''}${r.proposition?.raison === 'proposition' ? ` + bouton ${r.proposition.outil}` : ''}`)
}
const bons = resultats.filter((r) => r.bon).length
console.log(`\nBonnes décisions : ${bons} sur ${resultats.length}`)
writeFileSync(new URL(`../mesures/banc-decisions-${etiquette}.json`, import.meta.url), JSON.stringify({ date: new Date().toISOString(), etiquette, modeles: fournisseurs, bons, total: resultats.length, resultats }, null, 2) + '\n')
if (avecFreellmapi) freellmapi().arreter()
