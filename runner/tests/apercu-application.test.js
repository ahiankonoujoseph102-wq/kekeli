import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises'
import net from 'node:net'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { COOKIE_APERCU, adapterCookie, cleApercu, creerServeurApercu, jetonApercu, lireCookie, sansCookieApercu } from '../src/apercu/serveur.mjs'

const CLE = cleApercu('secret-de-test-tres-long-pour-les-signatures-0123456789', 'p1')
const dans = (minutes) => Math.floor(Date.now() / 1000) + minutes * 60
const attendre = (ms) => new Promise((r) => setTimeout(r, ms))

async function portLibre() {
  const serveur = net.createServer()
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const { port } = serveur.address()
  await new Promise((r) => serveur.close(r))
  return port
}

// Petite application d'essai : elle pose son propre cookie et renvoie ce qu'elle a reçu.
const APPLICATION = `
import http from 'node:http'
http.createServer((req, res) => {
  if (req.url === '/arret') { res.end('arrêt'); setTimeout(() => process.exit(1), 50); return }
  if (req.url === '/panne') { res.statusCode = 500; res.setHeader('Content-Type', 'text/html; charset=utf-8'); res.end('<h1>Internal Server Error</h1><p>Illuminate\\Database\\QueryException</p>'); return }
  let corps = ''
  req.on('data', (m) => (corps += m))
  req.on('end', () => {
    res.setHeader('Set-Cookie', 'session=abc; Path=/; HttpOnly; SameSite=Lax')
    res.setHeader('Content-Type', 'text/html; charset=utf-8')
    res.end('<html><body><h1>Application</h1><pre>' + JSON.stringify({ methode: req.method, chemin: req.url, cookie: req.headers.cookie ?? null, proto: req.headers['x-forwarded-proto'], corps }) + '</pre></body></html>')
  })
}).listen(Number(process.env.PORT), '127.0.0.1')
`

async function preparer({ dureeRedemarrageMs } = {}) {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-app-'))
  const reglages = path.join(dossier, '.kekeli', 'application.json')
  await writeFile(path.join(dossier, 'app.mjs'), APPLICATION)
  const serveur = creerServeurApercu({ dossier, intervalleMs: 50, projet: 'p1', cle: CLE, fichierApplication: reglages, dureeRedemarrageMs })
  await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
  const hote = `http://127.0.0.1:${serveur.address().port}`
  const jeton = jetonApercu(CLE, 'p1', dans(30))
  return {
    dossier,
    reglages,
    hote,
    jeton,
    cookie: `${COOKIE_APERCU}=${jeton}`,
    async arreter() {
      serveur.closeAllConnections()
      await new Promise((r) => serveur.close(r))
      await rm(dossier, { recursive: true, force: true })
    },
  }
}

/** Requête répétée tant que l'application démarre (réponse 503). */
async function quandPrete(url, options = {}) {
  for (let essai = 0; essai < 100; essai++) {
    const reponse = await fetch(url, { redirect: 'manual', ...options })
    if (reponse.status !== 503) return reponse
    await attendre(100)
  }
  throw new Error('L’application n’a pas démarré.')
}

test('le cookie de l’aperçu est lu, retiré avant l’application, et les cookies de l’application sont adaptés au cadre', () => {
  assert.equal(lireCookie('a=1; kekeli_apercu=xyz; b=2', COOKIE_APERCU), 'xyz')
  assert.equal(lireCookie('a=1', COOKIE_APERCU), null)
  assert.equal(sansCookieApercu('a=1; kekeli_apercu=xyz; b=2'), 'a=1; b=2')
  assert.equal(sansCookieApercu('kekeli_apercu=xyz'), null)
  assert.equal(adapterCookie('laravel_session=abc; expires=Tue, 10 Nov 2026 12:00:00 GMT; path=/; httponly; samesite=lax'), 'laravel_session=abc; expires=Tue, 10 Nov 2026 12:00:00 GMT; path=/; httponly; SameSite=None; Secure; Partitioned')
})

