// Mémoire d'une automatisation qui VÉRIFIE régulièrement un service (toutes les X minutes) quand ce service n'envoie
// pas d'événement (une boîte e-mail, un tableau) : les identifiants déjà traités sont gardés dans .etat/vus.json, que
// GitHub Actions garde d'un lancement à l'autre (actions/cache). Jamais de contenu, seulement des identifiants.
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs'

export const FICHIER_ETAT = '.etat/vus.json'
const MAXIMUM = 2000

export function lireVus({ lire = (f) => readFileSync(f, 'utf8') } = {}) {
  try {
    const liste = JSON.parse(lire(FICHIER_ETAT))
    return new Set(Array.isArray(liste) ? liste.map(String) : [])
  } catch {
    return new Set()
  }
}

/** Les nouveaux éléments seulement (jamais deux fois le même), puis la mémoire mise à jour. */
export function nouveaux(elements, vus, identifiant = (e) => e.id) {
  return elements.filter((e) => !vus.has(String(identifiant(e))))
}

export function garderVus(vus, { ecrire = (f, t) => { mkdirSync('.etat', { recursive: true }); writeFileSync(f, t) } } = {}) {
  ecrire(FICHIER_ETAT, JSON.stringify([...vus].slice(-MAXIMUM)))
}
