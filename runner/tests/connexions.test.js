import { test } from 'node:test'
import assert from 'node:assert/strict'
import { consigneSysteme, lireDepotRelie, outilsDuLieu, premierRepondant } from '../src/repondant.js'

const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test' }
const CLES = new Set(['gemini'])
const LISTE = [{ fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }]
const reponseJson = (donnees, status = 200) => new Response(JSON.stringify(donnees), { status, headers: { 'content-type': 'application/json' } })
const geminiOutil = (nom, args) => reponseJson({ candidates: [{ content: { parts: [{ functionCall: { name: nom, args } }] } }], usageMetadata: { promptTokenCount: 900, candidatesTokenCount: 10 } })
const geminiTexte = (texte) => reponseJson({ candidates: [{ content: { parts: [{ text: texte }] } }], usageMetadata: { promptTokenCount: 1500, candidatesTokenCount: 80 } })
const RELIE = { github: { relie: true, compte: 'ama-mensah', depots: ['ama-mensah/boutique-pagnes'], depots_total: 1 } }

test('Kekeli connaît l’état réel de GitHub : relié avec ses dépôts, ou pas relié avec le geste à faire', () => {
  const relie = consigneSysteme('discussion', { connexions: RELIE, depots: true })
  assert.match(relie, /Connexion GitHub du client : reliée \(compte ama-mensah\)/)
  assert.match(relie, /ama-mensah\/boutique-pagnes/)
  assert.match(relie, /lire_depot/)
  const pasRelie = consigneSysteme('discussion', { connexions: { github: { relie: false } } })
  assert.match(pasRelie, /pas encore reliée/)
  assert.match(pasRelie, /Connecter GitHub/)
  assert.doesNotMatch(consigneSysteme('discussion', {}), /Connexion GitHub/, 'rien quand la GitHub App n’est pas réglée')
})

test('l’outil « lire_depot » n’est proposé que lorsqu’un dépôt relié peut être lu', () => {
  assert.ok(outilsDuLieu('discussion', { depots: true }).includes('lire_depot'))
  assert.ok(!outilsDuLieu('discussion', {}).includes('lire_depot'))
})

test('« lis mon dépôt et dis-moi ce que c’est » : Kekeli lit le dépôt relié (lecture seule) puis le décrit', async () => {
  const lectures = []
  let appels = 0
  const r = await premierRepondant(
    { lieu: 'discussion', message: 'Lis mon dépôt et dis-moi ce que c’est', contexte: { connexions: RELIE }, lecture_depots: 'https://kekeli.test/runner/comptes/1/github?signature=abc', fournisseurs: LISTE },
    {
      clesPresentes: CLES,
      env: ENV,
      lireDepot: async (adresse, depot, chemin) => {
        lectures.push({ adresse, depot, chemin })
        return { depot, description: 'Boutique de pagnes à Lomé', langages: ['PHP', 'Blade'], lisez_moi: 'Site Laravel de la boutique Ama Pagnes : catalogue, commandes WhatsApp.', fichiers: ['routes/web.php', 'app/Models/Produit.php'] }
      },
      appeler: async (url, options) => {
        appels++
        const corps = JSON.parse(options.body)
        if (appels === 1) {
          assert.ok(corps.tools[0].functionDeclarations.some((f) => f.name === 'lire_depot'))
          return geminiOutil('lire_depot', { depot: 'ama-mensah/boutique-pagnes' })
        }
        assert.match(JSON.stringify(corps.contents.at(-1)), /Ama Pagnes/)
        return geminiTexte('C’est le site Laravel de votre boutique **Ama Pagnes** : un catalogue de pagnes et des commandes par WhatsApp.')
      },
    },
  )
  assert.deepEqual(lectures, [{ adresse: 'https://kekeli.test/runner/comptes/1/github?signature=abc', depot: 'ama-mensah/boutique-pagnes', chemin: '' }])
  assert.equal(r.decision, 'repondre')
  assert.match(r.reponse, /Ama Pagnes/)
})

test('la lecture d’un dépôt passe par l’adresse signée du site, jamais avec une clé GitHub', async () => {
  const vues = []
  const appeler = async (url) => {
    vues.push(url)
    return reponseJson({ depot: 'ama-mensah/boutique-pagnes', chemin: 'routes/web.php', contenu: '<?php' })
  }
  const r = await lireDepotRelie('https://kekeli.test/runner/comptes/1/github?signature=abc', 'ama-mensah/boutique-pagnes', 'routes/web.php', { appeler })
  assert.equal(r.contenu, '<?php')
  assert.match(vues[0], /depot=ama-mensah%2Fboutique-pagnes/)
  assert.match(vues[0], /chemin=routes%2Fweb\.php/)
  assert.deepEqual(await lireDepotRelie('https://kekeli.test/x', '../etc', ''), { erreur: 'Nom de dépôt refusé : utilise exactement un nom de la liste (« compte/nom »).' })
})
