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
//   POST /passerelle/<projet>/openai/v1/… : passerelle OpenAI de l'agent (jeton du projet ; la clé reste ici)
//   POST /transcrire : transcrit une courte dictée vocale du client avec OpenAI (signé), quand son navigateur ne sait pas le faire
//   POST /domaine  : ajoute, vérifie ou retire le nom de domaine du client pour son site publié (signé)
import { ACTIONS_VERSION } from './versions.js'
import { echangerCode, preparerConnexion, renouvelerJeton, serveurPermis, sonderServeur } from './connecteurs.js'
import { applicationValide } from './application.js'
import { estAutomatisation } from './applications/index.js'
import { lancerAutomatisation } from './lancement.js'
import { creerAtelier, demandeDocumentValide, produireDocument } from './documents/index.js'
import { creerAtelierVideo } from './videos/index.js'
import { Sandbox } from 'e2b'
import { variablesPermises } from './apercu/serveur.mjs'
import http from 'node:http'
import { pathToFileURL } from 'node:url'
import { verifier } from './signature.js'
import { fournisseursAvecCle, numeroCompteCloudflare, valeurCle } from './fournisseurs.js'
import { MisesDeCote } from './limites.js'
import { LIEUX, premierRepondant } from './repondant.js'
import { executerTache, memoireModeles } from './agent.js'
import { creerEnvoyeur } from './rappels.js'
import { ACTIONS, DOMAINE, gererDomaine } from './domaines.js'
import { fichiersDeLaSauvegarde, publier } from './publication.js'
import { telecharger, telechargerFichier } from './sauvegarde.js'
import { AUDIO_MAX_OCTETS, transcrire } from './transcription.js'
import { TAILLE_MAXIMALE_PASSERELLE, lireCheminPasserelle, passerOpenAI } from './passerelle.js'
import { Readable } from 'node:stream'

const TAILLE_MAXIMALE = 256 * 1024
// Une dictée vocale (enregistrement en base 64) peut être plus grande que les autres demandes.
const TAILLE_MAXIMALE_DICTEE = Math.ceil(AUDIO_MAX_OCTETS * 1.4) + 4096

function repondre(res, code, donnees) {
  res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8' })
  res.end(JSON.stringify(donnees))
}

