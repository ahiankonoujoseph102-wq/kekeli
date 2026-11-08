// Serveur d'aperçu de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel et lancé avec Node.js.
// Il montre les fichiers du projet comme un vrai site, et recharge la page dès qu'un fichier change.
// Aucune dépendance : seulement les modules de Node.js.
//
//   APERCU_DOSSIER : dossier du projet (par défaut /home/user/projet)
//   APERCU_PORT    : port d'écoute (par défaut 4100)
//   APERCU_PROJET  : identifiant du projet
//   APERCU_CLE     : clé de l'aperçu de ce projet (calculée par le Runner, jamais une clé de fournisseur)
//
// L'aperçu est privé : chaque adresse commence par /k/<jeton>/, où le jeton est fabriqué par le site
// Kekeli pour le propriétaire du projet (et l'administrateur), et n'est valable que peu de temps.
import { createHmac, timingSafeEqual } from 'node:crypto'
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

/** Clé de l'aperçu d'un projet, tirée du secret du Runner (le site Kekeli fait le même calcul). */
export function cleApercu(secretRunner, projet) {
  return createHmac('sha256', secretRunner).update(`apercu:${projet}`).digest('hex')
}

/** Jeton d'accès à l'aperçu, valable jusqu'à « expiration » (secondes depuis 1970). */
export function jetonApercu(cle, projet, expiration) {
  const signature = createHmac('sha256', cle).update(`${projet}.${expiration}`).digest('hex').slice(0, 32)
  return `${expiration}.${signature}`
}

export function jetonValide(cle, projet, jeton, maintenant = Date.now()) {
  const morceaux = /^(\d{9,12})\.([0-9a-f]{32})$/.exec(String(jeton ?? ''))
  if (!cle || !projet || !morceaux) return false
  if (Number(morceaux[1]) * 1000 < maintenant) return false
  const attendu = Buffer.from(jetonApercu(cle, projet, morceaux[1]))
  const recu = Buffer.from(String(jeton))
  return attendu.length === recu.length && timingSafeEqual(attendu, recu)
}

// Petit script ajouté aux pages HTML de l'aperçu seulement : il recharge la page quand un fichier change.
export function scriptDirect(prefixe = '/') {
  return `<script data-kekeli-apercu>(function(){var s=new EventSource('${prefixe}${CHEMIN_DIRECT.slice(1)}');var t;s.addEventListener('changement',function(){clearTimeout(t);t=setTimeout(function(){location.reload()},250)})})()</script>`
}
export const SCRIPT_DIRECT = scriptDirect()

