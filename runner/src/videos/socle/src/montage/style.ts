// Les styles des vidéos « motion design » de Kekeli : un style est un thème interchangeable (couleurs, police,
// rythme des entrées, transitions, éléments permis). Le même plan de montage peut sortir dans plusieurs styles.

export type Couleurs = {
  /** Fond profond (haut et bas du dégradé). */
  fond: string;
  fond2: string;
  /** Couleur des mots-clés, des étiquettes et des chiffres. */
  accent: string;
  /** Seconde couleur (marque, décor). */
  accent2: string;
  texte: string;
  vert: string;
  rouge: string;
  violet: string;
  carte: string;
  encre: string;
};

export type Style = {
  nom: string;
  couleurs: Couleurs;
  police: string;
  /** Les grands textes en capitales (accroches, tampons, étiquettes). */
  capitales: boolean;
  /** Durée d'une transition entre deux plans (secondes). */
  transition: number;
  /** Les entrées « qui claquent » : rebond fort, petit tremblement. */
  energie: number;
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
      vert: '#22c55e',
      rouge: '#ef4444',
      violet: '#a177fd',
      carte: '#f7f5ff',
      encre: '#141631',
    },
    police: 'Inter',
    capitales: true,
    transition: 0.22,
    energie: 1,
  },
};

/** Le style demandé, avec les couleurs du client (sa mémoire : couleurs de sa marque) par-dessus. */
export function styleDe(nom: string, couleurs: Partial<Couleurs> = {}): Style {
  const base = STYLES[nom] ?? STYLES.dynamique;
  return { ...base, couleurs: { ...base.couleurs, ...couleurs } };
}
