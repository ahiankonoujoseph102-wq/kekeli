// Kekeli Motion : relit une description de scène et la rend sûre. Seules les valeurs prévues passent (listes fermées,
// nombres bornés, couleurs #rrggbb, textes courts) ; tout le reste prend une valeur par défaut. Utilisé par le Runner
// (après le modèle) et par le navigateur (avant le dessin) : une scène modifiée à la main reste toujours dessinable.

import type { Scene, Personnage, Element, Format, Decor, Moment, TypeElement } from './types.ts';

const FORMATS: Format[] = ['paysage', 'carre', 'vertical'];
const DECORS: Decor[] = ['jardin', 'rue', 'village', 'plage', 'parc', 'campagne', 'marche', 'ville', 'vide'];
const MOMENTS: Moment[] = ['aube', 'matin', 'midi', 'apres_midi', 'coucher', 'soir', 'nuit'];
export const TYPES_ELEMENTS: TypeElement[] = ['arbre', 'palmier', 'maison', 'case', 'lampadaire', 'banc', 'fleurs', 'buisson', 'nuage', 'oiseaux', 'etal', 'boutique', 'chat', 'collines', 'mer', 'immeubles', 'cloture'];

/** Au plus 6 personnages et 18 éléments : la scène reste fluide sur un téléphone. */
export const PERSONNAGES_MAX = 6;
export const ELEMENTS_MAX = 18;

type Brut = Record<string, unknown>;

