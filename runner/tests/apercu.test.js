import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, mkdir, rm, symlink, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { CHEMIN_DIRECT, SCRIPT_DIRECT, ajouterScript, cheminSur, creerServeurApercu } from '../src/apercu/serveur.mjs'
import { codeApercu } from '../src/ordinateurs.js'
import { executerTache } from '../src/agent.js'
import { MisesDeCote } from '../src/limites.js'
import { fauxOpencode, finRequete, libre, occupe, outil } from './outils.js'

async function demarrer(dossier) {
  const serveur = creerServeurApercu({ dossier, intervalleMs: 50 })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const url = `http://127.0.0.1:${serveur.address().port}`
  return {
    url,
    async arreter() {
      serveur.closeAllConnections()
      await new Promise((r) => serveur.close(r))
    },
  }
}

async function dossierTemporaire() {
  return mkdtemp(path.join(tmpdir(), 'kekeli-apercu-'))
}

test('les chemins qui sortent du dossier ou touchent un fichier caché sont refusés', () => {
  const racine = '/home/user/projet'
  assert.equal(cheminSur(racine, '/'), racine)
  assert.equal(cheminSur(racine, '/css/style.css'), '/home/user/projet/css/style.css')
  assert.equal(cheminSur(racine, '/mon%20image.png'), '/home/user/projet/mon image.png')
  assert.equal(cheminSur(racine, '/../secret.txt'), null)
  assert.equal(cheminSur(racine, '/%2e%2e/secret.txt'), null)
  assert.equal(cheminSur(racine, '/.env'), null)
  assert.equal(cheminSur(racine, '/.git/config'), null)
  assert.equal(cheminSur(racine, '/a%00b'), null)
  assert.equal(cheminSur(racine, '/%E0%A4%A'), null)
})

test('le script de rechargement est ajouté juste avant la fin de la page', () => {
  assert.equal(ajouterScript('<html><body><p>Bonjour</p></body></html>'), `<html><body><p>Bonjour</p>${SCRIPT_DIRECT}</body></html>`)
  assert.equal(ajouterScript('<p>Bonjour</p>'), `<p>Bonjour</p>${SCRIPT_DIRECT}`)
})

test('l’aperçu montre une page d’attente, puis le site dès que index.html existe', async () => {
  const dossier = await dossierTemporaire()
  const apercu = await demarrer(dossier)
  try {
    let reponse = await fetch(apercu.url + '/')
    assert.equal(reponse.status, 200)
    assert.equal(reponse.headers.get('cache-control'), 'no-store')
    assert.match(await reponse.text(), /Votre site apparaîtra ici/)

    await writeFile(path.join(dossier, 'index.html'), '<!doctype html><html><body><h1>Restaurant Kekeli</h1></body></html>')
    await mkdir(path.join(dossier, 'css'))
    await writeFile(path.join(dossier, 'css', 'style.css'), 'h1 { color: orange; }')

    reponse = await fetch(apercu.url + '/')
    const html = await reponse.text()
    assert.match(reponse.headers.get('content-type'), /text\/html/)
    assert.match(html, /Restaurant Kekeli/)
    assert.ok(html.includes(CHEMIN_DIRECT))

    reponse = await fetch(apercu.url + '/css/style.css')
    assert.match(reponse.headers.get('content-type'), /text\/css/)
    assert.equal(await reponse.text(), 'h1 { color: orange; }')

    reponse = await fetch(apercu.url + '/css', { redirect: 'manual' })
    assert.equal(reponse.status, 302)
    assert.equal(reponse.headers.get('location'), '/css/')

    reponse = await fetch(apercu.url + '/absente.html')
    assert.equal(reponse.status, 404)
    assert.match(await reponse.text(), /Page introuvable/)

    reponse = await fetch(apercu.url + '/__kekeli/sante')
    assert.deepEqual(await reponse.json(), { ok: true })

    reponse = await fetch(apercu.url + '/', { method: 'POST' })
    assert.equal(reponse.status, 405)
  } finally {
    await apercu.arreter()
    await rm(dossier, { recursive: true, force: true })
  }
})

