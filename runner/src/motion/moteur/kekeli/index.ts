// Kekeli Motion : le point d'entrée. Dans une page, chaque <div data-kekeli-motion="nom"> reçoit l'animation
// enregistrée sous ce nom (KekeliMotion.enregistrer), ou celle écrite dans un <script type="application/json"> à
// l'intérieur du div. Rien d'autre n'est nécessaire : aucune bibliothèque, aucun service extérieur.

import { monter, type Animation, type Options } from './scene.ts';
import { normaliser } from './normaliser.ts';

export { monter, normaliser };

const scenes: Record<string, unknown> = {};
const montees = new WeakSet<Element>();

export function enregistrer(nom: string, scene: unknown): void {
  scenes[nom] = scene;
  if (typeof document !== 'undefined' && document.readyState !== 'loading') monterTout();
}

/** Monte toutes les animations de la page qui ne le sont pas encore. */
export function monterTout(racine: ParentNode = document): Animation[] {
  const faites: Animation[] = [];
  racine.querySelectorAll('[data-kekeli-motion]').forEach((noeud) => {
    if (montees.has(noeud)) return;
    const nom = noeud.getAttribute('data-kekeli-motion') ?? '';
    const ecrite = noeud.querySelector('script[type="application/json"]');
    let scene: unknown = scenes[nom];
    if (!scene && ecrite) {
      try { scene = JSON.parse(ecrite.textContent ?? ''); } catch { scene = null; }
    }
    if (!scene) return;
    montees.add(noeud);
    const options: Options = { statique: noeud.hasAttribute('data-statique') };
    faites.push(monter(noeud, scene, options));
  });
  return faites;
}

declare global {
  interface Window { KekeliMotion?: { monter: typeof monter; monterTout: typeof monterTout; enregistrer: typeof enregistrer; normaliser: typeof normaliser } }
}

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  window.KekeliMotion = { monter, monterTout, enregistrer, normaliser };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => monterTout());
  else monterTout();
}
