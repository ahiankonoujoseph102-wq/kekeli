// Conduite d'une tâche : envoi de la demande à l'agent OpenCode, suivi en direct de ses actions,
// comptage des requêtes par fournisseur et basculement automatique quand une limite est atteinte.
import { CATALOGUE, MemoireModeles, cleEntree, clesAgent, entreesUtilisables, modeleAutorise, modeleOpencode, reglagesOutils } from './fournisseurs.js'

// Modèle qui a travaillé pour chaque projet (gardé 30 minutes), commun à toutes les demandes du Runner.
export const memoireModeles = new MemoireModeles()
import { tokensDeRequete, traduireOutil } from './etapes.js'
import { PAUSE_MAXIMALE_MS, estPassagere, estTropGrande, estUneLimite } from './limites.js'
import { demandeDeCorrection } from './licences/verifier.mjs'
import { explication } from './qualite/verifier.mjs'
import { imagesIntrouvables } from './qualite/images.js'
import { brancheValide, depotValide, texteDepot } from './depot.js'
import { applicationValide, problemesApplication, texteApplication, texteCorrectionApplication } from './application.js'
import { ATTENTE_MISSION_MAX_MS, CONSIGNES_PLANIFICATEUR, RESTES_DU_DEPART, TEXTE_RESTES, avecAccueil, avecDonneesEssai, parleDeDemandes, CORRECTIONS_MAX, DUREE_EQUIPE_MS, DUREE_MISSION_MS, ROLES, compteur, consignePlanificateur, faitsDuBilan, planPropre, sansBlocFaits, texteMission, texteValidationFinale } from './equipe.js'
import { rediger } from './documents/redaction.js'

/** Rappel ajouté après une correction, une reprise ou un refus : le message final porte sur toute la demande. */
export const RAPPEL_COMPTE_RENDU =
  "À la fin, écris le message final de TOUTE la demande du client (pas seulement de cette dernière partie), comme le demande la consigne « Message final au client » : ce qui est prêt, la courte liste des pages ou sections, les choix faits par défaut, puis la dernière ligne « SUITES : … | … ». Jamais de nom de fichier."

/**
 * Garde-fou du dépassement (15 novembre 2026) : une création commencée n'est jamais coupée net. Quand la demande a
 * trop dépassé l'énergie du client, l'agent reçoit cette dernière consigne : finir l'étape en cours sans rien
 * commencer de nouveau, laisser un site qui s'ouvre, puis écrire le compte rendu.
 */
export const TEXTE_FIN_GARDE_FOU = `Le temps de travail prévu pour cette demande est écoulé. Termine maintenant, en très peu d'actions : finis seulement le fichier ou la section en cours, sans rien commencer de nouveau. Vérifie que chaque page déjà créée s'ouvre et qu'aucun lien du menu ne mène à une page absente (enlève le lien si la page n'existe pas). Puis écris ton message final au client : ce qui est prêt, et en une phrase ce qui reste à faire, qu'il pourra demander ensuite.`

/** Le compte rendu manque (le dernier message est une phrase de travail) : une seule relance, sans autre action. */
export const TEXTE_COMPTE_RENDU = "N'ajoute ni ne modifie plus rien. Écris maintenant ton message final au client pour TOUTE la demande, exactement comme le demande la consigne « Message final au client » : ce qui est prêt, la courte liste des pages ou sections, les choix faits par défaut, puis la dernière ligne « SUITES : … | … »."

/** Un vrai compte rendu se termine par la ligne des suites proposées. */
export const aUnCompteRendu = (texte) => /(^|\n)\s*\**SUITES\s*:/i.test(String(texte ?? ''))

export const TEXTE_REPRISE =
  "Le travail a été interrompu par un problème technique. Reprends la tâche précédente exactement là où tu t'es arrêté, sans refaire ce qui est déjà fait. " +
  RAPPEL_COMPTE_RENDU

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

