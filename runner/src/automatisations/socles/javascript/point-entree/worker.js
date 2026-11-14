// Point d'entrée des événements : un petit service Cloudflare Workers, sur VOTRE compte Cloudflare (offre gratuite).
// Il reçoit un événement (un formulaire rempli, une commande, un message), le vérifie, puis lance votre automatisation
// sur votre dépôt GitHub (événement « repository_dispatch »). Il ne garde aucune donnée et ne contient aucune clé :
// les valeurs secrètes sont des « secrets » du Worker, placés par le fichier .github/workflows/point-entree.yml.
//
// Trois façons d'appeler ce point d'entrée, toutes vérifiées :
// 1. Avec une SIGNATURE (un serveur : votre application, une boutique) : en-tête
//    « X-Kekeli-Signature: t=<secondes>,v1=<HMAC-SHA256 en hexadécimal de "<t>.<corps>" avec ENTREE_SECRET> ».
//    Une signature de plus de 5 minutes est refusée (un appel copié ne peut pas être rejoué plus tard).
// 2. Avec le SECRET dans l'adresse (« ?jeton=… ») ou dans l'en-tête « Authorization: Bearer … », pour un service qui
//    ne sait envoyer qu'à une adresse.
// 3. Depuis un FORMULAIRE PUBLIC d'un site (le navigateur ne peut garder aucun secret) : seulement depuis les adresses
//    de FORMULAIRE_ORIGINES, champ piège « site_web » vide, et 5 envois au plus par 10 minutes pour une même adresse IP.
// Un même événement n'est jamais lancé deux fois (identifiant gardé 24 heures dans Workers KV, la mémoire gratuite de
// Cloudflare : 1 000 écritures par jour, soit environ 500 événements par jour).

import { traiter } from './coeur.js'

export default {
  async fetch(requete, env, ctx) {
    return traiter(requete, env, { memoire: env.MEMOIRE ?? null, appeler: fetch, maintenant: Date.now() })
  },
}

