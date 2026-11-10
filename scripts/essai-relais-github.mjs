// Relais local pour l'essai réel du mode développeur, quand l'interface de GitHub n'est pas joignable depuis
// l'environnement d'essai : il répond comme GitHub pour ouvrir un dépôt PUBLIC en lecture seule, à partir d'un
// clone Git (même contenu). Jamais utilisé en production : le site parle alors directement à api.github.com.
//
//   node scripts/essai-relais-github.mjs 8124 proprietaire/nom=/chemin/du/clone …
//   puis lancer le site avec KEKELI_GITHUB_API=http://127.0.0.1:8124
import { execFileSync } from 'node:child_process'
import http from 'node:http'

const [port, ...correspondances] = process.argv.slice(2)
const depots = Object.fromEntries(correspondances.map((c) => c.split('=')))
const git = (dossier, ...args) => execFileSync('git', ['-C', dossier, ...args], { maxBuffer: 512 * 1024 * 1024 })

http.createServer((req, res) => {
  const morceaux = decodeURIComponent(new URL(req.url, 'http://relais').pathname).split('/').filter(Boolean)
  const nom = `${morceaux[1]}/${morceaux[2]}`
  const dossier = depots[nom]
  const json = (code, donnees) => {
    res.writeHead(code, { 'Content-Type': 'application/json' })
    res.end(JSON.stringify(donnees))
  }
  if (morceaux[0] !== 'repos' || !dossier) return json(404, { message: 'Not Found' })
  const branche = git(dossier, 'rev-parse', '--abbrev-ref', 'HEAD').toString().trim()
  const sha = git(dossier, 'rev-parse', 'HEAD').toString().trim()
  if (morceaux.length === 3) return json(200, { full_name: nom, default_branch: branche, private: false, permissions: { push: false } })
  if (morceaux[3] === 'commits') return json(200, { sha })
  if (morceaux[3] === 'tarball') {
    res.writeHead(200, { 'Content-Type': 'application/gzip' })
    return res.end(git(dossier, 'archive', '--format=tar.gz', `--prefix=${nom.replace('/', '-')}-${sha.slice(0, 7)}/`, 'HEAD'))
  }
  json(404, { message: 'Not Found' })
}).listen(Number(port), '127.0.0.1', () => console.log(`Relais GitHub d'essai sur le port ${port}`))
