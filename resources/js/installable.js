// Site installable : enregistre le service worker (public/sw.js), qui ne garde que la page « hors connexion ».
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(() => {});
    });
}
