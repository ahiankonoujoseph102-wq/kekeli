// Le premier répondant classe-t-il bien une demande de changement (« petite » retouche ou « grande ») ?
//   node runner/scripts/mesurer-ampleur.mjs <sortie.json> [fournisseur/modèle]
import { writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'

const [fournisseur, modele] = (process.argv[3] ?? 'gemini/gemini-3.5-flash-lite').split('/')
const phrases = [
  ['mets le prix à 9 500 FCFA', 'petite', 'image'], ['mets le titre en bleu', 'petite', 'document'], ['corrige la faute dans le deuxième paragraphe', 'petite', 'document'],
  ['enlève les sous-titres', 'petite', 'video'], ['ajoute une partie sur nos tarifs et réécris toute l’introduction', 'grande', 'document'], ['refais la vidéo en format horizontal avec d’autres images', 'grande', 'video'],
]
const resultats = []
for (const [texte, attendu, type] of phrases) {
  const r = await premierRepondant({ lieu: 'document', message: texte, historique: [], contexte: { type, nom: 'Projet', pays: 'Togo' }, fournisseurs: [{ fournisseur, modele }] }, { clesPresentes: new Set(['gemini']) })
  const obtenu = r.decision === 'outil' ? r.arguments?.ampleur ?? '(rien)' : r.decision
  resultats.push({ texte, attendu, obtenu, bon: obtenu === attendu })
  console.log(obtenu === attendu ? 'oui' : 'NON', texte, '→', obtenu)
}
writeFileSync(process.argv[2], JSON.stringify({ modele: `${fournisseur}/${modele}`, bons: resultats.filter((r) => r.bon).length, total: resultats.length, resultats }, null, 2))
