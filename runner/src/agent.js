// Conduite d'une tâche : envoi de la demande à l'agent OpenCode, suivi en direct de ses actions,
// comptage des requêtes par fournisseur et basculement automatique quand une limite est atteinte.
import { CATALOGUE, cleEntree, entreesUtilisables, reglagesOutils } from './fournisseurs.js'
import { tokensDeRequete, traduireOutil } from './etapes.js'
import { estUneLimite } from './limites.js'
import { demandeDeCorrection } from './licences/verifier.mjs'
import { explication } from './qualite/verifier.mjs'
import { imagesIntrouvables } from './qualite/images.js'
import { brancheValide, depotValide, texteDepot } from './depot.js'

export const TEXTE_REPRISE =
  "Le travail a été interrompu par un problème technique. Reprends la tâche précédente exactement là où tu t'es arrêté, sans refaire ce qui est déjà fait."

export const DUREE_MAXIMALE_MS = 20 * 60 * 1000

/** Texte envoyé à un nouvel agent quand le projet a été remis en place depuis sa sauvegarde. */
/**
 * Informations de Kekeli placées avant la demande : pays du client (images, monnaie, numéros de téléphone)
 * et photos qu'il a envoyées. Elles ne viennent pas de l'utilisateur et ne changent pas sa demande.
 */
export function texteAvecContexte(message, demande) {
  const pays = demande?.pays
  if (!pays?.nom && !Array.isArray(demande?.photos_client)) return message
  const photos = Array.isArray(demande?.photos_client) ? demande.photos_client.length : 0
  const lignes = []
  if (pays?.nom) {
    lignes.push(`Pays du client : ${pays.nom}${pays.region ? ` (${pays.region})` : ''}${pays.ville ? `, ville principale : ${pays.ville}` : ''}.`)
    if (pays.monnaie) lignes.push(`Monnaie : ${pays.monnaie}.`)
    if (pays.telephone) lignes.push(`Format des numéros de téléphone : ${pays.telephone}.`)
  }
  lignes.push(photos ? `Le client a envoyé ${photos} photo(s) : utilise-les en priorité (node /home/user/.kekeli/images.mjs client).` : "Le client n'a pas encore envoyé de photos.")
  return [`[Informations de Kekeli] ${lignes.join(' ')}`, '', message].join('\n')
}

export function texteAvecHistorique(message, historique) {
  const echanges = (Array.isArray(historique) ? historique : [])
    .filter((m) => m && typeof m.contenu === 'string' && m.contenu.trim())
    .slice(-10)
    .map((m) => `${m.role === 'assistant' ? 'Kekeli' : 'Utilisateur'} : ${couper(m.contenu, 1500)}`)
  if (!echanges.length) return message
  return [
    'Ce projet existe déjà : ses fichiers sont dans le dossier du projet. Regarde-les avant de modifier quoi que ce soit.',
    'Voici les échanges précédents avec l\'utilisateur, pour le contexte :',
    ...echanges,
    '',
    'Nouvelle demande de l\'utilisateur :',
    message,
  ].join('\n')
}

function couper(texte, longueur = 500) {
  const valeur = String(texte ?? '')
  return valeur.length > longueur ? valeur.slice(0, longueur - 1) + '…' : valeur
}

async function sessionDuProjet(client) {
  const { data: sessions } = await client.session.list()
  const existantes = (sessions ?? []).filter((s) => !s.parentID)
  if (existantes.length) {
    existantes.sort((a, b) => (b.time?.updated ?? 0) - (a.time?.updated ?? 0))
    return { session: existantes[0], nouvelle: false }
  }
  const { data: session, error } = await client.session.create({ body: { title: 'Kekeli' } })
  if (error || !session) throw new Error("Impossible de créer la conversation de l'agent.")
  return { session, nouvelle: true }
}

