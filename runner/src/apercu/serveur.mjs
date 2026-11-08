// Serveur d'aperçu de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel et lancé avec Node.js.
// Il montre les fichiers du projet comme un vrai site, et recharge la page dès qu'un fichier change.
// Aucune dépendance : seulement les modules de Node.js.
//
//   APERCU_DOSSIER : dossier du projet (par défaut /home/user/projet)
//   APERCU_PORT    : port d'écoute (par défaut 4100)
import http from 'node:http'
import { promises as fs } from 'node:fs'
import path from 'node:path'
import { pathToFileURL } from 'node:url'

export const CHEMIN_DIRECT = '/__kekeli/direct'
export const CHEMIN_SANTE = '/__kekeli/sante'

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.htm': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.md': 'text/plain; charset=utf-8',
  '.xml': 'application/xml; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.avif': 'image/avif',
  '.ico': 'image/x-icon',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.ttf': 'font/ttf',
  '.otf': 'font/otf',
  '.mp4': 'video/mp4',
  '.webm': 'video/webm',
  '.mp3': 'audio/mpeg',
  '.wav': 'audio/wav',
  '.pdf': 'application/pdf',
  '.webmanifest': 'application/manifest+json',
}

const IGNORES = new Set(['node_modules'])
const FICHIERS_MAXIMUM = 5000

// Petit script ajouté aux pages HTML de l'aperçu seulement : il recharge la page quand un fichier change.
export const SCRIPT_DIRECT = `<script data-kekeli-apercu>(function(){var s=new EventSource('${CHEMIN_DIRECT}');var t;s.addEventListener('changement',function(){clearTimeout(t);t=setTimeout(function(){location.reload()},250)})})()</script>`

function pageKekeli(titre, texte) {
  return `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${titre}</title>
<style>
  body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #fffbeb; color: #3f3f46; text-align: center; padding: 24px; box-sizing: border-box; }
  .soleil { font-size: 48px; animation: tourne 6s linear infinite; display: inline-block; }
  h1 { font-size: 20px; margin: 16px 0 8px; color: #18181b; }
  p { margin: 0 auto; max-width: 320px; line-height: 1.5; }
  @keyframes tourne { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<main>
  <div class="soleil" aria-hidden="true">☀️</div>
  <h1>${titre}</h1>
  <p>${texte}</p>
</main>
${SCRIPT_DIRECT}
</body>
</html>`
}

export const PAGE_ATTENTE = pageKekeli(
  'Votre site apparaîtra ici',
  'Kekeli n’a pas encore créé la page d’accueil. Elle s’affichera toute seule dès qu’elle sera prête.',
)

export const PAGE_INTROUVABLE = pageKekeli('Page introuvable', 'Cette page n’existe pas encore dans votre projet.')

/** Ajoute le script de rechargement à une page HTML, juste avant la fin du corps de la page. */
export function ajouterScript(html) {
  const position = html.toLowerCase().lastIndexOf('</body>')
  return position === -1 ? html + SCRIPT_DIRECT : html.slice(0, position) + SCRIPT_DIRECT + html.slice(position)
}

/**
 * Transforme le chemin demandé en chemin de fichier dans le dossier du projet.
 * Renvoie null si le chemin sort du dossier ou touche un fichier caché (par exemple .env ou .git).
 */
export function cheminSur(racine, cheminDemande) {
  let decode
  try {
    decode = decodeURIComponent(cheminDemande)
  } catch {
    return null
  }
  if (decode.includes('\0')) return null
  const morceaux = decode.split(/[\\/]+/).filter(Boolean)
  if (morceaux.some((m) => m.startsWith('.'))) return null
  const complet = path.resolve(racine, ...morceaux)
  return complet === racine || complet.startsWith(racine + path.sep) ? complet : null
}

/** Empreinte de tous les fichiers du projet (nom, taille, date de modification) pour repérer les changements. */
export async function empreinte(racine) {
  const lignes = []
  async function parcourir(dossier) {
    let entrees
    try {
      entrees = await fs.readdir(dossier, { withFileTypes: true })
    } catch {
      return
    }
    for (const entree of entrees) {
      if (lignes.length >= FICHIERS_MAXIMUM) return
      if (entree.name.startsWith('.') || IGNORES.has(entree.name)) continue
      const complet = path.join(dossier, entree.name)
      if (entree.isDirectory()) await parcourir(complet)
      else if (entree.isFile()) {
        try {
          const info = await fs.stat(complet)
          lignes.push(`${complet}:${info.size}:${info.mtimeMs}`)
        } catch {}
      }
    }
  }
  await parcourir(racine)
  return lignes.sort().join('\n')
}

