// Le plan de montage écrit par le modèle (étape 4 du chantier des vidéos motion design, 16 novembre 2026).
// Le modèle reçoit la demande du client et rend un JSON : le texte de la voix off (ou la transcription de la voix du
// client, recopiée telle quelle) et la liste des plans. Il n'écrit jamais de code. Kekeli vérifie ensuite tout ce qui
// peut l'être sans modèle : le schéma (schema.js), chaque mot de départ présent dans la voix et dans l'ordre, les
// icônes connues, et AUCUN chiffre absent de la demande du client (un chiffre inventé n'est jamais montré : il est
// signalé au client). En cas de défaut, le modèle corrige deux fois au plus, avec la liste exacte des défauts.
import { rediger } from '../../documents/redaction.js'
import { planPropre, STYLES, STYLES_VOUVOIEMENT, TYPES_PLANS } from './schema.js'
import { motsCherchables, normaliser, trouverPhrase } from './temps.js'
import { MOTS_LIES } from './sous-titres.js'
import { formesIcone } from './icones.js'

/** Le débit de la voix off une fois préparée (mesuré à l'étape 3 : 43 mots en 17,4 secondes). */
export const MOTS_PAR_SECONDE = 2.5

/** Les icônes proposées au modèle (Lucide, licence ISC) : simples, lisibles, jamais une marque. */
export const ICONES_PROPOSEES = ['shirt', 'sparkles', 'truck', 'users', 'globe', 'smartphone', 'shopping-bag', 'store', 'school', 'graduation-cap', 'book-open', 'bot', 'zap', 'clock', 'hourglass', 'calendar', 'map-pin', 'phone', 'message-circle', 'mail', 'heart', 'star', 'check', 'gift', 'tag', 'wallet', 'coins', 'banknote', 'chart-line', 'trending-up', 'briefcase', 'building-2', 'house', 'utensils', 'coffee', 'scissors', 'wrench', 'car', 'bike', 'camera', 'music', 'baby', 'stethoscope', 'pill', 'leaf', 'sun', 'shield-check', 'lock', 'rocket', 'lightbulb', 'target', 'award', 'footprints', 'watch', 'package', 'laptop', 'code', 'settings', 'search', 'thumbs-up']

