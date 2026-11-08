// Conduite d'une tâche : envoi de la demande à l'agent OpenCode, suivi en direct de ses actions,
// comptage des requêtes par fournisseur et basculement automatique quand une limite est atteinte.
import { CATALOGUE, cleEntree, entreesUtilisables } from './fournisseurs.js'
import { tokensDeRequete, traduireOutil } from './etapes.js'
import { estUneLimite } from './limites.js'
import { demandeDeCorrection } from './licences/verifier.mjs'

export const TEXTE_REPRISE =
  "Le travail a été interrompu par un problème technique. Reprends la tâche précédente exactement là où tu t'es arrêté, sans refaire ce qui est déjà fait."

export const DUREE_MAXIMALE_MS = 20 * 60 * 1000

/** Texte envoyé à un nouvel agent quand le projet a été remis en place depuis sa sauvegarde. */
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
/** Ce qui est affiché à l'utilisateur pour une demande de permission. */
export function descriptionPermission(p) {
  const motifs = Array.isArray(p.patterns) ? p.patterns : [p.pattern].flat().filter(Boolean)
  const commande = typeof p.metadata?.command === 'string' ? p.metadata.command : null
  return {
    outil: couper(p.permission ?? p.type ?? 'outil', 40),
    cible: couper(commande ?? motifs.join(' ') ?? p.title ?? '', 300).replaceAll('/home/user/projet/', ''),
  }
}

export async function executerTache(demande, { ordinateurs, envoyer, misesDeCote, clesPresentes, permissions = null, dureeMaxMs = DUREE_MAXIMALE_MS, journal = console }) {
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

  let abonnement = null
  let minuterie = null
  try {
    await envoyer({ type: 'demarrage' })
    const ordinateur = await ordinateurs.obtenir(demande.projet, { sites: demande.sites, sauvegarde: demande.sauvegarde?.telechargement })
    const { client } = ordinateur
    if (ordinateur.repris) await envoyer({ type: 'etape', genre: 'reprise', cible: '' })
    if (ordinateur.restaure) await envoyer({ type: 'etape', genre: 'restauration', cible: '' })
    if (ordinateur.apercu?.url) {
      await envoyer({ type: 'apercu', url: ordinateur.apercu.url, expire_a: ordinateur.expireA?.toISOString?.() ?? null })
    }
    const { session, nouvelle } = await sessionDuProjet(client)
    let texte = demande.reprise && !nouvelle ? TEXTE_REPRISE : demande.message
    if (nouvelle && ordinateur.restaure) texte = texteAvecHistorique(texte, demande.historique)

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

    // Lecture manuelle du flux : il doit rester ouvert entre le travail et la correction des licences.
    const lecteur = abonnement.stream[Symbol.asyncIterator]()
    const permissionsVues = new Set()
    const demanderPermission = async (p) => {
      if (!p?.id || permissionsVues.has(p.id)) return
      permissionsVues.add(p.id)
      if (!permissions) return repondrePermission(ordinateur, p, 'once')
      await envoyer({ type: 'permission', id: p.id, ...descriptionPermission(p) })
      const decision = await permissions.attendre(demande.projet, p.id)
      await repondrePermission(ordinateur, p, decision === 'autoriser' ? 'once' : 'reject')
      await envoyer({ type: 'permission_reglee', id: p.id, decision })
    }

    const suivre = async (contenu) => {
      occupe = false
      await lancer(entree, contenu)
      while (true) {
        const { value: evenement, done } = await lecteur.next()
        if (done) break
        const p = evenement?.properties ?? {}
        const sessionId = p.sessionID ?? p.part?.sessionID ?? p.info?.sessionID
        if (sessionId && sessionId !== session.id) continue

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

    // Le travail, puis la vérification des licences : en cas de problème, l'agent corrige une fois.
    const travail = async () => {
      const premier = await suivre(texte)
      if (!premier.fin || !ordinateurs.verifierLicences) return premier
      let licences = await ordinateurs.verifierLicences(demande.projet).catch(() => null)
      if (licences && !licences.ok && licences.problemes?.length) {
        await envoyer({ type: 'etape', genre: 'licences_correction', cible: String(licences.problemes.length) })
        const second = await suivre(demandeDeCorrection(licences))
        if (!second.fin) return second
        licences = await ordinateurs.verifierLicences(demande.projet).catch(() => null)
      }
      if (licences) {
        await envoyer({
          type: 'etape',
          genre: licences.ok ? 'licences_ok' : 'licences_probleme',
          cible: licences.ok ? (licences.fichier ?? '') : licences.problemes.slice(0, 3).map((p) => p.cible).join(' · '),
        })
      }
      return premier
    }

    const resultat = await Promise.race([travail(), delai])

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
    await abonnement?.stream?.return?.().catch?.(() => {})
  }
}
