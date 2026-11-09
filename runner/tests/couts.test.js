import { test } from 'node:test'
import assert from 'node:assert/strict'
import { dureeVieDepuis } from '../src/ordinateurs.js'
import { executerTache, TEXTE_REPRISE } from '../src/agent.js'
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

test('un tour terminé sur « quota dépassé » bascule vers le modèle suivant au lieu d’être annoncé comme réussi', async () => {
  const FLASH = { fournisseur: 'gemini', modele: 'gemini-3.8-flash' }
  const faux = fauxOpencode({ [FLASH.modele]: [occupe, libre], [GEMINI.modele]: [occupe, finRequete('r2'), libre] })
  let appelsMessages = 0
  faux.client.session.messages = async () => {
    appelsMessages++
    // Premier tour : erreur de quota dans le dernier message ; ensuite, une vraie réponse.
    if (faux.appels.prompts.length === 1) return { data: [{ info: { role: 'assistant', error: { name: 'APIError', data: { message: 'You exceeded your current quota, please check your plan and billing details.' } } }, parts: [] }] }
    return { data: [{ info: { role: 'assistant' }, parts: [{ type: 'text', text: 'Le site est prêt.' }] }] }
  }
  const evenements = []
  const misesDeCote = new MisesDeCote()
  await executerTache(
    { projet: 'p1', message: 'Un site', fournisseurs: [FLASH, GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote, clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.deepEqual(faux.appels.prompts.map((p) => p.modele), ['gemini-3.8-flash', 'gemini-3.5-flash-lite'])
  assert.ok(evenements.some((e) => e.type === 'refus' && e.modele === 'gemini-3.8-flash'))
  assert.ok(evenements.some((e) => e.type === 'mis_de_cote' && e.modele === 'gemini-3.8-flash'))
  assert.deepEqual(evenements.at(-1), { type: 'terminee', reponse: 'Le site est prêt.' })
  assert.ok(!misesDeCote.estDisponible('gemini/gemini-3.8-flash'))
  assert.ok(appelsMessages >= 2)
})

test('un tour terminé sur une autre erreur fait échouer la demande clairement', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, libre] })
  faux.client.session.messages = async () => ({ data: [{ info: { role: 'assistant', error: { name: 'APIError', data: { message: 'Model not found' } } }, parts: [] }] })
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Un site', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.deepEqual(evenements.at(-1), { type: 'erreur', raison: 'agent', detail: 'Model not found' })
})

test('hors du projet, l’agent peut seulement lire les outils de Kekeli', async () => {
  const { configurationOpencode } = await import('../src/ordinateurs.js')
  assert.deepEqual(configurationOpencode(new Set(['gemini'])).permission.external_directory, { '*': 'deny', '/home/user/.kekeli/*': 'allow' })
})

test('un tour terminé sans aucun message est relancé au lieu d’être annoncé comme réussi', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  faux.client.session.messages = async () => {
    // Premier tour : l'agent s'arrête en silence ; après la relance, il répond.
    if (faux.appels.prompts.length === 1) return { data: [{ info: { role: 'assistant' }, parts: [{ type: 'step-finish' }] }] }
    return { data: [{ info: { role: 'assistant' }, parts: [{ type: 'text', text: 'Le site est prêt.' }] }] }
  }
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Un site', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.equal(faux.appels.prompts.length, 2)
  assert.equal(faux.appels.prompts[1].texte, TEXTE_REPRISE)
  assert.ok(evenements.some((e) => e.type === 'etape' && e.genre === 'relance'))
  assert.deepEqual(evenements.at(-1), { type: 'terminee', reponse: 'Le site est prêt.' })
})

test('un agent qui reste muet est relancé deux fois au plus', async () => {
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, libre] })
  faux.client.session.messages = async () => ({ data: [{ info: { role: 'assistant' }, parts: [] }] })
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Un site', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.equal(faux.appels.prompts.length, 3)
  assert.equal(evenements.filter((e) => e.genre === 'relance').length, 2)
})
