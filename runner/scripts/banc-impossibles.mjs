// Banc des demandes impossibles (15 novembre 2026) : chaque demande de runner/mesures/impossibles-8.json part au vrai
// premier répondant d'une discussion (Togo, FCFA), avec un modèle gratuit. On garde la réponse pour vérifier qu'elle
// dit en une phrase ce qui est impossible et pourquoi, puis propose ce que Kekeli peut faire à la place.
//   node runner/scripts/banc-impossibles.mjs <etiquette>
//   MODELES=gemini/gemini-3.5-flash-lite : l'ordre des modèles.
import { readFileSync, writeFileSync } from 'node:fs'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { premierRepondant } from '../src/repondant.js'

const etiquette = process.argv[2] || 'essai'
const { demandes } = JSON.parse(readFileSync(new URL('../mesures/impossibles-8.json', import.meta.url)))
const fournisseurs = (process.env.MODELES || 'gemini/gemini-3.5-flash-lite').split(',').map((m) => ({ fournisseur: m.split('/')[0], modele: m.split('/').slice(1).join('/') }))
const choisis = process.argv.slice(3).map(Number).filter(Boolean)
const fichier = new URL(`../mesures/banc-impossibles-${etiquette}.json`, import.meta.url)
// Une reprise garde les réponses déjà obtenues (numéros donnés après l'étiquette).
let resultats = []
try { resultats = choisis.length ? JSON.parse(readFileSync(fichier)).resultats.filter((r) => !choisis.includes(r.numero)) : [] } catch {}
for (const d of demandes) {
  if (choisis.length && !choisis.includes(d.numero)) continue
  // Le modèle gratuit accepte 15 requêtes par minute : une pause entre deux demandes.
  await new Promise((r) => setTimeout(r, Number(process.env.PAUSE_S ?? 6) * 1000))
  const debut = Date.now()
  const r = await premierRepondant(
    { lieu: 'discussion', message: d.texte, contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)', photos: 0 }, fournisseurs, recherche: { permise: false, restantes: 0 } },
    { clesPresentes: fournisseursAvecCle(process.env), env: process.env },
  ).catch((erreur) => ({ ok: false, erreur: erreur.message }))
  const ligne = { numero: d.numero, brut: r.reponse || r.outil ? undefined : Object.keys(r), texte: d.texte, decision: r.decision ?? null, outil: r.outil ?? null, questions: r.questions ?? null, reponse: r.reponse ?? null, erreur: r.erreur ?? null, modele: r.modele ?? null, duree_ms: Date.now() - debut }
  resultats.push(ligne)
  console.log(`\n${d.numero}. ${d.texte}\n→ ${r.outil ? `outil ${r.outil}` : ''}${r.questions ? ` questions ${JSON.stringify(r.questions).slice(0, 400)}` : ''}\n${String(r.reponse ?? r.erreur ?? '').slice(0, 700)}`)
}
resultats.sort((a, b) => a.numero - b.numero)
writeFileSync(fichier, JSON.stringify({ date: new Date().toISOString(), etiquette, modeles: fournisseurs, resultats }, null, 2) + '\n')
