// Fabrique le moteur de Kekeli Motion à partir des sources TypeScript : npm run motion:construire
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { construireMoteur, DOSSIER_CONSTRUIT } from '../src/motion/construction.js'

const { navigateur, runner } = await construireMoteur()
await mkdir(DOSSIER_CONSTRUIT, { recursive: true })
await writeFile(path.join(DOSSIER_CONSTRUIT, 'kekeli-motion.js'), navigateur)
await writeFile(path.join(DOSSIER_CONSTRUIT, 'kekeli-motion.mjs'), runner)
console.log(`Moteur fabriqué : ${Math.round(navigateur.length / 1024)} Ko pour le navigateur.`)
