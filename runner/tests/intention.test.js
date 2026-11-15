import { test } from 'node:test'
import assert from 'node:assert/strict'
import { estQuestion, propositionEtTexte } from '../src/intention.js'
import { premierRepondant } from '../src/repondant.js'
import { texteVisible } from '../src/flux.js'
import { questionDePrecision } from '../src/confirmation.js'

const ENV = { GEMINI_API_KEY: 'cle-gemini-de-test' }
const LISTE = [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }]
const CLES = new Set(['gemini'])
const reponseJson = (donnees) => new Response(JSON.stringify(donnees), { status: 200, headers: { 'content-type': 'application/json' } })
const geminiTexte = (texte) => reponseJson({ candidates: [{ content: { parts: [{ text: texte }] } }], usageMetadata: { promptTokenCount: 1000, candidatesTokenCount: 80 } })
const geminiOutil = (nom, args = {}) => reponseJson({ candidates: [{ content: { parts: [{ functionCall: { name: nom, args } }] } }], usageMetadata: { promptTokenCount: 1000, candidatesTokenCount: 10 } })

test('une question n’est jamais une demande de création ; une vraie demande l’est', () => {
  const questions = [
    "C'est possible de créer un agent IA aujourd'hui avec l'IA ? Et comment gagner de l'argent avec l'IA",
    'Comment créer un agent IA ?',
    "Est-ce qu'un chatbot WhatsApp peut aider ma boutique ?",
    'Quelle différence entre un site vitrine et une boutique en ligne ?',
    'comment créer un site pour mon restaurant',
    'Pourquoi mon site est lent ?',
    'Combien coûte un nom de domaine ?',
  ]
  for (const q of questions) assert.equal(estQuestion(q), true, q)
  const demandes = [
    'Peux-tu me créer une présentation de 6 diapositives sur l’histoire du Togo ?',
    'Je veux un assistant pour mon école',
    'fais un site',
    'Est-ce que tu peux me faire un logo ?',
    'Crée-moi un site. Comment ça marche ?',
    "j'ai un salon de coiffure à Lomé",
    'Bonjour',
    'Écris-moi une lettre de motivation',
  ]
  for (const d of demandes) assert.equal(estQuestion(d), false, d)
})

test('pour une question, les outils de création ne sont pas proposés au modèle, et la consigne demande une réponse', async () => {
  const corps = []
  const appeler = async (url, options) => {
    corps.push(JSON.parse(options.body))
    return geminiTexte('**Oui**, c’est possible. Voici comment.\n\nVoulez-vous que je crée cet agent pour votre boutique ?\nPROPOSITION : creer_agent')
  }
  const r = await premierRepondant({ lieu: 'discussion', message: "C'est possible de créer un agent IA aujourd'hui ? Et comment gagner de l'argent avec l'IA", fournisseurs: LISTE, recherche: { permise: true, restantes: 2 } }, { clesPresentes: CLES, appeler, env: ENV })
  const noms = (corps[0].tools ?? []).flatMap((t) => t.functionDeclarations ?? []).map((f) => f.name)
  assert.deepEqual(noms, ['rechercher_web'], 'seule la recherche reste proposée')
  const systeme = JSON.stringify(corps[0].systemInstruction)
  assert.match(systeme, /Ce message est une QUESTION/)
  assert.ok(!systeme.includes('creer_agent » avec un plan court'), 'les règles de création ne sont pas chargées')
  // La ligne finale devient un bouton, jamais montrée au client.
  assert.equal(r.decision, 'repondre')
  assert.ok(!r.reponse.includes('PROPOSITION'))
  assert.match(r.reponse, /Voulez-vous que je crée cet agent/)
  assert.deepEqual(r.proposition, { outil: 'creer_agent', arguments: {}, raison: 'proposition' })
})

test('une vraie demande garde ses outils ; la ligne de proposition est ignorée hors d’une question', async () => {
  let noms = []
  const r = await premierRepondant({ lieu: 'discussion', message: 'Je veux un agent IA pour mon salon Belle Afi', fournisseurs: LISTE }, {
    clesPresentes: CLES,
    env: ENV,
    appeler: async (url, options) => {
      noms = JSON.parse(options.body).tools[0].functionDeclarations.map((f) => f.name)
      return geminiTexte('Bien sûr.\nPROPOSITION : creer_agent')
    },
  })
  assert.ok(noms.includes('creer_agent') && noms.includes('poser_questions'))
  assert.equal(r.proposition, undefined)
  assert.equal(r.reponse, 'Bien sûr.')
})

test('des questions mal formées ne lancent jamais la création : une question de précision à la place', async () => {
  const site = await premierRepondant({ lieu: 'discussion', message: 'fais un site', fournisseurs: LISTE }, { clesPresentes: CLES, env: ENV, appeler: async () => geminiOutil('poser_questions', { outil: 'creer_site', questions: 'rien' }) })
  assert.equal(site.decision, 'repondre')
  assert.equal(site.proposition.outil, 'creer_site')
  assert.match(site.reponse, /Voulez-vous que je crée le site/)
  const agent = await premierRepondant({ lieu: 'discussion', message: 'un agent', fournisseurs: LISTE }, { clesPresentes: CLES, env: ENV, appeler: async () => geminiOutil('poser_questions', { outil: 'creer_agent', questions: '[]' }) })
  assert.equal(agent.decision, 'repondre')
  assert.equal(agent.proposition, undefined)
  assert.equal(agent.reponse, questionDePrecision('creer_agent').question)
})

