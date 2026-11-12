// Relais OpenAI dans l'ordinateur virtuel (copié dans /home/user/.kekeli/) : il ne contient AUCUNE clé.
// OpenCode lui envoie ses demandes (adresse http://127.0.0.1:4200/v1) avec le jeton du projet ; le relais les garde
// en attente. Le Runner de Kekeli vient les chercher (adresse /relais/attente, avec le jeton du relais), les envoie
// lui-même à OpenAI avec sa clé, puis dépose la réponse (/relais/reponse/<numéro>) que le relais rend à OpenCode.
import http from 'node:http'
import { randomUUID, timingSafeEqual } from 'node:crypto'

const PORT = Number(process.env.RELAIS_PORT ?? 4200)
const JETON = String(process.env.RELAIS_JETON ?? '')
const TAILLE_MAXIMALE = 8 * 1024 * 1024
const ATTENTE_MS = 25000
const DELAI_REPONSE_MS = 10 * 60 * 1000

const enAttente = [] // demandes d'OpenCode pas encore prises par le Runner
const enCours = new Map() // numéro → réponse HTTP d'OpenCode
const attenteRunner = [] // le Runner attend une demande

const memeJeton = (a) => {
  const x = Buffer.from(String(a ?? ''))
  const y = Buffer.from(JETON)
  return JETON.length > 20 && x.length === y.length && timingSafeEqual(x, y)
}

const lire = (req) =>
  new Promise((resolve, reject) => {
    let corps = ''
    req.setEncoding('utf8')
    req.on('data', (m) => {
      corps += m
      if (corps.length > TAILLE_MAXIMALE) {
        reject(new Error('trop grand'))
        req.destroy()
      }
    })
    req.on('end', () => resolve(corps))
    req.on('error', reject)
  })

const json = (res, statut, donnees) => {
  res.writeHead(statut, { 'content-type': 'application/json' })
  res.end(JSON.stringify(donnees))
}

function donnerAuRunner() {
  while (enAttente.length && attenteRunner.length) attenteRunner.shift()(enAttente.shift())
}

http
  .createServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://relais')
      // Côté OpenCode : une demande au modèle, gardée jusqu'à la réponse du Runner.
      const m = /^\/v1\/(responses|chat\/completions)$/.exec(url.pathname)
      if (req.method === 'POST' && m) {
        const corps = await lire(req)
        const id = randomUUID()
        const minuterie = setTimeout(() => {
          enCours.delete(id)
          json(res, 504, { error: { message: 'Pas de réponse du Runner.' } })
        }, DELAI_REPONSE_MS)
        enCours.set(id, { res, minuterie })
        enAttente.push({ id, chemin: m[1], autorisation: String(req.headers.authorization ?? ''), corps })
        donnerAuRunner()
        return
      }
      // Côté Runner : toujours avec le jeton du relais.
      if (url.pathname.startsWith('/relais/')) {
        if (!memeJeton(req.headers['x-kekeli-relais'])) return json(res, 401, { erreur: 'jeton invalide' })
        if (req.method === 'GET' && url.pathname === '/relais/attente') {
          const demande = await new Promise((resolve) => {
            attenteRunner.push(resolve)
            donnerAuRunner()
            setTimeout(() => {
              const i = attenteRunner.indexOf(resolve)
              if (i >= 0) attenteRunner.splice(i, 1)
              resolve(null)
            }, ATTENTE_MS)
          })
          if (!demande) {
            res.writeHead(204)
            return res.end()
          }
          return json(res, 200, demande)
        }
        const r = /^\/relais\/reponse\/([0-9a-f-]{36})$/.exec(url.pathname)
        if (req.method === 'POST' && r) {
          const attente = enCours.get(r[1])
          if (!attente) return json(res, 404, { erreur: 'demande inconnue' })
          const reponse = JSON.parse(await lire(req))
          enCours.delete(r[1])
          clearTimeout(attente.minuterie)
          attente.res.writeHead(Number(reponse.statut) || 502, { 'content-type': String(reponse.type || 'application/json') })
          attente.res.end(String(reponse.corps ?? ''))
          return json(res, 200, { ok: true })
        }
      }
      json(res, 404, { erreur: 'adresse inconnue' })
    } catch (erreur) {
      if (!res.headersSent) json(res, 500, { erreur: erreur.message })
    }
  })
  .listen(PORT, '0.0.0.0')
