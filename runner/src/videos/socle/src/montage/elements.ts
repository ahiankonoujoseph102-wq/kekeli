// Les composants animés de Kekeli : un par type de plan. Chacun construit ses éléments dans le groupe du plan et rend
// son animation, dont les instants viennent de la voix (« p.temps », en secondes depuis le début du plan). Aucune
// animation ne dépasse la durée du plan. Tout reste dans la zone sûre (outils.ts).
import { Circle, Img, Line, Node, Path, Rect, Txt } from '@revideo/2d';
import { all, easeInOutCubic, easeOutBack, easeOutCubic, tween, waitFor } from '@revideo/core';
import type { ThreadGenerator } from '@revideo/core';
import { a, battement, carte, claque, coche, glisse, icone, ligne, pastille, pop, texteRiche } from './outils';
import type { Ctx } from './outils';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Plan = any;
type Composant = (c: Ctx, p: Plan, g: Node) => ThreadGenerator;

/** Place des éléments les uns sous les autres, centrés autour de « centre ». */
function empiler(elements: Node[], centre: number, ecart: number) {
  const hauteurs = elements.map((e) => (e as Rect).height?.() ?? 0);
  const total = hauteurs.reduce((s, h) => s + h, 0) + ecart * Math.max(0, elements.length - 1);
  let y = centre - total / 2;
  elements.forEach((e, i) => {
    e.y(y + hauteurs[i] / 2);
    y += hauteurs[i] + ecart;
  });
}

const couleurNommee = (c: Ctx, nom?: string, defaut = c.style.couleurs.accent) => (nom ? (c.style.couleurs as Record<string, string>)[nom] ?? defaut : defaut);

/** Un titre de plan en haut de la zone (capitales, mots-clés en couleur), qui glisse en arrivant. */
function titreHaut(c: Ctx, g: Node, contenu: string | undefined, cles: string[] = []): Rect | null {
  if (!contenu) return null;
  const t = texteRiche(c, g, contenu, { taille: c.u * 0.09, capitales: c.style.capitales, cles, hauteur: c.zone.hauteur * 0.18 });
  t.x(c.zone.x);
  t.y(c.zone.haut + t.height() / 2);
  return t;
}

// --- Accroche et textes -------------------------------------------------------------------------------------------

const accroche: Composant = (c, p, g) => {
  const elements: Node[] = [];
  let ic: Node | null = null;
  if (p.icone) {
    ic = icone(c, p.icone, c.u * 0.16, c.style.couleurs.accent, 2);
    g.add(ic);
    elements.push(new Rect({ width: c.u * 0.16, height: c.u * 0.16 }));
  }
  const sur = p.surtitre ? texteRiche(c, g, p.surtitre, { taille: c.u * 0.07, graisse: 800, capitales: c.style.capitales, hauteur: c.zone.hauteur * 0.14 }) : null;
  const t = texteRiche(c, g, p.texte ?? '', { taille: c.u * 0.155, capitales: c.style.capitales, cles: p.cles, hauteur: c.zone.hauteur * 0.6 });
  const pile = [...(sur ? [sur] : []), t];
  empiler([...elements, ...pile], c.zone.y - c.zone.hauteur * 0.05, c.u * 0.04);
  if (ic && elements[0]) {
    ic.x(c.zone.x);
    ic.y(elements[0].y());
  }
  pile.forEach((e) => e.x(c.zone.x));
  // Visible dès l'image 0 (miniature) : seulement un petit coup de poing, puis l'icône qui bouge.
  return all(
    a(0.12, (function* () {
      yield* t.scale(1.06, 0.12, easeOutCubic);
      yield* t.scale(1, 0.18, easeInOutCubic);
    })()),
    ic ? a(0.2, (function* () {
      for (let k = 0; k < Math.floor((p.duree - 0.3) / 0.6); k++) {
        yield* ic!.rotation(k % 2 ? -8 : 8, 0.3, easeInOutCubic);
        yield* ic!.rotation(0, 0.3, easeInOutCubic);
      }
    })()) : waitFor(0),
  );
};

const texte: Composant = (c, p, g) => {
  const sur = p.surtitre ? texteRiche(c, g, p.surtitre, { taille: c.u * 0.07, graisse: 800, capitales: c.style.capitales, hauteur: c.zone.hauteur * 0.14 }) : null;
  const t = texteRiche(c, g, p.texte ?? '', { taille: c.u * 0.14, capitales: c.style.capitales, cles: p.cles, hauteur: c.zone.hauteur * 0.5 });
  const sous = p.sous_texte ? texteRiche(c, g, p.sous_texte, { taille: c.u * 0.06, graisse: 700, cles: p.cles, hauteur: c.zone.hauteur * 0.2 }) : null;
  let ic: Node | null = null;
  const reserve = p.icone ? new Rect({ width: c.u * 0.18, height: c.u * 0.18 }) : null;
  if (p.icone) {
    ic = new Circle({ size: c.u * 0.18, fill: c.style.couleurs.accent });
    ic.add(icone(c, p.icone, c.u * 0.1, c.style.couleurs.encre, 2.4));
    g.add(ic);
  }
  const pile = [reserve, sur, t, sous].filter(Boolean) as Node[];
  empiler(pile, c.zone.y - c.zone.hauteur * 0.03, c.u * 0.035);
  pile.forEach((e) => e.x(c.zone.x));
  if (ic && reserve) {
    ic.x(c.zone.x);
    ic.y(reserve.y());
  }
  for (const e of [sur, t, sous, ic]) e?.opacity(0);
  return all(
    sur ? glisse(sur, 0, -c.u * 0.05, 0.25) : waitFor(0),
    a(0.05, claque(t, c.style.energie)),
    ic ? a(0.15, pop(ic)) : waitFor(0),
    sous ? a(0.35, glisse(sous, 0, c.u * 0.04, 0.3)) : waitFor(0),
  );
};

