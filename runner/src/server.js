// Serveur HTTP du Runner de Kekeli.
//
//   GET  /sante    : le Runner fonctionne-t-il ? (sans secret)
//   GET  /etat     : fournisseurs dont la clé est présente et fournisseurs mis de côté (signé)
//   POST /taches   : nouvelle tâche envoyée par le site Kekeli (signé) ; réponse immédiate 202,
//                    puis les étapes sont renvoyées au site au fur et à mesure.
//   POST /permissions : réponse de l'utilisateur à une demande de permission (signé)
//   POST /apercus  : réveille l'ordinateur d'un projet pour revoir l'aperçu, sans demande (signé)
//   POST /projets/effacer : efface pour de bon les ordinateurs d'un projet supprimé (signé)
//   POST /publier  : publie la dernière sauvegarde du projet sur Cloudflare Pages (signé) ; réponse quand le site est en ligne
//   POST /domaine  : ajoute, vérifie ou retire le nom de domaine du client pour son site publié (signé)
import http from 'node:http'
import { pathToFileURL } from 'node:url'
import { verifier } from './signature.js'
import { fournisseursAvecCle, numeroCompteCloudflare, valeurCle } from './fournisseurs.js'
import { MisesDeCote } from './limites.js'
import { executerTache } from './agent.js'
import { creerEnvoyeur } from './rappels.js'
import { ACTIONS, DOMAINE, gererDomaine } from './domaines.js'
import { fichiersDeLaSauvegarde, publier } from './publication.js'
import { telecharger } from './sauvegarde.js'

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

const PROJET = /^[A-Za-z0-9_-]{1,64}$/

/** Demandes de permission en attente d'une réponse de l'utilisateur (refus automatique au bout du délai). */
export function creerRegistrePermissions({ delaiMs = 10 * 60 * 1000 } = {}) {
  const attentes = new Map()
  return {
    attendre(projet, id) {
      return new Promise((resolve) => {
        const cle = `${projet}:${id}`
        const minuterie = setTimeout(() => {
          attentes.delete(cle)
          resolve('expiree')
        }, delaiMs)
        attentes.set(cle, (decision) => {
          clearTimeout(minuterie)
          attentes.delete(cle)
          resolve(decision)
        })
      })
    },
    decider(projet, id, decision) {
      const terminer = attentes.get(`${projet}:${id}`)
      if (!terminer) return false
      terminer(decision === 'autoriser' ? 'autoriser' : 'refuser')
      return true
    },
  }
}

/** Publication par défaut : sauvegarde téléchargée depuis le site Kekeli, puis envoyée à Cloudflare Pages. */
function publicationParDefaut(secret) {
  return async ({ nom, sauvegarde }) => {
    const octets = await telecharger(sauvegarde, secret)
    if (!octets) throw new Error("Le projet n'a pas encore de sauvegarde à publier.")
    return publier({ fichiers: await fichiersDeLaSauvegarde(octets), nom, compte: numeroCompteCloudflare(), jeton: valeurCle('CLOUDFLARE_API_TOKEN') })
  }
}

