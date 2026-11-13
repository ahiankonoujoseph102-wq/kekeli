// Mesure du relecteur des vidéos sur des scénarios réels : il doit trouver les défauts connus (promesse inventée, prix
// ou numéro faux) sans fausse alerte sur des scénarios justes.
//   node runner/scripts/mesurer-relecture-video.mjs <sortie.json> <modèle>…
import { readFileSync, writeFileSync } from 'node:fs'
import { relireScenario } from '../src/videos/relecture.js'

const cas = JSON.parse(readFileSync(new URL('../mesures/relecture-videos-cas.json', import.meta.url)))
const PRIX = { 'gpt-6.1-terra': [1, 5], 'gpt-6-luna': [0.1, 0.5] }
const sortie = {}
for (const modele of process.argv.slice(3)) {
  let bons = 0
  let usd = 0
  const details = []
  for (const c of cas) {
    const r = await relireScenario(c.demande, c.scenario, { fournisseurs: [{ fournisseur: 'openai', modele }], clesPresentes: new Set() })
    const trouve = r.defauts.length > 0
    const bon = r.ok && trouve === c.defaut
    if (bon) bons++
    usd += ((r.consommation?.tokens_entree ?? 0) * PRIX[modele][0] + ((r.consommation?.tokens_sortie ?? 0) + (r.consommation?.tokens_reflexion ?? 0)) * PRIX[modele][1]) / 1e6
    details.push({ cas: c.nom, attendu: c.defaut, defauts: r.defauts, bon })
    console.log(bon ? 'oui' : 'NON', modele, c.nom, '→', r.defauts.map((d) => `${d.texte} (${d.probleme})`).join(' ; ').slice(0, 180) || 'rien')
  }
  sortie[modele] = { bons, total: cas.length, usd_par_relecture: Math.round((usd / cas.length) * 1e6) / 1e6, details }
  console.log(modele, `${bons}/${cas.length}`, (usd / cas.length).toFixed(5), '$ par relecture')
}
writeFileSync(process.argv[2], JSON.stringify(sortie, null, 2))
