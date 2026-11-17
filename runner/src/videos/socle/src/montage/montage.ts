// Le moteur des vidéos « motion design » : le fond animé du style, puis chaque plan à son instant exact (calé sur la
// voix par le Runner), avec une transition courte entre deux plans. Les plans tournent en même temps sur une seule
// ligne de temps absolue : un plan ne peut jamais décaler le suivant.
import { Circle, Gradient, Node, Rect, Txt } from '@revideo/2d';
import { all, easeInOutCubic, easeOutCubic, linear, waitFor } from '@revideo/core';
import type { ThreadGenerator } from '@revideo/core';
import { COMPOSANTS } from './elements';
import type { Plan } from './elements';
import { hasardDe, zoneSure } from './outils';
import type { Ctx, Forme } from './outils';
import { sousTitres } from './sous-titres';
import type { BlocSousTitre } from './sous-titres';
import { styleDe } from './style';
import type { Couleurs } from './style';

export type Donnees = {
  largeur: number;
  hauteur: number;
  style: string;
  couleurs: Partial<Couleurs>;
  marque: Record<string, unknown>;
  duree: number;
  plans: Plan[];
  sousTitres?: BlocSousTitre[];
  voixIA?: boolean;
  icones: Record<string, Forme[]>;
};

/** Le dégradé de fond du style (haut, milieu, bas). */
function degrade(c: Ctx, vue: Node) {
  const { L, H } = c;
  vue.add(new Rect({ width: L, height: H, fill: new Gradient({ type: 'linear', from: [0, -H / 2], to: [0, H / 2], stops: [{ offset: 0, color: c.style.couleurs.fond }, { offset: 0.55, color: c.style.couleurs.fond2 }, { offset: 1, color: c.style.couleurs.fond }] }) }));
}

/** « Dynamique » : nuit profonde, halos qui dérivent, faisceaux lents et poussières qui scintillent. */
function decorNuit(c: Ctx, vue: Node, duree: number): ThreadGenerator {
  const { L, H } = c;
  degrade(c, vue);
  const halo1 = new Circle({ size: c.u * 1.1, fill: c.style.couleurs.accent2, opacity: 0.16, x: -L * 0.35, y: -H * 0.25 });
  const halo2 = new Circle({ size: c.u * 0.9, fill: c.style.couleurs.accent, opacity: 0.08, x: L * 0.35, y: H * 0.22 });
  vue.add(halo1);
  vue.add(halo2);
  const faisceaux = new Node({ y: -H * 0.55, opacity: 0.07 });
  for (let i = 0; i < 7; i++) faisceaux.add(new Rect({ width: c.u * 0.08, height: H * 1.6, fill: '#ffffff', rotation: -36 + i * 12, offsetY: -1 }));
  vue.add(faisceaux);
  const poussieres: ThreadGenerator[] = [];
  for (let i = 0; i < 26; i++) {
    const p = new Circle({ size: 3 + c.hasard() * 5, fill: '#ffffff', opacity: 0.1 + c.hasard() * 0.3, x: (c.hasard() - 0.5) * L, y: (c.hasard() - 0.5) * H });
    vue.add(p);
    poussieres.push(p.y(p.y() - c.u * (0.1 + c.hasard() * 0.2), duree, linear));
  }
  return all(
    halo1.position([L * 0.1, -H * 0.05], duree, easeInOutCubic),
    halo2.position([-L * 0.2, H * 0.3], duree, easeInOutCubic),
    faisceaux.rotation(18, duree, linear),
    ...poussieres,
  );
}

