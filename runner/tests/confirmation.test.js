// Jamais de création sans confirmation quand la demande est floue, répétée ou brouillée (chantier « vitesse », partie 5).
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { confirmationNecessaire, estBrouille, estRepete, sansRepetition } from '../src/confirmation.js'
import { premierRepondant } from '../src/repondant.js'

const demande = (message, autres = {}) => confirmationNecessaire({ outil: 'creer_site', message, ...autres })

test('une demande claire et détaillée part tout de suite', () => {
  assert.equal(demande('Crée un site vitrine pour mon restaurant Le Palmier à Lomé, avec le menu et les horaires.'), null)
  assert.equal(demande('je veux un site pour mon salon de coiffure Belle Afi à Kpalimé'), null)
  assert.equal(confirmationNecessaire({ outil: 'creer_image', message: 'Une affiche pour la promotion de pagnes à 5 000 FCFA samedi au marché de Bè' }), null)
  assert.equal(confirmationNecessaire({ outil: 'creer_application', message: 'fais une appli' }), null, 'les applications passent déjà par un plan')
})

test('une demande dictée qui se répète demande une confirmation, avec le texte compris', () => {
  const d = demande('crée un site crée un site pour mon restaurant crée un site pour mon restaurant à Lomé')
  assert.equal(d.raison, 'repete')
  assert.match(d.question, /« crée un site pour mon restaurant à Lomé »/)
  assert.match(d.question, /Voulez-vous que je crée le site maintenant \?$/)
  assert.equal(d.titre, 'Crée un site pour mon restaurant')
  assert.ok(estRepete('bonjour bonjour bonjour je veux un site'))
  assert.ok(!estRepete('je veux un site pour mon restaurant, un vrai site moderne'))
  assert.equal(sansRepetition('je veux un site je veux un site pour mes pagnes'), 'je veux un site pour mes pagnes')
})

test('le même message envoyé deux fois de suite demande une confirmation', () => {
  const historique = [{ role: 'client', contenu: 'Site pour ma boutique de pagnes à Lomé' }, { role: 'assistant', contenu: 'Le travail a été arrêté.' }]
  assert.equal(demande('site pour ma boutique de pagnes à Lomé', { historique }).raison, 'repete')
})

test('un message brouillé ou trop court demande une confirmation', () => {
  assert.equal(demande('xkcd fgh site trqw mmmmm').raison, 'brouille')
  assert.ok(estBrouille('zzzz dfgh pour kkkk'))
  assert.ok(!estBrouille('je vends des poulets braisés à Adidogomé'))
  const flou = demande('fais moi un site stp')
  assert.equal(flou.raison, 'flou')
  assert.match(flou.question, /dites-moi en une phrase l’activité, le nom et ce que le site doit montrer\.$/)
  assert.equal(confirmationNecessaire({ outil: 'creer_video', message: 'une vidéo' }).raison, 'flou')
})

test('une réponse à une question de Kekeli, un plan, un fichier joint ou des photos ne sont jamais retenus', () => {
  const historique = [{ role: 'client', contenu: 'j’ai un restaurant' }, { role: 'assistant', contenu: 'Voulez-vous que je crée le site de votre restaurant ?' }]
  assert.equal(demande('oui', { historique }), null)
  assert.equal(demande('crée le site', { historique: [{ role: 'client', contenu: 'J’ai un restaurant de poisson braisé à Aného, Chez Kossi' }, { role: 'assistant', contenu: 'Très bien.' }] }), null, 'les détails sont dans les messages d’avant')
  assert.equal(confirmationNecessaire({ outil: 'creer_document', message: 'un rapport', arguments: { plan: '# Plan\n- Partie 1' } }), null)
  assert.equal(confirmationNecessaire({ outil: 'creer_document', message: 'corrige', arguments: { fichier: 'cv.docx' } }), null)
  assert.equal(confirmationNecessaire({ outil: 'creer_image', message: 'fais une affiche', contexte: { photos: 1 } }), null)
})

test('le premier répondant pose la question au lieu de lancer l’outil, et la propose', async () => {
  const vus = []
  const r = await premierRepondant({ lieu: 'discussion', message: 'site site site', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }] }, {
    clesPresentes: new Set(['openai']),
    env: { OPENAI_API_KEY: 'cle-de-test' },
    surTexte: (e) => vus.push(e),
    appeler: async () => new Response(JSON.stringify({ output: [{ type: 'function_call', name: 'creer_site', arguments: '{}', call_id: 'a' }], usage: {} }), { status: 200, headers: { 'content-type': 'application/json' } }),
  })
  assert.equal(r.decision, 'repondre')
  assert.equal(r.proposition.outil, 'creer_site')
  assert.match(r.reponse, /Voulez-vous que je crée le site maintenant \?/)
  assert.equal(vus.filter((e) => e.type === 'texte').map((e) => e.delta).join(''), r.reponse)
})
