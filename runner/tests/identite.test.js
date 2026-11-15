// L'identité de Kekeli : la même réponse quel que soit le modèle qui répond.
import test from 'node:test'
import assert from 'node:assert/strict'
import { CONSIGNES_REPONDANT, IDENTITE, identiteSure } from '../src/repondant.js'

test('les consignes donnent l’identité de Kekeli : Lomé IA School, Joseph Ahiankonou, jamais un autre assistant', () => {
  assert.ok(CONSIGNES_REPONDANT.includes(IDENTITE))
  assert.match(IDENTITE, /Je suis Kekeli, un assistant IA créé à Lomé par Lomé IA School/)
  assert.match(IDENTITE, /Kekeli a été créé par Joseph Ahiankonou \(« Coach Joseph »\), formateur en IA à Lomé \(Togo\), fondateur de Lomé IA School \(https:\/\/lomeiaschool\.laravel\.cloud\) et de Talkami \(https:\/\/talkami\.net\)/)
  assert.match(IDENTITE, /j'utilise plusieurs technologies d'IA choisies pour chaque tâche/)
  assert.match(IDENTITE, /Ne dis JAMAIS que tu es Claude, ChatGPT, GPT, Gemini/)
})

test('filet de sécurité : « Je suis ChatGPT » ou « développé par OpenAI » est remplacé par la réponse de Kekeli', () => {
  assert.equal(identiteSure('Je suis ChatGPT, un modèle de langage. Je peux vous aider.'), "Je suis Kekeli ; j'utilise plusieurs technologies d'IA choisies pour chaque tâche. Je peux vous aider.")
  assert.equal(identiteSure('Bonjour ! J’ai été entraîné par Google pour répondre.'), "Bonjour ! Je suis Kekeli ; j'utilise plusieurs technologies d'IA choisies pour chaque tâche.")
  assert.equal(identiteSure('Je ne suis ni Claude ni Gemini : je suis Kekeli.'), 'Je ne suis ni Claude ni Gemini : je suis Kekeli.', 'une négation reste')
  assert.equal(identiteSure('Gemini est un modèle de Google ; Claude vient d’Anthropic.'), 'Gemini est un modèle de Google ; Claude vient d’Anthropic.', 'une réponse sur les modèles en général reste')
})

// ----- Fiches de connaissances du fondateur (15 novembre 2026) -----
import { readFileSync } from 'node:fs'
import { FICHES, blocConnaissances, fichesUtiles } from '../src/connaissances.js'
import { consigneSysteme } from '../src/repondant.js'

test('les fiches de Lomé IA School et de Talkami sont lues seulement quand la question s’y prête', () => {
  assert.deepEqual(fichesUtiles('qui est ton fondateur'), ['lomeiaschool', 'talkami'])
  assert.deepEqual(fichesUtiles('Je veux apprendre l’anglais'), ['talkami'])
  assert.deepEqual(fichesUtiles('je veux apprendre à créer des agents IA'), ['lomeiaschool'])
  assert.deepEqual(fichesUtiles('combien coûte Talkami'), ['talkami'])
  assert.deepEqual(fichesUtiles('I want to improve my English'), ['talkami'])
  for (const sansRapport of ['fais-moi une affiche pour ma boutique', 'crée un site pour mon centre de formation', 'traduis ce texte en anglais', 'le prix du ciment à Lomé', 'Bonjour']) {
    assert.deepEqual(fichesUtiles(sansRapport), [], sansRapport)
  }
  // Sans rapport : la consigne n'a ni fiche ni règle de recommandation (rien de plus à chaque message).
  assert.doesNotMatch(consigneSysteme('discussion', { connaissances: fichesUtiles('fais-moi une affiche pour ma boutique') }), /FICHE TALKAMI|FICHE LOMÉ IA SCHOOL|150 000/)
  const avec = consigneSysteme('discussion', { connaissances: fichesUtiles('je veux apprendre à créer des agents IA') })
  assert.match(avec, /Création d'agents IA : 150 000/)
  assert.match(avec, /ni dans un site, une application, un document, une présentation, une affiche, une image ou une vidéo créés pour un client/)
  assert.equal(blocConnaissances(['inconnue']), '')
})

test('les fiches sont recopiées telles quelles depuis le dépôt (prix exacts)', () => {
  const talkami = readFileSync(new URL('../connaissances/talkami.md', import.meta.url), 'utf8').trim()
  assert.equal(FICHES.talkami, talkami)
  for (const prix of ['Semaine : 2 000 FCFA', 'Essentiel (recommandée) : 7 500 FCFA', 'Intensif : 14 000 FCFA']) assert.ok(talkami.includes(prix), prix)
  assert.ok(FICHES.lomeiaschool.includes('Formation complète en IA, de A à Z : 250 000'))
  assert.ok(FICHES.lomeiaschool.includes('Chansons personnalisées : 5 100'))
  assert.ok(!FICHES.lomeiaschool.includes('T-Money') && !FICHES.talkami.includes('T-Money'))
})