export const CONSIGNES_MONTAGE = `Tu es le réalisateur des vidéos « motion design » de Kekeli : des publicités verticales pour TikTok, Reels et le statut WhatsApp, rythmées comme les meilleures publicités de Lomé IA School. Tu écris UN plan de montage en JSON. Tu n'écris jamais de code.

Réponds uniquement par ce JSON :
{
  "marque": { "nom": "…", "slogan": "…", "bouton": "Écris-nous sur WhatsApp", "contact": "+228 …" },
  "plans": [ { "type": "accroche", "voix": "les mots exacts dits pendant ce plan", … }, … ]
}
La voix off complète est la suite des "voix" de tous les plans, dans l'ordre : chaque mot de la voix appartient à un seul plan.

LA VOIX
- Si une transcription de la voix du client t'est donnée, tu la découpes en plans : la suite des "voix" la recopie MOT POUR MOT (rien d'ajouté, rien d'enlevé, rien de changé).
- Sinon tu l'écris : accroche forte dans la première phrase (une question ou un fait qui touche), problème, solution, preuve donnée par le client, offre, appel à l'action avec le contact. Environ {MOTS} mots (la durée visée). Phrases courtes, langage parlé.
- {TON}
- Les nombres en chiffres (« 24 heures », « 500 clients ») ; un numéro de téléphone écrit par groupes (« 91 11 11 11 »).

LES PLANS
- "voix" : de 3 à 6 MOTS par plan (une image toutes les 1,5 à 2,5 secondes). Une phrase longue est coupée en plusieurs plans. Seuls une liste, un numéro, une discussion, un produit ou la carte finale, qui changent en cours de route, peuvent avoir jusqu’à 14 mots ; une citation garde l’avis entier.
- Le premier plan est "accroche" ; il est lisible tout seul : c'est la miniature de la vidéo.
- Le dernier plan est "carte" (logo ou nom, slogan, bouton, contact) avec la dernière phrase de la voix.
- Le texte à l'écran est COURT (6 mots au plus par texte) et reprend l'idée de la voix, pas toute la phrase.
- "cles" : 1 ou 2 mots du texte à l'écran à mettre en couleur.
- "icone" : un nom choisi dans cette liste seulement : {ICONES}.
- Varie les types : jamais deux fois de suite le même type, sauf "texte" (jamais trois "texte" de suite). Au plus 4 plans sur 10 de type "texte" : préfère les plans illustrés (etiquette, liste, telephone, navigateur, discussion, horloge, pieces, avant_apres, tampon, produit, citation) qui MONTRENT ce que dit la voix.

LES TYPES (champs permis ; "voix" est toujours obligatoire)
- accroche : surtitre, texte, cles, icone
- texte : surtitre, texte, sous_texte, cles, icone
- tampon : sous_texte (la situation), texte (1 ou 2 mots qui claquent : « FINI », « GRATUIT »), couleur ("rouge" ou "vert")
- etiquette : numero (1, 2, 3…), texte, sous_texte, icone — pour énumérer des offres une à une
- compteur : texte, de, a (nombres), prefixe, suffixe, icone — SEULEMENT avec un nombre donné par le client
- liste : titre, elements [{texte, mot, icone}] — "mot" de chaque élément : le mot de la voix du plan qui le fait apparaître
- barre : titre, valeur (0 à 100), texte — SEULEMENT avec un pourcentage donné par le client
- telephone : titre, entete, ecran {genre: "boutique" | "liste" | "discussion"}, articles [{nom, prix, icone}] — prix SEULEMENT donnés par le client, sinon sans prix
- navigateur : titre, adresse, nom, texte, bouton
- tableau : titre, chiffres [{valeur, libelle}] — SEULEMENT des chiffres donnés par le client
- discussion : titre, entete, messages [{de: "client" | "moi", texte, mot}] — "mot" : le mot de la voix du plan qui fait apparaître la bulle
- pieces : texte, cles — pour parler d'argent gagné
- horloge : texte, cles — pour parler de temps perdu ou gagné
- avant_apres : titre, avant, apres
- numero : texte (« Écris-nous »), groupes [{texte: "91", mot: "91"}, …] — un groupe par morceau du numéro, chacun avec son "mot" (le morceau tel que dit dans la voix)
- carte : cles
- citation : texte (la phrase EXACTE d'un client du client, recopiée de la demande), auteur (son nom tel que donné), role (son activité, facultatif), etoiles (1 à 5, SEULEMENT si le client l'a dit), cles — SEULEMENT avec un vrai avis donné par le client, jamais inventé
- produit : texte (nom du produit), prix, ancien_prix (facultatif), sous_texte, icone, mot_prix (le mot de la voix qui fait tomber le prix) — prix SEULEMENT donnés par le client

HONNÊTETÉ
- N'invente JAMAIS un chiffre, un prix, une note, un nombre de clients, une adresse ou un numéro : utilise seulement ce que le client a donné. Sans chiffre, choisis un autre type de plan.
- N'invente JAMAIS une promesse ni une qualité que le client n'a pas dites : pas de « prix imbattables », « qualité premium », « le meilleur », « numéro 1 », « garanti », « le moins cher ». Reprends ses mots.
- Jamais le nom ni le logo d'une autre marque (une bulle verte simple, jamais le mot d'une marque dans une icône).
- Rien de choquant, aucune personne réelle.`

/**
 * Ce que chaque style demande au réalisateur : le ton, les plans à préférer, le rythme. Le même JSON sort ensuite dans
 * le style choisi (couleurs, fond, sous-titres, entrées : socle/src/montage/style.ts).
 */
export const CONSIGNES_STYLES = {
  dynamique: 'Style « Dynamique » : énergie, accroche choc, gros textes qui claquent, tampons, compteurs, liste qui se coche ; phrases très courtes.',
  sobre: 'Style « Sobre et professionnel » : ton posé et rassurant (cabinet, école, entreprise de services) ; pas de tampon ni de pluie de pièces ; préfère texte, etiquette, liste, navigateur, tableau, avant_apres ; phrases complètes et claires, aucune exclamation.',
  produit: 'Style « Produit et boutique » : montre le produit et son prix ; au moins un plan « produit » par produit dont le client donne le prix (un seul produit par plan) ; puis telephone (boutique), liste des points forts dits par le client, numéro et carte ; ton chaleureux et vendeur, sans promesse inventée.',
  temoignage: 'Style « Témoignage » : la parole d\'un client satisfait. Si la demande contient un vrai avis (une phrase d\'un client et son nom), la voix commence par la situation, puis un ou deux plans « citation » qui recopient cet avis mot pour mot, puis l\'offre et la carte ; rythme lent, 4 à 8 mots par plan. Sans avis donné par le client, AUCUN plan « citation » : raconte l\'expérience avec avant_apres, liste et texte.',
  educatif: 'Style « Éducatif » : expliquer une idée ou une méthode simplement : une question au départ, puis des étapes numérotées (etiquette 1, 2, 3 ou liste), un exemple concret, un résumé, puis la carte ; ton clair de professeur, un seul mot nouveau à la fois.',
}