export function creerServeurApercu({ dossier = '/home/user/projet', intervalleMs = 700 } = {}) {
  const racine = path.resolve(dossier)
  const clients = new Set()
  let derniere = null
  let minuterie = null

  async function surveiller() {
    const actuelle = await empreinte(racine)
    if (derniere !== null && actuelle !== derniere) {
      for (const client of clients) client.write('event: changement\ndata: {}\n\n')
    }
    derniere = actuelle
    minuterie = setTimeout(surveiller, intervalleMs)
  }

  function envoyer(res, code, type, contenu) {
    res.writeHead(code, {
      'Content-Type': type,
      'Cache-Control': 'no-store',
      'X-Content-Type-Options': 'nosniff',
    })
    res.end(contenu)
  }

  async function servirFichier(res, fichier) {
    const extension = path.extname(fichier).toLowerCase()
    const type = TYPES[extension] ?? 'application/octet-stream'
    const contenu = await fs.readFile(fichier)
    if (type.startsWith('text/html')) return envoyer(res, 200, type, ajouterScript(contenu.toString('utf8')))
    return envoyer(res, 200, type, contenu)
  }

  const serveur = http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url ?? '/', 'http://apercu')

      if (url.pathname === CHEMIN_SANTE) return envoyer(res, 200, 'application/json; charset=utf-8', '{"ok":true}')

      if (url.pathname === CHEMIN_DIRECT) {
        res.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-store', Connection: 'keep-alive' })
        res.write(': kekeli\n\n')
        clients.add(res)
        const battement = setInterval(() => res.write(': battement\n\n'), 20000)
        req.on('close', () => {
          clearInterval(battement)
          clients.delete(res)
        })
        return
      }

      if (req.method !== 'GET' && req.method !== 'HEAD') return envoyer(res, 405, 'text/plain; charset=utf-8', 'Méthode non autorisée')

      const fichier = cheminSur(racine, url.pathname)
      if (!fichier) return envoyer(res, 404, 'text/html; charset=utf-8', PAGE_INTROUVABLE)

      // Un lien symbolique ne doit pas permettre de sortir du dossier du projet.
      let reel
      try {
        reel = await fs.realpath(fichier)
      } catch {
        reel = null
      }
      const racineReelle = await fs.realpath(racine).catch(() => racine)
      if (reel && reel !== racineReelle && !reel.startsWith(racineReelle + path.sep)) {
        return envoyer(res, 404, 'text/html; charset=utf-8', PAGE_INTROUVABLE)
      }

      const info = reel ? await fs.stat(reel) : null
      if (info?.isDirectory()) {
        if (!url.pathname.endsWith('/')) {
          res.writeHead(302, { Location: url.pathname + '/' + url.search, 'Cache-Control': 'no-store' })
          return res.end()
        }
        const index = path.join(reel, 'index.html')
        if (await fs.stat(index).then((i) => i.isFile()).catch(() => false)) return await servirFichier(res, index)
        if (reel === racineReelle) return envoyer(res, 200, 'text/html; charset=utf-8', PAGE_ATTENTE)
        return envoyer(res, 404, 'text/html; charset=utf-8', PAGE_INTROUVABLE)
      }
      if (info?.isFile()) return await servirFichier(res, reel)
      if (!reel && url.pathname === '/') return envoyer(res, 200, 'text/html; charset=utf-8', PAGE_ATTENTE)
      return envoyer(res, 404, 'text/html; charset=utf-8', PAGE_INTROUVABLE)
    } catch {
      return envoyer(res, 500, 'text/plain; charset=utf-8', 'Erreur de l’aperçu')
    }
  })

  surveiller()
  serveur.on('close', () => {
    clearTimeout(minuterie)
    for (const client of clients) client.end()
  })
  return serveur
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const port = Number(process.env.APERCU_PORT ?? 4100)
  creerServeurApercu({ dossier: process.env.APERCU_DOSSIER ?? '/home/user/projet' }).listen(port, '0.0.0.0', () => {
    console.log(`Aperçu de Kekeli prêt sur le port ${port}.`)
  })
}