export function creerServeur({
  secret,
  clesPresentes,
  misesDeCote = new MisesDeCote(),
  ordinateurs,
  permissions = creerRegistrePermissions(),
  executer = executerTache,
  creerEnvoi = creerEnvoyeur,
  publierProjet = publicationParDefaut(secret),
  domaineProjet = ({ action, nom, domaine }) => gererDomaine({ action, nom, domaine, compte: numeroCompteCloudflare(), jeton: valeurCle('CLOUDFLARE_API_TOKEN') }),
  journal = console,
}) {
  // Une seule tâche à la fois par projet : les suivantes attendent leur tour.
  const filesParProjet = new Map()
  const enFile = (projet, travail) => {
    const precedente = filesParProjet.get(projet) ?? Promise.resolve()
    const suivante = precedente.then(travail).catch((erreur) => journal.error(erreur))
    filesParProjet.set(projet, suivante)
    suivante.finally(() => {
      if (filesParProjet.get(projet) === suivante) filesParProjet.delete(projet)
    })
  }
  const lireJson = (corps) => {
    try {
      return JSON.parse(corps)
    } catch {
      return null
    }
  }

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
        enFile(demande.projet, () => executer(demande, { ordinateurs, envoyer, misesDeCote, clesPresentes, permissions, journal }))
        return repondre(res, 202, { accepte: true })
      }

      if (req.method === 'POST' && url.pathname === '/permissions') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !PROJET.test(String(d.projet)) || typeof d.permission !== 'string') return repondre(res, 422, { erreur: 'demande incomplète' })
        const trouvee = permissions.decider(d.projet, d.permission, d.decision)
        return repondre(res, trouvee ? 200 : 404, { ok: trouvee })
      }

      if (req.method === 'POST' && url.pathname === '/apercus') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !PROJET.test(String(d.projet)) || !/^https?:\/\//.test(String(d.rappel))) return repondre(res, 422, { erreur: 'demande incomplète' })
        const envoyer = creerEnvoi({ url: d.rappel, secret, tache: null, journal })
        enFile(d.projet, async () => {
          try {
            const ordinateur = await ordinateurs.obtenir(d.projet, {
              sites: d.sites,
              sauvegarde: d.sauvegarde?.telechargement,
              dureeVieMinutes: d.duree_vie_minutes,
            })
            if (!ordinateur.apercu?.url) throw new Error("le serveur d'aperçu ne répond pas")
            await envoyer({ type: 'apercu', url: ordinateur.apercu.url, expire_a: ordinateur.expireA?.toISOString() })
          } catch (erreur) {
            journal.error(`Réveil de l'aperçu du projet ${d.projet} : ${erreur.message}`)
            await envoyer({ type: 'apercu_impossible' })
          }
        })
        return repondre(res, 202, { accepte: true })
      }

      // Publication du projet sur Cloudflare Pages, à partir de sa dernière sauvegarde (réponse quand c'est en ligne).
      if (req.method === 'POST' && url.pathname === '/publier') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !PROJET.test(String(d.projet)) || !/^[a-z0-9][a-z0-9-]{0,57}$/.test(String(d.nom)) || !/^https?:\/\//.test(String(d.sauvegarde))) {
          return repondre(res, 422, { erreur: 'demande incomplète' })
        }
        if (!clesPresentes.has('cloudflare')) return repondre(res, 503, { erreur: 'cloudflare_absent' })
        const resultat = await new Promise((resolve) => {
          enFile(d.projet, async () => {
            try {
              resolve({ ok: true, ...(await publierProjet({ projet: d.projet, nom: d.nom, sauvegarde: d.sauvegarde })) })
            } catch (erreur) {
              journal.error(`Publication du projet ${d.projet} : ${erreur.message}`)
              resolve({ ok: false, erreur: erreur.message })
            }
          })
        })
        return repondre(res, resultat.ok ? 200 : 502, resultat)
      }

      // Nom de domaine du client pour son site publié (formules payantes ; le site Kekeli le vérifie).
      if (req.method === 'POST' && url.pathname === '/domaine') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !ACTIONS.has(d.action) || !/^[a-z0-9][a-z0-9-]{0,57}$/.test(String(d.nom)) || !DOMAINE.test(String(d.domaine))) {
          return repondre(res, 422, { erreur: 'demande incomplète' })
        }
        if (!clesPresentes.has('cloudflare')) return repondre(res, 503, { erreur: 'cloudflare_absent' })
        try {
          return repondre(res, 200, { ok: true, ...(await domaineProjet({ action: d.action, nom: d.nom, domaine: d.domaine })) })
        } catch (erreur) {
          journal.error(`Nom de domaine ${d.domaine} : ${erreur.message}`)
          return repondre(res, 502, { ok: false, erreur: erreur.message })
        }
      }

      if (req.method === 'POST' && url.pathname === '/projets/effacer') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !PROJET.test(String(d.projet))) return repondre(res, 422, { erreur: 'demande incomplète' })
        const effaces = await ordinateurs.effacer(d.projet)
        return repondre(res, 200, { effaces })
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
