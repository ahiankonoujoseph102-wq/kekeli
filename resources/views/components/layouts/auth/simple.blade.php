<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="kekeli-fond kekeli-aube min-h-screen antialiased">
        {{-- Pages de connexion, comme Claude et ChatGPT : le logo, une seule colonne étroite, beaucoup de vide. --}}
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 px-4 py-10 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-4">
                {{-- Le logo bien mis en valeur : le symbole, puis le nom, comme sur l'image validée (docs/logo/). --}}
                <a href="{{ route('home') }}" class="kekeli-apparition flex flex-col items-center gap-3 font-medium" wire:navigate data-auth-logo>
                    <x-app-logo-icon class="kekeli-symbole-accueil h-16 w-auto" />
                    <x-app-logo-name class="h-6 w-auto text-zinc-900 dark:text-zinc-50" />
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
