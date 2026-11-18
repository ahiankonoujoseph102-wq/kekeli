// Un fichier ZIP, sans dépendance (format « PKZIP » de base, compression « deflate » de Node).

import { crc32, deflateRawSync } from 'node:zlib'

const DATE_DOS = (() => {
  // Une date fixe : le même contenu donne toujours le même ZIP (1er janvier 2026, 00 h 00).
  return { heure: 0, jour: ((2026 - 1980) << 9) | (1 << 5) | 1 }
})()

/** @param {Array<{chemin: string, contenu: string | Uint8Array}>} fichiers */
export function creerZip(fichiers) {
  const locaux = []
  const centraux = []
  let decalage = 0
  for (const { chemin, contenu } of fichiers) {
    if (!/^[\w.\-/]+$/.test(chemin) || chemin.includes('..') || chemin.startsWith('/')) throw new Error(`Chemin refusé dans le ZIP : ${chemin}`)
    const nom = Buffer.from(chemin, 'utf8')
    const brut = typeof contenu === 'string' ? Buffer.from(contenu, 'utf8') : Buffer.from(contenu)
    const compresse = deflateRawSync(brut, { level: 9 })
    const somme = crc32(brut) >>> 0
    const local = Buffer.alloc(30)
    local.writeUInt32LE(0x04034b50, 0)
    local.writeUInt16LE(20, 4)
    local.writeUInt16LE(0x0800, 6) // noms en UTF-8
    local.writeUInt16LE(8, 8)
    local.writeUInt16LE(DATE_DOS.heure, 10)
    local.writeUInt16LE(DATE_DOS.jour, 12)
    local.writeUInt32LE(somme, 14)
    local.writeUInt32LE(compresse.length, 18)
    local.writeUInt32LE(brut.length, 22)
    local.writeUInt16LE(nom.length, 26)
    local.writeUInt16LE(0, 28)
    locaux.push(local, nom, compresse)

    const central = Buffer.alloc(46)
    central.writeUInt32LE(0x02014b50, 0)
    central.writeUInt16LE(20, 4)
    central.writeUInt16LE(20, 6)
    central.writeUInt16LE(0x0800, 8)
    central.writeUInt16LE(8, 10)
    central.writeUInt16LE(DATE_DOS.heure, 12)
    central.writeUInt16LE(DATE_DOS.jour, 14)
    central.writeUInt32LE(somme, 16)
    central.writeUInt32LE(compresse.length, 20)
    central.writeUInt32LE(brut.length, 24)
    central.writeUInt16LE(nom.length, 28)
    central.writeUInt32LE(decalage, 42)
    centraux.push(central, nom)
    decalage += local.length + nom.length + compresse.length
  }
  const tailleCentral = centraux.reduce((n, b) => n + b.length, 0)
  const fin = Buffer.alloc(22)
  fin.writeUInt32LE(0x06054b50, 0)
  fin.writeUInt16LE(fichiers.length, 8)
  fin.writeUInt16LE(fichiers.length, 10)
  fin.writeUInt32LE(tailleCentral, 12)
  fin.writeUInt32LE(decalage, 16)
  return Buffer.concat([...locaux, ...centraux, fin])
}
