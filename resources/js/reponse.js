// Envoi instantané et réponse mot par mot, comme Claude et ChatGPT (chantier « vitesse », parties 2 et 3).
// - Au toucher sur « Envoyer » : le message s'affiche tout de suite, la zone de saisie se vide et le bouton devient
//   « Arrêter », sans attendre le serveur. En cas d'échec, le message reste affiché avec « Réessayer ».
// - La réponse s'écrit mot par mot dès que le modèle commence (adresse « reponses », sans file d'attente). Si le
//   premier signe tarde (site ou Runner qui se réveillent), une ligne courte le dit : jamais un écran figé.
// - Les durées vues par le téléphone (seulement des nombres) sont envoyées au site pour la mesure de la vitesse.
// - Chantier « réponses » (15 novembre 2026) : le texte reçu s'affiche à un rythme régulier, à chaque image (le
//   premier mot d'un coup), sans à-coups ; « Arrêter » coupe l'affichage tout de suite ; le fil suit la réponse tant
//   que le client n'a pas remonté, sinon un petit bouton « ↓ » ramène à la fin (kekeliFil).
// Rien de ce qui est écrit ici n'est gardé dans le navigateur, sauf l'heure du toucher le temps d'ouvrir la page.

import { finPremierMot, miseEnFormeProgressive, pasAffichage } from './reponse-texte.js';

const REVEIL_MS = 3000;
const CLE_TOUCHER = 'kekeli-toucher';
const CLE_ARRET = 'kekeli-arreter';

document.addEventListener('alpine:init', () => {
    window.Alpine.store('saisie', { enCours: false });
});

/**
 * Envoie un texte comme si le client l'avait écrit (réponses aux questions, « Choisis pour moi », suites proposées) :
 * la zone de saisie est remplie puis le bouton d'envoi touché, pour garder l'envoi instantané et la réponse en direct.
 */
window.kekeliEnvoyerTexte = (texte) => {
    // Dans un projet, la zone porte « data-message-input » ; sur l'accueil, « data-prompt-input ».
    const zone = document.querySelector('[data-message-input]') ?? document.querySelector('[data-prompt-input]');
    const bouton = zone?.closest('form')?.querySelector('[data-prompt-send]') ?? document.querySelector('[data-prompt-send]');
    if (!zone || !bouton || !texte) return;
    zone.value = texte;
    zone.dispatchEvent(new Event('input', { bubbles: true }));
    window.requestAnimationFrame(() => bouton.click());
};

/** L'heure du toucher sur « Envoyer », gardée le temps d'ouvrir la discussion depuis l'accueil. */
window.kekeliNoterToucher = () => {
    try {
        sessionStorage.setItem(CLE_TOUCHER, String(Date.now()));
        sessionStorage.removeItem(CLE_ARRET);
    } catch {
        // Le navigateur refuse le stockage de la session : la mesure du téléphone est simplement absente.
    }
};

/** « Arrêter » touché avant que la discussion ne s'ouvre (depuis l'accueil). */
window.kekeliArreterAvantOuverture = () => {
    try {
        sessionStorage.setItem(CLE_ARRET, '1');
    } catch {
        // Rien à faire de plus : le client pourra toucher « Arrêter » dans la discussion.
    }
};

