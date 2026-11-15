// Banc d'essai des affiches et des images (chantier « Kekeli fait tout correctement », 15 novembre 2026).
// 10 affiches et 5 images réalistes, chaque générateur, et pour chaque affiche les deux méthodes :
//   - « dessine » : le générateur dessine toute l'affiche, texte compris (comme ChatGPT) ;
//   - « code »    : le générateur crée seulement le fond, sans texte, et le texte est posé par le code (kekeli_images.py).
// Chaque affiche dessinée est relue par le modèle de vision de Kekeli (texte manquant, faux ou non demandé).
// Les clés restent dans les variables d'environnement ; rien n'est affiché. Plafond OpenAI : PLAFOND_USD.
//
//   cd runner && node scripts/banc-images.mjs <dossier> [générateurs séparés par des virgules] [jeux séparés par des virgules]
//   Générateurs : cf-schnell, cf-klein-4b, cf-klein-9b, cf-flux-2-dev, cf-phoenix, cf-lucid, cf-sdxl-lightning,
//   cf-dreamshaper, openai-flare. Les résultats s'ajoutent à <dossier>/banc.json (une ligne par essai).
import { execFileSync } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { numeroCompteCloudflare, valeurCle } from '../src/fournisseurs.js'
import { inviteAffiche, lireTextes, structureImage, textesAttendus, texteConforme, TAILLES, SANS_TEXTE } from '../src/documents/images.js'
import { prendreAppelCloudflare } from './reserve-essais.mjs'

const dossier = process.argv[2] || '../docs/captures/fonctions/images'
const generateursChoisis = (process.argv[3] || '').split(',').filter(Boolean)
const jeuxChoisis = (process.argv[4] || '').split(',').filter(Boolean)
const PLAFOND_USD = Number(process.env.PLAFOND_USD || 1.5)
const MOTEUR = new URL('../src/documents/rendu/kekeli_images.py', import.meta.url).pathname
const FCFA = 590
// Prix officiels (developers.openai.com/api/docs/pricing, lus le 15 novembre 2026), dollars par million de tokens.
const PRIX_OPENAI = { texte: 5, image_entree: 8, image_sortie: 30 }
const VISION = 'gpt-6.1-terra'

const affiche = (nom, format, theme, fond, textes, usage = 'affiche') => ({ nom, genre: 'affiche', structure: structureImage({ usage, format, disposition: 'bas', theme: { couleur: theme }, fond: { description: `${fond} ${SANS_TEXTE}` }, textes }) })
const image = (nom, format, fond, usage = 'photo_produit') => ({ nom, genre: 'image', structure: structureImage({ usage, format, fond: { description: `${fond} ${SANS_TEXTE}` }, textes: {} }) })

