import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execSync } from 'node:child_process'
import { mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { executerTache, phraseDeTravail, sansMotApplication } from '../src/agent.js'
import { CONSIGNES_AGENT, CONSIGNES_DEVELOPPEUR } from '../src/ordinateurs.js'
import { HISTORIQUE, lireFichiers, modifications, photographier } from '../src/modifications.js'
import { MisesDeCote } from '../src/limites.js'
import { fauxOpencode, finRequete, libre, occupe, outil } from './outils.js'

const GEMINI = { fournisseur: 'gemini', modele: 'gemini-3.5-flash-lite' }
const sid = 'ses_test'
const texte = (id, t, messageID = 'msg_agent') => ({ type: 'message.part.updated', properties: { part: { id, sessionID: sid, messageID, type: 'text', text: t, time: { start: 1, end: 2 } } } })
const texteEnCours = (id, t) => ({ type: 'message.part.updated', properties: { part: { id, sessionID: sid, messageID: 'msg_agent', type: 'text', text: t, time: { start: 1 } } } })
const demandeUtilisateur = { type: 'message.updated', properties: { info: { id: 'msg_client', role: 'user', sessionID: sid } } }

test('les consignes demandent une phrase simple avant chaque action et un message final court, sans jargon', () => {
  for (const consignes of [CONSIGNES_AGENT, CONSIGNES_DEVELOPPEUR]) {
    assert.match(consignes, /UNE phrase courte \(15 mots au plus\)/)
    assert.match(consignes, /Quand quelque chose échoue, dis-le franchement/)
    assert.match(consignes, /## Message final au client/)
    assert.match(consignes, /deux ou trois phrases au plus/)
    assert.match(consignes, /Jamais de titre, de liste, de nom de fichier, de commande, de code d'état/)
    assert.match(consignes, /demande-le poliment en une phrase/)
    // Plus de compte rendu en cinq parties, ni de rubrique « À vérifier de votre côté » à l'impératif.
    assert.doesNotMatch(consignes, /## Fichiers modifiés|## À vérifier de votre côté/)
  }
})

test('une phrase de travail est la première ligne, sans mise en forme', () => {
  assert.equal(phraseDeTravail('**Je regarde** comment la page `index.html` est construite.\n\nDétails…'), 'Je regarde comment la page index.html est construite.')
  assert.equal(phraseDeTravail('## J\'ajoute le menu'), 'J\'ajoute le menu')
  // Un titre du compte rendu final n'est jamais affiché comme une phrase de travail.
  assert.equal(phraseDeTravail('## Ce que j\'ai fait'), null)
  assert.equal(phraseDeTravail('Ce que j’ai fait :'), null)
  assert.equal(phraseDeTravail('Je crée la page '.repeat(30)).length, 280)
})

test('les phrases de l’agent s’affichent avant leurs actions ; la dernière (la réponse) n’est pas une phrase de travail', async () => {
  const faux = fauxOpencode({
    [GEMINI.modele]: [
      occupe,
      demandeUtilisateur,
      texte('t0', 'Ajoute un bouton WhatsApp', 'msg_client'),
      texteEnCours('t1', 'Je regarde'),
      texte('t1', 'Je regarde comment la page d’accueil est construite.'),
      outil('read', { filePath: '/home/user/projet/index.html' }, 'o1'),
      finRequete('r1'),
      texte('t2', 'J’ajoute le bouton WhatsApp en bas de la page.'),
      outil('edit', { filePath: '/home/user/projet/index.html' }, 'o2'),
      finRequete('r2'),
      texte('t3', '## Ce que j’ai fait\nJ’ai ajouté le bouton.'),
      finRequete('r3'),
      libre,
    ],
  })
  const evenements = []
  await executerTache(
    { projet: 'p1', message: 'Ajoute un bouton WhatsApp', fournisseurs: [GEMINI] },
    { ordinateurs: faux.ordinateurs, envoyer: async (e) => evenements.push(e), misesDeCote: new MisesDeCote(), clesPresentes: new Set(['gemini']), journal: { error() {}, warn() {}, log() {} } },
  )
  const suite = evenements.filter((e) => e.type === 'narration' || e.type === 'etape').map((e) => (e.type === 'narration' ? `phrase : ${e.texte}` : `étape : ${e.genre}`))
  assert.deepEqual(suite, [
    'phrase : Je regarde comment la page d’accueil est construite.',
    'étape : fichier_lu',
    'phrase : J’ajoute le bouton WhatsApp en bas de la page.',
    'étape : fichier_modifie',
  ])
  assert.equal(evenements.at(-1).type, 'terminee')
})

test('« Voir les modifications » : photo avant, comparaison après, sans toucher au dépôt Git du client', async () => {
  assert.deepEqual(lireFichiers('3\t1\tindex.html\n-\t-\timages/accueil.jpg\n0\t5\tancien.html\n', 'M\tindex.html\nA\timages/accueil.jpg\nD\tancien.html\n'), [
    { chemin: 'index.html', etat: 'modifie', ajouts: 3, suppressions: 1, binaire: false },
    { chemin: 'images/accueil.jpg', etat: 'ajoute', ajouts: 0, suppressions: 0, binaire: true },
    { chemin: 'ancien.html', etat: 'supprime', ajouts: 0, suppressions: 5, binaire: false },
  ])

  // Vrai Git, dans un dossier temporaire (le dépôt de Kekeli est ici redirigé vers ce dossier).
  const racine = await mkdtemp(path.join(tmpdir(), 'kekeli-modifs-'))
  const dossier = path.join(racine, 'projet')
  const historique = path.join(racine, 'historique.git')
  const sandbox = {
    commands: {
      run: async (commande) => {
        try {
          return { exitCode: 0, stdout: execSync(commande.replaceAll(HISTORIQUE, historique), { shell: '/bin/bash', encoding: 'utf8' }) }
        } catch (erreur) {
          const e = new Error(erreur.message)
          e.result = { exitCode: 1, stdout: String(erreur.stdout ?? '') }
          throw e
        }
      },
    },
  }
  try {
    await mkdir(path.join(dossier, 'node_modules'), { recursive: true })
    await writeFile(path.join(dossier, 'index.html'), '<h1>Bonjour</h1>\n<p>Ancien texte</p>\n')
    await writeFile(path.join(dossier, 'ancien.html'), 'à retirer\n')
    await writeFile(path.join(dossier, 'node_modules/gros.js'), 'x')
    assert.equal(await modifications(sandbox, dossier), null, 'pas de comparaison sans photo')

    await photographier(sandbox, dossier)
    await writeFile(path.join(dossier, 'index.html'), '<h1>Bonjour</h1>\n<p>Nouveau texte</p>\n')
    await writeFile(path.join(dossier, 'contact.html'), '<h1>Contact</h1>\n')
    await rm(path.join(dossier, 'ancien.html'))
    await writeFile(path.join(dossier, 'node_modules/gros.js'), 'y')

    const resultat = await modifications(sandbox, dossier)
    assert.deepEqual(resultat.fichiers.map((f) => `${f.etat} ${f.chemin}`).sort(), ['ajoute contact.html', 'modifie index.html', 'supprime ancien.html'])
    assert.match(resultat.diff, /-<p>Ancien texte<\/p>\n\+<p>Nouveau texte<\/p>/)
    assert.equal(resultat.tronque, false)
    // Le projet n'a pas reçu de dossier .git.
    await assert.rejects(readFile(path.join(dossier, '.git/HEAD')))
  } finally {
    await rm(racine, { recursive: true, force: true })
  }
})

test('après une correction, une reprise ou un refus, le message final porte sur toute la demande', async () => {
  const { RAPPEL_COMPTE_RENDU, TEXTE_REPRISE, texteApresRefus, texteDeCorrection } = await import('../src/agent.js')
  assert.ok(TEXTE_REPRISE.includes(RAPPEL_COMPTE_RENDU))
  assert.ok(texteApresRefus('« rm »').includes(RAPPEL_COMPTE_RENDU))
  assert.ok(texteDeCorrection({ qualite: [{ genre: 'emoji', fichier: 'index.html', cible: '🍲' }] }).endsWith(RAPPEL_COMPTE_RENDU))
})

test('une réflexion de l’agent en anglais n’est jamais montrée au client', async () => {
  const { estEnFrancais, phraseDeTravail } = await import('../src/agent.js')
  assert.equal(phraseDeTravail("Let's check if LICENCES.md is intact."), null)
  assert.equal(phraseDeTravail('Everything is correct and meets all requirements.'), null)
  assert.equal(phraseDeTravail('Je vérifie que le fichier des licences est complet.'), 'Je vérifie que le fichier des licences est complet.')
  assert.equal(phraseDeTravail("J'ajoute la section livraison et je change la couleur principale en vert."), "J'ajoute la section livraison et je change la couleur principale en vert.")
  assert.ok(estEnFrancais('Les tests échouent à cause du bouton, je corrige.'))
})

test('le client lit « site », jamais « application », sans que les noms de fichiers changent', () => {
  assert.equal(sansMotApplication("Je mets à jour le nom de l'application."), 'Je mets à jour le nom du site.')
  assert.equal(sansMotApplication('L’application répond et une application web est prêt.'), 'Le site répond et un site web est prêt.')
  assert.equal(sansMotApplication("Je relance l'application."), 'Je relance le site.')
  assert.equal(sansMotApplication("J'ai modifié config/application.php et `config('application.nom')`."), "J'ai modifié config/application.php et `config('application.nom')`.")
  assert.equal(sansMotApplication('Fichier application.php modifié'), 'Fichier application.php modifié')
  assert.equal(sansMotApplication(null), null)
})
