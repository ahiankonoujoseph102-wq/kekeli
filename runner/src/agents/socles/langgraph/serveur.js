// Serveur de l'agent : la page de discussion, la bulle à intégrer sur un site, et l'adresse /api/message.
// La clé du modèle reste ici, sur le serveur : le navigateur ne la voit jamais.
import http from 'node:http'
import { readFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { lireReglages } from './agent/config.js'
import { creerAgent, modeleDepuisReglages } from './agent/agent.js'

const RACINE = path.dirname(fileURLToPath(import.meta.url))
export const MESSAGE_MAX = 2000
const CONVERSATION = /^[A-Za-z0-9_-]{8,64}$/

/** Limite de messages par minute pour chaque personne (adresse du visiteur). */
export function creerLimiteur(parMinute, maintenant = () => Date.now()) {
  const vus = new Map()
  return (cle) => {
    const t = maintenant()
    const recents = (vus.get(cle) ?? []).filter((moment) => t - moment < 60000)
    if (recents.length >= parMinute) {
      vus.set(cle, recents)
      return false
    }
    recents.push(t)
    vus.set(cle, recents)
    if (vus.size > 10000) vus.delete(vus.keys().next().value)
    return true
  }
}

const lireCorps = (req) =>
  new Promise((resolve, reject) => {
    let corps = ''
    req.setEncoding('utf8')
    req.on('data', (morceau) => {
      corps += morceau
      if (corps.length > 20000) reject(new Error('trop grand'))
    })
    req.on('end', () => resolve(corps))
    req.on('error', reject)
  })

const json = (res, statut, donnees) => {
  res.writeHead(statut, { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'no-store' })
  res.end(JSON.stringify(donnees))
}

/** Message d'erreur simple pour le visiteur, sans détail technique (le détail reste dans le journal du serveur). */
export function messageErreur(erreur) {
  const statut = erreur?.status ?? erreur?.response?.status
  const texte = String(erreur?.error?.message ?? erreur?.message ?? '')
  // Un service du modèle peut donner une phrase claire pour le visiteur (par exemple : essai gratuit terminé).
  const phrase = /essai gratuit|variables secrètes/i.exec(texte) ? texte.replace(/^\d{3}\s*/, '').trim() : null
  if (phrase) return { statut: 429, erreur: phrase }
  if (statut === 429) return { statut: 429, erreur: "L'assistant reçoit beaucoup de messages. Réessayez dans un instant." }
  if (statut === 401 || statut === 403) return { statut: 503, erreur: "L'assistant n'est pas encore branché à son modèle. Revenez un peu plus tard." }
  return { statut: 502, erreur: "L'assistant n'a pas pu répondre. Réessayez dans un instant." }
}

export function creerServeur({ reglages = lireReglages(), agent = null, journal = console } = {}) {
  const limiter = creerLimiteur(reglages.messagesParMinute)
  const pret = Boolean(agent) || Boolean(reglages.cleModele && reglages.nomModele)
  agent ??= pret ? creerAgent({ modele: modeleDepuisReglages(reglages), reglages }) : null
  const page = readFileSync(path.join(RACINE, 'public', 'index.html'), 'utf8')
  const bulle = readFileSync(path.join(RACINE, 'public', 'bulle.js'), 'utf8')
  // La bulle peut être affichée par les sites de ORIGINES_AUTORISEES (vide : par n'importe quel site).
  const ancetres = reglages.originesAutorisees.length ? { 'content-security-policy': `frame-ancestors 'self' ${reglages.originesAutorisees.join(' ')}` } : {}

  return http.createServer(async (req, res) => {
    const url = new URL(req.url, 'http://agent')
    try {
      if (req.method === 'GET' && (url.pathname === '/' || url.pathname === '/index.html')) {
        res.writeHead(200, { 'content-type': 'text/html; charset=utf-8', 'x-content-type-options': 'nosniff', ...ancetres })
        return res.end(page)
      }
      if (req.method === 'GET' && url.pathname === '/bulle.js') {
        res.writeHead(200, { 'content-type': 'text/javascript; charset=utf-8', 'cache-control': 'public, max-age=300' })
        return res.end(bulle)
      }
      if (req.method === 'GET' && url.pathname === '/sante') return json(res, 200, { ok: true, pret })
      if (req.method === 'POST' && url.pathname === '/api/message') {
        const visiteur = String(req.headers['x-forwarded-for'] ?? '').split(',')[0].trim() || req.socket.remoteAddress || 'inconnu'
        if (!limiter(visiteur)) return json(res, 429, { erreur: 'Vous envoyez beaucoup de messages. Attendez une minute, puis réessayez.' })
        let demande
        try {
          demande = JSON.parse(await lireCorps(req))
        } catch {
          return json(res, 400, { erreur: 'Message illisible.' })
        }
        const message = String(demande?.message ?? '').trim()
        if (!CONVERSATION.test(String(demande?.conversation ?? ''))) return json(res, 400, { erreur: 'Conversation inconnue.' })
        if (!message) return json(res, 400, { erreur: 'Le message est vide.' })
        if (message.length > MESSAGE_MAX) return json(res, 400, { erreur: `Le message est trop long (${MESSAGE_MAX} caractères au plus).` })
        if (!agent) return json(res, 503, { erreur: "L'assistant n'est pas encore branché à son modèle. Revenez un peu plus tard." })
        try {
          return json(res, 200, { reponse: await agent.repondre(demande.conversation, message) })
        } catch (erreur) {
          journal.error(`Réponse impossible : ${erreur.message}`)
          const { statut, erreur: texte } = messageErreur(erreur)
          return json(res, statut, { erreur: texte })
        }
      }
      json(res, 404, { erreur: 'Adresse inconnue.' })
    } catch (erreur) {
      journal.error(erreur)
      if (!res.headersSent) json(res, 500, { erreur: "L'assistant n'a pas pu répondre." })
    }
  })
}

// Lancement : node serveur.js (les réglages viennent du fichier .env, lu par Node 22 avec --env-file si présent).
if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const reglages = lireReglages()
  creerServeur({ reglages }).listen(reglages.port, reglages.hote, () => console.log(`Agent prêt sur http://${reglages.hote}:${reglages.port}`))
}