const tampon: Composant = (c, p, g) => {
  const couleur = couleurNommee(c, p.couleur, c.style.couleurs.rouge);
  const sous = p.sous_texte ? texteRiche(c, g, p.sous_texte, { taille: c.u * 0.07, capitales: c.style.capitales, cles: p.cles, hauteur: c.zone.hauteur * 0.25 }) : null;
  const cadre = new Rect({ layout: true, padding: [c.u * 0.025, c.u * 0.06], radius: c.u * 0.03, stroke: couleur, lineWidth: c.u * 0.014, rotation: -9, fill: 'rgba(0,0,0,0.25)' });
  const mot = new Txt({ text: (p.texte ?? '').toLocaleUpperCase('fr'), fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.15, fill: couleur, letterSpacing: c.u * 0.004 });
  cadre.add(mot);
  g.add(cadre);
  for (let i = 0; i < 40 && cadre.width() > c.zone.largeur * 0.92 && mot.fontSize() > 20; i++) mot.fontSize(Math.floor(mot.fontSize() * 0.93));
  const onde = new Circle({ size: c.u * 0.2, stroke: couleur, lineWidth: c.u * 0.01, opacity: 0 });
  g.add(onde);
  const pile = [sous, cadre].filter(Boolean) as Node[];
  empiler(pile, c.zone.y, c.u * 0.08);
  pile.forEach((e) => e.x(c.zone.x));
  onde.position(cadre.position());
  sous?.opacity(0);
  cadre.opacity(0);
  return all(
    sous ? glisse(sous, 0, -c.u * 0.05, 0.25) : waitFor(0),
    a(sous ? 0.3 : 0.05, (function* () {
      cadre.scale(2.6);
      yield* all(cadre.opacity(1, 0.06), cadre.scale(1, 0.16, easeOutCubic));
      onde.opacity(0.9);
      yield* all(onde.size(c.u * 1.1, 0.4, easeOutCubic), onde.opacity(0, 0.4), c.couche.y(c.u * 0.012, 0.04).to(-c.u * 0.01, 0.05).to(0, 0.05));
    })()),
  );
};

const etiquette: Composant = (c, p, g) => {
  const rang = new Rect({ layout: true, alignItems: 'center', gap: c.u * 0.022 });
  g.add(rang);
  const numero = new Rect({ width: c.u * 0.085, height: c.u * 0.085, radius: c.u * 0.018, fill: c.style.couleurs.texte, layout: true, justifyContent: 'center', alignItems: 'center' });
  numero.add(new Txt({ text: String(p.numero ?? 1), fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.06, fill: c.style.couleurs.encre }));
  rang.add(numero);
  const etiq = new Rect({ layout: true, padding: [c.u * 0.012, c.u * 0.03], radius: c.u * 0.018, fill: c.style.couleurs.accent });
  const mot = new Txt({ text: (p.texte ?? '').toLocaleUpperCase('fr'), fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.075, fill: c.style.couleurs.encre });
  etiq.add(mot);
  rang.add(etiq);
  for (let i = 0; i < 40 && rang.width() > c.zone.largeur && mot.fontSize() > 20; i++) mot.fontSize(Math.floor(mot.fontSize() * 0.93));
  rang.rotation(-2);
  const sous = p.sous_texte ? texteRiche(c, g, p.sous_texte, { taille: c.u * 0.05, graisse: 700, cles: p.cles, hauteur: c.zone.hauteur * 0.14 }) : null;
  const boite = carte(c, g, c.u * 0.42, c.u * 0.42, { rayon: c.u * 0.06 });
  boite.add(icone(c, p.icone ?? 'sparkles', c.u * 0.24, c.style.couleurs.encre, 1.8));
  const pile = [rang, sous, boite].filter(Boolean) as Node[];
  empiler(pile, c.zone.y, c.u * 0.045);
  pile.forEach((e) => e.x(c.zone.x));
  for (const e of [rang, sous, boite]) e?.opacity(0);
  return all(
    glisse(rang, -c.u * 0.3, 0, 0.28),
    sous ? a(0.15, glisse(sous, 0, c.u * 0.03, 0.25)) : waitFor(0),
    a(0.2, (function* () {
      yield* pop(boite, 0.35);
      yield* boite.rotation(4, 0.4, easeInOutCubic);
    })()),
  );
};

// --- Chiffres -----------------------------------------------------------------------------------------------------

const compteur: Composant = (c, p, g) => {
  const titre = p.texte ? texteRiche(c, g, p.texte, { taille: c.u * 0.07, capitales: c.style.capitales, cles: p.cles, hauteur: c.zone.hauteur * 0.25 }) : null;
  const de = Number(p.de ?? 0);
  const vers = Number(p.a ?? 0);
  const decimales = Number.isInteger(vers) && Number.isInteger(de) ? 0 : 1;
  const ecrire = (v: number) => `${p.prefixe ?? ''}${v.toLocaleString('fr-FR', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }).replace(/[\u202f\u00a0]/g, ' ')}${p.suffixe ? ` ${p.suffixe}` : ''}`;
  // La taille est réglée sur le nombre final (le plus large), puis le texte part de la valeur de départ.
  const chiffre = new Txt({ text: ecrire(vers), fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.28, fill: c.style.couleurs.accent, shadowColor: 'rgba(0,0,0,0.5)', shadowBlur: 30 });
  g.add(chiffre);
  for (let i = 0; i < 40 && chiffre.width() > c.zone.largeur * 0.95 && chiffre.fontSize() > 30; i++) chiffre.fontSize(Math.floor(chiffre.fontSize() * 0.93));
  chiffre.text(ecrire(de));
  const ic = p.icone ? icone(c, p.icone, c.u * 0.13, c.style.couleurs.texte, 2) : null;
  if (ic) g.add(ic);
  const reserve = ic ? new Rect({ width: c.u * 0.13, height: c.u * 0.13 }) : null;
  const pile = [reserve, titre, new Rect({ width: 10, height: chiffre.height() })].filter(Boolean) as Node[];
  empiler(pile, c.zone.y, c.u * 0.05);
  chiffre.y(pile[pile.length - 1].y());
  pile.forEach((e) => e.x(c.zone.x));
  chiffre.x(c.zone.x);
  if (ic && reserve) ic.position(reserve.position());
  titre?.opacity(0);
  chiffre.opacity(0);
  ic?.opacity(0);
  const montee = Math.max(0.5, Math.min(1.3, p.duree - 0.5));
  return all(
    titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0),
    ic ? pop(ic) : waitFor(0),
    a(0.15, all(pop(chiffre, 0.3), tween(montee, (x) => chiffre.text(ecrire(de + (vers - de) * easeOutCubic(x)))))),
  );
};

