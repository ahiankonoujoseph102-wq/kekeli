// Banc des petites retouches : la même retouche demandée à deux modèles (le meilleur de la formule et un modèle moins
// cher), sur de vraies structures (lettre, affiche, scénario de la vidéo réelle). Bon = la retouche est faite ET rien
// d'autre ne change (nombre de valeurs modifiées). Coût mesuré sur les tokens.
//   node runner/scripts/banc-retouches.mjs <sortie.json> <modèle cher> <modèle moins cher>
import { readFileSync, writeFileSync } from 'node:fs'
import { rediger, CONSIGNES_DOCUMENT, consigneRedaction } from '../src/documents/redaction.js'
import { CONSIGNES_IMAGE } from '../src/documents/images.js'
import { CONSIGNES_VIDEO, consigneVideo } from '../src/videos/scenario.js'

const lire = (n) => JSON.parse(readFileSync(new URL(`../mesures/retouches/${n}.json`, import.meta.url)))
const PRIX = { 'gpt-6.1-sol': [2, 10], 'gpt-6.1-terra': [1, 5], 'gpt-6-luna': [0.1, 0.5] }
const feuilles = (o, p = '') => (o && typeof o === 'object' ? Object.entries(o).flatMap(([k, v]) => feuilles(v, `${p}/${k}`)) : [[p, o ?? '']])
const changees = (a, b) => {
  const A = new Map(feuilles(a).map(([k, v]) => [k, String(v)]))
  const B = new Map(feuilles(b).map(([k, v]) => [k, String(v)]))
  return [...new Set([...A.keys(), ...B.keys()])].filter((k) => A.get(k) !== B.get(k) && !/\/(message|titre_court|modification)$/.test(k))
}
const cas = [
  { type: 'document', demande: 'Corrige la faute « recrutemant ».', attendu: (o) => JSON.stringify(o).includes('recrutement') && !JSON.stringify(o).includes('recrutemant'), max: 1 },
  { type: 'document', demande: 'Change la date de la lettre au 20 novembre 2026.', attendu: (o) => JSON.stringify(o).includes('20 novembre 2026'), max: 1 },
  { type: 'document', demande: 'Mets l’objet en rouge.', attendu: (o) => /#(B42318|D32F2F|[A-F0-9]{6})/i.test(String(o.blocs?.[0]?.couleur_objet ?? '')), max: 1 },
  { type: 'image', demande: 'Mets le prix à 9 500 FCFA.', attendu: (o) => o.textes?.prix === '9 500 FCFA', max: 1 },
  { type: 'image', demande: 'Change la date : jusqu’au samedi 6 décembre.', attendu: (o) => /6 décembre/.test(o.textes?.date ?? ''), max: 1 },
  { type: 'image', demande: 'Mets le prix en plus gros.', attendu: (o) => Number(o.styles?.prix?.taille) > 1, max: 2 },
  { type: 'video', demande: 'Mets la couleur d’accent en vert.', attendu: (o) => /^#(2E7D32|[0-9A-F]{6})$/i.test(o.couleurs?.accent ?? '') && o.couleurs?.accent?.toUpperCase() !== '#E63946', max: 1 },
  { type: 'video', demande: 'Enlève les sous-titres.', attendu: (o) => o.sous_titres === false, max: 1 },
]
const modeles = process.argv.slice(3)
const resultats = []
for (const c of cas) {
  const avant = lire(c.type)
  const { systeme, consigne, extraire } = c.type === 'video'
    ? { systeme: CONSIGNES_VIDEO, consigne: consigneVideo({ demande: c.demande, precedente: avant, dureeMax: 30 }), extraire: (o) => o.video ?? o }
    : c.type === 'image'
      ? { systeme: CONSIGNES_IMAGE, consigne: consigneRedaction({ action: 'modifier', demande: c.demande, precedente: avant }), extraire: (o) => o.image ?? o }
      : { systeme: CONSIGNES_DOCUMENT, consigne: consigneRedaction({ action: 'modifier', demande: c.demande, precedente: avant }), extraire: (o) => o.document ?? o }
  for (const modele of modeles) {
    const r = await rediger({ systeme, consigne, fournisseurs: [{ fournisseur: 'openai', modele }], maxTokens: 8000 }, { clesPresentes: new Set() })
    const apres = r.ok ? extraire(r.objet) : null
    const diff = apres ? changees(c.type === 'video' ? { couleurs: avant.couleurs, sous_titres: avant.sous_titres, scenes: avant.scenes.map((s) => ({ titre: s.titre, texte: s.texte, prix: s.prix, voix: s.voix })) } : avant, c.type === 'video' ? { couleurs: apres.couleurs, sous_titres: apres.sous_titres, scenes: (apres.scenes ?? []).map((s) => ({ titre: s.titre, texte: s.texte, prix: s.prix, voix: s.voix })) } : apres) : ['échec']
    const bon = Boolean(apres) && c.attendu(apres) && diff.length <= c.max
    const u = r.consommation ?? {}
    const usd = ((u.tokens_entree ?? 0) * PRIX[modele][0] + ((u.tokens_sortie ?? 0) + (u.tokens_reflexion ?? 0)) * PRIX[modele][1]) / 1e6
    resultats.push({ type: c.type, demande: c.demande, modele, bon, valeurs_changees: diff, usd })
    console.log(bon ? 'oui' : 'NON', modele.padEnd(14), c.type.padEnd(9), c.demande, '| changé :', diff.join(', ').slice(0, 120), '|', usd.toFixed(5), '$')
  }
}
const bilan = Object.fromEntries(modeles.map((m) => { const l = resultats.filter((r) => r.modele === m); return [m, { bons: l.filter((r) => r.bon).length, total: l.length, usd: Math.round(l.reduce((a, r) => a + r.usd, 0) * 100000) / 100000 }] }))
console.log(JSON.stringify(bilan))
writeFileSync(process.argv[2], JSON.stringify({ date: new Date().toISOString(), bilan, resultats }, null, 2))
