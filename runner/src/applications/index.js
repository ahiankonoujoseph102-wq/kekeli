// Sites avec base de données de Kekeli (« applications » dans le code) : le socle, en Laravel ou en Next.js.
// Les fichiers fixes de chaque pile sont dans socles/<pile>/ ; le reste est fabriqué à partir de la
// description de l'application (modeles.json) par laravel.js ou nextjs.js.
import { readFileSync, readdirSync, statSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { fichiersLaravel } from './laravel.js'
import { fichiersNextjs } from './nextjs.js'
import { AGENTS, fichiersAgent } from '../agents/index.js'

const ICI = path.dirname(fileURLToPath(import.meta.url))

export const PILES = {
  laravel: { nom: 'Laravel (PHP) et MySQL', fabrique: fichiersLaravel },
  nextjs: { nom: 'Next.js (Node) et PostgreSQL', fabrique: fichiersNextjs },
  // Agents IA : leurs propres points de départ (runner/src/agents/).
  ...Object.fromEntries(Object.entries(AGENTS).map(([cle, agent]) => [cle, { nom: agent.nom, agent: true }])),
}

/** Une technologie d'agent IA (LangGraph ou CrewAI) ? */
export const estAgent = (pile) => Boolean(PILES[pile]?.agent)

/** Toutes les descriptions de sites avec base de données (aujourd’hui : le socle seul). */
export function modelesApplications() {
  return JSON.parse(readFileSync(path.join(ICI, 'modeles.json'), 'utf8')).modeles
}

export function modeleApplication(slug) {
  const modele = modelesApplications().find((m) => m.slug === slug)
  if (!modele) throw new Error(`Modèle d'application inconnu : ${slug}.`)
  return modele
}

/** Fichiers fixes d'une pile (chemin relatif → contenu en octets). */
function fichiersFixes(pile) {
  const racine = path.join(ICI, 'socles', pile)
  const fichiers = {}
  const parcourir = (dossier) => {
    for (const nom of readdirSync(dossier)) {
      const complet = path.join(dossier, nom)
      if (statSync(complet).isDirectory()) parcourir(complet)
      else fichiers[path.relative(racine, complet).split(path.sep).join('/')] = readFileSync(complet)
    }
  }
  parcourir(racine)
  return fichiers
}

/**
 * Tous les fichiers d'une application : fichiers fixes de la pile, puis fichiers fabriqués à partir
 * de la description (qui remplacent les fixes de même nom). `modele` : un slug ou une description.
 */
export function fichiersApplication(pile, modele) {
  if (!PILES[pile]) throw new Error(`Technologie inconnue : ${pile}.`)
  // Agent IA : son point de départ, au nom donné par le client.
  if (estAgent(pile)) return fichiersAgent(pile, typeof modele === 'object' ? modele?.application?.nom : undefined)
  const description = typeof modele === 'string' ? modeleApplication(modele) : modele
  const fichiers = { ...fichiersFixes(pile), ...PILES[pile].fabrique(description) }
  ajouterInstallation(fichiers, pile, description)
  ajouterLisezMoi(fichiers, pile, description)
  // Le fichier des versions exactes (Node) porte le nom de l'application.
  if (fichiers['package-lock.json']) {
    const verrou = JSON.parse(fichiers['package-lock.json'].toString())
    verrou.name = description.slug
    if (verrou.packages?.['']) verrou.packages[''].name = description.slug
    fichiers['package-lock.json'] = JSON.stringify(verrou, null, 2) + '\n'
  }
  return fichiers
}

const ICONES = path.join(ICI, 'installation')

/** Manifeste de l'application installable (nom, couleurs, icônes), lu par le téléphone. */
export function manifeste(nom) {
  return JSON.stringify(
    {
      name: nom,
      short_name: String(nom).slice(0, 12),
      lang: 'fr',
      start_url: '/',
      scope: '/',
      display: 'standalone',
      background_color: '#ffffff',
      theme_color: '#1c1a17',
      icons: [
        { src: '/icones/icone-192.png', sizes: '192x192', type: 'image/png', purpose: 'any maskable' },
        { src: '/icones/icone-512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
      ],
    },
    null,
    2,
  ) + '\n'
}

/**
 * Petit service de mise en route : rend l'application installable et montre une page simple quand le téléphone est
 * hors ligne. Il ne garde jamais les pages privées ni les données en mémoire (toujours lues sur le serveur).
 */
export const SERVICE_INSTALLATION = `// Application installable (écran d'accueil du téléphone). Rien n'est gardé hors ligne : les pages et les données
// viennent toujours du serveur, pour ne jamais montrer une page privée à la mauvaise personne.
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (evenement) => evenement.waitUntil(self.clients.claim()))
self.addEventListener('fetch', (evenement) => {
  if (evenement.request.mode !== 'navigate') return
  evenement.respondWith(
    fetch(evenement.request).catch(
      () => new Response('<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Hors ligne</title><body style="font-family:system-ui,sans-serif;padding:2rem;text-align:center"><h1>Pas de connexion</h1><p>Vérifiez votre connexion à Internet, puis réessayez.</p></body></html>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } }),
    ),
  )
})
`

const BALISES_INSTALLATION = `<link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#1c1a17">
    <link rel="apple-touch-icon" href="/icones/icone-192.png">
    <script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js')) }</script>`

/** Fichiers et balises qui rendent l'application installable sur l'écran d'accueil (Laravel et Next.js). */
export function ajouterInstallation(fichiers, pile, description) {
  const nom = description.application?.nom ?? 'Mon application'
  const dossier = pile === 'laravel' ? 'public' : 'public'
  fichiers[`${dossier}/manifest.webmanifest`] = manifeste(nom)
  fichiers[`${dossier}/service-worker.js`] = SERVICE_INSTALLATION
  fichiers[`${dossier}/icones/icone-192.png`] = readFileSync(path.join(ICONES, 'icone-192.png'))
  fichiers[`${dossier}/icones/icone-512.png`] = readFileSync(path.join(ICONES, 'icone-512.png'))
  if (pile === 'laravel') {
    const cle = 'resources/views/layouts/app.blade.php'
    fichiers[cle] = String(fichiers[cle]).replace('</head>', `    ${BALISES_INSTALLATION}\n</head>`)
  } else {
    const cle = 'app/layout.js'
    fichiers[cle] = String(fichiers[cle])
      .replace("export const metadata = { title: { default: NOM, template: '%s · ' + NOM } }", "export const metadata = { title: { default: NOM, template: '%s · ' + NOM }, manifest: '/manifest.webmanifest', icons: { apple: '/icones/icone-192.png' } }")
      .replace("export const viewport = { width: 'device-width', initialScale: 1 }", "export const viewport = { width: 'device-width', initialScale: 1, themeColor: '#1c1a17' }")
      .replace("import { utilisateurActuel } from '@/lib/session'", "import { utilisateurActuel } from '@/lib/session'\nimport Installation from './installation'")
      .replace('<body>', '<body>\n        <Installation />')
    fichiers['app/installation.js'] = `'use client'
// Rend l'application installable sur l'écran d'accueil du téléphone (service de mise en route).
import { useEffect } from 'react'

export default function Installation() {
  useEffect(() => {
    if ('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js').catch(() => {})
  }, [])
  return null
}
`
  }
}

/**
 * LISEZ-MOI.md en français : installer, remplir les variables, lancer. Le client récupère toujours son code complet
 * avec ce fichier ; l'agent le tient à jour quand il ajoute une fonction ou une variable.
 */
export function lisezMoi(pile, nom) {
  const laravel = pile === 'laravel'
  return `# ${nom}

Application web faite avec Kekeli. Son code vous appartient : vous pouvez l'héberger où vous voulez.

## Ce qu'il faut
${laravel ? '- PHP 8.3 et Composer.\n- Une base de données MySQL ou PostgreSQL (SQLite suffit pour essayer).' : '- Node.js 22.\n- Une base de données PostgreSQL (par exemple Supabase ou Neon ; une base locale suffit pour essayer).'}

## Installer
\`\`\`bash
${laravel ? 'composer install\ncp .env.example .env\nphp artisan key:generate' : 'npm install\ncp .env.example .env'}
\`\`\`

## Variables à remplir
Ouvrez le fichier \`.env\` et remplissez chaque ligne vide. Chaque variable est expliquée dans \`.env.example\`. Ne mettez jamais ces valeurs dans le code ni sur GitHub : elles vont seulement dans le fichier \`.env\` de votre serveur ou dans les réglages de votre hébergeur.

## Lancer
\`\`\`bash
${laravel ? 'php artisan migrate\nphp artisan serve' : 'npm run dev'}
\`\`\`
Puis ouvrez l'adresse affichée dans votre navigateur.

## Tests
\`\`\`bash
${laravel ? 'php artisan test' : 'npm test'}
\`\`\`

## Installer l'application sur un téléphone
Une fois l'application en ligne (adresse en https), ouvrez-la dans Chrome sur le téléphone, puis touchez « Ajouter à l'écran d'accueil ».
`
}

export function ajouterLisezMoi(fichiers, pile, description) {
  if (!fichiers['LISEZ-MOI.md']) fichiers['LISEZ-MOI.md'] = lisezMoi(pile, description.application?.nom ?? 'Mon application')
}
