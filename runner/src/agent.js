// Conduite d'une tâche : envoi de la demande à l'agent OpenCode, suivi en direct de ses actions,
// comptage des requêtes par fournisseur et basculement automatique quand une limite est atteinte.
import { CATALOGUE, cleEntree, entreesUtilisables } from './fournisseurs.js'
import { tokensDeRequete, traduireOutil } from './etapes.js'
import { estUneLimite } from './limites.js'

export const TEXTE_REPRISE =
  "Le travail a été interrompu par un problème technique. Reprends la tâche précédente exactement là où tu t'es arrêté, sans refaire ce qui est déjà fait."

export const DUREE_MAXIMALE_MS = 20 * 60 * 1000

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

async function repondrePermission(ordinateur, permission) {
  // Étape 2 : l'ordinateur virtuel est isolé et les permissions sont déjà accordées dans la
  // configuration. Les boutons « Autoriser » et « Refuser » arriveront à l'étape 4.
  const id = permission.id
  const essais = [
    [`/permission/${id}/reply`, { reply: 'once' }],
    [`/session/${permission.sessionID}/permissions/${id}`, { response: 'once' }],
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
export async function executerTache(demande, { ordinateurs, envoyer, misesDeCote, clesPresentes, dureeMaxMs = DUREE_MAXIMALE_MS, journal = console }) {
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
    const ordinateur = await ordinateurs.obtenir(demande.projet)
    const { client } = ordinateur
    if (ordinateur.apercu?.url) {
      await envoyer({ type: 'apercu', url: ordinateur.apercu.url, expire_a: ordinateur.expireA?.toISOString?.() ?? null })
    }
    const { session, nouvelle } = await sessionDuProjet(client)
    const texte = demande.reprise && !nouvelle ? TEXTE_REPRISE : demande.message

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

    const suivre = async () => {
      await lancer(entree, texte)
      for await (const evenement of abonnement.stream) {
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
            await repondrePermission(ordinateur, p)
            break
        }
      }
      return { type: 'erreur', raison: 'flux', detail: "Le flux des événements de l'agent s'est arrêté." }
    }

    const delai = new Promise((resolve) => {
      minuterie = setTimeout(() => resolve({ type: 'erreur', raison: 'duree', detail: 'La tâche a dépassé la durée maximale.' }), dureeMaxMs)
    })

    const resultat = await Promise.race([suivre(), delai])

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
