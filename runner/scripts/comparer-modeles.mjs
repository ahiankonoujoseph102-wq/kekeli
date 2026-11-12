// Comparaison réelle des modèles du premier répondant sur 12 vraies questions variées (docs/plan.md, partie 26.15).
// Chaque question passe par le vrai premier répondant (consigne, outils, recherche), avec un seul modèle à la fois.
//   node runner/scripts/comparer-modeles.mjs gemini/gemini-3.5-flash-lite openai/gpt-6-luna …
// Résultat : runner/mesures/comparaison-modeles.json (réponses, durée, tokens, coût estimé au prix officiel).
// Garde-fou : le script s'arrête avant de dépasser BUDGET_OPENAI dollars de dépense OpenAI (0,45 $ par défaut).
import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { premierRepondant } from '../src/repondant.js'

const FICHIER = new URL('../mesures/comparaison-modeles.json', import.meta.url)
const BUDGET = Number(process.env.BUDGET_OPENAI ?? 0.45)

// Prix officiels en dollars par million de tokens (pages lues le 12 novembre 2026).
const PRIX = {
  'gemini/gemini-3.5-flash-lite': { entree: 0.3, sortie: 2.5, cache: 0.03 },
  'gemini/gemini-3.1-flash-lite': { entree: 0.25, sortie: 1.5, cache: 0.025 },
  'gemini/gemini-3.8-flash': { entree: 0.75, sortie: 3.75, cache: 0.075 },
  'gemini/gemini-3.6-flash': { entree: 0.75, sortie: 3.75, cache: 0.075 },
  'openai/gpt-6-luna': { entree: 0.1, sortie: 0.5, cache: 0.01 },
  'openai/gpt-6.1-terra': { entree: 1, sortie: 5, cache: 0.05 },
  'openai/gpt-6.1-sol': { entree: 2, sortie: 10, cache: 0.1 },
}

export const QUESTIONS = [
  { id: 'boutique', genre: 'conseil d’entreprise', texte: 'je veux ouvrir une boutique de pagnes à Lomé combien il faut pour commencer et comment réussir' },
  { id: 'prix-gateaux', genre: 'conseil d’entreprise', texte: 'Comment fixer le prix de mes gâteaux pour faire vraiment du bénéfice ? Je vends à la maison, à Lomé.' },
  { id: 'domaine', genre: 'explication', texte: 'Explique-moi simplement ce qu’est un nom de domaine et la différence avec un hébergement.' },
  { id: 'capitale', genre: 'question simple', texte: 'Quelle est la capitale du Burkina Faso ?' },
  { id: 'facebook', genre: 'rédaction', texte: 'Écris une publication Facebook pour annoncer l’ouverture de mon salon de coiffure Belle Afi à Kpalimé samedi prochain, avec 20 % de réduction la première semaine.' },
  { id: 'relance', genre: 'rédaction', texte: 'Rédige un courriel poli mais ferme pour relancer un client qui me doit 150 000 FCFA depuis 2 mois.' },
  { id: 'tva', genre: 'calcul simple', texte: 'combien font 18 % de TVA sur 250 000 FCFA' },
  { id: 'marge', genre: 'calcul en plusieurs étapes', texte: 'J’achète des pagnes à 7 500 FCFA pièce. Je veux gagner 30 % du prix de vente, même après une remise de 10 % au client. À quel prix dois-je afficher chaque pagne ?' },
  { id: 'essence', genre: 'question avec recherche', texte: 'quel est le prix du litre de super sans plomb au Togo en ce moment' },
  { id: 'poulets', genre: 'question dictée sans ponctuation', texte: 'bonjour je vends des poulets braisés à adidogomé je veux savoir comment avoir plus de clients avec whatsapp et facebook merci' },
  { id: 'mobile-money', genre: 'question dictée sans ponctuation', texte: 'c est quoi la difference entre mobile money et un compte en banque pour mon petit commerce lequel est mieux' },
  { id: 'restaurant', genre: 'comparaison', texte: 'Pour un petit restaurant, vaut-il mieux une page Facebook, un site web, ou les deux ? Compare et conseille-moi.' },
]

const prix = (cle, r) => {
  const p = PRIX[cle]
  if (!p || !r.ok) return 0
  return ((r.tokens_entree ?? 0) * p.entree + ((r.tokens_sortie ?? 0) + (r.tokens_reflexion ?? 0)) * p.sortie + (r.tokens_cache ?? 0) * p.cache) / 1e6
}

const modeles = process.argv.slice(2)
const resultats = existsSync(FICHIER) ? JSON.parse(readFileSync(FICHIER, 'utf8')) : { essais: [] }
const depenseOpenAI = () => resultats.essais.filter((e) => e.modele.startsWith('openai/')).reduce((t, e) => t + e.cout_usd, 0)
const seulement = process.env.QUESTIONS ? process.env.QUESTIONS.split(',') : null

for (const cle of modeles) {
  const [fournisseur, ...reste] = cle.split('/')
  for (const q of QUESTIONS) {
    if (seulement && !seulement.includes(q.id)) continue
    if (fournisseur === 'openai' && depenseOpenAI() > BUDGET) {
      console.log(`Budget OpenAI atteint (${depenseOpenAI().toFixed(4)} $) : arrêt.`)
      process.exit(0)
    }
    const debut = Date.now()
    const r = await premierRepondant(
      { lieu: 'discussion', message: q.texte, contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)', titre: true }, historique: [], fournisseurs: [{ fournisseur, modele: reste.join('/') }], recherche: { permise: true, restantes: 2 } },
      { clesPresentes: fournisseursAvecCle() },
    ).catch((e) => ({ ok: false, erreur: e.message }))
    const essai = {
      modele: cle,
      question: q.id,
      genre: q.genre,
      ok: Boolean(r.ok),
      secondes: Math.round((Date.now() - debut) / 100) / 10,
      decision: r.decision ?? null,
      titre: r.titre ?? null,
      reflexion: r.reflexion ?? null,
      reponse: r.reponse ?? null,
      sources: (r.sources ?? []).map((s) => s.url),
      recherches: (r.recherches ?? []).length,
      tokens_entree: r.tokens_entree ?? 0,
      tokens_sortie: r.tokens_sortie ?? 0,
      tokens_reflexion: r.tokens_reflexion ?? 0,
      tokens_cache: r.tokens_cache ?? 0,
      refus: r.refus ?? [],
      cout_usd: 0,
    }
    essai.cout_usd = Math.round(prix(cle, essai) * 1e6) / 1e6
    resultats.essais = resultats.essais.filter((e) => !(e.modele === cle && e.question === q.id))
    resultats.essais.push(essai)
    writeFileSync(FICHIER, JSON.stringify(resultats, null, 2) + '\n')
    console.log(`${cle} · ${q.id} : ${essai.ok ? 'ok' : 'ÉCHEC'} en ${essai.secondes} s, ${essai.tokens_entree}+${essai.tokens_sortie}+${essai.tokens_reflexion} tokens, ${essai.cout_usd} $`)
  }
}
console.log(`Dépense OpenAI estimée des essais : ${depenseOpenAI().toFixed(4)} $`)