/** « Sobre » : papier clair, un filet de couleur à gauche, de fines lignes qui glissent très lentement. */
function decorPapier(c: Ctx, vue: Node, duree: number): ThreadGenerator {
  const { L, H } = c;
  degrade(c, vue);
  const lignes = new Node({ opacity: 0.6 });
  for (let i = -8; i <= 8; i++) lignes.add(new Rect({ width: L * 2.2, height: 2, fill: c.style.couleurs.trait, y: i * c.u * 0.16, rotation: -12 }));
  vue.add(lignes);
  const filet = new Rect({ width: c.u * 0.018, height: H, fill: c.style.couleurs.accent, x: -L / 2 + c.u * 0.009 });
  vue.add(filet);
  const forme = new Rect({ width: c.u * 0.9, height: c.u * 0.9, radius: c.u * 0.12, fill: c.style.couleurs.accent, opacity: 0.05, rotation: 18, x: L * 0.42, y: -H * 0.32 });
  vue.add(forme);
  return all(lignes.y(-c.u * 0.16, duree, linear), forme.rotation(30, duree, easeInOutCubic));
}

/** « Produit » : vitrine claire et chaude, grandes formes rondes qui flottent, petites pastilles de couleur. */
function decorVitrine(c: Ctx, vue: Node, duree: number): ThreadGenerator {
  const { L, H } = c;
  degrade(c, vue);
  const rond1 = new Circle({ size: c.u * 1.2, fill: c.style.couleurs.accent, opacity: 0.1, x: L * 0.45, y: -H * 0.38 });
  const rond2 = new Circle({ size: c.u * 0.8, fill: c.style.couleurs.accent2, opacity: 0.08, x: -L * 0.5, y: H * 0.3 });
  vue.add(rond1);
  vue.add(rond2);
  const taches: ThreadGenerator[] = [rond1.scale(1.12, duree, easeInOutCubic), rond2.position([-L * 0.38, H * 0.22], duree, easeInOutCubic)];
  for (let i = 0; i < 14; i++) {
    const p = new Circle({ size: c.u * (0.015 + c.hasard() * 0.02), fill: i % 2 ? c.style.couleurs.accent : c.style.couleurs.accent2, opacity: 0.22, x: (c.hasard() - 0.5) * L, y: (c.hasard() - 0.5) * H });
    vue.add(p);
    taches.push(p.y(p.y() - c.u * (0.05 + c.hasard() * 0.1), duree, easeInOutCubic));
  }
  return all(...taches);
}

/** « Témoignage » : veillée chaude et sombre, lumière douce qui respire, grands guillemets en filigrane. */
function decorVeillee(c: Ctx, vue: Node, duree: number): ThreadGenerator {
  const { L, H } = c;
  degrade(c, vue);
  const lumiere = new Circle({ size: c.u * 1.4, fill: c.style.couleurs.accent, opacity: 0.05, x: -L * 0.2, y: -H * 0.2 });
  vue.add(lumiere);
  const guillemets = new Txt({ text: '“', fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 1.1, fill: c.style.couleurs.accent, opacity: 0.06, x: L * 0.28, y: -H * 0.2 });
  vue.add(guillemets);
  const taches: ThreadGenerator[] = [lumiere.position([L * 0.15, H * 0.05], duree, easeInOutCubic), guillemets.y(-H * 0.24, duree, easeInOutCubic)];
  for (let i = 0; i < 10; i++) {
    const p = new Circle({ size: c.u * (0.04 + c.hasard() * 0.08), fill: c.style.couleurs.accent, opacity: 0.05 + c.hasard() * 0.06, x: (c.hasard() - 0.5) * L, y: (c.hasard() - 0.5) * H });
    vue.add(p);
    taches.push(p.x(p.x() + c.u * (c.hasard() - 0.5) * 0.15, duree, easeInOutCubic));
  }
  return all(...taches);
}

