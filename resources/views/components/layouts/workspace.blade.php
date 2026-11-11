{{--
    Gabarit de l'espace de travail d'un projet, en plein écran comme Lovable, Bolt.new ou Claude Code : pas de menu
    latéral, une barre fine en haut (dans la page du projet), la discussion et l'aperçu sur toute la hauteur.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="h-svh overflow-hidden bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-white">
        {{ $slot }}

        @fluxScripts
    </body>
</html>