window.kekeliDiscussion = (options) => ({
    controleur: null,
    tacheId: null,
    depart: null,
    chronos: {},
    arretDemande: false,
    // Affichage régulier : le texte reçu (« cible »), la part déjà montrée, et l'image demandée au navigateur.
    cible: '',
    montre: 0,
    image: null,
    finAffichage: null,

    store() {
        return window.Alpine.store('saisie');
    },

    init() {
        window.addEventListener('kekeli-arreter', (e) => this.abandonner(e));
        // « Régénérer » sous une réponse : l'événement remonte jusqu'à la discussion, qui ouvre la nouvelle réponse (un
        // appel direct depuis le petit bloc des boutons travaillait dans ce bloc et ne trouvait pas la réponse).
        this.$root.addEventListener('kekeli-regenerer', (e) => this.regenerer(e.detail?.tache));
        this.$nextTick(() => {
            // Une réponse qui attend encore (message envoyé depuis l'accueil, ou page rechargée) : elle s'ouvre tout de suite.
            const enAttente = this.$root.querySelector('[data-ouvrir-flux]');
            if (!enAttente) return;
            let toucher = null;
            let arret = false;
            try {
                toucher = Number(sessionStorage.getItem(CLE_TOUCHER)) || null;
                arret = sessionStorage.getItem(CLE_ARRET) === '1';
                sessionStorage.removeItem(CLE_TOUCHER);
                sessionStorage.removeItem(CLE_ARRET);
            } catch {
                // Sans stockage de session : la réponse s'ouvre normalement.
            }
            if (arret) {
                this.$wire.stop();
                return;
            }
            if (toucher && Date.now() - toucher < 60000) {
                this.depart = performance.now() - (Date.now() - toucher);
            }
            this.ouvrir(enAttente.dataset.reponseEnCours);
        });
    },

    /**
     * « Régénérer » (défaut du 16 novembre 2026 : la page n'ouvrait jamais la nouvelle réponse et tournait sans fin) :
     * comme un message envoyé, la nouvelle réponse s'écrit mot par mot à la place de l'ancienne.
     */
    async regenerer(tacheId) {
        if (this.store().enCours) return;
        this.depart = performance.now();
        this.chronos = {};
        this.arretDemande = false;
        this.store().enCours = true;
        let tache = null;
        try {
            tache = await this.$wire.regenerate(tacheId);
        } catch {
            tache = null;
        }
        if (!tache) {
            this.store().enCours = false;
            return;
        }
        window.dispatchEvent(new CustomEvent('kekeli-fil-bas'));
        this.ouvrir(tache);
    },

    /** Le toucher sur « Envoyer ». */
    async envoyer(texteImpose = null) {
        const champ = this.$root.querySelector('textarea[data-message-input]');
        const texte = texteImpose ?? (champ?.value ?? '');
        // Sans texte (seulement des photos ou des fichiers), le site s'en occupe comme avant.
        if (!texte.trim()) {
            this.$wire.send();
            return;
        }
        this.depart = performance.now();
        this.chronos = {};
        this.arretDemande = false;
        const bulle = this.afficherMessage(texte);
        // Comme Claude : un message envoyé ramène toujours le fil à la fin.
        window.dispatchEvent(new CustomEvent('kekeli-fil-bas'));
        this.chronos.affiche = Math.round(performance.now() - this.depart);
        if (champ) {
            champ.value = '';
            champ.dispatchEvent(new Event('input', { bubbles: true }));
        }
        this.store().enCours = true;
        const lent = setTimeout(() => this.etat(bulle, options.messages.reveil), REVEIL_MS);
        let tache = null;
        let coupure = false;
        try {
            tache = await this.$wire.send(texte);
        } catch {
            coupure = true;
        }
        clearTimeout(lent);
        this.chronos.envoye = Math.round(performance.now() - this.depart);
        if (coupure) {
            // Le message n'a pas pu partir (réseau) : il reste affiché, avec « Réessayer ».
            this.store().enCours = false;
            this.echec(bulle, texte);
            return;
        }
        if (!tache) {
            // Le site a refusé le message (crédit épuisé, travail déjà en cours…) : la page affiche la raison ; le texte
            // revient dans la zone de saisie pour ne rien perdre.
            this.store().enCours = false;
            bulle.remove();
            const zone = this.$root.querySelector('textarea[data-message-input]');
            if (zone && zone.value === '') {
                zone.value = texte;
                zone.dispatchEvent(new Event('input', { bubbles: true }));
            }
            return;
        }
        if (this.$root.querySelector(`[data-reponse-en-cours="${tache}"]`) || !bulle.querySelector('[data-reponse-provisoire]')) {
            // Le site a réaffiché la page avec le vrai message : la bulle provisoire disparaît.
            bulle.remove();
        } else {
            // Le site n'a pas réaffiché la page (réponse mot par mot, plus rapide) : la bulle reste, et la réponse
            // s'écrit juste dessous ; la page se réaffiche à la fin de la réponse (défaut corrigé le 15 novembre 2026 :
            // la bulle disparaissait et rien ne s'affichait avant la fin).
            bulle.dataset.tache = tache;
            bulle.querySelector('[data-reponse-provisoire]').dataset.reponseEnCours = tache;
        }
        if (this.arretDemande) {
            this.store().enCours = false;
            this.$wire.stop();
            return;
        }
        this.ouvrir(tache);
    },

    /** La bulle du message, affichée tout de suite (avant la réponse du site). */
    afficherMessage(texte) {
        this.$root.querySelector('[data-discussion-vide]')?.remove();
        const zone = this.$root.querySelector('[data-envois-en-attente]');
        const modele = this.$root.querySelector('template[data-modele-envoi]');
        const bulle = modele.content.firstElementChild.cloneNode(true);
        bulle.querySelector('[data-texte-envoi]').textContent = texte;
        zone.appendChild(bulle);
        return bulle;
    },

    etat(bulle, texte) {
        const ligne = bulle?.querySelector('[data-etat-envoi]');
        if (!ligne) return;
        ligne.textContent = texte;
        ligne.classList.remove('hidden');
    },

    /** L'envoi n'a pas abouti : le message reste, avec « Réessayer ». */
    echec(bulle, texte) {
        bulle.dataset.envoiEchoue = '';
        this.etat(bulle, options.messages.echec);
        const bouton = bulle.querySelector('[data-reessayer]');
        bouton.classList.remove('hidden');
        bulle.querySelector('[data-reflexion-provisoire]')?.remove();
        bouton.onclick = () => {
            bulle.remove();
            this.envoyer(texte);
        };
    },

    /** Ouvre la réponse mot par mot d'une demande. */
    async ouvrir(tacheId) {
        this.tacheId = tacheId;
        this.store().enCours = true;
        this.controleur = new AbortController();
        const zone = () => this.$root.querySelector(`[data-reponse-en-cours="${tacheId}"]`);
        this.zoneReponse = zone;
        this.cible = '';
        this.montre = 0;
        let debut = false;
        const reveil = setTimeout(() => {
            if (!debut) this.ligne(zone(), options.messages.reveil);
        }, REVEIL_MS);
        try {
            const reponse = await fetch(options.adresse.replace('__TACHE__', tacheId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream', 'X-CSRF-TOKEN': options.jeton },
                credentials: 'same-origin',
                body: JSON.stringify({ chronos: this.chronos }),
                signal: this.controleur.signal,
            });
            if (!reponse.ok || !reponse.body) throw new Error('flux indisponible');
            const lecteur = reponse.body.getReader();
            const decodeur = new TextDecoder();
            let tampon = '';
            for (;;) {
                const { value, done } = await lecteur.read();
                if (done) break;
                tampon += decodeur.decode(value, { stream: true });
                let fin;
                while ((fin = tampon.indexOf('\n\n')) !== -1) {
                    const bloc = tampon.slice(0, fin);
                    tampon = tampon.slice(fin + 2);
                    const donnee = bloc.replace(/^data:\s?/, '');
                    let e;
                    try {
                        e = JSON.parse(donnee);
                    } catch {
                        continue;
                    }
                    debut = true;
                    if (e.type === 'debut') this.ligne(zone(), '');
                    if (e.type === 'texte') {
                        this.cible += e.delta;
                        this.avancer();
                    }
                    if (e.type === 'efface') {
                        this.cible = '';
                        this.montre = 0;
                        this.ecrire(zone(), '');
                    }
                    if (e.type === 'etat' && e.etat === 'recherche') this.ligne(zone(), options.messages.recherche);
                    if (e.type === 'outil') this.ligne(zone(), options.messages.travail);
                    if (e.type === 'attente') this.ligne(zone(), options.messages.attente);
                }
            }
        } catch {
            // Flux coupé (réseau, page en arrière-plan) : la réponse se termine quand même côté site et s'affichera.
        } finally {
            clearTimeout(reveil);
        }
        // Le texte déjà reçu finit de s'afficher (une seconde au plus), puis la version du site le remplace.
        if (!this.arretDemande) await this.affichageTermine(1000);
        if (this.depart !== null) this.chronos.fin = Math.round(performance.now() - this.depart);
        this.envoyerChronos(tacheId);
        this.controleur = null;
        await this.$wire.$refresh();
        // La page montre maintenant le vrai message et la réponse : le message provisoire s'efface.
        this.$root.querySelectorAll(`[data-envoi][data-tache="${tacheId}"]`).forEach((envoi) => envoi.remove());
        this.store().enCours = false;
        await this.attendreFin(tacheId);
    },

    /**
     * Le flux s'est arrêté avant la fin de la réponse (réseau coupé, page en arrière-plan) : la réponse se termine côté
     * site (file d'attente en secours). La page regarde toutes les 2 secondes jusqu'à ce qu'elle soit là (3 minutes au
     * plus), puis s'arrête : aucune interrogation qui empêcherait le site de s'endormir.
     */
    async attendreFin(tacheId) {
        for (let essai = 0; essai < 90 && !this.arretDemande; essai++) {
            if (!this.$root.querySelector(`[data-reponse-en-cours="${tacheId}"]`)) return;
            await new Promise((r) => setTimeout(r, 2000));
            await this.$wire.$refresh();
        }
    },

    /** Demande la prochaine image d'affichage, s'il reste du texte à montrer. */
    avancer() {
        if (this.image === null && !this.arretDemande) this.image = window.requestAnimationFrame(() => this.uneImage());
    },

    /** Une image : quelques caractères de plus (le premier mot d'un coup), puis la suivante tant qu'il en reste. */
    uneImage() {
        this.image = null;
        if (this.arretDemande) return;
        const reste = this.cible.length - this.montre;
        if (reste > 0) {
            this.montre += this.montre === 0 ? Math.max(1, finPremierMot(this.cible)) : pasAffichage(reste);
            this.montre = Math.min(this.montre, this.cible.length);
            this.ecrire(this.zoneReponse?.(), this.cible.slice(0, this.montre));
            if (this.chronos.premier_mot === undefined && this.depart !== null) this.chronos.premier_mot = Math.round(performance.now() - this.depart);
        }
        if (this.montre < this.cible.length) this.avancer();
        else if (this.finAffichage) {
            this.finAffichage();
            this.finAffichage = null;
        }
    },

    /** Attend que tout le texte reçu soit affiché ; au-delà de « attente » millisecondes, montre tout d'un coup. */
    affichageTermine(attente) {
        if (this.montre >= this.cible.length) return Promise.resolve();
        return new Promise((fini) => {
            const minuterie = setTimeout(() => {
                this.montre = this.cible.length;
                this.ecrire(this.zoneReponse?.(), this.cible);
                this.finAffichage = null;
                fini();
            }, attente);
            this.finAffichage = () => {
                clearTimeout(minuterie);
                fini();
            };
        });
    },

    ecrire(zone, texte) {
        if (!zone) return;
        zone.classList.toggle('hidden', texte === '');
        zone.innerHTML = miseEnFormeProgressive(texte, { copier: options.messages.copier });
        // La réponse arrive : l'état « Réflexion… » de cette demande laisse la place au texte.
        zone.closest('[data-run-status]')?.querySelector('[data-run-working], [data-reflexion-ligne]')?.classList.add('hidden');
    },

    ligne(zone, texte) {
        const ligne = zone?.parentElement?.querySelector('[data-etat-reponse]');
        if (!ligne) return;
        ligne.textContent = texte;
        ligne.classList.toggle('hidden', texte === '');
    },

    envoyerChronos(tacheId) {
        if (!this.chronos.premier_mot && !this.chronos.fin) return;
        fetch(options.chronos.replace('__TACHE__', tacheId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': options.jeton },
            credentials: 'same-origin',
            body: JSON.stringify({ chronos: this.chronos }),
            keepalive: true,
        }).catch(() => {});
    },

    /**
     * « Arrêter » : la page cesse d'écouter et le site arrête la réponse (action « stop »). Touché avant que le site
     * ait créé la demande, l'arrêt se fait dès qu'elle existe.
     */
    abandonner(e = null) {
        this.arretDemande = true;
        // L'affichage s'arrête tout de suite : le texte pas encore montré ne s'affiche plus.
        if (this.image !== null) window.cancelAnimationFrame(this.image);
        this.image = null;
        this.finAffichage?.();
        this.finAffichage = null;
        this.controleur?.abort();
        if (e?.detail?.appeler && this.tacheId) this.$wire.stop();
        if (!this.tacheId) this.store().enCours = false;
    },
});