/** Le ton par défaut du style (le texte du client décide toujours). */
export function tonDuStyle(style = 'dynamique') {
  return STYLES_VOUVOIEMENT.includes(style)
    ? 'Vouvoiement par défaut (ce style vouvoie) ; tutoiement seulement si le client tutoie lui-même dans sa demande.'
    : 'Tutoiement par défaut ; vouvoiement si le client vouvoie dans sa demande.'
}

/** La consigne d'une vidéo : la demande du client, la durée visée, la marque connue, la transcription de sa voix. */
export function consigneMontage({ demande, dureeVoulue = 30, marque = {}, transcription = '', pays = 'Togo', monnaie = 'FCFA', style = 'dynamique' }) {
  const lignes = [`Demande du client : « ${String(demande).trim()} »`, `Pays : ${pays}. Monnaie : ${monnaie}.`, CONSIGNES_STYLES[style] ?? CONSIGNES_STYLES.dynamique]
  if (transcription) lignes.push(`Transcription de la voix du client (à découper dans les « voix » des plans, mot pour mot) : « ${transcription.trim()} »`)
  else lignes.push(`Durée visée : environ ${Math.round(dureeVoulue)} secondes, soit environ ${Math.round(dureeVoulue * MOTS_PAR_SECONDE)} mots de voix off.`)
  const connus = Object.entries(marque).filter(([, v]) => v).map(([k, v]) => `${k} : ${v}`)
  if (connus.length) lignes.push(`Marque du client (à reprendre telle quelle) : ${connus.join(' ; ')}.`)
  return lignes.join('\n')
}

/** Le texte des consignes avec la liste des icônes et le nombre de mots visé. */
export function consignesSysteme(dureeVoulue = 30, style = 'dynamique') {
  return CONSIGNES_MONTAGE.replace('{ICONES}', ICONES_PROPOSEES.join(', ')).replace('{MOTS}', String(Math.round(dureeVoulue * MOTS_PAR_SECONDE))).replace('{TON}', tonDuStyle(style))
}

/**
 * Les mots de départ du plan, cherchés dans le texte de la voix, l'un après l'autre (comme le fera le calage sur la
 * vraie voix) : un mot absent ou dans le désordre est un défaut à corriger avant de dépenser la voix off.
 */
export function verifierAncres(plan) {
  const faux = String(plan.texte_voix ?? '').split(/\s+/).filter(Boolean).map((mot, i) => ({ mot, debut: i, fin: i + 0.5 }))
  const mots = motsCherchables(faux)
  const erreurs = []
  let apres = 0
  plan.plans.forEach((p, i) => {
    const k = trouverPhrase(mots, p.mot, apres)
    if (k < 0) {
      erreurs.push(`plan ${i + 1} (${p.type}) : « ${p.mot} » n'est pas dans la voix après le plan précédent`)
      return
    }
    if (i === 0 && k !== 0) erreurs.push(`plan 1 : l'accroche doit commencer sur le premier mot de la voix (« ${mots[0]?.mot} »)`)
    apres = k + 1
    let suivant = k
    for (const e of [...(p.elements ?? []), ...(p.groupes ?? []), ...(p.messages ?? [])]) {
      if (!e.mot) continue
      const j = trouverPhrase(mots, e.mot, suivant)
      if (j < 0) erreurs.push(`plan ${i + 1} (${p.type}) : l'élément « ${e.texte} » commence sur « ${e.mot} », absent de la voix après le début du plan`)
      else suivant = j + 1
    }
  })
  return erreurs
}