function lireCorps(req, maximum = TAILLE_MAXIMALE) {
  return new Promise((resolve, reject) => {
    let corps = ''
    req.setEncoding('utf8')
    req.on('data', (morceau) => {
      corps += morceau
      if (corps.length > maximum) {
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
  repondant = (demande, options = {}) => premierRepondant(demande, { clesPresentes, misesDeCote, ...options }),
  transcrireDictee = (demande) => transcrire(demande),
  domaineProjet = ({ action, nom, domaine }) => gererDomaine({ action, nom, domaine, compte: numeroCompteCloudflare(), jeton: valeurCle('CLOUDFLARE_API_TOKEN') }),
  journal = console,
  atelier = creerAtelier({ Sandbox, journal }),
  atelierVideo = creerAtelierVideo({ Sandbox, journal }),
  produire = produireDocument,
  connexionConnecteur = (action, d) => ({
    sonder: () => sonderServeur(d.url),
    preparer: () => preparerConnexion({ url: d.url, redirection: d.redirection, etat: d.etat, client: d.client ?? null }),
    echanger: () => echangerCode({ url: d.url, client: d.client, code: d.code, verificateur: d.verificateur, redirection: d.redirection, ressource: d.ressource, iss: d.iss }),
    renouveler: () => renouvelerJeton({ url: d.url, client: d.client, renouvellement: d.renouvellement, ressource: d.ressource }),
  })[action](),
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

  const serveur = http.createServer(async (req, res) => {
    try {
      const url = new URL(req.url, 'http://runner')
      const passerelle = lireCheminPasserelle(url.pathname)
      const corps = req.method === 'POST' ? await lireCorps(req, passerelle ? TAILLE_MAXIMALE_PASSERELLE : url.pathname === '/transcrire' ? TAILLE_MAXIMALE_DICTEE : TAILLE_MAXIMALE) : ''
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
        enFile(demande.projet, () => executer(demande, { ordinateurs, envoyer, misesDeCote, clesPresentes, permissions, journal, memoire: memoireModeles }))
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
              // Application ou agent IA : relancé dans l'aperçu ; agent : essai gratuit suivi par le Runner.
              application: applicationValide(d.application) ? d.application : null,
              essaiAgent: typeof d.essai_agent === 'string' ? d.essai_agent : null,
            })
            // Versions : les fichiers d'une version remis dans le projet (voir, revenir, restaurer), sans aucun modèle.
            const version = d.version && ACTIONS_VERSION.includes(d.version.action) ? d.version : null
            if (version) {
              const octets = version.action === 'revenir' ? null : await telechargerFichier(String(version.archive), secret)
              await ordinateurs.appliquerVersion(d.projet, version.action, octets)
              // Une restauration (ou un retour après un échec) devient la nouvelle sauvegarde du projet.
              if (version.action === 'remettre' && d.sauvegarde?.envoi) await ordinateurs.sauvegarder(d.projet, d.sauvegarde.envoi)
              await envoyer({ type: 'version_appliquee', action: version.action, numero: version.numero ?? null })
            }
            if (!ordinateur.apercu?.url) throw new Error("le serveur d'aperçu ne répond pas")
            await envoyer({ type: 'apercu', url: ordinateur.apercu.url, expire_a: ordinateur.expireA?.toISOString() })
          } catch (erreur) {
            journal.error(`Réveil de l'aperçu du projet ${d.projet} : ${erreur.message}`)
            await envoyer({ type: 'apercu_impossible' })
          }
        })
        return repondre(res, 202, { accepte: true })
      }

      // « Lancer maintenant » : l'automatisation du client tourne une fois dans son ordinateur, avec un journal lisible.
      if (req.method === 'POST' && url.pathname === '/lancer') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !PROJET.test(String(d.projet)) || typeof d.tache !== 'string' || !/^https?:\/\//.test(String(d.rappel)) || !applicationValide(d.application) || !estAutomatisation(d.application.pile)) {
          return repondre(res, 422, { erreur: 'demande incomplète' })
        }
        const envoyer = creerEnvoi({ url: d.rappel, secret, tache: d.tache, journal })
        enFile(d.projet, async () => {
          try {
            await envoyer({ type: 'demarrage' })
            await ordinateurs.obtenir(d.projet, {
              sites: d.sites,
              sauvegarde: d.sauvegarde?.telechargement,
              dureeVieMinutes: d.duree_vie_minutes,
              application: d.application,
              essaiAgent: typeof d.essai_agent === 'string' ? d.essai_agent : null,
            })
            await ordinateurs.preparerApplication(d.projet, d.application.pile)
            await lancerAutomatisation({
              executer: (commande, options) => ordinateurs.executerDansProjet(d.projet, commande, options),
              lireLancement: () => ordinateurs.lireFichierProjet(d.projet, 'LANCEMENT'),
              secrets: variablesPermises(d.secrets ?? {}),
              essai: ordinateurs.reglagesEssai(d.projet),
              envoyer,
            })
          } catch (erreur) {
            journal.error(`Lancement de l'automatisation du projet ${d.projet} : ${erreur.message}`)
            await envoyer({ type: 'lancement', ok: false, resume: "Kekeli n'a pas pu préparer l'ordinateur pour ce lancement. Réessayez dans un instant.", lignes: [] })
          }
        })
        return repondre(res, 202, { accepte: true })
      }

      // Documents et présentations : rédaction par le modèle (ici), mise en page dans un ordinateur isolé.
      if (req.method === 'POST' && url.pathname === '/documents') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !PROJET.test(String(d.projet)) || typeof d.tache !== 'string' || !/^https?:\/\//.test(String(d.rappel)) || !/^https?:\/\//.test(String(d.envoi)) || !demandeDocumentValide(d)) {
          return repondre(res, 422, { erreur: 'demande incomplète' })
        }
        const envoyer = creerEnvoi({ url: d.rappel, secret, tache: d.tache, journal })
        enFile(d.projet, async () => {
          try {
            await produire(d, { atelier, atelierVideo, envoyer, secret, clesPresentes, misesDeCote, journal })
          } catch (erreur) {
            journal.error(`Document du projet ${d.projet} : ${erreur.message}`)
            await envoyer({ type: 'erreur', raison: erreur.raison ?? 'document', detail: String(erreur.message).slice(0, 300) })
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

      // Premier répondant : chaque message du client va d'abord au modèle, qui répond ou demande un outil. Sans ordinateur.
      if (req.method === 'POST' && url.pathname === '/repondre') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || !PROJET.test(String(d.projet)) || !LIEUX.includes(d.lieu) || typeof d.message !== 'string' || !d.message.trim() || d.message.length > 4000) {
          return repondre(res, 422, { erreur: 'demande incomplète' })
        }
        // Pendant une recherche sur Internet, le site affiche « Recherche sur le Web… » (événement signé, comme les autres).
        const envoyer = typeof d.rappel === 'string' && /^https?:\/\//.test(d.rappel) && typeof d.tache === 'string'
          ? creerEnvoi({ url: d.rappel, secret, tache: d.tache, journal })
          : null
        const surRecherche = envoyer ? (requete) => envoyer({ type: 'recherche', requete: String(requete).slice(0, 300) }) : null
        const recherche = { permise: d.recherche?.permise === true, restantes: Number(d.recherche?.restantes) || 0 }
        const lecture = typeof d.lecture === 'string' && /^https?:\/\//.test(d.lecture) ? d.lecture : null
        // Les connecteurs actifs du client (adresse, nom, jeton d'accès de courte durée) et l'action qu'il a autorisée.
        const connecteurs = Array.isArray(d.connecteurs) ? d.connecteurs.filter((c) => c && typeof c.url === 'string' && typeof c.nom === 'string').slice(0, 8) : []
        const accord = d.accord && typeof d.accord === 'object' && typeof d.accord.outil === 'string' ? d.accord : null
        const surConnecteur = envoyer ? ({ connecteur }) => envoyer({ type: 'connecteur', nom: String(connecteur).slice(0, 60) }) : null
        const resultat = await repondant({ lieu: d.lieu, message: d.message, contexte: d.contexte ?? {}, historique: d.historique ?? [], fournisseurs: d.fournisseurs ?? [], recherche, lecture, connecteurs, accord }, { surRecherche, surConnecteur })
          .catch((erreur) => {
            journal.error(`Premier répondant du projet ${d.projet} : ${erreur.message}`)
            return { ok: false, refus: [] }
          })
        return repondre(res, resultat.ok ? 200 : 503, resultat)
      }

      // Connexion d'un connecteur (MCP) : sonder le serveur, préparer le consentement OAuth, échanger le code,
      // renouveler le jeton. Les jetons sont rendus au site, qui les chiffre ; le Runner ne garde rien.
      const routeConnecteur = { '/connecteurs/sonder': 'sonder', '/connecteurs/preparer': 'preparer', '/connecteurs/echanger': 'echanger', '/connecteurs/renouveler': 'renouveler' }[url.pathname]
      if (req.method === 'POST' && routeConnecteur) {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || typeof d.url !== 'string' || !serveurPermis(d.url)) return repondre(res, 422, { erreur: 'adresse refusée' })
        try {
          const resultat = await connexionConnecteur(routeConnecteur, d)
          return repondre(res, 200, resultat)
        } catch (erreur) {
          journal.warn(`Connecteur (${routeConnecteur}) : ${erreur.message}`)
          return repondre(res, 502, { ok: false, erreur: String(erreur.message).slice(0, 200), raison: erreur.raison ?? null })
        }
      }

      // Passerelle OpenAI de l'agent : jeton du projet vérifié, modèle permis, vraie clé ajoutée ici seulement.
      if (req.method === 'POST' && passerelle) {
        const reponse = await passerOpenAI({ ...passerelle, autorisation: req.headers.authorization, corps }, { secretRunner: secret })
        if (!(reponse instanceof Response)) return repondre(res, reponse.statut, { error: { message: reponse.erreur } })
        res.writeHead(reponse.status, { 'Content-Type': reponse.headers.get('content-type') ?? 'application/json' })
        if (reponse.body) Readable.fromWeb(reponse.body).pipe(res)
        else res.end()
        return
      }

      if (req.method === 'POST' && url.pathname === '/transcrire') {
        if (!signe()) return repondre(res, 401, { erreur: 'signature invalide' })
        const d = lireJson(corps)
        if (!d || typeof d.audio !== 'string' || typeof d.type !== 'string') return repondre(res, 422, { erreur: 'demande incomplète' })
        const resultat = await transcrireDictee({ audio: d.audio, type: d.type, langue: d.langue, modele: d.modele })
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
  // Nombre de demandes en cours : sert à garder le Runner éveillé pendant qu'il travaille.
  serveur.tachesEnCours = () => filesParProjet.size
  return serveur
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
  const serveur = creerServeur({ secret, clesPresentes, ordinateurs })
  serveur.listen(port, hote, () => {
    console.log(`Runner de Kekeli prêt sur ${hote}:${port}. Fournisseurs avec clé : ${[...clesPresentes].join(', ') || 'aucun'}.`)
  })
  garderEveille(serveur, process.env.KEKELI_RUNNER_ADRESSE_PUBLIQUE, { maximumMinutes: Number(process.env.KEKELI_RUNNER_EVEIL_MAX_MINUTES ?? 60) || 60 })
}

