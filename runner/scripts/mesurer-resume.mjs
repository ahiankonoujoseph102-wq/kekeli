// Mesure du résumé des longues discussions : une discussion d'essai de 24 messages (faits importants au début), puis
// 3 questions qui demandent ces faits. « Avant » : les 10 derniers messages seulement (comme le site jusqu'ici).
// « Après » : les 6 derniers messages et le résumé du début. Qualité (faits retrouvés) et tokens envoyés.
//   node runner/scripts/mesurer-resume.mjs <fichier-de-sortie.json>
import { writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'
import { MESSAGES_ENTIERS, resumerDiscussion } from '../src/resume.js'

const debut = [
  ['client', 'Bonjour, je m’appelle Esi Agbodjan et j’ai une boutique de robes, « Kafui Mode », à Bè Kpota, près de la pharmacie du Bon Berger.'],
  ['assistant', 'Enchanté Esi ! Je peux vous aider pour votre boutique Kafui Mode.'],
  ['client', 'Mon numéro WhatsApp pour les commandes est le +228 91 23 45 67.'],
  ['assistant', 'C’est noté.'],
  ['client', 'Ma robe la plus vendue est la robe « Akossiwa » à 18 000 FCFA. Je veux toujours mettre ce prix en avant.'],
  ['assistant', 'Très bien, la robe Akossiwa à 18 000 FCFA.'],
  ['client', 'Pour mes visuels je veux les couleurs vert émeraude et or, jamais de rouge.'],
  ['assistant', 'Vert émeraude et or, sans rouge : c’est noté.'],
  ['client', 'Important : je ne livre jamais le dimanche.'],
  ['assistant', 'D’accord, pas de livraison le dimanche.'],
]
const bavardage = Array.from({ length: 14 }, (_, i) => i % 2 === 0
  ? ['client', `Question ${i / 2 + 1} : comment écrire une bonne publication Facebook pour attirer des clientes le week-end ? Donne des conseils détaillés.`]
  : ['assistant', 'Voici quelques conseils : une photo nette de la robe portée, un texte court avec le bénéfice, une question pour engager, un appel à l’action clair, publier le vendredi soir et le samedi matin, répondre vite aux commentaires, utiliser 3 à 5 mots-dièse locaux. '.repeat(3)])
const discussion = [...debut, ...bavardage].map(([role, contenu]) => ({ role, contenu }))
const questions = [
  { texte: 'Rappelle-moi mon numéro WhatsApp, s’il te plaît.', doit: ['91 23 45 67'] },
  { texte: 'Quel est le prix de ma robe la plus vendue ?', doit: ['18 000'] },
  { texte: 'Quelles couleurs avais-je choisies pour mes visuels, et est-ce que je livre le dimanche ?', doit: ['émeraude', 'or'] },
]
const PRIX = { 'gpt-6-luna': [0.1, 0.5], 'gpt-6.1-terra': [1, 5] }
const resultats = {}
for (const modele of ['gpt-6-luna', 'gpt-6.1-terra']) {
  const fournisseurs = [{ fournisseur: 'openai', modele }]
  const cout = (c) => ((c?.tokens_entree ?? 0) * PRIX[modele][0] + ((c?.tokens_sortie ?? 0) + (c?.tokens_reflexion ?? 0)) * PRIX[modele][1]) / 1e6
  const r = await resumerDiscussion({ messages: discussion.slice(0, -MESSAGES_ENTIERS) }, { fournisseurs, clesPresentes: new Set() })
  resultats[modele] = { resume: r.resume, resume_tokens: r.consommation, resume_usd: cout(r.consommation), avant: [], apres: [] }
  for (const q of questions) {
    for (const [cas, historique, contexte] of [['avant', discussion.slice(-10), {}], ['apres', discussion.slice(-MESSAGES_ENTIERS), { resume: r.resume }]]) {
      const rep = await premierRepondant({ lieu: 'discussion', message: q.texte, historique, contexte: { pays: 'Togo', monnaie: 'FCFA', ...contexte }, fournisseurs }, { clesPresentes: new Set() })
      const texte = String(rep.reponse ?? '')
      resultats[modele][cas].push({ question: q.texte, bon: q.doit.every((d) => texte.includes(d)), tokens_entree: rep.tokens_entree ?? 0, usd: cout(rep), reponse: texte.slice(0, 300) })
    }
  }
  for (const cas of ['avant', 'apres']) {
    const l = resultats[modele][cas]
    console.log(modele, cas, `${l.filter((x) => x.bon).length}/${l.length} faits retrouvés`, 'entrée moyenne', Math.round(l.reduce((a, x) => a + x.tokens_entree, 0) / l.length), 'tokens', `${(l.reduce((a, x) => a + x.usd, 0) / l.length).toFixed(5)} $ par message`)
  }
  console.log(modele, 'résumé', resultats[modele].resume_tokens?.tokens_entree, 'tokens d’entrée,', resultats[modele].resume_usd.toFixed(5), '$ (une fois)')
}
writeFileSync(process.argv[2], JSON.stringify(resultats, null, 2))
