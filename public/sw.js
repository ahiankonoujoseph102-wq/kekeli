// Service worker de Kekeli (site installable). Règle de sécurité : RIEN de personnel n'est gardé en cache.
// Seules la page « hors connexion » (sans aucune donnée de compte) et le logo sont gardés ; toutes les autres
// demandes vont au réseau, comme sans service worker. Si le réseau manque pendant l'ouverture d'une page,
// la page « hors connexion » s'affiche à la place. Changer VERSION efface l'ancien cache.
const VERSION = 'kekeli-hors-connexion-1';
const PAGE_HORS_CONNEXION = '/hors-connexion';
const A_GARDER = [PAGE_HORS_CONNEXION, '/logo.svg'];

self.addEventListener('install', (evenement) => {
    evenement.waitUntil(
        caches.open(VERSION)
            .then((cache) => cache.addAll(A_GARDER.map((adresse) => new Request(adresse, { cache: 'reload', credentials: 'omit' }))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (evenement) => {
    evenement.waitUntil(
        caches.keys()
            .then((noms) => Promise.all(noms.filter((nom) => nom !== VERSION).map((nom) => caches.delete(nom))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evenement) => {
    // Seulement l'ouverture d'une page (navigation) ; jamais une réponse mise en cache pour une page réelle.
    if (evenement.request.mode !== 'navigate') {
        return;
    }
    evenement.respondWith(
        fetch(evenement.request).catch(() => caches.open(VERSION).then((cache) => cache.match(PAGE_HORS_CONNEXION))),
    );
});
