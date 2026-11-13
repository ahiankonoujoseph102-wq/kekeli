// Mesure des sous-titres sur des scénarios réels : morceaux coupés au milieu d'une phrase (sans ponctuation de fin),
// numéros ou montants coupés, sous-titres qui répètent le texte de l'écran. Compare deux versions du découpage.
//   node runner/scripts/mesurer-sous-titres.mjs <voix-avant.mjs> <scenario.json>…
import { readFileSync } from 'node:fs'
import path from 'node:path'
import * as apres from '../src/videos/voix.js'

const avant = await import(path.resolve(process.argv[2]))
const scenarios = process.argv.slice(3).map((f) => JSON.parse(readFileSync(f, 'utf8')))
const mesurer = (decouper, repete) => {
  const r = { sous_titres: 0, phrase_coupee: 0, nombre_coupe: 0, repete_l_ecran: 0 }
  for (const sc of scenarios) {
    for (const s of [...sc.scenes, ...(sc.fin ? [sc.fin] : [])]) {
      if (!s.voix) continue
      const lignes = decouper(s.voix).filter((l) => !repete || !repete(l, [s.titre, s.texte, s.prix, s.contact, s.paiement]))
      for (const l of lignes) {
        r.sous_titres++
        if (!/[.!?…,;:]$/.test(l.trim())) r.phrase_coupee++
        if (/\d$/.test(l.trim()) || /^\d/.test(l.trim()) && /\d[ ,]?$/.test('')) r.nombre_coupe++
        if (apres.repeteLEcran(l, [s.titre, s.texte, s.prix, s.contact, s.paiement])) r.repete_l_ecran++
      }
    }
  }
  return r
}
console.log(JSON.stringify({ avant: mesurer(avant.morceaux, null), apres: mesurer(apres.morceaux, apres.repeteLEcran) }, null, 2))
