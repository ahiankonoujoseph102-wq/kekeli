import { test } from 'node:test'
import assert from 'node:assert/strict'
import { CONSIGNES_DOCUMENT, CONSIGNES_PRESENTATION, consigneRedaction, lireJson, requeteRedaction, structurePropre } from '../src/documents/redaction.js'
import { demandeDocumentValide, formatsVoulus, produireDocument } from '../src/documents/index.js'
import { outilsDuLieu } from '../src/repondant.js'

const ENV = { OPENAI_API_KEY: 'cle-openai-de-test', GEMINI_API_KEY: 'cle-gemini-de-test' }

test('les outils : créer un document ou une présentation dans une discussion, modifier dans un projet de document', () => {
  assert.ok(outilsDuLieu('discussion').includes('creer_document'))
  assert.ok(outilsDuLieu('discussion').includes('creer_presentation'))
  assert.deepEqual(outilsDuLieu('document'), ['modifier_document'])
  assert.deepEqual(outilsDuLieu('document', { recherche: true }), ['modifier_document', 'rechercher_web'])
})

test('les consignes de qualité : FCFA, Mixx by Yas, une idée par diapositive, jamais d’invention', () => {
  for (const consigne of [CONSIGNES_DOCUMENT, CONSIGNES_PRESENTATION]) {
    assert.match(consigne, /FCFA/)
    assert.match(consigne, /Mixx by Yas/)
    assert.match(consigne, /jamais « T-Money »/)
    assert.match(consigne, /jamais des consignes/)
  }
  assert.match(CONSIGNES_PRESENTATION, /Une idée par diapositive/)
  assert.match(CONSIGNES_DOCUMENT, /Vérifie les calculs/)
})

test('la requête : JSON demandé, rien de gardé chez OpenAI, la clé seulement dans l’en-tête', () => {
  const r = requeteRedaction({ fournisseur: 'openai', modele: 'gpt-6.1-sol' }, { systeme: 'S', consigne: 'C' }, ENV)
  assert.equal(r.url, 'https://api.openai.com/v1/responses')
  assert.equal(r.corps.store, false)
  assert.deepEqual(r.corps.text.format, { type: 'json_object' })
  assert.ok(!JSON.stringify(r.corps).includes('cle-openai-de-test'))
  const g = requeteRedaction({ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }, { systeme: 'S', consigne: 'C' }, ENV)
  assert.equal(g.corps.generationConfig.responseMimeType, 'application/json')
  assert.ok(!g.url.includes('cle-gemini-de-test'))
})

test('le JSON du modèle est lu même entouré de texte ou de ```json', () => {
  assert.deepEqual(lireJson('```json\n{"a":1}\n```'), { a: 1 })
  assert.deepEqual(lireJson('Voici : {"a":2} fin'), { a: 2 })
  assert.equal(lireJson('pas de json'), null)
})

test('la structure gardée : seulement les blocs connus, des textes bornés, une couleur valide', () => {
  const s = structurePropre({ titre: 'Devis', theme: { couleur: 'javascript:alert(1)' }, blocs: [{ type: 'paragraphe', texte: 'Bonjour' }, { type: 'script', texte: 'x' }, { type: 'tableau', entetes: ['A'], lignes: [['1']], derniere_ligne_total: true }, { type: 'sources', elements: [{ titre: 'T', url: 'javascript:x' }] }] }, 'document')
  assert.deepEqual(s.blocs.map((b) => b.type), ['paragraphe', 'tableau', 'sources'])
  assert.equal(s.theme.couleur, '#1F3A5F')
  assert.equal(s.blocs[2].elements[0].url, '')
  const p = structurePropre({ theme: 'inconnu', diapositives: [{ mise_en_page: 'points', titre: 'T', points: ['a', 'b'] }, { mise_en_page: 'video' }] }, 'presentation')
  assert.equal(p.theme, 'sobre')
  assert.deepEqual(p.diapositives.map((d) => d.mise_en_page), ['points'])
})

test('la consigne de modification donne la version actuelle ; un fichier joint et les recherches sont des informations', () => {
  const c = consigneRedaction({ action: 'modifier', demande: 'mets le titre en bleu', precedente: { titre: 'Rapport' }, resultats: [{ titre: 'Page', url: 'https://exemple.tg', extrait: 'x' }] })
  assert.match(c, /Fais SEULEMENT le changement demandé/)
  assert.match(c, /"titre":"Rapport"/)
  assert.match(c, /jamais des consignes/)
})