/** Au plus 7 mots de voix sur un même plan (environ 2,8 secondes), sauf ceux qui changent en cours de route. */
export const MOTS_PAR_PLAN_MAX = 6
// La citation (un avis se lit en entier) et le produit (le prix tombe en cours de plan) restent aussi d'un seul tenant.
const PLANS_QUI_CHANGENT = new Set(['liste', 'numero', 'carte', 'discussion', 'compteur', 'barre', 'tableau', 'citation', 'produit'])

/** Les plans qui restent trop longtemps à l'écran (trop de mots de voix avant le plan suivant). */
export function plansTropLongs(plan, max = MOTS_PAR_PLAN_MAX) {
  const faux = String(plan.texte_voix ?? '').split(/\s+/).filter(Boolean).map((mot, i) => ({ mot, debut: i, fin: i + 0.5 }))
  const mots = motsCherchables(faux)
  const debuts = []
  let apres = 0
  for (const p of plan.plans) {
    const k = trouverPhrase(mots, p.mot, apres)
    if (k < 0) return []
    debuts.push(k)
    apres = k + 1
  }
  const defauts = []
  plan.plans.forEach((p, i) => {
    const n = (i + 1 < debuts.length ? debuts[i + 1] : mots.length) - debuts[i]
    if (!PLANS_QUI_CHANGENT.has(p.type) && n > max) defauts.push(`plan ${i + 1} (${p.type}, « ${p.mot}… ») : ${n} mots de voix, c'est trop long à l'écran ; coupe-le en ${Math.ceil(n / 5)} plans de 3 à 6 mots chacun`)
  })
  return defauts
}

/**
 * Deux réparations sûres, faites par le code avant de demander une correction au modèle : l'accroche commence toujours
 * sur le premier mot de la voix, et une carte finale dont le mot de départ ne tombe pas après le plan précédent commence
 * sur la dernière phrase de la voix.
 */
export function reparerAncres(plan) {
  const voix = String(plan.texte_voix ?? '').trim()
  if (!voix || !plan.plans.length) return plan
  const mots = voix.split(/\s+/)
  plan.plans[0].mot = mots.slice(0, 2).join(' ')
  const derniere = plan.plans[plan.plans.length - 1]
  if (derniere.type === 'carte' && plan.plans.length > 1) {
    const sansCarte = { ...plan, plans: plan.plans.slice(0, -1) }
    const avant = verifierAncres(sansCarte).length
    if (verifierAncres(plan).length > avant) {
      const phrases = voix.split(/(?<=[.!?…])\s+/).filter(Boolean)
      const premiers = (phrases[phrases.length - 1] ?? '').split(/\s+/).slice(0, 2).join(' ')
      if (premiers) derniere.mot = premiers
    }
  }
  return plan
}

/** Les icônes inconnues sont enlevées (un plan sans icône vaut mieux qu'un carré vide). */
export function iconesConnues(plan) {
  const garder = (o) => {
    if (o.icone && !formesIcone(o.icone)) delete o.icone
  }
  for (const p of plan.plans) {
    garder(p)
    for (const e of [...(p.elements ?? []), ...(p.articles ?? [])]) garder(e)
  }
  return plan
}

const nombresDe = (texte) => (String(texte ?? '').replace(/(\d)[\s .](?=\d{3}\b)/g, '$1').match(/\d+(?:[.,]\d+)?/g) ?? []).map((n) => n.replace(',', '.').replace(/^0+(?=\d)/, ''))

/**
 * Les chiffres affichés à l'écran qui ne viennent pas du client (ni de sa demande, ni de sa voix, ni de sa marque) :
 * ils sont signalés et jamais montrés tels quels. Un numéro de plan (étiquette 1, 2, 3) et les valeurs de départ d'un
 * compteur (0) ne sont pas des chiffres du client.
 */
export function chiffresAConfirmer(plan, sources = []) {
  const permis = new Set(sources.flatMap(nombresDe))
  const affiches = []
  for (const p of plan.plans) {
    const textes = [...Object.keys(TYPES_PLANS[p.type]?.textes ?? {}).map((c) => p[c]), ...(p.elements ?? []).map((e) => e.texte), ...(p.chiffres ?? []).flatMap((c) => [c.valeur, c.libelle]), ...(p.articles ?? []).flatMap((a) => [a.nom, a.prix]), ...(p.messages ?? []).map((m) => m.texte)]
    if (p.type === 'compteur') textes.push(String(p.a))
    if (p.type === 'barre') textes.push(String(p.valeur))
    if (p.type === 'citation' && p.etoiles) textes.push(`${p.etoiles} étoiles`)
    for (const n of textes.flatMap(nombresDe)) if (!permis.has(n)) affiches.push({ plan: p.type, chiffre: n })
  }
  return affiches
}

