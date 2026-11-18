// Kekeli Motion : un personnage dessiné à l'encre, qui marche, court, attend ou salue, et réagit quand on le touche.
// Construit avec la bibliothèque de line-art-animation (licence MIT) : jambes résolues par gait(), membres en deux
// traits ouverts (limb), vêtements dessinés autour des genoux, ressorts pour ce qui balance.
// Le personnage est dessiné dans son propre espace (environ 95 unités de haut, pieds sur y = 120, tourné vers +x).

import { el, pen, blot, paint, limb, solid, shoe, place, toScene, f, pt, PEN, PAPER, type Vec, type Limb } from '../lib/ink.ts';
import { gait, type Gait } from '../lib/gait.ts';
import { spring, pendulum, ease, clamp, wobble } from '../lib/motion.ts';
import { nightTint, type Palette } from '../lib/daynight.ts';
import { PEAUX, type Personnage as Description } from './types.ts';

/** Durée de chaque réaction, en secondes. */
const DUREES: Record<string, number> = { salue: 1.9, saute: 0.9, chapeau: 1.7, tourne: 1.1, parle: 2.8 };

export interface Personnage {
  g: SVGGElement;
  /** Avance le personnage de dt secondes et le redessine. */
  dessiner(t: number, dt: number, P: Palette, vent: number): void;
  /** Déclenche sa réaction (comme si on l'avait touché). */
  reagir(): void;
}

const lerpP = (a: Vec, b: Vec, t: number): Vec => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t];
const tourner = (p: Vec, c: Vec, a: number): Vec => {
  const s = Math.sin(a), co = Math.cos(a);
  return [c[0] + (p[0] - c[0]) * co - (p[1] - c[1]) * s, c[1] + (p[0] - c[0]) * s + (p[1] - c[1]) * co];
};

export interface Placement {
  /** Couche du personnage et couche des bulles (jamais retournée, pour que le texte se lise). */
  couche: SVGGElement;
  bulles: SVGGElement;
  largeur: number;
  sol: number;
  echelle: number;
}

