// Le moteur des vidéos « motion design » : le fond animé du style, puis chaque plan à son instant exact (calé sur la
// voix par le Runner), avec une transition courte entre deux plans. Les plans tournent en même temps sur une seule
// ligne de temps absolue : un plan ne peut jamais décaler le suivant.
import { Circle, Gradient, Node, Rect } from '@revideo/2d';
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

/** Le fond du style « Dynamique » : dégradé de nuit, halos qui dérivent, faisceaux lents et poussières qui scintillent. */
function decor(c: Ctx, vue: Node, duree: number): ThreadGenerator {
  const { L, H } = c;
  const fond = new Rect({ width: L, height: H, fill: new Gradient({ type: 'linear', from: [0, -H / 2], to: [0, H / 2], stops: [{ offset: 0, color: c.style.couleurs.fond }, { offset: 0.55, color: c.style.couleurs.fond2 }, { offset: 1, color: c.style.couleurs.fond }] }) });
  vue.add(fond);
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
      g.scale(0.93);
      yield* all(g.opacity(1, T, easeOutCubic), g.scale(1, T + 0.08, easeOutCubic));
    })(),
    contenu,
    waitFor(p.duree),
  );
  if (dernier) return;
  yield* all(g.opacity(0, T, easeInOutCubic), g.scale(1.06, T, easeInOutCubic));
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
