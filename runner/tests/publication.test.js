import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { empreinte, fichiersDeLaSauvegarde, lots, publier, typeDeContenu } from '../src/publication.js'
import { creerServeur } from '../src/server.js'
import { entetesSignes } from '../src/signature.js'

const SECRET = 'secret-de-test-tres-long-pour-les-signatures-0123456789'
const silencieux = { log() {}, warn() {}, error() {} }

test('l’empreinte d’un fichier est calculée comme l’outil officiel de Cloudflare', () => {
  // blake3 du contenu en base 64 suivi de l'extension, 32 premiers caractères.
  assert.equal(empreinte('a.txt', Buffer.from('a')), 'bdb4c5362de6440eb0e07574e4c76153')
  assert.notEqual(empreinte('a.css', Buffer.from('a')), empreinte('a.txt', Buffer.from('a')))
  assert.equal(typeDeContenu('images/accueil.JPG'), 'image/jpeg')
  assert.equal(typeDeContenu('index.html'), 'text/html')
})

test('les gros fichiers sont envoyés en plusieurs lots', () => {
  const fichier = (taille) => ({ chemin: `f${taille}`, octets: Buffer.alloc(taille) })
  const resultat = lots([fichier(30 * 1024 * 1024), fichier(20 * 1024 * 1024), fichier(5 * 1024 * 1024), fichier(1024)])
  assert.equal(resultat.length, 2)
  assert.ok(resultat.every((lot) => lot.reduce((t, f) => t + f.octets.length, 0) <= 40 * 1024 * 1024))
})

test('la sauvegarde est ouverte sans les fichiers cachés ni les dossiers techniques', async () => {
  const dossier = await mkdtemp(path.join(tmpdir(), 'kekeli-sauvegarde-'))
  try {
    await mkdir(path.join(dossier, 'site/css'), { recursive: true })
    await mkdir(path.join(dossier, 'site/node_modules/x'), { recursive: true })
    await writeFile(path.join(dossier, 'site/index.html'), '<h1>Le Palmier</h1>')
    await writeFile(path.join(dossier, 'site/css/base.css'), 'body{}')
    await writeFile(path.join(dossier, 'site/.env'), 'SECRET=1')
    await writeFile(path.join(dossier, 'site/node_modules/x/a.js'), '1')
    execFileSync('tar', ['-czf', path.join(dossier, 's.tar.gz'), '-C', path.join(dossier, 'site'), '.'])
    const fichiers = await fichiersDeLaSauvegarde(await readFile(path.join(dossier, 's.tar.gz')))
    assert.deepEqual(fichiers.map((f) => f.chemin).sort(), ['css/base.css', 'index.html'])
  } finally {
    await rm(dossier, { recursive: true, force: true })
  }
})

