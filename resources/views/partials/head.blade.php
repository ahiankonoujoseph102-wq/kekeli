<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="{{ __('Kekeli, the AI agent that codes for you.') }}" />
<meta name="theme-color" content="#faf8f4" />

<title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
