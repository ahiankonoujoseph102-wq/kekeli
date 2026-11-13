// Essai comparatif des générateurs d'images pour « Images et affiches » (13 novembre 2026).
// Méthode 1 : le générateur crée seulement le fond (sans texte), le texte est posé par le code (kekeli_images.py).
// Méthode 2 : le générateur dessine toute l'affiche, texte compris (pour juger s'il écrit le français sans faute).
// Les clés restent dans les variables d'environnement ; rien n'est affiché. Plafond OpenAI : PLAFOND_USD.
//
//   cd runner && node scripts/comparer-images.mjs ../docs/captures/images/comparaison [jeu,jeu]
import { execFileSync } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { numeroCompteCloudflare, valeurCle } from '../src/fournisseurs.js'

const dossier = process.argv[2] || '../docs/captures/images/comparaison'
const seulement = (process.argv[3] || '').split(',').filter(Boolean)
const PLAFOND_USD = 0.9
const MOTEUR = new URL('../src/documents/rendu/kekeli_images.py', import.meta.url).pathname
// Prix officiels d'OpenAI (13 novembre 2026), en dollars par million de tokens.
const PRIX_OPENAI = { texte: 5, image_entree: 8, image_sortie: 30 }
const FCFA = 590

const SANS_TEXTE = 'No text, no letters, no words, no numbers, no logo, no watermark.'
const JEUX = [
  {
    nom: 'pagnes', format: 'vertical', taille: '1024x1536', largeur: 768, hauteur: 1152,
    fond: `Colorful African wax print fabrics neatly folded and stacked on a wooden market stall in Lomé, Togo, warm natural light, vibrant orange, blue and green patterns, shallow depth of field, professional advertising photo, empty space at the bottom. ${SANS_TEXTE}`,
    structure: { usage: 'affiche', format: 'vertical', theme: { couleur: '#E3A008' }, textes: { surtitre: 'Promotion', titre: 'Pagnes Ama : -20 % sur tout le wax', sous_titre: 'Grand Marché de Lomé, du 15 au 30 novembre', prix: '9 600 FCFA le pagne', prix_barre: '12 000 FCFA', contact: 'Appelez le 90 00 00 00', paiement: 'Paiement par Mixx by Yas ou Flooz' } },
    affiche: 'A vertical promotional poster for a fabric shop in Lomé, Togo, with colorful African wax print fabrics stacked on a market stall. The poster must show exactly this French text, spelled exactly, nothing else: title "Pagnes Ama : -20 % sur tout le wax", subtitle "Grand Marché de Lomé, du 15 au 30 novembre", old price "12 000 FCFA" crossed out, new price "9 600 FCFA le pagne" in a yellow badge, "Appelez le 90 00 00 00", "Paiement par Mixx by Yas ou Flooz". Clean modern typography, professional design.',
  },
  {
    nom: 'ecole', format: 'a4', taille: '1024x1536', largeur: 768, hauteur: 1088,
    fond: `A bright modern training classroom in West Africa with laptops on desks, students seen from behind, a screen at the front, soft daylight, clean professional photo, calm composition with empty space at the bottom. ${SANS_TEXTE}`,
    structure: { usage: 'affiche', format: 'a4', theme: { couleur: '#2F6FDE' }, textes: { surtitre: 'Inscriptions ouvertes', titre: 'Formez-vous à l’intelligence artificielle', sous_titre: 'Lomé IA School, Adidogomé', details: ['Initiation : 2 jours, 25 000 FCFA', 'Agents IA : 5 jours, 75 000 FCFA', 'Automatisations : 3 jours, 45 000 FCFA'], date: 'Rentrée le lundi 1er décembre 2026', contact: 'Renseignements : 90 00 00 00' } },
    affiche: 'An A4 vertical enrollment poster for an artificial intelligence training school in Lomé, Togo, with a bright classroom and laptops. The poster must show exactly this French text, spelled exactly: "Inscriptions ouvertes", title "Formez-vous à l’intelligence artificielle", "Lomé IA School, Adidogomé", three lines "Initiation : 2 jours, 25 000 FCFA", "Agents IA : 5 jours, 75 000 FCFA", "Automatisations : 3 jours, 45 000 FCFA", "Rentrée le lundi 1er décembre 2026", "Renseignements : 90 00 00 00". Clean professional design.',
  },
  {
    nom: 'chaussures', format: 'carre', taille: '1024x1024', largeur: 1024, hauteur: 1024,
    fond: `Professional studio product photo of a pair of elegant brown leather men's shoes on a warm beige background, soft shadows, sharp details, e-commerce style. ${SANS_TEXTE}`,
    structure: { usage: 'photo_produit', format: 'carre', textes: {} },
  },
  {
    nom: 'banniere', format: 'carre', taille: '1024x1024', largeur: 1024, hauteur: 1024,
    fond: `Top view of a delicious West African plate of rice with grilled fish, tomato sauce and fried plantain on a wooden table, appetizing food photography, natural light, empty dark space on the lower half. ${SANS_TEXTE}`,
    structure: { usage: 'banniere', format: 'carre', theme: { couleur: '#E3A008' }, textes: { surtitre: 'Chez Tante Afi', titre: 'Le plat du jour à 1 500 FCFA', sous_titre: 'Riz, poisson braisé et alloco', contact: 'Livraison : 90 00 00 00' } },
    affiche: 'A square Facebook banner for a small restaurant in Lomé, with a top view of rice, grilled fish and fried plantain. It must show exactly this French text, spelled exactly: "Chez Tante Afi", "Le plat du jour à 1 500 FCFA", "Riz, poisson braisé et alloco", "Livraison : 90 00 00 00". Modern clean design.',
  },
  {
    nom: 'logo', format: 'carre', taille: '1024x1024', largeur: 1024, hauteur: 1024, logo: true,
    fond: `Elegant flat lay of folded African wax print fabrics in indigo and gold tones on a light linen background, soft natural light, minimal, premium boutique feel. ${SANS_TEXTE}`,
    structure: { usage: 'banniere', format: 'carre', disposition: 'bas', theme: { couleur: '#E3A008' }, textes: { titre: 'Nouvelle collection', sous_titre: 'Pagnes Ama, Grand Marché de Lomé', contact: 'WhatsApp : 90 00 00 00' } },
  },
]

