import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fichiersApplication, modelesApplications, PILES } from '../src/applications/index.js'
import { verifierModele } from '../src/applications/commun.js'

const modeles = modelesApplications()

test('chaque modèle d’application se fabrique en Laravel et en Next.js', () => {
  assert.ok(modeles.some((m) => m.slug === 'socle'))
  assert.ok(modeles.filter((m) => m.galerie).length >= 4)
  for (const modele of modeles) {
    for (const pile of Object.keys(PILES)) {
      const fichiers = fichiersApplication(pile, modele.slug)
      assert.ok(Object.keys(fichiers).length > 30, `${pile} ${modele.slug}`)
      for (const [chemin, contenu] of Object.entries(fichiers)) {
        if (!/\.(php|js|mjs|json|ya?ml|md)$/.test(chemin) && !chemin.startsWith('.env')) continue
        const texte = contenu.toString()
        // Aucune valeur oubliée par la fabrique (« ${...} » laissé dans un fichier PHP ou JavaScript).
        if (/\.(php|js)$/.test(chemin) && !chemin.includes('migrations.js')) assert.doesNotMatch(texte, /\$\{(e|C|v|route|table|app)\b/, `${pile} ${modele.slug} ${chemin}`)
      }
    }
  }
})

test('les applications Laravel suivent les règles de sécurité', () => {
  const f = fichiersApplication('laravel', 'reservations-restaurant')
  const texte = (chemin) => f[chemin].toString()
  assert.match(texte('app/Models/User.php'), /'password' => 'hashed'/)
  assert.match(texte('app/Models/User.php'), /protected \$fillable = \['name', 'email', 'password'\];/)
  assert.doesNotMatch(texte('app/Models/Reservation.php'), /fillable = \[[^\]]*'(user_id|statut)'/)
  for (const [chemin, contenu] of Object.entries(f)) {
    if (!chemin.endsWith('.blade.php')) continue
    const formulaires = contenu.toString().match(/<form method="POST"/g)?.length ?? 0
    const jetons = contenu.toString().match(/@csrf/g)?.length ?? 0
    assert.equal(jetons, formulaires, `${chemin} : chaque formulaire a sa protection`)
  }
  assert.match(texte('app/Http/Controllers/ReservationController.php'), /abort_unless\(Gate::allows\('view', \$reservation\), 404\)/)
  assert.match(texte('tests/Feature/SeparationDesDonneesTest.php'), /assertNotFound/)
  assert.match(texte('.env.example'), /^APP_KEY=$/m)
  assert.match(texte('.env.example'), /^DB_PASSWORD=$/m)
  assert.match(texte('.gitignore'), /^\.env$/m)
  assert.ok(f['composer.lock'], 'versions exactes des paquets')
})

test('les applications Next.js suivent les règles de sécurité', () => {
  const f = fichiersApplication('nextjs', 'reservations-hotel')
  const texte = (chemin) => f[chemin].toString()
  assert.match(texte('lib/securite.js'), /bcrypt\.hash/)
  assert.match(texte('lib/sejours.js'), /WHERE id = \$1 AND user_id = \$2/)
  assert.match(texte('lib/sejours.js'), /d\.depart > d\.arrivee/)
  assert.match(texte('app/actions/sejours.js'), /^'use server'/)
  assert.match(texte('lib/session.js'), /httpOnly: true/)
  assert.match(texte('.gitignore'), /^\.env$/m)
  assert.match(texte('.env.example'), /^DATABASE_URL=$/m)
  const verrou = JSON.parse(texte('package-lock.json'))
  assert.equal(verrou.name, 'reservations-hotel')
  assert.equal(JSON.parse(texte('package.json')).dependencies.next, verrou.packages['node_modules/next'].version)
})

test('une description d’application mal formée ou qui prend un nom réservé est refusée', () => {
  const base = modeles.find((m) => m.slug === 'socle')
  assert.throws(() => verifierModele({ ...base, entite: { ...base.entite, classe: 'User' } }), /réservée/)
  assert.throws(() => verifierModele({ ...base, entite: { ...base.entite, table: 'users' } }), /réservée/)
  assert.throws(() => verifierModele({ ...base, entite: { ...base.entite, champs: [{ nom: 'statut', libelle: 'x', type: 'string' }] } }), /réservé/)
  assert.throws(() => verifierModele({ ...base, entite: { ...base.entite, champs: [{ nom: 'prix', libelle: 'Prix', type: 'argent' }] } }), /inconnu/)
  assert.throws(() => fichiersApplication('django', 'socle'), /inconnue/)
})
