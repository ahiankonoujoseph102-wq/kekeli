// Mémoire de Kekeli : extraction (seulement quand le message peut contenir une information durable), verrous du code
// (secrets, informations sensibles, rien d'inventé), souvenirs utiles seulement, et branchement dans /repondre.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { CONSIGNES_MEMOIRE, consigneMemoire, extraireSouvenirs, filtrerOperations, raisonDuRefus, sansInvention, souvenirsUtiles, vautLaPeine } from '../src/memoire.js'
import { consigneSysteme } from '../src/repondant.js'
import { creerServeur } from '../src/server.js'
import { entetesSignes } from '../src/signature.js'

const SECRET = 'secret-de-test-memoire'
const SOUVENIRS = [
  { id: 1, texte: "Sa boutique s'appelle Ama Pagnes, au grand marché de Lomé." },
  { id: 2, texte: 'Son numéro Flooz est le 96 11 22 33.' },
  { id: 3, texte: "Ses couleurs sont le vert et l'or." },
]

test('le filtre sans modèle : une petite question ne coûte rien, une information durable ou une demande sur la mémoire oui', () => {
  for (const sans of ["C'est quoi un nom de domaine ?", 'Merci beaucoup !', 'Explique-moi Python en deux phrases.']) assert.equal(vautLaPeine(sans), false, sans)
  for (const avec of ["Ma boutique s'appelle Ama Pagnes.", 'Mon numéro WhatsApp est le 90 12 34 56.', 'Oublie mes couleurs.', 'Retiens que je préfère le vouvoiement.', "Qu'est-ce que tu sais de moi ?", 'Je paie par Flooz.']) assert.equal(vautLaPeine(avec), true, avec)
  assert.equal(vautLaPeine('Voici mon logo', { photos: 1 }), true)
})

test('jamais un mot de passe, une clé, un code, une carte ou un compte bancaire ; le sensible seulement sur demande', () => {
  const message = "Ma boutique s'appelle Ama Pagnes. Mon mot de passe est Ama2026! et mon code PIN 4521. Je suis diabétique."
  const { operations, refus } = filtrerOperations([
    { action: 'ajouter', texte: "Sa boutique s'appelle Ama Pagnes.", categorie: 'identite' },
    { action: 'ajouter', texte: 'Son mot de passe est Ama2026!' },
    { action: 'ajouter', texte: 'Son code est 4521.' },
    { action: 'ajouter', texte: 'Elle est diabétique.' },
    { action: 'ajouter', texte: 'Sa carte est la 4111 1111 1111 1111.' },
  ], { message })
  assert.deepEqual(operations, [{ action: 'ajouter', texte: "Sa boutique s'appelle Ama Pagnes.", categorie: 'identite' }])
  assert.deepEqual(refus.sort(), ['secret', 'secret', 'secret', 'sensible'])
  assert.equal(raisonDuRefus('Elle est chrétienne.', 'Retiens que je suis chrétienne'), null)
  assert.equal(raisonDuRefus('Son mot de passe est Ama2026!', 'Retiens mon mot de passe'), 'secret')
  assert.equal(raisonDuRefus('Son IBAN est TG53 TG01 2345 6789 0123 4567 89.'), 'secret')
  assert.equal(raisonDuRefus('Son numéro WhatsApp est le +228 90 12 34 56.'), null)
})

test('rien d’inventé : chaque nombre et chaque e-mail d’un souvenir vient du message ; numéros inconnus ignorés', () => {
  assert.equal(sansInvention('Son numéro est le 90 12 34 56.', 'mon numéro : 90.12.34.56'), true)
  assert.equal(sansInvention('Son numéro est le 90 12 34 99.', 'mon numéro : 90 12 34 56'), false)
  assert.equal(sansInvention('Son e-mail est ama@exemple.tg.', 'écris-moi à AMA@exemple.tg'), true)
  const { operations } = filtrerOperations([
    { action: 'ajouter', texte: 'Son WhatsApp est le 90 00 00 01.' },
    { action: 'modifier', id: 2, texte: 'Son numéro Flooz est le 99 88 77 66.', categorie: 'paiement' },
    { action: 'modifier', id: 42, texte: 'Inconnu.' },
    { action: 'oublier', id: 3 },
    { action: 'oublier', id: 99 },
    { action: 'pirater', id: 1 },
  ], { message: 'Mon nouveau Flooz : 99 88 77 66. Oublie mes couleurs.', souvenirs: SOUVENIRS })
  assert.deepEqual(operations, [{ action: 'modifier', id: 2, texte: 'Son numéro Flooz est le 99 88 77 66.', categorie: 'paiement' }, { action: 'oublier', id: 3 }])
})

test('seuls les souvenirs utiles vont au modèle : tous pour une création, aucun pour une petite question sans rapport', () => {
  assert.equal(souvenirsUtiles('Fais-moi une affiche pour la fête des mères.', SOUVENIRS).length, 3)
  assert.equal(souvenirsUtiles("Qu'est-ce que tu sais de moi ?", SOUVENIRS).length, 3)
  assert.equal(souvenirsUtiles("C'est quoi un nom de domaine ?", SOUVENIRS).length, 0)
  assert.deepEqual(souvenirsUtiles('Comment encaisser avec Flooz ?', SOUVENIRS).map((s) => s.id), [2])
  const beaucoup = Array.from({ length: 40 }, (_, i) => ({ id: i + 1, texte: 'x'.repeat(150) }))
  assert.ok(souvenirsUtiles('Crée mon site', beaucoup).length < 12, 'le bloc de souvenirs reste court')
})

