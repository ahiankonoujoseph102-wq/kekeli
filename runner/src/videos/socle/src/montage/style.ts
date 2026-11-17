// Les styles des vidéos « motion design » de Kekeli : un style est un thème interchangeable (couleurs, police, fond,
// rythme des entrées, transitions, sous-titres). Le même plan de montage peut sortir dans les cinq styles.

export type Couleurs = {
  /** Fond profond (haut et bas du dégradé). */
  fond: string;
  fond2: string;
  /** Couleur des mots-clés, des étiquettes et des chiffres. */
  accent: string;
  /** Seconde couleur (marque, décor). */
  accent2: string;
  texte: string;
  /** Texte secondaire (slogan, libellés, mentions). */
  texteDoux: string;
  vert: string;
  rouge: string;
  violet: string;
  carte: string;
  encre: string;
  /** Le texte posé sur la couleur d'accent (étiquettes, boutons, pastilles). */
  surAccent: string;
  /** Surface translucide posée sur le fond (rangées de liste, cases) et son contour. */
  voile: string;
  trait: string;
  /** L'ombre des grands textes (transparente sur un fond clair : jamais de texte sale). */
  ombre: string;
};

/** Le décor de fond : nuit (halos et faisceaux), papier (clair, fines lignes), vitrine (clair et chaud, formes rondes), veillée (chaud et sombre, guillemets), tableau (quadrillage). */
export type Decor = 'nuit' | 'papier' | 'vitrine' | 'veillee' | 'tableau';

export type Style = {
  nom: string;
  couleurs: Couleurs;
  police: string;
  /** Les grands textes en capitales (accroches, tampons, étiquettes). */
  capitales: boolean;
  /** La graisse des grands textes (900 : très gras ; 700 : posé). */
  graisse: number;
  /** Durée d'une transition entre deux plans (secondes). */
  transition: number;
  /** Les entrées « qui claquent » : rebond fort, petit tremblement (0 : calme ; 1 : très énergique). */
  energie: number;
  decor: Decor;
  /** Les sous-titres : contour sombre sur la vidéo, ou bandeau clair arrondi (styles posés). */
  sousTitres: 'contour' | 'bandeau';
  /** Le vouvoiement par défaut (le texte du client décide toujours). */
  vouvoie: boolean;
};

