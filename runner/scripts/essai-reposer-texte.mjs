// Essai réel de la finition « texte reposé par Kekeli » : une affiche est créée, puis le client demande « mets le prix
// en plus gros et en rouge ». Le Runner seul, avec un vrai atelier E2B et un petit faux site local qui reçoit les
// fichiers (aucune clé n'est écrite ni affichée).
//
//   node runner/scripts/essai-reposer-texte.mjs <pro|gratuite> <dossier des images>
import http from 'node:http'
import { mkdirSync, writeFileSync, readFileSync, existsSync } from 'node:fs'
import path from 'node:path'
import { Sandbox } from 'e2b'
import { creerAtelier, produireDocument } from '../src/documents/index.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'

const formule = process.argv[2] ?? 'gratuite'
const sortie = path.resolve(process.argv[3] ?? 'essai-reposer')
mkdirSync(sortie, { recursive: true })

const versions = {}
let courante = 'v1'
const serveur = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://127.0.0.1')
  if (req.method === 'POST') {
    const morceaux = []
    req.on('data', (m) => morceaux.push(m))
    req.on('end', () => {
      const nom = url.searchParams.get('nom')
      mkdirSync(path.join(sortie, courante), { recursive: true })
      writeFileSync(path.join(sortie, courante, nom), Buffer.concat(morceaux))
      res.end('{}')
    })
    return
  }
  const fichier = path.join(sortie, url.searchParams.get('version') ?? 'v1', url.searchParams.get('nom') ?? '')
  if (!existsSync(fichier)) return res.writeHead(404).end()
  res.end(readFileSync(fichier))
})
await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
const base = `http://127.0.0.1:${serveur.address().port}`

const reglages = formule === 'pro'
  ? { generateur: 'openai/gpt-image-2.6-flare', verification: { fournisseur: 'openai', modele: 'gpt-6.1-terra' }, secours: 'cloudflare/flux-1-schnell' }
  : { generateur: 'cloudflare/flux-1-schnell', secours: 'cloudflare/flux-1-schnell' }
const fournisseurs = formule === 'pro' ? [{ fournisseur: 'openai', modele: 'gpt-6.1-sol' }] : [{ fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }, { fournisseur: 'gemini', modele: 'gemini-3.1-flash-lite' }]
const atelier = creerAtelier({ Sandbox, dureeVieMs: 15 * 60 * 1000 })
const cout = { images_payantes: [], requetes: [] }

async function version(nom, document) {
  courante = nom
  const evenements = []
  const debut = Date.now()
  await produireDocument({ tache: `essai-${nom}`, projet: `essai-reposer-${formule}`, envoi: `${base}/envoi`, fournisseurs, images: reglages, document }, {
    atelier,
    secret: 'essai-local',
    clesPresentes: new Set(fournisseursAvecCle()),
    envoyer: async (e) => {
      evenements.push(e)
      if (e.type === 'narration') console.log(`  ${nom} · ${e.texte}`)
      if (e.type === 'image_payante') cout.images_payantes.push(e)
      if (e.type === 'requete') cout.requetes.push(e)
      return null
    },
  })
  const doc = evenements.find((e) => e.type === 'document')
  console.log(`  ${nom} terminée en ${Math.round((Date.now() - debut) / 1000)} s, méthode ${doc?.structure?.methode}`)
  return doc
}

const demande = 'Une affiche pour le statut WhatsApp de ma boutique de pagnes Ama à Lomé : promotion -20 % sur tout le wax, 9 600 FCFA le pagne au lieu de 12 000 FCFA, appelez le 90 00 00 00, paiement par Mixx by Yas ou Flooz.'
const v1 = await version('v1', { type: 'image', action: 'creer', demande, format: 'vertical' })
const v2 = await version('v2', { type: 'image', action: 'modifier', demande: `${demande}\nMets le prix en plus gros et en rouge.`, precedente: v1.structure, format: 'auto', fond_precedent: v1.fichiers.fond ? `${base}/fond?version=v1&nom=${v1.fichiers.fond}` : null })
writeFileSync(path.join(sortie, 'resultat.json'), JSON.stringify({ formule, v1: v1.structure, v2: v2.structure, cout }, null, 2))
serveur.close()
console.log('Fini.')
process.exit(0)
