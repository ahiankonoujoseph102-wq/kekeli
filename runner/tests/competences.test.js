// Les compétences (skills) du premier répondant : chaque lieu ne charge que les règles de ses outils, sans changer leur texte.
import test from 'node:test'
import assert from 'node:assert/strict'
import { COMPETENCES, REGLES_DECISION, reglesDecision } from '../src/competences.js'
import { CONSIGNES_REPONDANT, OUTILS, consigneSysteme, consignesRepondant } from '../src/repondant.js'

test('les règles sont rangées par capacité, et toutes les capacités sont là', () => {
  assert.deepEqual(Object.keys(COMPETENCES), ['reponse', 'recherche', 'site', 'application', 'agent', 'automatisation', 'document', 'presentation', 'image', 'video'])
  for (const r of REGLES_DECISION) for (const o of r.outils ?? []) assert.ok(OUTILS[o], `outil connu : ${o}`)
  assert.equal(consignesRepondant(), CONSIGNES_REPONDANT, 'sans filtre : exactement les consignes complètes')
})

test('un projet de site ne charge pas les règles des agents, des automatisations, des documents ni des vidéos', () => {
  const site = consigneSysteme('site', {})
  assert.match(site, /Un travail sur le site coûte de l'énergie au client/)
  assert.match(site, /Question sur le code du projet/)
  for (const absente of ['Agent IA : un AGENT', 'Automatisation : une AUTOMATISATION', 'Document ou présentation :', 'Vidéo : une VIDÉO']) assert.ok(!site.includes(absente), absente)
  const document = consigneSysteme('document', {})
  assert.match(document, /Image interdite/, 'un projet d’image garde la règle des images interdites')
  assert.ok(!document.includes('Site ou application :'))
  assert.ok(site.length < CONSIGNES_REPONDANT.length * 0.65 && document.length < CONSIGNES_REPONDANT.length * 0.6)
})

test('le texte de chaque règle ne change pas : on filtre, on ne réécrit rien', () => {
  const toutes = reglesDecision()
  for (const r of REGLES_DECISION) assert.ok(toutes.includes(r.texte))
  assert.ok(reglesDecision(['modifier_site']).split('\n').every((l) => toutes.includes(l)))
})
