// Banc des réponses du premier répondant (chantier « réponses », 15 novembre 2026) : 20 vraies questions, posées au vrai
// premier répondant comme dans une discussion du site (Togo, FCFA, titre demandé, recherche permise), avec le modèle des
// réponses de la production (GPT-6.1 Terra chez Coarena, puis chez OpenAI en secours). Les clés restent dans
// l'environnement, jamais affichées.
//
//   node runner/scripts/banc-reponses.mjs <etiquette> [numéros…]
//
// Résultat : runner/mesures/banc-reponses-<etiquette>.json (réponse complète, mots, durée, tokens, coût estimé au prix
// officiel d'OpenAI, même quand Coarena répond gratuitement). Les notes de 1 à 10 sont données à la lecture, dans
// docs/captures/reponses.md.
import { readFileSync, writeFileSync } from 'node:fs'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { premierRepondant } from '../src/repondant.js'

const etiquette = process.argv[2] || 'essai'
const choisis = process.argv.slice(3).map(Number).filter(Boolean)
const { questions } = JSON.parse(readFileSync(new URL('../mesures/questions-reponses.json', import.meta.url)))

// Prix officiels d'OpenAI pour GPT-6.1 Terra, en dollars par million de tokens (estimation pour une réponse payante).
const PRIX = { entree: 1, sortie: 5 }
const FCFA_PAR_DOLLAR = 600
// MODELE=coarena/gemini-3-8-flash : un autre modèle déjà configuré, pour comparer (sans secours).
const fournisseurs = process.env.MODELE
  ? [{ fournisseur: process.env.MODELE.split('/')[0], modele: process.env.MODELE.split('/').slice(1).join('/') }]
  : [
      { fournisseur: 'coarena', modele: 'gpt-6-1-terra' },
      { fournisseur: 'openai', modele: 'gpt-6.1-terra' },
    ]

const resultats = []
for (const q of questions) {
  if (choisis.length && !choisis.includes(q.numero)) continue
  const debut = Date.now()
  let premierMot = null
  const r = await premierRepondant(
    { lieu: 'discussion', message: q.texte, contexte: { pays: 'Togo', monnaie: 'FCFA', photos: 0, titre: true }, fournisseurs, recherche: { permise: true, restantes: 2 } },
    { clesPresentes: fournisseursAvecCle(process.env), env: process.env, surTexte: (e) => { if (e.type === 'texte' && premierMot === null) premierMot = Date.now() - debut } },
  ).catch((erreur) => ({ ok: false, erreur: erreur.message }))
  const entree = Number(r.tokens_entree) || 0
  const sortie = (Number(r.tokens_sortie) || 0) + (Number(r.tokens_reflexion) || 0)
  const cout = (entree * PRIX.entree + sortie * PRIX.sortie) / 1e6
  const reponse = r.reponse ?? (r.decision === 'outil' ? `[outil ${r.outil}]` : r.questions ? `[questions] ${JSON.stringify(r.questions)}` : '')
  const ligne = {
    numero: q.numero, genre: q.genre, question: q.texte, decision: r.decision, fournisseur: r.fournisseur, modele: r.modele,
    mots: String(reponse).split(/\s+/).filter(Boolean).length, premier_mot_ms: premierMot, duree_ms: Date.now() - debut,
    tokens_entree: entree, tokens_sortie: sortie, recherches: (r.recherches ?? []).length, cout_usd: Number(cout.toFixed(6)),
    titre: r.titre ?? null, reponse,
  }
  resultats.push(ligne)
  console.log(`${q.numero}. ${q.genre} : ${ligne.mots} mots, ${(ligne.duree_ms / 1000).toFixed(1)} s, ${(cout * FCFA_PAR_DOLLAR).toFixed(2)} FCFA (${ligne.fournisseur ?? 'échec'})`)
}
const moyenne = (cle) => resultats.reduce((s, l) => s + (l[cle] ?? 0), 0) / Math.max(1, resultats.length)
const resume = { questions: resultats.length, mots_moyens: Math.round(moyenne('mots')), cout_moyen_usd: Number(moyenne('cout_usd').toFixed(6)), cout_moyen_fcfa: Number((moyenne('cout_usd') * FCFA_PAR_DOLLAR).toFixed(2)), duree_moyenne_s: Number((moyenne('duree_ms') / 1000).toFixed(1)) }
console.log(JSON.stringify(resume))
writeFileSync(new URL(`../mesures/banc-reponses-${etiquette}.json`, import.meta.url), JSON.stringify({ date: new Date().toISOString(), etiquette, resume, resultats }, null, 2) + '\n')
