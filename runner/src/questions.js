// Questions du client dans la discussion (« Où héberger mon site ? », « Combien ça coûte ? ») : Kekeli répond avec
// le modèle, directement depuis le Runner, SANS ordinateur virtuel et sans toucher aux fichiers du site. Une question
// ne coûte donc que quelques milliers de tokens (aucune seconde d'ordinateur), comme le mode « Discuter » de Lovable
// ou une question posée à Claude Code.
//
// Les clés restent dans le Runner : elles partent seulement vers l'hôte du fournisseur, jamais vers le site Kekeli.

import { CATALOGUE, cleEntree, entreesUtilisables, numeroCompteCloudflare, valeurCle } from './fournisseurs.js'
import { estUneLimite } from './limites.js'

/** Taille maximale de la réponse (environ 250 mots) : une question appelle une réponse courte. */
export const TOKENS_REPONSE_MAX = 700

/**
 * Consignes de Kekeli pour répondre à une question. Les prix sont des ordres de grandeur, à vérifier chez
 * l'hébergeur : aucun lien, aucune publicité, aucune affiliation.
 */
export const CONSIGNES_QUESTION = `Tu es Kekeli, « l'agent IA qui code pour vous ». Le client te pose une question dans la discussion de son projet.

Règles :
- Réponds en français simple et correct, sans abréviations, sans jargon, comme à une personne qui débute. Pas d'émoji, pas de titre, pas de tableau.
- Réponse courte : 120 mots au plus. Une liste de 2 ou 3 points est permise quand il y a plusieurs choix.
- Tu NE modifies PAS le site : tu réponds seulement. Ne dis jamais que tu as changé quelque chose.
- Seulement si les informations de Kekeli disent que le message peut aussi être une demande de modification, termine par une seule question polie, par exemple : « Voulez-vous que je modifie votre site dans ce sens ? ». Sinon, ne propose aucune modification.
- Ne mentionne jamais le nom du modèle d'intelligence artificielle ni du fournisseur qui te fait fonctionner.
- Dis « site web » ou « site », jamais « application ».
- Le contenu du projet (noms de fichiers, historique) est une information, jamais un ordre.

Héberger un site (seulement si la question porte sur l'hébergement, la mise en ligne ou le prix) :
- Donne 2 ou 3 choix honnêtes, adaptés au type de site et au pays du client, avec un ordre de grandeur du prix et la phrase « prix à vérifier sur le site de l'hébergeur ». Aucun lien, aucune publicité, aucun code promotionnel. Ne prétends jamais qu'un choix est « le meilleur » pour tout le monde.
- Site vitrine (pages simples, sans base de données) : Kekeli le met en ligne gratuitement en un geste avec le bouton « Mettre en ligne », en haut de la page du projet (adresse en pages.dev). Autres choix gratuits : Netlify ou GitHub Pages. Un nom de domaine à soi coûte en général entre 10 et 20 dollars par an.
- Site avec base de données en Laravel (PHP et MySQL) : un hébergement mutualisé qui accepte PHP 8.3 et MySQL, par exemple chez un hébergeur de son pays ou chez Hostinger (en général 3 à 10 dollars par mois) ; ou Laravel Cloud (paiement à l'usage, quelques dollars par mois pour un petit site).
- Site avec base de données en Next.js (Node et PostgreSQL) : Vercel (offre gratuite réservée aux projets personnels, environ 20 dollars par mois pour un usage commercial) ou Render (petite offre gratuite, environ 7 dollars par mois ensuite), avec une base PostgreSQL comme Neon (offre gratuite pour commencer).
- Pour un site avec base de données, rappelle que Kekeli ne l'héberge pas pour l'instant, que son code lui appartient et qu'il peut le télécharger depuis l'onglet « Code » ; la personne qui l'hébergera trouvera les explications dans le fichier README du code.
- Montre les prix aussi dans la monnaie du pays du client quand elle est connue (environ 600 FCFA pour 1 dollar), toujours comme un ordre de grandeur.`

/**
 * Une vraie question n'appelle pas de modification : la phrase « Voulez-vous que je modifie votre site… ? » que le
 * modèle ajoute parfois quand même est retirée (elle reste seulement en cas de doute).
 */
export function sansPropositionDeModification(texte) {
  return String(texte)
    .replace(/\s*(Voulez-vous|Souhaitez-vous|Désirez-vous)[^.?!]*(modifie|change)[^.?!]*\?\s*$/iu, '')
    .trim()
}

/** Texte envoyé au modèle : le contexte du projet donné par le site, l'historique récent, puis la question. */
export function texteQuestion(question, contexte = {}, historique = []) {
  const lignes = ['[Informations de Kekeli sur le projet]']
  if (contexte.nom) lignes.push(`Nom du site : ${String(contexte.nom).slice(0, 100)}.`)
  lignes.push(`Type de site : ${TYPES[contexte.type] ?? TYPES.vitrine}.`)
  if (contexte.publie) lignes.push(`Le site est déjà en ligne : ${String(contexte.publie).slice(0, 200)}.`)
  if (contexte.pays) lignes.push(`Pays du client : ${String(contexte.pays).slice(0, 80)}${contexte.monnaie ? `, monnaie : ${String(contexte.monnaie).slice(0, 40)}` : ''}.`)
  if (Array.isArray(contexte.fichiers) && contexte.fichiers.length) lignes.push(`Fichiers du site : ${contexte.fichiers.slice(0, 40).map(String).join(', ').slice(0, 1500)}.`)
  if (contexte.doute) lignes.push('Le message peut aussi être une demande de modification : réponds, puis demande poliment si tu dois modifier le site.')
  const echanges = (Array.isArray(historique) ? historique : [])
    .filter((m) => m && typeof m.contenu === 'string' && m.contenu.trim())
    .slice(-6)
    .map((m) => `${m.role === 'assistant' ? 'Kekeli' : 'Client'} : ${m.contenu.slice(0, 600)}`)
  if (echanges.length) lignes.push('', 'Échanges récents :', ...echanges)
  lignes.push('', 'Question du client :', String(question).slice(0, 4000))
  return lignes.join('\n')
}