test('publier : projet créé, seuls les fichiers manquants envoyés, puis déploiement avec la liste des fichiers', async () => {
  const appels = []
  const telecharger = async (adresse, options) => {
    const chemin = adresse.replace('https://api.cloudflare.com/client/v4', '')
    appels.push({ chemin, methode: options.method, cle: options.headers.Authorization, corps: options.body })
    const reponse = (resultat, statut = 200) => new Response(JSON.stringify({ success: statut < 400, result: resultat, errors: statut < 400 ? [] : [{ message: 'Project not found' }] }), { status: statut })
    if (chemin === '/accounts/compte/pages/projects/le-palmier-abc123' && options.method === 'GET') return reponse(null, 404)
    if (chemin === '/accounts/compte/pages/projects' && options.method === 'POST') return reponse({ name: 'le-palmier-abc123', subdomain: 'le-palmier-abc123.pages.dev' })
    if (chemin.endsWith('/upload-token')) return reponse({ jwt: 'jeton-d-envoi' })
    if (chemin === '/pages/assets/check-missing') return reponse([empreinte('index.html', Buffer.from('<h1>Bonjour</h1>'))])
    if (chemin === '/pages/assets/upload' || chemin === '/pages/assets/upsert-hashes') return reponse(true)
    if (chemin.endsWith('/deployments')) return reponse({ url: 'https://1234abcd.le-palmier-abc123.pages.dev' })
    return reponse(null, 500)
  }
  const fichiers = [
    { chemin: 'index.html', octets: Buffer.from('<h1>Bonjour</h1>') },
    { chemin: 'css/base.css', octets: Buffer.from('body{}') },
  ]
  const resultat = await publier({ fichiers, nom: 'le-palmier-abc123', compte: 'compte', jeton: 'cle-cloudflare', telecharger })

  assert.deepEqual(resultat, { adresse: 'https://le-palmier-abc123.pages.dev', deploiement: 'https://1234abcd.le-palmier-abc123.pages.dev', fichiers: 2, envoyes: 1 })
  // La clé Cloudflare sert au projet et au déploiement ; le jeton d'envoi, aux fichiers.
  assert.equal(appels.find((a) => a.chemin.endsWith('/upload-token')).cle, 'Bearer cle-cloudflare')
  assert.equal(appels.find((a) => a.chemin === '/pages/assets/upload').cle, 'Bearer jeton-d-envoi')
  const envoi = JSON.parse(appels.find((a) => a.chemin === '/pages/assets/upload').corps)
  assert.deepEqual(envoi.map((f) => f.metadata.contentType), ['text/html'])
  assert.equal(Buffer.from(envoi[0].value, 'base64').toString(), '<h1>Bonjour</h1>')
  const deploiement = appels.find((a) => a.chemin.endsWith('/deployments'))
  assert.equal(deploiement.cle, 'Bearer cle-cloudflare')
  assert.deepEqual(Object.keys(JSON.parse(deploiement.corps.get('manifest'))).sort(), ['/css/base.css', '/index.html'])
  assert.equal(deploiement.corps.get('branch'), 'main')

  await assert.rejects(publier({ fichiers: fichiers.slice(1), nom: 'x', compte: 'c', jeton: 'j', telecharger }), /page d'accueil/)
})

test('le serveur publie seulement sur demande signée, et seulement si Cloudflare est configuré', async () => {
  const publications = []
  const demarrer = async (clesPresentes) => {
    const serveur = creerServeur({
      secret: SECRET,
      clesPresentes,
      ordinateurs: {},
      publierProjet: async (d) => {
        publications.push(d)
        return { adresse: 'https://le-palmier-abc123.pages.dev', fichiers: 3 }
      },
      journal: silencieux,
    })
    await new Promise((r) => serveur.listen(0, '127.0.0.1', r))
    return serveur
  }
  const poster = (serveur, donnees, signe = true) => {
    const corps = JSON.stringify(donnees)
    return fetch(`http://127.0.0.1:${serveur.address().port}/publier`, { method: 'POST', body: corps, headers: signe ? entetesSignes(SECRET, corps) : {} })
  }
  const demande = { projet: '01JABC', nom: 'le-palmier-abc123', sauvegarde: 'http://site/runner/projets/01JABC/sauvegarde' }

  const sansCloudflare = await demarrer(new Set(['gemini']))
  try {
    assert.equal((await poster(sansCloudflare, demande)).status, 503)
  } finally {
    sansCloudflare.close()
  }

  const serveur = await demarrer(new Set(['gemini', 'cloudflare']))
  try {
    assert.equal((await poster(serveur, demande, false)).status, 401)
    assert.equal((await poster(serveur, { ...demande, nom: 'Nom Invalide' })).status, 422)
    const reponse = await poster(serveur, demande)
    assert.equal(reponse.status, 200)
    assert.deepEqual(await reponse.json(), { ok: true, adresse: 'https://le-palmier-abc123.pages.dev', fichiers: 3 })
    assert.deepEqual(publications, [demande])
  } finally {
    serveur.close()
  }
})
