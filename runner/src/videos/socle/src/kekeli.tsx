// Bibliothèque de montage de Kekeli (moteur Revideo, licence MIT). Le fichier « video.tsx » écrit par Kekeli appelle
// ces fonctions scène par scène. Tout le texte est posé par le code (jamais dessiné par un modèle d'images) et il est
// toujours ajusté pour ne jamais être coupé ni déborder de l'écran.
import { Gradient, Img, Node, Rect, Txt, Video, Circle } from '@revideo/2d';
import { all, easeInOutCubic, easeOutCubic, linear, waitFor } from '@revideo/core';

export type Media = { fichier: string; type: 'image' | 'video'; largeur: number; hauteur: number; debut?: number } | null;

export type Scene = {
  duree: number;
  titre?: string;
  texte?: string;
  prix?: string;
  media: Media;
  animation: 'zoom' | 'glisse' | 'fondu' | 'apparition';
  disposition: 'haut' | 'centre' | 'bas';
};

export type Fin = { duree: number; titre: string; texte?: string; contact?: string; paiement?: string; logo?: Media; media?: Media };

export type SousTitre = { debut: number; fin: number; texte: string };

export type Reglages = {
  largeur: number;
  hauteur: number;
  couleurs: { fond: string; texte: string; accent: string };
  sousTitres: boolean;
};

export type Kekeli = {
  vue: Node;
  L: number;
  H: number;
  petit: number;
  vertical: boolean;
  c: Reglages['couleurs'];
  scenes: Node;
  haut: Node;
  precedent: Node | null;
  sousTitres: boolean;
};

const POLICE = 'Inter';

export function preparer(vue: Node, r: Reglages): Kekeli {
  const scenes = new Node({});
  const haut = new Node({ zIndex: 10 });
  vue.add(new Rect({ width: r.largeur, height: r.hauteur, fill: r.couleurs.fond }));
  vue.add(scenes);
  vue.add(haut);
  return {
    vue,
    L: r.largeur,
    H: r.hauteur,
    petit: Math.min(r.largeur, r.hauteur),
    vertical: r.hauteur > r.largeur * 1.2,
    c: r.couleurs,
    scenes,
    haut,
    precedent: null,
    sousTitres: r.sousTitres,
  };
}

/** Réduit la taille du texte jusqu'à ce qu'il tienne dans la place prévue ; le signale s'il reste trop grand. */
export function ajuster(t: Txt, largeurMax: number, hauteurMax: number, minimum: number) {
  for (let i = 0; i < 40 && (t.height() > hauteurMax || t.width() > largeurMax) && t.fontSize() > minimum; i++) {
    t.fontSize(Math.max(minimum, Math.floor(t.fontSize() * 0.92)));
  }
  if (t.height() > hauteurMax + 2 || t.width() > largeurMax + 2) console.log(`ALERTE_TEXTE ${t.text()}`);
}

/** Le fond d'une scène : la photo ou la vidéo couvre tout l'écran ; sans média, un fond animé aux couleurs choisies. */
function fond(k: Kekeli, media: Media, groupe: Node): Node {
  if (media && (media.type === 'image' || media.type === 'video')) {
    const echelle = Math.max(k.L / media.largeur, k.H / media.hauteur);
    const props = { src: media.fichier, width: Math.ceil(media.largeur * echelle) + 2, height: Math.ceil(media.hauteur * echelle) + 2 };
    if (media.type === 'video') {
      // Le son des vidéos du client est mêlé ensuite par FFmpeg (avec la voix et la musique) : ici, l'image seule.
      const v = new Video({ ...props, volume: 0, loop: true, time: media.debut ?? 0, play: true });
      groupe.add(v);
      return v;
    }
    const image = new Img(props);
    groupe.add(image);
    return image;
  }
  const decor = new Node({});
  decor.add(new Rect({
    width: k.L,
    height: k.H,
    fill: new Gradient({ type: 'linear', from: [-k.L / 2, -k.H / 2], to: [k.L / 2, k.H / 2], stops: [{ offset: 0, color: k.c.fond }, { offset: 1, color: '#0E0D0B' }] }),
  }));
  decor.add(new Circle({ size: k.petit * 0.9, fill: k.c.accent, opacity: 0.18, x: -k.L * 0.3, y: -k.H * 0.28 }));
  decor.add(new Circle({ size: k.petit * 0.6, fill: k.c.accent, opacity: 0.12, x: k.L * 0.32, y: k.H * 0.3 }));
  groupe.add(decor);
  return decor;
}

/** Le centre vertical du bloc de texte selon la disposition, en laissant libre la place des sous-titres. */
function centreTexte(k: Kekeli, disposition: Scene['disposition']): number {
  if (disposition === 'haut') return -k.H * (k.vertical ? 0.24 : 0.2);
  if (disposition === 'bas') return k.H * (k.vertical ? 0.02 : 0.1);
  return -k.H * 0.06;
}

