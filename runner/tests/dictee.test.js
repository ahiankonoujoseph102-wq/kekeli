// Dictée du navigateur sans mots répétés (chantier « vitesse », partie 4) : le module est celui de la page
// (resources/js/dictee-texte.js), testé ici avec les suites de résultats réelles de Chrome sur Android et sur ordinateur.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fusionner, texteDicte } from '../../resources/js/dictee-texte.js'

const resultat = (transcript, isFinal) => Object.assign([{ transcript }], { isFinal })

test('Chrome sur Android : chaque morceau répète le début, aucun mot n’est doublé', () => {
  const suite = [
    [resultat('bonjour', false)],
    [resultat('bonjour je', false)],
    [resultat('bonjour je veux', true)],
    [resultat('bonjour je veux', true), resultat('bonjour je veux un site', false)],
    [resultat('bonjour je veux', true), resultat('bonjour je veux un site pour mon restaurant', true)],
  ]
  const vus = suite.map(texteDicte)
  assert.deepEqual(vus, ['bonjour', 'bonjour je', 'bonjour je veux', 'bonjour je veux un site', 'bonjour je veux un site pour mon restaurant'])
})

test('Chrome sur ordinateur : morceaux distincts, mis bout à bout', () => {
  assert.equal(texteDicte([resultat('Bonjour.', true), resultat(' je vends des pagnes', true), resultat(' à Lomé', false)]), 'Bonjour. je vends des pagnes à Lomé')
})

test('chevauchement et mots répétés exprès', () => {
  assert.equal(fusionner('je vends des poulets braisés', 'poulets braisés à Adidogomé'), 'je vends des poulets braisés à Adidogomé')
  assert.equal(fusionner('très', 'très bon'), 'très bon')
  assert.equal(fusionner('il est très', 'très bon'), 'il est très très bon', 'un seul mot commun n’est pas un chevauchement')
  assert.equal(fusionner('Bonjour, je veux', 'bonjour je veux un site'), 'bonjour je veux un site')
  assert.equal(fusionner('', ''), '')
})
