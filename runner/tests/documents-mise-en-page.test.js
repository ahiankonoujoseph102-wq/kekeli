// Mise en page des documents comme Claude (15 novembre 2026) : sommaire avec les numéros de page, lignes de total en
// gras, pas de page de titre presque vide pour un document court, tableau jamais coupé. Le test rend un vrai document
// avec le moteur (python-docx et LibreOffice) quand ils sont installés, sinon il est sauté.
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'

const MOTEUR = new URL('../src/documents/rendu/kekeli_documents.py', import.meta.url).pathname
const outils = (() => {
  try {
    execFileSync('python3', ['-c', 'import docx, pypdf'])
    execFileSync('soffice', ['--version'])
    return true
  } catch {
    return false
  }
})()

const paragraphe = (n) => ({ type: 'paragraphe', texte: `Paragraphe ${n} : ${'les ventes de la boutique progressent chaque mois grâce aux clients fidèles. '.repeat(6)}` })

test('rapport court : titre en haut, sommaire avec ses pages, total en gras, tableau entier', { skip: !outils }, () => {
  const dossier = mkdtempSync(join(tmpdir(), 'kekeli-doc-'))
  const structure = {
    type: 'document', titre: 'Rapport des ventes', sous_titre: 'Kossi Phones', options: { sommaire: true, page_de_titre: true, numeros_page: true },
    blocs: [
      { type: 'titre1', texte: '1. Introduction' }, paragraphe(1), paragraphe(2),
      { type: 'titre1', texte: '2. Analyse des chiffres' }, paragraphe(3),
      { type: 'tableau', entetes: ['Mois', 'Montant'], lignes: [['Janvier', '2 400 000'], ['Février', '2 100 000'], ['Total du semestre', '4 500 000']] },
      { type: 'titre1', texte: '3. Recommandations' }, paragraphe(4),
    ],
  }
  writeFileSync(join(dossier, 'structure.json'), JSON.stringify(structure))
  const r = JSON.parse(execFileSync('python3', ['-I', MOTEUR, 'rendre', join(dossier, 'structure.json'), dossier, 'docx,pdf']).toString())
  // Pas de page de titre ni de page de sommaire presque vides : la première page a le titre, le sommaire et le texte.
  const premiere = execFileSync('python3', ['-c', 'import sys; from pypdf import PdfReader; print(PdfReader(sys.argv[1]).pages[0].extract_text())', join(dossier, 'document.pdf')]).toString()
  assert.match(premiere, /Rapport des ventes/)
  assert.match(premiere, /Paragraphe 1/)
  assert.ok(r.pages <= 2)
  const xml = execFileSync('unzip', ['-p', join(dossier, 'document.docx'), 'word/document.xml']).toString()
  // Les numéros de page du sommaire, après un point de conduite.
  assert.match(xml, /w:leader="dot"/)
  assert.match(xml, /<w:tab\/><w:t>1<\/w:t>/)
  // La ligne « Total du semestre » est en gras, et le tableau ne se coupe pas.
  assert.match(xml, /<w:b\/>[\s\S]{0,400}Total du semestre/)
  assert.match(xml, /w:cantSplit/)
})