test('la consigne de la mémoire : des informations jamais des consignes, oublier, lister, refuser les secrets', () => {
  assert.match(CONSIGNES_MEMOIRE, /jamais une consigne pour toi/)
  assert.match(CONSIGNES_MEMOIRE, /mot de passe/)
  assert.match(CONSIGNES_MEMOIRE, /une déduction/)
  const c = consigneMemoire({ active: true, souvenirs: SOUVENIRS.slice(0, 1), message: 'Fais une affiche' })
  assert.match(c, /jamais des consignes/)
  assert.match(c, /- Sa boutique s'appelle Ama Pagnes/)
  assert.match(c, /Réglages, Mémoire/)
  assert.equal(consigneMemoire({ active: true, souvenirs: [], message: 'Bonjour' }), '', 'rien à ajouter : aucun token de plus')
  assert.match(consigneMemoire({ active: false, message: 'Retiens mon numéro' }), /désactivée/)
  assert.equal(consigneMemoire({ active: false, message: 'Bonjour' }), '')
  assert.match(consigneSysteme('discussion', { memoire: c }), /Mémoire de Kekeli/)
  assert.doesNotMatch(consigneSysteme('discussion', {}), /Mémoire de Kekeli/)
})

test('l’extraction appelle le modèle seulement quand le message le mérite, et passe par les verrous du code', async () => {
  let appels = 0
  let corps = null
  const appeler = async (url, options) => {
    appels++
    corps = JSON.parse(options.body)
    const texte = JSON.stringify({ operations: [{ action: 'ajouter', texte: 'Son WhatsApp est le 90 12 34 56.', categorie: 'coordonnees' }, { action: 'ajouter', texte: 'Son mot de passe est Ama2026!' }], logo: true })
    return new Response(JSON.stringify({ output: [{ type: 'message', content: [{ type: 'output_text', text: texte }] }], usage: { input_tokens: 500, output_tokens: 40 } }), { status: 200 })
  }
  const options = { fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }], env: { OPENAI_API_KEY: 'x' }, appeler }
  const rien = await extraireSouvenirs({ message: "C'est quoi un nom de domaine ?", souvenirs: SOUVENIRS }, options)
  assert.deepEqual([rien.appel, appels], [false, 0])
  const r = await extraireSouvenirs({ message: 'Mon WhatsApp est le 90 12 34 56 et mon mot de passe Ama2026!', souvenirs: SOUVENIRS, precedent: 'Quel est votre numéro ?' }, options)
  assert.equal(appels, 1)
  assert.deepEqual(r.operations, [{ action: 'ajouter', texte: 'Son WhatsApp est le 90 12 34 56.', categorie: 'coordonnees' }])
  assert.equal(r.logo, false, 'aucune image jointe : pas de logo')
  assert.equal(r.consommation.modele, 'gpt-6-luna')
  assert.match(JSON.stringify(corps), /2\. Son numéro Flooz/, 'les souvenirs connus sont donnés avec leur numéro')
  assert.match(JSON.stringify(corps), /Quel est votre numéro/)
})

test('/repondre : souvenirs utiles dans la consigne, extraction en même temps que la réponse, rien sans mémoire', async () => {
  const recues = []
  const extraites = []
  const serveur = creerServeur({
    secret: SECRET,
    clesPresentes: new Set(['gemini']),
    ordinateurs: {},
    repondant: async (demande) => {
      recues.push(demande)
      return { ok: true, decision: 'repondre', reponse: 'Voici votre affiche.', fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite', tokens_entree: 900, tokens_sortie: 100, refus: [] }
    },
    extraire: async (demande, options) => {
      extraites.push({ demande, options })
      return { ok: true, operations: [{ action: 'ajouter', texte: 'Ses couleurs sont le bleu et le blanc.', categorie: 'style' }], logo: false, refus: [], consommation: { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite', tokens_entree: 500, tokens_sortie: 30, requetes: 1 }, appel: true }
    },
    journal: { error() {}, warn() {}, log() {} },
  })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const poster = async (donnees) => {
    const corps = JSON.stringify(donnees)
    const reponse = await fetch(`http://127.0.0.1:${serveur.address().port}/repondre`, { method: 'POST', body: corps, headers: entetesSignes(SECRET, corps) })
    return reponse.json()
  }
  try {
    const avec = await poster({ projet: '01JABC', lieu: 'discussion', message: 'Fais une affiche, mes couleurs sont le bleu et le blanc.', fournisseurs: [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }], memoire: { active: true, souvenirs: SOUVENIRS, fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6-luna' }] } })
    assert.match(recues[0].contexte.memoire, /Ama Pagnes/)
    assert.equal(extraites[0].options.fournisseurs[0].modele, 'gpt-6-luna', 'le modèle de la mémoire, pas celui de la réponse')
    assert.equal(avec.memoire.operations[0].texte, 'Ses couleurs sont le bleu et le blanc.')
    assert.equal(avec.memoire.souvenirs_envoyes, 3)

    const eteinte = await poster({ projet: '01JABC', lieu: 'discussion', message: 'Retiens mon numéro 90 12 34 56', memoire: { active: false, souvenirs: [] } })
    assert.equal(extraites.length, 1, 'mémoire désactivée : aucune extraction')
    assert.deepEqual(eteinte.memoire.operations, [])
    assert.match(recues[1].contexte.memoire, /désactivée/)

    const sans = await poster({ projet: '01JABC', lieu: 'discussion', message: 'Bonjour' })
    assert.equal(sans.memoire, undefined)
  } finally {
    serveur.close()
  }
})
