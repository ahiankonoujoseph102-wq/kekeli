// Dictée vocale de Kekeli, comme le micro de ChatGPT et de Claude : le texte dicté s'écrit dans la zone de saisie et le
// client peut le corriger avant d'envoyer.
// 1. D'abord la dictée du navigateur (reconnaissance vocale de Chrome, gratuite), dans la langue de l'interface.
// 2. Si le navigateur ne la propose pas (ou ne peut pas la joindre) : un court enregistrement envoyé au site, qui le
//    fait écrire par OpenAI (coût compté dans l'énergie). L'enregistrement n'est jamais gardé.
// Le micro n'est demandé qu'au moment où le client touche le bouton ; un refus donne un message clair.

const DUREE_MAX_MS = 120 * 1000;

window.kekeliDictee = (options) => ({
    ecoute: false,
    transcription: false,
    message: '',
    reconnaissance: null,
    enregistreur: null,
    flux: null,
    minuterie: null,

    champ() {
        return document.getElementById(options.champ);
    },

    /** Écrit le texte dans la zone, après ce qui y était déjà, et prévient Livewire. */
    ecrire(base, texte) {
        const champ = this.champ();
        if (!champ) return;
        const propre = String(texte || '').replace(/\s+/g, ' ').trim();
        champ.value = [base, propre].filter(Boolean).join(base && propre ? ' ' : '');
        champ.dispatchEvent(new Event('input', { bubbles: true }));
    },

    basculer() {
        if (this.transcription) return;
        if (this.ecoute) return this.arreter();
        this.message = '';
        const Reconnaissance = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (Reconnaissance) this.dicterNavigateur(Reconnaissance);
        else this.enregistrer();
    },

    dicterNavigateur(Reconnaissance) {
        const r = new Reconnaissance();
        r.lang = options.langue;
        r.interimResults = true;
        r.continuous = true;
        const base = (this.champ()?.value || '').trimEnd();
        r.onresult = (evenement) => {
            let texte = '';
            for (let i = 0; i < evenement.results.length; i++) texte += evenement.results[i][0].transcript + ' ';
            this.ecrire(base, texte);
        };
        r.onerror = (evenement) => {
            this.ecoute = false;
            // Le navigateur ne peut pas joindre son service de dictée : l'enregistrement prend le relais.
            if (evenement.error === 'network' || evenement.error === 'language-not-supported') return this.enregistrer();
            this.erreur(evenement.error);
        };
        r.onend = () => {
            this.ecoute = false;
            this.reconnaissance = null;
            this.champ()?.focus();
        };
        this.reconnaissance = r;
        this.ecoute = true;
        try {
            r.start();
        } catch {
            this.ecoute = false;
            this.enregistrer();
        }
    },

    async enregistrer() {
        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            return this.erreur('indisponible');
        }
        try {
            this.flux = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (e) {
            return this.erreur(e && e.name === 'NotFoundError' ? 'audio-capture' : 'not-allowed');
        }
        const type = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'].find((t) => MediaRecorder.isTypeSupported?.(t)) || '';
        const morceaux = [];
        const debut = Date.now();
        this.enregistreur = new MediaRecorder(this.flux, type ? { mimeType: type } : {});
        this.enregistreur.ondataavailable = (e) => e.data.size && morceaux.push(e.data);
        this.enregistreur.onstop = () => {
            this.flux?.getTracks().forEach((piste) => piste.stop());
            clearTimeout(this.minuterie);
            this.ecoute = false;
            const audio = new Blob(morceaux, { type: (this.enregistreur.mimeType || type || 'audio/webm').split(';')[0] });
            if (audio.size > 0) this.envoyer(audio, (Date.now() - debut) / 1000);
        };
        this.enregistreur.start();
        this.ecoute = true;
        this.minuterie = setTimeout(() => this.arreter(), DUREE_MAX_MS);
    },

    async envoyer(audio, duree) {
        this.transcription = true;
        const donnees = new FormData();
        const extension = audio.type.includes('mp4') ? 'mp4' : audio.type.includes('ogg') ? 'ogg' : 'webm';
        donnees.append('audio', audio, `dictee.${extension}`);
        donnees.append('duree', String(Math.round(duree)));
        try {
            const reponse = await fetch(options.adresse, { method: 'POST', body: donnees, headers: { 'X-CSRF-TOKEN': options.jeton, Accept: 'application/json' }, credentials: 'same-origin' });
            const resultat = await reponse.json().catch(() => ({}));
            if (!reponse.ok) {
                this.message = resultat.erreur || options.messages.echec;
            } else {
                this.ecrire((this.champ()?.value || '').trimEnd(), resultat.texte);
                this.champ()?.focus();
            }
        } catch {
            this.message = options.messages.echec;
        } finally {
            this.transcription = false;
        }
    },

    arreter() {
        if (this.reconnaissance) this.reconnaissance.stop();
        if (this.enregistreur && this.enregistreur.state !== 'inactive') this.enregistreur.stop();
        this.ecoute = false;
    },

    erreur(code) {
        const m = options.messages;
        this.message = {
            'not-allowed': m.refuse,
            'service-not-allowed': m.refuse,
            'audio-capture': m.aucunMicro,
            'no-speech': m.rien,
            aborted: '',
        }[code] ?? m.indisponible;
    },
});
