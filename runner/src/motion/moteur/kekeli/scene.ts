// Kekeli Motion : la scène entière, montée dans une page à partir de sa description (JSON). Une carte qui se suffit
// à elle-même : la page autour ne change jamais de couleur. Le ciel suit l'heure (jour, coucher, nuit), le soleil et
// la lune se touchent pour passer du jour à la nuit, le doigt ou la souris font le vent.

import { el, pen, shape, paint, inkDefs, f, PEN } from '../lib/ink.ts';
import { pointerWind, seeded, clamp, spring } from '../lib/motion.ts';
import { palette, applyPalette, rgb, mix, toRGB, type Palette } from '../lib/daynight.ts';
import { creerPersonnage, type Personnage } from './personnage.ts';
import { creerElement, FONDS_LARGES, DU_CIEL, type ObjetDecor } from './decor.ts';
import { normaliser } from './normaliser.ts';
import { DIMENSIONS, MOMENTS, type Scene } from './types.ts';

export interface Options {
  /** Une seule image, sans animation (aperçu, image d'une version, mouvement réduit). */
  statique?: boolean;
  /** Le moment de cette image, en secondes depuis le début. */
  temps?: number;
  /** Identifiant unique, quand plusieurs animations sont sur la même page. */
  id?: string;
}

export interface Animation {
  svg: SVGSVGElement;
  /** Arrête l'animation (la carte reste affichée). */
  arreter(): void;
  /** Avance de quelques secondes (essais et captures). */
  avancer(secondes: number): void;
}

let compteur = 0;

