// Banc des questions avant une création (outil « poser_questions » du premier répondant), avec le modèle des réponses
// de la production (GPT-6.1 Terra chez OpenAI, puis GPT-6 Luna). Les clés restent dans l'environnement.
//
//   node runner/scripts/mesurer-questions.mjs <etiquette>
// Résultat : runner/mesures/questions-<etiquette>.json.
import { readFileSync, writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'

const etiquette = process.argv[2] || 'essai'
const { demandes } = JSON.parse(readFileSync(new URL('../mesures/demandes-questions.json', import.meta.url)))
const fournisseurs = [
  { fournisseur: 'openai', modele: 'gpt-6.1-terra' },
  { fournisseur: 'openai', modele: 'gpt-6-luna' },
]
const resultats = []
for (const d of demandes) {
  const debut = Date.now()
  const r = await premierRepondant({ lieu: 'discussion', message: d.texte, historique: [], contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)', titre: true }, fournisseurs }, { clesPresentes: new Set() })
  const obtenu = r.questions ? 'questions' : r.decision === 'outil' ? 'outil' : 'reponse'
  resultats.push({ texte: d.texte, attendu: d.attendu, obtenu, bon: obtenu === d.attendu, outil: r.outil ?? r.questions?.outil ?? null, questions: r.questions?.liste ?? null, secondes: Math.round((Date.now() - debut) / 100) / 10 })
  console.log(`${obtenu === d.attendu ? 'bon ' : 'FAUX'} ${obtenu.padEnd(9)} ${d.texte.slice(0, 70)}`)
}
const bons = resultats.filter((x) => x.bon).length
writeFileSync(new URL(`../mesures/questions-${etiquette}.json`, import.meta.url), JSON.stringify({ date: new Date().toISOString(), bons: `${bons} sur ${resultats.length}`, resultats }, null, 2) + '\n')
console.log(`${bons} sur ${resultats.length}`)
