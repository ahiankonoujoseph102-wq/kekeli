// Vitesse du premier mot des réponses courtes (chantier « vitesse », 15 novembre 2026) : chaque modèle reçoit les
// mêmes messages courts par le vrai premier répondant, en mode « mot par mot », et l'on mesure le temps jusqu'au
// premier mot visible et jusqu'à la fin. Aucune hypothèse : on garde le plus rapide seulement s'il est aussi bon.
//   node runner/scripts/mesurer-premier-mot.mjs openai/gpt-6.1-terra freellmapi/gpt-oss-120b gemini/gemini-3.5-flash-lite …
// Résultat : runner/mesures/premier-mot.json (réponses, durées en millisecondes, médianes).
import { writeFileSync } from 'node:fs'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { premierRepondant } from '../src/repondant.js'

const FICHIER = new URL('../mesures/premier-mot.json', import.meta.url)
const REPETITIONS = Number(process.env.REPETITIONS ?? 3)
const MESSAGES = [
  { id: 'salut', texte: 'Salut' },
  { id: 'capitale', texte: 'Quelle est la capitale du Burkina Faso ?' },
  { id: 'tva', texte: 'combien font 18 % de TVA sur 250 000 FCFA' },
  { id: 'domaine', texte: 'C’est quoi un nom de domaine, en deux phrases ?' },
]

const mediane = (v) => {
  const t = [...v].sort((a, b) => a - b)
  return t.length ? t[Math.floor((t.length - 1) / 2)] : null
}

const modeles = process.argv.slice(2)
const cles = fournisseursAvecCle(process.env)
const resultats = []
for (const cle of modeles) {
  const [fournisseur, ...reste] = cle.split('/')
  const modele = reste.join('/')
  const lignes = []
  for (let r = 0; r < REPETITIONS; r++) {
    for (const m of MESSAGES) {
      const depart = performance.now()
      let premier = null
      try {
        const reponse = await premierRepondant(
          { lieu: 'discussion', message: m.texte, contexte: { titre: true }, fournisseurs: [{ fournisseur, modele }] },
          { clesPresentes: cles, env: process.env, surTexte: (e) => { if (e.type === 'texte' && premier === null) premier = performance.now() - depart } },
        )
        lignes.push({ message: m.id, ok: reponse.ok !== false, decision: reponse.decision, premier_mot: premier === null ? null : Math.round(premier), fin: Math.round(performance.now() - depart), reponse: reponse.reponse ?? null, modele_servi: reponse.modele })
      } catch (erreur) {
        lignes.push({ message: m.id, ok: false, erreur: String(erreur.message).slice(0, 120) })
      }
    }
  }
  const bons = lignes.filter((l) => l.ok && l.premier_mot !== null)
  const resume = { modele: cle, essais: lignes.length, reussis: bons.length, premier_mot_median: mediane(bons.map((l) => l.premier_mot)), fin_mediane: mediane(bons.map((l) => l.fin)) }
  console.log(JSON.stringify(resume))
  resultats.push({ ...resume, lignes })
}
writeFileSync(FICHIER, JSON.stringify({ date: new Date().toISOString(), repetitions: REPETITIONS, resultats }, null, 2) + '\n')