export function monter(conteneur: Element, brute: unknown, options: Options = {}): Animation {
  const scene: Scene = normaliser(brute);
  const id = options.id ?? `kekeli-motion-${++compteur}`;
  const [W, H] = DIMENSIONS[scene.format];
  const sol = Math.round(H * (scene.format === 'vertical' ? 0.74 : 0.7));
  const echelle = Math.min(W, H) * (scene.format === 'paysage' ? 0.0042 : 0.0036);

  const svg = el('svg', {
    viewBox: `0 0 ${W} ${H}`,
    width: W,
    height: H,
    class: 'kekeli-motion',
    role: 'img',
    'aria-label': scene.titre,
    style: 'display:block;width:100%;height:auto;border-radius:12px;touch-action:manipulation;-webkit-tap-highlight-color:transparent',
  });
  conteneur.appendChild(svg);
  el('style', {}, svg).textContent = '.pp{fill:var(--paper)}text{user-select:none;-webkit-user-select:none}';
  const { defs, filter } = inkDefs(svg, id);
  const degrade = el('linearGradient', { id: `${id}-ciel`, x1: 0, y1: 0, x2: 0, y2: 1 }, defs);
  const haut = el('stop', { offset: 0 }, degrade);
  const bas = el('stop', { offset: 1 }, degrade);
  const halo = el('radialGradient', { id: `${id}-halo` }, defs);
  el('stop', { offset: 0, 'stop-color': '#ffd98a', 'stop-opacity': 0.75 }, halo);
  el('stop', { offset: 1, 'stop-color': '#ffd98a', 'stop-opacity': 0 }, halo);
  const decoupe = el('clipPath', { id: `${id}-horizon` }, defs);
  el('rect', { x: 0, y: 0, width: W, height: sol }, decoupe);

  // Les couches, du fond vers le devant.
  const ciel = el('rect', { x: 0, y: 0, width: W, height: sol + 2, fill: `url(#${id}-ciel)` }, svg);
  const etoiles = el('g', {}, svg);
  const astres = el('g', { 'clip-path': `url(#${id}-horizon)` }, svg);
  const coucheCiel = el('g', {}, svg);
  const fond = el('g', { filter }, svg);
  const terre = el('g', { filter }, svg);
  const milieu = el('g', { filter }, svg);
  const personnages = el('g', { filter }, svg);
  const devant = el('g', { filter }, svg);
  const bulles = el('g', {}, svg);

  // Les étoiles, toujours les mêmes.
  const r = seeded(7);
  const points = Array.from({ length: 46 }, () => `M${f(r(0, W))} ${f(r(0, sol * 0.62))} m-0.9 0 a0.9 0.9 0 1 0 1.8 0 a0.9 0.9 0 1 0 -1.8 0`).join('');
  const ciblesEtoiles = el('path', { d: points, fill: 'currentColor', opacity: 0 }, etoiles);

  // Le soleil et la lune, sur un arc au-dessus de l'horizon ; les toucher fait passer au jour ou à la nuit.
  const rayon = Math.min(W, H) * 0.045;
  const soleil = el('g', { style: 'cursor:pointer', role: 'button', 'aria-label': 'Soleil' }, astres);
  const rayons = pen(soleil, PEN * 1.2);
  const disque = paint(soleil, '#f3c35b', PEN * 1.2);
  el('circle', { r: rayon * 2, fill: 'transparent', 'pointer-events': 'all' }, soleil);
  const lune = el('g', { style: 'cursor:pointer', role: 'button', 'aria-label': 'Lune' }, astres);
  shape(lune, PEN * 1.2).setAttribute('d', `M0 ${f(-rayon * 0.85)} a${f(rayon * 0.85)} ${f(rayon * 0.85)} 0 1 0 0.01 0Z`);
  pen(lune, PEN).setAttribute('d', `M${f(-rayon * 0.3)} ${f(-rayon * 0.2)} a${f(rayon * 0.16)} ${f(rayon * 0.16)} 0 1 0 0.01 0 M${f(rayon * 0.25)} ${f(rayon * 0.3)} a${f(rayon * 0.1)} ${f(rayon * 0.1)} 0 1 0 0.01 0`);
  el('circle', { r: rayon * 2, fill: 'transparent', 'pointer-events': 'all' }, lune);
  let decalage = 0, cible = 0;
  const basculer = () => { cible += 0.5; reveiller(); };
  soleil.addEventListener('pointerdown', (e) => { e.stopPropagation(); basculer(); });
  lune.addEventListener('pointerdown', (e) => { e.stopPropagation(); basculer(); });

  // Le sol : le papier de la carte, une ligne d'horizon et une texture selon le décor.
  el('rect', { x: 0, y: sol, width: W, height: H - sol, fill: '#fdfdfc' }, terre);
  pen(terre, PEN * 1.1).setAttribute('d', `M0 ${sol} L${W} ${sol}`);
  const t = seeded(13);
  let texture = '';
  if (['jardin', 'parc', 'campagne'].includes(scene.decor)) {
    for (let i = 0; i < 34; i++) { const x = t(0, W), y = t(sol + 8, H - H * 0.08); texture += `M${f(x)} ${f(y)} q1 -3 3 -6 M${f(x + 3)} ${f(y)} q0 -3 -1 -5 `; }
  } else if (['rue', 'ville'].includes(scene.decor)) {
    texture = `M0 ${f(sol + 12)} L${W} ${f(sol + 12)} ` + Array.from({ length: Math.ceil(W / 36) }, (_, i) => `M${i * 36 + 12} ${sol} l0 12`).join(' ');
  } else if (['plage'].includes(scene.decor)) {
    for (let i = 0; i < 90; i++) { const x = t(0, W), y = t(sol + 6, H - H * 0.08); texture += `M${f(x)} ${f(y)} l0.6 0 `; }
  } else if (['village', 'marche'].includes(scene.decor)) {
    for (let i = 0; i < 26; i++) { const x = t(0, W), y = t(sol + 10, H - H * 0.08); texture += `M${f(x)} ${f(y)} q3 -2 6 0 `; }
  }
  if (texture) pen(terre, PEN * 0.6).setAttribute('d', texture);

  // Les éléments du décor, puis les personnages sur deux rangs.
  const contexte = { largeur: W, hauteur: H, sol, echelle, id };
  const objets: ObjetDecor[] = scene.elements.map((e, i) => {
    const parent = DU_CIEL.includes(e.type) ? coucheCiel : FONDS_LARGES.includes(e.type) || e.plan === 'fond' ? fond : e.plan === 'devant' ? devant : milieu;
    return creerElement(e, parent, contexte, 31 + i * 17);
  });
  const rangs = [el('g', {}, personnages), el('g', {}, personnages)];
  const acteurs: Personnage[] = scene.personnages.map((p, i) => creerPersonnage(p, {
    couche: rangs[i % 2],
    bulles,
    largeur: W,
    sol: sol + (H - sol) * (i % 2 ? 0.34 : 0.1),
    echelle: echelle * (i % 2 ? 1.08 : 1),
  }));

  // Une seule ligne discrète en bas, jamais un bouton.
  if (scene.indication) {
    const ligne = el('text', { x: W / 2, y: H - Math.max(14, H * 0.03), 'font-size': Math.round(Math.min(W, H) * 0.027), 'font-family': 'Inter, system-ui, sans-serif', 'text-anchor': 'middle', fill: 'currentColor', opacity: 0.55 }, svg);
    ligne.textContent = scene.indication;
  }

  const vent = pointerWind(svg, 0.25);
  const papier = toRGB(scene.papier);
  const gonfle = spring(40, 6);

  const dessiner = (temps: number, dt: number) => {
    decalage += (cible - decalage) * Math.min(1, dt * 1.6);
    const tau = (MOMENTS[scene.ciel.moment] + (scene.ciel.anime ? temps / scene.ciel.duree_jour : 0) + decalage) % 1;
    const P: Palette = palette(tau);
    // Un papier chaud le jour (celui choisi), qui garde la couleur de la nuit.
    P.paper = mix(P.paper, papier, (1 - P.inkT) * 0.8);
    applyPalette(svg, P);
    haut.setAttribute('stop-color', rgb(P.top));
    bas.setAttribute('stop-color', rgb(P.bottom));
    ciblesEtoiles.setAttribute('opacity', f(P.night * 0.9));
    const v = vent.step(temps);

    // Le soleil à τ, la lune à l'opposé ; au-dessus de l'horizon seulement (découpe).
    const place = (u: number): [number, number] => {
      const a = (u - 0.25) * Math.PI * 2;
      return [W / 2 - Math.cos(a) * W * 0.42, sol - Math.sin(a) * sol * 0.78];
    };
    const [sx, sy] = place(tau);
    const g = 1 + gonfle.step(0, dt) * 0.04;
    soleil.setAttribute('transform', `translate(${f(sx)} ${f(sy)}) scale(${f(g)})`);
    disque.setAttribute('d', `M0 ${f(-rayon)} a${f(rayon)} ${f(rayon)} 0 1 0 0.01 0Z`);
    disque.setAttribute('fill', rgb(mix(toRGB('#f3c35b'), toRGB('#e9845a'), P.sunset)));
    rayons.setAttribute('d', Array.from({ length: 8 }, (_, i) => {
      const a = i * Math.PI / 4 + temps * 0.15;
      return `M${f(Math.cos(a) * rayon * 1.3)} ${f(Math.sin(a) * rayon * 1.3)} L${f(Math.cos(a) * rayon * 1.65)} ${f(Math.sin(a) * rayon * 1.65)}`;
    }).join(' '));
    const [lx, ly] = place(tau + 0.5);
    lune.setAttribute('transform', `translate(${f(lx)} ${f(ly)})`);

    for (const o of objets) o.maj(temps, dt, P, v);
    for (const a of acteurs) a.dessiner(temps, dt, P, v);
  };

  // La boucle : seulement quand la carte est visible et l'onglet ouvert (batterie du téléphone).
  let temps = 0, prec = 0, idBoucle = 0, visible = true, arretee = false, eveilJusqua = 0;
  const reduit = typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches;
  const tourner = (now: number) => {
    idBoucle = 0;
    const dt = prec ? Math.min(0.05, (now - prec) / 1000) : 1 / 60;
    prec = now;
    temps += dt;
    dessiner(temps, dt);
    if (!arretee && visible && (!reduit || temps < eveilJusqua) && !(typeof document !== 'undefined' && document.hidden)) idBoucle = requestAnimationFrame(tourner);
    else prec = 0;
  };
  function reveiller() {
    if (reduit) eveilJusqua = temps + 3;
    if (!options.statique && !idBoucle && !arretee) idBoucle = requestAnimationFrame(tourner);
  }
  svg.addEventListener('pointerdown', () => { gonfle.kick(0); reveiller(); });

  const avancer = (secondes: number) => {
    for (let i = 0, n = Math.round(secondes * 30); i < n; i++) dessiner((temps += 1 / 30), 1 / 30);
  };

  if (options.statique || reduit) {
    avancer(clamp(options.temps ?? 2.5, 0, 120));
  } else {
    dessiner(0, 1 / 60);
    idBoucle = requestAnimationFrame(tourner);
    if (typeof IntersectionObserver === 'function') {
      new IntersectionObserver((entrees) => { visible = entrees.some((e) => e.isIntersecting); if (visible) reveiller(); }).observe(svg);
    }
    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', () => { if (!document.hidden) reveiller(); });
  }

  return {
    svg,
    arreter() { arretee = true; if (idBoucle) cancelAnimationFrame(idBoucle); idBoucle = 0; },
    avancer,
  };
}
