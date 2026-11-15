// Banc d'essai des recherches sur Internet du premier répondant (runner/src/repondant.js et runner/src/recherche.js),
// avec de VRAIES recherches (Tavily puis Exa, offres gratuites) et le modèle des réponses de la production
// (GPT-6.1 Terra chez Coarena, puis chez OpenAI). Les clés restent dans l'environnement, jamais affichées.
//
//   node runner/scripts/banc-recherche.mjs <etiquette> [numéros…]
//
// Pour chaque question de runner/mesures/questions-recherche.json : sources montrées, sources pertinentes (elles
// mentionnent vraiment le sujet), présence d'un site attendu, longueur de la réponse, faits utiles présents, requêtes
// envoyées aux fournisseurs de recherche et temps total. Résultat : runner/mesures/banc-recherche-<etiquette>.json.
import { readFileSync, writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'
import { chercherSurInternet } from '../src/recherche.js'

const etiquette = process.argv[2] || 'essai'
const choisis = process.argv.slice(3).map(Number).filter(Boolean)
const { questions } = JSON.parse(readFileSync(new URL('../mesures/questions-recherche.json', import.meta.url)))

if (!process.env.TAVILY_API_KEY && !process.env.EXA_API_KEY) {
  console.error('Aucune clé de recherche : mesure impossible.')
  process.exit(1)
}

const fournisseurs = [
  { fournisseur: 'coarena', modele: 'gpt-6-1-terra' },
  { fournisseur: 'openai', modele: 'gpt-6.1-terra' },
]

/** Sans accents ni majuscules, pour comparer. */
export const normal = (texte) => String(texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Chaque groupe (variantes séparées par « | ») a au moins une variante dans le texte. */
export const tousPresents = (groupes, texte) => groupes.every((g) => normal(g).split('|').some((v) => normal(texte).includes(v.trim())))

const hote = (url) => {
  try {
    return new URL(url).hostname.replace(/^www\./, '')
  } catch {
    return ''
  }
}

const resultats = []
for (const q of questions) {
  if (choisis.length && !choisis.includes(q.numero)) continue
  const vus = new Map()
  let requetesRecherche = 0
  const chercher = async (requete, options) => {
    const appeler = (url, init) => {
      if (/api\.tavily\.com|api\.exa\.ai/.test(String(url))) requetesRecherche++
      return (options.appeler ?? fetch)(url, init)
    }
    const r = await chercherSurInternet(requete, { ...options, appeler })
    for (const res of r.resultats ?? []) vus.set(res.url, res)
    return r
  }
  const debut = Date.now()
  let r
  try {
    r = await premierRepondant(
      { lieu: 'discussion', message: q.texte, historique: [], contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)', titre: true }, fournisseurs, recherche: { permise: true, restantes: 2 } },
      { clesPresentes: new Set(), chercher },
    )
  } catch (erreur) {
    r = { ok: false, erreur: erreur.message }
  }
  const secondes = Math.round((Date.now() - debut) / 100) / 10
  const sources = (r.sources ?? []).map((s) => {
    const brut = vus.get(s.url)
    const texte = `${s.titre} ${brut?.extrait ?? ''} ${brut?.contenu ?? ''}`
    return { titre: s.titre, url: s.url, pertinente: tousPresents(q.sujet, texte) }
  })
  const reponse = r.reponse ?? ''
  const ligne = {
    numero: q.numero,
    question: q.texte,
    modele: r.modele ?? null,
    recherches_demandees: (r.recherches ?? []).map((x) => x.requete),
    requetes_fournisseurs: requetesRecherche,
    resultats_bruts: vus.size,
    sources: sources.length,
    sources_pertinentes: sources.filter((s) => s.pertinente).length,
    sources_sans_rapport: sources.filter((s) => !s.pertinente).map((s) => hote(s.url)),
    site_attendu: q.attendus.length ? sources.some((s) => q.attendus.some((a) => hote(s.url).endsWith(a))) : null,
    mots: reponse.split(/\s+/).filter(Boolean).length,
    utile: Boolean(reponse) && tousPresents(q.faits, reponse),
    secondes,
    reponse,
    liste_sources: sources,
  }
  resultats.push(ligne)
  console.log(`${q.numero}. ${q.texte}\n   sources ${ligne.sources_pertinentes}/${ligne.sources} pertinentes, attendu ${ligne.site_attendu}, ${ligne.mots} mots, utile ${ligne.utile}, ${ligne.requetes_fournisseurs} requêtes, ${secondes} s`)
}

const somme = (cle) => resultats.reduce((t, x) => t + (Number(x[cle]) || 0), 0)
const avecAttendu = resultats.filter((x) => x.site_attendu !== null)
const resume = {
  etiquette,
  date: new Date().toISOString(),
  questions: resultats.length,
  sources: somme('sources'),
  sources_pertinentes: somme('sources_pertinentes'),
  sources_sans_rapport: resultats.reduce((t, x) => t + x.sources_sans_rapport.length, 0),
  site_attendu_trouve: `${avecAttendu.filter((x) => x.site_attendu).length} sur ${avecAttendu.length}`,
  reponses_utiles: `${resultats.filter((x) => x.utile).length} sur ${resultats.length}`,
  mots_moyens: Math.round(somme('mots') / Math.max(1, resultats.length)),
  requetes_fournisseurs: somme('requetes_fournisseurs'),
  secondes_moyennes: Math.round((somme('secondes') / Math.max(1, resultats.length)) * 10) / 10,
}
writeFileSync(new URL(`../mesures/banc-recherche-${etiquette}.json`, import.meta.url), JSON.stringify({ resume, resultats }, null, 2) + '\n')
console.log(JSON.stringify(resume, null, 2))
