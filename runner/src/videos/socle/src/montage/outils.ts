// Outils communs de la bibliothèque de montage : zone sûre, texte enrichi qui s'ajuste, icônes, cartes, animations.
// Tout est calculé à partir de la taille de la vidéo : rien n'est jamais posé hors de la zone sûre des réseaux sociaux.
import { Circle, Line, Node, Path, Rect, Txt } from '@revideo/2d';
import { all, easeInOutCubic, easeOutBack, easeOutCubic, waitFor } from '@revideo/core';
import type { ThreadGenerator } from '@revideo/core';
import type { Style } from './style';

export type Forme =
  | { forme: 'chemin'; d: string }
  | { forme: 'cercle'; x: number; y: number; r: number }
  | { forme: 'ellipse'; x: number; y: number; rx: number; ry: number }
  | { forme: 'rectangle'; x: number; y: number; l: number; h: number; r: number }
  | { forme: 'ligne'; points: [number, number][]; fermee?: boolean };

/**
 * La zone sûre (TikTok, Reels, statut WhatsApp) : rien d'important dans les 10 % du haut, sous 78 % de la hauteur, ni
 * dans la colonne de droite (au-delà de 950 sur 1080, les boutons de la plateforme). Le bas de la zone est réservé
 * aux sous-titres : le contenu s'arrête au-dessus.
 */
export type Zone = { gauche: number; droite: number; haut: number; bas: number; basSousTitres: number; largeur: number; hauteur: number; x: number; y: number };

export function zoneSure(L: number, H: number): Zone {
  const gauche = -L / 2 + L * 0.055;
  const droite = -L / 2 + L * (950 / 1080);
  const haut = -H / 2 + H * 0.1;
  const basSousTitres = -H / 2 + H * 0.78;
  // Les sous-titres prennent environ 11 % de la hauteur au-dessus de la limite du bas.
  const bas = basSousTitres - H * 0.12;
  return { gauche, droite, haut, bas, basSousTitres, largeur: droite - gauche, hauteur: bas - haut, x: (gauche + droite) / 2, y: (haut + bas) / 2 };
}

export type Ctx = {
  L: number;
  H: number;
  u: number;
  zone: Zone;
  style: Style;
  icones: Record<string, Forme[]>;
  couche: Node;
  /** Un tirage pseudo-aléatoire reproductible (la même vidéo à chaque rendu). */
  hasard: () => number;
};

export function hasardDe(graine: number): () => number {
  let s = graine >>> 0 || 1;
  return () => {
    s = (s * 1664525 + 1013904223) >>> 0;
    return s / 4294967296;
  };
}

