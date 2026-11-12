<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="{{ __('Kekeli, the AI agent that codes for you.') }}" />
<meta name="theme-color" content="#faf8f4" />

<title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{--
    Apparence : « Système » par défaut (Kekeli suit le téléphone ou l'ordinateur). Le choix du compte est donné à Flux
    avant que la page ne s'affiche, dans l'en-tête : jamais de flash de la mauvaise couleur au chargement.
--}}
@auth
    <script>try { localStorage.setItem('flux.appearance', @js(auth()->user()->appearance ?: 'system')) } catch (e) {}</script>
@endauth
@fluxAppearance