function texte(k: Kekeli, contenu: string, taille: number, graisse: number, couleur: string): Txt {
  return new Txt({
    text: contenu,
    fontFamily: POLICE,
    fontWeight: graisse,
    fontSize: taille,
    lineHeight: '118%',
    fill: couleur,
    textWrap: true,
    textAlign: 'center',
    width: k.L - k.petit * 0.16,
    shadowColor: 'rgba(0,0,0,0.45)',
    shadowBlur: 18,
  });
}

/** Une pastille de prix (couleur d'accent), comme sur une affiche. */
function pastille(k: Kekeli, contenu: string): Rect {
  const r = new Rect({ layout: true, padding: [k.petit * 0.018, k.petit * 0.045], radius: k.petit * 0.04, fill: k.c.accent });
  r.add(new Txt({ text: contenu, fontFamily: POLICE, fontWeight: 900, fontSize: k.petit * 0.07, fill: '#1C1A17' }));
  return r;
}

/** Place le titre, le texte et le prix l'un sous l'autre, centrés autour de « centre », sans jamais déborder. */
function blocTexte(k: Kekeli, groupe: Node, s: { titre?: string; texte?: string; prix?: string }, centre: number, hauteurMax: number): Node[] {
  const largeur = k.L - k.petit * 0.16;
  const elements: Node[] = [];
  const ecart = k.petit * 0.03;
  if (s.titre) {
    const t = texte(k, s.titre, k.petit * 0.092, 800, k.c.texte);
    groupe.add(t);
    ajuster(t, largeur, hauteurMax * (s.texte ? 0.55 : 0.8), k.petit * 0.045);
    elements.push(t);
  }
  if (s.texte) {
    const t = texte(k, s.texte, k.petit * 0.05, 500, k.c.texte);
    groupe.add(t);
    ajuster(t, largeur, hauteurMax * (s.titre ? 0.32 : 0.7), k.petit * 0.032);
    elements.push(t);
  }
  if (s.prix) {
    const p = pastille(k, s.prix);
    groupe.add(p);
    const t = p.children()[0] as Txt;
    ajuster(t, largeur * 0.9, k.petit * 0.12, k.petit * 0.04);
    elements.push(p);
  }
  const hauteurs = elements.map((e) => (e as Rect).height());
  const total = hauteurs.reduce((a, b) => a + b, 0) + ecart * Math.max(0, elements.length - 1);
  let y = centre - total / 2;
  elements.forEach((e, i) => {
    e.y(y + hauteurs[i] / 2);
    y += hauteurs[i] + ecart;
  });
  return elements;
}

/** Fait apparaître les textes d'une scène (un léger décalage entre eux, des mouvements courts et sobres). */
function* entree(elements: Node[], animation: Scene['animation'], k: Kekeli) {
  for (const e of elements) e.opacity(0);
  yield* all(
    ...elements.map((e, i) => (function* () {
      yield* waitFor(0.12 * i);
      const yFinal = e.y();
      const xFinal = e.x();
      if (animation === 'glisse') e.x(xFinal - k.petit * 0.08);
      else if (animation === 'zoom') e.scale(0.86);
      else if (animation === 'apparition') e.y(yFinal + k.petit * 0.04);
      yield* all(e.opacity(1, 0.45, easeOutCubic), e.x(xFinal, 0.45, easeOutCubic), e.y(yFinal, 0.45, easeOutCubic), e.scale(1, 0.45, easeOutCubic));
    })()),
  );
}

/** Une scène : le fond, un voile pour la lecture, les textes ; fondu enchaîné avec la scène précédente. */
export function* scene(k: Kekeli, s: Scene) {
  const groupe = new Node({ opacity: 0 });
  k.scenes.add(groupe);
  const media = fond(k, s.media, groupe);
  const avecTexte = Boolean(s.titre || s.texte || s.prix);
  if (s.media && avecTexte) {
    const centre = centreTexte(k, s.disposition);
    groupe.add(new Rect({
      width: k.L,
      height: k.H,
      fill: new Gradient({ type: 'linear', from: [0, -k.H / 2], to: [0, k.H / 2], stops: centre < -k.H * 0.1
        ? [{ offset: 0, color: 'rgba(0,0,0,0.7)' }, { offset: 0.55, color: 'rgba(0,0,0,0.15)' }, { offset: 1, color: 'rgba(0,0,0,0.35)' }]
        : [{ offset: 0, color: 'rgba(0,0,0,0.25)' }, { offset: 0.45, color: 'rgba(0,0,0,0.55)' }, { offset: 1, color: 'rgba(0,0,0,0.7)' }] }),
    }));
  }
  const elements = avecTexte ? blocTexte(k, groupe, s, centreTexte(k, s.disposition), k.H * (k.vertical ? 0.36 : 0.5)) : [];
  const precedent = k.precedent;
  k.precedent = groupe;
  const duree = Math.max(0.8, s.duree);
  yield* all(
    // Mouvement lent du fond pendant toute la scène.
    s.media?.type === 'image'
      ? (s.animation === 'glisse' ? media.x(k.petit * 0.04, duree, linear) : media.scale(1.08, duree, linear))
      : waitFor(0),
    (function* () {
      yield* groupe.opacity(1, 0.35, easeInOutCubic);
      if (precedent) precedent.remove();
      yield* entree(elements, s.animation, k);
      yield* waitFor(Math.max(0, duree - 0.35 - 0.45 - 0.12 * Math.max(0, elements.length - 1)));
    })(),
  );
}

