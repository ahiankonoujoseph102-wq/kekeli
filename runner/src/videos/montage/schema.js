// Le plan de montage d'une vidéo « motion design » (étape 1 du chantier des vidéos publicitaires, 16 novembre 2026).
// Le modèle (ou, pour l'essai, une personne) remplit ce plan : une liste de plans courts, chacun d'un TYPE connu, qui
// commence sur un MOT de la voix. Kekeli vérifie le plan ici, calcule les temps sur la voix (temps.js), puis la
// bibliothèque fixe de composants (socle/src/montage/) fait le rendu. Le modèle n'écrit jamais de code.

/** Les styles connus (un thème interchangeable : couleurs, police, transitions, rythme, éléments permis). */
export const STYLES = ['dynamique', 'sobre', 'produit', 'temoignage', 'educatif']

/** Les styles qui vouvoient par défaut (le texte du client décide toujours : s'il tutoie, la vidéo tutoie). */
export const STYLES_VOUVOIEMENT = ['sobre', 'produit']

/** Les types de plans et leurs champs de texte (longueur maximale), les autres champs sont vérifiés plus bas. */
export const TYPES_PLANS = {
  // Gros texte qui claque (l'accroche est visible dès l'image 0 : elle sert de miniature).
  accroche: { textes: { surtitre: 40, texte: 70 } },
  texte: { textes: { surtitre: 40, texte: 70, sous_texte: 80 } },
  // Tampon qui s'écrase sur l'écran (« FINI », « GRATUIT », « EN LIGNE »).
  tampon: { textes: { texte: 24, sous_texte: 60 } },
  // Étiquette numérotée « 1 · SITE WEB » avec une icône.
  etiquette: { textes: { texte: 30, sous_texte: 60 } },
  // Compteur qui défile jusqu'à un nombre.
  compteur: { textes: { texte: 60, prefixe: 12, suffixe: 16 } },
  // Liste qui se coche, un élément après l'autre, sur les mots de la voix.
  liste: { textes: { titre: 40 } },
  // Barre de progression qui se remplit.
  barre: { textes: { titre: 40, texte: 60 } },
  // Téléphone incliné avec un écran animé (discussion, boutique, liste).
  telephone: { textes: { titre: 40, entete: 30 } },
  // Fenêtre de navigateur avec un site d'exemple.
  navigateur: { textes: { titre: 40, adresse: 40, nom: 30, texte: 60, bouton: 24 } },
  // Tableau de bord : des chiffres et un petit graphique qui pousse.
  tableau: { textes: { titre: 40 } },
  // Discussion : bulles qui arrivent, « en train d'écrire ».
  discussion: { textes: { titre: 40, entete: 30 } },
  // Pluie de pièces.
  pieces: { textes: { texte: 50 } },
  // Horloge dont les aiguilles tournent.
  horloge: { textes: { texte: 50 } },
  // Avant et après, côte à côte.
  avant_apres: { textes: { avant: 50, apres: 50, titre: 40 } },
  // Numéro de téléphone géant, groupe par groupe, au moment où il est dit.
  numero: { textes: { texte: 40 } },
  // Carte finale : logo ou nom, slogan, bouton de contact, numéro.
  carte: { textes: {} },
  // Citation d'un client (style « Témoignage ») : sa phrase, son nom, son activité, ses étoiles, médaillon rond.
  citation: { textes: { texte: 140, auteur: 40, role: 40 } },
  // Produit (style « Produit et boutique ») : carte du produit et étiquette de prix (ancien prix barré).
  produit: { textes: { texte: 40, prix: 20, ancien_prix: 20, sous_texte: 60 } },
}

export const COULEURS_PERMISES = ['accent', 'vert', 'rouge', 'violet', 'blanc']
export const GENRES_ECRAN = ['discussion', 'boutique', 'liste']
export const PLANS_MAX = 60

