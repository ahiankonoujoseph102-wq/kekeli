// Essai réel d'une vidéo avec le Runner seul (vrai modèle, vraie voix, vrai ordinateur E2B), sans le site : un petit
// serveur local joue le rôle du site (fichiers joints du client, réception des fichiers de la vidéo).
//   node runner/scripts/essai-video.mjs <dossier de sortie> <fichier de la demande .json>
// La demande : { "demande": "…", "format": "auto", "duree_max": 30, "modele": "gpt-6.1-sol", "joints": ["chemin", …],
//                "voix": true, "precedente": null }
import { createServer } from 'node:http'
import { mkdirSync, readFileSync, writeFileSync, appendFileSync, existsSync } from 'node:fs'
import path from 'node:path'
import { Sandbox } from 'e2b'
import { creerAtelier, produireDocument } from '../src/documents/index.js'
import { creerAtelierVideo } from '../src/videos/index.js'
import { fournisseursAvecCle } from '../src/fournisseurs.js'

const sortie = path.resolve(process.argv[2] ?? 'essai-video')
const spec = JSON.parse(readFileSync(process.argv[3], 'utf8'))
mkdirSync(sortie, { recursive: true })

const joints = (spec.joints ?? []).map((chemin, i) => ({ chemin: path.resolve(chemin), numero: i + 1 }))
const typeDe = (c) => (/\.(mp4|mov|webm|mkv)$/i.test(c) ? 'video' : /\.(mp3|m4a|wav|ogg|aac)$/i.test(c) ? 'audio' : 'image')

const serveur = createServer((req, res) => {
  const url = new URL(req.url, 'http://127.0.0.1')
  if (req.method === 'GET' && url.pathname.startsWith('/fichier/')) {
    const j = joints[Number(url.pathname.split('/').pop()) - 1]
    if (!j) return res.writeHead(404).end()
    res.writeHead(200, { 'Content-Type': 'application/octet-stream' })
    return res.end(readFileSync(j.chemin))
  }
  if (req.method === 'POST' && url.pathname === '/envoi') {
    const morceaux = []
    req.on('data', (d) => morceaux.push(d))
    req.on('end', () => {
      const nom = path.basename(url.searchParams.get('nom'))
      const partie = Number(url.searchParams.get('partie') ?? 0)
      const fichier = path.join(sortie, nom)
      if (partie === 0) writeFileSync(fichier, Buffer.concat(morceaux))
      else appendFileSync(fichier, Buffer.concat(morceaux))
      res.writeHead(200, { 'Content-Type': 'application/json' }).end('{"ok":true}')
    })
    return
  }
  res.writeHead(404).end()
})
await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
const base = `http://127.0.0.1:${serveur.address().port}`

const debut = Date.now()
const evenements = []
const cout = { modele_usd: 0, voix_secondes: 0, transcription_secondes: 0, images: 0 }
const PRIX = { 'gpt-6.1-sol': [2, 10], 'gpt-6.1-terra': [1, 5], 'gpt-6-luna': [0.1, 0.5] }
const envoyer = async (e) => {
  evenements.push({ ...e, t: Math.round((Date.now() - debut) / 1000) })
  if (e.type === 'narration') console.log(`[${Math.round((Date.now() - debut) / 1000)} s] ${e.texte}`)
  if (e.type === 'rendu') console.log(`[${Math.round((Date.now() - debut) / 1000)} s] Rendu… ${e.pourcentage} %`)
  if (e.type === 'requete' && PRIX[e.modele]) cout.modele_usd += ((e.tokens_entree ?? 0) * PRIX[e.modele][0] + ((e.tokens_sortie ?? 0)) * PRIX[e.modele][1]) / 1e6
  if (e.type === 'voix') cout[e.usage === 'transcription' ? 'transcription_secondes' : 'voix_secondes'] += e.secondes
  if (e.type === 'images') cout.images += e.nombre
  if (e.type === 'terminee') console.log(`Réponse : ${e.reponse}`)
  if (e.type === 'erreur') console.log('Erreur :', e)
  return {}
}

const demande = {
  tache: `essai-${Date.now()}`,
  projet: spec.projet ?? '01JESSAIVIDEO000000000000',
  rappel: `${base}/evenements`,
  envoi: `${base}/envoi`,
  fournisseurs: [{ fournisseur: 'openai', modele: spec.modele ?? 'gpt-6.1-terra' }],
  video: {
    duree_max: spec.duree_max ?? 30,
    images_max: 6,
    voix: { permise: spec.voix !== false, modele: 'gpt-4o-mini-tts', voix: spec.voix_nom ?? 'marin' },
    transcription: { permise: true, modele: 'whisper-1' },
  },
  document: {
    type: 'video',
    action: spec.precedente ? 'modifier' : 'creer',
    demande: spec.demande,
    plan: spec.plan ?? '',
    format: spec.format ?? 'auto',
    precedente: spec.precedente ?? null,
    joints: joints.map((j) => ({ url: `${base}/fichier/${j.numero}`, type: typeDe(j.chemin), nom: path.basename(j.chemin) })),
    pays: 'Togo',
    monnaie: 'FCFA',
  },
}

try {
  const resultat = await produireDocument(demande, {
    atelier: creerAtelier({ Sandbox }),
    atelierVideo: creerAtelierVideo({ Sandbox }),
    envoyer,
    secret: process.env.KEKELI_RUNNER_SECRET || 'essai',
    clesPresentes: fournisseursAvecCle(),
  })
  const secondes = Math.round((Date.now() - debut) / 1000)
  const voixUsd = (cout.voix_secondes / 60) * 0.015 + (cout.transcription_secondes / 60) * 0.006
  const bilan = { secondes, cout: { ...cout, voix_usd: Math.round(voixUsd * 10000) / 10000, modele_usd: Math.round(cout.modele_usd * 10000) / 10000, openai_usd: Math.round((voixUsd + cout.modele_usd) * 10000) / 10000 }, verifications: resultat.verifications ?? null, structure: resultat.structure ?? null }
  writeFileSync(path.join(sortie, 'bilan.json'), JSON.stringify(bilan, null, 2))
  console.log(JSON.stringify({ secondes, cout: bilan.cout, verifications: bilan.verifications }, null, 2))
} finally {
  serveur.close()
  if (existsSync(sortie)) writeFileSync(path.join(sortie, 'evenements.json'), JSON.stringify(evenements, null, 2))
  // Les ateliers s'effacent seuls à la fin de leur durée de vie ; on les arrête tout de suite ici.
  const pages = Sandbox.list()
  for (const s of await pages.nextItems().catch(() => [])) {
    if (s.metadata?.kekeli_projet === demande.projet) await Sandbox.kill(s.sandboxId).catch(() => {})
  }
  process.exit(0)
}
