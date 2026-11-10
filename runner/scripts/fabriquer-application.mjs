// Écrit une application web dans un dossier, pour l'essayer : node scripts/fabriquer-application.mjs laravel reservations-restaurant /chemin/du/dossier
import { mkdirSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fichiersApplication } from '../src/applications/index.js'

const [pile, modele, dossier] = process.argv.slice(2)
if (!pile || !modele || !dossier) {
  console.error('Usage : node scripts/fabriquer-application.mjs <laravel|nextjs> <modèle> <dossier>')
  process.exit(1)
}
const fichiers = fichiersApplication(pile, modele)
for (const [chemin, contenu] of Object.entries(fichiers)) {
  const complet = path.join(dossier, chemin)
  mkdirSync(path.dirname(complet), { recursive: true })
  writeFileSync(complet, contenu)
}
console.log(`${Object.keys(fichiers).length} fichiers écrits dans ${dossier}.`)
