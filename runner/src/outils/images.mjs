// Outil d'images de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel, à côté de photos.mjs ;
// l'agent le lance avec Node.js. Aucune dépendance : seulement Node.js et ImageMagick (« convert »).
//
// Ordre de préférence (décision du 9 novembre 2026) :
//   1. les photos du client (dossier images/client du projet) : node images.mjs client
//   2. une photo libre, cherchée sur plusieurs sources, regardée et notée par Kekeli :
//        node images.mjs chercher "mots-clés en anglais" --role "à quoi sert l'image" --fichier images/accueil.jpg
//   3. une image créée par un service gratuit :
//        node images.mjs creer "description en anglais" --fichier images/ambiance.jpg [--format paysage|carre|portrait]
//
// Règles : seulement des licences qui n'obligent pas à citer l'auteur (CC0, domaine public, licences Pexels) ;
// jamais de filigrane, ni de nom d'auteur effacé ou flouté, ni de personne réelle reconnaissable au premier plan ;
// les marques, enseignes, textes et visages qui apparaissent par hasard sont floutés. Chaque image enregistrée
// est notée dans LICENCES.md. Les réglages (pays du client, services disponibles) viennent de reglages.json,
// écrit par Kekeli ; les clés des services ne sont jamais dans l'ordinateur (le réseau les ajoute lui-même).
import { execFileSync } from 'node:child_process'
import { appendFileSync, existsSync, mkdirSync, readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { adresseRecherche, photosDepuisReponse } from './photos.mjs'

const ICI = dirname(fileURLToPath(import.meta.url))
export const DOSSIER_PROJET = process.env.KEKELI_PROJET ?? '/home/user/projet'
const FICHIER_REGLAGES = join(ICI, 'reglages.json')
const FICHIER_JOURNAL = join(ICI, 'journal.jsonl')
const AGENT = 'KekeliBot/1.0 (https://kekeli.dev)'
// Le vrai jeton est ajouté par le réseau de l'ordinateur ; l'outil n'envoie qu'une fausse valeur.
const FAUSSE_CLE = 'injectee-par-e2b'
const LARGEUR_MAXIMALE = 1600
const CANDIDATS_MAXIMUM = 10

export function reglages(fichier = FICHIER_REGLAGES) {
  try {
    return JSON.parse(readFileSync(fichier, 'utf8'))
  } catch {
    return {}
  }
}

/** Nombre d'images déjà créées pendant cette demande (le journal est vidé par Kekeli à la fin de chaque demande). */
export function imagesCreeesDansLaDemande(fichier = FICHIER_JOURNAL) {
  try {
    return readFileSync(fichier, 'utf8').split('\n').filter((ligne) => ligne.includes('"image_creee"')).length
  } catch {
    return 0
  }
}

/** Note une consommation dans le journal de Kekeli (requêtes au modèle de vision, images créées). */
export function noter(evenement, fichier = FICHIER_JOURNAL) {
  try {
    appendFileSync(fichier, `${JSON.stringify({ ...evenement, a: new Date().toISOString() })}\n`)
  } catch {}
}

const telechargerTexte = async (adresse, options = {}, telecharger = fetch) => {
  const reponse = await telecharger(adresse, { ...options, headers: { 'User-Agent': AGENT, ...(options.headers ?? {}) }, signal: AbortSignal.timeout(options.delai ?? 20000) })
  if (!reponse.ok) throw new Error(`${new URL(adresse).hostname} a répondu ${reponse.status}`)
  return reponse
}

// ---------------------------------------------------------------------------------------------------------------
// Sources de photos libres (sans attribution obligatoire)

/** Wikimedia Commons : CC0 et domaine public seulement, miniatures standard (1 280 pixels, aperçu 500 pixels). */
export function candidatsWikimedia(reponse) {
  return photosDepuisReponse(reponse).map((photo) => ({
    source: 'Wikimedia Commons',
    adresse: photo.adresse,
    apercu: photo.adresse.replace(/\/1280px-/, '/500px-'),
    largeur: photo.largeur,
    hauteur: photo.hauteur,
    titre: photo.titre,
    licence: photo.licence,
    page: photo.page,
  }))
}

/** Openverse : CC0 et marque du domaine public ; seulement les photos hébergées par Flickr (autres hôtes non autorisés). */
export function candidatsOpenverse(reponse) {
  return (reponse?.results ?? [])
    .filter((r) => ['cc0', 'pdm'].includes(String(r.license).toLowerCase()) && /^https:\/\/live\.staticflickr\.com\//.test(r.url ?? ''))
    .filter((r) => !r.width || r.width >= 800)
    .map((r) => ({
      source: 'Openverse (Flickr)',
      adresse: r.url,
      apercu: r.thumbnail || r.url,
      largeur: r.width ?? null,
      hauteur: r.height ?? null,
      titre: String(r.title ?? '').slice(0, 120) || 'Photo',
      licence: r.license === 'cc0' ? 'CC0' : 'Domaine public',
      page: r.foreign_landing_url ?? null,
    }))
}

/** Pexels : licence Pexels (aucune attribution obligatoire). */
export function candidatsPexels(reponse) {
  return (reponse?.photos ?? []).map((p) => ({
    source: 'Pexels',
    adresse: p.src?.large2x || p.src?.large || p.src?.original,
    apercu: p.src?.medium || p.src?.large,
    largeur: p.width ?? null,
    hauteur: p.height ?? null,
    titre: String(p.alt ?? '').slice(0, 120) || 'Photo',
    licence: 'Licence Pexels',
    page: p.url ?? null,
  })).filter((c) => c.adresse && c.apercu)
}

/** Mots-clés complétés par le pays du client (par exemple « togo west africa ») quand il n'y est pas déjà. */
export function motsClesAvecPays(motsCles, pays) {
  const anglais = String(pays?.anglais ?? '').toLowerCase()
  if (!anglais || anglais.split(/[\s,]+/).some((mot) => mot && String(motsCles).toLowerCase().includes(mot))) return [motsCles]
  return [`${motsCles} ${anglais.split(',')[0]}`.trim(), motsCles]
}

export async function chercherPartout(motsCles, { pays, services = [] } = {}, telecharger = fetch) {
  const requetes = motsClesAvecPays(motsCles, pays)
  const listes = []
  for (const requete of requetes) {
    const essais = [
      telechargerTexte(adresseRecherche(requete, 8), {}, telecharger).then((r) => r.json()).then(candidatsWikimedia),
      telechargerTexte(`https://api.openverse.org/v1/images/?${new URLSearchParams({ q: requete, license: 'cc0,pdm', page_size: '12', source: 'flickr' })}`, {}, telecharger)
        .then((r) => r.json())
        .then(candidatsOpenverse),
    ]
    if (services.includes('pexels')) {
      essais.push(
        telechargerTexte(`https://api.pexels.com/v1/search?${new URLSearchParams({ query: requete, per_page: '8' })}`, { headers: { Authorization: FAUSSE_CLE } }, telecharger)
          .then((r) => r.json())
          .then(candidatsPexels),
      )
    }
    listes.push(...(await Promise.allSettled(essais)).map((resultat) => (resultat.status === 'fulfilled' ? resultat.value : [])))
  }
  // Les sources sont mélangées (une de chaque à tour de rôle), sans doublon.
  const vus = new Set()
  const melange = []
  for (let i = 0; melange.length < CANDIDATS_MAXIMUM && listes.some((liste) => i < liste.length); i++) {
    for (const liste of listes) {
      const candidat = liste[i]
      if (candidat && !vus.has(candidat.adresse) && melange.length < CANDIDATS_MAXIMUM) {
        vus.add(candidat.adresse)
        melange.push(candidat)
      }
    }
  }
  return melange
}

// ---------------------------------------------------------------------------------------------------------------
// Le regard de Kekeli : un modèle de vision note chaque photo et repère ce qu'il faut refuser ou flouter.

export function consigneVision({ motsCles, role, pays }) {
  return [
    `Tu choisis des photos pour le site professionnel d'un client${pays?.nom ? ` situé au ${pays.nom} (${pays.region ?? ''})` : ''}.`,
    `Sujet cherché : « ${motsCles} ». Rôle de la photo dans le site : « ${role || 'illustration'} ».`,
    'Pour chaque photo numérotée, réponds en JSON : une liste d\'objets avec ces champs :',
    'numero (entier), description (une phrase en français), pertinence (0 à 10 : correspond au sujet et au rôle),',
    `qualite (0 à 10 : netteté, lumière, cadrage professionnel), adaptee_au_pays (0 à 10 : personnes, plats, lieux, tenues cohérents avec ${pays?.nom ?? 'le pays du client'} ; 5 si la photo ne montre rien de propre à un pays ; moins de 4 si elle montre clairement un autre pays ou une autre culture, par exemple une cuisine asiatique ou européenne pour un restaurant africain),`,
    'filigrane (vrai si un filigrane ou un logo de banque d\'images est visible), nom_auteur_efface (vrai si un nom ou une signature semble effacé, flouté ou coupé),',
    'personne_reconnaissable (vrai si une personne réelle reconnaissable est le sujet principal, visage net et de face),',
    'zones_a_flouter : liste des zones à flouter, chacune { "genre": "visage" | "marque" | "texte", "boite": [ymin, xmin, ymax, xmax] } en millièmes de l\'image (0 à 1000) :',
    'les visages secondaires reconnaissables, les logos, marques, enseignes et textes lisibles qui apparaissent par hasard.',
  ].join('\n')
}

/** Analyse des aperçus par le modèle de vision (Gemini, sans clé dans l'ordinateur). */
export async function regarder(candidats, contexte, { modele, telecharger = fetch } = {}) {
  const parties = [{ text: consigneVision(contexte) }]
  const presents = []
  for (const [i, candidat] of candidats.entries()) {
    try {
      const reponse = await telechargerTexte(candidat.apercu, { delai: 15000 }, telecharger)
      const type = reponse.headers.get('content-type') ?? ''
      if (!/^image\/(jpeg|png|webp)/.test(type)) continue
      const octets = Buffer.from(await reponse.arrayBuffer())
      if (octets.length > 4_000_000) continue
      parties.push({ text: `Photo numéro ${i + 1} :` }, { inline_data: { mime_type: type.split(';')[0], data: octets.toString('base64') } })
      presents.push(i + 1)
    } catch {}
  }
  if (!presents.length) return []
  const reponse = await telechargerTexte(
    `https://generativelanguage.googleapis.com/v1beta/models/${modele}:generateContent`,
    {
      method: 'POST',
      delai: 90000,
      headers: { 'Content-Type': 'application/json', 'x-goog-api-key': FAUSSE_CLE },
      body: JSON.stringify({ contents: [{ parts: parties }], generationConfig: { responseMimeType: 'application/json', temperature: 0.2 } }),
    },
    telecharger,
  )
  const donnees = await reponse.json()
  const usage = donnees.usageMetadata ?? {}
  noter({ type: 'requete', fournisseur: 'gemini', modele, tokens_entree: usage.promptTokenCount ?? 0, tokens_sortie: usage.candidatesTokenCount ?? 0, tokens_reflexion: usage.thoughtsTokenCount ?? 0, tokens_cache: usage.cachedContentTokenCount ?? 0 })
  const texte = donnees.candidates?.[0]?.content?.parts?.map((p) => p.text ?? '').join('') ?? '[]'
  const avis = JSON.parse(texte.replace(/^```(json)?|```$/g, '').trim())
  return (Array.isArray(avis) ? avis : avis.photos ?? []).filter((a) => presents.includes(Number(a.numero)))
}

/** Meilleure photo acceptable selon les avis, ou null. Les photos refusées ne sont jamais choisies. */
export function choisir(candidats, avis) {
  const notes = avis
    // Une photo contraire à la réalité du pays du client (par exemple un plat d'un autre continent) est refusée.
    .filter((a) => !a.filigrane && !a.nom_auteur_efface && !a.personne_reconnaissable && Number(a.pertinence) >= 6 && Number(a.qualite) >= 5 && Number(a.adaptee_au_pays ?? 5) >= 4)
    .map((a) => ({ avis: a, candidat: candidats[Number(a.numero) - 1], score: Number(a.pertinence) * 2 + Number(a.qualite) + Number(a.adaptee_au_pays ?? 5) * 1.5 }))
    .filter((n) => n.candidat)
    .sort((a, b) => b.score - a.score)
  return notes[0] ?? null
}

// ---------------------------------------------------------------------------------------------------------------
// Enregistrement : taille raisonnable, zones floutées, métadonnées retirées.

/** Arguments d'ImageMagick pour flouter des zones (boîtes en millièmes de l'image), après redimensionnement. */
export function argumentsImageMagick(entree, sortie, { largeur, hauteur, zones = [], recadrage = null }) {
  const args = [entree, '-auto-orient']
  let l = largeur
  let h = hauteur
  if (recadrage) {
    args.push('-resize', `${recadrage.largeur}x${recadrage.hauteur}^`, '-gravity', 'center', '-extent', `${recadrage.largeur}x${recadrage.hauteur}`, '+repage', '-gravity', 'NorthWest')
    l = recadrage.largeur
    h = recadrage.hauteur
  } else if (l > LARGEUR_MAXIMALE) {
    args.push('-resize', `${LARGEUR_MAXIMALE}x`)
    h = Math.round((h * LARGEUR_MAXIMALE) / l)
    l = LARGEUR_MAXIMALE
  }
  for (const zone of zones) {
    const [ymin, xmin, ymax, xmax] = (zone.boite ?? []).map(Number)
    if (![ymin, xmin, ymax, xmax].every(Number.isFinite) || ymax <= ymin || xmax <= xmin) continue
    // Un peu de marge autour de la zone, pour que rien ne dépasse du flou.
    const x = Math.max(0, Math.floor(((xmin - 15) / 1000) * l))
    const y = Math.max(0, Math.floor(((ymin - 15) / 1000) * h))
    const w = Math.min(l - x, Math.ceil(((xmax - xmin + 30) / 1000) * l))
    const hz = Math.min(h - y, Math.ceil(((ymax - ymin + 30) / 1000) * h))
    if (w < 2 || hz < 2) continue
    args.push('(', '+clone', '-crop', `${w}x${hz}+${x}+${y}`, '+repage', '-scale', '8%', '-scale', '1250%', '-blur', '0x12', ')', '-geometry', `+${x}+${y}`, '-composite')
  }
  args.push('-strip', '-interlace', 'Plane', '-quality', '82', sortie)
  return args
}

function dimensions(fichier) {
  const [l, h] = execFileSync('identify', ['-format', '%w %h', `${fichier}[0]`]).toString().trim().split(/\s+/).map(Number)
  return { largeur: l, hauteur: h }
}

function cheminDansLeProjet(fichier) {
  const absolu = resolve(DOSSIER_PROJET, fichier)
  if (!absolu.startsWith(`${DOSSIER_PROJET}/`)) throw new Error('Le fichier doit être dans le dossier du projet.')
  if (!/\.(jpe?g|webp|png)$/i.test(absolu)) throw new Error('Le fichier doit finir par .jpg, .webp ou .png.')
  mkdirSync(dirname(absolu), { recursive: true })
  return absolu
}

/** Note l'image dans LICENCES.md (une ligne par fichier, remplacée si l'image change). */
export function noterLicence(cheminRelatif, ligne, dossier = DOSSIER_PROJET) {
  const fichier = join(dossier, 'LICENCES.md')
  const entete = '# Licences des ressources du projet\n\n'
  const lignes = (existsSync(fichier) ? readFileSync(fichier, 'utf8') : entete).split('\n')
  const debut = `- ${cheminRelatif} — `
  const autres = lignes.filter((l) => !l.startsWith(debut))
  while (autres.length && autres.at(-1) === '') autres.pop()
  // Une ligne vide sépare le titre de la liste.
  if (autres.length && autres.at(-1).startsWith('#')) autres.push('')
  writeFileSync(fichier, `${autres.join('\n')}\n${debut}${ligne}\n`)
}

// ---------------------------------------------------------------------------------------------------------------
// Commandes

export async function commandeChercher(motsCles, { role = '', fichier }, { telecharger = fetch, regler = reglages() } = {}) {
  if (!fichier) throw new Error('Indique le fichier à enregistrer, par exemple --fichier images/accueil.jpg')
  const cible = cheminDansLeProjet(fichier)
  const candidats = await chercherPartout(motsCles, { pays: regler.pays, services: regler.services ?? [] }, telecharger)
  if (!candidats.length) return { ok: false, message: `Aucune photo libre trouvée pour « ${motsCles} ». Essaie d'autres mots-clés en anglais${(regler.services ?? []).includes('cloudflare') ? ', ou crée l\'image avec : node images.mjs creer' : ', ou crée une illustration en SVG ou en CSS'}.` }

  let choix = null
  let regard = true
  if (regler.vision) {
    const avis = await regarder(candidats, { motsCles, role, pays: regler.pays }, { modele: regler.vision, telecharger }).catch(() => null)
    if (avis) choix = choisir(candidats, avis)
    else regard = false
    if (avis && !choix) {
      return { ok: false, message: `Kekeli a regardé ${candidats.length} photos libres pour « ${motsCles} » : aucune ne convient (sujet, qualité, filigrane ou personne reconnaissable). Essaie d'autres mots-clés en anglais${(regler.services ?? []).includes('cloudflare') ? ', ou crée l\'image avec : node images.mjs creer' : ''}.` }
    }
  } else regard = false
  // Sans regard possible, la première grande photo est prise, sans floutage : c'est signalé.
  choix ??= { candidat: candidats.find((c) => (c.largeur ?? 1000) >= 1000) ?? candidats[0], avis: { zones_a_flouter: [] } }

  const brut = `${cible}.telechargement`
  const reponse = await telechargerTexte(choix.candidat.adresse, { delai: 40000 }, telecharger)
  writeFileSync(brut, Buffer.from(await reponse.arrayBuffer()))
  const zones = (choix.avis.zones_a_flouter ?? []).filter((z) => ['visage', 'marque', 'texte'].includes(z.genre))
  execFileSync('convert', argumentsImageMagick(brut, cible, { ...dimensions(brut), zones }))
  execFileSync('rm', ['-f', brut])
  const relatif = relative(DOSSIER_PROJET, cible)
  const floutees = zones.length ? ` ; ${zones.length} zone(s) floutée(s) (${[...new Set(zones.map((z) => z.genre))].join(', ')})` : ''
  noterLicence(relatif, `« ${choix.candidat.titre} », source : ${choix.candidat.source}${choix.candidat.page ? ` (${choix.candidat.page})` : ''}, licence ${choix.candidat.licence} (aucune attribution obligatoire)${floutees}`)
  const taille = dimensions(cible)
  return {
    ok: true,
    message: [
      `Photo enregistrée : ${relatif} (${taille.largeur} × ${taille.hauteur}), source ${choix.candidat.source}, licence ${choix.candidat.licence}.`,
      choix.avis.description ? `Ce qu'elle montre : ${choix.avis.description}` : null,
      zones.length ? `Zones floutées : ${zones.map((z) => z.genre).join(', ')}.` : null,
      regard ? `Choisie parmi ${candidats.length} photos regardées par Kekeli.` : 'Attention : la photo n\'a pas pu être regardée par Kekeli ; vérifie qu\'elle convient.',
      'La ligne de licence est déjà écrite dans LICENCES.md. Utilise ce chemin relatif dans la page, avec un texte « alt » qui décrit la photo.',
    ].filter(Boolean).join('\n'),
  }
}

export const FORMATS = { paysage: { largeur: 1600, hauteur: 900 }, carre: { largeur: 1200, hauteur: 1200 }, portrait: { largeur: 1000, hauteur: 1250 } }

/** Description envoyée au service de création : réaliste, adaptée au pays, sans texte ni marque. */
export function descriptionCreation(description, pays) {
  const contexte = pays?.anglais ? ` Setting: ${pays.anglais}; people, food, clothing and places typical of ${pays.anglais.split(',')[0]}.` : ''
  return `${description}.${contexte} Professional photography style, natural light, high quality. No text, no letters, no logo, no brand, no watermark, no famous or real identifiable person.`
}

export async function commandeCreer(description, { fichier, format = 'paysage' }, { telecharger = fetch, regler = reglages(), dejaCreees = imagesCreeesDansLaDemande() } = {}) {
  if (!fichier) throw new Error('Indique le fichier à enregistrer, par exemple --fichier images/ambiance.jpg')
  if (!(regler.services ?? []).includes('cloudflare') || !regler.cloudflare_compte) {
    return { ok: false, message: "La création d'images n'est pas disponible pour le moment. Utilise une photo du client, une photo libre (node images.mjs chercher), ou une illustration en SVG ou en CSS." }
  }
  // Nombre maximum d'images créées par jour pour tout Kekeli, pour rester dans l'offre gratuite du service.
  if (dejaCreees >= Number(regler.images_max ?? 0)) {
    return { ok: false, message: "Plus de création d'image possible aujourd'hui. Utilise une photo du client, une photo libre (node images.mjs chercher), ou une illustration en SVG ou en CSS." }
  }
  const cible = cheminDansLeProjet(fichier)
  const reponse = await telechargerTexte(
    `https://api.cloudflare.com/client/v4/accounts/${encodeURIComponent(regler.cloudflare_compte)}/ai/run/@cf/black-forest-labs/flux-1-schnell`,
    { method: 'POST', delai: 90000, headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${FAUSSE_CLE}` }, body: JSON.stringify({ prompt: descriptionCreation(description, regler.pays), steps: 4 }) },
    telecharger,
  )
  const donnees = await reponse.json()
  const image = donnees?.result?.image
  if (!image) throw new Error("Le service de création n'a pas renvoyé d'image.")
  const brut = `${cible}.creation`
  writeFileSync(brut, Buffer.from(image, 'base64'))
  const recadrage = FORMATS[format] ?? FORMATS.paysage
  execFileSync('convert', argumentsImageMagick(brut, cible, { ...dimensions(brut), recadrage }))
  execFileSync('rm', ['-f', brut])
  noter({ type: 'image_creee', service: 'cloudflare', modele: 'flux-1-schnell' })
  const relatif = relative(DOSSIER_PROJET, cible)
  noterLicence(relatif, 'image créée par Kekeli (FLUX.1 schnell sur Cloudflare Workers AI) ; illustration, ce n\'est pas une vraie photo ; aucune attribution obligatoire')
  return {
    ok: true,
    message: `Image créée : ${relatif} (${recadrage.largeur} × ${recadrage.hauteur}). C'est une illustration : ne la présente jamais comme une vraie photo d'un produit du client. La ligne de licence est déjà écrite dans LICENCES.md.`,
  }
}

/** Photos envoyées par le client (dossier images/client), avec leur taille. */
export function commandeClient(dossier = join(DOSSIER_PROJET, 'images', 'client')) {
  if (!existsSync(dossier)) return { ok: true, message: "Le client n'a pas encore envoyé de photos. Pour ses vrais produits (plats, articles), propose-lui d'en envoyer avec le bouton « Mes photos »." }
  const photos = readdirSync(dossier).filter((nom) => /\.(jpe?g|png|webp)$/i.test(nom))
  if (!photos.length) return { ok: true, message: "Le client n'a pas encore envoyé de photos." }
  const lignes = photos.map((nom) => {
    let taille = ''
    try {
      const { largeur, hauteur } = dimensions(join(dossier, nom))
      taille = ` (${largeur} × ${hauteur})`
    } catch {}
    return `- images/client/${nom}${taille}, ${Math.round(statSync(join(dossier, nom)).size / 1024)} Ko`
  })
  return { ok: true, message: `Photos du client (à utiliser en priorité, à l'endroit qui correspond à leur contenu ; regarde-les avant de les placer) :\n${lignes.join('\n')}` }
}

export function lireOptions(args) {
  const options = {}
  const libres = []
  for (let i = 0; i < args.length; i++) {
    if (args[i].startsWith('--')) options[args[i].slice(2)] = args[++i]
    else libres.push(args[i])
  }
  return { texte: libres.join(' '), options }
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  const [action, ...reste] = process.argv.slice(2)
  const { texte, options } = lireOptions(reste)
  const executer = {
    chercher: () => commandeChercher(texte, options),
    creer: () => commandeCreer(texte, options),
    client: async () => commandeClient(),
  }[action]
  if (!executer || (action !== 'client' && !texte)) {
    console.log([
      'Utilisation :',
      '  node images.mjs client',
      '  node images.mjs chercher "mots-clés en anglais" --role "rôle de la photo" --fichier images/nom.jpg',
      '  node images.mjs creer "description en anglais" --fichier images/nom.jpg [--format paysage|carre|portrait]',
    ].join('\n'))
    process.exit(1)
  }
  executer()
    .then((resultat) => {
      console.log(resultat.message)
      if (!resultat.ok) process.exitCode = 2
    })
    .catch((erreur) => {
      console.log(`Impossible pour le moment : ${erreur.message}`)
      process.exitCode = 1
    })
}

