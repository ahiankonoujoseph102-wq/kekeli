// Écrit la partie « banc de 20 questions » de docs/captures/reponses.md (entre les deux marqueurs), à partir des
// mesures (runner/mesures/banc-reponses-avant.json, banc-reponses-apres.json) et des notes (notes-reponses.json).
//   node runner/scripts/page-reponses.mjs
import { readFileSync, writeFileSync } from 'node:fs'

const lire = (nom) => JSON.parse(readFileSync(new URL(`../mesures/${nom}`, import.meta.url)))
const avant = lire('banc-reponses-avant.json').resultats
const apres = lire('banc-reponses-apres.json').resultats
const { notes } = lire('notes-reponses.json')
const page = new URL('../../docs/captures/reponses.md', import.meta.url)
const DEBUT = '<!-- banc : début -->'
const FIN = '<!-- banc : fin -->'

const fcfa = (l) => ((l.tokens_entree * 1 + l.tokens_sortie * 5) / 1e6 * 600).toFixed(2).replace('.', ',')
const lignes = ['| Numéro | Question | Genre | Note avant | Note après | Mots avant → après | Pourquoi |', '|---|---|---|---|---|---|---|']
for (const n of notes) {
  const a = avant.find((l) => l.numero === n.numero)
  const b = apres.find((l) => l.numero === n.numero)
  lignes.push(`| ${n.numero} | ${a.question.replace(/\|/g, '/')} | ${a.genre} | ${n.avant} | ${n.apres} | ${a.mots} → ${b.mots} | ${n.raison} |`)
}
const moyenne = (k) => (notes.reduce((s, x) => s + x[k], 0) / notes.length).toFixed(2).replace('.', ',')
const details = notes.map((n) => {
  const a = avant.find((l) => l.numero === n.numero)
  const b = apres.find((l) => l.numero === n.numero)
  return `<details>\n<summary>${n.numero}. ${a.question} (note ${n.avant} → ${n.apres})</summary>\n\n**Avant** (${a.mots} mots, ${fcfa(a)} FCFA au prix payant) :\n\n${a.reponse.replace(/^/gm, '> ')}\n\n**Après** (${b.mots} mots, ${fcfa(b)} FCFA au prix payant) :\n\n${b.reponse.replace(/^/gm, '> ')}\n\n</details>`
}).join('\n\n')
const bloc = `${DEBUT}\n\n**Moyenne des notes : ${moyenne('avant')} sur 10 avant, ${moyenne('apres')} sur 10 après.**\n\n${lignes.join('\n')}\n\n### Les 20 réponses complètes, avant et après\n\n${details}\n\n${FIN}`
const texte = readFileSync(page, 'utf8')
writeFileSync(page, texte.slice(0, texte.indexOf(DEBUT)) + bloc + texte.slice(texte.indexOf(FIN) + FIN.length))
console.log('Partie du banc écrite.')