test('l’aperçu ne montre ni les fichiers cachés ni les fichiers hors du projet', async () => {
  const parent = await dossierTemporaire()
  const dossier = path.join(parent, 'projet')
  await mkdir(dossier)
  await writeFile(path.join(dossier, '.env'), 'SECRET=ne-pas-montrer')
  await writeFile(path.join(parent, 'dehors.txt'), 'hors du projet')
  await symlink(path.join(parent, 'dehors.txt'), path.join(dossier, 'lien.txt'))
  const apercu = await demarrer(dossier)
  try {
    for (const chemin of ['/.env', '/lien.txt', '/../dehors.txt', '/%2e%2e/dehors.txt']) {
      const reponse = await fetch(apercu.url + chemin)
      assert.equal(reponse.status, 404, chemin)
      assert.doesNotMatch(await reponse.text(), /ne-pas-montrer|hors du projet/, chemin)
    }
  } finally {
    await apercu.arreter()
    await rm(parent, { recursive: true, force: true })
  }
})

test('la page est prévenue en direct quand un fichier change', async () => {
  const dossier = await dossierTemporaire()
  const apercu = await demarrer(dossier)
  const arret = new AbortController()
  try {
    const reponse = await fetch(apercu.url + CHEMIN_DIRECT, { signal: arret.signal })
    assert.match(reponse.headers.get('content-type'), /text\/event-stream/)
    const lecteur = reponse.body.getReader()
    const decodeur = new TextDecoder()
    await new Promise((r) => setTimeout(r, 150))
    await writeFile(path.join(dossier, 'index.html'), '<h1>Bonjour</h1>')

    let recu = ''
    const limite = Date.now() + 3000
    while (!recu.includes('event: changement') && Date.now() < limite) {
      const { value, done } = await lecteur.read()
      if (done) break
      recu += decodeur.decode(value)
    }
    assert.ok(recu.includes('event: changement'))
  } finally {
    arret.abort()
    await apercu.arreter()
    await rm(dossier, { recursive: true, force: true })
  }
})

test('le code copié dans l’ordinateur virtuel est bien celui du serveur d’aperçu', async () => {
  const code = await codeApercu()
  assert.ok(code.includes('creerServeurApercu'))
  assert.ok(!code.includes('import e2b') && !code.includes("from 'e2b'"))
})

test('le lien de l’aperçu est envoyé au site, puis la nouvelle heure de mise en veille à la fin', async () => {
  const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, outil('write', { filePath: '/home/user/projet/index.html' }, 'p1'), finRequete('r1'), libre] })
  const debut = new Date('2026-11-08T10:00:00Z')
  const fin = new Date('2026-11-08T10:31:00Z')
  const ordinateurs = {
    obtenir: async () => ({ ...(await faux.ordinateurs.obtenir()), apercu: { url: 'https://4100-abc.e2b.app' }, expireA: debut }),
    prolonger: async () => fin,
  }
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Crée une page', fournisseurs: [GEMINI] },
    { ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  const apercus = evenements.filter((e) => e.type === 'apercu')
  assert.deepEqual(apercus, [
    { type: 'apercu', url: 'https://4100-abc.e2b.app', expire_a: debut.toISOString() },
    { type: 'apercu', url: 'https://4100-abc.e2b.app', expire_a: fin.toISOString() },
  ])
  assert.equal(evenements.at(-1).type, 'terminee')
  assert.ok(evenements.indexOf(apercus[1]) < evenements.length - 1)
})

test('sans aperçu, la tâche se déroule normalement', async () => {
  const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
  const faux = fauxOpencode({ [GEMINI.modele]: [occupe, finRequete('r1'), libre] })
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Bonjour', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  assert.ok(!evenements.some((e) => e.type === 'apercu'))
  assert.equal(evenements.at(-1).type, 'terminee')
})
