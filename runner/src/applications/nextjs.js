// Fabrique d'une application web Next.js à partir d'une description (runner/src/applications/modeles.json).
// Même application que la version Laravel : comptes, formulaire enregistré, administration, tests.
//
// Base de données : PostgreSQL partout. Dans l'ordinateur de Kekeli et dans les tests, PGlite (le vrai
// PostgreSQL, qui tourne dans Node sans rien installer) ; en ligne, la base PostgreSQL du client
// (DATABASE_URL). Requêtes SQL toujours avec des paramètres (jamais de texte collé dans une requête).
// Sécurité : mots de passe chiffrés (bcrypt), formulaires protégés (actions serveur de Next.js, qui
// vérifient l'origine de chaque envoi), essais de connexion limités, données de chaque utilisateur
// séparées, aucune clé ni aucun mot de passe dans le code.
import { FEUILLE_DE_STYLE, ICONES, accord, aucun, constanteOptions, exemple, le, majuscule, mes, tous, un, verifierModele } from './commun.js'

/** Versions fixées des paquets (mises à jour volontairement, après essai). */
export const VERSIONS_NEXTJS = {
  next: '15.5.29',
  react: '19.3.1',
  'react-dom': '19.3.1',
  '@electric-sql/pglite': '0.5.16',
  pg: '8.23.1',
  bcryptjs: '3.0.3',
  zod: '4.6.7',
  nodemailer: '10.4.0',
  vitest: '5.1.0',
}

const js = String.raw
const chaine = (texte) => JSON.stringify(String(texte))

const icone = (nom) =>
  `<svg className="icone" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">${ICONES[nom].replace(/stroke-width/g, 'strokeWidth')}</svg>`

/** Règle de validation zod d'un champ. */
function regle(champ) {
  const obligatoire = "'Ce champ est obligatoire.'"
  const texte = (max) => (champ.requis ? `z.string().trim().min(1, ${obligatoire}).max(${max}, 'Ce texte est trop long.')` : `z.string().trim().max(${max}, 'Ce texte est trop long.')`)
  const motif = (re, message) => `${texte(255)}.refine((v) => v === '' || ${re}.test(v), ${chaine(message)})`
  switch (champ.type) {
    case 'string':
      return texte(255)
    case 'text':
      return texte(5000)
    case 'tel':
      return motif('/^[0-9+ ().-]{6,30}$/', 'Numéro de téléphone invalide.')
    case 'email':
      return motif('/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/', 'Adresse électronique invalide.')
    case 'time':
      return motif('/^([01]\\d|2[0-3]):[0-5]\\d$/', 'Heure invalide.')
    case 'date': {
      let r = motif('/^\\d{4}-\\d{2}-\\d{2}$/', 'Date invalide.')
      if (champ.contrainte === 'futur') r += ".refine((v) => v === '' || v >= aujourdhui(), 'La date doit être aujourd’hui ou plus tard.')"
      if (champ.contrainte === 'passe') r += ".refine((v) => v === '' || v < aujourdhui(), 'La date doit être passée.')"
      return r
    }
    case 'integer': {
      const bornes = `${champ.min !== undefined ? `.min(${champ.min}, 'Au moins ${champ.min}.')` : ''}${champ.max !== undefined ? `.max(${champ.max}, 'Au plus ${champ.max}.')` : ''}`
      const nombre = `z.coerce.number({ error: 'Nombre invalide.' }).int('Nombre entier attendu.')${bornes}`
      return champ.requis ? `z.string().trim().min(1, ${obligatoire}).pipe(${nombre})` : `z.union([z.literal(''), z.string().trim().pipe(${nombre})])`
    }
    case 'select':
      return champ.requis ? `z.enum(${constanteOptions(champ)}, 'Choisissez une valeur de la liste.')` : `z.union([z.literal(''), z.enum(${constanteOptions(champ)}, 'Choisissez une valeur de la liste.')])`
  }
}

/** Description d'un champ pour le formulaire (composant client). */
function champFormulaire(champ) {
  const type = { string: 'text', text: 'textarea', tel: 'tel', email: 'email', date: 'date', time: 'time', integer: 'number', select: 'select' }[champ.type]
  const description = { nom: champ.nom, libelle: champ.requis ? champ.libelle : `${champ.libelle} (facultatif)`, type, requis: Boolean(champ.requis) }
  if (champ.type === 'integer') Object.assign(description, { min: champ.min, max: champ.max })
  if (champ.type === 'tel') description.autocomplete = 'tel'
  if (champ.type === 'email') description.autocomplete = 'email'
  return description
}

/** Affichage d'une valeur dans une page (JSX). */
function affichage(variable, champ) {
  if (champ.type === 'date') return `{dateFr(${variable}.${champ.nom})}`
  if (champ.type === 'text') return `<span className="texte-long">{${variable}.${champ.nom}}</span>`
  return `{${variable}.${champ.nom}}`
}

/** Valeur d'exemple pour les tests et les données d'essai. */
function valeurExemple(champ) {
  if (champ.type === 'date') {
    if (champ.contrainte === 'futur') return 'dansJours(7)'
    if (champ.contrainte === 'passe') return "'2018-05-14'"
    if (champ.contrainte?.startsWith('apres:')) return 'dansJours(10)'
    return chaine(exemple(champ))
  }
  return chaine(exemple(champ))
}

