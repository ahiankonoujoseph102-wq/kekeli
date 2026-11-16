import { test } from 'node:test'
import assert from 'node:assert/strict'
import { premierRepondant, requeteFournisseur } from '../src/repondant.js'
import { imagesPropres, voitLesImages } from '../src/vision.js'

// Une vraie petite image PNG (1 × 1 pixel), en base64.
const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
const IMAGE = { type: 'image/png', donnees: PNG }
const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test', OPENAI_API_KEY: 'cle-openai-de-test', OPENROUTER_API_KEY: 'cle-openrouter-de-test' }
const CLES = new Set(['gemini', 'openrouter'])
const reponseJson = (donnees, status = 200) => new Response(JSON.stringify(donnees), { status, headers: { 'content-type': 'application/json' } })

test('les images sont nettoyées : type d’image connu, base64, 4 au plus', () => {
  assert.deepEqual(imagesPropres([IMAGE, { type: 'application/pdf', donnees: PNG }, { type: 'image/png', donnees: '<script>' }]), [IMAGE])
  assert.equal(imagesPropres(Array(6).fill(IMAGE)).length, 4)
  assert.deepEqual(imagesPropres(null), [])
})

test('seuls les modèles qui savent lire une image la reçoivent', () => {
  assert.equal(voitLesImages({ fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }), true)
  assert.equal(voitLesImages({ fournisseur: 'openai', modele: 'gpt-6-luna' }), true)
  assert.equal(voitLesImages({ fournisseur: 'openrouter', modele: 'google/gemma-4-31b-it:free' }), true)
  assert.equal(voitLesImages({ fournisseur: 'freellmapi', modele: 'gpt-oss-120b' }), false)
  assert.equal(voitLesImages({ fournisseur: 'workersai', modele: '@cf/zai-org/glm-4.7-flash' }), false)
})

test('chaque fournisseur reçoit l’image dans son format, sur le dernier message du client', () => {
  const demande = { lieu: 'discussion', message: 'Que dit cette capture ?', historique: [{ role: 'client', contenu: 'Bonjour' }, { role: 'assistant', contenu: 'Bonjour.' }], images: [IMAGE], contexte: { images_vues: 1 } }
  const gemini = requeteFournisseur({ fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }, demande, ENV).corps
  const dernier = gemini.contents.at(-1)
  assert.deepEqual(dernier.parts[0], { inlineData: { mimeType: 'image/png', data: PNG } })
  assert.equal(dernier.parts[1].text, 'Que dit cette capture ?')
  assert.equal(gemini.contents[0].parts[0].text, 'Bonjour', 'les anciens messages restent du texte')
  assert.match(JSON.stringify(gemini.systemInstruction), /Ne dis jamais que tu ne peux pas voir, lire ou ouvrir une image/)
  const openai = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6-luna' }, demande, ENV).corps
  assert.deepEqual(openai.input.at(-1).content[1], { type: 'input_image', image_url: `data:image/png;base64,${PNG}` })
  const openrouter = requeteFournisseur({ fournisseur: 'openrouter', modele: 'google/gemma-4-31b-it:free' }, demande, ENV).corps
  assert.deepEqual(openrouter.messages.at(-1).content[1], { type: 'image_url', image_url: { url: `data:image/png;base64,${PNG}` } })
  // Un modèle qui ne lit que du texte ne reçoit jamais l'image.
  const texte = requeteFournisseur({ fournisseur: 'openrouter', modele: 'openai/gpt-oss-120b:free' }, demande, ENV).corps
  assert.equal(texte.messages.at(-1).content, 'Que dit cette capture ?')
})

test('avec une image, les modèles sans vision sont sautés : le premier gratuit qui voit répond, OpenAI reste en dernier', async () => {
  const appels = []
  const r = await premierRepondant(
    { lieu: 'discussion', message: 'Pourquoi mon retrait ne passe pas ?', contexte: { photos: 1 }, images: [IMAGE], fournisseurs: [
      { fournisseur: 'openrouter', modele: 'openai/gpt-oss-120b:free' },
      { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' },
      { fournisseur: 'openai', modele: 'gpt-6-luna' },
    ] },
    {
      clesPresentes: CLES,
      env: ENV,
      appeler: async (url, options) => {
        appels.push({ url, corps: JSON.parse(options.body) })
        return reponseJson({ candidates: [{ content: { parts: [{ text: 'Votre solde est de 18 450 FCFA : il manque 6 550 FCFA.' }] } }], usageMetadata: { promptTokenCount: 1300, candidatesTokenCount: 30 } })
      },
    },
  )
  assert.equal(appels.length, 1, 'le modèle sans vision n’est jamais appelé')
  assert.match(appels[0].url, /gemini-3\.1-flash-lite/)
  assert.ok(appels[0].corps.contents.at(-1).parts.some((p) => p.inlineData))
  assert.equal(r.fournisseur, 'gemini')
  assert.match(r.reponse, /18 450 FCFA/)
})
