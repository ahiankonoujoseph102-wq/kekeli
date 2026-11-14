<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="{{ __('Kekeli, the AI agent that codes for you.') }}" />
<meta name="theme-color" content="#faf8f4" media="(prefers-color-scheme: light)" />
<meta name="theme-color" content="#171513" media="(prefers-color-scheme: dark)" />

<title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

{{-- Un seul fichier pour le logo : public/logo.svg (favicon.ico et logo.png en sont tirés, scripts/logo.js). --}}
<link rel="icon" href="/logo.svg" type="image/svg+xml" />
<link rel="icon" href="/favicon.ico" sizes="32x32" />
<link rel="apple-touch-icon" href="/logo.png" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{--
    Apparence : « Système » par défaut (Kekeli suit le téléphone ou l'ordinateur). Le choix du compte est donné à Flux
    avant que la page ne s'affiche, dans l'en-tête : jamais de flash de la mauvaise couleur au chargement.
--}}
@auth
    <script>try { localStorage.setItem('flux.appearance', @js(auth()->user()->appearance ?: 'system')) } catch (e) {}</script>
@endauth
@fluxAppearance