let depenseUsd = 0
const resultats = existsSync(`${dossier}/comparaison.json`) ? JSON.parse(readFileSync(`${dossier}/comparaison.json`, 'utf8')) : []
const noter = (r) => {
  const i = resultats.findIndex((x) => x.jeu === r.jeu && x.generateur === r.generateur && x.methode === r.methode)
  if (i >= 0) resultats.splice(i, 1, r)
  else resultats.push(r)
  writeFileSync(`${dossier}/comparaison.json`, JSON.stringify(resultats, null, 2))
}

async function cloudflare(modele, jeu) {
  const compte = numeroCompteCloudflare()
  const url = `https://api.cloudflare.com/client/v4/accounts/${encodeURIComponent(compte)}/ai/run/@cf/black-forest-labs/${modele}`
  const entetes = { Authorization: `Bearer ${valeurCle('CLOUDFLARE_API_TOKEN')}` }
  let options
  if (modele === 'flux-2-klein-4b') {
    const formulaire = new FormData()
    formulaire.append('prompt', jeu.fond)
    formulaire.append('width', String(jeu.largeur))
    formulaire.append('height', String(jeu.hauteur))
    options = { method: 'POST', headers: entetes, body: formulaire }
  } else {
    options = { method: 'POST', headers: { ...entetes, 'Content-Type': 'application/json' }, body: JSON.stringify({ prompt: jeu.fond, steps: 4 }) }
  }
  const reponse = await fetch(url, options)
  const donnees = await reponse.json().catch(() => ({}))
  if (!reponse.ok || !donnees.result?.image) throw new Error(`Cloudflare ${reponse.status}`)
  // Neurones estimés (page officielle) : schnell 4,80 par tuile de 512 px + 9,60 par pas ; klein 26,05 par tuile.
  const tuiles = Math.ceil(jeu.largeur / 512) * Math.ceil(jeu.hauteur / 512)
  const neurones = modele === 'flux-2-klein-4b' ? 26.05 * tuiles : 4.8 * 4 + 9.6 * 4
  return { image: Buffer.from(donnees.result.image, 'base64'), extension: 'jpg', neurones, usd: (neurones / 1000) * 0.011 }
}

