// La course entre fournisseurs (chantier « premier mot », décision du porteur du projet du 15 novembre 2026) : si le
// premier fournisseur n'a rien envoyé au bout d'un court délai (1 000 millisecondes au départ, réglable dans
// Administration → Réglages), la même demande part chez un deuxième fournisseur, plus rapide ; le premier des deux qui
// envoie un contenu (un mot ou un appel d'outil) gagne, et l'autre demande est annulée tout de suite, pour ne pas
// payer une réponse entière inutile. Un seul texte s'affiche, jamais deux : seuls les morceaux du gagnant passent.
//
// La course ne sert qu'à la PREMIÈRE requête d'un message (celle dont le client attend le premier mot) ; elle ne lance
// jamais deux créations : un outil n'est exécuté qu'une fois, par le gagnant, après la course.

import { lireFlux } from './flux.js'

/**
 * Lance la course. « concurrents » : le principal puis le coureur, chacun { entree, url, entetes, corps } (requêtes déjà
 * prêtes en mode « mot par mot »). Rend { gagnant, reponse, donneesFlux, contenu, lancee, perdant } : « gagnant » est le
 * concurrent retenu ; « reponse » sa réponse HTTP ; « donneesFlux » sa réponse reconstruite (ou « contenu », le texte
 * d'une réponse d'un bloc ou d'une erreur) ; « lancee » dit si le coureur est parti ; « perdant » le concurrent annulé.
 */
export async function courir({ principal, coureur, delaiMs, appeler = fetch, surMorceau = () => {}, surLancement = () => {}, maintenant = () => Date.now() }) {
  let gagnant = null
  let lancee = false
  const tampons = new Map()
  const etats = new Map()
  const depart = maintenant()

  const choisir = (c) => {
    if (gagnant) return
    gagnant = c
    gagnant.ms_premier_contenu = maintenant() - depart
    // L'autre demande est annulée tout de suite : le fournisseur arrête d'écrire (et de facturer) sa réponse.
    for (const autre of [principal, coureur]) if (autre && autre !== c) etats.get(autre)?.controleur.abort()
    for (const morceau of tampons.get(c) ?? []) surMorceau(morceau)
    tampons.delete(c)
  }

  const lire = async (c) => {
    const controleur = new AbortController()
    etats.set(c, { controleur })
    const minuterie = setTimeout(() => controleur.abort(), 60000)
    try {
      const reponse = await appeler(c.url, { method: 'POST', headers: c.entetes, body: JSON.stringify(c.corps), signal: controleur.signal })
      if (!reponse.ok || !/event-stream/i.test(reponse.headers?.get?.('content-type') ?? '')) {
        const contenu = await reponse.text()
        // Une réponse d'un bloc réussie compte comme un premier contenu ; une erreur ne gagne jamais.
        if (reponse.ok) choisir(c)
        return { c, reponse, contenu, echec: !reponse.ok }
      }
      const donneesFlux = await lireFlux(c.entree.fournisseur, reponse.body, (morceau) => {
        if (gagnant === c) surMorceau(morceau)
        else if (!gagnant) (tampons.get(c) ?? tampons.set(c, []).get(c)).push(morceau)
      }, { surDebut: () => choisir(c) })
      // Une réponse finie sans aucun contenu (cas rare) : elle gagne si personne n'a encore gagné.
      choisir(c)
      return { c, reponse, donneesFlux }
    } catch (erreur) {
      return { c, erreur, annule: gagnant && gagnant !== c }
    } finally {
      clearTimeout(minuterie)
    }
  }

  const promesses = new Map([[principal, lire(principal)]])
  // Le coureur ne part que si le principal n'a rien envoyé au bout du délai.
  let minuterieCourse = null
  const depart2 = coureur
    ? new Promise((fini) => {
        minuterieCourse = setTimeout(() => {
          if (!gagnant) {
            lancee = true
            surLancement()
            promesses.set(coureur, lire(coureur))
          }
          fini()
        }, Math.max(0, delaiMs))
      })
    : Promise.resolve()

  // Le principal échoue avant le délai : le coureur part aussitôt (le client n'attend pas pour rien).
  const premier = await Promise.race([promesses.get(principal), depart2.then(() => null)])
  if (premier && (premier.erreur || premier.echec) && !gagnant && coureur && !lancee) {
    clearTimeout(minuterieCourse)
    lancee = true
    surLancement()
    promesses.set(coureur, lire(coureur))
  }
  clearTimeout(minuterieCourse)

  const resultats = await Promise.all(promesses.values())
  const duGagnant = gagnant ? resultats.find((r) => r.c === gagnant) : null
  // Personne n'a gagné (les deux ont échoué) : le résultat du principal, que la boucle habituelle traite comme avant.
  const retenu = duGagnant ?? resultats.find((r) => r.c === principal)
  const perdant = lancee ? (retenu.c === principal ? coureur : principal) : null
  return { ...retenu, gagnant: retenu.c, lancee, perdant }
}
