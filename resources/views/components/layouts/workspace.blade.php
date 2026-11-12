{{--
    Gabarit de l'espace de travail d'un projet, en plein écran comme Lovable, Bolt.new ou Claude Code : pas de menu
    latéral, une barre fine en haut (dans la page du projet), la discussion et l'aperçu sur toute la hauteur.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond h-svh overflow-hidden antialiased">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