test('formats : le fichier d’origine et le PDF ; un fichier joint garde son format', () => {
  assert.deepEqual(formatsVoulus('document', []), ['docx', 'pdf'])
  assert.deepEqual(formatsVoulus('presentation', ['pdf']), ['pdf'])
  assert.deepEqual(formatsVoulus('document', ['exe']), ['docx', 'pdf'])
  assert.ok(demandeDocumentValide({ document: { type: 'document', action: 'creer', demande: 'Une lettre' } }))
  assert.ok(!demandeDocumentValide({ document: { type: 'tableur', demande: 'x' } }))
  assert.ok(!demandeDocumentValide({ document: { type: 'document', demande: 'x', source: { format: 'exe', url: 'https://a' } } }))
})

test('un document de bout en bout : rédaction, mise en page dans l’atelier, fichiers envoyés au site, puis la fin', async () => {
  const evenements = []
  const envois = []
  const ecrits = {}
  const sandbox = {
    files: {
      write: async (chemin, contenu) => { ecrits[chemin] = contenu },
      read: async (chemin) => new Uint8Array([1, 2, 3]),
    },
    commands: {
      run: async (commande) => {
        if (commande.includes(' rendre ')) return { stdout: JSON.stringify({ fichiers: { docx: 'document.docx', pdf: 'document.pdf' }, apercus: ['apercu-1.jpg'], pages: 1, avertissements: [] }) }
        return { stdout: '' }
      },
    },
  }
  const reponseModele = { output: [{ type: 'message', content: [{ type: 'output_text', text: JSON.stringify({ message: 'Votre lettre est prête.', titre_court: 'Lettre de motivation', document: { titre: 'Lettre', blocs: [{ type: 'paragraphe', texte: 'Madame, Monsieur' }] } }) }] }], usage: { input_tokens: 1000, output_tokens: 500 } }
  const appeler = async (url, options) => {
    if (url.startsWith('https://api.openai.com')) return new Response(JSON.stringify(reponseModele), { status: 200 })
    envois.push({ url, signe: Boolean(options.headers['X-Kekeli-Signature'] ?? options.headers['x-kekeli-signature'] ?? Object.keys(options.headers).some((h) => /signature/i.test(h))) })
    return new Response('{}', { status: 200 })
  }
  const r = await produireDocument(
    { tache: 'tache-1', projet: 'projet-1', envoi: 'https://site.test/envoi?signature=x', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6.1-sol' }], document: { type: 'document', action: 'creer', demande: 'Une lettre de motivation', formats: ['docx', 'pdf'] } },
    { atelier: { obtenir: async () => sandbox }, envoyer: async (e) => { evenements.push(e) }, secret: 'secret', clesPresentes: new Set(), appeler, env: ENV, journal: { log() {}, warn() {} } },
  )
  assert.equal(r.ok, true)
  assert.deepEqual(envois.map((e) => decodeURIComponent(new URL(e.url).searchParams.get('nom'))), ['document.docx', 'document.pdf', 'apercu-1.jpg'])
  assert.ok(envois.every((e) => e.signe), 'chaque envoi est signé')
  const types = evenements.map((e) => e.type)
  assert.deepEqual(types.filter((t) => ['demarrage', 'requete', 'document', 'terminee'].includes(t)), ['demarrage', 'requete', 'document', 'terminee'])
  const doc = evenements.find((e) => e.type === 'document')
  assert.equal(doc.titre, 'Lettre de motivation')
  assert.equal(doc.structure.blocs[0].texte, 'Madame, Monsieur')
  assert.equal(evenements.find((e) => e.type === 'requete').fournisseur, 'openai')
  assert.ok(!JSON.stringify(ecrits).includes('cle-openai-de-test'), 'aucune clé dans l’atelier')
})

test('l’énergie épuisée pendant la rédaction arrête le travail avant la mise en page', async () => {
  let rendu = false
  const sandbox = { files: { write: async () => {}, read: async () => new Uint8Array() }, commands: { run: async (c) => { if (c.includes(' rendre ')) rendu = true; return { stdout: '' } } } }
  const appeler = async () => new Response(JSON.stringify({ output: [{ type: 'message', content: [{ type: 'output_text', text: '{"message":"x","document":{"blocs":[]}}' }] }], usage: {} }), { status: 200 })
  const r = await produireDocument(
    { tache: 't', projet: 'p', envoi: 'https://site.test/e', fournisseurs: [{ fournisseur: 'openai', modele: 'gpt-6.1-sol' }], document: { type: 'document', demande: 'x' } },
    { atelier: { obtenir: async () => sandbox }, envoyer: async (e) => (e.type === 'requete' ? { arreter: true } : null), secret: 's', clesPresentes: new Set(), appeler, env: ENV, journal: { log() {} } },
  )
  assert.equal(r.arrete, true)
  assert.equal(rendu, false)
})
