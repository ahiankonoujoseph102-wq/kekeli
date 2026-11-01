// Serveur HTTP du Runner de Kekeli.
//
//   GET  /sante    : le Runner fonctionne-t-il ? (sans secret)
//   GET  /etat     : fournisseurs dont la clé est présente et fournisseurs mis de côté (signé)
//   POST /taches   : nouvelle tâche envoyée par le site Kekeli (signé) ; réponse immédiate 202,
//                    puis les étapes sont renvoyées au site au fur et à mesure.
import http from 'node:http'
import { pathToFileURL } from 'node:url'
import { verifier } from './signature.js'
import { fournisseursAvecCle } from './fournisseurs.js'
import { MisesDeCote } from './limites.js'
import { executerTache } from './agent.js'
import { creerEnvoyeur } from './rappels.js'

const TAILLE_MAXIMALE = 256 * 1024

function repondre(res, code, donnees) {
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8' })
  res.end(JSON.stringify(donnees))
}

function lireCorps(req) {
  return new Promise((resolve, reject) => {
    let corps = ''
    req.setEncoding('utf8')
    req.on('data', (morceau) => {
      corps += morceau
      if (corps.length > TAILLE_MAXIMALE) {
        reject(new Error('trop grand'))
        req.destroy()
      }
    })
    req.on('end', () => resolve(corps))
    req.on('error', reject)
  })
}

export function demandeValide(demande) {
  return Boolean(
    demande &&
      typeof demande.tache === 'string' &&
      typeof demande.projet === 'string' &&
      /^[A-Za-z0-9_-]{1,64}$/.test(demande.projet) &&
      typeof demande.message === 'string' &&
      demande.message.length > 0 &&
      typeof demande.rappel === 'string' &&
      /^https?:\/\//.test(demande.rappel) &&
      Array.isArray(demande.fournisseurs),
  )
}

export function creerServeur({ secret, clesPresentes, misesDeCote = new MisesDeCote(), ordinateurs, executer = executerTache, creerEnvoi = creerEnvoyeur, journal = console }) {
  // Une seule tâche à la fois par projet : les suivantes attendent leur tour.
  const filesParProjet = new Map()

  return http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://runner')
      const corps = req.method === 'POST' ? await lireCorps(req) : ''
      const signe = () => verifier(secret, req.headers['x-kekeli-horodatage'], req.headers['x-kekeli-signature'], corps)

      if (req.method === 'GET' && url.pathname === '/sante') return repondre(res, 200, { ok: true })

      if (req.method === 'GET' && url.pathname === '/etat') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        return repondre(res, 200, { cles_presentes: [...clesPresentes], mis_de_cote: misesDeCote.etat() })
      }

      if (req.method === 'POST' && url.pathname === '/taches') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        let demande
        try {
          demande = JSON.parse(corps)
        } catch {
          return repondre(res, 422, { erreur: 'corps illisible' })
        }
        if (!demandeValide(demande)) return repondre(res, 422, { erreur: 'demande incomplète' })

        const envoyer = creerEnvoi({ url: demande.rappel, secret, tache: demande.tache, journal })
        const precedente = filesParProjet.get(demande.projet) ?? Promise.resolve()
        const suivante = precedente
          .then(() => executer(demande, { ordinateurs, envoyer, misesDeCote, clesPresentes, journal }))
          .catch((erreur) => journal.error(erreur))
        filesParProjet.set(demande.projet, suivante)
        suivante.finally(() => {
          if (filesParProjet.get(demande.projet) === suivante) filesParProjet.delete(demande.projet)
        })
        return repondre(res, 202, { accepte: true })
      }

      return repondre(res, 404, { erreur: 'introuvable' })
    } catch (erreur) {
      journal.error(erreur)
      return repondre(res, 400, { erreur: 'requête invalide' })
    }
  })
}

async function demarrer() {
  const secret = process.env.KEKELI_RUNNER_SECRET
  if (!secret || secret.length < 32) {
    console.error('La variable KEKELI_RUNNER_SECRET est absente ou trop courte (au moins 32 caractères).')
    process.exit(1)
  }
  if (!process.env.E2B_API_KEY) {
    console.error('La variable E2B_API_KEY est absente.')
    process.exit(1)
  }
  const { creerGestionnaireOrdinateurs, synchroniserSecrets } = await import('./ordinateurs.js')
  const clesPresentes = fournisseursAvecCle()
  await synchroniserSecrets(clesPresentes)
  const ordinateurs = creerGestionnaireOrdinateurs({ secretRunner: secret, clesPresentes })
  const port = Number(process.env.PORT ?? 3100)
  const hote = process.env.KEKELI_RUNNER_HOTE ?? '127.0.0.1'
  creerServeur({ secret, clesPresentes, ordinateurs }).listen(port, hote, () => {
    console.log(`Runner de Kekeli prêt sur ${hote}:${port}. Fournisseurs avec clé : ${[...clesPresentes].join(', ') || 'aucun'}.`)
  })
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) demarrer()
