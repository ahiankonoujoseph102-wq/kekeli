// Serveur d'aperçu de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel et lancé avec Node.js.
// Il montre les fichiers du projet comme un vrai site, et recharge la page dès qu'un fichier change.
// Aucune dépendance : seulement les modules de Node.js.
//
//   APERCU_DOSSIER : dossier du projet (par défaut /home/user/projet)
//   APERCU_PORT    : port d'écoute (par défaut 4100)
//   APERCU_PROJET  : identifiant du projet
//   APERCU_CLE     : clé de l'aperçu de ce projet (calculée par le Runner, jamais une clé de fournisseur)
//   APERCU_APPLICATION : fichier de réglages d'une application web (commande et port), pour le mode application
//
// Mode application web : le serveur lance l'application du client (Laravel ou Next.js), la relance si elle
// s'arrête, et la montre. Le lien privé /k/<jeton>/ dépose un cookie protégé, puis l'application est vue à son
// adresse normale (ses liens et ses formulaires marchent tels quels). Les cookies de l'application sont adaptés
// pour fonctionner dans le cadre de la page de Kekeli.
//
// L'aperçu est privé : chaque adresse commence par /k/<jeton>/, où le jeton est fabriqué par le site
// Kekeli pour le propriétaire du projet (et l'administrateur), et n'est valable que peu de temps.
import { spawn } from 'node:child_process'
import { createHmac, timingSafeEqual } from 'node:crypto'
import http from 'node:http'
import { openSync, promises as fs } from 'node:fs'
import net from 'node:net'
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