export function fichiersNextjs(modele) {
  verifierModele(modele)
  const app = modele.application
  const e = modele.entite
  const champs = e.champs
  const statuts = e.statuts
  const route = e.chemin
  const table = e.table
  const options = champs.filter((c) => c.type === 'select')
  const apres = champs.filter((c) => c.contrainte?.startsWith('apres:'))
  const colonnes = champs.map((c) => c.nom)
  const premierStatut = statuts[0].code
  const f = {}

  // ----------------------------------------------------------------------------------------------
  // Configuration
  f['package.json'] =
    JSON.stringify(
      {
        name: modele.slug,
        private: true,
        type: 'module',
        scripts: {
          dev: 'next dev',
          build: 'next build',
          start: 'next start',
          test: 'vitest run',
          'donnees-essai': 'node scripts/donnees-essai.mjs',
          admin: 'node scripts/admin.mjs',
        },
        dependencies: Object.fromEntries(['next', 'react', 'react-dom', '@electric-sql/pglite', 'pg', 'bcryptjs', 'zod', 'nodemailer'].map((p) => [p, VERSIONS_NEXTJS[p]])),
        devDependencies: { vitest: VERSIONS_NEXTJS.vitest },
        engines: { node: '>=20.9' },
      },
      null,
      2,
    ) + '\n'

  f['next.config.mjs'] = js`/** @type {import('next').NextConfig} */
const configuration = {
  poweredByHeader: false,
  // Pas de pastille de développement dans l'aperçu.
  devIndicators: false,
  // Ces bibliothèques de base de données restent côté serveur, telles quelles.
  serverExternalPackages: ['@electric-sql/pglite', 'pg'],
  async headers() {
    return [
      {
        source: '/(.*)',
        headers: [
          { key: 'X-Content-Type-Options', value: 'nosniff' },
          { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
          { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
        ],
      },
    ]
  },
}

export default configuration
`

  f['jsconfig.json'] = JSON.stringify({ compilerOptions: { baseUrl: '.', paths: { '@/*': ['./*'] } } }, null, 2) + '\n'

  f['vitest.config.mjs'] = js`import { defineConfig } from 'vitest/config'
import path from 'node:path'

export default defineConfig({
  resolve: { alias: { '@': path.resolve(import.meta.dirname) } },
  test: {
    environment: 'node',
    // Chaque fichier de tests a sa propre base PostgreSQL en mémoire (PGlite).
    env: { PGLITE_DOSSIER: 'memory://', ADMIN_EMAIL: 'patron@exemple.test' },
    testTimeout: 30000,
  },
})
`

  f['.env.example'] = `# Réglages de l'application. Copier ce fichier en « .env » et le remplir.
# Ne jamais envoyer le fichier .env sur GitHub : il contient des secrets.

# Nom affiché de l'application.
NEXT_PUBLIC_APP_NAME="${app.nom}"
# Adresse publique du site (pour les liens envoyés par courriel), par exemple https://mon-site.vercel.app
APP_URL=http://localhost:3000

# Base PostgreSQL en ligne (Neon, Render, Supabase…). Vide : base locale PGlite dans le dossier .donnees.
DATABASE_URL=

# Adresse électronique de l'administrateur : le compte créé avec cette adresse devient administrateur.
ADMIN_EMAIL=

# Envoi des courriels (mot de passe oublié) : réglages donnés par le service de courriels.
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASSWORD=
SMTP_FROM=
`

  f['.gitignore'] = `node_modules
.next
out
.env
.env*.local
.donnees
*.log
.vercel
`

  f['app/globals.css'] = FEUILLE_DE_STYLE + `.texte-long { white-space: pre-line; }\n`

  // ----------------------------------------------------------------------------------------------
  // Base de données
  f['lib/config.js'] = js`// Nom de l'application et rôle d'administrateur (variables d'environnement, jamais dans le code).
export const NOM = process.env.NEXT_PUBLIC_APP_NAME || ${chaine(app.nom)}
export const ACCROCHE = ${chaine(app.accroche)}

export function estAdresseAdmin(email) {
  const admin = (process.env.ADMIN_EMAIL || '').trim().toLowerCase()
  return admin !== '' && admin === String(email).trim().toLowerCase()
}
`

  f['lib/db.js'] = js`// Base de données PostgreSQL : la base du client en ligne (DATABASE_URL), sinon PGlite (PostgreSQL
// dans Node) pour essayer l'application et pour les tests. Les tables sont créées au premier usage.
import { migrer } from './migrations.js'

// Une seule base par serveur (gardée pendant les rechargements de Next.js) ; une par fichier de tests.
const global = process.env.VITEST ? {} : globalThis

async function ouvrir() {
  if (process.env.DATABASE_URL) {
    const { default: pg } = await import('pg')
    const pool = new pg.Pool({
      connectionString: process.env.DATABASE_URL,
      max: 5,
      ssl: /sslmode=disable/.test(process.env.DATABASE_URL) || /localhost|127\.0\.0\.1/.test(process.env.DATABASE_URL) ? false : { rejectUnauthorized: false },
    })
    return { query: (texte, valeurs) => pool.query(texte, valeurs), exec: (sql) => pool.query(sql) }
  }
  const { PGlite } = await import('@electric-sql/pglite')
  const dossier = process.env.PGLITE_DOSSIER || './.donnees/base'
  if (!dossier.includes('://')) (await import('node:fs')).mkdirSync(dossier, { recursive: true })
  const base = new PGlite(dossier)
  await base.waitReady
  return { query: (texte, valeurs) => base.query(texte, valeurs), exec: (sql) => base.exec(sql) }
}

/** La base, ouverte une seule fois (et ses tables créées ou mises à jour). */
export function base() {
  global.__base ??= ouvrir().then(async (b) => {
    await migrer(b)
    return b
  })
  return global.__base
}

/** Lignes renvoyées par une requête SQL avec paramètres ($1, $2…). */
export async function requete(texte, valeurs = []) {
  const resultat = await (await base()).query(texte, valeurs)
  return resultat.rows
}

/** Première ligne d'une requête, ou null. */
export async function une(texte, valeurs = []) {
  return (await requete(texte, valeurs))[0] ?? null
}
`

  const colonneSql = (c) => `  ${c.nom} ${c.type === 'integer' ? 'INTEGER' : 'TEXT'}${c.requis ? ' NOT NULL' : ''}`

  f['lib/migrations.js'] = js`// Tables de la base de données, dans l'ordre. Pour changer la base, AJOUTER une étape à la fin
// (ne jamais modifier une étape déjà appliquée) : elle sera appliquée au prochain démarrage.
export const MIGRATIONS = [
  {
    nom: '0001_comptes',
    sql: ${'`'}
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  is_admin BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE sessions (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE password_resets (
  id TEXT PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE login_attempts (
  key TEXT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX login_attempts_key ON login_attempts (key, created_at);
${'`'},
  },
  {
    nom: '0002_${table}',
    sql: ${'`'}
CREATE TABLE ${table} (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
${champs.map(colonneSql).join(',\n')},
  statut TEXT NOT NULL DEFAULT '${premierStatut}',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ${table}_user_id ON ${table} (user_id);
CREATE INDEX ${table}_statut ON ${table} (statut);
${'`'},
  },
]

/** Applique les étapes qui ne l'ont pas encore été. */
export async function migrer(base) {
  await base.exec('CREATE TABLE IF NOT EXISTS migrations (nom TEXT PRIMARY KEY, appliquee_le TIMESTAMPTZ NOT NULL DEFAULT now())')
  const faites = new Set((await base.query('SELECT nom FROM migrations')).rows.map((ligne) => ligne.nom))
  for (const etape of MIGRATIONS) {
    if (faites.has(etape.nom)) continue
    await base.exec('BEGIN')
    try {
      await base.exec(etape.sql)
      await base.query('INSERT INTO migrations (nom) VALUES ($1)', [etape.nom])
      await base.exec('COMMIT')
    } catch (erreur) {
      await base.exec('ROLLBACK')
      throw erreur
    }
  }
}
`

  // ----------------------------------------------------------------------------------------------
  // Comptes
  f['lib/securite.js'] = js`import bcrypt from 'bcryptjs'
import { createHash, randomBytes } from 'node:crypto'

/** Mot de passe chiffré (bcrypt) : jamais enregistré en clair. */
export function chiffrer(motDePasse) {
  return bcrypt.hash(motDePasse, process.env.VITEST ? 4 : 12)
}

export function verifier(motDePasse, chiffre) {
  return bcrypt.compare(motDePasse, chiffre)
}

/** Jeton tiré au hasard (pour la session ou le lien « mot de passe oublié »). */
export function jeton() {
  return randomBytes(32).toString('base64url')
}

/** Empreinte d'un jeton : seule l'empreinte est enregistrée dans la base. */
export function empreinte(valeur) {
  return createHash('sha256').update(valeur).digest('hex')
}
`

  f['lib/comptes.js'] = js`// Comptes : création, connexion (essais limités), sessions et mot de passe oublié.
// Ces fonctions ne touchent pas aux cookies : c'est le rôle de lib/session.js.
import { z } from 'zod'
import { estAdresseAdmin } from './config.js'
import { requete, une } from './db.js'
import { chiffrer, empreinte, jeton, verifier } from './securite.js'

export const DUREE_SESSION_JOURS = 30
const ESSAIS_PAR_MINUTE = 5

const schemaInscription = z
  .object({
    name: z.string().trim().min(1, 'Ce champ est obligatoire.').max(255, 'Ce texte est trop long.'),
    email: z.string().trim().toLowerCase().max(255).regex(/^[^\s@]+@[^\s@]+\.[^\s@]+$/, 'Adresse électronique invalide.'),
    password: z.string().min(8, 'Au moins 8 caractères.').max(200),
    password_confirmation: z.string(),
  })
  .refine((d) => d.password === d.password_confirmation, { path: ['password_confirmation'], message: 'Les deux mots de passe sont différents.' })

/** Erreurs de validation zod, par champ (la première de chaque champ). */
export function erreursParChamp(erreur) {
  const erreurs = {}
  for (const probleme of erreur.issues) {
    const champ = String(probleme.path[0] ?? 'formulaire')
    erreurs[champ] ??= probleme.message
  }
  return erreurs
}

/** Crée un compte. Le rôle d'administrateur ne vient jamais du formulaire, seulement de ADMIN_EMAIL. */
export async function inscrire(donnees) {
  const resultat = schemaInscription.safeParse(donnees)
  if (!resultat.success) return { erreurs: erreursParChamp(resultat.error) }
  const { name, email, password } = resultat.data
  if (await une('SELECT id FROM users WHERE email = $1', [email])) return { erreurs: { email: 'Un compte existe déjà avec cette adresse.' } }
  const user = await une('INSERT INTO users (name, email, password, is_admin) VALUES ($1, $2, $3, $4) RETURNING id, name, email, is_admin', [
    name,
    email,
    await chiffrer(password),
    estAdresseAdmin(email),
  ])
  return { user }
}

/** Vérifie une connexion ; cinq essais par minute au plus pour une même adresse et un même appareil. */
export async function verifierConnexion(email, motDePasse, appareil = '') {
  const adresse = String(email ?? '').trim().toLowerCase()
  const cle = adresse + '|' + appareil
  const recents = await une("SELECT count(*)::int AS total FROM login_attempts WHERE key = $1 AND created_at > now() - interval '1 minute'", [cle])
  if (recents.total >= ESSAIS_PAR_MINUTE) return { erreur: 'Trop d’essais. Réessayez dans une minute.' }
  const user = await une('SELECT id, name, email, password, is_admin FROM users WHERE email = $1', [adresse])
  if (!user || !(await verifier(String(motDePasse ?? ''), user.password))) {
    await requete('INSERT INTO login_attempts (key) VALUES ($1)', [cle])
    return { erreur: 'Adresse électronique ou mot de passe incorrect.' }
  }
  await requete('DELETE FROM login_attempts WHERE key = $1', [cle])
  return { user: { id: user.id, name: user.name, email: user.email, is_admin: user.is_admin } }
}

/** Ouvre une session ; renvoie le jeton à mettre dans le cookie (seule son empreinte est gardée). */
export async function ouvrirSession(userId) {
  const valeur = jeton()
  await requete("INSERT INTO sessions (id, user_id, expires_at) VALUES ($1, $2, now() + interval '" + DUREE_SESSION_JOURS + " days')", [empreinte(valeur), userId])
  return valeur
}

/** Utilisateur d'une session encore valable, ou null. */
export async function utilisateurDeSession(valeur) {
  if (!valeur) return null
  return une('SELECT u.id, u.name, u.email, u.is_admin FROM sessions s JOIN users u ON u.id = s.user_id WHERE s.id = $1 AND s.expires_at > now()', [empreinte(valeur)])
}

export async function fermerSession(valeur) {
  if (valeur) await requete('DELETE FROM sessions WHERE id = $1', [empreinte(valeur)])
}

/** Lien « mot de passe oublié » : renvoie le jeton (null si aucun compte), valable une heure. */
export async function demanderNouveauMotDePasse(email) {
  const user = await une('SELECT id FROM users WHERE email = $1', [String(email ?? '').trim().toLowerCase()])
  if (!user) return null
  const valeur = jeton()
  await requete('DELETE FROM password_resets WHERE user_id = $1', [user.id])
  await requete("INSERT INTO password_resets (id, user_id, expires_at) VALUES ($1, $2, now() + interval '60 minutes')", [empreinte(valeur), user.id])
  return valeur
}

/** Nouveau mot de passe avec un lien valable ; toutes les sessions du compte sont fermées. */
export async function changerMotDePasse(valeur, motDePasse, confirmation) {
  if (String(motDePasse ?? '').length < 8) return { erreurs: { password: 'Au moins 8 caractères.' } }
  if (motDePasse !== confirmation) return { erreurs: { password_confirmation: 'Les deux mots de passe sont différents.' } }
  const lien = await une('SELECT user_id FROM password_resets WHERE id = $1 AND expires_at > now()', [empreinte(String(valeur ?? ''))])
  if (!lien) return { erreurs: { formulaire: 'Ce lien n’est plus valable. Demandez-en un nouveau.' } }
  await requete('UPDATE users SET password = $1 WHERE id = $2', [await chiffrer(motDePasse), lien.user_id])
  await requete('DELETE FROM password_resets WHERE user_id = $1', [lien.user_id])
  await requete('DELETE FROM sessions WHERE user_id = $1', [lien.user_id])
  return { ok: true }
}
`

  f['lib/session.js'] = js`// Session de l'utilisateur dans un cookie protégé (jamais lisible par JavaScript dans la page).
import { cookies } from 'next/headers'
import { notFound, redirect } from 'next/navigation'
import { DUREE_SESSION_JOURS, fermerSession, ouvrirSession, utilisateurDeSession } from './comptes.js'

const COOKIE = 'session'

export async function connecter(userId) {
  const valeur = await ouvrirSession(userId)
  ;(await cookies()).set(COOKIE, valeur, {
    httpOnly: true,
    sameSite: 'lax',
    secure: process.env.NODE_ENV === 'production',
    path: '/',
    maxAge: DUREE_SESSION_JOURS * 24 * 3600,
  })
}

export async function deconnecterSession() {
  const magasin = await cookies()
  await fermerSession(magasin.get(COOKIE)?.value)
  magasin.delete(COOKIE)
}

/** Utilisateur connecté, ou null. */
export async function utilisateurActuel() {
  return utilisateurDeSession((await cookies()).get(COOKIE)?.value)
}

/** Page réservée aux comptes : renvoie vers la connexion sinon. */
export async function exigerUtilisateur() {
  const user = await utilisateurActuel()
  if (!user) redirect('/connexion')
  return user
}

/** Page réservée aux administrateurs (réponse 404 pour les autres, pour ne rien révéler). */
export async function exigerAdmin() {
  const user = await exigerUtilisateur()
  if (!user.is_admin) notFound()
  return user
}
`

  f['lib/courriel.js'] = js`// Envoi des courriels (mot de passe oublié) avec les réglages SMTP de l'hébergement.
// Sans réglage (essai dans Kekeli), le lien est seulement écrit dans le journal du serveur.
export async function envoyerCourriel({ a, sujet, texte }) {
  if (!process.env.SMTP_HOST) {
    console.log('[courriel non envoyé : SMTP_HOST vide] ' + sujet + ' → ' + a + '\n' + texte)
    return
  }
  const { default: nodemailer } = await import('nodemailer')
  const transport = nodemailer.createTransport({
    host: process.env.SMTP_HOST,
    port: Number(process.env.SMTP_PORT || 587),
    auth: process.env.SMTP_USER ? { user: process.env.SMTP_USER, pass: process.env.SMTP_PASSWORD } : undefined,
  })
  await transport.sendMail({ from: process.env.SMTP_FROM || process.env.SMTP_USER, to: a, subject: sujet, text: texte })
}
`

  f['lib/format.js'] = js`// Affichage en français, heure de Lomé.
export function dateFr(valeur) {
  if (!valeur) return ''
  const [annee, mois, jour] = String(valeur).slice(0, 10).split('-')
  return jour + '/' + mois + '/' + annee
}

export function dateHeureFr(valeur) {
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short', timeZone: 'Africa/Lome' }).format(new Date(valeur))
}

/** Date du jour (AAAA-MM-JJ, heure de Lomé, qui est l'heure universelle). */
export function aujourdhui() {
  return new Date().toISOString().slice(0, 10)
}

/** Date dans quelques jours (AAAA-MM-JJ). */
export function dansJours(jours) {
  return new Date(Date.now() + jours * 86400000).toISOString().slice(0, 10)
}
`

  // ----------------------------------------------------------------------------------------------
  // Données de l'application
  f[`lib/${route}.js`] = js`// ${majuscule(e.pluriel)} : validation, enregistrement et lecture. Un utilisateur ne lit que ses propres
// ${e.pluriel} ; l'administrateur les lit toutes. Le propriétaire et l'état ne viennent jamais du formulaire.
import { z } from 'zod'
import { erreursParChamp } from './comptes.js'
import { requete, une } from './db.js'
import { aujourdhui } from './format.js'

/** États possibles, dans l'ordre, avec leur nom affiché. */
export const STATUTS = {
${statuts.map((s) => `  ${s.code}: ${chaine(s.libelle)},`).join('\n')}
}
${options.map((c) => `
/** Choix possibles pour « ${c.libelle} ». */
export const ${constanteOptions(c)} = [${c.options.map(chaine).join(', ')}]
`).join('')}
/** Champs du formulaire (pour l'afficher). */
export const CHAMPS = ${JSON.stringify(champs.map(champFormulaire), null, 2).replace(/\n/g, '\n')}
${options.map((c) => `CHAMPS.find((champ) => champ.nom === '${c.nom}').options = ${constanteOptions(c)}\n`).join('')}
const schema = z
  .object({
${champs.map((c) => `    ${c.nom}: ${regle(c)},`).join('\n')}
  })${apres.map((c) => `
  .refine((d) => !d.${c.nom} || !d.${c.contrainte.slice(6)} || d.${c.nom} > d.${c.contrainte.slice(6)}, { path: ['${c.nom}'], message: ${chaine(`${c.libelle} : doit être après ${champs.find((x) => x.nom === c.contrainte.slice(6))?.libelle.toLowerCase() ?? 'la date précédente'}.`)} })`).join('')}

const COLONNES = ${JSON.stringify(colonnes)}

/** Enregistre ${'un' + (e.feminin ? 'e' : '')} ${e.singulier} pour un utilisateur ; renvoie { element } ou { erreurs }. */
export async function creer(userId, donnees) {
  const resultat = schema.safeParse(Object.fromEntries(COLONNES.map((nom) => [nom, String(donnees?.[nom] ?? '')])))
  if (!resultat.success) return { erreurs: erreursParChamp(resultat.error) }
  const valeurs = COLONNES.map((nom) => (resultat.data[nom] === '' ? null : resultat.data[nom]))
  const ligne = await une(
    'INSERT INTO ${table} (user_id, ' + COLONNES.join(', ') + ') VALUES ($1, ' + COLONNES.map((_, i) => '$' + (i + 2)).join(', ') + ') RETURNING *',
    [userId, ...valeurs],
  )
  return { element: ligne }
}

/** ${majuscule(e.pluriel)} d'un utilisateur, les plus récentes d'abord. */
export function listerPour(userId) {
  return requete('SELECT * FROM ${table} WHERE user_id = $1 ORDER BY created_at DESC, id DESC', [userId])
}

/** ${majuscule(e.singulier)} visible par cet utilisateur (la sienne, ou n'importe laquelle pour l'administrateur), sinon null. */
export function trouverPour(user, id) {
  if (!/^\d+$/.test(String(id))) return null
  return user.is_admin
    ? une('SELECT t.*, u.name AS client, u.email AS client_email FROM ${table} t JOIN users u ON u.id = t.user_id WHERE t.id = $1', [Number(id)])
    : une('SELECT * FROM ${table} WHERE id = $1 AND user_id = $2', [Number(id), user.id])
}

/** Administration : toutes les ${e.pluriel}, filtrées par état si demandé. */
export function listerTout(statut = null) {
  return statut && STATUTS[statut]
    ? requete('SELECT t.*, u.name AS client FROM ${table} t JOIN users u ON u.id = t.user_id WHERE t.statut = $1 ORDER BY t.created_at DESC, t.id DESC LIMIT 200', [statut])
    : requete('SELECT t.*, u.name AS client FROM ${table} t JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC, t.id DESC LIMIT 200')
}

/** Administration : nombre de ${e.pluriel} par état. */
export async function compterParStatut() {
  const lignes = await requete('SELECT statut, count(*)::int AS total FROM ${table} GROUP BY statut')
  return Object.fromEntries(lignes.map((l) => [l.statut, l.total]))
}

/** Administration : change l'état (seulement un état connu, seulement par un administrateur). */
export async function changerStatut(user, id, statut) {
  if (!user?.is_admin) return { erreur: 'Réservé à l’administrateur.' }
  if (!STATUTS[statut]) return { erreur: 'État inconnu.' }
  await requete('UPDATE ${table} SET statut = $1 WHERE id = $2', [statut, Number(id)])
  return { ok: true }
}
`

  // ----------------------------------------------------------------------------------------------
  // Actions des formulaires (côté serveur ; Next.js vérifie l'origine de chaque envoi)
  f['app/actions/comptes.js'] = js`'use server'

import { headers } from 'next/headers'
import { redirect } from 'next/navigation'
import { changerMotDePasse, demanderNouveauMotDePasse, inscrire, verifierConnexion } from '@/lib/comptes'
import { envoyerCourriel } from '@/lib/courriel'
import { NOM } from '@/lib/config'
import { connecter, deconnecterSession } from '@/lib/session'

const valeurs = (formulaire) => Object.fromEntries(formulaire.entries())

async function appareil() {
  const entetes = await headers()
  return (entetes.get('x-forwarded-for') || '').split(',')[0].trim() || 'inconnu'
}

export async function inscription(_etat, formulaire) {
  const resultat = await inscrire(valeurs(formulaire))
  if (resultat.erreurs) return { erreurs: resultat.erreurs, valeurs: { name: formulaire.get('name'), email: formulaire.get('email') } }
  await connecter(resultat.user.id)
  redirect('/espace?bienvenue=1')
}

export async function connexion(_etat, formulaire) {
  const resultat = await verifierConnexion(formulaire.get('email'), formulaire.get('password'), await appareil())
  if (resultat.erreur) return { erreurs: { email: resultat.erreur }, valeurs: { email: formulaire.get('email') } }
  await connecter(resultat.user.id)
  redirect('/espace')
}

export async function deconnexion() {
  await deconnecterSession()
  redirect('/')
}

export async function motDePasseOublie(_etat, formulaire) {
  const email = String(formulaire.get('email') || '')
  const valeur = await demanderNouveauMotDePasse(email)
  if (valeur) {
    const lien = (process.env.APP_URL || 'http://localhost:3000') + '/nouveau-mot-de-passe?jeton=' + encodeURIComponent(valeur)
    await envoyerCourriel({
      a: email,
      sujet: 'Nouveau mot de passe · ' + NOM,
      texte: 'Bonjour,\n\nPour choisir un nouveau mot de passe, ouvrez ce lien (valable une heure) :\n' + lien + '\n\nSi vous n’avez rien demandé, ignorez ce message.',
    })
  }
  // Même réponse que l'adresse existe ou non : on ne révèle pas qui a un compte.
  return { message: 'Si un compte existe avec cette adresse, un lien pour choisir un nouveau mot de passe vient d’être envoyé.' }
}

export async function nouveauMotDePasse(_etat, formulaire) {
  const resultat = await changerMotDePasse(formulaire.get('jeton'), formulaire.get('password'), formulaire.get('password_confirmation'))
  if (resultat.erreurs) return { erreurs: resultat.erreurs }
  redirect('/connexion?mot-de-passe=1')
}
`

  f[`app/actions/${route}.js`] = js`'use server'

import { redirect } from 'next/navigation'
import { changerStatut, creer } from '@/lib/${route}'
import { exigerAdmin, exigerUtilisateur } from '@/lib/session'

export async function enregistrer(_etat, formulaire) {
  // L'utilisateur vient de la session, jamais du formulaire.
  const user = await exigerUtilisateur()
  const resultat = await creer(user.id, Object.fromEntries(formulaire.entries()))
  if (resultat.erreurs) return { erreurs: resultat.erreurs, valeurs: Object.fromEntries(formulaire.entries()) }
  redirect('/espace/${route}/' + resultat.element.id + '?enregistre=1')
}

export async function changerEtat(id, formulaire) {
  const admin = await exigerAdmin()
  await changerStatut(admin, id, String(formulaire.get('statut')))
  redirect('/admin/${route}/' + id + '?etat=1')
}
`

  // ----------------------------------------------------------------------------------------------
  // Composants
  f['components/Formulaire.js'] = js`'use client'

// Formulaire envoyé à une action serveur, avec les erreurs affichées sous chaque champ.
import { useActionState } from 'react'

export default function Formulaire({ action, champs, bouton, caches = {}, message = null }) {
  const [etat, envoyer, enCours] = useActionState(action, null)
  const erreurs = etat?.erreurs ?? {}
  const valeurs = etat?.valeurs ?? {}
  return (
    <form action={envoyer} className="carte" noValidate>
      {etat?.message ? <p className="message succes" role="status">{etat.message}</p> : null}
      {message && !etat ? <p className="message succes" role="status">{message}</p> : null}
      {erreurs.formulaire ? <p className="message erreur" role="alert">{erreurs.formulaire}</p> : null}
      {Object.entries(caches).map(([nom, valeur]) => (
        <input key={nom} type="hidden" name={nom} value={valeur} />
      ))}
      {champs.map((champ) => (
        <div className="champ" key={champ.nom}>
          <label htmlFor={champ.nom}>{champ.libelle}</label>
          {champ.type === 'textarea' ? (
            <textarea id={champ.nom} name={champ.nom} required={champ.requis} defaultValue={valeurs[champ.nom] ?? ''} />
          ) : champ.type === 'select' ? (
            <select id={champ.nom} name={champ.nom} required={champ.requis} defaultValue={valeurs[champ.nom] ?? ''}>
              <option value="">Choisir…</option>
              {champ.options.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          ) : (
            <input
              id={champ.nom}
              name={champ.nom}
              type={champ.type}
              required={champ.requis}
              min={champ.min}
              max={champ.max}
              minLength={champ.minLength}
              autoComplete={champ.autocomplete}
              defaultValue={champ.type === 'password' ? undefined : valeurs[champ.nom] ?? ''}
            />
          )}
          {champ.aide ? <p className="aide">{champ.aide}</p> : null}
          {erreurs[champ.nom] ? <p className="erreur-champ">{erreurs[champ.nom]}</p> : null}
        </div>
      ))}
      <button type="submit" className="bouton" disabled={enCours}>
        {enCours ? 'Envoi…' : bouton}
      </button>
    </form>
  )
}
`

  f['components/Statut.js'] = js`import { STATUTS } from '@/lib/${route}'

const CODES = Object.keys(STATUTS)

export default function Statut({ code }) {
  return <span className={'statut s' + Math.max(0, CODES.indexOf(code))}>{STATUTS[code] ?? code}</span>
}
`

  // ----------------------------------------------------------------------------------------------
  // Pages
  f['app/layout.js'] = js`import './globals.css'
import Link from 'next/link'
import { deconnexion } from './actions/comptes'
import { NOM } from '@/lib/config'
import { utilisateurActuel } from '@/lib/session'

export const metadata = { title: { default: NOM, template: '%s · ' + NOM } }
export const viewport = { width: 'device-width', initialScale: 1 }

export default async function Disposition({ children }) {
  const user = await utilisateurActuel()
  return (
    <html lang="fr">
      <body>
        <header className="entete">
          <div className="conteneur">
            <Link className="marque" href="/">
              {NOM}
            </Link>
            <nav className="navigation" aria-label="Menu principal">
              {user ? (
                <>
                  <Link href="/espace">Mon espace</Link>
                  {user.is_admin ? <Link href="/admin">Administration</Link> : null}
                  <form action={deconnexion}>
                    <button type="submit" className="lien-bouton">
                      Se déconnecter
                    </button>
                  </form>
                </>
              ) : (
                <>
                  <Link href="/connexion">Se connecter</Link>
                  <Link href="/inscription">Créer un compte</Link>
                </>
              )}
            </nav>
          </div>
        </header>
        <main>
          <div className="conteneur">{children}</div>
        </main>
        <footer className="pied">
          <div className="conteneur">
            © {new Date().getFullYear()} {NOM}
          </div>
        </footer>
      </body>
    </html>
  )
}
`

  f['app/page.js'] = js`import Link from 'next/link'
import { ACCROCHE, NOM } from '@/lib/config'
import { utilisateurActuel } from '@/lib/session'

export const metadata = { title: 'Accueil' }

export default async function Accueil() {
  const user = await utilisateurActuel()
  return (
    <>
      <section className="heros">
        <h1>{NOM}</h1>
        <p className="accroche">{ACCROCHE}</p>
        <div className="actions">
          {user ? (
            <>
              <Link className="bouton" href="/espace/${route}/nouveau">
                ${icone('plus')} ${e.action}
              </Link>
              <Link className="bouton secondaire" href="/espace">
                Mon espace
              </Link>
            </>
          ) : (
            <>
              <Link className="bouton" href="/inscription">
                ${icone('utilisateur')} Créer un compte
              </Link>
              <Link className="bouton secondaire" href="/connexion">
                Se connecter
              </Link>
            </>
          )}
        </div>
      </section>
      <section className="grille">
        <div className="carte">
          <h2>1. Créez votre compte</h2>
          <p>Une adresse électronique et un mot de passe suffisent.</p>
        </div>
        <div className="carte">
          <h2>2. ${e.action}</h2>
          <p>Remplissez le formulaire depuis votre téléphone, en quelques secondes.</p>
        </div>
        <div className="carte">
          <h2>3. Suivez-${e.feminin ? 'la' : 'le'} depuis votre espace</h2>
          <p>Vous voyez à tout moment où en est ${le(e)}.</p>
        </div>
      </section>
    </>
  )
}
`

  f['app/inscription/page.js'] = js`import Link from 'next/link'
import { redirect } from 'next/navigation'
import Formulaire from '@/components/Formulaire'
import { utilisateurActuel } from '@/lib/session'
import { inscription } from '../actions/comptes'

export const metadata = { title: 'Créer un compte' }

const CHAMPS = [
  { nom: 'name', libelle: 'Nom et prénoms', type: 'text', requis: true, autocomplete: 'name' },
  { nom: 'email', libelle: 'Adresse électronique', type: 'email', requis: true, autocomplete: 'email' },
  { nom: 'password', libelle: 'Mot de passe', type: 'password', requis: true, autocomplete: 'new-password', minLength: 8, aide: 'Au moins 8 caractères.' },
  { nom: 'password_confirmation', libelle: 'Confirmer le mot de passe', type: 'password', requis: true, autocomplete: 'new-password' },
]

export default async function Inscription() {
  if (await utilisateurActuel()) redirect('/espace')
  return (
    <>
      <h1>Créer un compte</h1>
      <Formulaire action={inscription} champs={CHAMPS} bouton="Créer mon compte" />
      <p>
        Vous avez déjà un compte ? <Link href="/connexion">Se connecter</Link>
      </p>
    </>
  )
}
`

  f['app/connexion/page.js'] = js`import Link from 'next/link'
import { redirect } from 'next/navigation'
import Formulaire from '@/components/Formulaire'
import { utilisateurActuel } from '@/lib/session'
import { connexion } from '../actions/comptes'

export const metadata = { title: 'Se connecter' }

const CHAMPS = [
  { nom: 'email', libelle: 'Adresse électronique', type: 'email', requis: true, autocomplete: 'email' },
  { nom: 'password', libelle: 'Mot de passe', type: 'password', requis: true, autocomplete: 'current-password' },
]

export default async function Connexion({ searchParams }) {
  if (await utilisateurActuel()) redirect('/espace')
  const parametres = await searchParams
  return (
    <>
      <h1>Se connecter</h1>
      <Formulaire
        action={connexion}
        champs={CHAMPS}
        bouton="Se connecter"
        message={parametres['mot-de-passe'] ? 'Votre mot de passe est changé. Vous pouvez vous connecter.' : null}
      />
      <p>
        <Link href="/mot-de-passe-oublie">Mot de passe oublié ?</Link>
      </p>
      <p>
        Pas encore de compte ? <Link href="/inscription">Créer un compte</Link>
      </p>
    </>
  )
}
`

  f['app/mot-de-passe-oublie/page.js'] = js`import Formulaire from '@/components/Formulaire'
import { motDePasseOublie } from '../actions/comptes'

export const metadata = { title: 'Mot de passe oublié' }

export default function MotDePasseOublie() {
  return (
    <>
      <h1>Mot de passe oublié</h1>
      <p>Indiquez votre adresse électronique : nous vous envoyons un lien pour choisir un nouveau mot de passe.</p>
      <Formulaire
        action={motDePasseOublie}
        champs={[{ nom: 'email', libelle: 'Adresse électronique', type: 'email', requis: true, autocomplete: 'email' }]}
        bouton="Recevoir le lien"
      />
    </>
  )
}
`

  f['app/nouveau-mot-de-passe/page.js'] = js`import Formulaire from '@/components/Formulaire'
import { nouveauMotDePasse } from '../actions/comptes'

export const metadata = { title: 'Nouveau mot de passe' }

export default async function NouveauMotDePasse({ searchParams }) {
  const { jeton = '' } = await searchParams
  return (
    <>
      <h1>Nouveau mot de passe</h1>
      <Formulaire
        action={nouveauMotDePasse}
        caches={{ jeton }}
        champs={[
          { nom: 'password', libelle: 'Nouveau mot de passe', type: 'password', requis: true, autocomplete: 'new-password', minLength: 8 },
          { nom: 'password_confirmation', libelle: 'Confirmer le mot de passe', type: 'password', requis: true, autocomplete: 'new-password' },
        ]}
        bouton="Enregistrer"
      />
    </>
  )
}
`

  const resume = (variable) => champs.slice(0, 2).map((c) => affichage(variable, c)).join(' · ')

  f['app/espace/page.js'] = js`import Link from 'next/link'
import Statut from '@/components/Statut'
import { dateFr } from '@/lib/format'
import { listerPour } from '@/lib/${route}'
import { exigerUtilisateur } from '@/lib/session'

export const metadata = { title: 'Mon espace' }

export default async function Espace({ searchParams }) {
  const user = await exigerUtilisateur()
  const { bienvenue } = await searchParams
  // Seulement les ${e.pluriel} de l'utilisateur connecté.
  const elements = await listerPour(user.id)
  return (
    <>
      {bienvenue ? <p className="message succes" role="status">Bienvenue ! Votre compte est créé.</p> : null}
      <h1>Bonjour {user.name}</h1>
      <div className="actions">
        <Link className="bouton" href="/espace/${route}/nouveau">
          ${icone('plus')} ${e.action}
        </Link>
      </div>
      <h2>${mes(e)}</h2>
      {elements.length === 0 ? (
        <p className="carte">Vous n’avez encore ${aucun(e)}. Touchez « ${e.action} » pour commencer.</p>
      ) : (
        <ul className="liste carte">
          {elements.map((element) => (
            <li key={element.id}>
              <Link href={'/espace/${route}/' + element.id}>
                ${resume('element')}
              </Link>
              <Statut code={element.statut} />
            </li>
          ))}
        </ul>
      )}
    </>
  )
}
`

  f[`app/espace/${route}/nouveau/page.js`] = js`import Formulaire from '@/components/Formulaire'
import { CHAMPS } from '@/lib/${route}'
import { exigerUtilisateur } from '@/lib/session'
import { enregistrer } from '../../../actions/${route}'

export const metadata = { title: ${chaine(e.action)} }

export default async function Nouveau() {
  await exigerUtilisateur()
  return (
    <>
      <h1>${e.action}</h1>
      <Formulaire action={enregistrer} champs={CHAMPS} bouton="Envoyer" />
    </>
  )
}
`

  const lignesDetails = (variable) =>
    champs
      .map(
        (c) => `        <dt>${c.libelle}</dt>
        <dd>${affichage(variable, c)}</dd>`,
      )
      .join('\n')

  f[`app/espace/${route}/[id]/page.js`] = js`import Link from 'next/link'
import { notFound } from 'next/navigation'
import Statut from '@/components/Statut'
import { dateFr, dateHeureFr } from '@/lib/format'
import { trouverPour } from '@/lib/${route}'
import { exigerUtilisateur } from '@/lib/session'

export const metadata = { title: ${chaine(majuscule(e.singulier))} }

export default async function Detail({ params, searchParams }) {
  const user = await exigerUtilisateur()
  const { id } = await params
  const { enregistre } = await searchParams
  // Un utilisateur ne peut pas voir ${e.feminin ? 'une' : 'un'} ${e.singulier} qui ne lui appartient pas (réponse 404).
  const element = await trouverPour({ ...user, is_admin: false }, id)
  if (!element) notFound()
  return (
    <>
      {enregistre ? <p className="message succes" role="status">${majuscule(le(e))} est ${accord(e, 'enregistré')}. Nous vous tiendrons au courant.</p> : null}
      <h1>${majuscule(e.singulier)} du {dateHeureFr(element.created_at).slice(0, 10)}</h1>
      <p>
        État : <Statut code={element.statut} />
      </p>
      <dl className="details carte">
${lignesDetails('element')}
      </dl>
      <p>
        <Link href="/espace">← Retour à mon espace</Link>
      </p>
    </>
  )
}
`

  f['app/admin/page.js'] = js`import Link from 'next/link'
import Statut from '@/components/Statut'
import { dateFr } from '@/lib/format'
import { compterParStatut, listerTout, STATUTS } from '@/lib/${route}'
import { requete } from '@/lib/db'
import { exigerAdmin } from '@/lib/session'

export const metadata = { title: 'Administration' }

export default async function Administration() {
  await exigerAdmin()
  const parStatut = await compterParStatut()
  const total = Object.values(parStatut).reduce((a, b) => a + b, 0)
  const [{ comptes }] = await requete('SELECT count(*)::int AS comptes FROM users')
  const derniers = (await listerTout()).slice(0, 5)
  return (
    <>
      <h1>${icone('bouclier')} Administration</h1>
      <div className="chiffres">
        <div className="carte">
          <p className="chiffre">{total}</p>
          <p>${majuscule(e.pluriel)} au total</p>
        </div>
        {Object.entries(STATUTS).map(([code, libelle]) => (
          <div className="carte" key={code}>
            <p className="chiffre">{parStatut[code] ?? 0}</p>
            <p>{libelle}</p>
          </div>
        ))}
        <div className="carte">
          <p className="chiffre">{comptes}</p>
          <p>Comptes</p>
        </div>
      </div>
      <div className="actions">
        <Link className="bouton" href="/admin/${route}">
          ${icone('liste')} ${majuscule(tous(e))}
        </Link>
        <Link className="bouton secondaire" href="/admin/utilisateurs">
          Comptes
        </Link>
      </div>
      <h2>${e.feminin ? 'Dernières' : 'Derniers'} ${e.pluriel}</h2>
      <ul className="liste carte">
        {derniers.length === 0 ? <li>Rien pour le moment.</li> : null}
        {derniers.map((element) => (
          <li key={element.id}>
            <Link href={'/admin/${route}/' + element.id}>
              {element.client} · ${resume('element')}
            </Link>
            <Statut code={element.statut} />
          </li>
        ))}
      </ul>
    </>
  )
}
`

  f[`app/admin/${route}/page.js`] = js`import Link from 'next/link'
import Statut from '@/components/Statut'
import { dateFr } from '@/lib/format'
import { listerTout, STATUTS } from '@/lib/${route}'
import { exigerAdmin } from '@/lib/session'

export const metadata = { title: ${chaine(majuscule(e.pluriel))} }

export default async function Liste({ searchParams }) {
  await exigerAdmin()
  const { statut = null } = await searchParams
  const elements = await listerTout(statut)
  return (
    <>
      <h1>${majuscule(e.pluriel)}</h1>
      <nav className="filtres" aria-label="Filtrer par état">
        <Link href="/admin/${route}" className={!STATUTS[statut] ? 'actif' : ''}>
          ${e.feminin ? 'Toutes' : 'Tous'}
        </Link>
        {Object.entries(STATUTS).map(([code, libelle]) => (
          <Link key={code} href={'/admin/${route}?statut=' + code} className={statut === code ? 'actif' : ''}>
            {libelle}
          </Link>
        ))}
      </nav>
      <div className="defilement carte">
        <table className="tableau">
          <thead>
            <tr>
              <th>Client</th>
${champs.slice(0, 2).map((c) => `              <th>${c.libelle}</th>`).join('\n')}
              <th>État</th>
            </tr>
          </thead>
          <tbody>
            {elements.length === 0 ? (
              <tr>
                <td colSpan={${Math.min(2, champs.length) + 2}}>${majuscule(aucun(e))} pour le moment.</td>
              </tr>
            ) : null}
            {elements.map((element) => (
              <tr key={element.id}>
                <td>
                  <Link href={'/admin/${route}/' + element.id}>{element.client}</Link>
                </td>
${champs.slice(0, 2).map((c) => `                <td>${affichage('element', c)}</td>`).join('\n')}
                <td>
                  <Statut code={element.statut} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  )
}
`

  f[`app/admin/${route}/[id]/page.js`] = js`import Link from 'next/link'
import { notFound } from 'next/navigation'
import { dateFr, dateHeureFr } from '@/lib/format'
import { STATUTS, trouverPour } from '@/lib/${route}'
import { exigerAdmin } from '@/lib/session'
import { changerEtat } from '../../../actions/${route}'

export const metadata = { title: ${chaine(majuscule(e.singulier))} }

export default async function DetailAdmin({ params, searchParams }) {
  const admin = await exigerAdmin()
  const { id } = await params
  const { etat } = await searchParams
  const element = await trouverPour(admin, id)
  if (!element) notFound()
  return (
    <>
      {etat ? <p className="message succes" role="status">L’état est mis à jour.</p> : null}
      <h1>${majuscule(e.singulier)} de {element.client}</h1>
      <dl className="details carte">
        <dt>Client</dt>
        <dd>
          {element.client} ({element.client_email})
        </dd>
        <dt>${accord(e, 'Reçu')} le</dt>
        <dd>{dateHeureFr(element.created_at)}</dd>
${lignesDetails('element')}
      </dl>
      <form action={changerEtat.bind(null, element.id)} className="carte">
        <div className="champ">
          <label htmlFor="statut">État</label>
          <select id="statut" name="statut" defaultValue={element.statut}>
            {Object.entries(STATUTS).map(([code, libelle]) => (
              <option key={code} value={code}>
                {libelle}
              </option>
            ))}
          </select>
        </div>
        <button type="submit" className="bouton">
          Enregistrer l’état
        </button>
      </form>
      <p>
        <Link href="/admin/${route}">← ${majuscule(tous(e))}</Link>
      </p>
    </>
  )
}
`

  f['app/admin/utilisateurs/page.js'] = js`import { requete } from '@/lib/db'
import { exigerAdmin } from '@/lib/session'

export const metadata = { title: 'Comptes' }

export default async function Utilisateurs() {
  await exigerAdmin()
  const comptes = await requete(
    'SELECT u.id, u.name, u.email, u.is_admin, count(t.id)::int AS total FROM users u LEFT JOIN ${table} t ON t.user_id = u.id GROUP BY u.id ORDER BY u.created_at DESC LIMIT 500',
  )
  return (
    <>
      <h1>Comptes</h1>
      <div className="defilement carte">
        <table className="tableau">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Adresse électronique</th>
              <th>${majuscule(e.pluriel)}</th>
              <th>Rôle</th>
            </tr>
          </thead>
          <tbody>
            {comptes.map((compte) => (
              <tr key={compte.id}>
                <td>{compte.name}</td>
                <td>{compte.email}</td>
                <td>{compte.total}</td>
                <td>{compte.is_admin ? 'Administrateur' : 'Client'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </>
  )
}
`

  f['app/not-found.js'] = js`import Link from 'next/link'

export default function Introuvable() {
  return (
    <>
      <h1>Page introuvable</h1>
      <p>Cette page n’existe pas, ou vous n’avez pas le droit de la voir.</p>
      <p>
        <Link href="/">← Accueil</Link>
      </p>
    </>
  )
}
`

  // ----------------------------------------------------------------------------------------------
  // Scripts et tests
  f['scripts/donnees-essai.mjs'] = js`// Données d'essai, seulement pour l'aperçu dans Kekeli (jamais en production) : un administrateur dont
// l'adresse et le mot de passe viennent des variables d'environnement, et un client d'exemple.
import { randomBytes } from 'node:crypto'
import { requete, une } from '../lib/db.js'
import { dansJours } from '../lib/format.js'
import { chiffrer } from '../lib/securite.js'
import { creer } from '../lib/${route}.js'

if (process.env.NODE_ENV === 'production' || process.env.DATABASE_URL) {
  console.log('Pas de données d’essai sur une vraie base.')
  process.exit(0)
}

const email = process.env.ESSAI_ADMIN_EMAIL
const motDePasse = process.env.ESSAI_ADMIN_MOT_DE_PASSE
if (email && motDePasse) {
  await requete(
    'INSERT INTO users (name, email, password, is_admin) VALUES ($1, $2, $3, TRUE) ON CONFLICT (email) DO UPDATE SET password = EXCLUDED.password, is_admin = TRUE',
    ['Administrateur (essai)', email, await chiffrer(motDePasse)],
  )
}

const deja = await une('SELECT count(*)::int AS total FROM ${table}')
if (deja.total === 0) {
  const client = await une('INSERT INTO users (name, email, password) VALUES ($1, $2, $3) ON CONFLICT (email) DO UPDATE SET name = EXCLUDED.name RETURNING id', [
    'Client d’exemple',
    'client@exemple.test',
    await chiffrer(randomBytes(12).toString('hex')),
  ])
  for (let i = 0; i < 3; i++) {
    await creer(client.id, {
${champs.map((c) => `      ${c.nom}: ${valeurExemple(c)},`).join('\n')}
    })
  }
}
console.log('Données d’essai prêtes.')
process.exit(0)
`

  f['scripts/admin.mjs'] = js`// Donne le rôle d'administrateur à un compte existant : npm run admin -- adresse@exemple.com
import { une } from '../lib/db.js'

const email = String(process.argv[2] || '').trim().toLowerCase()
const user = email ? await une('UPDATE users SET is_admin = TRUE WHERE email = $1 RETURNING email', [email]) : null
console.log(user ? 'Le compte ' + user.email + ' est maintenant administrateur.' : 'Aucun compte avec cette adresse. Créez d’abord le compte sur le site.')
process.exit(user ? 0 : 1)
`

  f['tests/comptes.test.js'] = js`import { describe, expect, it } from 'vitest'
import { changerMotDePasse, demanderNouveauMotDePasse, fermerSession, inscrire, ouvrirSession, utilisateurDeSession, verifierConnexion } from '@/lib/comptes'
import { une } from '@/lib/db'

const motDePasse = 'motdepasse-solide'
const compte = (email, plus = {}) => ({ name: 'Afi Essai', email, password: motDePasse, password_confirmation: motDePasse, ...plus })

describe('comptes', () => {
  it('crée un compte avec un mot de passe chiffré', async () => {
    const { user } = await inscrire(compte('afi@exemple.test'))
    expect(user.is_admin).toBe(false)
    const ligne = await une('SELECT password FROM users WHERE id = $1', [user.id])
    expect(ligne.password).not.toBe(motDePasse)
    expect(ligne.password.startsWith('$2')).toBe(true)
  })

  it('refuse les données invalides et les adresses déjà prises', async () => {
    expect((await inscrire(compte('pas-une-adresse'))).erreurs.email).toBeTruthy()
    expect((await inscrire(compte('court@exemple.test', { password: 'court', password_confirmation: 'court' }))).erreurs.password).toBeTruthy()
    expect((await inscrire(compte('confirmation@exemple.test', { password_confirmation: 'autre-chose' }))).erreurs.password_confirmation).toBeTruthy()
    await inscrire(compte('double@exemple.test'))
    expect((await inscrire(compte('double@exemple.test'))).erreurs.email).toBeTruthy()
  })

  it('donne le rôle d’administrateur seulement à l’adresse ADMIN_EMAIL, jamais par le formulaire', async () => {
    expect((await inscrire(compte('patron@exemple.test'))).user.is_admin).toBe(true)
    expect((await inscrire(compte('pirate@exemple.test', { is_admin: 'true' }))).user.is_admin).toBe(false)
  })

  it('connecte avec le bon mot de passe et limite les essais', async () => {
    await inscrire(compte('kofi@exemple.test'))
    expect((await verifierConnexion('kofi@exemple.test', 'mauvais', 'appareil-1')).erreur).toBeTruthy()
    expect((await verifierConnexion('kofi@exemple.test', motDePasse, 'appareil-1')).user.email).toBe('kofi@exemple.test')
    for (let i = 0; i < 5; i++) await verifierConnexion('kofi@exemple.test', 'mauvais', 'appareil-2')
    expect((await verifierConnexion('kofi@exemple.test', motDePasse, 'appareil-2')).erreur).toMatch(/Trop/)
  })

  it('garde seulement l’empreinte de la session, et la ferme', async () => {
    const { user } = await inscrire(compte('session@exemple.test'))
    const valeur = await ouvrirSession(user.id)
    expect(await une('SELECT id FROM sessions WHERE id = $1', [valeur])).toBeNull()
    expect((await utilisateurDeSession(valeur)).id).toBe(user.id)
    await fermerSession(valeur)
    expect(await utilisateurDeSession(valeur)).toBeNull()
    expect(await utilisateurDeSession('jeton-invente')).toBeNull()
  })

  it('change le mot de passe avec un lien valable une seule fois', async () => {
    const { user } = await inscrire(compte('oubli@exemple.test'))
    expect(await demanderNouveauMotDePasse('inconnu@exemple.test')).toBeNull()
    const lien = await demanderNouveauMotDePasse('oubli@exemple.test')
    expect((await changerMotDePasse(lien, 'nouveau-solide', 'nouveau-solide')).ok).toBe(true)
    expect((await verifierConnexion('oubli@exemple.test', 'nouveau-solide', 'x')).user.id).toBe(user.id)
    expect((await changerMotDePasse(lien, 'encore-autre', 'encore-autre')).erreurs.formulaire).toBeTruthy()
  })
})
`

  const premierRequis = champs.find((c) => c.requis)

  f[`tests/${route}.test.js`] = js`import { describe, expect, it } from 'vitest'
import { inscrire } from '@/lib/comptes'
import { dansJours } from '@/lib/format'
import { changerStatut, creer, listerPour, listerTout, trouverPour } from '@/lib/${route}'

let numero = 0
async function client(plus = {}) {
  numero++
  const motDePasse = 'motdepasse-solide'
  const { user } = await inscrire({ name: 'Client ' + numero, email: 'client' + numero + '@exemple.test', password: motDePasse, password_confirmation: motDePasse, ...plus })
  return user
}

const donnees = (changements = {}) => ({
${champs.map((c) => `  ${c.nom}: ${valeurExemple(c)},`).join('\n')}
  ...changements,
})

describe('${e.pluriel}', () => {
  it('enregistre ${un(e)} pour le client connecté, avec le premier état', async () => {
    const user = await client()
    const { element } = await creer(user.id, donnees())
    expect(element.user_id).toBe(user.id)
    expect(element.statut).toBe(${chaine(premierStatut)})
    expect((await listerPour(user.id)).map((x) => x.id)).toEqual([element.id])
  })

  it('refuse les données incomplètes', async () => {
    const user = await client()
    const resultat = await creer(user.id, donnees({ ${premierRequis.nom}: '' }))
    expect(resultat.erreurs.${premierRequis.nom}).toBeTruthy()
    expect(await listerPour(user.id)).toEqual([])
  })

  it('ignore l’état et le propriétaire envoyés par le formulaire', async () => {
    const user = await client()
    const autre = await client()
    const { element } = await creer(user.id, donnees({ statut: ${chaine(statuts[statuts.length - 1].code)}, user_id: autre.id }))
    expect(element.user_id).toBe(user.id)
    expect(element.statut).toBe(${chaine(premierStatut)})
  })
})

describe('séparation des données', () => {
  it('un client ne voit jamais ${tous(e)} d’un autre', async () => {
    const alice = await client()
    const bob = await client()
    const { element } = await creer(bob.id, donnees())
    expect(await trouverPour(alice, element.id)).toBeNull()
    expect(await listerPour(alice.id)).toEqual([])
    expect((await trouverPour(bob, element.id)).id).toBe(element.id)
    expect(await trouverPour(alice, '1 OR 1=1')).toBeNull()
  })
})

describe('administration', () => {
  it('l’administrateur voit tout et change l’état ; un client ne peut pas', async () => {
    const admin = await client({ email: 'patron@exemple.test' })
    const user = await client()
    const { element } = await creer(user.id, donnees())
    expect(admin.is_admin).toBe(true)
    expect((await listerTout()).some((x) => x.id === element.id)).toBe(true)
    expect((await trouverPour(admin, element.id)).client).toBe(user.name)
    expect((await changerStatut(user, element.id, ${chaine(statuts[1].code)})).erreur).toBeTruthy()
    expect((await changerStatut(admin, element.id, 'inconnu')).erreur).toBeTruthy()
    expect((await changerStatut(admin, element.id, ${chaine(statuts[1].code)})).ok).toBe(true)
    expect((await trouverPour(user, element.id)).statut).toBe(${chaine(statuts[1].code)})
    expect((await listerTout(${chaine(statuts[1].code)})).map((x) => x.id)).toEqual([element.id])
  })
})
`

  f['tests/securite.test.js'] = js`import { readFileSync, readdirSync, statSync } from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

// Vérifications du code lui-même : aucune requête SQL fabriquée avec du texte venu de l'extérieur,
// les actions des formulaires restent côté serveur, aucune clé écrite dans le code.
function fichiers(dossier) {
  return readdirSync(dossier).flatMap((nom) => {
    const complet = path.join(dossier, nom)
    return statSync(complet).isDirectory() ? fichiers(complet) : [complet]
  })
}
const code = ['app', 'lib', 'components'].flatMap((d) => fichiers(d)).filter((f) => f.endsWith('.js'))

describe('sécurité du code', () => {
  it('les actions des formulaires sont des actions serveur', () => {
    for (const fichier of fichiers('app/actions')) expect(readFileSync(fichier, 'utf8').startsWith("'use server'")).toBe(true)
  })

  it('aucune clé ni aucun mot de passe écrit dans le code', () => {
    for (const fichier of code) {
      const texte = readFileSync(fichier, 'utf8')
      expect(texte, fichier).not.toMatch(/(api[_-]?key|secret|password)\s*[:=]\s*['"][A-Za-z0-9_\-]{12,}['"]/i)
      expect(texte, fichier).not.toMatch(/postgres(ql)?:\/\/[^\s'"]*:[^\s'"]*@/)
    }
  })
})
`

  // ----------------------------------------------------------------------------------------------
  // Documentation et hébergeurs
  f['README.md'] = `# ${app.nom}

Application web faite avec Kekeli (Next.js ${VERSIONS_NEXTJS.next}, base PostgreSQL).

## Ce qu'elle fait
- Comptes : création, connexion, mot de passe oublié (mots de passe chiffrés, essais de connexion limités).
- ${e.action} : formulaire enregistré dans la base de données ; chaque client ne voit que ses ${e.pluriel}.
- Administration (\`/admin\`) : ${tous(e)}, leur état, les comptes.

## L'essayer sur un ordinateur
\`\`\`bash
npm install
cp .env.example .env
npm run dev
\`\`\`
Sans \`DATABASE_URL\`, l'application utilise une base PostgreSQL locale (PGlite) dans le dossier \`.donnees\`.

## Tests
\`\`\`bash
npm test
\`\`\`

## Administrateur
Mettre l'adresse du propriétaire dans la variable \`ADMIN_EMAIL\`, puis créer le compte avec cette adresse sur le site.
Ou : \`npm run admin -- adresse@exemple.com\`.

## Base de données
Les tables sont créées toutes seules au premier démarrage (fichier \`lib/migrations.js\`). Pour changer la base, ajouter une étape à la fin de la liste.

## Mise en ligne
Kekeli donne un guide pas à pas pour chaque hébergeur (Vercel, Render). Aucun mot de passe ni aucune clé ne doit être écrit dans le code : ils vont dans les réglages de l'hébergeur.
`

  f['render.yaml'] = `# Render : « New » → « Blueprint » → choisir ce dépôt. Les secrets se remplissent dans Render, jamais ici.
services:
  - type: web
    name: ${modele.slug}
    runtime: node
    plan: free
    buildCommand: npm ci && npm run build
    startCommand: npm start
    healthCheckPath: /
    envVars:
      - key: NODE_VERSION
        value: "22"
      - key: APP_URL
        sync: false
      - key: ADMIN_EMAIL
        sync: false
      - key: DATABASE_URL
        fromDatabase:
          name: ${modele.slug}-base
          property: connectionString
databases:
  - name: ${modele.slug}-base
    plan: free
`

  return f
}