export const JEUX = [
  affiche('pagnes', 'vertical', '#E3A008', 'Colorful African wax print fabrics neatly folded and stacked on a wooden market stall in Lomé, Togo, warm natural light, vibrant orange, blue and green patterns, professional advertising photo, empty space at the bottom.', { surtitre: 'Promotion', titre: 'Pagnes Ama : -20 % sur tout le wax', sous_titre: 'Grand Marché de Lomé, du 15 au 30 novembre', prix: '9 600 FCFA le pagne', prix_barre: '12 000 FCFA', contact: 'Appelez le 90 00 00 00' }),
  affiche('inscription', 'a4', '#2F6FDE', 'A bright modern training classroom in West Africa with laptops on desks, students seen from behind, soft daylight, clean professional photo, empty space at the bottom.', { surtitre: 'Inscriptions ouvertes', titre: 'Formez-vous à l’intelligence artificielle', sous_titre: 'Lomé IA School, Adidogomé', date: 'Rentrée le lundi 7 décembre 2026', contact: 'Renseignements : 90 00 00 00' }),
  affiche('programme', 'vertical', '#7C3AED', 'A young West African woman smiling while working on a laptop in a bright modern coworking space, soft natural light, professional photo, calm composition with large empty space.', { surtitre: 'Programme de la formation', titre: 'Créer son agent IA en 5 jours', details: ['Jour 1 : Bases de l’IA', 'Jour 2 : Écrire de bonnes consignes', 'Jour 3 : Créer son agent', 'Jour 4 et 5 : Projet final'], prix: '75 000 FCFA', contact: 'WhatsApp : 90 00 00 00' }),
  affiche('restaurant', 'carre', '#E3A008', 'Top view of a delicious West African plate of rice with grilled fish, tomato sauce and fried plantain on a wooden table, appetizing food photography, natural light, empty dark space on the lower half.', { surtitre: 'Chez Tante Afi', titre: 'Le plat du jour à 1 500 FCFA', sous_titre: 'Riz, poisson braisé et alloco', contact: 'Livraison : 90 00 00 00' }),
  affiche('paiement', 'carre', '#0F9D58', 'A smiling West African shop owner holding a smartphone at the counter of a small modern grocery shop in Lomé, warm light, professional photo, empty space at the bottom.', { titre: 'Payez par Mixx by Yas ou Flooz', sous_titre: 'Livraison gratuite dès 10 000 FCFA d’achat', lieu: 'Supérette Kodjo, Bè Kpota', contact: '90 00 00 00' }, 'banniere'),
  affiche('concert', 'a4', '#DB2777', 'A lively outdoor night concert stage with colorful lights and a happy crowd seen from behind, Lomé, Togo, vibrant atmosphere, professional event photo, empty space at the bottom.', { surtitre: 'Soirée Afro', titre: 'Lomé Vibes Live', date: 'Samedi 12 décembre 2026, 20 h', lieu: 'Esplanade du Palais des congrès', prix: 'Entrée : 2 000 FCFA' }),
  affiche('coiffure', 'carre', '#C2410C', 'Close-up of beautiful neat African braids hairstyle seen from behind in a bright modern hair salon, soft light, professional beauty photo, empty space at the bottom.', { titre: 'Tresses à partir de 5 000 FCFA', sous_titre: 'Salon Belle Afi, Adidogomé', contact: 'Rendez-vous : 90 00 00 00' }),
  affiche('telephones', 'paysage', '#2563EB', 'Modern smartphones displayed on a clean white shelf in a bright small electronics shop, soft studio light, professional photo, empty space on the left.', { surtitre: 'Grande ouverture', titre: 'Kossi Phones à Agoè', sous_titre: 'Téléphones et accessoires garantis 6 mois', date: 'Le 5 décembre 2026', contact: '90 00 00 00' }, 'banniere'),
  affiche('gagner-argent', 'vertical', '#E3A008', 'A young West African entrepreneur working on a laptop and a smartphone at a desk at home, warm light, plants, professional photo, calm composition with large empty space.', { surtitre: 'Conseils pratiques', titre: '5 façons de gagner de l’argent sur Internet', details: ['Vendre sur WhatsApp et Facebook', 'Proposer ses services en ligne', 'Créer des contenus utiles', 'Donner des formations'], contact: 'Formation : 90 00 00 00' }),
  affiche('recrutement', 'a4', '#0F766E', 'A bright clean modern pharmacy counter in Lomé with shelves of medicine boxes slightly blurred, professional photo, empty space at the bottom.', { surtitre: 'Nous recrutons', titre: 'Deux vendeuses en pharmacie', details: ['Bac exigé', 'Expérience de 1 an', 'Disponible tout de suite'], contact: 'CV : 90 00 00 00' }),
  image('sac', 'carre', 'Professional studio product photo of an elegant handbag made of colorful African wax print fabric with leather handles, on a warm beige background, soft shadows, sharp details, e-commerce style.'),
  image('ambiance', 'paysage', 'A cozy outdoor restaurant terrace by the beach in Lomé at sunset, wooden tables with warm string lights, palm trees, golden hour, inviting atmosphere, professional photo.', 'illustration'),
  affiche('banniere-pressing', 'paysage', '#0EA5E9', 'Clean folded white shirts and colorful clothes on hangers in a bright modern laundry shop, fresh and clean feeling, professional photo, empty space on the left.', { titre: 'Pressing Clean Express', sous_titre: 'Votre linge propre en 24 heures, à Bè', contact: 'WhatsApp : 90 00 00 00' }, 'banniere'),
  image('bissap', 'carre', 'Professional product photo of a glass bottle of red hibiscus bissap juice with ice cubes and dried hibiscus flowers around it, on a light wooden table, fresh, sharp details, soft natural light, unlabeled bottle.'),
  image('illustration', 'carre', 'Flat vector illustration of a young West African woman entrepreneur selling clothes online with her smartphone, bright friendly colors, clean modern style, simple background.', 'illustration'),
]

