import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execFileSync, spawnSync } from 'node:child_process'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { argumentsMesure, bruitagesDuPlan, CIBLE_SON, commandesBruitages, controleSon, correction, filtreMelange, filtreNormalisation, filtreVoixRadio, gainMusique, lireMesure } from '../src/videos/montage/son.js'

const PLAN = {
  duree: 10,
  plans: [
    { type: 'accroche', debut: 0, duree: 2, temps: {} },
    { type: 'liste', debut: 2, duree: 3, temps: { element0: 0.3, element1: 0.4, element2: 1.2 } },
    { type: 'tampon', debut: 5, duree: 2, temps: {} },
    { type: 'carte', debut: 7, duree: 3, temps: {} },
  ],
}

test('la voix : silences à 0,2 seconde, tempo borné, traitement « radio »', () => {
  const f = filtreVoixRadio({ tempo: 1.07 })
  assert.match(f, /stop_duration=0\.2:.*stop_silence=0\.2/)
  assert.match(f, /atempo=1\.07/)
  assert.match(f, /highpass=f=90/)
  assert.match(f, /acompressor/)
  // Jamais plus de 15 % plus vite, jamais plus lent.
  assert.match(filtreVoixRadio({ tempo: 2 }), /atempo=1\.15/)
  assert.match(filtreVoixRadio({ tempo: 0.5 }), /atempo=1,/)
})

test('les bruitages : souffle aux changements, choc sur les temps forts, pop discrets et jamais trop proches', () => {
  const b = bruitagesDuPlan(PLAN)
  assert.equal(b[0].nom, 'souffle')
  assert.equal(b[0].t, 1.95)
  // Pas de bruitage sur l'accroche (image 0).
  assert.ok(b.every((x) => x.t > 0))
  // element0 et element1 sont à 0,1 seconde l'un de l'autre : un seul pop.
  assert.deepEqual(b.filter((x) => x.nom === 'pop').map((x) => x.t), [2.3, 3.2])
  assert.deepEqual(b.filter((x) => x.nom === 'choc').map((x) => x.t), [4.95, 6.95])
  for (let i = 1; i < b.length; i++) assert.ok(b[i].t - b[i - 1].t >= 0.25)
  assert.ok(b.every((x) => x.volume <= 0.5))
})

test('le mélange : musique 20 LU sous la voix et baissée quand la voix parle', () => {
  assert.equal(gainMusique(-13, -25), -8)
  assert.equal(gainMusique(NaN, -25), -20)
  const f = filtreMelange({ duree: 10, decalageVoix: 0.25, musique: true, bruitages: bruitagesDuPlan(PLAN), gainMusique: -8 })
  assert.match(f, /adelay=250:all=1/)
  assert.match(f, /volume=-8\.00dB/)
  assert.match(f, /sidechaincompress/)
  assert.match(f, /amix=inputs=\d+:normalize=0/)
  assert.match(f, /\[melange\]$/)
  // Sans musique : la voix et les bruitages seulement.
  assert.doesNotMatch(filtreMelange({ duree: 5, bruitages: [] }), /sidechaincompress/)
})

test('la mesure, la normalisation et le contrôle : -14 LUFS à 0,3 LU près, pic vrai sous -1,5 dBTP', () => {
  const journal = 'bla\n{\n"input_i" : "-19.20",\n"input_tp" : "-4.10",\n"input_lra" : "3.40",\n"input_thresh" : "-29.60",\n"target_offset" : "0.20"\n}\n'
  const m = lireMesure(journal)
  assert.deepEqual(m, { lufs: -19.2, picVrai: -4.1, lra: 3.4, seuil: -29.6, decalage: 0.2 })
  assert.match(filtreNormalisation(m), /loudnorm=I=-14:TP=-1\.5:LRA=11:measured_I=-19\.2:.*linear=true/)
  assert.deepEqual(controleSon({ lufs: -14.2, picVrai: -2 }), [])
  assert.equal(correction({ lufs: -14.2, picVrai: -2 }), null)
  assert.equal(controleSon({ lufs: -15, picVrai: -2 }).length, 1)
  assert.match(correction({ lufs: -15, picVrai: -2 }), /volume=1\.00dB,alimiter/)
  assert.equal(controleSon({ lufs: -14, picVrai: -0.5 }).length, 1)
  assert.ok(correction({ lufs: -14, picVrai: -0.5 }))
  assert.deepEqual(controleSon(null), ['mesure impossible'])
  assert.equal(CIBLE_SON.lufs, -14)
})

test('pour de vrai avec FFmpeg : bruitages fabriqués, voix préparée plus courte, son final conforme', { skip: spawnSync('ffmpeg', ['-version']).status !== 0 }, () => {
  const dossier = mkdtempSync(path.join(tmpdir(), 'kekeli-son-'))
  for (const args of commandesBruitages(dossier)) execFileSync('ffmpeg', args)
  // Une « voix » d'essai : deux sons séparés par un long silence de 1,5 seconde.
  const voix = path.join(dossier, 'voix.wav')
  execFileSync('ffmpeg', ['-v', 'error', '-y', '-f', 'lavfi', '-i', "aevalsrc='0.4*sin(2*PI*220*t)*between(t,0,1)+0.4*sin(2*PI*330*t)*between(t,2.5,3.5)':s=48000:d=3.6", voix])
  const prete = path.join(dossier, 'prete.wav')
  execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', voix, '-af', filtreVoixRadio({ tempo: 1.07 }), prete])
  const duree = (f) => Number(execFileSync('ffprobe', ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', f], { encoding: 'utf8' }))
  assert.ok(duree(prete) < 2.6, `voix préparée : ${duree(prete)} s`)
  const bruitages = [{ nom: 'souffle', t: 0.5, volume: 0.3 }, { nom: 'pop', t: 1, volume: 0.2 }, { nom: 'choc', t: 1.5, volume: 0.5 }]
  const melange = path.join(dossier, 'melange.wav')
  execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', prete, ...bruitages.flatMap((b) => ['-i', path.join(dossier, `${b.nom}.wav`)]), '-filter_complex', filtreMelange({ duree: 3, decalageVoix: 0.25, bruitages }), '-map', '[melange]', '-ar', '48000', '-ac', '2', melange])
  const mesurer = (f) => lireMesure(spawnSync('ffmpeg', argumentsMesure(f), { encoding: 'utf8' }).stderr)
  const finale = path.join(dossier, 'finale.wav')
  execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', melange, '-af', filtreNormalisation(mesurer(melange)), '-ar', '48000', '-ac', '2', finale])
  let m = mesurer(finale)
  const corr = correction(m)
  if (corr) {
    execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', finale, '-af', corr, path.join(dossier, 'corrige.wav')])
    m = mesurer(path.join(dossier, 'corrige.wav'))
  }
  assert.deepEqual(controleSon(m), [], JSON.stringify(m))
})