/**
 * La voix découpée par plan (« voix » de chaque plan, écrite par le modèle) devient le texte de la voix off, et le mot
 * de départ de chaque plan ses deux premiers mots : le modèle n'a ni à compter ni à recopier des mots de départ.
 */
export function assemblerVoix(brut) {
  if (!brut || !Array.isArray(brut.plans) || !brut.plans.some((p) => typeof p?.voix === 'string' && p.voix.trim())) return { brut, longueurs: [], coupes: 0 }
  const longueurs = brut.plans.map((p) => String(p?.voix ?? '').split(/\s+/).filter(Boolean).length)
  const plans = []
  let coupes = 0
  for (const p of brut.plans) {
    // La ponctuation française (« ? », « ! ») reste collée au mot d'avant : elle ne compte pas comme un mot.
    // Le préfixe du pays (« +228 ») n'est pas dit : le numéro se lit et se reconnaît groupe par groupe.
    const mots = String(p?.voix ?? '').replace(/\+\d{1,3}\s+(?=\d)/g, '').replace(/\s+([?!:;»])/g, '\u00a0$1').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean)
    if (!mots.length) {
      plans.push({ ...p })
      continue
    }
    // Un plan trop long (sauf ceux qui changent en cours de route) est coupé par le code : le premier morceau garde le
    // plan, les suivants deviennent des plans « texte » qui montrent les mots dits (rien d'inventé).
    const morceaux = PLANS_QUI_CHANGENT.has(p.type) || mots.length <= MOTS_PAR_PLAN_MAX ? [mots] : couperMots(mots)
    morceaux.forEach((m, i) => {
      // Le préfixe du pays (« +228 ») est dit en toutes lettres par la voix : il n'est jamais un groupe du numéro.
      const groupes = Array.isArray(p.groupes) ? { groupes: p.groupes.filter((g) => !/^\+/.test(String(g?.texte ?? ''))) } : {}
      if (i === 0) plans.push({ ...p, ...groupes, voix: m.join(' ').replace(/\u00a0/g, ' '), mot: m.slice(0, 2).join(' ').replace(/\u00a0/g, ' ') })
      else {
        coupes++
        const dit = m.join(' ').replace(/\u00a0/g, ' ')
        // La suite garde le pictogramme du plan d'origine : l'écran reste illustré.
        plans.push({ type: 'texte', voix: dit, mot: m.slice(0, 2).join(' ').replace(/\u00a0/g, ' '), texte: dit.replace(/\s*[.,;:!?…]+$/, ''), ...(p.icone ? { icone: p.icone } : {}) })
      }
    })
  }
  // Les étiquettes numérotées dans l'ordre (1, 2, 3…), quel que soit le numéro choisi par le modèle.
  let numero = 0
  for (const p of plans) if (p.type === 'etiquette') p.numero = ++numero
  return { brut: { ...brut, plans, texte_voix: plans.map((p) => p.voix).filter(Boolean).join(' ') }, longueurs, coupes }
}

/**
 * Coupe une suite de mots en morceaux de 2 à 6 mots qui se lisent bien : de préférence juste après une ponctuation,
 * jamais après un petit mot (« des | solutions »), avec le moins de morceaux possible.
 */
export function couperMots(mots) {
  const n = mots.length
  const cout = new Array(n + 1).fill(Infinity)
  const avant = new Array(n + 1).fill(-1)
  cout[0] = 0
  for (let fin = 1; fin <= n; fin++) {
    for (let debut = Math.max(0, fin - MOTS_PAR_PLAN_MAX); debut < fin; debut++) {
      const taille = fin - debut
      let c = cout[debut] + 1
      if (fin < n) {
        if (!/[.,;:!?…»]$/.test(mots[fin - 1])) c += 1.5
        if (MOTS_LIES.has(normaliser(mots[fin - 1]))) c += 3
      }
      if (taille < 3) c += 1.5
      if (c < cout[fin]) {
        cout[fin] = c
        avant[fin] = debut
      }
    }
  }
  const morceaux = []
  for (let fin = n; fin > 0; fin = avant[fin]) morceaux.unshift(mots.slice(avant[fin], fin))
  return morceaux
}

