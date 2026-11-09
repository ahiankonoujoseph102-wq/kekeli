import { test } from 'node:test'
import assert from 'node:assert/strict'
import { dureeVieDepuis } from '../src/ordinateurs.js'
import { executerTache } from '../src/agent.js'
import { MisesDeCote } from '../src/limites.js'
import { fauxOpencode, finRequete, libre, occupe } from './outils.js'

const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }

test('les minutes avant la pause viennent du site, entre 5 et 60, 30 par défaut', () => {
  assert.equal(dureeVieDepuis(10), 10 * 60 * 1000)
  assert.equal(dureeVieDepuis('15'), 15 * 60 * 1000)
  assert.equal(dureeVieDepuis(null), 30 * 60 * 1000)
  assert.equal(dureeVieDepuis(0), 30 * 60 * 1000)
  assert.equal(dureeVieDepuis(2), 5 * 60 * 1000)
  assert.equal(dureeVieDepuis(500), 60 * 60 * 1000)
})

test('la tâche transmet les minutes avant la pause à la gestion des ordinateurs', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  let options = null
  const ordinateurs = {
    obtenir: async (projet, recues) => {
      options = recues
      return faux.ordinateurs.obtenir()
    },
  }
  await executerTache(
    { projet: 'p1', message: 'Bonjour', fournisseurs: [GEMINI], duree_vie_minutes: 12, sites: ['api.exemple.com'] },
    { ordinateurs, envoyer: async () => {}, misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.equal(options.dureeVieMinutes, 12)
  assert.deepEqual(options.sites, ['api.exemple.com'])
})