const barre: Composant = (c, p, g) => {
  const titre = titreHaut(c, g, p.titre, p.cles);
  const largeur = c.zone.largeur * 0.9;
  const piste = new Rect({ width: largeur, height: c.u * 0.075, radius: c.u * 0.04, fill: 'rgba(255,255,255,0.15)', x: c.zone.x, y: c.zone.y });
  const plein = new Rect({ width: 0, height: c.u * 0.075, radius: c.u * 0.04, fill: c.style.couleurs.accent, x: c.zone.x - largeur / 2, offsetX: -1, y: c.zone.y });
  g.add(piste);
  g.add(plein);
  const pourcent = new Txt({ text: '0 %', fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.15, fill: c.style.couleurs.texte, x: c.zone.x, y: c.zone.y - c.u * 0.15 });
  g.add(pourcent);
  const sous = p.texte ? texteRiche(c, g, p.texte, { taille: c.u * 0.055, graisse: 700, cles: p.cles, hauteur: c.zone.hauteur * 0.2 }) : null;
  if (sous) {
    sous.x(c.zone.x);
    sous.y(c.zone.y + c.u * 0.08 + sous.height() / 2);
  }
  titre?.opacity(0);
  sous?.opacity(0);
  const duree = Math.max(0.5, Math.min(1.3, p.duree - 0.5));
  return all(
    titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0),
    a(0.15, all(plein.width((largeur * Number(p.valeur ?? 100)) / 100, duree, easeOutCubic), tween(duree, (x) => pourcent.text(`${Math.round(Number(p.valeur ?? 100) * easeOutCubic(x))} %`)))),
    sous ? a(0.4, glisse(sous, 0, c.u * 0.03, 0.25)) : waitFor(0),
  );
};

const tableau: Composant = (c, p, g) => {
  const titre = titreHaut(c, g, p.titre, p.cles);
  const haut = titre ? titre.y() + titre.height() / 2 + c.u * 0.04 : c.zone.haut;
  const hauteur = Math.min(c.zone.bas - haut, c.u * 0.85);
  const fenetre = carte(c, g, c.zone.largeur, hauteur, { fond: c.style.couleurs.encre, rayon: c.u * 0.035 });
  fenetre.x(c.zone.x);
  fenetre.y(haut + hauteur / 2);
  fenetre.stroke('rgba(255,255,255,0.15)');
  fenetre.lineWidth(2);
  const chiffres: { valeur: string; libelle: string }[] = p.chiffres ?? [];
  const parLigne = chiffres.length > 2 ? 2 : chiffres.length;
  const lc = (c.zone.largeur - c.u * 0.08 - c.u * 0.03 * (parLigne - 1)) / Math.max(1, parLigne);
  const hc = c.u * 0.2;
  const cases: Node[] = [];
  chiffres.forEach((ch, i) => {
    const col = i % parLigne;
    const rang = Math.floor(i / parLigne);
    const k = new Rect({ width: lc, height: hc, radius: c.u * 0.025, fill: 'rgba(255,255,255,0.07)', x: -c.zone.largeur / 2 + c.u * 0.04 + lc / 2 + col * (lc + c.u * 0.03), y: -hauteur / 2 + c.u * 0.05 + hc / 2 + rang * (hc + c.u * 0.03) });
    fenetre.add(k);
    const v = new Txt({ text: ch.valeur, fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.08, fill: c.style.couleurs.accent, y: -hc * 0.12 });
    k.add(v);
    for (let n = 0; n < 30 && v.width() > lc * 0.9; n++) v.fontSize(Math.floor(v.fontSize() * 0.93));
    const l = new Txt({ text: ch.libelle, fontFamily: c.style.police, fontWeight: 600, fontSize: c.u * 0.034, fill: 'rgba(255,255,255,0.75)', y: hc * 0.25 });
    k.add(l);
    for (let n = 0; n < 30 && l.width() > lc * 0.9; n++) l.fontSize(Math.floor(l.fontSize() * 0.93));
    k.opacity(0);
    cases.push(k);
  });
  // Un petit graphique en barres qui pousse sous les chiffres.
  const rangs = Math.ceil(chiffres.length / Math.max(1, parLigne));
  const hautGraphique = -hauteur / 2 + c.u * 0.05 + rangs * (hc + c.u * 0.03) + c.u * 0.02;
  const hauteurGraphique = hauteur / 2 - c.u * 0.05 - hautGraphique;
  const barres: Rect[] = [];
  const valeurs = [0.35, 0.5, 0.42, 0.66, 0.78, 0.95];
  if (hauteurGraphique > c.u * 0.1) {
    const lb = (c.zone.largeur - c.u * 0.08) / valeurs.length;
    valeurs.forEach((v, i) => {
      const b = new Rect({ width: lb * 0.62, height: 0, radius: [c.u * 0.012, c.u * 0.012, 0, 0], fill: i === valeurs.length - 1 ? c.style.couleurs.accent : c.style.couleurs.accent2, offsetY: 1, x: -c.zone.largeur / 2 + c.u * 0.04 + lb * (i + 0.5), y: hauteur / 2 - c.u * 0.05 });
      fenetre.add(b);
      barres.push(b);
    });
  }
  titre?.opacity(0);
  fenetre.opacity(0);
  return all(
    titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0),
    glisse(fenetre, 0, c.u * 0.15, 0.3),
    ...cases.map((k, i) => a(0.2 + i * 0.12, pop(k, 0.28))),
    ...barres.map((b, i) => a(0.35 + i * 0.06, b.height(hauteurGraphique * valeurs[i], 0.4, easeOutCubic))),
  );
};

