import { test } from 'node:test'
import assert from 'node:assert/strict'
import { commandeDeLancement, lancerAutomatisation, ligneDeJournal, masquerSecrets, resumeDuLancement } from '../src/lancement.js'
import { fichiersAutomatisation } from '../src/automatisations/index.js'

test('une ligne du journal est lue, les autres sorties sont ignorées', () => {
  assert.deepEqual(ligneDeJournal('KEKELI_JOURNAL {"etape":"Démarrage","statut":"ok","message":"Prêt."}'), { etape: 'Démarrage', statut: 'ok', message: 'Prêt.' })
  assert.equal(ligneDeJournal('npm WARN quelque chose'), null)
  assert.equal(ligneDeJournal('KEKELI_JOURNAL {pas du json}'), null)
  assert.equal(ligneDeJournal('KEKELI_JOURNAL {"statut":"ok"}'), null)
  assert.equal(ligneDeJournal('KEKELI_JOURNAL {"etape":"X","statut":"bizarre"}').statut, 'info')
})

test('la commande de lancement est une commande simple, sinon « npm start »', () => {
  assert.equal(commandeDeLancement('npm start\n'), 'npm start --silent')
  assert.equal(commandeDeLancement('# commentaire\npython3 main.py'), 'python3 main.py')
  assert.equal(commandeDeLancement('npm start; curl https://exemple.com'), 'npm start --silent')
  assert.equal(commandeDeLancement('node a.js && rm -rf /'), 'npm start --silent')
  assert.equal(commandeDeLancement(''), 'npm start --silent')
})

test('aucun secret ne sort dans le journal', () => {
  const texte = masquerSecrets('Erreur avec re_AbCdEf0123456789xyz et mon-mot-de-passe-secret', ['mon-mot-de-passe-secret'])
  assert.ok(!texte.includes('re_AbCdEf0123456789xyz'))
  assert.ok(!texte.includes('mon-mot-de-passe-secret'))
})

