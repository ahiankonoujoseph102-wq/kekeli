// L'identité de Kekeli : la même réponse quel que soit le modèle qui répond.
import test from 'node:test'
import assert from 'node:assert/strict'
import { CONSIGNES_REPONDANT, IDENTITE, identiteSure } from '../src/repondant.js'

test('les consignes donnent l’identité de Kekeli : Lomé IA School, Joseph Ahiankonou, jamais un autre assistant', () => {
  assert.ok(CONSIGNES_REPONDANT.includes(IDENTITE))
  assert.match(IDENTITE, /Je suis Kekeli, un assistant IA créé à Lomé par Lomé IA School/)
  assert.match(IDENTITE, /Joseph Ahiankonou \(Coach Joseph\)/)
  assert.match(IDENTITE, /j'utilise plusieurs technologies d'IA choisies pour chaque tâche/)
  assert.match(IDENTITE, /Ne dis JAMAIS que tu es Claude, ChatGPT, GPT, Gemini/)
})

test('filet de sécurité : « Je suis ChatGPT » ou « développé par OpenAI » est remplacé par la réponse de Kekeli', () => {
  assert.equal(identiteSure('Je suis ChatGPT, un modèle de langage. Je peux vous aider.'), "Je suis Kekeli ; j'utilise plusieurs technologies d'IA choisies pour chaque tâche. Je peux vous aider.")
  assert.equal(identiteSure('Bonjour ! J’ai été entraîné par Google pour répondre.'), "Bonjour ! Je suis Kekeli ; j'utilise plusieurs technologies d'IA choisies pour chaque tâche.")
  assert.equal(identiteSure('Je ne suis ni Claude ni Gemini : je suis Kekeli.'), 'Je ne suis ni Claude ni Gemini : je suis Kekeli.', 'une négation reste')
  assert.equal(identiteSure('Gemini est un modèle de Google ; Claude vient d’Anthropic.'), 'Gemini est un modèle de Google ; Claude vient d’Anthropic.', 'une réponse sur les modèles en général reste')
})