async function derniereReponse(client, sessionId) {
  const { data: messages } = await client.session.messages({ path: { id: sessionId } })
  const assistant = [...(messages ?? [])].reverse().find((m) => m.info?.role === 'assistant' && m.parts?.some((p) => p.type === 'text' && p.text?.trim()))
  if (!assistant) return ''
  return assistant.parts.filter((p) => p.type === 'text' && !p.synthetic).map((p) => p.text.trim()).filter(Boolean).join('\n\n')
}

async function repondrePermission(ordinateur, permission, reponse = 'once') {
  // « once » : autorisé une fois ; « reject » : refusé. L'agent est prévenu et continue sans cette action.
  const id = permission.id
  const essais = [
    [`/permission/${id}/reply`, { reply: reponse }],
    [`/session/${permission.sessionID}/permissions/${id}`, { response: reponse }],
  ]
  for (const [chemin, corps] of essais) {
    try {
      const reponse = await fetch(ordinateur.baseUrl + chemin, {
        method: 'POST',
        headers: { ...ordinateur.entetes, 'Content-Type': 'application/json' },
        body: JSON.stringify(corps),
      })
      if (reponse.ok) return
    } catch {}
  }
}

/**
 * Exécute une tâche. `demande` contient : projet, message, reprise (vrai si la tâche
 * reprend après une mise en attente) et fournisseurs (liste ordonnée { fournisseur, modele }).
 */
/**
 * Une seule demande de correction pour tous les problèmes trouvés par les vérifications automatiques :
 * licences, qualité des pages et images qui ne répondent pas.
 */
export function texteDeCorrection(verification) {
  const parties = []
  if (verification.licences?.problemes?.length) parties.push(demandeDeCorrection(verification.licences))
  if (verification.qualite.length) {
    parties.push(
      [
        'Vérification de la qualité : corrige ces points avant de terminer.',
        ...verification.qualite.map(explication),
        '',
        "Pour remplacer une image qui ne répond pas, lance l'outil de photos de Kekeli (node /home/user/.kekeli/photos.mjs \"mots-clés en anglais\" 4) et utilise une adresse qu'il donne, puis mets à jour LICENCES.md.",
      ].join('\n'),
    )
  }
  return parties.join('\n\n')
}

/** Consigne donnée à l'agent après une action refusée par l'utilisateur. */
export function texteApresRefus(actions) {
  return `L'utilisateur a refusé cette action : ${actions}. Ne la fais pas et n'essaie pas de la contourner. Explique-lui en une ou deux phrases ce que tu n'as pas fait, puis propose une autre solution si c'est utile.`
}

/** Ce qui est affiché à l'utilisateur pour une demande de permission. */
export function descriptionPermission(p) {
  const motifs = Array.isArray(p.patterns) ? p.patterns : [p.pattern].flat().filter(Boolean)
  const commande = typeof p.metadata?.command === 'string' ? p.metadata.command : null
  return {
    outil: couper(p.permission ?? p.type ?? 'outil', 40),
    cible: couper(commande ?? motifs.join(' ') ?? p.title ?? '', 300).replaceAll('/home/user/projet/', ''),
  }
}

const VERIFICATION = Symbol('verification')