// --- Maquettes : téléphone, navigateur, discussion ------------------------------------------------------------------

/** Les bulles d'une discussion dans un cadre (téléphone ou carte), qui arrivent sur leurs mots. */
function bulles(c: Ctx, p: Plan, cadre: Node, largeur: number, haut: number, echelle: number): ThreadGenerator[] {
  const taches: ThreadGenerator[] = [];
  let y = haut;
  const messages: { de: string; texte: string }[] = p.messages ?? [];
  messages.forEach((m, i) => {
    const moi = m.de === 'moi';
    const b = new Rect({ layout: true, padding: [14 * echelle, 22 * echelle], radius: 26 * echelle, fill: moi ? '#d9fdd3' : '#ffffff', maxWidth: largeur * 0.78, shadowColor: 'rgba(0,0,0,0.12)', shadowBlur: 8 });
    const t = new Txt({ text: m.texte, fontFamily: c.style.police, fontWeight: 600, fontSize: 30 * echelle, fill: '#111827', textWrap: true });
    b.add(t);
    cadre.add(b);
    b.y(y + b.height() / 2);
    b.x(moi ? largeur / 2 - b.width() / 2 - 18 * echelle : -largeur / 2 + b.width() / 2 + 18 * echelle);
    y += b.height() + 14 * echelle;
    b.opacity(0);
    const t0 = p.temps?.[`message${i}`] ?? 0.25 + i * 0.45;
    if (moi) {
      // « En train d'écrire » juste avant la réponse.
      const points = new Rect({ layout: true, gap: 8 * echelle, padding: [16 * echelle, 20 * echelle], radius: 24 * echelle, fill: '#d9fdd3', x: largeur / 2 - 70 * echelle, y: b.y(), opacity: 0 });
      for (let k = 0; k < 3; k++) points.add(new Circle({ size: 12 * echelle, fill: '#6b7280' }));
      cadre.add(points);
      taches.push(a(Math.max(0, t0 - 0.45), (function* () {
        points.opacity(1);
        yield* waitFor(0.4);
        points.opacity(0);
      })()));
    }
    taches.push(a(t0, glisse(b, moi ? 40 * echelle : -40 * echelle, 0, 0.22)));
  });
  return taches;
}

const telephone: Composant = (c, p, g) => {
  const titre = titreHaut(c, g, p.titre, p.cles);
  const haut = titre ? titre.y() + titre.height() / 2 + c.u * 0.04 : c.zone.haut;
  const hauteur = Math.min(c.zone.bas - haut + c.u * 0.06, c.u * 1.05);
  const largeur = hauteur * 0.5;
  const tel = new Rect({ width: largeur, height: hauteur, radius: largeur * 0.13, fill: '#0f1222', stroke: '#2a2f4a', lineWidth: 6, rotation: -6, x: c.zone.x, y: haut + hauteur / 2, shadowColor: 'rgba(0,0,0,0.55)', shadowBlur: 60, shadowOffsetY: 24 });
  g.add(tel);
  const ecran = new Rect({ width: largeur - 26, height: hauteur - 26, radius: largeur * 0.1, fill: '#f3f4f6', clip: true });
  tel.add(ecran);
  const e = largeur / 450;
  const entete = new Rect({ width: largeur, height: 110 * e, y: -(hauteur - 26) / 2 + 55 * e, fill: p.ecran === 'boutique' ? c.style.couleurs.accent2 : '#0e7a54' });
  ecran.add(entete);
  entete.add(new Txt({ text: p.entete ?? (p.ecran === 'boutique' ? 'Ma boutique' : 'Clients'), fontFamily: c.style.police, fontWeight: 800, fontSize: 32 * e, fill: '#ffffff', y: 12 * e }));
  const taches: ThreadGenerator[] = [];
  const hautContenu = -(hauteur - 26) / 2 + 130 * e;
  if (p.ecran === 'boutique') {
    const articles: { nom: string; prix: string; icone?: string }[] = p.articles ?? [];
    const lc = (largeur - 26 - 48 * e) / 2;
    articles.forEach((ar, i) => {
      const k = new Rect({ width: lc, height: lc * 1.25, radius: 22 * e, fill: '#ffffff', x: (i % 2 ? 1 : -1) * (lc / 2 + 8 * e), y: hautContenu + (Math.floor(i / 2) + 0.5) * (lc * 1.25 + 16 * e), shadowColor: 'rgba(0,0,0,0.1)', shadowBlur: 10 });
      ecran.add(k);
      k.add(icone(c, ar.icone ?? 'shopping-bag', lc * 0.38, c.style.couleurs.accent2, 2));
      (k.children()[0] as Node).y(-lc * 0.22);
      k.add(new Txt({ text: ar.nom, fontFamily: c.style.police, fontWeight: 700, fontSize: 24 * e, fill: '#111827', y: lc * 0.2 }));
      k.add(new Txt({ text: ar.prix, fontFamily: c.style.police, fontWeight: 800, fontSize: 24 * e, fill: c.style.couleurs.accent2, y: lc * 0.38 }));
      k.opacity(0);
      taches.push(a(0.3 + i * 0.15, pop(k, 0.28)));
    });
  } else if (p.ecran === 'discussion') {
    taches.push(...bulles(c, p, ecran, largeur - 26, hautContenu, e));
  } else {
    const messages: { texte: string }[] = p.messages ?? [];
    messages.forEach((m, i) => {
      const r = new Rect({ width: largeur - 70 * e, height: 70 * e, radius: 16 * e, fill: '#ffffff', y: hautContenu + 40 * e + i * 84 * e });
      ecran.add(r);
      const k = coche(c, 40 * e);
      k.noeud.x(-(largeur - 70 * e) / 2 + 40 * e);
      r.add(k.noeud);
      r.add(new Txt({ text: m.texte, fontFamily: c.style.police, fontWeight: 700, fontSize: 26 * e, fill: '#111827', x: 20 * e }));
      r.opacity(0);
      taches.push(a(0.25 + i * 0.3, all(glisse(r, 60 * e, 0, 0.22), a(0.2, k.cocher()))));
    });
  }
  titre?.opacity(0);
  tel.opacity(0);
  return all(
    titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0),
    (function* () {
      yield* glisse(tel, 0, c.u * 0.5, 0.35);
      yield* tel.rotation(-3, Math.max(0.2, p.duree - 0.5), easeInOutCubic);
    })(),
    ...taches,
  );
};

