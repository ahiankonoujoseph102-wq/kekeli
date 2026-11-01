// Traduit une action de l'agent OpenCode en étape affichée à l'utilisateur.
// Le Runner envoie un « genre » et une « cible » ; le site Kekeli écrit la phrase en français.

const DOSSIER_PROJET = '/home/user/projet/'

function cheminLisible(chemin) {
  const texte = String(chemin ?? '')
  return texte.startsWith(DOSSIER_PROJET) ? texte.slice(DOSSIER_PROJET.length) : texte
}

function couper(texte, longueur = 300) {
  const valeur = String(texte ?? '').trim()
  return valeur.length > longueur ? valeur.slice(0, longueur - 1) + '…' : valeur
}

const IGNORES = new Set(['todowrite', 'todoread', 'question', 'invalid'])

export function traduireOutil(partie) {
  if (partie?.type !== 'tool') return null
  const etat = partie.state ?? {}
  if (etat.status !== 'completed' && etat.status !== 'error') return null
  const outil = String(partie.tool ?? '')
  if (IGNORES.has(outil)) return null
  const entree = etat.input ?? {}

  if (etat.status === 'error') {
    return { genre: 'echec', cible: couper(outil, 60) }
  }

  switch (outil) {
    case 'write':
      return { genre: 'fichier_ecrit', cible: couper(cheminLisible(entree.filePath)) }
    case 'edit':
    case 'multiedit':
    case 'patch':
    case 'apply_patch':
      return { genre: 'fichier_modifie', cible: couper(cheminLisible(entree.filePath ?? etat.title)) }
    case 'read':
      return { genre: 'fichier_lu', cible: couper(cheminLisible(entree.filePath)) }
    case 'bash':
      return { genre: 'commande', cible: couper(String(entree.command ?? '').replaceAll(DOSSIER_PROJET, '')) }
    case 'glob':
    case 'grep':
    case 'list':
    case 'ls':
      return { genre: 'recherche', cible: couper(entree.pattern ?? cheminLisible(entree.path)) }
    case 'webfetch':
    case 'websearch':
      return { genre: 'internet', cible: couper(entree.url ?? entree.query) }
    default:
      return { genre: 'outil', cible: couper(outil, 60) }
  }
}

/** Tokens d'une requête au modèle, lus dans une partie « step-finish ». */
export function tokensDeRequete(partie) {
  const tokens = partie?.tokens ?? {}
  return {
    tokens_entree: Number(tokens.input ?? 0),
    tokens_sortie: Number(tokens.output ?? 0),
    tokens_reflexion: Number(tokens.reasoning ?? 0),
    tokens_cache: Number(tokens.cache?.read ?? 0),
  }
}
