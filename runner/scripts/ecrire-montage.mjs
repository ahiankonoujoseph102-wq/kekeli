// Étape 4 du chantier des vidéos motion design : le modèle écrit le plan de montage à partir de la demande du client
// (fournisseurs gratuits d'abord, comme en production depuis la décision « OpenAI en dernier »), Kekeli le vérifie et
// le fait corriger une fois si besoin. Le plan est ensuite rendu par scripts/essai-montage.mjs. Aucune clé affichée.
//
//   cd runner && node scripts/ecrire-montage.mjs <demande.txt> <plan.json> [--duree 30] [--texte texte-impose.txt]
// « --texte » : le texte exact que la voix doit dire (la voix du client, ou un script donné par le client).
// Variable MODELES (« fournisseur/modèle,… ») pour un autre ordre.
import { readFileSync, writeFileSync } from 'node:fs'
import { ecrireMontage } from '../src/videos/montage/plan-modele.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'

const [fichierDemande, sortie, ...reste] = process.argv.slice(2)
const option = (nom) => (reste.includes(nom) ? reste[reste.indexOf(nom) + 1] : null)
if (!fichierDemande || !sortie) {
  console.error('Usage : node scripts/ecrire-montage.mjs <demande.txt> <plan.json> [--duree 30] [--texte texte.txt]')
  process.exit(1)
}
const FOURNISSEURS = process.env.MODELES
  ? process.env.MODELES.split(',').map((m) => ({ fournisseur: m.split('/')[0], modele: m.split('/').slice(1).join('/') }))
  : [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }, { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }]
// Prix payants (dollars par million de tokens, config/kekeli.php) : l'énergie compte le prix payant, même en gratuit.
const PRIX = { 'gemini-3.5-flash-lite': [0.3, 2.5], 'gemini-3.1-flash-lite': [0.25, 1.5], 'gpt-6.1-terra': [0.75, 4.5], 'gpt-6.1-sol': [2.5, 15] }

const demande = readFileSync(fichierDemande, 'utf8').trim()
const texte = option('--texte') ? readFileSync(option('--texte'), 'utf8').trim() : ''
const duree = Number(option('--duree')) || 30
const debut = Date.now()
const r = await ecrireMontage({ demande, dureeVoulue: duree, transcription: texte, fournisseurs: FOURNISSEURS }, { clesPresentes: fournisseursAvecCle() })
const secondes = Math.round((Date.now() - debut) / 100) / 10
let cout = 0
for (const c of r.consommations) {
  const [entree, sortieTokens] = PRIX[c.modele] ?? [0, 0]
  cout += (c.tokens_entree * entree + (c.tokens_sortie + c.tokens_reflexion) * sortieTokens) / 1e6
}
const resume = { secondes, essais: r.essais, consommations: r.consommations, cout_usd: Math.round(cout * 1e6) / 1e6, defauts: r.defauts }
console.log(`Plan écrit en ${secondes} s, ${r.essais} essai(s), ${r.consommations.map((c) => `${c.modele} ${c.tokens_entree}+${c.tokens_sortie + c.tokens_reflexion} tokens`).join(', ')}, coût au prix payant ${cout.toFixed(5)} $`)
if (!r.plan) {
  console.error(`Plan refusé : ${r.defauts.join(' ; ')}`)
  writeFileSync(sortie.replace(/\.json$/, '-mesure.json'), JSON.stringify(resume, null, 1))
  process.exit(1)
}
writeFileSync(sortie, JSON.stringify({ ...r.plan, style: 'dynamique' }, null, 1))
writeFileSync(sortie.replace(/\.json$/, '-mesure.json'), JSON.stringify(resume, null, 1))
console.log(`${r.plan.plans.length} plans : ${r.plan.plans.map((p) => p.type).join(', ')}`)
console.log(`Voix (${r.plan.texte_voix.split(/\s+/).length} mots) : ${r.plan.texte_voix}`)
