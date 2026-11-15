// Banc des réponses du premier répondant (chantier « réponses », 15 novembre 2026) : 20 vraies questions, posées au vrai
// premier répondant comme dans une discussion du site (Togo, FCFA, titre demandé, recherche permise), avec le modèle des
// réponses de la production (GPT-6.1 Terra chez OpenAI, puis GPT-6 Luna en secours). Les clés restent dans
// l'environnement, jamais affichées.
//
//   node runner/scripts/banc-reponses.mjs <etiquette> [numéros…]
//
// Résultat : runner/mesures/banc-reponses-<etiquette>.json (réponse complète, mots, durée, tokens, coût estimé au prix
// officiel d'OpenAI ; prix estimé de la famille pour FreeLLMAPI). Les notes de 1 à 10 sont données à la lecture, dans
// docs/captures/reponses.md.
import { readFileSync, writeFileSync } from 'node:fs'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { premierRepondant } from '../src/repondant.js'

const etiquette = process.argv[2] || 'essai'
const choisis = process.argv.slice(3).map(Number).filter(Boolean)
// Le banc exigeant de 30 questions (chantier « comprendre, répondre et réagir », 15 novembre 2026), noté contre les
// réponses de référence de runner/mesures/reponses-reference-30.md, remplace l'ancien banc de 20 questions (BANC=20
// pour le relancer, pour comparer avec les anciennes mesures).
const { questions } = JSON.parse(readFileSync(new URL(process.env.BANC === '20' ? '../mesures/questions-reponses.json' : '../mesures/questions-30.json', import.meta.url)))

// Prix officiels d'OpenAI, en dollars par million de tokens (entrée, entrée en cache, sortie), comme config/kekeli.php.
const PRIX_MODELES = { 'gpt-6-luna': { entree: 0.1, cache: 0.01, sortie: 0.5 }, 'gpt-6.1-terra': { entree: 1, cache: 0.05, sortie: 5 }, 'gpt-6-1-terra': { entree: 1, cache: 0.05, sortie: 5 } }
const PRIX = { entree: 1, cache: 0.05, sortie: 5 }
const FCFA_PAR_DOLLAR = 600
// MODELE=freellmapi/gpt-oss-120b : un autre modèle déjà configuré, pour comparer (sans secours) ; REFLEXION=minimal pour Gemini 3.
// MODELES=openai/gpt-6.1-terra,openai/gpt-6-luna : l'ordre de la production (avec REFLEXION pour une question riche).
const fournisseurs = process.env.MODELES
  ? process.env.MODELES.split(',').map((m) => ({ fournisseur: m.split('/')[0], modele: m.split('/').slice(1).join('/'), ...(process.env.REFLEXION ? { reflexion: process.env.REFLEXION } : {}) }))
  : process.env.MODELE
  ? [{ fournisseur: process.env.MODELE.split('/')[0], modele: process.env.MODELE.split('/').slice(1).join('/'), ...(process.env.REFLEXION ? { reflexion: process.env.REFLEXION } : {}) }]
  : [
      { fournisseur: 'openai', modele: 'gpt-6.1-terra' },
      { fournisseur: 'openai', modele: 'gpt-6-luna' },
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
  const prix = PRIX_MODELES[r.modele] ?? PRIX
  const cache = Math.min(entree, Number(r.tokens_cache) || 0)
  const cout = ((entree - cache) * prix.entree + cache * prix.cache + sortie * prix.sortie) / 1e6
  const reponse = r.reponse ?? (r.decision === 'outil' ? `[outil ${r.outil}]` : r.questions ? `[questions] ${JSON.stringify(r.questions)}` : '')
  const ligne = {
    numero: q.numero, genre: q.genre, question: q.texte, decision: r.decision, fournisseur: r.fournisseur, modele: r.modele,
    mots: String(reponse).split(/\s+/).filter(Boolean).length, premier_mot_ms: premierMot, duree_ms: Date.now() - debut,
    tokens_entree: entree, tokens_cache: cache, tokens_sortie: sortie, tokens_reflexion: Number(r.tokens_reflexion) || 0, recherches: (r.recherches ?? []).length, cout_usd: Number(cout.toFixed(6)),
    titre: r.titre ?? null, reponse,
  }
  resultats.push(ligne)
  console.log(`${q.numero}. ${q.genre} : ${ligne.mots} mots, ${(ligne.duree_ms / 1000).toFixed(1)} s, ${(cout * FCFA_PAR_DOLLAR).toFixed(2)} FCFA (${ligne.fournisseur ?? 'échec'})`)
}
const moyenne = (cle) => resultats.reduce((s, l) => s + (l[cle] ?? 0), 0) / Math.max(1, resultats.length)
const resume = { questions: resultats.length, mots_moyens: Math.round(moyenne('mots')), cout_moyen_usd: Number(moyenne('cout_usd').toFixed(6)), cout_moyen_fcfa: Number((moyenne('cout_usd') * FCFA_PAR_DOLLAR).toFixed(2)), duree_moyenne_s: Number((moyenne('duree_ms') / 1000).toFixed(1)) }
console.log(JSON.stringify(resume))
writeFileSync(new URL(`../mesures/banc-reponses-${etiquette}.json`, import.meta.url), JSON.stringify({ date: new Date().toISOString(), etiquette, resume, resultats }, null, 2) + '\n')
