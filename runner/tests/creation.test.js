import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'

// L'outil d'images écrit dans le dossier du projet : ici, un dossier temporaire.
const dossier = mkdtempSync(path.join(tmpdir(), 'kekeli-creation-'))
process.env.KEKELI_PROJET = dossier
const { commandeCreer, CONSIGNE_TEXTE } = await import('../src/outils/images.mjs')

const imageJpeg = (() => {
  const fichier = path.join(dossier, 'modele.jpg')
  execFileSync('convert', ['-size', '1024x1024', 'xc:orange', fichier])
  return readFileSync(fichier).toString('base64')
})()

const regler = { services: ['cloudflare'], cloudflare_compte: '0123456789abcdef0123456789abcdef', images_max: 10, vision: 'gemini-3.1-flash-lite' }

/** Faux services : Cloudflare crée une image ; le modèle de vision répond selon la liste « avis ». */
function fauxServices(avis) {
  const appels = { creations: 0, regards: 0, graines: new Set() } // « graines » : descriptions différentes envoyées
  const telecharger = async (adresse, options = {}) => {
    if (adresse.includes('api.cloudflare.com')) {
      appels.creations++
      appels.graines.add(JSON.parse(options.body).prompt)
      return new Response(JSON.stringify({ result: { image: imageJpeg } }), { headers: { 'content-type': 'application/json' } })
    }
    const reponse = avis[Math.min(appels.regards++, avis.length - 1)]
    return new Response(JSON.stringify({ candidates: [{ content: { parts: [{ text: JSON.stringify(reponse) }] } }], usageMetadata: { promptTokenCount: 1000 } }), { headers: { 'content-type': 'application/json' } })
  }
  return { appels, telecharger }
}

test('une image créée sans texte est gardée telle quelle, après vérification par Kekeli', async () => {
  const { appels, telecharger } = fauxServices([{ texte: false, zones: [] }])
  const resultat = await commandeCreer('market', { fichier: 'images/a.jpg' }, { telecharger, regler, dejaCreees: 0 })
  assert.equal(resultat.ok, true)
  assert.equal(appels.creations, 1)
  assert.match(resultat.message, /aucun texte, aucune lettre, aucune marque/)
  assert.ok(existsSync(path.join(dossier, 'images/a.jpg')))
  assert.match(CONSIGNE_TEXTE, /même petits, flous ou inventés/)
})

test('une image créée avec du texte est recréée, avec une description précisée, jusqu’à ce qu’elle n’en ait plus', async () => {
  const { appels, telecharger } = fauxServices([{ texte: true, zones: [{ boite: [0, 0, 100, 100] }] }, { texte: false, zones: [] }])
  const resultat = await commandeCreer('shop', { fichier: 'images/b.jpg' }, { telecharger, regler, dejaCreees: 0 })
  assert.equal(resultat.ok, true)
  assert.equal(appels.creations, 2)
  assert.equal(appels.graines.size, 2)
  assert.match(resultat.message, /aucun texte/)
})

test('si le texte reste après trois créations, il est flouté ; et le maximum du jour est respecté', async () => {
  const toujours = fauxServices([{ texte: true, zones: [{ boite: [100, 100, 300, 400] }] }])
  const resultat = await commandeCreer('shop', { fichier: 'images/c.jpg' }, { telecharger: toujours.telecharger, regler, dejaCreees: 0 })
  assert.equal(toujours.appels.creations, 3)
  assert.match(resultat.message, /1 zone\(s\) floutée\(s\)/)
  assert.equal(execFileSync('identify', ['-format', '%w %h', path.join(dossier, 'images/c.jpg')]).toString(), '1600 900')

  // Il ne reste qu'une image permise aujourd'hui : pas de deuxième essai.
  const limite = fauxServices([{ texte: true, zones: [{ boite: [100, 100, 300, 400] }] }])
  await commandeCreer('shop', { fichier: 'images/d.jpg' }, { telecharger: limite.telecharger, regler: { ...regler, images_max: 1 }, dejaCreees: 0 })
  assert.equal(limite.appels.creations, 1)
})

test.after(() => rmSync(dossier, { recursive: true, force: true }))
