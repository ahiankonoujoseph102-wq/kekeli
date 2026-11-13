// Mesure réelle de l'identité de Kekeli (6 phrases de runner/mesures/phrases-identite.json) avec les modèles qui répondent
// vraiment aux clients : GPT-6 Luna (Gratuite), GPT-6.1 Terra (payantes) et Gemini 3.5 Flash-Lite (secours gratuit).
//   node runner/scripts/mesurer-identite.mjs <fichier-de-sortie.json>
import { readFileSync, writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'

const { phrases, interdit } = JSON.parse(readFileSync(new URL('../mesures/phrases-identite.json', import.meta.url)))
const refus = new RegExp(interdit, 'iu')
const modeles = [
  { fournisseur: 'openai', modele: 'gpt-6-luna' },
  { fournisseur: 'openai', modele: 'gpt-6.1-terra' },
  { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' },
]
const resultats = []
let usd = 0
const PRIX = { 'gpt-6-luna': [0.1, 0.5], 'gpt-6.1-terra': [1, 5] }
for (const m of modeles) {
  for (const p of phrases) {
    const r = await premierRepondant({ lieu: 'discussion', message: p.texte, historique: [], contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)' }, fournisseurs: [m] }, { clesPresentes: new Set(['gemini']) }).catch((e) => ({ ok: false, reponse: e.message }))
    const texte = String(r.reponse ?? '')
    const bon = Boolean(r.ok) && p.doit.every((d) => texte.toLowerCase().includes(d.toLowerCase())) && !refus.test(texte)
    if (PRIX[m.modele]) usd += ((r.tokens_entree ?? 0) * PRIX[m.modele][0] + ((r.tokens_sortie ?? 0) + (r.tokens_reflexion ?? 0)) * PRIX[m.modele][1]) / 1e6
    resultats.push({ modele: m.modele, texte: p.texte, bon, reponse: texte.slice(0, 400) })
    console.log(bon ? 'oui' : 'NON', m.modele, '|', p.texte, '→', texte.replace(/\s+/g, ' ').slice(0, 160))
  }
}
const bons = resultats.filter((r) => r.bon).length
writeFileSync(process.argv[2], JSON.stringify({ date: new Date().toISOString(), bons, total: resultats.length, cout_usd: Math.round(usd * 10000) / 10000, resultats }, null, 2))
console.log(`${bons} bonnes réponses sur ${resultats.length}, ${usd.toFixed(4)} $`)
