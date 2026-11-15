// Premier mot des modèles Gemini disponibles chez Google (offre gratuite, clé GEMINI_API_KEY), avec la vraie requête
// du premier répondant (mêmes consignes, mêmes outils), en mode « mot par mot », et trois réglages de réflexion :
// celui de Kekeli (« low » pour une question simple de Gemini 3), « minimal » (Gemini 3) et aucun (budget 0, Gemini 2.5).
// Peu d'appels par modèle, pour ne pas épuiser les quotas gratuits du jour.
//   node runner/scripts/mesurer-gemini.mjs [modèles…]
// Résultat : runner/mesures/premier-mot-gemini.json.
import { writeFileSync } from 'node:fs'
import { requeteFournisseur } from '../src/repondant.js'
import { lireFlux, requeteEnFlux } from '../src/flux.js'

const MODELES = process.argv.slice(2).length ? process.argv.slice(2) : ['gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-3-flash-preview', 'gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-flash-lite-latest']
const MESSAGES = ['Salut', 'Quelle est la capitale du Burkina Faso ?']
const REPETITIONS = Number(process.env.REPETITIONS ?? 2)

const mediane = (v) => { const t = [...v].sort((a, b) => a - b); return t.length ? t[Math.floor((t.length - 1) / 2)] : null }
const reglages = (modele) => /^gemini-[3-9]/.test(modele)
  ? [['kekeli (low)', null], ['minimal', { thinkingLevel: 'minimal' }]]
  : [['kekeli (par défaut)', null], ['aucune (budget 0)', { thinkingBudget: 0 }]]

const resultats = []
for (const modele of MODELES) {
  for (const [nom, reflexion] of reglages(modele)) {
    const essais = []
    for (let r = 0; r < REPETITIONS; r++) {
      for (const message of MESSAGES) {
        const requete = requeteFournisseur({ fournisseur: 'gemini', modele }, { lieu: 'discussion', message, contexte: { pays: 'Togo', monnaie: 'FCFA', titre: true } })
        if (reflexion) requete.corps.generationConfig = { ...requete.corps.generationConfig, thinkingConfig: reflexion }
        const flux = requeteEnFlux('gemini', requete)
        const debut = Date.now()
        let premier = null
        let texte = ''
        try {
          const reponse = await fetch(flux.url, { method: 'POST', headers: requete.entetes, body: JSON.stringify(flux.corps), signal: AbortSignal.timeout(60000) })
          if (!reponse.ok) { essais.push({ message, erreur: reponse.status }); continue }
          const fin = await lireFlux('gemini', reponse.body, (m) => { texte += m; if (premier === null && /\S/.test(texte.replace(/^TITRE[^\n]*\n?/, ''))) premier = Date.now() - debut })
          essais.push({ message, premier_mot_ms: premier, fin_ms: Date.now() - debut, reflexion_tokens: fin.usageMetadata?.thoughtsTokenCount ?? 0, texte: texte.slice(0, 160) })
        } catch (erreur) {
          essais.push({ message, erreur: String(erreur.message).slice(0, 80) })
        }
      }
    }
    const bons = essais.filter((e) => e.premier_mot_ms != null)
    const ligne = { modele, reflexion: nom, essais: essais.length, reussis: bons.length, premier_mot_median_ms: mediane(bons.map((e) => e.premier_mot_ms)), premier_mot_pire_ms: bons.length ? Math.max(...bons.map((e) => e.premier_mot_ms)) : null, erreurs: essais.filter((e) => e.erreur).map((e) => e.erreur), detail: essais }
    console.log(JSON.stringify({ modele, reflexion: nom, reussis: `${bons.length}/${essais.length}`, median: ligne.premier_mot_median_ms, pire: ligne.premier_mot_pire_ms, erreurs: ligne.erreurs }))
    resultats.push(ligne)
  }
}
writeFileSync(new URL('../mesures/premier-mot-gemini.json', import.meta.url), JSON.stringify({ date: new Date().toISOString(), repetitions: REPETITIONS, resultats }, null, 2) + '\n')
