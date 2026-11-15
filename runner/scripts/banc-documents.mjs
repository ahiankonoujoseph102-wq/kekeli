// Banc d'essai des documents et des présentations (chantier « Kekeli fait tout correctement », 15 novembre 2026).
// 1. « rediger » : le modèle de construction écrit une seule fois la structure de chaque document (5 demandes réelles) ;
// 2. « rendre <étiquette> <moteur> » : la MÊME structure est mise en page par un moteur (l'ancien ou le nouveau), en
//    local (python-docx, python-pptx, LibreOffice, Poppler), pour comparer la mise en page avant et après à contenu égal.
// Les calculs des tableaux (quantité × prix = total) sont vérifiés par le code. Aucune clé n'est affichée.
//
//   cd runner && node scripts/banc-documents.mjs rediger ../docs/captures/fonctions/documents
//   cd runner && node scripts/banc-documents.mjs rendre ../docs/captures/fonctions/documents apres src/documents/rendu/kekeli_documents.py
import { execFileSync } from 'node:child_process'
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { CONSIGNES_DOCUMENT, CONSIGNES_PRESENTATION, consigneRedaction, rediger, structurePropre } from '../src/documents/redaction.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'

const [action, dossier = '../docs/captures/fonctions/documents', etiquette = 'apres', moteur = 'src/documents/rendu/kekeli_documents.py'] = process.argv.slice(2)

export const DEMANDES = [
  { nom: 'devis', type: 'document', texte: "Fais un devis en FCFA de ma boutique « Pagnes Ama » au Grand Marché de Lomé pour l'école Les Étoiles : 40 pagnes wax à 12 000 FCFA, la couture de 40 tenues à 5 000 FCFA et la livraison à 10 000 FCFA. Acompte de 50 % par Mixx by Yas ou Flooz." },
  { nom: 'plan-affaires', type: 'document', texte: "Écris un plan d'affaires d'une page pour mon projet « Bissap Délice » : jus de bissap en bouteille vendu 500 FCFA à Lomé (Adidogomé), coût de fabrication 200 FCFA la bouteille, objectif 600 bouteilles par mois, vente sur WhatsApp et dans 5 boutiques, investissement de départ 350 000 FCFA (congélateur, bouteilles, étiquettes)." },
  { nom: 'rapport', type: 'document', texte: "Rédige un rapport de 3 pages sur les ventes du premier semestre 2026 de ma boutique de téléphones Kossi Phones à Agoè, avec un tableau des chiffres par mois : janvier 2 400 000 FCFA, février 2 100 000, mars 2 750 000, avril 3 000 000, mai 2 900 000, juin 3 350 000, le total du semestre, la moyenne par mois, l'évolution entre janvier et juin, puis des recommandations." },
  { nom: 'cv', type: 'document', texte: "Fais-moi un CV professionnel. Je m'appelle Esi Lawson, j'habite à Lomé (Bè), téléphone +228 90 12 34 56, e-mail esi.lawson@exemple.tg. Je suis comptable depuis 6 ans : 2020-2026 comptable chez Togo Distribution SARL (paie de 45 employés, déclarations fiscales, rapprochements bancaires), 2018-2020 assistante comptable au Cabinet Akakpo. Licence en comptabilité à l'Université de Lomé (2018). Logiciels : Sage, Excel avancé. Langues : français, éwé, anglais (bon niveau)." },
  { nom: 'presentation', type: 'presentation', texte: "Fais une présentation de 8 diapositives pour Lomé IA School, une école de formation en intelligence artificielle à Adidogomé : nos formations (initiation 2 jours 25 000 FCFA, agents IA 5 jours 75 000 FCFA, automatisations 3 jours 45 000 FCFA), pour qui, comment s'inscrire." },
]

// Le modèle de construction des formules payantes, comme en production (build_models), puis Terra en secours.
// Variable MODELES (« fournisseur/modèle,… ») pour un autre ordre, par exemple quand le crédit OpenAI est épuisé.
const FOURNISSEURS = process.env.MODELES
  ? process.env.MODELES.split(',').map((m) => ({ fournisseur: m.split('/')[0], modele: m.split('/').slice(1).join('/') }))
  : [{ fournisseur: 'openai', modele: 'gpt-6.1-sol' }, { fournisseur: 'openai', modele: 'gpt-6.1-terra' }]
