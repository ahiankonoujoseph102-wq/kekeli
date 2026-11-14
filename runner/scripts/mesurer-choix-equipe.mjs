// Mesure réelle du choix « équipe d'agents ou agent seul » dans le plan de « creer_agent » (premier répondant), sur les
// 6 demandes de runner/mesures/demandes-equipe.json, avec Gemini 3.5 Flash-Lite (gratuit), GPT-6 Luna (Gratuite) et
// GPT-6.1 Terra (formules payantes). Clés dans l'environnement, jamais affichées.
//   node runner/scripts/mesurer-choix-equipe.mjs <fichier-de-sortie.json>
import { readFileSync, writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'

const { demandes } = JSON.parse(readFileSync(new URL('../mesures/demandes-equipe.json', import.meta.url)))
const modeles = (process.env.MODELES || 'gemini/gemini-3.5-flash-lite,openai/gpt-6-luna,openai/gpt-6.1-terra').split(',').map((m) => {
  const [fournisseur, ...reste] = m.split('/')
  return { fournisseur, modele: reste.join('/') }
})
const PRIX = { 'gpt-6-luna': [0.1, 0.5], 'gpt-6.1-terra': [1, 5] }
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
/** Le plan propose une équipe : une ligne « Équipe : », ou un coordinateur avec plusieurs agents. */
export const proposeEquipe = (plan) => /(^|\n)\s*[-*#]*\s*\**\s*Équipe\b|coordinateur/iu.test(String(plan))

// « FUSIONNER=fichier.json » : remplace seulement les résultats des modèles mesurés dans un fichier déjà fait.
const resultats = []
let usd = 0
for (const m of modeles) {
  for (const d of demandes) {
    const r = await premierRepondant({ lieu: 'discussion', message: d.texte, historique: [], contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)' }, fournisseurs: [m] }, { clesPresentes: new Set(['gemini']) }).catch((e) => ({ ok: false, reponse: e.message }))
    if (PRIX[m.modele]) usd += ((r.tokens_entree ?? 0) * PRIX[m.modele][0] + ((r.tokens_sortie ?? 0) + (r.tokens_reflexion ?? 0)) * PRIX[m.modele][1]) / 1e6
    // Un plan écrit en texte au lieu d'appeler l'outil (Gemini, parfois) compte aussi pour le choix mesuré ici.
    const enTexte = /##\s*Rôle/u.test(String(r.reponse ?? '')) ? String(r.reponse) : ''
    const plan = r.decision === 'outil' && r.outil === 'creer_agent' ? String(r.arguments?.plan ?? '') : enTexte
    const obtenu = !plan ? (r.ok ? 'question' : 'echec') : proposeEquipe(plan) ? 'equipe' : 'seul'
    const bon = obtenu === d.attendu
    resultats.push({ modele: m.modele, attendu: d.attendu, obtenu, bon, demande: d.texte, plan: plan.slice(0, 1500), reponse: plan ? undefined : String(r.reponse ?? '').slice(0, 400) })
    console.log(bon ? 'oui' : 'NON', m.modele, d.attendu, '→', obtenu, '|', d.texte.slice(0, 70))
    if (m.fournisseur === 'gemini') await attendre(5000)
  }
}
if (process.env.FUSIONNER) {
  const avant = JSON.parse(readFileSync(process.env.FUSIONNER))
  const mesures = new Set(modeles.map((m) => m.modele))
  resultats.unshift(...avant.resultats.filter((r) => !mesures.has(r.modele)))
  usd += avant.cout_usd ?? 0
}
const bons = resultats.filter((r) => r.bon).length
writeFileSync(process.argv[2], JSON.stringify({ date: new Date().toISOString(), bons, total: resultats.length, cout_usd: Math.round(usd * 10000) / 10000, resultats }, null, 2) + '\n')
console.log(`${bons} bons choix sur ${resultats.length}, ${usd.toFixed(4)} $`)
