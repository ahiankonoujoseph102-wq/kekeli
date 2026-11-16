// Le son des vidéos motion design (étape 3, 16 novembre 2026), comme les montages de Lomé IA School :
// 1. la voix est préparée AVANT la transcription (les images se calent sur la voix finale) : silences raccourcis à
//    0,2 seconde, tempo légèrement accéléré, traitement « radio » (coupe des graves, présence, compression) ;
// 2. la musique est posée environ 20 LU sous la voix et baisse encore quand la voix parle ;
// 3. des bruitages discrets (souffle à chaque changement de plan, petit « pop » quand un élément arrive, choc sourd
//    sur un temps fort) sont fabriqués par FFmpeg : aucun fichier extérieur, aucune licence à respecter ;
// 4. le mélange final est mesuré : -14 LUFS intégrés, pic vrai sous -1,5 dBTP, LRA 11 ; si l'écart dépasse 0,3 LU,
//    un gain constant le corrige et un limiteur protège le pic. AAC 192 kb/s, 48 kHz, stéréo.

export const CIBLE_SON = { lufs: -14, picVrai: -1.5, lra: 11, ecartMax: 0.3, ecartMusique: 20 }

/** Le tempo de la voix : légèrement plus vif (× 1,07 pour la voix de Kekeli, × 1,05 pour celle du client). */
export const TEMPO_VOIX = { kekeli: 1.07, client: 1.05 }

/** Les silences de la voix ne durent jamais plus de 0,2 seconde (rythme des publicités). */
export const SILENCE_MAX = 0.2

/**
 * Le filtre FFmpeg qui prépare la voix : silences raccourcis, tempo, puis traitement « radio » (coupe sous 90 Hz,
 * creux à 250 Hz contre le son « boueux », présence à 3 kHz, compression qui rend la voix égale et proche).
 */
export function filtreVoixRadio({ tempo = TEMPO_VOIX.kekeli, silence = SILENCE_MAX } = {}) {
  const t = Math.min(1.15, Math.max(1, Number(tempo) || 1))
  return [
    // Le début et chaque silence de plus de 0,2 seconde ramenés à 0,2 seconde (rien n'est coupé dans un mot).
    `silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.05:stop_periods=-1:stop_duration=${silence}:stop_threshold=-45dB:stop_silence=${silence}`,
    `atempo=${t}`,
    'highpass=f=90',
    'equalizer=f=250:t=q:w=1:g=-2',
    'equalizer=f=3000:t=q:w=1.2:g=3',
    'lowpass=f=12000',
    'acompressor=threshold=-20dB:ratio=4:attack=5:release=80:makeup=4',
    'aresample=48000',
  ].join(',')
}

/** Les bruitages, fabriqués par FFmpeg (sons de synthèse : rien à télécharger, rien à citer). */
export const BRUITAGES = {
  // Souffle court qui accompagne un changement de plan.
  souffle: 'anoisesrc=d=0.4:c=pink:a=0.5,highpass=f=500,lowpass=f=6000,afade=t=in:d=0.18,afade=t=out:st=0.18:d=0.22',
  // Petit « pop » quand une case, un chiffre ou une bulle arrive.
  pop: "aevalsrc='0.6*sin(2*PI*(500+900*exp(-35*t))*t)*exp(-28*t)':s=48000:d=0.16",
  // Choc sourd sur un temps fort (tampon, numéro, compteur).
  choc: "aevalsrc='0.9*sin(2*PI*(45+110*exp(-9*t))*t)*exp(-9*t)':s=48000:d=0.45",
}

/** Les commandes FFmpeg qui fabriquent les bruitages dans un dossier (un fichier WAV chacun). */
export function commandesBruitages(dossier) {
  return Object.entries(BRUITAGES).map(([nom, source]) => ['-v', 'error', '-y', '-f', 'lavfi', '-i', source, '-ac', '2', '-ar', '48000', `${dossier}/${nom}.wav`])
}

const TEMPS_FORTS = new Set(['tampon', 'numero', 'compteur', 'carte'])
/** Deux bruitages jamais plus proches que 0,25 seconde ; jamais plus d'un toutes les 0,6 seconde en moyenne. */
const ECART_BRUITAGES = 0.25

/**
 * Les bruitages d'un plan calé sur la voix : un souffle à chaque changement de plan (pas sur l'accroche, déjà là à
 * l'image 0), un choc sur les temps forts, un « pop » pour chaque élément qui arrive sur un mot. Discrets : volume
 * bas, jamais deux trop proches.
 * @returns {{ nom: string, t: number, volume: number }[]}
 */
export function bruitagesDuPlan(planCale) {
  const sons = []
  planCale.plans.forEach((p, i) => {
    if (i > 0) sons.push({ nom: TEMPS_FORTS.has(p.type) ? 'choc' : 'souffle', t: Math.max(0, p.debut - 0.05), volume: TEMPS_FORTS.has(p.type) ? 0.5 : 0.28 })
    for (const [cle, t] of Object.entries(p.temps ?? {})) {
      if (/^(element|groupe|message)\d+$/.test(cle)) sons.push({ nom: 'pop', t: p.debut + t, volume: 0.22 })
    }
  })
  sons.sort((a, b) => a.t - b.t)
  const gardes = []
  for (const s of sons) {
    if (gardes.length && s.t - gardes[gardes.length - 1].t < ECART_BRUITAGES) continue
    gardes.push({ ...s, t: Math.round(s.t * 1000) / 1000 })
  }
  return gardes
}

const ms = (s) => Math.max(0, Math.round(s * 1000))

