{{--
    Page « hors connexion » du site installable (public/sw.js la garde en cache et l'affiche quand le réseau manque).
    Elle ne contient AUCUNE donnée de compte : seulement le logo, une phrase et le contact de Kekeli. Elle ne charge
    rien d'autre (styles écrits ici, couleurs de App\Support\Design, mode sombre selon le téléphone).
--}}
@php
    $c = \App\Support\Design::LIGHT;
    $d = \App\Support\Design::DARK;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    <meta name="theme-color" content="{{ $c['fond'] }}" media="(prefers-color-scheme: light)" />
    <meta name="theme-color" content="{{ $d['fond'] }}" media="(prefers-color-scheme: dark)" />
    <title>{{ __('No connection') }} · Kekeli</title>
    <style>
        :root { --k-logo-forme: {{ $c['texte'] }}; --k-logo-flamme: {{ $c['accent'] }}; color-scheme: light dark; }
        body { margin: 0; min-height: 100svh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; box-sizing: border-box; background: {{ $c['fond'] }}; color: {{ $c['texte'] }}; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; text-align: center; }
        main { max-width: 26rem; display: flex; flex-direction: column; align-items: center; gap: 18px; }
        svg { width: 56px; height: auto; }
        h1 { margin: 0; font-family: Georgia, serif; font-weight: 400; font-size: 2.1rem; line-height: 1.15; letter-spacing: -0.01em; }
        p { margin: 0; font-size: 1rem; line-height: 1.6; color: {{ $c['texte-doux'] }}; }
        button { appearance: none; border: 0; border-radius: 999px; padding: 11px 22px; font: inherit; font-weight: 600; background: {{ $c['bouton'] }}; color: {{ $c['bouton-texte'] }}; cursor: pointer; }
        .contact { position: fixed; left: 0; right: 0; bottom: 20px; padding: 0 16px; font-size: 0.75rem; }
        .kekeli-lien { color: inherit; text-decoration: underline; text-underline-offset: 3px; text-decoration-color: {{ $c['bord-fort'] }}; }
        .whitespace-nowrap { white-space: nowrap; }
        @media (prefers-color-scheme: dark) {
            :root { --k-logo-forme: {{ $d['texte'] }}; }
            body { background: {{ $d['fond'] }}; color: {{ $d['texte'] }}; }
            p { color: {{ $d['texte-doux'] }}; }
            button { background: {{ $d['bouton'] }}; color: {{ $d['bouton-texte'] }}; }
            .kekeli-lien { text-decoration-color: {{ $d['bord-fort'] }}; }
        }
    </style>
</head>
<body>
    <main data-offline-page>
        <x-app-logo-icon />
        <h1>{{ __('No connection') }}</h1>
        <p>{{ __('Kekeli needs the Internet to work. Your projects are saved: check your connection, then try again.') }}</p>
        <button type="button" onclick="window.location.reload()">{{ __('Try again') }}</button>
        <p class="contact"><x-contact inline short /></p>
    </main>
</body>
</html>
