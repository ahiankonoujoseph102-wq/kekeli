// La page de discussion (et donc la bulle) affiche les réponses mises en forme, de façon sûre : gras, listes et retours
// à la ligne ; jamais d'astérisques visibles, jamais de HTML accepté. Fichier fait par Kekeli.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../public/texte.js', import.meta.url), 'utf8')
const module = { exports: {} }
new Function('module', source)(module)
const { analyser } = module.exports
const page = readFileSync(new URL('../public/index.html', import.meta.url), 'utf8')

/** Le texte vu par le visiteur, bloc par bloc. */
const vu = (texte) => analyser(texte).map((b) => b.type === 'paragraphe' ? b.lignes.map((l) => l.map((m) => m.texte).join('')).join('\n') : b.elements.map((l) => '• ' + l.map((m) => m.texte).join('')).join('\n')).join('\n\n')

test('le gras, les listes et les retours à la ligne sont mis en forme, sans astérisque visible', () => {
  const texte = 'Voici votre **facture** :\n- 2 Kente : **70 000 FCFA**\n- Livraison : *gratuite*\n\n**Total : 95 000 FCFA**\nMerci !'
  const blocs = analyser(texte)
  assert.deepEqual(blocs.map((b) => b.type), ['paragraphe', 'puces', 'paragraphe'])
  assert.deepEqual(blocs[0].lignes[0], [{ texte: 'Voici votre ' }, { texte: 'facture', gras: true }, { texte: ' :' }])
  assert.equal(blocs[1].elements[1][1].italique, true)
  assert.equal(blocs[2].lignes.length, 2, 'le retour à la ligne est gardé')
  assert.doesNotMatch(vu(texte), /\*/)
})

test('les listes numérotées, les titres et les signes seuls ne laissent rien de visible', () => {
  const texte = '### Votre commande\n1. Wax Vlisco\n2. Kente\n---\n**Stock** limité **'
  assert.deepEqual(analyser(texte).map((b) => b.type), ['paragraphe', 'numeros', 'paragraphe'])
  assert.doesNotMatch(vu(texte), /\*|#|---/)
  assert.match(vu(texte), /Votre commande/)
})

test('aucun HTML n’est jamais interprété : il reste du texte', () => {
  const texte = '<img src=x onerror=alert(1)> **<b>gras</b>**'
  const morceaux = analyser(texte)[0].lignes[0]
  assert.ok(morceaux.some((m) => m.texte.includes('<img src=x onerror=alert(1)>')), 'le HTML reste du texte')
  assert.doesNotMatch(source, /innerHTML|insertAdjacentHTML|outerHTML|document\.write/)
})

test('la page de discussion utilise texte.js et n’affiche jamais une réponse avec innerHTML', () => {
  assert.match(page, /<script src="texte\.js"><\/script>/)
  assert.match(page, /KekeliTexte\.afficher/)
  assert.doesNotMatch(page, /innerHTML|insertAdjacentHTML|outerHTML/)
})