// Générateurs : identifiant de Cloudflare, façon de l'appeler (« json » ou « formulaire »), réponse (« base64 » ou
// « binaire »), neurones par image estimés à partir des prix officiels (0,011 $ pour 1 000 neurones).
const CF = {
  'cf-schnell': { id: '@cf/black-forest-labs/flux-1-schnell', appel: 'json', corps: (p) => ({ prompt: p.slice(0, 2000), steps: 4 }) },
  'cf-klein-4b': { id: '@cf/black-forest-labs/flux-2-klein-4b', appel: 'formulaire' },
  'cf-klein-9b': { id: '@cf/black-forest-labs/flux-2-klein-9b', appel: 'formulaire' },
  'cf-flux-2-dev': { id: '@cf/black-forest-labs/flux-2-dev', appel: 'formulaire' },
  'cf-phoenix': { id: '@cf/leonardo/phoenix-1.0', appel: 'json', corps: (p, l, h) => ({ prompt: p.slice(0, 2000), width: l, height: h }) },
  'cf-lucid': { id: '@cf/leonardo/lucid-origin', appel: 'json', corps: (p, l, h) => ({ prompt: p.slice(0, 2000), width: l, height: h }) },
  'cf-sdxl-lightning': { id: '@cf/bytedance/stable-diffusion-xl-lightning', appel: 'json', corps: (p, l, h) => ({ prompt: p.slice(0, 2000), width: l, height: h }) },
  'cf-dreamshaper': { id: '@cf/lykon/dreamshaper-8-lcm', appel: 'json', corps: (p, l, h) => ({ prompt: p.slice(0, 2000), width: l, height: h }) },
}
const GENERATEURS = [...Object.keys(CF), 'openai-flare']

// Taille demandée : celle de Kekeli, ramenée à 1024 pixels au plus pour Cloudflare (multiples de 16).
const taille = (format, max) => {
  const [l, h] = TAILLES[format]
  const r = Math.min(1, max / Math.max(l, h))
  return [Math.round((l * r) / 16) * 16, Math.round((h * r) / 16) * 16]
}

async function cloudflare(nom, invite, format) {
  prendreAppelCloudflare()
  const g = CF[nom]
  const [l, h] = taille(format, 1024)
  const url = `https://api.cloudflare.com/client/v4/accounts/${encodeURIComponent(numeroCompteCloudflare())}/ai/run/${g.id}`
  const entetes = { Authorization: `Bearer ${valeurCle('CLOUDFLARE_API_TOKEN')}` }
  let options
  if (g.appel === 'formulaire') {
    const f = new FormData()
    f.append('prompt', invite.slice(0, 2000))
    f.append('width', String(l))
    f.append('height', String(h))
    options = { method: 'POST', headers: entetes, body: f }
  } else {
    options = { method: 'POST', headers: { ...entetes, 'Content-Type': 'application/json' }, body: JSON.stringify(g.corps(invite, l, h)) }
  }
  const reponse = await fetch(url, { ...options, signal: AbortSignal.timeout(180000) })
  const type = reponse.headers.get('content-type') ?? ''
  if (!reponse.ok) throw new Error(`Cloudflare ${reponse.status} ${(await reponse.text()).slice(0, 160)}`)
  if (type.startsWith('image/')) return { octets: Buffer.from(await reponse.arrayBuffer()), extension: type.includes('png') ? 'png' : 'jpg', usd: 0 }
  const d = await reponse.json()
  const b64 = d.result?.image ?? d.image
  if (!b64) throw new Error('Cloudflare sans image')
  return { octets: Buffer.from(b64, 'base64'), extension: 'jpg', usd: 0 }
}