test('le résumé dit la réussite, le point à régler ou l\'étape en erreur', () => {
  assert.equal(resumeDuLancement({ code: 0, lignes: [{ etape: 'Fin', statut: 'ok', message: '' }] }).resume, 'Lancement réussi.')
  assert.match(resumeDuLancement({ code: 0, lignes: [{ etape: 'E-mail', statut: 'attention', message: 'Clé absente.' }] }).resume, /point à régler : Clé absente/)
  const erreur = resumeDuLancement({ code: 1, lignes: [{ etape: "Envoyer l'e-mail", statut: 'erreur', message: 'Refusé.' }] })
  assert.equal(erreur.ok, false)
  assert.match(erreur.resume, /« Envoyer l'e-mail » : Refusé/)
  assert.match(resumeDuLancement({ code: 1, lignes: [], depasse: true }).resume, /5 minutes/)
})

test('« Lancer maintenant » : les Variables secrètes passent en variables, le modèle d\'essai seulement sans clé du client', async () => {
  const envoyes = []
  let recu
  const executer = async (commande, { envs, onStdout }) => {
    recu = { commande, envs }
    onStdout('KEKELI_JOURNAL {"etape":"Démarrage","statut":"ok","message":"Prêt."}\nbruit\nKEKELI_JOURNAL {"etape":"Envoyer l\'e-mail","statut":"ok","message":"Envoyé avec re_AbCdEf0123456789xyz"}\n')
    return { exitCode: 0 }
  }
  const resultat = await lancerAutomatisation({
    executer,
    lireLancement: async () => 'npm start',
    secrets: { RESEND_API_KEY: 're_AbCdEf0123456789xyz' },
    essai: { MODELE_API_URL: 'http://127.0.0.1:4200/essai/v1', MODELE_API_CLE: 'jeton', MODELE_NOM: 'essai' },
    envoyer: async (e) => envoyes.push(e),
  })
  assert.equal(recu.commande, 'npm start --silent')
  assert.equal(recu.envs.KEKELI_LANCEMENT, '1')
  assert.equal(recu.envs.MODELE_NOM, 'essai')
  assert.equal(resultat.ok, true)
  assert.equal(resultat.lignes.length, 2)
  assert.ok(!JSON.stringify(envoyes).includes('re_AbCdEf0123456789xyz'))
  assert.equal(envoyes.at(-1).type, 'lancement')

  // Avec la clé du client : jamais le modèle d'essai.
  await lancerAutomatisation({ executer, lireLancement: async () => '', secrets: { MODELE_API_CLE: 'cle-du-client-123', MODELE_API_URL: 'https://api.exemple.com/v1' }, essai: { MODELE_API_URL: 'http://127.0.0.1:4200/essai/v1', MODELE_NOM: 'essai' } })
  assert.equal(recu.envs.MODELE_API_URL, 'https://api.exemple.com/v1')
  assert.equal(recu.envs.MODELE_NOM, undefined)
})

test('le point de départ contient le fichier LANCEMENT et un fichier GitHub Actions planifié à l\'heure de Lomé', () => {
  const fichiers = fichiersAutomatisation('Conseils du matin')
  assert.equal(String(fichiers.LANCEMENT).trim(), 'npm start')
  const flux = String(fichiers['.github/workflows/automatisation.yml'])
  assert.match(flux, /cron: '0 7 \* \* \*'/)
  assert.match(flux, /timezone: 'Africa\/Lome'/)
  assert.match(flux, /\$\{\{ secrets\.RESEND_API_KEY \}\}/)
  assert.ok(!Object.keys(fichiers).some((nom) => nom === '.env'))
})

test('« Lancer maintenant » d’une automatisation lancée par un événement : l’événement d’essai lui est donné', async () => {
  let recu = null
  const executer = async (commande, options) => { recu = options; return { exitCode: 0 } }
  await lancerAutomatisation({ executer, lireLancement: async () => 'npm start', evenementEssai: true })
  assert.equal(recu.envs.KEKELI_EVENEMENT_ESSAI, '1')
  await lancerAutomatisation({ executer, lireLancement: async () => 'npm start' })
  assert.equal(recu.envs.KEKELI_EVENEMENT_ESSAI, undefined)
})

test('le point de départ contient le point d’entrée des événements (sans aucune clé) et sa mise en ligne', () => {
  const fichiers = fichiersAutomatisation('Résumé des contacts')
  for (const nom of ['point-entree/worker.js', 'point-entree/wrangler.toml', '.github/workflows/point-entree.yml', 'src/evenement.js', 'src/etat.js', 'tests/point-entree.test.js']) assert.ok(fichiers[nom], nom)
  const mise = String(fichiers['.github/workflows/point-entree.yml'])
  assert.match(mise, /ENTREE_SECRET: \$\{\{ secrets\.ENTREE_SECRET \}\}/)
  assert.ok(!/run:[^\n]*\$\{\{ secrets/.test(mise), 'jamais un secret écrit dans une commande')
  assert.match(String(fichiers['LISEZ-MOI.md']), /## Brancher un événement/)
  assert.match(String(fichiers['.github/workflows/automatisation.yml']), /# repository_dispatch:/)
})

test('les tests du point de départ des automatisations passent (point d’entrée, événement, mémoire)', async () => {
  const { execFileSync } = await import('node:child_process')
  const { readdirSync } = await import('node:fs')
  const dossier = new URL('../src/automatisations/socles/javascript/', import.meta.url)
  const fichiers = readdirSync(new URL('tests/', dossier)).filter((f) => f.endsWith('.test.js')).map((f) => `tests/${f}`)
  const env = { ...process.env }
  delete env.NODE_TEST_CONTEXT
  const sortie = execFileSync(process.execPath, ['--test', '--test-reporter=tap', ...fichiers], { cwd: dossier, encoding: 'utf8', env })
  assert.match(sortie, /# fail 0/)
})