/**
 * Hébergement qui s'endort sans visite (Laravel Cloud, « scale to zero ») : seule une requête reçue de l'extérieur
 * le garde éveillé. Pendant qu'une demande travaille, le Runner appelle donc sa propre adresse publique chaque
 * minute ; sans demande en cours, il laisse l'hébergement s'endormir (et ne coûte rien).
 *
 * Protection : le Runner ne se garde jamais éveillé plus de `maximumMinutes` d'affilée (60 par défaut, la durée
 * maximale d'un ordinateur E2B dans l'offre « Hobby »). Une demande bloquée ne peut donc pas le laisser allumé
 * sans travail : passé ce délai, il arrête de s'appeler et l'hébergement s'endort tout seul.
 */
export function garderEveille(serveur, adressePublique, { intervalleMs = 60000, maximumMinutes = 60, telecharger = fetch, journal = console, maintenant = Date.now } = {}) {
  if (!adressePublique) return null
  const adresse = `${adressePublique.replace(/\/+$/, '')}/sante`
  let debut = null
  let prevenu = false
  const minuteur = setInterval(() => {
    if (serveur.tachesEnCours() === 0) {
      debut = null
      prevenu = false
      return
    }
    debut ??= maintenant()
    if (maintenant() - debut > maximumMinutes * 60000) {
      if (!prevenu) journal.warn(`Runner éveillé depuis plus de ${maximumMinutes} minutes : il se laisse maintenant endormir.`)
      prevenu = true
      return
    }
    telecharger(adresse, { signal: AbortSignal.timeout(10000) }).catch(() => {})
  }, intervalleMs)
  minuteur.unref?.()
  return minuteur
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) demarrer()
