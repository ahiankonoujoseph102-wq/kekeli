// Mise en forme d'une réponse pendant qu'elle s'écrit (gras, titres, listes, tableaux, code), comme Claude et ChatGPT.
// Tout le texte du modèle est d'abord échappé : aucun HTML ni lien du modèle n'est jamais interprété. À la fin, la
// page affiche la réponse mise en forme par le site (app/Agent/Markdown.php), qui fait foi : pour qu'aucun bloc ne
// saute à ce moment-là, chaque bloc a déjà ici la même forme que la version finale (titres au même niveau, tableaux
// dans « kekeli-tableau », code dans « kekeli-code » avec sa barre, renvois en pastilles).

const echapper = (texte) => String(texte).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** Le gras (**…**), le code (`…`) et les renvois ([1]) dans une ligne déjà échappée ; un gras pas encore fermé est déjà en gras. */
function enLigne(texte) {
    let html = texte.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    const ouvert = html.lastIndexOf('**');
    if (ouvert !== -1) html = `${html.slice(0, ouvert)}<strong>${html.slice(ouvert + 2)}</strong>`;
    return html.replace(/\[(\d{1,2})\]/g, '<span class="kekeli-renvoi">$1</span>');
}

/** Les cellules d'une ligne de tableau Markdown (« | a | b | »). */
const cellules = (ligne) => ligne.trim().replace(/^\|/, '').replace(/\|$/, '').split('|').map((c) => c.trim());
const estSeparateur = (ligne) => /^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/.test(ligne.trim());

export function miseEnFormeProgressive(texte, { copier = 'Copy' } = {}) {
    const lignes = echapper(texte).split('\n');
    const html = [];
    let liste = null;
    let paragraphe = [];
    let code = null;
    let tableau = null;
    const fermerParagraphe = () => {
        if (paragraphe.length) html.push(`<p>${enLigne(paragraphe.join('<br>'))}</p>`);
        paragraphe = [];
    };
    const fermerListe = () => {
        if (liste) html.push(`<${liste.type}>${liste.elements.map((e) => `<li>${enLigne(e)}</li>`).join('')}</${liste.type}>`);
        liste = null;
    };
    const fermerTableau = () => {
        if (!tableau) return;
        const [entete, ...corps] = tableau;
        const ligneHtml = (c, balise) => `<tr>${c.map((x) => `<${balise}>${enLigne(x)}</${balise}>`).join('')}</tr>`;
        html.push(`<div class="kekeli-tableau"><table><thead>${ligneHtml(entete, 'th')}</thead><tbody>${corps.map((c) => ligneHtml(c, 'td')).join('')}</tbody></table></div>`);
        tableau = null;
    };
    const fermerTout = () => {
        fermerParagraphe();
        fermerListe();
        fermerTableau();
    };
    const blocCode = (langue, contenu) =>
        `<div class="kekeli-code"><div class="kekeli-code-barre"><span>${langue}</span><span class="kekeli-code-copier" aria-hidden="true">${echapper(copier)}</span></div><pre><code>${contenu.join('\n')}</code></pre></div>`;

    for (const ligne of lignes) {
        if (code !== null) {
            if (/^```/.test(ligne.trim())) {
                html.push(blocCode(code.langue, code.lignes));
                code = null;
            } else {
                code.lignes.push(ligne);
            }
            continue;
        }
        const ouverture = ligne.trim().match(/^```([\w+.-]{0,30})/);
        if (ouverture) {
            fermerTout();
            code = { langue: ouverture[1], lignes: [] };
            continue;
        }
        if (/^\s*\|/.test(ligne)) {
            fermerParagraphe();
            fermerListe();
            if (estSeparateur(ligne)) continue;
            (tableau ??= []).push(cellules(ligne));
            continue;
        }
        fermerTableau();
        const titre = ligne.match(/^(#{1,4})\s+(.*)$/);
        const puce = ligne.match(/^\s*[-*•]\s+(.*)$/);
        const numero = ligne.match(/^\s*\d+[.)]\s+(.*)$/);
        if (titre) {
            fermerTout();
            const niveau = titre[1].length;
            html.push(`<h${niveau}>${enLigne(titre[2])}</h${niveau}>`);
        } else if (puce || numero) {
            fermerParagraphe();
            const type = puce ? 'ul' : 'ol';
            if (!liste || liste.type !== type) {
                fermerListe();
                liste = { type, elements: [] };
            }
            liste.elements.push((puce ?? numero)[1]);
        } else if (ligne.trim() === '') {
            fermerTout();
        } else {
            fermerListe();
            paragraphe.push(ligne);
        }
    }
    if (code !== null) html.push(blocCode(code.langue, code.lignes));
    fermerTout();
    return html.join('');
}

/**
 * Combien de caractères montrer à cette image (environ 60 images par seconde), comme l'écriture régulière de Claude :
 * au moins un caractère, et un huitième du texte qui attend, pour rattraper vite un gros morceau (600 caractères
 * arrivés d'un coup sont montrés en moins d'une seconde).
 */
export function pasAffichage(enAttente) {
    if (enAttente <= 0) return 0;
    return Math.min(enAttente, Math.max(1, Math.ceil(enAttente / 8)));
}

/** Le premier mot entier (jusqu'à l'espace qui le suit) : il s'affiche d'un coup, sans attendre. */
export function finPremierMot(texte) {
    const m = String(texte).match(/^\s*\S+\s?/);
    return m ? m[0].length : 0;
}
