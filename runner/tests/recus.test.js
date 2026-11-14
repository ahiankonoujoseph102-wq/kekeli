// Reçus d'abonnement : structure écrite par le site (jamais par un modèle), mise en page par le moteur de documents.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { structureRecuValide } from '../src/recus.js'
import { creerServeur } from '../src/server.js'
import { entetesSignes } from '../src/signature.js'

const SECRET = 'secret-de-test-recus'
const RECU = { type: 'document', titre: 'Reçu KEK-2026-000001', blocs: [{ type: 'tableau', entetes: ['Détail', 'Valeur'], lignes: [['Statut', 'Payé']] }, { type: 'paragraphe', texte: 'Merci.' }] }

test('seule une structure de reçu courte et connue est acceptée', () => {
  assert.equal(structureRecuValide(RECU), true)
  assert.equal(structureRecuValide({ ...RECU, type: 'presentation' }), false)
  assert.equal(structureRecuValide({ ...RECU, blocs: [{ type: 'image', url: 'https://exemple.tg/x.png' }] }), false)
  assert.equal(structureRecuValide({ ...RECU, blocs: Array.from({ length: 31 }, () => ({ type: 'paragraphe', texte: 'x' })) }), false)
  assert.equal(structureRecuValide(null), false)
  // Le logo de l'en-tête : une petite image PNG en base64, rien d'autre.
  assert.equal(structureRecuValide({ ...RECU, logo_png: Buffer.from('png').toString('base64') }), true)
  assert.equal(structureRecuValide({ ...RECU, logo_png: 'https://exemple.tg/logo.png' }), false)
  assert.equal(structureRecuValide({ ...RECU, logo_png: 'A'.repeat(300001) }), false)
})

test('le logo est déposé à côté du document et seul son nom est donné au moteur', async () => {
  const ecrits = {}
  const sandbox = {
    files: {
      write: async (chemin, contenu) => { ecrits[chemin.split('/').pop()] = contenu },
      read: async () => new Uint8Array([37, 80, 68, 70]),
    },
    commands: { run: async (commande) => ({ stdout: commande.includes(' rendre ') ? JSON.stringify({ fichiers: { pdf: 'document.pdf' }, apercus: [], pages: 1 }) : '' }) },
  }
  const { rendreRecu } = await import('../src/recus.js')
  const resultat = await rendreRecu({ ...RECU, logo_png: Buffer.from('image').toString('base64') }, { atelier: { obtenir: async () => sandbox } })
  assert.equal(resultat.ok, true)
  assert.equal(Buffer.from(ecrits['logo.png']).toString(), 'image')
  const structure = JSON.parse(ecrits['structure.json'])
  assert.equal(structure.logo, 'logo.png')
  assert.equal(structure.logo_png, undefined)
})

test('/recus : demande signée, le PDF revient dans la réponse', async () => {
  const recus = []
  const serveur = creerServeur({
    secret: SECRET,
    clesPresentes: new Set(),
    ordinateurs: {},
    recu: async (structure) => {
      recus.push(structure)
      return { ok: true, pdf: Buffer.from('%PDF-1.4').toString('base64'), apercu: null, pages: 1 }
    },
    journal: { error() {}, warn() {}, log() {} },
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const poster = (donnees, signe = true) => {
    const corps = JSON.stringify(donnees)
    return fetch(`http://127.0.0.1:${serveur.address().port}/recus`, { method: 'POST', body: corps, headers: signe ? entetesSignes(SECRET, corps) : {} })
  }
  try {
    assert.equal((await poster({ structure: RECU }, false)).status, 401)
    assert.equal((await poster({ structure: { type: 'document' } })).status, 422)
    const reponse = await poster({ structure: RECU })
    assert.equal(reponse.status, 200)
    assert.equal(Buffer.from((await reponse.json()).pdf, 'base64').toString(), '%PDF-1.4')
    assert.equal(recus[0].titre, 'Reçu KEK-2026-000001')
  } finally {
    serveur.close()
  }
})
