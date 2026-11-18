// Kekeli Motion : la description d'une scène, écrite par le modèle en JSON (jamais du code), vérifiée par le Runner
// (runner/src/motion/scene.js, scenePropre) puis relue ici une seconde fois avant le dessin.

export type Format = 'paysage' | 'carre' | 'vertical';
export type Decor = 'jardin' | 'rue' | 'village' | 'plage' | 'parc' | 'campagne' | 'marche' | 'ville' | 'vide';
export type Moment = 'aube' | 'matin' | 'midi' | 'apres_midi' | 'coucher' | 'soir' | 'nuit';
export type Genre = 'homme' | 'femme' | 'enfant';
export type Action = 'marche' | 'court' | 'debout' | 'salue';
export type Reaction = 'salue' | 'saute' | 'chapeau' | 'tourne' | 'parle';
export type Peau = 'fonce' | 'moyen' | 'clair';
export type Cheveux = 'courts' | 'longs' | 'tresses' | 'foulard' | 'ras';
export type Accessoire = 'aucun' | 'sac' | 'chapeau' | 'panier' | 'parapluie' | 'cartable';
export type Bas = 'pantalon' | 'jupe' | 'pagne' | 'short';
export type TypeElement =
  | 'arbre' | 'palmier' | 'maison' | 'case' | 'lampadaire' | 'banc' | 'fleurs' | 'buisson'
  | 'nuage' | 'oiseaux' | 'etal' | 'boutique' | 'chat' | 'collines' | 'mer' | 'immeubles' | 'cloture';

export interface Personnage {
  genre: Genre;
  action: Action;
  direction: 'droite' | 'gauche';
  /** Position de départ, de 0 (bord gauche) à 1 (bord droit). */
  x: number;
  /** 0,6 à 1,4 : plus lent ou plus rapide que la marche naturelle. */
  vitesse: number;
  peau: Peau;
  cheveux: Cheveux;
  haut: string;
  bas: Bas;
  couleur_bas: string;
  accessoire: Accessoire;
  couleur_accessoire: string;
  reaction: Reaction;
  /** Ce que dit le personnage quand on le touche (réaction « parle »), 40 caractères au plus. */
  bulle: string;
}

export interface Element {
  type: TypeElement;
  /** Position, de 0 (bord gauche) à 1 (bord droit). */
  x: number;
  /** 0,6 à 1,6 : taille. */
  taille: number;
  /** Couleur principale (toit, feuillage, auvent, fleurs) en #rrggbb, ou vide. */
  couleur: string;
  /** Plan : « fond » (plus petit, derrière) ou « devant ». */
  plan: 'fond' | 'milieu' | 'devant';
  /** Texte d'une enseigne de boutique, donné par le client, 24 caractères au plus. */
  texte: string;
}

export interface Ciel {
  moment: Moment;
  /** Le temps passe tout seul (cycle du jour et de la nuit), ou reste au même moment. */
  anime: boolean;
  /** Durée d'une journée entière en secondes, quand le temps passe (20 à 300). */
  duree_jour: number;
}

export interface Scene {
  version: 1;
  titre: string;
  format: Format;
  decor: Decor;
  ciel: Ciel;
  personnages: Personnage[];
  elements: Element[];
  /** Une seule ligne discrète en bas de la carte, par exemple « Touchez les personnages et le soleil ». */
  indication: string;
  /** Couleur du papier le jour, #rrggbb (par défaut, papier chaud). */
  papier: string;
}

export const DIMENSIONS: Record<Format, [number, number]> = { paysage: [960, 540], carre: [720, 720], vertical: [540, 960] };

/** Moment de la journée → τ de daynight.ts (0 minuit, 0,25 aube, 0,5 midi, 0,75 crépuscule). */
export const MOMENTS: Record<Moment, number> = { aube: 0.255, matin: 0.32, midi: 0.5, apres_midi: 0.62, coucher: 0.735, soir: 0.78, nuit: 0.95 };

export const PEAUX: Record<Peau, string> = { fonce: '#6b4430', moyen: '#a8724f', clair: '#e3bfa0' };