const motsNormalises = (t) => String(t ?? '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[’']/g, '').split(/[^a-z0-9]+/).filter(Boolean)

/** Les promesses toutes faites qu'un modèle ajoute volontiers : refusées si le client ne les a pas dites. */
export const PROMESSES = ['imbattable', 'premium', 'le meilleur', 'la meilleure', 'les meilleurs', 'numéro 1', 'n°1', 'n° 1', 'garanti', 'garantie', 'le moins cher', 'la moins chère', 'sans égal', 'incomparable', 'exceptionnel', 'exceptionnelle', 'qualité supérieure']

/** Les promesses du plan (à l'écran ou dans la voix) qui ne sont pas dans les mots du client. */
export function promessesInventees(plan, sources = []) {
  const client = sources.join(' ').toLowerCase()
  const textes = plan.plans.flatMap((p) => [...Object.keys(TYPES_PLANS[p.type]?.textes ?? {}).map((c) => p[c]), ...(p.elements ?? []).map((e) => e.texte)])
  const tout = [...textes, plan.texte_voix, plan.marque?.slogan].filter(Boolean).join(' ').toLowerCase()
  return PROMESSES.filter((m) => new RegExp(`(^|[^\\p{L}])${m.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}s?($|[^\\p{L}])`, 'u').test(tout) && !client.includes(m))
}

/**
 * Les citations qui ne viennent pas du client : un avis doit être recopié de sa demande (ou de sa voix), avec un nom
 * qu'il a donné. Kekeli n'invente jamais un témoignage. Une citation est acceptée si au moins 80 % de ses mots sont
 * dans les mots du client, dans le même ordre à peu près (on vérifie les mots, pas la ponctuation).
 */
export function citationsInventees(plan, sources = []) {
  const client = motsNormalises(sources.join(' '))
  const ensemble = new Set(client)
  const fautes = []
  for (const p of plan.plans.filter((x) => x.type === 'citation')) {
    const mots = motsNormalises(p.texte)
    const trouves = mots.filter((m) => ensemble.has(m)).length
    if (!mots.length || trouves / mots.length < 0.8) fautes.push(`la citation « ${p.texte} » n'est pas un avis donné par le client : recopie un vrai avis de sa demande, ou enlève ce plan`)
    if (p.auteur && !motsNormalises(p.auteur).every((m) => ensemble.has(m))) fautes.push(`le nom « ${p.auteur} » n'a pas été donné par le client : utilise le nom qu'il a donné, ou enlève ce plan`)
  }
  return fautes
}