const discussion: Composant = (c, p, g) => {
  const titre = titreHaut(c, g, p.titre, p.cles);
  const haut = titre ? titre.y() + titre.height() / 2 + c.u * 0.04 : c.zone.haut;
  const hauteur = Math.min(c.zone.bas - haut, c.u * 0.9);
  const fond = carte(c, g, c.zone.largeur, hauteur, { fond: '#efeae2' });
  fond.x(c.zone.x);
  fond.y(haut + hauteur / 2);
  const e = c.zone.largeur / 620;
  const entete = new Rect({ width: c.zone.largeur, height: 100 * e, y: -hauteur / 2 + 50 * e, fill: '#0e7a54' });
  fond.add(entete);
  // Une simple bulle verte comme avatar : jamais le logo d'une application.
  entete.add(new Circle({ size: 56 * e, fill: '#25a35a', x: -c.zone.largeur / 2 + 56 * e }));
  entete.add(new Txt({ text: p.entete ?? 'Clients', fontFamily: c.style.police, fontWeight: 800, fontSize: 30 * e, fill: '#ffffff', x: -c.zone.largeur / 2 + 100 * e, offsetX: -1 }));
  const taches = bulles(c, p, fond, c.zone.largeur, -hauteur / 2 + 120 * e, e);
  titre?.opacity(0);
  fond.opacity(0);
  return all(titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0), glisse(fond, 0, c.u * 0.12, 0.3), ...taches);
};

const navigateur: Composant = (c, p, g) => {
  const titre = titreHaut(c, g, p.titre, p.cles);
  const haut = titre ? titre.y() + titre.height() / 2 + c.u * 0.04 : c.zone.haut;
  const hauteur = Math.min(c.zone.bas - haut, c.u * 0.8);
  const largeur = c.zone.largeur;
  const fen = carte(c, g, largeur, hauteur, { fond: '#ffffff', rayon: c.u * 0.03 });
  fen.x(c.zone.x);
  fen.y(haut + hauteur / 2);
  const e = largeur / 900;
  const barre = new Rect({ width: largeur, height: 70 * e, y: -hauteur / 2 + 35 * e, fill: '#e5e7eb' });
  fen.add(barre);
  ['#ef4444', '#f59e0b', '#22c55e'].forEach((couleur, i) => barre.add(new Circle({ size: 18 * e, fill: couleur, x: -largeur / 2 + 32 * e + i * 28 * e })));
  const adresse = new Rect({ width: largeur * 0.6, height: 40 * e, radius: 20 * e, fill: '#ffffff', x: 40 * e });
  barre.add(adresse);
  adresse.add(new Txt({ text: p.adresse ?? 'monsite.tg', fontFamily: c.style.police, fontWeight: 500, fontSize: 22 * e, fill: '#4b5563' }));
  // Le site d'exemple : en-tête avec le nom, grand bandeau coloré, bouton.
  const site = new Node({ y: 35 * e });
  fen.add(site);
  site.add(new Txt({ text: p.nom ?? 'Mon entreprise', fontFamily: c.style.police, fontWeight: 900, fontSize: 40 * e, fill: c.style.couleurs.encre, y: -hauteur / 2 + 100 * e }));
  const bandeau = new Rect({ width: largeur - 60 * e, height: hauteur * 0.5, radius: 24 * e, fill: c.style.couleurs.accent2, y: -hauteur / 2 + 150 * e + hauteur * 0.25 });
  site.add(bandeau);
  const tb = new Txt({ text: p.texte ?? '', fontFamily: c.style.police, fontWeight: 900, fontSize: 52 * e, fill: '#ffffff', textWrap: true, textAlign: 'center', width: largeur - 120 * e });
  bandeau.add(tb);
  for (let n = 0; n < 30 && tb.height() > hauteur * 0.42; n++) tb.fontSize(Math.floor(tb.fontSize() * 0.93));
  const bouton = new Rect({ layout: true, padding: [16 * e, 40 * e], radius: 40 * e, fill: c.style.couleurs.accent, y: bandeau.y() + hauteur * 0.25 + 70 * e });
  bouton.add(new Txt({ text: p.bouton ?? 'Réserver', fontFamily: c.style.police, fontWeight: 800, fontSize: 34 * e, fill: c.style.couleurs.encre }));
  site.add(bouton);
  // Le curseur va sur le bouton et clique.
  const curseur = new Path({ data: 'M0 0 L0 34 L9 26 L15 40 L21 37 L15 24 L27 24 Z', fill: '#111827', stroke: '#ffffff', lineWidth: 2, scale: 1.6 * e, x: largeur * 0.3, y: hauteur * 0.45, opacity: 0 });
  fen.add(curseur);
  titre?.opacity(0);
  fen.opacity(0);
  bandeau.scale(0.9);
  bouton.opacity(0);
  const clic = Math.max(0.6, p.duree * 0.6);
  return all(
    titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0),
    glisse(fen, 0, c.u * 0.15, 0.3),
    a(0.2, bandeau.scale(1, 0.35, easeOutBack)),
    a(0.35, pop(bouton)),
    a(Math.max(0.4, clic - 0.45), all(curseur.opacity(1, 0.1), curseur.position([bouton.x() + 20 * e, bouton.y() + site.y() + 10 * e], 0.4, easeInOutCubic))),
    a(clic, (function* () {
      yield* bouton.scale(0.9, 0.08);
      yield* bouton.scale(1.08, 0.12, easeOutBack);
      yield* bouton.scale(1, 0.1);
    })()),
  );
};