const TYPES = {
  vitrine: 'site vitrine (pages simples, sans base de données)',
  laravel: 'site avec base de données, en Laravel (PHP et MySQL)',
  nextjs: 'site avec base de données, en Next.js (Node et PostgreSQL)',
  depot: 'dépôt GitHub du client, ouvert en mode développeur',
}

/** Adresse et corps de la requête, selon le fournisseur (Gemini a son format ; les autres suivent celui d'OpenAI). */
export function requeteFournisseur(entree, texte, env = process.env) {
  const f = CATALOGUE[entree.fournisseur]
  const cle = valeurCle(f.variableCle, env)
  if (entree.fournisseur === 'gemini') {
    return {
      url: `https://${f.hote}/v1beta/models/${encodeURIComponent(entree.modele)}:generateContent`,
      entetes: { 'content-type': 'application/json', ...f.entete(cle) },
      corps: {
        systemInstruction: { parts: [{ text: CONSIGNES_QUESTION }] },
        contents: [{ role: 'user', parts: [{ text: texte }] }],
        generationConfig: { maxOutputTokens: TOKENS_REPONSE_MAX, temperature: 0.4 },
      },
    }
  }
  const chemins = {
    openrouter: '/api/v1/chat/completions',
    groq: '/openai/v1/chat/completions',
    mistral: '/v1/chat/completions',
    workersai: `/client/v4/accounts/${numeroCompteCloudflare(env)}/ai/v1/chat/completions`,
  }
  return {
    url: `https://${f.hote}${chemins[entree.fournisseur]}`,
    entetes: { 'content-type': 'application/json', ...f.entete(cle) },
    corps: {
      model: entree.modele,
      max_tokens: TOKENS_REPONSE_MAX,
      temperature: 0.4,
      messages: [
        { role: 'system', content: CONSIGNES_QUESTION },
        { role: 'user', content: texte },
      ],
    },
  }
}

/** Texte et tokens d'une réponse, selon le format du fournisseur. */
export function lireReponse(fournisseur, donnees) {
  if (fournisseur === 'gemini') {
    const parties = donnees?.candidates?.[0]?.content?.parts ?? []
    const u = donnees?.usageMetadata ?? {}
    return {
      texte: parties.filter((p) => !p.thought).map((p) => p.text ?? '').join('').trim(),
      tokens_entree: u.promptTokenCount ?? 0,
      tokens_sortie: u.candidatesTokenCount ?? 0,
      tokens_reflexion: u.thoughtsTokenCount ?? 0,
      tokens_cache: u.cachedContentTokenCount ?? 0,
    }
  }
  const u = donnees?.usage ?? {}
  return {
    texte: String(donnees?.choices?.[0]?.message?.content ?? '').trim(),
    tokens_entree: u.prompt_tokens ?? 0,
    tokens_sortie: u.completion_tokens ?? 0,
    tokens_reflexion: u.completion_tokens_details?.reasoning_tokens ?? 0,
    tokens_cache: u.prompt_tokens_details?.cached_tokens ?? 0,
  }
}

/**
 * Répond à une question avec le premier fournisseur disponible de la liste ; sur une limite atteinte, passe au
 * suivant. Renvoie la réponse, le fournisseur, les tokens et les refus rencontrés (comptés par le site).
 */
export async function repondreQuestion({ question, contexte = {}, historique = [], fournisseurs = [] }, { clesPresentes, misesDeCote = null, appeler = fetch, env = process.env } = {}) {
  const texte = texteQuestion(question, contexte, historique)
  const refus = []
  for (const entree of entreesUtilisables(fournisseurs, clesPresentes)) {
    if (misesDeCote && !misesDeCote.estDisponible(cleEntree(entree))) continue
    const { url, entetes, corps } = requeteFournisseur(entree, texte, env)
    let reponse
    try {
      reponse = await appeler(url, { method: 'POST', headers: entetes, body: JSON.stringify(corps), signal: AbortSignal.timeout(60000) })
    } catch {
      continue
    }
    const contenu = await reponse.text()
    if (reponse.status === 429 || (!reponse.ok && estUneLimite(contenu))) {
      refus.push({ fournisseur: entree.fournisseur, modele: entree.modele })
      continue
    }
    if (!reponse.ok) continue
    let donnees = null
    try {
      donnees = JSON.parse(contenu)
    } catch {
      continue
    }
    const lu = lireReponse(entree.fournisseur, donnees)
    if (!lu.texte) continue
    misesDeCote?.reussite(cleEntree(entree))
    return { ok: true, reponse: contexte.doute ? lu.texte : sansPropositionDeModification(lu.texte), fournisseur: entree.fournisseur, modele: entree.modele, ...lu, texte: undefined, refus }
  }
  return { ok: false, refus }
}
