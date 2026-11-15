// Essai réel des fiches du fondateur (runner/src/connaissances.js) avec le modèle des réponses de la production
// (GPT-6.1 Terra chez Coarena, puis chez OpenAI). Les clés restent dans l'environnement, jamais affichées.
//
//   node runner/scripts/essai-fondateur.mjs
//
// Résultat : runner/mesures/fondateur.json (chaque réponse et les vérifications).
import { writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'

const fournisseurs = [
  { fournisseur: 'coarena', modele: 'gpt-6-1-terra' },
  { fournisseur: 'openai', modele: 'gpt-6.1-terra' },
]
const normal = (t) => String(t ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[  ]/g, ' ')
const PROMOTION = /talkami|lome ?ia ?school|lomeiaschool/

const essais = [
  { texte: 'qui est ton fondateur', doit: ['joseph ahiankonou', 'https://talkami.net', 'https://lomeiaschool.laravel.cloud'], motsMax: 120 },
  { texte: 'je veux apprendre l\'anglais', doit: ['https://talkami.net'] },
  { texte: 'je veux apprendre à créer des agents IA', doit: ['150 000', 'creation d\'agents ia|creation d’agents ia', 'lomeiaschool.laravel.cloud'] },
  { texte: 'combien coûte Talkami', doit: ['2 000', '7 500', '14 000'] },
  { texte: 'fais-moi une affiche pour ma boutique', sansPromotion: true },
]

const resultats = []
for (const e of essais) {
  const debut = Date.now()
  const r = await premierRepondant(
    { lieu: 'discussion', message: e.texte, historique: [], contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)', titre: true }, fournisseurs, recherche: { permise: true, restantes: 2 } },
    { clesPresentes: new Set() },
  )
  const texte = r.decision === 'outil' ? `[outil ${r.outil}] ${JSON.stringify(r.arguments ?? {})}` : (r.reponse ?? '')
  const t = normal(texte)
  const manque = (e.doit ?? []).filter((g) => !g.split('|').some((v) => t.includes(normal(v))))
  const mots = texte.split(/\s+/).filter(Boolean).length
  const fournisseurNomme = /\b(openai|anthropic|gemini|coarena|gpt|claude)\b/.test(t.replace(/chatgpt (&|et) claude/g, ''))
  const bon = manque.length === 0 && (!e.motsMax || mots <= e.motsMax) && (!e.sansPromotion || !PROMOTION.test(t)) && !fournisseurNomme
  resultats.push({ question: e.texte, decision: r.decision, modele: r.modele, recherches: (r.recherches ?? []).length, mots, manque, fournisseur_nomme: fournisseurNomme, bon, secondes: Math.round((Date.now() - debut) / 100) / 10, reponse: texte })
  console.log(`${bon ? 'bon ' : 'FAUX'} ${e.texte} (${mots} mots${manque.length ? `, manque ${manque.join(', ')}` : ''})`)
}
const bons = resultats.filter((r) => r.bon).length
writeFileSync(new URL('../mesures/fondateur.json', import.meta.url), JSON.stringify({ date: new Date().toISOString(), bons: `${bons} sur ${resultats.length}`, resultats }, null, 2) + '\n')
console.log(`${bons} sur ${resultats.length}`)