export async function executerTache(demande, { ordinateurs, envoyer, misesDeCote, clesPresentes, permissions = null, verifierImages = imagesIntrouvables, dureeMaxMs = DUREE_MAXIMALE_MS, delaiVerificationMs = 30000, journal = console }) {
  // Le site peut demander l'arrêt du travail en répondant à un événement (énergie de l'utilisateur épuisée) :
  // l'agent est arrêté aussitôt, puis le projet est sauvegardé tel quel.
  const envoyerAuSite = envoyer
  let arret = null
  let surArret = null
  envoyer = async (evenement) => {
    const reponse = await envoyerAuSite(evenement)
    if (reponse?.arreter && !arret) {
      arret = typeof reponse.raison === 'string' ? reponse.raison : 'energie'
      await surArret?.()
    }
    return reponse
  }
  const arretee = () => ({ type: 'arretee', raison: arret })

  const entrees = entreesUtilisables(demande.fournisseurs, clesPresentes)
  if (!entrees.length) {
    await envoyer({ type: 'erreur', raison: 'aucun_fournisseur', detail: "Aucun fournisseur d'IA utilisable : aucune clé trouvée dans le Runner." })
    return
  }
  const disponibles = () => entrees.filter((e) => misesDeCote.estDisponible(cleEntree(e)))
  const enAttente = () => ({ type: 'en_attente', reprise_vers: misesDeCote.prochaineDisponibilite(entrees.map(cleEntree)).toISOString() })

  if (!disponibles().length) {
    await envoyer(enAttente())
    return
  }

  // Mode développeur : un dépôt GitHub existant du client (nom, branches et adresse signée de l'archive).
  const depot = demande.depot ?? null
  if (depot && (!depotValide(depot.depot) || !brancheValide(depot.branche_base) || !brancheValide(depot.branche_travail) || !/^https?:\/\//.test(String(depot.archive ?? '')))) {
    await envoyer({ type: 'erreur', raison: 'interne', detail: 'Informations du dépôt GitHub incomplètes.' })
    return
  }

  let abonnement = null
  let minuterie = null
  try {
    await envoyer({ type: 'demarrage' })
    const ordinateur = await ordinateurs.obtenir(demande.projet, {
      sites: demande.sites,
      sauvegarde: demande.sauvegarde?.telechargement,
      dureeVieMinutes: demande.duree_vie_minutes,
      depot,
    })
    const { client } = ordinateur
    if (ordinateur.repris) await envoyer({ type: 'etape', genre: 'reprise', cible: '' })
    if (ordinateur.restaure) await envoyer({ type: 'etape', genre: 'restauration', cible: '' })
    // Pendant le travail, l'ordinateur reste allumé jusqu'à la durée maximale d'une demande.
    await ordinateurs.garderEnMarche?.(demande.projet, dureeMaxMs + 2 * 60 * 1000)?.catch?.(() => {})
    if (ordinateur.apercu?.url) {
      const pendantLeTravail = new Date(Math.max(ordinateur.expireA?.getTime?.() ?? 0, Date.now() + dureeMaxMs + 2 * 60 * 1000))
      await envoyer({ type: 'apercu', url: ordinateur.apercu.url, expire_a: ordinateur.expireA ? pendantLeTravail.toISOString() : null })
    }
    const { session, nouvelle } = await sessionDuProjet(client)
    let detection = null
    if (depot) {
      // Mode développeur : dépôt ouvert, type de projet reconnu, outils et dépendances installés (une fois).
      if (ordinateur.depotOuvert) await envoyer({ type: 'etape', genre: 'depot_ouvert', cible: `${depot.depot} (branche ${depot.branche_travail})` })
      const preparation = await ordinateurs.preparerDepot?.(demande.projet)?.catch?.((erreur) => {
        journal.warn(`Préparation du dépôt : ${erreur.message}`)
        return null
      })
      detection = preparation?.detection ?? null
      if (detection && preparation.etapes.length) {
        await envoyer({ type: 'etape', genre: 'detection', cible: detection.nom })
        for (const etape of preparation.etapes) {
          await envoyer({ type: 'etape', genre: etape.ok ? 'installation' : 'installation_echec', cible: `${couper(etape.commande, 120)} (${etape.secondes} s)` })
        }
        await envoyer({ type: 'depot_detection', detection })
      }
    } else {
      // Réglages des outils (pays du client, services d'images) et photos du client, avant le travail.
      await ordinateurs.preparerTache?.(demande.projet, { reglages: reglagesOutils(demande, clesPresentes), photosClient: demande.photos_client ?? [] })?.catch?.((erreur) => journal.warn(`Préparation de la tâche : ${erreur.message}`))
    }
    let texte = demande.reprise && !nouvelle ? TEXTE_REPRISE : depot ? `${texteDepot(depot, detection)}\n\n${demande.message}` : texteAvecContexte(demande.message, demande)
    if (nouvelle && ordinateur.restaure) texte = texteAvecHistorique(texte, demande.historique)

    surArret = () => client.session.abort({ path: { id: session.id } }).catch(() => {})
    abonnement = await client.event.subscribe()
    let entree = disponibles()[0]
    let occupe = false
    const dejaVues = new Set()

    const lancer = async (cible, contenu) => {
      await envoyer({ type: 'fournisseur', fournisseur: cible.fournisseur, modele: cible.modele })
      const { error } = await client.session.promptAsync({
        path: { id: session.id },
        body: {
          model: { providerID: CATALOGUE[cible.fournisseur].fournisseurOpencode, modelID: cible.modele },
          parts: [{ type: 'text', text: contenu }],
        },
      })
      if (error) throw new Error("L'agent n'a pas accepté la demande.")
    }

    // État de la conversation demandé à OpenCode : « inactive », « active » ou « injoignable ».
    const etatSession = async () => {
      try {
        const { data, error } = await client.session.status()
        if (error || !data) return 'injoignable'
        return !data[session.id] || data[session.id].type === 'idle' ? 'inactive' : 'active'
      } catch {
        return 'injoignable'
      }
    }
    let injoignable = 0

    // Lecture manuelle du flux : il doit rester ouvert entre le travail et la correction des licences.
    const lecteur = abonnement.stream[Symbol.asyncIterator]()
    const permissionsVues = new Set()
    const refus = []
    const demanderPermission = async (p) => {
      if (!p?.id || permissionsVues.has(p.id)) return
      permissionsVues.add(p.id)
      if (!permissions) return repondrePermission(ordinateur, p, 'once')
      // L'attente est enregistrée avant la question, pour qu'une réponse très rapide ne se perde pas.
      const attente = permissions.attendre(demande.projet, p.id)
      await envoyer({ type: 'permission', id: p.id, ...descriptionPermission(p) })
      const decision = await attente
      if (decision !== 'autoriser') refus.push(descriptionPermission(p).cible)
      await repondrePermission(ordinateur, p, decision === 'autoriser' ? 'once' : 'reject')
      await envoyer({ type: 'permission_reglee', id: p.id, decision })
    }

    const suivre = async (contenu) => {
      occupe = false
      await lancer(entree, contenu)
      let enCours = null
      while (true) {
        // Sans événement pendant un moment, on demande directement à OpenCode où en est la conversation :
        // une fin de travail ne doit jamais se perdre (coupure du flux, par exemple).
        if (arret) return { fin: true }
        enCours ??= lecteur.next()
        let minuterieVerification
        const verification = new Promise((r) => {
          minuterieVerification = setTimeout(() => r(VERIFICATION), delaiVerificationMs)
        })
        const suivant = await Promise.race([enCours, verification])
        clearTimeout(minuterieVerification)
        if (suivant === VERIFICATION) {
          const etat = await etatSession()
          injoignable = etat === 'injoignable' ? injoignable + 1 : 0
          // L'ordinateur ne répond plus du tout (arrêté ou en panne) : inutile d'attendre la durée maximale.
          if (injoignable >= 4) return { type: 'erreur', raison: 'ordinateur', detail: "L'ordinateur virtuel ne répond plus." }
          if (occupe && etat === 'inactive') return { fin: true }
          continue
        }
        enCours = null
        const { value: evenement, done } = suivant
        if (done) break
        const p = evenement?.properties ?? {}
        const sessionId = p.sessionID ?? p.part?.sessionID ?? p.info?.sessionID
        if (sessionId && sessionId !== session.id) continue

        // Toute activité de l'agent dans cette conversation montre qu'il travaille : OpenCode n'annonce
        // pas toujours « occupé » avant la fin (par exemple quand la première action est refusée).
        if (sessionId === session.id && evenement.type !== 'session.status' && evenement.type !== 'session.idle') occupe = true

        switch (evenement.type) {
          case 'message.part.updated': {
            const partie = p.part
            if (!partie?.id || dejaVues.has(partie.id)) break
            if (partie.type === 'step-finish') {
              dejaVues.add(partie.id)
              misesDeCote.reussite(cleEntree(entree))
              await envoyer({ type: 'requete', fournisseur: entree.fournisseur, modele: entree.modele, ...tokensDeRequete(partie) })
            } else {
              const etape = traduireOutil(partie)
              if (etape) {
                dejaVues.add(partie.id)
                await envoyer({ type: 'etape', ...etape })
              }
            }
            break
          }
          case 'session.status': {
            const statut = p.status ?? {}
            if (statut.type === 'busy') {
              occupe = true
            } else if (statut.type === 'idle' && occupe) {
              return { fin: true }
            } else if (statut.type === 'retry') {
              const cle = cleEntree(entree)
              if (estUneLimite(statut.message)) await envoyer({ type: 'refus', fournisseur: entree.fournisseur, modele: entree.modele })
              const jusqua = misesDeCote.analyserEssai(cle, statut.message, Number(statut.attempt ?? 1), {
                remiseAZero: entree.remise_a_zero,
                prochainEssai: Number(statut.next),
              })
              if (jusqua) {
                await envoyer({ type: 'mis_de_cote', fournisseur: entree.fournisseur, modele: entree.modele, jusqu_a: jusqua.toISOString(), message: couper(statut.message) })
                occupe = false
                await client.session.abort({ path: { id: session.id } })
                const suivant = disponibles()[0]
                if (!suivant) return enAttente()
                entree = suivant
                await lancer(entree, TEXTE_REPRISE)
              }
            }
            break
          }
          case 'session.idle':
            if (occupe) return { fin: true }
            break
          case 'session.error': {
            const nom = p.error?.name
            if (nom === 'MessageAbortedError') break
            return { type: 'erreur', raison: 'agent', detail: couper(p.error?.data?.message ?? nom ?? 'Erreur inconnue') }
          }
          case 'permission.asked':
          case 'permission.updated':
            // La réponse de l'utilisateur peut prendre du temps : on continue de lire les événements.
            demanderPermission(p).catch((erreur) => journal.warn(`Permission : ${erreur.message}`))
            break
        }
      }
      return { type: 'erreur', raison: 'flux', detail: "Le flux des événements de l'agent s'est arrêté." }
    }

    const delai = new Promise((resolve) => {
      minuterie = setTimeout(() => resolve({ type: 'erreur', raison: 'duree', detail: 'La tâche a dépassé la durée maximale.' }), dureeMaxMs)
    })

    // Vérifications automatiques à la fin du travail : licences, qualité des pages, images sur Internet.
    const verifier = async () => {
      // Typographie française corrigée par Kekeli avant les vérifications (espaces insécables).
      const typographie = ordinateurs.corrigerTypographie ? await ordinateurs.corrigerTypographie(demande.projet).catch(() => null) : null
      if (typographie?.corrections > 0) await envoyer({ type: 'etape', genre: 'typographie', cible: `${typographie.corrections} espace(s) insécable(s)` })
      const licences = ordinateurs.verifierLicences ? await ordinateurs.verifierLicences(demande.projet).catch(() => null) : null
      const pages = ordinateurs.verifierQualite ? await ordinateurs.verifierQualite(demande.projet).catch(() => null) : null
      const introuvables = pages?.images?.length ? await verifierImages(pages.images).catch(() => []) : []
      const qualite = [...(pages?.problemes ?? []), ...introuvables.map((adresse) => ({ genre: 'image_introuvable', fichier: 'site', cible: adresse }))]
      return { licences, pages, qualite, total: (licences?.ok === false ? licences.problemes?.length ?? 0 : 0) + qualite.length }
    }

    // Un tour peut se terminer sur une erreur du fournisseur (par exemple « quota dépassé ») sans que
    // OpenCode la signale comme une erreur : on regarde le dernier message de l'agent.
    const erreurDuTour = async () => {
      try {
        const { data: messages } = await client.session.messages({ path: { id: session.id } })
        const dernier = [...(messages ?? [])].reverse().find((m) => m.info?.role === 'assistant')
        const erreur = dernier?.info?.error
        if (!erreur || erreur.name === 'MessageAbortedError') return null
        return String(erreur.data?.message ?? erreur.message ?? erreur.name ?? 'Erreur inconnue')
      } catch {
        return null
      }
    }

    // Vrai si le dernier message de l'agent ne contient aucun texte : certains modèles s'arrêtent ainsi en plein
    // travail, sans erreur. On ne prend pas ce silence pour un travail terminé.
    const tourMuet = async () => {
      try {
        const { data: messages } = await client.session.messages({ path: { id: session.id } })
        const dernier = [...(messages ?? [])].reverse().find((m) => m.info?.role === 'assistant')
        if (!dernier || dernier.info?.error) return false
        return !(dernier.parts ?? []).some((p) => p.type === 'text' && String(p.text ?? '').trim())
      } catch {
        return false
      }
    }

    // Un tour terminé en silence est relancé, au plus deux fois.
    const relancerSiMuet = async (resultat) => {
      for (let essais = 0; resultat.fin && !arret && essais < 2 && (await tourMuet()); essais++) {
        await envoyer({ type: 'etape', genre: 'relance', cible: '' })
        resultat = await terminerOuBasculer(await suivre(TEXTE_REPRISE))
      }
      return resultat
    }

    // Une fin de tour sur une limite atteinte : bascule vers le fournisseur suivant (ou mise en attente).
    // Une autre erreur : la demande échoue clairement, au lieu d'être annoncée comme réussie.
    const terminerOuBasculer = async (resultat) => {
      for (let essais = 0; resultat.fin && !arret && essais < entrees.length; essais++) {
        const message = await erreurDuTour()
        if (!message) return resultat
        if (!estUneLimite(message)) return { type: 'erreur', raison: 'agent', detail: couper(message) }
        await envoyer({ type: 'refus', fournisseur: entree.fournisseur, modele: entree.modele })
        const jusqua = misesDeCote.analyserEssai(cleEntree(entree), message, 3, { remiseAZero: entree.remise_a_zero }) ?? misesDeCote.mettreDeCote(cleEntree(entree), 60 * 1000)
        await envoyer({ type: 'mis_de_cote', fournisseur: entree.fournisseur, modele: entree.modele, jusqu_a: jusqua.toISOString(), message: couper(message) })
        const suivant = disponibles()[0]
        if (!suivant) return enAttente()
        entree = suivant
        resultat = await suivre(TEXTE_REPRISE)
      }
      return resultat
    }

    // Le travail, puis les vérifications : en cas de problème, l'agent corrige une fois.
    const travail = async () => {
      let premier = await relancerSiMuet(await terminerOuBasculer(await suivre(texte)))
      if (arret) return arretee()
      // Une action refusée arrête l'agent net : on le relance pour qu'il en tienne compte et le dise.
      if (premier.fin && refus.length) {
        const actions = refus.splice(0).map((cible) => `« ${cible} »`).join(', ')
        premier = await terminerOuBasculer(await suivre(texteApresRefus(actions)))
      }
      if (arret) return arretee()
      // Mode développeur : pas de vérification de site web ; l'agent lance lui-même les tests du projet.
      if (depot) return premier
      if (!premier.fin || (!ordinateurs.verifierLicences && !ordinateurs.verifierQualite)) return premier
      let verification = await verifier()
      if (verification.total > 0) {
        await envoyer({ type: 'etape', genre: 'verification_correction', cible: String(verification.total) })
        const second = await terminerOuBasculer(await suivre(texteDeCorrection(verification)))
        if (arret) return arretee()
        if (!second.fin) return second
        verification = await verifier()
      }
      const { licences, pages, qualite } = verification
      if (licences) {
        await envoyer({
          type: 'etape',
          genre: licences.ok ? 'licences_ok' : 'licences_probleme',
          cible: licences.ok ? (licences.fichier ?? '') : licences.problemes.slice(0, 3).map((p) => p.cible).join(' · '),
        })
      }
      if (pages) {
        await envoyer({
          type: 'etape',
          genre: qualite.length ? 'qualite_probleme' : 'qualite_ok',
          cible: qualite.length
            ? qualite.slice(0, 3).map((p) => `${p.fichier} : ${p.cible || p.genre}`).join(' · ')
            : `${pages.pages} page(s), ${pages.images?.length ?? 0} image(s) vérifiée(s)`,
        })
      }
      return premier
    }

    const resultat = await Promise.race([travail(), delai])

    // Consommation des outils de l'agent : requêtes au modèle de vision et images créées (comptées dans l'énergie).
    const evenementsOutils = (await ordinateurs.lireJournal?.(demande.projet)?.catch?.(() => [])) ?? []
    for (const evenement of evenementsOutils.filter((e) => e.type === 'requete' && e.fournisseur && e.modele)) {
      const { a, ...requete } = evenement
      await envoyer({ ...requete, outil: true })
    }
    const imagesCreees = evenementsOutils.filter((e) => e.type === 'image_creee').length
    if (imagesCreees) await envoyer({ type: 'images', nombre: imagesCreees })

    // Mode développeur : le travail est enregistré dans un commit, et le site reçoit la branche et les fichiers modifiés.
    if (depot && ordinateurs.etatDepot) {
      const etat = await ordinateurs.etatDepot(demande.projet).catch((erreur) => {
        journal.warn(`État du dépôt : ${erreur.message}`)
        return null
      })
      if (etat) {
        await envoyer({ type: 'depot', ...etat })
        await envoyer({ type: 'etape', genre: 'depot_commits', cible: `${etat.nombre_fichiers} fichier(s), branche ${etat.branche}` })
      }
    }

    // Sauvegarde des fichiers du projet dans le site Kekeli, même si la demande a échoué.
    if (demande.sauvegarde?.envoi && ordinateurs.sauvegarder) {
      try {
        const sauvegarde = await ordinateurs.sauvegarder(demande.projet, demande.sauvegarde.envoi)
        if (sauvegarde) await envoyer({ type: 'etape', genre: 'sauvegarde', cible: `${Math.max(1, Math.round(sauvegarde.taille / 1024))} Ko` })
      } catch (erreur) {
        journal.warn(`Sauvegarde du projet ${demande.projet} : ${erreur.message}`)
        await envoyer({ type: 'etape', genre: 'echec_sauvegarde', cible: '' })
      }
    }

    // L'ordinateur (et donc l'aperçu) reste allumé un moment après chaque tâche.
    const expireA = ordinateurs.prolonger ? await ordinateurs.prolonger(demande.projet).catch(() => null) : null
    if (ordinateur.apercu?.url && expireA) {
      await envoyer({ type: 'apercu', url: ordinateur.apercu.url, expire_a: expireA.toISOString() })
    }

    if (resultat.fin) {
      await envoyer({ type: 'terminee', reponse: await derniereReponse(client, session.id) })
    } else {
      if (resultat.raison === 'duree') await client.session.abort({ path: { id: session.id } }).catch(() => {})
      await envoyer(resultat)
    }
  } catch (erreur) {
    journal.error(`Tâche du projet ${demande.projet} : ${erreur.message}`)
    await envoyer({ type: 'erreur', raison: 'interne', detail: couper(erreur.message) })
  } finally {
    clearTimeout(minuterie)
    // Fermeture du flux sans l'attendre : une lecture peut encore être en cours.
    abonnement?.stream?.return?.()?.catch?.(() => {})
  }
}