const objet = (v: unknown): Brut => (v && typeof v === 'object' && !Array.isArray(v) ? (v as Brut) : {});
const parmi = <T extends string>(v: unknown, liste: readonly T[], defaut: T): T => (liste.includes(String(v) as T) ? (String(v) as T) : defaut);
const nombre = (v: unknown, min: number, max: number, defaut: number): number => {
  const n = typeof v === 'number' ? v : Number(v);
  return Number.isFinite(n) ? Math.min(max, Math.max(min, n)) : defaut;
};
const couleur = (v: unknown, defaut: string): string => (typeof v === 'string' && /^#[0-9a-fA-F]{6}$/.test(v.trim()) ? v.trim().toLowerCase() : defaut);
/** Un texte court, sans caractère de contrôle ni balise (il est toujours posé comme texte, jamais comme HTML). */
export const texte = (v: unknown, max: number): string =>
  typeof v === 'string' ? v.replace(/[\u0000-\u001f\u007f<>]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, max) : '';

const HAUTS = ['#d9534f', '#2f6f8f', '#f2c94c', '#7fb069', '#e8833a', '#8e6bbf', '#f4f1ea'];
const BAS = ['#3a4a5f', '#5b4636', '#1f2a36', '#e8833a', '#2f6f8f'];

function personnage(v: unknown, i: number): Personnage {
  const p = objet(v);
  const genre = parmi(p.genre, ['homme', 'femme', 'enfant'] as const, i % 2 ? 'femme' : 'homme');
  const bas = parmi(p.bas, ['pantalon', 'jupe', 'pagne', 'short'] as const, genre === 'femme' ? 'jupe' : genre === 'enfant' ? 'short' : 'pantalon');
  return {
    genre,
    action: parmi(p.action, ['marche', 'court', 'debout', 'salue'] as const, 'marche'),
    direction: parmi(p.direction, ['droite', 'gauche'] as const, 'droite'),
    x: nombre(p.x, 0, 1, (0.15 + i * 0.27) % 1),
    vitesse: nombre(p.vitesse, 0.6, 1.4, 1),
    peau: parmi(p.peau, ['fonce', 'moyen', 'clair'] as const, 'fonce'),
    cheveux: parmi(p.cheveux, ['courts', 'longs', 'tresses', 'foulard', 'ras'] as const, genre === 'femme' ? 'tresses' : 'courts'),
    haut: couleur(p.haut, HAUTS[i % HAUTS.length]),
    bas,
    couleur_bas: couleur(p.couleur_bas, BAS[i % BAS.length]),
    accessoire: parmi(p.accessoire, ['aucun', 'sac', 'chapeau', 'panier', 'parapluie', 'cartable'] as const, 'aucun'),
    couleur_accessoire: couleur(p.couleur_accessoire, '#c9a46a'),
    reaction: parmi(p.reaction, ['salue', 'saute', 'chapeau', 'tourne', 'parle'] as const, 'salue'),
    bulle: texte(p.bulle, 40),
  };
}

function element(v: unknown): Element | null {
  const e = objet(v);
  const type = parmi(e.type, TYPES_ELEMENTS, 'arbre');
  if (!TYPES_ELEMENTS.includes(e.type as TypeElement)) return null;
  return {
    type,
    x: nombre(e.x, -0.1, 1.1, 0.5),
    taille: nombre(e.taille, 0.6, 1.6, 1),
    couleur: couleur(e.couleur, ''),
    plan: parmi(e.plan, ['fond', 'milieu', 'devant'] as const, 'milieu'),
    texte: texte(e.texte, 24),
  };
}

/** Le décor par défaut de chaque lieu, quand la description n'en donne aucun. */
const DEFAUTS: Record<Decor, Array<Partial<Element> & { type: TypeElement }>> = {
  jardin: [{ type: 'collines' }, { type: 'arbre', x: 0.14 }, { type: 'buisson', x: 0.36 }, { type: 'arbre', x: 0.62, plan: 'fond' }, { type: 'fleurs', x: 0.84 }, { type: 'fleurs', x: 0.1, plan: 'devant' }, { type: 'nuage', x: 0.3 }, { type: 'oiseaux', x: 0.2 }],
  parc: [{ type: 'arbre', x: 0.1 }, { type: 'banc', x: 0.34 }, { type: 'lampadaire', x: 0.52 }, { type: 'arbre', x: 0.78 }, { type: 'fleurs', x: 0.92, plan: 'devant' }, { type: 'nuage', x: 0.6 }, { type: 'oiseaux', x: 0.4 }],
  rue: [{ type: 'immeubles' }, { type: 'maison', x: 0.16 }, { type: 'lampadaire', x: 0.42 }, { type: 'boutique', x: 0.68 }, { type: 'lampadaire', x: 0.94 }, { type: 'nuage', x: 0.5 }],
  ville: [{ type: 'immeubles' }, { type: 'boutique', x: 0.26 }, { type: 'lampadaire', x: 0.52 }, { type: 'maison', x: 0.8 }, { type: 'nuage', x: 0.2 }],
  village: [{ type: 'collines' }, { type: 'case', x: 0.14 }, { type: 'case', x: 0.4, plan: 'fond' }, { type: 'palmier', x: 0.6 }, { type: 'case', x: 0.84 }, { type: 'oiseaux', x: 0.3 }],
  plage: [{ type: 'mer' }, { type: 'palmier', x: 0.1 }, { type: 'palmier', x: 0.86, plan: 'fond' }, { type: 'nuage', x: 0.4 }, { type: 'oiseaux', x: 0.6 }],
  campagne: [{ type: 'collines' }, { type: 'arbre', x: 0.2, plan: 'fond' }, { type: 'cloture', x: 0.5 }, { type: 'arbre', x: 0.82 }, { type: 'nuage', x: 0.35 }],
  marche: [{ type: 'etal', x: 0.18 }, { type: 'etal', x: 0.5, couleur: '#2f6f8f' }, { type: 'palmier', x: 0.84 }, { type: 'etal', x: 0.34, plan: 'fond', couleur: '#f2c94c' }, { type: 'nuage', x: 0.6 }],
  vide: [],
};

export function normaliser(brute: unknown): Scene {
  const s = objet(brute);
  const ciel = objet(s.ciel);
  const decor = parmi(s.decor, DECORS, 'jardin');
  const personnages = (Array.isArray(s.personnages) ? s.personnages : []).slice(0, PERSONNAGES_MAX).map(personnage);
  let elements = (Array.isArray(s.elements) ? s.elements : []).map(element).filter((e): e is Element => e !== null);
  if (elements.length === 0) elements = DEFAUTS[decor].map((e) => element({ x: 0.5, ...e })!).filter(Boolean);
  // Les grands fonds d'abord (derrière tout le reste), une seule fois chacun.
  const vus = new Set<string>();
  elements = elements.filter((e) => !['collines', 'mer', 'immeubles'].includes(e.type) || (!vus.has(e.type) && vus.add(e.type)));
  elements.sort((a, b) => Number(['collines', 'mer', 'immeubles'].includes(b.type)) - Number(['collines', 'mer', 'immeubles'].includes(a.type)));
  return {
    version: 1,
    titre: texte(s.titre, 80) || 'Animation',
    format: parmi(s.format, FORMATS, 'paysage'),
    decor,
    ciel: {
      moment: parmi(ciel.moment, MOMENTS, 'apres_midi'),
      anime: ciel.anime === undefined ? true : Boolean(ciel.anime),
      duree_jour: nombre(ciel.duree_jour, 20, 300, 60),
    },
    personnages,
    elements: elements.slice(0, ELEMENTS_MAX),
    indication: texte(s.indication, 70),
    papier: couleur(s.papier, '#fbf7ef'),
  };
}
