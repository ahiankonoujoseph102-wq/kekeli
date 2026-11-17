// Banc des trois questions avant une vidéo (étape 5 du chantier des vidéos motion design) : le style, le format et la
// voix off, avec leurs choix exacts ; une question dont le client a déjà donné la réponse n'est pas posée. Modèles
// gratuits d'abord (comme en production depuis « OpenAI en dernier »). Aucune clé affichée.
//
//   node runner/scripts/mesurer-questions-video.mjs <etiquette>
// Résultat : runner/mesures/questions-video-<etiquette>.json. Variable MODELES (« fournisseur/modèle,… »).
import { writeFileSync } from 'node:fs'
import { premierRepondant } from '../src/repondant.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'

const etiquette = process.argv[2] || 'essai'
const fournisseurs = process.env.MODELES
  ? process.env.MODELES.split(',').map((m) => ({ fournisseur: m.split('/')[0], modele: m.split('/').slice(1).join('/') }))
  : [{ fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }, { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }]

const STYLES = ['Dynamique', 'Sobre et professionnel', 'Produit et boutique', 'Témoignage', 'Éducatif']
const FORMATS = ['Vertical', 'Carré', 'Horizontal']
const VOIX = ['Voix de femme', 'Voix d’homme', 'Ma propre voix']
// « attendu » : les questions qui doivent être posées (s : style, f : format, v : voix).
const DEMANDES = [
  { texte: 'Fais-moi une vidéo publicitaire pour mon pressing Clean Express à Agoè, chemise lavée et repassée 500 FCFA, WhatsApp 91 11 11 11', attendu: 'sfv' },
  { texte: 'je veux une vidéo pour ma boutique de pagnes', attendu: 'sfv' },
  { texte: 'Crée une vidéo pour mon statut WhatsApp : mon restaurant Chez Dédé à Bè, attiéké poisson 1500 FCFA, contact 90 00 00 00', attendu: 'sv' },
  { texte: 'Une vidéo témoignage pour mon salon de coiffure Belle Afi, ma cliente Akossiwa a dit : « Mes tresses tiennent un mois, je ne vais plus ailleurs »', attendu: 'fv' },
  // « Ma propre voix » sans son joint : Kekeli peut aussi écrire tout de suite le texte à lire (règle de la voix du client).
  { texte: 'Fais une vidéo éducative de 45 secondes pour expliquer comment créer un compte Mixx by Yas, avec ma propre voix', attendu: 'f', ouTexteALire: true },
  { texte: 'Vidéo YouTube sobre et professionnelle pour présenter mon cabinet comptable Kodjo Conseil à Lomé', attendu: 'v' },
]

const resultats = []
for (const d of DEMANDES) {
  const debut = Date.now()
  const r = await premierRepondant({ lieu: 'discussion', message: d.texte, historique: [], contexte: { pays: 'Togo', monnaie: 'franc CFA (FCFA)', titre: true }, fournisseurs }, { clesPresentes: fournisseursAvecCle() })
  const liste = r.questions?.liste ?? []
  const tous = liste.map((q) => q.choix.join(' | '))
  const pose = {
    s: liste.some((q) => STYLES.every((c) => q.choix.includes(c))),
    f: liste.some((q) => FORMATS.every((c) => q.choix.some((x) => x.startsWith(c)))),
    v: liste.some((q) => VOIX.every((c) => q.choix.includes(c))),
  }
  let obtenu = Object.entries(pose).filter(([, v]) => v).map(([k]) => k).join('')
  const texteALire = d.ouTexteALire && r.decision === 'repondre' && !r.questions && /enregistrer ma voix|lisez|à lire|script/i.test(r.reponse ?? '')
  const bon = texteALire || (r.questions?.outil === 'creer_video' && liste.length <= 3 && [...'sfv'].every((k) => d.attendu.includes(k) === pose[k]))
  if (texteALire) obtenu = 'texte à lire'
  resultats.push({ texte: d.texte, attendu: d.attendu, obtenu, bon, outil: r.outil ?? r.questions?.outil ?? null, questions: liste, secondes: Math.round((Date.now() - debut) / 100) / 10 })
  console.log(`${bon ? 'bon ' : 'FAUX'} attendu ${d.attendu.padEnd(3)} obtenu ${obtenu.padEnd(3)} ${d.texte.slice(0, 60)}`)
  if (!bon) console.log(`     ${r.questions ? tous.join(' / ') : `décision : ${r.decision} ${r.outil ?? ''}`}`)
}
const bons = resultats.filter((x) => x.bon).length
writeFileSync(new URL(`../mesures/questions-video-${etiquette}.json`, import.meta.url), JSON.stringify({ date: new Date().toISOString(), modeles: fournisseurs, bons: `${bons} sur ${resultats.length}`, resultats }, null, 2) + '\n')
console.log(`${bons} sur ${resultats.length}`)