// Dossiers jamais surveillés : dépendances, fichiers écrits par l'application elle-même (journaux, base d'essai).
const IGNORES = new Set(['node_modules', 'vendor', 'storage'])
const FICHIERS_IGNORES = /\.(sqlite|sqlite-journal|sqlite-wal|log)$/
export const COOKIE_APERCU = 'kekeli_apercu'
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
  .chargement { width: 32px; height: 32px; border: 3px solid #e4e4e7; border-top-color: #f59e0b; border-radius: 50%; animation: tourne 0.9s linear infinite; display: inline-block; }
  h1 { font-size: 20px; margin: 16px 0 8px; color: #18181b; }
  p { margin: 0 auto; max-width: 320px; line-height: 1.5; }
  @keyframes tourne { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<main>
  <div class="chargement" aria-hidden="true"></div>
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

export const pageApplicationDemarre = (installation) =>
  pageKekeli(
    installation ? 'Votre site s’installe' : 'Votre site redémarre',
    installation ? 'Kekeli prépare votre site. Il s’affichera tout seul dans un instant.' : 'Encore quelques secondes : la page se recharge toute seule.',
    '/',
  ).replace('<meta charset="utf-8">', '<meta charset="utf-8">\n<meta http-equiv="refresh" content="4">')

/** Durée après un lancement du site pendant laquelle une erreur du serveur est montrée comme un redémarrage. */
export const DUREE_REDEMARRAGE_MS = 90 * 1000

/** Erreur du site du client hors redémarrage : un message clair en français, jamais la page technique en anglais. */
export const PAGE_ERREUR_SITE = pageKekeli(
  'Cette page a rencontré un problème',
  'Votre site est en construction et cette page ne s’affiche pas encore correctement. Dites à Kekeli, dans la discussion, ce que vous faisiez : il trouvera la cause et la corrigera.',
  '/',
)

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
      if (entree.name.startsWith('.') || IGNORES.has(entree.name) || FICHIERS_IGNORES.test(entree.name)) continue
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

/** Valeur d'un cookie dans l'en-tête Cookie d'une requête. */
export function lireCookie(entete, nom) {
  for (const morceau of String(entete ?? '').split(';')) {
    const [cle, ...valeur] = morceau.trim().split('=')
    if (cle === nom) return valeur.join('=')
  }
  return null
}

/** En-tête Cookie sans le cookie de l'aperçu (l'application du client ne le voit jamais). */
export function sansCookieApercu(entete) {
  const reste = String(entete ?? '')
    .split(';')
    .map((m) => m.trim())
    .filter((m) => m && !m.startsWith(COOKIE_APERCU + '='))
  return reste.length ? reste.join('; ') : null
}

/**
 * Cookie posé par l'application, adapté au cadre de la page de Kekeli : envoyé aussi depuis le cadre
 * (SameSite=None), seulement en HTTPS (Secure) et rangé à part pour le site Kekeli (Partitioned).
 */
export function adapterCookie(ligne) {
  const attributs = String(ligne)
    .split(';')
    .map((a) => a.trim())
    .filter((a) => a && !/^(samesite|secure|partitioned)(=|$)/i.test(a))
  return [...attributs, 'SameSite=None', 'Secure', 'Partitioned'].join('; ')
}

/** Réglages de l'application (commande, port, variables), ou null tant qu'ils n'existent pas. */
async function lireReglagesApplication(fichier) {
  try {
    const reglages = JSON.parse(await fs.readFile(fichier, 'utf8'))
    return Number.isInteger(reglages.port) && typeof reglages.commande === 'string' ? reglages : null
  } catch {
    return null
  }
}

/**
 * Lance l'application du client et la relance si elle s'arrête (attente qui double, au plus 30 secondes).
 * Elle est aussi relancée quand ses réglages changent, ou quand le fichier « relancer » est touché.
 */
export function superviserApplication({ fichier, dossier, intervalleMs = 2000, journal = '/tmp/application.log' }) {
  let enfant = null
  let signature = null
  let attente = 1000
  let prochainEssai = 0
  let arrete = false
  let minuterie = null
  let lanceLe = 0

  const arreterEnfant = () => {
    if (!enfant) return
    try {
      process.kill(-enfant.pid, 'SIGTERM')
    } catch {}
    enfant = null
  }

  async function verifier() {
    if (arrete) return
    const reglages = await lireReglagesApplication(fichier)
    const relance = await fs.stat(path.join(path.dirname(fichier), 'relancer')).then((i) => i.mtimeMs).catch(() => 0)
    const nouvelle = reglages ? JSON.stringify([reglages, relance]) : null
    if (nouvelle !== signature) {
      arreterEnfant()
      signature = nouvelle
      attente = 1000
      prochainEssai = 0
    }
    if (reglages && !enfant && Date.now() >= prochainEssai) {
      const sortie = openSync(journal, 'a')
      const lance = spawn('bash', ['-lc', reglages.commande], { cwd: dossier, env: { ...process.env, ...(reglages.env ?? {}) }, detached: true, stdio: ['ignore', sortie, sortie] })
      enfant = lance
      lanceLe = Date.now()
      lance.on('exit', () => {
        if (enfant === lance) {
          enfant = null
          prochainEssai = Date.now() + attente
          attente = Math.min(attente * 2, 30000)
        }
      })
    }
    minuterie = setTimeout(verifier, intervalleMs)
    minuterie.unref?.()
  }

  verifier()
  return {
    port: async () => (await lireReglagesApplication(fichier))?.port ?? null,
    /** Temps écoulé depuis le dernier lancement du site (en millisecondes), ou Infinity s'il n'a jamais été lancé. */
    depuisLancement: () => (lanceLe ? Date.now() - lanceLe : Infinity),
    arreter() {
      arrete = true
      clearTimeout(minuterie)
      arreterEnfant()
    },
  }
}

export function creerServeurApercu({ dossier = '/home/user/projet', intervalleMs = 700, projet, cle, maintenant = () => Date.now(), fichierApplication = null, dureeRedemarrageMs = DUREE_REDEMARRAGE_MS } = {}) {
  const racine = path.resolve(dossier)
  const clients = new Set()
  let derniere = null
  let minuterie = null
  let lanceLe = 0

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

  // ------------------------------------------------------------------------------------------------
  // Mode application web
  const application = fichierApplication ? superviserApplication({ fichier: fichierApplication, dossier: racine }) : null

  /** Jeton de l'aperçu porté par le cookie, s'il est encore valable. */
  function jetonDuCookie(req) {
    const jeton = lireCookie(req.headers.cookie, COOKIE_APERCU)
    return jeton && jetonValide(cle, projet, jeton, maintenant()) ? jeton : null
  }

  function ouvrirSuivi(req, res) {
    res.writeHead(200, { 'Content-Type': 'text/event-stream', 'Cache-Control': 'no-store', Connection: 'keep-alive' })
    res.write(': kekeli\n\n')
    clients.add(res)
    const battement = setInterval(() => res.write(': battement\n\n'), 20000)
    req.on('close', () => {
      clearInterval(battement)
      clients.delete(res)
    })
  }

  /** En-têtes envoyés à l'application : ceux du navigateur, sans le cookie de l'aperçu, avec l'origine HTTPS. */
  function entetesVersApplication(req) {
    const entetes = { ...req.headers }
    const cookie = sansCookieApercu(req.headers.cookie)
    if (cookie) entetes.cookie = cookie
    else delete entetes.cookie
    delete entetes['accept-encoding']
    entetes['x-forwarded-proto'] = String(req.headers['x-forwarded-proto'] ?? 'https').split(',')[0]
    entetes['x-forwarded-host'] = req.headers['x-forwarded-host'] ?? req.headers.host ?? ''
    entetes['x-forwarded-for'] = req.headers['x-forwarded-for'] ?? req.socket.remoteAddress ?? ''
    return entetes
  }

  async function servirApplication(req, res, url) {
    // Le lien privé dépose le cookie de l'aperçu, puis renvoie vers l'adresse normale de l'application.
    const acces = jetonDuChemin(url.pathname)
    if (acces) {
      res.writeHead(302, {
        'Set-Cookie': `${COOKIE_APERCU}=${acces.jeton}; Path=/; HttpOnly; Secure; SameSite=None; Partitioned`,
        Location: (acces.reste || '/') + url.search,
        'Cache-Control': 'no-store',
      })
      return res.end()
    }
    if (!jetonDuCookie(req)) return envoyer(res, 403, 'text/html; charset=utf-8', PAGE_PRIVEE)
    if (url.pathname === CHEMIN_DIRECT) return ouvrirSuivi(req, res)

    const port = await application.port()
    if (!port) return envoyer(res, 503, 'text/html; charset=utf-8', pageApplicationDemarre(true))

    const vers = http.request({ host: '127.0.0.1', port, method: req.method, path: req.url, headers: entetesVersApplication(req) }, (reponse) => {
      const entetes = { ...reponse.headers }
      if (entetes['set-cookie']) entetes['set-cookie'] = [].concat(entetes['set-cookie']).map(adapterCookie)
      const html = String(entetes['content-type'] ?? '').includes('text/html') && !entetes['content-encoding'] && req.method === 'GET'
      // Erreur du serveur sur une page : jamais la page technique (souvent en anglais) devant le client.
      if (req.method === 'GET' && (reponse.statusCode ?? 200) >= 500 && (html || !entetes['content-type'] || String(entetes['content-type']).includes('text/plain'))) {
        reponse.resume()
        return application.depuisLancement() < dureeRedemarrageMs
          ? envoyer(res, 503, 'text/html; charset=utf-8', pageApplicationDemarre(false))
          : envoyer(res, 500, 'text/html; charset=utf-8', ajouterScript(PAGE_ERREUR_SITE, '/'))
      }
      if (!html) {
        res.writeHead(reponse.statusCode ?? 502, entetes)
        return reponse.pipe(res)
      }
      // Pages HTML : le petit script de rechargement est ajouté (la page se met à jour quand Kekeli travaille).
      const morceaux = []
      reponse.on('data', (m) => morceaux.push(m))
      reponse.on('end', () => {
        const corps = Buffer.from(ajouterScript(Buffer.concat(morceaux).toString('utf8'), '/'))
        delete entetes['content-length']
        delete entetes['transfer-encoding']
        res.writeHead(reponse.statusCode ?? 200, { ...entetes, 'content-length': corps.length })
        res.end(corps)
      })
    })
    vers.on('error', () => {
      if (!res.headersSent) envoyer(res, 503, 'text/html; charset=utf-8', pageApplicationDemarre(false))
      else res.end()
    })
    req.pipe(vers)
  }

  const serveur = http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url ?? '/', 'http://apercu')

      if (url.pathname === CHEMIN_SANTE) return envoyer(res, 200, 'application/json; charset=utf-8', '{"ok":true}')
      if (application) return await servirApplication(req, res, url)

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

  // Connexions en direct de l'application (par exemple le rechargement à chaud de Next.js).
  if (application) {
    serveur.on('upgrade', async (req, socket, tete) => {
      const port = jetonDuCookie(req) ? await application.port() : null
      if (!port) return socket.destroy()
      const vers = net.connect(port, '127.0.0.1', () => {
        const entetes = entetesVersApplication(req)
        entetes.connection = 'Upgrade'
        entetes.upgrade = req.headers.upgrade
        vers.write(`${req.method} ${req.url} HTTP/1.1\r\n` + Object.entries(entetes).map(([n, v]) => `${n}: ${v}`).join('\r\n') + '\r\n\r\n')
        if (tete?.length) vers.write(tete)
        vers.pipe(socket)
        socket.pipe(vers)
      })
      vers.on('error', () => socket.destroy())
      socket.on('error', () => vers.destroy())
    })
  }

  surveiller()
  serveur.on('close', () => {
    application?.arreter()
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
    fichierApplication: process.env.APERCU_APPLICATION || null,
  }).listen(port, '0.0.0.0', () => {
    console.log(`Aperçu de Kekeli prêt sur le port ${port}.`)
  })
}
