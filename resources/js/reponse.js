// Envoi instantané et réponse mot par mot, comme Claude et ChatGPT (chantier « vitesse », parties 2 et 3).
// - Au toucher sur « Envoyer » : le message s'affiche tout de suite, la zone de saisie se vide et le bouton devient
//   « Arrêter », sans attendre le serveur. En cas d'échec, le message reste affiché avec « Réessayer ».
// - La réponse s'écrit mot par mot dès que le modèle commence (adresse « reponses », sans file d'attente). Si le
//   premier signe tarde (site ou Runner qui se réveillent), une ligne courte le dit : jamais un écran figé.
// - Les durées vues par le téléphone (seulement des nombres) sont envoyées au site pour la mesure de la vitesse.
// Rien de ce qui est écrit ici n'est gardé dans le navigateur, sauf l'heure du toucher le temps d'ouvrir la page.

import { miseEnFormeProgressive } from './reponse-texte.js';

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
    const zone = document.querySelector('[data-prompt-input]');
    const bouton = document.querySelector('[data-prompt-send]');
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

    store() {
        return window.Alpine.store('saisie');
    },

    init() {
        window.addEventListener('kekeli-arreter', (e) => this.abandonner(e));
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
        // Le site a affiché le vrai message : la bulle provisoire disparaît.
        bulle.remove();
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
        let texte = '';
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
                        if (this.chronos.premier_mot === undefined && this.depart !== null) this.chronos.premier_mot = Math.round(performance.now() - this.depart);
                        texte += e.delta;
                        this.ecrire(zone(), texte);
                    }
                    if (e.type === 'efface') {
                        texte = '';
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
        if (this.depart !== null) this.chronos.fin = Math.round(performance.now() - this.depart);
        this.envoyerChronos(tacheId);
        this.controleur = null;
        await this.$wire.$refresh();
        this.store().enCours = false;
    },

    ecrire(zone, texte) {
        if (!zone) return;
        zone.classList.toggle('hidden', texte === '');
        zone.innerHTML = miseEnFormeProgressive(texte);
        // La réponse arrive : l'état « Réflexion… » de cette demande laisse la place au texte.
        zone.closest('[data-run-status]')?.querySelector('[data-run-working]')?.classList.add('hidden');
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
        this.controleur?.abort();
        if (e?.detail?.appeler && this.tacheId) this.$wire.stop();
        if (!this.tacheId) this.store().enCours = false;
    },
});
