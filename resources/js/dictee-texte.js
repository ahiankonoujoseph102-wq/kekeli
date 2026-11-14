// Texte de la dictée du navigateur, sans mots répétés (chantier « vitesse », partie 4).
// Chrome sur Android, en écoute continue, renvoie souvent chaque morceau en répétant le début déjà reconnu
// (« bonjour », puis « bonjour je », puis « bonjour je veux ») ou en le faisant chevaucher le précédent ; Chrome sur
// ordinateur renvoie des morceaux distincts. On assemble donc les morceaux en gardant chaque mot une seule fois :
// les morceaux définitifs d'abord, puis le morceau provisoire (encore en cours), qui remplace l'ancien à chaque fois.

const normaliser = (mot) => mot.toLocaleLowerCase('fr').replace(/[.,;:!?…«»"']/g, '');
const mots = (texte) => String(texte || '').replace(/\s+/g, ' ').trim().split(' ').filter(Boolean);

/** Ajoute un morceau au texte déjà assemblé, sans répéter le début commun ni le chevauchement. */
export function fusionner(texte, morceau) {
    const avant = mots(texte);
    const nouveau = mots(morceau);
    if (!nouveau.length) return avant.join(' ');
    if (!avant.length) return nouveau.join(' ');
    const a = avant.map(normaliser);
    const n = nouveau.map(normaliser);
    // Le morceau reprend tout le texte depuis le début (Android) : il le remplace.
    if (n.length >= a.length && a.every((m, i) => m === n[i])) return nouveau.join(' ');
    // Le morceau est déjà contenu à la fin du texte : rien à ajouter.
    if (n.length <= a.length && n.every((m, i) => m === a[a.length - n.length + i])) return avant.join(' ');
    // Chevauchement : la fin du texte est le début du morceau (au moins 2 mots, pour ne pas manger un mot répété exprès).
    for (let k = Math.min(a.length, n.length - 1); k >= 2; k--) {
        if (a.slice(a.length - k).every((m, i) => m === n[i])) return [...avant, ...nouveau.slice(k)].join(' ');
    }
    return [...avant, ...nouveau].join(' ');
}

/**
 * Le texte d'un événement « result » de la reconnaissance vocale.
 *
 * @param {ArrayLike<{isFinal: boolean, 0: {transcript: string}}>} resultats  evenement.results
 */
export function texteDicte(resultats) {
    let definitif = '';
    let provisoire = '';
    for (let i = 0; i < resultats.length; i++) {
        const r = resultats[i];
        const morceau = r?.[0]?.transcript ?? '';
        if (r?.isFinal) definitif = fusionner(definitif, morceau);
        else provisoire = fusionner(provisoire, morceau);
    }
    return fusionner(definitif, provisoire);
}