const texte = (v, max) => String(v ?? '').replace(/[\u0000-\u001f]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, max)
const couleurHexa = (c) => (/^#[0-9A-Fa-f]{6}$/.test(String(c ?? '').trim()) ? String(c).trim() : null)
const nombre = (v, defaut = 0) => (Number.isFinite(Number(v)) ? Number(v) : defaut)

/**
 * Le plan de montage nettoyé : seulement les champs connus, des textes bornés, des listes bornées. Les erreurs qui
 * empêchent de faire la vidéo sont rendues dans « erreurs » (le plan n'est alors pas utilisé).
 * @returns {{ plan: object|null, erreurs: string[] }}
 */
export function planPropre(brut = {}) {
  const erreurs = []
  const style = STYLES.includes(brut.style) ? brut.style : 'dynamique'
  const format = ['vertical', 'carre', 'paysage'].includes(brut.format) ? brut.format : 'vertical'
  const couleurs = {}
  for (const [nom, valeur] of Object.entries(brut.couleurs && typeof brut.couleurs === 'object' ? brut.couleurs : {})) {
    if (['fond', 'accent', 'accent2', 'texte'].includes(nom) && couleurHexa(valeur)) couleurs[nom] = couleurHexa(valeur)
  }
  const m = brut.marque && typeof brut.marque === 'object' ? brut.marque : {}
  const marque = {
    nom: texte(m.nom, 50),
    slogan: texte(m.slogan, 90),
    bouton: texte(m.bouton, 40),
    contact: texte(m.contact, 30),
    ...(m.logo && typeof m.logo === 'object' && m.logo.fichier ? { logo: { fichier: String(m.logo.fichier), largeur: nombre(m.logo.largeur, 1), hauteur: nombre(m.logo.hauteur, 1) } } : {}),
  }
  const plans = []
  for (const [i, p] of (Array.isArray(brut.plans) ? brut.plans : []).slice(0, PLANS_MAX).entries()) {
    const type = String(p?.type ?? '')
    const def = TYPES_PLANS[type]
    if (!def) {
      erreurs.push(`plan ${i + 1} : type inconnu « ${type} »`)
      continue
    }
    const mot = texte(p.mot, 80)
    if (!mot) erreurs.push(`plan ${i + 1} : le mot de départ manque`)
    const propre = { type, mot }
    for (const [champ, max] of Object.entries(def.textes)) {
      const v = texte(p[champ], max)
      if (v) propre[champ] = v
    }
    // Les mots-clés (mis en couleur) : seulement des mots qui sont dans les textes du plan.
    const tousTextes = Object.keys(def.textes).map((c) => propre[c] ?? '').join(' ').toLowerCase()
    const cles = (Array.isArray(p.cles) ? p.cles : []).map((c) => texte(c, 30)).filter((c) => c && tousTextes.includes(c.toLowerCase())).slice(0, 6)
    if (cles.length) propre.cles = cles
    if (p.icone) propre.icone = texte(p.icone, 40)
    if (COULEURS_PERMISES.includes(p.couleur)) propre.couleur = p.couleur
    if (type === 'etiquette') propre.numero = Math.max(1, Math.min(20, Math.round(nombre(p.numero, 1))))
    if (type === 'compteur') {
      propre.de = nombre(p.de, 0)
      propre.a = nombre(p.a, 0)
    }
    if (type === 'citation' && p.etoiles != null) propre.etoiles = Math.max(0, Math.min(5, Math.round(nombre(p.etoiles, 0))))
    if (type === 'citation' && !propre.texte) erreurs.push(`plan ${i + 1} : la citation est vide`)
    if (type === 'produit') {
      if (!propre.texte || !propre.prix) erreurs.push(`plan ${i + 1} : le produit a besoin de son nom et de son prix`)
      if (p.mot_prix) propre.mot_prix = texte(p.mot_prix, 60)
    }
    if (type === 'barre') propre.valeur = Math.max(0, Math.min(100, nombre(p.valeur, 100)))
    if (type === 'liste') {
      propre.elements = (Array.isArray(p.elements) ? p.elements : []).slice(0, 6).map((e) => ({ texte: texte(e?.texte, 40), mot: texte(e?.mot, 60), ...(e?.icone ? { icone: texte(e.icone, 40) } : {}) })).filter((e) => e.texte)
      if (!propre.elements.length) erreurs.push(`plan ${i + 1} : la liste est vide`)
    }
    if (type === 'tableau') {
      propre.chiffres = (Array.isArray(p.chiffres) ? p.chiffres : []).slice(0, 4).map((c) => ({ valeur: texte(c?.valeur, 14), libelle: texte(c?.libelle, 24) })).filter((c) => c.valeur)
      if (!propre.chiffres.length) erreurs.push(`plan ${i + 1} : le tableau n'a aucun chiffre`)
    }
    if (type === 'telephone' || type === 'discussion') {
      const e = p.ecran && typeof p.ecran === 'object' ? p.ecran : {}
      propre.ecran = GENRES_ECRAN.includes(e.genre) ? e.genre : type === 'discussion' ? 'discussion' : 'liste'
      propre.messages = (Array.isArray(p.messages) ? p.messages : []).slice(0, 6).map((x) => ({ de: x?.de === 'moi' ? 'moi' : 'client', texte: texte(x?.texte, 60), ...(x?.mot ? { mot: texte(x.mot, 60) } : {}) })).filter((x) => x.texte)
      propre.articles = (Array.isArray(p.articles) ? p.articles : []).slice(0, 4).map((x) => ({ nom: texte(x?.nom, 24), prix: texte(x?.prix, 20), ...(x?.icone ? { icone: texte(x.icone, 40) } : {}) })).filter((x) => x.nom)
    }
    if (type === 'numero') {
      propre.groupes = (Array.isArray(p.groupes) ? p.groupes : []).slice(0, 8).map((g) => ({ texte: texte(g?.texte, 6), mot: texte(g?.mot, 30) })).filter((g) => g.texte && g.mot)
      if (!propre.groupes.length) erreurs.push(`plan ${i + 1} : le numéro n'a aucun groupe de chiffres`)
    }
    plans.push(propre)
  }
  if (!plans.length) erreurs.push('aucun plan')
  if (plans.length && plans[plans.length - 1].type !== 'carte') erreurs.push('le dernier plan doit être la carte finale')
  if (plans.length && plans[0].type !== 'accroche') erreurs.push("le premier plan doit être l'accroche (elle sert de miniature)")
  if (plans.some((p) => p.type === 'carte') && !marque.nom && !marque.logo) erreurs.push('la carte finale a besoin du nom ou du logo')
  // Le texte lu par la voix de Kekeli (il donne l'orthographe exacte des sous-titres) ; vide pour la voix du client.
  const voix = texte(brut.texte_voix, 2000)
  return { plan: erreurs.length ? null : { style, format, couleurs, marque, plans, ...(voix ? { texte_voix: voix } : {}) }, erreurs }
}
