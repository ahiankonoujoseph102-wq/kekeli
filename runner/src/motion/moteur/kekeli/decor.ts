// Kekeli Motion : les éléments du décor (arbres, palmiers, maisons, cases, lampadaires, étals, oiseaux, nuages…),
// dessinés à l'encre avec la bibliothèque de line-art-animation (licence MIT). Chacun réagit au vent (le doigt ou la
// souris qui passe) et à un toucher : le monde réagit, jamais un bouton.

import { el, pen, shape, paint, limb, f, PEN, PAPER } from '../lib/ink.ts';
import { pendulum, spring, seeded, wobble, clamp } from '../lib/motion.ts';
import { nightTint, mix, toRGB, rgb, type Palette } from '../lib/daynight.ts';
import type { Element as Description } from './types.ts';

export interface ObjetDecor {
  maj(t: number, dt: number, P: Palette, vent: number): void;
}

export interface Contexte {
  largeur: number;
  hauteur: number;
  sol: number;
  echelle: number;
  /** Identifiant unique de l'animation (plusieurs animations sur une même page). */
  id: string;
}

const LUMIERE = '#f4c867';
const vert = (c: string) => c || '#a9c48a';

/** Une fenêtre éclairée la nuit : la couleur passe du papier à une lumière chaude. */
const fenetre = (P: Palette, allumee: number) => rgb(mix(P.paper, toRGB(LUMIERE), clamp(allumee, 0, 1)));

/** Place un groupe : son point (0, 0) sur le sol, à la taille voulue. */
function groupe(parent: Element, x: number, y: number, k: number, toucher?: () => void): SVGGElement {
  const g = el('g', { transform: `translate(${f(x)} ${f(y)}) scale(${f(k)})` }, parent);
  if (toucher) {
    g.setAttribute('style', 'cursor:pointer');
    g.addEventListener('pointerdown', (e) => { e.stopPropagation(); toucher(); });
  }
  return g;
}

/** Contour en lobes (feuillage, buisson, nuage), toujours le même pour une même graine. */
function lobes(cx: number, cy: number, rx: number, ry: number, n: number, graine: number): string {
  const r = seeded(graine);
  const pts: Array<[number, number]> = [];
  for (let i = 0; i < n; i++) {
    const a = (i / n) * Math.PI * 2 + r(-0.1, 0.1);
    pts.push([cx + Math.cos(a) * rx * r(0.86, 1.04), cy + Math.sin(a) * ry * r(0.86, 1.04)]);
  }
  let d = `M${f(pts[0][0])} ${f(pts[0][1])}`;
  for (let i = 0; i < n; i++) {
    const a = pts[i], b = pts[(i + 1) % n];
    const mx = (a[0] + b[0]) / 2, my = (a[1] + b[1]) / 2;
    const ox = mx - cx, oy = my - cy, l = Math.hypot(ox, oy) || 1;
    d += ` Q${f(mx + (ox / l) * rx * 0.28)} ${f(my + (oy / l) * ry * 0.28)} ${f(b[0])} ${f(b[1])}`;
  }
  return d + 'Z';
}

export function creerElement(d: Description, parent: SVGGElement, ctx: Contexte, graine: number): ObjetDecor {
  const plan = d.plan === 'fond' ? 0.62 : d.plan === 'devant' ? 1.18 : 1;
  const k = ctx.echelle * d.taille * plan;
  const x = d.x * ctx.largeur;
  const y = d.plan === 'fond' ? ctx.sol - ctx.hauteur * 0.035 : d.plan === 'devant' ? ctx.sol + (ctx.hauteur - ctx.sol) * 0.62 : ctx.sol;
  const fabrique = FABRIQUES[d.type];
  return fabrique ? fabrique(d, parent, ctx, { x, y, k }, graine) : { maj() {} };
}

type Pose = { x: number; y: number; k: number };
type Fabrique = (d: Description, parent: SVGGElement, ctx: Contexte, pose: Pose, graine: number) => ObjetDecor;

