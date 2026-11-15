// Mise en forme d'une réponse pendant qu'elle s'écrit (gras, titres courts, listes, code), comme Claude et ChatGPT.
// Tout le texte du modèle est d'abord échappé : aucun HTML ni lien du modèle n'est jamais interprété. À la fin, la
// page affiche la réponse mise en forme par le site (app/Agent/Markdown.php), qui fait foi.

const echapper = (texte) => String(texte).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

/** Le gras (**…**) et le code (`…`) dans une ligne déjà échappée. */
function enLigne(texte) {
    return texte.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
}

export function miseEnFormeProgressive(texte) {
    const lignes = echapper(texte).split('\n');
    const html = [];
    let liste = null;
    let paragraphe = [];
    let code = null;
    const fermerParagraphe = () => {
        if (paragraphe.length) html.push(`<p>${enLigne(paragraphe.join('<br>'))}</p>`);
        paragraphe = [];
    };
    const fermerListe = () => {
        if (liste) html.push(`<${liste.type}>${liste.elements.map((e) => `<li>${enLigne(e)}</li>`).join('')}</${liste.type}>`);
        liste = null;
    };
    for (const ligne of lignes) {
        if (code !== null) {
            if (/^```/.test(ligne.trim())) {
                html.push(`<pre><code>${code.join('\n')}</code></pre>`);
                code = null;
            } else {
                code.push(ligne);
            }
            continue;
        }
        if (/^```/.test(ligne.trim())) {
            fermerParagraphe();
            fermerListe();
            code = [];
            continue;
        }
        const titre = ligne.match(/^#{1,4}\s+(.*)$/);
        const puce = ligne.match(/^\s*[-*•]\s+(.*)$/);
        const numero = ligne.match(/^\s*\d+[.)]\s+(.*)$/);
        if (titre) {
            fermerParagraphe();
            fermerListe();
            html.push(`<h3>${enLigne(titre[1])}</h3>`);
        } else if (puce || numero) {
            fermerParagraphe();
            const type = puce ? 'ul' : 'ol';
            if (!liste || liste.type !== type) {
                fermerListe();
                liste = { type, elements: [] };
            }
            liste.elements.push((puce ?? numero)[1]);
        } else if (ligne.trim() === '') {
            fermerParagraphe();
            fermerListe();
        } else {
            fermerListe();
            paragraphe.push(ligne);
        }
    }
    if (code !== null) html.push(`<pre><code>${code.join('\n')}</code></pre>`);
    fermerParagraphe();
    fermerListe();
    return html.join('');
}