/**
 * Le filtre du mélange (filter_complex) : entrée 0 = la voix préparée, 1 = la musique (facultative), puis un fichier
 * par bruitage dans l'ordre de « bruitages ». La musique est mise 20 LU sous la voix (niveaux mesurés avant), puis
 * baisse encore quand la voix parle ; le résultat sort en [melange], à normaliser ensuite.
 * @param {{ duree: number, decalageVoix: number, musique: boolean, bruitages: {nom: string, t: number, volume: number}[], gainMusique?: number }} o
 */
export function filtreMelange({ duree, decalageVoix = 0, musique = false, bruitages = [], gainMusique = 0 }) {
  const parties = [`[0:a]aformat=sample_rates=48000:channel_layouts=stereo,adelay=${ms(decalageVoix)}:all=1,apad=whole_dur=${duree}${musique ? ',asplit=2[voix][cle]' : '[voix]'}`]
  const sorties = ['[voix]']
  let index = 1
  if (musique) {
    parties.push(`[1:a]aformat=sample_rates=48000:channel_layouts=stereo,atrim=0:${duree},asetpts=PTS-STARTPTS,volume=${gainMusique.toFixed(2)}dB,afade=t=in:d=0.6,afade=t=out:st=${Math.max(0, duree - 2).toFixed(2)}:d=2[mus]`)
    // La musique baisse quand la voix parle (environ 6 dB de plus), et remonte doucement dans les silences.
    parties.push('[mus][cle]sidechaincompress=threshold=0.02:ratio=6:attack=15:release=350[musb]')
    sorties.push('[musb]')
    index = 2
  }
  bruitages.forEach((b, i) => {
    parties.push(`[${index + i}:a]aformat=sample_rates=48000:channel_layouts=stereo,volume=${b.volume},adelay=${ms(b.t)}:all=1[b${i}]`)
    sorties.push(`[b${i}]`)
  })
  parties.push(`${sorties.join('')}amix=inputs=${sorties.length}:normalize=0:duration=longest,atrim=0:${duree}[melange]`)
  return parties.join(';')
}

/** Le gain (dB) à donner à la musique pour qu'elle soit « ecart » LU sous la voix, d'après leurs mesures. */
export function gainMusique(lufsVoix, lufsMusique, ecart = CIBLE_SON.ecartMusique) {
  if (!Number.isFinite(lufsVoix) || !Number.isFinite(lufsMusique)) return -20
  return Math.round((lufsVoix - ecart - lufsMusique) * 10) / 10
}

/**
 * La mesure d'un son par le filtre « loudnorm » de FFmpeg (sortie JSON dans le journal) : volume intégré, pic vrai,
 * plage de volume (LRA) et seuil, pour la deuxième passe.
 * @returns {{ lufs: number, picVrai: number, lra: number, seuil: number } | null}
 */
export function lireMesure(journal) {
  const blocs = String(journal).match(/\{[^{}]*"input_i"[^{}]*\}/g)
  if (!blocs) return null
  const d = JSON.parse(blocs[blocs.length - 1])
  const n = (v) => Number.parseFloat(v)
  return { lufs: n(d.input_i), picVrai: n(d.input_tp), lra: n(d.input_lra), seuil: n(d.input_thresh), decalage: n(d.target_offset) }
}

/** Les arguments FFmpeg qui mesurent un son (sans rien écrire). */
export function argumentsMesure(fichier) {
  return ['-hide_banner', '-nostats', '-i', fichier, '-vn', '-af', `loudnorm=I=${CIBLE_SON.lufs}:TP=${CIBLE_SON.picVrai}:LRA=${CIBLE_SON.lra}:print_format=json`, '-f', 'null', '-']
}

/**
 * Le filtre de la deuxième passe : normalisation linéaire (le son garde sa dynamique) à partir de la première mesure,
 * puis un limiteur de sécurité juste sous le pic permis.
 */
export function filtreNormalisation(mesure) {
  const lin = `loudnorm=I=${CIBLE_SON.lufs}:TP=${CIBLE_SON.picVrai}:LRA=${CIBLE_SON.lra}:measured_I=${mesure.lufs}:measured_TP=${mesure.picVrai}:measured_LRA=${mesure.lra}:measured_thresh=${mesure.seuil}:offset=${Number.isFinite(mesure.decalage) ? mesure.decalage : 0}:linear=true`
  return `${lin},aresample=48000`
}

/** Le gain constant qui corrige un écart restant (et le limiteur si le pic dépasse), ou null si tout est bon. */
export function correction(mesure) {
  const ecart = CIBLE_SON.lufs - mesure.lufs
  const trop = mesure.picVrai > CIBLE_SON.picVrai
  if (Math.abs(ecart) <= CIBLE_SON.ecartMax && !trop) return null
  const limite = Math.pow(10, (CIBLE_SON.picVrai - 0.5) / 20).toFixed(3)
  return `volume=${ecart.toFixed(2)}dB,alimiter=limit=${limite}:level=false:attack=1:release=50,aresample=48000`
}

/** Les problèmes d'un son mesuré (vide quand il respecte la règle : -14 LUFS à 0,3 LU près, pic vrai ≤ -1,5 dBTP). */
export function controleSon(mesure) {
  if (!mesure) return ['mesure impossible']
  const problemes = []
  if (Math.abs(mesure.lufs - CIBLE_SON.lufs) > CIBLE_SON.ecartMax) problemes.push(`volume ${mesure.lufs.toFixed(1)} LUFS au lieu de ${CIBLE_SON.lufs}`)
  if (mesure.picVrai > CIBLE_SON.picVrai + 0.05) problemes.push(`pic vrai ${mesure.picVrai.toFixed(1)} dBTP au-dessus de ${CIBLE_SON.picVrai}`)
  return problemes
}
