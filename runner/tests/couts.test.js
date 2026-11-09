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

test('pendant le travail, l’ordinateur reste allumé, puis la pause normale est reprogrammée', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  const appels = []
  const ordinateurs = {
    obtenir: async () => ({ ...(await faux.ordinateurs.obtenir()), apercu: { url: 'https://4100-abc.e2b.app' }, expireA: new Date(Date.now() + 10 * 60000) }),
    garderEnMarche: async (projet, duree) => appels.push(['garder', duree]),
    prolonger: async () => {
      appels.push(['prolonger'])
      return new Date(Date.now() + 10 * 60000)
    },
  }
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Un grand site', fournisseurs: [GEMINI] },
    { ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} }, dureeMaxMs: 20 * 60000 },
  )
  assert.deepEqual(appels, [['garder', 22 * 60000], ['prolonger']])
  const [pendant, apres] = evenements.filter((e) => e.type === 'apercu').map((e) => new Date(e.expire_a).getTime())
  assert.ok(pendant > Date.now() + 21 * 60000, 'pendant le travail, l’aperçu reste allumé au moins 21 minutes')
  assert.ok(apres < Date.now() + 11 * 60000, 'après le travail, la pause normale de 10 minutes reprend')
})

test('un ordinateur qui ne répond plus arrête la demande sans attendre la durée maximale', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe] })
  faux.client.session.status = async () => {
    throw new Error('ordinateur introuvable')
  }
  const evenements = []
  const debut = Date.now()
  await executerTache(
    { projet: 'p1', message: 'Bonjour', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} }, dureeMaxMs: 60000, delaiVerificationMs: 20 },
  )
  assert.deepEqual(evenements.at(-1), { type: 'erreur', raison: 'ordinateur', detail: "L'ordinateur virtuel ne répond plus." })
  assert.ok(Date.now() - debut < 5000)
})
