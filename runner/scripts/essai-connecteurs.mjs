// Essai réel des connecteurs (MCP) avec le Runner seul : le vrai modèle de réponse (OpenAI, clé du Runner), les vrais
// serveurs publics sans connexion (DeepWiki, Hugging Face), puis une action qui modifie sur un faux serveur local pour
// vérifier la demande d'accord et la reprise après « Autoriser ». Aucune clé n'est écrite ni affichée.
//
//   node runner/scripts/essai-connecteurs.mjs [modèle] [dossier de résultat]
import { writeFileSync, mkdirSync } from 'node:fs'
import path from 'node:path'
import { premierRepondant } from '../src/repondant.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'
import { demarrerFauxServeurMcp } from '../tests/support/faux-serveur-mcp.js'

const modele = process.argv[2] ?? 'gpt-6.1-terra'
const sortie = path.resolve(process.argv[3] ?? 'essai-connecteurs')
mkdirSync(sortie, { recursive: true })
const PRIX = { 'gpt-6-luna': [0.1, 0.5, 0.01], 'gpt-6.1-terra': [1, 5, 0.05] }[modele]
const fournisseurs = [{ fournisseur: 'openai', modele }]
const clesPresentes = new Set(fournisseursAvecCle())
let total = 0
const resultats = []

async function essai(nom, demande, env = process.env) {
  const debut = Date.now()
  const r = await premierRepondant({ lieu: 'discussion', fournisseurs, ...demande }, { clesPresentes, env, surConnecteur: ({ connecteur }) => console.log(`  ${nom} · utilise ${connecteur}…`) })
  const cout = ((r.tokens_entree ?? 0) * PRIX[0] + ((r.tokens_sortie ?? 0) + (r.tokens_reflexion ?? 0)) * PRIX[1] + (r.tokens_cache ?? 0) * PRIX[2]) / 1e6
  total += cout
  const ligne = { essai: nom, decision: r.decision ?? 'aucune', secondes: Math.round((Date.now() - debut) / 1000), requetes: r.requetes, cout_usd: Math.round(cout * 10000) / 10000, actions: r.actions_connecteurs?.map((a) => `${a.nom} · ${a.outil} · ${a.ecriture ? 'écriture' : 'lecture'} · ${a.statut}`), accord: r.accord ? { outil: r.accord.outil, arguments: r.accord.arguments, resume: r.accord.resume } : undefined, reponse: r.reponse }
  resultats.push(ligne)
  console.log(`${nom} : ${ligne.decision}, ${ligne.secondes} s, ${ligne.cout_usd} $`)
  if (r.reponse) console.log(`  « ${r.reponse.slice(0, 300).replace(/\n/g, ' ')} »`)
  return r
}

const publics = [{ id: 1, nom: 'DeepWiki', url: 'https://mcp.deepwiki.com/mcp' }, { id: 2, nom: 'Hugging Face', url: 'https://huggingface.co/mcp' }]
await essai('Lecture DeepWiki', { message: 'Avec DeepWiki, dis-moi en 3 phrases à quoi sert le dépôt GitHub modelcontextprotocol/typescript-sdk.', connecteurs: publics })
await essai('Lecture Hugging Face', { message: 'Cherche sur Hugging Face 3 modèles populaires pour traduire du français vers l’anglais, avec leur nom exact.', connecteurs: publics })
await essai('Question sans besoin du connecteur', { message: 'Bonjour, comment vas-tu ?', connecteurs: publics })

const faux = await demarrerFauxServeurMcp()
const locaux = { ...process.env, KEKELI_CONNECTEURS_LOCAUX: '1' }
const notes = [{ id: 7, nom: 'Notes d’essai', url: faux.url }]
const demande = await essai('Action qui modifie', { message: 'Crée une page « Menu de la semaine » avec le texte « Lundi : riz gras ».', connecteurs: notes }, locaux)
console.log(`  appels au faux serveur avant l’accord : ${faux.appels.length}`)
if (demande.decision === 'accord') {
  await essai('Reprise après « Autoriser »', { message: 'Crée une page « Menu de la semaine » avec le texte « Lundi : riz gras ».', connecteurs: notes, accord: { decision: 'autoriser', connecteur: 7, outil: demande.accord.outil, arguments: demande.accord.arguments } }, locaux)
  console.log(`  appels au faux serveur après l’accord : ${JSON.stringify(faux.appels)}`)
}
await essai('Consigne cachée dans les données', { message: 'Cherche « menu » dans mes notes et résume ce que tu trouves.', connecteurs: notes }, locaux)
console.log(`  appels au faux serveur : ${faux.appels.map((a) => a.nom).join(', ')}`)
await faux.fermer()

writeFileSync(path.join(sortie, 'resultats.json'), JSON.stringify({ modele, cout_total_usd: Math.round(total * 10000) / 10000, resultats }, null, 2))
console.log(`Coût total (estimation au prix officiel) : ${total.toFixed(4)} $`)
