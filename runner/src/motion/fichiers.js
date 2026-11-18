// Kekeli Motion : les fichiers d'une version. Une page autonome (aperçu dans Kekeli, et à ouvrir telle quelle), un
// script à poser dans un site, une image fixe en SVG, et le code complet en TypeScript dans un ZIP, avec son LISEZ-MOI.
// Le modèle n'écrit jamais de code : toutes ces sorties viennent du moteur de Kekeli et de la scène vérifiée.

import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { DOSSIER_CONSTRUIT, DOSSIER_MOTEUR, MODULES } from './construction.js'
import { creerZip } from './zip.js'
import { imageFixe } from './image-fixe.js'

let moteurNavigateur = null
export async function lireMoteur() {
  moteurNavigateur ??= await readFile(path.join(DOSSIER_CONSTRUIT, 'kekeli-motion.js'), 'utf8')
  return moteurNavigateur
}

/** Un JSON posé dans une balise <script> : jamais de « </script> » ni de « <!-- » possibles. */
export function jsonDansScript(valeur) {
  return JSON.stringify(valeur).replace(/</g, '\\u003c').replace(/>/g, '\\u003e').replace(/&/g, '\\u0026').replace(/\u2028/g, '\\u2028').replace(/\u2029/g, '\\u2029')
}

const echapper = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c])

/** Le nom d'une animation dans un site : « jardin-au-coucher-du-soleil » (lettres, chiffres et tirets seulement). */
export function nomAnimation(titre) {
  const nom = String(titre ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48)
  return nom || 'animation'
}

/** La page autonome : la carte au centre, sur un fond qui ne change jamais. */
export async function pageAnimation(scene) {
  const moteur = await lireMoteur()
  return `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${echapper(scene.titre)}</title>
<style>
  html, body { margin: 0; background: #f4f1ea; }
  body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 12px; box-sizing: border-box; }
  .kekeli-carte { width: 100%; max-width: ${scene.format === 'vertical' ? '420px' : scene.format === 'carre' ? '560px' : '960px'}; }
</style>
</head>
<body>
<div class="kekeli-carte" data-kekeli-motion="${echapper(nomAnimation(scene.titre))}"><script type="application/json">${jsonDansScript(scene)}</script></div>
<script>
// Dans un cadre (aperçu de Kekeli, page d'un site) : jamais de barre de défilement autour de la carte.
if (window.self !== window.top) document.documentElement.style.overflow = 'hidden';
${moteur}
</script>
</body>
</html>
`
}

/** Le script à poser dans un site : le moteur, puis la scène enregistrée sous son nom. */
export async function scriptIntegration(scene) {
  const moteur = await lireMoteur()
  return `${moteur}\nwindow.KekeliMotion.enregistrer(${JSON.stringify(nomAnimation(scene.titre))}, ${jsonDansScript(scene)});\n`
}

/** Le bout de HTML à poser dans une page pour afficher l'animation. */
export function htmlIntegration(scene, chemin = 'animations') {
  const nom = nomAnimation(scene.titre)
  return `<div data-kekeli-motion="${nom}" style="max-width:960px;margin:0 auto"></div>\n<script src="${chemin}/${nom}.js" defer></script>`
}

export function lisezMoi(scene) {
  const nom = nomAnimation(scene.titre)
  return `# ${scene.titre}

Animation créée avec Kekeli Motion : un dessin à l'encre, en SVG, qui bouge en direct dans le navigateur.

## Ce que l'on peut toucher

- les personnages : ils réagissent (saluer, sauter, soulever leur chapeau, se retourner, parler) ;
- le soleil ou la lune : le jour passe à la nuit, et la nuit au jour ;
- les arbres, les fleurs, les lampadaires, les fenêtres, les oiseaux, le chat : chacun réagit à sa façon ;
- le doigt ou la souris qui passe sur la scène fait le vent.

## La mettre dans un site, sans rien installer

1. Copiez le fichier \`dist/${nom}.js\` dans le dossier \`animations\` de votre site.
2. Collez ces deux lignes à l'endroit de la page où l'animation doit apparaître :

\`\`\`html
${htmlIntegration(scene)}
\`\`\`

L'animation prend toute la largeur disponible (960 pixels au plus) et garde ses proportions. Elle s'arrête toute seule quand elle n'est pas visible, et reste immobile si le téléphone demande moins d'animations.

## Le code en TypeScript

- \`src/lib/\` : la bibliothèque de dessin à l'encre (tracés, marche et course, ressorts, jour et nuit) ;
- \`src/kekeli/\` : les personnages, les décors et la scène de Kekeli Motion ;
- \`src/scene.json\` : la description de cette animation (décor, ciel, personnages, éléments). On peut la modifier à la main : les valeurs inconnues sont remplacées par des valeurs sûres ;
- \`src/main.ts\` : monte l'animation dans la page.

Pour le modifier et le reconstruire (Node.js 22 ou plus récent) :

\`\`\`sh
npm install
npm run dev     # aperçu sur http://localhost:5173
npm run build   # fabrique le dossier dist/
\`\`\`

## Licences

Voir \`LICENCES.md\`.
`
}