/** La fin : l'appel à l'action (commander, s'inscrire), le contact et les moyens de paiement, le logo du client. */
export function* fin(k: Kekeli, f: Fin) {
  const groupe = new Node({ opacity: 0 });
  k.scenes.add(groupe);
  if (f.media) {
    fond(k, f.media, groupe);
    groupe.add(new Rect({ width: k.L, height: k.H, fill: 'rgba(0,0,0,0.6)' }));
  } else {
    groupe.add(new Rect({ width: k.L, height: k.H, fill: k.c.fond }));
    groupe.add(new Circle({ size: k.petit * 1.1, fill: k.c.accent, opacity: 0.16, y: -k.H * 0.05 }));
  }
  const largeur = k.L - k.petit * 0.16;
  const elements: Node[] = [];
  if (f.logo) {
    const echelle = Math.min((k.petit * 0.3) / f.logo.largeur, (k.petit * 0.2) / f.logo.hauteur);
    const logo = new Img({ src: f.logo.fichier, width: f.logo.largeur * echelle, height: f.logo.hauteur * echelle });
    groupe.add(logo);
    elements.push(logo);
  }
  const titre = texte(k, f.titre, k.petit * 0.085, 800, k.c.texte);
  groupe.add(titre);
  ajuster(titre, largeur, k.H * 0.24, k.petit * 0.045);
  elements.push(titre);
  if (f.texte) {
    const t = texte(k, f.texte, k.petit * 0.048, 500, k.c.texte);
    groupe.add(t);
    ajuster(t, largeur, k.H * 0.14, k.petit * 0.03);
    elements.push(t);
  }
  if (f.contact) {
    const t = texte(k, f.contact, k.petit * 0.06, 800, k.c.accent);
    groupe.add(t);
    ajuster(t, largeur, k.H * 0.1, k.petit * 0.035);
    elements.push(t);
  }
  if (f.paiement) {
    const p = pastille(k, f.paiement);
    (p.children()[0] as Txt).fontSize(k.petit * 0.045);
    groupe.add(p);
    ajuster(p.children()[0] as Txt, largeur * 0.9, k.petit * 0.1, k.petit * 0.03);
    elements.push(p);
  }
  const ecart = k.petit * 0.035;
  const hauteurs = elements.map((e) => (e as Rect).height());
  const total = hauteurs.reduce((a, b) => a + b, 0) + ecart * (elements.length - 1);
  let y = -k.H * (k.vertical ? 0.08 : 0.02) - total / 2;
  elements.forEach((e, i) => {
    e.y(y + hauteurs[i] / 2);
    y += hauteurs[i] + ecart;
  });
  const precedent = k.precedent;
  k.precedent = groupe;
  yield* groupe.opacity(1, 0.4, easeInOutCubic);
  if (precedent) precedent.remove();
  yield* entree(elements, 'apparition', k);
  yield* waitFor(Math.max(0.5, f.duree - 0.4 - 0.45 - 0.12 * (elements.length - 1)));
}

/**
 * Les sous-titres : grands, blancs sur un fond sombre arrondi, en bas (au centre bas pour une vidéo verticale, au-dessus
 * des boutons des réseaux sociaux). Ils suivent exactement la voix.
 */
export function* sousTitres(k: Kekeli, lignes: SousTitre[]) {
  if (!k.sousTitres || !lignes.length) return;
  const boite = new Rect({ layout: true, padding: [k.petit * 0.018, k.petit * 0.035], radius: k.petit * 0.025, fill: 'rgba(0,0,0,0.72)', opacity: 0, y: k.vertical ? k.H * 0.2 : k.H / 2 - k.H * 0.13 });
  const t = new Txt({ text: ' ', fontFamily: POLICE, fontWeight: 700, fontSize: k.petit * (k.vertical ? 0.056 : 0.05), lineHeight: '120%', fill: '#FFFFFF', textWrap: true, textAlign: 'center', maxWidth: k.L - k.petit * 0.2 });
  boite.add(t);
  k.haut.add(boite);
  let temps = 0;
  for (const l of lignes) {
    if (l.debut > temps) {
      boite.opacity(0);
      yield* waitFor(l.debut - temps);
    }
    t.text(l.texte);
    t.fontSize(k.petit * (k.vertical ? 0.056 : 0.05));
    ajuster(t, k.L - k.petit * 0.2, k.petit * 0.2, k.petit * 0.034);
    boite.opacity(1);
    yield* waitFor(Math.max(0.05, l.fin - Math.max(temps, l.debut)));
    temps = l.fin;
  }
  boite.opacity(0);
}