async function openai(modele, invite, taille, qualite, logo) {
  if (depenseUsd > PLAFOND_USD) throw new Error('plafond OpenAI atteint')
  const cle = valeurCle('OPENAI_API_KEY')
  let reponse
  if (logo) {
    const formulaire = new FormData()
    formulaire.append('model', modele)
    formulaire.append('prompt', invite)
    formulaire.append('size', taille)
    formulaire.append('quality', qualite)
    formulaire.append('image[]', new Blob([readFileSync(logo)], { type: 'image/png' }), 'logo.png')
    reponse = await fetch('https://api.openai.com/v1/images/edits', { method: 'POST', headers: { Authorization: `Bearer ${cle}` }, body: formulaire })
  } else {
    reponse = await fetch('https://api.openai.com/v1/images/generations', {
      method: 'POST',
      headers: { Authorization: `Bearer ${cle}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({ model: modele, prompt: invite, size: taille, quality: qualite, output_format: 'png' }),
    })
  }
  const donnees = await reponse.json().catch(() => ({}))
  if (!reponse.ok || !donnees.data?.[0]?.b64_json) throw new Error(`OpenAI ${reponse.status} ${donnees.error?.code ?? ''}`)
  const u = donnees.usage ?? {}
  const imageEntree = u.input_tokens_details?.image_tokens ?? 0
  const texteEntree = (u.input_tokens ?? 0) - imageEntree
  const usd = (texteEntree * PRIX_OPENAI.texte + imageEntree * PRIX_OPENAI.image_entree + (u.output_tokens ?? 0) * PRIX_OPENAI.image_sortie) / 1e6
  depenseUsd += usd
  return { image: Buffer.from(donnees.data[0].b64_json, 'base64'), extension: 'png', usd, tokens: u }
}

function composer(jeu, fond, nom, logo) {
  const travail = `${dossier}/.travail-${nom}`
  mkdirSync(travail, { recursive: true })
  writeFileSync(`${travail}/structure.json`, JSON.stringify(jeu.structure))
  execFileSync('python3', ['-I', MOTEUR, 'composer', `${travail}/structure.json`, travail, fond, logo ?? '-', '-'])
  execFileSync('cp', [`${travail}/image.png`, `${dossier}/${nom}.png`])
  execFileSync('rm', ['-rf', travail])
}

async function essayer(jeu, generateur, methode, fabriquer) {
  const debut = Date.now()
  try {
    const r = await fabriquer()
    const secondes = Math.round((Date.now() - debut) / 100) / 10
    const fichier = `${jeu.nom}-${generateur}-${methode}.${r.extension}`
    writeFileSync(`${dossier}/${fichier}`, r.image)
    if (methode === 'fond-et-texte-par-le-code' && (jeu.structure.textes && Object.keys(jeu.structure.textes).length || jeu.logo)) {
      composer(jeu, `${dossier}/${fichier}`, `${jeu.nom}-${generateur}-affiche-finale`, jeu.logo ? `${dossier}/logo-pagnes-ama.png` : null)
    }
    noter({ jeu: jeu.nom, generateur, methode, secondes, usd: Math.round(r.usd * 10000) / 10000, fcfa: Math.round(r.usd * FCFA * 10) / 10, fichier })
    console.log(jeu.nom, generateur, methode, `${secondes} s`, `${r.usd.toFixed(4)} $`)
  } catch (erreur) {
    noter({ jeu: jeu.nom, generateur, methode, erreur: erreur.message })
    console.log(jeu.nom, generateur, methode, 'erreur', erreur.message)
  }
}

mkdirSync(dossier, { recursive: true })
for (const jeu of JEUX.filter((j) => !seulement.length || seulement.includes(j.nom))) {
  const methode = 'fond-et-texte-par-le-code'
  await essayer(jeu, 'cloudflare-flux-1-schnell', methode, () => cloudflare('flux-1-schnell', jeu))
  await essayer(jeu, 'cloudflare-flux-2-klein-4b', methode, () => cloudflare('flux-2-klein-4b', jeu))
  await essayer(jeu, 'openai-gpt-image-2.6-flare', methode, () => openai('gpt-image-2.6-flare', jeu.fond, jeu.taille, 'medium'))
  if (jeu.affiche) {
    await essayer(jeu, 'openai-gpt-image-2.6-flare', 'texte-dessine-par-le-modele', () => openai('gpt-image-2.6-flare', jeu.affiche, jeu.taille, 'medium'))
    if (jeu.nom === 'pagnes') await essayer(jeu, 'openai-gpt-image-2.6-sunburst', 'texte-dessine-par-le-modele', () => openai('gpt-image-2.6-sunburst', jeu.affiche, jeu.taille, 'medium'))
  }
  if (jeu.logo) {
    await essayer(jeu, 'openai-gpt-image-2.6-flare', 'logo-integre-par-le-modele', () => openai('gpt-image-2.6-flare', 'A square Facebook banner for the fabric shop whose logo is attached: elegant folded African wax fabrics in indigo and gold. Place the attached logo unchanged at the top left. Text, spelled exactly: "Nouvelle collection", "Pagnes Ama, Grand Marché de Lomé", "WhatsApp : 90 00 00 00".', jeu.taille, 'medium', `${dossier}/logo-pagnes-ama.png`))
  }
}
console.log('Dépense OpenAI de cette exécution :', depenseUsd.toFixed(4), '$')