const PRIX = { 'gpt-6.1-sol': [2.5, 15], 'gpt-6.1-terra': [0.75, 4.5] } // dollars par million de tokens (estimation de config/kekeli.php)

const chiffre = (s) => {
  const t = String(s ?? '').replace(/\s| /g, '').replace(/FCFA|F$|%/gi, '').replace(',', '.')
  return /^-?\d+(\.\d+)?$/.test(t) ? Number(t) : null
}

/** Vérifie chaque ligne « quantité, prix unitaire, total » et le total général des tableaux. */
export function verifierCalculs(structure) {
  const erreurs = []
  for (const bloc of structure.blocs ?? []) {
    if (bloc.type !== 'tableau') continue
    const entetes = (bloc.entetes ?? []).map((e) => String(e).toLowerCase())
    const iq = entetes.findIndex((e) => /quantit|qté|nombre/.test(e))
    const ip = entetes.findIndex((e) => /prix unitaire|p\.? ?u|unitaire/.test(e))
    const it = entetes.findIndex((e) => /total|montant/.test(e))
    let somme = 0
    for (const ligne of bloc.lignes ?? []) {
      const [q, p, t] = [chiffre(ligne[iq]), chiffre(ligne[ip]), chiffre(ligne[it])]
      if (iq >= 0 && ip >= 0 && it >= 0 && q !== null && p !== null && t !== null) {
        if (Math.abs(q * p - t) > 0.5) erreurs.push(`${ligne.join(' | ')} : ${q} × ${p} ≠ ${t}`)
        somme += t
      }
    }
    void somme
  }
  return erreurs
}

if (action === 'rediger') {
  mkdirSync(dossier, { recursive: true })
  const cles = fournisseursAvecCle()
  for (const d of DEMANDES) {
    if (existsSync(`${dossier}/${d.nom}.json`) && !process.env.REFAIRE) continue
    const debut = Date.now()
    const r = await rediger({ systeme: d.type === 'presentation' ? CONSIGNES_PRESENTATION : CONSIGNES_DOCUMENT, consigne: consigneRedaction({ demande: d.texte, pays: 'Togo', monnaie: 'FCFA', date: '15 novembre 2026' }), fournisseurs: FOURNISSEURS }, { clesPresentes: cles })
    if (!r?.objet) {
      console.log(d.nom, 'rédaction impossible')
      continue
    }
    const structure = structurePropre(r.objet.document ?? r.objet, d.type)
    const c = r.consommation ?? {}
    const [pe, ps] = PRIX[c.modele] ?? [0, 0]
    const usd = ((c.tokens_entree ?? 0) * pe + (c.tokens_sortie ?? 0) * ps) / 1e6
    writeFileSync(`${dossier}/${d.nom}.json`, JSON.stringify({ demande: d.texte, modele: c.modele, secondes: Math.round((Date.now() - debut) / 1000), usd: Math.round(usd * 10000) / 10000, calculs_faux: verifierCalculs(structure), structure }, null, 2))
    console.log(d.nom, c.modele, `${Math.round((Date.now() - debut) / 1000)} s`, `${usd.toFixed(4)} $`, verifierCalculs(structure).length ? 'calculs faux' : 'calculs justes')
  }
}

if (action === 'rendre') {
  for (const d of DEMANDES) {
    if (!existsSync(`${dossier}/${d.nom}.json`)) continue
    const { structure } = JSON.parse(readFileSync(`${dossier}/${d.nom}.json`, 'utf8'))
    const sortie = `${dossier}/${etiquette}/${d.nom}`
    mkdirSync(sortie, { recursive: true })
    writeFileSync(`${sortie}/structure.json`, JSON.stringify(structure))
    const r = JSON.parse(execFileSync('python3', ['-I', moteur, 'rendre', `${sortie}/structure.json`, sortie, d.type === 'presentation' ? 'pptx,pdf' : 'docx,pdf']).toString())
    // Une planche de toutes les pages, pour juger d'un coup d'œil.
    execFileSync('montage', [...r.apercus.slice(0, 8).map((a) => `${sortie}/${a}`), '-tile', `${Math.min(4, r.apercus.length)}x`, '-geometry', d.type === 'presentation' ? '480x+6+6' : '420x+6+6', '-background', '#D8D4CC', `${dossier}/${etiquette}-${d.nom}.jpg`])
    console.log(d.nom, etiquette, `${r.pages} pages`)
  }
}
