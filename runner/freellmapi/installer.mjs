// Installe FreeLLMAPI (licence MIT) à côté du Runner, à la version figée de runner/src/freellmapi.js :
//   npm run freellmapi:installer   (dans le dossier runner, au déploiement)
// Seul le serveur est construit (ni le tableau de bord, ni l'application de bureau). Aucune clé n'est lue ici.
import { execFileSync } from 'node:child_process'
import { existsSync, readFileSync, rmSync } from 'node:fs'
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
lancer('git', ['init', '-q', depot], ici)
lancer('git', ['fetch', '-q', '--depth', '1', 'https://github.com/tashfeenahmed/freellmapi.git', VERSION_FREELLMAPI])
lancer('git', ['checkout', '-q', 'FETCH_HEAD'])
lancer('npm', ['ci', '--no-audit', '--no-fund', '-w', 'shared', '-w', 'server'])
lancer('npm', ['run', 'build', '-w', 'server'])
execFileSync('sh', ['-c', `echo ${VERSION_FREELLMAPI} > .version-kekeli`], { cwd: depot })
console.log(`FreeLLMAPI ${VERSION_FREELLMAPI.slice(0, 7)} installé dans ${depot}.`)
