// Installe FreeLLMAPI (licence MIT) à côté du Runner, à la version figée de runner/src/freellmapi.js :
//   npm run freellmapi:installer   (dans le dossier runner, au déploiement)
// Seul le serveur est construit (ni le tableau de bord, ni l'application de bureau). Aucune clé n'est lue ici.
import { execFileSync } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { VERSION_FREELLMAPI } from '../src/freellmapi.js'

const ici = path.dirname(fileURLToPath(import.meta.url))
const depot = path.join(ici, 'depot')
const marque = path.join(depot, '.version-kekeli')
const lancer = (commande, args, cwd = depot) => execFileSync(commande, args, { cwd, stdio: 'inherit' })

if (existsSync(marque) && readFileSync(marque, 'utf8').trim() === VERSION_FREELLMAPI && existsSync(path.join(depot, 'server/dist/index.js'))) {
  console.log(`FreeLLMAPI ${VERSION_FREELLMAPI.slice(0, 7)} déjà installé.`)
  process.exit(0)
}
rmSync(depot, { recursive: true, force: true })
mkdirSync(depot, { recursive: true })
// L'archive exacte de la version figée, par HTTPS (sans git, absent de certains hébergeurs pendant la construction).
const reponse = await fetch(`https://codeload.github.com/tashfeenahmed/freellmapi/tar.gz/${VERSION_FREELLMAPI}`)
if (!reponse.ok) throw new Error(`téléchargement de FreeLLMAPI impossible : ${reponse.status}`)
const archive = path.join(tmpdir(), `freellmapi-${VERSION_FREELLMAPI}.tar.gz`)
writeFileSync(archive, Buffer.from(await reponse.arrayBuffer()))
lancer('tar', ['-xzf', archive, '--strip-components=1', '-C', depot], ici)
rmSync(archive, { force: true })
lancer('npm', ['ci', '--no-audit', '--no-fund', '-w', 'shared', '-w', 'server'])
lancer('npm', ['run', 'build', '-w', 'server'])
writeFileSync(marque, `${VERSION_FREELLMAPI}\n`)
console.log(`FreeLLMAPI ${VERSION_FREELLMAPI.slice(0, 7)} installé dans ${depot}.`)
