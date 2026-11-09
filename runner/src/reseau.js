// Sites que l'ordinateur virtuel a le droit de joindre. Tout le reste d'Internet est bloqué.
//
// - Les sites « système » sont indispensables au fonctionnement de l'agent (fournisseurs d'IA,
//   dépôts de paquets) : l'utilisateur ne peut pas les retirer.
// - Les sites « libres de droits » donnent des photos, des polices, des icônes et du code
//   utilisables légalement, même pour un usage commercial.
// - L'utilisateur peut ajouter ses propres sites, projet par projet, depuis le site Kekeli.
import { CATALOGUE } from './fournisseurs.js'

export const SITES_SYSTEME = [
  'models.dev',
  'models.opencode.ai',
  'registry.npmjs.org',
  'pypi.org',
  'files.pythonhosted.org',
]

export const SITES_LIBRES = [
  // Photos et vidéos libres de droits.
  'unsplash.com',
  'images.unsplash.com',
  'api.unsplash.com',
  'www.pexels.com',
  'images.pexels.com',
  'videos.pexels.com',
  'api.pexels.com',
  'pixabay.com',
  'cdn.pixabay.com',
  // Médias sous licence libre (Openverse, Wikimedia Commons).
  'api.openverse.org',
  'commons.wikimedia.org',
  'upload.wikimedia.org',
  'thumb.wikimedia.org',
  // Polices d'écriture (SIL Open Font License).
  'fonts.googleapis.com',
  'fonts.gstatic.com',
  // Bibliothèques de code et icônes sous licence libre.
  'cdn.jsdelivr.net',
  'unpkg.com',
  'cdnjs.cloudflare.com',
]

const DOMAINE = /^(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/

/** Un nom de site valide : lettres, chiffres, tirets et points, sans protocole ni chemin. */
export function domaineValide(valeur) {
  return typeof valeur === 'string' && valeur.length <= 253 && DOMAINE.test(valeur)
}

/** Liste complète des sites autorisés : système, fournisseurs avec clé, sources libres, puis ceux de l'utilisateur. */
export function sitesAutorises(sitesUtilisateur, clesPresentes) {
  const fournisseurs = [...clesPresentes].map((nom) => CATALOGUE[nom]?.hote).filter(Boolean)
  const utilisateur = (Array.isArray(sitesUtilisateur) ? sitesUtilisateur : [])
    .map((site) => String(site ?? '').trim().toLowerCase())
    .filter(domaineValide)
  return [...new Set([...SITES_SYSTEME, ...fournisseurs, ...SITES_LIBRES, ...utilisateur])]
}
