import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { corrigerHtml, corrigerProjet, corrigerTexte } from '../src/qualite/typographie.mjs'
import { codeOutil } from '../src/ordinateurs.js'

const visible = (texte) => texte.replace(/ /g, '[fine]').replace(/ /g, '[insécable]')

test('une espace insécable avant ? ! ; et : , pour qu’aucun signe ne se retrouve seul en début de ligne', () => {
  assert.equal(visible(corrigerTexte('Vous avez faim ? Venez ! Ouvert ; fermé le lundi.')), 'Vous avez faim[fine]? Venez[fine]! Ouvert[fine]; fermé le lundi.')
  assert.equal(visible(corrigerTexte('Téléphone : +228 90 12 34 56')), 'Téléphone[insécable]: +228 90 12 34 56')
  // Sans espace du tout, après une lettre : l'espace est ajoutée.
  assert.equal(visible(corrigerTexte('Contact? Adresse: Bè')), 'Contact[fine]? Adresse[insécable]: Bè')
  assert.equal(visible(corrigerTexte('« Bienvenue » et «Merci»')), '«[insécable]Bienvenue[insécable]» et «[insécable]Merci[insécable]»')
  assert.equal(visible(corrigerTexte('Prix : 2 500 FCFA')), 'Prix[insécable]: 2[insécable]500[insécable]FCFA')
  // Déjà correct : rien ne change.
  assert.equal(corrigerTexte('Déjà : bon !'), 'Déjà : bon !')
})

test('les heures, les adresses, les entités, les numéros de téléphone et les années ne sont jamais touchés', () => {
  for (const texte of ['Ouvert de 11:00 à 23:00.', 'https://wa.me/22890123456', 'Le Palmier &amp; fils', '+228 90 12 34 56', 'En 2026 2027.', 'Prix&#039;s']) {
    assert.equal(corrigerTexte(texte), texte, texte)
  }
})

test('seul le texte visible des pages est corrigé, jamais les balises, attributs, scripts ni styles', () => {
  const html = '<style>a:hover{color:red}</style><script>x = a ? b : c;</script><a href="https://wa.me/228" title="Contact : ici">Écrire : ici</a><pre>a ? b</pre>'
  const { html: resultat, corrections } = corrigerHtml(html)
  assert.equal(corrections, 1)
  assert.equal(visible(resultat), '<style>a:hover{color:red}</style><script>x = a ? b : c;</script><a href="https://wa.me/228" title="Contact : ici">Écrire[insécable]: ici</a><pre>a ? b</pre>')
})

test('le correcteur parcourt les pages HTML du projet et ne réécrit que celles qui changent', async () => {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-typo-'))
  try {
    await mkdir(path.join(dossier, 'pages'), { recursive: true })
    await writeFile(path.join(dossier, 'index.html'), '<h1>Bienvenue !</h1>')
    await writeFile(path.join(dossier, 'pages/contact.html'), '<p>Déjà : bon.</p>')
    await writeFile(path.join(dossier, 'style.css'), 'a:hover { color: red; }')
    assert.deepEqual(await corrigerProjet(dossier), { fichiers: 1, corrections: 1 })
    assert.equal(await readFile(path.join(dossier, 'index.html'), 'utf8'), '<h1>Bienvenue !</h1>')
    assert.equal(await readFile(path.join(dossier, 'style.css'), 'utf8'), 'a:hover { color: red; }')
    assert.doesNotMatch(await codeOutil('qualite/typographie.mjs'), /from '\.\.?\//)
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('le modèle de mise en page garde le menu à droite, un vrai panneau mobile et des textes lisibles sur photo', async () => {
  const css = await codeOutil('modeles/base.css')
  assert.match(css, /\.entete nav \{ display: contents; \}/)
  assert.match(css, /\.menu \{ display: none; position: fixed; top: 64px; left: 0; right: 0; bottom: 0;/)
  assert.match(css, /\.menu \.bouton \{ display: flex; justify-content: center; margin-top: 1rem;/)
  assert.match(css, /\.accueil \.sur-titre, \.sur-photo \.sur-titre \{ color: #ffffff; background: rgba\(0, 0, 0, 0\.55\)/)
})
