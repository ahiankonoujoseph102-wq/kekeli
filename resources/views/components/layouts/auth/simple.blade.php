<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond kekeli-aube min-h-screen antialiased">
        {{-- Pages de connexion, comme Claude et ChatGPT : le logo, une seule colonne étroite, beaucoup de vide. --}}
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 px-4 py-10 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-4">
                {{-- Comme Claude : le symbole seul, puis le titre de la page ; le nom n'est pas répété (lu par les lecteurs d'écran). --}}
                <a href="{{ route('home') }}" class="kekeli-apparition flex flex-col items-center font-medium" wire:navigate aria-label="Kekeli" data-auth-logo>
                    <x-app-logo-icon class="kekeli-symbole-accueil h-16 w-auto" />
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
