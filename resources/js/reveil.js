// Le Runner réveillé pendant que le client écrit (16 novembre 2026) : au premier toucher dans la zone du message, la
// page prévient le site (au plus une fois par minute), qui réveille le Runner endormi. Le premier message n'attend plus
// ce réveil. Aucune donnée n'est envoyée : seulement le signal.
const CLE = 'kekeli-reveil';

function prevenir(boite) {
    const adresse = boite?.dataset.reveil;
    if (!adresse) return;
    try {
        const dernier = Number(sessionStorage.getItem(CLE)) || 0;
        if (Date.now() - dernier < 60000) return;
        sessionStorage.setItem(CLE, String(Date.now()));
    } catch {
        // Sans stockage de session : le site limite lui-même à une fois par minute.
    }
    const jeton = boite.dataset.reveilJeton ?? '';
    fetch(adresse, { method: 'POST', headers: { 'X-CSRF-TOKEN': jeton, Accept: 'application/json' }, credentials: 'same-origin', keepalive: true }).catch(() => {});
}

for (const evenement of ['focusin', 'input']) {
    document.addEventListener(evenement, (e) => {
        const boite = e.target?.closest?.('[data-prompt-box]');
        if (boite && e.target.tagName === 'TEXTAREA') prevenir(boite);
    }, { passive: true });
}