/** Tous les défauts d'un plan, sans modèle : schéma, mots de départ, longueur des plans, chiffres inventés. */
export function defautsDuPlan(entree, { sources = [], transcription = '', dureeVoulue = 0 } = {}) {
  const { brut, longueurs } = assemblerVoix(entree)
  const { plan, erreurs } = planPropre(brut)
  // La variété se juge sur les plans choisis par le modèle (avant les coupes faites par le code).
  const typesChoisis = Array.isArray(entree?.plans) ? entree.plans.map((p) => p?.type) : []
  if (!plan) return { plan: null, defauts: erreurs }
  if (!plan.texte_voix) return { plan: null, defauts: ['texte_voix manque'] }
  iconesConnues(plan)
  reparerAncres(plan)
  const defauts = [...verifierAncres(plan), ...plansTropLongs(plan)]
  // De la variété : jamais trois plans de suite du même type (le regard se lasse).
  const textes = typesChoisis.filter((t) => t === 'texte').length
  if (typesChoisis.length >= 5 && textes / typesChoisis.length > 0.4) defauts.push(`${textes} plans « texte » sur ${typesChoisis.length} : c'est trop ; au plus 4 sur 10. Remplace des plans « texte » par des plans illustrés (etiquette, liste, telephone, navigateur, discussion, horloge, pieces, avant_apres, tampon)`)
  typesChoisis.forEach((t, i) => {
    if (i >= 2 && typesChoisis[i - 1] === t && typesChoisis[i - 2] === t) defauts.push(`plans ${i - 1} à ${i + 1} : trois plans « ${t} » de suite ; change le type du plan ${i} (étiquette, tampon, liste, avant_apres, téléphone…)`)
  })
  longueurs.forEach((n, i) => {
    if (n === 0) defauts.push(`plan ${i + 1} : "voix" manque`)
  })
  // La durée visée : une voix écrite par Kekeli fait au moins 70 % des mots attendus (au plus 130 %).
  if (!transcription && dureeVoulue > 0) {
    const n = plan.texte_voix.split(/\s+/).filter(Boolean).length
    const vise = Math.round(dureeVoulue * MOTS_PAR_SECONDE)
    if (n < vise * 0.7) defauts.push(`la voix a ${n} mots : trop court pour ${dureeVoulue} secondes ; écris environ ${vise} mots en tout (ajoute des plans)`)
    if (n > vise * 1.3) defauts.push(`la voix a ${n} mots : trop long pour ${dureeVoulue} secondes ; écris environ ${vise} mots en tout`)
  }
  // La voix du client est recopiée mot pour mot : la suite des morceaux redonne exactement la transcription.
  if (transcription && motsNormalises(plan.texte_voix).join(' ') !== motsNormalises(transcription).join(' ')) defauts.push('la suite des "voix" ne reprend pas la transcription mot pour mot (rien à ajouter, enlever ni changer)')
  for (const c of chiffresAConfirmer(plan, sources)) defauts.push(`plan « ${c.plan} » : le chiffre ${c.chiffre} n'a pas été donné par le client (enlève-le ou change de type de plan)`)
  defauts.push(...citationsInventees(plan, sources))
  for (const m of promessesInventees(plan, sources)) defauts.push(`« ${m} » : une promesse que le client n'a pas faite ; enlève-la (à l'écran et dans la voix)`)
  // La voix non plus n'invente aucun chiffre (une transcription du client, elle, est dans les sources).
  const permis = new Set(sources.flatMap(nombresDe))
  for (const n of new Set(nombresDe(plan.texte_voix))) if (!permis.has(n)) defauts.push(`voix : le chiffre ${n} n'a pas été donné par le client (enlève-le de la voix)`)
  return { plan, defauts }
}

/**
 * Le plan de montage écrit par le modèle de construction (fournisseurs gratuits d'abord, OpenAI en dernier), vérifié,
 * puis corrigé deux fois au plus si besoin. La consommation de chaque appel est rendue pour l'énergie.
 * @returns {Promise<{ plan: object|null, defauts: string[], consommations: object[], essais: number }>}
 */
export async function ecrireMontage({ demande, dureeVoulue = 30, marque = {}, transcription = '', pays, monnaie, style = 'dynamique', fournisseurs = [], secours = [] }, options = {}) {
  if (!STYLES.includes(style)) style = 'dynamique'
  const systeme = consignesSysteme(dureeVoulue, style)
  const consigne = consigneMontage({ demande, dureeVoulue, marque, transcription, pays, monnaie, style })
  const sources = [demande, transcription, ...Object.values(marque)]
  const consommations = []
  let r = await rediger({ systeme, consigne, fournisseurs, secours, maxTokens: 8000 }, options)
  if (r.consommation) consommations.push(r.consommation)
  if (!r.ok) return { plan: null, defauts: [r.erreur], consommations, essais: 1 }
  let { plan, defauts } = defautsDuPlan(r.objet, { sources, transcription, dureeVoulue })
  if (!defauts.length) return { plan: { ...plan, style }, defauts, consommations, essais: 1 }
  // Deux corrections au plus, chacune avec la liste exacte des défauts trouvés par le code.
  let essais = 1
  for (; essais < 3 && defauts.length; ) {
    const precedent = r.objet
    r = await rediger({ systeme, consigne: `${consigne}\n\nTon plan (ci-dessous) a ces défauts : corrige-les tous et rends le plan complet au même format (chaque plan avec sa "voix").\n${defauts.map((d) => `- ${d}`).join('\n')}\n${JSON.stringify(precedent)}`, fournisseurs, secours, maxTokens: 8000 }, options)
    essais++
    if (r.consommation) consommations.push(r.consommation)
    if (!r.ok) return { plan: null, defauts, consommations, essais }
    ;({ plan, defauts } = defautsDuPlan(r.objet, { sources, transcription, dureeVoulue }))
  }
  return { plan: defauts.length ? null : { ...plan, style }, defauts, consommations, essais }
}