/** « Éducatif » : tableau bleu nuit quadrillé, quadrillage qui glisse lentement, quelques repères (+, ○, △). */
function decorTableau(c: Ctx, vue: Node, duree: number): ThreadGenerator {
  const { L, H } = c;
  degrade(c, vue);
  const pas = c.u * 0.09;
  const grille = new Node({ opacity: 0.5 });
  for (let x = -Math.ceil(L / pas); x <= Math.ceil(L / pas); x++) grille.add(new Rect({ width: 1.5, height: H * 1.4, fill: c.style.couleurs.trait, x: x * pas }));
  for (let y = -Math.ceil(H / pas); y <= Math.ceil(H / pas); y++) grille.add(new Rect({ width: L * 1.4, height: 1.5, fill: c.style.couleurs.trait, y: y * pas }));
  vue.add(grille);
  const reperes = [
    new Txt({ text: '+', fontFamily: c.style.police, fontWeight: 700, fontSize: c.u * 0.12, fill: c.style.couleurs.accent, opacity: 0.25, x: -L * 0.36, y: -H * 0.36 }),
    new Circle({ size: c.u * 0.14, stroke: c.style.couleurs.accent2, lineWidth: 4, opacity: 0.25, x: L * 0.36, y: H * 0.36 }),
    new Rect({ width: c.u * 0.12, height: c.u * 0.12, stroke: c.style.couleurs.accent, lineWidth: 4, opacity: 0.2, rotation: 45, x: L * 0.38, y: -H * 0.4 }),
  ];
  reperes.forEach((r) => vue.add(r));
  return all(grille.position([pas, pas], duree, linear), ...reperes.map((r, i) => r.rotation(r.rotation() + (i % 2 ? -40 : 40), duree, easeInOutCubic)));
}

const DECORS = { nuit: decorNuit, papier: decorPapier, vitrine: decorVitrine, veillee: decorVeillee, tableau: decorTableau };

function decor(c: Ctx, vue: Node, duree: number): ThreadGenerator {
  return (DECORS[c.style.decor] ?? decorNuit)(c, vue, duree);
}

/** Un plan : il apparaît à son instant, joue son animation, puis laisse la place au suivant. */
function* jouerPlan(c: Ctx, p: Plan, rang: number, dernier: boolean): ThreadGenerator {
  if (p.debut > 0) yield* waitFor(p.debut);
  const g = new Node({ opacity: rang === 0 ? 1 : 0 });
  c.couche.add(g);
  const composant = COMPOSANTS[p.type];
  const contenu = composant ? composant(c, p, g) : waitFor(0);
  const T = c.style.transition;
  yield* all(
    // L'accroche est déjà là sur l'image 0 (miniature) ; les autres plans entrent en zoomant légèrement.
    rang === 0 ? waitFor(0) : (function* () {
      g.scale(1 - 0.07 * Math.max(0.3, c.style.energie));
      yield* all(g.opacity(1, T, easeOutCubic), g.scale(1, T + 0.08, easeOutCubic));
    })(),
    contenu,
    waitFor(p.duree),
  );
  if (dernier) return;
  // Le plan qui s'en va disparaît vite (0,18 seconde au plus) : jamais deux textes l'un sur l'autre, même quand le
  // style entre lentement (« Sobre », « Témoignage »).
  const sortie = Math.min(T, 0.18);
  yield* all(g.opacity(0, sortie, easeInOutCubic), g.scale(1 + 0.06 * Math.max(0.3, c.style.energie), sortie, easeInOutCubic));
  g.remove();
}

export function* monter(vue: Node, d: Donnees): ThreadGenerator {
  const style = styleDe(d.style, d.couleurs);
  const zone = zoneSure(d.largeur, d.hauteur);
  const couche = new Node({});
  const c: Ctx = { L: d.largeur, H: d.hauteur, u: Math.min(d.largeur, d.hauteur), zone, style, icones: d.icones ?? {}, couche, hasard: hasardDe(7) };
  const anime = decor(c, vue, d.duree);
  vue.add(couche);
  // Les sous-titres sur leur propre couche, au-dessus des plans (jamais cachés par une transition).
  const coucheSousTitres = new Node({});
  vue.add(coucheSousTitres);
  yield* all(
    anime,
    ...d.plans.map((p, i) => jouerPlan(c, { ...p, marque: d.marque, voixIA: d.voixIA === true }, i, i === d.plans.length - 1)),
    sousTitres(c, coucheSousTitres, d.sousTitres ?? []),
  );
}
