{{--
    Gabarit de l'espace de travail d'un projet : la même barre de navigation que les autres pages connectées (à gauche
    sur un ordinateur, dans le menu ☰ sur un téléphone), puis le projet sur toute la hauteur, comme Claude ou Manus.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond h-svh overflow-hidden antialiased">
        <x-navigation />

        <div class="min-h-0 min-w-0 [grid-area:main]" data-flux-main>
            {{ $slot }}
        </div>

        @fluxScripts
    </body>
</html>
