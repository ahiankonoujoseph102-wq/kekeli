// Kekeli Motion : une animation SVG interactive, comme une image ou une vidéo de Kekeli, mais vivante dans la page.
// Le modèle de construction décrit la scène (JSON, jamais du code), Kekeli la vérifie, puis fabrique les fichiers de la
// version : la page autonome (aperçu), le script à poser dans un site, l'image fixe (SVG) et le code TypeScript (ZIP).
// Aucun ordinateur E2B : rien n'est exécuté sur le Runner, le moteur ne fait que tracer des chemins SVG.

import { envoyerFichier } from '../documents/index.js'
import { rediger } from '../documents/redaction.js'
import { CONSIGNES_ANIMATION, consigneAnimation, scenePropre } from './scene.js'
import { codeTypeScript, pageAnimation, scriptIntegration, nomAnimation } from './fichiers.js'
import { imageFixe } from './image-fixe.js'

/** Les fichiers d'une version : format => nom (les noms acceptés par le site, DocumentVersion::FILE_NAMES). */
export const FICHIERS_ANIMATION = { html: 'animation.html', js: 'animation.js', svg: 'animation.svg', zip: 'animation.zip' }

/** Fabrique tous les fichiers d'une scène déjà vérifiée. */
export async function fichiersAnimation(scene) {
  return {
    'animation.html': Buffer.from(await pageAnimation(scene)),
    'animation.js': Buffer.from(await scriptIntegration(scene)),
    'animation.svg': Buffer.from(await imageFixe(scene)),
    'animation.zip': await codeTypeScript(scene),
  }
}

export async function produireAnimation(demande, { envoyer, secret, clesPresentes, misesDeCote = null, appeler = fetch, env = process.env, journal = console, redigerScene = rediger }) {
  const doc = demande.document
  const dire = (texte) => envoyer({ type: 'narration', texte })
  await envoyer({ type: 'demarrage' })
  await dire(doc.action === 'modifier' ? 'Je fais le changement demandé.' : 'Je prépare la scène de votre animation.')

  const r = await redigerScene({
    systeme: CONSIGNES_ANIMATION,
    consigne: consigneAnimation({ demande: doc.demande, format: doc.format, precedente: doc.action === 'modifier' ? doc.precedente : null, pays: doc.pays }),
    fournisseurs: demande.fournisseurs,
    secours: demande.secours,
    maxTokens: 4000,
  }, { clesPresentes, misesDeCote, appeler, env })
  if (r?.consommation) {
    await envoyer({ type: 'fournisseur', fournisseur: r.consommation.fournisseur, modele: r.consommation.modele })
    const reponse = await envoyer({ type: 'requete', ...r.consommation })
    if (reponse?.arreter) return { arrete: true }
  }
  if (!r?.ok) throw Object.assign(new Error('description de la scène impossible'), { raison: r?.erreur ?? 'redaction' })

  const { scene, titre, message } = scenePropre(r.objet)
  await dire('Je dessine les personnages et le décor, puis je vérifie l’animation.')
  const fichiers = await fichiersAnimation(scene)
  for (const [nom, octets] of Object.entries(fichiers)) await envoyerFichier(demande.envoi, secret, nom, octets, { appeler })

  const texte = message || `Votre animation « ${titre} » est prête. Touchez les personnages, le soleil ou la lune pour les voir réagir.`
  await envoyer({
    type: 'document',
    titre,
    message: texte,
    fichiers: FICHIERS_ANIMATION,
    apercus: [],
    pages: 1,
    avertissements: [],
    // La scène vérifiée sert à la version suivante (modification) et à l'intégration dans un site.
    structure: { ...scene, nom: nomAnimation(scene.titre) },
  })
  await envoyer({ type: 'terminee', reponse: texte })
  journal.log(`Animation du projet ${demande.projet} : ${Object.keys(fichiers).length} fichiers envoyés.`)
  return { ok: true, scene }
}