export const STYLES: Record<string, Style> = {
  // « Dynamique » : celui des publicités de Lomé IA School (nuit profonde, or, violet, gros textes qui claquent).
  dynamique: {
    nom: 'dynamique',
    couleurs: {
      fond: '#070a2a',
      fond2: '#2a0f5c',
      accent: '#feca26',
      accent2: '#a177fd',
      texte: '#ffffff',
      texteDoux: 'rgba(255,255,255,0.82)',
      vert: '#22c55e',
      rouge: '#ef4444',
      violet: '#a177fd',
      carte: '#f7f5ff',
      encre: '#141631',
      surAccent: '#141631',
      voile: 'rgba(255,255,255,0.1)',
      trait: 'rgba(255,255,255,0.22)',
      ombre: 'rgba(0,0,0,0.45)',
    },
    police: 'Inter',
    capitales: true,
    graisse: 900,
    transition: 0.22,
    energie: 1,
    decor: 'nuit',
    sousTitres: 'contour',
    vouvoie: false,
  },
  // « Sobre et professionnel » : cabinet, banque, école, entreprise de services. Fond clair, bleu profond, textes
  // posés en minuscules, entrées douces, transitions plus lentes.
  sobre: {
    nom: 'sobre',
    couleurs: {
      fond: '#f7f6f2',
      fond2: '#e8ebf2',
      accent: '#1f4fbf',
      accent2: '#0f766e',
      texte: '#13213c',
      texteDoux: 'rgba(19,33,60,0.72)',
      vert: '#15803d',
      rouge: '#b42318',
      violet: '#5b4bb7',
      carte: '#ffffff',
      encre: '#13213c',
      surAccent: '#ffffff',
      voile: 'rgba(19,33,60,0.05)',
      trait: 'rgba(19,33,60,0.14)',
      ombre: 'rgba(0,0,0,0)',
    },
    police: 'Inter',
    capitales: false,
    graisse: 700,
    transition: 0.4,
    energie: 0.25,
    decor: 'papier',
    sousTitres: 'bandeau',
    vouvoie: true,
  },
  // « Produit et boutique » : vêtements, cosmétiques, restaurant, téléphone. Fond clair et chaud, prix en rouge
  // corail, formes rondes qui flottent, entrées vives.
  produit: {
    nom: 'produit',
    couleurs: {
      fond: '#fff6ec',
      fond2: '#ffe2c7',
      accent: '#e2462a',
      accent2: '#17614f',
      texte: '#22160f',
      texteDoux: 'rgba(34,22,15,0.72)',
      vert: '#16a34a',
      rouge: '#dc2626',
      violet: '#7c3aed',
      carte: '#ffffff',
      encre: '#22160f',
      surAccent: '#ffffff',
      voile: 'rgba(34,22,15,0.05)',
      trait: 'rgba(34,22,15,0.14)',
      ombre: 'rgba(0,0,0,0)',
    },
    police: 'Inter',
    capitales: true,
    graisse: 900,
    transition: 0.25,
    energie: 0.7,
    decor: 'vitrine',
    sousTitres: 'bandeau',
    vouvoie: true,
  },
  // « Témoignage » : la parole d'un client satisfait. Fond chaud et sombre, grands guillemets, citation posée,
  // médaillon rond, rythme lent.
  temoignage: {
    nom: 'temoignage',
    couleurs: {
      fond: '#1a120d',
      fond2: '#3b2518',
      accent: '#f2b35b',
      accent2: '#d9775a',
      texte: '#fff7ec',
      texteDoux: 'rgba(255,247,236,0.8)',
      vert: '#4ade80',
      rouge: '#f87171',
      violet: '#c4a1ff',
      carte: '#fffaf3',
      encre: '#1a120d',
      surAccent: '#1a120d',
      voile: 'rgba(255,247,236,0.08)',
      trait: 'rgba(255,247,236,0.2)',
      ombre: 'rgba(0,0,0,0.4)',
    },
    police: 'Inter',
    capitales: false,
    graisse: 700,
    transition: 0.45,
    energie: 0.3,
    decor: 'veillee',
    sousTitres: 'contour',
    vouvoie: false,
  },
  // « Éducatif » : expliquer, apprendre, une méthode en étapes. Tableau bleu nuit quadrillé, couleur menthe pour les
  // idées, jaune pour les chiffres, étapes numérotées.
  educatif: {
    nom: 'educatif',
    couleurs: {
      fond: '#0d2537',
      fond2: '#123a55',
      accent: '#5eead4',
      accent2: '#fbbf24',
      texte: '#f4fbff',
      texteDoux: 'rgba(244,251,255,0.8)',
      vert: '#4ade80',
      rouge: '#fb7185',
      violet: '#a5b4fc',
      carte: '#f4fbff',
      encre: '#0d2537',
      surAccent: '#0d2537',
      voile: 'rgba(244,251,255,0.08)',
      trait: 'rgba(244,251,255,0.22)',
      ombre: 'rgba(0,0,0,0.35)',
    },
    police: 'Inter',
    capitales: false,
    graisse: 800,
    transition: 0.3,
    energie: 0.5,
    decor: 'tableau',
    sousTitres: 'contour',
    vouvoie: false,
  },
};

/** Le style demandé, avec les couleurs du client (sa mémoire : couleurs de sa marque) par-dessus. */
export function styleDe(nom: string, couleurs: Partial<Couleurs> = {}): Style {
  const base = STYLES[nom] ?? STYLES.dynamique;
  return { ...base, couleurs: { ...base.couleurs, ...couleurs } };
}
