// Les sous-titres « mot par mot » : un bloc de 4 mots au plus apparaît, chaque mot s'allume au moment exact où la voix
// le dit avec un petit saut (les mots pas encore dits restent en retrait), les mots-clés et les nombres sont dans la couleur d'accent.
// Ils sont posés dans la bande réservée au bas de la zone sûre (entre le contenu et la limite de 78 % de la hauteur).
// Deux présentations selon le style : contour sombre sur la vidéo (« Dynamique », « Témoignage », « Éducatif ») ou
// bandeau clair arrondi, texte couleur encre (« Sobre », « Produit », sur fond clair).
import { Node, Rect, Txt } from '@revideo/2d';
import { all, any, easeOutBack, easeOutCubic, waitFor } from '@revideo/core';
import type { ThreadGenerator } from '@revideo/core';
import type { Ctx } from './outils';

export type MotSousTitre = { texte: string; debut: number; fin: number; cle?: boolean };
export type BlocSousTitre = { debut: number; fin: number; mots: MotSousTitre[] };

/** Les mots pas encore dits : visibles mais en retrait (on lit la phrase en avance, comme sur TikTok). */
const RETRAIT = 0.42;

function* jouerBloc(c: Ctx, parent: Node, b: BlocSousTitre): ThreadGenerator {
  // Un bloc sans durée (deux blocs au même instant) n'est jamais montré : jamais deux blocs l'un sur l'autre.
  if (b.fin - b.debut < 0.05) return;
  if (b.debut > 0) yield* waitFor(b.debut);
  const y = (c.zone.bas + c.zone.basSousTitres) / 2;
  // Une rangée mise en page par Revideo (les mots côte à côte, un écart fixe) : la largeur réelle de chaque mot est
  // mesurée au moment du dessin, jamais deux mots l'un sur l'autre.
  let taille = c.u * 0.075;
  const bandeau = c.style.sousTitres === 'bandeau';
  const rangee = new Rect({
    layout: true, direction: 'row', alignItems: 'center', gap: taille * 0.36, x: c.zone.x, y, opacity: 0, scale: 0.86,
    ...(bandeau ? { fill: c.style.couleurs.carte, radius: taille * 0.45, padding: [taille * 0.22, taille * 0.5], shadowColor: 'rgba(0,0,0,0.12)', shadowBlur: 24, shadowOffsetY: 6 } : {}),
  });
  parent.add(rangee);
  const mots = b.mots.map((m) => {
    const t = new Txt({
      text: c.style.capitales ? m.texte.toLocaleUpperCase('fr') : m.texte,
      fontFamily: c.style.police,
      fontWeight: 900,
      fontSize: taille,
      fill: m.cle ? c.style.couleurs.accent : c.style.couleurs.texte,
      // Un contour sombre et une ombre : lisible sur n'importe quel fond, même une photo claire (sauf dans le bandeau).
      ...(bandeau ? {} : { stroke: 'rgba(0,0,0,0.85)', lineWidth: taille * 0.14, strokeFirst: true, shadowColor: 'rgba(0,0,0,0.55)', shadowBlur: 18, shadowOffsetY: 5 }),
      opacity: RETRAIT,
    });
    rangee.add(t);
    return t;
  });
  // Une seule ligne, toujours dans la largeur de la zone sûre : la taille baisse si le bloc est trop large.
  for (let i = 0; i < 30 && rangee.width() > c.zone.largeur * 0.98; i++) {
    taille = Math.floor(taille * 0.93);
    rangee.gap(taille * 0.36);
    if (bandeau) rangee.padding([taille * 0.22, taille * 0.5]);
    mots.forEach((t) => {
      t.fontSize(taille);
      if (!bandeau) t.lineWidth(taille * 0.14);
    });
  }
  if (rangee.width() > c.zone.largeur + 2) console.log(`ALERTE_TEXTE ${b.mots.map((m) => m.texte).join(' ')}`);
  const duree = Math.max(0.1, b.fin - b.debut);
  // « any » : le bloc s'arrête exactement à sa fin, même si le rebond du dernier mot n'est pas fini.
  yield* any(waitFor(duree), all(
    rangee.opacity(1, 0.1, easeOutCubic),
    rangee.scale(1, 0.18, easeOutBack),
    // Chaque mot s'allume sur sa parole, avec un petit rebond.
    ...b.mots.map((m, i) => (function* () {
      const attente = Math.max(0, m.debut - b.debut);
      if (attente > 0) yield* waitFor(attente);
      // Un petit saut vers le haut (jamais un agrandissement, qui recouvrirait les mots voisins).
      const saut = taille * (0.04 + 0.05 * c.style.energie);
      yield* all(mots[i].opacity(1, 0.06), mots[i].y(-saut, 0.08, easeOutCubic));
      yield* mots[i].y(0, 0.14, easeOutCubic);
    })()),
    waitFor(duree),
  ));
  // Coupe nette : le bloc disparaît à l'instant où le suivant arrive (jamais deux blocs l'un sur l'autre).
  rangee.remove();
}

/** Tous les blocs, chacun à son instant, sur une couche au-dessus des plans. */
export function sousTitres(c: Ctx, parent: Node, blocs: BlocSousTitre[]): ThreadGenerator {
  return all(...blocs.map((b) => jouerBloc(c, parent, b)));
}
