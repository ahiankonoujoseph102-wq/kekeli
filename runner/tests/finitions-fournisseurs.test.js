// Finitions du 12 novembre 2026 : choix guidé du fournisseur du client dans le LISEZ-MOI, bulles qui ne se
// chevauchent plus dans la page de discussion des agents.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fichiersAgent, sectionFournisseurs, FOURNISSEURS_CLIENT, consignesAgent } from '../src/agents/index.js'

test('le LISEZ-MOI de l’agent guide le choix du fournisseur : adresse, modèle conseillé, offres gratuites', () => {
  assert.deepEqual(Object.keys(FOURNISSEURS_CLIENT), ['openai', 'gemini', 'mistral', 'groq', 'openrouter'])
  for (const pile of ['langgraph', 'crewai']) {
    const lisezMoi = String(fichiersAgent(pile, 'Assistante')['LISEZ-MOI.md'])
    assert.match(lisezMoi, /## Choisir votre fournisseur/)
    assert.match(lisezMoi, /`https:\/\/generativelanguage\.googleapis\.com\/v1beta\/openai\/`/)
    assert.match(lisezMoi, /`https:\/\/api\.groq\.com\/openai\/v1`/)
    assert.match(lisezMoi, /Google Gemini : Avec l'offre gratuite de Google, les messages peuvent être utilisés/)
    assert.ok(lisezMoi.indexOf('## Choisir votre fournisseur') < lisezMoi.indexOf('## Lancer'))
  }
  assert.equal((sectionFournisseurs().match(/^\| /gm) ?? []).length, 6)
})

test('page de discussion des agents : dans la colonne qui défile, rien ne rétrécit (plus de bulle sous les suggestions)', () => {
  for (const pile of ['langgraph', 'crewai']) {
    assert.match(String(fichiersAgent(pile)['public/index.html']), /#fil > \* \{ flex-shrink: 0; \}/)
    assert.match(consignesAgent(pile), /flex-shrink: 0/)
  }
})
