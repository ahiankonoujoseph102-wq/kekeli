<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="{{ __('Kekeli, the AI agent that codes for you.') }}" />
<meta name="theme-color" content="#f59e0b" />

<title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

<link rel="icon" href="/favicon.svg" type="image/svg+xml" />

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
