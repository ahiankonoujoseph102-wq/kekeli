// Fabrique les modèles de la galerie à partir de scripts/modeles/donnees.mjs, avec le modèle de mise en page de
// Kekeli (runner/src/modeles/base.css) et les icônes Lucide. Résultat dans resources/modeles-sites/<modèle>/ :
//   site/            les fichiers du site, copiés tels quels dans le projet du client ;
//   modele.json      nom, description, questions et contenu d'exemple à remplacer avant toute publication.
// Les images (site/images/*.jpg) se fabriquent à part : node scripts/modeles/images.mjs
//
//   node scripts/modeles/fabriquer.mjs
import { copyFileSync, mkdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { COURRIEL, MODELES, TELEPHONE, WHATSAPP } from './donnees.mjs'

const racine = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const sortie = path.join(racine, 'resources/modeles-sites')
const icones = JSON.parse(readFileSync(path.join(racine, 'runner/src/modeles/icones/lucide.json'), 'utf8'))

const e = (texte) => String(texte).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
const ancre = (texte) => texte.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')

function icone(nom, classe = 'icone') {
  if (!icones[nom]) throw new Error(`Icône inconnue : ${nom}`)
  return `<svg class="${classe}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icones[nom]}</svg>`
}

const lienWhatsapp = (texte) => `https://wa.me/${WHATSAPP}?text=${encodeURIComponent(texte)}`

function page(m) {
  const sections = m.menu.map((titre) => ({ titre, id: ancre(titre) }))
  const [idOffre, idApropos, idGalerie, idContact] = sections.map((s) => s.id)
  const offre = m.offre.forme === 'prix'
    ? `<div class="carte liste-prix">${m.offre.elements.map((x) => `
          <div class="prix"><div><strong class="prix-nom">${e(x.nom)}</strong><br><span class="texte-doux">${e(x.detail)}</span></div><span class="prix-montant">${e(x.prix)}</span></div>`).join('')}
        </div>`
    : `<div class="grille">${m.offre.elements.map((x) => `
          <article class="carte">
            ${icone(x.icone, 'icone icone--grande')}
            <h3>${e(x.nom)}</h3>
            <p class="texte-doux">${e(x.detail)}</p>${x.prix ? `
            <p class="montant">${e(x.prix)}</p>` : ''}
          </article>`).join('')}
        </div>`

  return `<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>${e(m.activite.nom)} – ${e(m.accueil.surTitre)}</title>
  <meta name="description" content="${e(m.accueil.texte)}">
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/modele.css">
</head>
<body>
  <header class="entete">
    <div class="conteneur">
      <a class="logo" href="#accueil">${icone('sun')}<span>${e(m.activite.nom)}</span></a>
      <button class="bouton-menu" type="button" aria-label="Ouvrir le menu">☰</button>
      <nav aria-label="Menu principal">
        <ul class="menu">${sections.map((s) => `
          <li><a href="#${s.id}">${e(s.titre)}</a></li>`).join('')}
          <li><a class="bouton bouton--whatsapp" href="${lienWhatsapp(`Bonjour ${m.activite.nom}, je vous contacte depuis votre site.`)}">${icone('message-circle')} WhatsApp</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main>
    <section id="accueil" class="accueil" style="background-image: url('${m.accueil.image.fichier.startsWith('images/') ? '' : 'images/'}${m.accueil.image.fichier}')">
      <div class="conteneur">
        <span class="sur-titre">${e(m.accueil.surTitre)}</span>
        <h1>${e(m.accueil.titre)}</h1>
        <p>${e(m.accueil.texte)}</p>
        <div class="boutons">
          <a class="bouton bouton--whatsapp" href="${lienWhatsapp(`Bonjour ${m.activite.nom}, ${m.accueil.bouton.toLowerCase()}, s'il vous plaît.`)}">${icone('message-circle')} ${e(m.accueil.bouton)}</a>
          <a class="bouton bouton--clair" href="#${idOffre}">${e(m.offre.surTitre)}</a>
        </div>
      </div>
    </section>

    <section id="${idOffre}" class="section">
      <div class="conteneur">
        <div class="centre">
          <span class="sur-titre">${e(m.offre.surTitre)}</span>
          <h2>${e(m.offre.titre)}</h2>
        </div>
        ${offre}
      </div>
    </section>

    <section id="${idApropos}" class="section section--alternee">
      <div class="conteneur deux-colonnes">
        <img src="images/${m.apropos.image.fichier}" alt="${e(m.apropos.image.alt)}" width="1600" height="900" loading="lazy" class="arrondie">
        <div>
          <span class="sur-titre">${e(m.apropos.surTitre)}</span>
          <h2>${e(m.apropos.titre)}</h2>${m.apropos.textes.map((t) => `
          <p>${e(t)}</p>`).join('')}
          <ul class="points">${m.apropos.points.map((p) => `
            <li>${icone('check')} ${e(p)}</li>`).join('')}
          </ul>
        </div>
      </div>
    </section>

    <section id="${idGalerie}" class="section">
      <div class="conteneur">
        <div class="centre">
          <span class="sur-titre">${e(m.menu[2])}</span>
          <h2>${e(m.galerie.titre)}</h2>
        </div>
        <div class="grille galerie">${m.galerie.images.map((i) => `
          <img src="images/${i.fichier}" alt="${e(i.alt)}" width="1600" height="900" loading="lazy">`).join('')}
        </div>
      </div>
    </section>

    <section class="section section--alternee" aria-label="Témoignages">
      <div class="conteneur">
        <div class="grille">${m.temoignages.map((t) => `
          <figure class="carte temoignage">
            ${icone('quote', 'icone icone--grande')}
            <blockquote>${e(t.texte)}</blockquote>
            <figcaption class="texte-doux">${e(t.auteur)}</figcaption>
          </figure>`).join('')}
        </div>
      </div>
    </section>

    <section id="${idContact}" class="section">
      <div class="conteneur deux-colonnes">
        <div>
          <span class="sur-titre">${e(m.menu[3])}</span>
          <h2>Venez nous voir ou écrivez-nous</h2>
          <ul class="coordonnees">
            <li>${icone('map-pin')} ${e(m.activite.adresse)}</li>
            <li>${icone('phone')} <a href="tel:${TELEPHONE.replace(/\s/g, '')}">${e(TELEPHONE)}</a></li>
            <li>${icone('mail')} <a href="mailto:${COURRIEL}">${e(COURRIEL)}</a></li>${m.horaires.map((h) => `
            <li>${icone('clock')} ${e(h)}</li>`).join('')}
          </ul>
        </div>
        <div class="carte centre">
          ${icone('message-circle', 'icone icone--grande')}
          <h3>Le plus simple : WhatsApp</h3>
          <p class="texte-doux">Nous répondons rapidement, tous les jours.</p>
          <a class="bouton bouton--whatsapp" href="${lienWhatsapp(`Bonjour ${m.activite.nom}, je voudrais des informations.`)}">${icone('message-circle')} Écrire sur WhatsApp</a>
        </div>
      </div>
    </section>
  </main>

  <footer class="pied">
    <div class="conteneur">
      <div class="grille">
        <div>
          <span class="logo">${icone('sun')} ${e(m.activite.nom)}</span>
          <p>${e(m.accueil.texte)}</p>
        </div>
        <div>
          <h3>Contact</h3>
          <p>${e(m.activite.adresse)}<br>${e(TELEPHONE)}</p>
        </div>
      </div>
      <p class="texte-doux">© <span data-annee>2026</span> ${e(m.activite.nom)}. Tous droits réservés.</p>
    </div>
  </footer>

  <script>
    document.querySelector('.bouton-menu')?.addEventListener('click', () => document.querySelector('.menu')?.classList.toggle('ouvert'))
    document.querySelectorAll('.menu a').forEach((a) => a.addEventListener('click', () => document.querySelector('.menu')?.classList.remove('ouvert')))
    document.querySelector('[data-annee]').textContent = new Date().getFullYear()
  </script>
</body>
</html>
`
}

function css(m) {
  return `/* Couleurs et petits ajouts propres à ce site (le modèle de mise en page de Kekeli est dans base.css). */
:root {
  --couleur-principale: ${m.couleurs.principale};
  --couleur-principale-foncee: ${m.couleurs.foncee};
  --couleur-accent: ${m.couleurs.accent};
  --fond: ${m.couleurs.fond};
}

.logo .icone { color: var(--couleur-principale); }
.arrondie { border-radius: var(--rayon); box-shadow: var(--ombre); aspect-ratio: 16 / 9; object-fit: cover; width: 100%; }
.liste-prix { max-width: 760px; margin-inline: auto; }
.prix-montant { color: var(--couleur-principale); font-weight: 700; }
.montant { font-weight: 700; color: var(--couleur-principale); margin: 0; }
.points, .coordonnees { list-style: none; padding: 0; margin: 1rem 0 0; display: grid; gap: 0.6rem; }
.points .icone { color: var(--couleur-accent); }
.coordonnees .icone { color: var(--couleur-principale); }
.galerie img { border-radius: var(--rayon); aspect-ratio: 4 / 3; object-fit: cover; width: 100%; box-shadow: var(--ombre); }
.temoignage { margin: 0; }
.temoignage blockquote { margin: 0.5rem 0 1rem; font-size: 1.05rem; }
.temoignage .icone--grande { color: var(--couleur-accent); }
`
}

/** Contenu d'exemple à remplacer avant toute publication : Kekeli refuse de publier tant qu'il en reste. */
function exemples(m) {
  return [
    { genre: 'nom', valeur: m.activite.nom },
    { genre: 'adresse', valeur: m.activite.adresse },
    { genre: 'telephone', valeur: TELEPHONE },
    { genre: 'courriel', valeur: COURRIEL },
    ...m.temoignages.map((t) => ({ genre: 'temoignage', valeur: t.texte })),
    ...m.apropos.textes.slice(0, 1).map((t) => ({ genre: 'presentation', valeur: t })),
  ]
}

function licences(m) {
  const images = [m.accueil.image, m.apropos.image, ...m.galerie.images]
  return `# Licences

Toutes les images et les icônes de ce site viennent de sources libres ; aucune attribution n'est obligatoire.

## Images
${images.map((i) => `- images/${i.fichier} : image créée par Kekeli (FLUX.1 schnell sur Cloudflare Workers AI) ; illustration, ce n'est pas une vraie photo ; aucune attribution obligatoire`).join('\n')}

## Icônes
- Icônes Lucide (https://lucide.dev), licence ISC ; aucune attribution obligatoire.
`
}

for (const m of MODELES) {
  const dossier = path.join(sortie, m.slug)
  mkdirSync(path.join(dossier, 'site/css'), { recursive: true })
  mkdirSync(path.join(dossier, 'site/images'), { recursive: true })
  writeFileSync(path.join(dossier, 'site/index.html'), page(m))
  writeFileSync(path.join(dossier, 'site/css/modele.css'), css(m))
  copyFileSync(path.join(racine, 'runner/src/modeles/base.css'), path.join(dossier, 'site/css/base.css'))
  writeFileSync(path.join(dossier, 'site/LICENCES.md'), licences(m))
  const manquantes = [m.accueil.image, m.apropos.image, ...m.galerie.images].filter((i) => !existsSync(path.join(dossier, 'site/images', i.fichier)))
  writeFileSync(path.join(dossier, 'modele.json'), `${JSON.stringify({
    slug: m.slug,
    nom: m.nom,
    description: m.description,
    couleurs: m.couleurs,
    questions: m.questions,
    exemples: exemples(m),
  }, null, 2)}\n`)
  console.log(`${m.slug} : fabriqué${manquantes.length ? ` (${manquantes.length} image(s) à fabriquer)` : ''}`)
}