// --- Liste, pièces, horloge, avant et après -------------------------------------------------------------------------

const liste: Composant = (c, p, g) => {
  const titre = titreHaut(c, g, p.titre, p.cles);
  const elements: { texte: string; icone?: string }[] = p.elements ?? [];
  const hautLigne = Math.min(c.u * 0.16, (c.zone.bas - (titre ? titre.y() + titre.height() / 2 : c.zone.haut) - c.u * 0.06) / Math.max(1, elements.length) - c.u * 0.025);
  const largeur = c.zone.largeur * 0.95;
  const debut = titre ? titre.y() + titre.height() / 2 + c.u * 0.06 : c.zone.haut;
  const taches: ThreadGenerator[] = [];
  elements.forEach((el, i) => {
    const r = new Rect({ width: largeur, height: hautLigne, radius: hautLigne * 0.28, fill: 'rgba(255,255,255,0.1)', stroke: 'rgba(255,255,255,0.22)', lineWidth: 2, x: c.zone.x, y: debut + hautLigne / 2 + i * (hautLigne + c.u * 0.025) });
    g.add(r);
    const k = coche(c, hautLigne * 0.55);
    k.noeud.x(-largeur / 2 + hautLigne * 0.5);
    r.add(k.noeud);
    let x = -largeur / 2 + hautLigne * 0.95;
    if (el.icone) {
      const ic = icone(c, el.icone, hautLigne * 0.42, c.style.couleurs.accent, 2.2);
      ic.x(x + hautLigne * 0.21);
      r.add(ic);
      x += hautLigne * 0.6;
    }
    const t = new Txt({ text: el.texte, fontFamily: c.style.police, fontWeight: 800, fontSize: hautLigne * 0.4, fill: c.style.couleurs.texte, x, offsetX: -1 });
    r.add(t);
    const place = largeur / 2 - x - hautLigne * 0.3;
    for (let n = 0; n < 30 && t.width() > place; n++) t.fontSize(Math.floor(t.fontSize() * 0.93));
    if (t.width() > place + 2) console.log(`ALERTE_TEXTE ${el.texte}`);
    r.opacity(0);
    const t0 = p.temps?.[`element${i}`] ?? 0.2 + i * 0.35;
    taches.push(a(t0, all(glisse(r, c.u * 0.12, 0, 0.22), a(0.22, k.cocher()))));
  });
  titre?.opacity(0);
  return all(titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0), ...taches);
};

const pieces: Composant = (c, p, g) => {
  const t = p.texte ? texteRiche(c, g, p.texte, { taille: c.u * 0.11, capitales: c.style.capitales, cles: p.cles, hauteur: c.zone.hauteur * 0.4 }) : null;
  if (t) {
    t.x(c.zone.x);
    t.y(c.zone.y);
    t.opacity(0);
  }
  const taches: ThreadGenerator[] = [];
  for (let i = 0; i < 24; i++) {
    const taille = c.u * (0.06 + c.hasard() * 0.05);
    const piece = new Circle({ size: taille, fill: '#f6c445', stroke: '#c8901a', lineWidth: taille * 0.1, x: c.zone.gauche + c.hasard() * c.zone.largeur, y: -c.H / 2 - taille, zIndex: -1 });
    piece.add(new Circle({ size: taille * 0.55, stroke: '#e0a92b', lineWidth: taille * 0.07 }));
    g.add(piece);
    const t0 = c.hasard() * Math.max(0.2, p.duree - 0.8);
    const chute = 0.7 + c.hasard() * 0.5;
    taches.push(a(t0, all(piece.y(c.zone.bas + c.u * 0.1, chute, easeInOutCubic), piece.scale([1, 0.3], chute / 2).to([1, 1], chute / 2), piece.opacity(0, chute, (x) => (x > 0.8 ? (x - 0.8) * 5 : 0)))));
  }
  return all(t ? a(0.05, claque(t, c.style.energie)) : waitFor(0), ...taches);
};

