// Mesure réelle du premier répondant (runner/src/repondant.js) sur les phrases de runner/mesures/phrases-repondant.json,
// avec les modèles GRATUITS de Google AI Studio seulement (clé GEMINI_API_KEY dans l'environnement, jamais affichée).
// Aucune dépense : une requête par phrase, espacées pour rester sous la limite par minute.
//
//   node runner/scripts/mesurer-repondant.mjs [fichier-de-sortie.json]
import { readFileSync, writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'

const { phrases, contexte_site: contexteSite } = JSON.parse(readFileSync(new URL('../mesures/phrases-repondant.json', import.meta.url)))
const fournisseurs = [
  { fournisseur: 'gemini', modele: process.env.KEKELI_MODELE_GEMINI || 'gemini-3.5-flash-lite' },
  { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' },
]
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))

/**
 * Un « doute » : la réponse se termine par une question qui propose de créer ou de modifier le site. Une question de
 * politesse (« Comment puis-je vous aider ? ») reste une réponse.
 */
const decisionDe = (r) => {
  if (r.decision === 'outil') return 'outil'
  const fin = r.reponse.trim().split(/(?<=[.!?…])\s+/).at(-1) ?? ''
  return /\?\s*$/.test(fin) && /(voulez|souhaitez|aimeriez|désirez)[- ]vous (que je|changer|ajouter|modifier|mettre|enlever)|je (crée|cree|modifie)/i.test(fin) ? 'doute' : 'repondre'
}

/**
 * Les nombres sont écrits en chiffres : il y en a au moins un, et aucun montant ni extension n'est écrit en lettres
 * (« dix dollars », « six mille francs », « point com »).
 */
export function chiffresBienEcrits(texte) {
  const enLettres = /\b(un|une|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|quinze|vingt|trente|quarante|cinquante|soixante|cent|cents|mille)\b[\s-]+(?:(?:et|à|a)\s+)?(?:\w+\s+){0,2}?(dollars?|francs?|fcfa|euros?)\b|\bpoint\s+(com|tg|fr|org|net)\b/iu
  return /\d/.test(texte) && !enLettres.test(texte)
}

// « --recompter fichier.json » : recompte un résultat déjà enregistré, sans aucune requête.
if (process.argv[2] === '--recompter') {
  const fichier = process.argv[3]
  const d = JSON.parse(readFileSync(fichier))
  for (const r of d.resultats) {
    r.obtenu = r.outil ? 'outil' : r.reponse ? decisionDe({ decision: 'repondre', reponse: r.reponse }) : 'échec'
    const phrase = phrases[r.numero - 1]
    r.chiffres = phrase?.chiffres && r.reponse ? chiffresBienEcrits(r.reponse) : null
    r.bon = r.attendu.includes(r.obtenu) && r.chiffres !== false
  }
  d.bons = d.resultats.filter((r) => r.bon).length
  writeFileSync(fichier, JSON.stringify(d, null, 2) + '\n')
  console.log(`${fichier} : ${d.bons} sur ${d.total}`)
  process.exit(0)
}

if (!process.env.GEMINI_API_KEY) {
  console.error('Clé Gemini absente : mesure impossible.')
  process.exit(1)
}

const resultats = []
for (const [i, p] of phrases.entries()) {
  const r = await premierRepondant(
    { lieu: p.lieu, message: p.texte, historique: p.historique ?? [], contexte: p.lieu === 'site' ? contexteSite : { pays: 'Togo', monnaie: 'franc CFA (FCFA)' }, fournisseurs },
    { clesPresentes: new Set(['gemini']) },
  ).catch((e) => ({ ok: false, erreur: e.message, refus: [] }))
  const obtenu = r.ok ? decisionDe(r) : 'échec'
  const chiffres = p.chiffres && r.reponse ? chiffresBienEcrits(r.reponse) : null
  const bon = p.attendu.includes(obtenu) && chiffres !== false
  resultats.push({ numero: i + 1, lieu: p.lieu, texte: p.texte, attendu: p.attendu, obtenu, bon, chiffres, outil: r.outil ?? null, reponse: r.reponse ?? null, modele: r.modele ?? null, tokens_entree: r.tokens_entree ?? 0, tokens_sortie: (r.tokens_sortie ?? 0) + (r.tokens_reflexion ?? 0) })
  console.log(`${bon ? 'bon ' : 'FAUX'} ${String(i + 1).padStart(2)} [${p.lieu}] ${p.texte.slice(0, 60)} → ${obtenu}${r.outil ? ` (${r.outil})` : ''}${chiffres === false ? ' (nombres en lettres)' : ''}`)
  await attendre(4500)
}
const bons = resultats.filter((r) => r.bon).length
console.log(`\nBonnes décisions : ${bons} sur ${resultats.length} (${Math.round((bons / resultats.length) * 100)} %)`)
writeFileSync(process.argv[2] || 'runner/mesures/resultats-repondant.json', JSON.stringify({ date: new Date().toISOString(), bons, total: resultats.length, resultats }, null, 2) + '\n')