export const LICENCES = `# Licences

## line-art-animation

Le dossier \`src/lib/\` et une partie du dossier \`src/kekeli/\` reprennent le code de line-art-animation
(https://github.com/JDhruv14/line-art-animation), sous licence MIT :

MIT License

Copyright (c) 2026 JDhruv14

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## Le reste

Les personnages, les décors et la scène ont été dessinés par Kekeli pour cette animation. Aucune image, aucune
police et aucun son extérieurs ne sont utilisés (la police est celle du téléphone ou de l'ordinateur).
`

/** Le projet TypeScript complet, prêt à modifier et à reconstruire. */
export async function codeTypeScript(scene) {
  const nom = nomAnimation(scene.titre)
  const fichiers = []
  for (const module of MODULES) fichiers.push([`src/${module}.ts`, await readFile(path.join(DOSSIER_MOTEUR, `${module}.ts`), 'utf8')])
  fichiers.push(['src/lib/LICENSE', await readFile(path.join(DOSSIER_MOTEUR, 'lib', 'LICENSE'), 'utf8')])
  fichiers.push(['src/scene.json', `${JSON.stringify(scene, null, 2)}\n`])
  fichiers.push(['src/main.ts', `// Monte l'animation dans la page : chaque <div data-kekeli-motion="${nom}"> la reçoit.
import scene from './scene.json';
import { enregistrer } from './kekeli/index.ts';

enregistrer(${JSON.stringify(nom)}, scene);
`])
  fichiers.push(['index.html', `<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${echapper(scene.titre)}</title>
<style>html, body { margin: 0; background: #f4f1ea; } main { max-width: 960px; margin: 0 auto; padding: 12px; }</style>
</head>
<body>
<main><div data-kekeli-motion="${echapper(nom)}"></div></main>
<script type="module" src="/src/main.ts"></script>
</body>
</html>
`])
  fichiers.push(['package.json', `${JSON.stringify({
    name: nom,
    version: '1.0.0',
    private: true,
    type: 'module',
    scripts: { dev: 'vite', build: 'tsc --noEmit && vite build', typecheck: 'tsc --noEmit' },
    devDependencies: { typescript: '5.9.3', vite: '7.1.9' },
  }, null, 2)}\n`])
  fichiers.push(['tsconfig.json', `${JSON.stringify({
    compilerOptions: {
      target: 'ES2022', module: 'ESNext', moduleResolution: 'bundler', lib: ['ES2022', 'DOM', 'DOM.Iterable'],
      strict: true, noEmit: true, allowImportingTsExtensions: true, resolveJsonModule: true, verbatimModuleSyntax: true, skipLibCheck: true,
    },
    include: ['src'],
  }, null, 2)}\n`])
  fichiers.push(['vite.config.ts', `import { defineConfig } from 'vite';

// Un seul fichier JavaScript, à poser dans n'importe quel site.
export default defineConfig({
  build: { lib: { entry: 'src/main.ts', name: 'KekeliMotionScene', formats: ['iife'], fileName: () => '${nom}.js' } },
});
`])
  fichiers.push([`dist/${nom}.js`, await scriptIntegration(scene)])
  fichiers.push(['animation.html', await pageAnimation(scene)])
  fichiers.push(['animation.svg', await imageFixe(scene)])
  fichiers.push(['LISEZ-MOI.md', lisezMoi(scene)])
  fichiers.push(['LICENCES.md', LICENCES])
  return creerZip(fichiers.map(([chemin, contenu]) => ({ chemin: `${nom}/${chemin}`, contenu })))
}
