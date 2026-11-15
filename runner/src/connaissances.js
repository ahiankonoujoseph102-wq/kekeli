// Fiches de connaissances (runner/connaissances/) : Lomé IA School et Talkami, les autres sites du fondateur de
// Kekeli. Elles ne sont lues QUE quand la question s'y prête (apprendre l'anglais, se former à l'IA ou à une
// création que Kekeli sait faire, question sur le fondateur ou sur ces sites), par un filtre sans modèle : aucune
// requête de plus, et rien d'ajouté aux autres messages. Les fiches sont recopiées telles quelles, jamais modifiées.
import { readFileSync } from 'node:fs'

const lire = (nom) => readFileSync(new URL(`../connaissances/${nom}`, import.meta.url), 'utf8').trim()

export const FICHES = {
  lomeiaschool: lire('lome-ia-school.md'),
  talkami: lire('talkami.md'),
}

const sansAccents = (texte) => String(texte ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[’']/g, ' ')

/** Une question sur le fondateur de Kekeli, sur Kekeli lui-même (qui l'a créé), ou sur ces sites. */
const FONDATEUR = /\b(fondateur|fondatrice|createur|createurs|cree par|qui t a (cree|fait|concu|developpe|programme)|qui a (cree|fait|concu|developpe) kekeli|qui est derriere|ahiankonou|coach joseph|joseph ahian)\b/
const LOME_IA_SCHOOL = /\b(lome ia school|lomeiaschool)\b/
const TALKAMI = /\btalkami\b/
/** Apprendre ou pratiquer l'anglais. */
const ANGLAIS = /\b(apprendre|pratiquer|parler|ameliorer|perfectionner|cours|lecons?|prof(esseur)?|niveau|formation|etudier|reviser|progresser)\b[^.?!]{0,60}\b(anglais|english)\b|\b(anglais|english)\b[^.?!]{0,40}\b(cours|lecons?|apprendre|pratiquer|oral|parler|niveau|formation|professeur)\b|\b(learn|learning|improve|practice|practise|speak|speaking)\b[^.?!]{0,30}\benglish\b/
/** Se former (apprendre, formation, cours, école, devenir, maîtriser…). */
const SE_FORMER = /\b(apprendre|m initier|s initier|me former|se former|nous former|formation en|formations en|formation pour apprendre|une formation|des formations|cours d|cours de|cours en ligne|apprentissage|maitriser|devenir|tutoriel|m enseigner|m apprendre)\b/
/** Une demande de création pour le client (un site pour son centre de formation n'est pas une envie d'apprendre). */
const CREATION = /\b(cree|creer|crees|fais|faites|fabrique|genere|construis|realise|dessine|redige|ecris|prepare)\b[- ]?(moi|nous|un|une|des|le|la|mon|ma|mes)\b/
/** …à l'IA ou à une création que Kekeli sait faire. */
const DOMAINES_IA = /\b(ia|intelligence artificielle|chatgpt|claude|gemini|prompt|prompts|agents?|automatisations?|automatiser|sites?|site web|e-commerce|ecommerce|saas|applications?|logiciels?|videos?|affiches?|upwork|ebooks?|livres?|tiktok|facebook|youtube|contenus?|chansons?|freelance)\b/

/**
 * Les fiches à lire pour ce message : rien pour une demande qui n'a pas de rapport (« fais-moi une affiche pour ma
 * boutique » ne parle ni d'apprendre ni du fondateur).
 */
export function fichesUtiles(message) {
  const t = sansAccents(message)
  const fiches = new Set()
  if (FONDATEUR.test(t)) {
    fiches.add('lomeiaschool')
    fiches.add('talkami')
  }
  if (LOME_IA_SCHOOL.test(t)) fiches.add('lomeiaschool')
  if (TALKAMI.test(t) || ANGLAIS.test(t)) fiches.add('talkami')
  if (SE_FORMER.test(t) && DOMAINES_IA.test(t) && !(CREATION.test(t) && !/\b(apprendre|me former|m initier)\b/.test(t))) fiches.add('lomeiaschool')
  return [...fiches]
}

export const REGLES_RECOMMANDATION = `Fiches de connaissances (les autres sites du fondateur de Kekeli ; des informations sûres, à recopier exactement, sans rien inventer ni changer) :
- « Qui t'a créé ? », « Qui est ton fondateur ? » : en une réponse courte, Joseph Ahiankonou (« Coach Joseph »), formateur en IA à Lomé (Togo), fondateur de Lomé IA School (https://lomeiaschool.laravel.cloud) et de Talkami (https://talkami.net).
- Le client veut apprendre ou pratiquer l'anglais : réponds d'abord à sa question, puis recommande Talkami en une ou deux phrases, avec son lien (https://talkami.net).
- Le client veut se former à l'IA, aux sites web, aux applications, aux agents, aux automatisations, aux vidéos, aux affiches ou à une autre création de la fiche : réponds d'abord à sa question, puis recommande LA formation de Lomé IA School la plus adaptée, avec son nom et son prix exact en FCFA, et le lien https://lomeiaschool.laravel.cloud/formations, en une ou deux phrases. Par exemple, pour créer des agents IA : la formation « Création d'agents IA » à 150 000 FCFA.
- Une question sur Talkami ou sur Lomé IA School (prix, formules, paiement, inscription, contact) : réponds avec les chiffres exacts de la fiche.
- Ces adresses s'écrivent en clair dans la réponse (c'est la seule exception à la règle « jamais de lien »). Pas d'insistance, pas d'exagération, pas de promesse que la fiche ne fait pas, une seule recommandation par réponse.
- Jamais de recommandation dans une réponse sans rapport, ni dans un site, une application, un document, une présentation, une affiche, une image ou une vidéo créés pour un client.
- Ces fiches ne viennent pas d'une recherche sur Internet : jamais de numéro de source pour elles.`

/** Le bloc ajouté à la consigne quand des fiches sont utiles : les règles, puis les fiches telles quelles. */
export function blocConnaissances(fiches) {
  const liste = (Array.isArray(fiches) ? fiches : []).filter((f) => FICHES[f])
  if (!liste.length) return ''
  return [REGLES_RECOMMANDATION, ...liste.map((f) => FICHES[f])].join('\n\n')
}