let depense = 0
async function openai(invite, format) {
  if (depense > PLAFOND_USD) throw new Error('plafond du banc atteint')
  const reponse = await fetch('https://api.openai.com/v1/images/generations', {
    method: 'POST',
    headers: { Authorization: `Bearer ${valeurCle('OPENAI_API_KEY')}`, 'Content-Type': 'application/json' },
    body: JSON.stringify({ model: 'gpt-image-2.6-flare', prompt: invite, size: TAILLES[format].join('x'), quality: 'medium', output_format: 'png', moderation: 'auto' }),
    signal: AbortSignal.timeout(240000),
  })
  const d = await reponse.json().catch(() => ({}))
  if (!reponse.ok || !d.data?.[0]?.b64_json) throw new Error(`OpenAI ${reponse.status} ${String(d.error?.message ?? '').slice(0, 120)}`)
  const u = d.usage ?? {}
  const ie = u.input_tokens_details?.image_tokens ?? 0
  const usd = (((u.input_tokens ?? 0) - ie) * PRIX_OPENAI.texte + ie * PRIX_OPENAI.image_entree + (u.output_tokens ?? 0) * PRIX_OPENAI.image_sortie) / 1e6
  depense += usd
  return { octets: Buffer.from(d.data[0].b64_json, 'base64'), extension: 'png', usd }
}

function composer(structure, fond, sortie) {
  const travail = `${dossier}/.travail`
  mkdirSync(travail, { recursive: true })
  writeFileSync(`${travail}/structure.json`, JSON.stringify(structure))
  execFileSync('python3', ['-I', MOTEUR, 'composer', `${travail}/structure.json`, travail, fond, '-', '-'])
  execFileSync('cp', [`${travail}/image.png`, sortie])
  execFileSync('rm', ['-rf', travail])
}

const fichierBanc = `${dossier}/banc.json`
const resultats = existsSync(fichierBanc) ? JSON.parse(readFileSync(fichierBanc, 'utf8')) : []
const noter = (r) => {
  const i = resultats.findIndex((x) => x.jeu === r.jeu && x.generateur === r.generateur && x.methode === r.methode)
  if (i >= 0) resultats.splice(i, 1, r)
  else resultats.push(r)
  writeFileSync(fichierBanc, JSON.stringify(resultats, null, 2))
}

async function essai(jeu, generateur, methode) {
  const s = jeu.structure
  const invite = methode === 'dessine' ? inviteAffiche(s) : s.fond.description
  const debut = Date.now()
  try {
    const r = generateur === 'openai-flare' ? await openai(invite, s.format) : await cloudflare(generateur, invite, s.format)
    const secondes = Math.round((Date.now() - debut) / 100) / 10
    const base = `${jeu.nom}-${generateur}-${methode}`
    const brut = `${dossier}/${base}-brut.${r.extension}`
    writeFileSync(brut, r.octets)
    let final = brut
    if (methode === 'code' && jeu.genre === 'affiche') {
      final = `${dossier}/${base}.png`
      composer(s, brut, final)
    }
    let texte = null
    if (methode === 'dessine') {
      const lu = await lireTextes(readFileSync(final), { modele: VISION })
      depense += ((lu.consommation.tokens_entree ?? 0) * 0.75 + (lu.consommation.tokens_sortie ?? 0) * 4.5) / 1e6
      texte = lu.textes ? { ...texteConforme(textesAttendus(s), lu.textes), lus: lu.textes } : { ok: null }
    }
    noter({ jeu: jeu.nom, genre: jeu.genre, format: s.format, generateur, methode, secondes, usd: Math.round(r.usd * 10000) / 10000, fcfa: Math.round(r.usd * FCFA * 10) / 10, fichier: final.split('/').pop(), texte })
    console.log(jeu.nom, generateur, methode, `${secondes} s`, `${r.usd.toFixed(4)} $`, texte ? (texte.ok ? 'texte exact' : `texte : ${texte.manquants?.length ?? '?'} manquants, ${texte.enTrop?.length ?? '?'} en trop`) : '')
  } catch (erreur) {
    noter({ jeu: jeu.nom, genre: jeu.genre, generateur, methode, erreur: erreur.message })
    console.log(jeu.nom, generateur, methode, 'erreur', erreur.message)
  }
}

if (import.meta.url === `file://${process.argv[1]}`) {
  mkdirSync(dossier, { recursive: true })
  const generateurs = generateursChoisis.length ? generateursChoisis : GENERATEURS
  for (const jeu of JEUX.filter((j) => !jeuxChoisis.length || jeuxChoisis.includes(j.nom))) {
    for (const g of generateurs) {
      if (jeu.genre === 'affiche') {
        await essai(jeu, g, 'dessine')
        await essai(jeu, g, 'code')
      } else {
        await essai(jeu, g, 'image')
      }
    }
  }
  console.log('Dépense OpenAI de cette exécution (estimation) :', depense.toFixed(4), '$')
}