test('la ligne « PROPOSITION » : enlevée du texte, jamais montrée pendant l’écriture', () => {
  assert.deepEqual(propositionEtTexte('Réponse.\n\nVoulez-vous ?\nPROPOSITION : creer_site'), { texte: 'Réponse.\n\nVoulez-vous ?', outil: 'creer_site' })
  assert.deepEqual(propositionEtTexte('Réponse.\n**PROPOSITION :** « inconnu »'), { texte: 'Réponse.', outil: null })
  assert.deepEqual(propositionEtTexte('Sans proposition.'), { texte: 'Sans proposition.', outil: null })
  assert.equal(texteVisible('Réponse.\nPROPOSITION : creer_agent'), 'Réponse.')
  assert.equal(texteVisible('Réponse.\nPROPO'), 'Réponse.')
  assert.equal(texteVisible('Réponse.\nPour finir'), 'Réponse.\nPour finir')
  // La ligne du titre, écrite à la fin, n'est jamais montrée non plus.
  assert.equal(texteVisible('Réponse.\nTITRE : Prix du ciment'), 'Réponse.')
  assert.equal(texteVisible('Réponse.\nTIT'), 'Réponse.')
})

test('la ligne « RÉFLEXION » n’est demandée qu’à un modèle qui réfléchit vraiment', async () => {
  const { requeteFournisseur } = await import('../src/repondant.js')
  const env = { OPENAI_API_KEY: 'cle-openai-de-test', GEMINI_API_KEY: 'cle-gemini-de-test' }
  const riche = { lieu: 'discussion', message: 'Compare Mixx by Yas et Flooz pour ma boutique', contexte: { niveau: 'riche' } }
  const sans = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6.1-terra', reflexion: 'none' }, riche, env)
  assert.equal(sans.corps.reasoning.effort, 'none')
  assert.doesNotMatch(sans.corps.instructions, /RÉFLEXION :/)
  const avec = requeteFournisseur({ fournisseur: 'openai', modele: 'gpt-6.1-terra' }, riche, env)
  assert.equal(avec.corps.reasoning.effort, 'medium')
  assert.match(avec.corps.instructions, /RÉFLEXION :/)
})

// Défaut du 15 novembre 2026 : après les questions avant une affiche, les réponses (« Quel format ? Statut WhatsApp
// vertical ») étaient prises pour une question ; l'outil disparaissait et Kekeli disait qu'il ne pouvait pas créer
// l'image. Chaque fonction annoncée doit rester disponible et être lancée après les réponses.
test('après les questions, chaque outil de création reste disponible et part avec les réponses', async () => {
  const outils = ['creer_image', 'creer_site', 'creer_application', 'creer_agent', 'creer_automatisation', 'creer_document', 'creer_presentation', 'creer_video']
  const reponses = "Quel type d'affiche voulez-vous ? Conseils pratiques à partager\nQuel format ? Statut WhatsApp vertical\nQue doit-elle montrer ? Programme d'une formation"
  assert.equal(estQuestion(reponses), true, 'le texte seul ressemble bien à une question')
  for (const outil of outils) {
    let noms = []
    let systeme = ''
    const r = await premierRepondant({
      lieu: 'discussion',
      message: reponses,
      contexte: { questions_posees: true, outil_prevu: outil },
      historique: [{ role: 'client', contenu: 'Fais-moi une affiche sur les moyens de gagner de l’argent sur Internet' }, { role: 'assistant', contenu: 'Quelques questions avant de commencer.' }],
      fournisseurs: LISTE,
    }, {
      clesPresentes: CLES,
      env: ENV,
      appeler: async (url, options) => {
        const corps = JSON.parse(options.body)
        noms = corps.tools[0].functionDeclarations.map((f) => f.name)
        systeme = JSON.stringify(corps.systemInstruction ?? corps)
        return geminiOutil(outil, {})
      },
    })
    assert.ok(noms.includes(outil), `${outil} proposé au modèle`)
    assert.ok(!noms.includes('poser_questions'), 'jamais de questions deux fois de suite')
    assert.match(systeme, new RegExp(`outil prévu : « ${outil} »`))
    assert.match(systeme, /ne dis jamais que tu ne peux pas le faire/)
    assert.equal(r.decision, 'outil', outil)
    assert.equal(r.outil, outil)
  }
})

test('une question sur ce que Kekeli sait faire ne dit jamais « je ne peux pas » : la consigne le rappelle', async () => {
  let systeme = ''
  await premierRepondant({ lieu: 'discussion', message: 'Tu peux créer des images ?', fournisseurs: LISTE }, {
    clesPresentes: CLES,
    env: ENV,
    appeler: async (url, options) => {
      systeme = JSON.stringify(JSON.parse(options.body).systemInstruction)
      return geminiTexte('Oui.')
    },
  })
  assert.match(systeme, /ne dis jamais que tu ne peux pas en créer ici/)
})