export function creerPersonnage(d: Description, { couche, bulles, largeur, sol, echelle }: Placement): Personnage {
  const enfant = d.genre === 'enfant';
  const s = echelle * (enfant ? 0.68 : 1);
  const court = d.action === 'court';
  const allure: Gait = court
    ? gait({ kind: 'run', thigh: 23, shin: 23, stride: 30, lift: 13, period: 0.66, duty: 0.38, heelKick: 4, ankleY: 118.4 })
    : gait({ kind: 'walk' });
  const avance = d.action === 'marche' || court;
  const peau = PEAUX[d.peau];
  const jupe = d.bas === 'jupe' || d.bas === 'pagne';
  const jambesNues = jupe || d.bas === 'short';

  const fig = el('g', { class: 'kekeli-personnage', style: 'cursor:pointer', role: 'button', 'aria-label': 'Personnage' }, couche);
  // Une zone à toucher plus grande que le dessin (au moins 40 pixels sur un téléphone).
  el('rect', { x: 18, y: 8, width: 46, height: 116, fill: 'transparent', 'pointer-events': 'all' }, fig);
  const dos = el('g', {}, fig);
  const brasLoin: Limb = solid(limb(fig, 3.6), d.haut);
  const jambes: Limb[] = [limb(fig, 4.4), limb(fig, 4.4)];
  jambes.forEach((j) => solid(j, jambesNues ? peau : d.couleur_bas));
  const shorts: Limb[] = d.bas === 'short' ? [solid(limb(fig, 5.6), d.couleur_bas), solid(limb(fig, 5.6), d.couleur_bas)] : [];
  const chaussures = [blot(fig), blot(fig)];
  const bas = jupe ? paint(fig, d.couleur_bas) : null;
  const motif = d.bas === 'pagne' ? pen(fig, PEN * 0.55) : null;
  const torse = paint(fig, d.haut);
  const col = pen(fig, PEN * 0.6);
  const cheveuxArriere = d.cheveux === 'longs' || d.cheveux === 'tresses' ? (d.cheveux === 'tresses' ? pen(fig, 1.8) : blot(fig)) : null;
  const tete = el('ellipse', { rx: 3.7, ry: 4.7, fill: peau, stroke: 'currentColor', 'stroke-width': PEN }, fig);
  const cheveux = d.cheveux === 'foulard' ? paint(fig, d.couleur_accessoire) : blot(fig);
  const accessoire = el('g', {}, fig);
  const brasPres: Limb = solid(limb(fig, 3.6), d.haut);
  const mains = [el('circle', { r: 1.5, fill: peau, stroke: 'currentColor', 'stroke-width': PEN * 0.6 }, fig), el('circle', { r: 1.5, fill: peau, stroke: 'currentColor', 'stroke-width': PEN * 0.6 }, fig)];
  fig.insertBefore(mains[0], brasLoin.g.nextSibling);
  const bulle = el('g', { opacity: 0 }, bulles);
  const bulleForme = el('path', { fill: PAPER, stroke: 'currentColor', 'stroke-width': 1.2, 'stroke-linejoin': 'round' }, bulle);
  const bulleTexte = el('text', { 'font-size': 15, 'font-family': 'Inter, system-ui, sans-serif', fill: 'currentColor', 'text-anchor': 'middle' }, bulle);
  bulleTexte.textContent = d.bulle || 'Bonjour';

  // Accessoires, dessinés une fois puis bougés.
  let sac: SVGPathElement | null = null, sangle: SVGPathElement | null = null, chapeau: SVGPathElement | null = null;
  let panier: SVGPathElement | null = null, tressage: SVGPathElement | null = null, parapluie: SVGPathElement | null = null, manche: SVGPathElement | null = null;
  if (d.accessoire === 'sac' || d.accessoire === 'cartable') { sangle = pen(d.accessoire === 'cartable' ? dos : accessoire, PEN * 0.7); sac = paint(d.accessoire === 'cartable' ? dos : accessoire, d.couleur_accessoire); }
  if (d.accessoire === 'chapeau') chapeau = paint(accessoire, d.couleur_accessoire);
  if (d.accessoire === 'panier') { panier = paint(accessoire, d.couleur_accessoire); tressage = pen(accessoire, PEN * 0.5); }
  if (d.accessoire === 'parapluie') { manche = pen(accessoire, PEN * 0.9); parapluie = paint(accessoire, d.couleur_accessoire); }

  const balancier = [spring(60, 9), spring(60, 9)];
  const pendule = pendulum(14, 1.8);
  const meche = spring(30, 4);
  let dir: 1 | -1 = d.direction === 'gauche' ? -1 : 1;
  let x = clamp(d.x, 0, 1) * largeur;
  let phase = d.x * 3.1;
  let reaction = -1;

  const vitesse = allure.speed * s * d.vitesse;
  fig.addEventListener('pointerdown', (e) => { e.stopPropagation(); if (reaction < 0) reaction = 0; });

  return {
    g: fig,
    reagir() { if (reaction < 0) reaction = 0; },
    dessiner(t, dt, P, vent) {
      // La réaction en cours : quand elle ralentit le personnage (saluer, parler…), quand il saute ou se retourne.
      let r = 0, saut = 0;
      const type = d.reaction;
      if (reaction >= 0) {
        reaction += dt;
        const total = DUREES[type] ?? 1.5;
        r = reaction < 0.3 ? ease(reaction / 0.3) : reaction < total - 0.4 ? 1 : 1 - ease((reaction - total + 0.4) / 0.4);
        if (type === 'saute') saut = Math.sin(Math.PI * clamp((reaction - 0.12) / 0.62, 0, 1)) * 26;
        if (type === 'tourne' && reaction >= total / 2 && reaction - dt < total / 2) dir = (dir === 1 ? -1 : 1);
        if (reaction > total) reaction = -1;
      }
      const freine = type === 'saute' ? 0 : r * (type === 'tourne' ? 1 : 0.92);
      const go = avance ? 1 - freine : 0;
      phase += (dt / allure.period) * go;
      if (avance) {
        x += dir * vitesse * dt * go;
        if (x > largeur + 70) x = -70;
        if (x < -70) x = largeur + 70;
      }
      const p = allure.pose(phase, go);
      const b = p.bob;
      place(fig, x, sol - saut, dir, s);

      // Jambes : la jambe de devant est dessinée par-dessus. Un saut replie un peu les genoux.
      const rentre = saut > 0 ? Math.min(1, saut / 18) : 0;
      p.feet.forEach((pied, i) => {
        const genou: Vec = [pied.knee[0] + 4 * rentre, pied.knee[1] - 2 * rentre];
        const cheville: Vec = [pied.ankle[0] - 2 * rentre, pied.ankle[1] - 6 * rentre];
        jambes[i].draw([p.hip, genou, cheville], [0.3, 0.8]);
        shoe(chaussures[i], cheville, pied.pitch);
        if (shorts[i]) shorts[i].draw([p.hip, lerpP(p.hip, genou, 0.62)], [0, 0]);
      });
      const devant = p.front;
      fig.insertBefore(jambes[devant].g, bas ?? torse);
      fig.insertBefore(chaussures[devant], bas ?? torse);
      if (shorts[devant]) fig.insertBefore(shorts[devant].g, bas ?? torse);

      // Le haut du corps, penché en avant quand il court.
      const penche = court ? 0.16 * go : 0;
      const hanche: Vec = [p.hip[0], p.hip[1]];
      const R = (q: Vec): Vec => tourner(q, hanche, penche);
      const epaule = R([41, 41 + b]);
      const tc = R([42.7, 31.4 + b]);
      const taille: Vec = R([40.5, p.hip[1] - 1.5]);

      if (bas) {
        // Jupe ou pagne : l'ourlet s'ouvre et se ferme avec la foulée, autour du genou de devant.
        const kF = p.feet[devant].knee, aF = p.feet[devant].ankle;
        const ourlet = d.bas === 'pagne' ? 110 : 98;
        const avantX = Math.max(kF[0] + 2.4, 46.6);
        const arriere = Math.min(p.feet[1 - devant].knee[0] - 2.2, 33.5);
        bas.setAttribute('d', `M${f(taille[0] - 6)} ${f(taille[1] - 1)} L${f(taille[0] + 6)} ${f(taille[1] - 1)} Q${f(avantX)} ${f(taille[1] + 14)} ${f(Math.max(avantX, Math.min(aF[0] - 3, avantX + 6)))} ${ourlet + b * 0.3} L${f(arriere - 1)} ${ourlet + 1 + b * 0.3} Q${f(arriere + 1)} ${f(taille[1] + 12)} ${f(taille[0] - 6)} ${f(taille[1] - 1)}Z`);
        bas.setAttribute('fill', nightTint(d.couleur_bas, P));
        if (motif) motif.setAttribute('d', `M${f(taille[0] - 3)} ${f(taille[1] + 8)} L${f(taille[0] + 4)} ${f(taille[1] + 8)} M${f(arriere + 3)} ${f(ourlet - 6)} L${f(avantX - 1)} ${f(ourlet - 6)}`);
      }

      torse.setAttribute('d', `M${f(epaule[0] - 5.6)} ${f(epaule[1] + 1.4)} Q${f(epaule[0] - 1)} ${f(epaule[1] - 2.4)} ${f(epaule[0] + 5)} ${f(epaule[1] + 1)} Q${f(epaule[0] + 6.6)} ${f(epaule[1] + 14)} ${f(taille[0] + 6)} ${f(taille[1])} L${f(taille[0] - 6.2)} ${f(taille[1] + 0.6)} Q${f(epaule[0] - 7)} ${f(epaule[1] + 14)} ${f(epaule[0] - 5.6)} ${f(epaule[1] + 1.4)}Z`);
      torse.setAttribute('fill', nightTint(d.haut, P));
      col.setAttribute('d', `M${f(epaule[0] - 1.6)} ${f(epaule[1] - 0.6)} Q${f(epaule[0] + 0.6)} ${f(epaule[1] + 2.4)} ${f(epaule[0] + 2.8)} ${f(epaule[1] - 0.4)}`);

      // Tête et cheveux.
      tete.setAttribute('cx', f(tc[0])); tete.setAttribute('cy', f(tc[1]));
      const [hx, hy] = tc;
      if (d.cheveux === 'foulard') {
        cheveux.setAttribute('d', `M${f(hx - 4.6)} ${f(hy + 0.6)} Q${f(hx - 5.4)} ${f(hy - 7.4)} ${f(hx + 0.4)} ${f(hy - 8.2)} Q${f(hx + 5.6)} ${f(hy - 7.8)} ${f(hx + 4.4)} ${f(hy - 1.8)} Q${f(hx)} ${f(hy - 3.6)} ${f(hx - 4.6)} ${f(hy + 0.6)}Z M${f(hx - 1.6)} ${f(hy - 7.6)} Q${f(hx - 5)} ${f(hy - 12.6)} ${f(hx + 1.6)} ${f(hy - 11)} Q${f(hx + 3.4)} ${f(hy - 9.6)} ${f(hx + 1.2)} ${f(hy - 7.8)}Z`);
        cheveux.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      } else {
        const epais = d.cheveux === 'ras' ? 0.8 : 1.6;
        cheveux.setAttribute('d', `M${f(hx - 3.9)} ${f(hy + 0.8)} Q${f(hx - 4.8 - epais * 0.3)} ${f(hy - 5.6 - epais)} ${f(hx + 0.4)} ${f(hy - 5.2 - epais)} Q${f(hx + 4.4)} ${f(hy - 4.6 - epais * 0.6)} ${f(hx + 3.7)} ${f(hy - 1.6)} Q${f(hx + 1)} ${f(hy - 3.4)} ${f(hx - 1.6)} ${f(hy - 2.2)} Q${f(hx - 2.6)} ${f(hy - 0.4)} ${f(hx - 3.9)} ${f(hy + 0.8)}Z`);
      }
      const flot = meche.step(-2 - (avance ? allure.speed * 0.05 * go : 0) - vent * 3, dt);
      if (cheveuxArriere && d.cheveux === 'longs') cheveuxArriere.setAttribute('d', `M${f(hx - 3.6)} ${f(hy - 2)} Q${f(hx - 6 + flot * 0.3)} ${f(hy + 4)} ${f(hx - 4.6 + flot * 0.6)} ${f(hy + 10)} L${f(hx - 1.6 + flot * 0.4)} ${f(hy + 9)} Q${f(hx - 2.4)} ${f(hy + 3)} ${f(hx - 1)} ${f(hy - 1)}Z`);
      if (cheveuxArriere && d.cheveux === 'tresses') cheveuxArriere.setAttribute('d', `M${f(hx - 3)} ${f(hy - 1)} Q${f(hx - 5.4 + flot * 0.4)} ${f(hy + 4)} ${f(hx - 4.4 + flot * 0.7)} ${f(hy + 9)} M${f(hx - 1.2)} ${f(hy - 1.4)} Q${f(hx - 3 + flot * 0.4)} ${f(hy + 4)} ${f(hx - 2 + flot * 0.7)} ${f(hy + 8.6)}`);

      // Bras : ils balancent à l'opposé des jambes (ressorts), plus fort et pliés quand il court.
      const jambe = (p.feet[0].ankle[0] - 40) / (allure.stride / 2);
      const ampleur = court ? 34 : 15;
      const aLoin = (balancier[0].step(-jambe * ampleur * go, dt) * Math.PI) / 180;
      const aPres = (balancier[1].step(jambe * (ampleur - 2) * go + wobble(t * 0.7 + d.x * 9) * (avance ? 0 : 2), dt) * Math.PI) / 180;
      const plie = court ? 1.25 : 0.12;
      const eLoin: Vec = [epaule[0] - 1 + 15 * Math.sin(aLoin), epaule[1] + 2 + 15 * Math.cos(aLoin)];
      const mLoin: Vec = [eLoin[0] + 14 * Math.sin(aLoin + plie), eLoin[1] + 14 * Math.cos(aLoin + plie)];
      brasLoin.draw([[epaule[0] - 1, epaule[1] + 2], eLoin, mLoin], [0.2, 0.5]);
      mains[0].setAttribute('cx', f(mLoin[0])); mains[0].setAttribute('cy', f(mLoin[1]));

      const ePres: Vec = [epaule[0] + 1.6, epaule[1] + 2.4];
      let coude: Vec = [ePres[0] + 15 * Math.sin(aPres), ePres[1] + 15 * Math.cos(aPres)];
      let main: Vec = [coude[0] + 14 * Math.sin(aPres + plie), coude[1] + 14 * Math.cos(aPres + plie)];
      const geste = reaction >= 0 && (type === 'salue' || type === 'chapeau' || type === 'parle') ? r : 0;
      const souleve = type === 'chapeau' && d.accessoire === 'chapeau' ? geste * 5 : 0;
      if (geste > 0.01) {
        // Saluer (la main s'agite au-dessus de la tête), soulever son chapeau, ou parler (la main s'ouvre devant lui).
        const cible: Vec = type === 'parle'
          ? [epaule[0] + 15, epaule[1] + 8 + Math.sin(reaction * 7) * 1.4]
          : type === 'chapeau' && d.accessoire === 'chapeau' ? [hx + 4, hy - 5 - souleve] : [epaule[0] + 9 + Math.sin(reaction * 13) * 4.2, epaule[1] - 17];
        const milieu: Vec = type === 'parle' ? [epaule[0] + 6, epaule[1] + 13] : [epaule[0] + 10, epaule[1] - 2];
        coude = lerpP(coude, milieu, geste);
        main = lerpP(main, cible, geste);
      } else if (d.accessoire === 'parapluie') {
        coude = [ePres[0] + 7, ePres[1] + 9];
        main = [ePres[0] + 11, ePres[1] - 2];
      } else if (d.action === 'salue' && reaction < 0) {
        coude = [epaule[0] + 10, epaule[1] - 2];
        main = [epaule[0] + 9 + Math.sin(t * 9) * 4, epaule[1] - 17];
      }
      brasPres.draw([ePres, coude, main], [0.2, 0.4]);
      mains[1].setAttribute('cx', f(main[0])); mains[1].setAttribute('cy', f(main[1]));

      // Les accessoires.
      const secousse = Math.cos(2 * Math.PI * phase * 2) * 1.6 * go;
      if (sac && sangle) {
        if (d.accessoire === 'cartable') {
          sangle.setAttribute('d', `M${f(epaule[0] - 3)} ${f(epaule[1] + 1)} L${f(epaule[0] + 2)} ${f(epaule[1] + 10)}`);
          sac.setAttribute('d', `M${f(epaule[0] - 12)} ${f(epaule[1] + 3)} L${f(epaule[0] - 4)} ${f(epaule[1] + 2)} L${f(epaule[0] - 4)} ${f(epaule[1] + 20)} L${f(epaule[0] - 12.4)} ${f(epaule[1] + 19)}Z`);
        } else {
          const a = pendule.step(-secousse - vent * 0.3, dt);
          const attache: Vec = [taille[0] - 3, taille[1] + 3];
          sangle.setAttribute('d', `M${f(epaule[0] + 3)} ${f(epaule[1])} L${pt(attache)}`);
          accessoire.setAttribute('transform', `rotate(${f(a * 30)} ${pt(attache)})`);
          sac.setAttribute('d', `M${f(attache[0] - 5)} ${f(attache[1])} L${f(attache[0] + 4)} ${f(attache[1])} L${f(attache[0] + 4.6)} ${f(attache[1] + 9)} L${f(attache[0] - 5.6)} ${f(attache[1] + 9)}Z`);
        }
        sac.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      }
      if (chapeau) {
        const l = souleve;
        chapeau.setAttribute('d', `M${f(hx - 6.6)} ${f(hy - 3.6 - l)} L${f(hx + 6.8)} ${f(hy - 3.6 - l)} Q${f(hx + 4.4)} ${f(hy - 5 - l)} ${f(hx + 4)} ${f(hy - 8 - l)} Q${f(hx)} ${f(hy - 11 - l)} ${f(hx - 3.8)} ${f(hy - 8 - l)} Q${f(hx - 4.2)} ${f(hy - 5 - l)} ${f(hx - 6.6)} ${f(hy - 3.6 - l)}Z`);
        chapeau.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      }
      if (panier && tressage) {
        // Un panier porté sur la tête, en équilibre.
        const y0 = hy - 5.6 - (d.cheveux === 'foulard' ? 2.6 : 0);
        panier.setAttribute('d', `M${f(hx - 8)} ${f(y0)} L${f(hx + 8)} ${f(y0)} Q${f(hx + 7)} ${f(y0 - 6)} ${f(hx + 5)} ${f(y0 - 7)} L${f(hx - 5)} ${f(y0 - 7)} Q${f(hx - 7)} ${f(y0 - 6)} ${f(hx - 8)} ${f(y0)}Z`);
        panier.setAttribute('fill', nightTint(d.couleur_accessoire, P));
        tressage.setAttribute('d', `M${f(hx - 6.6)} ${f(y0 - 3.4)} L${f(hx + 6.6)} ${f(y0 - 3.4)} M${f(hx - 3)} ${f(y0)} L${f(hx - 2.4)} ${f(y0 - 7)} M${f(hx + 2)} ${f(y0)} L${f(hx + 2.4)} ${f(y0 - 7)}`);
      }
      if (parapluie && manche) {
        const haut: Vec = [main[0] + 0.6, main[1] - 22];
        manche.setAttribute('d', `M${f(main[0])} ${f(main[1] + 2)} L${pt(haut)}`);
        parapluie.setAttribute('d', `M${f(haut[0] - 15)} ${f(haut[1] + 5)} Q${f(haut[0] - 13)} ${f(haut[1] - 9)} ${f(haut[0])} ${f(haut[1] - 10)} Q${f(haut[0] + 13)} ${f(haut[1] - 9)} ${f(haut[0] + 15)} ${f(haut[1] + 5)} Q${f(haut[0] + 10)} ${f(haut[1] + 2)} ${f(haut[0] + 5)} ${f(haut[1] + 5)} Q${f(haut[0])} ${f(haut[1] + 2)} ${f(haut[0] - 5)} ${f(haut[1] + 5)} Q${f(haut[0] - 10)} ${f(haut[1] + 2)} ${f(haut[0] - 15)} ${f(haut[1] + 5)}Z`);
        parapluie.setAttribute('fill', nightTint(d.couleur_accessoire, P));
      }

      // La bulle, dans l'espace de la scène (jamais retournée).
      const visible = type === 'parle' && reaction >= 0 ? r : 0;
      bulle.setAttribute('opacity', f(visible));
      if (visible > 0) {
        const [bx, by] = toScene(x, sol - saut, dir, hx, hy - 9, s);
        const largeurTexte = Math.max(46, Math.min(320, bulleTexte.textContent!.length * 8.2 + 22));
        const hauteur = 30;
        const gauche = clamp(bx - largeurTexte / 2, 6, largeur - largeurTexte - 6);
        const haut = by - hauteur - 10;
        bulleForme.setAttribute('d', `M${f(gauche + 8)} ${f(haut)} L${f(gauche + largeurTexte - 8)} ${f(haut)} Q${f(gauche + largeurTexte)} ${f(haut)} ${f(gauche + largeurTexte)} ${f(haut + 8)} L${f(gauche + largeurTexte)} ${f(haut + hauteur - 8)} Q${f(gauche + largeurTexte)} ${f(haut + hauteur)} ${f(gauche + largeurTexte - 8)} ${f(haut + hauteur)} L${f(bx + 6)} ${f(haut + hauteur)} L${f(bx)} ${f(haut + hauteur + 8)} L${f(bx - 4)} ${f(haut + hauteur)} L${f(gauche + 8)} ${f(haut + hauteur)} Q${f(gauche)} ${f(haut + hauteur)} ${f(gauche)} ${f(haut + hauteur - 8)} L${f(gauche)} ${f(haut + 8)} Q${f(gauche)} ${f(haut)} ${f(gauche + 8)} ${f(haut)}Z`);
        bulleTexte.setAttribute('x', f(gauche + largeurTexte / 2));
        bulleTexte.setAttribute('y', f(haut + hauteur / 2 + 5));
      }
    },
  };
}
