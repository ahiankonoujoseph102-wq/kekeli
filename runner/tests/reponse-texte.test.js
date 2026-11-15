// Affichage d'une réponse pendant qu'elle s'écrit (chantier « réponses », 15 novembre 2026) : même forme que la
// version finale du site (aucun saut à la fin) et rythme régulier. Le module est celui de la page
// (resources/js/reponse-texte.js).
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { finPremierMot, miseEnFormeProgressive, pasAffichage } from '../../resources/js/reponse-texte.js'

test('les blocs ont déjà la forme finale : titres au bon niveau, tableau, code avec sa barre, renvois', () => {
  const html = miseEnFormeProgressive('## Les prix\n\n| Modèle | Prix |\n|---|---|\n| Nattes | 3 000 FCFA |\n\nVoir [1].\n\n```html\n<a>WhatsApp</a>\n```', { copier: 'Copier' })
  assert.match(html, /<h2>Les prix<\/h2>/)
  assert.match(html, /<div class="kekeli-tableau"><table><thead><tr><th>Modèle<\/th><th>Prix<\/th><\/tr><\/thead><tbody><tr><td>Nattes<\/td><td>3 000 FCFA<\/td><\/tr><\/tbody><\/table><\/div>/)
  assert.match(html, /<span class="kekeli-renvoi">1<\/span>/)
  assert.match(html, /<div class="kekeli-code"><div class="kekeli-code-barre"><span>html<\/span><span class="kekeli-code-copier" aria-hidden="true">Copier<\/span><\/div><pre><code>&lt;a&gt;WhatsApp&lt;\/a&gt;<\/code><\/pre><\/div>/)
})

test('pendant l’écriture : un tableau à moitié écrit est déjà un tableau, un gras pas encore fermé est déjà en gras', () => {
  assert.match(miseEnFormeProgressive('| Modèle | Prix |'), /<table><thead><tr><th>Modèle<\/th><th>Prix<\/th><\/tr><\/thead><tbody><\/tbody><\/table>/)
  assert.equal(miseEnFormeProgressive('Comptez **entre 3 000'), '<p>Comptez <strong>entre 3 000</strong></p>')
  assert.equal(miseEnFormeProgressive('<script>alert(1)</script>'), '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', 'aucun HTML du modèle')
})

test('le rythme : le premier mot d’un coup, puis un gros morceau rattrapé en moins d’une seconde', () => {
  assert.equal(finPremierMot('Bonjour, comment'), 'Bonjour, '.length)
  assert.equal(pasAffichage(0), 0)
  assert.equal(pasAffichage(3), 1)
  assert.equal(pasAffichage(300), 38)
  // 600 caractères arrivés d'un coup : montrés en moins de 60 images (une seconde).
  let reste = 600
  let images = 0
  while (reste > 0) { reste -= pasAffichage(reste); images++ }
  assert.ok(images < 60, `${images} images`)
})