/** Une phrase de travail affichée au client : la première ligne, sans mise en forme, 280 caractères au plus. */
export function phraseDeTravail(texte) {
  const ligne = String(texte ?? '').split('\n').map((l) => l.trim()).find(Boolean) ?? ''
  const phrase = couper(ligne.replace(/^#+\s*/, '').replace(/\*\*/g, '').replace(/`/g, ''), 280)
  // Un titre du compte rendu final (« Ce que j'ai fait »…) n'est pas une phrase de travail.
  if (TITRES_COMPTE_RENDU.has(phrase.replace(/[\s:.]+$/, '').toLowerCase())) return null
  // Le client ne lit que du français : une réflexion de l'agent en anglais n'est jamais affichée.
  return estEnFrancais(phrase) ? phrase : null
}

// Pour le client, Kekeli fait des sites : le mot « application » est remplacé par « site » dans ce qu'il lit
// (phrases de travail et compte rendu), sans toucher aux noms de fichiers comme config/site.php.
const REMPLACEMENTS_APPLICATION = [
  [/\b([Dd])e l['’]application\b/g, (_, d) => `${d === 'D' ? 'D' : 'd'}u site`],
  [/\b([Àà]) l['’]application\b/g, (_, a) => `${a === 'À' ? 'A' : 'a'}u site`],
  [/\b([Ll])['’]application\b/g, (_, l) => `${l === 'L' ? 'L' : 'l'}e site`],
  [/\b([Dd])['’]application\b/g, (_, d) => `${d}e site`],
  [/\b([Uu])ne application\b/g, (_, u) => `${u}n site`],
  [/\b([Cc])ette application\b/g, (_, c) => `${c}e site`],
  [/\b([Aa])pplications web\b/g, (_, a) => (a === 'A' ? 'Sites' : 'sites')],
  [/\b([Aa])pplication web\b/g, (_, a) => (a === 'A' ? 'Site' : 'site')],
  [/\b([Aa])pplications\b/g, (_, a) => (a === 'A' ? 'Sites' : 'sites')],
  [/\b([Aa])pplication\b/g, (_, a) => (a === 'A' ? 'Site' : 'site')],
]

export function sansMotApplication(texte) {
  if (typeof texte !== 'string' || !/application/i.test(texte)) return texte
  // Les noms de fichiers, chemins et extraits de code (entre accents graves) restent tels quels.
  return texte
    .split(/(`[^`]*`|[\w./-]*application[\w-]*\.[a-z]{1,4}\b|[\w-]+\/[\w./-]*application[\w./-]*)/g)
    .map((morceau, i) => (i % 2 ? morceau : REMPLACEMENTS_APPLICATION.reduce((t, [motif, remplacement]) => t.replace(motif, remplacement), morceau)))
    .join('')
}

const TITRES_COMPTE_RENDU = new Set(["ce que j'ai fait", 'ce que j’ai fait', 'fichiers modifiés', 'tests et vérifications', "ce qui n'a pas marché ou reste à faire", 'ce qui n’a pas marché ou reste à faire', 'à vérifier de votre côté', 'compte rendu'])

const MOTS_FRANCAIS = new Set(['je', 'j', 'le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'pour', 'en', 'est', 'à', 'que', 'qui', 'sur', 'avec', 'dans', 'ce', 'cette', 'mon', 'votre', 'vos', 'site', 'page', 'ajoute', 'regarde', 'corrige', 'vérifie', 'crée', 'tests'])
const MOTS_ANGLAIS = new Set(['the', 'i', 'is', 'let', 'let’s', "let's", 'to', 'and', 'of', 'if', 'it', 'this', 'we', 'will', 'now', 'check', 'next', 'first', 'okay', 'ok', 'need', 'should', 'file', 'make', 'sure', 'all', 'are', 'be', 'with', 'for', 'that', 'everything', 'correct', 'output', 'final', 'report', 'done'])

/** Vrai si la phrase est en français (plus de mots français courants que de mots anglais courants). */
export function estEnFrancais(phrase) {
  const mots = String(phrase ?? '').toLowerCase().split(/[^\p{L}'’]+/u).filter(Boolean).flatMap((m) => m.split(/['’]/)).filter(Boolean)
  const francais = mots.filter((m) => MOTS_FRANCAIS.has(m)).length
  const anglais = String(phrase ?? '').toLowerCase().split(/[^\p{L}'’]+/u).filter((m) => MOTS_ANGLAIS.has(m)).length
  return francais > 0 && francais >= anglais
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
  return [...parties, RAPPEL_COMPTE_RENDU].join('\n\n')
}

/** Consigne donnée à l'agent après une action refusée par l'utilisateur. */
export function texteApresRefus(actions) {
  return `L'utilisateur a refusé cette action : ${actions}. Ne la fais pas et n'essaie pas de la contourner. Explique-lui en une ou deux phrases ce que tu n'as pas fait, puis propose une autre solution si c'est utile. ${RAPPEL_COMPTE_RENDU}`
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

export async function executerTache(demande, { ordinateurs, envoyer, misesDeCote, clesPresentes, permissions = null, verifierImages = imagesIntrouvables, dureeMaxMs = DUREE_MAXIMALE_MS, delaiVerificationMs = 30000, journal = console, memoire = new MemoireModeles(), redigerPlan = rediger, attenteLimiteMs = 20000, dormir = (ms) => new Promise((ok) => setTimeout(ok, ms)) }) {
  // Le site peut demander l'arrêt du travail en répondant à un événement (énergie de l'utilisateur épuisée) :
  // l'agent est arrêté aussitôt, puis le projet est sauvegardé tel quel.
  const envoyerAuSite = envoyer
  let arret = null
  let surArret = null
  // « terminer » (garde-fou du dépassement) : le tour en cours est interrompu une seule fois, puis l'agent finit
  // proprement avec TEXTE_FIN_GARDE_FOU ; la demande se termine ensuite normalement, avec son compte rendu.
  let finir = null
  // Mode équipe : les requêtes de la mission en cours sont aussi comptées à part (mesure par mission).
  let compteurMission = null
  envoyer = async (evenement) => {
    if (evenement?.type === 'requete') compteurMission?.ajouter(evenement)
    const reponse = await envoyerAuSite(evenement)
    if (reponse?.arreter && !arret) {
      arret = typeof reponse.raison === 'string' ? reponse.raison : 'energie'
      await surArret?.()
    } else if (reponse?.terminer && !finir && !arret) {
      finir = 'demande'
      await surArret?.()
    }
    return reponse
  }
  const arretee = () => ({ type: 'arretee', raison: arret })
  // Mode équipe (applications seulement) : plusieurs missions, donc une durée maximale plus longue.
  const equipe = Boolean(demande.equipe && demande.application && !demande.depot)
  if (equipe) dureeMaxMs = Math.max(dureeMaxMs, DUREE_EQUIPE_MS)

  // OpenAI compte seulement si sa clé est dans le Runner : l'agent y passe par le relais de l'ordinateur.
  const entrees = entreesUtilisables(demande.fournisseurs, clesAgent(clesPresentes))
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

  // Application web : technologie (laravel ou nextjs) et site de départ (le socle).
  const application = demande.application ?? null
  if (application && !applicationValide(application)) {
    await envoyer({ type: 'erreur', raison: 'interne', detail: 'Informations de l’application web incomplètes.' })
    return
  }

  let abonnement = null
  let arreterRelais = null
  let minuterie = null
  try {
    await envoyer({ type: 'demarrage' })
    const ordinateur = await ordinateurs.obtenir(demande.projet, {
      sites: demande.sites,
      sauvegarde: demande.sauvegarde?.telechargement,
      dureeVieMinutes: demande.duree_vie_minutes,
      depot,
      application,
      modeles: entrees,
      essaiAgent: demande.essai_agent ?? null,
    })
    const { client } = ordinateur
    // Construction avec OpenAI : le Runner suit le relais de l'ordinateur pendant toute la demande.
    if (entrees.some((e) => e.fournisseur === 'openai' || e.fournisseur === 'freellmapi')) arreterRelais = ordinateurs.suivreRelaisOpenAI?.(demande.projet) ?? null
    if (ordinateur.repris) await envoyer({ type: 'etape', genre: 'reprise', cible: '' })
    if (ordinateur.restaure) await envoyer({ type: 'etape', genre: 'restauration', cible: '' })
    // Pendant le travail, l'ordinateur reste allumé jusqu'à la durée maximale d'une demande.
    await ordinateurs.garderEnMarche?.(demande.projet, dureeMaxMs + 2 * 60 * 1000)?.catch?.(() => {})
    if (ordinateur.apercu?.url) {
      const pendantLeTravail = new Date(Math.max(ordinateur.expireA?.getTime?.() ?? 0, Date.now() + dureeMaxMs + 2 * 60 * 1000))
      await envoyer({ type: 'apercu', url: ordinateur.apercu.url, expire_a: ordinateur.expireA ? pendantLeTravail.toISOString() : null })
    }
    const { session, nouvelle } = await sessionDuProjet(client)
    // La conversation suivie : celle du projet, ou celle d'une mission du mode équipe (session enfant, contexte neuf),
    // avec l'agent OpenCode du rôle de la mission ; la limite de durée de la mission en cours (le gardien).
    let actuelle = session
    let agentCourant = null
    let limiteMission = null
    let detection = null
    let echecs = []
    let preparationApplication = null
    if (depot) {
      // Mode développeur : dépôt ouvert, type de projet reconnu, outils et dépendances installés (une fois).
      if (ordinateur.depotOuvert) await envoyer({ type: 'etape', genre: 'depot_ouvert', cible: `${depot.depot} (branche ${depot.branche_travail})` })
      const preparation = await ordinateurs.preparerDepot?.(demande.projet)?.catch?.((erreur) => {
        journal.warn(`Préparation du dépôt : ${erreur.message}`)
        return null
      })
      detection = preparation?.detection ?? null
      echecs = (preparation?.etapes ?? []).filter((etape) => !etape.ok)
      if (detection && preparation.etapes.length) {
        await envoyer({ type: 'etape', genre: 'detection', cible: detection.nom })
        for (const etape of preparation.etapes) {
          await envoyer({ type: 'etape', genre: etape.ok ? 'installation' : 'installation_echec', cible: `${couper(etape.commande, 120)} (${etape.secondes} s)` })
        }
        await envoyer({ type: 'depot_detection', detection })
      }
    } else if (application) {
      // Variables secrètes du client (ses propres services), données seulement à l'application de cet ordinateur.
      await ordinateurs.ecrireSecrets?.(demande.projet, demande.secrets ?? {})?.catch?.((erreur) => journal.warn(`Variables secrètes : ${erreur.message}`))
      // Application web : fichiers de départ posés, outils et dépendances installés, base d'essai, lancement.
      if (ordinateur.applicationDeposee) await envoyer({ type: 'etape', genre: 'application_deposee', cible: application.modele })
      preparationApplication = await ordinateurs.preparerApplication?.(demande.projet, application.pile)?.catch?.((erreur) => {
        journal.warn(`Préparation de l'application : ${erreur.message}`)
        return null
      })
      for (const etape of preparationApplication?.etapes ?? []) {
        await envoyer({ type: 'etape', genre: etape.ok ? 'installation' : 'installation_echec', cible: `${couper(etape.commande, 120)} (${etape.secondes} s)` })
      }
      if (preparationApplication && !preparationApplication.agent) {
        await envoyer({ type: 'application', pile: application.pile, identifiant: preparationApplication.identifiant, mot_de_passe: preparationApplication.mot_de_passe })
      }
    } else {
      // Réglages des outils (pays du client, services d'images) et photos du client, avant le travail.
      await ordinateurs.preparerTache?.(demande.projet, { reglages: reglagesOutils(demande, clesPresentes), photosClient: demande.photos_client ?? [] })?.catch?.((erreur) => journal.warn(`Préparation de la tâche : ${erreur.message}`))
    }
    let texte =
      demande.reprise && !nouvelle
        ? TEXTE_REPRISE
        : depot
          ? `${texteDepot(depot, detection, echecs)}\n\n${demande.message}`
          : application
            ? `${texteApplication(application, preparationApplication)}\n\n${texteAvecContexte(demande.message, demande)}`
            : texteAvecContexte(demande.message, demande)
    if (nouvelle && ordinateur.restaure) texte = texteAvecHistorique(texte, demande.historique)

    if (!demande.reprise) await ordinateurs.photographier?.(demande.projet)?.catch?.((erreur) => journal.warn(`Photo du projet : ${erreur.message}`))

    surArret = () => client.session.abort({ path: { id: actuelle.id } }).catch(() => {})
    abonnement = await client.event.subscribe()
    let entree = memoire.ordonner(demande.projet, disponibles())[0]
    let occupe = false
    const dejaVues = new Set()

    const lancer = async (cible, contenu) => {
      // Dernier verrou avant chaque appel : jamais de modèle payant (par exemple chez OpenRouter).
      if (!modeleAutorise(cible.fournisseur, cible.modele)) throw new Error(`Modèle refusé : ${cible.fournisseur}/${cible.modele} n'est pas gratuit.`)
      await envoyer({ type: 'fournisseur', fournisseur: cible.fournisseur, modele: cible.modele })
      const corps = {
        model: { providerID: CATALOGUE[cible.fournisseur].fournisseurOpencode, modelID: modeleOpencode(cible) },
        parts: [{ type: 'text', text: contenu }],
      }
      let { error } = await client.session.promptAsync({ path: { id: actuelle.id }, body: agentCourant ? { ...corps, agent: agentCourant } : corps })
      // Un ordinateur créé avant le mode équipe ne connaît pas ses agents : la mission part avec l'agent habituel.
      if (error && agentCourant) ({ error } = await client.session.promptAsync({ path: { id: actuelle.id }, body: corps }))
      if (error) {
        // Le détail reste dans le journal du Runner et pour l'administrateur ; le client voit un message général.
        const detail = couper(typeof error === 'string' ? error : error?.data?.message ?? error?.message ?? JSON.stringify(error))
        journal.warn(`OpenCode a refusé la demande (${cible.fournisseur}/${cible.modele}) : ${detail}`)
        throw new Error(`L'agent n'a pas accepté la demande. ${detail}`.trim())
      }
    }

    // État de la conversation demandé à OpenCode : « inactive », « active » ou « injoignable ».
    const etatSession = async () => {
      try {
        const { data, error } = await client.session.status()
        if (error || !data) return 'injoignable'
        return !data[actuelle.id] || data[actuelle.id].type === 'idle' ? 'inactive' : 'active'
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

    // Phrases de l'agent pendant le travail (« Je regarde comment la page d'accueil est construite. ») : une phrase
    // est envoyée au site dès que l'action qui la suit commence ; la dernière phrase d'un tour est la réponse finale.
    const messagesUtilisateur = new Set()
    let phraseEnAttente = null
    const envoyerPhrase = async () => {
      if (!phraseEnAttente) return
      const texte = phraseEnAttente
      phraseEnAttente = null
      await envoyer({ type: 'narration', texte: demande.depot || demande.application?.installable ? texte : sansMotApplication(texte) })
    }

    const suivre = async (contenu) => {
      occupe = false
      phraseEnAttente = null
      await lancer(entree, contenu)
      let enCours = null
      while (true) {
        // Sans événement pendant un moment, on demande directement à OpenCode où en est la conversation :
        // une fin de travail ne doit jamais se perdre (coupure du flux, par exemple).
        if (arret || finir === 'demande') return { fin: true }
        // Le gardien du mode équipe : une mission qui dépasse sa durée est arrêtée (l'équipe passe à la suivante).
        if (limiteMission && Date.now() > limiteMission) {
          await client.session.abort({ path: { id: actuelle.id } }).catch(() => {})
          return { type: 'erreur', raison: 'delai_mission', detail: 'La mission a dépassé sa durée maximale.' }
        }
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
        if (sessionId && sessionId !== actuelle.id) continue

        // Toute activité de l'agent dans cette conversation montre qu'il travaille : OpenCode n'annonce
        // pas toujours « occupé » avant la fin (par exemple quand la première action est refusée).
        if (sessionId === actuelle.id && evenement.type !== 'session.status' && evenement.type !== 'session.idle') occupe = true

        switch (evenement.type) {
          case 'message.part.updated': {
            const partie = p.part
            if (!partie?.id || dejaVues.has(partie.id)) break
            if (partie.type === 'step-finish') {
              dejaVues.add(partie.id)
              misesDeCote.reussite(cleEntree(entree))
              ;memoire.retenir(demande.projet, entree)
              await envoyer({ type: 'requete', fournisseur: entree.fournisseur, modele: entree.modele, ...tokensDeRequete(partie) })
            } else if (partie.type === 'text') {
              // Texte terminé de l'agent (jamais celui de la demande) : une phrase de travail possible.
              if (!partie.time?.end || partie.synthetic || messagesUtilisateur.has(partie.messageID)) break
              const texte = String(partie.text ?? '').trim()
              if (!texte || texte === String(contenu).trim()) break
              dejaVues.add(partie.id)
              await envoyerPhrase()
              phraseEnAttente = phraseDeTravail(texte)
            } else if (partie.type === 'tool' && (partie.state?.status === 'running' || partie.state?.status === 'completed' || partie.state?.status === 'error')) {
              // L'action qui suit une phrase commence : la phrase était bien une phrase de travail.
              await envoyerPhrase()
              const etape = traduireOutil(partie)
              if (etape) {
                dejaVues.add(partie.id)
                await envoyer({ type: 'etape', ...etape })
              }
            }
            break
          }
          case 'message.updated':
            if (p.info?.role === 'user' && p.info.id) messagesUtilisateur.add(p.info.id)
            break
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
                await client.session.abort({ path: { id: actuelle.id } })
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
            const message = String(p.error?.data?.message ?? nom ?? 'Erreur inconnue')
            // Erreur passagère du modèle : le tour s'arrête là, et le travail reprendra (voir terminerOuBasculer).
            if (estPassagere(message)) return { fin: true, passagere: message }
            return { type: 'erreur', raison: 'agent', detail: couper(message) }
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
      // Première demande d'un site : un site sans aucune page n'est jamais annoncé comme terminé.
      if (pages && pages.pages === 0 && !(Array.isArray(demande.historique) && demande.historique.length)) {
        qualite.unshift({ genre: 'accueil_absent', fichier: 'index.html', cible: 'index.html' })
      }
      return { licences, pages, qualite, total: (licences?.ok === false ? licences.problemes?.length ?? 0 : 0) + qualite.length }
    }

    // Un tour peut se terminer sur une erreur du fournisseur (par exemple « quota dépassé ») sans que
    // OpenCode la signale comme une erreur : on regarde le dernier message de l'agent.
    const erreurDuTour = async () => {
      try {
        const { data: messages } = await client.session.messages({ path: { id: actuelle.id } })
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
        const { data: messages } = await client.session.messages({ path: { id: actuelle.id } })
        const dernier = [...(messages ?? [])].reverse().find((m) => m.info?.role === 'assistant')
        if (!dernier || dernier.info?.error) return false
        return !(dernier.parts ?? []).some((p) => p.type === 'text' && String(p.text ?? '').trim())
      } catch {
        return false
      }
    }

    // Un tour terminé en silence est relancé, au plus deux fois.
    const relancerSiMuet = async (resultat) => {
      for (let essais = 0; resultat.fin && !arret && !finir && essais < 2 && (await tourMuet()); essais++) {
        await envoyer({ type: 'etape', genre: 'relance', cible: '' })
        resultat = await terminerOuBasculer(await suivre(TEXTE_REPRISE))
      }
      return resultat
    }

    // Une fin de tour sur une limite atteinte : bascule vers le fournisseur suivant (ou mise en attente).
    // Une autre erreur : la demande échoue clairement, au lieu d'être annoncée comme réussie.
    const terminerOuBasculer = async (resultat) => {
      let relances = 0
      for (let essais = 0; resultat.fin && !arret && finir !== 'demande' && essais < entrees.length; essais++) {
        const message = resultat.passagere ?? (await erreurDuTour())
        if (!message) return resultat
        // Erreur passagère : le même modèle reprend le travail, deux fois au plus.
        if (estPassagere(message)) {
          if (relances < 2) {
            relances++
            essais--
            await envoyer({ type: 'etape', genre: 'relance', cible: '' })
            resultat = await suivre(TEXTE_REPRISE)
            continue
          }
          // Toujours la même panne : le modèle suivant de la liste reprend le travail (le premier est mis de côté 5 minutes).
          const suivant = disponibles().find((e) => cleEntree(e) !== cleEntree(entree))
          if (!suivant) return { type: 'erreur', raison: 'agent', detail: couper(message) }
          const jusqua = misesDeCote.mettreDeCote(cleEntree(entree), 5 * 60 * 1000)
          await envoyer({ type: 'mis_de_cote', fournisseur: entree.fournisseur, modele: entree.modele, jusqu_a: jusqua.toISOString(), message: couper(message) })
          relances = 0
          entree = suivant
          resultat = await suivre(TEXTE_REPRISE)
          continue
        }
        // Demande trop grande pour ce modèle : il est mis de côté (6 heures) et le suivant de la liste reprend le travail.
        if (estTropGrande(message)) {
          const jusqua = misesDeCote.mettreDeCote(cleEntree(entree), PAUSE_MAXIMALE_MS, false)
          await envoyer({ type: 'mis_de_cote', fournisseur: entree.fournisseur, modele: entree.modele, jusqu_a: jusqua.toISOString(), message: couper(message) })
          const suivant = disponibles()[0]
          if (!suivant) return { type: 'erreur', raison: 'agent', detail: couper(message) }
          entree = suivant
          resultat = await suivre(TEXTE_REPRISE)
          continue
        }
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
    // Dernière consigne du garde-fou : une seule fois, puis aucune vérification de plus (le compte rendu suffit).
    const finirProprement = async () => {
      finir = 'final'
      await envoyer({ type: 'etape', genre: 'fin_garde_fou', cible: '' })
      const fin = await suivre(TEXTE_FIN_GARDE_FOU)
      return fin.fin ? { fin: true, garde_fou: true } : fin
    }

    // Mode équipe : le planificateur découpe la demande en missions (JSON vérifié), chaque mission est faite par l'agent
    // de son rôle dans sa propre session, puis vérifiée par Kekeli (tests, sécurité, application qui répond), avec au
    // plus une correction. Plan inutilisable : l'agent unique habituel construit, en secours.
    const verifierParKekeli = () => ordinateurs.verifierApplication?.(demande.projet, application.pile)?.catch?.(() => null) ?? Promise.resolve(null)
    const planifier = async () => {
      let r = null
      // Limite par minute des offres gratuites : le planificateur attend un peu et réessaie (deux fois au plus).
      for (let essai = 0; essai < 3 && !arret; essai++) {
        if (essai) await dormir(attenteLimiteMs)
        r = await redigerPlan(
          { systeme: CONSIGNES_PLANIFICATEUR, consigne: consignePlanificateur({ message: demande.message, pile: application.pile }), fournisseurs: demande.fournisseurs, secours: demande.secours ?? [], maxTokens: 4000 },
          { clesPresentes, misesDeCote },
        ).catch((erreur) => ({ ok: false, erreur: erreur.message }))
        if (r?.ok || !(r?.erreur === 'aucun_fournisseur' && r?.refus?.length)) break
      }
      if (r?.consommation) {
        await envoyer({ type: 'fournisseur', fournisseur: r.consommation.fournisseur, modele: r.consommation.modele })
        await envoyer({ type: 'requete', ...r.consommation })
      }
      const missions = r?.ok ? planPropre(r.objet) : null
      return missions && nouvelle && !ordinateur.restaure ? avecDonneesEssai(avecAccueil(missions, application.pile)) : missions
    }
    // Une mission arrêtée par la limite par minute d'un fournisseur gratuit (attente courte) : elle attend et reprend
    // elle-même, dans sa session, au lieu d'arrêter toute l'équipe ; le temps d'attente ne compte pas dans sa durée.
    const reprendreApresAttente = async (r) => {
      for (let n = 0; r?.type === 'en_attente' && n < 15 && !arret && !finir; n++) {
        const attente = new Date(r.reprise_vers).getTime() - (misesDeCote.horloge?.() ?? Date.now())
        if (!(attente <= ATTENTE_MISSION_MAX_MS)) return r
        const pause = Math.max(attenteLimiteMs, attente + 1000)
        await dormir(pause)
        if (limiteMission) limiteMission += pause
        entree = disponibles()[0] ?? entree
        r = await terminerOuBasculer(await suivre(TEXTE_REPRISE))
      }
      return r
    }
    const travailEnEquipe = async () => {
      await envoyer({ type: 'narration', texte: 'Je prépare le plan de travail de l’équipe.' })
      const missions = await planifier()
      if (arret) return arretee()
      if (!missions) {
        await envoyer({ type: 'equipe', etat: 'secours' })
        return relancerSiMuet(await terminerOuBasculer(await suivre(texte)))
      }
      await envoyer({ type: 'equipe', etat: 'plan', missions })
      const contexte = texteApplication(application, preparationApplication)
      const faits = []
      const faites = []
      for (const [i, mission] of missions.entries()) {
        if (arret || finir) break
        const { data: enfant, error } = await client.session.create({ body: { parentID: session.id, title: `${mission.code} ${mission.titre}`.slice(0, 80) } })
        if (error || !enfant) {
          faites.push({ ...mission, etat: 'echouee', probleme: 'conversation impossible à créer' })
          continue
        }
        actuelle = enfant
        agentCourant = ROLES[mission.role].agent
        compteurMission = compteur()
        const debut = Date.now()
        limiteMission = debut + DUREE_MISSION_MS
        await envoyer({ type: 'equipe', etat: 'mission', code: mission.code, statut: 'en_cours', numero: i + 1, total: missions.length })
        await envoyer({ type: 'narration', texte: `Mission ${i + 1} sur ${missions.length} : ${mission.titre}.` })
        let r = await reprendreApresAttente(await relancerSiMuet(await terminerOuBasculer(await suivre(texteMission(mission, { contexte, demande: demande.message, faits, faites, numero: i + 1, total: missions.length })))))
        const reponse = await derniereReponse(client, enfant.id)
        const { faits: nouveaux, bilan } = faitsDuBilan(reponse)
        let etat = 'faite'
        let probleme = ''
        if (r.type === 'en_attente' || ['ordinateur', 'flux'].includes(r.raison)) {
          actuelle = session
          agentCourant = null
          limiteMission = null
          compteurMission = null
          return r
        }
        if (!r.fin) {
          etat = r.raison === 'delai_mission' ? 'delai' : 'echouee'
          probleme = String(r.detail ?? '').slice(0, 300)
        } else if (!arret && !finir) {
          // La vérification indépendante : les tests, la sécurité et l'application qui répond, par Kekeli (sans modèle).
          let verification = await verifierParKekeli()
          for (let essai = 0; verification && problemesApplication(verification) > 0 && essai < CORRECTIONS_MAX && !arret && !finir; essai++) {
            await envoyer({ type: 'etape', genre: 'verification_correction', cible: String(problemesApplication(verification)) })
            limiteMission = Date.now() + DUREE_MISSION_MS / 2
            r = await reprendreApresAttente(await terminerOuBasculer(await suivre(`${texteCorrectionApplication(verification)}\n\nCorrige ce qui concerne ta mission ou ce qu'elle a cassé, sans rien ajouter d'autre.`)))
            verification = r.fin ? await verifierParKekeli() : verification
          }
          if (verification && problemesApplication(verification) > 0) {
            etat = 'echouee'
            probleme = [!verification.tests.ok ? 'tests en échec' : '', verification.securite.length ? 'sécurité' : '', !verification.sante.ok ? 'application qui ne répond pas' : ''].filter(Boolean).join(', ')
          } else if (verification) {
            etat = 'verifiee'
          }
        }
        for (const fait of nouveaux) faits.push({ ...fait, mission: mission.code })
        if (probleme) faits.push({ type: 'probleme', titre: `Mission ${mission.code} non vérifiée`, resume: probleme, mission: mission.code })
        faites.push({ ...mission, etat, probleme, bilan: bilan || sansBlocFaits(reponse).split('\n')[0]?.slice(0, 300) || '' })
        await envoyer({ type: 'equipe', etat: 'mission', code: mission.code, statut: etat, probleme, bilan: faites.at(-1).bilan, faits: nouveaux, secondes: Math.round((Date.now() - debut) / 1000), ...compteurMission.lire() })
        actuelle = session
        agentCourant = null
        limiteMission = null
        compteurMission = null
      }
      if (arret) return arretee()
      if (finir === 'demande') return finirProprement()
      // La validation finale, par l'agent habituel dans la conversation du projet (qui reçoit la demande et le bilan).
      await envoyer({ type: 'narration', texte: 'Je vérifie que tout ce que vous avez demandé est prêt.' })
      let fin = await reprendreApresAttente(await relancerSiMuet(await terminerOuBasculer(await suivre(`${texte}\n\n${texteValidationFinale(faites)}`))))
      // Les restes des « demandes » du point de départ, vus par Kekeli dans le code : une seule correction.
      if (fin.fin && !arret && !finir && nouvelle && (await restesDuDepart())) {
        await envoyer({ type: 'narration', texte: 'Je retire ce qui reste du point de départ.' })
        fin = await reprendreApresAttente(await terminerOuBasculer(await suivre(TEXTE_RESTES)))
      }
      return fin
    }
    const restesDuDepart = async () => {
      const commande = RESTES_DU_DEPART[application.pile]
      if (!commande || parleDeDemandes(demande.message) || !ordinateurs.executerDansProjet) return false
      const r = await ordinateurs.executerDansProjet(demande.projet, `${commande} ; true`, { timeoutMs: 30000 }).catch(() => null)
      return String(r?.stdout ?? '').trim().length > 0
    }

    const travail = async () => {
      let premier = equipe && !demande.reprise
        ? await travailEnEquipe()
        : await relancerSiMuet(await terminerOuBasculer(await suivre(equipe && demande.reprise ? `${texteAvecContexte(demande.message, demande)}\n\n[Mode équipe de Kekeli] Le travail de l'équipe a été interrompu. Regarde ce qui est déjà fait dans le dossier du projet, termine ce qui manque de la demande, lance les tests, puis écris ton message final au client.` : texte)))
      if (arret) return arretee()
      if (finir === 'demande') return finirProprement()
      // Une action refusée arrête l'agent net : on le relance pour qu'il en tienne compte et le dise.
      if (premier.fin && refus.length && !finir) {
        const actions = refus.splice(0).map((cible) => `« ${cible} »`).join(', ')
        premier = await terminerOuBasculer(await suivre(texteApresRefus(actions)))
      }
      if (arret) return arretee()
      // Mode développeur : pas de vérification de site web ; l'agent lance lui-même les tests du projet.
      if (depot) return premier
      if (application) return verifierLApplication(premier)
      if (!premier.fin || (!ordinateurs.verifierLicences && !ordinateurs.verifierQualite)) return premier
      let verification = await verifier()
      if (verification.total > 0) {
        await envoyer({ type: 'etape', genre: 'verification_correction', cible: String(verification.total) })
        const second = await terminerOuBasculer(await suivre(texteDeCorrection(verification)))
        if (arret) return arretee()
        if (finir === 'demande') return finirProprement()
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

    // Application web : tests, sécurité et application qui répond ; en cas de problème, l'agent corrige une fois.
    const verifierLApplication = async (premier) => {
      if (!premier.fin || !ordinateurs.verifierApplication) return premier
      let verification = await ordinateurs.verifierApplication(demande.projet, application.pile).catch((erreur) => {
        journal.warn(`Vérification de l'application : ${erreur.message}`)
        return null
      })
      if (verification && problemesApplication(verification) > 0) {
        await envoyer({ type: 'etape', genre: 'verification_correction', cible: String(problemesApplication(verification)) })
        const second = await terminerOuBasculer(await suivre(`${texteCorrectionApplication(verification)}\n\n${RAPPEL_COMPTE_RENDU}`))
        if (arret) return arretee()
        if (finir === 'demande') return finirProprement()
        if (!second.fin) return second
        verification = await ordinateurs.verifierApplication(demande.projet, application.pile).catch(() => null)
      }
      if (verification) {
        const { tests, securite, sante } = verification
        await envoyer({ type: 'etape', genre: tests.ok ? 'tests_ok' : 'tests_echec', cible: tests.resume ? `${tests.commande} : ${tests.resume}` : tests.commande })
        await envoyer({ type: 'etape', genre: securite.length ? 'securite_probleme' : 'securite_ok', cible: securite.map((p) => couper(p.detail, 140)).join(' · ') })
        await envoyer({ type: 'etape', genre: sante.ok ? 'application_ok' : 'application_erreur', cible: sante.code ? `code ${sante.code}` : 'aucune réponse' })
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

    // « Voir les modifications » : ce qui a changé pendant la demande (avant l'enregistrement Git du mode développeur).
    const lireChangements = () =>
      ordinateurs.modifications?.(demande.projet)?.catch?.((erreur) => {
        journal.warn(`Modifications du projet : ${erreur.message}`)
        return null
      })
    let changements = await lireChangements()
    // Une application neuve dont l'accueil est resté celui du point de départ (« Envoyez votre demande… ») : le client
    // verrait l'écran de départ présenté comme fini (mesure du 15 novembre 2026 avec un modèle gratuit). Une seule
    // relance pour le remplacer ; sinon, le travail s'arrête avec un message clair, plus bas.
    const neuve = nouvelle && !ordinateur.restaure && !ordinateur.repris && !depot && !demande.reprise
    // Mode équipe : l'accueil peut être construit dans un autre fichier que celui du point de départ (banc du 18 novembre
    // 2026, deux fausses alertes) ; Kekeli regarde donc ce que la page d'accueil affiche vraiment dans l'ordinateur.
    const accueilResteCeluiDuDepart = async () => (equipe ? accueilAfficheDuDepart(ordinateurs, demande.projet, application) : accueilDuDepart(changements, application))
    if (resultat.fin && neuve && !arret && (await accueilResteCeluiDuDepart())) {
      await envoyer({ type: 'etape', genre: 'verification_correction', cible: 'accueil' })
      await suivre(`${TEXTE_ACCUEIL}\n\n${RAPPEL_COMPTE_RENDU}`).catch(() => null)
      changements = await lireChangements()
    }
    if (changements) await envoyer({ type: 'modifications', ...changements })

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

    // Une construction neuve où le modèle n'a écrit aucun fichier du projet (modèle trop faible pour ce travail) :
    // jamais l'écran de départ (« Mon site ») présenté comme fini. Le travail s'arrête avec un message clair.
    if (resultat.fin && neuve && (travailVide(changements) || (await accueilResteCeluiDuDepart()))) {
      await envoyer({ type: 'erreur', raison: 'trop_lourd', detail: 'Aucun fichier du projet créé ni modifié pendant la construction.' })
      return
    }

    if (resultat.fin) {
      let reponse = await derniereReponse(client, session.id)
      // Comme Claude Code, chaque création ou modification finit par un compte rendu : s'il manque, une seule relance.
      if (demande.compte_rendu && !depot && !arret && !aUnCompteRendu(reponse)) {
        const fin = await suivre(TEXTE_COMPTE_RENDU).catch(() => null)
        if (fin?.fin) reponse = (await derniereReponse(client, session.id)) || reponse
      }
      await envoyer({ type: 'terminee', reponse: demande.depot || demande.application?.installable ? reponse : sansMotApplication(reponse), ...(resultat.garde_fou || finir ? { garde_fou: true } : {}) })
    } else {
      if (resultat.raison === 'duree') await client.session.abort({ path: { id: session.id } }).catch(() => {})
      await envoyer(resultat)
    }
  } catch (erreur) {
    journal.error(`Tâche du projet ${demande.projet} : ${erreur.message}`)
    await envoyer({ type: 'erreur', raison: 'interne', detail: couper(erreur.message) })
  } finally {
    clearTimeout(minuterie)
    arreterRelais?.()
    // Fermeture du flux sans l'attendre : une lecture peut encore être en cours.
    abonnement?.stream?.return?.()?.catch?.(() => {})
  }
}

/** Fichiers qui ne montrent pas un vrai travail sur le projet (licences, journaux, base d'essai, réglages locaux). */
const FICHIERS_TECHNIQUES = /^(LICENCES\.md|\.env(\..*)?|.*\.log|.*\.sqlite|database\/database\.sqlite|\.kekeli\/.*)$/i

/**
 * Le modèle n'a rien construit : aucun fichier du projet ajouté ni modifié depuis la photo « avant » (les fichiers
 * techniques ne comptent pas). Sans comparaison possible (null), on ne bloque jamais le travail.
 */
export function travailVide(changements) {
  if (!changements || !Array.isArray(changements.fichiers)) return false
  return changements.fichiers.filter((f) => f?.etat !== 'supprime' && !FICHIERS_TECHNIQUES.test(String(f?.chemin ?? ''))).length === 0
}

/** Une phrase de l'accueil du point de départ (runner/src/applications/modeles.json, « accroche »). */
export const PHRASE_ACCUEIL_DU_DEPART = 'Envoyez votre demande en ligne'

/**
 * Mode équipe : la page d'accueil réellement servie dans l'ordinateur montre encore celle du point de départ. Sans
 * moyen de la lire (ordinateur, port inconnu), jamais vrai : le travail n'est jamais bloqué sur un doute.
 */
export async function accueilAfficheDuDepart(ordinateurs, projet, application) {
  const port = { laravel: 8000, nextjs: 3000 }[application?.pile]
  if (!port || !ordinateurs?.executerDansProjet) return false
  const r = await ordinateurs.executerDansProjet(projet, `curl -s -m 20 http://127.0.0.1:${port}/ ; true`, { timeoutMs: 40000 }).catch(() => null)
  const page = String(r?.stdout ?? '')
  return page.length > 0 && page.includes(PHRASE_ACCUEIL_DU_DEPART)
}

/** La page d'accueil posée par le point de départ de chaque technologie d'application. */
const ACCUEIL_DU_DEPART = { laravel: 'resources/views/accueil.blade.php', nextjs: 'app/page.js' }

/** Relance quand l'accueil d'une application neuve n'a pas été remplacé. */
export const TEXTE_ACCUEIL =
  "La page d'accueil de l'application est encore celle du point de départ (« Envoyez votre demande en ligne… ») et l'administration montre encore les exemples de départ. Remplace l'accueil par celui de l'application demandée (son nom, ce qu'elle propose, les vraies données de la demande), adapte l'administration et le tableau de bord à ses données, ajoute les données d'essai utiles (par exemple les prestations et leurs prix), puis vérifie chaque page."

/**
 * Une application neuve (Laravel ou Next.js) dont la page d'accueil du point de départ n'a pas été modifiée : le client
 * verrait l'écran de départ. Sans comparaison possible, ou pour un site, un agent ou une automatisation, jamais vrai.
 */
export function accueilDuDepart(changements, application) {
  const accueil = ACCUEIL_DU_DEPART[application?.pile]
  if (!accueil || !changements || !Array.isArray(changements.fichiers)) return false
  return !changements.fichiers.some((f) => f?.chemin === accueil && f?.etat !== 'supprime')
}