const horloge: Composant = (c, p, g) => {
  const taille = c.u * 0.42;
  const cadran = new Circle({ size: taille, fill: '#ffffff', stroke: c.style.couleurs.accent, lineWidth: taille * 0.05, x: c.zone.x, y: c.zone.y - c.zone.hauteur * 0.12 });
  g.add(cadran);
  for (let h = 0; h < 12; h++) {
    const ang = (h * Math.PI) / 6;
    cadran.add(new Line({ points: [[Math.sin(ang) * taille * 0.38, -Math.cos(ang) * taille * 0.38], [Math.sin(ang) * taille * 0.43, -Math.cos(ang) * taille * 0.43]], stroke: c.style.couleurs.encre, lineWidth: taille * 0.02, lineCap: 'round' }));
  }
  const heures = new Line({ points: [[0, 0], [0, -taille * 0.22]], stroke: c.style.couleurs.encre, lineWidth: taille * 0.04, lineCap: 'round' });
  const minutes = new Line({ points: [[0, 0], [0, -taille * 0.34]], stroke: c.style.couleurs.rouge, lineWidth: taille * 0.025, lineCap: 'round' });
  cadran.add(heures);
  cadran.add(minutes);
  cadran.add(new Circle({ size: taille * 0.06, fill: c.style.couleurs.encre }));
  const t = p.texte ? texteRiche(c, g, p.texte, { taille: c.u * 0.1, capitales: c.style.capitales, cles: p.cles, hauteur: c.zone.hauteur * 0.25 }) : null;
  if (t) {
    t.x(c.zone.x);
    t.y(cadran.y() + taille / 2 + c.u * 0.06 + t.height() / 2);
    t.opacity(0);
  }
  cadran.opacity(0);
  return all(
    pop(cadran, 0.3),
    a(0.15, all(minutes.rotation(720, Math.max(0.5, p.duree - 0.3), easeInOutCubic), heures.rotation(60, Math.max(0.5, p.duree - 0.3), easeInOutCubic))),
    t ? a(0.25, claque(t, c.style.energie)) : waitFor(0),
  );
};

const avantApres: Composant = (c, p, g) => {
  const titre = titreHaut(c, g, p.titre, p.cles);
  const haut = titre ? titre.y() + titre.height() / 2 + c.u * 0.05 : c.zone.haut;
  const ecart = c.u * 0.03;
  const largeur = (c.zone.largeur - ecart) / 2;
  const hauteur = Math.min(c.zone.bas - haut, c.u * 0.7);
  const faire = (cote: -1 | 1, etiquetteTexte: string, contenu: string, couleur: string, ic: string) => {
    const k = carte(c, g, largeur, hauteur, { fond: 'rgba(255,255,255,0.08)' });
    k.stroke(couleur);
    k.lineWidth(4);
    k.x(c.zone.x + cote * (largeur / 2 + ecart / 2));
    k.y(haut + hauteur / 2);
    const et = new Rect({ layout: true, padding: [c.u * 0.012, c.u * 0.03], radius: c.u * 0.03, fill: couleur, y: -hauteur / 2 + c.u * 0.07 });
    et.add(new Txt({ text: etiquetteTexte, fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.045, fill: '#ffffff' }));
    k.add(et);
    const rond = new Circle({ size: largeur * 0.4, fill: couleur, y: -hauteur * 0.08 });
    rond.add(icone(c, ic, largeur * 0.22, '#ffffff', 3));
    k.add(rond);
    const t = new Txt({ text: contenu, fontFamily: c.style.police, fontWeight: 800, fontSize: c.u * 0.048, fill: c.style.couleurs.texte, textWrap: true, textAlign: 'center', width: largeur - c.u * 0.06, y: hauteur * 0.28 });
    k.add(t);
    for (let n = 0; n < 30 && t.height() > hauteur * 0.32; n++) t.fontSize(Math.floor(t.fontSize() * 0.93));
    k.opacity(0);
    return k;
  };
  const avant = faire(-1, 'AVANT', p.avant ?? '', c.style.couleurs.rouge, 'x');
  const apres = faire(1, 'APRÈS', p.apres ?? '', c.style.couleurs.vert, 'check');
  titre?.opacity(0);
  return all(
    titre ? glisse(titre, 0, -c.u * 0.04, 0.25) : waitFor(0),
    a(0.1, glisse(avant, -c.u * 0.2, 0, 0.28)),
    a(Math.max(0.4, p.duree * 0.45), glisse(apres, c.u * 0.2, 0, 0.28)),
  );
};

// --- Numéro et carte finale ---------------------------------------------------------------------------------------

const numero: Composant = (c, p, g) => {
  const titre = p.texte ? texteRiche(c, g, p.texte, { taille: c.u * 0.085, capitales: c.style.capitales, couleur: c.style.couleurs.accent, hauteur: c.zone.hauteur * 0.2 }) : null;
  const pastilleContact = new Rect({ layout: true, alignItems: 'center', gap: c.u * 0.015, padding: [c.u * 0.012, c.u * 0.03], radius: c.u * 0.04, fill: c.style.couleurs.vert });
  pastilleContact.add(icone(c, 'message-circle', c.u * 0.045, '#ffffff', 2.4));
  pastilleContact.add(new Txt({ text: p.marque?.bouton || 'Écrivez-nous', fontFamily: c.style.police, fontWeight: 800, fontSize: c.u * 0.04, fill: '#ffffff' }));
  g.add(pastilleContact);
  const groupes: { texte: string }[] = p.groupes ?? [];
  const rangee = new Rect({ layout: true, gap: c.u * 0.035, alignItems: 'center' });
  g.add(rangee);
  const morceaux = groupes.map((gr) => {
    const t = new Txt({ text: gr.texte, fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.19, fill: c.style.couleurs.texte, shadowColor: 'rgba(0,0,0,0.5)', shadowBlur: 24 });
    rangee.add(t);
    return t;
  });
  for (let n = 0; n < 40 && rangee.width() > c.zone.largeur * 0.98; n++) morceaux.forEach((t) => t.fontSize(Math.floor(t.fontSize() * 0.94)));
  const pile = [titre, pastilleContact, rangee].filter(Boolean) as Node[];
  empiler(pile, c.zone.y, c.u * 0.05);
  pile.forEach((e) => e.x(c.zone.x));
  titre?.opacity(0);
  pastilleContact.opacity(0);
  morceaux.forEach((t) => t.opacity(0));
  return all(
    titre ? claque(titre, c.style.energie) : waitFor(0),
    a(0.15, pop(pastilleContact, 0.28)),
    // Chaque groupe de chiffres apparaît au moment où la voix le dit (la place est déjà réservée : rien ne saute).
    ...morceaux.map((t, i) => a(p.temps?.[`groupe${i}`] ?? 0.3 + i * 0.35, (function* () {
      t.opacity(1);
      t.scale(1.6);
      yield* t.scale(1, 0.2, easeOutBack);
    })())),
  );
};