/**
 * Le fil de la discussion, comme Claude et ChatGPT : il suit la réponse tant que le client est en bas ; dès qu'il
 * remonte, il n'est plus ramené en bas, et un petit bouton « ↓ » permet de revenir à la fin. Un message envoyé
 * ramène toujours à la fin.
 */
window.kekeliFil = () => ({
    suivre: true,
    bouton: false,
    image: null,

    init() {
        this.bas();
        new MutationObserver(() => {
            if (this.suivre) this.basBientot();
            else this.mesurer();
        }).observe(this.$el, { childList: true, subtree: true, characterData: true });
        this.$el.addEventListener('scroll', () => this.mesurer(), { passive: true });
        window.addEventListener('kekeli-fil-bas', () => {
            this.suivre = true;
            this.basBientot();
        });
    },

    distance() {
        return this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight;
    },

    /** Après un défilement : le client est-il en bas (le fil le suit), ou a-t-il remonté (bouton « ↓ ») ? */
    mesurer() {
        const d = this.distance();
        this.suivre = d < 48;
        this.bouton = d > 160;
    },

    /** Une seule descente par image, même si beaucoup de texte arrive. */
    basBientot() {
        if (this.image !== null) return;
        this.image = window.requestAnimationFrame(() => {
            this.image = null;
            // Le client a pu remonter entre-temps : on ne le ramène jamais en bas.
            if (this.suivre) this.bas();
        });
    },

    bas(lisse = false) {
        const doux = lisse && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.$el.scrollTo({ top: this.$el.scrollHeight, behavior: doux ? 'smooth' : 'auto' });
    },

    /** Le bouton « ↓ ». */
    revenir() {
        this.suivre = true;
        this.bouton = false;
        this.bas(true);
    },
});