function pageKekeli(titre, texte, prefixe = '/') {
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
${prefixe ? scriptDirect(prefixe) : ''}
</body>
</html>`
}

export const pageAttente = (prefixe = '/') =>
  pageKekeli(
    'Votre site apparaîtra ici',
    'Kekeli n’a pas encore créé la page d’accueil. Elle s’affichera toute seule dès qu’elle sera prête.',
    prefixe,
  )
export const PAGE_ATTENTE = pageAttente()

export const pageIntrouvable = (prefixe = '/') => pageKekeli('Page introuvable', 'Cette page n’existe pas encore dans votre projet.', prefixe)
export const PAGE_INTROUVABLE = pageIntrouvable()

export const PAGE_PRIVEE = pageKekeli(
  'Aperçu privé',
  'Cet aperçu est réservé au propriétaire du projet. Ouvrez-le depuis votre page de projet dans Kekeli.',
  null,
)

/** Ajoute le script de rechargement à une page HTML, juste avant la fin du corps de la page. */
export function ajouterScript(html, prefixe = '/') {
  const script = scriptDirect(prefixe)
  const position = html.toLowerCase().lastIndexOf('</body>')
  return position === -1 ? html + script : html.slice(0, position) + script + html.slice(position)
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

export function creerServeurApercu({ dossier = '/home/user/projet', intervalleMs = 700, projet, cle, maintenant = () => Date.now() } = {}) {
  const racine = path.resolve(dossier)
  const clients = new Set()
  let derniere = null
  let minuterie = null

  let ferme = false

  async function surveiller() {
    const actuelle = await empreinte(racine)
    // Le serveur a pu être fermé pendant la lecture des fichiers : on ne relance pas la surveillance.
    if (ferme) return
    if (derniere !== null && actuelle !== derniere) {
      for (const client of clients) client.write('event: changement\ndata: {}\n\n')
    }
    derniere = actuelle
    minuterie = setTimeout(surveiller, intervalleMs)
    // La surveillance seule ne garde pas le programme en vie : c'est le serveur qui le fait.
    minuterie.unref?.()
  }

  function envoyer(res, code, type, contenu) {
    res.writeHead(code, {
      'Content-Type': type,
      'Cache-Control': 'no-store',
      'X-Content-Type-Options': 'nosniff',
    })
    res.end(contenu)
  }

  async function servirFichier(res, fichier, prefixe) {
    const extension = path.extname(fichier).toLowerCase()
    const type = TYPES[extension] ?? 'application/octet-stream'
    const contenu = await fs.readFile(fichier)
    if (type.startsWith('text/html')) return envoyer(res, 200, type, ajouterScript(contenu.toString('utf8'), prefixe))
    return envoyer(res, 200, type, contenu)
  }

  /** Jeton valable trouvé au début d'un chemin « /k/<jeton>/… », ou null. */
  function jetonDuChemin(chemin) {
    const morceaux = /^\/k\/([^/]+)(\/.*)?$/.exec(chemin)
    if (!morceaux || !jetonValide(cle, projet, morceaux[1], maintenant())) return null
    return { jeton: morceaux[1], reste: morceaux[2] ?? '' }
  }

  const serveur = http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url ?? '/', 'http://apercu')

      if (url.pathname === CHEMIN_SANTE) return envoyer(res, 200, 'application/json; charset=utf-8', '{"ok":true}')

      const acces = jetonDuChemin(url.pathname)
      if (!acces) {
        // Une page de l'aperçu qui utilise un lien absolu (par exemple « /menu.html ») : la page d'origine
        // porte le jeton, on redirige vers la même adresse avec le jeton.
        const depuis = (() => {
          try {
            const origine = new URL(req.headers.referer ?? '')
            return origine.host === req.headers.host ? jetonDuChemin(origine.pathname) : null
          } catch {
            return null
          }
        })()
        if (depuis && req.method === 'GET') {
          res.writeHead(302, { Location: `/k/${depuis.jeton}${url.pathname}${url.search}`, 'Cache-Control': 'no-store' })
          return res.end()
        }
        return envoyer(res, 403, 'text/html; charset=utf-8', PAGE_PRIVEE)
      }
      if (acces.reste === '') {
        res.writeHead(302, { Location: `/k/${acces.jeton}/${url.search}`, 'Cache-Control': 'no-store' })
        return res.end()
      }
      const prefixe = `/k/${acces.jeton}/`
      const chemin = acces.reste

      if (chemin === CHEMIN_DIRECT) {
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

      const fichier = cheminSur(racine, chemin)
      if (!fichier) return envoyer(res, 404, 'text/html; charset=utf-8', pageIntrouvable(prefixe))

      // Un lien symbolique ne doit pas permettre de sortir du dossier du projet.
      let reel
      try {
        reel = await fs.realpath(fichier)
      } catch {
        reel = null
      }
      const racineReelle = await fs.realpath(racine).catch(() => racine)
      if (reel && reel !== racineReelle && !reel.startsWith(racineReelle + path.sep)) {
        return envoyer(res, 404, 'text/html; charset=utf-8', pageIntrouvable(prefixe))
      }

      const info = reel ? await fs.stat(reel) : null
      if (info?.isDirectory()) {
        if (!chemin.endsWith('/')) {
          res.writeHead(302, { Location: prefixe + chemin.slice(1) + '/' + url.search, 'Cache-Control': 'no-store' })
          return res.end()
        }
        const index = path.join(reel, 'index.html')
        if (await fs.stat(index).then((i) => i.isFile()).catch(() => false)) return await servirFichier(res, index, prefixe)
        if (reel === racineReelle) return envoyer(res, 200, 'text/html; charset=utf-8', pageAttente(prefixe))
        return envoyer(res, 404, 'text/html; charset=utf-8', pageIntrouvable(prefixe))
      }
      if (info?.isFile()) return await servirFichier(res, reel, prefixe)
      if (!reel && chemin === '/') return envoyer(res, 200, 'text/html; charset=utf-8', pageAttente(prefixe))
      return envoyer(res, 404, 'text/html; charset=utf-8', pageIntrouvable(prefixe))
    } catch {
      return envoyer(res, 500, 'text/plain; charset=utf-8', 'Erreur de l’aperçu')
    }
  })

  surveiller()
  serveur.on('close', () => {
    ferme = true
    clearTimeout(minuterie)
    for (const client of clients) client.end()
  })
  return serveur
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const port = Number(process.env.APERCU_PORT ?? 4100)
  creerServeurApercu({
    dossier: process.env.APERCU_DOSSIER ?? '/home/user/projet',
    projet: process.env.APERCU_PROJET,
    cle: process.env.APERCU_CLE,
  }).listen(port, '0.0.0.0', () => {
    console.log(`Aperçu de Kekeli prêt sur le port ${port}.`)
  })
}
