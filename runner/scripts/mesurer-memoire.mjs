// Mesure réelle de l'extraction des souvenirs (runner/src/memoire.js) sur runner/mesures/phrases-memoire.json, avec
// les modèles les moins chers : Gemini 3.5 Flash-Lite et Gemini 3.1 Flash-Lite (gratuits), GPT-6 Luna (payant, le
// moins cher d'OpenAI). Une bonne extraction : tout ce qu'il faut retenir, rien d'interdit, rien quand il n'y a rien.
//   node runner/scripts/mesurer-memoire.mjs <fichier-de-sortie.json>
import { readFileSync, writeFileSync } from 'node:fs'
import { extraireSouvenirs, vautLaPeine } from '../src/memoire.js'

const { souvenirs, phrases } = JSON.parse(readFileSync(new URL('../mesures/phrases-memoire.json', import.meta.url)))
const modeles = (process.env.MODELES || 'gemini/gemini-3.5-flash-lite,gemini/gemini-3.1-flash-lite,openai/gpt-6-luna').split(',').map((m) => {
  const [fournisseur, ...reste] = m.split('/')
  return { fournisseur, modele: reste.join('/') }
})
const PRIX = { 'gpt-6-luna': [0.1, 0.5] }
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))
const norme = (t) => String(t).toLowerCase().normalize('NFD').replace(/\p{M}/gu, '')

const resultats = []
let usd = 0
for (const m of modeles) {
  const tokens = { entree: 0, sortie: 0, appels: 0 }
  for (const p of phrases) {
    const r = await extraireSouvenirs({ message: p.texte, souvenirs: p.connus ? souvenirs : [], photos: p.photos ?? 0 }, { fournisseurs: [m], clesPresentes: new Set(['gemini']) }).catch((e) => ({ ok: false, operations: [], erreur: e.message }))
    const c = r.consommation ?? {}
    tokens.entree += c.tokens_entree ?? 0
    tokens.sortie += (c.tokens_sortie ?? 0) + (c.tokens_reflexion ?? 0)
    tokens.appels += r.appel ? 1 : 0
    if (PRIX[m.modele]) usd += ((c.tokens_entree ?? 0) * PRIX[m.modele][0] + ((c.tokens_sortie ?? 0) + (c.tokens_reflexion ?? 0)) * PRIX[m.modele][1]) / 1e6
    const ecrits = norme(r.operations.filter((o) => o.texte).map((o) => o.texte).join(' | '))
    const oublies = r.operations.filter((o) => o.action === 'oublier').map((o) => o.id)
    const modifies = r.operations.filter((o) => o.action === 'modifier').map((o) => o.id)
    const defauts = []
    if (!r.ok) defauts.push('échec')
    for (const d of p.doit ?? []) if (!ecrits.replace(/\s/g, '').includes(norme(d).replace(/\s/g, ''))) defauts.push(`manque « ${d} »`)
    for (const j of p.jamais ?? []) if (ecrits.includes(norme(j))) defauts.push(`interdit « ${j} »`)
    if (p.rien && r.operations.length) defauts.push('opération inutile')
    for (const id of p.oublier ?? []) if (!oublies.includes(id)) defauts.push(`n'a pas oublié ${id}`)
    for (const id of p.modifier ?? []) if (!modifies.includes(id) && !oublies.includes(id)) defauts.push(`n'a pas modifié ${id}`)
    if (p.logo && !r.logo) defauts.push('logo non reconnu')
    if (!p.logo && r.logo) defauts.push('logo inventé')
    resultats.push({ modele: m.modele, texte: p.texte, bon: !defauts.length, defauts, appel: r.appel, operations: r.operations, refus_du_code: r.refus ?? [] })
    console.log(defauts.length ? 'NON' : 'oui', m.modele, '|', p.texte.slice(0, 60), defauts.length ? '→ ' + defauts.join(', ') : '', JSON.stringify(r.operations).slice(0, 200))
    if (m.fournisseur === 'gemini' && r.appel) await attendre(4500)
  }
  resultats.push({ modele: m.modele, resume: true, tokens })
}
const filtre = phrases.filter((p) => vautLaPeine(p.texte, { photos: p.photos ?? 0 })).length
const parModele = Object.fromEntries(modeles.map((m) => [m.modele, resultats.filter((r) => r.modele === m.modele && !r.resume && r.bon).length]))
writeFileSync(process.argv[2], JSON.stringify({ date: new Date().toISOString(), total_par_modele: phrases.length, bons: parModele, messages_qui_appellent_le_modele: filtre, cout_usd: Math.round(usd * 10000) / 10000, resultats }, null, 2) + '\n')
console.log(JSON.stringify(parModele), `sur ${phrases.length} ; ${filtre} messages sur ${phrases.length} appellent le modèle ; ${usd.toFixed(4)} $`)