test('mode application : privé sans le lien, puis l’application du client est montrée, relancée si elle s’arrête', async () => {
  const apercu = await preparer()
  try {
    // Sans le lien privé : rien.
    for (const chemin of ['/', '/espace', `/k/${jetonApercu(CLE, 'p2', dans(30))}/`]) {
      assert.equal((await fetch(apercu.hote + chemin, { redirect: 'manual' })).status, 403, chemin)
    }
    assert.equal((await fetch(apercu.hote + '/', { headers: { cookie: `${COOKIE_APERCU}=${jetonApercu(CLE, 'p1', dans(-1))}` } })).status, 403)

    // Le lien privé dépose le cookie, puis renvoie vers l'adresse normale de l'application.
    let reponse = await fetch(`${apercu.hote}/k/${apercu.jeton}/espace?x=1`, { redirect: 'manual' })
    assert.equal(reponse.status, 302)
    assert.equal(reponse.headers.get('location'), '/espace?x=1')
    assert.match(reponse.headers.get('set-cookie'), new RegExp(`^${COOKIE_APERCU}=${apercu.jeton.replace('.', '\\.')}; Path=/; HttpOnly; Secure; SameSite=None; Partitioned$`))

    // Tant que l'application n'est pas installée : page d'attente qui se recharge.
    reponse = await fetch(apercu.hote + '/', { headers: { cookie: apercu.cookie } })
    assert.equal(reponse.status, 503)
    assert.match(await reponse.text(), /Votre site s’installe/)

    // L'application est installée : le serveur la lance et la montre.
    const port = await portLibre()
    await mkdir(path.dirname(apercu.reglages), { recursive: true })
    await writeFile(apercu.reglages, JSON.stringify({ commande: 'node app.mjs', port, env: { PORT: String(port) } }))
    reponse = await quandPrete(apercu.hote + '/espace', { headers: { cookie: `${apercu.cookie}; session=abc` } })
    assert.equal(reponse.status, 200)
    assert.equal(reponse.headers.get('set-cookie'), 'session=abc; Path=/; HttpOnly; SameSite=None; Secure; Partitioned')
    const page = await reponse.text()
    assert.match(page, /<h1>Application<\/h1>/)
    assert.match(page, /data-kekeli-apercu/)
    const recu = JSON.parse(page.match(/<pre>(.*)<\/pre>/)[1].replace(/&quot;/g, '"'))
    assert.equal(recu.chemin, '/espace')
    assert.equal(recu.cookie, 'session=abc')
    assert.equal(recu.proto, 'https')

    // Les formulaires sont transmis tels quels.
    reponse = await fetch(apercu.hote + '/espace/reservations', { method: 'POST', body: 'date=2027-01-10', headers: { cookie: apercu.cookie, 'content-type': 'application/x-www-form-urlencoded' } })
    const envoi = JSON.parse((await reponse.text()).match(/<pre>(.*)<\/pre>/)[1])
    assert.equal(envoi.methode, 'POST')
    assert.equal(envoi.corps, 'date=2027-01-10')

    // L'application s'arrête : elle est relancée toute seule.
    await fetch(apercu.hote + '/arret', { headers: { cookie: apercu.cookie } })
    await attendre(300)
    reponse = await quandPrete(apercu.hote + '/', { headers: { cookie: apercu.cookie } })
    assert.equal(reponse.status, 200)
  } finally {
    await apercu.arreter()
  }
})

test('une erreur du site n’est jamais montrée en anglais : « Votre site redémarre » juste après un lancement, sinon un message clair', async () => {
  for (const [duree, attendu] of [
    [60000, { code: 503, texte: /Votre site redémarre/, rechargement: true }],
    [0, { code: 500, texte: /Cette page a rencontré un problème/, rechargement: false }],
  ]) {
    const apercu = await preparer({ dureeRedemarrageMs: duree })
    try {
      const port = await portLibre()
      await mkdir(path.dirname(apercu.reglages), { recursive: true })
      await writeFile(apercu.reglages, JSON.stringify({ commande: 'node app.mjs', port, env: { PORT: String(port) } }))
      await quandPrete(apercu.hote + '/', { headers: { cookie: apercu.cookie } })
      const reponse = await fetch(apercu.hote + '/panne', { headers: { cookie: apercu.cookie } })
      const page = await reponse.text()
      assert.equal(reponse.status, attendu.code)
      assert.match(page, attendu.texte)
      assert.equal(/http-equiv="refresh"/.test(page), attendu.rechargement)
      assert.doesNotMatch(page, /Internal Server Error|QueryException|application/i)
      assert.match(page, /lang="fr"/)
      // Une page qui marche reste montrée telle quelle.
      assert.equal((await fetch(apercu.hote + '/', { headers: { cookie: apercu.cookie } })).status, 200)
    } finally {
      await apercu.arreter()
    }
  }
})