const norme = (m: string) => m.toLowerCase().replace(/[’']/g, '').normalize('NFD').replace(/[^\p{L}\p{N}]/gu, '');

/**
 * Un texte dont certains mots (« cles ») sont dans la couleur d'accent, posé mot par mot (chaque mot est un texte à
 * part, placé ligne par ligne et centré) ; la taille baisse jusqu'à ce que tout tienne dans la largeur et la hauteur
 * données, et aucun mot n'est jamais coupé. Rend un cadre transparent de la taille du texte, ajouté à « parent ».
 */
export function texteRiche(c: Ctx, parent: Node, contenu: string, o: { taille: number; graisse?: number; couleur?: string; cles?: string[]; couleurCles?: string; capitales?: boolean; largeur?: number; hauteur?: number; minimum?: number; aligner?: 'center' | 'left'; interligne?: number; ombre?: boolean }): Rect {
  const ecrit = o.capitales ? contenu.toLocaleUpperCase('fr') : contenu;
  const cles = new Set((o.cles ?? []).flatMap((k) => k.split(/\s+/)).map(norme));
  const largeur = o.largeur ?? c.zone.largeur;
  const hauteurMax = o.hauteur ?? c.zone.hauteur * 0.4;
  const minimum = o.minimum ?? o.taille * 0.4;
  // La ponctuation française (« ? », « ! », « : », « ; », « » ») reste collée au mot d'avant (espace insécable).
  const mots = ecrit.split(/\s+/).filter(Boolean).reduce<string[]>((liste, m) => {
    if (liste.length && /^[?!:;»%]+$/.test(m)) liste[liste.length - 1] += `\u00a0${m}`;
    else if (liste.length && liste[liste.length - 1] === '«') liste[liste.length - 1] += `\u00a0${m}`;
    else liste.push(m);
    return liste;
  }, []);
  const cadre = new Rect({ width: largeur, height: 10 });
  parent.add(cadre);
  let taille = o.taille;
  for (let essai = 0; essai < 25; essai++) {
    cadre.removeChildren();
    const noeuds = mots.map((m) => {
      const t = new Txt({
        text: m,
        fontFamily: c.style.police,
        fontWeight: o.graisse ?? 900,
        fontSize: taille,
        fill: cles.has(norme(m)) ? o.couleurCles ?? c.style.couleurs.accent : o.couleur ?? c.style.couleurs.texte,
        ...(o.ombre === false ? {} : { shadowColor: 'rgba(0,0,0,0.45)', shadowBlur: 22, shadowOffsetY: 6 }),
      });
      cadre.add(t);
      return t;
    });
    const espace = taille * 0.28;
    const hauteurLigne = taille * (o.interligne ?? 1.1);
    const lignes: Txt[][] = [[]];
    let x = 0;
    for (const t of noeuds) {
      const l = t.width();
      if (lignes[lignes.length - 1].length && x + espace + l > largeur) {
        lignes.push([]);
        x = 0;
      }
      x += (lignes[lignes.length - 1].length ? espace : 0) + l;
      lignes[lignes.length - 1].push(t);
    }
    const trop = noeuds.some((t) => t.width() > largeur) || lignes.length * hauteurLigne > hauteurMax;
    if (trop && taille > minimum) {
      taille = Math.max(minimum, Math.floor(taille * 0.92));
      continue;
    }
    const total = lignes.length * hauteurLigne;
    lignes.forEach((rangee, i) => {
      const longueur = rangee.reduce((s, t) => s + t.width(), 0) + espace * (rangee.length - 1);
      let xi = o.aligner === 'left' ? -largeur / 2 : -longueur / 2;
      for (const t of rangee) {
        t.x(xi + t.width() / 2);
        t.y(-total / 2 + hauteurLigne * (i + 0.5));
        xi += t.width() + espace;
      }
    });
    cadre.height(total);
    if (trop) console.log(`ALERTE_TEXTE ${contenu}`);
    break;
  }
  return cadre;
}

/** Réduit la taille d'un texte jusqu'à ce qu'il tienne ; signale ce qui reste trop grand (contrôle de la vidéo). */
export function ajuster(t: Txt, largeurMax: number, hauteurMax: number, minimum: number) {
  // La largeur du bloc est fixée : on mesure la largeur réelle de chaque ligne par sa hauteur (retour à la ligne).
  for (let i = 0; i < 40 && t.height() > hauteurMax && t.fontSize() > minimum; i++) t.fontSize(Math.max(minimum, Math.floor(t.fontSize() * 0.93)));
  if (t.height() > hauteurMax + 2 || t.width() > largeurMax + 2) console.log(`ALERTE_TEXTE ${t.text()}`);
}

/** Un texte simple sur une ligne (étiquettes, chiffres), ajusté à une largeur. */
export function ligne(c: Ctx, parent: Node, contenu: string, o: { taille: number; graisse?: number; couleur?: string; largeur?: number; capitales?: boolean }): Txt {
  const t = new Txt({ text: o.capitales ? contenu.toLocaleUpperCase('fr') : contenu, fontFamily: c.style.police, fontWeight: o.graisse ?? 800, fontSize: o.taille, fill: o.couleur ?? c.style.couleurs.texte });
  parent.add(t);
  const max = o.largeur ?? c.zone.largeur;
  for (let i = 0; i < 40 && t.width() > max && t.fontSize() > 10; i++) t.fontSize(Math.floor(t.fontSize() * 0.93));
  if (t.width() > max + 2) console.log(`ALERTE_TEXTE ${contenu}`);
  return t;
}

/** Une icône Lucide (dessinée dans un carré de 24), au trait, centrée. */
export function icone(c: Ctx, nom: string | undefined, taille: number, couleur: string, epaisseur = 2.2): Node {
  // Une boîte de la taille de l'icône : elle se place dans une rangée (mise en page) et peut s'animer (« pop » remet
  // l'échelle à 1) sans changer la taille du dessin, qui est dans le groupe intérieur.
  const boite = new Rect({ width: taille, height: taille });
  const groupe = new Rect({ layout: false, scale: taille / 24 });
  boite.add(groupe);
  const formes = (nom && c.icones[nom]) || [];
  const trait = { stroke: couleur, lineWidth: epaisseur, lineCap: 'round' as const, lineJoin: 'round' as const };
  for (const f of formes) {
    if (f.forme === 'chemin') groupe.add(new Path({ data: f.d, ...trait, x: -12, y: -12 }));
    else if (f.forme === 'cercle') groupe.add(new Circle({ size: f.r * 2, x: f.x - 12, y: f.y - 12, ...trait }));
    else if (f.forme === 'ellipse') groupe.add(new Circle({ width: f.rx * 2, height: f.ry * 2, x: f.x - 12, y: f.y - 12, ...trait }));
    else if (f.forme === 'rectangle') groupe.add(new Rect({ width: f.l, height: f.h, radius: f.r, x: f.x + f.l / 2 - 12, y: f.y + f.h / 2 - 12, ...trait }));
    else if (f.forme === 'ligne') groupe.add(new Line({ points: f.points.map(([x, y]) => [x - 12, y - 12] as [number, number]), closed: f.fermee ?? false, ...trait }));
  }
  return boite;
}

/** Une coche dessinée (ronde, verte), qui peut s'animer de vide à pleine. */
export function coche(c: Ctx, taille: number): { noeud: Node; cocher: () => ThreadGenerator } {
  const noeud = new Node({});
  const rond = new Circle({ size: taille, fill: 'rgba(255,255,255,0.12)', stroke: 'rgba(0,0,0,0.18)', lineWidth: taille * 0.06 });
  const trait = new Line({ points: [[-taille * 0.22, 0], [-taille * 0.05, taille * 0.18], [taille * 0.25, -taille * 0.16]], stroke: '#ffffff', lineWidth: taille * 0.12, lineCap: 'round', lineJoin: 'round', end: 0 });
  noeud.add(rond);
  noeud.add(trait);
  return {
    noeud,
    cocher: function* () {
      rond.fill(c.style.couleurs.vert);
      rond.stroke(c.style.couleurs.vert);
      yield* all(noeud.scale(1.25, 0.12, easeOutCubic), trait.end(1, 0.22, easeOutCubic));
      yield* noeud.scale(1, 0.15, easeInOutCubic);
    },
  };
}

/** Une pastille arrondie (fond plein) avec un texte. */
export function pastille(c: Ctx, parent: Node, contenu: string, o: { taille: number; fond: string; couleur: string; graisse?: number; largeurMax?: number }): Rect {
  const r = new Rect({ layout: true, padding: [o.taille * 0.32, o.taille * 0.7], radius: o.taille * 2, fill: o.fond, alignItems: 'center', gap: o.taille * 0.4, shadowColor: 'rgba(0,0,0,0.35)', shadowBlur: 24, shadowOffsetY: 8 });
  parent.add(r);
  const t = new Txt({ text: contenu, fontFamily: c.style.police, fontWeight: o.graisse ?? 800, fontSize: o.taille, fill: o.couleur });
  r.add(t);
  const max = o.largeurMax ?? c.zone.largeur;
  for (let i = 0; i < 40 && r.width() > max && t.fontSize() > 10; i++) t.fontSize(Math.floor(t.fontSize() * 0.93));
  if (r.width() > max + 2) console.log(`ALERTE_TEXTE ${contenu}`);
  return r;
}

/** Une carte blanche arrondie, avec une ombre douce (maquettes, listes). */
export function carte(c: Ctx, parent: Node, largeur: number, hauteur: number, o: { fond?: string; rayon?: number } = {}): Rect {
  const r = new Rect({ width: largeur, height: hauteur, radius: o.rayon ?? c.u * 0.035, fill: o.fond ?? c.style.couleurs.carte, shadowColor: 'rgba(0,0,0,0.45)', shadowBlur: 40, shadowOffsetY: 16, clip: true });
  parent.add(r);
  return r;
}

// --- Animations ---------------------------------------------------------------------------------------------------

/** Apparition avec rebond (de rien à sa taille). */
export function* pop(n: Node, duree = 0.32): ThreadGenerator {
  n.scale(0);
  n.opacity(1);
  yield* n.scale(1, duree, easeOutBack);
}

/** Le texte « claque » : il arrive grand et s'écrase à sa taille, avec un petit tremblement. */
export function* claque(n: Node, energie = 1): ThreadGenerator {
  n.opacity(0);
  n.scale(1 + 0.7 * energie);
  const r0 = n.rotation();
  yield* all(n.opacity(1, 0.08), n.scale(1, 0.18, easeOutCubic));
  yield* n.rotation(r0 + 2.5 * energie, 0.05);
  yield* n.rotation(r0 - 1.5 * energie, 0.05);
  yield* n.rotation(r0, 0.06);
}

/** Arrivée en glissant depuis un côté (dx, dy en pixels). */
export function* glisse(n: Node, dx: number, dy: number, duree = 0.3): ThreadGenerator {
  const x = n.x();
  const y = n.y();
  n.opacity(0);
  n.x(x + dx);
  n.y(y + dy);
  yield* all(n.opacity(1, duree * 0.6), n.x(x, duree, easeOutCubic), n.y(y, duree, easeOutCubic));
}

/** Lance une animation à un instant donné du plan (en secondes depuis son début). */
export function* a(t: number, tache: ThreadGenerator): ThreadGenerator {
  if (t > 0) yield* waitFor(t);
  yield* tache;
}

/** Un léger battement continu (bouton, appel à l'action) jusqu'à la fin du plan. */
export function* battement(n: Node, duree: number, periode = 0.9): ThreadGenerator {
  let t = 0;
  while (t + periode <= duree) {
    yield* n.scale(1.06, periode / 2, easeInOutCubic);
    yield* n.scale(1, periode / 2, easeInOutCubic);
    t += periode;
  }
}
