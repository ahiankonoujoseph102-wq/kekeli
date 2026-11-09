// Correcteur de typographie française de Kekeli. Ce fichier est copié dans chaque ordinateur virtuel et lancé
// après chaque demande, avant les vérifications. Aucune dépendance : seulement les modules de Node.js.
//
// Règle : une espace insécable avant « ? », « ! », « ; » (espace fine) et « : » (espace normale), à l'intérieur
// des guillemets « », dans les nombres (« 2 500 ») et avant une unité (« FCFA »), pour qu'aucun signe ni aucune
// unité ne se retrouve seul en début de ligne. Seul le texte visible des pages
// HTML est corrigé : jamais les balises, les attributs, les scripts, les styles, ni les adresses ou les heures.
//
//   node typographie.mjs /home/user/projet   → { fichiers, corrections } sur la sortie standard
import { promises as fs } from 'node:fs'
import path from 'node:path'
import { pathToFileURL } from 'node:url'

const FINE = ' '
const INSECABLE = ' '
const IGNORES = new Set(['node_modules', 'dist', 'build', 'images'])
const BLOCS_IGNORES = /^(script|style|pre|code|textarea)$/i

/** Nombre d'espaces insécables d'un texte (pour compter les corrections). */
const insecables = (texte) => [...texte].filter((c) => c === FINE || c === INSECABLE).length

/** Corrige un morceau de texte visible (sans balises). */
export function corrigerTexte(texte) {
  return texte
    // Espace ordinaire (ou aucune espace après une lettre) avant ? ! ; → espace fine insécable.
    // Jamais dans une entité HTML comme &amp; ou &#039;.
    .replace(/(?<!&[#\p{L}\p{N}]*)([\p{L}\p{N}»)”])[ \t]*([?!;])(?=\s|$|[<"»)])/gu, (_, avant, signe) => `${avant}${FINE}${signe}`)
    // Deux-points précédés d'une lettre et suivis d'une espace ou de la fin : jamais dans 11:00 ni dans une adresse.
    .replace(/([\p{L}\p{N}»)”])[ \t]*:(?=\s|$)/gu, (_, avant) => `${avant}${INSECABLE}:`)
    // Nombres et prix : « 2 500 FCFA » n'est jamais coupé entre les chiffres ni avant l'unité.
    .replace(/(?<=\d)[ \t](?=\d{3}(?!\d))/g, INSECABLE)
    .replace(/(?<=\d)[ \t](?=(FCFA|F CFA|CFA|€|\$|%|km|kg|h\b|min\b))/g, INSECABLE)
    // Guillemets français.
    .replace(/«[ \t]*(?=\S)/g, `«${INSECABLE}`)
    .replace(/(?<=\S)[ \t]*»/g, `${INSECABLE}»`)
}

/** Corrige le texte visible d'une page HTML, sans toucher aux balises ni aux blocs de code. */
export function corrigerHtml(html) {
  let corrections = 0
  let ignore = null
  const morceaux = html.split(/(<[^>]*>)/)
  for (let i = 0; i < morceaux.length; i++) {
    const morceau = morceaux[i]
    if (morceau.startsWith('<')) {
      const ouverture = /^<\s*([a-z0-9]+)/i.exec(morceau)
      const fermeture = /^<\s*\/\s*([a-z0-9]+)/i.exec(morceau)
      if (!ignore && ouverture && BLOCS_IGNORES.test(ouverture[1]) && !morceau.endsWith('/>')) ignore = ouverture[1].toLowerCase()
      else if (ignore && fermeture && fermeture[1].toLowerCase() === ignore) ignore = null
      continue
    }
    if (ignore || !morceau.trim()) continue
    const texte = corrigerTexte(morceau)
    if (texte !== morceau) {
      // Une espace ordinaire remplacée compte aussi : on compte les signes désormais protégés.
      corrections += Math.max(1, insecables(texte) - insecables(morceau))
      morceaux[i] = texte
    }
  }
  return { html: morceaux.join(''), corrections }
}

async function pagesHtml(racine, relatif = '') {
  const trouvees = []
  for (const entree of await fs.readdir(path.join(racine, relatif), { withFileTypes: true })) {
    if (entree.name.startsWith('.') || IGNORES.has(entree.name)) continue
    const chemin = path.join(relatif, entree.name)
    if (entree.isDirectory()) trouvees.push(...(await pagesHtml(racine, chemin)))
    else if (/\.html?$/i.test(entree.name)) trouvees.push(chemin)
  }
  return trouvees
}

export async function corrigerProjet(racine) {
  let corrections = 0
  let fichiers = 0
  for (const page of await pagesHtml(racine)) {
    const chemin = path.join(racine, page)
    const html = await fs.readFile(chemin, 'utf8')
    const resultat = corrigerHtml(html)
    if (resultat.html !== html) {
      await fs.writeFile(chemin, resultat.html)
      corrections += resultat.corrections
      fichiers++
    }
  }
  return { fichiers, corrections }
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? '').href) {
  corrigerProjet(path.resolve(process.argv[2] ?? '/home/user/projet'))
    .then((resultat) => process.stdout.write(JSON.stringify(resultat)))
    .catch((erreur) => process.stdout.write(JSON.stringify({ fichiers: 0, corrections: 0, erreur: erreur.message })))
}