const carteFinale: Composant = (c, p, g) => {
  const m = (p.marque ?? {}) as { nom?: string; slogan?: string; bouton?: string; contact?: string; logo?: { fichier: string; largeur: number; hauteur: number } };
  const elements: Node[] = [];
  if (m.logo) {
    const echelle = Math.min((c.u * 0.28) / m.logo.largeur, (c.u * 0.22) / m.logo.hauteur);
    const logo = new Img({ src: m.logo.fichier, width: m.logo.largeur * echelle, height: m.logo.hauteur * echelle, radius: c.u * 0.03 });
    g.add(logo);
    elements.push(logo);
  } else {
    const initiales = (m.nom ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((x) => x[0]).join('').toLocaleUpperCase('fr');
    const carre = new Rect({ width: c.u * 0.22, height: c.u * 0.22, radius: c.u * 0.05, fill: c.style.couleurs.accent, layout: true, justifyContent: 'center', alignItems: 'center' });
    carre.add(new Txt({ text: initiales || '★', fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.1, fill: c.style.couleurs.encre }));
    g.add(carre);
    elements.push(carre);
  }
  const nom = m.nom ? texteRiche(c, g, m.nom, { taille: c.u * 0.09, cles: p.cles, hauteur: c.zone.hauteur * 0.18 }) : null;
  const slogan = m.slogan ? texteRiche(c, g, m.slogan, { taille: c.u * 0.05, graisse: 700, cles: p.cles, couleur: 'rgba(255,255,255,0.88)', hauteur: c.zone.hauteur * 0.16 }) : null;
  const bouton = new Rect({ layout: true, direction: 'column', alignItems: 'center', padding: [c.u * 0.025, c.u * 0.05], radius: c.u * 0.04, fill: c.style.couleurs.vert, shadowColor: 'rgba(0,0,0,0.45)', shadowBlur: 30, shadowOffsetY: 10 });
  const haut = new Rect({ layout: true, alignItems: 'center', gap: c.u * 0.015 });
  haut.add(icone(c, 'message-circle', c.u * 0.04, '#ffffff', 2.4));
  haut.add(new Txt({ text: (m.bouton ?? 'Écrivez-nous').toLocaleUpperCase('fr'), fontFamily: c.style.police, fontWeight: 800, fontSize: c.u * 0.032, fill: '#ffffff' }));
  bouton.add(haut);
  if (m.contact) bouton.add(new Txt({ text: m.contact, fontFamily: c.style.police, fontWeight: 900, fontSize: c.u * 0.07, fill: '#ffffff' }));
  g.add(bouton);
  for (let n = 0; n < 40 && bouton.width() > c.zone.largeur * 0.98; n++) bouton.scale(bouton.scale().x * 0.95);
  // Honnêteté : une voix créée par intelligence artificielle est toujours annoncée (discrète, dans la zone sûre).
  const mention = p.voixIA ? new Txt({ text: 'Voix off créée par intelligence artificielle', fontFamily: c.style.police, fontWeight: 600, fontSize: c.u * 0.026, fill: 'rgba(255,255,255,0.7)' }) : null;
  if (mention) g.add(mention);
  const pile = [...elements, nom, slogan, bouton, mention].filter(Boolean) as Node[];
  empiler(pile, c.zone.y, c.u * 0.04);
  pile.forEach((e) => e.x(c.zone.x));
  pile.forEach((e) => e.opacity(0));
  // Petites étoiles qui scintillent autour.
  const etoiles: ThreadGenerator[] = [];
  for (let i = 0; i < 8; i++) {
    const s = new Path({ data: 'M0 -10 L3 -3 L10 0 L3 3 L0 10 L-3 3 L-10 0 L-3 -3 Z', fill: i % 2 ? c.style.couleurs.accent : c.style.couleurs.accent2, scale: 1 + c.hasard() * 1.5, x: c.zone.gauche + c.hasard() * c.zone.largeur, y: c.zone.haut + c.hasard() * c.zone.hauteur, opacity: 0 });
    g.add(s);
    etoiles.push(a(0.3 + c.hasard() * 1.2, (function* () {
      for (let k = 0; k < 3; k++) {
        yield* s.opacity(1, 0.3);
        yield* s.opacity(0.2, 0.3);
      }
    })()));
  }
  const fin = Math.max(1, p.duree - 0.2);
  return all(
    ...pile.map((e, i) => a(0.08 * i, e === bouton ? pop(e, 0.35) : glisse(e, 0, c.u * 0.04, 0.3))),
    a(0.9, battement(bouton, fin - 1)),
    ...etoiles,
  );
};

export const COMPOSANTS: Record<string, Composant> = {
  accroche,
  texte,
  tampon,
  etiquette,
  compteur,
  liste,
  barre,
  telephone,
  navigateur,
  tableau,
  discussion,
  pieces,
  horloge,
  avant_apres: avantApres,
  numero,
  carte: carteFinale,
};

