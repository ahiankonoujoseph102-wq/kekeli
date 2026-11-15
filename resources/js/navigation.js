// Des clics instantanés, comme Claude et ChatGPT (chantier « clics instantanés », 15 novembre 2026).
//
// 1. Préchargement : sur un ordinateur, la page part dès que la souris survole un lien (« wire:navigate.hover » de
//    Livewire) ; sur un téléphone, dès que le doigt touche le lien, avant même qu'il se relève.
// 2. Réponse visible tout de suite : au toucher, le lien devient le lien actif, le menu du téléphone se referme et la
//    page qui arrive montre un squelette léger (« Nouvelle discussion » montre déjà sa grande question).
// Rien n'est inventé : la vraie page remplace le squelette dès qu'elle arrive (Livewire).

const LIEN = 'a[wire\\:navigate\\.hover], a[wire\\:navigate]';

// Sur un téléphone, il n'y a pas de survol : le toucher le remplace (Livewire attend 60 millisecondes, puis précharge).
document.addEventListener(
    'touchstart',
    (evenement) => {
        const lien = evenement.target instanceof Element ? evenement.target.closest('a[wire\\:navigate\\.hover]') : null;
        if (lien) lien.dispatchEvent(new MouseEvent('mouseenter'));
    },
    { passive: true, capture: true },
);

let squeletteMontre = null;
let minuterie = null;

/** Le squelette de la page qui arrive : celui de « Nouvelle discussion », ou un squelette léger pour les autres. */
function montrerSquelette(lien) {
    const zone = document.querySelector('[data-flux-main]');
    const modeles = document.querySelector('template[data-squelettes]');
    if (!zone || !modeles) return;
    const nom = lien?.hasAttribute('data-nav-new') || lien?.hasAttribute('data-new-discussion') ? 'discussion' : 'page';
    const modele = modeles.content.querySelector(`[data-squelette="${nom}"]`);
    if (!modele) return;
    retirerSquelette();
    squeletteMontre = modele.cloneNode(true);
    zone.style.position ||= 'relative';
    zone.prepend(squeletteMontre);
}

function retirerSquelette() {
    clearTimeout(minuterie);
    squeletteMontre?.remove();
    squeletteMontre = null;
}

let dernierLien = null;

/** Le toucher est pris en compte : la page en cours s'efface doucement et le squelette arrive (voir montrerSquelette). */
function commencer(lien) {
    if (document.documentElement.dataset.navigation === 'en-cours') return;
    document.documentElement.dataset.navigation = 'en-cours';
    // « Nouvelle discussion » s'affiche aussitôt ; les autres pages, seulement si elles tardent (pas de clignotement).
    if (lien?.hasAttribute('data-nav-new') || lien?.hasAttribute('data-new-discussion')) montrerSquelette(lien);
    else minuterie = setTimeout(() => montrerSquelette(lien), 150);
    // Si la page ne part pas (lien ouvert autrement), tout revient comme avant.
    clearTimeout(commencer.secours);
    commencer.secours = setTimeout(terminer, 10000);
}

function terminer() {
    clearTimeout(commencer.secours);
    delete document.documentElement.dataset.navigation;
    dernierLien = null;
    retirerSquelette();
}

document.addEventListener(
    'click',
    (evenement) => {
        const lien = evenement.target instanceof Element ? evenement.target.closest(LIEN) : null;
        if (!lien || evenement.button > 0 || evenement.metaKey || evenement.ctrlKey || evenement.shiftKey || lien.target === '_blank') return;
        if (new URL(lien.href, location.href).origin !== location.origin) return;
        dernierLien = lien;
        // Le lien touché devient tout de suite le lien actif de la barre (Flux le colore avec « data-current »).
        const barre = lien.closest('[data-navigation]');
        if (barre) {
            barre.querySelectorAll('[data-current]').forEach((actif) => actif.removeAttribute('data-current'));
            lien.setAttribute('data-current', '');
        }
        commencer(lien);
    },
    true,
);

document.addEventListener('livewire:navigate', () => {
    commencer(dernierLien);
    // Sur un téléphone, le menu se referme tout de suite.
    const menu = document.querySelector('[data-flux-sidebar][data-flux-sidebar-on-mobile]:not([data-flux-sidebar-collapsed-mobile])');
    if (menu) document.dispatchEvent(new CustomEvent('flux-sidebar-toggle'));
});

document.addEventListener('livewire:navigated', terminer);
