// Mesure des voix off gratuites en français (15 novembre 2026) : chaque voix dit une publicité entière en UNE
// requête ; on mesure le temps, la durée, la compréhension (retranscription mot à mot par Whisper chez Groq, gratuit)
// et le naturel (note d'un juge qui écoute : Gemini Flash-Lite gratuit, qui comprend l'audio).
// Utilisation : node runner/scripts/mesurer-voix.mjs [voix…]  (par exemple gemini-3.8-flash-tts:Kore)
// Résultat : runner/mesures/voix-gratuites.json et les fichiers WAV dans le dossier temporaire donné par SORTIE.

import fs from 'node:fs/promises'
import path from 'node:path'
import { direAvecGemini, transcrireGroq } from '../src/videos/voix.js'
import { valeurCle, numeroCompteCloudflare } from '../src/fournisseurs.js'

const TEXTE = 'Vos pagnes s’usent trop vite ? Chez Ama Pagnes, au grand marché de Lomé, chaque pagne est choisi à la main. Plus de 300 clientes nous font déjà confiance. Le pagne wax est à 12 000 FCFA. Commandez sur WhatsApp au +228 90 12 34 56.'
const SORTIE = process.env.SORTIE ?? '/tmp/voix'
const voulues = process.argv.slice(2).length ? process.argv.slice(2) : [
  'gemini-3.8-flash-tts:Kore', 'gemini-3.8-flash-tts:Charon',
  'gemini-3.8-flash-lite-tts:Kore', 'gemini-3.8-flash-lite-tts:Charon',
  'gemini-3.1-flash-tts-preview:Kore', 'gemini-3.1-flash-tts-preview:Charon',
  'gemini-2.5-flash-preview-tts:Kore', 'gemini-2.5-flash-preview-tts:Charon',
  'cloudflare-melotts:fr',
]

async function melo(texte) {
  const compte = numeroCompteCloudflare(process.env)
  const r = await fetch(`https://api.cloudflare.com/client/v4/accounts/${compte}/ai/run/@cf/myshell-ai/melotts`, { method: 'POST', headers: { Authorization: `Bearer ${valeurCle('CLOUDFLARE_API_TOKEN')}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ prompt: texte, lang: 'fr' }) })
  const d = await r.json()
  if (!r.ok || !d.result?.audio) throw new Error(`melotts ${r.status} ${JSON.stringify(d.errors ?? '').slice(0, 160)}`)
  return Buffer.from(d.result.audio, 'base64')
}

/** Le juge : écoute la voix et note le naturel en français de 1 à 10. */
async function juger(octets, type) {
  const corps = {
    contents: [{ role: 'user', parts: [
      { inline_data: { mime_type: type, data: octets.toString('base64') } },
      { text: 'Tu es un directeur artistique exigeant qui choisit des voix off pour des publicités en français en Afrique de l’Ouest. Écoute cette voix off. Réponds UNIQUEMENT en JSON : {"naturel": note de 1 à 10 (10 = un vrai comédien francophone, 1 = robot), "prononciation": note de 1 à 10 (montants, numéro de téléphone, noms), "genre": "femme" | "homme", "defauts": "une phrase"}' },
    ] }],
    generationConfig: { responseMimeType: 'application/json', temperature: 0 },
  }
  const r = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${process.env.JUGE ?? 'gemini-3.5-flash-lite'}:generateContent`, { method: 'POST', headers: { 'x-goog-api-key': valeurCle('GEMINI_API_KEY'), 'Content-Type': 'application/json' }, body: JSON.stringify(corps) })
  const d = await r.json()
  if (!r.ok) return { erreur: `${r.status} ${JSON.stringify(d).slice(0, 120)}` }
  try { return JSON.parse(d.candidates?.[0]?.content?.parts?.[0]?.text ?? '{}') } catch { return { erreur: 'juge illisible' } }
}

/** Les mots retrouvés à la retranscription (sans accents ni ponctuation). */
function exactitude(attendu, entendu) {
  const mots = (t) => String(t).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9 ]/g, ' ').split(/\s+/).filter(Boolean)
  const a = mots(attendu)
  const e = new Set(mots(entendu))
  return Math.round((a.filter((m) => e.has(m)).length / a.length) * 100)
}

const dureeWav = (b) => (b.length > 44 && b.toString('ascii', 0, 4) === 'RIFF' ? (b.length - 44) / b.readUInt32LE(28) : null)

await fs.mkdir(SORTIE, { recursive: true })
const resultats = []
for (const v of voulues) {
  const [modele, voix] = v.split(':')
  const debut = Date.now()
  let octets
  try {
    // DEJA=1 : la voix déjà enregistrée est relue (aucun quota dépensé), seulement jugée et retranscrite à nouveau.
    const deja = process.env.DEJA ? await fs.readFile(path.join(SORTIE, `${v.replace(/[^a-z0-9]+/gi, '-')}.wav`)).catch(() => null) : null
    octets = deja ?? (modele === 'cloudflare-melotts' ? await melo(TEXTE) : await direAvecGemini(TEXTE, { modele, voix, style: 'chaleureuse et dynamique' }))
  } catch (erreur) {
    resultats.push({ voix: v, ok: false, erreur: erreur.message.slice(0, 200), secondes: (Date.now() - debut) / 1000 })
    console.log(v, 'ÉCHEC', erreur.message.slice(0, 200))
    continue
  }
  const secondes = (Date.now() - debut) / 1000
  const nom = `${v.replace(/[^a-z0-9]+/gi, '-')}.${octets.toString('ascii', 0, 4) === 'RIFF' ? 'wav' : 'mp3'}`
  await fs.writeFile(path.join(SORTIE, nom), octets)
  const type = nom.endsWith('wav') ? 'audio/wav' : 'audio/mp3'
  const transcrit = await transcrireGroq(octets, nom).catch((e) => ({ texte: '', erreur: e.message }))
  const note = await juger(octets, type)
  if (resultats.length) await new Promise((r) => setTimeout(r, Number(process.env.PAUSE_MS ?? 5000)))
  const r = { voix: v, ok: true, secondes, duree_audio: dureeWav(octets) ?? transcrit.duree ?? null, mots_compris: exactitude(TEXTE, transcrit.texte), entendu: transcrit.texte, ...note }
  resultats.push(r)
  console.log(v, JSON.stringify(r).slice(0, 300))
}
const fichier = new URL(`../mesures/voix-gratuites${process.env.SUFFIXE ?? ''}.json`, import.meta.url)
await fs.writeFile(fichier, `${JSON.stringify({ date: new Date().toISOString(), texte: TEXTE, resultats }, null, 2)}\n`)