const FABRIQUES: Partial<Record<Description['type'], Fabrique>> = {
  arbre(d, parent, _ctx, { x, y, k }, graine) {
    const balance = pendulum(6, 1.6);
    const feuilles: Array<{ g: SVGPathElement; t: number; x: number; v: number }> = [];
    const g = groupe(parent, x, y, k, () => { balance.kick(1.4); for (const fe of feuilles) if (fe.t < 0) { fe.t = 0; fe.x = (Math.random() - 0.5) * 50; fe.v = 0.6 + Math.random(); } });
    const tronc = el('g', {}, g);
    shape(tronc).setAttribute('d', 'M-5 0 Q-3 -26 -4 -48 L4 -48 Q3 -26 6 0Z');
    pen(tronc, PEN * 0.8).setAttribute('d', 'M-2 -40 Q-10 -52 -16 -58 M2 -44 Q10 -55 15 -62');
    const couronne = el('g', {}, g);
    const masse = paint(couronne, vert(d.couleur));
    masse.setAttribute('d', lobes(0, -78, 38, 30, 11, graine));
    pen(couronne, PEN * 0.6).setAttribute('d', 'M-18 -80 q4 -3 8 0 M6 -92 q4 -3 8 0 M-4 -66 q4 -3 8 0 M14 -72 q4 -3 8 0');
    for (let i = 0; i < 4; i++) feuilles.push({ g: paint(g, vert(d.couleur), PEN * 0.6), t: -1, x: 0, v: 1 });
    return {
      maj(t, dt, P, vent) {
        const a = balance.step(vent * 0.5 + wobble(t * 0.6 + graine) * 0.15, dt);
        couronne.setAttribute('transform', `rotate(${f(a * 4)} 0 -48)`);
        masse.setAttribute('fill', nightTint(vert(d.couleur), P, 0.7));
        for (const fe of feuilles) {
          if (fe.t < 0) { fe.g.setAttribute('d', ''); continue; }
          fe.t += dt;
          const yy = -70 + fe.t * 34 * fe.v, xx = fe.x + Math.sin(fe.t * 4) * 6 + vent * fe.t * 12;
          fe.g.setAttribute('d', `M${f(xx)} ${f(yy)} q3 -3 6 0 q-3 3 -6 0Z`);
          if (yy > -2) fe.t = -1;
        }
      },
    };
  },

  palmier(d, parent, _ctx, { x, y, k }, graine) {
    const balance = pendulum(5, 1.2);
    const g = groupe(parent, x, y, k, () => balance.kick(1.6));
    const tronc = limb(g, 7, PEN);
    tronc.draw([[0, 0], [5, -60], [14, -118]], [0, 0]);
    const anneaux = pen(g, PEN * 0.6);
    anneaux.setAttribute('d', Array.from({ length: 9 }, (_, i) => { const u = (i + 1) / 10, cx = 14 * u * u + 2 * u, cy = -118 * u; return `M${f(cx - 3)} ${f(cy + 1)} q3 2 6 0`; }).join(' '));
    const tete = el('g', {}, g);
    const palmes = Array.from({ length: 6 }, () => paint(tete, vert(d.couleur || '#8db36f'), PEN * 0.8));
    return {
      maj(t, dt, P, vent) {
        const a = balance.step(vent * 0.6 + wobble(t * 0.5 + graine) * 0.2, dt);
        palmes.forEach((p, i) => {
          const angle = -2.6 + i * 0.95 + a * 0.25 + Math.sin(t * 1.3 + i) * 0.03;
          const l = 46 - Math.abs(i - 2.5) * 3;
          const tx = 14 + Math.cos(angle) * l, ty = -118 + Math.sin(angle) * l * 0.55 + 18;
          const mx = 14 + Math.cos(angle) * l * 0.5, my = -118 + Math.sin(angle) * l * 0.5 - 8;
          p.setAttribute('d', `M14 -118 Q${f(mx)} ${f(my - 5)} ${f(tx)} ${f(ty)} Q${f(mx)} ${f(my + 4)} 14 -116Z`);
          p.setAttribute('fill', nightTint(vert(d.couleur || '#8db36f'), P, 0.7));
        });
      },
    };
  },

  maison(d, parent, _ctx, { x, y, k }) {
    let allumee = 0;
    const g = groupe(parent, x, y, k, () => { allumee = allumee > 0.5 ? 0 : 1; });
    shape(g).setAttribute('d', 'M-40 0 L-39 -54 L39 -55 L40 0Z');
    const toit = paint(g, d.couleur || '#b5563c');
    toit.setAttribute('d', 'M-48 -52 L0 -88 L48 -53 Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M20 -74 L20 -88 L28 -88 L28 -68');
    const porte = paint(g, '#7a5236', PEN * 0.9);
    porte.setAttribute('d', 'M-8 0 L-8 -30 Q0 -34 8 -30 L8 0Z');
    const vitres = [paint(g, PAPER, PEN * 0.9), paint(g, PAPER, PEN * 0.9)];
    vitres[0].setAttribute('d', 'M-32 -40 L-18 -40 L-18 -26 L-32 -26Z');
    vitres[1].setAttribute('d', 'M18 -40 L32 -40 L32 -26 L18 -26Z');
    pen(g, PEN * 0.6).setAttribute('d', 'M-25 -40 L-25 -26 M-32 -33 L-18 -33 M25 -40 L25 -26 M18 -33 L32 -33');
    return {
      maj(_t, _dt, P) {
        toit.setAttribute('fill', nightTint(d.couleur || '#b5563c', P));
        vitres.forEach((v) => v.setAttribute('fill', fenetre(P, Math.max(P.lamp, allumee))));
      },
    };
  },

  case(d, parent, _ctx, { x, y, k }, graine) {
    let allumee = 0;
    const g = groupe(parent, x, y, k, () => { allumee = allumee > 0.5 ? 0 : 1; });
    shape(g).setAttribute('d', 'M-30 0 Q-33 -20 -29 -36 L29 -36 Q33 -20 30 0Z');
    const porte = paint(g, PAPER, PEN * 0.9);
    porte.setAttribute('d', 'M-7 0 L-7 -18 Q0 -26 7 -18 L7 0Z');
    const toit = paint(g, d.couleur || '#c9a46a');
    toit.setAttribute('d', 'M-42 -30 Q-20 -60 0 -76 Q20 -60 42 -30 Q0 -40 -42 -30Z');
    const r = seeded(graine);
    pen(g, PEN * 0.5).setAttribute('d', Array.from({ length: 9 }, (_, i) => { const u = -32 + i * 8 + r(-1, 1); return `M${f(u)} ${f(-33 + Math.abs(u) * 0.05)} L${f(u * 0.45)} ${f(-56 + Math.abs(u) * 0.3)}`; }).join(' '));
    return {
      maj(_t, _dt, P) {
        toit.setAttribute('fill', nightTint(d.couleur || '#c9a46a', P));
        porte.setAttribute('fill', fenetre(P, Math.max(P.lamp * 0.8, allumee)));
      },
    };
  },

  lampadaire(_d, parent, ctx, { x, y, k }) {
    let force = 1;
    const halo = el('circle', { cx: f(x), cy: f(y - 92 * k), r: f(46 * k), fill: `url(#${ctx.id}-halo)`, opacity: 0, 'pointer-events': 'none' }, parent);
    const g = groupe(parent, x, y, k, () => { force = force > 0.5 ? 0 : 1; });
    shape(g, PEN * 0.9).setAttribute('d', 'M-3 0 L-1.6 -84 L1.6 -84 L3 0Z');
    const verre = paint(g, PAPER, PEN * 0.9);
    verre.setAttribute('d', 'M-8 -84 L8 -84 L6 -98 L-6 -98Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M-10 -98 L10 -98 L0 -106Z');
    pen(g, PEN * 0.7).setAttribute('d', 'M-6 0 L6 0');
    return {
      maj(_t, _dt, P) {
        const l = P.lamp * force;
        halo.setAttribute('opacity', f(l * 0.85));
        verre.setAttribute('fill', fenetre(P, l));
      },
    };
  },

  banc(_d, parent, _ctx, { x, y, k }) {
    const g = groupe(parent, x, y, k);
    shape(g, PEN * 0.9).setAttribute('d', 'M-30 -16 L30 -16 L30 -12 L-30 -12Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M-30 -30 L30 -30 L30 -25 L-30 -25Z');
    pen(g, PEN).setAttribute('d', 'M-26 -12 L-27 0 M26 -12 L27 0 M-24 -25 L-24 -16 M24 -25 L24 -16');
    return { maj() {} };
  },

  fleurs(d, parent, _ctx, { x, y, k }, graine) {
    const r = seeded(graine);
    const couleurs = d.couleur ? [d.couleur, d.couleur, '#f2d16b'] : ['#e07a5f', '#f2d16b', '#c46aa5', '#f4a259'];
    const ressorts = Array.from({ length: 6 }, () => spring(40, 4));
    const g = groupe(parent, x, y, k, () => ressorts.forEach((s, i) => s.kick((i % 2 ? 1 : -1) * 18)));
    const tiges = ressorts.map(() => pen(g, PEN * 0.8));
    const tetes = ressorts.map((_, i) => paint(g, couleurs[i % couleurs.length], PEN * 0.7));
    const pieds = ressorts.map((_, i) => [-22 + i * 9 + r(-2, 2), 18 + r(0, 12)] as const);
    return {
      maj(t, dt, P, vent) {
        ressorts.forEach((s, i) => {
          const a = s.step(vent * 6 + Math.sin(t * 1.7 + i) * 2, dt);
          const [px, h] = pieds[i];
          const tx = px + a * 0.5, ty = -h;
          tiges[i].setAttribute('d', `M${f(px)} 0 Q${f(px + a * 0.1)} ${f(-h / 2)} ${f(tx)} ${f(ty)}`);
          tetes[i].setAttribute('d', lobes(tx, ty - 2, 3.6, 3.2, 5, graine + i));
          tetes[i].setAttribute('fill', nightTint(couleurs[i % couleurs.length], P));
        });
      },
    };
  },

  buisson(d, parent, _ctx, { x, y, k }, graine) {
    const s = spring(30, 3);
    const g = groupe(parent, x, y, k, () => s.kick(0.8));
    const masse = paint(g, vert(d.couleur));
    masse.setAttribute('d', lobes(0, -14, 26, 15, 9, graine));
    return {
      maj(t, dt, P, vent) {
        const e = s.step(vent * 0.05, dt);
        masse.setAttribute('transform', `translate(0 0) scale(${f(1 + e * 0.08 + wobble(t + graine) * 0.006)} ${f(1 - e * 0.05)})`);
        masse.setAttribute('fill', nightTint(vert(d.couleur), P, 0.7));
      },
    };
  },

  etal(d, parent, _ctx, { x, y, k }, graine) {
    const balance = pendulum(10, 1.4);
    const g = groupe(parent, x, y, k, () => balance.kick(2));
    pen(g, PEN).setAttribute('d', 'M-34 0 L-34 -62 M34 0 L34 -62');
    shape(g).setAttribute('d', 'M-38 -24 L38 -24 L36 -18 L-36 -18Z');
    pen(g, PEN * 0.8).setAttribute('d', 'M-32 -18 L-30 0 M32 -18 L30 0');
    const r = seeded(graine);
    const fruits = ['#e8833a', '#d64933', '#f2c94c', '#7fb069'];
    for (let i = 0; i < 9; i++) paint(g, fruits[i % 4], PEN * 0.6).setAttribute('d', lobes(-28 + i * 7 + r(-1, 1), -28 - (i % 2) * 3, 3.6, 3.4, 6, graine + i));
    const auvent = el('g', {}, g);
    const toile = paint(auvent, d.couleur || '#d9534f');
    const rayures = pen(auvent, PEN * 0.6);
    return {
      maj(t, dt, P, vent) {
        const a = balance.step(vent * 0.4, dt);
        const bord = -54 + a * 3 + Math.sin(t * 3) * vent * 0.6;
        toile.setAttribute('d', `M-42 -66 L42 -66 L44 ${f(bord)} Q33 ${f(bord + 5)} 22 ${f(bord)} Q11 ${f(bord + 5)} 0 ${f(bord)} Q-11 ${f(bord + 5)} -22 ${f(bord)} Q-33 ${f(bord + 5)} -44 ${f(bord)}Z`);
        rayures.setAttribute('d', `M-22 -66 L-22 ${f(bord)} M0 -66 L0 ${f(bord)} M22 -66 L22 ${f(bord)}`);
        toile.setAttribute('fill', nightTint(d.couleur || '#d9534f', P));
      },
    };
  },

  boutique(d, parent, _ctx, { x, y, k }) {
    let allumee = 0;
    const g = groupe(parent, x, y, k, () => { allumee = allumee > 0.5 ? 0 : 1; });
    shape(g).setAttribute('d', 'M-46 0 L-45 -78 L45 -78 L46 0Z');
    const vitrine = paint(g, PAPER, PEN * 0.9);
    vitrine.setAttribute('d', 'M-38 -8 L-38 -40 L4 -40 L4 -8Z');
    paint(g, '#7a5236', PEN * 0.9).setAttribute('d', 'M14 0 L14 -38 L34 -38 L34 0Z');
    const auvent = paint(g, d.couleur || '#2f6f8f');
    auvent.setAttribute('d', 'M-50 -52 L50 -52 L54 -42 Q45 -38 36 -42 Q27 -38 18 -42 Q9 -38 0 -42 Q-9 -38 -18 -42 Q-27 -38 -36 -42 Q-45 -38 -54 -42Z');
    shape(g, PEN * 0.9).setAttribute('d', 'M-36 -74 L36 -74 L36 -58 L-36 -58Z');
    const enseigne = el('text', { x: 0, y: -62.4, 'font-size': 10, 'font-family': 'Inter, system-ui, sans-serif', 'font-weight': 500, fill: 'currentColor', 'text-anchor': 'middle' }, g);
    enseigne.textContent = d.texte || 'Boutique';
    return {
      maj(_t, _dt, P) {
        vitrine.setAttribute('fill', fenetre(P, Math.max(P.lamp, allumee)));
        auvent.setAttribute('fill', nightTint(d.couleur || '#2f6f8f', P));
      },
    };
  },

  chat(d, parent, _ctx, { x, y, k }) {
    const queue = pendulum(8, 0.9);
    const etire = spring(30, 4);
    const g = groupe(parent, x, y, k * 0.8, () => { queue.kick(5); etire.kick(4); });
    const corps = el('g', {}, g);
    const pelage = d.couleur || '#3a3a3a';
    const dos = paint(corps, pelage);
    dos.setAttribute('d', 'M-14 0 Q-17 -14 -6 -20 Q4 -24 8 -14 Q10 -6 9 0Z');
    const tete = paint(corps, pelage);
    tete.setAttribute('d', 'M2 -20 Q2 -28 8 -28 L9 -33 L12 -28 L15 -33 L16 -27 Q19 -22 15 -18 Q9 -15 4 -17Z');
    const qu = pen(g, 2.2);
    return {
      maj(t, dt, P, vent) {
        const a = queue.step(vent * 0.3 + Math.sin(t * 1.1) * 0.4, dt);
        const e = etire.step(0, dt);
        corps.setAttribute('transform', `scale(1 ${f(1 + e * 0.02)})`);
        qu.setAttribute('d', `M-13 -2 Q${f(-24 + a * 4)} ${f(-4)} ${f(-22 + a * 8)} ${f(-16 - a * 2)}`);
        dos.setAttribute('fill', nightTint(pelage, P, 0.4));
        tete.setAttribute('fill', nightTint(pelage, P, 0.4));
      },
    };
  },

  cloture(_d, parent, _ctx, { x, y, k }) {
    const g = groupe(parent, x, y, k);
    pen(g, PEN).setAttribute('d', Array.from({ length: 7 }, (_, i) => `M${-42 + i * 14} 0 L${-42 + i * 14} -30 L${-39 + i * 14} -34`).join(' ') + ' M-46 -10 L46 -10 M-46 -22 L46 -22');
    return { maj() {} };
  },

  // Les grands fonds : ils couvrent toute la largeur, leur position x est ignorée.
  collines(d, parent, ctx, _pose, graine) {
    const r = seeded(graine);
    const h = ctx.sol;
    let dd = `M0 ${f(h)} L0 ${f(h - ctx.hauteur * 0.08)}`;
    for (let i = 1; i <= 4; i++) dd += ` Q${f(ctx.largeur * (i - 0.5) / 4)} ${f(h - ctx.hauteur * r(0.12, 0.2))} ${f(ctx.largeur * i / 4)} ${f(h - ctx.hauteur * r(0.05, 0.1))}`;
    const forme = paint(parent, d.couleur || '#c9d8b0', PEN);
    forme.setAttribute('d', dd + ` L${f(ctx.largeur)} ${f(h)}Z`);
    return { maj(_t, _dt, P) { forme.setAttribute('fill', nightTint(d.couleur || '#c9d8b0', P, 0.8)); } };
  },

  mer(d, parent, ctx) {
    const haut = ctx.sol - ctx.hauteur * 0.07;
    const eau = paint(parent, d.couleur || '#9cc3d5', PEN);
    eau.setAttribute('d', `M0 ${f(haut)} L${f(ctx.largeur)} ${f(haut)} L${f(ctx.largeur)} ${f(ctx.sol)} L0 ${f(ctx.sol)}Z`);
    const vagues = pen(parent, PEN * 0.8);
    return {
      maj(t, _dt, P) {
        eau.setAttribute('fill', nightTint(d.couleur || '#9cc3d5', P, 0.8));
        const decalage = (t * 18) % 40;
        let v = '';
        for (let rang = 0; rang < 2; rang++) {
          const yy = haut + (ctx.sol - haut) * (0.35 + rang * 0.35);
          for (let xx = -40 + decalage + rang * 20; xx < ctx.largeur; xx += 40) v += `M${f(xx)} ${f(yy)} q5 -3 10 0 `;
        }
        vagues.setAttribute('d', v);
      },
    };
  },

  immeubles(_d, parent, ctx, _pose, graine) {
    const r = seeded(graine);
    const g = el('g', {}, parent);
    const vitres: SVGPathElement[] = [];
    let xx = 0;
    while (xx < ctx.largeur) {
      const l = ctx.largeur * r(0.06, 0.11), h = ctx.hauteur * r(0.14, 0.32);
      shape(g, PEN * 0.8).setAttribute('d', `M${f(xx)} ${f(ctx.sol)} L${f(xx)} ${f(ctx.sol - h)} L${f(xx + l)} ${f(ctx.sol - h)} L${f(xx + l)} ${f(ctx.sol)}Z`);
      let fen = '';
      for (let fy = ctx.sol - h + 10; fy < ctx.sol - 14; fy += 16) for (let fx = xx + 6; fx < xx + l - 8; fx += 12) if (r() > 0.45) fen += `M${f(fx)} ${f(fy)} l5 0 l0 7 l-5 0Z`;
      const v = el('path', { d: fen, fill: LUMIERE, opacity: 0 }, g);
      vitres.push(v);
      xx += l + ctx.largeur * r(0.005, 0.02);
    }
    return { maj(_t, _dt, P) { vitres.forEach((v) => v.setAttribute('opacity', f(P.lamp))); } };
  },

  nuage(_d, parent, ctx, { x, k }, graine) {
    const gonfle = spring(30, 4);
    const yy = ctx.sol * (0.18 + seeded(graine)() * 0.22);
    let xx = x;
    const g = groupe(parent, xx, yy, k * 0.9, () => gonfle.kick(2));
    const forme = shape(g, PEN * 0.9);
    forme.setAttribute('d', lobes(0, 0, 34, 12, 8, graine));
    return {
      maj(_t, dt, _P, vent) {
        xx += (6 + vent * 10) * dt;
        if (xx > ctx.largeur + 80) xx = -80;
        if (xx < -80) xx = ctx.largeur + 80;
        const e = gonfle.step(0, dt);
        g.setAttribute('transform', `translate(${f(xx)} ${f(yy)}) scale(${f(k * 0.9 * (1 + e * 0.04))})`);
      },
    };
  },

  oiseaux(_d, parent, ctx, { x, k }, graine) {
    const r = seeded(graine);
    let fuite = 0;
    const g = el('g', { style: 'cursor:pointer' }, parent);
    g.addEventListener('pointerdown', (e) => { e.stopPropagation(); fuite = 2.4; });
    const vol = Array.from({ length: 4 }, () => ({ trait: pen(g, PEN * 1.1), dx: r(-30, 30), dy: r(-14, 14), ph: r(0, 6) }));
    // Une zone à toucher autour de la volée.
    const zone = el('rect', { width: 90, height: 60, fill: 'transparent', 'pointer-events': 'all' }, g);
    let xx = x, yy = ctx.sol * 0.3;
    return {
      maj(t, dt) {
        fuite = Math.max(0, fuite - dt);
        xx += (28 + fuite * 50) * k * 0.5 * dt;
        yy += (-fuite * 18 + Math.sin(t * 0.4 + graine) * 3) * dt;
        if (xx > ctx.largeur + 60) { xx = -60; yy = ctx.sol * r(0.2, 0.4); }
        zone.setAttribute('x', f(xx - 45)); zone.setAttribute('y', f(yy - 30));
        vol.forEach((o) => {
          const a = Math.sin(t * (9 + fuite * 4) + o.ph) * 4 * k * 0.6;
          const bx = xx + o.dx * k * 0.6, by = yy + o.dy * k * 0.6, l = 6 * k * 0.6;
          o.trait.setAttribute('d', `M${f(bx - l)} ${f(by - a)} Q${f(bx - l / 2)} ${f(by - a * 0.3 - 1)} ${f(bx)} ${f(by)} Q${f(bx + l / 2)} ${f(by - a * 0.3 - 1)} ${f(bx + l)} ${f(by - a)}`);
        });
      },
    };
  },
};

/** Les éléments posés au fond, sur toute la largeur. */
export const FONDS_LARGES = ['collines', 'mer', 'immeubles'];
/** Les éléments du ciel. */
export const DU_CIEL = ['nuage', 'oiseaux'];
