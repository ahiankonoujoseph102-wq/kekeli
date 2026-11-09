import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, mkdir, rm, symlink, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { CHEMIN_DIRECT, ajouterScript, cheminSur, cleApercu, creerServeurApercu, jetonApercu, jetonValide, scriptDirect } from '../src/apercu/serveur.mjs'
import { codeApercu } from '../src/ordinateurs.js'
import { executerTache } from '../src/agent.js'
import { MisesDeCote } from '../src/limites.js'
import { fauxOpencode, finRequete, libre, occupe, outil } from './outils.js'

const CLE = cleApercu('secret-de-test-tres-long-pour-les-signatures-0123456789', 'p1')
const dans = (minutes) => Math.floor(Date.now() / 1000) + minutes * 60

async function demarrer(dossier) {
  const serveur = creerServeurApercu({ dossier, intervalleMs: 50, projet: 'p1', cle: CLE })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const hote = `http://127.0.0.1:${serveur.address().port}`
  const jeton = jetonApercu(CLE, 'p1', dans(30))
  return {
    hote,
    jeton,
    url: `${hote}/k/${jeton}`,
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
  const script = scriptDirect('/k/abc/')
  assert.ok(script.includes("'/k/abc/__kekeli/direct'"))
  assert.equal(ajouterScript('<html><body><p>Bonjour</p></body></html>', '/k/abc/'), `<html><body><p>Bonjour</p>${script}</body></html>`)
  assert.equal(ajouterScript('<p>Bonjour</p>', '/k/abc/'), `<p>Bonjour</p>${script}`)
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
    assert.ok(html.includes(`/k/${apercu.jeton}/__kekeli/direct`))

    reponse = await fetch(apercu.url + '/css/style.css')
    assert.match(reponse.headers.get('content-type'), /text\/css/)
    assert.equal(await reponse.text(), 'h1 { color: orange; }')

    reponse = await fetch(apercu.url + '/css', { redirect: 'manual' })
    assert.equal(reponse.status, 302)
    assert.equal(reponse.headers.get('location'), `/k/${apercu.jeton}/css/`)

    reponse = await fetch(apercu.url + '/absente.html')
    assert.equal(reponse.status, 404)
    assert.match(await reponse.text(), /Page introuvable/)

    reponse = await fetch(apercu.hote + '/__kekeli/sante')
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
      assert.ok([403, 404].includes(reponse.status), chemin)
      assert.doesNotMatch(await reponse.text(), /ne-pas-montrer|hors du projet/, chemin)
    }
  } finally {
    await apercu.arreter()
    await rm(parent, { recursive: true, force: true })
  }
})

test('le jeton n’ouvre que l’aperçu de son projet, et seulement jusqu’à son expiration', () => {
  const jeton = jetonApercu(CLE, 'p1', dans(5))
  assert.ok(jetonValide(CLE, 'p1', jeton))
  assert.ok(!jetonValide(CLE, 'p2', jeton))
  assert.ok(!jetonValide(cleApercu('un-autre-secret-tout-aussi-long-0123456789', 'p1'), 'p1', jeton))
  assert.ok(!jetonValide(CLE, 'p1', jetonApercu(CLE, 'p1', dans(-1))))
  assert.ok(!jetonValide(CLE, 'p1', jeton.replace(/.$/, (c) => (c === '0' ? '1' : '0'))))
  assert.ok(!jetonValide(CLE, 'p1', 'nimporte-quoi'))
  assert.ok(!jetonValide(null, 'p1', jeton))
})

test('sans jeton valable, l’aperçu est privé ; un lien absolu depuis une page autorisée est redirigé', async () => {
  const dossier = await dossierTemporaire()
  await writeFile(path.join(dossier, 'index.html'), '<h1>Mon restaurant</h1>')
  await writeFile(path.join(dossier, 'menu.html'), '<h1>Le menu</h1>')
  const apercu = await demarrer(dossier)
  try {
    for (const chemin of ['/', '/index.html', '/menu.html', `/k/${jetonApercu(CLE, 'p1', dans(-1))}/`, `/k/${jetonApercu(CLE, 'p2', dans(30))}/`, '/k/faux/']) {
      const reponse = await fetch(apercu.hote + chemin, { redirect: 'manual' })
      assert.equal(reponse.status, 403, chemin)
      const texte = await reponse.text()
      assert.match(texte, /Aperçu privé/, chemin)
      assert.doesNotMatch(texte, /Mon restaurant|Le menu/, chemin)
    }

    // Un lien absolu « /menu.html » dans une page ouverte avec le jeton.
    let reponse = await fetch(apercu.hote + '/menu.html', { redirect: 'manual', headers: { Referer: `${apercu.url}/index.html` } })
    assert.equal(reponse.status, 302)
    assert.equal(reponse.headers.get('location'), `/k/${apercu.jeton}/menu.html`)

    // La même astuce depuis un autre site ne marche pas.
    reponse = await fetch(apercu.hote + '/menu.html', { redirect: 'manual', headers: { Referer: `https://autre-site.example/k/${apercu.jeton}/` } })
    assert.equal(reponse.status, 403)

    // Sans barre finale, on ajoute la barre.
    reponse = await fetch(apercu.url, { redirect: 'manual' })
    assert.equal(reponse.headers.get('location'), `/k/${apercu.jeton}/`)
    reponse = await fetch(apercu.url + '/menu.html')
    assert.match(await reponse.text(), /Le menu/)
  } finally {
    await apercu.arreter()
    await rm(dossier, { recursive: true, force: true })
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
  assert.equal(apercus.length, 2)
  assert.equal(apercus[0].url, 'https://4100-abc.e2b.app')
  // Pendant le travail, l'ordinateur reste allumé jusqu'à la durée maximale d'une demande.
  assert.ok(new Date(apercus[0].expire_a).getTime() > Date.now() + 21 * 60000)
  assert.deepEqual(apercus[1], { type: 'apercu', url: 'https://4100-abc.e2b.app', expire_a: fin.toISOString() })
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
