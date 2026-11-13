// « Enregistrer ma voix », pour la voix off d'une vidéo : le micro du téléphone enregistre le client, puis le fichier
// est joint au message comme un fichier ajouté avec « + » (Kekeli le place dans la vidéo et en écrit les sous-titres).
// Le micro n'est demandé qu'au moment où le client touche « Enregistrer ma voix » ; un refus donne un message clair.

const DUREE_MAX_MS = 180 * 1000;

window.kekeliEnregistreur = (champ) => ({
    enregistre: false,
    secondes: 0,
    message: '',
    enregistreur: null,
    flux: null,
    minuterie: null,
    morceaux: [],

    async commencer() {
        this.message = '';
        if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            this.message = this.$el.dataset.indisponible;
            return;
        }
        try {
            this.flux = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            this.message = this.$el.dataset.refus;
            return;
        }
        const type = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm'].find((t) => MediaRecorder.isTypeSupported?.(t)) ?? '';
        this.morceaux = [];
        this.enregistreur = new MediaRecorder(this.flux, type ? { mimeType: type } : {});
        this.enregistreur.ondataavailable = (e) => e.data.size && this.morceaux.push(e.data);
        this.enregistreur.onstop = () => this.joindre(type);
        this.enregistreur.start();
        this.enregistre = true;
        this.secondes = 0;
        const debut = Date.now();
        this.minuterie = setInterval(() => {
            this.secondes = Math.floor((Date.now() - debut) / 1000);
            if (Date.now() - debut >= DUREE_MAX_MS) this.arreter();
        }, 500);
    },

    arreter() {
        clearInterval(this.minuterie);
        this.enregistre = false;
        if (this.enregistreur?.state === 'recording') this.enregistreur.stop();
        this.flux?.getTracks().forEach((piste) => piste.stop());
    },

    joindre(type) {
        const extension = type.startsWith('audio/mp4') ? 'm4a' : 'webm';
        const fichier = new File(this.morceaux, `ma-voix.${extension}`, { type: type.split(';')[0] || 'audio/webm' });
        this.$wire.uploadMultiple(champ, [fichier], () => {}, () => { this.message = this.$el.dataset.echec; });
    },

    get duree() {
        return `${Math.floor(this.secondes / 60)}:${String(this.secondes % 60).padStart(2, '0')}`;
    },
});
