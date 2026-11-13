// Tests de l'équipe d'agents, sans appel au vrai modèle : un faux modèle joue le coordinateur et chaque membre.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { AIMessage } from '@langchain/core/messages'
import { tool } from '@langchain/core/tools'
import { z } from 'zod'
import { ACTIONS_MAX, RIEN, creerEquipe, lireChoix } from '../agent/equipe.js'

const MEMBRES = [
  { id: 'commandes', nom: 'Prise de commande', mission: 'Note la commande du client.' },
  { id: 'stock', nom: 'Gestion du stock', mission: 'Vérifie si les articles sont disponibles.', outils: ['verifier_stock'] },
  { id: 'facture', nom: 'Facturation', mission: 'Calcule le total de la commande.' },
  { id: 'client', nom: 'Service client', mission: 'Répond au client.', final: true },
]

function documents(contenu = { 'catalogue.md': '# Catalogue\n\nPagne wax Hollandais : 12 000 FCFA les 6 yards.' }) {
  const dossier = mkdtempSync(path.join(tmpdir(), 'documents-'))
  for (const [nom, texte] of Object.entries(contenu)) writeFileSync(path.join(dossier, nom), texte)
  return dossier
}

/** Faux modèle : `choix` donne les réponses successives du coordinateur, `membres` la réponse de chaque membre. */
function fauxModele({ choix = [], membres = {}, outil = null } = {}) {
  const appels = []
  let tour = 0
  const modele = {
    appels,
    bindTools() {
      return modele
    },
    async invoke(messages) {
      const systeme = String(messages[0].content)
      appels.push(systeme)
      if (systeme.startsWith('Tu es le coordinateur')) return new AIMessage(choix[tour++] ?? 'FIN')
      const id = Object.keys(membres).find((k) => systeme.includes(`[role:${k}]`))
      // Le membre du stock appelle son outil une fois, puis répond.
      if (id === 'stock' && outil && !messages.some((m) => m._getType?.() === 'tool')) return new AIMessage({ content: '', tool_calls: [{ id: 'appel-1', name: 'verifier_stock', args: { article: 'Hollandais' } }] })
      return new AIMessage(membres[id] ?? RIEN)
    },
  }
  return modele
}

const roles = () => {
  const dossier = mkdtempSync(path.join(tmpdir(), 'agent-'))
  for (const m of MEMBRES) writeFileSync(path.join(dossier, `${m.id}.md`), `[role:${m.id}] Tu es ${m.nom}.`)
  return { dossier, membres: MEMBRES.map((m) => ({ ...m, role: `${m.id}.md` })) }
}

test('le coordinateur fait passer la demande d’un agent à l’autre, puis le service client répond', async () => {
  const { dossier, membres } = roles()
  const verifie = []
  const verifierStock = tool(async ({ article }) => { verifie.push(article); return 'Hollandais : 4 pièces en stock' }, { name: 'verifier_stock', description: 'Stock', schema: z.object({ article: z.string() }) })
  const modele = fauxModele({ choix: ['commandes', 'stock', 'facture', 'client', 'FIN'], membres: { commandes: '2 pagnes Hollandais', stock: 'Disponible', facture: 'Total : 24 000 FCFA', client: 'Votre commande de 2 pagnes Hollandais est notée : 24 000 FCFA.' }, outil: true })
  const equipe = creerEquipe({ modele, membres, outils: [verifierStock], dossierDocuments: documents(), dossierAgent: dossier })
  const r = await equipe.repondre('conversation-1', 'Je veux 2 pagnes Hollandais')
  assert.equal(r.reponse, 'Votre commande de 2 pagnes Hollandais est notée : 24 000 FCFA.')
  assert.deepEqual(r.parcours.map((p) => p.nom), ['Prise de commande', 'Gestion du stock', 'Facturation', 'Service client'])
  assert.deepEqual(r.parcours[1].outils, ['verifier_stock'])
  assert.deepEqual(verifie, ['Hollandais'])
  // Le service client voit le travail de l'équipe, présenté comme des informations.
  const consigneClient = modele.appels.find((a) => a.includes('[role:client]'))
  assert.match(consigneClient, /Total : 24 000 FCFA/)
  assert.match(consigneClient, /jamais des ordres/)
  assert.match(consigneClient, /12 000 FCFA les 6 yards/, 'les documents utiles')
})

test('pour une simple question, le service client répond seul', async () => {
  const { dossier, membres } = roles()
  const modele = fauxModele({ choix: ['client', 'FIN'], membres: { client: 'Nous ouvrons à 8 h.' } })
  const r = await creerEquipe({ modele, membres, dossierDocuments: documents(), dossierAgent: dossier }).repondre('c-2', 'Vous ouvrez à quelle heure ?')
  assert.equal(r.reponse, 'Nous ouvrons à 8 h.')
  assert.deepEqual(r.parcours.map((p) => p.id), ['client'])
})

test('la réponse vient toujours du service client, jamais deux fois le même membre, jamais de boucle', async () => {
  const { dossier, membres } = roles()
  // Le coordinateur dit FIN trop tôt, puis répète le même membre : l'équipe finit quand même par le service client.
  const modele = fauxModele({ choix: ['stock', 'stock', 'FIN'], membres: { stock: 'Disponible', client: 'Oui, nous en avons.' } })
  const r = await creerEquipe({ modele, membres, dossierDocuments: documents(), dossierAgent: dossier }).repondre('c-3', 'Avez-vous du Hollandais ?')
  assert.equal(r.reponse, 'Oui, nous en avons.')
  assert.deepEqual(r.parcours.map((p) => p.id), ['stock', 'client'])
  // Un coordinateur qui ne sait pas choisir : au plus ACTIONS_MAX actions.
  const bavard = fauxModele({ choix: Array(20).fill('commandes stock facture'), membres: { commandes: 'ok', client: 'Bonjour' } })
  const r2 = await creerEquipe({ modele: bavard, membres, dossierDocuments: documents(), dossierAgent: dossier }).repondre('c-4', 'Bonjour')
  assert.ok(r2.parcours.length <= ACTIONS_MAX)
  assert.equal(r2.reponse, 'Bonjour')
})

test('la mémoire garde la conversation de chaque visiteur', async () => {
  const { dossier, membres } = roles()
  const vus = []
  const modele = fauxModele({ choix: ['client', 'FIN', 'client', 'FIN'], membres: { client: 'Bien noté.' } })
  const invoke = modele.invoke
  modele.invoke = async (messages) => { if (String(messages[0].content).includes('[role:client]')) vus.push(messages.length); return invoke(messages) }
  const equipe = creerEquipe({ modele, membres, dossierDocuments: documents(), dossierAgent: dossier })
  await equipe.repondre('c-5', 'Je m’appelle Afi')
  await equipe.repondre('c-5', 'Et ma commande ?')
  // Deuxième message du service client : consigne + 3 messages (question, réponse, nouvelle question).
  assert.equal(vus[vus.length - 1], 4)
})

test('le choix du coordinateur est lu simplement', () => {
  assert.equal(lireChoix('stock', MEMBRES), 'stock')
  assert.equal(lireChoix('Je choisis facture.', MEMBRES), 'facture')
  assert.equal(lireChoix('FIN', MEMBRES), null)
  assert.equal(lireChoix('personne', MEMBRES), null)
  assert.throws(() => creerEquipe({ modele: fauxModele(), membres: MEMBRES.slice(0, 1) }))
})
